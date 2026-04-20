<div data-refresh-region="user-directory">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Users</span><span>Accounts</span></div>
        <h3><?= e((string) $summary['total_users']) ?></h3>
        <div class="text-muted">All active and inactive staff accounts in the system.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Active</span><span>Available</span></div>
        <h3><?= e((string) $summary['active_users']) ?></h3>
        <div class="text-muted">Users who can still authenticate into the platform.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Inactive</span><span>Restricted</span></div>
        <h3><?= e((string) $summary['inactive_users']) ?></h3>
        <div class="text-muted">Accounts paused without deleting their history.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Admin Tier</span><span>Oversight</span></div>
        <h3><?= e((string) $summary['admin_users']) ?></h3>
        <div class="text-muted">Super admin and admin seats with elevated access.</div>
    </section>
</div>

<div class="surface-card card-panel table-shell mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1"><i class="bi bi-people me-1"></i>Staff Directory</p>
            <h3><i class="bi bi-person-badge me-2"></i>Users</h3>
        </div>
        <div class="workspace-panel__actions">
            <span class="badge-soft"><i class="bi bi-shield-lock"></i><?= e((string) $summary['admin_users']) ?> admin seats</span>
            <a href="<?= e(url('users/create')) ?>" class="btn btn-primary" data-modal data-title="Add User" data-refresh-target='[data-refresh-region="user-directory"]'><i class="bi bi-plus-lg me-1"></i>Add User</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th><i class="bi bi-person-circle me-1"></i>User</th>
                <th><i class="bi bi-shield-lock me-1"></i>Role</th>
                <th><i class="bi bi-geo-alt me-1"></i>Branch</th>
                <th><i class="bi bi-toggle-on me-1"></i>Status</th>
                <th><i class="bi bi-clock me-1"></i>Last Login</th>
                <th class="text-end"><i class="bi bi-list-check me-1"></i>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $avatar = strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 1)); ?>
                <tr>
                    <td>
                        <div class="entity-cell">
                            <div class="entity-avatar"><?= e($avatar) ?></div>
                            <div class="entity-copy">
                                <div class="entity-title"><?= e($user['full_name']) ?></div>
                                <div class="entity-subtitle"><?= e(!empty($user['username']) ? '@' . $user['username'] . ' | ' . $user['email'] : $user['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge-soft"><?= e($user['role_name']) ?></span></td>
                    <td><span class="badge-soft"><?= e($user['branch_name'] ?? 'Unassigned') ?></span></td>
                    <td><span class="badge-soft text-capitalize"><?= e($user['status']) ?></span></td>
                    <td><?= e((string) ($user['last_login_at'] ?? 'Never')) ?></td>
                    <td class="text-end">
                        <div class="compact-actions">
                            <a href="<?= e(url('users/show?id=' . $user['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="<?= e(url('users/edit?id=' . $user['id'])) ?>" class="btn btn-sm btn-outline-primary" data-modal data-title="Edit User" data-refresh-target='[data-refresh-region="user-directory"]'>Edit</a>
                            <form action="<?= e(url('users/toggle-status')) ?>" method="post" class="d-inline" data-ajax="true" data-refresh-target='[data-refresh-region="user-directory"]'>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                <button type="submit" class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>" data-confirm-action data-confirm-title="<?= e($user['status'] === 'active' ? 'Deactivate this user?' : 'Reactivate this user?') ?>" data-confirm-text="<?= e($user['status'] === 'active' ? 'The account will lose access until it is reactivated.' : 'The account will regain access immediately.') ?>" data-confirm-button="<?= e($user['status'] === 'active' ? 'Deactivate' : 'Activate') ?>">
                                    <?= e($user['status'] === 'active' ? 'Deactivate' : 'Activate') ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php $activeRoleId = (int) ((current_user()['role_id'] ?? 0)); ?>
<div data-refresh-region="user-role-matrix">
<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Access Control</p>
            <h3><i class="bi bi-shield-check me-2"></i>Role Permission Matrix</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><i class="bi bi-diagram-3"></i><?= e((string) $summary['managed_roles']) ?> roles</span>
            <span class="badge-soft"><i class="bi bi-key"></i><?= e((string) $summary['managed_permissions']) ?> permissions</span>
        </div>
    </div>

    <div class="text-muted mb-3">Manage what each role can access across the platform. Super Admin access remains fixed.</div>

    <div class="role-access-grid">
        <?php foreach ($roles as $role): ?>
            <?php
            $roleId = (int) $role['id'];
            $assigned = $rolePermissionIds[$roleId] ?? [];
            $assignedCount = $role['name'] === 'Super Admin' ? $summary['managed_permissions'] : count($assigned);
            $isOwnRole = $activeRoleId === $roleId;
            ?>
            <form action="<?= e(url('users/roles/permissions/update')) ?>" method="post" class="record-card role-access-card" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="user-role-matrix"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="role_id" value="<?= e((string) $roleId) ?>">

                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4><?= e($role['name']) ?></h4>
                        <div class="text-muted"><?= e($role['description'] ?? 'Role access profile') ?></div>
                    </div>
                    <div class="record-card__meta">
                        <?php if ($isOwnRole): ?>
                            <span class="badge-soft"><i class="bi bi-person-check"></i>Your active role</span>
                        <?php endif; ?>
                        <span class="badge-soft"><i class="bi bi-list-check"></i><?= e((string) $assignedCount) ?> assigned</span>
                    </div>
                </div>

                <?php if ($role['name'] === 'Super Admin'): ?>
                    <div class="role-access-note">
                        <i class="bi bi-shield-lock"></i>
                        <span>Super Admin is the fixed full-access role and is not editable from the interface.</span>
                    </div>
                <?php endif; ?>

                <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                    <div class="role-access-module">
                        <div class="role-access-module__header">
                            <div>
                                <h5><?= e(ucwords(str_replace('_', ' ', (string) $module))) ?></h5>
                                <div class="text-muted small"><?= e((string) count($modulePermissions)) ?> permission<?= count($modulePermissions) === 1 ? '' : 's' ?></div>
                            </div>
                        </div>

                        <div class="role-access-module__body">
                            <?php foreach ($modulePermissions as $permission): ?>
                                <?php
                                $permissionId = (int) $permission['id'];
                                $checked = $role['name'] === 'Super Admin' || in_array($permissionId, $assigned, true);
                                $permissionLabel = ucwords(str_replace('_', ' ', (string) $permission['name']));
                                ?>
                                <label class="check-card role-access-check <?= $checked ? 'role-access-check--active' : '' ?>">
                                    <input
                                        type="checkbox"
                                        name="permission_ids[]"
                                        value="<?= e((string) $permissionId) ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                        <?= $role['name'] === 'Super Admin' ? 'disabled' : '' ?>
                                    >
                                    <div class="role-access-check__copy">
                                        <strong><?= e($permissionLabel) ?></strong>
                                        <div class="text-muted small"><?= e($permission['description'] ?? $permissionLabel) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($role['name'] !== 'Super Admin'): ?>
                    <div class="workspace-panel__actions justify-content-between flex-wrap gap-2 mt-3">
                        <div class="text-muted small">
                            <?= e($isOwnRole ? 'Changes to this role affect your current session on the next request.' : 'Save to apply the updated access profile.') ?>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Permissions
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>
</section>
</div>

<div class="surface-card card-panel table-shell workspace-panel">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Recent Activity</p>
            <h3><i class="bi bi-clipboard-data me-2"></i>Audit Log</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><i class="bi bi-journal-check"></i><?= e((string) count($recentLogs)) ?> recent events</span>
            <a href="<?= e(url('audit-logs')) ?>" class="btn btn-sm btn-outline-primary">View Full Trail</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= e((string) $log['created_at']) ?></td>
                    <td><?= e($log['user_name'] ?? 'System') ?></td>
                    <td><span class="badge-soft"><?= e(ucfirst($log['action'])) ?></span></td>
                    <td><?= e($log['entity_type']) ?></td>
                    <td><?= e($log['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
