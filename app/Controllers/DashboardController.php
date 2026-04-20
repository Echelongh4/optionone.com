<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Branch;
use App\Models\Dashboard;
use App\Services\OperationsEmailService;

class DashboardController extends Controller
{
    public function home(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::isPlatformAdmin() ? 'platform' : 'dashboard');
        }

        $this->redirect('login');
    }

    public function index(Request $request): void
    {
        $branchId = $this->branchId();
        $payload = $this->dashboardPayload($branchId);

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'breadcrumbs' => ['Dashboard'],
            'summary' => $payload['summary'],
            'comparisons' => $payload['comparisons'],
            'analytics' => $payload['analytics'],
            'creditSnapshot' => $payload['creditSnapshot'],
            'inventoryHealth' => $payload['inventoryHealth'],
            'operations' => $payload['operations'],
            'topProducts' => $payload['topProducts'],
            'lowStock' => $payload['lowStock'],
            'recentTransactions' => $payload['recentTransactions'],
            'revenueExpenses' => $payload['revenueExpenses'],
            'hourlySales' => $payload['hourlySales'],
            'paymentMethods' => $payload['paymentMethods'],
            'dashboardPayload' => $payload,
        ]);
    }

    public function live(Request $request): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->dashboardPayload($this->branchId()));
    }

    public function emailDailySummary(Request $request): void
    {
        $branchId = $this->branchId();
        $branch = (new Branch())->find($branchId);
        $dashboard = new Dashboard();
        $operationsEmailService = new OperationsEmailService();

        if (!$operationsEmailService->dailySummaryEnabled()) {
            Session::flash('warning', 'Daily summary email is disabled in Settings.');
            $this->redirect('dashboard');
        }

        $payload = $dashboard->dailySummaryPayload($branchId);
        $payload['branch_name'] = (string) ($branch['name'] ?? 'Primary branch');

        $sentCount = $operationsEmailService->sendDailySummary($payload, $branchId);

        if ($sentCount > 0) {
            Session::flash('success', 'Daily summary emailed to ' . $sentCount . ' recipients.');
        } else {
            Session::flash('warning', 'Daily summary email could not be delivered. Check mail settings and recipient addresses.');
        }

        $this->redirect('dashboard');
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function dashboardPayload(int $branchId): array
    {
        $dashboard = new Dashboard();
        $summary = $dashboard->salesSummary($branchId);
        $comparisons = $dashboard->salesComparisons($branchId);
        $expenseSummary = $dashboard->expenseSummary($branchId);
        $creditSnapshot = $dashboard->creditSnapshot($branchId);
        $inventoryHealth = $dashboard->inventoryHealth($branchId);
        $operations = $dashboard->operationsSnapshot($branchId);
        $topProducts = $dashboard->topSellingProducts($branchId, '30_days', 7);
        $lowStock = $dashboard->lowStockProducts($branchId, 6);
        $recentTransactions = $dashboard->recentTransactions($branchId, 8);
        $revenueExpenses = $dashboard->revenueVsExpenses($branchId, 14);
        $hourlySales = $dashboard->hourlySales($branchId);
        $paymentMethods = $dashboard->paymentMethodBreakdown($branchId, 30);

        $dailyRevenue = (float) ($summary['daily']['revenue'] ?? 0);
        $weeklyRevenue = (float) ($summary['weekly']['revenue'] ?? 0);
        $monthlyRevenue = (float) ($summary['monthly']['revenue'] ?? 0);
        $monthlySalesCount = (int) ($summary['monthly']['total_sales'] ?? 0);

        return [
            'meta' => [
                'currency_label' => currency_symbol(),
                'branch_name' => (string) ((current_user()['branch_name'] ?? null) ?: ((new Branch())->find($branchId)['name'] ?? 'Main Branch')),
                'generated_at' => date(DATE_ATOM),
                'refresh_interval_ms' => 60000,
            ],
            'summary' => $summary,
            'comparisons' => $comparisons,
            'analytics' => [
                'average_ticket' => $monthlySalesCount > 0 ? $monthlyRevenue / $monthlySalesCount : 0,
                'today_expenses' => (float) ($expenseSummary['daily'] ?? 0),
                'weekly_expenses' => (float) ($expenseSummary['weekly'] ?? 0),
                'monthly_expenses' => (float) ($expenseSummary['monthly'] ?? 0),
                'today_net' => $dailyRevenue - (float) ($expenseSummary['daily'] ?? 0),
                'weekly_net' => $weeklyRevenue - (float) ($expenseSummary['weekly'] ?? 0),
                'monthly_net' => $monthlyRevenue - (float) ($expenseSummary['monthly'] ?? 0),
                'inventory_value' => (float) ($inventoryHealth['stock_value'] ?? 0),
                'recent_transaction_count' => count($recentTransactions),
            ],
            'creditSnapshot' => $creditSnapshot,
            'inventoryHealth' => $inventoryHealth,
            'operations' => $operations,
            'topProducts' => $topProducts,
            'lowStock' => $lowStock,
            'recentTransactions' => $recentTransactions,
            'revenueExpenses' => $revenueExpenses,
            'hourlySales' => $hourlySales,
            'paymentMethods' => $paymentMethods,
        ];
    }
}
