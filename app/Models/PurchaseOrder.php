<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class PurchaseOrder extends Model
{
    protected string $table = 'purchase_orders';

    public function list(array $filters = [], ?int $branchId = null): array
    {
        $sql = 'SELECT po.*, s.name AS supplier_name,
                       CONCAT(u.first_name, " ", u.last_name) AS created_by_name,
                       COUNT(poi.id) AS item_count,
                       COALESCE(SUM(poi.quantity), 0) AS ordered_units,
                       COALESCE(SUM(poi.received_quantity), 0) AS received_units,
                       GREATEST(COALESCE(SUM(poi.quantity - poi.received_quantity), 0), 0) AS remaining_units
                FROM purchase_orders po
                INNER JOIN suppliers s ON s.id = po.supplier_id
                INNER JOIN users u ON u.id = po.created_by
                LEFT JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
                WHERE po.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND po.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND po.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (
                po.po_number LIKE :search
                OR s.name LIKE :search
                OR CONCAT(u.first_name, " ", u.last_name) LIKE :search
            )';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (($filters['supplier_id'] ?? '') !== '') {
            $sql .= ' AND po.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND DATE(po.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND DATE(po.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' GROUP BY po.id ORDER BY po.created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function findDetailed(int $id): ?array
    {
        $order = $this->fetch(
            'SELECT po.*, s.name AS supplier_name, s.contact_person, s.email AS supplier_email, s.phone AS supplier_phone,
                    CONCAT(u.first_name, " ", u.last_name) AS created_by_name,
                    b.name AS branch_name
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             INNER JOIN users u ON u.id = po.created_by
             INNER JOIN branches b ON b.id = po.branch_id
             WHERE po.id = :id AND po.deleted_at IS NULL
             LIMIT 1',
            ['id' => $id]
        );

        if ($order === null) {
            return null;
        }

        $items = $this->fetchAll(
            'SELECT poi.*, p.name AS product_name, p.sku,
                    COALESCE(poi.received_quantity, 0) AS received_quantity,
                    COALESCE(poi.received_total, 0) AS received_total,
                    GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0) AS remaining_quantity,
                    CASE
                        WHEN poi.quantity > 0 THEN ROUND((COALESCE(poi.received_quantity, 0) / poi.quantity) * 100, 2)
                        ELSE 0
                    END AS completion_percent
             FROM purchase_order_items poi
             INNER JOIN products p ON p.id = poi.product_id
             WHERE poi.purchase_order_id = :purchase_order_id
             ORDER BY poi.id ASC',
            ['purchase_order_id' => $id]
        );

        $orderedUnits = 0.0;
        $receivedUnits = 0.0;
        $remainingUnits = 0.0;
        $pendingItemCount = 0;

        foreach ($items as $item) {
            $orderedUnits += (float) ($item['quantity'] ?? 0);
            $receivedUnits += (float) ($item['received_quantity'] ?? 0);
            $remainingUnits += max((float) ($item['remaining_quantity'] ?? 0), 0);

            if ((float) ($item['remaining_quantity'] ?? 0) > 0.0001) {
                $pendingItemCount++;
            }
        }

        $order['items'] = $items;
        $order['ordered_units'] = $orderedUnits;
        $order['received_units'] = $receivedUnits;
        $order['remaining_units'] = $remainingUnits;
        $order['pending_item_count'] = $pendingItemCount;
        $order['completion_rate'] = $orderedUnits > 0 ? round(($receivedUnits / $orderedUnits) * 100, 2) : 0.0;

        return $order;
    }

    public function createOrder(array $payload, array $items): int
    {
        $compiled = $this->compileItems($items);

        return Database::transaction(function () use ($payload, $compiled): int {
            $status = in_array($payload['status'], ['draft', 'ordered'], true) ? $payload['status'] : 'draft';
            $now = date('Y-m-d H:i:s');
            $orderId = $this->insert([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'created_by' => $payload['created_by'],
                'po_number' => $this->generateNumber(),
                'status' => $status,
                'subtotal' => $compiled['subtotal'],
                'tax_total' => $compiled['tax_total'],
                'total' => $compiled['total'],
                'notes' => $payload['notes'],
                'ordered_at' => $status === 'ordered' ? $now : null,
                'expected_at' => $payload['expected_at'] !== '' ? $payload['expected_at'] . ' 00:00:00' : null,
                'received_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($compiled['items'] as $item) {
                $this->db->prepare(
                    'INSERT INTO purchase_order_items (
                        purchase_order_id,
                        product_id,
                        quantity,
                        received_quantity,
                        unit_cost,
                        tax_rate,
                        total,
                        received_total,
                        last_received_at,
                        created_at,
                        updated_at
                     ) VALUES (
                        :purchase_order_id,
                        :product_id,
                        :quantity,
                        0,
                        :unit_cost,
                        :tax_rate,
                        :total,
                        0,
                        NULL,
                        NOW(),
                        NOW()
                     )'
                )->execute([
                    'purchase_order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'],
                    'total' => $item['total'],
                ]);
            }

            return $orderId;
        });
    }

    public function updateOrder(int $id, array $payload, array $items): array
    {
        $order = $this->findDetailed($id);
        if ($order === null) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        if (!$this->isEditable($order)) {
            throw new HttpException(500, 'Only draft purchase orders can be edited.');
        }

        $compiled = $this->compileItems($items);

        return Database::transaction(function () use ($id, $order, $payload, $compiled): array {
            $status = in_array($payload['status'], ['draft', 'ordered'], true) ? $payload['status'] : 'draft';
            $timestamp = date('Y-m-d H:i:s');

            $this->updateRecord([
                'supplier_id' => $payload['supplier_id'],
                'status' => $status,
                'subtotal' => $compiled['subtotal'],
                'tax_total' => $compiled['tax_total'],
                'total' => $compiled['total'],
                'notes' => $payload['notes'],
                'ordered_at' => $status === 'ordered' ? ($order['ordered_at'] ?: $timestamp) : null,
                'expected_at' => $payload['expected_at'] !== '' ? $payload['expected_at'] . ' 00:00:00' : null,
                'updated_at' => $timestamp,
            ], 'id = :id', ['id' => $id]);

            $this->execute('DELETE FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id', [
                'purchase_order_id' => $id,
            ]);

            foreach ($compiled['items'] as $item) {
                $this->db->prepare(
                    'INSERT INTO purchase_order_items (
                        purchase_order_id,
                        product_id,
                        quantity,
                        received_quantity,
                        unit_cost,
                        tax_rate,
                        total,
                        received_total,
                        last_received_at,
                        created_at,
                        updated_at
                     ) VALUES (
                        :purchase_order_id,
                        :product_id,
                        :quantity,
                        0,
                        :unit_cost,
                        :tax_rate,
                        :total,
                        0,
                        NULL,
                        NOW(),
                        NOW()
                     )'
                )->execute([
                    'purchase_order_id' => $id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'],
                    'total' => $item['total'],
                ]);
            }

            $updatedOrder = $this->findDetailed($id);
            if ($updatedOrder === null) {
                throw new HttpException(500, 'Unable to reload the updated purchase order.');
            }

            return $updatedOrder;
        });
    }

    public function duplicateOrder(int $id, int $createdBy, int $branchId): int
    {
        $order = $this->findDetailed($id);
        if ($order === null) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        $items = array_map(static fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (float) $item['quantity'],
            'unit_cost' => (float) $item['unit_cost'],
            'tax_rate' => (float) $item['tax_rate'],
        ], $order['items']);

        $notes = trim('Duplicated from ' . (string) $order['po_number'] . '.' . (
            trim((string) ($order['notes'] ?? '')) !== ''
                ? PHP_EOL . trim((string) $order['notes'])
                : ''
        ));

        return $this->createOrder([
            'branch_id' => $branchId,
            'supplier_id' => (int) $order['supplier_id'],
            'created_by' => $createdBy,
            'expected_at' => $this->dateValue((string) ($order['expected_at'] ?? '')),
            'notes' => $notes,
            'status' => 'draft',
        ], $items);
    }

    public function updateStatus(int $id, string $status, ?string $note = null): void
    {
        $order = $this->findDetailed($id);
        if ($order === null) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        $allowed = match ($status) {
            'ordered' => $order['status'] === 'draft',
            'cancelled' => in_array($order['status'], ['draft', 'ordered', 'partial_received'], true),
            default => false,
        };

        if (!$allowed) {
            throw new HttpException(500, 'That purchase order transition is not allowed.');
        }

        $payload = [
            'status' => $status,
            'notes' => $this->mergeNote((string) ($order['notes'] ?? ''), $note),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'ordered' && empty($order['ordered_at'])) {
            $payload['ordered_at'] = date('Y-m-d H:i:s');
        }

        $this->updateRecord($payload, 'id = :id', ['id' => $id]);
    }

    public function receiveOrder(int $id, int $userId, int $branchId, array $receivedItems, ?string $note = null): array
    {
        $order = $this->findDetailed($id);
        if ($order === null) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        if (in_array($order['status'], ['received', 'cancelled'], true)) {
            throw new HttpException(500, 'This purchase order is already closed.');
        }

        $receiptItems = $this->prepareReceiptItems($order, $receivedItems);

        return Database::transaction(function () use ($order, $receiptItems, $userId, $branchId, $note): array {
            $productModel = new Product();
            $receiptTimestamp = date('Y-m-d H:i:s');

            foreach ($receiptItems as $receiptItem) {
                $productModel->adjustInventory(
                    productId: (int) $receiptItem['product_id'],
                    branchId: $branchId,
                    quantityChange: (float) $receiptItem['receive_quantity'],
                    movementType: 'purchase',
                    reason: 'Purchase order receipt',
                    userId: $userId,
                    referenceType: 'purchase_order',
                    referenceId: (int) $order['id'],
                    unitCost: (float) $receiptItem['unit_cost']
                );

                $newReceivedQuantity = (float) $receiptItem['received_quantity'] + (float) $receiptItem['receive_quantity'];
                $lineSubtotal = (float) $receiptItem['receive_quantity'] * (float) $receiptItem['unit_cost'];
                $lineTax = $lineSubtotal * ((float) $receiptItem['tax_rate'] / 100);
                $lineTotal = $lineSubtotal + $lineTax;
                $newReceivedTotal = (float) $receiptItem['received_total'] + $lineTotal;

                if (abs($newReceivedQuantity - (float) $receiptItem['quantity']) < 0.0001) {
                    $newReceivedQuantity = (float) $receiptItem['quantity'];
                    $newReceivedTotal = (float) $receiptItem['total'];
                }

                $this->db->prepare(
                    'UPDATE purchase_order_items
                     SET received_quantity = :received_quantity,
                         received_total = :received_total,
                         last_received_at = :last_received_at,
                         updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'received_quantity' => $newReceivedQuantity,
                    'received_total' => $newReceivedTotal,
                    'last_received_at' => $receiptTimestamp,
                    'id' => $receiptItem['id'],
                ]);
            }

            $updatedOrder = $this->findDetailed((int) $order['id']);
            if ($updatedOrder === null) {
                throw new HttpException(500, 'Unable to reload the updated purchase order.');
            }

            $status = 'ordered';
            if ((float) $updatedOrder['received_units'] > 0.0001 && (float) $updatedOrder['remaining_units'] > 0.0001) {
                $status = 'partial_received';
            }
            if ((float) $updatedOrder['remaining_units'] <= 0.0001) {
                $status = 'received';
            }

            $payload = [
                'status' => $status,
                'notes' => $this->mergeNote((string) ($order['notes'] ?? ''), $note),
                'ordered_at' => $order['ordered_at'] ?: $receiptTimestamp,
                'received_at' => $status === 'received' ? $receiptTimestamp : null,
                'updated_at' => $receiptTimestamp,
            ];

            $this->updateRecord($payload, 'id = :id', ['id' => $order['id']]);

            $freshOrder = $this->findDetailed((int) $order['id']);
            if ($freshOrder === null) {
                throw new HttpException(500, 'Unable to reload the updated purchase order.');
            }

            return $freshOrder;
        });
    }

    private function prepareReceiptItems(array $order, array $receivedItems): array
    {
        $itemsById = [];
        foreach ($order['items'] as $item) {
            $itemsById[(int) $item['id']] = $item;
        }

        $receiptItems = [];
        foreach ($receivedItems as $itemId => $quantity) {
            $lineId = (int) $itemId;
            $receiveQuantity = (float) $quantity;

            if ($receiveQuantity <= 0) {
                continue;
            }

            if (!isset($itemsById[$lineId])) {
                throw new HttpException(500, 'One or more receipt lines are invalid.');
            }

            $item = $itemsById[$lineId];
            $remaining = max((float) ($item['quantity'] ?? 0) - (float) ($item['received_quantity'] ?? 0), 0);

            if ($remaining <= 0.0001) {
                throw new HttpException(500, 'One or more purchase order lines are already fully received.');
            }

            if ($receiveQuantity - $remaining > 0.0001) {
                throw new HttpException(500, 'Received quantity cannot exceed the remaining quantity on a purchase order line.');
            }

            $item['receive_quantity'] = $receiveQuantity;
            $receiptItems[] = $item;
        }

        if ($receiptItems === []) {
            throw new HttpException(500, 'Enter at least one received quantity greater than zero.');
        }

        return $receiptItems;
    }

    public function isEditable(array $order): bool
    {
        return (string) ($order['status'] ?? '') === 'draft'
            && (float) ($order['received_units'] ?? 0) <= 0.0001;
    }

    private function compileItems(array $items): array
    {
        if ($items === []) {
            throw new HttpException(500, 'Add at least one purchase order item.');
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['product_id'] ?? 0),
            $items
        ))));

        if ($productIds === []) {
            throw new HttpException(500, 'Add at least one valid purchase order item.');
        }

        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $statement = $this->db->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $statement->execute($productIds);
        $existing = array_map(static fn (array $row): int => (int) $row['id'], $statement->fetchAll());

        $compiledItems = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);

            if ($productId <= 0 || $quantity <= 0 || $unitCost < 0) {
                continue;
            }

            if (!in_array($productId, $existing, true)) {
                throw new HttpException(500, 'One or more purchase order items use invalid products.');
            }

            $lineSubtotal = $quantity * $unitCost;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;

            $compiledItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $taxRate,
                'total' => $lineTotal,
            ];
        }

        if ($compiledItems === []) {
            throw new HttpException(500, 'Add at least one valid purchase order item.');
        }

        return [
            'items' => $compiledItems,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ];
    }

    private function mergeNote(string $existingNotes, ?string $note): string
    {
        $note = trim((string) $note);
        $existingNotes = trim($existingNotes);

        if ($note === '') {
            return $existingNotes;
        }

        $timestampedNote = '[' . date('Y-m-d H:i') . '] ' . $note;

        return $existingNotes !== ''
            ? $existingNotes . PHP_EOL . $timestampedNote
            : $timestampedNote;
    }

    private function generateNumber(): string
    {
        return 'PO-' . date('YmdHis') . '-' . random_int(100, 999);
    }

    private function dateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }
}
