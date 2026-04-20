<?php
$exportBase = url('reports/export');
$resetUrl = url('reports');
$todayLabel = date('Y-m-d');
$filters = array_merge([
    'date_from' => $todayLabel,
    'date_to' => $todayLabel,
    'cashier_id' => '',
], is_array($filters ?? null) ? $filters : []);
$cashiers = is_array($cashiers ?? null) ? $cashiers : [];
$summary = array_merge([
    'gross_sales' => 0,
    'net_sales' => 0,
    'gross_profit' => 0,
    'operating_profit' => 0,
    'refunds_total' => 0,
    'cogs' => 0,
    'total_transactions' => 0,
    'voided_transactions' => 0,
], is_array($summary ?? null) ? $summary : []);
$trend = array_merge([
    'labels' => [],
    'gross_sales' => [],
    'net_sales' => [],
    'refunds' => [],
    'expenses' => [],
], is_array($trend ?? null) ? $trend : []);
$topProducts = is_array($topProducts ?? null) ? $topProducts : [];
$cashierPerformance = is_array($cashierPerformance ?? null) ? $cashierPerformance : [];
$taxSummary = is_array($taxSummary ?? null) ? $taxSummary : [];
$expenseBreakdown = is_array($expenseBreakdown ?? null) ? $expenseBreakdown : [];
$receivablesSummary = array_merge([
    'customers_on_credit' => 0,
    'active_credit_customers' => 0,
    'outstanding_balance' => 0,
    'credit_issued' => 0,
    'credit_recovered' => 0,
], is_array($receivablesSummary ?? null) ? $receivablesSummary : []);
$receivablesLedger = is_array($receivablesLedger ?? null) ? $receivablesLedger : [];
$inventorySnapshot = is_array($inventorySnapshot ?? null) ? $inventorySnapshot : [];
$reportContext = $reportContext ?? [];
$countRows = static function ($value): int {
    return is_countable($value) ? count($value) : 0;
};
$selectedCashierLabel = (string) ($reportContext['selected_cashier_name'] ?? 'All cashiers');
$branchLabel = (string) ($reportContext['branch_name'] ?? (current_user()['branch_name'] ?? 'Main Branch'));
$reportingDays = (int) ($reportContext['reporting_days'] ?? 0);
$averageTicket = (float) ($reportContext['average_ticket'] ?? 0);
$refundRate = (float) ($reportContext['refund_rate'] ?? 0);
$peakNetSales = (float) ($reportContext['peak_net_sales'] ?? 0);
$topCashierName = (string) ($reportContext['top_cashier_name'] ?? 'No cashier data');
$topCashierNetSales = (float) ($reportContext['top_cashier_net_sales'] ?? 0);
$topProductName = (string) ($reportContext['top_product_name'] ?? 'No product data');
$topProductRevenue = (float) ($reportContext['top_product_revenue'] ?? 0);
$lowStockItems = (int) ($reportContext['low_stock_items'] ?? 0);
$rangeStart = new \DateTimeImmutable((string) ($filters['date_from'] ?? 'today'));
$rangeEnd = new \DateTimeImmutable((string) ($filters['date_to'] ?? 'today'));
$rangeLabel = $rangeStart->format('M d, Y') . ' - ' . $rangeEnd->format('M d, Y');
$today = new \DateTimeImmutable('today');
$monthStart = $today->modify('first day of this month');
$monthEnd = $today->modify('last day of this month');
$quickPresets = [
    ['label' => 'Today', 'from' => $today->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
    ['label' => '7 Days', 'from' => $today->modify('-6 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
    ['label' => '30 Days', 'from' => $today->modify('-29 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
    ['label' => 'MTD', 'from' => $monthStart->format('Y-m-d'), 'to' => $today->format('Y-m-d')],
    ['label' => 'This Month', 'from' => $monthStart->format('Y-m-d'), 'to' => $monthEnd->format('Y-m-d')],
];
$exportDatasets = [
    'sales' => 'Sales Ledger',
    'products' => 'Product Sales',
    'cashiers' => 'Cashier Performance',
    'tax' => 'Tax Summary',
    'expenses' => 'Expenses',
    'inventory' => 'Inventory Snapshot',
    'receivables' => 'Receivables Ledger',
];
$exportFormats = [
    'csv' => 'CSV',
    'xlsx' => 'Excel',
    'pdf' => 'PDF',
];
$formatExportLink = static function (string $type, string $format) use ($exportBase, $filters): string {
    return $exportBase . '?' . http_build_query(array_merge($filters, [
        'type' => $type,
        'format' => $format,
    ]));
};
$reportDatasets = [
    ['type' => 'sales', 'icon' => 'bi-receipt-cutoff', 'title' => 'Sales Ledger', 'description' => 'Transaction-level sales, refunds, and cashier activity.', 'metric' => number_format((int) ($summary['total_transactions'] ?? 0)) . ' transactions'],
    ['type' => 'products', 'icon' => 'bi-box-seam', 'title' => 'Product Sales', 'description' => 'Top-selling items ranked by net revenue after returns.', 'metric' => number_format($countRows($topProducts)) . ' items'],
    ['type' => 'cashiers', 'icon' => 'bi-people', 'title' => 'Cashier Performance', 'description' => 'Performance by operator, including voids and refunds.', 'metric' => number_format($countRows($cashierPerformance)) . ' cashiers'],
    ['type' => 'tax', 'icon' => 'bi-percent', 'title' => 'Tax Summary', 'description' => 'Taxable sales and tax collected by rate.', 'metric' => number_format($countRows($taxSummary)) . ' bands'],
    ['type' => 'expenses', 'icon' => 'bi-wallet2', 'title' => 'Expense Ledger', 'description' => 'Expense categories and spend distribution.', 'metric' => number_format($countRows($expenseBreakdown)) . ' categories'],
    ['type' => 'inventory', 'icon' => 'bi-archive', 'title' => 'Inventory Snapshot', 'description' => 'Current stock, valuation, and low-stock status.', 'metric' => number_format($countRows($inventorySnapshot)) . ' items'],
    ['type' => 'receivables', 'icon' => 'bi-cash-stack', 'title' => 'Receivables Ledger', 'description' => 'Credit customers, balances, recoveries, and activity.', 'metric' => number_format($countRows($receivablesLedger)) . ' ledgers'],
];
$exportLinks = $reportDatasets;
$renderExportActions = static function (string $type) use ($formatExportLink): string {
    ob_start();
    ?>
    <div class="compact-actions reports-export-actions">
        <a href="<?= e($formatExportLink($type, 'csv')) ?>" class="btn btn-sm btn-outline-primary" data-download="true" data-no-loader="true">CSV</a>
        <a href="<?= e($formatExportLink($type, 'xlsx')) ?>" class="btn btn-sm btn-outline-secondary" data-download="true" data-no-loader="true">Excel</a>
        <a href="<?= e($formatExportLink($type, 'pdf')) ?>" class="btn btn-sm btn-outline-secondary" data-download="true" data-no-loader="true">PDF</a>
    </div>
    <?php

    return (string) ob_get_clean();
};
?>
<div class="reports-page">
<section class="surface-card card-panel reports-hero">
    <div class="reports-hero__copy">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-stars me-1"></i>Report Command Center</p>
            <h3 class="mb-2">Track sales, margin, receivables, and stock exposure from one reporting workspace.</h3>
            <p class="text-muted mb-0">Filter the reporting window, compare performance visually, and export every dataset as CSV, Excel, or PDF without leaving the page.</p>
        </div>
        <div class="chip-cluster reports-hero__chips">
            <span class="badge-soft"><i class="bi bi-diagram-3"></i><?= e($branchLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-person-badge"></i><?= e($selectedCashierLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-calendar-range"></i><?= e($rangeLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-hourglass-split"></i><?= e((string) $reportingDays) ?> day<?= $reportingDays === 1 ? '' : 's' ?></span>
        </div>
    </div>
    <div class="reports-hero__stats">
        <article class="reports-insight-card">
            <span class="reports-insight-card__label">Average Ticket</span>
            <strong class="reports-insight-card__value"><?= e(format_currency($averageTicket)) ?></strong>
            <span class="reports-insight-card__meta">Net sales per recorded transaction.</span>
        </article>
        <article class="reports-insight-card">
            <span class="reports-insight-card__label">Refund Rate</span>
            <strong class="reports-insight-card__value"><?= e(number_format($refundRate, 1)) ?>%</strong>
            <span class="reports-insight-card__meta">Share of gross sales reversed in this window.</span>
        </article>
        <article class="reports-insight-card">
            <span class="reports-insight-card__label">Lead Cashier</span>
            <strong class="reports-insight-card__value reports-insight-card__value--compact"><?= e($topCashierName) ?></strong>
            <span class="reports-insight-card__meta"><?= e(format_currency($topCashierNetSales)) ?> net sales led.</span>
        </article>
        <article class="reports-insight-card">
            <span class="reports-insight-card__label">Stock Watch</span>
            <strong class="reports-insight-card__value"><?= e(number_format($lowStockItems)) ?></strong>
            <span class="reports-insight-card__meta">Items currently flagged low in this branch snapshot.</span>
        </article>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Gross Sales</span><span><?= e((string) $summary['total_transactions']) ?> transactions</span></div>
        <h3><?= e(format_currency($summary['gross_sales'])) ?></h3>
        <div class="text-muted">Captured sales before refunds across the selected period.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Net Sales</span><span>Refunds <?= e(format_currency($summary['refunds_total'])) ?></span></div>
        <h3><?= e(format_currency($summary['net_sales'])) ?></h3>
        <div class="text-muted">Sales value after processed returns are deducted.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Gross Profit</span><span>COGS <?= e(format_currency($summary['cogs'])) ?></span></div>
        <h3><?= e(format_currency($summary['gross_profit'])) ?></h3>
        <div class="text-muted">Estimated margin after inventory cost is accounted for.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Operating Result</span><span><?= e((string) $summary['voided_transactions']) ?> voided</span></div>
        <h3><?= e(format_currency($summary['operating_profit'])) ?></h3>
        <div class="text-muted">Gross profit minus logged operating expenses.</div>
    </section>
</div>

<div class="surface-card card-panel workspace-panel mb-4">
    <div class="reports-command__grid">
        <div class="filter-panel reports-filter-panel">
            <div class="filter-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1"><i class="bi bi-funnel me-1"></i>Filter Workspace</p>
                    <h3>Refine the reporting window</h3>
                </div>
                <div class="chip-cluster">
                    <span class="badge-soft"><i class="bi bi-graph-up-arrow"></i><?= e(format_currency($summary['net_sales'])) ?> net sales</span>
                    <span class="badge-soft"><i class="bi bi-lightning-charge"></i><?= e(format_currency($peakNetSales)) ?> peak day</span>
                </div>
            </div>
            <form method="get" action="<?= e(url('reports')) ?>" class="filter-grid reports-filter-grid" id="reportsFilterForm" data-report-filter-form>
                <div class="field-stack">
                    <label class="form-label" for="report-date-from">From</label>
                    <input id="report-date-from" type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="report-date-to">To</label>
                    <input id="report-date-to" type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="report-cashier">Cashier</label>
                    <select id="report-cashier" name="cashier_id" class="form-select">
                        <option value="">All cashiers</option>
                        <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?= e((string) $cashier['id']) ?>" <?= $filters['cashier_id'] === (string) $cashier['id'] ? 'selected' : '' ?>><?= e($cashier['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label">Quick Range</label>
                    <div class="inline-actions reports-preset-group">
                        <?php foreach ($quickPresets as $preset): ?>
                            <?php $isActivePreset = $filters['date_from'] === $preset['from'] && $filters['date_to'] === $preset['to']; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary reports-preset<?= $isActivePreset ? ' is-active' : '' ?>" data-report-preset data-from="<?= e($preset['from']) ?>" data-to="<?= e($preset['to']) ?>"><?= e($preset['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field-span-full reports-filter-footer">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill me-1"></i>Apply Filters</button>
                        <a href="<?= e($resetUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                    </div>
                    <div class="chip-cluster reports-filter-summary">
                        <span class="badge-soft"><i class="bi bi-calendar2-week"></i><?= e((string) $reportingDays) ?> day<?= $reportingDays === 1 ? '' : 's' ?></span>
                        <span class="badge-soft"><i class="bi bi-person-lines-fill"></i><?= e($selectedCashierLabel) ?></span>
                    </div>
                </div>
            </form>
        </div>

        <div class="record-card reports-export-panel">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1"><i class="bi bi-download me-1"></i>Export Studio</p>
                    <h3>Download any dataset instantly</h3>
                </div>
                <div class="record-card__meta">
                    <span class="badge-soft"><i class="bi bi-files"></i><?= e((string) $countRows($reportDatasets)) ?> datasets ready</span>
                </div>
            </div>

            <form method="get" action="<?= e(url('reports/export')) ?>" id="reportsExportForm" class="reports-export-form" data-loading-form data-loading-mode="export" data-download="true" data-skip-loading>
                <input type="hidden" name="date_from" value="<?= e($filters['date_from']) ?>" data-export-sync="date_from">
                <input type="hidden" name="date_to" value="<?= e($filters['date_to']) ?>" data-export-sync="date_to">
                <input type="hidden" name="cashier_id" value="<?= e($filters['cashier_id']) ?>" data-export-sync="cashier_id">
                <div class="reports-export-form__grid">
                    <div class="field-stack">
                        <label class="form-label" for="report-export-type">Dataset</label>
                        <select id="report-export-type" name="type" class="form-select">
                            <?php foreach ($exportDatasets as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="report-export-format">Format</label>
                        <select id="report-export-format" name="format" class="form-select">
                            <?php foreach ($exportFormats as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="reports-export-form__footer">
                    <div class="small text-muted">Current filters are synced into every export automatically.</div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-down me-1"></i>Download Current Dataset</button>
                </div>
            </form>

            <div class="reports-export-grid">
                <?php foreach ($reportDatasets as $dataset): ?>
                    <article class="reports-export-card">
                        <div class="reports-export-card__head">
                            <span class="reports-export-card__icon"><i class="bi <?= e($dataset['icon']) ?>"></i></span>
                            <span class="badge-soft"><?= e($dataset['metric']) ?></span>
                        </div>
                        <div class="reports-export-card__copy">
                            <h4><?= e($dataset['title']) ?></h4>
                            <p class="text-muted mb-0"><?= e($dataset['description']) ?></p>
                        </div>
                        <?= $renderExportActions($dataset['type']) ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="metric-grid reports-credit-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Outstanding Credit</span><span><?= e((string) $receivablesSummary['customers_on_credit']) ?> customers</span></div>
        <h3><?= e(format_currency($receivablesSummary['outstanding_balance'])) ?></h3>
        <div class="text-muted">Current open-account receivables still outstanding.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Credit Issued</span><span><?= e((string) $receivablesSummary['active_credit_customers']) ?> active customers</span></div>
        <h3><?= e(format_currency($receivablesSummary['credit_issued'])) ?></h3>
        <div class="text-muted">New credit assigned within the filtered reporting period.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Credit Recovered</span><span>Collections and reversals</span></div>
        <h3><?= e(format_currency($receivablesSummary['credit_recovered'])) ?></h3>
        <div class="text-muted">Payments, returns, and void relief applied in the selected range.</div>
    </section>
</div>

<div class="chart-grid">
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Performance Trend</p>
                <h3><i class="bi bi-graph-up me-2"></i>Net Sales, Refunds, and Expenses</h3>
            </div>
            <div class="workspace-panel__meta">
                <span class="badge-soft"><i class="bi bi-calendar-range"></i><?= e($rangeLabel) ?></span>
                <span class="badge-soft"><i class="bi bi-lightning-charge"></i>Peak <?= e(format_currency($peakNetSales)) ?></span>
            </div>
        </div>
        <div class="reports-chart-shell">
            <canvas id="reportTrendChart"></canvas>
        </div>
    </section>
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Product Mix</p>
                <h3><i class="bi bi-star me-2"></i>Top Products by Net Revenue</h3>
            </div>
            <div class="workspace-panel__meta">
                <span class="badge-soft"><i class="bi bi-box-seam"></i><?= e((string) $countRows($topProducts)) ?> ranked items</span>
                <span class="badge-soft"><i class="bi bi-currency-dollar"></i><?= e(format_currency($topProductRevenue)) ?></span>
                <span class="badge-soft"><i class="bi bi-trophy"></i><?= e($topProductName) ?></span>
            </div>
        </div>
        <div class="reports-chart-shell">
            <canvas id="reportTopProductsChart"></canvas>
        </div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Cashier Performance</p>
                <h3><i class="bi bi-people me-2"></i>Cashiers</h3>
            </div>
            <div class="workspace-panel__actions">
                <span class="badge-soft"><?= e((string) $countRows($cashierPerformance)) ?> rows</span>
                <?= $renderExportActions('cashiers') ?>
            </div>
        </div>
        <?php if ($cashierPerformance === []): ?>
            <div class="empty-state reports-empty-state">No cashier activity matched the selected filters.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="6" data-table-order="4:desc">
                <thead>
                <tr>
                    <th>Cashier</th>
                    <th>Sales</th>
                    <th>Voids</th>
                    <th>Refunds</th>
                    <th>Net Sales</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($cashierPerformance as $row): ?>
                    <tr>
                        <td><?= e($row['cashier_name']) ?></td>
                        <td><?= e((string) $row['total_sales']) ?></td>
                        <td><?= e((string) $row['voided_sales']) ?></td>
                        <td><?= e(format_currency($row['refunds_total'])) ?></td>
                        <td><?= e(format_currency($row['net_sales'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Expense Breakdown</p>
                <h3><i class="bi bi-wallet2 me-2"></i>Expenses</h3>
            </div>
            <div class="workspace-panel__actions">
                <span class="badge-soft"><?= e((string) $countRows($expenseBreakdown)) ?> categories</span>
                <?= $renderExportActions('expenses') ?>
            </div>
        </div>
        <?php if ($expenseBreakdown === []): ?>
            <div class="empty-state reports-empty-state">No expense entries matched the selected range.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="6" data-table-order="2:desc">
                <thead>
                <tr>
                    <th>Category</th>
                    <th>Entries</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($expenseBreakdown as $row): ?>
                    <tr>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= e((string) $row['total_entries']) ?></td>
                        <td><?= e(format_currency($row['total_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Tax Position</p>
                <h3><i class="bi bi-percent me-2"></i>Collected by rate</h3>
            </div>
            <div class="workspace-panel__actions">
                <span class="badge-soft"><?= e((string) $countRows($taxSummary)) ?> tax bands</span>
                <?= $renderExportActions('tax') ?>
            </div>
        </div>
        <?php if ($taxSummary === []): ?>
            <div class="empty-state reports-empty-state">No tax rows matched the selected scope.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="6" data-table-order="3:desc">
                <thead>
                <tr>
                    <th>Tax</th>
                    <th>Rate</th>
                    <th>Taxable Sales</th>
                    <th>Tax Collected</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($taxSummary as $row): ?>
                    <tr>
                        <td><?= e($row['tax_name']) ?></td>
                        <td><?= e(number_format((float) $row['tax_rate'], 2)) ?>%</td>
                        <td><?= e(format_currency($row['taxable_sales'])) ?></td>
                        <td><?= e(format_currency($row['tax_collected'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <section class="surface-card card-panel table-shell workspace-panel">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Top Products</p>
                <h3><i class="bi bi-box-seam me-2"></i>Products</h3>
            </div>
            <div class="workspace-panel__actions">
                <span class="badge-soft"><?= e((string) $countRows($topProducts)) ?> ranked items</span>
                <?= $renderExportActions('products') ?>
            </div>
        </div>
        <?php if ($topProducts === []): ?>
            <div class="empty-state reports-empty-state">No product sales matched the current filters.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="6" data-table-order="3:desc">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Net Qty</th>
                    <th>Returned</th>
                    <th>Net Revenue</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($topProducts as $row): ?>
                    <tr>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e(number_format((float) $row['net_quantity'], 2)) ?></td>
                        <td><?= e(number_format((float) $row['returned_quantity'], 2)) ?></td>
                        <td><?= e(format_currency($row['net_revenue'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<div class="surface-card card-panel table-shell workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Receivables Ledger</p>
            <h3><i class="bi bi-cash-stack me-2"></i>Receivables</h3>
        </div>
        <div class="workspace-panel__actions">
            <span class="badge-soft"><?= e((string) $countRows($receivablesLedger)) ?> ledger rows</span>
            <?= $renderExportActions('receivables') ?>
        </div>
    </div>
    <?php if ($receivablesLedger === []): ?>
        <div class="empty-state reports-empty-state">No receivables activity matched the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="8" data-table-order="2:desc">
                <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Current Balance</th>
                    <th>Charged</th>
                    <th>Recovered</th>
                    <th>Last Activity</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($receivablesLedger as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($row['customer_name']) ?></div>
                            <div class="small text-muted"><?= e($row['email'] ?? '') ?></div>
                        </td>
                        <td><?= e($row['phone'] ?: 'No phone') ?></td>
                        <td><?= e(format_currency($row['current_balance'])) ?></td>
                        <td><?= e(format_currency($row['charged_total'])) ?></td>
                        <td><?= e(format_currency($row['relieved_total'])) ?></td>
                        <td><?= e((string) ($row['last_activity_at'] ?? 'No activity in range')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="surface-card card-panel table-shell workspace-panel">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Inventory Snapshot</p>
            <h3><i class="bi bi-archive me-2"></i>Inventory</h3>
        </div>
        <div class="workspace-panel__actions">
            <span class="badge-soft"><?= e((string) $countRows($inventorySnapshot)) ?> items in view</span>
            <?= $renderExportActions('inventory') ?>
        </div>
    </div>
    <?php if ($inventorySnapshot === []): ?>
        <div class="empty-state reports-empty-state">No inventory items are available for this branch snapshot.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle data-table" data-table-buttons="false" data-table-page-length="8" data-table-order="5:desc">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Category</th>
                <th>On Hand</th>
                <th>Avg Cost</th>
                <th>Stock Value</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($inventorySnapshot as $item): ?>
                <tr>
                    <td><?= e($item['sku']) ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['category_name'] ?? 'Uncategorized') ?></td>
                    <td><?= e(number_format((float) $item['quantity_on_hand'], 2)) ?></td>
                    <td><?= e(format_currency($item['average_cost'])) ?></td>
                    <td><?= e(format_currency($item['inventory_value'])) ?></td>
                    <td><span class="badge-soft text-capitalize"><?= e($item['stock_state']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const trendContext = document.getElementById('reportTrendChart');
        const topProductsContext = document.getElementById('reportTopProductsChart');
        const trend = <?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>;
        const topProducts = <?= json_encode($topProducts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>;
        const filterForm = document.getElementById('reportsFilterForm');
        const exportForm = document.getElementById('reportsExportForm');
        const presetButtons = Array.from(document.querySelectorAll('[data-report-preset]'));
        const dateFromInput = filterForm?.querySelector('[name="date_from"]') || null;
        const dateToInput = filterForm?.querySelector('[name="date_to"]') || null;
        const cashierInput = filterForm?.querySelector('[name="cashier_id"]') || null;
        const exportSyncInputs = {
            date_from: exportForm?.querySelector('[data-export-sync="date_from"]') || null,
            date_to: exportForm?.querySelector('[data-export-sync="date_to"]') || null,
            cashier_id: exportForm?.querySelector('[data-export-sync="cashier_id"]') || null,
        };
        let trendChart;
        let topProductsChart;

        const buildOptions = (palette) => ({
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: palette.text,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    backgroundColor: palette.panel,
                    titleColor: palette.text,
                    bodyColor: palette.text,
                    borderColor: palette.line,
                    borderWidth: 1,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: palette.muted },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: palette.line },
                    ticks: { color: palette.muted },
                },
            },
        });

        const syncExportFilters = () => {
            if (exportSyncInputs.date_from) {
                exportSyncInputs.date_from.value = dateFromInput?.value || '';
            }

            if (exportSyncInputs.date_to) {
                exportSyncInputs.date_to.value = dateToInput?.value || '';
            }

            if (exportSyncInputs.cashier_id) {
                exportSyncInputs.cashier_id.value = cashierInput?.value || '';
            }
        };

        const syncPresetState = () => {
            const currentFrom = dateFromInput?.value || '';
            const currentTo = dateToInput?.value || '';

            presetButtons.forEach((button) => {
                const matches = button.dataset.from === currentFrom && button.dataset.to === currentTo;
                button.classList.toggle('is-active', matches);
            });
        };

        presetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                if (dateFromInput) {
                    dateFromInput.value = button.dataset.from || '';
                }

                if (dateToInput) {
                    dateToInput.value = button.dataset.to || '';
                }

                syncExportFilters();
                syncPresetState();
            });
        });

        [dateFromInput, dateToInput, cashierInput].forEach((input) => {
            input?.addEventListener('change', () => {
                syncExportFilters();
                syncPresetState();
            });
        });

        const renderCharts = () => {
            const palette = window.NovaUI?.chartPalette ? window.NovaUI.chartPalette() : (() => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

                return {
                    theme: isDark ? 'dark' : 'light',
                    text: isDark ? '#eef4fb' : '#212529',
                    muted: isDark ? '#93a1b4' : '#6c757d',
                    line: isDark ? 'rgba(255, 255, 255, 0.10)' : 'rgba(33, 37, 41, 0.08)',
                    primary: isDark ? '#35c6bc' : '#0d6efd',
                    primaryStrong: isDark ? '#83f0e0' : '#6610f2',
                    accent: isDark ? '#ff9a62' : '#0dcaf0',
                    success: isDark ? '#3bc47d' : '#198754',
                    danger: isDark ? '#ff7e7e' : '#dc3545',
                    warning: '#ffc107',
                    teal: '#20c997',
                    pink: isDark ? '#f472b6' : '#d63384',
                    purple: isDark ? '#a78bfa' : '#6f42c1',
                    panel: isDark ? 'rgba(19, 27, 41, 0.94)' : 'rgba(255, 255, 255, 0.88)',
                    primarySoft: isDark ? 'rgba(53, 198, 188, 0.22)' : 'rgba(36, 107, 219, 0.14)',
                    warningSoft: isDark ? 'rgba(255, 193, 7, 0.16)' : 'rgba(255, 193, 7, 0.08)',
                    dangerSoft: isDark ? 'rgba(255, 126, 126, 0.18)' : 'rgba(220, 53, 69, 0.08)',
                };
            })();

            if (trendChart) {
                trendChart.destroy();
            }

            if (topProductsChart) {
                topProductsChart.destroy();
            }

            if (trendContext) {
                trendChart = new Chart(trendContext, {
                    type: 'line',
                    data: {
                        labels: trend.labels,
                        datasets: [
                            {
                                label: 'Net Sales',
                                data: trend.net_sales,
                                borderColor: palette.primary,
                                backgroundColor: palette.primarySoft,
                                fill: true,
                                tension: 0.34,
                                pointRadius: 2.5,
                                pointHoverRadius: 4,
                            },
                            {
                                label: 'Refunds',
                                data: trend.refunds,
                                borderColor: palette.warning,
                                backgroundColor: palette.warningSoft,
                                fill: false,
                                tension: 0.24,
                                borderDash: [6, 6],
                                pointRadius: 2,
                            },
                            {
                                label: 'Expenses',
                                data: trend.expenses,
                                borderColor: palette.danger,
                                backgroundColor: palette.dangerSoft,
                                fill: false,
                                tension: 0.24,
                                pointRadius: 2,
                            },
                        ],
                    },
                    options: buildOptions(palette),
                });
            }

            if (topProductsContext) {
                topProductsChart = new Chart(topProductsContext, {
                    type: 'bar',
                    data: {
                        labels: topProducts.map((row) => row.product_name),
                        datasets: [
                            {
                                label: 'Net Revenue',
                                data: topProducts.map((row) => row.net_revenue),
                                borderRadius: 12,
                                backgroundColor: topProducts.map((_, index) => {
                                    const tones = [palette.primary, palette.accent, palette.primaryStrong, palette.success, palette.warning, palette.teal];
                                    return tones[index % tones.length];
                                }),
                            },
                        ],
                    },
                    options: {
                        ...buildOptions(palette),
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: palette.line },
                                ticks: { color: palette.muted },
                            },
                            y: {
                                grid: { display: false },
                                ticks: { color: palette.muted },
                            },
                        },
                    },
                });
            }
        };

        syncExportFilters();
        syncPresetState();
        renderCharts();
        document.addEventListener('novapos:themechange', renderCharts);
    });
</script>
