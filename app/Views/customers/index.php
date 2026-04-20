<?php
$totalCustomers = count($customers);
$vipCustomers = count(array_filter($customers, static fn (array $customer): bool => ($customer['customer_group_name'] ?? '') === 'VIP'));
$totalCredit = array_sum(array_map(static fn (array $customer): float => (float) $customer['credit_balance'], $customers));
$customersWithBalance = count(array_filter($customers, static fn (array $customer): bool => (float) $customer['credit_balance'] > 0));
?>

<div data-refresh-region="customer-register">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Customers</span><span>Profiles</span></div>
        <h3><?= e((string) $totalCustomers) ?></h3>
        <div class="text-muted">Registered customer records.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>VIP Members</span><span>Loyalty</span></div>
        <h3><?= e((string) $vipCustomers) ?></h3>
        <div class="text-muted">Customers in preferred pricing groups.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Outstanding Credit</span><span>Ledger</span></div>
        <h3><?= e(format_currency($totalCredit)) ?></h3>
        <div class="text-muted"><?= e((string) $customersWithBalance) ?> customers currently owe the business.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Customer Groups</span><span>Pricing</span></div>
        <h3><?= e((string) count($customerGroups ?? [])) ?></h3>
        <div class="text-muted">Reusable tiers for loyalty and pricing rules.</div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Customer Register</p>
            <h3><i class="bi bi-people me-2"></i>Customers</h3>
        </div>
        <div class="workspace-panel__actions">
            <a href="<?= e(url('customers/create')) ?>" class="btn btn-primary" data-modal data-title="Add Customer" data-refresh-target='[data-refresh-region="customer-register"]'><i class="bi bi-plus-lg me-1"></i>Add Customer</a>
        </div>
    </div>

    <form method="get" action="<?= e(url('customers')) ?>" class="form-grid align-items-end">
        <div class="field-stack">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Customer name, phone, or email">
        </div>
        <div class="field-stack">
            <label class="form-label">Group</label>
            <select name="group_id" class="form-select">
                <option value="">All groups</option>
                <?php foreach (($groups ?? []) as $group): ?>
                    <option value="<?= e((string) $group['id']) ?>" <?= (string) ($filters['group_id'] ?? '') === (string) $group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-stack">
            <label class="form-label">Credit Status</label>
            <select name="credit_status" class="form-select">
                <option value="">All customers</option>
                <option value="with_balance" <?= ($filters['credit_status'] ?? '') === 'with_balance' ? 'selected' : '' ?>>With outstanding balance</option>
                <option value="clear" <?= ($filters['credit_status'] ?? '') === 'clear' ? 'selected' : '' ?>>Clear accounts only</option>
            </select>
        </div>
        <div class="workspace-panel__actions align-items-end">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Apply Filter</button>
            <a href="<?= e(url('customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
    </form>

    <?php if ($customers === []): ?>
        <div class="empty-state">No customers matched the current filter.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table">
                <thead>
                <tr>
                    <th><i class="bi bi-person-circle me-1"></i>Customer</th>
                    <th><i class="bi bi-tags me-1"></i>Group</th>
                    <th><i class="bi bi-star me-1"></i>Loyalty</th>
                    <th><i class="bi bi-wallet2 me-1"></i>Credit</th>
                    <th><i class="bi bi-receipt me-1"></i>Total Orders</th>
                    <th><i class="bi bi-currency-dollar me-1"></i>Total Spent</th>
                    <th><i class="bi bi-clock me-1"></i>Last Purchase</th>
                    <th class="text-end"><i class="bi bi-list-check me-1"></i>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div class="entity-cell">
                                <div class="entity-cell__title"><?= e($customer['full_name']) ?></div>
                                <div class="entity-cell__meta"><?= e($customer['phone'] ?: ($customer['email'] ?: 'No contact')) ?></div>
                            </div>
                        </td>
                        <td><?= e($customer['customer_group_name'] ?? 'Standard') ?></td>
                        <td><?= e((string) $customer['loyalty_balance']) ?> pts</td>
                        <td><?= e(format_currency($customer['credit_balance'])) ?></td>
                        <td><?= e((string) $customer['total_orders']) ?></td>
                        <td><?= e(format_currency($customer['total_spent'])) ?></td>
                        <td><?= e((string) ($customer['last_purchase_at'] ?? 'No purchase yet')) ?></td>
                        <td class="text-end">
                            <div class="d-none d-lg-flex justify-content-end gap-2">
                                <a href="<?= e(url('customers/show?id=' . $customer['id'])) ?>" class="btn btn-sm btn-outline-secondary">Profile</a>
                                <a href="<?= e(url('customers/edit?id=' . $customer['id'])) ?>" class="btn btn-sm btn-outline-primary" data-modal data-title="Edit Customer" data-refresh-target='[data-refresh-region="customer-register"]'>Edit</a>
                                <?php if ((float) $customer['credit_balance'] <= 0): ?>
                                    <form action="<?= e(url('customers/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="customer-register"]'>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $customer['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-action data-confirm-title="Archive this customer?" data-confirm-text="The profile will be removed from the active customer register while keeping historical sales linked." data-confirm-button="Archive Customer">Archive</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown d-lg-none">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e(url('customers/show?id=' . $customer['id'])) ?>">Profile</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('customers/edit?id=' . $customer['id'])) ?>" data-modal data-title="Edit Customer" data-refresh-target='[data-refresh-region="customer-register"]'>Edit</a></li>
                                    <?php if ((float) $customer['credit_balance'] <= 0): ?>
                                        <li>
                                            <form action="<?= e(url('customers/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="customer-register"]'>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e((string) $customer['id']) ?>">
                                                <button type="submit" class="dropdown-item text-danger" data-confirm-action data-confirm-title="Archive this customer?" data-confirm-text="The profile will be removed from the active customer register while keeping historical sales linked." data-confirm-button="Archive Customer">Archive</button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>

<section class="surface-card card-panel workspace-panel" data-refresh-region="customer-groups-manager">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Customer Groups</p>
            <h3><i class="bi bi-tags me-2"></i>Pricing tiers</h3>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Current Groups</p>
                    <h4>Manage group rules</h4>
                </div>
            </div>
            <div class="stack-grid">
                <?php if (($customerGroups ?? []) === []): ?>
                    <div class="empty-state">No customer groups have been created yet.</div>
                <?php else: ?>
                    <?php foreach ($customerGroups as $group): ?>
                        <form action="<?= e(url('customers/groups/update')) ?>" method="post" class="record-card" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="customer-groups-manager"]'>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                            <?php if ($editGroupId !== null && (int) $editGroupId === (int) $group['id'] && ($groupEditErrors ?? []) !== []): ?>
                                <div class="alert alert-danger rounded-4 mb-0">
                                    <strong>Group update failed.</strong>
                                </div>
                            <?php endif; ?>
                            <div class="record-card__header">
                                <div class="workspace-panel__intro">
                                    <h4><?= e($group['name']) ?></h4>
                                    <div class="small text-muted"><?= e((string) ($group['customer_count'] ?? 0)) ?> customers assigned</div>
                                </div>
                                <div class="record-card__meta">
                                    <span class="badge-soft"><?= e(ucfirst((string) $group['discount_type'])) ?></span>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field-stack">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= e($group['name']) ?>" required>
                                </div>
                                <div class="field-stack">
                                    <label class="form-label">Discount Type</label>
                                    <select name="discount_type" class="form-select">
                                        <?php foreach (['none', 'percentage', 'fixed'] as $type): ?>
                                            <option value="<?= e($type) ?>" <?= (string) $group['discount_type'] === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-stack">
                                    <label class="form-label">Discount Value</label>
                                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="<?= e((string) $group['discount_value']) ?>" required>
                                </div>
                                <div class="field-stack field-span-full">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" rows="2" class="form-control"><?= e($group['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="workspace-panel__actions justify-content-between flex-wrap gap-2">
                                <button type="submit" class="btn btn-outline-danger" formaction="<?= e(url('customers/groups/delete')) ?>" formmethod="post" formnovalidate <?= (int) ($group['customer_count'] ?? 0) > 0 ? 'disabled' : '' ?> data-confirm-action data-confirm-title="Delete this customer group?" data-confirm-text="The group will be removed permanently." data-confirm-button="Delete Group">Delete</button>
                                <button type="submit" class="btn btn-outline-primary">Save Group</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="utility-card">
            <?php if (($groupCreateErrors ?? []) !== []): ?>
                <div class="alert alert-danger rounded-4 mb-3">
                    <strong>Please fix the customer group form errors and try again.</strong>
                </div>
            <?php endif; ?>
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">New Group</p>
                    <h4>Create a customer tier</h4>
                </div>
            </div>
            <form action="<?= e(url('customers/groups/store')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="customer-groups-manager"]'>
                <?= csrf_field() ?>
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($groupForm['name'] ?? '') ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <?php foreach (['none', 'percentage', 'fixed'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= (string) ($groupForm['discount_type'] ?? 'none') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Discount Value</label>
                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="<?= e((string) ($groupForm['discount_value'] ?? 0)) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-control"><?= e($groupForm['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Group</button>
            </form>
        </section>
    </div>
</section>
