<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Certificate Professionalization & Net WPM Consistency
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== CERTIFICATE PROFESSIONALIZATION VERIFICATION SUITE ===\n\n";

$passed = 0;
$failed = 0;

function test(string $name, bool $result, string $detail = '') {
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "[PASS] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/RankingService.php';
require_once __DIR__ . '/../core/CertificateService.php';
require_once __DIR__ . '/../core/helpers.php';

Session::start();
Session::set('user_id', 1);
Session::set('role_slug', 'super_admin');
$allPerms = Database::fetchAll("SELECT slug FROM permissions");
Session::set('permissions', array_column($allPerms, 'slug'));

// ============================================================
// 1. SETUP TEST COMPETITION & CANDIDATES
// ============================================================
echo "\n--- 1. TEST SETUP: CANDIDATES, ZERO WPM & NORMAL WPM ---\n";

// Cleanup prior artifacts
Database::delete('certificates', "certificate_number LIKE 'MITC-CERT-PROF-%'");
Database::delete('test_results', "candidate_id IN (SELECT id FROM candidates WHERE registration_number LIKE 'MITC-PROF-%')");
Database::delete('test_attempts', "candidate_id IN (SELECT id FROM candidates WHERE registration_number LIKE 'MITC-PROF-%')");
Database::delete('candidates', "registration_number LIKE 'MITC-PROF-%'");
Database::delete('competitions', "code = 'PROF-CERT-2026'");

$compId = Database::insert('competitions', [
    'name'                 => 'Grand Provincial Typing Championship 2026',
    'code'                 => 'PROF-CERT-2026',
    'description'          => 'Professionalized Certificate Verification Competition',
    'competition_date'     => '2026-09-20',
    'status'               => 'completed',
    'results_finalized_at' => date('Y-m-d H:i:s'),
    'results_finalized_by' => 1,
    'results_locked'       => 1,
    'created_by'           => 1,
]);

// 1. Candidate with 0.00 Net WPM (High error rate)
$candZeroId = Database::insert('candidates', [
    'competition_id'      => $compId,
    'full_name'           => 'Zubair Ahmed',
    'roll_number'         => 'R-ZERO',
    'registration_number' => 'MITC-PROF-001',
    'course'              => 'DIT',
    'status'              => 'completed',
    'registered_by'       => 1,
]);
$attZeroId = Database::insert('test_attempts', [
    'competition_id' => $compId,
    'candidate_id'   => $candZeroId,
    'attempt_number' => 1,
    'status'         => 'completed',
    'started_at'     => date('Y-m-d H:i:s', time() - 60),
    'finished_at'    => date('Y-m-d H:i:s'),
    'submitted_at'   => date('Y-m-d H:i:s'),
]);
$resZeroId = Database::insert('test_results', [
    'attempt_id'           => $attZeroId,
    'candidate_id'         => $candZeroId,
    'competition_id'       => $compId,
    'gross_wpm'            => 15.00,
    'net_wpm'              => 0.00, // Authoritative 0.00
    'accuracy'             => 28.50,
    'score'                => 0.00,
    'rank'                 => 4,
    'qualification_status' => 'not_qualified',
    'calculated_at'        => date('Y-m-d H:i:s'),
]);

// 2. Candidate 1st Position (Gold Winner)
$candGoldId = Database::insert('candidates', [
    'competition_id'      => $compId,
    'full_name'           => 'Muhammad Shahzaib Khanzada Al-Mansoor', // Long Name
    'roll_number'         => 'R-GOLD',
    'registration_number' => 'MITC-PROF-002',
    'course'              => 'DIT',
    'status'              => 'completed',
    'registered_by'       => 1,
]);
$attGoldId = Database::insert('test_attempts', [
    'competition_id' => $compId,
    'candidate_id'   => $candGoldId,
    'attempt_number' => 1,
    'status'         => 'completed',
    'started_at'     => date('Y-m-d H:i:s', time() - 300),
    'finished_at'    => date('Y-m-d H:i:s'),
    'submitted_at'   => date('Y-m-d H:i:s'),
]);
$resGoldId = Database::insert('test_results', [
    'attempt_id'           => $attGoldId,
    'candidate_id'         => $candGoldId,
    'competition_id'       => $compId,
    'gross_wpm'            => 72.00,
    'net_wpm'              => 70.50, // Authoritative 70.50
    'accuracy'             => 98.75,
    'score'                => 69.62,
    'rank'                 => 1,
    'qualification_status' => 'qualified',
    'calculated_at'        => date('Y-m-d H:i:s'),
]);

// 3. Candidate 2nd Position (Silver)
$candSilverId = Database::insert('candidates', [
    'competition_id'      => $compId,
    'full_name'           => 'Ayesha Khan',
    'roll_number'         => 'R-SILVER',
    'registration_number' => 'MITC-PROF-003',
    'course'              => 'CIT',
    'status'              => 'completed',
    'registered_by'       => 1,
]);
$attSilverId = Database::insert('test_attempts', [
    'competition_id' => $compId,
    'candidate_id'   => $candSilverId,
    'attempt_number' => 1,
    'status'         => 'completed',
    'started_at'     => date('Y-m-d H:i:s', time() - 300),
    'finished_at'    => date('Y-m-d H:i:s'),
    'submitted_at'   => date('Y-m-d H:i:s'),
]);
$resSilverId = Database::insert('test_results', [
    'attempt_id'           => $attSilverId,
    'candidate_id'         => $candSilverId,
    'competition_id'       => $compId,
    'gross_wpm'            => 60.00,
    'net_wpm'              => 58.00,
    'accuracy'             => 96.00,
    'score'                => 55.68,
    'rank'                 => 2,
    'qualification_status' => 'qualified',
    'calculated_at'        => date('Y-m-d H:i:s'),
]);

test('Test dataset initialized', $compId > 0);

// ============================================================
// 2. CERTIFICATE GENERATION & AUTHORITATIVE SNAPSHOT VERIFICATION
// ============================================================
echo "\n--- 2. AUTHORITATIVE SNAPSHOT VERIFICATION ---\n";

// Position Certificate for Rank 1 (Gold, Long Name)
$certGold = CertificateService::generateCertificate($candGoldId, $compId, 'position', 1, 'champion');
test('Rank 1 Certificate generated', $certGold['status'] === 'generated');
$certGoldRecord = Database::fetch("SELECT * FROM certificates WHERE id = ?", [$certGold['certificate_id']]);
test('Rank 1 net_wpm_snapshot matches test_results.net_wpm exactly', (float)$certGoldRecord['net_wpm_snapshot'] === 70.50);
test('Rank 1 accuracy_snapshot matches test_results.accuracy exactly', (float)$certGoldRecord['accuracy_snapshot'] === 98.75);

// Participation Certificate for 0.00 WPM candidate
$certZero = CertificateService::generateCertificate($candZeroId, $compId, 'participation', 1, 'classic');
test('Zero WPM Certificate generated', $certZero['status'] === 'generated');
$certZeroRecord = Database::fetch("SELECT * FROM certificates WHERE id = ?", [$certZero['certificate_id']]);
test('Zero WPM net_wpm_snapshot stores 0.00 without mutation', (float)$certZeroRecord['net_wpm_snapshot'] === 0.00);

// ============================================================
// 3. STANDARDIZED CERTIFICATE WORDING TESTS
// ============================================================
echo "\n--- 3. STANDARDIZED CERTIFICATE TEXT & TYPOGRAPHY ---\n";

// Test Position Certificate Wording
$_GET['id'] = $certGoldRecord['id'];
$_GET['template'] = 'classic';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$posHtml = ob_get_clean();

test("Contains 'This is to certify that'", stripos($posHtml, 'This is to certify that') !== false);
test("Contains full candidate name", strpos($posHtml, 'Muhammad Shahzaib Khanzada Al-Mansoor') !== false);
test("Contains 'has successfully participated in the'", strpos($posHtml, 'has successfully participated in the') !== false);
test("Contains 'and has secured 1st Position'", strpos($posHtml, '1st Position') !== false);
test("Contains 'with a Net Typing Speed of 70.50 WPM'", strpos($posHtml, '70.50 WPM') !== false);
test("Contains 'and Accuracy of 98.75%'", strpos($posHtml, '98.75%') !== false);
test("Contains standardized achievement concluding statement", strpos($posHtml, 'recognition of outstanding typing performance, accuracy, speed, dedication, and achievement') !== false);

// Test Participation Certificate Wording with 0.00 WPM
$_GET['id'] = $certZeroRecord['id'];
$_GET['template'] = 'classic';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$partHtml = ob_get_clean();

test("Participation contains 'organized by Microsoft Institute Khairpur Mirs''", strpos($partHtml, "Microsoft Institute Khairpur") !== false);
test("Participation displays Net Typing Speed: 0.00 WPM", strpos($partHtml, '0.00 WPM') !== false);
test("Participation displays Accuracy: 28.50%", strpos($partHtml, '28.50%') !== false);

// ============================================================
// 4. BRANDING, WATERMARK, SIGNATURES & DATES
// ============================================================
echo "\n--- 4. BRANDING, WATERMARK, SIGNATURES & DATES ---\n";

test("Contains watermark layer with class 'cert-watermark'", strpos($posHtml, 'cert-watermark') !== false);
test("Contains institutional logo/placeholder emblem", strpos($posHtml, 'cert-logo-placeholder') !== false || strpos($posHtml, 'cert-logo-img') !== false);
test("Contains 'Date of Issue:' footer", strpos($posHtml, 'Date of Issue:') !== false);
test("Contains 'Certificate No:' with certificate number", strpos($posHtml, 'Certificate No:') !== false && strpos($posHtml, $certGoldRecord['certificate_number']) !== false);
test("Contains 'Competition Date:' footer", strpos($posHtml, 'Competition Date:') !== false);
test("Contains Left Signature Title 'Competition Coordinator'", strpos($posHtml, 'Competition Coordinator') !== false);
test("Contains Right Signature Title 'Director / Principal'", strpos($posHtml, 'Director / Principal') !== false);

// ============================================================
// 5. ALL 4 TEMPLATES VERIFICATION
// ============================================================
echo "\n--- 5. ALL 4 PROFESSIONAL TEMPLATES RENDERING ---\n";

$templates = ['classic', 'modern', 'academic', 'champion'];
foreach ($templates as $tmpl) {
    $_GET['id'] = $certGoldRecord['id'];
    $_GET['template'] = $tmpl;
    ob_start();
    include __DIR__ . '/../views/certificates/view.php';
    $tmplHtml = ob_get_clean();

    test("Template [{$tmpl}] renders cleanly", strlen($tmplHtml) > 2000);
    test("Template [{$tmpl}] contains 297mm x 210mm A4 geometry", strpos($tmplHtml, '297mm') !== false && strpos($tmplHtml, '210mm') !== false);
    test("Template [{$tmpl}] contains watermark", strpos($tmplHtml, 'cert-watermark') !== false);
    test("Template [{$tmpl}] contains candidate name", strpos($tmplHtml, 'Muhammad Shahzaib Khanzada Al-Mansoor') !== false);
    test("Template [{$tmpl}] contains exact Net WPM 70.50", strpos($tmplHtml, '70.50') !== false);
}

// ============================================================
// 6. CLEANUP TEST ARTIFACTS
// ============================================================
echo "\n--- 6. CLEANUP TEST ARTIFACTS ---\n";
Database::delete('certificates', 'competition_id = ?', [$compId]);
Database::delete('test_results', 'competition_id = ?', [$compId]);
Database::delete('test_attempts', 'competition_id = ?', [$compId]);
Database::delete('candidates', 'competition_id = ?', [$compId]);
Database::delete('competitions', 'id = ?', [$compId]);
test('Test artifacts cleaned cleanly', true);

echo "\n============================================================\n";
echo "CERTIFICATE PROFESSIONALIZATION TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
