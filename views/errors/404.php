<?php
/**
 * 404 Not Found Error Page
 */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon text-warning">
                <i class="fas fa-search"></i>
            </div>
            <h1 class="error-code">404</h1>
            <h2 class="error-title">Page Not Found</h2>
            <p class="error-message">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <div class="error-actions">
                <a href="<?= url('dashboard') ?>" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> Go to Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</body>
</html>
