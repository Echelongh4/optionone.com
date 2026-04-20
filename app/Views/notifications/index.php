<?php
$statusFilter = (string) ($filters['status'] ?? 'all');
$typeFilter = (string) ($filters['type'] ?? '');
$today = date('Y-m-d');
$weekThreshold = strtotime('-7 days');
$groupOrder = ['Today', 'This Week', 'Earlier', 'Undated'];
$groupedNotifications = [];

$resolveTypeMeta = static function (string $type): array {
    return match ($type) {
        'low_stock' => ['bi-box-seam', 'warning', 'Low Stock'],
        'sale', 'checkout', 'payment' => ['bi-cart-check', 'success', 'Sales'],
        'return', 'refund' => ['bi-arrow-counterclockwise', 'danger', 'Returns'],
        'inventory', 'transfer' => ['bi-arrow-left-right', 'info', 'Inventory'],
        'backup', 'restore' => ['bi-database-check', 'primary', 'System'],
        'security', 'login' => ['bi-shield-lock', 'primary', 'Security'],
        default => ['bi-bell', 'secondary', ucwords(str_replace('_', ' ', $type !== '' ? $type : 'General'))],
    };
};

foreach ($notifications as $notification) {
    $createdAtRaw = trim((string) ($notification['created_at'] ?? ''));
    $timestamp = $createdAtRaw !== '' ? strtotime($createdAtRaw) : false;

    if ($timestamp === false) {
        $group = 'Undated';
        $dateLabel = 'Unknown date';
        $timeLabel = '--';
    } elseif (date('Y-m-d', $timestamp) === $today) {
        $group = 'Today';
        $dateLabel = 'Today';
        $timeLabel = date('H:i', $timestamp);
    } elseif ($timestamp >= $weekThreshold) {
        $group = 'This Week';
        $dateLabel = date('D, M d', $timestamp);
        $timeLabel = date('H:i', $timestamp);
    } else {
        $group = 'Earlier';
        $dateLabel = date('M d, Y', $timestamp);
        $timeLabel = date('H:i', $timestamp);
    }

    [$iconClass, $toneClass, $typeLabel] = $resolveTypeMeta((string) ($notification['type'] ?? ''));
    $groupedNotifications[$group][] = [
        'notification' => $notification,
        'icon' => $iconClass,
        'tone' => $toneClass,
        'type_label' => $typeLabel,
        'date_label' => $dateLabel,
        'time_label' => $timeLabel,
    ];
}
?>

<div class="notification-center">
    <aside class="notification-center__sidebar">
        <section class="workspace-panel notification-overview-panel">
            <div class="workspace-panel__header">
                <div>
                    <p class="eyebrow mb-1"><i class="bi bi-bell me-1"></i>Notifications</p>
                    <h3 class="mb-1">Inbox</h3>
                </div>
                <?php if (($summary['unread'] ?? 0) > 0): ?>
                    <form action="<?= e(url('notifications/read')) ?>" method="post" class="workspace-panel__actions">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-check2-all me-1"></i>Mark All Read</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="notification-overview__stats mt-4">
                <article class="notification-overview__stat">
                    <div class="table-kicker">Total</div>
                    <div class="h3 mb-1"><?= e((string) ($summary['total'] ?? 0)) ?></div>
                    <div class="text-muted small">All items in your feed.</div>
                </article>
                <article class="notification-overview__stat notification-overview__stat--accent">
                    <div class="table-kicker">Unread</div>
                    <div class="h3 mb-1"><?= e((string) ($summary['unread'] ?? 0)) ?></div>
                    <div class="text-muted small">Still awaiting review.</div>
                </article>
                <article class="notification-overview__stat">
                    <div class="table-kicker">Read</div>
                    <div class="h3 mb-1"><?= e((string) ($summary['read'] ?? 0)) ?></div>
                    <div class="text-muted small">Already acknowledged.</div>
                </article>
                <article class="notification-overview__stat">
                    <div class="table-kicker">Actionable</div>
                    <div class="h3 mb-1"><?= e((string) ($summary['actionable'] ?? 0)) ?></div>
                    <div class="text-muted small">Linked to a workflow or page.</div>
                </article>
            </div>
        </section>

        <form method="get" action="<?= e(url('notifications')) ?>" class="surface-card card-panel notification-filter-card mt-4">
            <div class="eyebrow mb-1">Filter Inbox</div>
            <h4 class="mb-3">Filters</h4>

            <div class="field-stack mb-3">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Unread only</option>
                    <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read only</option>
                </select>
            </div>

            <div class="field-stack mb-3">
                <label class="form-label" for="type">Type</label>
                <select name="type" id="type" class="form-select">
                    <option value="">All types</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notification-filter-card__actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
                <a href="<?= e(url('notifications')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </aside>

    <section class="notification-center__main">
        <div class="workspace-panel mb-4">
            <div class="workspace-panel__header">
                <div>
                    <p class="eyebrow mb-1"><i class="bi bi-inboxes me-1"></i>Activity</p>
                    <h4 class="mb-1">Recent Notifications</h4>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft"><i class="bi bi-list-task"></i><?= e((string) count($notifications)) ?> visible</span>
                </div>
            </div>
        </div>

        <?php if ($notifications === []): ?>
            <div class="surface-card card-panel text-center py-5">
                <div class="empty-state mx-auto" style="max-width: 32rem;">No notifications match the current filters.</div>
            </div>
        <?php endif; ?>

        <div class="d-grid gap-4">
            <?php foreach ($groupOrder as $group): ?>
                <?php if (!isset($groupedNotifications[$group]) || $groupedNotifications[$group] === []): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <section class="surface-card card-panel notification-group-panel">
                    <div class="notification-group-panel__header">
                        <div>
                            <h5 class="mb-1"><?= e($group) ?></h5>
                            <div class="text-muted small"><?= e((string) count($groupedNotifications[$group])) ?> notifications</div>
                        </div>
                    </div>

                    <div class="notification-list mt-3">
                        <?php foreach ($groupedNotifications[$group] as $entry): ?>
                            <?php
                            $notification = $entry['notification'];
                            $isUnread = !(bool) ($notification['is_read'] ?? false);
                            $hasLink = trim((string) ($notification['link_url'] ?? '')) !== '';
                            ?>
                            <article class="notification-item <?= $isUnread ? 'notification-item--unread' : '' ?>">
                                <div class="notification-item__icon notification-item__icon--<?= e($entry['tone']) ?>">
                                    <i class="bi <?= e($entry['icon']) ?>"></i>
                                </div>

                                <div class="notification-item__body">
                                    <div class="notification-item__top">
                                        <div class="notification-item__headline">
                                            <div class="notification-item__title-row">
                                                <h6 class="mb-0"><?= e($notification['title']) ?></h6>
                                                <?php if ($isUnread): ?>
                                                    <span class="notification-dot"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notification-item__meta">
                                                <span class="badge-soft notification-chip"><?= e($entry['type_label']) ?></span>
                                                <?php if ($hasLink): ?>
                                                    <span class="status-pill bg-info-subtle text-info-emphasis">Actionable</span>
                                                <?php endif; ?>
                                                <span class="small text-muted"><?= e($entry['date_label']) ?></span>
                                            </div>
                                        </div>
                                        <div class="notification-item__time"><?= e($entry['time_label']) ?></div>
                                    </div>

                                    <p class="notification-item__message mb-0"><?= e($notification['message']) ?></p>

                                    <div class="notification-item__actions">
                                        <?php if ($hasLink): ?>
                                            <a href="<?= e(url('notifications/open?id=' . (int) $notification['id'])) ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Open Alert
                                            </a>
                                        <?php endif; ?>

                                        <form action="<?= e(url($isUnread ? 'notifications/read-one' : 'notifications/unread-one')) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $notification['id']) ?>">
                                            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                                            <input type="hidden" name="type" value="<?= e($typeFilter) ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi <?= $isUnread ? 'bi-check2-circle' : 'bi-arrow-counterclockwise' ?> me-1"></i><?= $isUnread ? 'Mark Read' : 'Mark Unread' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
</div>
