<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;
use App\Models\Notification;

$flashSuccess = Session::pullFlash('success');
$flashError = Session::pullFlash('error');
$flashWarning = Session::pullFlash('warning');
$flashInfo = Session::pullFlash('info');
$flashResetLink = Session::pullFlash('reset_link');
$flashVerificationLink = Session::pullFlash('verification_link');
$user = current_user();
$isPlatformAdmin = $user !== null && Auth::isPlatformAdmin($user);
$impersonationMeta = $user !== null ? Auth::impersonationMeta() : null;
$isImpersonating = $impersonationMeta !== null;
$layoutMode = $layoutMode ?? 'app';
$breadcrumbs = $breadcrumbs ?? [];
$notificationUnreadCount = 0;
$notifications = [];
$brandName = (string) setting_value('business_name', config('app.name'));
$brandLogo = (string) setting_value('business_logo_path', '');
$barcodeFormat = (string) setting_value('barcode_format', 'CODE128');
$pageTitle = (string) ($title ?? 'Dashboard');
$configuredDeveloperName = trim((string) config('app.developer_name', ''));
$derivedDeveloperName = trim((string) preg_replace('/\s+pos$/i', '', (string) config('app.name', 'NovaPOS')));
$developerName = $configuredDeveloperName !== '' ? $configuredDeveloperName : ($derivedDeveloperName !== '' ? $derivedDeveloperName : 'NovaPOS');
$developerCredit = 'Developed by ' . $developerName;
$brandWords = preg_split('/\\s+/', trim($brandName)) ?: [];
$brandInitials = '';
foreach ($brandWords as $brandWord) {
    if ($brandWord === '') {
        continue;
    }

    $brandInitials .= strtoupper(substr($brandWord, 0, 1));
    if (strlen($brandInitials) >= 2) {
        break;
    }
}
$brandInitials = $brandInitials !== '' ? substr($brandInitials, 0, 2) : 'NP';
$userInitials = strtoupper(substr((string) ($user['first_name'] ?? 'N'), 0, 1) . substr((string) ($user['last_name'] ?? 'P'), 0, 1));

if ($layoutMode !== 'auth' && $user !== null) {
    $notificationModel = new Notification();
    $branchId = $user['branch_id'] !== null ? (int) $user['branch_id'] : null;
    $notificationUnreadCount = $notificationModel->unreadCount((int) $user['id'], $branchId);
    $notifications = $notificationModel->recent((int) $user['id'], $branchId);
}

$notificationTypeMeta = static function (array $notification): array {
    $type = (string) ($notification['type'] ?? '');

    return match ($type) {
        'low_stock' => ['bi-box-seam', 'warning', 'Low Stock'],
        'sale', 'checkout', 'payment' => ['bi-cart-check', 'success', 'Sales'],
        'return', 'refund' => ['bi-arrow-counterclockwise', 'danger', 'Returns'],
        'void_request' => ['bi-patch-exclamation', 'warning', 'Void Requests'],
        'void_request_approved' => ['bi-patch-check', 'success', 'Void Approved'],
        'void_request_rejected' => ['bi-patch-minus', 'secondary', 'Void Rejected'],
        'inventory', 'transfer' => ['bi-arrow-left-right', 'info', 'Inventory'],
        'backup', 'restore' => ['bi-database-check', 'primary', 'System'],
        'security', 'login' => ['bi-shield-lock', 'primary', 'Security'],
        default => ['bi-bell', 'secondary', ucwords(str_replace('_', ' ', $type !== '' ? $type : 'General'))],
    };
};

$renderNotificationDropdown = static function (array $notifications, int $notificationUnreadCount) use ($notificationTypeMeta): string {
    ob_start();
    ?>
    <div class="dropdown-menu dropdown-menu-end p-0 shadow-sm glass-dropdown notification-dropdown-menu">
        <div class="notification-dropdown">
            <div class="notification-dropdown__header">
                <div>
                    <strong>Notifications</strong>
                </div>
                <div class="notification-dropdown__header-actions">
                    <?php if ($notificationUnreadCount > 0): ?>
                        <span class="badge-soft"><?= e((string) $notificationUnreadCount) ?> unread</span>
                    <?php endif; ?>
                    <a href="<?= e(url('notifications')) ?>" class="small">View all</a>
                </div>
            </div>
            <?php if ($notificationUnreadCount > 0): ?>
                <form action="<?= e(url('notifications/read')) ?>" method="post" class="notification-dropdown__toolbar">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Mark all read</button>
                </form>
            <?php endif; ?>
            <?php if ($notifications === []): ?>
                <div class="notification-dropdown__empty">No notifications yet.</div>
            <?php else: ?>
                <div class="notification-dropdown__list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        [$notificationIcon, $notificationTone, $notificationTypeLabel] = $notificationTypeMeta($notification);
                        $notificationIsUnread = !(bool) ($notification['is_read'] ?? false);
                        $notificationCreatedAt = trim((string) ($notification['created_at'] ?? ''));
                        $notificationCreatedLabel = $notificationCreatedAt !== '' ? date('M d, H:i', strtotime($notificationCreatedAt)) : 'New';
                        ?>
                        <a href="<?= e(url('notifications/open?id=' . (int) $notification['id'])) ?>" class="notification-dropdown__item <?= $notificationIsUnread ? 'is-unread' : '' ?>">
                            <span class="notification-dropdown__icon notification-item__icon notification-item__icon--<?= e($notificationTone) ?>">
                                <i class="bi <?= e($notificationIcon) ?>"></i>
                            </span>
                            <span class="notification-dropdown__content">
                                <span class="notification-dropdown__row">
                                    <span class="notification-dropdown__title"><?= e($notification['title']) ?></span>
                                    <span class="notification-dropdown__time"><?= e($notificationCreatedLabel) ?></span>
                                </span>
                                <span class="notification-dropdown__message"><?= e($notification['message']) ?></span>
                                <span class="notification-dropdown__meta">
                                    <span class="badge-soft notification-chip"><?= e($notificationTypeLabel) ?></span>
                                    <?php if ($notificationIsUnread): ?>
                                        <span class="notification-dot"></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
};

$notificationDropdownHtml = $renderNotificationDropdown($notifications, $notificationUnreadCount);
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$productsCategoriesActive = str_contains($requestUri, '/products/categories');
$productsActive = str_contains($requestUri, '/products') && !$productsCategoriesActive;
$bodyClasses = [];
if (str_contains($requestUri, '/pos')) {
    $bodyClasses[] = 'page-pos';
}
?>
<!doctype html>
<html lang="en" data-theme="light" data-sidebar-state="expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $brandName) ?></title>
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans/latin-400.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans/latin-500.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans/latin-600.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans/latin-700.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans-condensed/latin-500.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans-condensed/latin-600.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/@fontsource/ibm-plex-sans-condensed/latin-700.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/bootstrap-icons/font/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/datatables/css/dataTables.bootstrap5.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/datatables/css/responsive.bootstrap5.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/datatables/css/buttons.bootstrap5.min.css')) ?>" rel="stylesheet">
    <script>
        (function () {
            try {
                var savedTheme = window.localStorage.getItem('novapos-theme');
                if (savedTheme === 'dark' || savedTheme === 'light') {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
                var sidebarState = window.localStorage.getItem('novapos-sidebar-state');
                if (sidebarState === 'collapsed' || sidebarState === 'expanded') {
                    document.documentElement.setAttribute('data-sidebar-state', sidebarState);
                }
            } catch (error) {
                document.documentElement.setAttribute('data-theme', document.documentElement.getAttribute('data-theme') || 'light');
                document.documentElement.setAttribute('data-sidebar-state', 'expanded');
            }
        }());
    </script>
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>" data-barcode-format="<?= e($barcodeFormat) ?>" data-brand-name="<?= e($brandName) ?>">
<div class="app-loading-bar" data-loading-bar aria-hidden="true"></div>
<div class="app-loader" data-app-loader aria-hidden="true" role="status" aria-live="polite" aria-atomic="true">
    <div class="app-loader__scrim"></div>
    <div class="app-loader__panel">
        <div class="app-loader__brand">
            <div class="app-loader__mark">
                <?php if ($brandLogo !== ''): ?>
                    <img src="<?= e(url($brandLogo)) ?>" alt="<?= e($brandName) ?>" class="brand-logo brand-logo--mini">
                <?php else: ?>
                    <div class="brand-mark brand-mark--mini"><?= e($brandInitials) ?></div>
                <?php endif; ?>
            </div>
            <div class="app-loader__copy">
                <div class="app-loader__eyebrow">Secure Workspace</div>
                <h2 class="app-loader__title mb-0" data-loader-title><?= e($brandName) ?></h2>
            </div>
        </div>
        <p class="app-loader__detail mb-0" data-loader-detail>Loading workspace</p>
        <div class="app-loader__indicator" aria-hidden="true">
            <div class="spinner-border app-loader__spinner" role="presentation"></div>
            <div class="app-loader__progress-track"><span class="app-loader__progress-bar"></span></div>
        </div>
        <div class="app-loader__meta">
            <span data-loader-stage>Initializing secure session</span>
            <span class="app-loader__elapsed" data-loader-elapsed>0s</span>
        </div>
        <div class="visually-hidden" data-loader-live>Loading <?= e($brandName) ?></div>
    </div>
</div>
<?php if ($layoutMode === 'auth'): ?>
    <main class="auth-shell">
        <div class="auth-shell__glow"></div>
        <div class="auth-shell__stack">
            <?php if ($flashSuccess !== null || $flashError !== null || $flashWarning !== null || $flashInfo !== null || $flashResetLink !== null || $flashVerificationLink !== null): ?>
                <div class="auth-flash-stack">
                    <?php foreach ([
                        'success' => $flashSuccess,
                        'error' => $flashError,
                        'warning' => $flashWarning,
                        'info' => $flashInfo,
                    ] as $type => $message): ?>
                        <?php if ($message !== null && $message !== ''): ?>
                            <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> rounded-4 mb-0">
                                <?= e((string) $message) ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($flashResetLink !== null && $flashResetLink !== ''): ?>
                        <div class="surface-card card-panel auth-flash-card">
                            <div class="table-kicker"><i class="bi bi-link-45deg"></i>Local Reset Link</div>
                            <div class="small text-muted">Mail delivery is not configured, so the recovery link is available here for local testing.</div>
                            <a href="<?= e((string) $flashResetLink) ?>" class="auth-flash-card__link"><?= e((string) $flashResetLink) ?></a>
                        </div>
                    <?php endif; ?>

                    <?php if ($flashVerificationLink !== null && $flashVerificationLink !== ''): ?>
                        <div class="surface-card card-panel auth-flash-card">
                            <div class="table-kicker"><i class="bi bi-envelope-check"></i>Local Verification Link</div>
                            <div class="small text-muted">Mail delivery is not configured, so the verification link is available here for local testing.</div>
                            <a href="<?= e((string) $flashVerificationLink) ?>" class="auth-flash-card__link"><?= e((string) $flashVerificationLink) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>

            <footer class="app-footer card border-0 shadow-sm auth-footer">
                <div class="app-footer__inner d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                    <div class="app-footer__copy">
                        <div class="small text-muted">&copy; <?= e(date('Y')) ?> <?= e($brandName) ?></div>
                        <div class="small text-muted"><?= e($developerCredit) ?></div>
                    </div>
                    <div class="small text-muted">Secure access portal</div>
                </div>
            </footer>
        </div>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar sidebar--desktop card border-0 shadow-sm d-none d-md-flex" aria-label="Primary">
            <div class="sidebar__top">
                <button type="button" class="btn btn-outline-secondary sidebar-toggle" data-sidebar-toggle aria-label="Collapse sidebar" title="Collapse sidebar">
                    <i class="bi bi-layout-sidebar-inset"></i>
                    <span class="sidebar-toggle__label">Collapse</span>
                </button>
                <div class="brand-lockup">
                    <?php if ($brandLogo !== ''): ?>
                        <img src="<?= e(url($brandLogo)) ?>" alt="<?= e($brandName) ?>" class="brand-logo">
                    <?php else: ?>
                        <div class="brand-mark"><?= e($brandInitials) ?></div>
                    <?php endif; ?>
                    <div class="brand-copy">
                        <h1 class="brand-title"><?= e($brandName) ?></h1>
                        <p class="brand-tag">Operations</p>
                    </div>
                </div>
                <div class="sidebar-meta">
                    <span class="badge-soft"><i class="bi bi-shield-lock"></i><span class="sidebar-meta__label"><?= e($user['role_name'] ?? 'User') ?></span></span>
                    <span class="badge-soft"><i class="bi bi-diagram-3"></i><span class="sidebar-meta__label"><?= e($user['branch_name'] ?? 'All Branches') ?></span></span>
                </div>
            </div>

            <nav class="nav flex-column gap-2 nav-cluster sidebar-nav">
                <div class="nav-section-label">Overview</div>
                <?php if (can_permission('view_dashboard')): ?>
                    <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
                <?php endif; ?>

                <?php if (can_permission(['manage_inventory', 'manage_products'])): ?>
                    <div class="nav-section-label">Operations</div>
                    <?php if (can_permission('manage_inventory')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($requestUri, '/inventory') || str_contains($requestUri, '/suppliers') ? 'active' : '' ?>" href="<?= e(url('inventory')) ?>"><i class="bi bi-box-seam"></i>Inventory</a>
                    <?php endif; ?>
                    <?php if (can_permission('manage_products')): ?>
                        <a class="nav-link custom-nav-link <?= $productsActive ? 'active' : '' ?>" href="<?= e(url('products')) ?>"><i class="bi bi-tags"></i>Products</a>
                        <a class="nav-link custom-nav-link <?= $productsCategoriesActive ? 'active' : '' ?>" href="<?= e(url('products/categories')) ?>"><i class="bi bi-diagram-3"></i>Categories</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (can_permission(['manage_customers', 'manage_sales', 'access_pos'])): ?>
                    <div class="nav-section-label">Commerce</div>
                    <?php if (can_permission('manage_customers')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/customers') ? 'active' : '' ?>" href="<?= e(url('customers')) ?>"><i class="bi bi-people"></i>Customers</a>
                    <?php endif; ?>
                    <?php if (can_permission('manage_sales')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/sales') ? 'active' : '' ?>" href="<?= e(url('sales')) ?>"><i class="bi bi-cart"></i>Sales</a>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/returns') ? 'active' : '' ?>" href="<?= e(url('returns')) ?>"><i class="bi bi-arrow-counterclockwise"></i>Returns</a>
                    <?php endif; ?>
                    <?php if (can_permission('access_pos')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/pos') ? 'active' : '' ?>" href="<?= e(url('pos')) ?>"><i class="bi bi-cash-stack"></i>POS Terminal</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (can_permission(['manage_expenses', 'manage_reports'])): ?>
                    <div class="nav-section-label">Insights</div>
                    <?php if (can_permission('manage_expenses')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/expenses') ? 'active' : '' ?>" href="<?= e(url('expenses')) ?>"><i class="bi bi-receipt"></i>Expenses</a>
                    <?php endif; ?>
                    <?php if (can_permission('manage_reports')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports') ? 'active' : '' ?>" href="<?= e(url('reports')) ?>"><i class="bi bi-graph-up"></i>Reports</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (can_permission(['manage_users', 'manage_settings'])): ?>
                    <div class="nav-section-label">Administration</div>
                    <?php if (can_permission('manage_users')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/users') ? 'active' : '' ?>" href="<?= e(url('users')) ?>"><i class="bi bi-person-badge"></i>Users</a>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/audit-logs') ? 'active' : '' ?>" href="<?= e(url('audit-logs')) ?>"><i class="bi bi-clipboard-data"></i>Audit Trail</a>
                    <?php endif; ?>
                    <?php if (can_permission('manage_settings')): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/billing') ? 'active' : '' ?>" href="<?= e(url('billing')) ?>"><i class="bi bi-credit-card-2-front"></i>Billing</a>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/settings') ? 'active' : '' ?>" href="<?= e(url('settings')) ?>"><i class="bi bi-gear"></i>Settings</a>
                    <?php endif; ?>
                    <?php if ($isPlatformAdmin): ?>
                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/platform') ? 'active' : '' ?>" href="<?= e(url('platform')) ?>"><i class="bi bi-buildings"></i>Platform Admin</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="sidebar-card d-none d-md-block">
                <div class="text-uppercase small text-muted">Signed in as</div>
                <div class="fw-semibold"><?= e($user['full_name'] ?? 'User') ?></div>
                <div class="small text-muted"><?= e(($user['role_name'] ?? '') . (($user['branch_name'] ?? '') !== '' ? ' | ' . $user['branch_name'] : '')) ?></div>
            </div>
        </aside>

        <div class="offcanvas offcanvas-start mobile-sidebar-panel shadow-lg" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" data-bs-scroll="true">
            <div class="offcanvas-header mobile-sidebar-panel__header border-0 border-bottom">
                <div class="mobile-sidebar-panel__brand d-flex align-items-center gap-3 min-w-0">
                    <?php if ($brandLogo !== ''): ?>
                        <img src="<?= e(url($brandLogo)) ?>" alt="<?= e($brandName) ?>" class="brand-logo brand-logo--mini">
                    <?php else: ?>
                        <div class="brand-mark brand-mark--mini"><?= e($brandInitials) ?></div>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <h5 class="offcanvas-title mobile-sidebar-panel__title mb-0" id="mobileSidebarLabel"><?= e($brandName) ?></h5>
                        <div class="small text-muted mobile-sidebar-panel__subtitle">Menu</div>
                    </div>
                </div>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body mobile-sidebar-panel__body p-0">
                <aside class="sidebar sidebar--mobile d-flex flex-column h-100">
                    <div class="mobile-sidebar-hero">
                        <div class="mobile-sidebar-hero__identity">
                            <div class="mobile-sidebar-hero__avatar"><?= e($userInitials) ?></div>
                            <div class="mobile-sidebar-hero__copy min-w-0">
                                <div class="mobile-sidebar-hero__eyebrow">Signed in as</div>
                                <div class="mobile-sidebar-hero__name"><?= e($user['full_name'] ?? 'User') ?></div>
                                <div class="mobile-sidebar-hero__meta"><?= e(($user['role_name'] ?? '') . (($user['branch_name'] ?? '') !== '' ? ' | ' . $user['branch_name'] : '')) ?></div>
                            </div>
                        </div>
                        <?php if (can_permission('access_pos')): ?>
                            <a href="<?= e(url('pos')) ?>" class="btn btn-primary mobile-sidebar-hero__cta offcanvas-link"><i class="bi bi-plus-lg me-2"></i>Start New Sale</a>
                        <?php endif; ?>
                    </div>

                    <nav class="mobile-nav-stack" aria-label="Mobile navigation">
                        <section class="mobile-nav-group">
                            <div class="mobile-nav-group__label">Overview</div>
                            <div class="mobile-nav-group__links">
                                <?php if (can_permission('view_dashboard')): ?>
                                    <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('dashboard')) ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
                                <?php endif; ?>
                            </div>
                        </section>

                        <?php if (can_permission(['manage_inventory', 'manage_products'])): ?>
                            <section class="mobile-nav-group">
                                <div class="mobile-nav-group__label">Operations</div>
                                <div class="mobile-nav-group__links">
                                    <?php if (can_permission('manage_inventory')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($requestUri, '/inventory') || str_contains($requestUri, '/suppliers') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('inventory')) ?>"><i class="bi bi-box-seam"></i>Inventory</a>
                                    <?php endif; ?>
                                    <?php if (can_permission('manage_products')): ?>
                                        <a class="nav-link custom-nav-link <?= $productsActive ? 'active' : '' ?> offcanvas-link" href="<?= e(url('products')) ?>"><i class="bi bi-tags"></i>Products</a>
                                        <a class="nav-link custom-nav-link <?= $productsCategoriesActive ? 'active' : '' ?> offcanvas-link" href="<?= e(url('products/categories')) ?>"><i class="bi bi-diagram-3"></i>Categories</a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if (can_permission(['manage_customers', 'manage_sales', 'access_pos'])): ?>
                            <section class="mobile-nav-group">
                                <div class="mobile-nav-group__label">Commerce</div>
                                <div class="mobile-nav-group__links">
                                    <?php if (can_permission('manage_customers')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/customers') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('customers')) ?>"><i class="bi bi-people"></i>Customers</a>
                                    <?php endif; ?>
                                    <?php if (can_permission('manage_sales')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/sales') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('sales')) ?>"><i class="bi bi-cart"></i>Sales</a>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/returns') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('returns')) ?>"><i class="bi bi-arrow-counterclockwise"></i>Returns</a>
                                    <?php endif; ?>
                                    <?php if (can_permission('access_pos')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/pos') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('pos')) ?>"><i class="bi bi-cash-stack"></i>POS Terminal</a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if (can_permission(['manage_expenses', 'manage_reports'])): ?>
                            <section class="mobile-nav-group">
                                <div class="mobile-nav-group__label">Insights</div>
                                <div class="mobile-nav-group__links">
                                    <?php if (can_permission('manage_expenses')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/expenses') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('expenses')) ?>"><i class="bi bi-receipt"></i>Expenses</a>
                                    <?php endif; ?>
                                    <?php if (can_permission('manage_reports')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('reports')) ?>"><i class="bi bi-graph-up"></i>Reports</a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if (can_permission(['manage_users', 'manage_settings'])): ?>
                            <section class="mobile-nav-group">
                                <div class="mobile-nav-group__label">Administration</div>
                                <div class="mobile-nav-group__links">
                                    <?php if (can_permission('manage_users')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/users') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('users')) ?>"><i class="bi bi-person-badge"></i>Users</a>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/audit-logs') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('audit-logs')) ?>"><i class="bi bi-clipboard-data"></i>Audit Trail</a>
                                    <?php endif; ?>
                                    <?php if (can_permission('manage_settings')): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/billing') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('billing')) ?>"><i class="bi bi-credit-card-2-front"></i>Billing</a>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/settings') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('settings')) ?>"><i class="bi bi-gear"></i>Settings</a>
                                    <?php endif; ?>
                                    <?php if ($isPlatformAdmin): ?>
                                        <a class="nav-link custom-nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/platform') ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform')) ?>"><i class="bi bi-buildings"></i>Platform Admin</a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </nav>

                    <div class="mobile-sidebar-actions d-md-none">
                        <button type="button" class="btn btn-outline-secondary" data-theme-toggle><i class="bi bi-moon-stars me-2"></i>Toggle Theme</button>
                        <form action="<?= e(url('logout')) ?>" method="post" data-loading-mode="logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>

        <div class="main-shell">
            <header class="topbar navbar navbar-expand-md card border-0 shadow-sm">
                <div class="topbar-mobile d-md-none">
                    <div class="topbar-mobile__row">
                        <div class="topbar-menu d-flex align-items-center">
                            <button class="btn btn-outline-secondary topbar-menu__button" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open menu">
                                <i class="bi bi-list fs-5"></i>
                            </button>
                        </div>

                        <div class="topbar-mobile__copy">
                            <h2 class="page-title mb-0"><?= e($pageTitle) ?></h2>
                        </div>

                        <div class="topbar-actions topbar-actions--mobile">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary position-relative topbar-alerts" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Alerts">
                                    <i class="bi bi-bell-fill"></i>
                                    <?php if ($notificationUnreadCount > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e((string) $notificationUnreadCount) ?></span>
                                    <?php endif; ?>
                                </button>
                                <?= $notificationDropdownHtml ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary topbar-mobile-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Quick actions">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--mobile-actions">
                                    <div class="topbar-menu-panel__header">
                                        <div>
                                            <strong>Quick Actions</strong>
                                            <div class="small text-muted">Common tasks and account controls</div>
                                        </div>
                                    </div>
                                    <div class="topbar-menu-panel__actions">
                                        <button type="button" class="dropdown-item topbar-dropdown-item" data-theme-toggle>
                                            <i class="bi bi-moon-stars"></i><span class="theme-toggle__label">Theme</span>
                                        </button>
                                        <?php if (can_permission('access_pos')): ?>
                                            <a href="<?= e(url('pos')) ?>" class="dropdown-item topbar-dropdown-item">
                                                <i class="bi bi-plus-circle"></i><span>Start New Sale</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isPlatformAdmin): ?>
                                            <a href="<?= e(url('platform')) ?>" class="dropdown-item topbar-dropdown-item">
                                                <i class="bi bi-buildings"></i><span>Platform Admin</span>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= e(url('notifications')) ?>" class="dropdown-item topbar-dropdown-item">
                                            <i class="bi bi-bell"></i><span>Open Notifications</span>
                                        </a>
                                        <form action="<?= e(url('logout')) ?>" method="post" class="topbar-dropdown-form" data-loading-mode="logout">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item topbar-dropdown-item topbar-dropdown-item--danger">
                                                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="topbar-mobile__meta">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary topbar-mobile-workspace-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-grid-1x2"></i>
                                <span><?= e($user['branch_name'] ?? 'All Branches') ?></span>
                                <small><?= e(date('D, d M Y')) ?></small>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--workspace">
                                <div class="topbar-menu-panel__header">
                                    <div>
                                        <strong>Workspace</strong>
                                        <div class="small text-muted">Current session context</div>
                                    </div>
                                </div>
                                <div class="topbar-menu-panel__grid">
                                    <div class="topbar-menu-panel__item">
                                        <span>Branch</span>
                                        <strong><?= e($user['branch_name'] ?? 'All Branches') ?></strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>Role</span>
                                        <strong><?= e($user['role_name'] ?? 'User') ?></strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>Date</span>
                                        <strong><?= e(date('D, d M Y')) ?></strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>Business</span>
                                        <strong><?= e($brandName) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($breadcrumbs !== []): ?>
                        <nav class="topbar-mobile__trail" aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item active"><?= e($crumb) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="topbar-desktop d-none d-md-grid">
                    <div class="topbar-desktop__main">
                        <div class="topbar-copy">
                            <div class="topbar-title-row">
                                <h2 class="page-title mb-0"><?= e($pageTitle) ?></h2>
                            </div>
                            <?php if ($breadcrumbs !== []): ?>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0 mt-2">
                                        <?php foreach ($breadcrumbs as $crumb): ?>
                                            <li class="breadcrumb-item active"><?= e($crumb) ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </nav>
                            <?php endif; ?>
                        </div>

                        <div class="topbar-utility">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary topbar-workspace-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-grid-1x2"></i>
                                    <span>
                                        <strong><?= e($user['branch_name'] ?? 'All Branches') ?></strong>
                                        <small><?= e(date('D, d M Y')) ?></small>
                                    </span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--workspace">
                                    <div class="topbar-menu-panel__header">
                                        <div>
                                            <strong>Workspace</strong>
                                            <div class="small text-muted">Current session context</div>
                                        </div>
                                    </div>
                                    <div class="topbar-menu-panel__grid">
                                        <div class="topbar-menu-panel__item">
                                            <span>Branch</span>
                                            <strong><?= e($user['branch_name'] ?? 'All Branches') ?></strong>
                                        </div>
                                        <div class="topbar-menu-panel__item">
                                            <span>Role</span>
                                            <strong><?= e($user['role_name'] ?? 'User') ?></strong>
                                        </div>
                                        <div class="topbar-menu-panel__item">
                                            <span>Date</span>
                                            <strong><?= e(date('D, d M Y')) ?></strong>
                                        </div>
                                        <div class="topbar-menu-panel__item">
                                            <span>Business</span>
                                            <strong><?= e($brandName) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="topbar-desktop__sub">
                        <div class="topbar-summary" aria-label="Current access">
                            <span class="topbar-summary__chip"><i class="bi bi-shield-lock"></i><span><?= e($user['role_name'] ?? 'User') ?></span></span>
                        </div>

                        <div class="topbar-actions topbar-actions--desktop">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary position-relative topbar-alerts" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-bell-fill me-0 me-md-1"></i><span class="topbar-action-label">Alerts</span>
                                    <?php if ($notificationUnreadCount > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e((string) $notificationUnreadCount) ?></span>
                                    <?php endif; ?>
                                </button>
                                <?= $notificationDropdownHtml ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary topbar-menu-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-lightning-charge"></i><span class="topbar-action-label">Quick Actions</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--actions">
                                    <div class="topbar-menu-panel__header">
                                        <div>
                                            <strong>Quick Actions</strong>
                                            <div class="small text-muted">Common tasks and account controls</div>
                                        </div>
                                    </div>
                                    <div class="topbar-menu-panel__actions">
                                        <button type="button" class="dropdown-item topbar-dropdown-item" data-theme-toggle>
                                            <i class="bi bi-moon-stars"></i><span class="theme-toggle__label">Theme</span>
                                        </button>
                                        <?php if (can_permission('access_pos')): ?>
                                            <a href="<?= e(url('pos')) ?>" class="dropdown-item topbar-dropdown-item">
                                                <i class="bi bi-plus-circle"></i><span>Start New Sale</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isPlatformAdmin): ?>
                                            <a href="<?= e(url('platform')) ?>" class="dropdown-item topbar-dropdown-item">
                                                <i class="bi bi-buildings"></i><span>Platform Admin</span>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= e(url('notifications')) ?>" class="dropdown-item topbar-dropdown-item">
                                            <i class="bi bi-bell"></i><span>Open Notifications</span>
                                        </a>
                                        <form action="<?= e(url('logout')) ?>" method="post" class="topbar-dropdown-form" data-loading-mode="logout">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item topbar-dropdown-item topbar-dropdown-item--danger">
                                                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="page-body">
                <?php if ($isImpersonating): ?>
                    <section class="surface-card card-panel mb-0">
                        <div class="workspace-panel__header">
                            <div class="workspace-panel__intro">
                                <p class="eyebrow mb-1">Support Access Active</p>
                                <h3><i class="bi bi-person-workspace me-2"></i><?= e((string) ($impersonationMeta['target_company_name'] ?? ($user['company_name'] ?? 'Tenant Workspace'))) ?></h3>
                            </div>
                            <div class="workspace-panel__actions">
                                <span class="badge-soft"><i class="bi bi-clock-history"></i><?= e((string) ($impersonationMeta['started_at'] ?? '')) ?></span>
                                <form action="<?= e(url('platform/impersonation/stop')) ?>" method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle me-1"></i>Return to Platform</button>
                                </form>
                            </div>
                        </div>
                        <div class="small text-muted">
                            Acting as <?= e((string) ($impersonationMeta['target_user_name'] ?? ($user['full_name'] ?? 'tenant user'))) ?>.
                            Reason: <?= e((string) ($impersonationMeta['reason'] ?? 'Support access')) ?>.
                        </div>
                    </section>
                <?php endif; ?>
                <?= $content ?>
            </main>

            <footer class="app-footer card border-0 shadow-sm mt-2">
                <div class="app-footer__inner d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                    <div class="app-footer__copy">
                        <div class="small text-muted">&copy; <?= e(date('Y')) ?> <?= e($brandName) ?></div>
                        <div class="small text-muted"><?= e($developerCredit) ?></div>
                    </div>
                    <div class="small text-muted"><?= e($user['branch_name'] ?? 'All Branches') ?> | <?= e($user['role_name'] ?? 'User') ?></div>
                </div>
            </footer>
        </div>
    </div>
<?php endif; ?>

<div class="position-fixed top-0 end-0 p-3 toast-stack">
    <?php foreach (['success' => $flashSuccess, 'error' => $flashError, 'warning' => $flashWarning] as $type => $message): ?>
        <?php if ($message): ?>
            <div class="toast align-items-center text-bg-<?= $type === 'error' ? 'danger' : ($type === 'warning' ? 'warning' : 'success') ?> border-0" role="alert" data-autohide="true">
                <div class="d-flex">
                    <div class="toast-body"><?= e($message) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="modal fade global-modal" id="globalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-panel">
            <div class="modal-header">
                <h5 class="modal-title" id="globalModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" id="globalModalBody"></div>
            <div class="modal-footer" id="globalModalFooter"></div>
        </div>
    </div>
</div>

<script src="<?= e(asset('vendor/alpinejs/cdn.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/chart.js/chart.umd.js')) ?>"></script>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/dataTables.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/dataTables.responsive.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/responsive.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/jszip/jszip.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/pdfmake/pdfmake.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/pdfmake/vfs_fonts.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/dataTables.buttons.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/buttons.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/buttons.html5.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/js/buttons.print.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/jsbarcode/JsBarcode.all.min.js')) ?>"></script>
<?php if (in_array('page-pos', $bodyClasses, true)): ?>
<script src="<?= e(asset('js/pos.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/inventory.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
