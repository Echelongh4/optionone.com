<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;

class Report extends Model
{
    protected string $table = 'sales';

    public function summary(array $filters, ?int $branchId = null): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');
        $summary = $this->fetch(
            'SELECT COUNT(*) AS total_transactions,
                    COALESCE(SUM(CASE WHEN s.status IN ("completed", "partial_return", "refunded") THEN s.grand_total ELSE 0 END), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(ret.total_refund, 0)), 0) AS refunds_total,
                    COALESCE(SUM(CASE WHEN s.status = "voided" THEN 1 ELSE 0 END), 0) AS voided_transactions
             FROM sales s
             LEFT JOIN (
                 SELECT sale_id, SUM(total_refund) AS total_refund
                 FROM returns
                 WHERE status = "completed"
                 GROUP BY sale_id
             ) ret ON ret.sale_id = s.id
             WHERE ' . $salesScope['where'],
            $salesScope['params']
        ) ?? [
            'total_transactions' => 0,
            'gross_sales' => 0,
            'refunds_total' => 0,
            'voided_transactions' => 0,
        ];

        $costOfGoods = $this->fetch(
            'SELECT COALESCE(SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity, 0), 0) * p.cost_price), 0) AS cogs
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             INNER JOIN products p ON p.id = si.product_id
             LEFT JOIN (
                 SELECT ri.sale_item_id, SUM(ri.quantity) AS returned_quantity
                 FROM return_items ri
                 INNER JOIN returns r ON r.id = ri.return_id
                 WHERE r.status = "completed"
                 GROUP BY ri.sale_item_id
             ) ret ON ret.sale_item_id = si.id
             WHERE ' . $salesScope['where'] . '
               AND s.status IN ("completed", "partial_return", "refunded")',
            $salesScope['params']
        ) ?? ['cogs' => 0];

        $expenseScope = $this->expenseScope($filters, $branchId, 'e');
        $expenseSummary = $this->fetch(
            'SELECT COALESCE(SUM(e.amount), 0) AS expenses_total
             FROM expenses e
             WHERE ' . $expenseScope['where'],
            $expenseScope['params']
        ) ?? ['expenses_total' => 0];

        $grossSales = (float) ($summary['gross_sales'] ?? 0);
        $refundsTotal = (float) ($summary['refunds_total'] ?? 0);
        $netSales = $grossSales - $refundsTotal;
        $cogs = (float) ($costOfGoods['cogs'] ?? 0);
        $grossProfit = $netSales - $cogs;
        $expensesTotal = (float) ($expenseSummary['expenses_total'] ?? 0);

        return [
            'total_transactions' => (int) ($summary['total_transactions'] ?? 0),
            'gross_sales' => $grossSales,
            'refunds_total' => $refundsTotal,
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses_total' => $expensesTotal,
            'operating_profit' => $grossProfit - $expensesTotal,
            'voided_transactions' => (int) ($summary['voided_transactions'] ?? 0),
        ];
    }

    public function performanceTrend(array $filters, ?int $branchId = null): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');
        $salesRows = $this->fetchAll(
            'SELECT DATE(COALESCE(s.completed_at, s.created_at)) AS report_date,
                    COALESCE(SUM(CASE WHEN s.status IN ("completed", "partial_return", "refunded") THEN s.grand_total ELSE 0 END), 0) AS total
             FROM sales s
             WHERE ' . $salesScope['where'] . '
               AND s.status IN ("completed", "partial_return", "refunded")
             GROUP BY DATE(COALESCE(s.completed_at, s.created_at))
             ORDER BY report_date ASC',
            $salesScope['params']
        );

        $returnsScope = $this->returnsScope($filters, $branchId, 'r', 's');
        $refundRows = $this->fetchAll(
            'SELECT DATE(r.created_at) AS report_date,
                    COALESCE(SUM(r.total_refund), 0) AS total
             FROM returns r
             INNER JOIN sales s ON s.id = r.sale_id
             WHERE ' . $returnsScope['where'] . '
             GROUP BY DATE(r.created_at)
             ORDER BY report_date ASC',
            $returnsScope['params']
        );

        $expenseScope = $this->expenseScope($filters, $branchId, 'e');
        $expenseRows = $this->fetchAll(
            'SELECT e.expense_date AS report_date,
                    COALESCE(SUM(e.amount), 0) AS total
             FROM expenses e
             WHERE ' . $expenseScope['where'] . '
             GROUP BY e.expense_date
             ORDER BY report_date ASC',
            $expenseScope['params']
        );

        $salesMap = [];
        foreach ($salesRows as $row) {
            $salesMap[$row['report_date']] = (float) $row['total'];
        }

        $refundMap = [];
        foreach ($refundRows as $row) {
            $refundMap[$row['report_date']] = (float) $row['total'];
        }

        $expenseMap = [];
        foreach ($expenseRows as $row) {
            $expenseMap[$row['report_date']] = (float) $row['total'];
        }

        $labels = [];
        $grossSales = [];
        $refunds = [];
        $expenses = [];
        $netSales = [];

        $cursor = new DateTimeImmutable($filters['date_from']);
        $end = new DateTimeImmutable($filters['date_to']);

        while ($cursor <= $end) {
            $dateKey = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M d');
            $grossValue = $salesMap[$dateKey] ?? 0;
            $refundValue = $refundMap[$dateKey] ?? 0;
            $expenseValue = $expenseMap[$dateKey] ?? 0;
            $grossSales[] = $grossValue;
            $refunds[] = $refundValue;
            $expenses[] = $expenseValue;
            $netSales[] = $grossValue - $refundValue;
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'gross_sales' => $grossSales,
            'refunds' => $refunds,
            'expenses' => $expenses,
            'net_sales' => $netSales,
        ];
    }

    public function topProducts(array $filters, ?int $branchId = null, int $limit = 8): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');
        $limit = max(1, $limit);

        return $this->fetchAll(
            'SELECT si.product_id,
                    si.product_name,
                    COALESCE(SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity, 0), 0)), 0) AS net_quantity,
                    COALESCE(SUM(COALESCE(ret.returned_quantity, 0)), 0) AS returned_quantity,
                    COALESCE(SUM(si.line_total - COALESCE(ret.refunded_total, 0)), 0) AS net_revenue
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             LEFT JOIN (
                 SELECT ri.sale_item_id,
                        SUM(ri.quantity) AS returned_quantity,
                        SUM(ri.line_total) AS refunded_total
                 FROM return_items ri
                 INNER JOIN returns r ON r.id = ri.return_id
                 WHERE r.status = "completed"
                 GROUP BY ri.sale_item_id
             ) ret ON ret.sale_item_id = si.id
             WHERE ' . $salesScope['where'] . '
               AND s.status IN ("completed", "partial_return", "refunded")
             GROUP BY si.product_id, si.product_name
             HAVING net_quantity > 0 OR net_revenue > 0
             ORDER BY net_revenue DESC, net_quantity DESC
             LIMIT ' . $limit,
            $salesScope['params']
        );
    }

    public function cashierPerformance(array $filters, ?int $branchId = null): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');

        return $this->fetchAll(
            'SELECT u.id,
                    CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                    COUNT(s.id) AS total_sales,
                    COALESCE(SUM(CASE WHEN s.status IN ("completed", "partial_return", "refunded") THEN s.grand_total ELSE 0 END), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(ret.total_refund, 0)), 0) AS refunds_total,
                    COALESCE(SUM(CASE WHEN s.status = "voided" THEN 1 ELSE 0 END), 0) AS voided_sales,
                    COALESCE(SUM(CASE WHEN s.status IN ("completed", "partial_return", "refunded") THEN s.grand_total ELSE 0 END), 0) - COALESCE(SUM(COALESCE(ret.total_refund, 0)), 0) AS net_sales
             FROM sales s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN (
                 SELECT sale_id, SUM(total_refund) AS total_refund
                 FROM returns
                 WHERE status = "completed"
                 GROUP BY sale_id
             ) ret ON ret.sale_id = s.id
             WHERE ' . $salesScope['where'] . '
             GROUP BY u.id, cashier_name
             ORDER BY net_sales DESC, total_sales DESC',
            $salesScope['params']
        );
    }

    public function taxSummary(array $filters, ?int $branchId = null): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');

        return $this->fetchAll(
            'SELECT COALESCE(t.name, CONCAT("Tax ", CAST(si.tax_rate AS CHAR), "%")) AS tax_name,
                    si.tax_rate,
                    COALESCE(SUM((si.line_total - si.tax_total) - COALESCE(ret.refunded_base, 0)), 0) AS taxable_sales,
                    COALESCE(SUM(si.tax_total - COALESCE(ret.refunded_tax, 0)), 0) AS tax_collected
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             LEFT JOIN products p ON p.id = si.product_id
             LEFT JOIN taxes t ON t.id = p.tax_id
             LEFT JOIN (
                 SELECT ri.sale_item_id,
                        SUM(ri.tax_total) AS refunded_tax,
                        SUM(ri.line_total - ri.tax_total) AS refunded_base
                 FROM return_items ri
                 INNER JOIN returns r ON r.id = ri.return_id
                 WHERE r.status = "completed"
                 GROUP BY ri.sale_item_id
             ) ret ON ret.sale_item_id = si.id
             WHERE ' . $salesScope['where'] . '
               AND s.status IN ("completed", "partial_return", "refunded")
             GROUP BY tax_name, si.tax_rate
             ORDER BY tax_collected DESC, tax_name ASC',
            $salesScope['params']
        );
    }
    public function expenseBreakdown(array $filters, ?int $branchId = null): array
    {
        $expenseScope = $this->expenseScope($filters, $branchId, 'e');

        return $this->fetchAll(
            'SELECT ec.name AS category_name,
                    COUNT(e.id) AS total_entries,
                    COALESCE(SUM(e.amount), 0) AS total_amount
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE ' . $expenseScope['where'] . '
             GROUP BY ec.id, ec.name
             ORDER BY total_amount DESC, category_name ASC',
            $expenseScope['params']
        );
    }

    public function receivablesSummary(array $filters, ?int $branchId = null): array
    {
        $customerClauses = ['c.deleted_at IS NULL'];
        $customerParams = [];

        if ($branchId !== null) {
            $customerClauses[] = 'c.branch_id = :receivable_customer_branch_id';
            $customerParams['receivable_customer_branch_id'] = $branchId;
        }

        $current = $this->fetch(
            'SELECT COUNT(*) AS customers_on_credit,
                    COALESCE(SUM(c.credit_balance), 0) AS outstanding_balance
             FROM customers c
             WHERE ' . implode(' AND ', $customerClauses) . '
               AND c.credit_balance > 0',
            $customerParams
        ) ?? [
            'customers_on_credit' => 0,
            'outstanding_balance' => 0,
        ];

        $activityClauses = ['c.deleted_at IS NULL'];
        $activityParams = [];

        if ($branchId !== null) {
            $activityClauses[] = 'c.branch_id = :receivable_activity_branch_id';
            $activityParams['receivable_activity_branch_id'] = $branchId;
        }

        if (($filters['cashier_id'] ?? '') !== '') {
            $activityClauses[] = 'cct.user_id = :receivable_activity_user_id';
            $activityParams['receivable_activity_user_id'] = (int) $filters['cashier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $activityClauses[] = 'DATE(cct.created_at) >= :receivable_activity_date_from';
            $activityParams['receivable_activity_date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $activityClauses[] = 'DATE(cct.created_at) <= :receivable_activity_date_to';
            $activityParams['receivable_activity_date_to'] = $filters['date_to'];
        }

        $activity = $this->fetch(
            'SELECT COALESCE(SUM(CASE WHEN cct.amount > 0 THEN cct.amount ELSE 0 END), 0) AS credit_issued,
                    COALESCE(SUM(CASE WHEN cct.amount < 0 THEN ABS(cct.amount) ELSE 0 END), 0) AS credit_recovered,
                    COUNT(DISTINCT CASE WHEN cct.amount > 0 THEN cct.customer_id END) AS active_credit_customers
             FROM customer_credit_transactions cct
             INNER JOIN customers c ON c.id = cct.customer_id
             WHERE ' . implode(' AND ', $activityClauses),
            $activityParams
        ) ?? [
            'credit_issued' => 0,
            'credit_recovered' => 0,
            'active_credit_customers' => 0,
        ];

        return [
            'outstanding_balance' => (float) ($current['outstanding_balance'] ?? 0),
            'customers_on_credit' => (int) ($current['customers_on_credit'] ?? 0),
            'credit_issued' => (float) ($activity['credit_issued'] ?? 0),
            'credit_recovered' => (float) ($activity['credit_recovered'] ?? 0),
            'active_credit_customers' => (int) ($activity['active_credit_customers'] ?? 0),
        ];
    }

    public function receivablesLedger(array $filters, ?int $branchId = null): array
    {
        $whereClauses = ['c.deleted_at IS NULL'];
        $joinClauses = ['cct.customer_id = c.id'];
        $params = [];

        if ($branchId !== null) {
            $whereClauses[] = 'c.branch_id = :receivable_ledger_branch_id';
            $params['receivable_ledger_branch_id'] = $branchId;
        }

        if (($filters['cashier_id'] ?? '') !== '') {
            $joinClauses[] = 'cct.user_id = :receivable_ledger_user_id';
            $params['receivable_ledger_user_id'] = (int) $filters['cashier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $joinClauses[] = 'DATE(cct.created_at) >= :receivable_ledger_date_from';
            $params['receivable_ledger_date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $joinClauses[] = 'DATE(cct.created_at) <= :receivable_ledger_date_to';
            $params['receivable_ledger_date_to'] = $filters['date_to'];
        }

        return $this->fetchAll(
            'SELECT c.id,
                    CONCAT(c.first_name, " ", c.last_name) AS customer_name,
                    c.phone,
                    c.email,
                    c.credit_balance AS current_balance,
                    COALESCE(SUM(CASE WHEN cct.amount > 0 THEN cct.amount ELSE 0 END), 0) AS charged_total,
                    COALESCE(SUM(CASE WHEN cct.amount < 0 THEN ABS(cct.amount) ELSE 0 END), 0) AS relieved_total,
                    MAX(cct.created_at) AS last_activity_at
             FROM customers c
             LEFT JOIN customer_credit_transactions cct ON ' . implode(' AND ', $joinClauses) . '
             WHERE ' . implode(' AND ', $whereClauses) . '
             GROUP BY c.id, customer_name, c.phone, c.email, c.credit_balance
             HAVING c.credit_balance > 0 OR charged_total > 0 OR relieved_total > 0
             ORDER BY c.credit_balance DESC, last_activity_at DESC, customer_name ASC',
            $params
        );
    }

    public function inventorySnapshot(?int $branchId = null, int $limit = 20): array
    {
        $limit = max(1, $limit);

        return $this->fetchAll(
            'SELECT p.sku,
                    p.name,
                    c.name AS category_name,
                    COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
                    COALESCE(i.average_cost, p.cost_price, 0) AS average_cost,
                    COALESCE(i.quantity_on_hand, 0) * COALESCE(i.average_cost, p.cost_price, 0) AS inventory_value,
                    p.low_stock_threshold,
                    CASE
                        WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN "low"
                        ELSE "normal"
                    END AS stock_state
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
             WHERE p.deleted_at IS NULL
             ORDER BY CASE
                        WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN 0
                        ELSE 1
                      END,
                      COALESCE(i.quantity_on_hand, 0) ASC,
                      p.name ASC
             LIMIT ' . $limit,
            ['branch_id' => $branchId]
        );
    }

    public function salesLedger(array $filters, ?int $branchId = null): array
    {
        $salesScope = $this->salesScope($filters, $branchId, 's');

        return $this->fetchAll(
            'SELECT DATE(COALESCE(s.completed_at, s.created_at)) AS sale_date,
                    s.sale_number,
                    s.status,
                    COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name,
                    CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                    s.subtotal,
                    s.tax_total,
                    s.grand_total,
                    s.change_due,
                    COALESCE(payments.collected_amount, 0) AS collected_amount,
                    COALESCE(payments.cash_tendered, 0) AS cash_tendered,
                    COALESCE(payments.credit_amount, 0) AS credit_amount,
                    COALESCE(payments.payment_methods, "") AS payment_methods,
                    COALESCE(payments.payment_references, "") AS payment_references,
                    COALESCE(ret.total_refund, 0) AS refund_total,
                    s.grand_total - COALESCE(ret.total_refund, 0) AS net_total
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
                        COALESCE(SUM(CASE WHEN payment_method <> "credit" THEN amount ELSE 0 END), 0) AS collected_amount,
                        COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) AS cash_tendered,
                        COALESCE(SUM(CASE WHEN payment_method = "credit" THEN amount ELSE 0 END), 0) AS credit_amount,
                        GROUP_CONCAT(DISTINCT REPLACE(payment_method, "_", " ") ORDER BY payment_method SEPARATOR ", ") AS payment_methods,
                        GROUP_CONCAT(DISTINCT NULLIF(reference, "") ORDER BY id SEPARATOR ", ") AS payment_references
                 FROM payments
                 GROUP BY sale_id
             ) payments ON payments.sale_id = s.id
             WHERE ' . $salesScope['where'] . '
             ORDER BY DATE(COALESCE(s.completed_at, s.created_at)) DESC, s.sale_number DESC',
            $salesScope['params']
        );
    }

    public function expenseLedger(array $filters, ?int $branchId = null): array
    {
        $expenseScope = $this->expenseScope($filters, $branchId, 'e');

        return $this->fetchAll(
            'SELECT e.expense_date,
                    ec.name AS category_name,
                    e.description,
                    e.amount,
                    CONCAT(u.first_name, " ", u.last_name) AS logged_by,
                    e.status
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             INNER JOIN users u ON u.id = e.user_id
             WHERE ' . $expenseScope['where'] . '
             ORDER BY e.expense_date DESC, e.created_at DESC',
            $expenseScope['params']
        );
    }

    public function exportDataset(string $type, array $filters, ?int $branchId = null): array
    {
        return match ($type) {
            'sales' => $this->salesExportDataset($filters, $branchId),
            'products' => $this->productExportDataset($filters, $branchId),
            'cashiers' => $this->cashierExportDataset($filters, $branchId),
            'tax' => $this->taxExportDataset($filters, $branchId),
            'inventory' => $this->inventoryExportDataset($branchId),
            'expenses' => $this->expenseExportDataset($filters, $branchId),
            'receivables' => $this->receivablesExportDataset($filters, $branchId),
        };
    }

    private function salesExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['sale_date'],
                $row['sale_number'],
                $row['status'],
                $row['customer_name'],
                $row['cashier_name'],
                number_format((float) $row['subtotal'], 2, '.', ''),
                number_format((float) $row['tax_total'], 2, '.', ''),
                number_format((float) $row['grand_total'], 2, '.', ''),
                number_format((float) $row['collected_amount'], 2, '.', ''),
                number_format((float) $row['cash_tendered'], 2, '.', ''),
                number_format((float) $row['credit_amount'], 2, '.', ''),
                number_format((float) $row['change_due'], 2, '.', ''),
                (string) ($row['payment_methods'] ?? ''),
                (string) ($row['payment_references'] ?? ''),
                number_format((float) $row['refund_total'], 2, '.', ''),
                number_format((float) $row['net_total'], 2, '.', ''),
            ];
        }, $this->salesLedger($filters, $branchId));

        return [
            'filename' => 'sales-report-' . date('Ymd-His') . '.csv',
            'title' => 'Sales Report',
            'headers' => ['Sale Date', 'Sale Number', 'Status', 'Customer', 'Cashier', 'Subtotal', 'Tax', 'Grand Total', 'Collected', 'Cash Given', 'On Account', 'Change Due', 'Payment Methods', 'Payment References', 'Refund Total', 'Net Total'],
            'rows' => $rows,
        ];
    }

    private function productExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['product_name'],
                number_format((float) $row['net_quantity'], 2, '.', ''),
                number_format((float) $row['returned_quantity'], 2, '.', ''),
                number_format((float) $row['net_revenue'], 2, '.', ''),
            ];
        }, $this->topProducts($filters, $branchId, 100));

        return [
            'filename' => 'product-sales-report-' . date('Ymd-His') . '.csv',
            'title' => 'Product Sales Report',
            'headers' => ['Product', 'Net Quantity Sold', 'Returned Quantity', 'Net Revenue'],
            'rows' => $rows,
        ];
    }

    private function cashierExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['cashier_name'],
                (string) $row['total_sales'],
                (string) $row['voided_sales'],
                number_format((float) $row['gross_sales'], 2, '.', ''),
                number_format((float) $row['refunds_total'], 2, '.', ''),
                number_format((float) $row['net_sales'], 2, '.', ''),
            ];
        }, $this->cashierPerformance($filters, $branchId));

        return [
            'filename' => 'cashier-performance-' . date('Ymd-His') . '.csv',
            'title' => 'Cashier Performance Report',
            'headers' => ['Cashier', 'Transactions', 'Voids', 'Gross Sales', 'Refunds', 'Net Sales'],
            'rows' => $rows,
        ];
    }

    private function taxExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['tax_name'],
                number_format((float) $row['tax_rate'], 2, '.', ''),
                number_format((float) $row['taxable_sales'], 2, '.', ''),
                number_format((float) $row['tax_collected'], 2, '.', ''),
            ];
        }, $this->taxSummary($filters, $branchId));

        return [
            'filename' => 'tax-summary-' . date('Ymd-His') . '.csv',
            'title' => 'Tax Report',
            'headers' => ['Tax', 'Rate', 'Taxable Sales', 'Tax Collected'],
            'rows' => $rows,
        ];
    }

    private function inventoryExportDataset(?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['sku'],
                $row['name'],
                $row['category_name'] ?? 'Uncategorized',
                number_format((float) $row['quantity_on_hand'], 2, '.', ''),
                number_format((float) $row['average_cost'], 2, '.', ''),
                number_format((float) $row['inventory_value'], 2, '.', ''),
                $row['stock_state'],
            ];
        }, $this->inventorySnapshot($branchId, 500));

        return [
            'filename' => 'inventory-snapshot-' . date('Ymd-His') . '.csv',
            'title' => 'Inventory Report',
            'headers' => ['SKU', 'Product', 'Category', 'Quantity On Hand', 'Average Cost', 'Inventory Value', 'Stock State'],
            'rows' => $rows,
        ];
    }

    private function expenseExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['expense_date'],
                $row['category_name'],
                $row['description'],
                number_format((float) $row['amount'], 2, '.', ''),
                $row['logged_by'],
                $row['status'],
            ];
        }, $this->expenseLedger($filters, $branchId));

        return [
            'filename' => 'expense-report-' . date('Ymd-His') . '.csv',
            'title' => 'Expense Report',
            'headers' => ['Expense Date', 'Category', 'Description', 'Amount', 'Logged By', 'Status'],
            'rows' => $rows,
        ];
    }

    private function receivablesExportDataset(array $filters, ?int $branchId): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                $row['customer_name'],
                $row['phone'] ?? '',
                $row['email'] ?? '',
                number_format((float) $row['current_balance'], 2, '.', ''),
                number_format((float) $row['charged_total'], 2, '.', ''),
                number_format((float) $row['relieved_total'], 2, '.', ''),
                $row['last_activity_at'] ?? '',
            ];
        }, $this->receivablesLedger($filters, $branchId));

        return [
            'filename' => 'receivables-report-' . date('Ymd-His') . '.csv',
            'title' => 'Receivables Report',
            'headers' => ['Customer', 'Phone', 'Email', 'Current Balance', 'Charged In Period', 'Recovered In Period', 'Last Activity'],
            'rows' => $rows,
        ];
    }

    private function salesScope(array $filters, ?int $branchId, string $alias): array
    {
        $clauses = [$alias . '.deleted_at IS NULL', $alias . '.status <> "held"'];
        $params = [];

        if ($branchId !== null) {
            $clauses[] = $alias . '.branch_id = :sales_branch_id';
            $params['sales_branch_id'] = $branchId;
        }

        if (($filters['cashier_id'] ?? '') !== '') {
            $clauses[] = $alias . '.user_id = :sales_cashier_id';
            $params['sales_cashier_id'] = (int) $filters['cashier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = 'DATE(COALESCE(' . $alias . '.completed_at, ' . $alias . '.created_at)) >= :sales_date_from';
            $params['sales_date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = 'DATE(COALESCE(' . $alias . '.completed_at, ' . $alias . '.created_at)) <= :sales_date_to';
            $params['sales_date_to'] = $filters['date_to'];
        }

        return ['where' => implode(' AND ', $clauses), 'params' => $params];
    }

    private function returnsScope(array $filters, ?int $branchId, string $returnAlias, string $saleAlias): array
    {
        $clauses = [$returnAlias . '.status = "completed"', $saleAlias . '.deleted_at IS NULL'];
        $params = [];

        if ($branchId !== null) {
            $clauses[] = $saleAlias . '.branch_id = :returns_branch_id';
            $params['returns_branch_id'] = $branchId;
        }

        if (($filters['cashier_id'] ?? '') !== '') {
            $clauses[] = $saleAlias . '.user_id = :returns_cashier_id';
            $params['returns_cashier_id'] = (int) $filters['cashier_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = 'DATE(' . $returnAlias . '.created_at) >= :returns_date_from';
            $params['returns_date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = 'DATE(' . $returnAlias . '.created_at) <= :returns_date_to';
            $params['returns_date_to'] = $filters['date_to'];
        }

        return ['where' => implode(' AND ', $clauses), 'params' => $params];
    }

    private function expenseScope(array $filters, ?int $branchId, string $alias): array
    {
        $clauses = [$alias . '.deleted_at IS NULL'];
        $params = [];

        if ($branchId !== null) {
            $clauses[] = $alias . '.branch_id = :expense_branch_id';
            $params['expense_branch_id'] = $branchId;
        }

        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = $alias . '.expense_date >= :expense_date_from';
            $params['expense_date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = $alias . '.expense_date <= :expense_date_to';
            $params['expense_date_to'] = $filters['date_to'];
        }

        return ['where' => implode(' AND ', $clauses), 'params' => $params];
    }
}
