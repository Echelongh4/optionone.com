(function () {
    function q(selector, ctx = document) { return ctx.querySelector(selector); }

    function decodeParam(key) {
        try {
            const params = new URLSearchParams(window.location.search);
            return params.get(key) || '';
        } catch (e) {
            return '';
        }
    }

    function initInventory() {
        const adjustModalEl = document.getElementById('adjustModal');
        if (!adjustModalEl) return;

        const confirmModalEl = document.getElementById('confirmAdjustModal');
        const localForm = q('form[data-loading-form]', adjustModalEl);
        const largeThreshold = parseFloat((adjustModalEl.dataset && adjustModalEl.dataset.largeThreshold) || '1000');

        function adoptModalToBody(modalEl) {
            if (!modalEl) return null;

            try {
                const existing = document.body.querySelector(`#${modalEl.id}`);
                if (existing && existing !== modalEl) {
                    existing.remove();
                }

                if (modalEl.parentNode !== document.body) {
                    document.body.appendChild(modalEl);
                }
            } catch (e) {
                // ignore DOM move failures
            }

            return modalEl;
        }

        adoptModalToBody(adjustModalEl);
        adoptModalToBody(confirmModalEl);

        let bsModal = null;
        try {
            bsModal = new bootstrap.Modal(adjustModalEl, { backdrop: 'static' });
        } catch (e) {
            // bootstrap unavailable
        }

        if (document.body.dataset.inventoryModalEventsBound !== 'true') {
            try {
                document.addEventListener('shown.bs.modal', function (ev) {
                    trapFocus(ev.target);
                });
                document.addEventListener('hidden.bs.modal', function () {
                    releaseFocus();
                });
                document.body.dataset.inventoryModalEventsBound = 'true';
            } catch (e) {
                // ignore bootstrap event binding failures
            }
        }

        function showFallback(modalEl) {
            if (!modalEl) return;
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modal-backdrop-fallback';
            document.body.appendChild(backdrop);
            trapFocus(modalEl);
        }

        function hideFallback(modalEl) {
            if (!modalEl) return;
            modalEl.classList.remove('show');
            modalEl.style.display = '';
            const backdrop = document.getElementById('modal-backdrop-fallback');
            if (backdrop) backdrop.remove();
            releaseFocus();
        }

        let focusTrapHandler = null;
        function trapFocus(modalEl) {
            try {
                const focusable = Array.from(modalEl.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'))
                    .filter(el => el.offsetWidth || el.offsetHeight || el.getClientRects().length);
                if (!focusable.length) return;

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                first.focus();

                focusTrapHandler = function (e) {
                    if (e.key !== 'Tab') return;

                    if (e.shiftKey) {
                        if (document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                };

                document.addEventListener('keydown', focusTrapHandler);
            } catch (err) {
                // ignore focus errors
            }
        }

        function releaseFocus() {
            if (!focusTrapHandler) return;
            document.removeEventListener('keydown', focusTrapHandler);
            focusTrapHandler = null;
        }

        function captureFormState(formEl) {
            return {
                productId: formEl.querySelector('select[name="product_id"]')?.value || '',
                direction: formEl.querySelector('select[name="direction"]')?.value || 'increase',
                quantity: formEl.querySelector('input[name="quantity"]')?.value || '',
                unitCost: formEl.querySelector('input[name="unit_cost"]')?.value || '',
                reason: formEl.querySelector('#reason-field')?.value || '',
                reasonCode: formEl.querySelector('#reason-code-field')?.value || '',
                confirmLarge: formEl.querySelector('#confirm-large-field')?.value || '',
            };
        }

        function applyFormState(formEl, state) {
            const productEl = formEl.querySelector('select[name="product_id"]');
            const directionEl = formEl.querySelector('select[name="direction"]');
            const quantityEl = formEl.querySelector('input[name="quantity"]');
            const unitCostEl = formEl.querySelector('input[name="unit_cost"]');
            const reasonEl = formEl.querySelector('#reason-field');
            const reasonCodeEl = formEl.querySelector('#reason-code-field');
            const presetEl = formEl.querySelector('#preset-reason');
            const confirmLargeEl = formEl.querySelector('#confirm-large-field');

            if (productEl) productEl.value = state.productId || '';
            if (directionEl) directionEl.value = state.direction || 'increase';
            if (quantityEl) quantityEl.value = state.quantity || '';
            if (unitCostEl) unitCostEl.value = state.unitCost || '';
            if (reasonEl) reasonEl.value = state.reason || '';
            if (reasonCodeEl) reasonCodeEl.value = state.reasonCode || '';
            if (presetEl) presetEl.value = state.reasonCode || '';
            if (confirmLargeEl) confirmLargeEl.value = state.confirmLarge || '';
        }

        function applyPrefill(formEl, prefill) {
            if (!prefill) return;

            const productEl = formEl.querySelector('select[name="product_id"]');
            const directionEl = formEl.querySelector('select[name="direction"]');
            const unitCostEl = formEl.querySelector('input[name="unit_cost"]');
            const reasonEl = formEl.querySelector('#reason-field');
            const reasonCodeEl = formEl.querySelector('#reason-code-field');
            const presetEl = formEl.querySelector('#preset-reason');
            const confirmLargeEl = formEl.querySelector('#confirm-large-field');

            if (productEl && prefill.productId) productEl.value = prefill.productId;
            if (directionEl && prefill.direction) directionEl.value = prefill.direction;
            if (unitCostEl && prefill.unitCost !== undefined && prefill.unitCost !== '') unitCostEl.value = prefill.unitCost;
            if (reasonEl && prefill.reason) reasonEl.value = prefill.reason;
            if (reasonCodeEl && prefill.reasonCode) reasonCodeEl.value = prefill.reasonCode;
            if (presetEl && prefill.reasonCode) presetEl.value = prefill.reasonCode;
            if (reasonEl && !reasonEl.value && presetEl && presetEl.value) reasonEl.value = presetEl.value;
            if (confirmLargeEl) confirmLargeEl.value = prefill.confirmLarge || '';
        }

        function resetCloneBindingState(formEl) {
            if (!formEl) return;

            formEl.removeAttribute('data-inventory-confirm-bound');
            formEl.querySelectorAll('[data-inventory-preset-bound]').forEach(function (el) {
                el.removeAttribute('data-inventory-preset-bound');
            });
        }

        function bindPresetSync(formEl) {
            const presetEl = formEl.querySelector('#preset-reason');
            const reasonEl = formEl.querySelector('#reason-field');
            const reasonCodeEl = formEl.querySelector('#reason-code-field');

            if (!presetEl || !reasonEl || presetEl.dataset.inventoryPresetBound === 'true') return;
            presetEl.dataset.inventoryPresetBound = 'true';

            presetEl.addEventListener('change', function () {
                if (!this.value) {
                    if (reasonCodeEl) reasonCodeEl.value = '';
                    return;
                }

                reasonEl.value = this.value;
                if (reasonCodeEl) reasonCodeEl.value = this.value;
            });
        }

        function bindLargeAdjustmentConfirm(formEl) {
            if (!formEl || formEl.dataset.inventoryConfirmBound === 'true') return;
            formEl.dataset.inventoryConfirmBound = 'true';

            formEl.addEventListener('submit', function (ev) {
                try {
                    const qty = parseFloat(formEl.querySelector('input[name="quantity"]')?.value || '0');
                    const direction = formEl.querySelector('select[name="direction"]')?.value || 'increase';
                    const confirmLargeEl = formEl.querySelector('#confirm-large-field');

                    if (direction !== 'increase' || qty <= largeThreshold || !confirmLargeEl || confirmLargeEl.value) {
                        return;
                    }

                    ev.preventDefault();

                    const confirmEl = document.getElementById('confirmAdjustModal');
                    const confirmMsg = confirmEl ? q('#confirmAdjustMessage', confirmEl) : null;
                    const confirmProceed = document.getElementById('confirmAdjustProceed');
                    if (confirmMsg) confirmMsg.textContent = 'This will add ' + qty + ' units. Are you sure you want to proceed?';

                    let bsConfirm = null;
                    try { bsConfirm = new bootstrap.Modal(confirmEl, { backdrop: 'static' }); } catch (e) { bsConfirm = null; }

                    function onProceed() {
                        confirmLargeEl.value = '1';
                        if (confirmProceed) confirmProceed.removeEventListener('click', onProceed);
                        if (bsConfirm) bsConfirm.hide();
                        else hideFallback(confirmEl);
                        formEl.submit();
                    }

                    if (bsConfirm) {
                        confirmProceed.addEventListener('click', onProceed);
                        bsConfirm.show();
                    } else {
                        showFallback(confirmEl);
                        if (confirmProceed) confirmProceed.addEventListener('click', onProceed);
                    }
                } catch (e) {
                    // allow submit
                }
            });
        }

        function bindAdjustmentForm(formEl) {
            bindPresetSync(formEl);
            bindLargeAdjustmentConfirm(formEl);
        }

        function triggerPrefill(triggerEl) {
            if (!triggerEl) return {};
            return {
                productId: triggerEl.dataset.productId || '',
                direction: triggerEl.dataset.direction || '',
                unitCost: triggerEl.dataset.unitCost || '',
                reason: triggerEl.dataset.reason || '',
                reasonCode: triggerEl.dataset.reasonCode || '',
                confirmLarge: '',
            };
        }

        function queryPrefill() {
            return {
                productId: decodeParam('product_id') || decodeParam('product'),
                direction: decodeParam('prefill_direction'),
                unitCost: decodeParam('prefill_unit_cost'),
                reason: decodeParam('prefill_reason'),
                reasonCode: decodeParam('prefill_reason_code'),
                confirmLarge: '',
            };
        }

        const defaultFormState = localForm ? captureFormState(localForm) : null;

        function prepareForm(formEl, prefill) {
            if (!formEl || !defaultFormState) return;
            applyFormState(formEl, defaultFormState);
            applyPrefill(formEl, prefill);
            bindAdjustmentForm(formEl);
        }

        function openAdjustInGlobalModal(triggerEl, prefill = {}) {
            const globalModal = document.getElementById('globalModal');
            if (!globalModal || !localForm) return;

            const modalBody = globalModal.querySelector('#globalModalBody');
            const modalTitle = globalModal.querySelector('#globalModalLabel');
            const modalInstance = bootstrap.Modal.getOrCreateInstance(globalModal);
            const cloned = localForm.cloneNode(true);

            modalBody.innerHTML = '';
            modalBody.appendChild(cloned);
            modalTitle.textContent = 'Adjust Inventory';

            resetCloneBindingState(cloned);
            prepareForm(cloned, prefill);

            try { globalModal._lastTrigger = triggerEl || document.activeElement; } catch (err) {}
            modalInstance.show();
        }

        function openAdjustLocally(triggerEl, prefill = {}) {
            if (!localForm) return;
            prepareForm(localForm, prefill);
            try { adjustModalEl._lastTrigger = triggerEl || document.activeElement; } catch (e) {}

            if (bsModal) bsModal.show();
            else showFallback(adjustModalEl);
        }

        function openAdjustment(triggerEl, prefill = {}) {
            const globalModal = document.getElementById('globalModal');
            if (globalModal) {
                openAdjustInGlobalModal(triggerEl, prefill);
            } else {
                openAdjustLocally(triggerEl, prefill);
            }
        }

        if (!bsModal) {
            Array.from(adjustModalEl.querySelectorAll('[data-bs-dismiss="modal"]')).forEach(function (btn) {
                if (btn.dataset.inventoryDismissBound === 'true') return;
                btn.dataset.inventoryDismissBound = 'true';
                btn.addEventListener('click', function () { hideFallback(adjustModalEl); });
            });
        }

        if (localForm) {
            bindAdjustmentForm(localForm);
        }

        document.querySelectorAll('[data-open-inventory-adjustment]').forEach(function (trigger) {
            if (trigger.dataset.inventoryOpenBound === 'true') return;
            trigger.dataset.inventoryOpenBound = 'true';

            trigger.addEventListener('click', function (ev) {
                ev.preventDefault();
                openAdjustment(trigger, triggerPrefill(trigger));
            });
        });

        const initialPrefill = queryPrefill();
        const hasServerErrors = adjustModalEl.dataset.hasErrors === '1';
        const openNow = hasServerErrors || decodeParam('open_adjustment') || initialPrefill.productId || initialPrefill.reason;

        if (openNow) {
            openAdjustment(null, initialPrefill);
        }
    }

    window.Inventory = { init: initInventory };
})();
