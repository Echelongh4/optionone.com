<?php
$exportFormats = [
    'csv' => 'CSV',
    'xlsx' => 'Excel',
    'pdf' => 'PDF',
];
?>
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Events</span><span>Filtered window</span></div>
        <h3><?= e((string) ($summary['total_events'] ?? 0)) ?></h3>
        <div class="text-muted">All activity entries matching the current filters.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Today</span><span>Current day</span></div>
        <h3><?= e((string) ($summary['events_today'] ?? 0)) ?></h3>
        <div class="text-muted">Events written today within the selected filter scope.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Actors</span><span>Distinct users</span></div>
        <h3><?= e((string) ($summary['active_users'] ?? 0)) ?></h3>
        <div class="text-muted">Named staff accounts represented in the activity stream.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>High Impact</span><span>Critical operations</span></div>
        <h3><?= e((string) ($summary['high_impact_events'] ?? 0)) ?></h3>
        <div class="text-muted">Delete, void, backup, restore, download, and status-change events.</div>
    </section>
</div>

<div class="surface-card card-panel workspace-panel mb-4">
    <div class="filter-panel">
        <div class="filter-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1"><i class="bi bi-shield-check me-1"></i>Activity Monitoring</p>
                <h3><i class="bi bi-clipboard-data me-2"></i>Audit Filters and Export</h3>
            </div>
            <div class="chip-cluster">
                <span class="badge-soft"><i class="bi bi-calendar-range"></i><?= e($filters['date_from']) ?> to <?= e($filters['date_to']) ?></span>
                <span class="badge-soft"><i class="bi bi-list-task"></i><?= e((string) count($logs)) ?> rows loaded</span>
            </div>
        </div>

        <form method="get" action="<?= e(url('audit-logs')) ?>" class="filter-grid">
            <div class="field-stack">
                <label class="form-label">Search</label>
                <input type="text" name="term" class="form-control" value="<?= e($filters['term']) ?>" placeholder="User, IP, description, or entity">
            </div>
            <div class="field-stack">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">All users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= e((string) $user['id']) ?>" <?= $filters['user_id'] === (string) $user['id'] ? 'selected' : '' ?>>
                            <?= e($user['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-stack">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $action))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-stack">
                <label class="form-label">Entity</label>
                <select name="entity_type" class="form-select">
                    <option value="">All entities</option>
                    <?php foreach ($entityTypes as $entityType): ?>
                        <option value="<?= e($entityType) ?>" <?= $filters['entity_type'] === $entityType ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $entityType))) ?></option>
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
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?= e(url('audit-logs')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <form method="get" action="<?= e(url('audit-logs/export')) ?>" class="row g-3 align-items-end mt-1" data-loading-mode="export" data-download="true" data-skip-loading>
            <input type="hidden" name="term" value="<?= e($filters['term']) ?>">
            <input type="hidden" name="user_id" value="<?= e($filters['user_id']) ?>">
            <input type="hidden" name="action" value="<?= e($filters['action']) ?>">
            <input type="hidden" name="entity_type" value="<?= e($filters['entity_type']) ?>">
            <input type="hidden" name="date_from" value="<?= e($filters['date_from']) ?>">
            <input type="hidden" name="date_to" value="<?= e($filters['date_to']) ?>">
            <div class="col-md-4">
                <label class="form-label">Format</label>
                <select name="format" class="form-select">
                    <?php foreach ($exportFormats as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-grid">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Download Audit Export</button>
            </div>
        </form>
    </div>
</div>

<section class="surface-card card-panel table-shell workspace-panel">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Activity Feed</p>
            <h3><i class="bi bi-journal-text me-2"></i>Audit Events</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><i class="bi bi-clock-history"></i>Newest first</span>
        </div>
    </div>

    <?php if ($logs === []): ?>
        <div class="empty-state mx-auto" style="max-width: 32rem;">No audit events matched the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table">
                <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Description</th>
                    <th>Source</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) $log['created_at']) ?></div>
                            <div class="small text-muted">ID <?= e((string) $log['id']) ?></div>
                        </td>
                        <td>
                            <div class="entity-copy">
                                <div class="entity-title"><?= e($log['user_name'] ?? 'System') ?></div>
                                <div class="entity-subtitle">
                                    <?= e($log['role_name'] ?? 'System') ?>
                                    <?php if (($log['branch_name'] ?? '') !== ''): ?>
                                        · <?= e((string) $log['branch_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-soft"><?= e(ucwords(str_replace('_', ' ', (string) $log['action']))) ?></span></td>
                        <td>
                            <div class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string) $log['entity_type']))) ?></div>
                            <div class="small text-muted">#<?= e((string) ($log['entity_id'] ?? 'n/a')) ?></div>
                        </td>
                        <td>
                            <div><?= e((string) $log['description']) ?></div>
                            <div class="small text-muted text-break"><?= e((string) ($log['user_agent'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e((string) ($log['ip_address'] ?? 'Unknown')) ?></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
