<?php
$catalog = $catalog ?? [];
$catalogMeta = $catalogMeta ?? [];
$catalogFilteredTotal = (int) ($catalogFilteredTotal ?? count($catalog));
$customers = $customers ?? [];
$heldSales = $heldSales ?? [];
$recallSale = $recallSale ?? null;
$receiptModal = $receiptModal ?? null;
$holdSubmissionKey = $holdSubmissionKey ?? '';
$checkoutSubmissionKey = $checkoutSubmissionKey ?? '';
$operatorName = (string) (current_user()['full_name'] ?? 'Sales Staff');
$branchName = (string) (current_user()['branch_name'] ?? 'Main Branch');
$currencyLabel = currency_symbol();
$todayLabel = date('D, d M Y');

$catalogSeed = array_map(static function (array $product): array {
    $imagePath = trim((string) ($product['image_path'] ?? ''));
    $categoryName = trim((string) ($product['category_name'] ?? ''));
    $stockQuantity = (float) ($product['stock_quantity'] ?? 0);
    $lowStockThreshold = (float) ($product['low_stock_threshold'] ?? 0);

    return [
        'id' => (string) ($product['id'] ?? ''),
        'name' => (string) ($product['name'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'sku' => (string) ($product['sku'] ?? ''),
        'barcode' => (string) ($product['barcode'] ?? ''),
        'unit' => (string) ($product['unit'] ?? 'unit'),
        'price' => (float) ($product['price'] ?? 0),
        'tax_rate' => (float) ($product['tax_rate'] ?? 0),
        'category_id' => (string) ($product['category_id'] ?? ''),
        'category_name' => $categoryName !== '' ? $categoryName : 'Uncategorized',
        'track_stock' => (int) ($product['track_stock'] ?? 0),
        'stock_quantity' => $stockQuantity,
        'low_stock_threshold' => $lowStockThreshold,
        'is_low_stock' => (int) ($product['track_stock'] ?? 0) === 1 && $stockQuantity <= max($lowStockThreshold, 0),
        'image_url' => $imagePath !== '' ? url($imagePath) : '',
        'search_blob' => strtolower(trim(implode(' ', [
            (string) ($product['name'] ?? ''),
            (string) ($product['brand'] ?? ''),
            (string) ($product['sku'] ?? ''),
            (string) ($product['barcode'] ?? ''),
            $categoryName,
            (string) ($product['unit'] ?? ''),
        ]))),
    ];
}, $catalog);

$customerSeed = array_map(static function (array $customer): array {
    $fullName = trim((string) ($customer['full_name'] ?? ''));

    return [
        'id' => (string) ($customer['id'] ?? ''),
        'full_name' => $fullName !== '' ? $fullName : 'Walk-in Customer',
        'phone' => (string) ($customer['phone'] ?? ''),
        'email' => (string) ($customer['email'] ?? ''),
        'credit_balance' => (float) ($customer['credit_balance'] ?? 0),
        'loyalty_balance' => (int) ($customer['loyalty_balance'] ?? 0),
        'special_pricing_type' => (string) ($customer['special_pricing_type'] ?? 'none'),
        'special_pricing_value' => (float) ($customer['special_pricing_value'] ?? 0),
        'customer_group_name' => (string) ($customer['customer_group_name'] ?? ''),
        'total_orders' => (int) ($customer['total_orders'] ?? 0),
        'total_spent' => (float) ($customer['total_spent'] ?? 0),
        'last_purchase_at' => (string) ($customer['last_purchase_at'] ?? ''),
        'search_blob' => strtolower(trim(implode(' ', [
            $fullName,
            (string) ($customer['phone'] ?? ''),
            (string) ($customer['email'] ?? ''),
            (string) ($customer['customer_group_name'] ?? ''),
        ]))),
    ];
}, $customers);

$heldSaleSeed = array_map(static function (array $sale): array {
    $createdAt = trim((string) ($sale['created_at'] ?? ''));

    return [
        'id' => (string) ($sale['id'] ?? ''),
        'sale_number' => (string) ($sale['sale_number'] ?? ''),
        'grand_total' => (float) ($sale['grand_total'] ?? 0),
        'customer_name' => (string) ($sale['customer_name'] ?? 'Walk-in customer'),
        'created_at' => $createdAt,
        'created_label' => $createdAt !== '' ? date('M d, H:i', strtotime($createdAt)) : 'Queued',
    ];
}, $heldSales);

$recallSeed = null;
if (is_array($recallSale)) {
    $recallSeed = [
        'held_sale_id' => (string) ($recallSale['id'] ?? ''),
        'sale_number' => (string) ($recallSale['sale_number'] ?? ''),
        'customer_id' => $recallSale['customer_id'] !== null ? (string) $recallSale['customer_id'] : '',
        'customer_name' => (string) ($recallSale['customer_name'] ?? 'Walk-in Customer'),
        'notes' => (string) ($recallSale['notes'] ?? ''),
        'redeem_points' => (int) ($recallSale['loyalty_points_redeemed'] ?? 0),
        'order_discount_type' => 'fixed',
        'order_discount_value' => (float) ($recallSale['order_discount_total'] ?? 0),
        'cart' => array_map(static function (array $item): array {
            return [
                'product_id' => (string) ($item['product_id'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'discount_type' => (string) ($item['discount_type'] ?? 'fixed'),
                'discount_value' => (float) ($item['discount_value'] ?? 0),
            ];
        }, $recallSale['items'] ?? []),
        'payments' => array_map(static function (array $payment): array {
            return [
                'method' => (string) ($payment['payment_method'] ?? 'cash'),
                'amount' => (float) ($payment['amount'] ?? 0),
                'reference' => (string) ($payment['reference'] ?? ''),
                'notes' => (string) ($payment['notes'] ?? ''),
                'cheque_number' => (string) ($payment['cheque_number'] ?? ''),
                'cheque_bank' => (string) ($payment['cheque_bank'] ?? ''),
                'cheque_date' => (string) ($payment['cheque_date'] ?? ''),
            ];
        }, $recallSale['payments'] ?? []),
    ];
}

$heldSaleCount = count($heldSaleSeed);
$catalogCount = (int) ($catalogMeta['total_count'] ?? count($catalogSeed));
$lowStockCount = (int) ($catalogMeta['low_stock_count'] ?? count(array_filter($catalogSeed, static fn (array $product): bool => (bool) ($product['is_low_stock'] ?? false))));
$seedOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?>

<div
    class="pos-workspace"
    x-data='window.posTerminal(<?= json_encode([
        'catalog' => $catalogSeed,
        'catalogMeta' => $catalogMeta,
        'catalogFilteredTotal' => $catalogFilteredTotal,
        'catalogUrl' => url('pos/catalog'),
        'customers' => $customerSeed,
        'heldSales' => $heldSaleSeed,
        'recall' => $recallSeed,
        'currencyLabel' => $currencyLabel,
        'operatorName' => $operatorName,
        'branchName' => $branchName,
        'customersUrl' => url('customers/suggest'),
    ], $seedOptions) ?>)'
    x-init="init()"
    @keydown.window="handleHotkeys($event)"
>
    <section class="surface-card card-panel pos-command-bar">
        <div class="pos-command-bar__title">
            <h3 class="mb-0">POS Terminal</h3>
        </div>
        <div class="pos-command-bar__meta">
            <span class="badge-soft pos-command-chip pos-command-chip--operator"><i class="bi bi-person-badge me-1"></i><?= e($operatorName) ?></span>
            <span class="badge-soft pos-command-chip pos-command-chip--branch"><i class="bi bi-shop me-1"></i><?= e($branchName) ?></span>
            <span class="badge-soft pos-command-chip pos-command-chip--date"><i class="bi bi-calendar3 me-1"></i><?= e($todayLabel) ?></span>
            <span class="badge-soft pos-command-chip pos-command-chip--catalog"><i class="bi bi-box-seam me-1"></i><?= e((string) $catalogCount) ?> catalog</span>
            <span class="badge-soft pos-command-chip pos-command-chip--held"><i class="bi bi-clock-history me-1"></i><?= e((string) $heldSaleCount) ?> held</span>
            <span class="badge-soft pos-command-chip pos-command-chip--stock"><i class="bi bi-exclamation-triangle me-1"></i><?= e((string) $lowStockCount) ?> low stock</span>
        </div>
    </section>

    <?php if ($recallSeed !== null): ?>
        <section class="surface-card card-panel pos-resume-banner">
            <div>
                <p class="eyebrow mb-1">Held Sale Recalled</p>
                <h4 class="mb-1"><?= e($recallSeed['sale_number']) ?> is back on the register</h4>
                <div class="text-muted">Customer: <?= e($recallSeed['customer_name']) ?><?= $recallSeed['notes'] !== '' ? ' | Notes carried over for checkout.' : '' ?></div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= e(url('sales')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Sales History</a>
                <a href="<?= e(url('pos')) ?>" class="btn btn-outline-primary"><i class="bi bi-x-lg me-1"></i>Start Fresh Sale</a>
            </div>
        </section>
    <?php endif; ?>

    <div class="pos-mobile-switcher" x-show="isCompactViewport" x-cloak>
        <button
            type="button"
            class="pos-mobile-switcher__button"
            :class="{ 'is-active': activeMobilePane === 'catalog' }"
            @click="switchMobilePane('catalog')"
        >
            <span>Catalog</span>
            <small x-text="resultsSummary"></small>
        </button>
        <button
            type="button"
            class="pos-mobile-switcher__button"
            :class="{ 'is-active': activeMobilePane === 'cart' }"
            @click="switchMobilePane('cart')"
        >
            <span>Basket</span>
            <small x-text="cart.length > 0 ? `${computed.lineCount} lines | ${currency(computed.totals.grand_total)}` : 'No items yet'"></small>
        </button>
    </div>

    <div class="pos-layout pos-layout--modern">
        <section
            x-ref="catalogPanel"
            class="surface-card card-panel pos-panel pos-panel--catalog"
            :class="{ 'is-mobile-hidden': isCompactViewport && activeMobilePane !== 'catalog' }"
        >
            <div class="pos-catalog-toolbar">
                <div class="pos-catalog-toolbar__search">
                    <label for="posProductSearch" class="form-label visually-hidden">Product Search</label>
                    <div class="pos-search-input">
                        <i class="bi bi-search"></i>
                        <input
                            id="posProductSearch"
                            x-ref="productSearch"
                            type="search"
                            class="form-control"
                            x-model="search"
                            @input.debounce.120ms="resetCatalogPage()"
                            @keydown.enter.prevent="addFirstVisibleProduct()"
                            placeholder="Scan barcode or search name, brand, SKU, or category"
                            autocomplete="off"
                        >
                        <button type="button" class="btn btn-outline-secondary btn-sm" x-show="search !== ''" @click="search = ''; resetCatalogPage(); $refs.productSearch.focus()" x-cloak>
                            Clear
                        </button>
                    </div>
                </div>

                <div class="pos-catalog-toolbar__filters">
                    <div class="pos-select-stack">
                        <label for="posCatalogBrand" class="form-label visually-hidden">Brand</label>
                        <select id="posCatalogBrand" class="form-select pos-select-control" x-model="brandFilter" @change="resetCatalogPage()">
                            <option value="">All brands</option>
                            <template x-for="brand in brandOptions" :key="brand.key">
                                <option :value="brand.key" x-text="`${brand.label} (${brand.count})`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="pos-select-stack">
                        <label for="posCatalogCategory" class="form-label visually-hidden">Category</label>
                        <select id="posCatalogCategory" class="form-select pos-select-control" x-model="categoryId" @change="resetCatalogPage()">
                            <option value="">All categories</option>
                            <template x-for="category in categoryOptions" :key="category.id">
                                <option :value="category.id" x-text="`${category.name} (${category.count})`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="pos-select-stack">
                        <label for="posCatalogStock" class="form-label visually-hidden">Stock</label>
                        <select id="posCatalogStock" class="form-select pos-select-control" x-model="stockFilter" @change="resetCatalogPage()">
                            <option value="all">All stock states</option>
                            <option value="available">Available stock</option>
                            <option value="low_stock">Low stock</option>
                            <option value="out_of_stock">Out of stock</option>
                            <option value="open">Stock not tracked</option>
                        </select>
                    </div>
                </div>
            </div>

            <template x-if="heldSales.length > 0">
                <div class="pos-held-strip pos-held-strip--compact">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <strong>Held sales</strong>
                        <div class="small text-muted">Resume parked baskets without rebuilding them.</div>
                    </div>
                    <div class="held-sale-list">
                        <template x-for="sale in heldSales" :key="sale.id">
                            <a class="held-sale-pill" :href="`<?= e(url('pos')) ?>?held_id=${sale.id}`">
                                <span>
                                    <strong x-text="sale.sale_number"></strong>
                                    <small x-text="sale.customer_name"></small>
                                </span>
                                <span>
                                    <strong x-text="currency(sale.grand_total)"></strong>
                                    <small x-text="sale.created_label"></small>
                                </span>
                            </a>
                        </template>
                    </div>
                </div>
            </template>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h4 class="mb-0">Product catalog</h4>
                    <div class="small text-muted" x-text="catalogSummary"></div>
                </div>
                <span class="badge-soft" x-text="`${catalogStats.filtered} products`"></span>
            </div>

            <div class="pos-catalog-nav">
                <div class="pos-filter-pill-set" aria-label="Catalog quick filters">
                    <template x-for="filter in catalogQuickFilters" :key="filter.id">
                        <button
                            type="button"
                            class="pos-filter-pill"
                            :class="{ 'is-active': quickMode === filter.id }"
                            :disabled="filter.id !== 'all' && filter.count === 0"
                            @click="setQuickMode(filter.id)"
                        >
                            <span x-text="filter.label"></span>
                            <small x-text="filter.count"></small>
                        </button>
                    </template>
                </div>

                <div class="pos-catalog-nav__actions">
                    <div class="pos-catalog-nav__meta" x-text="catalogPagerLabel"></div>

                    <label class="pos-catalog-nav__rows">
                        <span>Rows</span>
                        <select class="form-select form-select-sm pos-catalog-nav__select" x-model.number="catalogPageSize" @change="setCatalogPageSize($event.target.value)">
                            <option value="8">8</option>
                            <option value="12">12</option>
                            <option value="20">20</option>
                            <option value="40">40</option>
                        </select>
                    </label>

                    <label class="pos-catalog-nav__rows">
                        <span>Sort</span>
                        <select class="form-select form-select-sm pos-catalog-nav__select" x-model="catalogSort" @change="resetCatalogPage()">
                            <option value="relevance">Smart</option>
                            <option value="name">Name</option>
                            <option value="price">Price</option>
                            <option value="stock">Stock</option>
                        </select>
                    </label>

                    <div class="pos-catalog-nav__pager">
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="previousCatalogPage()" :disabled="!catalogPager.canPrevious">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="nextCatalogPage()" :disabled="!catalogPager.canNext">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm" x-show="hasCatalogFilters" @click="clearCatalogFilters()" x-cloak>
                        Reset
                    </button>
                </div>
            </div>

            <div class="pos-catalog-surface" x-show="catalogStats.filtered > 0" x-cloak>
                <div class="pos-catalog-list" role="list">
                    <template x-for="product in pagedCatalog" :key="product.id">
                        <article class="pos-catalog-item" :class="stockTone(product)" role="listitem">
                            <button
                                type="button"
                                class="btn btn-primary pos-row-add"
                                @click="addProduct(product.id)"
                                :title="`Add ${product.name}`"
                            >
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <div class="pos-catalog-item__body">
                                <div class="pos-catalog-item__head">
                                    <strong x-text="product.name"></strong>
                                    <span class="badge-soft" x-show="product.category_name" x-cloak x-text="product.category_name"></span>
                                </div>
                                <div class="pos-catalog-item__meta">
                                    <span x-text="product.brand || 'Unbranded'"></span>
                                    <span x-text="product.sku || product.barcode || 'No code'"></span>
                                    <span x-text="product.unit || 'unit'"></span>
                                </div>
                            </div>
                            <div class="pos-catalog-item__aside">
                                <span class="pos-stock-chip" :class="stockTone(product)" x-text="stockLabel(product)"></span>
                                <div class="pos-catalog-item__price">
                                    <strong x-text="currency(displayPrice(product))"></strong>
                                    <small x-show="hasSpecialPricing(product)" x-cloak x-text="`Base ${currency(Number(product.price || 0))}`"></small>
                                    <small x-show="!hasSpecialPricing(product)" x-cloak x-text="product.category_name || 'Ready for sale'"></small>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <div class="empty-state" x-show="catalogStats.filtered === 0" x-cloak>
                <div class="fw-semibold mb-2">No catalog matches.</div>
                <div>Adjust the search, filters, or quick mode to bring products back into view.</div>
            </div>

        </section>

        <aside
            x-ref="cartPanel"
            class="surface-card card-panel pos-panel pos-panel--cart pos-checkout-rail"
            :class="{ 'is-mobile-hidden': isCompactViewport && activeMobilePane !== 'cart' }"
        >
            <div class="pos-cart-header pos-checkout-rail__header">
                <div class="pos-checkout-rail__heading">
                    <p class="eyebrow mb-1">Current Basket</p>
                    <h3 class="mb-1">Checkout</h3>
                    <div class="small text-muted" x-text="cart.length > 0 ? `${computed.lineCount} line${computed.lineCount === 1 ? '' : 's'} ready for payment` : 'Add items from the catalog to start the sale.'"></div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearCart()" :disabled="cart.length === 0">Clear</button>
            </div>

            <div class="pos-summary-strip pos-summary-strip--compact pos-checkout-rail__summary">
                <div class="pos-stat pos-stat--accent">
                    <span class="pos-stat__label">Due</span>
                    <strong class="pos-stat__value" x-text="currency(computed.totals.grand_total)"></strong>
                    <small class="pos-stat__meta" x-text="computed.payment.remaining_due > 0.009 ? `${currency(computed.payment.remaining_due)} left` : 'Covered'"></small>
                </div>
                <div class="pos-stat">
                    <span class="pos-stat__label">Buyer</span>
                    <strong class="pos-stat__value pos-stat__value--compact" x-text="selectedCustomer ? selectedCustomer.full_name : 'Walk-in customer'"></strong>
                    <small class="pos-stat__meta" x-text="selectedCustomer ? customerCompactMeta(selectedCustomer) : 'Attach buyer profile'"></small>
                </div>
                <div class="pos-stat">
                    <span class="pos-stat__label">Collected</span>
                    <strong class="pos-stat__value" x-text="currency(computed.payment.collected_amount)"></strong>
                    <small class="pos-stat__meta" x-text="paymentStatusShort()"></small>
                </div>
            </div>

            <div class="pos-cart-body pos-checkout-rail__body">
            <section class="pos-cart-list-shell pos-checkout-card pos-checkout-card--basket">
                <div class="pos-checkout-card__header">
                    <div class="pos-checkout-card__copy">
                        <p class="eyebrow mb-1">Basket</p>
                        <h4 class="mb-0">Order items</h4>
                    </div>
                    <span class="badge-soft pos-checkout-card__badge" x-text="cart.length > 0 ? `${computed.lineCount} line${computed.lineCount === 1 ? '' : 's'}` : 'Waiting for items'"></span>
                </div>

                <div class="cart-empty pos-checkout-empty" x-show="cart.length === 0" x-cloak>
                    Search or scan a product to start the sale. The first match can be added with Enter from the product finder.
                </div>

                <div class="pos-cart-list" x-show="cart.length > 0" x-cloak>
                    <template x-for="line in computed.lines" :key="line.product.id">
                        <article class="pos-cart-line pos-cart-line--modern" :class="{ 'has-warning': line.stock_issue }">
                            <div class="pos-cart-line__top">
                                <div>
                                    <strong x-text="line.product.name"></strong>
                                    <div class="small text-muted" x-text="line.product.brand || line.product.sku || line.product.barcode || line.product.category_name"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeProduct(line.product.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div class="pos-cart-line__controls">
                                <div class="qty-stepper">
                                    <button type="button" @click="changeQuantity(line.product.id, -1)">-</button>
                                    <input
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        :value="quantityInputValue(line.product.id, line.source.quantity)"
                                        @focus="beginQuantityEdit(line.product.id, $event)"
                                        @input="handleQuantityInput(line.product.id, $event.target.value)"
                                        @keydown.enter.prevent="$event.target.blur()"
                                        @keydown.escape.prevent="cancelQuantityEdit(line.product.id, $event)"
                                        @blur="commitQuantityInput(line.product.id, $event)"
                                    >
                                    <button type="button" @click="changeQuantity(line.product.id, 1)">+</button>
                                </div>

                                <div class="pos-inline-fields">
                                    <select class="form-select" x-model="line.source.discount_type" @change="recalculate()">
                                        <option value="fixed">Fixed</option>
                                        <option value="percent">Percent</option>
                                    </select>
                                    <input type="number" min="0" step="0.01" class="form-control" x-model.number="line.source.discount_value" @input.debounce.150ms="recalculate()" placeholder="Discount">
                                </div>
                            </div>

                            <div class="pos-cart-line__summary">
                                <span x-text="currency(line.unit_price) + ' x ' + line.quantity"></span>
                                <span x-text="currency(line.line_total)"></span>
                            </div>

                            <div class="pos-inline-warning" x-show="line.stock_issue" x-cloak>
                                Requested quantity exceeds current stock on hand for this branch.
                            </div>
                        </article>
                    </template>
                </div>
            </section>

            <section class="pos-customer-card pos-checkout-card">
                <div class="pos-checkout-card__header">
                    <div class="pos-checkout-card__copy">
                        <p class="eyebrow mb-1">Customer</p>
                        <h4 class="mb-0">Buyer profile</h4>
                    </div>
                    <span class="badge-soft pos-checkout-card__badge" x-text="selectedCustomer ? 'Profile active' : 'Walk-in mode'"></span>
                </div>

                <div class="pos-customer-picker pos-customer-picker--stacked">
                    <div class="pos-customer-search">
                        <i class="bi bi-person-circle"></i>
                        <input type="search" class="form-control" x-model="customerSearch" @input="scheduleCustomerLookup()" @focus="fetchCustomers({ query: customerSearch, limit: customerSearch.trim() === '' ? 12 : 20, replace: false })" placeholder="Filter customer dropdown by name, phone, or email">
                    </div>

                    <div class="pos-customer-select-row">
                        <div class="pos-select-stack">
                            <label for="posCustomerSelect" class="form-label visually-hidden">Customer dropdown</label>
                            <select id="posCustomerSelect" class="form-select pos-select-control" x-model="selectedCustomerId" @focus="fetchCustomers({ query: customerSearch, limit: customerSearch.trim() === '' ? 12 : 20, replace: false })" @change="selectCustomer($event.target.value)">
                                <option value="">Walk-in customer</option>
                                <template x-for="customer in customerSelectOptions" :key="customer.id">
                                    <option :value="customer.id" x-text="customerOptionLabel(customer)"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-secondary" @click="selectCustomer('')" :disabled="!selectedCustomer">
                            Walk-in
                        </button>
                    </div>
                </div>

                <div class="small text-muted pos-customer-picker__summary" x-text="customerSelectSummary"></div>

                <div class="cart-empty pos-checkout-empty" x-show="customerSearch.trim() !== '' && customerSelectOptions.length === 0" x-cloak>
                    No customers match this filter. Clear the search or keep this sale as walk-in.
                </div>

                <template x-if="selectedCustomer">
                    <div class="pos-customer-profile pos-customer-summary pos-customer-summary--selected pos-customer-summary--compact">
                        <div class="pos-customer-profile__hero">
                            <div class="pos-customer-avatar" x-text="customerInitials(selectedCustomer)"></div>
                            <div class="pos-customer-profile__identity">
                                <div class="pos-customer-profile__title">
                                    <div>
                                        <strong x-text="selectedCustomer.full_name"></strong>
                                        <div class="small text-muted" x-text="customerCompactMeta(selectedCustomer)"></div>
                                    </div>
                                    <span class="badge-soft" x-show="selectedCustomer.customer_group_name" x-cloak x-text="selectedCustomer.customer_group_name"></span>
                                </div>

                                <div class="pos-customer-profile__contacts">
                                    <span class="pos-customer-contact" x-show="selectedCustomer.phone" x-cloak>
                                        <i class="bi bi-telephone"></i>
                                        <span x-text="selectedCustomer.phone"></span>
                                    </span>
                                    <span class="pos-customer-contact" x-show="selectedCustomer.email" x-cloak>
                                        <i class="bi bi-envelope"></i>
                                        <span x-text="selectedCustomer.email"></span>
                                    </span>
                                    <span class="pos-customer-contact" x-show="!selectedCustomer.phone && !selectedCustomer.email" x-cloak>
                                        <i class="bi bi-person-vcard"></i>
                                        <span>No phone or email saved</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pos-customer-facts">
                            <article class="pos-customer-fact">
                                <span>Loyalty</span>
                                <strong x-text="`${selectedCustomer.loyalty_balance} pts`"></strong>
                            </article>
                            <article class="pos-customer-fact">
                                <span>Credit</span>
                                <strong x-text="currency(selectedCustomer.credit_balance)"></strong>
                            </article>
                            <article class="pos-customer-fact">
                                <span>Pricing</span>
                                <strong x-text="pricingLabel(selectedCustomer)"></strong>
                            </article>
                            <article class="pos-customer-fact">
                                <span>Last sale</span>
                                <strong x-text="customerLastPurchaseLabel(selectedCustomer)"></strong>
                            </article>
                        </div>
                    </div>
                </template>

                <template x-if="!selectedCustomer">
                    <div class="pos-customer-inline-note">
                        <i class="bi bi-person-plus"></i>
                        <span>Walk-in checkout. Attach a buyer to enable loyalty, open account credit, and customer pricing.</span>
                    </div>
                </template>
            </section>

            <section class="cart-summary-card pos-summary-card--modern pos-checkout-card" x-show="cart.length > 0" x-cloak>
                <div class="pos-checkout-card__header">
                    <div class="pos-checkout-card__copy">
                        <p class="eyebrow mb-1">Discounts</p>
                        <h4 class="mb-0">Discounts & totals</h4>
                    </div>
                    <span class="badge-soft pos-checkout-card__badge" x-text="selectedCustomer ? pricingLabel(selectedCustomer) : 'Standard pricing'"></span>
                </div>

                <div class="pos-checkout-note pos-checkout-note--compact">
                    <strong x-text="selectedCustomer ? pricingLabel(selectedCustomer) : 'Standard register pricing'"></strong>
                    <small x-text="selectedCustomer ? (maxRedeemablePoints() > 0 ? `${maxRedeemablePoints()} pts ready to redeem` : 'No loyalty points ready on this basket') : 'Attach buyer to unlock loyalty and account credit'"></small>
                </div>

                <div class="pos-inline-fields pos-inline-fields--wide">
                    <select class="form-select" x-model="orderDiscountType" @change="recalculate()">
                        <option value="fixed">Order discount: fixed</option>
                        <option value="percent">Order discount: percent</option>
                    </select>
                    <input type="number" min="0" step="0.01" class="form-control" x-model.number="orderDiscountValue" @input.debounce.150ms="recalculate()" placeholder="Order discount value">
                </div>

                <div class="pos-inline-fields pos-inline-fields--wide">
                    <input type="number" min="0" step="1" class="form-control" x-model.number="redeemPoints" @input.debounce.150ms="syncRedeemPoints()" placeholder="Redeem loyalty points">
                    <button type="button" class="btn btn-outline-secondary" @click="redeemPoints = maxRedeemablePoints(); recalculate()" :disabled="!selectedCustomer || maxRedeemablePoints() === 0">
                        Max Points
                    </button>
                </div>

                <div class="pos-totals-card">
                    <div class="summary-row"><span>Subtotal</span><strong x-text="currency(computed.totals.subtotal)"></strong></div>
                    <div class="summary-row" x-show="computed.totals.item_discount_total > 0" x-cloak><span>Item Discounts</span><strong x-text="currency(computed.totals.item_discount_total)"></strong></div>
                    <div class="summary-row" x-show="computed.totals.order_discount_total > 0" x-cloak><span>Order Discount</span><strong x-text="currency(computed.totals.order_discount_total)"></strong></div>
                    <div class="summary-row" x-show="computed.totals.loyalty_discount_total > 0" x-cloak><span>Loyalty Discount</span><strong x-text="currency(computed.totals.loyalty_discount_total)"></strong></div>
                    <div class="summary-row" x-show="computed.totals.tax_total > 0" x-cloak><span>Tax</span><strong x-text="currency(computed.totals.tax_total)"></strong></div>
                    <div class="summary-row summary-row--total"><span>Grand Total</span><strong x-text="currency(computed.totals.grand_total)"></strong></div>
                </div>
            </section>

            <section class="pos-payment-card pos-checkout-card" x-show="cart.length > 0" x-cloak>
                <div class="pos-checkout-card__header">
                    <div class="pos-checkout-card__copy">
                        <p class="eyebrow mb-1">Payment</p>
                        <h4 class="mb-0">Collection</h4>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="addPayment()">Add Split</button>
                </div>

                <div class="pos-payment-status-strip">
                    <div class="pos-mini-stat">
                        <span>Amount Due</span>
                        <strong x-text="currency(computed.payment.required_collected)"></strong>
                        <small>Amount to collect now</small>
                    </div>
                    <div class="pos-mini-stat" :class="{ 'is-ready': computed.payment.remaining_due <= 0.009, 'is-warning': computed.payment.remaining_due > 0.009 }">
                        <span>Still Due</span>
                        <strong x-text="currency(computed.payment.remaining_due)"></strong>
                        <small x-text="computed.payment.remaining_due <= 0.009 ? 'Fully covered' : 'Collect balance'"></small>
                    </div>
                    <div class="pos-mini-stat">
                        <span>Cash Received</span>
                        <strong x-text="currency(computed.payment.cash_tendered)"></strong>
                        <small x-text="computed.payment.cash_tendered > 0.009 ? `${currency(computed.payment.cash_applied)} applied to sale` : 'Enter amount received from customer'"></small>
                    </div>
                    <div class="pos-mini-stat" :class="{ 'is-ready': computed.payment.change_due > 0.009 }">
                        <span>Change To Return</span>
                        <strong x-text="currency(computed.payment.change_due)"></strong>
                        <small x-text="computed.payment.change_due > 0.009 ? 'Give back to customer' : 'No change to return'"></small>
                    </div>
                    <div class="pos-mini-stat">
                        <span>On account</span>
                        <strong x-text="currency(computed.payment.credit_amount)"></strong>
                        <small x-text="selectedCustomer ? 'Customer balance' : 'Needs buyer'"></small>
                    </div>
                </div>

                <div class="pos-payment-toolbar">
                    <div class="pos-select-stack">
                        <label for="posQuickPaymentMethod" class="form-label mb-0">Quick payment method</label>
                        <select id="posQuickPaymentMethod" class="form-select pos-select-control" x-model="quickPaymentMethod">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="credit" :disabled="!selectedCustomer">Open Account</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" @click="applyQuickPaymentMethod()" :disabled="quickPaymentMethod === 'credit' && !selectedCustomer">
                        Use For Sale
                    </button>
                </div>

                <div class="pos-payment-list">
                    <template x-for="(payment, index) in payments" :key="`${payment.method}-${index}`">
                        <div class="pos-payment-row">
                            <div class="pos-payment-row__header">
                                <div class="pos-payment-row__title">
                                    <strong x-text="`Split ${index + 1}`"></strong>
                                    <small x-text="paymentMethodLabel(payment.method)"></small>
                                </div>
                                <span class="pos-payment-row__badge" :class="{ 'is-auto': payment.is_auto }" x-text="payment.is_auto ? 'Exact due' : 'Manual entry'"></span>
                            </div>
                            <div class="pos-payment-row__top">
                                <select class="form-select" x-model="payment.method" @change="handlePaymentMethodChange(index)">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="credit" :disabled="!selectedCustomer">Open Account</option>
                                </select>
                                <input type="number" min="0" step="0.01" class="form-control" :value="paymentAmountInputValue(index)" @focus="beginPaymentAmountEdit(index, $event)" @input.debounce.150ms="paymentAmountDrafts[index] = $event.target.value; handlePaymentAmountInput(index)" @keydown.enter.prevent="$event.target.blur()" @blur="endPaymentAmountEdit(index, $event)" :placeholder="paymentAmountPlaceholder(payment.method)" :aria-label="paymentAmountPlaceholder(payment.method)" :data-payment-amount-index="index">
                            </div>
                            <div class="pos-payment-row__meta">
                                <input type="text" class="form-control" x-model="payment.reference" :placeholder="paymentReferencePlaceholder(payment.method)">
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="fillRemaining(index)">Use Exact Due</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" @click="removePayment(index)" :disabled="payments.length === 1">Remove</button>
                            </div>
                            <div class="pos-payment-row__detail-grid" x-show="payment.method === 'cheque'" x-cloak>
                                <input type="text" class="form-control" x-model="payment.cheque_number" @input.debounce.150ms="handlePaymentDetailInput(index)" placeholder="Cheque number">
                                <input type="text" class="form-control" x-model="payment.cheque_bank" @input.debounce.150ms="handlePaymentDetailInput(index)" placeholder="Bank name">
                                <input type="date" class="form-control" x-model="payment.cheque_date" @change="handlePaymentDetailInput(index)" aria-label="Cheque date">
                            </div>
                            <div class="pos-payment-row__quickcash" x-show="payment.method === 'cash' && cashTenderSuggestions(index).length > 0" x-cloak>
                                <template x-for="suggestion in cashTenderSuggestions(index)" :key="`${index}-${suggestion.label}-${suggestion.amount}`">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        :class="{ 'btn-primary': suggestion.tone === 'primary' }"
                                        @click="applyCashTenderSuggestion(index, suggestion.amount)"
                                        x-text="`${suggestion.label}: ${currency(suggestion.amount)}`"
                                    ></button>
                                </template>
                            </div>
                            <div class="pos-payment-row__support" x-show="payment.method === 'cash' || payment.method === 'cheque'" x-cloak>
                                <strong x-text="payment.method === 'cash' ? cashPaymentSummary(index) : chequePaymentSummary(index)"></strong>
                                <small x-text="payment.method === 'cash' ? cashPaymentSupportText(index) : chequePaymentSupportText(index)"></small>
                            </div>
                            <div class="small text-muted" x-show="payment.method === 'credit' && !selectedCustomer" x-cloak>
                                Attach a customer before assigning any amount to open account credit.
                            </div>
                        </div>
                    </template>
                </div>
            </section>
            </div>

            <div class="pos-action-row pos-checkout-rail__footer" x-ref="checkoutActions">
                <div class="pos-action-row__meta">
                    <strong x-text="checkoutCalloutTitle()"></strong>
                    <small x-text="checkoutCalloutText()"></small>
                </div>
                <form x-ref="holdForm" action="<?= e(url('pos/hold')) ?>" method="post" class="pos-action-form" data-ajax="true" data-reload-on-success="false" data-loading-mode="pos" data-skip-app-loader="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submission_key" value="<?= e((string) $holdSubmissionKey) ?>">
                    <input type="hidden" name="customer_id" :value="selectedCustomerId">
                    <input type="hidden" name="held_sale_id" :value="heldSaleId">
                    <input type="hidden" name="order_discount_type" :value="orderDiscountType">
                    <input type="hidden" name="order_discount_value" :value="safeNumber(orderDiscountValue)">
                    <input type="hidden" name="redeem_points" :value="safeInteger(redeemPoints)">
                    <input type="hidden" name="notes" :value="notes">
                    <input type="hidden" name="cart_payload" :value="cartPayload">
                    <button x-ref="holdButton" type="submit" class="btn btn-outline-secondary w-100" :disabled="cart.length === 0 || pendingAction !== ''" @click.prevent="submitAction('hold', $event)">
                        <i class="bi bi-pause-circle me-1"></i>Hold Sale
                    </button>
                </form>

                <form x-ref="checkoutForm" action="<?= e(url('pos/checkout')) ?>" method="post" class="pos-action-form" data-ajax="true" data-reload-on-success="false" data-loading-mode="pos" data-skip-app-loader="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submission_key" value="<?= e((string) $checkoutSubmissionKey) ?>">
                    <input type="hidden" name="customer_id" :value="selectedCustomerId">
                    <input type="hidden" name="held_sale_id" :value="heldSaleId">
                    <input type="hidden" name="order_discount_type" :value="orderDiscountType">
                    <input type="hidden" name="order_discount_value" :value="safeNumber(orderDiscountValue)">
                    <input type="hidden" name="redeem_points" :value="safeInteger(redeemPoints)">
                    <input type="hidden" name="notes" :value="notes">
                    <input type="hidden" name="cart_payload" :value="cartPayload">
                    <input type="hidden" name="payments_payload" :value="paymentsPayload">
                    <button x-ref="checkoutButton" type="submit" class="btn btn-primary w-100" :disabled="!canCheckout || pendingAction !== ''" @click.prevent="submitAction('checkout', $event)">
                        <i class="bi bi-cash-coin me-1"></i>Checkout Sale
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <div class="pos-mobile-cartbar" x-show="isCompactViewport && (cart.length > 0 || activeMobilePane === 'cart')" x-cloak>
        <div class="pos-mobile-cartbar__summary">
            <span class="pos-mobile-cartbar__eyebrow" x-text="`${computed.itemCount} item${computed.itemCount === 1 ? '' : 's'} in basket`"></span>
            <strong x-text="currency(computed.totals.grand_total)"></strong>
            <small x-text="currency(computed.payment.remaining_due) + ' remaining'"></small>
        </div>
        <div class="pos-mobile-cartbar__actions" x-show="activeMobilePane === 'catalog'" x-cloak>
            <button type="button" class="btn btn-outline-secondary" @click="switchMobilePane('cart')">Open Basket</button>
            <button type="button" class="btn btn-primary" @click="switchMobilePane('cart', true)" :disabled="cart.length === 0">Checkout</button>
        </div>
        <div class="pos-mobile-cartbar__actions pos-mobile-cartbar__actions--checkout" x-show="activeMobilePane === 'cart'" x-cloak>
            <button type="button" class="btn btn-outline-secondary" @click="switchMobilePane('catalog')">Catalog</button>
            <button type="button" class="btn btn-outline-secondary" @click.prevent="triggerMobileAction('hold')" :disabled="cart.length === 0 || pendingAction !== ''">Hold</button>
            <button type="button" class="btn btn-primary" @click.prevent="triggerMobileAction('checkout')" :disabled="!canCheckout || pendingAction !== ''">Checkout</button>
        </div>
    </div>

    <?php if (is_array($receiptModal) && !empty($receiptModal['href'])): ?>
        <a
            href="<?= e((string) $receiptModal['href']) ?>"
            class="d-none"
            id="posReceiptModalTrigger"
            data-modal
            data-auto-open-modal="true"
            data-title="<?= e((string) ($receiptModal['title'] ?? 'Receipt')) ?>"
            data-modal-size="<?= e((string) ($receiptModal['size'] ?? 'lg')) ?>"
        >
            Open receipt modal
        </a>
    <?php endif; ?>
</div>
