(function () {
    'use strict';

    function posTerminal(config) {
        const numberFormatter = new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        const dateFormatter = new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
        });
        const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
        const recentStorageKey = 'novapos-recent-products';
        const normalizePaymentMethod = (method = 'cash') => String(method || 'cash');
        const paymentRow = (method = 'cash', isAuto = null, amount = 0, details = {}) => {
            const normalizedMethod = String(method || 'cash');
            const resolvedAuto = isAuto === null
                ? normalizedMethod !== 'cheque'
                : Boolean(isAuto);

            return {
                method: normalizedMethod,
                amount: Number(amount || 0),
                reference: String(details.reference || ''),
                notes: String(details.notes || ''),
                cheque_number: String(details.cheque_number || ''),
                cheque_bank: String(details.cheque_bank || ''),
                cheque_date: String(details.cheque_date || ''),
                is_auto: resolvedAuto,
            };
        };
        const normalizePayment = (payment = {}, fallbackMethod = 'cash') => paymentRow(
            normalizePaymentMethod(payment.method || fallbackMethod),
            payment.is_auto ?? false,
            payment.amount ?? 0,
            payment
        );

        return {
            catalog: Array.isArray(config.catalog) ? [...config.catalog] : [],
            catalogMeta: (config.catalogMeta && typeof config.catalogMeta === 'object') ? { ...config.catalogMeta } : {},
            catalogFilteredTotal: Number(config.catalogFilteredTotal || (Array.isArray(config.catalog) ? config.catalog.length : 0)),
            catalogRequest: null,
            catalogLookupTimer: null,
            customers: Array.isArray(config.customers) ? [...config.customers] : [],
            heldSales: Array.isArray(config.heldSales) ? [...config.heldSales] : [],
            heldSaleSearch: '',
            search: '',
            quickMode: 'all',
            brandFilter: '',
            categoryId: '',
            stockFilter: 'all',
            catalogPage: 1,
            catalogPageSize: 8,
            catalogSort: 'relevance',
            customerSearch: '',
            customerSearchRequest: null,
            customerLookupTimer: null,
            customerRenderLimit: 150,
            selectedCustomerId: String(config.recall?.customer_id || ''),
            orderDiscountType: config.recall?.order_discount_type || 'fixed',
            orderDiscountValue: Number(config.recall?.order_discount_value || 0),
            redeemPoints: Number(config.recall?.redeem_points || 0),
            notes: config.recall?.notes || '',
            heldSaleId: String(config.recall?.held_sale_id || ''),
            quickPaymentMethod: String(config.recall?.payments?.[0]?.method || 'cash'),
            cart: Array.isArray(config.recall?.cart)
                ? config.recall.cart.map((line) => ({
                    product_id: String(line.product_id || ''),
                    quantity: Number(line.quantity || 1),
                    discount_type: String(line.discount_type || 'fixed'),
                    discount_value: Number(line.discount_value || 0),
                }))
                : [],
            payments: Array.isArray(config.recall?.payments) && config.recall.payments.length > 0
                ? config.recall.payments.map((payment) => normalizePayment(payment))
                : [paymentRow('cash')],
            recentProductIds: [],
            isCompactViewport: false,
            activeMobilePane: 'catalog',
            compactViewportQuery: null,
            pendingAction: '',
            pendingReceiptModal: null,
            lastCompletedSale: null,
            activeQuantityEditProductId: null,
            quantityDrafts: {},
            activePaymentAmountEditIndex: null,
            paymentAmountDrafts: {},
            computed: {
                lines: [],
                lineCount: 0,
                itemCount: 0,
                totals: {
                    subtotal: 0,
                    item_discount_total: 0,
                    order_discount_total: 0,
                    loyalty_discount_total: 0,
                    tax_total: 0,
                    grand_total: 0,
                    net_before_loyalty: 0,
                    net_after_discounts: 0,
                },
                payment: {
                    collected_amount: 0,
                    cash_tendered: 0,
                    cash_applied: 0,
                    non_cash_collected: 0,
                    non_cash_overpayment: 0,
                    credit_amount: 0,
                    required_collected: 0,
                    remaining_due: 0,
                    change_due: 0,
                }
            },
            init() {
                this.loadRecentProducts();
                this.recalculate();
                this.ensurePaymentRow();
                this.quickPaymentMethod = String(this.payments[0]?.method || this.quickPaymentMethod || 'cash');
                this.initCompactViewport();
                this.bindAjaxForms();
                this.bootstrapCustomers();
                this.bootstrapCatalog();
            },
            get selectedCustomer() {
                return this.customers.find((customer) => customer.id === this.selectedCustomerId) || null;
            },
            get hasStockIssues() {
                return this.computed.lines.some((line) => Boolean(line.stock_issue));
            },
            get categoryOptions() {
                return Array.isArray(this.catalogMeta.category_options) ? this.catalogMeta.category_options : [];
            },
            get brandOptions() {
                return Array.isArray(this.catalogMeta.brand_options) ? this.catalogMeta.brand_options : [];
            },
            get catalogQuickFilters() {
                const recentCount = this.recentProductIds.length;

                return [
                    { id: 'all', label: 'All', count: Number(this.catalogMeta.total_count || 0) },
                    { id: 'recent', label: 'Recent', count: recentCount },
                    { id: 'in_stock', label: 'Available', count: Number(this.catalogMeta.available_count || 0) },
                    { id: 'low_stock', label: 'Low stock', count: Number(this.catalogMeta.low_stock_count || 0) },
                ];
            },
            get hasCatalogFilters() {
                return this.search.trim() !== ''
                    || this.quickMode !== 'all'
                    || this.brandFilter !== ''
                    || this.categoryId !== ''
                    || this.stockFilter !== 'all';
            },
            get catalogStats() {
                return {
                    filtered: this.totalCatalogMatches,
                    total: Number(this.catalogMeta.total_count || 0),
                };
            },
            get catalogPageTotal() {
                if (this.totalCatalogMatches <= 0) {
                    return 0;
                }

                return Math.max(1, Math.ceil(this.totalCatalogMatches / this.catalogPageSize));
            },
            get catalogPager() {
                const pages = this.catalogPageTotal;
                if (pages === 0) {
                    return {
                        page: 0,
                        pages: 0,
                        start: 0,
                        end: 0,
                        canPrevious: false,
                        canNext: false,
                    };
                }

                const page = Math.min(Math.max(this.catalogPage, 1), pages);
                const start = ((page - 1) * this.catalogPageSize) + 1;
                const end = Math.min(start + this.catalogPageSize - 1, this.totalCatalogMatches);

                return {
                    page,
                    pages,
                    start,
                    end,
                    canPrevious: page > 1,
                    canNext: page < pages,
                };
            },
            get catalogPagerLabel() {
                const page = Number(this.catalogPager.page || 0);
                const pages = Number(this.catalogPager.pages || 0);
                const start = Number(this.catalogPager.start || 0);
                const end = Number(this.catalogPager.end || 0);
                const filtered = Number(this.catalogStats.filtered || 0);

                if (pages === 0) {
                    return 'No matching products';
                }

                if (pages <= 1) {
                    return `${start}-${end} of ${filtered}`;
                }

                return `${start}-${end} of ${filtered} | Page ${page}/${pages}`;
            },
            get customerMatches() {
                const query = this.normalize(this.customerSearch);

                return [...this.customers]
                    .map((customer) => ({ customer, rank: this.rankCustomer(customer, query) }))
                    .filter((entry) => entry.rank > Number.NEGATIVE_INFINITY)
                    .sort((left, right) => {
                        if (right.rank !== left.rank) {
                            return right.rank - left.rank;
                        }

                        return left.customer.full_name.localeCompare(right.customer.full_name);
                    })
                    .map((entry) => entry.customer);
            },
            get customerSelectOptions() {
                const selected = this.selectedCustomer;
                const matches = selected
                    ? [selected, ...this.customerMatches.filter((customer) => customer.id !== selected.id)]
                    : this.customerMatches;

                return matches.slice(0, this.customerRenderLimit);
            },
            get customerSelectSummary() {
                const total = this.customerMatches.length;
                const visible = this.customerSelectOptions.length;
                const query = this.normalize(this.customerSearch);

                if (query !== '') {
                    if (total === 0) {
                        return 'No customers match this filter.';
                    }

                    return total > visible
                        ? `Showing ${visible} of ${total} matching customers in the dropdown.`
                        : `${total} matching customer${total === 1 ? '' : 's'} in the dropdown.`;
                }

                if (total > visible) {
                    return `Showing ${visible} of ${total} customers. Filter the dropdown to narrow the list.`;
                }

                return `${total} customer${total === 1 ? '' : 's'} available in the dropdown.`;
            },
            get catalogSummary() {
                const total = Number(this.catalogStats.total || this.catalog.length || 0);
                const filtered = Number(this.catalogStats.filtered || 0);
                const pieces = [];

                if (this.search.trim() !== '') {
                    pieces.push(`search: ${this.search.trim()}`);
                }

                if (this.quickMode !== 'all') {
                    const labels = {
                        recent: 'recent picks',
                        in_stock: 'available now',
                        low_stock: 'low stock only',
                    };
                    pieces.push(labels[this.quickMode] || this.quickMode);
                }

                if (this.brandFilter !== '') {
                    const brand = this.brandOptions.find((option) => option.key === this.brandFilter);
                    if (brand) {
                        pieces.push(`brand: ${brand.label}`);
                    }
                }

                if (this.categoryId !== '') {
                    const category = this.categoryOptions.find((option) => option.id === this.categoryId);
                    if (category) {
                        pieces.push(`category: ${category.name}`);
                    }
                }

                if (this.stockFilter !== 'all') {
                    const labels = {
                        available: 'available stock',
                        low_stock: 'low stock',
                        out_of_stock: 'out of stock',
                        open: 'stock not tracked',
                    };
                    pieces.push(labels[this.stockFilter] || this.stockFilter);
                }

                const base = filtered === total
                    ? `${filtered} product${filtered === 1 ? '' : 's'} available`
                    : `${filtered} of ${total} products`;

                return pieces.length > 0 ? `${base} | ${pieces.join(' | ')}` : base;
            },
            get catalogMatches() {
                return this.catalog;
            },
            get heldSaleMatches() {
                const query = this.normalize(this.heldSaleSearch);
                if (query === '') {
                    return this.heldSales;
                }

                return this.heldSales.filter((sale) => {
                    const haystack = this.normalize([
                        sale.sale_number,
                        sale.customer_name,
                        sale.created_label,
                    ].join(' '));

                    return haystack.includes(query);
                });
            },
            get totalCatalogMatches() {
                return Number(this.catalogFilteredTotal || 0);
            },
            get pagedCatalog() {
                return this.catalog;
            },
            get resultsSummary() {
                const total = this.totalCatalogMatches;
                const query = this.normalize(this.search);

                if (total === 0) {
                    return 'No catalog matches';
                }

                if (query !== '') {
                    return `${total} ranked match${total === 1 ? '' : 'es'}`;
                }
                if (this.quickMode === 'recent') {
                    return `${total} recent product${total === 1 ? '' : 's'}`;
                }
                if (this.categoryId !== '') {
                    const category = this.categoryOptions.find((option) => option.id === this.categoryId);
                    if (!category) {
                        return `${total} product${total === 1 ? '' : 's'}`;
                    }

                    return `${total} in ${category.name}`;
                }

                return `${total} product${total === 1 ? '' : 's'} ready`;
            },
            get exactCatalogMatch() {
                const query = this.normalize(this.search);
                if (query === '') {
                    return null;
                }

                return this.catalog.find((product) => {
                    const barcode = this.normalize(product.barcode);
                    const sku = this.normalize(product.sku);
                    const name = this.normalize(product.name);

                    return (barcode !== '' && barcode === query)
                        || (sku !== '' && sku === query)
                        || name === query;
                }) || null;
            },
            get cartPayload() {
                return JSON.stringify(this.cart.map((line) => ({
                    product_id: Number(line.product_id || 0),
                    quantity: this.safeNumber(line.quantity),
                    discount_type: String(line.discount_type || 'fixed'),
                    discount_value: this.safeNumber(line.discount_value),
                })));
            },
            get paymentsPayload() {
                return JSON.stringify(this.payments
                    .map((payment) => ({
                        method: String(payment.method || 'cash'),
                        amount: this.safeNumber(payment.amount),
                        reference: String(payment.reference || ''),
                        notes: String(payment.notes || ''),
                        cheque_number: String(payment.cheque_number || ''),
                        cheque_bank: String(payment.cheque_bank || ''),
                        cheque_date: String(payment.cheque_date || ''),
                    }))
                    .filter((payment) => payment.amount > 0));
            },
            get paymentValidationIssue() {
                if (this.cart.length === 0) {
                    return '';
                }

                if (this.payments.length > 1 && this.payments.some((payment) => this.safeNumber(payment.amount) <= 0.009)) {
                    return 'Remove empty split rows or enter an amount for every payment line.';
                }

                const incompleteCheque = this.payments.find((payment) => {
                    if (String(payment.method || 'cash') !== 'cheque' || this.safeNumber(payment.amount) <= 0.009) {
                        return false;
                    }

                    return String(payment.cheque_number || '').trim() === ''
                        || String(payment.cheque_bank || '').trim() === ''
                        || String(payment.cheque_date || '').trim() === '';
                });
                if (incompleteCheque) {
                    return 'Complete the cheque number, bank, and cheque date before checkout.';
                }

                return '';
            },
            get canCheckout() {
                return this.cart.length > 0
                    && !this.hasStockIssues
                    && Number(this.computed.totals.grand_total || 0) > 0
                    && this.paymentValidationIssue === ''
                    && Number(this.computed.payment.non_cash_overpayment || 0) <= 0.009
                    && Number(this.computed.payment.remaining_due || 0) <= 0.009;
            },
            initCompactViewport() {
                if (typeof window.matchMedia !== 'function') {
                    return;
                }

                const mediaQuery = window.matchMedia('(max-width: 991.98px)');
                const syncViewport = () => {
                    this.isCompactViewport = mediaQuery.matches;
                    if (!this.isCompactViewport) {
                        this.activeMobilePane = 'catalog';
                    }
                };

                this.compactViewportQuery = mediaQuery;
                if (typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', syncViewport);
                } else if (typeof mediaQuery.addListener === 'function') {
                    mediaQuery.addListener(syncViewport);
                }

                syncViewport();
            },
            bindAjaxForms() {
                [this.$refs.holdForm, this.$refs.checkoutForm].forEach((form) => {
                    if (!(form instanceof HTMLFormElement) || form.dataset.posAjaxBound === 'true') {
                        return;
                    }

                    form.dataset.posAjaxBound = 'true';
                    form.addEventListener('ajax:success', (event) => {
                        this.handleAjaxSuccess(event.detail?.payload || {});
                    });
                });
            },
            async bootstrapCatalog() {
                await this.fetchCatalog({ page: 1, keepPage: false });
            },
            scheduleCatalogLookup() {
                window.clearTimeout(this.catalogLookupTimer);
                this.catalogLookupTimer = window.setTimeout(() => {
                    this.fetchCatalog({ page: 1, keepPage: false });
                }, this.catalogLookupDelay());
            },
            catalogLookupDelay() {
                const query = String(this.search || '').trim();
                if (query === '') {
                    return 90;
                }

                const barcodeLike = /^[0-9A-Za-z\-]{6,}$/.test(query) && !/\s/.test(query);
                return barcodeLike ? 45 : 120;
            },
            async fetchCatalog({ page = null, keepPage = true } = {}) {
                const baseUrl = String(config.catalogUrl || '').trim();
                if (baseUrl === '') {
                    return;
                }

                if (this.catalogRequest && typeof this.catalogRequest.abort === 'function') {
                    this.catalogRequest.abort();
                }

                const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                this.catalogRequest = controller;

                try {
                    const url = new URL(baseUrl, window.location.origin);
                    const requestedPage = page !== null ? Number(page || 1) : (keepPage ? this.catalogPage : 1);
                    url.searchParams.set('page', String(Math.max(1, requestedPage)));
                    url.searchParams.set('page_size', String(this.catalogPageSize));
                    url.searchParams.set('q', this.search.trim());
                    url.searchParams.set('brand', this.brandFilter);
                    url.searchParams.set('category_id', this.categoryId);
                    url.searchParams.set('stock_filter', this.stockFilter);
                    url.searchParams.set('quick_mode', this.quickMode);
                    url.searchParams.set('sort', this.catalogSort);
                    if (this.recentProductIds.length > 0) {
                        url.searchParams.set('recent_ids', this.recentProductIds.join(','));
                    }

                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controller?.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Catalog lookup failed');
                    }

                    const payload = await response.json();
                    this.catalog = Array.isArray(payload.items) ? payload.items : [];
                    this.catalogFilteredTotal = Number(payload.filtered_total || 0);
                    this.catalogPage = Math.max(1, Number(payload.page || requestedPage || 1));
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        console.error('Catalog lookup failed', error);
                    }
                } finally {
                    if (this.catalogRequest === controller) {
                        this.catalogRequest = null;
                    }
                }
            },
            async bootstrapCustomers() {
                if (this.selectedCustomerId !== '') {
                    await this.fetchCustomers({ customerId: this.selectedCustomerId, limit: 1, replace: false });
                }

                await this.fetchCustomers({ query: '', limit: 12, replace: false });
            },
            scheduleCustomerLookup() {
                window.clearTimeout(this.customerLookupTimer);
                this.customerLookupTimer = window.setTimeout(() => {
                    this.fetchCustomers({
                        query: this.customerSearch,
                        limit: this.customerSearch.trim() === '' ? 12 : 20,
                        replace: false,
                    });
                }, 140);
            },
            async fetchCustomers({ query = '', limit = 20, customerId = '', replace = false } = {}) {
                const baseUrl = String(config.customersUrl || '').trim();
                if (baseUrl === '') {
                    return;
                }

                if (this.customerSearchRequest && typeof this.customerSearchRequest.abort === 'function') {
                    this.customerSearchRequest.abort();
                }

                const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                this.customerSearchRequest = controller;

                try {
                    const url = new URL(baseUrl, window.location.origin);
                    if (String(query || '').trim() !== '') {
                        url.searchParams.set('q', String(query).trim());
                    }
                    if (String(customerId || '').trim() !== '') {
                        url.searchParams.set('id', String(customerId).trim());
                    }
                    url.searchParams.set('limit', String(limit || 20));

                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controller?.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Customer lookup failed');
                    }

                    const rows = await response.json();
                    if (!Array.isArray(rows)) {
                        return;
                    }

                    this.mergeCustomers(rows, replace);
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        console.error('Customer lookup failed', error);
                    }
                } finally {
                    if (this.customerSearchRequest === controller) {
                        this.customerSearchRequest = null;
                    }
                }
            },
            mergeCustomers(rows, replace = false) {
                const incoming = rows
                    .filter((row) => row && String(row.id || '').trim() !== '')
                    .map((row) => ({ ...row, id: String(row.id) }));

                if (replace) {
                    this.customers = incoming;
                    return;
                }

                const merged = new Map(this.customers.map((customer) => [String(customer.id), customer]));
                incoming.forEach((customer) => {
                    merged.set(String(customer.id), customer);
                });
                this.customers = Array.from(merged.values());
            },
            handleAjaxSuccess(payload = {}) {
                const action = String(payload.pos_action || '');
                const receiptModal = payload.receiptModal?.href ? payload.receiptModal : null;
                this.pendingAction = '';

                if (action === 'hold') {
                    if (payload.held_sale) {
                        this.upsertHeldSale(payload.held_sale);
                    }
                    this.resetCurrentSale();
                } else if (action === 'checkout') {
                    this.rememberCompletedSale(payload, receiptModal);
                    this.applyCompletedSaleStock();
                    if (payload.held_sale_id) {
                        this.removeHeldSale(payload.held_sale_id);
                    }
                    this.resetCurrentSale();
                }

                if (receiptModal) {
                    this.queueReceiptModal(receiptModal);
                }
            },
            rememberCompletedSale(payload = {}, receiptModal = null) {
                this.lastCompletedSale = {
                    saleNumber: String(payload.sale_number || payload.invoice_number || payload.reference || 'Sale completed'),
                    total: this.roundCurrencyAmount(this.computed.totals.grand_total),
                    collected: this.roundCurrencyAmount(this.computed.payment.collected_amount),
                    changeDue: this.roundCurrencyAmount(this.computed.payment.change_due),
                    customerName: this.selectedCustomer ? this.selectedCustomer.full_name : 'Walk-in customer',
                    receiptModal: receiptModal ? {
                        href: String(receiptModal.href || ''),
                        title: String(receiptModal.title || 'Receipt'),
                        size: String(receiptModal.size || 'lg'),
                    } : null,
                };
            },
            dismissCompletedSale() {
                this.lastCompletedSale = null;
            },
            reopenLastReceipt() {
                if (!this.lastCompletedSale?.receiptModal?.href) {
                    return;
                }

                this.openReceiptModal(this.lastCompletedSale.receiptModal);
            },
            queueReceiptModal(receiptModal) {
                this.pendingReceiptModal = {
                    href: String(receiptModal.href || ''),
                    title: String(receiptModal.title || 'Receipt'),
                    size: String(receiptModal.size || 'lg'),
                };

                window.setTimeout(() => {
                    const queuedModal = this.pendingReceiptModal;
                    this.pendingReceiptModal = null;
                    if (!queuedModal || queuedModal.href === '') {
                        return;
                    }

                    this.openReceiptModal(queuedModal);
                }, 180);
            },
            resetCurrentSale() {
                this.cart = [];
                this.notes = '';
                this.selectedCustomerId = '';
                this.customerSearch = '';
                this.orderDiscountType = 'fixed';
                this.orderDiscountValue = 0;
                this.redeemPoints = 0;
                this.heldSaleId = '';
                this.quickPaymentMethod = 'cash';
                this.payments = [paymentRow('cash')];
                this.recalculate();

                if (this.isCompactViewport) {
                    this.switchMobilePane('catalog');
                }

                window.requestAnimationFrame(() => this.$refs.productSearch?.focus());
            },
            upsertHeldSale(sale) {
                const normalized = {
                    id: String(sale.id || ''),
                    sale_number: String(sale.sale_number || ''),
                    grand_total: Number(sale.grand_total || 0),
                    customer_name: String(sale.customer_name || 'Walk-in customer'),
                    created_at: String(sale.created_at || ''),
                    created_label: String(sale.created_label || 'Queued'),
                };
                const existingIndex = this.heldSales.findIndex((entry) => String(entry.id) === normalized.id);

                if (existingIndex >= 0) {
                    this.heldSales.splice(existingIndex, 1, normalized);
                } else {
                    this.heldSales.unshift(normalized);
                }

                this.heldSales.sort((left, right) => String(right.created_at || '').localeCompare(String(left.created_at || '')));
            },
            removeHeldSale(saleId) {
                const saleKey = String(saleId || '');
                if (saleKey === '') {
                    return;
                }

                this.heldSales = this.heldSales.filter((sale) => String(sale.id) !== saleKey);
            },
            applyCompletedSaleStock() {
                this.computed.lines.forEach((line) => {
                    if (Number(line?.product?.track_stock || 0) !== 1) {
                        return;
                    }

                    const product = this.catalog.find((entry) => String(entry.id) === String(line.product.id));
                    if (!product) {
                        return;
                    }

                    const currentStock = Number(product.stock_quantity || 0);
                    const nextStock = Math.max(0, currentStock - Number(line.quantity || 0));
                    product.stock_quantity = nextStock;
                    product.is_low_stock = nextStock <= Math.max(Number(product.low_stock_threshold || 0), 0);
                });
            },
            openReceiptModal(receiptModal) {
                if (window.NovaUI?.openRemoteModal) {
                    window.setTimeout(() => {
                        window.NovaUI.openRemoteModal(String(receiptModal.href || ''), {
                            title: String(receiptModal.title || 'Receipt'),
                            size: String(receiptModal.size || 'lg'),
                            skipAppLoader: true,
                        });
                    }, 0);
                    return;
                }

                window.setTimeout(() => {
                    window.location.href = String(receiptModal.href || window.location.href);
                }, 0);
            },
            async settleConfirmationOverlay() {
                if (!window.Swal) {
                    return;
                }

                try {
                    window.Swal.close();
                } catch (error) {
                    // Ignore SweetAlert cleanup failures.
                }

                await new Promise((resolve) => window.setTimeout(resolve, 120));
            },
            switchMobilePane(pane, focusCheckout = false) {
                const nextPane = String(pane || 'catalog') === 'cart' ? 'cart' : 'catalog';
                this.activeMobilePane = nextPane;
                if (!this.isCompactViewport) {
                    return;
                }

                window.requestAnimationFrame(() => {
                    if (nextPane === 'catalog') {
                        this.scrollCompactPaneIntoView(this.$refs.catalogPanel);
                        return;
                    }

                    const target = focusCheckout ? this.$refs.checkoutActions : this.$refs.cartPanel;
                    this.scrollCompactPaneIntoView(target);
                });
            },
            compactScrollOffset() {
                const rootStyles = window.getComputedStyle(document.documentElement);
                const rawOffset = rootStyles.getPropertyValue('--mobile-topbar-offset') || '0';
                const topbarOffset = Number.parseFloat(rawOffset);

                if (Number.isFinite(topbarOffset) && topbarOffset > 0) {
                    return topbarOffset + 8;
                }

                return 88;
            },
            scrollCompactPaneIntoView(target) {
                if (!this.isCompactViewport || !(target instanceof HTMLElement)) {
                    return;
                }

                const absoluteTop = target.getBoundingClientRect().top + window.scrollY;
                const destination = Math.max(absoluteTop - this.compactScrollOffset(), 0);
                window.scrollTo({ top: destination, behavior: 'smooth' });
            },
            resetCatalogPage() {
                this.catalogPage = 1;
                this.scheduleCatalogLookup();
            },
            setQuickMode(mode) {
                this.quickMode = String(mode || 'all');
                this.resetCatalogPage();
            },
            clearCatalogFilters() {
                this.search = '';
                this.quickMode = 'all';
                this.brandFilter = '';
                this.categoryId = '';
                this.stockFilter = 'all';
                this.catalogSort = 'relevance';
                this.catalogPage = 1;
                this.scheduleCatalogLookup();
                window.requestAnimationFrame(() => this.$refs.productSearch?.focus());
            },
            setCatalogPageSize(value) {
                const nextSize = Number(value || this.catalogPageSize);
                this.catalogPageSize = [8, 12, 20, 40].includes(nextSize) ? nextSize : 8;
                this.catalogPage = 1;
                this.fetchCatalog({ page: 1, keepPage: false });
            },
            previousCatalogPage() {
                if (!this.catalogPager.canPrevious) {
                    return;
                }

                this.fetchCatalog({ page: Math.max(1, this.catalogPager.page - 1), keepPage: false });
            },
            nextCatalogPage() {
                if (!this.catalogPager.canNext) {
                    return;
                }

                this.fetchCatalog({ page: Math.min(this.catalogPager.pages, this.catalogPager.page + 1), keepPage: false });
            },
            normalize(value) {
                return String(value || '').trim().toLowerCase();
            },
            safeNumber(value) {
                const parsed = Number(value || 0);
                return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0;
            },
            safeInteger(value) {
                return Math.max(0, Math.floor(Number(value || 0)));
            },
            formatEditableNumber(value, fallback = '1') {
                const parsed = Number(value);
                if (!Number.isFinite(parsed) || parsed <= 0) {
                    return fallback;
                }

                return Number.isInteger(parsed) ? String(parsed) : String(parsed);
            },
            quantityInputValue(productId, quantity) {
                const productKey = String(productId || '');
                if (this.activeQuantityEditProductId === productKey
                    && Object.prototype.hasOwnProperty.call(this.quantityDrafts, productKey)) {
                    return this.quantityDrafts[productKey];
                }

                return this.formatEditableNumber(quantity);
            },
            paymentAmountInputValue(index) {
                const paymentIndex = Number(index);
                if (this.activePaymentAmountEditIndex === paymentIndex
                    && Object.prototype.hasOwnProperty.call(this.paymentAmountDrafts, paymentIndex)) {
                    return this.paymentAmountDrafts[paymentIndex];
                }

                return this.formatEditableNumber(this.payments[paymentIndex]?.amount, '0');
            },
            escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            },
            currency(amount) {
                return `${config.currencyLabel} ${numberFormatter.format(Number(amount || 0))}`;
            },
            displayPrice(product) {
                return this.applyCustomerPricing(Number(product.price || 0));
            },
            hasSpecialPricing(product) {
                return Number(product.price || 0) !== this.displayPrice(product);
            },
            pricingLabel(customer) {
                if (!customer) {
                    return 'Standard pricing';
                }

                const type = String(customer.special_pricing_type || 'none');
                const value = Number(customer.special_pricing_value || 0);
                if (type === 'percentage' && value > 0) {
                    return `${numberFormatter.format(value)}% off`;
                }
                if (type === 'fixed' && value > 0) {
                    return `${this.currency(value)} off`;
                }

                return 'Standard pricing';
            },
            pricingSupportText(customer) {
                if (!customer) {
                    return 'Attach a customer to unlock loyalty redemption, open account credit, and customer-specific pricing.';
                }

                const pricing = this.pricingLabel(customer);
                if (pricing === 'Standard pricing') {
                    return 'This buyer is using standard register pricing. Loyalty redemption is still available on eligible baskets.';
                }

                return `${pricing} is already reflected in the item prices shown in this basket.`;
            },
            customerInitials(customer) {
                const tokens = String(customer?.full_name || 'Walk-in')
                    .trim()
                    .split(/\s+/)
                    .filter(Boolean);

                if (tokens.length === 0) {
                    return 'WI';
                }

                return tokens.slice(0, 2).map((token) => token.charAt(0).toUpperCase()).join('');
            },
            customerProfileSummary(customer) {
                const totalOrders = Math.max(0, Number(customer?.total_orders || 0));
                const totalSpent = Math.max(0, Number(customer?.total_spent || 0));

                if (totalOrders > 0 && totalSpent > 0) {
                    return `${totalOrders} order${totalOrders === 1 ? '' : 's'} | ${this.currency(totalSpent)} lifetime spend`;
                }

                if (totalOrders > 0) {
                    return `${totalOrders} completed order${totalOrders === 1 ? '' : 's'}`;
                }

                if (totalSpent > 0) {
                    return `${this.currency(totalSpent)} lifetime spend`;
                }

                return 'New customer profile';
            },
            customerCompactMeta(customer) {
                const details = [];
                const group = String(customer?.customer_group_name || '').trim();
                const phone = String(customer?.phone || '').trim();
                const email = String(customer?.email || '').trim();
                const totalOrders = Math.max(0, Number(customer?.total_orders || 0));

                if (group !== '') {
                    details.push(group);
                }

                if (phone !== '') {
                    details.push(phone);
                } else if (email !== '') {
                    details.push(email);
                }

                if (details.length === 0 && totalOrders > 0) {
                    details.push(`${totalOrders} order${totalOrders === 1 ? '' : 's'}`);
                }

                return details.length > 0 ? details.slice(0, 2).join(' | ') : 'Customer profile active';
            },
            customerLastPurchaseLabel(customer) {
                const rawValue = String(customer?.last_purchase_at || '').trim();
                if (rawValue === '') {
                    return 'No recorded purchase yet';
                }

                const parsed = new Date(rawValue.replace(' ', 'T'));
                if (Number.isNaN(parsed.getTime())) {
                    return rawValue;
                }

                return dateTimeFormatter.format(parsed);
            },
            paymentMethodLabel(method) {
                const lookup = {
                    cash: 'Cash collection',
                    card: 'Card payment',
                    mobile_money: 'Mobile money',
                    cheque: 'Cheque payment',
                    split: 'Split payment',
                    credit: 'Open account charge',
                };

                const normalizedMethod = String(method || 'cash');
                return lookup[normalizedMethod] || normalizedMethod.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
            },
            paymentAmountPlaceholder(method) {
                return String(method || 'cash') === 'cash' ? 'Cash Received'
                    : String(method || 'cash') === 'cheque' ? 'Cheque Amount'
                    : 'Amount';
            },
            paymentReferencePlaceholder(method) {
                const lookup = {
                    cash: 'Reference (optional)',
                    card: 'Reference / approval code',
                    mobile_money: 'Reference / transaction ID',
                    cheque: 'Bank reference (optional)',
                    credit: 'Credit note (optional)',
                };

                return lookup[String(method || 'cash')] || 'Reference';
            },
            calendarDateLabel(value) {
                const rawValue = String(value || '').trim();
                if (rawValue === '') {
                    return '';
                }

                const parsed = new Date(`${rawValue}T00:00:00`);
                if (Number.isNaN(parsed.getTime())) {
                    return rawValue;
                }

                return dateFormatter.format(parsed);
            },
            cashPaymentBreakdown(index) {
                const payment = this.payments[index];
                if (!payment || payment.method !== 'cash') {
                    return {
                        tendered: 0,
                        due_before_cash: 0,
                        remaining_due: 0,
                        change_due: 0,
                    };
                }

                const otherCredit = this.payments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === index || entry.method !== 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);
                const otherCollected = this.payments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === index || entry.method === 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);
                const dueBeforeCash = Math.max(0, Math.max(0, this.computed.totals.grand_total - otherCredit) - otherCollected);
                const tendered = this.safeNumber(payment.amount);

                return {
                    tendered,
                    due_before_cash: dueBeforeCash,
                    remaining_due: Math.max(0, dueBeforeCash - tendered),
                    change_due: Math.max(0, tendered - dueBeforeCash),
                };
            },
            cashPaymentSummary(index) {
                const breakdown = this.cashPaymentBreakdown(index);
                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return 'Non-cash lines already exceed the sale balance.';
                }

                if (breakdown.tendered <= 0.009 && breakdown.due_before_cash > 0.009) {
                    return `${this.currency(breakdown.due_before_cash)} still needs to be received in cash.`;
                }

                if (breakdown.change_due > 0.009) {
                    return `${this.currency(breakdown.tendered)} given - ${this.currency(breakdown.due_before_cash)} due = ${this.currency(breakdown.change_due)} change.`;
                }

                if (breakdown.remaining_due > 0.009) {
                    return `${this.currency(breakdown.tendered)} given leaves ${this.currency(breakdown.remaining_due)} still due.`;
                }

                if (breakdown.tendered > 0.009) {
                    return `${this.currency(breakdown.tendered)} received exactly covers the cash balance.`;
                }

                return 'Enter the amount received from the customer.';
            },
            cashPaymentSupportText(index) {
                const breakdown = this.cashPaymentBreakdown(index);
                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return 'Reduce cheque, card, or mobile money entries before collecting more cash.';
                }

                if (breakdown.change_due > 0.009) {
                    return 'Return the highlighted change to the customer after checkout.';
                }

                if (breakdown.remaining_due > 0.009) {
                    return 'Collect the remaining balance or split it to another payment method.';
                }

                if (breakdown.tendered > 0.009) {
                    return 'The cash received matches the remaining sale balance.';
                }

                return 'Use the amount the customer handed over, not just the sale total.';
            },
            roundCurrencyAmount(amount) {
                return Number(this.safeNumber(amount).toFixed(2));
            },
            roundUpCashAmount(amount, step) {
                const safeStep = this.safeNumber(step);
                const safeAmount = this.safeNumber(amount);
                if (safeStep <= 0) {
                    return this.roundCurrencyAmount(safeAmount);
                }

                return this.roundCurrencyAmount(Math.ceil(safeAmount / safeStep) * safeStep);
            },
            nextCashNoteAmount(amount) {
                const due = this.safeNumber(amount);
                const notes = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000];
                const match = notes.find((note) => note >= due - 0.0001);
                return this.roundCurrencyAmount(match ?? this.roundUpCashAmount(due, 100));
            },
            cashTenderSuggestions(index) {
                const payment = this.payments[index];
                if (!payment || String(payment.method || 'cash') !== 'cash') {
                    return [];
                }

                const breakdown = this.cashPaymentBreakdown(index);
                if (breakdown.due_before_cash <= 0.009) {
                    return [];
                }

                const due = this.roundCurrencyAmount(breakdown.due_before_cash);
                const suggestions = [
                    { label: 'Exact Due', amount: due, tone: 'primary' },
                    { label: 'Round 1', amount: this.roundUpCashAmount(due, 1), tone: 'light' },
                    { label: 'Round 5', amount: this.roundUpCashAmount(due, 5), tone: 'light' },
                    { label: 'Next Note', amount: this.nextCashNoteAmount(due), tone: 'light' },
                ];

                const unique = [];
                const seen = new Set();
                suggestions.forEach((suggestion) => {
                    const amountKey = this.roundCurrencyAmount(suggestion.amount).toFixed(2);
                    if (this.safeNumber(suggestion.amount) <= 0.009 || seen.has(amountKey)) {
                        return;
                    }

                    seen.add(amountKey);
                    unique.push({
                        ...suggestion,
                        amount: this.roundCurrencyAmount(suggestion.amount),
                    });
                });

                return unique.slice(0, 4);
            },
            applyCashTenderSuggestion(index, amount) {
                const payment = this.payments[index];
                if (!payment || String(payment.method || 'cash') !== 'cash') {
                    return;
                }

                const paymentIndex = Number(index);
                payment.is_auto = false;
                payment.amount = this.roundCurrencyAmount(amount);
                delete this.paymentAmountDrafts[paymentIndex];
                if (this.activePaymentAmountEditIndex === paymentIndex) {
                    this.activePaymentAmountEditIndex = null;
                }
                this.recalculate();
                this.focusPaymentAmountInput(paymentIndex, true);
            },
            chequePaymentSummary(index) {
                const payment = this.payments[index];
                if (!payment || payment.method !== 'cheque') {
                    return 'Cheque payment details pending.';
                }

                const missing = [];
                if (String(payment.cheque_number || '').trim() === '') {
                    missing.push('cheque number');
                }
                if (String(payment.cheque_bank || '').trim() === '') {
                    missing.push('bank');
                }
                if (String(payment.cheque_date || '').trim() === '') {
                    missing.push('cheque date');
                }

                if (missing.length > 0) {
                    return `Capture the ${missing.join(', ')} before checkout.`;
                }

                return `${this.currency(this.safeNumber(payment.amount))} by cheque ${String(payment.cheque_number || '').trim()} from ${String(payment.cheque_bank || '').trim()}.`;
            },
            chequePaymentSupportText(index) {
                const payment = this.payments[index];
                if (!payment || payment.method !== 'cheque') {
                    return 'Record the cheque details before checkout.';
                }

                if (String(payment.cheque_number || '').trim() === ''
                    || String(payment.cheque_bank || '').trim() === ''
                    || String(payment.cheque_date || '').trim() === '') {
                    return 'Record the cheque number, bank, and cheque date before finishing the sale.';
                }

                const detailParts = [`Dated ${this.calendarDateLabel(payment.cheque_date) || String(payment.cheque_date || '').trim()}`];
                if (String(payment.reference || '').trim() !== ''
                    && String(payment.reference || '').trim() !== String(payment.cheque_number || '').trim()) {
                    detailParts.push(`Ref ${String(payment.reference || '').trim()}`);
                }

                return detailParts.join(' | ');
            },
            paymentStatusSummary() {
                if (this.cart.length === 0) {
                    return 'Awaiting basket';
                }

                if (this.paymentValidationIssue !== '') {
                    return 'Finish payment details';
                }

                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return `${this.currency(this.computed.payment.non_cash_overpayment)} too much on non-cash`;
                }

                if (this.computed.payment.change_due > 0.009) {
                    return `${this.currency(this.computed.payment.change_due)} to return`;
                }

                if (this.computed.payment.remaining_due > 0.009) {
                    return `${this.currency(this.computed.payment.remaining_due)} still unpaid`;
                }

                if (this.computed.payment.credit_amount > 0.009) {
                    return `${this.currency(this.computed.payment.credit_amount)} on account`;
                }

                return 'Payment covered';
            },
            paymentStatusShort() {
                if (this.cart.length === 0) {
                    return 'Awaiting basket';
                }

                if (this.paymentValidationIssue !== '') {
                    return 'Fix details';
                }

                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return 'Adjust non-cash';
                }

                if (this.computed.payment.change_due > 0.009) {
                    return 'Change to return';
                }

                if (this.computed.payment.remaining_due > 0.009) {
                    return 'Still due';
                }

                if (this.computed.payment.credit_amount > 0.009) {
                    return 'Includes account balance';
                }

                return 'Payment covered';
            },
            holdButtonLabel() {
                return this.pendingAction === 'hold' ? 'Holding Sale...' : 'Hold Sale';
            },
            checkoutButtonLabel() {
                return this.pendingAction === 'checkout' ? 'Processing Checkout...' : 'Checkout Sale';
            },
            actionShortcutHints() {
                return [
                    { key: '/', label: 'Product search' },
                    { key: 'F7', label: 'Payment amount' },
                    { key: 'F8', label: 'Hold sale' },
                    { key: 'F9', label: 'Checkout sale' },
                ];
            },
            checkoutCalloutTitle() {
                if (this.cart.length === 0) {
                    return 'Basket is empty';
                }

                if (this.hasStockIssues) {
                    return 'Stock attention needed';
                }

                if (this.paymentValidationIssue !== '') {
                    return 'Payment details missing';
                }

                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return 'Payment mix needs adjustment';
                }

                if (this.computed.payment.remaining_due > 0.009) {
                    return 'Payment is incomplete';
                }

                if (this.computed.payment.change_due > 0.009) {
                    return 'Ready with change to return';
                }

                if (this.computed.payment.credit_amount > 0.009) {
                    return 'Ready to post to account';
                }

                return 'Ready to complete this sale';
            },
            checkoutCalloutText() {
                if (this.cart.length === 0) {
                    return 'Scan or add a product to unlock hold and checkout.';
                }

                if (this.hasStockIssues) {
                    return 'At least one line exceeds stock on hand. Adjust the quantity before checkout.';
                }

                if (this.paymentValidationIssue !== '') {
                    return this.paymentValidationIssue;
                }

                if (this.computed.payment.non_cash_overpayment > 0.009) {
                    return `${this.currency(this.computed.payment.non_cash_overpayment)} is over the balance on cheque, card, or transfer lines. Reduce those entries or move the extra amount to cash.`;
                }

                if (this.computed.payment.remaining_due > 0.009) {
                    return this.selectedCustomer
                        ? `${this.currency(this.computed.payment.remaining_due)} is still outstanding. Collect more or assign the balance to open account.`
                        : `${this.currency(this.computed.payment.remaining_due)} is still outstanding. Collect more before checkout or attach a customer to use open account.`;
                }

                if (this.computed.payment.change_due > 0.009) {
                    return `${this.currency(this.computed.payment.change_due)} should be returned to the customer after the sale.`;
                }

                if (this.computed.payment.credit_amount > 0.009) {
                    return `${this.currency(this.computed.payment.credit_amount)} will be posted to the customer account after checkout.`;
                }

                return `${this.currency(this.computed.totals.grand_total)} is fully covered and ready for checkout.`;
            },
            buildActionConfirmation(kind) {
                const action = String(kind || '');
                const buyerLabel = this.selectedCustomer ? this.selectedCustomer.full_name : 'Walk-in customer';
                const lineCount = Math.max(0, Number(this.computed.lineCount || 0));
                const itemCount = Math.max(0, Number(this.computed.itemCount || 0));
                const grandTotal = Math.max(0, Number(this.computed.totals.grand_total || 0));
                const collectedAmount = Math.max(0, Number(this.computed.payment.collected_amount || 0));
                const cashTendered = Math.max(0, Number(this.computed.payment.cash_tendered || 0));
                const creditAmount = Math.max(0, Number(this.computed.payment.credit_amount || 0));
                const remainingDue = Math.max(0, Number(this.computed.payment.remaining_due || 0));
                const changeDue = Math.max(0, Number(this.computed.payment.change_due || 0));
                const subtotal = Math.max(0, Number(this.computed.totals.subtotal || 0));
                const manualDiscountTotal = Math.max(0, Number(this.computed.totals.item_discount_total || 0))
                    + Math.max(0, Number(this.computed.totals.order_discount_total || 0));
                const manualDiscountRate = subtotal > 0 ? (manualDiscountTotal / subtotal) : 0;
                const lineLabel = `${lineCount} line${lineCount === 1 ? '' : 's'} / ${itemCount} item${itemCount === 1 ? '' : 's'}`;
                const summaryRows = [
                    { label: 'Buyer', value: buyerLabel },
                    { label: 'Basket', value: lineLabel },
                    { label: 'Total', value: this.currency(grandTotal) },
                ];

                if (action === 'hold') {
                    const notes = [];
                    if (collectedAmount > 0.009 || creditAmount > 0.009) {
                        notes.push('Current payment allocations will be saved with this held sale.');
                    }
                    if (String(this.notes || '').trim() !== '') {
                        notes.push('Cashier notes will stay attached to this held sale.');
                    }
                    if (String(this.heldSaleId || '').trim() !== '') {
                        notes.push('This will update the existing held basket with the latest changes.');
                    }

                    const html = `
                        <div class="text-start">
                            <p class="mb-3">${this.escapeHtml(String(this.heldSaleId || '').trim() !== '' ? 'Update this held sale with the current basket details?' : 'Save this basket now and return to it later?')}</p>
                            <ul class="ps-3 mb-0">
                                ${summaryRows.map((row) => `<li><strong>${this.escapeHtml(row.label)}:</strong> ${this.escapeHtml(row.value)}</li>`).join('')}
                            </ul>
                            ${notes.length > 0 ? `<div class="mt-3"><strong>Heads up:</strong><ul class="ps-3 mb-0 mt-2">${notes.map((note) => `<li>${this.escapeHtml(note)}</li>`).join('')}</ul></div>` : ''}
                        </div>
                    `;
                    const plainText = [
                        String(this.heldSaleId || '').trim() !== '' ? 'Update this held sale with the current basket details?' : 'Save this basket now and return to it later?',
                        ...summaryRows.map((row) => `${row.label}: ${row.value}`),
                        ...(notes.length > 0 ? ['', 'Heads up:', ...notes.map((note) => `- ${note}`)] : []),
                    ].join('\n');

                    return {
                        title: String(this.heldSaleId || '').trim() !== '' ? 'Update held sale?' : 'Hold this sale?',
                        icon: 'question',
                        confirmButtonText: String(this.heldSaleId || '').trim() !== '' ? 'Update Hold' : 'Hold Sale',
                        cancelButtonText: 'Continue editing',
                        html,
                        plainText,
                    };
                }

                if (action !== 'checkout') {
                    return null;
                }

                const reviewPoints = [];
                if (creditAmount > 0.009) {
                    reviewPoints.push(`${this.currency(creditAmount)} will be posted to ${buyerLabel}'s open account.`);
                    summaryRows.push({ label: 'On account', value: this.currency(creditAmount) });
                }
                if (collectedAmount > 0.009) {
                    summaryRows.push({ label: 'Received', value: this.currency(collectedAmount) });
                }
                if (cashTendered > 0.009) {
                    summaryRows.push({ label: 'Cash received', value: this.currency(cashTendered) });
                }
                if (changeDue > 0.009) {
                    reviewPoints.push(`${this.currency(changeDue)} will be returned as change.`);
                    summaryRows.push({ label: 'Change to return', value: this.currency(changeDue) });
                }
                if (manualDiscountTotal > 0.009 && (manualDiscountTotal >= 50 || manualDiscountRate >= 0.10)) {
                    reviewPoints.push(`${this.currency(manualDiscountTotal)} in manual discounts has been applied to this sale.`);
                    summaryRows.push({ label: 'Manual discounts', value: this.currency(manualDiscountTotal) });
                }
                if (!this.selectedCustomer && manualDiscountTotal > 0.009) {
                    reviewPoints.push('This discounted sale is being completed without a buyer profile attached.');
                }
                if (String(this.heldSaleId || '').trim() !== '') {
                    reviewPoints.push('This held sale will be completed and cleared from the hold queue.');
                }
                if (remainingDue > 0.009) {
                    reviewPoints.push(`${this.currency(remainingDue)} is still outstanding.`);
                }

                if (reviewPoints.length === 0) {
                    reviewPoints.push('Totals and payment entries are ready for final checkout.');
                }

                const html = `
                    <div class="text-start">
                        <p class="mb-3">Review this sale before final checkout.</p>
                        <ul class="ps-3 mb-0">
                            ${summaryRows.map((row) => `<li><strong>${this.escapeHtml(row.label)}:</strong> ${this.escapeHtml(row.value)}</li>`).join('')}
                        </ul>
                        <div class="mt-3">
                            <strong>Check these details:</strong>
                            <ul class="ps-3 mb-0 mt-2">
                                ${reviewPoints.map((point) => `<li>${this.escapeHtml(point)}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
                const plainText = [
                    'Review this sale before final checkout.',
                    ...summaryRows.map((row) => `${row.label}: ${row.value}`),
                    '',
                    'Check these details:',
                    ...reviewPoints.map((point) => `- ${point}`),
                ].join('\n');

                return {
                    title: 'Review before checkout',
                    icon: 'info',
                    confirmButtonText: 'Checkout Sale',
                    cancelButtonText: 'Continue editing',
                    html,
                    plainText,
                };
            },
            async confirmAction(kind) {
                const prompt = this.buildActionConfirmation(kind);
                if (!prompt) {
                    return true;
                }

                if (window.NovaUI?.showConfirm) {
                    try {
                        const result = await window.NovaUI.showConfirm({
                            title: prompt.title,
                            html: prompt.html,
                            icon: prompt.icon,
                            confirmButtonText: prompt.confirmButtonText,
                            cancelButtonText: prompt.cancelButtonText,
                        });

                        return Boolean(result?.isConfirmed);
                    } catch (error) {
                        console.error('POS confirmation dialog failed, falling back to browser confirm.', error);
                    }
                }

                return window.confirm([prompt.title, prompt.plainText].filter(Boolean).join('\n\n'));
            },
            applyCustomerPricing(basePrice) {
                const customer = this.selectedCustomer;
                if (!customer) {
                    return Number(basePrice || 0);
                }

                const type = String(customer.special_pricing_type || 'none');
                const value = Number(customer.special_pricing_value || 0);
                if (type === 'percentage') {
                    return Math.max(0, Number(basePrice || 0) - (Number(basePrice || 0) * (value / 100)));
                }
                if (type === 'fixed') {
                    return Math.max(0, Number(basePrice || 0) - value);
                }

                return Number(basePrice || 0);
            },
            rankProduct(product, query) {
                if (query === '') {
                    return 1;
                }

                const searchBlob = this.normalize(product.search_blob);
                if (!searchBlob.includes(query)) {
                    const tokens = query.split(/\s+/).filter(Boolean);
                    if (tokens.length === 0 || !tokens.every((token) => searchBlob.includes(token))) {
                        return Number.NEGATIVE_INFINITY;
                    }
                }

                const name = this.normalize(product.name);
                const brand = this.normalize(product.brand);
                const sku = this.normalize(product.sku);
                const barcode = this.normalize(product.barcode);
                const category = this.normalize(product.category_name);
                let score = 10;

                if (barcode !== '' && barcode === query) {
                    score += 1200;
                } else if (sku !== '' && sku === query) {
                    score += 1100;
                } else if (name === query) {
                    score += 1000;
                } else if (brand !== '' && brand === query) {
                    score += 940;
                } else if (name.startsWith(query)) {
                    score += 900;
                } else if (brand !== '' && brand.startsWith(query)) {
                    score += 780;
                } else if (sku.startsWith(query) || barcode.startsWith(query)) {
                    score += 820;
                } else if (name.includes(query)) {
                    score += 720;
                } else if (brand !== '' && brand.includes(query)) {
                    score += 640;
                } else if (category.includes(query)) {
                    score += 520;
                }

                if (product.is_low_stock) {
                    score += 5;
                }

                return score;
            },
            compareCatalogProducts(left, right, query) {
                const activeSort = query !== '' ? 'relevance' : this.catalogSort;

                if (activeSort === 'name') {
                    return left.name.localeCompare(right.name);
                }

                if (activeSort === 'price') {
                    const priceDelta = this.displayPrice(left) - this.displayPrice(right);
                    if (priceDelta !== 0) {
                        return priceDelta;
                    }

                    return left.name.localeCompare(right.name);
                }

                if (activeSort === 'stock') {
                    const stockDelta = this.stockSortValue(left) - this.stockSortValue(right);
                    if (stockDelta !== 0) {
                        return stockDelta;
                    }

                    return left.name.localeCompare(right.name);
                }

                if (query === '' && this.quickMode !== 'recent' && this.recentProductIds.length > 0) {
                    const leftIndex = this.recentProductIds.indexOf(left.id);
                    const rightIndex = this.recentProductIds.indexOf(right.id);

                    if (leftIndex !== rightIndex) {
                        if (leftIndex === -1) {
                            return 1;
                        }
                        if (rightIndex === -1) {
                            return -1;
                        }

                        return leftIndex - rightIndex;
                    }
                }

                return left.name.localeCompare(right.name);
            },
            rankCustomer(customer, query) {
                if (query === '') {
                    return Math.min(50, Number(customer.total_orders || 0)) + 1;
                }

                const searchBlob = this.normalize(customer.search_blob);
                if (!searchBlob.includes(query)) {
                    const tokens = query.split(/\s+/).filter(Boolean);
                    if (tokens.length === 0 || !tokens.every((token) => searchBlob.includes(token))) {
                        return Number.NEGATIVE_INFINITY;
                    }
                }

                const name = this.normalize(customer.full_name);
                const phone = this.normalize(customer.phone);
                const email = this.normalize(customer.email);
                let score = 10;

                if (phone !== '' && phone === query) {
                    score += 1200;
                } else if (email !== '' && email === query) {
                    score += 1100;
                } else if (name === query) {
                    score += 1000;
                } else if (name.startsWith(query)) {
                    score += 880;
                } else if (phone.startsWith(query)) {
                    score += 760;
                } else if (email.startsWith(query)) {
                    score += 720;
                } else if (name.includes(query)) {
                    score += 620;
                }

                return score + Math.min(80, Number(customer.total_orders || 0));
            },
            customerOptionLabel(customer) {
                const detail = customer.phone || customer.email || customer.customer_group_name || 'No contact info';
                return `${customer.full_name} | ${detail}`;
            },
            productById(productId) {
                return this.catalog.find((product) => product.id === String(productId)) || null;
            },
            brandKeyForProduct(product) {
                return this.normalize(product?.brand) === '' ? '__unbranded__' : String(product?.brand || '');
            },
            catalogStockState(product) {
                if (Number(product?.track_stock || 0) === 0) {
                    return 'open';
                }

                if (Number(product?.stock_quantity || 0) <= 0) {
                    return 'out_of_stock';
                }

                if (Boolean(product?.is_low_stock)) {
                    return 'low_stock';
                }

                return 'in_stock';
            },
            stockSortValue(product) {
                if (product.track_stock === 0) {
                    return Number.MAX_SAFE_INTEGER;
                }

                return Number(product.stock_quantity || 0);
            },
            stockTone(product) {
                const stockState = this.catalogStockState(product);
                if (stockState === 'open') {
                    return 'is-neutral';
                }
                if (stockState === 'out_of_stock') {
                    return 'is-danger';
                }
                if (stockState === 'low_stock') {
                    return 'is-warning';
                }

                return 'is-success';
            },
            stockLabel(product) {
                if (product.track_stock === 0) {
                    return 'Stock open';
                }

                return `${numberFormatter.format(Number(product.stock_quantity || 0))} ${product.unit}`;
            },
            addFirstVisibleProduct() {
                if (this.pagedCatalog.length > 0) {
                    this.addProduct(this.pagedCatalog[0].id);
                }
            },
            addExactOrFirstVisibleProduct() {
                const matchedProduct = this.exactCatalogMatch;
                if (matchedProduct) {
                    this.addProduct(matchedProduct.id);
                    this.search = '';
                    this.resetCatalogPage();
                    window.requestAnimationFrame(() => this.$refs.productSearch?.focus());
                    return;
                }

                this.addFirstVisibleProduct();
            },
            addProduct(productId) {
                const product = this.productById(productId);
                if (!product) {
                    return;
                }

                const existing = this.cart.find((line) => String(line.product_id) === String(productId));
                if (existing) {
                    existing.quantity = this.safeNumber(existing.quantity) + 1;
                } else {
                    this.cart.unshift({
                        product_id: String(productId),
                        quantity: 1,
                        discount_type: 'fixed',
                        discount_value: 0,
                    });
                }

                this.saveRecentProduct(productId);
                this.recalculate();
                if (this.isCompactViewport && this.activeMobilePane === 'catalog' && this.cart.length === 1) {
                    this.switchMobilePane('cart');
                }
            },
            beginQuantityEdit(productId, event) {
                const productKey = String(productId || '');
                this.activeQuantityEditProductId = productKey;
                this.quantityDrafts[productKey] = event?.target instanceof HTMLInputElement
                    ? String(event.target.value ?? '')
                    : this.formatEditableNumber(this.cart.find((entry) => String(entry.product_id) === productKey)?.quantity, '1');
            },
            handleQuantityInput(productId, rawValue) {
                const line = this.cart.find((entry) => String(entry.product_id) === String(productId));
                if (!line) {
                    return;
                }

                const candidate = String(rawValue ?? '').trim();
                this.quantityDrafts[String(productId)] = candidate;
                if (candidate === '') {
                    return;
                }

                const parsed = Number(candidate);
                if (!Number.isFinite(parsed) || parsed <= 0) {
                    return;
                }

                line.quantity = parsed;
                this.recalculate();
            },
            commitQuantityInput(productId, event) {
                const line = this.cart.find((entry) => String(entry.product_id) === String(productId));
                const input = event?.target;
                if (!line || !(input instanceof HTMLInputElement)) {
                    return;
                }

                const productKey = String(productId || '');
                const candidate = String(
                    Object.prototype.hasOwnProperty.call(this.quantityDrafts, productKey)
                        ? this.quantityDrafts[productKey]
                        : input.value ?? ''
                ).trim();
                const parsed = Number(candidate);
                if (!Number.isFinite(parsed) || parsed <= 0) {
                    line.quantity = Math.max(1, this.safeNumber(line.quantity || 1));
                } else {
                    line.quantity = parsed;
                }

                delete this.quantityDrafts[productKey];
                if (this.activeQuantityEditProductId === productKey) {
                    this.activeQuantityEditProductId = null;
                }
                this.recalculate();
                input.value = this.formatEditableNumber(line.quantity);
            },
            cancelQuantityEdit(productId, event) {
                const line = this.cart.find((entry) => String(entry.product_id) === String(productId));
                const input = event?.target;
                const productKey = String(productId || '');
                if (!line || !(input instanceof HTMLInputElement)) {
                    return;
                }

                delete this.quantityDrafts[productKey];
                if (this.activeQuantityEditProductId === productKey) {
                    this.activeQuantityEditProductId = null;
                }

                input.value = this.formatEditableNumber(line.quantity);
            },
            changeQuantity(productId, delta) {
                const line = this.cart.find((entry) => String(entry.product_id) === String(productId));
                if (!line) {
                    return;
                }

                const productKey = String(productId || '');
                delete this.quantityDrafts[productKey];
                if (this.activeQuantityEditProductId === productKey) {
                    this.activeQuantityEditProductId = null;
                }
                line.quantity = Math.max(1, this.safeNumber(line.quantity) + Number(delta || 0));
                this.recalculate();
            },
            removeProduct(productId) {
                this.cart = this.cart.filter((line) => String(line.product_id) !== String(productId));
                this.recalculate();
                if (this.isCompactViewport && this.cart.length === 0) {
                    this.switchMobilePane('catalog');
                }
            },
            async clearCart() {
                if (this.cart.length === 0) {
                    return;
                }

                const result = await window.NovaUI.showConfirm({
                    icon: 'warning',
                    title: 'Clear this basket?',
                    text: 'This removes all lines, discounts, payments, and notes from the current sale.',
                    confirmButtonText: 'Clear Basket',
                });

                if (!result.isConfirmed) {
                    return;
                }

                this.resetCurrentSale();
            },
            selectCustomer(customerId) {
                this.selectedCustomerId = String(customerId || '');
                this.customerSearch = '';
                if (this.selectedCustomerId !== '') {
                    this.fetchCustomers({ customerId: this.selectedCustomerId, limit: 1, replace: false });
                }
                if (!this.selectedCustomer) {
                    this.redeemPoints = 0;
                    if (this.quickPaymentMethod === 'credit') {
                        this.quickPaymentMethod = 'cash';
                    }
                    this.payments = this.payments.map((payment) => payment.method === 'credit'
                        ? { ...payment, method: 'cash' }
                        : payment
                    );
                } else {
                    this.syncRedeemPoints();
                }

                this.recalculate();
            },
            ensurePaymentRow() {
                if (this.payments.length === 0) {
                    this.payments.push(paymentRow('cash'));
                }
            },
            focusPaymentAmountInput(index, selectText = true) {
                window.requestAnimationFrame(() => {
                    const root = this.$root instanceof HTMLElement ? this.$root : document;
                    const input = root.querySelector(`[data-payment-amount-index="${String(index)}"]`);
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    input.focus();
                    if (selectText && typeof input.select === 'function') {
                        input.select();
                    }
                });
            },
            handlePaymentAmountInput(index) {
                const payment = this.payments[index];
                if (!payment) {
                    return;
                }

                const draft = String(this.paymentAmountDrafts[Number(index)] ?? '').trim();
                payment.is_auto = false;
                if (draft === '') {
                    payment.amount = 0;
                    this.recalculate();
                    return;
                }

                const parsed = Number(draft);
                if (Number.isFinite(parsed) && parsed >= 0) {
                    payment.amount = parsed;
                }
                this.recalculate();
            },
            beginPaymentAmountEdit(index, event) {
                const paymentIndex = Number(index);
                this.activePaymentAmountEditIndex = paymentIndex;
                this.paymentAmountDrafts[paymentIndex] = event?.target instanceof HTMLInputElement
                    ? String(event.target.value ?? '')
                    : this.formatEditableNumber(this.payments[paymentIndex]?.amount, '0');
            },
            endPaymentAmountEdit(index, event) {
                const paymentIndex = Number(index);
                const payment = this.payments[paymentIndex];
                const input = event?.target;
                if (!payment || !(input instanceof HTMLInputElement)) {
                    return;
                }

                const candidate = String(
                    Object.prototype.hasOwnProperty.call(this.paymentAmountDrafts, paymentIndex)
                        ? this.paymentAmountDrafts[paymentIndex]
                        : input.value ?? ''
                ).trim();
                const parsed = Number(candidate);
                if (candidate === '') {
                    payment.amount = 0;
                } else if (Number.isFinite(parsed) && parsed >= 0) {
                    payment.amount = parsed;
                } else {
                    payment.amount = this.safeNumber(payment.amount);
                }

                delete this.paymentAmountDrafts[paymentIndex];
                if (this.activePaymentAmountEditIndex === paymentIndex) {
                    this.activePaymentAmountEditIndex = null;
                }

                this.recalculate();
                input.value = this.paymentAmountInputValue(paymentIndex);
            },
            handlePaymentDetailInput(index) {
                const payment = this.payments[index];
                if (!payment) {
                    return;
                }

                if (String(payment.method || 'cash') === 'cheque'
                    && String(payment.reference || '').trim() === ''
                    && String(payment.cheque_number || '').trim() !== '') {
                    payment.reference = String(payment.cheque_number || '').trim();
                }

                this.recalculate();
            },
            addPayment() {
                this.payments = this.payments.map((payment) => ({
                    ...payment,
                    is_auto: false,
                }));
                this.payments.push(paymentRow('cash'));
                this.recalculate();
                this.focusPaymentAmountInput(this.payments.length - 1);
            },
            removePayment(index) {
                if (this.payments.length === 1) {
                    this.payments = [paymentRow('cash')];
                } else {
                    this.payments.splice(index, 1);
                }

                if (this.payments.length === 1 && this.safeNumber(this.payments[0]?.amount) === 0) {
                    this.payments[0].is_auto = false;
                }

                this.quickPaymentMethod = String(this.payments[0]?.method || 'cash');
                this.recalculate();
            },
            handlePaymentMethodChange(index) {
                if (!this.selectedCustomer && this.payments[index]?.method === 'credit') {
                    this.payments[index].method = 'cash';
                    window.showToast('Attach a customer before using open account credit.', 'warning');
                }

                if (['cash', 'cheque'].includes(String(this.payments[index]?.method || 'cash'))) {
                    this.payments[index].is_auto = false;
                }

                if (String(this.payments[index]?.method || 'cash') !== 'cheque') {
                    this.payments[index].cheque_number = '';
                    this.payments[index].cheque_bank = '';
                    this.payments[index].cheque_date = '';
                }

                if (index === 0) {
                    this.quickPaymentMethod = String(this.payments[index]?.method || 'cash');
                }

                this.recalculate();
                this.focusPaymentAmountInput(index);
            },
            applyQuickPaymentMethod() {
                this.setSinglePayment(this.quickPaymentMethod);
            },
            setSinglePayment(method) {
                if (method === 'credit' && !this.selectedCustomer) {
                    window.showToast('Attach a customer before assigning the sale to credit.', 'warning');
                    return;
                }

                this.quickPaymentMethod = method;
                this.payments = [paymentRow(method)];
                this.recalculate();
                this.focusPaymentAmountInput(0);
            },
            fillRemaining(index) {
                const payment = this.payments[index];
                if (!payment) {
                    return;
                }

                if (payment.method === 'credit' && !this.selectedCustomer) {
                    window.showToast('Attach a customer before assigning any amount to credit.', 'warning');
                    payment.method = 'cash';
                }

                payment.is_auto = true;

                const otherCollected = this.payments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === index || entry.method === 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);
                const otherCredit = this.payments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === index || entry.method !== 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);
                const totals = this.computed.totals;
                if (payment.method === 'credit') {
                    payment.amount = Math.max(0, totals.grand_total - otherCollected - otherCredit);
                } else {
                    const requiredCollected = Math.max(0, totals.grand_total - otherCredit);
                    payment.amount = Math.max(0, requiredCollected - otherCollected);
                }

                this.recalculate();
            },
            saveRecentProduct(productId) {
                this.recentProductIds = [
                    String(productId),
                    ...this.recentProductIds.filter((id) => id !== String(productId)),
                ].slice(0, 10);

                try {
                    window.localStorage.setItem(recentStorageKey, JSON.stringify(this.recentProductIds));
                } catch (error) {
                    // Ignore storage failures in privacy mode.
                }

                if (this.quickMode === 'recent') {
                    this.fetchCatalog({ page: 1, keepPage: false });
                }
            },
            loadRecentProducts() {
                try {
                    const parsed = JSON.parse(window.localStorage.getItem(recentStorageKey) || '[]');
                    this.recentProductIds = Array.isArray(parsed) ? parsed.map((id) => String(id)) : [];
                } catch (error) {
                    this.recentProductIds = [];
                }
            },
            availableRedeemPoints() {
                return Math.max(0, Number(this.selectedCustomer?.loyalty_balance || 0));
            },
            maxRedeemablePoints() {
                const maxFromValue = Math.floor(Math.max(0, Number(this.computed.totals.net_before_loyalty || 0)) / 0.10 + 0.0001);
                return Math.max(0, Math.min(this.availableRedeemPoints(), maxFromValue));
            },
            syncRedeemPoints() {
                this.redeemPoints = Math.min(this.safeInteger(this.redeemPoints), this.maxRedeemablePoints());
                this.recalculate();
            },
            syncAutoPayments() {
                this.ensurePaymentRow();

                const normalizedPayments = this.payments.map((payment) => ({
                    ...normalizePayment(payment, payment.method || 'cash'),
                    amount: this.safeNumber(payment.amount || 0),
                    is_auto: Boolean(payment.is_auto),
                }));
                let changed = false;

                if (!this.selectedCustomer) {
                    normalizedPayments.forEach((payment) => {
                        if (payment.method === 'credit') {
                            payment.method = 'cash';
                            changed = true;
                        }
                    });
                }

                if (this.cart.length === 0) {
                    normalizedPayments.forEach((payment) => {
                        if (this.safeNumber(payment.amount) !== 0) {
                            payment.amount = 0;
                            changed = true;
                        }
                        if (payment.is_auto) {
                            payment.is_auto = false;
                            changed = true;
                        }
                    });

                    this.payments = normalizedPayments;
                    this.quickPaymentMethod = String(normalizedPayments[0]?.method || this.quickPaymentMethod || 'cash');
                    return changed;
                }

                if (normalizedPayments.length === 1) {
                    const singlePayment = normalizedPayments[0];
                    const hasReferenceData = String(singlePayment.reference || '').trim() !== ''
                        || String(singlePayment.notes || '').trim() !== ''
                        || String(singlePayment.cheque_number || '').trim() !== ''
                        || String(singlePayment.cheque_bank || '').trim() !== ''
                        || String(singlePayment.cheque_date || '').trim() !== '';
                    const shouldRestoreAuto = !singlePayment.is_auto
                        && this.activePaymentAmountEditIndex === null
                        && this.safeNumber(singlePayment.amount) <= 0.009
                        && !hasReferenceData;

                    if (shouldRestoreAuto) {
                        singlePayment.is_auto = String(singlePayment.method || 'cash') !== 'cheque';
                        changed = true;
                    }
                }

                const autoIndexes = normalizedPayments.reduce((indexes, payment, index) => {
                    if (payment.is_auto) {
                        indexes.push(index);
                    }
                    return indexes;
                }, []);

                if (autoIndexes.length === 0) {
                    this.payments = normalizedPayments;
                    this.quickPaymentMethod = String(normalizedPayments[0]?.method || this.quickPaymentMethod || 'cash');
                    return changed;
                }

                const activeAutoIndex = autoIndexes[autoIndexes.length - 1];
                autoIndexes.slice(0, -1).forEach((index) => {
                    if (this.safeNumber(normalizedPayments[index].amount) !== 0) {
                        normalizedPayments[index].amount = 0;
                        changed = true;
                    }
                });

                const targetPayment = normalizedPayments[activeAutoIndex];
                const otherCollected = normalizedPayments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === activeAutoIndex || entry.method === 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);
                const otherCredit = normalizedPayments.reduce((carry, entry, paymentIndex) => {
                    if (paymentIndex === activeAutoIndex || entry.method !== 'credit') {
                        return carry;
                    }

                    return carry + this.safeNumber(entry.amount);
                }, 0);

                const nextAmount = targetPayment.method === 'credit'
                    ? Math.max(0, this.computed.totals.grand_total - otherCollected - otherCredit)
                    : Math.max(0, Math.max(0, this.computed.totals.grand_total - otherCredit) - otherCollected);
                const roundedNextAmount = Number(nextAmount.toFixed(2));
                if (Math.abs(this.safeNumber(targetPayment.amount) - roundedNextAmount) > 0.0001) {
                    targetPayment.amount = roundedNextAmount;
                    changed = true;
                }

                this.payments = normalizedPayments;
                this.quickPaymentMethod = String(normalizedPayments[0]?.method || this.quickPaymentMethod || 'cash');
                return changed;
            },
            recalculate(syncAutoPayments = true) {
                const selectedCustomer = this.selectedCustomer;
                const normalizedCart = this.cart.map((line) => ({
                    ...line,
                    product_id: String(line.product_id || ''),
                    quantity: Math.max(1, this.safeNumber(line.quantity || 1)),
                    discount_type: line.discount_type === 'percent' ? 'percent' : 'fixed',
                    discount_value: this.safeNumber(line.discount_value || 0),
                })).filter((line) => line.product_id !== '');

                this.cart = normalizedCart;

                let subtotal = 0;
                let itemDiscountTotal = 0;
                let taxableBaseTotal = 0;

                const preliminaryLines = normalizedCart.map((line) => {
                    const product = this.productById(line.product_id);
                    const unitPrice = product ? this.displayPrice(product) : 0;
                    const lineSubtotal = unitPrice * line.quantity;
                    const lineDiscount = line.discount_type === 'percent'
                        ? lineSubtotal * (line.discount_value / 100)
                        : line.discount_value;
                    const boundedDiscount = Math.min(lineSubtotal, Math.max(0, lineDiscount));
                    const taxableAfterLineDiscount = Math.max(0, lineSubtotal - boundedDiscount);

                    subtotal += lineSubtotal;
                    itemDiscountTotal += boundedDiscount;
                    taxableBaseTotal += taxableAfterLineDiscount;

                    return {
                        source: line,
                        product,
                        quantity: line.quantity,
                        unit_price: unitPrice,
                        line_subtotal: lineSubtotal,
                        line_discount: boundedDiscount,
                        taxable_after_line_discount: taxableAfterLineDiscount,
                    };
                }).filter((line) => line.product !== null);

                const safeOrderDiscountValue = this.safeNumber(this.orderDiscountValue);
                const orderDiscountTotal = this.orderDiscountType === 'percent'
                    ? taxableBaseTotal * (safeOrderDiscountValue / 100)
                    : safeOrderDiscountValue;
                const boundedOrderDiscountTotal = Math.min(taxableBaseTotal, Math.max(0, orderDiscountTotal));
                const baseAfterOrderDiscount = Math.max(0, taxableBaseTotal - boundedOrderDiscountTotal);

                const requestedPoints = selectedCustomer ? this.safeInteger(this.redeemPoints) : 0;
                const maxPoints = selectedCustomer
                    ? Math.min(Number(selectedCustomer.loyalty_balance || 0), Math.floor(baseAfterOrderDiscount / 0.10 + 0.0001))
                    : 0;
                const boundedRedeemPoints = Math.max(0, Math.min(requestedPoints, maxPoints));
                if (boundedRedeemPoints !== requestedPoints) {
                    this.redeemPoints = boundedRedeemPoints;
                }

                const loyaltyDiscountTotal = Number((boundedRedeemPoints * 0.10).toFixed(2));
                const baseAfterAllDiscounts = Math.max(0, baseAfterOrderDiscount - loyaltyDiscountTotal);

                let taxTotal = 0;
                let grandTotal = 0;
                let itemCount = 0;

                const computedLines = preliminaryLines.map((line) => {
                    const orderShare = taxableBaseTotal > 0
                        ? (line.taxable_after_line_discount / taxableBaseTotal) * boundedOrderDiscountTotal
                        : 0;
                    const baseAfterManual = Math.max(0, line.taxable_after_line_discount - orderShare);
                    const loyaltyShare = baseAfterOrderDiscount > 0
                        ? (baseAfterManual / baseAfterOrderDiscount) * loyaltyDiscountTotal
                        : 0;
                    const taxableAfterAllDiscounts = Math.max(0, baseAfterManual - loyaltyShare);
                    const lineTax = taxableAfterAllDiscounts * (Number(line.product.tax_rate || 0) / 100);
                    const lineTotal = taxableAfterAllDiscounts + lineTax;
                    const stockIssue = Number(line.product.track_stock || 0) === 1 && Number(line.product.stock_quantity || 0) < Number(line.quantity || 0);

                    taxTotal += lineTax;
                    grandTotal += lineTotal;
                    itemCount += line.quantity;

                    return {
                        ...line,
                        order_discount_share: orderShare,
                        loyalty_discount_share: loyaltyShare,
                        discount_total: line.line_discount + orderShare + loyaltyShare,
                        tax_total: lineTax,
                        line_total: lineTotal,
                        stock_issue: stockIssue,
                    };
                });

                const normalizedPayments = this.payments.map((payment) => ({
                    ...normalizePayment(payment, payment.method || 'cash'),
                    amount: this.safeNumber(payment.amount || 0),
                    is_auto: Boolean(payment.is_auto),
                }));
                this.payments = normalizedPayments;

                const creditAmount = normalizedPayments.reduce((carry, payment) => {
                    return carry + (payment.method === 'credit' ? payment.amount : 0);
                }, 0);
                const cashTendered = normalizedPayments.reduce((carry, payment) => {
                    return carry + (payment.method === 'cash' ? payment.amount : 0);
                }, 0);
                const nonCashCollected = normalizedPayments.reduce((carry, payment) => {
                    return carry + (payment.method !== 'credit' && payment.method !== 'cash' ? payment.amount : 0);
                }, 0);
                const requiredCollected = Math.max(0, grandTotal - creditAmount);
                const nonCashOverpayment = Math.max(0, nonCashCollected - requiredCollected);
                const cashRequired = Math.max(0, requiredCollected - nonCashCollected);
                const remainingDue = nonCashOverpayment > 0.009 ? 0 : Math.max(0, cashRequired - cashTendered);
                const changeDue = nonCashOverpayment > 0.009 ? 0 : Math.max(0, cashTendered - cashRequired);
                const collectedAmount = nonCashCollected + cashTendered;
                const cashApplied = Math.max(0, cashTendered - changeDue);

                this.computed = {
                    lines: computedLines,
                    lineCount: computedLines.length,
                    itemCount,
                    totals: {
                        subtotal,
                        item_discount_total: itemDiscountTotal,
                        order_discount_total: boundedOrderDiscountTotal,
                        loyalty_discount_total: loyaltyDiscountTotal,
                        tax_total: taxTotal,
                        grand_total: grandTotal,
                        net_before_loyalty: baseAfterOrderDiscount,
                        net_after_discounts: baseAfterAllDiscounts,
                    },
                    payment: {
                        collected_amount: collectedAmount,
                        cash_tendered: cashTendered,
                        cash_applied: cashApplied,
                        non_cash_collected: nonCashCollected,
                        non_cash_overpayment: nonCashOverpayment,
                        credit_amount: creditAmount,
                        required_collected: requiredCollected,
                        remaining_due: remainingDue,
                        change_due: changeDue,
                    },
                };

                if (syncAutoPayments && this.syncAutoPayments()) {
                    this.recalculate(false);
                }
            },
            validate(kind) {
                this.recalculate();

                if (this.cart.length === 0) {
                    window.showToast('Add at least one product before continuing.', 'warning');
                    return false;
                }

                const stockIssue = this.computed.lines.find((line) => line.stock_issue);
                if (stockIssue) {
                    window.showToast(`Stock is too low for ${stockIssue.product.name}. Reduce the quantity before continuing.`, 'danger');
                    return false;
                }

                if (kind === 'checkout') {
                    if (this.computed.totals.grand_total <= 0) {
                        window.showToast('The sale total must be greater than zero to checkout.', 'warning');
                        return false;
                    }

                    if (this.paymentValidationIssue !== '') {
                        window.showToast(this.paymentValidationIssue, 'warning');
                        return false;
                    }

                    const hasCredit = this.payments.some((payment) => payment.method === 'credit' && this.safeNumber(payment.amount) > 0);
                    if (hasCredit && !this.selectedCustomer) {
                        window.showToast('Attach a customer before assigning any balance to credit.', 'warning');
                        return false;
                    }

                    if (this.computed.payment.non_cash_overpayment > 0.009) {
                        window.showToast('Cheque, card, and mobile money payments cannot exceed the amount due. Reduce the non-cash lines or move the extra amount to cash.', 'warning');
                        return false;
                    }

                    if (this.computed.payment.remaining_due > 0.009) {
                        window.showToast('Collected payments do not yet cover the amount due after credit allocation.', 'warning');
                        return false;
                    }
                }

                return true;
            },
            async submitAction(kind, event) {
                if (this.pendingAction !== '') {
                    return;
                }

                if (!this.validate(kind)) {
                    return;
                }

                const form = kind === 'hold' ? this.$refs.holdForm : this.$refs.checkoutForm;
                const submitter = event?.currentTarget || (kind === 'hold' ? this.$refs.holdButton : this.$refs.checkoutButton);
                if (!form) {
                    return;
                }

                this.pendingAction = String(kind || '');

                try {
                    const confirmed = await this.confirmAction(kind);
                    if (!confirmed) {
                        this.pendingAction = '';
                        return;
                    }

                    await this.settleConfirmationOverlay();

                    if (typeof form.requestSubmit === 'function') {
                        if (submitter instanceof HTMLElement && !submitter.disabled) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                    } else {
                        form.submit();
                    }

                    window.setTimeout(() => {
                        if (this.pendingAction === kind) {
                            this.pendingAction = '';
                        }
                    }, 6000);
                } catch (error) {
                    this.pendingAction = '';
                    console.error('POS action confirmation failed', error);
                    window.showToast('Unable to continue right now. Please try again.', 'danger');
                }
            },
            async triggerMobileAction(kind) {
                const submitter = kind === 'hold' ? this.$refs.holdButton : this.$refs.checkoutButton;
                if (!submitter) {
                    return;
                }

                await this.submitAction(kind, { currentTarget: submitter });
            },
            focusPrimaryPaymentAmount() {
                if (this.cart.length === 0) {
                    return;
                }

                this.ensurePaymentRow();
                this.focusPaymentAmountInput(0);
            },
            async handleHotkeys(event) {
                const target = event.target;
                const isTyping = target instanceof HTMLElement && (
                    target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.tagName === 'SELECT' ||
                    target.isContentEditable
                );

                if (event.key === '/' && !isTyping) {
                    event.preventDefault();
                    this.$refs.productSearch?.focus();
                    this.$refs.productSearch?.select();
                    return;
                }

                if (event.key === 'F7') {
                    event.preventDefault();
                    this.focusPrimaryPaymentAmount();
                    return;
                }

                if (event.key === 'F8') {
                    event.preventDefault();
                    await this.submitAction('hold', { currentTarget: this.$refs.holdButton });
                    return;
                }

                if (event.key === 'F9') {
                    event.preventDefault();
                    await this.submitAction('checkout', { currentTarget: this.$refs.checkoutButton });
                }
            },
        };
    }

    window.posTerminal = posTerminal;
})();
