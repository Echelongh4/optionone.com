<?php
$filters = $filters ?? [
    'search' => '',
    'status' => '',
    'cashier_id' => '',
    'date_from' => '',
    'date_to' => '',
];
$sales = $sales ?? [];
$cashiers = $cashiers ?? [];

$statusOptions = [
    '' => 'All statuses',
    'held' => 'Held',
    'completed' => 'Completed',
    'partial_return' => 'Partial Return',
    'refunded' => 'Refunded',
    'voided' => 'Voided',
    'void_pending' => 'Void Pending',
];

$activeFilterCount = count(array_filter([
    $filters['search'] ?? '',
    $filters['status'] ?? '',
    $filters['cashier_id'] ?? '',
    $filters['date_from'] ?? '',
    $filters['date_to'] ?? '',
], static fn ($value): bool => $value !== '' && $value !== null));

$salesUrl = static function (array $overrides = [], array $remove = []) use ($filters): string {
    $params = array_merge($filters, $overrides);

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null);

    return url('sales') . ($params !== [] ? '?' . http_build_query($params) : '');
};

$statusClass = static function (array $sale): string {
    $status = (string) ($sale['status'] ?? '');

    if (($sale['void_request_status'] ?? '') === 'pending') {
        return 'status-pill status-pill--warning';
    }

    return match ($status) {
        'completed' => 'status-pill status-pill--success',
        'partial_return', 'held' => 'status-pill status-pill--warning',
        'refunded', 'voided' => 'status-pill status-pill--danger',
        default => 'status-pill status-pill--info',
    };
};

$visibleSalesCount = count($sales);
$grossValue = array_reduce($sales, static fn (float $sum, array $sale): float => $sum + (float) ($sale['grand_total'] ?? 0), 0.0);
$refundValue = array_reduce($sales, static fn (float $sum, array $sale): float => $sum + (float) ($sale['total_refund'] ?? 0), 0.0);
$creditExposure = array_reduce($sales, static fn (float $sum, array $sale): float => $sum + (float) ($sale['credit_amount'] ?? 0), 0.0);
$completedCount = count(array_filter($sales, static fn (array $sale): bool => (string) ($sale['status'] ?? '') === 'completed'));
$heldCount = count(array_filter($sales, static fn (array $sale): bool => (string) ($sale['status'] ?? '') === 'held'));
$returnCount = count(array_filter($sales, static fn (array $sale): bool => in_array((string) ($sale['status'] ?? ''), ['partial_return', 'refunded'], true)));
$voidPendingCount = count(array_filter($sales, static fn (array $sale): bool => (string) ($sale['void_request_status'] ?? '') === 'pending'));
$averageTicket = $visibleSalesCount > 0 ? $grossValue / $visibleSalesCount : 0;
$today = date('Y-m-d');
$seedOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$searchSuggestionPool = [];

$quickViews = [
    [
        'label' => 'All Sales',
        'href' => $salesUrl(['status' => '', 'date_from' => '', 'date_to' => '']),
        'active' => ($filters['status'] ?? '') === '' && ($filters['date_from'] ?? '') === '' && ($filters['date_to'] ?? '') === '',
        'meta' => $visibleSalesCount . ' rows',
    ],
    [
        'label' => 'Completed',
        'href' => $salesUrl(['status' => 'completed']),
        'active' => ($filters['status'] ?? '') === 'completed',
        'meta' => $completedCount . ' closed',
    ],
    [
        'label' => 'Held',
        'href' => $salesUrl(['status' => 'held']),
        'active' => ($filters['status'] ?? '') === 'held',
        'meta' => $heldCount . ' parked',
    ],
    [
        'label' => 'Returns',
        'href' => $salesUrl(['status' => 'partial_return']),
        'active' => ($filters['status'] ?? '') === 'partial_return',
        'meta' => $returnCount . ' adjusted',
    ],
    [
        'label' => 'Void Queue',
        'href' => $salesUrl(['status' => 'void_pending']),
        'active' => ($filters['status'] ?? '') === 'void_pending',
        'meta' => $voidPendingCount . ' pending',
    ],
    [
        'label' => 'Today',
        'href' => $salesUrl(['date_from' => $today, 'date_to' => $today, 'status' => '']),
        'active' => ($filters['date_from'] ?? '') === $today && ($filters['date_to'] ?? '') === $today,
        'meta' => $today,
    ],
];

foreach ($sales as $sale) {
    $suggestionCreatedAt = trim((string) ($sale['created_at'] ?? ''));

    $searchSuggestionPool[] = [
        'id' => (int) ($sale['id'] ?? 0),
        'sale_number' => (string) ($sale['sale_number'] ?? ''),
        'customer_name' => trim((string) ($sale['customer_name'] ?? '')) ?: 'Walk-in customer',
        'customer_phone' => (string) ($sale['customer_phone'] ?? ''),
        'cashier_name' => (string) ($sale['cashier_name'] ?? ''),
        'payment_references' => (string) ($sale['payment_references'] ?? ''),
        'notes' => (string) ($sale['notes'] ?? ''),
        'created_label' => $suggestionCreatedAt !== '' ? date('M d, Y H:i', strtotime($suggestionCreatedAt)) : 'Unknown time',
        'grand_total_label' => format_currency((float) ($sale['grand_total'] ?? 0)),
    ];
}

$searchSuggestionSeeds = [];
foreach ($searchSuggestionPool as $sale) {
    $saleNumber = trim((string) ($sale['sale_number'] ?? ''));
    if ($saleNumber === '' || in_array($saleNumber, $searchSuggestionSeeds, true)) {
        continue;
    }

    $searchSuggestionSeeds[] = $saleNumber;
    if (count($searchSuggestionSeeds) >= 4) {
        break;
    }
}
?>

<section class="surface-card card-panel table-shell mb-4 sales-command-bar">
    <div class="toolbar-card">
        <div class="sales-command-copy">
            <p class="eyebrow mb-1"><i class="bi bi-receipt-cutoff me-1"></i>Sales Workspace</p>
            <h3 class="mb-1">Search, filter, and recover transactions faster</h3>
            <div class="text-muted">Deep search can match sale numbers, customer names, phone numbers, cashier names, payment references, notes, and products sold.</div>
        </div>
        <div class="sales-command-actions">
            <div class="sales-command-actions__meta">
                <span class="badge-soft"><i class="bi bi-wallet2 me-1"></i><?= e(format_currency($grossValue)) ?> visible value</span>
                <span class="badge-soft"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(format_currency($refundValue)) ?> refunded</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge-soft"><i class="bi bi-funnel me-1"></i><?= e((string) $activeFilterCount) ?> active filters</span>
                <?php endif; ?>
            </div>
            <div class="sales-command-actions__links">
                <a href="<?= e(url('returns')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Returns</a>
                <a href="<?= e(url('pos')) ?>" class="btn btn-primary"><i class="bi bi-cart-check me-1"></i>Open POS</a>
            </div>
        </div>
    </div>

    <div class="sales-command-surface">
        <div class="sales-quick-views">
            <?php foreach ($quickViews as $quickView): ?>
                <a href="<?= e($quickView['href']) ?>" class="sales-filter-chip <?= $quickView['active'] ? 'sales-filter-chip--active' : '' ?>" <?= $quickView['active'] ? 'aria-current="page"' : '' ?>>
                    <span><?= e($quickView['label']) ?></span>
                    <small><?= e((string) $quickView['meta']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= e(url('sales')) ?>" class="sales-filter-grid">
            <div class="field-stack sales-filter-grid__search">
                <label class="form-label">Deep Search</label>
                <input id="deepSearchInput" type="text" name="search" autocomplete="off" class="form-control" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="Sale no., customer, cashier, payment reference, note, SKU, or barcode">
                <div id="salesSuggest" class="typeahead-dropdown" aria-hidden="true"></div>
                <div class="sales-search-helper">
                    <span class="badge-soft sales-search-shortcut"><span class="kbd-pill">/</span>Focuses search</span>
                    <?php foreach ($searchSuggestionSeeds as $seed): ?>
                        <button type="button" class="pos-filter-pill sales-search-token" data-sales-search-token="<?= e($seed) ?>"><?= e($seed) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted">Use this when the cashier only remembers part of the transaction. The dropdown suggestions come from the sales already on screen.</div>
            </div>
            <div class="field-stack sales-filter-grid__status">
                <label class="form-label">Status Queue</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-stack sales-filter-grid__cashier">
                <label class="form-label">Cashier</label>
                <select name="cashier_id" class="form-select">
                    <option value="">All cashiers</option>
                    <?php foreach ($cashiers as $cashier): ?>
                        <option value="<?= e((string) $cashier['id']) ?>" <?= (string) ($filters['cashier_id'] ?? '') === (string) $cashier['id'] ? 'selected' : '' ?>>
                            <?= e($cashier['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-stack sales-filter-grid__from">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="field-stack sales-filter-grid__to">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="sales-filter-actions">
                <div class="sales-filter-meta">
                    <span class="badge-soft"><i class="bi bi-list-ul me-1"></i><?= e((string) $visibleSalesCount) ?> visible sales</span>
                    <span class="badge-soft"><i class="bi bi-credit-card-2-front me-1"></i><?= e(format_currency($creditExposure)) ?> on account</span>
                    <span class="badge-soft"><i class="bi bi-hourglass-split me-1"></i><?= e((string) $voidPendingCount) ?> pending voids</span>
                </div>
                <div class="sales-filter-actions__buttons">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Apply Filters</button>
                    <a href="<?= e(url('sales')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
    (function(){
        const suggestionPool = <?= json_encode($searchSuggestionPool, $seedOptions) ?>;
        const input = document.getElementById('deepSearchInput');
        const dropdown = document.getElementById('salesSuggest');
        let activeIndex = -1;
        let items = [];

        function render() {
            if (items.length === 0) { dropdown.innerHTML = ''; dropdown.style.display = 'none'; return; }
            dropdown.style.display = 'block';
            dropdown.innerHTML = items.map((it, idx) => `
                <button type="button" class="typeahead-item" data-idx="${idx}" data-id="${it.id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">${escapeHtml(it.sale_number)} - ${escapeHtml(it.customer_name)}</div>
                            <div class="small text-muted">${escapeHtml(it.created_label)} | ${escapeHtml(it.grand_total_label)}</div>
                        </div>
                        <div class="text-end ms-2"><i class="bi bi-arrow-right"></i></div>
                    </div>
                </button>
            `).join('');
        }

        function escapeHtml(s){ return String(s).replace(/[&<>\"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
        function buildBlob(item){ return [item.sale_number, item.customer_name, item.customer_phone, item.cashier_name, item.payment_references, item.notes].join(' ').toLowerCase(); }
        function focusSearch(){ input.focus(); input.select(); }

        input.addEventListener('input', function(){
            const q = this.value.trim();
            if (q === '') { items = []; render(); return; }
            const query = q.toLowerCase();
            items = suggestionPool.map((item) => {
                const blob = buildBlob(item);
                if (!blob.includes(query)) {
                    return null;
                }

                let rank = 1;
                if ((item.sale_number || '').toLowerCase() === query) {
                    rank += 100;
                } else if ((item.sale_number || '').toLowerCase().startsWith(query)) {
                    rank += 80;
                }
                if ((item.customer_name || '').toLowerCase().includes(query)) {
                    rank += 40;
                }
                if ((item.customer_phone || '').toLowerCase().includes(query)) {
                    rank += 20;
                }
                if ((item.payment_references || '').toLowerCase().includes(query)) {
                    rank += 15;
                }

                return { ...item, rank };
            }).filter(Boolean).sort((left, right) => right.rank - left.rank).slice(0, 6);
            activeIndex = -1;
            render();
        });

        dropdown.addEventListener('click', function(e){
            const btn = e.target.closest('[data-id]');
            if (!btn) return; const id = btn.getAttribute('data-id'); if (id) { location.href = '<?= e(url('sales/show')) ?>?id=' + encodeURIComponent(id); }
        });

        input.addEventListener('keydown', function(e){
            if (dropdown.style.display === 'none') return;
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, items.length - 1); highlight(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlight(); }
            if (e.key === 'Enter') { e.preventDefault(); if (activeIndex >= 0 && items[activeIndex]) { location.href = '<?= e(url('sales/show')) ?>?id=' + encodeURIComponent(items[activeIndex].id); } else { this.form.submit(); } }
            if (e.key === 'Escape') { items = []; render(); }
        });

        function highlight(){
            Array.from(dropdown.querySelectorAll('.typeahead-item')).forEach((el, i)=>{ el.classList.toggle('active', i===activeIndex); });
        }

        document.addEventListener('click', function(e){
            if (!e.target.closest('.sales-filter-grid__search')) {
                items = [];
                render();
            }
        });

        window.addEventListener('keydown', function(e){
            const active = document.activeElement;
            const isTyping = active instanceof HTMLElement && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                focusSearch();
            }
        });

        document.querySelectorAll('[data-sales-search-token]').forEach((button) => {
            button.addEventListener('click', function(){
                input.value = button.getAttribute('data-sales-search-token') || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                focusSearch();
            });
        });
    })();
</script>

<div class="metric-grid mb-4">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Visible Sales</span><span>Current queue</span></div>
        <h3><?= e((string) $visibleSalesCount) ?></h3>
        <div class="text-muted">Transactions returned by the current filters.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Visible Value</span><span>Gross total</span></div>
        <h3><?= e(format_currency($grossValue)) ?></h3>
        <div class="text-muted">Across the sales currently on screen.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Refunded</span><span>Recovery impact</span></div>
        <h3><?= e(format_currency($refundValue)) ?></h3>
        <div class="text-muted"><?= e((string) $returnCount) ?> sales were returned or refunded.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Average Ticket</span><span>Current slice</span></div>
        <h3><?= e(format_currency($averageTicket)) ?></h3>
        <div class="text-muted">Useful when checking cashier performance or anomalies.</div>
    </section>
</div>

<section class="surface-card card-panel table-shell sales-register-shell">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-clock-history me-1"></i>Sales Ledger</p>
            <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>Transaction history</h3>
            <div class="text-muted">Each row surfaces payment mix, item volume, void activity, and customer context for fast investigation.</div>
        </div>
        <div class="table-shell__meta">
            <span class="badge-soft"><i class="bi bi-person-badge me-1"></i><?= e((string) count($cashiers)) ?> cashiers</span>
            <span class="badge-soft"><i class="bi bi-archive me-1"></i><?= e((string) $heldCount) ?> held</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle data-table" data-table-search="false">
            <thead>
                <tr>
                    <th>Sale</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Settlement</th>
                    <th>Status</th>
                    <th class="text-end no-sort actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <?php
                    $createdAt = trim((string) ($sale['created_at'] ?? ''));
                    $createdLabel = $createdAt !== '' ? date('M d, Y H:i', strtotime($createdAt)) : 'Unknown time';
                    $voidRequestedAt = trim((string) ($sale['void_requested_at'] ?? ''));
                    $voidRequestedLabel = $voidRequestedAt !== '' ? date('M d, H:i', strtotime($voidRequestedAt)) : 'Awaiting review';
                    $lineCount = (int) ($sale['line_count'] ?? 0);
                    $itemQuantityTotal = (float) ($sale['item_quantity_total'] ?? 0);
                    $customerName = trim((string) ($sale['customer_name'] ?? '')) ?: 'Walk-in customer';
                    $customerPhone = trim((string) ($sale['customer_phone'] ?? ''));
                    $paymentMethods = trim((string) ($sale['payment_methods'] ?? ''));
                    $paymentReferences = trim((string) ($sale['payment_references'] ?? ''));
                    $cashTendered = (float) ($sale['cash_tendered'] ?? 0);
                    $saleNotes = trim((string) ($sale['notes'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <div class="sales-row-main">
                                <div class="fw-semibold"><?= e((string) ($sale['sale_number'] ?? '')) ?></div>
                                <div class="small text-muted"><?= e($createdLabel) ?></div>
                            </div>
                            <div class="sales-inline-meta mt-2">
                                <span class="badge-soft"><i class="bi bi-bag-check me-1"></i><?= e((string) $lineCount) ?> lines</span>
                                <span class="badge-soft"><i class="bi bi-box-seam me-1"></i><?= e(number_format($itemQuantityTotal, 2)) ?> qty</span>
                            </div>
                            <?php if ($saleNotes !== ''): ?>
                                <div class="small text-muted mt-2">Note: <?= e($saleNotes) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="sales-row-main">
                                <div class="fw-semibold"><?= e($customerName) ?></div>
                                <div class="small text-muted"><?= e($customerPhone !== '' ? $customerPhone : 'No phone on file') ?></div>
                            </div>
                            <?php if ($paymentReferences !== ''): ?>
                                <div class="small text-muted mt-2">Ref: <?= e($paymentReferences) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="sales-row-main">
                                <div class="fw-semibold"><?= e((string) ($sale['cashier_name'] ?? '')) ?></div>
                                <div class="small text-muted">Handled this transaction</div>
                            </div>
                        </td>
                        <td>
                            <div class="sales-money-stack">
                                <div class="summary-row"><span>Total</span><strong><?= e(format_currency((float) ($sale['grand_total'] ?? 0))) ?></strong></div>
                                <div class="summary-row"><span>Refunded</span><strong><?= e(format_currency((float) ($sale['total_refund'] ?? 0))) ?></strong></div>
                                <div class="summary-row"><span>Collected</span><strong><?= e(format_currency((float) ($sale['collected_amount'] ?? 0))) ?></strong></div>
                                <?php if ($cashTendered > 0): ?>
                                    <div class="summary-row"><span>Cash Given</span><strong><?= e(format_currency($cashTendered)) ?></strong></div>
                                <?php endif; ?>
                                <div class="summary-row"><span>Credit</span><strong><?= e(format_currency((float) ($sale['credit_amount'] ?? 0))) ?></strong></div>
                            </div>
                            <div class="small text-muted mt-2"><?= e($paymentMethods !== '' ? ucwords($paymentMethods) : 'No payment methods recorded') ?></div>
                            <?php if ((float) ($sale['change_due'] ?? 0) > 0): ?>
                                <div class="small text-muted">Change due: <?= e(format_currency((float) ($sale['change_due'] ?? 0))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="sales-status-stack">
                                <span class="<?= e($statusClass($sale)) ?>"><?= e(str_replace('_', ' ', (string) ($sale['status'] ?? 'unknown'))) ?></span>
                                <?php if (($sale['void_request_status'] ?? '') === 'pending'): ?>
                                    <div class="small text-muted">Void requested by <?= e((string) ($sale['void_requested_by_name'] ?? 'staff')) ?></div>
                                    <div class="small text-muted"><?= e($voidRequestedLabel) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end actions">
                            <div class="sales-action-stack">
                                <a href="<?= e(url('sales/show?id=' . $sale['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Detail</a>
                                <a href="<?= e(url('pos/receipt?id=' . $sale['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1"></i>Receipt</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
