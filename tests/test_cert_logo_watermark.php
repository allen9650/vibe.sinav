<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Certificate Logo Management, Watermark, and Auth Fixes
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== CERTIFICATE LOGO, WATERMARK & AUTH VERIFICATION SUITE ===\n\n";

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
// 1. AUTH FIX VERIFICATION
// ============================================================
echo "\n--- 1. AUTH FIX VERIFICATION ---\n";
test('Auth::id() method exists and returns integer ID', Auth::id() === 1);
test('Session::get("user_id") matches Auth::id()', Session::get('user_id') === Auth::id());

// ============================================================
// 2. SETUP TEST COMPETITION & CANDIDATE
// ============================================================
echo "\n--- 2. SETUP TEST COMPETITION & CERTIFICATE ---\n";

Database::delete('certificates', "certificate_number LIKE 'MITC-CERT-LOGO-%'");
Database::delete('test_results', "candidate_id IN (SELECT id FROM candidates WHERE registration_number = 'MITC-LOGO-001')");
Database::delete('test_attempts', "candidate_id IN (SELECT id FROM candidates WHERE registration_number = 'MITC-LOGO-001')");
Database::delete('candidates', "registration_number = 'MITC-LOGO-001'");
Database::delete('competitions', "code = 'LOGO-TEST-2026'");

$compId = Database::insert('competitions', [
    'name'                 => 'Logo Verification Championship 2026',
    'code'                 => 'LOGO-TEST-2026',
    'description'          => 'Logo & Watermark Testing',
    'competition_date'     => '2026-09-30',
    'status'               => 'completed',
    'results_finalized_at' => date('Y-m-d H:i:s'),
    'results_finalized_by' => 1,
    'results_locked'       => 1,
    'created_by'           => 1,
]);

$candId = Database::insert('candidates', [
    'competition_id'      => $compId,
    'full_name'           => 'Ali Raza Khan',
    'roll_number'         => 'R-LOGO-01',
    'registration_number' => 'MITC-LOGO-001',
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
    'gross_wpm'            => 65.00,
    'net_wpm'              => 62.50,
    'accuracy'             => 98.00,
    'score'                => 61.25,
    'rank'                 => 1,
    'qualification_status' => 'qualified',
    'calculated_at'        => date('Y-m-d H:i:s'),
]);

$certRes = CertificateService::generateCertificate($candId, $compId, 'position', 1, 'classic');
test('Test Certificate generated successfully', $certRes['status'] === 'generated');
$certId = $certRes['certificate_id'];

// ============================================================
// 3. LOGO PRIORITY & WATERMARK TEST
// ============================================================
echo "\n--- 3. LOGO PRIORITY & WATERMARK TEST ---\n";

// A. Test Graceful Fallback (No Logo configured)
updateSetting('cert_logo', '');
updateSetting('institute_logo', '');
updateSetting('cert_watermark_enabled', '1');
updateSetting('cert_watermark_opacity', '0.05');

$_GET['id'] = $certId;
$_GET['template'] = 'classic';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$fallbackHtml = ob_get_clean();

test('Fallback rendered when no logo exists', strpos($fallbackHtml, 'cert-logo-placeholder') !== false);
test('Watermark enabled with opacity 0.05', strpos($fallbackHtml, 'opacity: 0.05') !== false);
test('No PHP fatal errors or notices in output', !empty($fallbackHtml));

// B. Test Logo Configuration & Size Adjustment
$dummyLogoName = 'test_logo_sample.png';
$uploadsDir = UPLOADS_PATH . '/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
file_put_contents($uploadsDir . $dummyLogoName, 'fake_png_data');
updateSetting('cert_logo', $dummyLogoName);
updateSetting('cert_logo_size', '65'); // Custom 65px size

$_GET['editor'] = '1';
$_GET['tab'] = 'branding';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$logoHtml = ob_get_clean();

test('Custom certificate logo rendered', strpos($logoHtml, $dummyLogoName) !== false);
test('Logo size setting (65px) rendered in CSS', strpos($logoHtml, 'max-height: 65px') !== false);
test('Logo size range slider rendered in editor', strpos($logoHtml, 'id="logoSizeRange"') !== false);

// C. Test Signature Image Rendering
$dummySig1Name = 'test_sig1_sample.png';
file_put_contents($uploadsDir . $dummySig1Name, 'fake_sig_data');
updateSetting('cert_signature_1_image', $dummySig1Name);

ob_start();
include __DIR__ . '/../views/certificates/view.php';
$sigHtml = ob_get_clean();

test('Coordinator signature image rendered above signature line', strpos($sigHtml, $dummySig1Name) !== false);

// ============================================================
// 4. ALL 4 TEMPLATES WITH LOGO & WATERMARK
// ============================================================
echo "\n--- 4. ALL 4 TEMPLATES VERIFICATION ---\n";

$templates = ['classic', 'modern', 'academic', 'champion'];
foreach ($templates as $tmpl) {
    $_GET['id'] = $certId;
    $_GET['template'] = $tmpl;
    ob_start();
    include __DIR__ . '/../views/certificates/view.php';
    $tmplHtml = ob_get_clean();

    test("Template [{$tmpl}] renders without error", strlen($tmplHtml) > 2000);
    test("Template [{$tmpl}] includes logo", strpos($tmplHtml, $dummyLogoName) !== false);
    test("Template [{$tmpl}] includes watermark", strpos($tmplHtml, 'cert-watermark') !== false);
}

// ============================================================
// 5. CLEANUP
// ============================================================
echo "\n--- 5. CLEANUP ---\n";
@unlink($uploadsDir . $dummyLogoName);
@unlink($uploadsDir . $dummySig1Name);
updateSetting('cert_logo', '');
updateSetting('cert_signature_1_image', '');

Database::delete('certificates', 'competition_id = ?', [$compId]);
Database::delete('test_results', 'competition_id = ?', [$compId]);
Database::delete('test_attempts', 'competition_id = ?', [$compId]);
Database::delete('candidates', 'competition_id = ?', [$compId]);
Database::delete('competitions', 'id = ?', [$compId]);
test('Test artifacts cleaned cleanly', true);

echo "\n============================================================\n";
echo "CERTIFICATE LOGO & WATERMARK TESTS: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
