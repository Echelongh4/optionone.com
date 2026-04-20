<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Application error') ?></title>
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
        <div class="error-kicker"><span class="error-code">Error</span><span class="error-label">Application Issue</span></div>
        <div class="error-icon"><i class="bi bi-exclamation-diamond"></i></div>
        <div class="error-heading">
            <h1><?= e($title ?? 'Application error') ?></h1>
            <p><?= e($message ?? 'An unexpected error occurred while processing your request.') ?></p>
        </div>
        <div class="error-actions">
            <a href="<?= e(url('dashboard')) ?>" class="btn btn-primary">Back to dashboard</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">Try again</button>
        </div>
        <div class="error-note">If this keeps happening, review the logs or contact support with the time of the error.</div>
    </section>
</div>
</body>
</html>
