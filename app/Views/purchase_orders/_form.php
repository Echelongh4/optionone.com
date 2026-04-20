<?php
$productSeed = array_map(static function (array $item): array {
    return [
        'product_id' => (string) ($item['product_id'] ?? ''),
        'quantity' => (float) ($item['quantity'] ?? 1),
        'unit_cost' => (float) ($item['unit_cost'] ?? 0),
        'tax_rate' => (float) ($item['tax_rate'] ?? 0),
    ];
}, $items);

$formTitle = $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order';
$formEyebrow = $isEdit ? 'Draft Revision' : 'Purchase Planning';
$formNote = $isEdit
    ? 'Revise supplier, expected date, notes, and order lines before the order is issued.'
    : 'Build a supplier order, save it as a draft, or issue it immediately.';
$orderNumber = (string) ($order['po_number'] ?? '');
$cancelUrl = $isEdit && !empty($form['id'])
    ? url('inventory/purchase-orders/show?id=' . $form['id'])
    : url('inventory/purchase-orders');
?>

<div
    class="surface-card card-panel"
    x-data='purchaseOrderForm(<?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($productSeed, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
    x-init="init()"
>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <strong>Please review this purchase order.</strong>
            <?php if (!empty($errors['general'][0])): ?>
                <div class="small mt-1"><?= e($errors['general'][0]) ?></div>
            <?php elseif (!empty($errors['items'][0])): ?>
                <div class="small mt-1"><?= e($errors['items'][0]) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" class="d-grid gap-4" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_key" value="<?= e((string) ($submissionKey ?? '')) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
        <?php endif; ?>

        <section>
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                <div>
                    <p class="eyebrow mb-1"><?= e($formEyebrow) ?></p>
                    <h3 class="mb-1"><?= e($formTitle) ?></h3>
                    <div class="text-muted"><?= e($formNote) ?></div>
                </div>
                <?php if ($isEdit && $orderNumber !== ''): ?>
                    <span class="badge-soft"><i class="bi bi-receipt-cutoff"></i><?= e($orderNumber) ?></span>
                <?php else: ?>
                    <span class="badge-soft">Draft or issue immediately</span>
                <?php endif; ?>
            </div>

            <div class="form-grid">
                <div>
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= e((string) $supplier['id']) ?>" <?= (string) ($form['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Expected delivery date</label>
                    <input type="date" name="expected_at" class="form-control" value="<?= e((string) ($form['expected_at'] ?? '')) ?>">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Internal notes, supplier remarks, or receiving context"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                </div>
            </div>
        </section>

        <section>
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
                <div>
                    <p class="eyebrow mb-1">Order Lines</p>
                    <h3 class="mb-0">Products to purchase</h3>
                </div>
                <button type="button" class="btn btn-outline-secondary" @click="addItem()"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" data-purchase-order-lines-table>
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Tax %</th>
                        <th>Preview</th>
                        <th class="text-end">Remove</th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td>
                                <select class="form-select" :name="`product_id[${index}]`" x-model="item.product_id" @change="hydrateItem(index)">
                                    <option value="">Select product</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="String(product.id)" x-text="`${product.name} (${product.sku})`"></option>
                                    </template>
                                </select>
                            </td>
                            <td><input class="form-control" type="number" step="0.01" min="0.01" :name="`quantity[${index}]`" x-model="item.quantity"></td>
                            <td><input class="form-control" type="number" step="0.01" min="0" :name="`unit_cost[${index}]`" x-model="item.unit_cost"></td>
                            <td><input class="form-control" type="number" step="0.01" min="0" :name="`tax_rate[${index}]`" x-model="item.tax_rate"></td>
                            <td><div class="small text-muted" x-text="preview(item)"></div></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItem(index)" :disabled="items.length === 1">Remove</button>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="d-flex gap-2 justify-content-end flex-wrap">
            <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary"><?= e($draftLabel) ?></button>
            <button type="submit" name="submit_action" value="ordered" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>
