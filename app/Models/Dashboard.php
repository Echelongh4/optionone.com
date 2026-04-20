<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Dashboard extends Model
{
    protected string $table = 'sales';

    public function salesSummary(?int $branchId = null): array
    {
        $daily = $this->saleSummaryForPeriod(
            'DATE(completed_at) = CURDATE()',
            $branchId
        );
        $weekly = $this->saleSummaryForPeriod(
            'YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)',
            $branchId
        );
        $monthly = $this->saleSummaryForPeriod(
            'YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())',
            $branchId
        );

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    public function salesComparisons(?int $branchId = null): array
    {
        $dailyCurrent = $this->saleSummaryForPeriod(
            'DATE(completed_at) = CURDATE()',
            $branchId
        );
        $dailyPrevious = $this->saleSummaryForPeriod(
            'DATE(completed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
            $branchId
        );

        $weeklyCurrent = $this->saleSummaryForPeriod(
            'YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)',
            $branchId
        );
        $weeklyPrevious = $this->saleSummaryForPeriod(
            'YEARWEEK(completed_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)',
            $branchId
        );

        $monthlyCurrent = $this->saleSummaryForPeriod(
            'YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())',
            $branchId
        );
        $monthlyPrevious = $this->saleSummaryForPeriod(
            'YEAR(completed_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
             AND MONTH(completed_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))',
            $branchId
        );

        return [
            'daily' => $this->comparisonSnapshot($dailyCurrent, $dailyPrevious),
            'weekly' => $this->comparisonSnapshot($weeklyCurrent, $weeklyPrevious),
            'monthly' => $this->comparisonSnapshot($monthlyCurrent, $monthlyPrevious),
        ];
    }

    public function topSellingProducts(?int $branchId = null, string $period = '30_days', int $limit = 7): array
    {
        $dateClause = match ($period) {
            'today' => 'DATE(s.completed_at) = CURDATE()',
            default => 's.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
        };

        $sql = 'SELECT si.product_name, SUM(si.quantity) AS quantity_sold, SUM(si.line_total) AS total_sales
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE s.status = "completed"
                  AND ' . $dateClause;
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' GROUP BY si.product_name
                  ORDER BY quantity_sold DESC
                  LIMIT ' . max(1, $limit);

        return $this->fetchAll($sql, $params);
    }

    public function lowStockProducts(?int $branchId = null, int $limit = 8): array
    {
        $sql = 'SELECT p.id, p.name, p.sku, p.barcode, COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand, p.low_stock_threshold
                FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND i.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' WHERE p.deleted_at IS NULL
                    AND p.track_stock = 1';

        if ($branchId !== null) {
            $sql .= ' AND p.branch_id = :product_branch_id';
            $params['product_branch_id'] = $branchId;
        }

        $sql .= ' AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold
                  ORDER BY quantity_on_hand ASC, p.name ASC
                  LIMIT ' . max(1, $limit);

        return $this->fetchAll($sql, $params);
    }

    public function recentTransactions(?int $branchId = null, int $limit = 8): array
    {
        $sql = 'SELECT s.id, s.sale_number, s.grand_total, s.status, s.completed_at, s.created_at,
                       CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                       COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name
                FROM sales s
                INNER JOIN users u ON u.id = s.user_id
                LEFT JOIN customers c ON c.id = s.customer_id';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' WHERE s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY s.created_at DESC LIMIT ' . max(1, $limit);

        return $this->fetchAll($sql, $params);
    }

    public function revenueVsExpenses(?int $branchId = null, int $days = 7): array
    {
        $days = max(2, $days);
        $intervalDays = $days - 1;

        $salesSql = 'SELECT DATE(completed_at) AS report_date, COALESCE(SUM(grand_total), 0) AS total
                     FROM sales
                     WHERE status = "completed"
                       AND completed_at >= DATE_SUB(CURDATE(), INTERVAL ' . $intervalDays . ' DAY)';
        $expenseSql = 'SELECT DATE(expense_date) AS report_date, COALESCE(SUM(amount), 0) AS total
                       FROM expenses
                       WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL ' . $intervalDays . ' DAY)
                         AND deleted_at IS NULL';
        $salesParams = [];
        $expenseParams = [];

        if ($branchId !== null) {
            $salesSql .= ' AND branch_id = :sales_branch_id';
            $expenseSql .= ' AND branch_id = :expense_branch_id';
            $salesParams['sales_branch_id'] = $branchId;
            $expenseParams['expense_branch_id'] = $branchId;
        }

        $salesSql .= ' GROUP BY DATE(completed_at)';
        $expenseSql .= ' GROUP BY DATE(expense_date)';

        $sales = $this->fetchAll($salesSql, $salesParams);
        $expenses = $this->fetchAll($expenseSql, $expenseParams);

        $salesMap = [];
        foreach ($sales as $row) {
            $salesMap[$row['report_date']] = (float) $row['total'];
        }

        $expenseMap = [];
        foreach ($expenses as $row) {
            $expenseMap[$row['report_date']] = (float) $row['total'];
        }

        $labels = [];
        $revenue = [];
        $expenseTotals = [];

        for ($offset = $intervalDays; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $labels[] = date('M d', strtotime($date));
            $revenue[] = $salesMap[$date] ?? 0;
            $expenseTotals[] = $expenseMap[$date] ?? 0;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'expenses' => $expenseTotals,
        ];
    }

    public function expenseSummary(?int $branchId = null): array
    {
        return [
            'daily' => $this->expenseTotalForPeriod('DATE(expense_date) = CURDATE()', $branchId),
            'weekly' => $this->expenseTotalForPeriod('YEARWEEK(expense_date, 1) = YEARWEEK(CURDATE(), 1)', $branchId),
            'monthly' => $this->expenseTotalForPeriod(
                'YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())',
                $branchId
            ),
        ];
    }

    public function hourlySales(?int $branchId = null): array
    {
        $sql = 'SELECT HOUR(completed_at) AS hour_slot,
                       COUNT(*) AS total_sales,
                       COALESCE(SUM(grand_total), 0) AS revenue
                FROM sales
                WHERE status = "completed"
                  AND DATE(completed_at) = CURDATE()';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' GROUP BY HOUR(completed_at)';

        $rows = $this->fetchAll($sql, $params);
        $revenueMap = [];
        $salesMap = [];

        foreach ($rows as $row) {
            $hour = (int) ($row['hour_slot'] ?? 0);
            $revenueMap[$hour] = (float) ($row['revenue'] ?? 0);
            $salesMap[$hour] = (int) ($row['total_sales'] ?? 0);
        }

        $labels = [];
        $revenue = [];
        $sales = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = date('g A', strtotime(sprintf('%02d:00:00', $hour)));
            $revenue[] = $revenueMap[$hour] ?? 0;
            $sales[] = $salesMap[$hour] ?? 0;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'sales' => $sales,
        ];
    }

    public function paymentMethodBreakdown(?int $branchId = null, int $days = 30): array
    {
        $days = max(1, $days);
        $sql = 'SELECT p.payment_method,
                       COUNT(*) AS payment_count,
                       COALESCE(SUM(p.amount), 0) AS total_amount
                FROM payments p
                INNER JOIN sales s ON s.id = p.sale_id
                WHERE s.status IN ("completed", "partial_return", "refunded")
                  AND p.paid_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' GROUP BY p.payment_method
                  ORDER BY total_amount DESC';

        $rows = $this->fetchAll($sql, $params);

        return [
            'labels' => array_map(
                static fn (array $row): string => ucwords(str_replace('_', ' ', (string) ($row['payment_method'] ?? 'Unknown'))),
                $rows
            ),
            'totals' => array_map(static fn (array $row): float => (float) ($row['total_amount'] ?? 0), $rows),
            'counts' => array_map(static fn (array $row): int => (int) ($row['payment_count'] ?? 0), $rows),
        ];
    }

    public function inventoryHealth(?int $branchId = null): array
    {
        $sql = 'SELECT COUNT(*) AS tracked_products,
                       SUM(CASE WHEN COALESCE(i.quantity_on_hand, 0) <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                       SUM(CASE WHEN COALESCE(i.quantity_on_hand, 0) > 0 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN 1 ELSE 0 END) AS low_stock_count,
                       SUM(CASE WHEN COALESCE(i.quantity_on_hand, 0) > p.low_stock_threshold THEN 1 ELSE 0 END) AS healthy_count,
                       COALESCE(SUM(COALESCE(i.quantity_on_hand, 0) * CASE WHEN COALESCE(i.average_cost, 0) > 0 THEN i.average_cost ELSE p.cost_price END), 0) AS stock_value
                FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND i.branch_id = :inventory_branch_id';
            $params['inventory_branch_id'] = $branchId;
        }

        $sql .= ' WHERE p.deleted_at IS NULL
                    AND p.track_stock = 1';

        if ($branchId !== null) {
            $sql .= ' AND p.branch_id = :product_branch_id';
            $params['product_branch_id'] = $branchId;
        }

        return $this->fetch($sql, $params) ?? [
            'tracked_products' => 0,
            'out_of_stock_count' => 0,
            'low_stock_count' => 0,
            'healthy_count' => 0,
            'stock_value' => 0,
        ];
    }

    public function operationsSnapshot(?int $branchId = null): array
    {
        $purchaseOrderSql = 'SELECT COUNT(*) AS open_purchase_orders
                             FROM purchase_orders
                             WHERE deleted_at IS NULL
                               AND status IN ("draft", "ordered", "partial_received")';
        $purchaseOrderParams = [];
        if ($branchId !== null) {
            $purchaseOrderSql .= ' AND branch_id = :branch_id';
            $purchaseOrderParams['branch_id'] = $branchId;
        }

        $transfersSql = 'SELECT COUNT(*) AS in_transit_transfers
                         FROM stock_transfers
                         WHERE status = "in_transit"';
        $transferParams = [];
        if ($branchId !== null) {
            $transfersSql .= ' AND (source_branch_id = :transfer_source_branch_id OR destination_branch_id = :transfer_destination_branch_id)';
            $transferParams['transfer_source_branch_id'] = $branchId;
            $transferParams['transfer_destination_branch_id'] = $branchId;
        }

        $voidsSql = 'SELECT COUNT(*) AS pending_void_requests
                     FROM sale_void_requests svr
                     INNER JOIN sales s ON s.id = svr.sale_id
                     WHERE svr.status = "pending"';
        $voidParams = [];
        if ($branchId !== null) {
            $voidsSql .= ' AND s.branch_id = :void_branch_id';
            $voidParams['void_branch_id'] = $branchId;
        }

        $returnsSql = 'SELECT COUNT(*) AS returns_last_7_days,
                              COALESCE(SUM(r.total_refund), 0) AS refund_total_last_7_days
                       FROM returns r
                       INNER JOIN sales s ON s.id = r.sale_id
                       WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
        $returnParams = [];
        if ($branchId !== null) {
            $returnsSql .= ' AND s.branch_id = :return_branch_id';
            $returnParams['return_branch_id'] = $branchId;
        }

        $purchaseOrders = $this->fetch($purchaseOrderSql, $purchaseOrderParams) ?? ['open_purchase_orders' => 0];
        $transfers = $this->fetch($transfersSql, $transferParams) ?? ['in_transit_transfers' => 0];
        $voidRequests = $this->fetch($voidsSql, $voidParams) ?? ['pending_void_requests' => 0];
        $returns = $this->fetch($returnsSql, $returnParams) ?? [
            'returns_last_7_days' => 0,
            'refund_total_last_7_days' => 0,
        ];

        return [
            'open_purchase_orders' => (int) ($purchaseOrders['open_purchase_orders'] ?? 0),
            'in_transit_transfers' => (int) ($transfers['in_transit_transfers'] ?? 0),
            'pending_void_requests' => (int) ($voidRequests['pending_void_requests'] ?? 0),
            'returns_last_7_days' => (int) ($returns['returns_last_7_days'] ?? 0),
            'refund_total_last_7_days' => (float) ($returns['refund_total_last_7_days'] ?? 0),
        ];
    }

    public function todayExpenseTotal(?int $branchId = null): float
    {
        return (float) ($this->expenseSummary($branchId)['daily'] ?? 0);
    }

    public function creditSnapshot(?int $branchId = null): array
    {
        $customerWhere = ['c.deleted_at IS NULL'];
        $customerParams = [];

        if ($branchId !== null) {
            $customerWhere[] = 'c.branch_id = :credit_branch_id';
            $customerParams['credit_branch_id'] = $branchId;
        }

        $current = $this->fetch(
            'SELECT COUNT(*) AS customers_on_credit,
                    COALESCE(SUM(c.credit_balance), 0) AS outstanding_balance
             FROM customers c
             WHERE ' . implode(' AND ', $customerWhere) . '
               AND c.credit_balance > 0',
            $customerParams
        ) ?? [
            'customers_on_credit' => 0,
            'outstanding_balance' => 0,
        ];

        $activityWhere = ['c.deleted_at IS NULL', 'cct.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
        $activityParams = [];

        if ($branchId !== null) {
            $activityWhere[] = 'c.branch_id = :credit_activity_branch_id';
            $activityParams['credit_activity_branch_id'] = $branchId;
        }

        $activity = $this->fetch(
            'SELECT COALESCE(SUM(CASE WHEN cct.amount > 0 THEN cct.amount ELSE 0 END), 0) AS issued_last_7_days,
                    COALESCE(SUM(CASE WHEN cct.amount < 0 THEN ABS(cct.amount) ELSE 0 END), 0) AS recovered_last_7_days
             FROM customer_credit_transactions cct
             INNER JOIN customers c ON c.id = cct.customer_id
             WHERE ' . implode(' AND ', $activityWhere),
            $activityParams
        ) ?? [
            'issued_last_7_days' => 0,
            'recovered_last_7_days' => 0,
        ];

        return [
            'customers_on_credit' => (int) ($current['customers_on_credit'] ?? 0),
            'outstanding_balance' => (float) ($current['outstanding_balance'] ?? 0),
            'issued_last_7_days' => (float) ($activity['issued_last_7_days'] ?? 0),
            'recovered_last_7_days' => (float) ($activity['recovered_last_7_days'] ?? 0),
        ];
    }

    public function dailySummaryPayload(?int $branchId = null): array
    {
        $salesSummary = $this->salesSummary($branchId);
        $creditSnapshot = $this->creditSnapshot($branchId);
        $lowStock = $this->lowStockProducts($branchId);

        return [
            'daily_sales_count' => (int) ($salesSummary['daily']['total_sales'] ?? 0),
            'daily_revenue' => (float) ($salesSummary['daily']['revenue'] ?? 0),
            'daily_expenses' => $this->todayExpenseTotal($branchId),
            'customers_on_credit' => (int) ($creditSnapshot['customers_on_credit'] ?? 0),
            'outstanding_credit' => (float) ($creditSnapshot['outstanding_balance'] ?? 0),
            'low_stock_count' => count($lowStock),
            'top_products' => $this->topSellingProducts($branchId, 'today', 5),
            'report_date' => date('Y-m-d'),
        ];
    }

    private function saleSummaryForPeriod(string $periodClause, ?int $branchId = null): array
    {
        $sql = 'SELECT COUNT(*) AS total_sales, COALESCE(SUM(grand_total), 0) AS revenue
                FROM sales
                WHERE status = "completed"
                  AND ' . $periodClause;
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        return $this->fetch($sql, $params) ?? [
            'total_sales' => 0,
            'revenue' => 0,
        ];
    }

    private function expenseTotalForPeriod(string $periodClause, ?int $branchId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) AS total
                FROM expenses
                WHERE deleted_at IS NULL
                  AND ' . $periodClause;
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $row = $this->fetch($sql, $params);

        return (float) ($row['total'] ?? 0);
    }

    private function comparisonSnapshot(array $current, array $previous): array
    {
        $currentRevenue = (float) ($current['revenue'] ?? 0);
        $previousRevenue = (float) ($previous['revenue'] ?? 0);
        $currentSales = (int) ($current['total_sales'] ?? 0);
        $previousSales = (int) ($previous['total_sales'] ?? 0);

        return [
            'current_revenue' => $currentRevenue,
            'previous_revenue' => $previousRevenue,
            'revenue_change_pct' => $this->percentageChange($currentRevenue, $previousRevenue),
            'current_sales' => $currentSales,
            'previous_sales' => $previousSales,
            'sales_change_pct' => $this->percentageChange((float) $currentSales, (float) $previousSales),
        ];
    }

    private function percentageChange(float $current, float $previous): float
    {
        if (abs($previous) < 0.00001) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
