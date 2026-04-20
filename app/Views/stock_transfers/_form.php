<?php
$itemSeed = json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$productSeed = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$referenceNumber = (string) ($transfer['reference_number'] ?? '');
$formTitle = $isEdit ? 'Edit stock transfer' : 'Create stock transfer';
$eyebrow = $isEdit ? 'Draft Revision' : 'Transfer Builder';
$summary = $isEdit
    ? 'Revise route notes and items before this transfer leaves the source branch.'
    : 'Prepare a branch transfer, save it as a draft, or dispatch it immediately.';
$backUrl = $isEdit && !empty($form['id'])
    ? url('inventory/transfers/show?id=' . $form['id'])
    : url('inventory/transfers');
?>
<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <p class="eyebrow mb-1"><?= e($eyebrow) ?></p>
                <h3 class="mb-1"><?= e($formTitle) ?></h3>
                <div class="text-muted"><?= e($summary) ?></div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if ($referenceNumber !== ''): ?>
                    <span class="badge-soft"><i class="bi bi-arrow-left-right"></i><?= e($referenceNumber) ?></span>
                <?php endif; ?>
                <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>

        <?php if (!empty($errors['general'][0])): ?>
            <div class="alert alert-danger rounded-4">
                <strong>Transfer could not be saved.</strong>
                <div class="small mt-1"><?= e($errors['general'][0]) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($branches === []): ?>
            <div class="alert alert-warning rounded-4 mb-0">
                Add another active branch in Settings before creating stock transfers.
            </div>
        <?php else: ?>
            <form action="<?= e($action) ?>" method="post" class="d-grid gap-4" data-loading-form data-ajax="true" x-data='stockTransferForm(<?= $itemSeed ?>, <?= $productSeed ?>)'>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($submissionKey ?? '')) ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label class="form-label">Source Branch</label>
                        <input type="text" class="form-control" value="<?= e($sourceBranchName) ?>" disabled>
                    </div>
                    <div>
                        <label class="form-label">Destination Branch</label>
                        <select name="destination_branch_id" class="form-select" required>
                            <option value="">Select a destination branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e((string) $branch['id']) ?>" <?= (string) ($form['destination_branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                                    <?= e($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['destination_branch_id'][0])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['destination_branch_id'][0]) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Optional handling instructions for the destination branch"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <p class="eyebrow mb-1">Transfer Items</p>
                            <h3 class="mb-0">Products leaving <?= e($sourceBranchName) ?></h3>
                        </div>
                        <button type="button" class="btn btn-outline-primary" @click="addLine()"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
                    </div>
                    <?php if (!empty($errors['items'][0])): ?>
                        <div class="text-danger small mb-3"><?= e($errors['items'][0]) ?></div>
                    <?php endif; ?>

                    <template x-for="(item, index) in items" :key="index">
                        <div class="form-grid align-items-end mb-3">
                            <div>
                                <label class="form-label">Product</label>
                                <select class="form-select" :name="`product_id[]`" x-model="item.product_id" required>
                                    <option value="">Select product</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="String(product.id)" x-text="`${product.name} (${product.sku}) - ${Number(product.quantity_on_hand).toFixed(2)} in stock`"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Quantity</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" :name="`quantity[]`" x-model="item.quantity" required>
                            </div>
                            <div class="d-grid">
                                <button type="button" class="btn btn-outline-danger" @click="removeLine(index)" :disabled="items.length === 1">Remove</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div class="small text-muted">Dispatching now deducts stock from the source branch immediately and notifies the destination branch.</div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary"><?= e($draftLabel) ?></button>
                        <button type="submit" name="submit_action" value="in_transit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>

<script>
function stockTransferForm(initialItems, productCatalog) {
    return {
        items: Array.isArray(initialItems) && initialItems.length ? initialItems.map((item) => ({
            product_id: String(item.product_id ?? ''),
            quantity: item.quantity ?? 1,
        })) : [{ product_id: '', quantity: 1 }],
        products: Array.isArray(productCatalog) ? productCatalog : [],
        addLine() {
            this.items.push({ product_id: '', quantity: 1 });
        },
        removeLine(index) {
            if (this.items.length === 1) {
                return;
            }

            this.items.splice(index, 1);
        }
    };
}
</script>
