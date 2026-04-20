<div class="surface-card card-panel">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4">Please provide a valid expense entry.</div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" enctype="multipart/form-data" class="d-grid gap-4" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $expense['id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label class="form-label">Category</label>
                <select name="expense_category_id" class="form-select" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= (string) ($expense['expense_category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= e((string) ($expense['amount'] ?? 0)) ?>" required>
            </div>
            <div>
                <label class="form-label">Expense date</label>
                <input type="date" name="expense_date" class="form-control" value="<?= e((string) ($expense['expense_date'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['draft', 'approved', 'rejected'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string) ($expense['status'] ?? 'approved') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Receipt image</label>
                <input type="file" name="receipt" class="form-control" accept="image/png,image/jpeg,image/webp">
                <?php if (!empty($expense['receipt_path'])): ?>
                    <div class="small text-muted mt-2">Current receipt available.</div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_receipt" id="remove_receipt" value="1">
                        <label class="form-check-label" for="remove_receipt">Remove current receipt</label>
                    </div>
                <?php endif; ?>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" rows="4" class="form-control" required><?= e($expense['description'] ?? '') ?></textarea>
            </div>
        </div>
        <?php if (!empty($expense['receipt_path'])): ?>
            <div class="media-preview">
                <img src="<?= e(url((string) $expense['receipt_path'])) ?>" alt="Expense receipt" class="img-fluid">
            </div>
        <?php endif; ?>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= e(url('expenses')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>
