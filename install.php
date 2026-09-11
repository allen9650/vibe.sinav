<?php
/**
 * Marr Typing Competition System
 * One-Time Installer
 * 
 * Creates the database, tables, and seeds default data.
 * Self-disables after successful installation.
 */

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Type: text/html; charset=UTF-8');

// Load configuration
require_once __DIR__ . '/config/app.php';
require_once CORE_PATH . '/Database.php';

$results = [];
$success = true;
$step = 0;

function addResult(string $message, bool $ok, array &$results, int &$step): void
{
    $step++;
    $results[] = [
        'step'    => $step,
        'message' => $message,
        'status'  => $ok ? 'OK' : 'FAIL',
    ];
}

// ============================================================
// Check if already installed
// ============================================================
$lockFile = __DIR__ . '/install.lock';
$isInstalled = file_exists($lockFile);

if ($isInstalled && !isset($_GET['force'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Installed</title>
        <link rel="stylesheet" href="public/assets/css/bootstrap.min.css">
        <link rel="stylesheet" href="public/assets/css/all.min.css">
        <link rel="stylesheet" href="public/assets/css/app.css">
    </head>
    <body class="auth-body">
        <div class="auth-wrapper">
            <div class="auth-card text-center">
                <div class="auth-logo" style="background: linear-gradient(135deg, #10b981, #22d3ee);">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="mt-3 mb-2">Already Installed</h2>
                <p class="text-muted mb-4">The system has already been installed. Delete <code>install.lock</code> to reinstall.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// Run Installation
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['run'])) {

    $dbName = env('DB_DATABASE', 'PTM');

    try {
        // Step 1: Connect to MySQL server (without database)
        $pdo = Database::getInstanceWithoutDB();
        addResult('Connected to MySQL server', true, $results, $step);

        // Step 2: Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        addResult("Database '{$dbName}' created/verified", true, $results, $step);

        // Step 3: Select database
        $pdo->exec("USE `{$dbName}`");
        addResult("Selected database '{$dbName}'", true, $results, $step);

        // Step 4: Run schema.sql
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new RuntimeException('schema.sql not found');
        }
        $schema = file_get_contents($schemaFile);
        $pdo->exec($schema);
        addResult('Database schema executed — 17 tables created', true, $results, $step);

        // Step 5: Verify tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $expectedTables = [
            'roles', 'permissions', 'role_permissions', 'users', 'user_roles',
            'competitions', 'candidates', 'typing_paragraphs', 'competition_paragraphs',
            'test_settings', 'test_attempts', 'test_progress', 'test_results',
            'security_events', 'attendance', 'system_settings', 'audit_logs'
        ];

        $missingTables = array_diff($expectedTables, $tables);
        if (!empty($missingTables)) {
            throw new RuntimeException('Missing tables: ' . implode(', ', $missingTables));
        }
        addResult('All 17 tables verified: ' . implode(', ', $expectedTables), true, $results, $step);

        // Step 6: Run seed.sql
        $seedFile = __DIR__ . '/database/seed.sql';
        if (!file_exists($seedFile)) {
            throw new RuntimeException('seed.sql not found');
        }
        $seed = file_get_contents($seedFile);
        $pdo->exec($seed);
        addResult('Seed data inserted (roles, permissions, admin user, settings)', true, $results, $step);

        // Step 7: Generate proper password hash for admin
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$adminPassword]);
        addResult('Admin password hash generated with bcrypt (cost 12)', true, $results, $step);

        // Step 8: Verify admin user
        $admin = $pdo->query("SELECT id, username, full_name, role_id, force_password_change FROM users WHERE username = 'admin'")->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            throw new RuntimeException('Admin user not found after seeding');
        }
        addResult("Admin user verified: {$admin['username']} (ID: {$admin['id']}, force_change: {$admin['force_password_change']})", true, $results, $step);

        // Step 9: Verify roles
        $roleCount = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        addResult("Roles verified: {$roleCount} roles found", $roleCount == 4, $results, $step);

        // Step 10: Verify permissions
        $permCount = $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
        addResult("Permissions verified: {$permCount} permissions found", $permCount > 0, $results, $step);

        // Step 11: Verify role_permissions
        $rpCount = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
        addResult("Role-permission mappings verified: {$rpCount} mappings", $rpCount > 0, $results, $step);

        // Step 12: Verify system settings
        $settingsCount = $pdo->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        addResult("System settings verified: {$settingsCount} settings", $settingsCount > 0, $results, $step);

        // Step 13: Create install.lock
        file_put_contents($lockFile, 'Installed on: ' . date('Y-m-d H:i:s') . "\nDo not delete this file unless you want to reinstall.");
        addResult('Installation lock file created (install.lock)', true, $results, $step);

    } catch (Exception $e) {
        $success = false;
        addResult('ERROR: ' . $e->getMessage(), false, $results, $step);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Marr</title>
    <link rel="stylesheet" href="public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/assets/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/app.css">
</head>
<body class="auth-body">
    <div style="width: 100%; max-width: 700px; padding: 2rem; margin: 0 auto;">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-cogs"></i>
                </div>
                <h1 class="auth-title">System Installer</h1>
                <p class="auth-subtitle">Marr</p>
            </div>

            <?php if (empty($results)): ?>
                <!-- Pre-installation check -->
                <div class="mb-4">
                    <h5 class="mb-3">Pre-Installation Checklist</h5>
                    <?php
                    $checks = [
                        ['PHP Version ≥ 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')],
                        ['PDO MySQL Extension', extension_loaded('pdo_mysql')],
                        ['.env file exists', file_exists(__DIR__ . '/.env')],
                        ['schema.sql exists', file_exists(__DIR__ . '/database/schema.sql')],
                        ['seed.sql exists', file_exists(__DIR__ . '/database/seed.sql')],
                        ['uploads directory writable', is_writable(__DIR__ . '/public/uploads') || @mkdir(__DIR__ . '/public/uploads', 0755, true)],
                    ];
                    $allPassed = true;
                    foreach ($checks as [$label, $passed]):
                        if (!$passed) $allPassed = false;
                    ?>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-<?= $passed ? 'check-circle text-success' : 'times-circle text-danger' ?> me-2"></i>
                            <span class="<?= $passed ? '' : 'text-danger' ?>"><?= $label ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3 p-3" style="background: var(--bg-input); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <strong>Database Configuration:</strong>
                    <div class="mt-2 small text-muted">
                        <div>Host: <code><?= e(env('DB_HOST', 'localhost')) ?></code></div>
                        <div>Database: <code><?= e(env('DB_DATABASE', 'PTM')) ?></code></div>
                        <div>Username: <code><?= e(env('DB_USERNAME', 'root')) ?></code></div>
                    </div>
                </div>

                <?php if ($allPassed): ?>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-play me-2"></i> Run Installation
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please fix the failed checks above before installing.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Installation Results -->
                <div class="mb-4">
                    <?php foreach ($results as $r): ?>
                        <div class="d-flex align-items-start mb-2">
                            <span class="me-2" style="min-width: 24px;">
                                <?php if ($r['status'] === 'OK'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </span>
                            <span class="small <?= $r['status'] === 'OK' ? '' : 'text-danger fw-bold' ?>">
                                Step <?= $r['step'] ?>: <?= e($r['message']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Installation Complete!</strong>
                    </div>
                    <div class="p-3 mb-3" style="background: var(--bg-input); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <strong>Default Admin Login:</strong>
                        <div class="mt-2 small">
                            <div>Username: <code>admin</code></div>
                            <div>Password: <code>admin123</code></div>
                            <div class="text-warning mt-1">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                You will be required to change your password on first login.
                            </div>
                        </div>
                    </div>
                    <a href="index.php" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                    </a>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Installation Failed.</strong> Please fix the errors above and try again.
                    </div>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-redo me-2"></i> Retry Installation
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
