<?php
$dailyRevenue = (float) ($summary['daily']['revenue'] ?? 0);
$weeklyRevenue = (float) ($summary['weekly']['revenue'] ?? 0);
$monthlyRevenue = (float) ($summary['monthly']['revenue'] ?? 0);
$dailySalesCount = (int) ($summary['daily']['total_sales'] ?? 0);
$weeklySalesCount = (int) ($summary['weekly']['total_sales'] ?? 0);
$monthlySalesCount = (int) ($summary['monthly']['total_sales'] ?? 0);
$averageTicket = (float) ($analytics['average_ticket'] ?? 0);
$todayExpenses = (float) ($analytics['today_expenses'] ?? 0);
$todayNet = (float) ($analytics['today_net'] ?? 0);
$monthlyNet = (float) ($analytics['monthly_net'] ?? 0);
$inventoryValue = (float) ($analytics['inventory_value'] ?? 0);
$lowStockCount = count($lowStock);
$recentTransactionCount = count($recentTransactions);
$outstandingCredit = (float) ($creditSnapshot['outstanding_balance'] ?? 0);
$customersOnCredit = (int) ($creditSnapshot['customers_on_credit'] ?? 0);
$creditIssuedLast7Days = (float) ($creditSnapshot['issued_last_7_days'] ?? 0);
$creditRecoveredLast7Days = (float) ($creditSnapshot['recovered_last_7_days'] ?? 0);
$inventoryTracked = (int) ($inventoryHealth['tracked_products'] ?? 0);
$healthyCount = (int) ($inventoryHealth['healthy_count'] ?? 0);
$outOfStockCount = (int) ($inventoryHealth['out_of_stock_count'] ?? 0);
$inventoryLowStockCount = (int) ($inventoryHealth['low_stock_count'] ?? 0);
$openPurchaseOrders = (int) ($operations['open_purchase_orders'] ?? 0);
$inTransitTransfers = (int) ($operations['in_transit_transfers'] ?? 0);
$pendingVoidRequests = (int) ($operations['pending_void_requests'] ?? 0);
$returnsLast7Days = (int) ($operations['returns_last_7_days'] ?? 0);
$refundTotalLast7Days = (float) ($operations['refund_total_last_7_days'] ?? 0);
$generatedAtRaw = (string) ($dashboardPayload['meta']['generated_at'] ?? '');
$generatedAtLabel = $generatedAtRaw !== '' ? date('M d, H:i', strtotime($generatedAtRaw)) : 'Just now';
$refreshIntervalMs = max(15000, (int) ($dashboardPayload['meta']['refresh_interval_ms'] ?? 60000));
$branchName = (string) ($dashboardPayload['meta']['branch_name'] ?? (current_user()['branch_name'] ?? 'Main Branch'));
$currencyLabel = (string) ($dashboardPayload['meta']['currency_label'] ?? currency_symbol());
$healthyRate = $inventoryTracked > 0 ? ($healthyCount / $inventoryTracked) * 100 : 0;
$warningRate = $inventoryTracked > 0 ? ($inventoryLowStockCount / $inventoryTracked) * 100 : 0;
$criticalRate = $inventoryTracked > 0 ? ($outOfStockCount / $inventoryTracked) * 100 : 0;
$dashboardJsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$dashboardJson = json_encode($dashboardPayload, $dashboardJsonOptions);

$formatPercent = static function (float $value): string {
    $rounded = round($value, 1);
    if (abs($rounded) < 0.05) {
        return '0.0%';
    }

    return ($rounded > 0 ? '+' : '') . number_format($rounded, 1) . '%';
};

$trendTone = static function (float $value): string {
    if ($value > 0.05) {
        return 'positive';
    }

    if ($value < -0.05) {
        return 'negative';
    }

    return 'neutral';
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'completed' => 'status-pill--success',
        'draft', 'held' => 'status-pill--info',
        'partial_return', 'pending', 'ordered', 'partial_received' => 'status-pill--warning',
        'refunded', 'voided', 'cancelled', 'rejected' => 'status-pill--danger',
        default => 'status-pill--info',
    };
};

$renderDashboardEmpty = static function (string $title, string $copy, string $icon = 'bi-stars'): string {
    ob_start();
    ?>
    <div class="dashboard-empty">
        <span class="dashboard-empty__icon"><i class="bi <?= e($icon) ?>"></i></span>
        <strong class="dashboard-empty__title"><?= e($title) ?></strong>
        <p class="dashboard-empty__copy mb-0"><?= e($copy) ?></p>
    </div>
    <?php

    return (string) ob_get_clean();
};
?>

<div
    class="dashboard-shell"
    data-dashboard-root
    data-live-url="<?= e(url('dashboard/live')) ?>"
    data-refresh-interval="<?= e((string) $refreshIntervalMs) ?>"
    data-sales-show-url="<?= e(url('sales/show')) ?>"
    data-product-show-url="<?= e(url('products/show')) ?>"
    data-inventory-url="<?= e(url('inventory')) ?>"
>
    <script type="application/json" data-dashboard-payload><?= $dashboardJson !== false ? $dashboardJson : '{}' ?></script>

    <section class="dashboard-hero surface-card card-panel">
        <div class="dashboard-hero__main">
            <p class="eyebrow mb-2">Live Command Center</p>
            <h2 class="dashboard-hero__title">Branch performance, stock pressure, and cash movement in one workspace.</h2>
            <p class="dashboard-hero__copy">
                Monitor revenue momentum, expense drag, payment mix, receivables, and replenishment signals without opening separate reports.
            </p>
            <div class="dashboard-hero__meta">
                <span class="badge-soft"><i class="bi bi-shop me-1"></i><?= e($branchName) ?></span>
                <span class="badge-soft" data-dashboard-generated-label><i class="bi bi-clock-history me-1"></i>Updated <?= e($generatedAtLabel) ?></span>
                <span class="dashboard-sync dashboard-sync--live" data-dashboard-sync-badge data-state="live">
                    <span class="dashboard-sync__dot"></span>
                    Auto refresh every <?= e((string) round($refreshIntervalMs / 1000)) ?>s
                </span>
            </div>
        </div>

        <div class="dashboard-hero__rail">
            <article class="dashboard-hero-stat">
                <span class="dashboard-hero-stat__label">Month Revenue</span>
                <strong class="dashboard-hero-stat__value" data-dashboard-monthly-revenue><?= e(format_currency($monthlyRevenue)) ?></strong>
                <span class="dashboard-hero-stat__meta"><?= e((string) $monthlySalesCount) ?> completed sales</span>
            </article>
            <article class="dashboard-hero-stat">
                <span class="dashboard-hero-stat__label">Month Net</span>
                <strong class="dashboard-hero-stat__value" data-dashboard-monthly-net><?= e(format_currency($monthlyNet)) ?></strong>
                <span class="dashboard-hero-stat__meta">After <?= e(format_currency((float) ($analytics['monthly_expenses'] ?? 0))) ?> expenses</span>
            </article>
            <article class="dashboard-hero-stat">
                <span class="dashboard-hero-stat__label">Inventory Value</span>
                <strong class="dashboard-hero-stat__value" data-dashboard-inventory-value><?= e(format_currency($inventoryValue)) ?></strong>
                <span class="dashboard-hero-stat__meta"><?= e((string) $inventoryTracked) ?> tracked products</span>
            </article>
            <article class="dashboard-hero-stat">
                <span class="dashboard-hero-stat__label">Open Workflows</span>
                <strong class="dashboard-hero-stat__value" data-dashboard-open-workflows><?= e((string) ($openPurchaseOrders + $inTransitTransfers + $pendingVoidRequests)) ?></strong>
                <span class="dashboard-hero-stat__meta"><?= e((string) $openPurchaseOrders) ?> POs, <?= e((string) $inTransitTransfers) ?> transfers</span>
            </article>
        </div>
    </section>

    <section class="surface-card card-panel dashboard-toolbar">
        <div class="dashboard-toolbar__copy">
            <p class="eyebrow mb-1">Action Deck</p>
            <h3 class="mb-1">Keep operators moving without leaving the dashboard.</h3>
            <p class="text-muted mb-0" data-dashboard-sync-note>Live updates are active for this branch. Manual refresh is available any time.</p>
        </div>
        <div class="dashboard-toolbar__actions">
            <?php if (can_permission('access_pos')): ?>
                <a href="<?= e(url('pos')) ?>" class="btn btn-primary"><i class="bi bi-cash-stack me-1"></i>New Sale</a>
            <?php endif; ?>
            <?php if (can_permission('manage_inventory')): ?>
                <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Inventory</a>
            <?php endif; ?>
            <?php if (can_permission('manage_reports')): ?>
                <a href="<?= e(url('reports')) ?>" class="btn btn-outline-secondary"><i class="bi bi-graph-up-arrow me-1"></i>Reports</a>
                <form action="<?= e(url('dashboard/email-summary')) ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary" data-confirm-action data-confirm-title="Email daily summary?" data-confirm-text="This will send today's branch summary to configured recipients." data-confirm-button="Send summary">
                        <i class="bi bi-envelope-paper me-1"></i>Email Summary
                    </button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary dashboard-refresh-btn" data-dashboard-refresh>
                <i class="bi bi-arrow-repeat me-1"></i>Refresh Now
            </button>
        </div>
    </section>

    <div class="dashboard-kpi-grid">
        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Today Revenue</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-calendar-day"></i>Today</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-daily-revenue><?= e(format_currency($dailyRevenue)) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-daily-sales><?= e((string) $dailySalesCount) ?> sales closed</span>
                <span class="dashboard-trend dashboard-trend--<?= e($trendTone((float) ($comparisons['daily']['revenue_change_pct'] ?? 0))) ?>" data-dashboard-daily-trend><?= e($formatPercent((float) ($comparisons['daily']['revenue_change_pct'] ?? 0))) ?> vs yesterday</span>
            </div>
        </section>

        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Today Expenses</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-wallet2"></i>Spend</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-today-expenses><?= e(format_currency($todayExpenses)) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-today-net-label>Net today <?= e(format_currency($todayNet)) ?></span>
                <span class="dashboard-trend dashboard-trend--<?= e($todayNet >= 0 ? 'positive' : 'negative') ?>" data-dashboard-today-net-tone><?= e($todayNet >= 0 ? 'Margin protected' : 'Expenses ahead of sales') ?></span>
            </div>
        </section>

        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Week Revenue</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-calendar-week"></i>Week</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-weekly-revenue><?= e(format_currency($weeklyRevenue)) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-weekly-sales><?= e((string) $weeklySalesCount) ?> sales this week</span>
                <span class="dashboard-trend dashboard-trend--<?= e($trendTone((float) ($comparisons['weekly']['revenue_change_pct'] ?? 0))) ?>" data-dashboard-weekly-trend><?= e($formatPercent((float) ($comparisons['weekly']['revenue_change_pct'] ?? 0))) ?> vs last week</span>
            </div>
        </section>

        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Average Ticket</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-receipt-cutoff"></i>Basket</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-average-ticket><?= e(format_currency($averageTicket)) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-monthly-sales><?= e((string) $monthlySalesCount) ?> month-to-date sales</span>
                <span class="dashboard-trend dashboard-trend--neutral">Built from completed transactions</span>
            </div>
        </section>

        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Outstanding Credit</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-credit-card-2-front"></i>Receivables</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-outstanding-credit><?= e(format_currency($outstandingCredit)) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-customers-on-credit><?= e((string) $customersOnCredit) ?> customers on credit</span>
                <span class="dashboard-trend dashboard-trend--positive" data-dashboard-credit-recovered><?= e(format_currency($creditRecoveredLast7Days)) ?> recovered in 7 days</span>
            </div>
        </section>

        <section class="dashboard-kpi surface-card card-panel">
            <div class="dashboard-kpi__header">
                <span class="dashboard-kpi__label">Low Stock Pressure</span>
                <span class="dashboard-kpi__chip"><i class="bi bi-exclamation-diamond"></i>Inventory</span>
            </div>
            <div class="dashboard-kpi__value" data-dashboard-low-stock-count><?= e((string) $lowStockCount) ?></div>
            <div class="dashboard-kpi__meta">
                <span data-dashboard-out-of-stock><?= e((string) $outOfStockCount) ?> out of stock</span>
                <span class="dashboard-trend dashboard-trend--<?= e($lowStockCount > 0 || $outOfStockCount > 0 ? 'negative' : 'positive') ?>" data-dashboard-stock-health-note><?= e($lowStockCount > 0 || $outOfStockCount > 0 ? 'Restock recommended' : 'Healthy coverage') ?></span>
            </div>
        </section>
    </div>

    <div class="dashboard-analytics-grid">
        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-7">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Revenue Engine</p>
                    <h3 class="mb-0">Revenue vs expenses over the last 14 days</h3>
                </div>
                <div class="dashboard-surface__meta">
                    <span class="badge-soft"><i class="bi bi-cash-coin me-1"></i><?= e($currencyLabel) ?> tracked live</span>
                </div>
            </div>
            <div class="dashboard-chart-wrapper">
                <canvas id="dashboardRevenueExpenseChart" data-dashboard-chart="revenue-expense"></canvas>
            </div>
            <div class="dashboard-chart-stats">
                <div class="dashboard-chart-stat">
                    <span>14-day Revenue</span>
                    <strong data-dashboard-revenue-total><?= e(format_currency(array_sum($revenueExpenses['revenue'] ?? []))) ?></strong>
                </div>
                <div class="dashboard-chart-stat">
                    <span>14-day Expenses</span>
                    <strong data-dashboard-expense-total><?= e(format_currency(array_sum($revenueExpenses['expenses'] ?? []))) ?></strong>
                </div>
                <div class="dashboard-chart-stat">
                    <span>Net Position</span>
                    <strong data-dashboard-net-total><?= e(format_currency(array_sum($revenueExpenses['revenue'] ?? []) - array_sum($revenueExpenses['expenses'] ?? []))) ?></strong>
                </div>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-5">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Store Pace</p>
                    <h3 class="mb-0">Hourly sales flow today</h3>
                </div>
                <div class="dashboard-surface__meta">
                    <span class="badge-soft"><i class="bi bi-clock me-1"></i>Updated continuously</span>
                </div>
            </div>
            <div class="dashboard-chart-wrapper">
                <canvas id="dashboardHourlySalesChart" data-dashboard-chart="hourly-sales"></canvas>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-4">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Payment Mix</p>
                    <h3 class="mb-0">Tender distribution in the last 30 days</h3>
                </div>
            </div>
            <div class="dashboard-chart-wrapper dashboard-chart-wrapper--compact">
                <canvas id="dashboardPaymentMethodsChart" data-dashboard-chart="payment-methods"></canvas>
            </div>
            <div class="dashboard-mini-list" data-dashboard-payment-list>
                <?php if (($paymentMethods['labels'] ?? []) === []): ?>
                    <?= $renderDashboardEmpty('No payment activity yet', 'Tender performance will appear here after completed sales sync.', 'bi-pie-chart') ?>
                <?php else: ?>
                    <?php foreach (($paymentMethods['labels'] ?? []) as $index => $label): ?>
                        <div class="dashboard-mini-list__item">
                            <div>
                                <strong><?= e((string) $label) ?></strong>
                                <div class="small text-muted"><?= e((string) (($paymentMethods['counts'][$index] ?? 0))) ?> payments</div>
                            </div>
                            <span><?= e(format_currency((float) (($paymentMethods['totals'][$index] ?? 0)))) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-8">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Top Sellers</p>
                    <h3 class="mb-0">Products driving the most unit volume</h3>
                </div>
            </div>
            <div class="dashboard-chart-wrapper">
                <canvas id="dashboardTopProductsChart" data-dashboard-chart="top-products"></canvas>
            </div>
            <div class="dashboard-mini-list dashboard-mini-list--two-col" data-dashboard-top-products-list>
                <?php if ($topProducts === []): ?>
                    <?= $renderDashboardEmpty('No top sellers yet', 'Top-volume products will appear after more completed sales are recorded.', 'bi-bar-chart') ?>
                <?php else: ?>
                    <?php foreach ($topProducts as $product): ?>
                        <div class="dashboard-mini-list__item">
                            <div>
                                <strong><?= e((string) ($product['product_name'] ?? 'Product')) ?></strong>
                                <div class="small text-muted"><?= e((string) (float) ($product['quantity_sold'] ?? 0)) ?> units sold</div>
                            </div>
                            <span><?= e(format_currency((float) ($product['total_sales'] ?? 0))) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="dashboard-insight-grid">
        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-4">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Inventory Health</p>
                    <h3 class="mb-0">Tracked stock coverage</h3>
                </div>
                <div class="dashboard-surface__meta">
                    <span class="badge-soft"><?= e((string) $inventoryTracked) ?> tracked</span>
                </div>
            </div>
            <div class="dashboard-progress-cluster">
                <div class="dashboard-progress-row">
                    <div class="dashboard-progress-row__meta">
                        <span>Healthy</span>
                        <strong data-dashboard-healthy-count><?= e((string) $healthyCount) ?></strong>
                    </div>
                    <div class="dashboard-progress">
                        <span class="dashboard-progress__bar dashboard-progress__bar--positive" data-dashboard-healthy-bar style="width: <?= e((string) max(0, min(100, round($healthyRate, 1)))) ?>%;"></span>
                    </div>
                </div>
                <div class="dashboard-progress-row">
                    <div class="dashboard-progress-row__meta">
                        <span>Low stock</span>
                        <strong data-dashboard-warning-count><?= e((string) $inventoryLowStockCount) ?></strong>
                    </div>
                    <div class="dashboard-progress">
                        <span class="dashboard-progress__bar dashboard-progress__bar--warning" data-dashboard-warning-bar style="width: <?= e((string) max(0, min(100, round($warningRate, 1)))) ?>%;"></span>
                    </div>
                </div>
                <div class="dashboard-progress-row">
                    <div class="dashboard-progress-row__meta">
                        <span>Out of stock</span>
                        <strong data-dashboard-critical-count><?= e((string) $outOfStockCount) ?></strong>
                    </div>
                    <div class="dashboard-progress">
                        <span class="dashboard-progress__bar dashboard-progress__bar--critical" data-dashboard-critical-bar style="width: <?= e((string) max(0, min(100, round($criticalRate, 1)))) ?>%;"></span>
                    </div>
                </div>
            </div>
            <div class="dashboard-stat-list">
                <div class="dashboard-stat-list__item">
                    <span>Stock value</span>
                    <strong data-dashboard-health-stock-value><?= e(format_currency((float) ($inventoryHealth['stock_value'] ?? 0))) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Coverage note</span>
                    <strong data-dashboard-health-note><?= e($inventoryLowStockCount + $outOfStockCount > 0 ? 'Reorder pressure rising' : 'Coverage is balanced') ?></strong>
                </div>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-4">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Operations Queue</p>
                    <h3 class="mb-0">Open workflows that need attention</h3>
                </div>
            </div>
            <div class="dashboard-stat-list dashboard-stat-list--stack">
                <div class="dashboard-stat-list__item">
                    <span>Open purchase orders</span>
                    <strong data-dashboard-open-pos><?= e((string) $openPurchaseOrders) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>In-transit transfers</span>
                    <strong data-dashboard-in-transit><?= e((string) $inTransitTransfers) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Pending void requests</span>
                    <strong data-dashboard-pending-voids><?= e((string) $pendingVoidRequests) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Returns in 7 days</span>
                    <strong data-dashboard-returns-last-7-days><?= e((string) $returnsLast7Days) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Refund total in 7 days</span>
                    <strong data-dashboard-refund-total><?= e(format_currency($refundTotalLast7Days)) ?></strong>
                </div>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-4">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Credit Snapshot</p>
                    <h3 class="mb-0">Exposure and recovery trend</h3>
                </div>
            </div>
            <div class="dashboard-stat-list dashboard-stat-list--stack">
                <div class="dashboard-stat-list__item">
                    <span>Customers on credit</span>
                    <strong data-dashboard-credit-customers><?= e((string) $customersOnCredit) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Outstanding balance</span>
                    <strong data-dashboard-credit-outstanding><?= e(format_currency($outstandingCredit)) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Issued in 7 days</span>
                    <strong data-dashboard-credit-issued><?= e(format_currency($creditIssuedLast7Days)) ?></strong>
                </div>
                <div class="dashboard-stat-list__item">
                    <span>Recovered in 7 days</span>
                    <strong data-dashboard-credit-recovered-total><?= e(format_currency($creditRecoveredLast7Days)) ?></strong>
                </div>
            </div>
        </section>
    </div>

    <div class="dashboard-detail-grid">
        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-8">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Recent Transactions</p>
                    <h3 class="mb-0">Latest completed and in-flight sales activity</h3>
                </div>
                <div class="dashboard-surface__meta">
                    <span class="badge-soft" data-dashboard-recent-count><?= e((string) $recentTransactionCount) ?> records</span>
                </div>
            </div>
            <div class="table-responsive dashboard-table-shell">
                <table class="table align-middle mb-0 dashboard-transaction-table data-table" data-table-search="false" data-table-buttons="false" data-table-paging="false" data-table-info="false">
                    <thead>
                        <tr>
                            <th>Sale</th>
                            <th>Customer</th>
                            <th>Cashier</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody data-dashboard-transactions>
                        <?php if ($recentTransactions === []): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No recent transactions available yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <?php
                                $transactionDate = (string) ($transaction['completed_at'] ?? $transaction['created_at'] ?? '');
                                $transactionLabel = $transactionDate !== '' ? date('M d, H:i', strtotime($transactionDate)) : 'Queued';
                                $transactionStatus = (string) ($transaction['status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('sales/show?id=' . (int) ($transaction['id'] ?? 0))) ?>" class="dashboard-table-link"><?= e((string) ($transaction['sale_number'] ?? 'Sale')) ?></a>
                                        <div class="small text-muted"><?= e($transactionLabel) ?></div>
                                    </td>
                                    <td><?= e((string) (($transaction['customer_name'] ?? '') !== '' ? $transaction['customer_name'] : 'Walk-in customer')) ?></td>
                                    <td><?= e((string) ($transaction['cashier_name'] ?? 'Staff')) ?></td>
                                    <td><span class="status-pill <?= e($statusClass($transactionStatus)) ?>"><?= e(ucwords(str_replace('_', ' ', $transactionStatus))) ?></span></td>
                                    <td class="text-end"><?= e(format_currency((float) ($transaction['grand_total'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card card-panel dashboard-surface dashboard-surface--span-4">
            <div class="dashboard-surface__header">
                <div>
                    <p class="eyebrow mb-1">Low Stock Alerts</p>
                    <h3 class="mb-0">Products closest to stockout</h3>
                </div>
                <div class="dashboard-surface__meta">
                    <span class="badge-soft" data-dashboard-alert-count><?= e((string) $lowStockCount) ?> flagged</span>
                </div>
            </div>
            <div class="dashboard-alert-list" data-dashboard-low-stock-list>
                <?php if ($lowStock === []): ?>
                    <?= $renderDashboardEmpty('No low stock alerts', 'Inventory coverage looks healthy right now for this branch.', 'bi-shield-check') ?>
                <?php else: ?>
                    <?php foreach ($lowStock as $product): ?>
                        <article class="dashboard-alert-card">
                            <div class="dashboard-alert-card__head">
                                <div>
                                    <strong><?= e((string) ($product['name'] ?? 'Product')) ?></strong>
                                    <div class="small text-muted">SKU <?= e((string) ($product['sku'] ?? 'N/A')) ?></div>
                                </div>
                                <span class="status-pill status-pill--warning">On hand <?= e((string) (float) ($product['quantity_on_hand'] ?? 0)) ?></span>
                            </div>
                            <div class="small text-muted">Threshold <?= e((string) (float) ($product['low_stock_threshold'] ?? 0)) ?> | Restock recommended</div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
(() => {
    const root = document.querySelector('[data-dashboard-root]');
    const payloadNode = root?.querySelector('[data-dashboard-payload]');
    if (!root || !payloadNode) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadNode.textContent || '{}');
    } catch (error) {
        console.error('Dashboard payload parse error', error);
        return;
    }

    const liveUrl = root.dataset.liveUrl || window.location.href;
    const refreshInterval = Math.max(Number(root.dataset.refreshInterval || payload?.meta?.refresh_interval_ms || 60000), 15000);
    const salesShowUrl = root.dataset.salesShowUrl || '';
    const productShowUrl = root.dataset.productShowUrl || '';
    const inventoryUrl = root.dataset.inventoryUrl || '';
    const refreshButton = root.querySelector('[data-dashboard-refresh]');
    const charts = {};
    let syncState = 'live';
    let refreshBusy = false;
    let lastSyncedAt = payload?.meta?.generated_at ? new Date(payload.meta.generated_at) : new Date();

    const q = (selector) => root.querySelector(selector);
    const n = (value) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const setText = (selector, value) => {
        const node = q(selector);
        if (node) {
            node.textContent = value;
        }
    };
    const fNum = (value, digits = 0) => n(value).toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
    const fMoney = (value) => `${String(payload?.meta?.currency_label || '$').trim() || '$'} ${fNum(value, 2)}`;
    const fPct = (value) => {
        const amount = n(value);
        if (Math.abs(amount) < 0.05) {
            return '0.0%';
        }
        return `${amount > 0 ? '+' : ''}${fNum(amount, 1)}%`;
    };
    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const emptyMarkup = (title, copy, icon) => `
        <div class="dashboard-empty">
            <span class="dashboard-empty__icon"><i class="bi ${esc(icon || 'bi-stars')}"></i></span>
            <strong class="dashboard-empty__title">${esc(title)}</strong>
            <p class="dashboard-empty__copy mb-0">${esc(copy)}</p>
        </div>
    `;
    const trendTone = (value) => n(value) > 0.05 ? 'positive' : (n(value) < -0.05 ? 'negative' : 'neutral');
    const statusTone = (status) => {
        switch (String(status || '').toLowerCase()) {
            case 'completed': return 'success';
            case 'draft':
            case 'held': return 'info';
            case 'partial_return':
            case 'pending':
            case 'ordered':
            case 'partial_received': return 'warning';
            case 'refunded':
            case 'voided':
            case 'cancelled':
            case 'rejected': return 'danger';
            default: return 'info';
        }
    };
    const timeLabel = (value) => {
        const date = new Date(String(value || ''));
        if (Number.isNaN(date.getTime())) {
            return 'Just now';
        }
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: '2-digit',
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    };
    const ageLabel = () => {
        if (!(lastSyncedAt instanceof Date) || Number.isNaN(lastSyncedAt.getTime())) {
            return 'Waiting for the first sync.';
        }
        const elapsed = Math.max(0, Math.round((Date.now() - lastSyncedAt.getTime()) / 1000));
        if (elapsed < 10) return 'Synced just now.';
        if (elapsed < 60) return `Synced ${elapsed}s ago.`;
        if (elapsed < 3600) return `Synced ${Math.round(elapsed / 60)}m ago.`;
        return `Synced ${Math.round(elapsed / 3600)}h ago.`;
    };
    const trend = (selector, value, suffix) => {
        const node = q(selector);
        if (!node) return;
        node.classList.remove('dashboard-trend--positive', 'dashboard-trend--negative', 'dashboard-trend--neutral');
        node.classList.add(`dashboard-trend--${trendTone(value)}`);
        node.textContent = `${fPct(value)} ${suffix}`;
    };
    const sync = (state = syncState, message = '') => {
        syncState = state;
        const intervalLabel = `${Math.round(refreshInterval / 1000)}s`;
        const badge = q('[data-dashboard-sync-badge]');
        if (badge) {
            badge.dataset.state = state;
            badge.classList.remove('dashboard-sync--live', 'dashboard-sync--loading', 'dashboard-sync--error');
            badge.classList.add(`dashboard-sync--${state}`);
            badge.innerHTML = '<span class="dashboard-sync__dot"></span>';
            badge.append(document.createTextNode(state === 'error' ? 'Sync issue' : (state === 'loading' ? 'Refreshing now' : `Auto refresh every ${intervalLabel}`)));
        }
        setText('[data-dashboard-generated-label]', `Updated ${timeLabel(payload?.meta?.generated_at || '')}`);
        setText('[data-dashboard-sync-note]', message || (state === 'error'
            ? `${ageLabel()} Automatic retry remains active every ${intervalLabel}.`
            : (state === 'loading'
                ? 'Refreshing dashboard data...'
                : `${ageLabel()} Automatic refresh runs every ${intervalLabel}.`)));
        if (refreshButton) {
            refreshButton.disabled = state === 'loading';
            refreshButton.classList.toggle('is-loading', state === 'loading');
        }
    };
    const destroyCharts = () => {
        Object.values(charts).forEach((chart) => chart?.destroy?.());
        Object.keys(charts).forEach((key) => delete charts[key]);
    };
    const chartPalette = () => {
        if (window.NovaUI?.chartPalette) {
            return window.NovaUI.chartPalette();
        }

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        return {
            theme: isDark ? 'dark' : 'light',
            text: isDark ? '#eef4fb' : '#0f1724',
            muted: isDark ? '#93a1b4' : '#6b7280',
            line: isDark ? 'rgba(255, 255, 255, 0.10)' : 'rgba(15, 23, 36, 0.08)',
            primary: isDark ? '#35c6bc' : '#0ea5a4',
            primaryStrong: isDark ? '#83f0e0' : '#067d7c',
            accent: isDark ? '#ff9a62' : '#06b6d4',
            success: isDark ? '#3bc47d' : '#16a34a',
            warning: '#f59e0b',
            danger: isDark ? '#ff7e7e' : '#ef4444',
            purple: isDark ? '#a78bfa' : '#7c3aed',
            pink: isDark ? '#f472b6' : '#ec4899',
            panel: isDark ? 'rgba(19, 27, 41, 0.94)' : 'rgba(255, 255, 255, 0.88)',
            primarySoft: isDark ? 'rgba(53, 198, 188, 0.22)' : 'rgba(14, 165, 164, 0.18)',
            accentSoft: isDark ? 'rgba(255, 154, 98, 0.22)' : 'rgba(6, 182, 212, 0.24)',
            warningSoft: isDark ? 'rgba(245, 158, 11, 0.16)' : 'rgba(245, 158, 11, 0.08)',
            dangerSoft: isDark ? 'rgba(255, 126, 126, 0.18)' : 'rgba(239, 68, 68, 0.08)',
            glass: isDark ? 'rgba(53, 198, 188, 0.22)' : 'rgba(14, 165, 164, 0.18)',
            glassAccent: isDark ? 'rgba(255, 154, 98, 0.22)' : 'rgba(6, 182, 212, 0.24)',
            warm: isDark ? 'rgba(244, 114, 182, 0.18)' : 'rgba(236, 72, 153, 0.10)',
        };
    };

    const render = () => {
        const summary = payload?.summary || {};
        const comparisons = payload?.comparisons || {};
        const analytics = payload?.analytics || {};
        const inventory = payload?.inventoryHealth || {};
        const operations = payload?.operations || {};
        const credit = payload?.creditSnapshot || {};
        const recent = Array.isArray(payload?.recentTransactions) ? payload.recentTransactions : [];
        const low = Array.isArray(payload?.lowStock) ? payload.lowStock : [];
        const topProducts = Array.isArray(payload?.topProducts) ? payload.topProducts : [];
        const payLabels = Array.isArray(payload?.paymentMethods?.labels) ? payload.paymentMethods.labels : [];
        const payTotals = Array.isArray(payload?.paymentMethods?.totals) ? payload.paymentMethods.totals : [];
        const payCounts = Array.isArray(payload?.paymentMethods?.counts) ? payload.paymentMethods.counts : [];
        const tracked = Math.max(0, n(inventory?.tracked_products));
        const healthy = Math.max(0, n(inventory?.healthy_count));
        const lowCount = Math.max(0, n(inventory?.low_stock_count));
        const outCount = Math.max(0, n(inventory?.out_of_stock_count));
        const percent = (value) => tracked > 0 ? Math.max(0, Math.min(100, (n(value) / tracked) * 100)) : 0;
        const revenueTotal = (payload?.revenueExpenses?.revenue || []).reduce((sum, value) => sum + n(value), 0);
        const expenseTotal = (payload?.revenueExpenses?.expenses || []).reduce((sum, value) => sum + n(value), 0);

        setText('[data-dashboard-monthly-revenue]', fMoney(summary?.monthly?.revenue));
        setText('[data-dashboard-monthly-net]', fMoney(analytics?.monthly_net));
        setText('[data-dashboard-inventory-value]', fMoney(analytics?.inventory_value));
        setText('[data-dashboard-open-workflows]', fNum(n(operations?.open_purchase_orders) + n(operations?.in_transit_transfers) + n(operations?.pending_void_requests)));
        setText('[data-dashboard-daily-revenue]', fMoney(summary?.daily?.revenue));
        setText('[data-dashboard-daily-sales]', `${fNum(summary?.daily?.total_sales)} sales closed`);
        trend('[data-dashboard-daily-trend]', comparisons?.daily?.revenue_change_pct, 'vs yesterday');
        setText('[data-dashboard-today-expenses]', fMoney(analytics?.today_expenses));
        setText('[data-dashboard-today-net-label]', `Net today ${fMoney(analytics?.today_net)}`);
        setText('[data-dashboard-today-net-tone]', n(analytics?.today_net) >= 0 ? 'Margin protected' : 'Expenses ahead of sales');
        q('[data-dashboard-today-net-tone]')?.classList.remove('dashboard-trend--positive', 'dashboard-trend--negative', 'dashboard-trend--neutral');
        q('[data-dashboard-today-net-tone]')?.classList.add(`dashboard-trend--${n(analytics?.today_net) >= 0 ? 'positive' : 'negative'}`);
        setText('[data-dashboard-weekly-revenue]', fMoney(summary?.weekly?.revenue));
        setText('[data-dashboard-weekly-sales]', `${fNum(summary?.weekly?.total_sales)} sales this week`);
        trend('[data-dashboard-weekly-trend]', comparisons?.weekly?.revenue_change_pct, 'vs last week');
        setText('[data-dashboard-average-ticket]', fMoney(analytics?.average_ticket));
        setText('[data-dashboard-monthly-sales]', `${fNum(summary?.monthly?.total_sales)} month-to-date sales`);
        setText('[data-dashboard-outstanding-credit]', fMoney(credit?.outstanding_balance));
        setText('[data-dashboard-customers-on-credit]', `${fNum(credit?.customers_on_credit)} customers on credit`);
        setText('[data-dashboard-credit-recovered]', `${fMoney(credit?.recovered_last_7_days)} recovered in 7 days`);
        setText('[data-dashboard-low-stock-count]', fNum(low.length));
        setText('[data-dashboard-out-of-stock]', `${fNum(outCount)} out of stock`);
        setText('[data-dashboard-stock-health-note]', low.length > 0 || outCount > 0 ? 'Restock recommended' : 'Healthy coverage');
        q('[data-dashboard-stock-health-note]')?.classList.remove('dashboard-trend--positive', 'dashboard-trend--negative', 'dashboard-trend--neutral');
        q('[data-dashboard-stock-health-note]')?.classList.add(`dashboard-trend--${low.length > 0 || outCount > 0 ? 'negative' : 'positive'}`);
        setText('[data-dashboard-revenue-total]', fMoney(revenueTotal));
        setText('[data-dashboard-expense-total]', fMoney(expenseTotal));
        setText('[data-dashboard-net-total]', fMoney(revenueTotal - expenseTotal));
        setText('[data-dashboard-healthy-count]', fNum(healthy));
        setText('[data-dashboard-warning-count]', fNum(lowCount));
        setText('[data-dashboard-critical-count]', fNum(outCount));
        setText('[data-dashboard-health-stock-value]', fMoney(inventory?.stock_value));
        setText('[data-dashboard-health-note]', lowCount + outCount > 0 ? 'Reorder pressure rising' : 'Coverage is balanced');
        q('[data-dashboard-healthy-bar]')?.style.setProperty('width', `${percent(healthy)}%`);
        q('[data-dashboard-warning-bar]')?.style.setProperty('width', `${percent(lowCount)}%`);
        q('[data-dashboard-critical-bar]')?.style.setProperty('width', `${percent(outCount)}%`);
        setText('[data-dashboard-open-pos]', fNum(operations?.open_purchase_orders));
        setText('[data-dashboard-in-transit]', fNum(operations?.in_transit_transfers));
        setText('[data-dashboard-pending-voids]', fNum(operations?.pending_void_requests));
        setText('[data-dashboard-returns-last-7-days]', fNum(operations?.returns_last_7_days));
        setText('[data-dashboard-refund-total]', fMoney(operations?.refund_total_last_7_days));
        setText('[data-dashboard-credit-customers]', fNum(credit?.customers_on_credit));
        setText('[data-dashboard-credit-outstanding]', fMoney(credit?.outstanding_balance));
        setText('[data-dashboard-credit-issued]', fMoney(credit?.issued_last_7_days));
        setText('[data-dashboard-credit-recovered-total]', fMoney(credit?.recovered_last_7_days));
        setText('[data-dashboard-recent-count]', `${fNum(recent.length)} records`);
        setText('[data-dashboard-alert-count]', `${fNum(low.length)} flagged`);

        const paymentList = q('[data-dashboard-payment-list]');
        if (paymentList) {
            paymentList.innerHTML = payLabels.length === 0
                ? emptyMarkup('No payment activity yet', 'Tender performance will appear here after completed sales sync.', 'bi-pie-chart')
                : payLabels.map((label, index) => `<div class="dashboard-mini-list__item"><div><strong>${esc(label)}</strong><div class="small text-muted">${fNum(payCounts[index] || 0)} payments</div></div><span>${esc(fMoney(payTotals[index] || 0))}</span></div>`).join('');
        }

        const productList = q('[data-dashboard-top-products-list]');
        if (productList) {
            productList.innerHTML = topProducts.length === 0
                ? emptyMarkup('No top sellers yet', 'Top-volume products will appear after more completed sales are recorded.', 'bi-bar-chart')
                : topProducts.map((product) => `<div class="dashboard-mini-list__item"><div><strong>${esc(product?.product_name || 'Product')}</strong><div class="small text-muted">${fNum(product?.quantity_sold || 0, 1)} units sold</div></div><span>${esc(fMoney(product?.total_sales || 0))}</span></div>`).join('');
        }

        const transactionBody = q('[data-dashboard-transactions]');
        const transactionTable = q('.dashboard-transaction-table');
        const dataTablePlugin = window.jQuery?.fn?.dataTable;
        if (transactionTable && dataTablePlugin && dataTablePlugin.isDataTable(transactionTable)) {
            window.jQuery(transactionTable).DataTable().destroy();
        }

        if (transactionBody) {
            transactionBody.innerHTML = recent.length === 0
                ? '<tr><td colspan="5" class="text-center text-muted py-4">No recent transactions available yet.</td></tr>'
                : recent.map((transaction) => `<tr><td><a href="${esc(salesShowUrl && transaction?.id ? `${salesShowUrl}?id=${encodeURIComponent(transaction.id)}` : '#')}" class="dashboard-table-link">${esc(transaction?.sale_number || 'Sale')}</a><div class="small text-muted">${esc(timeLabel(transaction?.completed_at || transaction?.created_at || ''))}</div></td><td>${esc(String(transaction?.customer_name || '').trim() || 'Walk-in customer')}</td><td>${esc(transaction?.cashier_name || 'Staff')}</td><td><span class="status-pill status-pill--${statusTone(transaction?.status)}">${esc(String(transaction?.status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()))}</span></td><td class="text-end">${esc(fMoney(transaction?.grand_total || 0))}</td></tr>`).join('');
        }

        if (transactionTable && window.NovaUI?.initDataTables) {
            window.NovaUI.initDataTables(root);
        }

        const lowStockList = q('[data-dashboard-low-stock-list]');
        if (lowStockList) {
            lowStockList.innerHTML = low.length === 0
                ? emptyMarkup('No low stock alerts', 'Inventory coverage looks healthy right now for this branch.', 'bi-shield-check')
                : low.map((product) => `<article class="dashboard-alert-card"><div class="dashboard-alert-card__head"><div><strong><a href="${esc(productShowUrl && product?.id ? `${productShowUrl}?id=${encodeURIComponent(product.id)}` : inventoryUrl)}" class="dashboard-table-link">${esc(product?.name || 'Product')}</a></strong><div class="small text-muted">SKU ${esc(product?.sku || 'N/A')}</div></div><span class="status-pill status-pill--warning">On hand ${esc(fNum(product?.quantity_on_hand || 0, 1))}</span></div><div class="small text-muted">Threshold ${esc(fNum(product?.low_stock_threshold || 0, 1))} | Restock recommended</div></article>`).join('');
        }

        if (window.Chart) {
            destroyCharts();
            const colors = chartPalette();
            const revenueCanvas = q('[data-dashboard-chart="revenue-expense"]');
            const hourlyCanvas = q('[data-dashboard-chart="hourly-sales"]');
            const paymentCanvas = q('[data-dashboard-chart="payment-methods"]');
            const productCanvas = q('[data-dashboard-chart="top-products"]');

            if (revenueCanvas) {
                charts.revenue = new Chart(revenueCanvas, {
                    type: 'line',
                    data: { labels: payload?.revenueExpenses?.labels || [], datasets: [
                        { label: 'Revenue', data: payload?.revenueExpenses?.revenue || [], borderColor: colors.primary, backgroundColor: colors.glass, pointBackgroundColor: colors.primary, borderWidth: 2.5, pointRadius: 3, tension: 0.34, fill: true },
                        { label: 'Expenses', data: payload?.revenueExpenses?.expenses || [], borderColor: colors.accent, backgroundColor: colors.warm, pointBackgroundColor: colors.accent, borderWidth: 2.2, pointRadius: 3, tension: 0.34, fill: true },
                    ] },
                    options: { maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { position: 'top' } }, scales: { x: { grid: { display: false }, ticks: { color: colors.muted } }, y: { beginAtZero: true, ticks: { color: colors.muted, callback: (value) => fMoney(value) } } } },
                });
            }

            if (hourlyCanvas) {
                charts.hourly = new Chart(hourlyCanvas, {
                    data: { labels: payload?.hourlySales?.labels || [], datasets: [
                        { type: 'bar', label: 'Sales Count', data: payload?.hourlySales?.sales || [], backgroundColor: colors.glassAccent, borderRadius: 10, borderSkipped: false, yAxisID: 'y' },
                        { type: 'line', label: 'Revenue', data: payload?.hourlySales?.revenue || [], borderColor: colors.primaryStrong, pointBackgroundColor: colors.primaryStrong, borderWidth: 2.5, pointRadius: 3, tension: 0.32, yAxisID: 'y1' },
                    ] },
                    options: { maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, scales: { x: { grid: { display: false }, ticks: { color: colors.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } }, y: { beginAtZero: true, ticks: { color: colors.muted } }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: colors.muted, callback: (value) => fMoney(value) } } } },
                });
            }

            if (paymentCanvas) {
                charts.payment = new Chart(paymentCanvas, {
                    type: 'doughnut',
                    data: { labels: payLabels, datasets: [{ data: payTotals, backgroundColor: [colors.primary, colors.accent, colors.warning, colors.success, colors.purple, colors.pink], borderWidth: 0, hoverOffset: 8 }] },
                    options: { maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } },
                });
            }

            if (productCanvas) {
                charts.products = new Chart(productCanvas, {
                    type: 'bar',
                    data: { labels: topProducts.map((item) => item?.product_name || 'Product'), datasets: [{ label: 'Units sold', data: topProducts.map((item) => n(item?.quantity_sold)), backgroundColor: [colors.primary, colors.primaryStrong, colors.accent, colors.success, colors.warning, colors.purple, colors.pink], borderRadius: 14, borderSkipped: false }] },
                    options: { maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: colors.muted } }, y: { grid: { display: false }, ticks: { color: colors.muted } } } },
                });
            }
        }

        sync(syncState);
    };

    const refreshDashboard = async (manual = false) => {
        if (refreshBusy) {
            return;
        }

        refreshBusy = true;
        sync('loading');

        try {
            const response = await fetch(liveUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`Dashboard refresh failed with status ${response.status}`);
            }

            payload = await response.json();
            lastSyncedAt = new Date(String(payload?.meta?.generated_at || Date.now()));
            if (Number.isNaN(lastSyncedAt.getTime())) {
                lastSyncedAt = new Date();
            }

            render();
            sync('live', manual ? `Dashboard refreshed successfully. Automatic refresh remains active every ${Math.round(refreshInterval / 1000)}s.` : '');
            if (manual && window.showToast) {
                window.showToast('Dashboard refreshed.', 'success');
            }
        } catch (error) {
            console.error('Dashboard refresh failed', error);
            sync('error');
            if (manual && window.showToast) {
                window.showToast('Dashboard refresh failed.', 'warning');
            }
        } finally {
            refreshBusy = false;
        }
    };

    refreshButton?.addEventListener('click', () => {
        void refreshDashboard(true);
    });

    document.addEventListener('novapos:themechange', render);
    window.setInterval(() => {
        if (!document.hidden) {
            void refreshDashboard(false);
        }
    }, refreshInterval);
    window.setInterval(() => sync(syncState), 15000);

    render();
    sync('live');
})();
</script>
