<?php
$businessName = (string) setting_value('business_name', config('app.name'));
$businessAddress = (string) setting_value('business_address', '');
$businessPhone = (string) setting_value('business_phone', '');
$receiptHeader = (string) setting_value('receipt_header', 'Thank you for your business.');
$receiptFooter = (string) setting_value('receipt_footer', 'Goods sold are subject to store policy.');
$businessLogo = (string) setting_value('business_logo_path', '');
$loyaltyDiscount = (float) ($sale['loyalty_discount_total'] ?? 0);
$redeemedPoints = (int) ($sale['loyalty_points_redeemed'] ?? 0);
$earnedPoints = (int) ($sale['loyalty_points_earned'] ?? 0);
$creditAmount = (float) ($sale['credit_amount'] ?? 0);
$cashTendered = (float) ($sale['cash_tendered'] ?? 0);
$collectedAmount = (float) ($sale['collected_amount'] ?? $sale['amount_paid']);
$thermalPrinterEnabled = filter_var(setting_value('thermal_printer_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
$embedded = (bool) ($embedded ?? false);
$isHeld = (string) ($sale['status'] ?? '') === 'held';
$receiptTitle = $isHeld ? 'Held Sale Slip' : 'Thermal Receipt Preview';
$receiptTimestamp = (string) ($sale['completed_at'] ?? '');
if ($receiptTimestamp === '') {
    $receiptTimestamp = (string) ($sale['created_at'] ?? '');
}
?>
<div class="surface-card card-panel" data-modal-receipt-shortcuts="true">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="eyebrow mb-1"><?= e($receiptTitle) ?></p>
            <h3 class="mb-0"><?= e($sale['sale_number']) ?></h3>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$embedded): ?>
                <a href="<?= e(url('pos')) ?>" class="btn btn-outline-secondary">Back to POS</a>
            <?php endif; ?>
            <?php if ($thermalPrinterEnabled): ?>
                <form
                    action="<?= e(url('pos/print')) ?>"
                    method="post"
                    class="d-inline"
                    data-loading-form
                    <?= $embedded ? 'data-ajax="true" data-reload-on-success="false" data-close-modal-on-success="false"' : '' ?>
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="sale_id" value="<?= e((string) $sale['id']) ?>">
                    <button type="submit" class="btn btn-outline-primary">Print to Thermal Printer</button>
                </form>
            <?php endif; ?>
            <?php if ($embedded): ?>
                <button type="button" class="btn btn-primary" data-modal-primary-focus="true" data-print-node="#posReceiptPaper" data-print-title="<?= e($sale['sale_number']) ?>" data-print-page-size="80mm">
                    Print Receipt
                </button>
            <?php else: ?>
                <button type="button" onclick="window.print()" class="btn btn-primary">Print Receipt</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="receipt-paper" id="posReceiptPaper">
        <div class="text-center mb-4">
            <?php if ($businessLogo !== ''): ?>
                <img src="<?= e(url($businessLogo)) ?>" alt="<?= e($businessName) ?>" class="img-fluid mb-3" style="max-height: 60px; object-fit: contain;">
            <?php endif; ?>
            <h4 class="mb-1"><?= e($businessName) ?></h4>
            <?php if ($businessAddress !== ''): ?><div><?= e($businessAddress) ?></div><?php endif; ?>
            <?php if ($businessPhone !== ''): ?><div><?= e($businessPhone) ?></div><?php endif; ?>
            <div><?= e($sale['branch_name'] ?? 'Main Branch') ?></div>
            <div><?= e($sale['sale_number']) ?></div>
            <div><?= e($receiptTimestamp) ?></div>
            <?php if ($receiptHeader !== ''): ?><div class="small mt-2"><?= e($receiptHeader) ?></div><?php endif; ?>
        </div>
        <div class="d-flex justify-content-between mb-2"><span>Cashier</span><strong><?= e($sale['cashier_name']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Customer</span><strong><?= e($sale['customer_name']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Status</span><strong><?= e(ucfirst((string) ($sale['status'] ?? 'completed'))) ?></strong></div>
        <?php if ($redeemedPoints > 0 || $earnedPoints > 0): ?>
            <div class="d-flex justify-content-between mb-2"><span>Loyalty</span><strong><?= e($redeemedPoints > 0 ? '-' . $redeemedPoints . ' pts' : '0 pts') ?><?= $earnedPoints > 0 ? ' / +' . e((string) $earnedPoints) . ' pts' : '' ?></strong></div>
        <?php endif; ?>
        <?php if ($creditAmount > 0): ?>
            <div class="d-flex justify-content-between mb-2"><span>Open Account</span><strong><?= e(format_currency($creditAmount)) ?></strong></div>
        <?php endif; ?>
        <hr>
        <?php foreach ($sale['items'] as $item): ?>
            <div class="mb-3">
                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                <div class="small d-flex justify-content-between">
                    <span><?= e((string) $item['quantity']) ?> x <?= e(format_currency($item['unit_price'])) ?></span>
                    <span><?= e(format_currency($item['line_total'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong><?= e(format_currency($sale['subtotal'])) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Item Discounts</span><strong><?= e(format_currency($sale['item_discount_total'])) ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Order Discount</span><strong><?= e(format_currency($sale['order_discount_total'])) ?></strong></div>
        <?php if ($loyaltyDiscount > 0): ?>
            <div class="d-flex justify-content-between mb-2"><span>Loyalty Discount</span><strong><?= e(format_currency($loyaltyDiscount)) ?></strong></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-2"><span>Tax</span><strong><?= e(format_currency($sale['tax_total'])) ?></strong></div>
        <div class="d-flex justify-content-between fs-5 mb-2"><span>Total</span><strong><?= e(format_currency($sale['grand_total'])) ?></strong></div>
        <?php if ($isHeld): ?>
            <div class="d-flex justify-content-between mb-2"><span>Amount on Hold</span><strong><?= e(format_currency($sale['grand_total'])) ?></strong></div>
        <?php else: ?>
            <div class="d-flex justify-content-between mb-2"><span>Paid Now</span><strong><?= e(format_currency($collectedAmount)) ?></strong></div>
            <?php if ($cashTendered > 0): ?>
                <div class="d-flex justify-content-between mb-2"><span>Cash Given</span><strong><?= e(format_currency($cashTendered)) ?></strong></div>
            <?php endif; ?>
            <?php if ($creditAmount > 0): ?>
                <div class="d-flex justify-content-between mb-2"><span>Assigned to Credit</span><strong><?= e(format_currency($creditAmount)) ?></strong></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-2"><span>Change</span><strong><?= e(format_currency($sale['change_due'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($redeemedPoints > 0): ?>
            <div class="d-flex justify-content-between mb-2"><span>Points Redeemed</span><strong><?= e((string) $redeemedPoints) ?> pts</strong></div>
        <?php endif; ?>
        <?php if ($earnedPoints > 0): ?>
            <div class="d-flex justify-content-between"><span>Points Earned</span><strong><?= e((string) $earnedPoints) ?> pts</strong></div>
        <?php endif; ?>
        <?php if (($sale['payments'] ?? []) !== []): ?>
            <hr>
            <div class="small text-uppercase text-muted mb-2">Payment Breakdown</div>
            <?php foreach ($sale['payments'] as $payment): ?>
                <?php $paymentSummary = pos_payment_detail_summary($payment); ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span><?= e(pos_payment_method_label((string) ($payment['payment_method'] ?? ''))) ?></span>
                        <strong><?= e(format_currency($payment['amount'])) ?></strong>
                    </div>
                    <?php if ($paymentSummary !== ''): ?>
                        <div class="small text-muted"><?= e($paymentSummary) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <hr>
        <div class="small text-center text-muted"><?= e($receiptFooter) ?></div>
    </div>
</div>
