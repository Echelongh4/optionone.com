<?php
$invoice = is_array($invoice ?? null) ? $invoice : [];
$company = is_array($company ?? null) ? $company : [];
$subscription = is_array($subscription ?? null) ? $subscription : null;
$payments = is_array($payments ?? null) ? $payments : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$paymentSubmissions = is_array($paymentSubmissions ?? null) ? $paymentSubmissions : [];
$paymentsReady = (bool) ($paymentsReady ?? false);
$billingProfile = array_merge([
    'contact_name' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
    'tax_number' => '',
    'notes' => '',
], $billingProfile ?? []);
$platformSettings = array_merge([
    'sender_name' => config('app.name', 'NovaPOS'),
    'sender_email' => config('mail.from_address', ''),
    'support_email' => '',
    'payment_instructions' => '',
    'invoice_footer' => '',
], $platformSettings ?? []);
$backPath = (string) ($backPath ?? 'billing');
$backLabel = (string) ($backLabel ?? 'Back');
$status = (string) ($invoice['status'] ?? 'issued');
$statusTone = match ($status) {
    'active', 'paid' => 'success',
    'trialing', 'issued' => 'warning',
    'past_due', 'overdue' => 'danger',
    'suspended', 'cancelled', 'void' => 'secondary',
    default => 'secondary',
};
$formatDate = static function (?string $value, string $fallback = 'Not set'): string {
    $value = trim((string) $value);
    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};
$defaultCurrency = default_currency_code();
$currency = normalize_billing_currency((string) ($invoice['currency'] ?? ($subscription['currency'] ?? $defaultCurrency)), $defaultCurrency);
$isPlatformView = (bool) ($isPlatformView ?? false);
$invoiceAcceptingPayments = in_array($status, ['issued', 'overdue'], true) && (float) ($invoice['balance_due'] ?? 0) > 0;
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Invoice Workspace</p>
        <h2 class="dashboard-hero__title"><?= e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></h2>
        <p class="dashboard-hero__copy"><?= e((string) ($invoice['description'] ?? 'Billing invoice')) ?></p>
        <div class="dashboard-hero__meta">
            <span class="status-pill status-pill--<?= e($statusTone) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
            <span class="badge-soft"><i class="bi bi-cash-stack me-1"></i><?= e(format_money((float) ($invoice['total'] ?? 0), $currency)) ?></span>
            <span class="badge-soft"><i class="bi bi-calendar-event me-1"></i><?= e($formatDate((string) ($invoice['due_at'] ?? ''), 'No due date')) ?></span>
        </div>
    </div>
    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Total</span>
            <strong class="dashboard-hero-stat__value"><?= e(format_money((float) ($invoice['total'] ?? 0), $currency)) ?></strong>
            <span class="dashboard-hero-stat__meta">Invoice value</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Paid</span>
            <strong class="dashboard-hero-stat__value"><?= e(format_money((float) ($invoice['amount_paid'] ?? 0), $currency)) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($formatDate((string) ($invoice['paid_at'] ?? ''), 'Awaiting payment')) ?></span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Balance</span>
            <strong class="dashboard-hero-stat__value"><?= e(format_money((float) ($invoice['balance_due'] ?? 0), $currency)) ?></strong>
            <span class="dashboard-hero-stat__meta">Outstanding</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Plan</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Unassigned')) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e(ucfirst((string) ($subscription['billing_cycle'] ?? 'custom'))) ?> billing</span>
        </article>
    </div>
</section>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Invoice Actions</p>
        <h3 class="mb-1">Review charges, payment history, and settlement instructions.</h3>
        <p class="text-muted mb-0">Use the browser print action if you need a hard copy or PDF export.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <a href="<?= e(url($backPath)) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e($backLabel) ?></a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Invoice</button>
    </div>
</section>

<div class="content-grid">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Invoice Summary</p>
                <h4>Charges and period coverage</h4>
            </div>
        </div>
        <div class="stack-grid">
            <article class="record-card">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Company</div>
                        <div class="fw-semibold"><?= e((string) ($company['name'] ?? 'Company')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Invoice number</div>
                        <div class="fw-semibold"><?= e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Subtotal</div>
                        <div class="fw-semibold"><?= e(format_money((float) ($invoice['subtotal'] ?? 0), $currency)) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Tax</div>
                        <div class="fw-semibold"><?= e(format_money((float) ($invoice['tax_total'] ?? 0), $currency)) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Total</div>
                        <div class="fw-semibold"><?= e(format_money((float) ($invoice['total'] ?? 0), $currency)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Issued</div>
                        <div class="fw-semibold"><?= e($formatDate((string) ($invoice['issued_at'] ?? ''), 'Pending')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Due</div>
                        <div class="fw-semibold"><?= e($formatDate((string) ($invoice['due_at'] ?? ''), 'Not scheduled')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Period start</div>
                        <div class="fw-semibold"><?= e($formatDate((string) ($invoice['period_start'] ?? ''), 'Not set')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Period end</div>
                        <div class="fw-semibold"><?= e($formatDate((string) ($invoice['period_end'] ?? ''), 'Not set')) ?></div>
                    </div>
                </div>
            </article>

            <?php if (trim((string) ($invoice['notes'] ?? '')) !== '' || trim((string) $platformSettings['invoice_footer']) !== ''): ?>
                <article class="record-card">
                    <div class="workspace-panel__intro mb-2">
                        <h4>Notes</h4>
                    </div>
                    <?php if (trim((string) ($invoice['notes'] ?? '')) !== ''): ?>
                        <div class="small text-muted mb-3"><?= nl2br(e((string) $invoice['notes'])) ?></div>
                    <?php endif; ?>
                    <?php if (trim((string) $platformSettings['invoice_footer']) !== ''): ?>
                        <div class="small text-muted"><?= nl2br(e((string) $platformSettings['invoice_footer'])) ?></div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Billing Contacts</p>
                <h4>Who this invoice is addressed to</h4>
            </div>
        </div>
        <div class="stack-grid">
            <article class="record-card">
                <div class="small text-muted">Billing contact</div>
                <div class="fw-semibold"><?= e((string) $billingProfile['contact_name']) ?></div>
                <div class="small text-muted"><?= e((string) $billingProfile['contact_email']) ?></div>
                <div class="small text-muted"><?= e((string) $billingProfile['contact_phone']) ?></div>
                <?php if (trim((string) $billingProfile['address']) !== ''): ?>
                    <div class="small text-muted mt-2"><?= nl2br(e((string) $billingProfile['address'])) ?></div>
                <?php endif; ?>
                <?php if (trim((string) $billingProfile['tax_number']) !== ''): ?>
                    <div class="small text-muted mt-2">Tax number: <?= e((string) $billingProfile['tax_number']) ?></div>
                <?php endif; ?>
            </article>
            <article class="record-card">
                <div class="small text-muted">Issued by</div>
                <div class="fw-semibold"><?= e((string) $platformSettings['sender_name']) ?></div>
                <div class="small text-muted"><?= e((string) $platformSettings['sender_email']) ?></div>
                <div class="small text-muted"><?= e(trim((string) $platformSettings['support_email']) !== '' ? (string) $platformSettings['support_email'] : 'Support contact not published') ?></div>
            </article>
            <article class="record-card">
                <div class="workspace-panel__intro mb-2">
                    <h4>Payment instructions</h4>
                </div>
                <div class="small text-muted"><?= trim((string) $platformSettings['payment_instructions']) !== '' ? nl2br(e((string) $platformSettings['payment_instructions'])) : 'Payment instructions have not been configured yet.' ?></div>
            </article>
            <?php foreach ($paymentMethods as $method): ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4><?= e((string) ($method['name'] ?? 'Payment method')) ?></h4>
                            <div class="inline-note"><?= e(ucfirst(str_replace('_', ' ', (string) ($method['type'] ?? 'other')))) ?></div>
                        </div>
                        <?php if (trim((string) ($method['checkout_url'] ?? '')) !== ''): ?>
                            <div class="record-card__meta">
                                <a href="<?= e((string) $method['checkout_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Open Checkout</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (trim((string) ($method['description'] ?? '')) !== ''): ?>
                        <div class="small text-muted mb-2"><?= e((string) $method['description']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge-soft"><?= e($currency) ?> invoice</span>
                        <span class="badge-soft"><?= !empty($method['requires_reference']) ? 'Reference required' : 'Reference optional' ?></span>
                        <span class="badge-soft"><?= !empty($method['requires_proof']) ? 'Proof required' : 'Proof optional' ?></span>
                    </div>
                    <?php if (trim((string) ($method['provider_name'] ?? '')) !== '' || trim((string) ($method['account_name'] ?? '')) !== '' || trim((string) ($method['account_number'] ?? '')) !== ''): ?>
                        <div class="small text-muted">
                            <?= e(trim(implode(' | ', array_values(array_filter([
                                (string) ($method['provider_name'] ?? ''),
                                (string) ($method['account_name'] ?? ''),
                                (string) ($method['account_number'] ?? ''),
                            ]))))) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($method['instructions'] ?? '')) !== ''): ?>
                        <div class="small text-muted mt-2"><?= nl2br(e((string) $method['instructions'])) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php if (!$isPlatformView && $paymentsReady && $invoiceAcceptingPayments && $paymentMethods !== []): ?>
    <section class="surface-card card-panel workspace-panel mb-4">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Submit Payment</p>
                <h3><i class="bi bi-cloud-upload me-2"></i>Send payment for review</h3>
            </div>
        </div>
        <form action="<?= e(url('billing/invoices/payments/submit')) ?>" method="post" enctype="multipart/form-data" class="stack-grid" data-loading-form id="billing-payment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
            <input type="hidden" name="return_to" value="<?= e('billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0)) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="billing_payment_method_id">Payment method</label>
                    <select class="form-select" id="billing_payment_method_id" name="billing_payment_method_id" required>
                        <?php foreach ($paymentMethods as $method): ?>
                            <?php
                            $accountSummary = trim(implode(' | ', array_values(array_filter([
                                (string) ($method['provider_name'] ?? ''),
                                (string) ($method['account_name'] ?? ''),
                                (string) ($method['account_number'] ?? ''),
                            ]))));
                            ?>
                            <option
                                value="<?= e((string) ($method['id'] ?? 0)) ?>"
                                data-type-label="<?= e(ucfirst(str_replace('_', ' ', (string) ($method['type'] ?? 'other')))) ?>"
                                data-description="<?= e(rawurlencode((string) ($method['description'] ?? ''))) ?>"
                                data-instructions="<?= e(rawurlencode((string) ($method['instructions'] ?? ''))) ?>"
                                data-account-summary="<?= e(rawurlencode($accountSummary)) ?>"
                                data-checkout-url="<?= e((string) ($method['checkout_url'] ?? '')) ?>"
                                data-requires-reference="<?= !empty($method['requires_reference']) ? '1' : '0' ?>"
                                data-requires-proof="<?= !empty($method['requires_proof']) ? '1' : '0' ?>"
                            ><?= e((string) ($method['name'] ?? 'Payment method')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="small text-muted mt-1">This invoice is payable in <?= e($currency) ?>.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="payment_amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" max="<?= e((string) ($invoice['balance_due'] ?? 0)) ?>" class="form-control" id="payment_amount" name="amount" value="<?= e((string) ($invoice['balance_due'] ?? 0)) ?>" required>
                </div>
                <div class="col-12">
                    <article class="record-card" id="payment-method-summary" aria-live="polite">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge-soft" id="payment-method-type">Method details</span>
                            <span class="badge-soft d-none" id="payment-method-reference-badge">Reference required</span>
                            <span class="badge-soft d-none" id="payment-method-proof-badge">Proof required</span>
                        </div>
                        <div class="small text-muted" id="payment-method-description">Choose a payment method to view provider details, instructions, and review requirements.</div>
                        <div class="small text-muted mt-2 d-none" id="payment-method-account"></div>
                        <div class="small text-muted mt-2 d-none" id="payment-method-instructions"></div>
                        <div class="mt-3 d-none" id="payment-method-checkout-wrap">
                            <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" id="payment-method-checkout-link">Open Checkout</a>
                        </div>
                    </article>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="payer_name">Payer name</label>
                    <input type="text" class="form-control" id="payer_name" name="payer_name" value="<?= e((string) ($billingProfile['contact_name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="payer_email">Payer email</label>
                    <input type="email" class="form-control" id="payer_email" name="payer_email" value="<?= e((string) ($billingProfile['contact_email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer_reference">Customer reference</label>
                    <input type="text" class="form-control" id="customer_reference" name="customer_reference" placeholder="Transfer, receipt, or teller reference">
                    <div class="small text-muted mt-1" id="payment-reference-hint">Provide either a customer or gateway reference when the chosen method requires it.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="gateway_reference">Gateway reference</label>
                    <input type="text" class="form-control" id="gateway_reference" name="gateway_reference" placeholder="Hosted checkout or provider reference">
                </div>
                <div class="col-12">
                    <label class="form-label" for="proof">Proof image</label>
                    <input type="file" class="form-control" id="proof" name="proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="small text-muted mt-1" id="payment-proof-hint">Upload JPG, PNG, or WebP proof when your payment method requires confirmation.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="payment_note">Note</label>
                    <textarea class="form-control" id="payment_note" name="note" rows="4" placeholder="Anything the platform reviewer should know about this payment"></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send-check me-1"></i>Submit Payment</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($paymentSubmissions !== []): ?>
    <section class="surface-card card-panel table-shell mb-4">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Payment Submissions</p>
                <h3><i class="bi bi-hourglass-split me-2"></i>Submitted reviews</h3>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Reviewer</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paymentSubmissions as $submission): ?>
                    <?php $submissionStatus = (string) ($submission['status'] ?? 'submitted'); ?>
                    <tr>
                        <td><?= e($formatDate((string) ($submission['submitted_at'] ?? ''), 'Pending')) ?></td>
                        <td><span class="status-pill status-pill--<?= e(match ($submissionStatus) {
                            'approved' => 'success',
                            'submitted' => 'warning',
                            'rejected' => 'danger',
                            default => 'secondary',
                        }) ?>"><?= e(ucfirst($submissionStatus)) ?></span></td>
                        <td><?= e((string) ($submission['payment_method_name'] ?? 'Payment method')) ?></td>
                        <td><?= e(format_money((float) ($submission['amount'] ?? 0), (string) ($submission['currency'] ?? $currency))) ?></td>
                        <td><?= e(trim(implode(' | ', array_values(array_filter([(string) ($submission['customer_reference'] ?? ''), (string) ($submission['gateway_reference'] ?? '')])))) ?: 'Not provided') ?></td>
                        <td>
                            <div><?= e(trim((string) ($submission['reviewed_by_name'] ?? '')) !== '' ? (string) $submission['reviewed_by_name'] : 'Pending review') ?></div>
                            <?php if (trim((string) ($submission['review_note'] ?? '')) !== ''): ?>
                                <div class="small text-muted"><?= e((string) $submission['review_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                <?php if (trim((string) ($submission['proof_path'] ?? '')) !== ''): ?>
                                    <a href="<?= e(url((string) $submission['proof_path'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View Proof</a>
                                <?php endif; ?>
                                <?php if ($isPlatformView && $submissionStatus === 'submitted'): ?>
                                    <form action="<?= e(url('platform/billing/payments/submissions/approve')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submission_id" value="<?= e((string) ($submission['id'] ?? 0)) ?>">
                                        <input type="hidden" name="return_to" value="<?= e('platform/billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0)) ?>">
                                        <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Approval note (optional)">
                                        <button type="submit" class="btn btn-sm btn-primary">Approve and Post</button>
                                    </form>
                                    <form action="<?= e(url('platform/billing/payments/submissions/reject')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submission_id" value="<?= e((string) ($submission['id'] ?? 0)) ?>">
                                        <input type="hidden" name="return_to" value="<?= e('platform/billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0)) ?>">
                                        <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Reason for rejection" required>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Payment History</p>
            <h3><i class="bi bi-cash-coin me-2"></i>Recorded collections</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Paid At</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Recorded By</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($payments === []): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No payment records have been captured for this invoice yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= e($formatDate((string) ($payment['paid_at'] ?? ''), 'Pending')) ?></td>
                        <td><?= e(trim((string) ($payment['payment_method_name'] ?? '')) !== '' ? (string) $payment['payment_method_name'] : ucfirst(str_replace(['_', '-'], ' ', (string) ($payment['payment_method'] ?? 'other')))) ?></td>
                        <td><?= e(format_money((float) ($payment['amount'] ?? 0), $currency)) ?></td>
                        <td><?= e((string) ($payment['reference'] ?? '')) ?></td>
                        <td><?= e(trim((string) ($payment['recorded_by_name'] ?? '')) !== '' ? (string) $payment['recorded_by_name'] : 'System') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!$isPlatformView && $paymentsReady && $invoiceAcceptingPayments && $paymentMethods !== []): ?>
    <script>
        (() => {
            const form = document.getElementById('billing-payment-form');
            const methodSelect = document.getElementById('billing_payment_method_id');
            if (!form || !methodSelect) {
                return;
            }

            const customerReference = document.getElementById('customer_reference');
            const gatewayReference = document.getElementById('gateway_reference');
            const proofInput = document.getElementById('proof');
            const elements = {
                type: document.getElementById('payment-method-type'),
                description: document.getElementById('payment-method-description'),
                account: document.getElementById('payment-method-account'),
                instructions: document.getElementById('payment-method-instructions'),
                checkoutWrap: document.getElementById('payment-method-checkout-wrap'),
                checkoutLink: document.getElementById('payment-method-checkout-link'),
                referenceBadge: document.getElementById('payment-method-reference-badge'),
                proofBadge: document.getElementById('payment-method-proof-badge'),
                referenceHint: document.getElementById('payment-reference-hint'),
                proofHint: document.getElementById('payment-proof-hint'),
            };

            const decode = (value) => {
                try {
                    return decodeURIComponent(value || '');
                } catch (error) {
                    return value || '';
                }
            };

            const updateVisibility = (element, shouldShow, value = '') => {
                if (!element) {
                    return;
                }

                if ('textContent' in element) {
                    element.textContent = value;
                }

                element.classList.toggle('d-none', !shouldShow);
            };

            const selectedOption = () => methodSelect.options[methodSelect.selectedIndex] || null;

            const syncMethodSummary = () => {
                const option = selectedOption();
                if (!option) {
                    return;
                }

                const typeLabel = option.dataset.typeLabel || 'Payment method';
                const description = decode(option.dataset.description);
                const instructions = decode(option.dataset.instructions);
                const accountSummary = decode(option.dataset.accountSummary);
                const checkoutUrl = option.dataset.checkoutUrl || '';
                const requiresReference = option.dataset.requiresReference === '1';
                const requiresProof = option.dataset.requiresProof === '1';

                if (elements.type) {
                    elements.type.textContent = typeLabel;
                }

                if (elements.description) {
                    elements.description.textContent = description !== ''
                        ? description
                        : 'Follow the method details below before submitting this payment for review.';
                }

                updateVisibility(elements.account, accountSummary !== '', accountSummary);
                updateVisibility(elements.instructions, instructions !== '', instructions);
                updateVisibility(elements.referenceBadge, requiresReference, 'Reference required');
                updateVisibility(elements.proofBadge, requiresProof, 'Proof required');

                if (elements.checkoutWrap && elements.checkoutLink) {
                    elements.checkoutWrap.classList.toggle('d-none', checkoutUrl === '');
                    elements.checkoutLink.setAttribute('href', checkoutUrl !== '' ? checkoutUrl : '#');
                }

                if (proofInput) {
                    proofInput.required = requiresProof;
                }

                if (elements.referenceHint) {
                    elements.referenceHint.textContent = requiresReference
                        ? 'Enter either a customer reference or a gateway reference before submitting.'
                        : 'Reference is optional for this method, but keeping one helps with reconciliation.';
                }

                if (elements.proofHint) {
                    elements.proofHint.textContent = requiresProof
                        ? 'This payment method requires a JPG, PNG, or WebP proof image before submission.'
                        : 'Proof is optional for this method, but you can still upload it for faster review.';
                }

                if (customerReference) {
                    customerReference.setCustomValidity('');
                }
                if (gatewayReference) {
                    gatewayReference.setCustomValidity('');
                }
                if (proofInput) {
                    proofInput.setCustomValidity('');
                }
            };

            const validateRequirements = () => {
                const option = selectedOption();
                if (!option) {
                    return true;
                }

                const requiresReference = option.dataset.requiresReference === '1';
                const requiresProof = option.dataset.requiresProof === '1';
                const hasReference = (customerReference?.value || '').trim() !== '' || (gatewayReference?.value || '').trim() !== '';
                const hasProof = !!(proofInput?.files && proofInput.files.length > 0);

                if (customerReference) {
                    customerReference.setCustomValidity(requiresReference && !hasReference ? 'Enter either a customer reference or a gateway reference for this payment method.' : '');
                }
                if (gatewayReference) {
                    gatewayReference.setCustomValidity('');
                }
                if (proofInput) {
                    proofInput.setCustomValidity(requiresProof && !hasProof ? 'Upload proof of payment for this method before submitting.' : '');
                }

                return (!requiresReference || hasReference) && (!requiresProof || hasProof);
            };

            methodSelect.addEventListener('change', syncMethodSummary);
            customerReference?.addEventListener('input', validateRequirements);
            gatewayReference?.addEventListener('input', validateRequirements);
            proofInput?.addEventListener('change', validateRequirements);

            form.addEventListener('submit', (event) => {
                if (validateRequirements()) {
                    return;
                }

                event.preventDefault();
                customerReference?.reportValidity();
                proofInput?.reportValidity();
            });

            syncMethodSummary();
        })();
    </script>
<?php endif; ?>
