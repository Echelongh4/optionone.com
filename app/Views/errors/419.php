<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Session expired') ?></title>
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
    <link href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/bootstrap-icons/font/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="error-body">
<div class="error-shell">
    <section class="error-card card-panel">
        <div class="error-kicker"><span class="error-code">419</span><span class="error-label">Session Timeout</span></div>
        <div class="error-icon"><i class="bi bi-arrow-clockwise"></i></div>
        <div class="error-heading">
            <h1><?= e($title ?? 'Session expired') ?></h1>
            <p><?= e($message ?? 'Your session token expired before the request could complete.') ?></p>
        </div>
        <div class="error-actions">
            <button type="button" onclick="window.location.reload()" class="btn btn-primary">Refresh page</button>
            <a href="<?= e(url('dashboard')) ?>" class="btn btn-outline-secondary">Back to dashboard</a>
        </div>
        <div class="error-note">Refresh the page and try again. If the problem continues, sign in again.</div>
    </section>
</div>
</body>
</html>
