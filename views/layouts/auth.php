<?php
/**
 * Auth Layout — Login and public pages
 */
$systemName = 'vibe.Sınav';
$instituteName = 'vibe.Sınav';
$instituteLogo = '';
$logoUrl = null;

try {
    $systemName = getSetting('system_name', $systemName);
    $instituteName = getSetting('institute_name', $instituteName);
    $instituteLogo = getSetting('institute_logo', '');
    if (!empty($instituteLogo) && file_exists(UPLOADS_PATH . '/' . $instituteLogo)) {
        $logoUrl = upload($instituteLogo);
    }
} catch (Exception $e) {
    // Use defaults if DB not available
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($instituteName) ?> — Assessment System">
    <title><?= e($pageTitle ?? 'Login') ?> — <?= e($instituteName) ?> Assessment System</title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= time() ?>">
</head>
<body class="auth-body position-relative">
    <!-- Attempt Test Link in Top Left Corner -->
    <div class="position-absolute top-0 start-0 p-3" style="z-index: 10;">
        <a href="<?= url('test-start') ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2 shadow-sm" title="Attempt Assessment / Candidate Intake">
            <i class="fas fa-desktop text-primary"></i>
            <span class="fw-semibold">Attempt Test</span>
        </a>
    </div>

    <!-- Quick Theme Toggle in Top Right Corner -->
    <div class="position-absolute top-0 end-0 p-3" style="z-index: 10;">
        <button type="button" class="btn btn-sm btn-outline-secondary theme-quick-toggle d-flex align-items-center gap-2" title="Toggle Light / Dark Mode">
            <i class="fas fa-sun text-warning"></i>
            <span class="small d-none d-sm-inline">Theme</span>
        </button>
    </div>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <!-- Floating / Sliding Logo Container -->
                <div class="auth-logo <?= $logoUrl ? 'has-custom-logo' : '' ?>" title="<?= e($instituteName) ?>">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="<?= e($instituteName) ?> Logo" class="auth-logo-img">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap"></i>
                    <?php endif; ?>
                </div>

                <!-- Institute Name & Assessment System -->
                <h1 class="auth-title mb-1"><?= e($instituteName) ?></h1>
                <div class="auth-assessment-badge mb-2">
                    <span class="text-info fw-bold text-uppercase">
                        <i class="fas fa-graduation-cap me-1"></i> ASSESSMENT SYSTEM
                    </span>
                </div>

                <!-- Powered by vibe.Sınav -->
                <div class="powered-by-tag mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 rounded-pill">
                        <i class="fas fa-bolt text-warning me-1"></i> Powered by <?= e($systemName ?: 'vibe.Sınav') ?>
                    </span>
                </div>
            </div>

            <?php
            // Flash messages
            $flashMessages = Session::getFlash();
            foreach ($flashMessages as $msg): ?>
                <div class="alert alert-<?= $msg['type'] === 'error' ? 'danger' : e($msg['type']) ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= match($msg['type']) {
                        'success' => 'check-circle',
                        'error', 'danger' => 'exclamation-circle',
                        'warning' => 'exclamation-triangle',
                        default => 'info-circle'
                    } ?> me-2"></i>
                    <?= e($msg['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>

            <?= $content ?>
        </div>

        <div class="auth-footer text-center mt-3">
            <p class="mb-1 small text-muted">
                Powered by <strong class="text-primary"><?= e($systemName ?: 'vibe.Sınav') ?></strong>
            </p>
            <p class="small text-muted mb-0">Developed by Ahsan Raza</p>
        </div>
    </div>

    <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
