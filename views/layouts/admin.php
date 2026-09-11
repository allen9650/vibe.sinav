<?php
/**
 * Admin Layout — Sidebar + Top navbar
 */
$user = Auth::user();
$systemName = getSetting('system_name', 'vibe.Sınav');
$instituteName = getSetting('institute_name', 'vibe.Sınav');
$instituteLogo = getSetting('institute_logo', '');
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($systemName) ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($systemName) ?></title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= time() ?>">
</head>
<body class="admin-body">
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <?php if ($instituteLogo): ?>
                    <img src="<?= upload(e($instituteLogo)) ?>" alt="Logo" class="sidebar-logo">
                <?php else: ?>
                    <div class="sidebar-logo-icon">
                        <i class="fas fa-keyboard"></i>
                    </div>
                <?php endif; ?>
                <div class="sidebar-brand-text">
                    <span class="brand-name" title="<?= e($instituteName) ?>"><?= e($instituteName) ?></span>
                    <span class="brand-subtitle" title="vibe.Sınav Assessment System">vibe.Sınav Assessment System</span>
                </div>
            </div>
            <button class="sidebar-close d-lg-none" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <!-- 1. Overview & Dashboard -->
            <div class="nav-section">
                <span class="nav-section-title">Overview</span>
                <?php if (Auth::isTeacher()): ?>
                <a href="<?= url('teacher-dashboard') ?>" class="nav-link <?= activeClass('teacher-dashboard') ?>" id="nav-teacher-dashboard">
                    <i class="fas fa-chalkboard-teacher text-primary"></i>
                    <span>Teacher Dashboard</span>
                </a>
                <?php else: ?>
                <a href="<?= url('dashboard') ?>" class="nav-link <?= activeClass('dashboard') ?>" id="nav-dashboard">
                    <i class="fas fa-tachometer-alt text-primary"></i>
                    <span>System Dashboard</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- 2. Live Lab Monitoring -->
            <div class="nav-section">
                <span class="nav-section-title">Live Lab Monitoring</span>
                <a href="<?= url('monitoring') ?>" class="nav-link <?= (isActivePage('monitoring') || isActivePage('assessments-monitor')) ? 'active' : '' ?>" id="nav-live-monitor">
                    <i class="fas fa-satellite-dish text-danger"></i>
                    <span>Live Exam Monitor</span>
                    <span class="badge bg-danger text-white ms-auto" style="font-size: 0.65rem; padding: 2px 6px;">LIVE</span>
                </a>
                <a href="<?= url('test-start') ?>" class="nav-link <?= activeClass('test-start') ?>" target="_blank" id="nav-test-start">
                    <i class="fas fa-desktop text-success"></i>
                    <span>Candidate Intake Screen</span>
                    <i class="fas fa-external-link-alt ms-auto text-muted" style="font-size: 0.75rem;"></i>
                </a>
            </div>

            <!-- 3. Examinations & Assessments -->
            <div class="nav-section">
                <span class="nav-section-title">Examinations & Assessments</span>
                <a href="<?= url('assessments') ?>" class="nav-link <?= (isActivePage('assessments') && !isActivePage('assessments-create') && !isActivePage('assessments-devices') && !isActivePage('assessments-results') && !isActivePage('assessments-monitor')) ? 'active' : '' ?>" id="nav-assessments">
                    <i class="fas fa-tasks text-info"></i>
                    <span>Assessments & Quizzes</span>
                </a>
                <a href="<?= url('assessments-devices') ?>" class="nav-link <?= activeClass('assessments-devices') ?>" id="nav-assessments-devices">
                    <i class="fas fa-network-wired text-primary"></i>
                    <span>Lab Device Allocation</span>
                </a>
                <a href="<?= url('assessments-results') ?>" class="nav-link <?= (isActivePage('assessments-results') || isActivePage('assessments-candidate-detail')) ? 'active' : '' ?>" id="nav-assessments-results">
                    <i class="fas fa-poll text-success"></i>
                    <span>Results Ledger</span>
                </a>
                <?php if (Auth::hasAnyRole(['super-admin', 'admin', 'teacher'])): ?>
                <a href="<?= url('assessments-create') ?>" class="nav-link <?= activeClass('assessments-create') ?>" id="nav-assessments-create">
                    <i class="fas fa-plus-circle text-warning"></i>
                    <span>New Assessment</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- 4. Question Repository -->
            <div class="nav-section">
                <span class="nav-section-title">Question Repository</span>
                <a href="<?= url('questions') ?>" class="nav-link <?= (isActivePage('questions') && !isActivePage('questions-create')) ? 'active' : '' ?>" id="nav-questions">
                    <i class="fas fa-layer-group text-primary"></i>
                    <span>Question Bank</span>
                </a>
                <a href="<?= url('questions-create') ?>" class="nav-link <?= activeClass('questions-create') ?>" id="nav-questions-create">
                    <i class="fas fa-file-circle-plus text-info"></i>
                    <span>Add New Question</span>
                </a>
            </div>

            <!-- 5. Attendance & Reports -->
            <?php if (Auth::hasAnyRole(['super-admin', 'admin', 'teacher'])): ?>
            <div class="nav-section">
                <span class="nav-section-title">Attendance & Reports</span>
                <a href="<?= url('attendance') ?>" class="nav-link <?= activeClass('attendance') ?>" id="nav-attendance">
                    <i class="fas fa-clipboard-user text-warning"></i>
                    <span>Exam Attendance</span>
                </a>
                <a href="<?= url('reports') ?>" class="nav-link <?= activeClass('reports') ?>" id="nav-reports">
                    <i class="fas fa-chart-pie text-success"></i>
                    <span>Reports Center</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- 6. System & Administration (Admins / Super-Admin / Authorized Staff) -->
            <?php if (Auth::hasAnyRole(['super-admin', 'admin']) || Auth::hasPermission('security_events.view')): ?>
            <div class="nav-section">
                <span class="nav-section-title">System & Administration</span>
                <a href="<?= url('security-events') ?>" class="nav-link <?= activeClass('security-events') ?>" id="nav-security-events">
                    <i class="fas fa-shield-alt text-danger"></i>
                    <span>Security Violations</span>
                </a>
                <?php if (Auth::hasRole('super-admin')): ?>
                <a href="<?= url('users') ?>" class="nav-link <?= (isActivePage('users') || isActivePage('users-create') || isActivePage('users-edit')) ? 'active' : '' ?>" id="nav-users">
                    <i class="fas fa-users-cog text-info"></i>
                    <span>User Management</span>
                </a>
                <a href="<?= url('backup') ?>" class="nav-link <?= activeClass('backup') ?>" id="nav-backup">
                    <i class="fas fa-database text-warning"></i>
                    <span>Database Backup</span>
                </a>
                <a href="<?= url('system-health') ?>" class="nav-link <?= activeClass('system-health') ?>" id="nav-system-health">
                    <i class="fas fa-heartbeat text-danger"></i>
                    <span>System Health</span>
                </a>
                <a href="<?= url('settings') ?>" class="nav-link <?= activeClass('settings') ?>" id="nav-settings">
                    <i class="fas fa-cog text-light"></i>
                    <span>Settings</span>
                </a>
                <a href="<?= url('audit-logs') ?>" class="nav-link <?= activeClass('audit-logs') ?>" id="nav-audit-logs">
                    <i class="fas fa-history text-secondary"></i>
                    <span>Audit Logs</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-avatar-sm">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= e($user['full_name'] ?? 'User') ?></span>
                    <span class="user-role">
                        <?= e($user['role_name'] ?? 'User') ?>
                        <?= !empty($user['campus_name']) ? ' • ' . e($user['campus_name']) : '' ?>
                    </span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-breadcrumb d-none d-md-block">
                    <?= breadcrumb(array_merge(
                        ['Dashboard' => url('dashboard')],
                        ($currentPage !== 'dashboard' ? [$pageTitle => '#'] : [])
                    )) ?>
                </div>
            </div>

            <div class="navbar-right">
                <div class="navbar-info d-none d-md-flex">
                    <span class="timezone-badge">
                        <i class="fas fa-clock me-1"></i>
                        <span id="liveClock"><?= date('h:i A') ?></span>
                    </span>
                </div>

                <!-- Theme Switcher Dropdown (Light / Dark / Auto) -->
                <div class="dropdown me-1" id="themeSwitcher">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2 theme-switcher-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Toggle Theme">
                        <i class="fas fa-desktop" id="currentThemeIcon"></i>
                        <span class="d-none d-lg-inline fw-semibold small" id="currentThemeText">Auto</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm py-2">
                        <li class="dropdown-header small text-muted text-uppercase fw-bold pb-1">Appearance</li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 theme-select-btn" data-theme-value="light">
                                <i class="fas fa-sun text-warning fa-fw"></i> Light
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 theme-select-btn" data-theme-value="dark">
                                <i class="fas fa-moon text-info fa-fw"></i> Dark
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 theme-select-btn" data-theme-value="auto">
                                <i class="fas fa-desktop text-secondary fa-fw"></i> System (Auto)
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false" id="userDropdownBtn">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="d-none d-md-inline user-dropdown-name"><?= e($user['full_name'] ?? 'User') ?></span>
                        <i class="fas fa-chevron-down ms-1 dropdown-arrow"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <strong><?= e($user['full_name'] ?? 'User') ?></strong>
                            <br>
                            <small class="text-muted"><?= e($user['role_name'] ?? '') ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= url('profile') ?>" id="menu-profile">
                                <i class="fas fa-user-circle me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= url('change-password') ?>" id="menu-change-password">
                                <i class="fas fa-key me-2"></i> Change Password
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= url('logout') ?>" id="menu-logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Flash Messages -->
            <?php $flashMessages = Session::getFlash(); ?>
            <?php foreach ($flashMessages as $msg): ?>
                <div class="alert alert-<?= $msg['type'] === 'error' ? 'danger' : e($msg['type']) ?> alert-dismissible fade show flash-alert" role="alert">
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

            <!-- View Content -->
            <?= $content ?>
        </div>

        <!-- Footer -->
        <footer class="main-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>&copy; <?= date('Y') ?> <strong><?= e($instituteName) ?></strong> • <span class="text-primary fw-semibold">vibe.Sınav Assessment System</span></span>
            <span class="text-muted small">Developed by Ahsan Raza</span>
        </footer>
    </main>

    <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
