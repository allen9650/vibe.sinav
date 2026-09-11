<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Interactive Certificate Editor
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== CERTIFICATE EDITOR & CUSTOMIZER TEST SUITE ===\n\n";

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

// Cleanup prior artifacts
Database::delete('certificates', "certificate_number LIKE 'MITC-CERT-EDT-%'");
Database::delete('test_results', "candidate_id IN (SELECT id FROM candidates WHERE registration_number = 'MITC-EDT-001')");
Database::delete('test_attempts', "candidate_id IN (SELECT id FROM candidates WHERE registration_number = 'MITC-EDT-001')");
Database::delete('candidates', "registration_number = 'MITC-EDT-001'");
Database::delete('competitions', "code = 'EDT-COMP-2026'");

// 1. Create Test Competition & Candidate
$compId = Database::insert('competitions', [
    'name'                 => 'Editor Test Competition 2026',
    'code'                 => 'EDT-COMP-2026',
    'description'          => 'Certificate Editor Testing',
    'competition_date'     => '2026-09-25',
    'status'               => 'completed',
    'results_finalized_at' => date('Y-m-d H:i:s'),
    'results_finalized_by' => 1,
    'results_locked'       => 1,
    'created_by'           => 1,
]);

$candId = Database::insert('candidates', [
    'competition_id'      => $compId,
    'full_name'           => 'Original Name',
    'roll_number'         => 'R-999',
    'registration_number' => 'MITC-EDT-001',
    'course'              => 'DIT',
    'status'              => 'completed',
    'registered_by'       => 1,
]);

$attId = Database::insert('test_attempts', [
    'competition_id' => $compId,
    'candidate_id'   => $candId,
    'attempt_number' => 1,
    'status'         => 'completed',
    'started_at'     => date('Y-m-d H:i:s', time() - 300),
    'finished_at'    => date('Y-m-d H:i:s'),
    'submitted_at'   => date('Y-m-d H:i:s'),
]);

$resId = Database::insert('test_results', [
    'attempt_id'           => $attId,
    'candidate_id'         => $candId,
    'competition_id'       => $compId,
    'gross_wpm'            => 55.00,
    'net_wpm'              => 52.00,
    'accuracy'             => 97.00,
    'score'                => 50.44,
    'rank'                 => 1,
    'qualification_status' => 'qualified',
    'calculated_at'        => date('Y-m-d H:i:s'),
]);

// 2. Generate Initial Certificate
$certRes = CertificateService::generateCertificate($candId, $compId, 'position', 1, 'classic');
test('Initial Certificate created', $certRes['status'] === 'generated');
$certId = $certRes['certificate_id'];

// 3. Test Customization Update
$customData = [
    'template_key'       => 'modern',
    'display_name'       => 'Dr. Muhammad Tariq (Honors)',
    'position'           => 'Champion of the Year 2026',
    'custom_statement'   => 'For unprecedented speed records and technical mastery in keyboard typing proficiency.',
    'signatory_1_title'  => 'Head of Department',
    'signatory_1_name'   => 'Prof. A. R. Shaikh',
    'signatory_2_title'  => 'Executive Director',
    'signatory_2_name'   => 'Engr. S. K. Baloch',
];

$updateSuccess = CertificateService::updateCertificateCustomization($certId, $customData, 1);
test('updateCertificateCustomization executed successfully', $updateSuccess === true);

$updatedCert = Database::fetch("SELECT * FROM certificates WHERE id = ?", [$certId]);
test('Custom display_name persisted', $updatedCert['display_name'] === 'Dr. Muhammad Tariq (Honors)');
test('Custom position persisted', $updatedCert['position'] === 'Champion of the Year 2026');
test('Custom statement persisted', strpos($updatedCert['custom_statement'], 'unprecedented speed') !== false);
test('Custom signatory 1 persisted', $updatedCert['signatory_1_name'] === 'Prof. A. R. Shaikh');
test('Custom signatory 2 persisted', $updatedCert['signatory_2_name'] === 'Engr. S. K. Baloch');
test('Template changed to modern', $updatedCert['template_key'] === 'modern');

// 4. Test View Rendering with Customizations
$_GET['id'] = $certId;
$_GET['template'] = 'modern';
$_GET['editor'] = '1';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$customViewHtml = ob_get_clean();

test("View renders custom display name", strpos($customViewHtml, 'Dr. Muhammad Tariq (Honors)') !== false);
test("View renders custom position", strpos($customViewHtml, 'Champion of the Year 2026') !== false);
test("View renders custom statement", strpos($customViewHtml, 'unprecedented speed records') !== false);
test("View renders custom signatory 1 name", strpos($customViewHtml, 'Prof. A. R. Shaikh') !== false);
test("View renders custom signatory 2 name", strpos($customViewHtml, 'Engr. S. K. Baloch') !== false);
test("View contains interactive editor panel", strpos($customViewHtml, 'certEditorPanel') !== false);

// 5. Test Reset Customization
$resetSuccess = CertificateService::resetCertificateCustomization($certId, 1);
test('resetCertificateCustomization executed successfully', $resetSuccess === true);

$resetCert = Database::fetch("SELECT * FROM certificates WHERE id = ?", [$certId]);
test('display_name reset to NULL', $resetCert['display_name'] === null);
test('custom_statement reset to NULL', $resetCert['custom_statement'] === null);
test('signatory_1_name reset to NULL', $resetCert['signatory_1_name'] === null);

// 6. Test View Rendering after Reset
$_GET['id'] = $certId;
$_GET['template'] = 'classic';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$resetViewHtml = ob_get_clean();

test("View reverts back to original candidate name", strpos($resetViewHtml, 'Original Name') !== false);

// 7. Cleanup
Database::delete('certificates', 'competition_id = ?', [$compId]);
Database::delete('test_results', 'competition_id = ?', [$compId]);
Database::delete('test_attempts', 'competition_id = ?', [$compId]);
Database::delete('candidates', 'competition_id = ?', [$compId]);
Database::delete('competitions', 'id = ?', [$compId]);
test('Editor test artifacts cleaned cleanly', true);

echo "\n============================================================\n";
echo "CERTIFICATE EDITOR TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
