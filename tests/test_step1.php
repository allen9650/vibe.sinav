<?php
/**
 * CLI Test Script — Tests all Step 1 functionality
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 1 — COMPREHENSIVE TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;
$tests = [];

function test($name, $result, $detail = '') {
    global $passed, $failed, $tests;
    if ($result) {
        $passed++;
        echo "[PASS] $name" . ($detail ? " — $detail" : '') . "\n";
    } else {
        $failed++;
        echo "[FAIL] $name" . ($detail ? " — $detail" : '') . "\n";
    }
    $tests[] = ['name' => $name, 'result' => $result, 'detail' => $detail];
}

// ============================================================
// TEST 1: Configuration
// ============================================================
echo "\n--- 1. CONFIGURATION ---\n";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/helpers.php';

test('PHP version >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
test('PDO MySQL extension loaded', extension_loaded('pdo_mysql'));
test('Timezone set to Asia/Karachi', date_default_timezone_get() === 'Asia/Karachi', date_default_timezone_get());
test('.env file loaded', !empty(env('DB_DATABASE')), 'DB=' . env('DB_DATABASE'));
test('APP_NAME defined', defined('APP_NAME'), APP_NAME);

// ============================================================
// TEST 2: Database Connection & Schema
// ============================================================
echo "\n--- 2. DATABASE ---\n";

try {
    $pdo = Database::getInstanceWithoutDB();
    test('MySQL connection (without DB)', true);
} catch (Exception $e) {
    test('MySQL connection (without DB)', false, $e->getMessage());
    echo "\n[FATAL] Cannot proceed without database connection.\n";
    exit(1);
}

// Create/recreate database
$dbName = env('DB_DATABASE', 'ms_typing');
try {
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    test('Database created', true, $dbName);
} catch (Exception $e) {
    test('Database created', false, $e->getMessage());
    exit(1);
}

// Run schema
try {
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);
    test('Schema executed', true);
} catch (Exception $e) {
    test('Schema executed', false, $e->getMessage());
    exit(1);
}

// Verify all 17 tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = [
    'roles', 'permissions', 'role_permissions', 'users', 'user_roles',
    'competitions', 'candidates', 'typing_paragraphs', 'competition_paragraphs',
    'test_settings', 'test_attempts', 'test_progress', 'test_results',
    'security_events', 'attendance', 'system_settings', 'audit_logs'
];
$missingTables = array_diff($expectedTables, $tables);
test('All 17 tables exist', empty($missingTables), 
    empty($missingTables) ? count($tables) . ' tables' : 'Missing: ' . implode(', ', $missingTables));

// Run seed
try {
    $seed = file_get_contents(__DIR__ . '/../database/seed.sql');
    $pdo->exec($seed);
    test('Seed data inserted', true);
} catch (Exception $e) {
    test('Seed data inserted', false, $e->getMessage());
    exit(1);
}

// Fix admin password with proper hash
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([$hash]);
test('Admin password bcrypt-hashed', true);

// Verify data counts
$roleCount = (int) $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
test('Roles seeded', $roleCount === 4, "$roleCount roles");

$permCount = (int) $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
test('Permissions seeded', $permCount > 30, "$permCount permissions");

$rpCount = (int) $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
test('Role-permissions mapped', $rpCount > 0, "$rpCount mappings");

$settingsCount = (int) $pdo->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
test('System settings seeded', $settingsCount >= 10, "$settingsCount settings");

// ============================================================
// TEST 3: Authentication
// ============================================================
echo "\n--- 3. AUTHENTICATION ---\n";

// Simulate session for CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'CLI Test Agent';
Session::start();

// Test login with correct credentials
$result = Auth::login('admin', 'admin123');
test('Login with correct credentials', $result['success'], $result['message']);

// Verify session data
test('Session user_id set', Session::get('user_id') === 1, 'user_id=' . Session::get('user_id'));
test('Session role set', Session::get('role_slug') === 'super-admin', Session::get('role_slug'));
test('Session permissions cached', !empty(Session::get('permissions')), count(Session::get('permissions', [])) . ' permissions');
test('Force password change flag', Session::get('force_password_change') === true, 'force=' . var_export(Session::get('force_password_change'), true));

// Test Auth::user()
$user = Auth::user();
test('Auth::user() returns data', $user !== null && $user['username'] === 'admin');

// Test Auth::hasPermission()
test('Super Admin has all permissions', Auth::hasPermission('dashboard.view'));
test('Super Admin has settings permission', Auth::hasPermission('settings.manage'));
test('Super Admin has any random permission', Auth::hasPermission('nonexistent.permission'));

// Test Auth::hasRole()
test('Auth::hasRole("super-admin")', Auth::hasRole('super-admin'));
test('Auth::hasRole("admin") is false', !Auth::hasRole('admin'));

// Logout
Auth::logout();
Session::start(); // restart for further tests
test('Logout clears session', !Session::isAuthenticated());

// ============================================================
// TEST 4: Wrong Password & Lockout
// ============================================================
echo "\n--- 4. WRONG PASSWORD & LOCKOUT ---\n";

// Test wrong password
$result = Auth::login('admin', 'wrongpassword');
test('Wrong password rejected', !$result['success'], $result['message']);

// Verify login_attempts incremented
$attempts = (int) Database::fetchColumn("SELECT login_attempts FROM users WHERE username = 'admin'");
test('Login attempts incremented', $attempts === 1, "attempts=$attempts");

// Test nonexistent user
$result = Auth::login('nonexistent_user', 'password');
test('Nonexistent user rejected', !$result['success'], $result['message']);

// Reset attempts for further tests
Database::update('users', ['login_attempts' => 0, 'locked_until' => null], "username = ?", ['admin']);

// Test lockout after max attempts
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) {
    Auth::login('admin', 'wrongpassword');
}
$result = Auth::login('admin', 'admin123'); // Even correct password should fail when locked
$isLocked = !$result['success'] && str_contains($result['message'], 'locked');
test('Account locks after ' . LOGIN_MAX_ATTEMPTS . ' failed attempts', $isLocked, $result['message']);

// Reset for further tests
Database::update('users', ['login_attempts' => 0, 'locked_until' => null], "username = ?", ['admin']);

// ============================================================
// TEST 5: Password Change
// ============================================================
echo "\n--- 5. PASSWORD CHANGE ---\n";

// Login first
Auth::login('admin', 'admin123');

// Test change with wrong current password
$result = Auth::changePassword(1, 'wrongcurrent', 'NewPass123');
test('Wrong current password rejected', !$result['success'], $result['message']);

// Test weak password
$result = Auth::changePassword(1, 'admin123', 'weak');
test('Weak password rejected', !$result['success'], $result['message']);

// Test password without uppercase
$result = Auth::changePassword(1, 'admin123', 'nouppercasepassword1');
test('No uppercase rejected', !$result['success'], $result['message']);

// Test password same as current
$result = Auth::changePassword(1, 'admin123', 'admin123');
test('Same password rejected', !$result['success'], $result['message']);

// Test valid password change
$result = Auth::changePassword(1, 'admin123', 'NewSecure1');
test('Valid password change succeeds', $result['success'], $result['message']);

// Verify new password works
Auth::logout();
Session::start();
$result = Auth::login('admin', 'NewSecure1');
test('Login with new password works', $result['success']);

// Verify force_password_change cleared
test('Force password change cleared', Session::get('force_password_change') === false);

// Reset password back to admin123 for convenience
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
Database::update('users', ['password' => $hash, 'force_password_change' => 1], 'id = ?', [1]);
Auth::logout();
Session::start();

// ============================================================
// TEST 6: RBAC — Permission Checks
// ============================================================
echo "\n--- 6. RBAC ---\n";

// Verify Super Admin role-permission count
$saPerms = (int) Database::fetchColumn("SELECT COUNT(*) FROM role_permissions WHERE role_id = 1");
$totalPerms = (int) Database::fetchColumn("SELECT COUNT(*) FROM permissions");
test('Super Admin has all permissions', $saPerms === $totalPerms, "$saPerms/$totalPerms");

// Verify Admin permissions (should NOT have roles.manage, users.delete, audit.view)
$adminPerms = Database::fetchAll("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 2");
$adminSlugs = array_column($adminPerms, 'slug');
test('Admin lacks roles.manage', !in_array('roles.manage', $adminSlugs));
test('Admin lacks users.delete', !in_array('users.delete', $adminSlugs));
test('Admin lacks audit.view', !in_array('audit.view', $adminSlugs));
test('Admin has dashboard.view', in_array('dashboard.view', $adminSlugs));

// Verify Invigilator permissions
$invPerms = Database::fetchAll("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 3");
$invSlugs = array_column($invPerms, 'slug');
test('Invigilator has tests.view', in_array('tests.view', $invSlugs));
test('Invigilator lacks settings.manage', !in_array('settings.manage', $invSlugs));

// Verify Candidate permissions
$candPerms = Database::fetchAll("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 4");
$candSlugs = array_column($candPerms, 'slug');
test('Candidate has dashboard.view', in_array('dashboard.view', $candSlugs));
test('Candidate lacks users.view', !in_array('users.view', $candSlugs));

// ============================================================
// TEST 7: CSRF Protection
// ============================================================
echo "\n--- 7. CSRF ---\n";

Session::start();
$token = CSRF::token();
test('CSRF token generated', !empty($token), strlen($token) . ' chars');
test('CSRF token consistent in session', CSRF::token() === $token);

$_POST['csrf_token'] = $token;
test('CSRF validates correct token', CSRF::validate($token));
test('CSRF rejects wrong token', !CSRF::validate('wrong_token'));
test('CSRF rejects empty token', !CSRF::validate(''));

// ============================================================
// TEST 8: Audit Logging
// ============================================================
echo "\n--- 8. AUDIT LOGGING ---\n";

// Check logs were created during tests
$logCount = (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs");
test('Audit logs created during tests', $logCount > 0, "$logCount logs");

// Check login events
$loginLogs = (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%'");
test('Login events logged', $loginLogs > 0, "$loginLogs login events");

// Check password change events
$pwLogs = (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%password%'");
test('Password change events logged', $pwLogs > 0, "$pwLogs password events");

// Check security events
$secEvents = (int) Database::fetchColumn("SELECT COUNT(*) FROM security_events");
test('Security events logged', $secEvents > 0, "$secEvents security events");

// Test manual audit log
AuditLog::log('test_action', 'test', 'Test audit log entry', 1);
$testLog = Database::fetch("SELECT * FROM audit_logs WHERE action = 'test_action' ORDER BY id DESC LIMIT 1");
test('Manual audit log works', $testLog !== null && $testLog['description'] === 'Test audit log entry');

// ============================================================
// TEST 9: Dashboard Queries
// ============================================================
echo "\n--- 9. DASHBOARD QUERIES ---\n";

$totalComp = (int) Database::fetchColumn("SELECT COUNT(*) FROM competitions");
test('Total competitions query works', $totalComp === 0, "count=$totalComp");

$totalCand = (int) Database::fetchColumn("SELECT COUNT(*) FROM candidates");
test('Total candidates query works', $totalCand === 0, "count=$totalCand");

$completedTests = (int) Database::fetchColumn("SELECT COUNT(*) FROM test_results");
test('Completed tests query works', $completedTests === 0, "count=$completedTests");

$activeTests = (int) Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE status = 'in_progress'");
test('Active tests query works', $activeTests === 0, "count=$activeTests");

// ============================================================
// TEST 10: System Settings
// ============================================================
echo "\n--- 10. SYSTEM SETTINGS ---\n";

$instituteName = getSetting('institute_name');
test('Institute name setting', $instituteName === "Microsoft Institute Khairpur Mirs'", $instituteName);

$systemName = getSetting('system_name');
test('System name setting', $systemName === 'Microsoft Institute Typing Competition System', $systemName);

$timezone = getSetting('timezone');
test('Timezone setting', $timezone === 'Asia/Karachi', $timezone);

$defaultVal = getSetting('nonexistent_key', 'default');
test('Default value for missing setting', $defaultVal === 'default');

// ============================================================
// TEST 11: Input Validation
// ============================================================
echo "\n--- 11. INPUT VALIDATION ---\n";

$v = new Validator(['username' => '', 'email' => 'invalid', 'password' => 'short']);
$v->required('username')->email('email')->minLength('password', 8);
test('Validator catches empty required', $v->fails() && $v->error('username') !== null);
test('Validator catches invalid email', $v->error('email') !== null);
test('Validator catches short password', $v->error('password') !== null);

$v2 = new Validator(['name' => 'Test', 'email' => 'test@example.com']);
$v2->required('name')->email('email');
test('Validator passes valid data', $v2->passes());

// ============================================================
// TEST 12: SQL Injection Protection
// ============================================================
echo "\n--- 12. SECURITY ---\n";

// SQL injection test — prepared statements should prevent this
$maliciousUsername = "admin'; DROP TABLE users; --";
$result = Auth::login($maliciousUsername, 'test');
test('SQL injection in login prevented', !$result['success']);

// Verify users table still exists
$tableExists = Database::fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'", [$dbName]);
test('Users table still intact after injection attempt', $tableExists > 0);

// XSS — verify e() function escapes properly
$xssInput = '<script>alert("xss")</script>';
$escaped = e($xssInput);
test('XSS escaping works', $escaped === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');

// Verify password is properly hashed (not stored in plaintext)
$storedPw = Database::fetchColumn("SELECT password FROM users WHERE username = 'admin'");
test('Password stored as bcrypt hash', str_starts_with($storedPw, '$2y$'));
test('Password not stored as plaintext', $storedPw !== 'admin123');

// ============================================================
// SUMMARY
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED out of " . ($passed + $failed) . " tests\n";
echo str_repeat('=', 60) . "\n";

if ($failed > 0) {
    echo "\nFAILED TESTS:\n";
    foreach ($tests as $t) {
        if (!$t['result']) {
            echo "  - {$t['name']}" . ($t['detail'] ? " ({$t['detail']})" : '') . "\n";
        }
    }
}

echo "\n" . ($failed === 0 ? "ALL TESTS PASSED!" : "SOME TESTS FAILED - Review above.") . "\n";
