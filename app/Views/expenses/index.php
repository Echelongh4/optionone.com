<?php $totalCategories = count($expenseCategories ?? []); ?>

<div data-refresh-region="expense-register">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Entries</span><span>Records</span></div>
        <h3><?= e((string) $summary['total_entries']) ?></h3>
        <div class="text-muted">Expense rows matching the current filter.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Amount</span><span>Approved</span></div>
        <h3><?= e(format_currency($summary['total_amount'])) ?></h3>
        <div class="text-muted">Aggregate spend across the selected range.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Approved Value</span><span>Posted</span></div>
        <h3><?= e(format_currency($summary['approved_amount'] ?? 0)) ?></h3>
        <div class="text-muted">Approved expenses within the active filter.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Today</span><span>Outflow</span></div>
        <h3><?= e(format_currency($summary['today_amount'])) ?></h3>
        <div class="text-muted">Expenses logged for the current day.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Categories</span><span>Configured</span></div>
        <h3><?= e((string) $totalCategories) ?></h3>
        <div class="text-muted">Available buckets for operational spend tracking.</div>
    </section>
</div>

<div class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Expense Register</p>
            <h3><i class="bi bi-receipt me-2"></i>Expenses</h3>
        </div>
        <div class="workspace-panel__actions">
            <a href="<?= e(url('expenses/create')) ?>" class="btn btn-primary" data-modal data-title="Log Expense" data-refresh-target='[data-refresh-region="expense-register"]'>
                <i class="bi bi-plus-lg me-1"></i>Log Expense
            </a>
        </div>
    </div>

    <form method="get" action="<?= e(url('expenses')) ?>" class="form-grid">
        <div class="field-stack">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Description, category, or user">
        </div>
        <div class="field-stack">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-stack">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'approved', 'rejected'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-stack">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="field-stack">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="workspace-panel__actions align-items-end">
            <button type="submit" class="btn btn-outline-primary">Apply Filter</button>
            <a href="<?= e(url('expenses')) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th><i class="bi bi-calendar-event me-1"></i>Date</th>
                <th><i class="bi bi-tags me-1"></i>Category</th>
                <th><i class="bi bi-card-text me-1"></i>Description</th>
                <th><i class="bi bi-person-workspace me-1"></i>Logged By</th>
                <th><i class="bi bi-check2-circle me-1"></i>Status</th>
                <th><i class="bi bi-currency-dollar me-1"></i>Amount</th>
                <th><i class="bi bi-paperclip me-1"></i>Receipt</th>
                <th class="text-end"><i class="bi bi-list-check me-1"></i>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?= e((string) $expense['expense_date']) ?></td>
                    <td><?= e($expense['category_name']) ?></td>
                    <td><?= e($expense['description']) ?></td>
                    <td><?= e($expense['created_by_name']) ?></td>
                    <td><span class="badge-soft text-capitalize"><?= e((string) $expense['status']) ?></span></td>
                    <td><?= e(format_currency($expense['amount'])) ?></td>
                    <td>
                        <?php if ($expense['receipt_path']): ?>
                            <a href="<?= e(url($expense['receipt_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-none d-lg-flex justify-content-end gap-2">
                            <a href="<?= e(url('expenses/show?id=' . $expense['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="<?= e(url('expenses/edit?id=' . $expense['id'])) ?>" class="btn btn-sm btn-outline-primary" data-modal data-title="Edit Expense" data-refresh-target='[data-refresh-region="expense-register"]'>Edit</a>
                            <form action="<?= e(url('expenses/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="expense-register"]'>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $expense['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-action data-confirm-title="Archive this expense?" data-confirm-text="The expense will be removed from the active register while audit history stays intact." data-confirm-button="Archive Expense">Archive</button>
                            </form>
                        </div>
                        <div class="dropdown d-lg-none">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(url('expenses/show?id=' . $expense['id'])) ?>">View</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('expenses/edit?id=' . $expense['id'])) ?>" data-modal data-title="Edit Expense" data-refresh-target='[data-refresh-region="expense-register"]'>Edit</a></li>
                                <li>
                                    <form action="<?= e(url('expenses/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="expense-register"]'>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $expense['id']) ?>">
                                        <button type="submit" class="dropdown-item text-danger" data-confirm-action data-confirm-title="Archive this expense?" data-confirm-text="The expense will be removed from the active register while audit history stays intact." data-confirm-button="Archive Expense">Archive</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<section class="surface-card card-panel workspace-panel" data-refresh-region="expense-category-manager">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Category Setup</p>
            <h3><i class="bi bi-tags me-2"></i>Expense Categories</h3>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Current Categories</p>
                    <h4>Maintain category labels</h4>
                </div>
            </div>
            <div class="stack-grid">
                <?php if ($expenseCategories === []): ?>
                    <div class="empty-state">No expense categories have been created yet.</div>
                <?php else: ?>
                    <?php foreach ($expenseCategories as $category): ?>
                        <form action="<?= e(url('expenses/categories/update')) ?>" method="post" class="record-card" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="expense-category-manager"]'>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                            <?php if ($editCategoryId !== null && (int) $editCategoryId === (int) $category['id'] && $categoryEditErrors !== []): ?>
                                <div class="alert alert-danger rounded-4 mb-0">
                                    <strong>Category update failed.</strong>
                                </div>
                            <?php endif; ?>
                            <div class="record-card__header">
                                <div class="workspace-panel__intro">
                                    <h4><?= e($category['name']) ?></h4>
                                    <div class="small text-muted"><?= e((string) $category['expense_count']) ?> expense records</div>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field-stack">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= e($category['name']) ?>" required>
                                </div>
                                <div class="field-stack field-span-full">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" rows="2" class="form-control"><?= e($category['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="workspace-panel__actions justify-content-between flex-wrap gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                    formaction="<?= e(url('expenses/categories/delete')) ?>"
                                    formmethod="post"
                                    formnovalidate
                                    <?= (int) $category['expense_count'] > 0 ? 'disabled' : '' ?>
                                    data-confirm-action
                                    data-confirm-title="Delete this category?"
                                    data-confirm-text="This category will be removed permanently."
                                    data-confirm-button="Delete Category"
                                >
                                    Delete
                                </button>
                                <button type="submit" class="btn btn-outline-primary">Save Category</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="utility-card">
            <?php if ($categoryCreateErrors !== []): ?>
                <div class="alert alert-danger rounded-4 mb-3">
                    <strong>Please fix the category form errors and try again.</strong>
                </div>
            <?php endif; ?>
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">New Category</p>
                    <h4>Create a spending bucket</h4>
                </div>
            </div>
            <form action="<?= e(url('expenses/categories/store')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="expense-category-manager"]'>
                <?= csrf_field() ?>
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($categoryForm['name']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-control"><?= e($categoryForm['description']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </form>
        </section>
    </div>
</section>
