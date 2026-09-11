<?php
/**
 * 403 Forbidden / Access Denied Error Page
 * Intelligent role-aware error view.
 */
$currentUser = class_exists('Auth') ? Auth::user() : null;
$userRole = class_exists('Auth') ? (Auth::user()['role_name'] ?? ucfirst(Auth::getRole())) : 'User';
$isTeacher = class_exists('Auth') && Auth::isTeacher();
$homeUrl = $isTeacher ? url('teacher-dashboard') : url('dashboard');
$requestedPage = htmlspecialchars($_GET['page'] ?? 'requested resource', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Access Denied | vibe.Sınav</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
    <div class="error-page" style="max-width: 540px; margin: 0 auto; padding: 2rem 1rem;">
        <div class="card border-0 shadow-lg bg-dark bg-opacity-75 border-top border-4 border-danger text-center p-4">
            <div class="card-body">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger mb-3" style="width: 72px; height: 72px;">
                    <i class="fas fa-shield-halved fa-2x"></i>
                </div>
                
                <h1 class="display-5 fw-bold text-danger mb-0 font-monospace">403</h1>
                <h3 class="fw-bold text-light mb-2">Access Denied</h3>
                
                <p class="text-muted small mb-3">
                    You do not have the required permissions to access <code><?= e($requestedPage) ?></code>.
                </p>

                <?php if ($currentUser): ?>
                    <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary border-opacity-25 mb-4 text-start small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">Signed In As:</span>
                            <span class="fw-semibold text-light"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">Account Role:</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary"><?= e($userRole) ?></span>
                        </div>
                        <?php if (!empty($currentUser['campus_name'])): ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Campus:</span>
                            <span class="text-info"><?= e($currentUser['campus_name']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mt-3">
                    <a href="<?= $homeUrl ?>" class="btn btn-primary fw-semibold px-3">
                        <i class="fas fa-home me-1"></i> Go to My Dashboard
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary px-3">
                        <i class="fas fa-arrow-left me-1"></i> Go Back
                    </a>
                    <?php if ($currentUser): ?>
                        <a href="<?= url('logout') ?>" class="btn btn-outline-danger px-3" title="Sign out and log in with different credentials">
                            <i class="fas fa-right-from-bracket me-1"></i> Switch User
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 text-muted small pt-0">
                Contact your Super Administrator if your account requires additional privileges.
            </div>
        </div>
    </div>
</body>
</html>
