(() => {
    const root = document.documentElement;
    const body = document.body;
    const brandName = body?.dataset.brandName || 'NovaPOS';
    const loaderPresets = {
        boot: {
            title: brandName,
            detail: 'Loading workspace',
            stages: ['Initializing secure session', 'Syncing workspace modules', 'Rendering the interface'],
        },
        navigate: {
            title: brandName,
            detail: 'Opening page',
            stages: ['Resolving route access', 'Loading the requested page', 'Finalizing the workspace'],
        },
        submit: {
            title: brandName,
            detail: 'Saving changes',
            stages: ['Validating form data', 'Applying changes securely', 'Refreshing the workspace'],
        },
        filter: {
            title: brandName,
            detail: 'Refreshing data',
            stages: ['Applying filters', 'Refreshing result sets', 'Rendering updated metrics'],
        },
        modal: {
            title: brandName,
            detail: 'Loading panel',
            stages: ['Loading panel content', 'Binding interactions', 'Finalizing panel view'],
        },
        ajax: {
            title: brandName,
            detail: 'Processing request',
            stages: ['Sending request payload', 'Waiting for server response', 'Updating the interface'],
        },
        pos: {
            title: brandName,
            detail: 'Processing sale',
            stages: ['Calculating totals', 'Updating inventory and payments', 'Preparing receipt details'],
        },
        export: {
            title: brandName,
            detail: 'Preparing export',
            stages: ['Collecting records', 'Formatting export data', 'Preparing download output'],
        },
        logout: {
            title: brandName,
            detail: 'Signing out',
            stages: ['Closing secure session', 'Clearing local workspace state', 'Returning to sign in'],
        },
    };

    const loaderState = {
        depth: 0,
        visible: false,
        mode: 'boot',
        activatedAt: 0,
        minVisibleMs: 360,
        stageIndex: 0,
        stageTimer: null,
        elapsedTimer: null,
        hideTimer: null,
    };

    const cssVar = (name) => getComputedStyle(root).getPropertyValue(name).trim();
    const getTheme = () => localStorage.getItem('novapos-theme') || root.getAttribute('data-theme') || 'light';
    const sidebarStorageKey = 'novapos-sidebar-state';
    const getSidebarState = () => {
        try {
            return localStorage.getItem(sidebarStorageKey) === 'collapsed' ? 'collapsed' : 'expanded';
        } catch (error) {
            return root.getAttribute('data-sidebar-state') === 'collapsed' ? 'collapsed' : 'expanded';
        }
    };
    const chartPalette = () => ({
        theme: getTheme(),
        text: cssVar('--text') || '#212529',
        muted: cssVar('--muted') || '#6c757d',
        line: cssVar('--line') || 'rgba(33, 37, 41, 0.08)',
        primary: cssVar('--primary') || '#0d6efd',
        primaryStrong: cssVar('--primary-strong') || '#6610f2',
        accent: cssVar('--accent') || '#0dcaf0',
        success: cssVar('--success') || '#198754',
        danger: cssVar('--danger') || '#dc3545',
        warning: cssVar('--bs-warning') || '#ffc107',
        secondary: cssVar('--bs-secondary') || '#6c757d',
        teal: cssVar('--bs-teal') || '#20c997',
        pink: cssVar('--bs-pink') || '#d63384',
        purple: cssVar('--bs-purple') || '#6f42c1',
        panel: cssVar('--panel-strong') || 'rgba(255,255,255,0.88)',
        primarySoft: getTheme() === 'dark' ? 'rgba(53, 198, 188, 0.22)' : 'rgba(14, 165, 164, 0.18)',
        accentSoft: getTheme() === 'dark' ? 'rgba(255, 154, 98, 0.22)' : 'rgba(6, 182, 212, 0.24)',
        warningSoft: getTheme() === 'dark' ? 'rgba(245, 158, 11, 0.16)' : 'rgba(245, 158, 11, 0.08)',
        dangerSoft: getTheme() === 'dark' ? 'rgba(255, 126, 126, 0.18)' : 'rgba(239, 68, 68, 0.08)',
        glass: getTheme() === 'dark' ? 'rgba(53, 198, 188, 0.22)' : 'rgba(14, 165, 164, 0.18)',
        glassAccent: getTheme() === 'dark' ? 'rgba(255, 154, 98, 0.22)' : 'rgba(6, 182, 212, 0.24)',
        warm: getTheme() === 'dark' ? 'rgba(244, 114, 182, 0.18)' : 'rgba(236, 72, 153, 0.10)',
    });

    const applyChartDefaults = () => {
        if (!window.Chart) {
            return;
        }

        const palette = chartPalette();
        Chart.defaults.color = palette.muted;
        Chart.defaults.borderColor = palette.line;
        Chart.defaults.font.family = cssVar('--font-sans') || "'IBM Plex Sans', 'Segoe UI', sans-serif";
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 10;
        Chart.defaults.plugins.legend.labels.padding = 18;
        Chart.defaults.scale.grid.color = palette.line;
        Chart.defaults.scale.ticks.color = palette.muted;
    };

    const setTheme = (theme) => {
        root.setAttribute('data-theme', theme);
        localStorage.setItem('novapos-theme', theme);
        applyChartDefaults();
        syncThemeButtons();
        document.dispatchEvent(new CustomEvent('novapos:themechange', { detail: { theme } }));
    };

    const syncSidebarToggleButtons = () => {
        const isCollapsed = getSidebarState() === 'collapsed';

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            const label = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
            const icon = button.querySelector('i');
            const text = button.querySelector('.sidebar-toggle__label');

            button.setAttribute('aria-pressed', String(isCollapsed));
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);

            if (icon) {
                icon.className = `bi ${isCollapsed ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset'}`;
            }

            if (text) {
                text.textContent = isCollapsed ? 'Expand' : 'Collapse';
            }
        });
    };

    const setSidebarState = (state) => {
        const nextState = state === 'collapsed' ? 'collapsed' : 'expanded';
        root.setAttribute('data-sidebar-state', nextState);

        try {
            localStorage.setItem(sidebarStorageKey, nextState);
        } catch (error) {
            // Ignore storage failures in privacy mode.
        }

        syncSidebarToggleButtons();
    };

    const syncThemeButtons = () => {
        const theme = getTheme();
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.innerHTML = theme === 'dark'
                ? '<i class="bi bi-sun-fill me-2"></i><span class="theme-toggle__label">Light Mode</span>'
                : '<i class="bi bi-moon-stars-fill me-2"></i><span class="theme-toggle__label">Dark Mode</span>';
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
    };

    const buildSwalOptions = (options = {}) => {
        const palette = chartPalette();
        const danger = options.danger === true || options.icon === 'warning' || options.icon === 'error';

        return {
            icon: options.icon || 'question',
            title: options.title || 'Proceed with this action?',
            text: options.text,
            html: options.html,
            showCancelButton: options.showCancelButton !== false,
            reverseButtons: true,
            focusCancel: true,
            confirmButtonText: options.confirmButtonText || 'Confirm',
            cancelButtonText: options.cancelButtonText || 'Cancel',
            background: palette.panel,
            color: palette.text,
            backdrop: 'rgba(16, 37, 55, 0.68)',
            buttonsStyling: false,
            customClass: {
                popup: 'nova-swal-popup',
                title: 'nova-swal-title',
                htmlContainer: 'nova-swal-body',
                confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary',
                cancelButton: 'btn btn-outline-secondary',
                actions: 'nova-swal-actions',
            },
            ...options,
        };
    };

    const showConfirm = (options = {}) => Swal.fire(buildSwalOptions(options));

    const createToastElement = (message, type) => {
        const toastEl = document.createElement('div');
        const variant = type === 'danger' ? 'danger' : (type === 'warning' ? 'warning' : 'success');
        const icon = variant === 'danger'
            ? 'bi bi-x-octagon-fill'
            : (variant === 'warning' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill');

        toastEl.className = `toast align-items-center text-bg-${variant} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('data-autohide', 'true');

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex';

        const bodyEl = document.createElement('div');
        bodyEl.className = 'toast-body d-flex align-items-center gap-2';
        bodyEl.innerHTML = `<i class="${icon}"></i>`;
        bodyEl.append(document.createTextNode(message));

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close btn-close-white me-2 m-auto';
        closeButton.setAttribute('data-bs-dismiss', 'toast');
        closeButton.setAttribute('aria-label', 'Close');

        wrapper.append(bodyEl, closeButton);
        toastEl.appendChild(wrapper);

        return toastEl;
    };

    const showToast = (message, type = 'success') => {
        try {
            const stack = document.querySelector('.toast-stack');
            if (!stack) {
                throw new Error('Toast stack missing');
            }

            const toastEl = createToastElement(message, type);
            stack.appendChild(toastEl);
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4200 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        } catch (error) {
            Swal.fire(buildSwalOptions({
                icon: type === 'danger' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                title: message,
                showCancelButton: false,
                confirmButtonText: 'Close',
            }));
        }
    };
    const printNode = (node, options = {}) => {
        if (!(node instanceof Element)) {
            return false;
        }

        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        document.body.appendChild(iframe);

        const printWindow = iframe.contentWindow;
        const printDocument = iframe.contentDocument || printWindow?.document;
        if (!printWindow || !printDocument) {
            iframe.remove();
            return false;
        }

        const headMarkup = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
            .map((element) => element.outerHTML)
            .join('');
        const title = String(options.title || document.title || brandName);
        const baseHref = document.baseURI || window.location.href;
        const pageSize = String(options.pageSize || '').trim().toLowerCase();
        const isReceipt = pageSize === '80mm' || node.classList.contains('receipt-paper');
        const pageCss = isReceipt
            ? '@page { size: 80mm auto; margin: 0; }'
            : '@page { margin: 12mm; }';
        const bodyCss = isReceipt
            ? 'html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; width: 100% !important; min-width: 0 !important; max-width: none !important; overflow: visible !important; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } .receipt-print-shell { display: flex !important; justify-content: center !important; align-items: flex-start !important; width: 100% !important; margin: 0 auto !important; padding: 0 !important; } .receipt-print-shell > * { width: 80mm !important; max-width: 80mm !important; min-width: 80mm !important; margin: 0 auto !important; } .receipt-paper { width: 80mm !important; max-width: 80mm !important; min-width: 80mm !important; border-radius: 0 !important; box-shadow: none !important; margin: 0 auto !important; padding: 5mm 4mm 6mm !important; }'
            : 'body { background: #fff; margin: 0; padding: 24px; } .receipt-print-shell { display: flex; justify-content: center; }';

        printDocument.open();
        printDocument.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<base href="${baseHref}">
<title>${title}</title>
${headMarkup}
<style>
${pageCss}
${bodyCss}
@media print {
    body { padding: 0 !important; }
    .receipt-print-shell { display: flex !important; justify-content: center !important; }
}
</style>
</head>
<body>
    <div class="receipt-print-shell">${node.outerHTML}</div>
</body>
</html>`);
        printDocument.close();

        const cleanup = () => {
            window.setTimeout(() => iframe.remove(), 120);
        };

        const applyReceiptPageSize = () => {
            if (!isReceipt) {
                return;
            }

            const receiptNode = printDocument.querySelector('.receipt-paper') || printDocument.querySelector('.receipt-print-shell');
            if (!(receiptNode instanceof Element)) {
                return;
            }

            const receiptRect = receiptNode.getBoundingClientRect();
            const receiptStyle = printWindow.getComputedStyle(receiptNode);
            const paddingBottom = parseFloat(receiptStyle.paddingBottom || '0') || 0;
            let contentBottomPx = 0;

            receiptNode.querySelectorAll('*').forEach((element) => {
                if (!(element instanceof Element)) {
                    return;
                }

                const elementStyle = printWindow.getComputedStyle(element);
                if (elementStyle.display === 'none' || elementStyle.visibility === 'hidden' || elementStyle.position === 'absolute') {
                    return;
                }

                const rect = element.getBoundingClientRect();
                contentBottomPx = Math.max(contentBottomPx, rect.bottom - receiptRect.top);
            });

            const measuredHeightPx = Math.max(
                contentBottomPx + paddingBottom,
                Math.ceil(receiptRect.height || 0),
            );
            const receiptHeightMm = Math.max(64, Math.ceil((measuredHeightPx * 25.4) / 96) + 1);
            let pageStyle = printDocument.getElementById('dynamic-print-page-size');
            if (!pageStyle) {
                pageStyle = printDocument.createElement('style');
                pageStyle.id = 'dynamic-print-page-size';
                printDocument.head.appendChild(pageStyle);
            }

            pageStyle.textContent = `@page { size: 80mm ${receiptHeightMm}mm; margin: 0; } html, body { width: 100% !important; min-width: 0 !important; max-width: none !important; height: ${receiptHeightMm}mm !important; min-height: ${receiptHeightMm}mm !important; max-height: ${receiptHeightMm}mm !important; margin: 0 auto !important; overflow: hidden !important; } .receipt-print-shell { display: flex !important; justify-content: center !important; align-items: flex-start !important; width: 100% !important; height: ${receiptHeightMm}mm !important; min-height: ${receiptHeightMm}mm !important; max-height: ${receiptHeightMm}mm !important; margin: 0 auto !important; overflow: hidden !important; } .receipt-print-shell > * { margin: 0 auto !important; }`;
        };

        const triggerPrint = () => {
            window.setTimeout(() => {
                applyReceiptPageSize();
                window.setTimeout(() => {
                    try {
                        printWindow.focus();
                        printWindow.print();
                    } finally {
                        cleanup();
                    }
                }, 100);
            }, 220);
        };

        printWindow.addEventListener('afterprint', cleanup, { once: true });
        if (printDocument.readyState === 'complete') {
            triggerPrint();
        } else {
            iframe.addEventListener('load', triggerPrint, { once: true });
        }

        return true;
    };
    const initPrintNodeTriggers = () => {
        if (document.body.dataset.printNodeBound === 'true') {
            return;
        }

        document.body.dataset.printNodeBound = 'true';
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-print-node]');
            if (!trigger) {
                return;
            }

            event.preventDefault();

            const selector = trigger.getAttribute('data-print-node') || '';
            if (selector === '') {
                return;
            }

            const modalScope = trigger.closest('.modal');
            const node = modalScope?.querySelector(selector) || document.querySelector(selector);
            if (!node) {
                showToast('Receipt content was not found for printing.', 'danger');
                return;
            }

            printNode(node, {
                title: trigger.dataset.printTitle || document.title,
                pageSize: trigger.dataset.printPageSize || '',
            });
        });
    };

    const getLoaderElements = () => ({
        overlay: document.querySelector('[data-app-loader]'),
        bar: document.querySelector('[data-loading-bar]'),
        title: document.querySelector('[data-loader-title]'),
        detail: document.querySelector('[data-loader-detail]'),
        stage: document.querySelector('[data-loader-stage]'),
        elapsed: document.querySelector('[data-loader-elapsed]'),
        live: document.querySelector('[data-loader-live]'),
    });

    const getLoadingPreset = (mode = 'navigate') => loaderPresets[mode] || loaderPresets.navigate;

    const clearLoaderTimers = () => {
        window.clearInterval(loaderState.stageTimer);
        window.clearInterval(loaderState.elapsedTimer);
        window.clearTimeout(loaderState.hideTimer);
        loaderState.stageTimer = null;
        loaderState.elapsedTimer = null;
        loaderState.hideTimer = null;
    };

    const startLoaderTelemetry = (stages = []) => {
        const { stage, elapsed } = getLoaderElements();
        const steps = Array.isArray(stages) && stages.length > 0 ? stages : ['Working'];

        loaderState.stageIndex = 0;
        if (stage) {
            stage.textContent = steps[0];
        }

        if (elapsed) {
            elapsed.textContent = '0s';
        }

        window.clearInterval(loaderState.stageTimer);
        window.clearInterval(loaderState.elapsedTimer);

        if (steps.length > 1 && stage) {
            loaderState.stageTimer = window.setInterval(() => {
                loaderState.stageIndex = (loaderState.stageIndex + 1) % steps.length;
                stage.textContent = steps[loaderState.stageIndex];
            }, 1600);
        }

        if (elapsed) {
            loaderState.elapsedTimer = window.setInterval(() => {
                const seconds = Math.max(Math.floor((Date.now() - loaderState.activatedAt) / 1000), 0);
                elapsed.textContent = `${seconds}s`;
            }, 1000);
        }
    };

    const syncLoaderCopy = (mode = 'navigate', options = {}) => {
        const preset = getLoadingPreset(mode);
        const { overlay, title, detail, live } = getLoaderElements();
        const titleText = options.title || preset.title;
        const detailText = options.detail || preset.detail;

        loaderState.mode = mode;

        if (overlay) {
            overlay.dataset.loadingMode = mode;
            overlay.setAttribute('aria-hidden', 'false');
        }

        if (title) {
            title.textContent = titleText;
        }

        if (detail) {
            detail.textContent = detailText;
        }

        if (live) {
            live.textContent = `${titleText}. ${detailText}`;
        }

        startLoaderTelemetry(options.stages || preset.stages);
    };

    const showAppLoader = (mode = 'navigate', options = {}) => {
        const { overlay, bar } = getLoaderElements();
        if (!overlay || !bar || !body) {
            return;
        }

        clearLoaderTimers();
        if (options.track !== false) {
            loaderState.depth += 1;
        }

        loaderState.visible = true;
        loaderState.activatedAt = Date.now();
        syncLoaderCopy(mode, options);

        body.classList.add('app-loading');
        overlay.classList.add('is-active');
        bar.classList.add('is-active');
    };

    const hideAppLoader = ({ force = false } = {}) => {
        const { overlay, bar } = getLoaderElements();
        if (!overlay || !bar || !body) {
            return;
        }

        if (force) {
            loaderState.depth = 0;
        } else {
            loaderState.depth = Math.max(loaderState.depth - 1, 0);
        }

        if (loaderState.depth > 0) {
            return;
        }

        const elapsed = Date.now() - loaderState.activatedAt;
        const remaining = Math.max(loaderState.minVisibleMs - elapsed, 0);

        loaderState.hideTimer = window.setTimeout(() => {
            clearLoaderTimers();
            loaderState.visible = false;
            overlay.classList.remove('is-active');
            overlay.setAttribute('aria-hidden', 'true');
            bar.classList.remove('is-active');
            body.classList.remove('app-loading', 'app-booting');
        }, remaining);
    };

    const guessLoadingLabel = (element, fallback = 'Working') => {
        const explicit = element?.dataset?.loadingLabel;
        if (explicit) {
            return explicit;
        }

        const sourceText = String(element?.textContent || element?.value || '').trim().toLowerCase();
        if (sourceText.includes('sign in') || sourceText.includes('login')) {
            return 'Signing in';
        }

        if (sourceText.includes('sign out') || sourceText.includes('logout')) {
            return 'Signing out';
        }

        if (sourceText.includes('save') || sourceText.includes('update') || sourceText.includes('submit')) {
            return 'Saving';
        }

        if (sourceText.includes('delete') || sourceText.includes('remove')) {
            return 'Deleting';
        }

        if (sourceText.includes('export') || sourceText.includes('download')) {
            return 'Exporting';
        }

        if (sourceText.includes('print')) {
            return 'Preparing';
        }

        if (sourceText.includes('filter') || sourceText.includes('search') || sourceText.includes('apply')) {
            return 'Refreshing';
        }

        if (sourceText.includes('sale') || sourceText.includes('checkout') || sourceText.includes('pay')) {
            return 'Processing';
        }

        return fallback;
    };

    const setLoadingState = (submitter, loading, label = null) => {
        if (!submitter) {
            return;
        }

        const resolvedLabel = label || guessLoadingLabel(submitter);
        const isInput = submitter.tagName === 'INPUT';

        if (loading) {
            if (submitter.dataset.loadingActive === 'true') {
                return;
            }

            submitter.dataset.loadingActive = 'true';
            submitter.dataset.loadingLabelResolved = resolvedLabel;
            submitter.dataset.originalDisabled = submitter.disabled ? 'true' : 'false';
            submitter.style.setProperty('--btn-loading-width', `${Math.ceil(submitter.getBoundingClientRect().width)}px`);
            submitter.classList.add('is-loading');
            submitter.setAttribute('aria-busy', 'true');
            submitter.setAttribute('disabled', 'disabled');

            if (isInput) {
                if (!submitter.dataset.originalValue) {
                    submitter.dataset.originalValue = submitter.value;
                }
                submitter.value = resolvedLabel;
                return;
            }

            if (!submitter.dataset.originalHtml) {
                submitter.dataset.originalHtml = submitter.innerHTML;
            }

            submitter.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>${resolvedLabel}</span>`;
            return;
        }

        submitter.classList.remove('is-loading');
        submitter.removeAttribute('aria-busy');
        submitter.style.removeProperty('--btn-loading-width');

        if (submitter.dataset.originalDisabled !== 'true') {
            submitter.removeAttribute('disabled');
        }

        if (isInput) {
            if (submitter.dataset.originalValue) {
                submitter.value = submitter.dataset.originalValue;
            }
        } else if (submitter.dataset.originalHtml) {
            submitter.innerHTML = submitter.dataset.originalHtml;
        }

        delete submitter.dataset.loadingActive;
        delete submitter.dataset.loadingLabelResolved;
        delete submitter.dataset.originalDisabled;
    };

    const looksLikeDownloadAction = ({ destination = '', text = '', method = '', element = null } = {}) => {
        if (element?.dataset?.download === 'true' || element?.dataset?.noLoader === 'true') {
            return true;
        }

        const normalizedDestination = String(destination || '').trim().toLowerCase();
        const normalizedText = String(text || '').trim().toLowerCase();
        const normalizedMethod = String(method || '').trim().toUpperCase();

        if (
            normalizedDestination.includes('/export')
            || normalizedDestination.includes('/download')
            || normalizedDestination.includes('/import-template')
            || normalizedDestination.includes('format=csv')
            || normalizedDestination.includes('format=xlsx')
            || normalizedDestination.includes('format=pdf')
            || normalizedDestination.endsWith('.csv')
            || normalizedDestination.endsWith('.xlsx')
            || normalizedDestination.endsWith('.pdf')
            || normalizedDestination.endsWith('.sql')
            || normalizedDestination.endsWith('.zip')
        ) {
            return true;
        }

        if (normalizedMethod === 'GET' && (normalizedText.includes('download') || normalizedText.includes('export'))) {
            return true;
        }

        return false;
    };

    const isDownloadLink = (link) => {
        if (!link) {
            return false;
        }

        return looksLikeDownloadAction({
            destination: link.getAttribute('href') || link.href || '',
            text: link.textContent || '',
            method: 'GET',
            element: link,
        });
    };

    const isDownloadForm = (form, submitter = null) => {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        const method = String(submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'GET').toUpperCase();
        const action = String(submitter?.getAttribute('formaction') || form.getAttribute('action') || form.action || '');
        const text = String(submitter?.textContent || submitter?.value || form.textContent || '');
        const target = String(submitter?.getAttribute('formtarget') || form.getAttribute('target') || '').toLowerCase();

        if (submitter?.dataset?.download === 'true' || form.dataset.download === 'true') {
            return true;
        }

        if (target && target !== '_self') {
            return true;
        }

        return looksLikeDownloadAction({
            destination: action,
            text,
            method,
            element: submitter || form,
        });
    };

    const inferLoadingMode = ({ element = null, form = null, link = null, fallbackMode = 'navigate' } = {}) => {
        const candidate = element || form || link;
        const method = String(form?.getAttribute('method') || '').toUpperCase();
        const destination = String(form?.getAttribute('action') || link?.getAttribute('href') || candidate?.getAttribute?.('href') || '').toLowerCase();
        const text = String(candidate?.textContent || candidate?.value || '').toLowerCase();

        if (destination.includes('/logout') || text.includes('logout') || text.includes('sign out')) {
            return 'logout';
        }

        if (looksLikeDownloadAction({ destination, text, method, element: candidate })) {
            return 'export';
        }

        if (method === 'GET') {
            return 'filter';
        }

        if (destination.includes('/pos') || text.includes('sale') || text.includes('checkout') || text.includes('payment')) {
            return 'pos';
        }

        return loaderPresets[fallbackMode] ? fallbackMode : 'navigate';
    };

    const resolveLoadingContext = ({ form = null, submitter = null, link = null, fallbackMode = 'navigate' } = {}) => {
        const source = submitter || form || link;
        const explicitMode = source?.dataset?.loadingMode || form?.dataset?.loadingMode || link?.dataset?.loadingMode;
        const mode = loaderPresets[explicitMode]
            ? explicitMode
            : inferLoadingMode({ element: source, form, link, fallbackMode });

        return {
            mode,
            title: source?.dataset?.loadingTitle || form?.dataset?.loadingTitle || link?.dataset?.loadingTitle,
            detail: source?.dataset?.loadingDetail || form?.dataset?.loadingDetail || link?.dataset?.loadingDetail,
        };
    };
    const initAlpineTree = (scope) => {
        if (!scope || !window.Alpine || typeof window.Alpine.initTree !== 'function') {
            return;
        }

        try {
            window.Alpine.initTree(scope);
        } catch (error) {
            console.error('Alpine.initTree error', error);
        }
    };
    const normalizeSelectorList = (value) => String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);

    const inheritAjaxContextFromTrigger = (scope, trigger) => {
        if (!scope || !trigger) {
            return;
        }

        const inheritedKeys = ['refreshTarget', 'refreshUrl', 'reloadOnSuccess', 'closeModalOnSuccess'];
        const targets = scope.querySelectorAll('form[data-ajax="true"], [data-modal]');

        targets.forEach((node) => {
            inheritedKeys.forEach((key) => {
                const value = trigger.dataset?.[key];
                if (value !== undefined) {
                    node.dataset[key] = value;
                }
            });
        });
    };

    const refreshPageRegions = async (selectors, url = window.location.href) => {
        const selectorList = Array.from(new Set(normalizeSelectorList(selectors)));
        if (selectorList.length === 0) {
            return false;
        }

        const response = await fetch(url || window.location.href, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html, */*',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to refresh page regions.');
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        selectorList.forEach((selector) => {
            const currentNodes = Array.from(document.querySelectorAll(selector));
            const nextNodes = Array.from(doc.querySelectorAll(selector));

            if (currentNodes.length === 0 || currentNodes.length !== nextNodes.length) {
                throw new Error(`Refresh target mismatch for selector: ${selector}`);
            }

            currentNodes.forEach((currentNode, index) => {
                const nextNode = nextNodes[index];
                currentNode.replaceWith(nextNode);
                initAlpineTree(nextNode);
                initConfirmButtons(nextNode);
                initLoadingForms(nextNode);
                initBarcodes(nextNode);
                initDataTables(nextNode);
                initSaleLineExperience(nextNode);
                bindModalTriggers(nextNode);
                initAutoOpenModals(nextNode);
            });
        });

        try {
            if (window.Inventory && (document.querySelector('.inventory-adjustment-form') || document.body.dataset.page === 'inventory' || document.getElementById('adjustModal'))) {
                window.Inventory.init();
            }
        } catch (error) {
            console.error('Inventory.init error', error);
        }

        try {
            if (window.Categories && document.getElementById('categories-table')) {
                window.Categories.init();
            }
        } catch (error) {
            console.error('Categories.init error', error);
        }

        try {
            if (window.Products && document.getElementById('products-table')) {
                window.Products.init();
            }
        } catch (error) {
            console.error('Products.init error', error);
        }

        return true;
    };

    const rebindManagedDataTable = (rootNode, selector) => {
        if (!rootNode || !window.jQuery || !$.fn.dataTable) {
            return;
        }

        const table = rootNode.querySelector(selector);
        if (!table) {
            return;
        }

        if ($.fn.dataTable.isDataTable(table)) {
            $(table).DataTable().destroy();
        }

        initDataTables(rootNode);
    };

    const initSaleLineExperience = (scope = document) => {
        const saleRoots = [];
        if (scope instanceof Element && scope.matches('[data-sale-detail-root]')) {
            saleRoots.push(scope);
        }

        saleRoots.push(...scope.querySelectorAll('[data-sale-detail-root]'));

        saleRoots.forEach((rootNode) => {
            const searchInput = rootNode.querySelector('#saleItemSearch');
            const filterButtons = Array.from(rootNode.querySelectorAll('[data-sale-line-filter]'));
            const dataTableElement = rootNode.querySelector('[data-sale-line-table]');

            if (!searchInput && filterButtons.length === 0) {
                return;
            }

            const getActiveFilter = () => rootNode.dataset.saleLineFilter || 'all';
            const syncFilterButtons = () => {
                const activeFilter = getActiveFilter();
                filterButtons.forEach((button) => {
                    button.classList.toggle('is-active', (button.getAttribute('data-sale-line-filter') || 'all') === activeFilter);
                });
            };
            const rowMatches = (row, query, activeFilter) => {
                const searchBlob = String(row.getAttribute('data-line-search') || '').toLowerCase();
                const matchesQuery = query === '' || searchBlob.includes(query);
                const isReturnable = row.getAttribute('data-line-returnable') === '1';
                const isReturned = row.getAttribute('data-line-returned') === '1';
                const matchesFilter = activeFilter === 'all'
                    || (activeFilter === 'returnable' && isReturnable)
                    || (activeFilter === 'returned' && isReturned);

                return matchesQuery && matchesFilter;
            };
            const applyFilters = () => {
                const query = String(searchInput?.value || '').trim().toLowerCase();
                const activeFilter = getActiveFilter();

                rootNode.querySelectorAll('[data-sale-line-row]').forEach((row) => {
                    const matches = rowMatches(row, query, activeFilter);
                    row.style.display = matches ? '' : 'none';

                    const detailRow = row.nextElementSibling;
                    if (detailRow && detailRow.classList.contains('child')) {
                        detailRow.style.display = matches ? '' : 'none';
                    }
                });
            };

            if (searchInput && searchInput.dataset.saleLineBound !== 'true') {
                searchInput.dataset.saleLineBound = 'true';
                searchInput.addEventListener('input', applyFilters);
            }

            filterButtons.forEach((button) => {
                if (button.dataset.saleLineBound === 'true') {
                    return;
                }

                button.dataset.saleLineBound = 'true';
                button.addEventListener('click', () => {
                    rootNode.dataset.saleLineFilter = button.getAttribute('data-sale-line-filter') || 'all';
                    syncFilterButtons();
                    applyFilters();
                });
            });

            if (dataTableElement && window.jQuery?.fn?.dataTable?.isDataTable(dataTableElement)) {
                window.jQuery(dataTableElement)
                    .off('draw.saleLineFilters')
                    .on('draw.saleLineFilters', applyFilters);
            }

            if (!rootNode.dataset.saleLineFilter) {
                rootNode.dataset.saleLineFilter = 'all';
            }

            syncFilterButtons();
            applyFilters();
        });
    };

    window.productForm = function (initialVariants) {
        const blankVariant = () => ({
            variant_name: '',
            variant_value: '',
            sku: '',
            barcode: '',
            price_adjustment: 0,
            stock_quantity: 0,
        });

        return {
            variants: Array.isArray(initialVariants) && initialVariants.length ? initialVariants : [blankVariant()],
            init() {},
            addVariant() {
                this.variants.push(blankVariant());
            },
            removeVariant(index) {
                this.variants.splice(index, 1);
                if (this.variants.length === 0) {
                    this.variants.push(blankVariant());
                }
            },
            generate(type) {
                const value = type === 'sku'
                    ? 'SKU-' + Math.random().toString(36).substring(2, 10).toUpperCase()
                    : `${new Date().toISOString().slice(2, 10).replace(/-/g, '')}${Math.floor(100000 + Math.random() * 900000)}`;
                const root = this.$root || this.$el || document.activeElement?.closest('[data-product-form-root]') || document;
                const input = root.querySelector(`[name="${type}"]`);

                if (!input) {
                    return;
                }

                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
    };

    window.purchaseOrderForm = function (products, seededItems) {
        const blankItem = () => ({
            product_id: '',
            quantity: 1,
            unit_cost: 0,
            tax_rate: 0,
        });

        return {
            products,
            items: Array.isArray(seededItems) && seededItems.length ? seededItems : [blankItem()],
            init() {},
            addItem() {
                this.items.push(blankItem());
            },
            removeItem(index) {
                if (this.items.length === 1) {
                    this.items = [blankItem()];
                    return;
                }

                this.items.splice(index, 1);
            },
            hydrateItem(index) {
                const selected = this.products.find((product) => String(product.id) === String(this.items[index].product_id));
                if (!selected) {
                    return;
                }

                if (!Number(this.items[index].unit_cost || 0)) {
                    this.items[index].unit_cost = Number(selected.cost_price || 0);
                }

                this.items[index].tax_rate = Number(selected.tax_rate || 0);
            },
            preview(item) {
                const subtotal = Number(item.quantity || 0) * Number(item.unit_cost || 0);
                const total = subtotal + (subtotal * (Number(item.tax_rate || 0) / 100));
                return `Line total: ${total.toFixed(2)}`;
            }
        };
    };

    const initThemeButtons = () => {
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            if (button.dataset.boundThemeToggle === 'true') {
                return;
            }

            button.dataset.boundThemeToggle = 'true';
            button.addEventListener('click', () => {
                setTheme(getTheme() === 'dark' ? 'light' : 'dark');
            });
        });

        syncThemeButtons();
    };

    const initConfirmButtons = (scope = document) => {
        scope.querySelectorAll('[data-confirm-delete], [data-confirm-action]').forEach((button) => {
            if (button.dataset.confirmBound === 'true') {
                return;
            }

            button.dataset.confirmBound = 'true';
            button.addEventListener('click', async (event) => {
                event.preventDefault();

                const form = button.closest('form');
                const href = button.getAttribute('href') || button.dataset.href;
                const result = await showConfirm({
                    title: button.dataset.confirmTitle || 'Proceed with this action?',
                    text: button.dataset.confirmText || 'This change will be applied immediately.',
                    confirmButtonText: button.dataset.confirmButton || 'Confirm',
                    icon: button.dataset.confirmIcon || 'warning',
                    danger: true,
                });

                if (!result.isConfirmed) {
                    return;
                }

                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        if (button instanceof HTMLElement && (button.getAttribute('type') || '').toLowerCase() === 'submit') {
                            form.requestSubmit(button);
                        } else {
                            form.requestSubmit();
                        }
                        return;
                    }

                    if (!isDownloadForm(form, button)) {
                        const context = resolveLoadingContext({ form, submitter: button, fallbackMode: 'submit' });
                        showAppLoader(context.mode, context);
                        setLoadingState(button, true);
                    }
                    form.submit();
                    return;
                }

                if (href) {
                    if (!looksLikeDownloadAction({ destination: href, text: button.textContent || '', method: 'GET', element: button })) {
                        const context = resolveLoadingContext({ link: button, fallbackMode: 'navigate' });
                        showAppLoader(context.mode, context);
                    }
                    window.location.href = href;
                }
            });
        });
    };
    const initBarcodes = (scope = document) => {

    // Delegated confirmation handler for elements that may be moved or cloned
    // by third-party widgets (DataTables). This will only handle elements
    // that do not already have `data-confirm-bound` to avoid double prompts.
    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-confirm-action], [data-confirm-delete]');
        if (!trigger) return;
        if (trigger.dataset.confirmBound === 'true') return; // already handled by direct binding

        event.preventDefault();
        const form = trigger.closest('form');
        const href = trigger.getAttribute('href') || trigger.dataset.href;

        (async function () {
            const result = await showConfirm({
                title: trigger.dataset.confirmTitle || 'Proceed with this action?',
                text: trigger.dataset.confirmText || 'This change will be applied immediately.',
                confirmButtonText: trigger.dataset.confirmButton || 'Confirm',
                icon: trigger.dataset.confirmIcon || 'warning',
                danger: true,
            });

            if (!result.isConfirmed) return;

            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    if (trigger instanceof HTMLElement && (trigger.getAttribute('type') || '').toLowerCase() === 'submit') {
                        form.requestSubmit(trigger);
                    } else {
                        form.requestSubmit();
                    }
                    return;
                }

                if (!isDownloadForm(form, trigger)) {
                    const context = resolveLoadingContext({ form, submitter: trigger, fallbackMode: 'submit' });
                    showAppLoader(context.mode, context);
                    setLoadingState(trigger, true);
                }
                form.submit();
                return;
            }

            if (href) {
                if (!looksLikeDownloadAction({ destination: href, text: trigger.textContent || '', method: 'GET', element: trigger })) {
                    const context = resolveLoadingContext({ link: trigger, fallbackMode: 'navigate' });
                    showAppLoader(context.mode, context);
                }
                window.location.href = href;
            }
        })();
    });

    // Capture submit events for category delete forms (covers forms without
    // data-confirm attributes or moved/cloned by DataTables). Use capture
    // phase to intercept before native submission.
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // already handled
        if (form.dataset.deleteConfirmBound === 'true') return;

        const action = String(form.getAttribute('action') || form.action || '').toLowerCase();
        const hasId = !!form.querySelector('input[name="id"]');

        if (!hasId) return; // not a per-record action

        // detect categories delete endpoint (be permissive)
        if (action.indexOf('/products/categories/delete') === -1 && action.indexOf('categories/delete') === -1) {
            return;
        }

        e.preventDefault();

        const result = await showConfirm({
            title: form.dataset.confirmTitle || 'Delete this category?',
            text: form.dataset.confirmText || 'This category will be removed permanently.',
            icon: 'warning',
            confirmButtonText: form.dataset.confirmButton || 'Delete',
            danger: true,
        });

        if (!result.isConfirmed) return;

        // mark to avoid re-interception and submit
        form.dataset.deleteConfirmBound = 'true';
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } finally {
            // cleanup in case the page remains and user re-submits later
            setTimeout(() => { delete form.dataset.deleteConfirmBound; }, 2000);
        }
    }, true);

    // Global delegated handler for Edit buttons to work regardless of where
    // DataTables places them (child rows, dropdowns, clones, etc.). This mirrors
    // the table-level handler but listens at document scope to catch moved nodes.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.edit-row');
        if (!btn) return;
        if (e.defaultPrevented) return;

        e.preventDefault();

        // Find the nearest data row with category id. The button could be inside
        // a responsive child row or dropdown; search up and previous siblings.
        let tr = btn.closest('tr');
        if (!tr) return;

        let parentRow = tr.closest('tr[data-category-id]');
        if (!parentRow) {
            let prev = tr.previousElementSibling;
            while (prev) {
                if (prev.dataset && prev.dataset.categoryId) { parentRow = prev; break; }
                prev = prev.previousElementSibling;
            }
        }

        if (!parentRow) return;

        const id = parentRow.dataset.categoryId;
        const inlineForm = document.getElementById('edit-form-' + id);
        if (!inlineForm) return;

        const modalEl = document.getElementById('globalModal');
        const modalBody = modalEl ? modalEl.querySelector('#globalModalBody') : null;
        const modalTitle = modalEl ? modalEl.querySelector('#globalModalLabel') : null;

        const cloned = inlineForm.cloneNode(true);
        cloned.querySelectorAll('input, textarea, select').forEach(function (el) {
            const name = el.name;
            const orig = inlineForm.querySelector('[name="' + name + '"]');
            if (orig) { try { el.value = orig.value; } catch (err) {} }
        });

        if (modalBody && modalTitle) {
            modalTitle.textContent = 'Edit Category';
            modalBody.innerHTML = '';
            modalBody.appendChild(cloned);

            try { initConfirmButtons(modalBody); initLoadingForms(modalBody); initDataTables(modalBody); bindModalTriggers(modalBody); initAlpineTree(modalBody); } catch (err) {}

            try { const first = cloned.querySelector('input,select,textarea'); if (first) first.focus({ preventScroll: true }); } catch (e) {}
            try { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } catch (e) { modalEl.classList.add('show'); }

            cloned.querySelectorAll('.cancel-edit').forEach(function (b) {
                b.addEventListener('click', function () { try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch (e) { modalEl.classList.remove('show'); } });
            });
        }
    });

    // Also handle clicks inside an actions container that may not include the
    // `.edit-row` class (DataTables responsive child markup may re-wrap buttons).
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented) return;

        // If an explicit edit button was clicked, ignore — handled above.
        if (e.target.closest('.edit-row')) return;

        const actionsContainer = e.target.closest('.actions, .dropdown-menu, .dataTables_child');
        if (!actionsContainer) return;

        // Try to find an edit button within the actions container
        let foundEdit = actionsContainer.querySelector('.edit-row');

        // Fallback: look for a button/link whose label or text includes 'edit'
        if (!foundEdit) {
            foundEdit = Array.from(actionsContainer.querySelectorAll('button, a')).find(el => {
                const t = String(el.getAttribute('aria-label') || el.title || el.textContent || '').toLowerCase();
                return t.includes('edit');
            });
        }

        if (!foundEdit) return;

        // Prevent re-entering: simulate click on the found edit control so existing
        // handlers will run (they are bound on document/table scope).
        try { foundEdit.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })); } catch (err) {}
    });
        const format = body?.dataset.barcodeFormat || 'CODE128';
        scope.querySelectorAll('[data-barcode]').forEach((element) => {
            if (!element.dataset.barcode || element.dataset.barcodeReady === 'true') {
                return;
            }

            try {
                JsBarcode(element, element.dataset.barcode, {
                    format,
                    displayValue: true,
                    fontSize: 11,
                    height: 34,
                    margin: 0,
                    lineColor: chartPalette().text,
                });
                element.dataset.barcodeReady = 'true';
            } catch (error) {
                // Ignore barcode render failures for malformed demo data.
            }
        });
    };

    const initLoadingForms = (scope = document) => {
        scope.querySelectorAll('form:not([data-skip-loading])').forEach((form) => {
            if (form.dataset.loadingBound === 'true' || form.dataset.ajax === 'true') {
                return;
            }

            form.dataset.loadingBound = 'true';
            form.addEventListener('submit', (event) => {
                const target = String(form.getAttribute('target') || '').toLowerCase();
                if (target && target !== '_self') {
                    return;
                }

                const submitter = event.submitter || form.querySelector('[type="submit"]');
                if (isDownloadForm(form, submitter)) {
                    return;
                }

                const context = resolveLoadingContext({ form, submitter, fallbackMode: 'submit' });
                form.setAttribute('aria-busy', 'true');
                showAppLoader(context.mode, context);
                setLoadingState(submitter, true);
            });
        });
    };

    const initToasts = () => {
        document.querySelectorAll('.toast').forEach((toastEl) => {
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4200 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        });
    };

    const bindOverflowIndicators = (scrollContainer, host = scrollContainer) => {
        if (!scrollContainer || !host) {
            return {
                sync() {},
                destroy() {},
            };
        }

        const sync = () => {
            const max = Math.max(scrollContainer.scrollWidth - scrollContainer.clientWidth, 0);
            host.classList.toggle('has-scroll-left', max > 5 && scrollContainer.scrollLeft > 5);
            host.classList.toggle('has-scroll-right', max > 5 && scrollContainer.scrollLeft < max - 5);
        };

        const onScroll = () => sync();
        scrollContainer.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', sync);

        let resizeObserver = null;
        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(() => sync());
            resizeObserver.observe(scrollContainer);
        }

        window.requestAnimationFrame(sync);

        return {
            sync,
            destroy() {
                scrollContainer.removeEventListener('scroll', onScroll);
                window.removeEventListener('resize', sync);
                resizeObserver?.disconnect();
                host.classList.remove('has-scroll-left', 'has-scroll-right');
            }
        };
    };

    const overflowIndicatorControllers = new Map();
    const actionColumnPattern = /\bactions?\b/i;
    const collectScopedNodes = (scope, selector) => {
        const nodes = [];

        if (scope instanceof Element && scope.matches(selector)) {
            nodes.push(scope);
        }

        if (scope instanceof Document || scope instanceof DocumentFragment || scope instanceof Element) {
            nodes.push(...scope.querySelectorAll(selector));
        }

        return Array.from(new Set(nodes));
    };
    const syncTableActionColumns = (table) => {
        if (!(table instanceof HTMLTableElement)) {
            return [];
        }

        const headerRow = table.tHead?.rows?.[0];
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return [];
        }

        const actionIndexes = Array.from(headerRow.cells).reduce((indexes, cell, index) => {
            const headingText = String(cell.textContent || '').trim();
            const shouldMark = cell.classList.contains('actions')
                || cell.dataset.stickyActions === 'true'
                || actionColumnPattern.test(headingText);

            if (shouldMark) {
                cell.classList.add('actions');
                indexes.push(index);
            }

            return indexes;
        }, []);

        if (actionIndexes.length === 0) {
            return [];
        }

        const applyToSection = (section) => {
            if (!(section instanceof HTMLTableSectionElement)) {
                return;
            }

            Array.from(section.rows).forEach((row) => {
                actionIndexes.forEach((index) => {
                    const cell = row.cells[index];
                    if (cell) {
                        cell.classList.add('actions');
                    }
                });
            });
        };

        Array.from(table.tBodies || []).forEach(applyToSection);
        applyToSection(table.tFoot || null);

        return actionIndexes;
    };
    const initTableChrome = (scope = document) => {
        for (const [host, controller] of overflowIndicatorControllers.entries()) {
            if (!host.isConnected) {
                controller.destroy();
                overflowIndicatorControllers.delete(host);
            }
        }

        collectScopedNodes(scope, 'table').forEach((table) => {
            syncTableActionColumns(table);
        });

        collectScopedNodes(scope, '.table-responsive, .table-frame--datatable').forEach((host) => {
            if (!host.querySelector('table')) {
                return;
            }

            const existingController = overflowIndicatorControllers.get(host);
            if (existingController) {
                existingController.sync();
                return;
            }

            overflowIndicatorControllers.set(host, bindOverflowIndicators(host, host));
        });
    };

    const parseDataTableBoolean = (value, fallback = true) => {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }

        return !['false', '0', 'no', 'off'].includes(String(value).trim().toLowerCase());
    };

    const buildDataTableDom = ({ buttonsEnabled = true, searching = true, info = true, paging = true } = {}) => {
        const headSections = [];
        const footSections = [];

        if (buttonsEnabled) {
            headSections.push("<'datatable-head__actions'B>");
        }

        if (searching) {
            headSections.push("<'datatable-head__search'f>");
        }

        if (info) {
            footSections.push("<'datatable-foot__info'i>");
        }

        if (paging) {
            footSections.push("<'datatable-foot__pagination'p>");
        }

        return `${headSections.length > 0 ? `<'datatable-head'${headSections.join('')}>` : ''}` +
            "<'datatable-scroll't>" +
            `${footSections.length > 0 ? `<'datatable-foot'${footSections.join('')}>` : ''}`;
    };

    const getTableColumnCount = (table) => {
        const headerRow = table?.querySelector('thead tr');
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return 0;
        }

        return Array.from(headerRow.cells).reduce((total, cell) => {
            const span = Number.parseInt(String(cell.colSpan || 1), 10);
            return total + (Number.isFinite(span) && span > 0 ? span : 1);
        }, 0);
    };

    const hasUnsupportedDataTableStructure = (table) => {
        if (!(table instanceof HTMLTableElement)) {
            return true;
        }

        const tbody = table.tBodies?.[0];
        if (!(tbody instanceof HTMLTableSectionElement)) {
            return false;
        }

        if (tbody.querySelector('template')) {
            return true;
        }

        const expectedColumnCount = getTableColumnCount(table);
        if (expectedColumnCount < 1) {
            return true;
        }

        return Array.from(tbody.rows).some((row) => {
            const cells = Array.from(row.cells);
            if (cells.length === 0) {
                return true;
            }

            const hasMergedCells = cells.some((cell) => (cell.colSpan || 1) !== 1 || (cell.rowSpan || 1) !== 1);
            if (hasMergedCells) {
                return true;
            }

            return cells.length !== expectedColumnCount;
        });
    };

    const initDataTables = (scope = document) => {
        initTableChrome(scope);

        if (!window.jQuery || !$.fn.dataTable) {
            return;
        }

        $(scope).find('.data-table').each(function initTable() {
            if ($.fn.dataTable.isDataTable(this)) {
                return;
            }

            if (hasUnsupportedDataTableStructure(this)) {
                if (this.dataset.dataTableSkipLogged !== 'true') {
                    this.dataset.dataTableSkipLogged = 'true';
                    console.warn('Skipping DataTables init for unsupported table structure.', this);
                }
                return;
            }

            const $table = $(this);
            syncTableActionColumns(this);
            const $responsiveParent = $table.parent('.table-responsive');
            if ($responsiveParent.length) {
                $responsiveParent.removeClass('table-responsive').addClass('table-frame table-frame--datatable');
            } else if (!$table.parent().hasClass('table-frame--datatable')) {
                $table.wrap('<div class="table-frame table-frame--datatable"></div>');
            }
            const tableFrame = $table.parent('.table-frame--datatable').get(0) || this.closest('.table-frame--datatable');
            if (tableFrame) {
                initTableChrome(tableFrame);
            }

            const searching = parseDataTableBoolean($table.attr('data-table-search'), true);
            const paging = parseDataTableBoolean($table.attr('data-table-paging'), true);
            const info = parseDataTableBoolean($table.attr('data-table-info'), true);
            const ordering = parseDataTableBoolean($table.attr('data-table-ordering'), true);
            const responsive = parseDataTableBoolean($table.attr('data-table-responsive'), true);
            const buttonsEnabled = parseDataTableBoolean($table.attr('data-table-buttons'), true);
            const pageLengthRaw = Number.parseInt(String($table.attr('data-table-page-length') || '10'), 10);
            const pageLength = Number.isFinite(pageLengthRaw) && pageLengthRaw > 0 ? pageLengthRaw : 10;
            const orderConfigRaw = String($table.attr('data-table-order') || '').trim();
            let orderConfig = [];

            if (orderConfigRaw !== '') {
                const [columnRaw, directionRaw] = orderConfigRaw.split(':');
                const columnIndex = Number.parseInt(String(columnRaw || ''), 10);
                const direction = String(directionRaw || 'asc').trim().toLowerCase() === 'desc' ? 'desc' : 'asc';

                if (Number.isFinite(columnIndex) && columnIndex >= 0) {
                    orderConfig = [[columnIndex, direction]];
                }
            }

            const nonOrderableIndexes = [];
            $table.find('thead th').each(function collectNonSortable(index) {
                const $heading = $(this);
                const headingText = String($heading.text() || '').trim().toLowerCase();
                if (
                    $heading.hasClass('no-sort') ||
                    $heading.data('sortable') === false ||
                    $heading.attr('data-sortable') === 'false' ||
                    headingText.includes('action')
                ) {
                    nonOrderableIndexes.push(index);
                }
            });

            $table.DataTable({
                pageLength,
                responsive,
                searching,
                paging,
                info,
                ordering,
                order: orderConfig,
                orderClasses: false,
                autoWidth: false,
                pagingType: 'simple_numbers',
                dom: buildDataTableDom({ buttonsEnabled, searching, info, paging }),
                columnDefs: ordering && nonOrderableIndexes.length > 0 ? [{ targets: nonOrderableIndexes, orderable: false }] : [],
                buttons: buttonsEnabled ? [
                    { extend: 'copy', text: '<i class="bi bi-clipboard me-1"></i>Copy', className: 'btn btn-sm btn-outline-secondary' },
                    { extend: 'csv', text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV', className: 'btn btn-sm btn-outline-secondary' },
                    { extend: 'excel', text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel', className: 'btn btn-sm btn-outline-secondary' },
                    { extend: 'print', text: '<i class="bi bi-printer me-1"></i>Print', className: 'btn btn-sm btn-outline-secondary' }
                ] : [],
                language: {
                    search: '',
                    searchPlaceholder: 'Search this table',
                    info: 'Showing _START_ to _END_ of _TOTAL_ rows',
                    infoEmpty: 'No rows to show',
                    zeroRecords: 'No matching rows found',
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>'
                    }
                },
                initComplete() {
                    const $wrapper = $table.closest('.dataTables_wrapper');
                    $wrapper.addClass('datatable-ready');
                    if (searching) {
                        $wrapper.find('.dataTables_filter input').attr('aria-label', 'Search this table');
                    }
                    syncTableActionColumns($table.get(0));
                    if (tableFrame) {
                        initTableChrome(tableFrame);
                    }
                }
            });
        });
    };
    const syncMobileChrome = () => {
        const topbar = document.querySelector('.topbar');
        const isPosCompactShell = body?.classList.contains('page-pos') && window.innerWidth < 992;

        if (!topbar || (!isPosCompactShell && window.innerWidth >= 768)) {
            root.style.setProperty('--mobile-topbar-offset', '0px');
            return;
        }

        const rect = topbar.getBoundingClientRect();
        const offset = Math.max(Math.ceil(rect.bottom + 10), 0);
        root.style.setProperty('--mobile-topbar-offset', `${offset}px`);
    };

    const initSidebarExperience = () => {
        document.querySelectorAll('.custom-nav-link.active').forEach((link) => {
            link.setAttribute('aria-current', 'page');
        });

        document.querySelectorAll('.sidebar--desktop .custom-nav-link').forEach((link) => {
            const label = String(link.textContent || '').trim();
            if (label !== '' && !link.getAttribute('title')) {
                link.setAttribute('title', label);
            }
        });

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            if (button.dataset.sidebarBound === 'true') {
                return;
            }

            button.dataset.sidebarBound = 'true';
            button.addEventListener('click', () => {
                setSidebarState(getSidebarState() === 'collapsed' ? 'expanded' : 'collapsed');
            });
        });

        syncSidebarToggleButtons();

        try {
            const offcanvasEl = document.getElementById('mobileSidebar');
            if (!offcanvasEl || !window.bootstrap) {
                return;
            }

            const offcanvasBody = offcanvasEl.querySelector('.mobile-sidebar-panel__body');
            const syncSidebarState = (open) => {
                body.classList.toggle('sidebar-open', open);
            };

            offcanvasEl.addEventListener('show.bs.offcanvas', () => {
                syncMobileChrome();
                syncSidebarState(true);

                if (offcanvasBody) {
                    offcanvasBody.scrollTop = 0;
                }
            });

            offcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
                syncSidebarState(false);

                if (offcanvasBody) {
                    offcanvasBody.scrollTop = 0;
                }
            });
        } catch (error) {
            // Ignore when Bootstrap offcanvas is unavailable.
        }
    };

    const initOffcanvasLinks = () => {
        try {
            const offcanvasEl = document.getElementById('mobileSidebar');
            if (!offcanvasEl) {
                return;
            }

            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            document.querySelectorAll('.offcanvas-link').forEach((link) => {
                if (link.dataset.offcanvasBound === 'true') {
                    return;
                }

                link.dataset.offcanvasBound = 'true';
                link.addEventListener('click', () => {
                    setTimeout(() => offcanvas.hide(), 120);
                });
            });
        } catch (error) {
            // Ignore when Bootstrap offcanvas is unavailable.
        }
    };

    const shouldShowLinkLoader = (link, event) => {
        if (!link || event.defaultPrevented) {
            return false;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }

        const href = String(link.getAttribute('href') || '').trim();
        if (href === '' || href === '#' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }

        if (link.hasAttribute('download') || link.dataset.bsToggle || link.hasAttribute('data-modal') || link.dataset.noLoader === 'true' || isDownloadLink(link)) {
            return false;
        }

        const target = String(link.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') {
            return false;
        }

        let url;
        let current;
        try {
            url = new URL(link.href, window.location.href);
            current = new URL(window.location.href);
        } catch (error) {
            return false;
        }

        if (url.origin !== current.origin) {
            return false;
        }

        if (url.pathname === current.pathname && url.search === current.search && url.hash !== '' && url.hash !== current.hash) {
            return false;
        }

        return true;
    };

    const initPageTransitions = () => {
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!shouldShowLinkLoader(link, event)) {
                return;
            }

            const context = resolveLoadingContext({ link, fallbackMode: 'navigate' });
            showAppLoader(context.mode, context);
        });
    };
    const clearAjaxFormErrors = (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
        form.querySelectorAll('[data-ajax-error="true"]').forEach((node) => node.remove());
    };

    const ajaxErrorTarget = (field) => {
        if (!(field instanceof HTMLElement)) {
            return null;
        }

        return field.closest('.input-group') || field.closest('.form-check') || field;
    };

    const appendAjaxError = (field, message) => {
        if (!(field instanceof HTMLElement)) {
            return;
        }

        field.classList.add('is-invalid');

        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.dataset.ajaxError = 'true';
        feedback.textContent = message;

        const target = ajaxErrorTarget(field);
        if (target && target.parentNode) {
            target.insertAdjacentElement('afterend', feedback);
        }
    };

    const renderAjaxFormErrors = (form, errors = {}, summaryMessage = '') => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        clearAjaxFormErrors(form);

        let matchedField = null;
        let renderedAnyField = false;

        Object.entries(errors || {}).forEach(([name, messages]) => {
            const field = Array.from(form.elements).find((element) => element && element.name === name);
            const message = Array.isArray(messages) ? String(messages[0] || '') : String(messages || '');
            if (!field || message === '') {
                return;
            }

            appendAjaxError(field, message);
            if (!matchedField) {
                matchedField = field;
            }
            renderedAnyField = true;
        });

        if (summaryMessage || !renderedAnyField) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger rounded-4';
            alert.dataset.ajaxError = 'true';
            const strong = document.createElement('strong');
            strong.textContent = summaryMessage || 'Please fix the highlighted fields and try again.';
            alert.appendChild(strong);
            form.insertAdjacentElement('afterbegin', alert);
        }

        if (matchedField && typeof matchedField.focus === 'function') {
            matchedField.focus({ preventScroll: true });
        }
    };
    const submitAjaxForm = async (form, options = {}) => {
        const submitter = options.submitter || form.querySelector('[type="submit"]');
        let formData;
        try {
            formData = submitter ? new FormData(form, submitter) : new FormData(form);
        } catch (error) {
            formData = new FormData(form);
            if (submitter?.name) {
                formData.append(submitter.name, submitter.value);
            }
        }

        const action = (submitter?.getAttribute('formaction') || form.getAttribute('action') || window.location.href);
        const method = String(submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'post').toUpperCase();
        const context = resolveLoadingContext({ form, submitter, fallbackMode: 'ajax' });
        const useAppLoader = form.dataset.skipAppLoader !== 'true';
        let keepLoader = false;

        form.setAttribute('aria-busy', 'true');
        setLoadingState(submitter, true);
        if (useAppLoader) {
            showAppLoader(context.mode, context);
        }
        clearAjaxFormErrors(form);

        try {
            const response = await fetch(action, {
                method,
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json, text/html, */*',
                },
            });

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const payload = await response.json();

                if (payload.success === false) {
                    renderAjaxFormErrors(form, payload.errors || {}, payload.message || '');
                    showToast(payload.message || 'Operation failed', 'danger');
                    return { ok: false, payload };
                }

                if (payload.download_url) {
                    keepLoader = true;
                    const anchor = document.createElement('a');
                    anchor.href = payload.download_url;
                    anchor.target = '_blank';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    setTimeout(() => hideAppLoader({ force: true }), 450);
                }

                if (payload.message) {
                    showToast(payload.message, 'success');
                }

                try {
                    form.dispatchEvent(new CustomEvent('ajax:success', {
                        bubbles: true,
                        detail: { payload, submitter },
                    }));
                } catch (error) {
                    console.error('Ajax success event failed', error);
                }

                const refreshSelectors = normalizeSelectorList(
                    payload.refreshTarget || options.refreshTarget || form.dataset.refreshTarget
                );
                const refreshUrl = payload.refreshUrl || options.refreshUrl || form.dataset.refreshUrl || window.location.href;
                let refreshed = false;

                if (refreshSelectors.length > 0) {
                    try {
                        refreshed = await refreshPageRegions(refreshSelectors, refreshUrl);
                    } catch (error) {
                        console.error('Region refresh failed', error);
                    }
                }

                if (!refreshed && payload.redirect) {
                    keepLoader = true;
                    setTimeout(() => {
                        window.location.href = payload.redirect;
                    }, 280);
                } else if (!refreshed && options.reloadOnSuccess) {
                    keepLoader = true;
                    setTimeout(() => window.location.reload(), 450);
                }

                return { ok: true, payload };
            }

            const html = await response.text();
            return { ok: response.ok, html };
        } catch (error) {
            showToast('Request failed. Please try again.', 'danger');
            return { ok: false, error };
        } finally {
            form.removeAttribute('aria-busy');
            setLoadingState(submitter, false);
            if (useAppLoader && !keepLoader) {
                hideAppLoader();
            }
        }
    };
    const openRemoteModal = async (href, options = {}) => {
        if (!href) {
            return false;
        }

        const modalEl = document.getElementById('globalModal');
        if (!modalEl) {
            return false;
        }

        const dialog = modalEl.querySelector('.modal-dialog');
        const modalTitle = modalEl.querySelector('#globalModalLabel');
        const modalBody = modalEl.querySelector('#globalModalBody');
        const modalFooter = modalEl.querySelector('#globalModalFooter');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const size = options.size || 'lg';
        const sizeClass = size === 'xl'
            ? 'modal-xl'
            : (size === 'sm' ? 'modal-sm' : (size === 'fullscreen' ? 'modal-fullscreen-lg-down' : 'modal-lg'));
        const context = options.context || resolveLoadingContext({ link: options.trigger || null, fallbackMode: 'modal' });
        const useAppLoader = options.skipAppLoader !== true;

        try { modalEl._lastTrigger = options.trigger || null; } catch (err) {}
        dialog.className = `modal-dialog ${sizeClass} modal-dialog-centered modal-dialog-scrollable`;
        modalTitle.textContent = options.title || 'Details';
        modalBody.innerHTML = '<div class="modal-loading text-center py-5"><div class="spinner-border text-info mb-3"></div><div class="text-muted">Loading panel...</div></div>';
        modalFooter.innerHTML = '';
        modal.show();
        if (useAppLoader) {
            showAppLoader(context.mode, context);
        }

        try {
            const response = await fetch(href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            modalBody.innerHTML = await response.text();
            if (options.trigger) {
                inheritAjaxContextFromTrigger(modalBody, options.trigger);
            }
            initAlpineTree(modalBody);
            initConfirmButtons(modalBody);
            initLoadingForms(modalBody);
            initBarcodes(modalBody);
            initDataTables(modalBody);
            bindModalTriggers(modalBody);
            try { if (window.Inventory) { Inventory.init(modalBody); } } catch (err) { /* silent */ }

                    try {
                        modalEl.setAttribute('role', 'dialog');
                        modalEl.setAttribute('aria-modal', 'true');
                    } catch (err) {}

                    try {
                        const preferredFocus = modalBody.querySelector('[data-modal-primary-focus="true"]');
                        const fallbackFocus = modalBody.querySelector('input, select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])');
                        const focusTarget = preferredFocus || fallbackFocus;
                        if (focusTarget instanceof HTMLElement) {
                            window.requestAnimationFrame(() => focusTarget.focus({ preventScroll: true }));
                        }
                    } catch (err) { /* silent */ }

                    try {
                        const focusableSelector = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';
                        let lastFocused = document.activeElement;
                        const trap = (event) => {
                            if (event.key === 'Tab') {
                                const focusable = Array.from(modalBody.querySelectorAll(focusableSelector)).filter(el => el.offsetParent !== null);
                                if (focusable.length === 0) return;
                                const first = focusable[0];
                                const last = focusable[focusable.length - 1];
                                if (event.shiftKey) {
                                    if (document.activeElement === first) {
                                        event.preventDefault();
                                        last.focus();
                                    }
                                } else if (document.activeElement === last) {
                                    event.preventDefault();
                                    first.focus();
                                }
                                return;
                            }

                            const receiptShortcutRoot = modalBody.querySelector('[data-modal-receipt-shortcuts="true"]');
                            if (!(receiptShortcutRoot instanceof HTMLElement)) {
                                return;
                            }

                            const active = document.activeElement;
                            const isTypingTarget = active instanceof HTMLElement && (
                                active.tagName === 'INPUT'
                                || active.tagName === 'TEXTAREA'
                                || active.tagName === 'SELECT'
                                || active.isContentEditable
                            );

                            if (event.key === 'Enter' && !isTypingTarget) {
                                const primaryAction = modalBody.querySelector('[data-modal-primary-focus="true"]');
                                if (primaryAction instanceof HTMLElement) {
                                    event.preventDefault();
                                    primaryAction.click();
                                }
                                return;
                            }

                            if (event.key === 'Escape') {
                                event.preventDefault();
                                modal.hide();
                            }
                        };

                        modalEl.addEventListener('keydown', trap);
                        modalEl.addEventListener('hidden.bs.modal', () => {
                            modalEl.removeEventListener('keydown', trap);
                            try { if (lastFocused) lastFocused.focus({ preventScroll: true }); } catch (e) {}
                        }, { once: true });
                    } catch (err) { /* silent */ }

            return true;
        } catch (error) {
            modalBody.innerHTML = '<div class="alert alert-danger rounded-4 mb-0">Failed to load this panel. Please try again.</div>';
            return false;
        } finally {
            if (useAppLoader) {
                hideAppLoader();
            }
        }
    };
    const bindModalTriggers = (scope = document) => {
        scope.querySelectorAll('a[data-modal], button[data-modal]').forEach((trigger) => {
            if (trigger.dataset.modalBound === 'true') {
                return;
            }

            trigger.dataset.modalBound = 'true';
            trigger.addEventListener('click', async (event) => {
                event.preventDefault();
                await openRemoteModal(trigger.getAttribute('href') || trigger.dataset.href, {
                    trigger,
                    title: trigger.dataset.title || 'Details',
                    size: trigger.dataset.modalSize || 'lg',
                    context: resolveLoadingContext({ link: trigger, fallbackMode: 'modal' }),
                });
            });
        });
    };
    const initAutoOpenModals = (scope = document) => {
        scope.querySelectorAll('[data-auto-open-modal="true"]').forEach((trigger) => {
            if (trigger.dataset.autoModalOpened === 'true') {
                return;
            }

            trigger.dataset.autoModalOpened = 'true';
            window.setTimeout(() => {
                try {
                    trigger.click();
                } catch (error) {
                    console.error('Auto-open modal failed', error);
                }
            }, 120);
        });
    };

    const initAuthLogin = () => {
        const authRoot = document.querySelector('[data-auth-login]');
        if (!authRoot || authRoot.dataset.authBound === 'true') {
            return;
        }

        authRoot.dataset.authBound = 'true';
        const passwordInput = authRoot.querySelector('[data-auth-password]');
        const passwordToggle = authRoot.querySelector('[data-password-toggle]');
        const capsWarning = authRoot.querySelector('[data-caps-warning]');

        if (passwordInput && passwordToggle) {
            const syncPasswordToggle = () => {
                const isVisible = passwordInput.type === 'text';
                passwordToggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
                passwordToggle.innerHTML = isVisible
                    ? '<i class="bi bi-eye-slash"></i>'
                    : '<i class="bi bi-eye"></i>';
            };

            passwordToggle.addEventListener('click', () => {
                passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
                syncPasswordToggle();
                passwordInput.focus({ preventScroll: true });
            });

            syncPasswordToggle();
        }

        if (passwordInput && capsWarning) {
            const syncCapsState = (event) => {
                const isActive = typeof event?.getModifierState === 'function' && event.getModifierState('CapsLock');
                capsWarning.hidden = !isActive;
            };

            ['keydown', 'keyup'].forEach((eventName) => {
                passwordInput.addEventListener(eventName, syncCapsState);
            });

            passwordInput.addEventListener('blur', () => {
                capsWarning.hidden = true;
            });
        }
    };

    root.setAttribute('data-theme', getTheme());
    applyChartDefaults();
    showAppLoader('boot', { track: false });

    let bootLoaderSettled = false;
    const bootLoaderFailSafe = window.setTimeout(() => {
        hideAppLoader({ force: true });
        bootLoaderSettled = true;
    }, 1800);
    const settleBootLoader = (delay = 160) => {
        if (bootLoaderSettled) {
            return;
        }

        bootLoaderSettled = true;
        window.clearTimeout(bootLoaderFailSafe);
        window.setTimeout(() => hideAppLoader({ force: true }), delay);
    };

    window.NovaUI = {
        chartPalette,
        bindOverflowIndicators,
        getTheme,
        initDataTables,
        initTableChrome,
        printNode,
        setTheme,
        showConfirm,
        showToast,
        showAppLoader,
        hideAppLoader,
        openRemoteModal,
    };
    window.showToast = showToast;

    document.addEventListener('DOMContentLoaded', () => {
        initThemeButtons();
        initConfirmButtons(document);
        initBarcodes(document);
        initLoadingForms(document);
        initToasts();
        syncMobileChrome();
        initSidebarExperience();
        initOffcanvasLinks();
        initPageTransitions();
        initDataTables(document);
        initSaleLineExperience(document);
        bindModalTriggers();
        initPrintNodeTriggers();
        initAutoOpenModals();
        initAuthLogin();

        // Initialize inventory module when present (keeps app.js single-entry routing)
        if (window.Inventory && (document.querySelector('.inventory-adjustment-form') || document.body.dataset.page === 'inventory' || document.getElementById('adjustModal'))) {
            try { Inventory.init(); } catch (e) { console.error('Inventory.init error', e); }
        }

        // Categories module: moved from inline view script
        function initCategories() {
            try {
                const searchInput = document.getElementById('category-search');
                const clearBtn = document.getElementById('clear-search');
                const parentFilter = document.getElementById('parent-filter');
                const createTriggers = Array.from(document.querySelectorAll('#open-new-category-modal, [data-open-category-modal]'));
                const table = document.getElementById('categories-table');
                if (!table) return;
                const selectAll = document.getElementById('select-all');
                const bulkDeleteBtn = document.getElementById('bulk-delete');
                const legacyCreateForms = document.getElementById('legacy-create-forms');
                const csrf = table.dataset.csrf || '';
                const deleteUrl = table.dataset.deleteUrl || '';

                function visibleRows() {
                    return Array.from(table.querySelectorAll('tbody > tr')).filter(r => r.dataset && r.dataset.categoryId);
                }

                function applyFilter() {
                    const q = (searchInput && searchInput.value || '').trim().toLowerCase();
                    const parent = parentFilter ? parentFilter.value : '';

                    visibleRows().forEach(function (row) {
                        const parentId = row.dataset.parentId || '';
                        const text = (row.textContent || '').toLowerCase();
                        const matchesQuery = q === '' || text.indexOf(q) !== -1;
                        const matchesParent = parent === '' || parent === parentId;
                        const show = matchesQuery && matchesParent;
                        row.style.display = show ? '' : 'none';
                        if (!show) {
                            const cb = row.querySelector('.row-select');
                            if (cb) cb.checked = false;
                        }
                    });

                    updateBulkState();
                }

                function updateBulkState() {
                    const anyChecked = Array.from(table.querySelectorAll('.row-select')).some(cb => cb.checked && cb.closest('tr').style.display !== 'none');
                    bulkDeleteBtn && (bulkDeleteBtn.disabled = !anyChecked);
                    if (selectAll) selectAll.checked = Array.from(table.querySelectorAll('.row-select')).every(cb => cb.checked || cb.closest('tr').style.display === 'none');
                }

                searchInput && searchInput.addEventListener('input', applyFilter);
                clearBtn && clearBtn.addEventListener('click', function () { if (searchInput) { searchInput.value = ''; applyFilter(); searchInput.focus(); } });
                parentFilter && parentFilter.addEventListener('change', applyFilter);

                function openCreateCategoryModal(triggerEl) {
                    if (!legacyCreateForms) return;

                    const modalEl = document.getElementById('globalModal');
                    const modalBody = modalEl ? modalEl.querySelector('#globalModalBody') : null;
                    const modalTitle = modalEl ? modalEl.querySelector('#globalModalLabel') : null;
                    if (!modalEl || !modalBody || !modalTitle) return;

                    const cloned = legacyCreateForms.cloneNode(true);
                    cloned.classList.remove('d-none');
                    const content = cloned.querySelector('#new-category') || cloned;

                    modalTitle.textContent = 'New Category';
                    modalBody.innerHTML = '';
                    modalBody.appendChild(content);

                    try {
                        initConfirmButtons(modalBody);
                        initLoadingForms(modalBody);
                        initDataTables(modalBody);
                        bindModalTriggers(modalBody);
                        initAlpineTree(modalBody);
                    } catch (err) {}

                    try { modalEl._lastTrigger = triggerEl || document.activeElement; } catch (err) {}
                    try { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } catch (e) { modalEl.classList.add('show'); }
                    try {
                        const first = modalBody.querySelector('input,select,textarea');
                        if (first) first.focus({ preventScroll: true });
                    } catch (err) {}
                }

                createTriggers.forEach((trigger) => {
                    if (trigger.dataset.categoryCreateBound === 'true') {
                        return;
                    }

                    trigger.dataset.categoryCreateBound = 'true';
                    trigger.addEventListener('click', function (e) {
                        e.preventDefault();
                        openCreateCategoryModal(trigger);
                    });
                });

                table.addEventListener('change', function (e) {
                    if (e.target && e.target.classList.contains('row-select')) {
                        updateBulkState();
                    }
                    if (e.target && e.target.id === 'select-all') {
                        const checked = e.target.checked;
                        Array.from(table.querySelectorAll('.row-select')).forEach(cb => { if (cb.closest('tr').style.display !== 'none') cb.checked = checked; });
                        updateBulkState();
                    }
                });

                // Edit -> open inside global modal (delegated to survive DataTables DOM updates)
                table.addEventListener('click', function (e) {
                    const btn = e.target.closest('.edit-row');
                    if (!btn) return;
                    e.preventDefault();

                    const tr = btn.closest('tr');
                    if (!tr) return;

                    // locate the parent data row. DataTables may move action buttons into a
                    // responsive "child" row which does not carry the data-category-id attribute.
                    // Walk up to find a nearest tr with data-category-id or search previous siblings.
                    let parentRow = tr.closest('tr[data-category-id]');
                    if (!parentRow) {
                        let prev = tr.previousElementSibling;
                        while (prev) {
                            if (prev.dataset && prev.dataset.categoryId) { parentRow = prev; break; }
                            prev = prev.previousElementSibling;
                        }
                    }

                    if (!parentRow) return;

                    // find the corresponding hidden edit form by id
                    const id = parentRow.dataset.categoryId;
                    const inlineForm = document.getElementById('edit-form-' + id);
                    if (!inlineForm) return;

                    const modalEl = document.getElementById('globalModal');
                    const modalBody = modalEl ? modalEl.querySelector('#globalModalBody') : null;
                    const modalTitle = modalEl ? modalEl.querySelector('#globalModalLabel') : null;

                    const cloned = inlineForm.cloneNode(true);
                    cloned.querySelectorAll('input, textarea, select').forEach(function (el) {
                        const name = el.name;
                        const orig = inlineForm.querySelector('[name="' + name + '"]');
                        if (orig) { try { el.value = orig.value; } catch (e) {} }
                    });

                    if (modalBody && modalTitle) {
                        modalTitle.textContent = 'Edit Category';
                        modalBody.innerHTML = '';
                        modalBody.appendChild(cloned);

                        // bind app helpers to modal content
                        try {
                            initConfirmButtons(modalBody);
                            initLoadingForms(modalBody);
                            initDataTables(modalBody);
                            bindModalTriggers(modalBody);
                            initAlpineTree(modalBody);
                        } catch (err) {}

                        // focus first input for accessibility
                        try { const first = cloned.querySelector('input,select,textarea'); if (first) first.focus({ preventScroll: true }); } catch (e) {}

                        try { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } catch (e) { modalEl.classList.add('show'); }

                        cloned.querySelectorAll('.cancel-edit').forEach(function (btn) {
                            btn.addEventListener('click', function () { try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch (e) { modalEl.classList.remove('show'); } });
                        });
                    }
                });

                // cancel-edit handlers are bound to cloned forms when shown in modal

                bulkDeleteBtn && bulkDeleteBtn.addEventListener('click', async function () {
                    const ids = Array.from(table.querySelectorAll('.row-select')).filter(cb => cb.checked && cb.closest('tr').style.display !== 'none').map(cb => cb.value);
                    if (ids.length === 0) return;

                    const confirmResult = await showConfirm({
                        title: 'Delete selected categories?',
                        text: 'Delete ' + ids.length + ' selected categories? This cannot be undone.',
                        icon: 'warning',
                        confirmButtonText: 'Delete',
                    });

                    if (!confirmResult.isConfirmed) return;

                    try {
                        showAppLoader('submit');
                        let deletedCount = 0;
                        let failedCount = 0;
                        let failureMessage = '';
                        for (const id of ids) {
                            const formData = new URLSearchParams();
                            formData.append('_token', csrf);
                            formData.append('id', id);

                            const response = await fetch(deleteUrl || '/', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                },
                                body: formData.toString(),
                                credentials: 'same-origin'
                            });

                            const payload = await response.json().catch(() => null);
                            if (response.ok && payload && payload.success) {
                                deletedCount++;
                            } else {
                                failedCount++;
                                failureMessage = failureMessage || payload?.message || 'One or more selected categories could not be deleted.';
                            }
                        }

                        if (deletedCount > 0 && failedCount === 0) {
                            showToast('Deleted ' + deletedCount + ' categories.', 'success');
                            await refreshPageRegions('[data-refresh-region="product-category-manager"]');
                        } else if (deletedCount > 0) {
                            showToast('Deleted ' + deletedCount + ' categories. ' + failedCount + ' were skipped.', 'warning');
                            await refreshPageRegions('[data-refresh-region="product-category-manager"]');
                        } else {
                            showToast(failureMessage || 'No categories were deleted.', 'danger');
                        }
                    } catch (err) {
                        showToast('Bulk delete failed. Check console for details.', 'danger');
                        console.error(err);
                    } finally {
                        hideAppLoader();
                    }
                });

                // initialise
                applyFilter();

                initTableChrome(document);
            } catch (err) { console.error('initCategories error', err); }
        }

        window.Categories = { init: initCategories };
        if (window.Categories && document.getElementById('categories-table')) {
            try { window.Categories.init(); } catch (e) { console.error('Categories.init error', e); }
        }

        // Products module: product table UX & behaviors
        function initProducts() {
            try {
                const table = document.getElementById('products-table');
                if (!table) return;

                // Initialize DataTables (if available) via central init
                try { initDataTables(table); } catch (err) {}

                initTableChrome(table.closest('.table-shell') || table);
                // Bulk archive controls
                try {
                    const selectAll = document.getElementById('select-all');
                    const bulkBtn = document.getElementById('bulk-archive');
                    const csrf = table.dataset.csrf || '';
                    const bulkArchiveUrl = table.dataset.bulkArchiveUrl || '/products/bulk-archive';

                    function updateBulkState() {
                        const any = document.querySelectorAll('#products-table .row-select:checked').length > 0;
                        if (bulkBtn) bulkBtn.disabled = !any;
                    }

                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            const checked = !!this.checked;
                            document.querySelectorAll('#products-table .row-select').forEach(cb => cb.checked = checked);
                            updateBulkState();
                        });
                    }

                    table.addEventListener('change', function (ev) {
                        if (ev.target && ev.target.matches && ev.target.matches('.row-select')) {
                            updateBulkState();
                        }
                    });

                    if (bulkBtn) {
                        bulkBtn.addEventListener('click', async function () {
                            const ids = Array.from(document.querySelectorAll('#products-table .row-select:checked')).map(cb => cb.value);
                            if (!ids.length) return;

                            const result = await showConfirm({
                                title: 'Archive selected products?',
                                text: 'This will soft-archive the selected products. You can restore them from the admin panel.',
                                confirmButtonText: 'Archive',
                                danger: true,
                            });

                            if (!result.isConfirmed) return;

                            try {
                                showAppLoader('modal', { title: 'Archiving...' });
                                const payload = new URLSearchParams();
                                ids.forEach(id => payload.append('ids[]', id));
                                payload.append('_token', csrf);

                                const resp = await fetch(bulkArchiveUrl, {
                                    method: 'POST',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: payload.toString(),
                                    credentials: 'same-origin'
                                });

                                if (!resp.ok) throw new Error('Request failed');
                                const json = await resp.json();
                                if (json.success) {
                                    showToast(json.message || 'Products archived', 'success');
                                    await refreshPageRegions('[data-refresh-region="products-catalog"]');
                                } else {
                                    showToast(json.message || 'Failed to archive products', 'danger');
                                }
                            } catch (err) {
                                console.error('Bulk archive error', err);
                                showToast('Archive request failed', 'danger');
                            } finally {
                                hideAppLoader();
                            }
                        });
                    }
                } catch (err) { /* silent */ }
            } catch (err) { console.error('initProducts error', err); }
        }

        window.Products = { init: initProducts };
        if (window.Products && document.getElementById('products-table')) {
            try { window.Products.init(); } catch (e) { console.error('Products.init error', e); }
        }

        // Delegate every ajax-marked form through the shared AJAX submit path.
        document.addEventListener('submit', async function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const isAjax = form.dataset.ajax === 'true' || form.hasAttribute('data-ajax');
            if (!isAjax) {
                return;
            }

            event.preventDefault();
            if (form.dataset.ajaxSubmitting === 'true') {
                return;
            }

            form.dataset.ajaxSubmitting = 'true';

            try {
                const hostModal = form.closest('.modal');
                const shouldReload = form.dataset.reloadOnSuccess !== undefined
                    ? form.dataset.reloadOnSuccess !== 'false'
                    : !!hostModal;
                const shouldCloseModal = form.dataset.closeModalOnSuccess !== 'false';
                const result = await submitAjaxForm(form, {
                    submitter: event.submitter,
                    reloadOnSuccess: shouldReload,
                });

                if (result.ok && shouldCloseModal && hostModal) {
                    try {
                        bootstrap.Modal.getOrCreateInstance(hostModal).hide();
                    } catch (error) {
                        hostModal.classList.remove('show');
                    }
                }
            } catch (error) {
                console.error('Ajax form submit failed', error);
            } finally {
                delete form.dataset.ajaxSubmitting;
            }
        });

        // Restore focus to opener when any bootstrap modal hides (accessibility)
        try {
            document.addEventListener('hidden.bs.modal', function (ev) {
                try {
                    const modalEl = ev.target;
                    if (modalEl && modalEl._lastTrigger && typeof modalEl._lastTrigger.focus === 'function') {
                        modalEl._lastTrigger.focus({ preventScroll: true });
                    }
                    if (modalEl) modalEl._lastTrigger = null;
                } catch (err) { /* ignore */ }
            });
        } catch (err) { /* ignore if bootstrap events unavailable */ }

        settleBootLoader();
    });

    window.addEventListener('resize', syncMobileChrome, { passive: true });
    window.addEventListener('orientationchange', syncMobileChrome);
    window.addEventListener('load', () => {
        syncMobileChrome();
        settleBootLoader(80);
    });
    window.addEventListener('pageshow', () => {
        settleBootLoader(0);
        syncMobileChrome();
    });
})();
