<div class="surface-card card-panel">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4">
            <strong>Please fix the supplier form errors.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" class="d-grid gap-4" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $supplier['id']) ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div>
                <label class="form-label">Supplier name</label>
                <input type="text" name="name" class="form-control" value="<?= e($supplier['name'] ?? '') ?>" required>
            </div>
            <div>
                <label class="form-label">Contact person</label>
                <input type="text" name="contact_person" class="form-control" value="<?= e($supplier['contact_person'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($supplier['email'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($supplier['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Tax number</label>
                <input type="text" name="tax_number" class="form-control" value="<?= e($supplier['tax_number'] ?? '') ?>">
            </div>
            <div></div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= e($supplier['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= e(url('suppliers')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>