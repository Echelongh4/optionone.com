<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportExportService;
use DateTimeImmutable;

class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $filters = $this->normalizedFilters($request);
        $reportModel = new Report();
        $branchId = $this->branchId();
        $userModel = new User();
        $cashiers = array_values(array_filter(
            $userModel->allActive(),
            static function (array $user) use ($branchId): bool {
                $matchesBranch = $user['branch_id'] === null || (int) $user['branch_id'] === $branchId;
                return $matchesBranch && in_array($user['role_name'], ['Super Admin', 'Admin', 'Manager', 'Cashier'], true);
            }
        ));
        $summary = $reportModel->summary($filters, $branchId);
        $trend = $reportModel->performanceTrend($filters, $branchId);
        $topProducts = $reportModel->topProducts($filters, $branchId);
        $cashierPerformance = $reportModel->cashierPerformance($filters, $branchId);
        $taxSummary = $reportModel->taxSummary($filters, $branchId);
        $expenseBreakdown = $reportModel->expenseBreakdown($filters, $branchId);
        $receivablesSummary = $reportModel->receivablesSummary($filters, $branchId);
        $receivablesLedger = $reportModel->receivablesLedger($filters, $branchId);
        $inventorySnapshot = $reportModel->inventorySnapshot($branchId);
        $selectedCashierName = $this->resolveCashierName($filters['cashier_id'], $cashiers);
        $reportingDays = $this->reportingDays($filters['date_from'], $filters['date_to']);
        $topCashier = $cashierPerformance[0] ?? null;
        $topProduct = $topProducts[0] ?? null;
        $peakNetSales = !empty($trend['net_sales']) ? max(array_map('floatval', $trend['net_sales'])) : 0.0;
        $lowStockItems = count(array_filter(
            $inventorySnapshot,
            static fn (array $item): bool => (string) ($item['stock_state'] ?? 'normal') === 'low'
        ));
        $averageTicket = (int) ($summary['total_transactions'] ?? 0) > 0
            ? (float) ($summary['net_sales'] ?? 0) / max((int) ($summary['total_transactions'] ?? 0), 1)
            : 0.0;
        $refundRate = (float) ($summary['gross_sales'] ?? 0) > 0
            ? ((float) ($summary['refunds_total'] ?? 0) / max((float) ($summary['gross_sales'] ?? 0), 0.01)) * 100
            : 0.0;

        $this->render('reports/index', [
            'title' => 'Reports',
            'breadcrumbs' => ['Dashboard', 'Reports'],
            'filters' => $filters,
            'cashiers' => $cashiers,
            'summary' => $summary,
            'trend' => $trend,
            'topProducts' => $topProducts,
            'cashierPerformance' => $cashierPerformance,
            'taxSummary' => $taxSummary,
            'expenseBreakdown' => $expenseBreakdown,
            'receivablesSummary' => $receivablesSummary,
            'receivablesLedger' => $receivablesLedger,
            'inventorySnapshot' => $inventorySnapshot,
            'reportContext' => [
                'branch_name' => (string) (current_user()['branch_name'] ?? 'Main Branch'),
                'selected_cashier_name' => $selectedCashierName,
                'reporting_days' => $reportingDays,
                'average_ticket' => $averageTicket,
                'refund_rate' => $refundRate,
                'peak_net_sales' => $peakNetSales,
                'top_cashier_name' => (string) ($topCashier['cashier_name'] ?? 'No cashier data'),
                'top_cashier_net_sales' => (float) ($topCashier['net_sales'] ?? 0),
                'top_product_name' => (string) ($topProduct['product_name'] ?? 'No product data'),
                'top_product_revenue' => (float) ($topProduct['net_revenue'] ?? 0),
                'low_stock_items' => $lowStockItems,
            ],
        ]);
    }

    public function export(Request $request): void
    {
        $filters = $this->normalizedFilters($request);
        $type = strtolower(trim((string) $request->query('type', 'sales')));
        $format = $this->normalizedFormat((string) $request->query('format', 'csv'));
        $allowedTypes = ['sales', 'products', 'cashiers', 'tax', 'inventory', 'expenses', 'receivables'];

        if (!in_array($type, $allowedTypes, true)) {
            throw new HttpException(404, 'Unknown report export type.');
        }

        $dataset = (new Report())->exportDataset($type, $filters, $this->branchId());

        (new ReportExportService())->download($dataset, $format, [
            'title' => $this->reportTitle($type),
            'filters' => $filters,
            'branch_name' => (string) (current_user()['branch_name'] ?? 'Main Branch'),
            'cashier_name' => $this->resolveCashierName((string) ($filters['cashier_id'] ?? '')),
            'generated_by' => (string) (current_user()['full_name'] ?? 'System User'),
        ]);
    }

    private function normalizedFilters(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $defaultFrom = $today->modify('-29 days')->format('Y-m-d');
        $defaultTo = $today->format('Y-m-d');
        $dateFrom = trim((string) $request->query('date_from', $defaultFrom));
        $dateTo = trim((string) $request->query('date_to', $defaultTo));
        $cashierId = trim((string) $request->query('cashier_id', ''));

        if (!$this->isValidDate($dateFrom)) {
            $dateFrom = $defaultFrom;
        }

        if (!$this->isValidDate($dateTo)) {
            $dateTo = $defaultTo;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($cashierId !== '' && !ctype_digit($cashierId)) {
            $cashierId = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'cashier_id' => $cashierId,
        ];
    }

    private function normalizedFormat(string $value): string
    {
        $format = strtolower(trim($value));

        return match ($format) {
            'excel' => 'xlsx',
            'csv', 'xlsx', 'pdf' => $format,
            default => throw new HttpException(404, 'Unknown report export format.'),
        };
    }

    private function reportTitle(string $type): string
    {
        return match ($type) {
            'sales' => 'Sales Report',
            'products' => 'Product Sales Report',
            'cashiers' => 'Cashier Performance Report',
            'tax' => 'Tax Report',
            'inventory' => 'Inventory Report',
            'expenses' => 'Expense Report',
            'receivables' => 'Receivables Report',
            default => 'Report Export',
        };
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function resolveCashierName(string $cashierId, array $cashiers = []): string
    {
        if ($cashierId === '') {
            return 'All cashiers';
        }

        $pool = $cashiers !== [] ? $cashiers : (new User())->allActive();
        foreach ($pool as $cashier) {
            if ((string) ($cashier['id'] ?? '') === $cashierId) {
                return (string) ($cashier['full_name'] ?? trim(((string) ($cashier['first_name'] ?? '')) . ' ' . ((string) ($cashier['last_name'] ?? ''))));
            }
        }

        return 'Selected cashier';
    }

    private function reportingDays(string $dateFrom, string $dateTo): int
    {
        try {
            $start = new DateTimeImmutable($dateFrom);
            $end = new DateTimeImmutable($dateTo);
        } catch (\Throwable) {
            return 0;
        }

        return ((int) $start->diff($end)->days) + 1;
    }
}
