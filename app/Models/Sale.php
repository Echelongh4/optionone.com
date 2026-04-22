<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class Sale extends Model
{
    protected string $table = 'sales';

    private const LOYALTY_EARN_RATE = 10.0;
    private const LOYALTY_POINT_VALUE = 0.10;
    private static ?bool $paymentDetailColumnsReady = null;

    public function history(array $filters = [], ?int $branchId = null): array
    {
        $paymentSearchSql = 'COALESCE(p.reference, "") LIKE :search
                          OR COALESCE(p.notes, "") LIKE :search';

        if ($this->paymentDetailColumnsReady()) {
            $paymentSearchSql .= '
                          OR COALESCE(p.cheque_number, "") LIKE :search
                          OR COALESCE(p.cheque_bank, "") LIKE :search';
        }

        $sql = 'SELECT s.*, CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                       COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name,
                       COALESCE(c.phone, "") AS customer_phone,
                       COALESCE(ret.total_refund, 0) AS total_refund,
                       COALESCE(items.line_count, 0) AS line_count,
                       COALESCE(items.item_quantity_total, 0) AS item_quantity_total,
                       COALESCE(payments.collected_amount, 0) AS collected_amount,
                       COALESCE(payments.cash_tendered, 0) AS cash_tendered,
                       COALESCE(payments.credit_amount, 0) AS credit_amount,
                       COALESCE(payments.payment_methods, "") AS payment_methods,
                       COALESCE(payments.payment_references, "") AS payment_references,
                       (
                           SELECT svr.status
                           FROM sale_void_requests svr
                           WHERE svr.sale_id = s.id
                             AND svr.status = "pending"
                           ORDER BY svr.id DESC
                           LIMIT 1
                       ) AS void_request_status,
                       (
                           SELECT CONCAT(req.first_name, " ", req.last_name)
                           FROM sale_void_requests svr
                           INNER JOIN users req ON req.id = svr.requested_by
                           WHERE svr.sale_id = s.id
                             AND svr.status = "pending"
                           ORDER BY svr.id DESC
                           LIMIT 1
                       ) AS void_requested_by_name,
                       (
                           SELECT svr.created_at
                           FROM sale_void_requests svr
                           WHERE svr.sale_id = s.id
                             AND svr.status = "pending"
                           ORDER BY svr.id DESC
                           LIMIT 1
                       ) AS void_requested_at
                FROM sales s
                INNER JOIN users u ON u.id = s.user_id
                LEFT JOIN customers c ON c.id = s.customer_id
                LEFT JOIN (
                    SELECT sale_id, SUM(total_refund) AS total_refund
                    FROM returns
                    WHERE status = "completed"
                    GROUP BY sale_id
                ) ret ON ret.sale_id = s.id
                LEFT JOIN (
                    SELECT sale_id,
                           COUNT(*) AS line_count,
                           COALESCE(SUM(quantity), 0) AS item_quantity_total
                    FROM sale_items
                    GROUP BY sale_id
                ) items ON items.sale_id = s.id
                LEFT JOIN (
                    SELECT sale_id,
                           COALESCE(SUM(CASE WHEN payment_method <> "credit" THEN amount ELSE 0 END), 0) AS collected_amount,
                           COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) AS cash_tendered,
                           COALESCE(SUM(CASE WHEN payment_method = "credit" THEN amount ELSE 0 END), 0) AS credit_amount,
                           GROUP_CONCAT(DISTINCT REPLACE(payment_method, "_", " ") ORDER BY payment_method SEPARATOR ", ") AS payment_methods,
                           GROUP_CONCAT(DISTINCT NULLIF(reference, "") ORDER BY id SEPARATOR ", ") AS payment_references
                    FROM payments
                    GROUP BY sale_id
                ) payments ON payments.sale_id = s.id
                WHERE s.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (
                s.sale_number LIKE :search
                OR COALESCE(s.notes, "") LIKE :search
                OR CONCAT(u.first_name, " ", u.last_name) LIKE :search
                OR COALESCE(CONCAT(c.first_name, " ", c.last_name), "") LIKE :search
                OR COALESCE(c.phone, "") LIKE :search
                OR EXISTS (
                    SELECT 1
                    FROM payments p
                    WHERE p.sale_id = s.id
                      AND (
                          ' . $paymentSearchSql . '
                      )
                )
                OR EXISTS (
                    SELECT 1
                    FROM sale_items si
                    WHERE si.sale_id = s.id
                      AND (
                          COALESCE(si.product_name, "") LIKE :search
                          OR COALESCE(si.sku, "") LIKE :search
                          OR COALESCE(si.barcode, "") LIKE :search
                      )
                )
            )';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (($filters['status'] ?? '') === 'void_pending') {
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM sale_void_requests svr
                WHERE svr.sale_id = s.id
                  AND svr.status = "pending"
            )';
        } elseif (($filters['status'] ?? '') !== '') {
            $sql .= ' AND s.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['cashier_id'] ?? '') !== '') {
            $sql .= ' AND s.user_id = :cashier_id';
            $params['cashier_id'] = (int) $filters['cashier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND DATE(s.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND DATE(s.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY s.created_at DESC';

        return $this->fetchAll($sql, $params);
    }
    public function heldSales(?int $branchId = null): array
    {
        $sql = 'SELECT s.id, s.sale_number, s.grand_total, s.created_at,
                       COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                WHERE s.status = "held"';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY s.created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function findDetailed(int $saleId): ?array
    {
        $sale = $this->fetch(
            'SELECT s.*, COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name,
                    CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                    b.name AS branch_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN branches b ON b.id = s.branch_id
             WHERE s.id = :id
             LIMIT 1',
            ['id' => $saleId]
        );

        if ($sale === null) {
            return null;
        }

        $sale['items'] = $this->fetchAll(
            'SELECT si.*, COALESCE(rq.returned_quantity, 0) AS returned_quantity
             FROM sale_items si
             LEFT JOIN (
                 SELECT ri.sale_item_id, SUM(ri.quantity) AS returned_quantity
                 FROM return_items ri
                 INNER JOIN returns r ON r.id = ri.return_id
                 WHERE r.status = "completed"
                 GROUP BY ri.sale_item_id
             ) rq ON rq.sale_item_id = si.id
             WHERE si.sale_id = :sale_id
             ORDER BY si.id',
            ['sale_id' => $saleId]
        );
        $sale['payments'] = $this->fetchAll(
            'SELECT * FROM payments WHERE sale_id = :sale_id ORDER BY id',
            ['sale_id' => $saleId]
        );
        $sale['payments'] = array_map(function (array $payment): array {
            if (!array_key_exists('cheque_number', $payment)) {
                $payment['cheque_number'] = null;
            }

            if (!array_key_exists('cheque_bank', $payment)) {
                $payment['cheque_bank'] = null;
            }

            if (!array_key_exists('cheque_date', $payment)) {
                $payment['cheque_date'] = null;
            }

            return $payment;
        }, $sale['payments']);
        $sale['collected_amount'] = array_reduce(
            $sale['payments'],
            static fn (float $carry, array $payment): float => $carry + ((string) ($payment['payment_method'] ?? '') !== 'credit' ? (float) ($payment['amount'] ?? 0) : 0),
            0.0
        );
        $sale['cash_tendered'] = array_reduce(
            $sale['payments'],
            static fn (float $carry, array $payment): float => $carry + ((string) ($payment['payment_method'] ?? '') === 'cash' ? (float) ($payment['amount'] ?? 0) : 0),
            0.0
        );
        $sale['credit_amount'] = array_reduce(
            $sale['payments'],
            static fn (float $carry, array $payment): float => $carry + ((string) ($payment['payment_method'] ?? '') === 'credit' ? (float) ($payment['amount'] ?? 0) : 0),
            0.0
        );
        $sale['returns'] = $this->fetchAll(
            'SELECT r.*, CONCAT(u.first_name, " ", u.last_name) AS processed_by_name
             FROM returns r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.sale_id = :sale_id
             ORDER BY r.created_at DESC',
            ['sale_id' => $saleId]
        );
        $sale['loyalty_entries'] = $this->fetchAll(
            'SELECT * FROM loyalty_points WHERE sale_id = :sale_id ORDER BY created_at ASC, id ASC',
            ['sale_id' => $saleId]
        );
        $sale['loyalty_points_earned'] = array_reduce(
            $sale['loyalty_entries'],
            static fn (int $carry, array $entry): int => $carry + ((string) ($entry['transaction_type'] ?? '') === 'earn' ? (int) ($entry['points'] ?? 0) : 0),
            0
        );
        $sale['credit_transactions'] = $this->fetchAll(
            'SELECT cct.*, s.sale_number, r.return_number,
                    CONCAT(u.first_name, " ", u.last_name) AS user_name
             FROM customer_credit_transactions cct
             LEFT JOIN sales s ON s.id = cct.sale_id
             LEFT JOIN returns r ON r.id = cct.return_id
             LEFT JOIN users u ON u.id = cct.user_id
             WHERE cct.sale_id = :sale_id
             ORDER BY cct.created_at ASC, cct.id ASC',
            ['sale_id' => $saleId]
        );
        $sale['credit_relieved_total'] = array_reduce(
            $sale['credit_transactions'],
            static fn (float $carry, array $entry): float => $carry + ((float) ($entry['amount'] ?? 0) < 0 ? abs((float) ($entry['amount'] ?? 0)) : 0),
            0.0
        );
        $sale['outstanding_credit_amount'] = max(0, (float) $sale['credit_amount'] - (float) $sale['credit_relieved_total']);

        return $sale;
    }

    public function findDetailedForBranch(int $saleId, int $branchId): ?array
    {
        $sale = $this->findDetailed($saleId);
        if ($sale === null) {
            return null;
        }

        if ((int) ($sale['branch_id'] ?? 0) !== $branchId) {
            return null;
        }

        return $sale;
    }
    public function hold(array $items, array $payments, array $orderDiscount, ?int $customerId, int $userId, int $branchId, string $notes = '', int $redeemPoints = 0, ?int $heldSaleId = null, ?string $heldSaleToken = null): int
    {
        $compiled = $this->compileItems($items, $orderDiscount, $branchId, $customerId, $redeemPoints);
        $draftPayments = $this->sanitizeDraftPayments($payments, $customerId);

        return Database::transaction(function () use ($compiled, $draftPayments, $customerId, $userId, $branchId, $notes, $heldSaleId, $heldSaleToken): int {
            $existing = $this->resolveEditableHeldSale($heldSaleId, $branchId, $heldSaleToken);
            $saleId = $this->persistSaleHeader([
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'sale_number' => $existing['sale_number'] ?? $this->generateSaleNumber('HLD'),
                'status' => 'held',
                'subtotal' => $compiled['totals']['subtotal'],
                'item_discount_total' => $compiled['totals']['item_discount_total'],
                'order_discount_total' => $compiled['totals']['order_discount_total'],
                'loyalty_discount_total' => $compiled['totals']['loyalty_discount_total'],
                'loyalty_points_redeemed' => $compiled['totals']['loyalty_points_redeemed'],
                'tax_total' => $compiled['totals']['tax_total'],
                'grand_total' => $compiled['totals']['grand_total'],
                'amount_paid' => 0,
                'change_due' => 0,
                'notes' => $notes,
                'held_until' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'completed_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], $heldSaleId);

            $this->replaceSaleItems($saleId, $compiled['items']);
            $this->replacePayments($saleId, $draftPayments);

            return $saleId;
        });
    }

    public function checkout(array $items, array $payments, array $orderDiscount, ?int $customerId, int $userId, int $branchId, string $notes = '', int $redeemPoints = 0, ?int $heldSaleId = null, ?string $heldSaleToken = null): int
    {
        $compiled = $this->compileItems($items, $orderDiscount, $branchId, $customerId, $redeemPoints);
        $settlement = $this->summarizePayments($payments, $compiled['totals']['grand_total'], $customerId);

        if ($compiled['totals']['grand_total'] <= 0) {
            throw new HttpException(500, 'The cart total must be greater than zero.');
        }

        return Database::transaction(function () use ($compiled, $settlement, $customerId, $userId, $branchId, $notes, $heldSaleId, $heldSaleToken): int {
            $existing = $this->resolveEditableHeldSale($heldSaleId, $branchId, $heldSaleToken);
            $this->lockAndValidateInventory($compiled['items'], $branchId);
            $saleId = $this->persistSaleHeader([
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'sale_number' => $existing['sale_number'] ?? $this->generateSaleNumber('SAL'),
                'status' => 'completed',
                'subtotal' => $compiled['totals']['subtotal'],
                'item_discount_total' => $compiled['totals']['item_discount_total'],
                'order_discount_total' => $compiled['totals']['order_discount_total'],
                'loyalty_discount_total' => $compiled['totals']['loyalty_discount_total'],
                'loyalty_points_redeemed' => $compiled['totals']['loyalty_points_redeemed'],
                'tax_total' => $compiled['totals']['tax_total'],
                'grand_total' => $compiled['totals']['grand_total'],
                'amount_paid' => $settlement['collected_amount'],
                'change_due' => $settlement['change_due'],
                'notes' => $notes,
                'held_until' => null,
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], $heldSaleId);

            $this->replaceSaleItems($saleId, $compiled['items']);
            $this->replacePayments($saleId, $settlement['payments']);

            $productModel = new Product();
            foreach ($compiled['items'] as $line) {
                if ((int) $line['track_stock'] === 1) {
                    $productModel->adjustInventory(
                        productId: (int) $line['product_id'],
                        branchId: $branchId,
                        quantityChange: -1 * (float) $line['quantity'],
                        movementType: 'sale',
                        reason: 'POS sale completed',
                        userId: $userId,
                        referenceType: 'sale',
                        referenceId: $saleId,
                        unitCost: (float) $line['cost_price']
                    );
                }
            }

            if ($settlement['credit_amount'] > 0.0001 && $customerId !== null) {
                (new Customer())->adjustCreditBalance(
                    customerId: (int) $customerId,
                    amount: $settlement['credit_amount'],
                    transactionType: 'charge',
                    saleId: $saleId,
                    userId: $userId,
                    notes: 'Open account balance created from POS sale.'
                );
            }

            $this->updateLoyalty($customerId, $saleId, $compiled['totals']['grand_total'], $compiled['totals']['loyalty_points_redeemed']);

            return $saleId;
        });
    }
    public function processReturn(int $saleId, array $returnLines, string $reason, int $userId, int $branchId): int
    {
        return Database::transaction(function () use ($saleId, $returnLines, $reason, $userId, $branchId): int {
            $sale = $this->loadReturnableSaleForUpdate($saleId, $branchId);
            $processed = $this->compileReturnLines($this->loadSaleItemsForReturn($saleId), $returnLines, $reason);

            $this->db->prepare(
                'INSERT INTO returns (sale_id, user_id, customer_id, return_number, reason, status, subtotal, tax_total, total_refund, approved_by, created_at, updated_at)
                 VALUES (:sale_id, :user_id, :customer_id, :return_number, :reason, "completed", :subtotal, :tax_total, :total_refund, :approved_by, NOW(), NOW())'
            )->execute([
                'sale_id' => $sale['id'],
                'user_id' => $userId,
                'customer_id' => $sale['customer_id'],
                'return_number' => $this->generateSaleNumber('RET'),
                'reason' => $reason,
                'subtotal' => $processed['subtotal'],
                'tax_total' => $processed['tax_total'],
                'total_refund' => $processed['refund_total'],
                'approved_by' => $userId,
            ]);
            $returnId = (int) $this->db->lastInsertId();

            $productModel = new Product();
            foreach ($processed['lines'] as $line) {
                $this->db->prepare(
                    'INSERT INTO return_items (return_id, sale_item_id, product_id, quantity, unit_price, tax_total, line_total, reason, created_at)
                     VALUES (:return_id, :sale_item_id, :product_id, :quantity, :unit_price, :tax_total, :line_total, :reason, NOW())'
                )->execute([
                    'return_id' => $returnId,
                    'sale_item_id' => $line['sale_item_id'],
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_total' => $line['tax_total'],
                    'line_total' => $line['line_total'],
                    'reason' => $line['reason'],
                ]);

                $productModel->adjustInventory(
                    productId: $line['product_id'],
                    branchId: (int) $sale['branch_id'],
                    quantityChange: (float) $line['quantity'],
                    movementType: 'return',
                    reason: 'Customer return processed',
                    userId: $userId,
                    referenceType: 'return',
                    referenceId: $returnId,
                    unitCost: 0
                );
            }

            $refundedTotal = $this->fetch(
                'SELECT COALESCE(SUM(total_refund), 0) AS refunded_total FROM returns WHERE sale_id = :sale_id AND status = "completed"',
                ['sale_id' => $sale['id']]
            );
            $newStatus = (float) ($refundedTotal['refunded_total'] ?? 0) >= ((float) $sale['grand_total'] - 0.01) ? 'refunded' : 'partial_return';

            $this->updateRecord([
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $sale['id']]);

            $customerId = isset($sale['customer_id']) && $sale['customer_id'] !== null ? (int) $sale['customer_id'] : null;
            if ($customerId !== null) {
                $creditExposure = $this->creditExposureForSale((int) $sale['id']);
                $availableBalance = $this->currentCustomerCreditBalance($customerId);
                $creditRelief = min($processed['refund_total'], $creditExposure, $availableBalance);

                if ($creditRelief > 0.0001) {
                    (new Customer())->adjustCreditBalance(
                        customerId: $customerId,
                        amount: -1 * $creditRelief,
                        transactionType: 'return',
                        saleId: (int) $sale['id'],
                        returnId: $returnId,
                        userId: $userId,
                        notes: 'Reduced customer credit after sale return.'
                    );
                }
            }

            return $returnId;
        });
    }

    private function loadReturnableSaleForUpdate(int $saleId, int $branchId): array
    {
        $sale = $this->fetch(
            'SELECT id, branch_id, customer_id, grand_total, status
             FROM sales
             WHERE id = :id
             LIMIT 1
             FOR UPDATE',
            ['id' => $saleId]
        );

        if ($sale === null || (int) ($sale['branch_id'] ?? 0) !== $branchId) {
            throw new HttpException(404, 'Sale not found for this branch.');
        }

        if (!in_array((string) ($sale['status'] ?? ''), ['completed', 'partial_return'], true)) {
            throw new HttpException(500, 'Only completed sales can be returned.');
        }

        return $sale;
    }

    private function loadSaleItemsForReturn(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT si.*,
                    COALESCE(rq.returned_quantity, 0) AS returned_quantity
             FROM sale_items si
             LEFT JOIN (
                 SELECT ri.sale_item_id, SUM(ri.quantity) AS returned_quantity
                 FROM return_items ri
                 INNER JOIN returns r ON r.id = ri.return_id
                 WHERE r.sale_id = :sale_id
                   AND r.status = "completed"
                 GROUP BY ri.sale_item_id
             ) rq ON rq.sale_item_id = si.id
             WHERE si.sale_id = :sale_id
             ORDER BY si.id
             FOR UPDATE',
            ['sale_id' => $saleId]
        );
    }

    private function compileReturnLines(array $saleItems, array $returnLines, string $defaultReason): array
    {
        $lineMap = [];
        foreach ($saleItems as $item) {
            $lineMap[(int) $item['id']] = $item;
        }

        $processedLines = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $refundTotal = 0.0;

        foreach ($returnLines as $payload) {
            $saleItemId = (int) ($payload['sale_item_id'] ?? 0);
            $quantity = round((float) ($payload['quantity'] ?? 0), 4);
            $item = $lineMap[$saleItemId] ?? null;

            if ($item === null || $quantity <= 0) {
                continue;
            }

            $remaining = round((float) $item['quantity'] - (float) $item['returned_quantity'], 4);
            if ($quantity > $remaining + 0.0001) {
                throw new HttpException(500, 'Return quantity exceeds the remaining quantity for ' . $item['product_name'] . '.');
            }

            $ratio = $quantity / max((float) $item['quantity'], 1);
            $lineSubtotal = (float) $item['unit_price'] * $quantity;
            $lineTax = (float) $item['tax_total'] * $ratio;
            $lineTotal = (float) $item['line_total'] * $ratio;

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $refundTotal += $lineTotal;

            $processedLines[] = [
                'sale_item_id' => $saleItemId,
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => (float) $item['unit_price'],
                'tax_total' => $lineTax,
                'line_total' => $lineTotal,
                'reason' => trim((string) ($payload['reason'] ?? '')) ?: $defaultReason,
            ];
        }

        if ($processedLines === []) {
            throw new HttpException(500, 'Add at least one return line.');
        }

        return [
            'lines' => $processedLines,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'refund_total' => $refundTotal,
        ];
    }
    public function voidSale(int $saleId, string $reason, int $approvedBy, int $branchId): void
    {
        $sale = $this->findDetailed($saleId);

        if ($sale === null || $sale['status'] !== 'completed') {
            throw new HttpException(500, 'Only completed sales can be voided.');
        }

        if ($sale['returns'] !== []) {
            throw new HttpException(500, 'Sales with returns cannot be voided.');
        }

        Database::transaction(function () use ($sale, $reason, $approvedBy, $branchId): void {
            $productModel = new Product();
            foreach ($sale['items'] as $line) {
                $productModel->adjustInventory(
                    productId: (int) $line['product_id'],
                    branchId: $branchId,
                    quantityChange: (float) $line['quantity'],
                    movementType: 'void',
                    reason: 'Sale voided by supervisor',
                    userId: $approvedBy,
                    referenceType: 'sale',
                    referenceId: (int) $sale['id'],
                    unitCost: 0
                );
            }

            $customerId = isset($sale['customer_id']) && $sale['customer_id'] !== null ? (int) $sale['customer_id'] : null;
            if ($customerId !== null) {
                $creditExposure = min($this->creditExposureForSale((int) $sale['id']), $this->currentCustomerCreditBalance($customerId));
                if ($creditExposure > 0.0001) {
                    (new Customer())->adjustCreditBalance(
                        customerId: $customerId,
                        amount: -1 * $creditExposure,
                        transactionType: 'void',
                        saleId: (int) $sale['id'],
                        userId: $approvedBy,
                        notes: 'Removed open account balance after sale void.'
                    );
                }
            }

            $this->reverseLoyaltyOnVoid($sale);

            $this->updateRecord([
                'status' => 'voided',
                'void_reason' => $reason,
                'approved_by' => $approvedBy,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $sale['id']]);
        });
    }
    public function receipt(int $saleId): ?array
    {
        return $this->findDetailed($saleId);
    }

    private function compileItems(array $items, array $orderDiscount, int $branchId, ?int $customerId = null, int $redeemPoints = 0): array
    {
        if ($items === []) {
            throw new HttpException(500, 'Add at least one item to the cart.');
        }

        $customer = $this->resolveCustomerPricing($customerId);
        $productIds = array_values(array_unique(array_map(static fn (array $item): int => (int) $item['product_id'], $items)));
        $requestedQuantities = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (float) ($item['quantity'] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            $requestedQuantities[$productId] = ($requestedQuantities[$productId] ?? 0) + $quantity;
        }
        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $statement = $this->db->prepare(
            "SELECT p.id, p.name, p.sku, p.barcode, p.price, p.cost_price, p.track_stock,
                    COALESCE(t.rate, 0) AS tax_rate,
                    COALESCE(i.quantity_on_hand, 0) AS stock_quantity
             FROM products p
             LEFT JOIN taxes t ON t.id = p.tax_id
             LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
             WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL"
        );
        $statement->execute(array_merge([$branchId], $productIds));
        $rows = $statement->fetchAll();
        $catalog = [];

        foreach ($rows as $row) {
            $catalog[(int) $row['id']] = $row;
        }

        $compiledItems = [];
        $subtotal = 0.0;
        $itemDiscountTotal = 0.0;
        $taxableBaseTotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (float) ($item['quantity'] ?? 1));
            $product = $catalog[$productId] ?? null;

            if ($product === null) {
                throw new HttpException(500, 'One or more products are invalid.');
            }

            $requestedQuantity = (float) ($requestedQuantities[$productId] ?? $quantity);
            if ((int) $product['track_stock'] === 1 && (float) $product['stock_quantity'] < $requestedQuantity) {
                throw new HttpException(500, 'Insufficient stock for ' . $product['name'] . '.');
            }

            $baseUnitPrice = (float) $product['price'];
            $unitPrice = $this->applyCustomerPricing($baseUnitPrice, $customer);
            $lineSubtotal = $unitPrice * $quantity;
            $discountType = (string) ($item['discount_type'] ?? 'fixed');
            $discountValue = (float) ($item['discount_value'] ?? 0);
            $lineDiscount = $discountType === 'percent'
                ? $lineSubtotal * ($discountValue / 100)
                : $discountValue;
            $lineDiscount = min($lineSubtotal, max(0, $lineDiscount));
            $taxableAfterLineDiscount = max(0, $lineSubtotal - $lineDiscount);

            $subtotal += $lineSubtotal;
            $itemDiscountTotal += $lineDiscount;
            $taxableBaseTotal += $taxableAfterLineDiscount;

            $compiledItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'base_unit_price' => $baseUnitPrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'line_discount' => $lineDiscount,
                'tax_rate' => (float) $product['tax_rate'],
                'taxable_after_line_discount' => $taxableAfterLineDiscount,
                'track_stock' => (int) $product['track_stock'],
                'cost_price' => (float) $product['cost_price'],
            ];
        }

        $orderDiscountType = (string) ($orderDiscount['type'] ?? 'fixed');
        $orderDiscountValue = (float) ($orderDiscount['value'] ?? 0);
        $orderDiscountTotal = $orderDiscountType === 'percent'
            ? $taxableBaseTotal * ($orderDiscountValue / 100)
            : $orderDiscountValue;
        $orderDiscountTotal = min($taxableBaseTotal, max(0, $orderDiscountTotal));
        $baseAfterOrderDiscount = max(0, $taxableBaseTotal - $orderDiscountTotal);

        $redeemPoints = max(0, $redeemPoints);
        $availablePoints = (int) ($customer['loyalty_balance'] ?? 0);
        if ($redeemPoints > 0 && $customer === null) {
            throw new HttpException(500, 'Select a customer before redeeming loyalty points.');
        }
        if ($redeemPoints > $availablePoints) {
            throw new HttpException(500, 'Requested loyalty redemption exceeds the customer\'s available balance.');
        }

        $maxRedeemablePoints = (int) floor($baseAfterOrderDiscount / self::LOYALTY_POINT_VALUE + 0.0001);
        if ($redeemPoints > $maxRedeemablePoints) {
            throw new HttpException(500, 'Redeemed loyalty points exceed the order value available for discount.');
        }

        $loyaltyDiscountTotal = round($redeemPoints * self::LOYALTY_POINT_VALUE, 2);
        $netBeforeTax = max(0, $baseAfterOrderDiscount - $loyaltyDiscountTotal);
        $grandTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($compiledItems as &$line) {
            $manualShare = $taxableBaseTotal > 0 ? ($line['taxable_after_line_discount'] / $taxableBaseTotal) * $orderDiscountTotal : 0;
            $baseAfterManual = max(0, $line['taxable_after_line_discount'] - $manualShare);
            $loyaltyShare = $baseAfterOrderDiscount > 0 ? ($baseAfterManual / $baseAfterOrderDiscount) * $loyaltyDiscountTotal : 0;
            $taxableAfterAllDiscounts = max(0, $baseAfterManual - $loyaltyShare);
            $lineTax = $taxableAfterAllDiscounts * ($line['tax_rate'] / 100);
            $lineTotal = $taxableAfterAllDiscounts + $lineTax;

            $line['order_discount_share'] = $manualShare;
            $line['loyalty_discount_share'] = $loyaltyShare;
            $line['discount_total'] = $line['line_discount'] + $manualShare + $loyaltyShare;
            $line['tax_total'] = $lineTax;
            $line['line_total'] = $lineTotal;

            $taxTotal += $lineTax;
            $grandTotal += $lineTotal;
        }
        unset($line);

        return [
            'items' => $compiledItems,
            'customer' => $customer,
            'totals' => [
                'subtotal' => $subtotal,
                'item_discount_total' => $itemDiscountTotal,
                'order_discount_total' => $orderDiscountTotal,
                'loyalty_discount_total' => $loyaltyDiscountTotal,
                'loyalty_points_redeemed' => $redeemPoints,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'net_before_tax' => $netBeforeTax,
            ],
        ];
    }

    private function persistSaleHeader(array $payload, ?int $saleId): int
    {
        if ($saleId === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');

            return $this->insert($payload);
        }

        $this->updateRecord($payload, 'id = :id', ['id' => $saleId]);

        return $saleId;
    }

    private function replaceSaleItems(int $saleId, array $items): void
    {
        $this->db->prepare('DELETE FROM sale_items WHERE sale_id = :sale_id')->execute(['sale_id' => $saleId]);

        foreach ($items as $line) {
            $this->db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, variant_id, product_name, sku, barcode, quantity, unit_price, discount_type, discount_value, discount_total, tax_rate, tax_total, line_total, created_at, updated_at)
                 VALUES (:sale_id, :product_id, NULL, :product_name, :sku, :barcode, :quantity, :unit_price, :discount_type, :discount_value, :discount_total, :tax_rate, :tax_total, :line_total, NOW(), NOW())'
            )->execute([
                'sale_id' => $saleId,
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'sku' => $line['sku'],
                'barcode' => $line['barcode'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_total' => $line['discount_total'],
                'tax_rate' => $line['tax_rate'],
                'tax_total' => $line['tax_total'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    private function replacePayments(int $saleId, array $payments): void
    {
        $detailColumnsReady = $this->paymentDetailColumnsReady();
        $this->db->prepare('DELETE FROM payments WHERE sale_id = :sale_id')->execute(['sale_id' => $saleId]);

        foreach ($payments as $payment) {
            if ((float) ($payment['amount'] ?? 0) <= 0) {
                continue;
            }

            $notes = $payment['notes'] ?? null;
            if (!$detailColumnsReady && (string) ($payment['method'] ?? '') === 'cheque') {
                $chequeFallback = array_filter([
                    !empty($payment['cheque_number']) ? 'Cheque #: ' . (string) $payment['cheque_number'] : null,
                    !empty($payment['cheque_bank']) ? 'Bank: ' . (string) $payment['cheque_bank'] : null,
                    !empty($payment['cheque_date']) ? 'Date: ' . (string) $payment['cheque_date'] : null,
                ]);

                $notes = trim(implode(' | ', array_filter([
                    is_string($notes) ? trim($notes) : '',
                    implode(' | ', $chequeFallback),
                ]))) ?: null;
            }

            $params = [
                'sale_id' => $saleId,
                'payment_method' => $payment['method'],
                'amount' => (float) $payment['amount'],
                'reference' => $payment['reference'] ?? null,
                'notes' => $notes,
            ];

            if ($detailColumnsReady) {
                $this->db->prepare(
                    'INSERT INTO payments (sale_id, payment_method, amount, reference, cheque_number, cheque_bank, cheque_date, notes, paid_at, created_at)
                     VALUES (:sale_id, :payment_method, :amount, :reference, :cheque_number, :cheque_bank, :cheque_date, :notes, NOW(), NOW())'
                )->execute($params + [
                    'cheque_number' => $payment['cheque_number'] ?? null,
                    'cheque_bank' => $payment['cheque_bank'] ?? null,
                    'cheque_date' => $payment['cheque_date'] ?? null,
                ]);
                continue;
            }

            $this->db->prepare(
                'INSERT INTO payments (sale_id, payment_method, amount, reference, notes, paid_at, created_at)
                 VALUES (:sale_id, :payment_method, :amount, :reference, :notes, NOW(), NOW())'
            )->execute($params);
        }
    }

    private function summarizePayments(array $payments, float $grandTotal, ?int $customerId): array
    {
        $allowedMethods = ['cash', 'card', 'mobile_money', 'cheque', 'split', 'credit'];
        $sanitizedPayments = [];
        $cashTendered = 0.0;
        $nonCashCollected = 0.0;
        $creditAmount = 0.0;

        foreach ($payments as $payment) {
            $method = (string) ($payment['method'] ?? 'cash');
            if (!in_array($method, $allowedMethods, true)) {
                continue;
            }

            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $reference = trim((string) ($payment['reference'] ?? '')) ?: null;
            $notes = trim((string) ($payment['notes'] ?? '')) ?: null;
            $chequeNumber = trim((string) ($payment['cheque_number'] ?? '')) ?: null;
            $chequeBank = trim((string) ($payment['cheque_bank'] ?? '')) ?: null;
            $chequeDate = trim((string) ($payment['cheque_date'] ?? '')) ?: null;

            if ($method === 'cheque') {
                if ($chequeNumber === null || $chequeBank === null || $chequeDate === null) {
                    throw new HttpException(500, 'Each cheque payment must include the cheque number, bank, and cheque date.');
                }

                $parsedChequeDate = date_create($chequeDate);
                if ($parsedChequeDate === false) {
                    throw new HttpException(500, 'One or more cheque dates are invalid.');
                }

                $chequeDate = $parsedChequeDate->format('Y-m-d');
                $reference ??= $chequeNumber;
            } else {
                $chequeNumber = null;
                $chequeBank = null;
                $chequeDate = null;
            }

            $sanitizedPayments[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
                'cheque_number' => $chequeNumber,
                'cheque_bank' => $chequeBank,
                'cheque_date' => $chequeDate,
            ];

            if ($method === 'credit') {
                $creditAmount += $amount;
                continue;
            }

            if ($method === 'cash') {
                $cashTendered += $amount;
                continue;
            }

            $nonCashCollected += $amount;
        }

        if ($creditAmount > 0.0001 && $customerId === null) {
            throw new HttpException(500, 'Select a customer before assigning part of the sale to credit.');
        }

        if ($creditAmount > $grandTotal + 0.0001) {
            throw new HttpException(500, 'Credit assigned cannot exceed the sale total.');
        }

        $requiredCollected = max(0, $grandTotal - $creditAmount);
        if ($nonCashCollected > $requiredCollected + 0.0001) {
            throw new HttpException(500, 'Cheque, card, and transfer payments cannot exceed the balance due.');
        }

        $cashRequired = max(0, $requiredCollected - $nonCashCollected);
        $collectedAmount = $nonCashCollected + $cashTendered;
        if ($collectedAmount + 0.0001 < $requiredCollected) {
            throw new HttpException(500, 'Collected payments are less than the balance due after credit allocation.');
        }

        return [
            'payments' => $sanitizedPayments,
            'collected_amount' => round($collectedAmount, 2),
            'credit_amount' => round($creditAmount, 2),
            'change_due' => round(max(0, $cashTendered - $cashRequired), 2),
        ];
    }

    private function lockAndValidateInventory(array $compiledItems, int $branchId): void
    {
        $requiredQuantities = [];
        foreach ($compiledItems as $line) {
            if ((int) ($line['track_stock'] ?? 0) !== 1) {
                continue;
            }

            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $requiredQuantities[$productId] = ($requiredQuantities[$productId] ?? 0) + (float) ($line['quantity'] ?? 0);
        }

        if ($requiredQuantities === []) {
            return;
        }

        $productIds = array_keys($requiredQuantities);
        sort($productIds);
        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));

        $statement = $this->db->prepare(
            "SELECT p.id, p.name, COALESCE(i.quantity_on_hand, 0) AS stock_quantity
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
             WHERE p.id IN ($placeholders)
             FOR UPDATE"
        );
        $statement->execute(array_merge([$branchId], $productIds));
        $rows = $statement->fetchAll();
        $lockedInventory = [];

        foreach ($rows as $row) {
            $lockedInventory[(int) ($row['id'] ?? 0)] = $row;
        }

        foreach ($requiredQuantities as $productId => $requiredQuantity) {
            $inventory = $lockedInventory[$productId] ?? null;
            if ($inventory === null) {
                throw new HttpException(500, 'Unable to lock stock for one or more products. Reload the POS page and try again.');
            }

            if ((float) ($inventory['stock_quantity'] ?? 0) + 0.0001 < $requiredQuantity) {
                throw new HttpException(500, 'Insufficient stock for ' . (string) ($inventory['name'] ?? ('product #' . $productId)) . '.');
            }
        }
    }

    private function sanitizeDraftPayments(array $payments, ?int $customerId): array
    {
        $allowedMethods = ['cash', 'card', 'mobile_money', 'cheque', 'split', 'credit'];
        $sanitizedPayments = [];

        foreach ($payments as $payment) {
            $method = (string) ($payment['method'] ?? 'cash');
            if (!in_array($method, $allowedMethods, true)) {
                continue;
            }

            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            if ($method === 'credit' && $customerId === null) {
                throw new HttpException(500, 'Select a customer before saving part of the sale to credit.');
            }

            $reference = trim((string) ($payment['reference'] ?? '')) ?: null;
            $notes = trim((string) ($payment['notes'] ?? '')) ?: null;
            $chequeNumber = trim((string) ($payment['cheque_number'] ?? '')) ?: null;
            $chequeBank = trim((string) ($payment['cheque_bank'] ?? '')) ?: null;
            $chequeDate = trim((string) ($payment['cheque_date'] ?? '')) ?: null;

            if ($method !== 'cheque') {
                $chequeNumber = null;
                $chequeBank = null;
                $chequeDate = null;
            } elseif ($chequeDate !== null) {
                $parsedChequeDate = date_create($chequeDate);
                if ($parsedChequeDate === false) {
                    throw new HttpException(500, 'One or more cheque dates are invalid.');
                }

                $chequeDate = $parsedChequeDate->format('Y-m-d');
                $reference ??= $chequeNumber;
            }

            $sanitizedPayments[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
                'cheque_number' => $chequeNumber,
                'cheque_bank' => $chequeBank,
                'cheque_date' => $chequeDate,
            ];
        }

        return $sanitizedPayments;
    }

    private function paymentDetailColumnsReady(): bool
    {
        if (self::$paymentDetailColumnsReady !== null) {
            return self::$paymentDetailColumnsReady;
        }

        self::$paymentDetailColumnsReady = $this->columnExists('payments', 'cheque_number')
            && $this->columnExists('payments', 'cheque_bank')
            && $this->columnExists('payments', 'cheque_date');

        return self::$paymentDetailColumnsReady;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->fetch(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1',
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        ) !== null;
    }

    private function creditExposureForSale(int $saleId): float
    {
        $row = $this->fetch(
            'SELECT COALESCE(SUM(amount), 0) AS exposure
             FROM customer_credit_transactions
             WHERE sale_id = :sale_id',
            ['sale_id' => $saleId]
        );

        return max(0, round((float) ($row['exposure'] ?? 0), 2));
    }

    private function currentCustomerCreditBalance(int $customerId): float
    {
        $row = $this->fetch('SELECT credit_balance FROM customers WHERE id = :id LIMIT 1', ['id' => $customerId]);

        return max(0, round((float) ($row['credit_balance'] ?? 0), 2));
    }
    private function updateLoyalty(?int $customerId, int $saleId, float $grandTotal, int $redeemedPoints = 0): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = $this->fetch('SELECT loyalty_balance FROM customers WHERE id = :id LIMIT 1', ['id' => $customerId]);
        $newBalance = (int) ($customer['loyalty_balance'] ?? 0);

        if ($redeemedPoints > 0) {
            if ($redeemedPoints > $newBalance) {
                throw new HttpException(500, 'Customer loyalty balance changed before checkout could finish.');
            }

            $newBalance -= $redeemedPoints;
            $this->db->prepare(
                'INSERT INTO loyalty_points (customer_id, sale_id, points, transaction_type, balance_after, notes, created_at)
                 VALUES (:customer_id, :sale_id, :points, "redeem", :balance_after, :notes, NOW())'
            )->execute([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'points' => $redeemedPoints,
                'balance_after' => $newBalance,
                'notes' => 'Redeemed during POS checkout',
            ]);
        }

        $pointsEarned = (int) floor($grandTotal / self::LOYALTY_EARN_RATE);
        if ($pointsEarned > 0) {
            $newBalance += $pointsEarned;
            $this->db->prepare(
                'INSERT INTO loyalty_points (customer_id, sale_id, points, transaction_type, balance_after, notes, created_at)
                 VALUES (:customer_id, :sale_id, :points, "earn", :balance_after, :notes, NOW())'
            )->execute([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'points' => $pointsEarned,
                'balance_after' => $newBalance,
                'notes' => 'Auto-earned from POS sale',
            ]);
        }

        $this->db->prepare('UPDATE customers SET loyalty_balance = :balance, updated_at = NOW() WHERE id = :id')
            ->execute(['balance' => $newBalance, 'id' => $customerId]);
    }

    private function reverseLoyaltyOnVoid(array $sale): void
    {
        $customerId = isset($sale['customer_id']) && $sale['customer_id'] !== null ? (int) $sale['customer_id'] : null;
        if ($customerId === null) {
            return;
        }

        $entries = $this->fetchAll(
            'SELECT * FROM loyalty_points WHERE sale_id = :sale_id ORDER BY id ASC',
            ['sale_id' => $sale['id']]
        );

        if ($entries === []) {
            return;
        }

        $customer = $this->fetch('SELECT loyalty_balance FROM customers WHERE id = :id LIMIT 1', ['id' => $customerId]);
        $balance = (int) ($customer['loyalty_balance'] ?? 0);

        foreach ($entries as $entry) {
            $points = (int) ($entry['points'] ?? 0);
            $type = (string) ($entry['transaction_type'] ?? '');

            if ($type === 'earn') {
                $balance -= $points;
                $this->db->prepare(
                    'INSERT INTO loyalty_points (customer_id, sale_id, points, transaction_type, balance_after, notes, created_at)
                     VALUES (:customer_id, :sale_id, :points, "adjustment", :balance_after, :notes, NOW())'
                )->execute([
                    'customer_id' => $customerId,
                    'sale_id' => $sale['id'],
                    'points' => -1 * abs($points),
                    'balance_after' => $balance,
                    'notes' => 'Removed earned points after sale void',
                ]);
            }

            if ($type === 'redeem') {
                $balance += abs($points);
                $this->db->prepare(
                    'INSERT INTO loyalty_points (customer_id, sale_id, points, transaction_type, balance_after, notes, created_at)
                     VALUES (:customer_id, :sale_id, :points, "adjustment", :balance_after, :notes, NOW())'
                )->execute([
                    'customer_id' => $customerId,
                    'sale_id' => $sale['id'],
                    'points' => abs($points),
                    'balance_after' => $balance,
                    'notes' => 'Restored redeemed points after sale void',
                ]);
            }
        }

        $this->db->prepare('UPDATE customers SET loyalty_balance = :balance, updated_at = NOW() WHERE id = :id')
            ->execute([
                'balance' => $balance,
                'id' => $customerId,
            ]);
    }

    private function resolveCustomerPricing(?int $customerId): ?array
    {
        if ($customerId === null) {
            return null;
        }

        $customer = $this->fetch(
            'SELECT id, loyalty_balance, special_pricing_type, special_pricing_value
             FROM customers
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $customerId]
        );

        if ($customer === null) {
            throw new HttpException(500, 'Selected customer could not be loaded for this sale.');
        }

        return $customer;
    }

    public function heldSaleResumeToken(?array $sale): string
    {
        if (!is_array($sale) || (int) ($sale['id'] ?? 0) <= 0) {
            return '';
        }

        return hash('sha256', implode('|', [
            (string) ($sale['id'] ?? ''),
            (string) ($sale['status'] ?? ''),
            (string) ($sale['updated_at'] ?? ''),
            (string) ($sale['held_until'] ?? ''),
            number_format((float) ($sale['grand_total'] ?? 0), 2, '.', ''),
        ]));
    }

    private function resolveEditableHeldSale(?int $heldSaleId, int $branchId, ?string $heldSaleToken = null): ?array
    {
        if ($heldSaleId === null) {
            return null;
        }

        $existing = $this->fetch(
            'SELECT *
             FROM sales
             WHERE id = :id
             LIMIT 1
             FOR UPDATE',
            ['id' => $heldSaleId]
        );

        if ($existing === null || (int) ($existing['branch_id'] ?? 0) !== $branchId) {
            throw new HttpException(404, 'The selected held sale could not be found for this branch.');
        }

        if ((string) ($existing['status'] ?? '') !== 'held') {
            throw new HttpException(500, 'Only held sales can be resumed or updated from the POS.');
        }

        $expectedToken = trim((string) $heldSaleToken);
        if ($expectedToken !== '' && !hash_equals($this->heldSaleResumeToken($existing), $expectedToken)) {
            throw new HttpException(409, 'This held sale changed on another register or tab. Reload it before continuing.');
        }

        return $existing;
    }

    private function applyCustomerPricing(float $basePrice, ?array $customer): float
    {
        if ($customer === null) {
            return $basePrice;
        }

        $pricingType = (string) ($customer['special_pricing_type'] ?? 'none');
        $pricingValue = (float) ($customer['special_pricing_value'] ?? 0);

        return match ($pricingType) {
            'percentage' => max(0, $basePrice - ($basePrice * ($pricingValue / 100))),
            'fixed' => max(0, $basePrice - $pricingValue),
            default => $basePrice,
        };
    }

    private function generateSaleNumber(string $prefix): string
    {
        return $prefix . '-' . date('YmdHis') . '-' . random_int(100, 999);
    }
}
