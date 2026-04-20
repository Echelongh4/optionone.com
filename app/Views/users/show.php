<div data-refresh-region="user-detail">
<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-1">Account Overview</p>
                <h3 class="mb-0"><?= e($userData['full_name']) ?></h3>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('users/edit?id=' . $userData['id'])) ?>" class="btn btn-outline-primary" data-modal data-title="Edit User" data-refresh-target='[data-refresh-region="user-detail"]'>Edit User</a>
                <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>
        <div class="form-grid">
            <div><div class="text-muted small">Username</div><div class="fw-semibold"><?= e(!empty($userData['username']) ? '@' . $userData['username'] : 'Not assigned') ?></div></div>
            <div><div class="text-muted small">Email</div><div class="fw-semibold"><?= e($userData['email']) ?></div></div>
            <div><div class="text-muted small">Phone</div><div class="fw-semibold"><?= e($userData['phone'] ?: 'Not provided') ?></div></div>
            <div><div class="text-muted small">Role</div><div class="fw-semibold"><?= e($userData['role_name']) ?></div></div>
            <div><div class="text-muted small">Branch</div><div class="fw-semibold"><?= e($userData['branch_name'] ?? 'Unassigned') ?></div></div>
            <div><div class="text-muted small">Status</div><div class="fw-semibold"><?= e(ucfirst($userData['status'])) ?></div></div>
            <div><div class="text-muted small">Last Login</div><div class="fw-semibold"><?= e((string) ($userData['last_login_at'] ?? 'Never')) ?></div></div>
        </div>
    </section>
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Access Context</p>
        <h3 class="mb-3">Operational Notes</h3>
        <div class="text-muted mb-3">Branch assignment and role selection control where the user operates and what modules are available in the shell.</div>
        <div class="small text-muted">Created at: <?= e((string) $userData['created_at']) ?></div>
        <div class="small text-muted">Updated at: <?= e((string) $userData['updated_at']) ?></div>
    </section>
</div>

<div class="surface-card card-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1">User Audit Log</p>
            <h3 class="mb-0">Activity History</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>When</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Description</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activity as $log): ?>
                <tr>
                    <td><?= e((string) $log['created_at']) ?></td>
                    <td><?= e(ucfirst($log['action'])) ?></td>
                    <td><?= e($log['entity_type']) ?></td>
                    <td><?= e($log['description']) ?></td>
                    <td><?= e($log['ip_address'] ?? 'N/A') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
