<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;

$flashSuccess = Session::pullFlash('success');
$flashError = Session::pullFlash('error');
$flashWarning = Session::pullFlash('warning');
$flashInfo = Session::pullFlash('info');
$flashVerificationLink = Session::pullFlash('verification_link');
$user = current_user();
$pageTitle = (string) ($title ?? 'Platform Admin');
$platformName = (string) setting_value('business_name', config('app.name', 'NovaPOS'));
$platformTag = 'Platform Admin';
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isCompaniesPage = str_contains($requestUri, '/platform/companies');
$isAdminUsersPage = str_contains($requestUri, '/platform/admin-users');
$isSettingsPage = str_contains($requestUri, '/platform/settings');
$isBillingPage = str_contains($requestUri, '/platform/billing');
$isPlatformHome = str_contains($requestUri, '/platform') && !$isCompaniesPage && !$isAdminUsersPage && !$isBillingPage && !$isSettingsPage;
$canAccessWorkspace = $user !== null && Auth::hasPermission('view_dashboard');
$bodyClasses = ['page-platform'];
if ($isPlatformHome) {
    $bodyClasses[] = 'page-platform-overview';
}
if ($isSettingsPage) {
    $bodyClasses[] = 'page-platform-settings';
}
if ($isBillingPage) {
    $bodyClasses[] = 'page-platform-billing';
}
if ($isCompaniesPage) {
    $bodyClasses[] = 'page-platform-companies';
}
if ($isAdminUsersPage) {
    $bodyClasses[] = 'page-platform-admin-users';
}
$platformWords = preg_split('/\s+/', trim($platformName)) ?: [];
$platformInitials = '';
foreach ($platformWords as $platformWord) {
    if ($platformWord === '') {
        continue;
    }

    $platformInitials .= strtoupper(substr($platformWord, 0, 1));
    if (strlen($platformInitials) >= 2) {
        break;
    }
}
$platformInitials = $platformInitials !== '' ? substr($platformInitials, 0, 2) : 'NP';
$userInitials = strtoupper(substr((string) ($user['first_name'] ?? 'N'), 0, 1) . substr((string) ($user['last_name'] ?? 'P'), 0, 1));
$todayLabel = date('D, d M Y');
$platformContextLabel = 'Cross-company control plane';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
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
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }());
    </script>
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>" data-brand-name="<?= e($platformName) ?>">
    <div class="app-shell platform-shell">
        <aside class="sidebar sidebar--desktop sidebar--platform card border-0 shadow-sm d-none d-lg-flex" aria-label="Platform navigation">
            <div class="sidebar__top">
                <div class="brand-lockup">
                    <div class="brand-mark"><?= e($platformInitials) ?></div>
                    <div class="brand-copy">
                        <h1 class="brand-title"><?= e($platformName) ?></h1>
                        <p class="brand-tag"><?= e($platformTag) ?></p>
                    </div>
                </div>
                <div class="sidebar-meta">
                    <span class="badge-soft"><i class="bi bi-shield-lock"></i><span class="sidebar-meta__label">Platform Admin</span></span>
                    <span class="badge-soft"><i class="bi bi-person-circle"></i><span class="sidebar-meta__label"><?= e($user['full_name'] ?? 'User') ?></span></span>
                </div>
            </div>

            <nav class="nav flex-column gap-2 nav-cluster sidebar-nav">
                <div class="nav-section-label">Platform</div>
                <a class="nav-link custom-nav-link <?= $isPlatformHome ? 'active' : '' ?>" href="<?= e(url('platform')) ?>"><i class="bi bi-grid-1x2"></i>Overview</a>
                <a class="nav-link custom-nav-link <?= $isSettingsPage ? 'active' : '' ?>" href="<?= e(url('platform/settings')) ?>"><i class="bi bi-sliders"></i>General Settings</a>
                <a class="nav-link custom-nav-link <?= $isBillingPage ? 'active' : '' ?>" href="<?= e(url('platform/billing')) ?>"><i class="bi bi-credit-card"></i>Billing</a>
                <a class="nav-link custom-nav-link <?= $isCompaniesPage ? 'active' : '' ?>" href="<?= e(url('platform/companies')) ?>"><i class="bi bi-buildings"></i>Companies</a>
                <a class="nav-link custom-nav-link <?= $isAdminUsersPage ? 'active' : '' ?>" href="<?= e(url('platform/admin-users')) ?>"><i class="bi bi-people"></i>Admin Users</a>

                <?php if ($canAccessWorkspace): ?>
                    <div class="nav-section-label">Workspace</div>
                    <a class="nav-link custom-nav-link" href="<?= e(url('dashboard')) ?>"><i class="bi bi-shop"></i>Tenant Dashboard</a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-card d-none d-lg-block">
                <div class="text-uppercase small text-muted">Session</div>
                <div class="fw-semibold"><?= e($user['full_name'] ?? 'User') ?></div>
                <div class="small text-muted"><?= e($user['email'] ?? '') ?></div>
            </div>
        </aside>

        <div class="offcanvas offcanvas-start mobile-sidebar-panel mobile-sidebar-panel--platform shadow-lg" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" data-bs-scroll="true">
            <div class="offcanvas-header mobile-sidebar-panel__header border-0 border-bottom">
                <div class="mobile-sidebar-panel__brand d-flex align-items-center gap-3 min-w-0">
                    <div class="brand-mark brand-mark--mini"><?= e($platformInitials) ?></div>
                    <div class="min-w-0">
                        <h5 class="offcanvas-title mobile-sidebar-panel__title mb-0" id="mobileSidebarLabel"><?= e($platformName) ?></h5>
                        <div class="small text-muted mobile-sidebar-panel__subtitle"><?= e($platformTag) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body mobile-sidebar-panel__body p-0">
                <aside class="sidebar sidebar--mobile sidebar--platform-mobile d-flex flex-column h-100">
                    <div class="mobile-sidebar-hero mobile-sidebar-hero--platform">
                        <div class="mobile-sidebar-hero__identity">
                            <div class="mobile-sidebar-hero__avatar"><?= e($userInitials) ?></div>
                            <div class="mobile-sidebar-hero__copy min-w-0">
                                <div class="mobile-sidebar-hero__eyebrow">Signed in as</div>
                                <div class="mobile-sidebar-hero__name"><?= e($user['full_name'] ?? 'Platform User') ?></div>
                                <div class="mobile-sidebar-hero__meta"><?= e($user['email'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="mobile-sidebar-hero__pills">
                            <span class="badge-soft"><i class="bi bi-shield-lock me-1"></i>Platform Admin</span>
                            <span class="badge-soft"><i class="bi bi-diagram-3 me-1"></i><?= e($platformContextLabel) ?></span>
                        </div>
                        <?php if ($canAccessWorkspace): ?>
                            <a href="<?= e(url('dashboard')) ?>" class="btn btn-primary mobile-sidebar-hero__cta offcanvas-link"><i class="bi bi-shop me-2"></i>Open Tenant Dashboard</a>
                        <?php endif; ?>
                    </div>

                    <nav class="mobile-nav-stack" aria-label="Platform mobile navigation">
                        <section class="mobile-nav-group">
                            <div class="mobile-nav-group__label">Platform</div>
                            <div class="mobile-nav-group__links">
                                <a class="nav-link custom-nav-link <?= $isPlatformHome ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform')) ?>"><i class="bi bi-grid-1x2"></i>Overview</a>
                                <a class="nav-link custom-nav-link <?= $isSettingsPage ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform/settings')) ?>"><i class="bi bi-sliders"></i>General Settings</a>
                                <a class="nav-link custom-nav-link <?= $isBillingPage ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform/billing')) ?>"><i class="bi bi-credit-card"></i>Billing</a>
                                <a class="nav-link custom-nav-link <?= $isCompaniesPage ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform/companies')) ?>"><i class="bi bi-buildings"></i>Companies</a>
                                <a class="nav-link custom-nav-link <?= $isAdminUsersPage ? 'active' : '' ?> offcanvas-link" href="<?= e(url('platform/admin-users')) ?>"><i class="bi bi-people"></i>Admin Users</a>
                            </div>
                        </section>

                        <?php if ($canAccessWorkspace): ?>
                            <section class="mobile-nav-group">
                                <div class="mobile-nav-group__label">Workspace</div>
                                <div class="mobile-nav-group__links">
                                    <a class="nav-link custom-nav-link offcanvas-link" href="<?= e(url('dashboard')) ?>"><i class="bi bi-shop"></i>Tenant Dashboard</a>
                                </div>
                            </section>
                        <?php endif; ?>
                    </nav>

                    <div class="mobile-sidebar-actions d-lg-none">
                        <button type="button" class="btn btn-outline-secondary" data-theme-toggle><i class="bi bi-moon-stars me-2"></i>Toggle Theme</button>
                        <form action="<?= e(url('logout')) ?>" method="post" data-loading-mode="logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>

        <div class="main-shell main-shell--platform">
            <header class="topbar topbar--platform navbar navbar-expand-md card border-0 shadow-sm">
                <div class="topbar-desktop d-none d-lg-grid">
                    <div class="topbar-desktop__main">
                        <div class="topbar-copy">
                            <div class="topbar-title-row">
                                <h2 class="page-title mb-0"><?= e($pageTitle) ?></h2>
                            </div>
                            <?php if (!empty($breadcrumbs)): ?>
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
                            <span class="topbar-utility__chip"><i class="bi bi-diagram-3"></i><?= e($platformContextLabel) ?></span>
                        </div>
                    </div>

                    <div class="topbar-desktop__sub">
                        <div class="topbar-summary" aria-label="Current access">
                            <span class="topbar-summary__chip"><i class="bi bi-shield-lock"></i><span>Platform Admin</span></span>
                            <?php if ($user !== null): ?>
                                <span class="topbar-summary__chip"><i class="bi bi-envelope"></i><span><?= e($user['email'] ?? '') ?></span></span>
                            <?php endif; ?>
                        </div>

                        <div class="topbar-actions topbar-actions--desktop">
                            <button type="button" class="btn btn-outline-secondary topbar-menu-toggle" data-theme-toggle>
                                <i class="bi bi-moon-stars"></i><span class="topbar-action-label">Theme</span>
                            </button>
                            <?php if ($canAccessWorkspace): ?>
                                <a href="<?= e(url('dashboard')) ?>" class="btn btn-outline-secondary topbar-menu-toggle">
                                    <i class="bi bi-shop"></i><span class="topbar-action-label">Tenant Dashboard</span>
                                </a>
                            <?php endif; ?>
                            <form action="<?= e(url('logout')) ?>" method="post" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger topbar-menu-toggle">
                                    <i class="bi bi-box-arrow-right"></i><span class="topbar-action-label">Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="topbar-mobile d-lg-none">
                    <div class="topbar-mobile__row">
                        <div class="topbar-menu d-flex align-items-center">
                            <button class="btn btn-outline-secondary topbar-menu__button" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open platform navigation">
                                <i class="bi bi-list fs-5"></i>
                            </button>
                        </div>

                        <div class="topbar-mobile__copy">
                            <div class="topbar-mobile__kicker"><?= e($platformTag) ?></div>
                            <h2 class="page-title mb-0"><?= e($pageTitle) ?></h2>
                        </div>

                        <div class="topbar-actions topbar-actions--mobile">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary topbar-mobile-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open quick actions">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--mobile-actions">
                                    <div class="topbar-menu-panel__header">
                                        <div>
                                            <strong>Platform Actions</strong>
                                            <div class="small text-muted">Theme, workspace, and session controls</div>
                                        </div>
                                    </div>
                                    <div class="topbar-menu-panel__actions">
                                        <button type="button" class="dropdown-item topbar-dropdown-item" data-theme-toggle>
                                            <i class="bi bi-moon-stars"></i><span class="theme-toggle__label">Theme</span>
                                        </button>
                                        <?php if ($canAccessWorkspace): ?>
                                            <a href="<?= e(url('dashboard')) ?>" class="dropdown-item topbar-dropdown-item">
                                                <i class="bi bi-shop"></i><span>Tenant Dashboard</span>
                                            </a>
                                        <?php endif; ?>
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
                                <i class="bi bi-diagram-3"></i>
                                <span><?= e($platformContextLabel) ?></span>
                                <small><?= e($todayLabel) ?></small>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end glass-dropdown topbar-menu-panel topbar-menu-panel--workspace">
                                <div class="topbar-menu-panel__header">
                                    <div>
                                        <strong>Current Platform Session</strong>
                                        <div class="small text-muted">Context for this control-plane session</div>
                                    </div>
                                </div>
                                <div class="topbar-menu-panel__grid">
                                    <div class="topbar-menu-panel__item">
                                        <span>Access</span>
                                        <strong>Platform Admin</strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>Page</span>
                                        <strong><?= e($pageTitle) ?></strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>User</span>
                                        <strong><?= e($user['full_name'] ?? 'Platform User') ?></strong>
                                    </div>
                                    <div class="topbar-menu-panel__item">
                                        <span>Date</span>
                                        <strong><?= e($todayLabel) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($breadcrumbs)): ?>
                        <nav class="topbar-mobile__trail" aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item active"><?= e($crumb) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
            </header>

            <main class="page-body page-body--platform">
                <?php if ($flashSuccess !== null || $flashError !== null || $flashWarning !== null || $flashInfo !== null || $flashVerificationLink !== null): ?>
                    <section class="surface-card card-panel mb-0">
                        <div class="stack-grid">
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

                            <?php if ($flashVerificationLink !== null && $flashVerificationLink !== ''): ?>
                                <div class="auth-flash-card">
                                    <div class="table-kicker"><i class="bi bi-envelope-check"></i>Local Verification Link</div>
                                    <div class="small text-muted">Mail delivery is not configured, so the verification link is available here for platform-side onboarding support.</div>
                                    <a href="<?= e((string) $flashVerificationLink) ?>" class="auth-flash-card__link"><?= e((string) $flashVerificationLink) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <footer class="app-footer card border-0 shadow-sm mt-2">
                <div class="app-footer__inner d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                    <div class="app-footer__copy">
                        <div class="small text-muted">&copy; <?= e(date('Y')) ?> <?= e($platformName) ?></div>
                        <div class="small text-muted"><?= e($platformTag) ?></div>
                    </div>
                    <div class="small text-muted d-flex align-items-center gap-2">
                        <span class="badge-soft"><i class="bi bi-person-circle"></i><?= e($userInitials) ?></span>
                        <span><?= e($user['full_name'] ?? 'Platform User') ?></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="<?= e(asset('vendor/alpinejs/cdn.min.js')) ?>" defer></script>
    <script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
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
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
