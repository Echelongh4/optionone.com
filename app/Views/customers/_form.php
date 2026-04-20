<div class="surface-card card-panel">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4">Please review the customer details and try again.</div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" class="d-grid gap-4" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $customer['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label class="form-label">First name</label>
                <input type="text" name="first_name" class="form-control" value="<?= e($customer['first_name'] ?? '') ?>" required>
            </div>
            <div>
                <label class="form-label">Last name</label>
                <input type="text" name="last_name" class="form-control" value="<?= e($customer['last_name'] ?? '') ?>" required>
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($customer['email'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($customer['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Customer group</label>
                <select name="customer_group_id" class="form-select">
                    <option value="">No group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= e((string) $group['id']) ?>" <?= (string) ($customer['customer_group_id'] ?? '') === (string) $group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Credit balance</label>
                <input type="number" step="0.01" min="0" name="credit_balance" class="form-control" value="<?= e((string) ($customer['credit_balance'] ?? 0)) ?>">
            </div>
            <div>
                <label class="form-label">Loyalty balance</label>
                <input type="number" min="0" name="loyalty_balance" class="form-control" value="<?= e((string) ($customer['loyalty_balance'] ?? 0)) ?>">
            </div>
            <div>
                <label class="form-label">Special pricing type</label>
                <select name="special_pricing_type" class="form-select">
                    <?php foreach (['none', 'percentage', 'fixed'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($customer['special_pricing_type'] ?? 'none') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Special pricing value</label>
                <input type="number" step="0.01" min="0" name="special_pricing_value" class="form-control" value="<?= e((string) ($customer['special_pricing_value'] ?? 0)) ?>">
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Address</label>
                <textarea name="address" rows="4" class="form-control"><?= e($customer['address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= e(url('customers')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>