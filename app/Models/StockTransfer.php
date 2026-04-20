<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class StockTransfer extends Model
{
    protected string $table = 'stock_transfers';

    public function summary(?int $branchId = null, bool $viewAll = false): array
    {
        $sql = 'SELECT COUNT(*) AS total_transfers,
                       COALESCE(SUM(CASE WHEN st.status = "draft" THEN 1 ELSE 0 END), 0) AS draft_count,
                       COALESCE(SUM(CASE WHEN st.status = "in_transit" THEN 1 ELSE 0 END), 0) AS in_transit_count,
                       COALESCE(SUM(CASE WHEN st.status = "completed" THEN 1 ELSE 0 END), 0) AS completed_count
                FROM stock_transfers st
                WHERE 1 = 1';
        $params = [];

        if (!$viewAll && $branchId !== null) {
            $sql .= ' AND (st.source_branch_id = :source_branch_id OR st.destination_branch_id = :destination_branch_id)';
            $params['source_branch_id'] = $branchId;
            $params['destination_branch_id'] = $branchId;
        }

        return $this->fetch($sql, $params) ?? [
            'total_transfers' => 0,
            'draft_count' => 0,
            'in_transit_count' => 0,
            'completed_count' => 0,
        ];
    }

    public function list(array $filters = [], ?int $branchId = null, bool $viewAll = false): array
    {
        $sql = 'SELECT st.*, sb.name AS source_branch_name, db.name AS destination_branch_name,
                       CONCAT(u.first_name, " ", u.last_name) AS created_by_name,
                       COUNT(sti.id) AS item_count,
                       COALESCE(SUM(sti.quantity), 0) AS total_units
                FROM stock_transfers st
                INNER JOIN branches sb ON sb.id = st.source_branch_id
                INNER JOIN branches db ON db.id = st.destination_branch_id
                INNER JOIN users u ON u.id = st.created_by
                LEFT JOIN stock_transfer_items sti ON sti.stock_transfer_id = st.id
                WHERE 1 = 1';
        $params = [];

        if (!$viewAll && $branchId !== null) {
            $sql .= ' AND (st.source_branch_id = :source_branch_id OR st.destination_branch_id = :destination_branch_id)';
            $params['source_branch_id'] = $branchId;
            $params['destination_branch_id'] = $branchId;
        }

        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND st.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (
                st.reference_number LIKE :search
                OR sb.name LIKE :search
                OR db.name LIKE :search
                OR CONCAT(u.first_name, " ", u.last_name) LIKE :search
            )';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (($filters['direction'] ?? '') !== '' && $branchId !== null) {
            if ($filters['direction'] === 'outgoing') {
                $sql .= ' AND st.source_branch_id = :direction_branch_id';
                $params['direction_branch_id'] = $branchId;
            }

            if ($filters['direction'] === 'incoming') {
                $sql .= ' AND st.destination_branch_id = :direction_branch_id';
                $params['direction_branch_id'] = $branchId;
            }
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND DATE(st.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND DATE(st.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' GROUP BY st.id ORDER BY st.created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function findDetailed(int $id): ?array
    {
        $transfer = $this->fetch(
            'SELECT st.*, sb.name AS source_branch_name, db.name AS destination_branch_name,
                    CONCAT(u.first_name, " ", u.last_name) AS created_by_name
             FROM stock_transfers st
             INNER JOIN branches sb ON sb.id = st.source_branch_id
             INNER JOIN branches db ON db.id = st.destination_branch_id
             INNER JOIN users u ON u.id = st.created_by
             WHERE st.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if ($transfer === null) {
            return null;
        }

        $transfer['items'] = $this->fetchAll(
            'SELECT sti.*, p.name AS product_name, p.sku,
                    COALESCE(src.quantity_on_hand, 0) AS source_quantity_on_hand,
                    COALESCE(dest.quantity_on_hand, 0) AS destination_quantity_on_hand
             FROM stock_transfer_items sti
             INNER JOIN products p ON p.id = sti.product_id
             LEFT JOIN inventory src ON src.product_id = sti.product_id AND src.branch_id = :source_branch_id
             LEFT JOIN inventory dest ON dest.product_id = sti.product_id AND dest.branch_id = :destination_branch_id
             WHERE sti.stock_transfer_id = :stock_transfer_id
             ORDER BY sti.id ASC',
            [
                'stock_transfer_id' => $id,
                'source_branch_id' => $transfer['source_branch_id'],
                'destination_branch_id' => $transfer['destination_branch_id'],
            ]
        );

        return $transfer;
    }

    public function createTransfer(array $payload, array $items): int
    {
        $this->validateBranches((int) $payload['source_branch_id'], (int) $payload['destination_branch_id']);
        $compiled = $this->compileItems($items);

        return Database::transaction(function () use ($payload, $compiled): int {
            $status = $payload['status'] === 'in_transit' ? 'in_transit' : 'draft';
            $now = date('Y-m-d H:i:s');
            $transferId = $this->insert([
                'source_branch_id' => $payload['source_branch_id'],
                'destination_branch_id' => $payload['destination_branch_id'],
                'created_by' => $payload['created_by'],
                'reference_number' => $this->generateReference(),
                'status' => $status,
                'notes' => $payload['notes'] !== '' ? $payload['notes'] : null,
                'transfer_date' => $status === 'in_transit' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($compiled as $item) {
                $this->db->prepare(
                    'INSERT INTO stock_transfer_items (stock_transfer_id, product_id, quantity, created_at)
                     VALUES (:stock_transfer_id, :product_id, :quantity, NOW())'
                )->execute([
                    'stock_transfer_id' => $transferId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            if ($status === 'in_transit') {
                $transfer = $this->findDetailed($transferId);
                if ($transfer === null) {
                    throw new HttpException(500, 'Unable to prepare transfer dispatch.');
                }

                $this->dispatchTransferRecord($transfer, (int) $payload['created_by']);
            }

            return $transferId;
        });
    }

    public function updateTransfer(int $id, array $payload, array $items): array
    {
        $transfer = $this->findDetailed($id);
        if ($transfer === null) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        if (!$this->isEditable($transfer)) {
            throw new HttpException(500, 'Only draft stock transfers can be edited.');
        }

        $this->validateBranches((int) $transfer['source_branch_id'], (int) $payload['destination_branch_id']);
        $compiled = $this->compileItems($items);

        return Database::transaction(function () use ($id, $transfer, $payload, $compiled): array {
            $status = $payload['status'] === 'in_transit' ? 'in_transit' : 'draft';
            $timestamp = date('Y-m-d H:i:s');

            $this->updateRecord([
                'destination_branch_id' => $payload['destination_branch_id'],
                'status' => $status,
                'notes' => $payload['notes'] !== '' ? $payload['notes'] : null,
                'transfer_date' => $status === 'in_transit' ? ($transfer['transfer_date'] ?: $timestamp) : null,
                'updated_at' => $timestamp,
            ], 'id = :id', ['id' => $id]);

            $this->execute('DELETE FROM stock_transfer_items WHERE stock_transfer_id = :stock_transfer_id', [
                'stock_transfer_id' => $id,
            ]);

            foreach ($compiled as $item) {
                $this->db->prepare(
                    'INSERT INTO stock_transfer_items (stock_transfer_id, product_id, quantity, created_at)
                     VALUES (:stock_transfer_id, :product_id, :quantity, NOW())'
                )->execute([
                    'stock_transfer_id' => $id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            if ($status === 'in_transit') {
                $updatedTransfer = $this->findDetailed($id);
                if ($updatedTransfer === null) {
                    throw new HttpException(500, 'Unable to prepare transfer dispatch.');
                }

                $this->dispatchTransferRecord($updatedTransfer, (int) $payload['updated_by']);
            }

            $freshTransfer = $this->findDetailed($id);
            if ($freshTransfer === null) {
                throw new HttpException(500, 'Unable to reload the updated stock transfer.');
            }

            return $freshTransfer;
        });
    }

    public function duplicateTransfer(int $id, int $createdBy): int
    {
        $transfer = $this->findDetailed($id);
        if ($transfer === null) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        $items = array_map(static fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (float) $item['quantity'],
        ], $transfer['items']);

        $notes = trim('Duplicated from ' . (string) $transfer['reference_number'] . '.' . (
            trim((string) ($transfer['notes'] ?? '')) !== ''
                ? PHP_EOL . trim((string) $transfer['notes'])
                : ''
        ));

        return $this->createTransfer([
            'source_branch_id' => (int) $transfer['source_branch_id'],
            'destination_branch_id' => (int) $transfer['destination_branch_id'],
            'notes' => $notes,
            'status' => 'draft',
            'created_by' => $createdBy,
        ], $items);
    }

    public function send(int $id, int $userId): void
    {
        $transfer = $this->findDetailed($id);
        if ($transfer === null) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        if ($transfer['status'] !== 'draft') {
            throw new HttpException(500, 'Only draft transfers can be dispatched.');
        }

        Database::transaction(function () use ($transfer, $userId): void {
            $this->dispatchTransferRecord($transfer, $userId);
        });
    }

    public function receive(int $id, int $userId): void
    {
        $transfer = $this->findDetailed($id);
        if ($transfer === null) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        if ($transfer['status'] !== 'in_transit') {
            throw new HttpException(500, 'Only in-transit transfers can be received.');
        }

        Database::transaction(function () use ($transfer, $userId): void {
            $productModel = new Product();
            $sourceBranchName = (string) $transfer['source_branch_name'];

            foreach ($transfer['items'] as $item) {
                $unitCost = $this->transferUnitCost(
                    transferId: (int) $transfer['id'],
                    productId: (int) $item['product_id'],
                    branchId: (int) $transfer['source_branch_id']
                );

                $productModel->adjustInventory(
                    productId: (int) $item['product_id'],
                    branchId: (int) $transfer['destination_branch_id'],
                    quantityChange: (float) $item['quantity'],
                    movementType: 'transfer_in',
                    reason: 'Transfer received from ' . $sourceBranchName,
                    userId: $userId,
                    referenceType: 'stock_transfer',
                    referenceId: (int) $transfer['id'],
                    unitCost: $unitCost
                );
            }

            $this->updateRecord([
                'status' => 'completed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $transfer['id']]);
        });
    }

    public function cancel(int $id, int $userId): void
    {
        $transfer = $this->findDetailed($id);
        if ($transfer === null) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        if (!in_array($transfer['status'], ['draft', 'in_transit'], true)) {
            throw new HttpException(500, 'Only draft or in-transit transfers can be cancelled.');
        }

        Database::transaction(function () use ($transfer, $userId): void {
            if ($transfer['status'] === 'in_transit') {
                $productModel = new Product();

                foreach ($transfer['items'] as $item) {
                    $unitCost = $this->transferUnitCost(
                        transferId: (int) $transfer['id'],
                        productId: (int) $item['product_id'],
                        branchId: (int) $transfer['source_branch_id']
                    );

                    $productModel->adjustInventory(
                        productId: (int) $item['product_id'],
                        branchId: (int) $transfer['source_branch_id'],
                        quantityChange: (float) $item['quantity'],
                        movementType: 'transfer_in',
                        reason: 'Cancelled transfer returned to source branch',
                        userId: $userId,
                        referenceType: 'stock_transfer',
                        referenceId: (int) $transfer['id'],
                        unitCost: $unitCost
                    );
                }
            }

            $this->updateRecord([
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $transfer['id']]);
        });
    }

    public function isEditable(array $transfer): bool
    {
        return (string) ($transfer['status'] ?? '') === 'draft';
    }

    private function dispatchTransferRecord(array $transfer, int $userId): void
    {
        $productModel = new Product();
        $destinationBranchName = (string) $transfer['destination_branch_name'];

        foreach ($transfer['items'] as $item) {
            $inventory = $this->fetch(
                'SELECT quantity_on_hand, average_cost
                 FROM inventory
                 WHERE product_id = :product_id AND branch_id = :branch_id
                 LIMIT 1',
                [
                    'product_id' => $item['product_id'],
                    'branch_id' => $transfer['source_branch_id'],
                ]
            );

            $available = (float) ($inventory['quantity_on_hand'] ?? 0);
            if ($available + 0.0001 < (float) $item['quantity']) {
                throw new HttpException(500, 'Insufficient stock for ' . $item['product_name'] . '.');
            }

            $productModel->adjustInventory(
                productId: (int) $item['product_id'],
                branchId: (int) $transfer['source_branch_id'],
                quantityChange: (float) $item['quantity'] * -1,
                movementType: 'transfer_out',
                reason: 'Transfer sent to ' . $destinationBranchName,
                userId: $userId,
                referenceType: 'stock_transfer',
                referenceId: (int) $transfer['id'],
                unitCost: (float) ($inventory['average_cost'] ?? 0)
            );
        }

        $this->updateRecord([
            'status' => 'in_transit',
            'transfer_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $transfer['id']]);
    }

    private function transferUnitCost(int $transferId, int $productId, int $branchId): float
    {
        $movement = $this->fetch(
            'SELECT unit_cost
             FROM stock_movements
             WHERE reference_type = :reference_type
               AND reference_id = :reference_id
               AND product_id = :product_id
               AND branch_id = :branch_id
               AND movement_type = "transfer_out"
             ORDER BY id DESC
             LIMIT 1',
            [
                'reference_type' => 'stock_transfer',
                'reference_id' => $transferId,
                'product_id' => $productId,
                'branch_id' => $branchId,
            ]
        );

        return (float) ($movement['unit_cost'] ?? 0);
    }

    private function validateBranches(int $sourceBranchId, int $destinationBranchId): void
    {
        if ($sourceBranchId <= 0 || $destinationBranchId <= 0) {
            throw new HttpException(500, 'Select valid branches for this transfer.');
        }

        if ($sourceBranchId === $destinationBranchId) {
            throw new HttpException(500, 'Source and destination branches must be different.');
        }

        $statement = $this->db->prepare(
            'SELECT id FROM branches WHERE id IN (?, ?) AND status = "active" AND deleted_at IS NULL'
        );
        $statement->execute([$sourceBranchId, $destinationBranchId]);
        $branchIds = array_map(static fn (array $row): int => (int) $row['id'], $statement->fetchAll());

        if (!in_array($sourceBranchId, $branchIds, true) || !in_array($destinationBranchId, $branchIds, true)) {
            throw new HttpException(500, 'One or more selected branches are not available.');
        }
    }

    private function compileItems(array $items): array
    {
        if ($items === []) {
            throw new HttpException(500, 'Add at least one stock transfer item.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!isset($normalized[$productId])) {
                $normalized[$productId] = [
                    'product_id' => $productId,
                    'quantity' => 0.0,
                ];
            }

            $normalized[$productId]['quantity'] += $quantity;
        }

        if ($normalized === []) {
            throw new HttpException(500, 'Add at least one valid stock transfer item.');
        }

        $productIds = array_keys($normalized);
        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $statement = $this->db->prepare("SELECT id, name, sku FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $statement->execute($productIds);

        $products = [];
        foreach ($statement->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }

        $compiled = [];
        foreach ($normalized as $productId => $item) {
            if (!isset($products[$productId])) {
                throw new HttpException(500, 'One or more transfer items use invalid products.');
            }

            $compiled[] = [
                'product_id' => $productId,
                'quantity' => $item['quantity'],
                'product_name' => $products[$productId]['name'],
                'sku' => $products[$productId]['sku'],
            ];
        }

        return $compiled;
    }

    private function generateReference(): string
    {
        return 'TRF-' . date('YmdHis') . '-' . random_int(100, 999);
    }
}
