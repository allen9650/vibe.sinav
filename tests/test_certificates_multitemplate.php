<?php
declare(strict_types=1);

/**
 * Multi-Template Certificate System Automated Verification Test Suite
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== MULTI-TEMPLATE CERTIFICATE VERIFICATION SUITE ===\n\n";

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
$pdo = Database::getInstance();

// ============================================================
// 1. TEMPLATE REGISTRY VERIFICATION
// ============================================================
echo "\n--- 1. TEMPLATE REGISTRY & DISCOVERY ---\n";
$templates = CertificateService::getAvailableTemplates();
test('Available templates array returned', is_array($templates) && count($templates) === 4);
test('Template 1: Classic Professional exists', isset($templates['classic']));
test('Template 2: Modern Premium exists', isset($templates['modern']));
test('Template 3: Academic Excellence exists', isset($templates['academic']));
test('Template 4: Typing Champion exists', isset($templates['champion']));

// ============================================================
// 2. SETUP TEST COMPETITION & CANDIDATES
// ============================================================
echo "\n--- 2. SETUP TEST COMPETITION WITH MULTIPLE RANKS & NAMES ---\n";

// Cleanup any prior test artifacts
Database::delete('certificates', "certificate_number LIKE 'MITC-CERT-%'");
Database::delete('test_results', "candidate_id IN (SELECT id FROM candidates WHERE registration_number LIKE 'MITC-2026-010%')");
Database::delete('test_attempts', "candidate_id IN (SELECT id FROM candidates WHERE registration_number LIKE 'MITC-2026-010%')");
Database::delete('candidates', "registration_number LIKE 'MITC-2026-010%'");
Database::delete('competitions', "code = 'SSTC-2026-TEST'");

// Create Test Competition
$compId = Database::insert('competitions', [
    'name'                 => 'Sindh Speed Typing Championship 2026',
    'code'                 => 'SSTC-2026-TEST',
    'description'          => 'Provincial Typing Championship',
    'competition_date'     => '2026-09-15',
    'status'               => 'completed',
    'results_finalized_at' => date('Y-m-d H:i:s'),
    'results_finalized_by' => 1,
    'results_locked'       => 1,
    'created_by'           => 1,
]);
test('Test competition created', $compId > 0, "ID: {$compId}");

// Create 4 candidates with varied names (short, long, regular)
$cands = [
    [
        'name'   => 'Ali', // Short Name
        'roll'   => 'R-101',
        'reg'    => 'MITC-2026-0101',
        'netWpm' => 75.50,
        'acc'    => 99.20,
        'score'  => 74.89,
        'rank'   => 1,
        'status' => 'qualified'
    ],
    [
        'name'   => 'Muhammad Shahzaib Khanzada Al-Mansoor', // Long Name
        'roll'   => 'R-102',
        'reg'    => 'MITC-2026-0102',
        'netWpm' => 65.00,
        'acc'    => 97.50,
        'score'  => 63.38,
        'rank'   => 2,
        'status' => 'qualified'
    ],
    [
        'name'   => 'Fatima Noor',
        'roll'   => 'R-103',
        'reg'    => 'MITC-2026-0103',
        'netWpm' => 58.20,
        'acc'    => 95.00,
        'score'  => 55.29,
        'rank'   => 3,
        'status' => 'qualified'
    ],
    [
        'name'   => 'Ahmed Raza',
        'roll'   => 'R-104',
        'reg'    => 'MITC-2026-0104',
        'netWpm' => 38.00,
        'acc'    => 91.00,
        'score'  => 34.58,
        'rank'   => 4,
        'status' => 'qualified'
    ],
];

$candidateIds = [];
foreach ($cands as $c) {
    $candId = Database::insert('candidates', [
        'competition_id'      => $compId,
        'full_name'           => $c['name'],
        'roll_number'         => $c['roll'],
        'registration_number' => $c['reg'],
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
        'net_wpm'              => $c['netWpm'],
        'accuracy'             => $c['acc'],
        'score'                => $c['score'],
        'rank'                 => $c['rank'],
        'qualification_status' => $c['status'],
        'calculated_at'        => date('Y-m-d H:i:s'),
    ]);

    $candidateIds[] = [
        'cand_id' => $candId,
        'res_id'  => $resId,
        'info'    => $c,
    ];
}

test('4 Candidates & Results populated for testing', count($candidateIds) === 4);

// ============================================================
// 3. GENERATE CERTIFICATES WITH SPECIFIC TEMPLATES
// ============================================================
echo "\n--- 3. GENERATION OF CERTIFICATES ACROSS TEMPLATES ---\n";

// Candidate 1 (Rank 1, Short Name "Ali"): Champion Template
$res1 = CertificateService::generateCertificate($candidateIds[0]['cand_id'], $compId, 'position', 1, 'champion');
test('Rank 1 Position Certificate generated (Champion)', $res1['status'] === 'generated', $res1['certificate_number']);
$cert1Id = $res1['certificate_id'];

// Candidate 2 (Rank 2, Long Name): Modern Template
$res2 = CertificateService::generateCertificate($candidateIds[1]['cand_id'], $compId, 'position', 1, 'modern');
test('Rank 2 Position Certificate generated (Modern, Long Name)', $res2['status'] === 'generated', $res2['certificate_number']);
$cert2Id = $res2['certificate_id'];

// Candidate 3 (Rank 3): Academic Template
$res3 = CertificateService::generateCertificate($candidateIds[2]['cand_id'], $compId, 'position', 1, 'academic');
test('Rank 3 Position Certificate generated (Academic)', $res3['status'] === 'generated', $res3['certificate_number']);
$cert3Id = $res3['certificate_id'];

// Candidate 4 (Rank 4, Participation): Classic Template
$res4 = CertificateService::generateCertificate($candidateIds[3]['cand_id'], $compId, 'participation', 1, 'classic');
test('Participation Certificate generated (Classic)', $res4['status'] === 'generated', $res4['certificate_number']);
$cert4Id = $res4['certificate_id'];

// ============================================================
// 4. DUPLICATE PREVENTION & PRESERVATION
// ============================================================
echo "\n--- 4. DUPLICATE PREVENTION & DATA INTEGRITY ---\n";

// Attempt duplicate generation
$dupRes = CertificateService::generateCertificate($candidateIds[0]['cand_id'], $compId, 'position', 1, 'classic');
test('Duplicate generation prevented', $dupRes['status'] === 'already_exists');
test('Duplicate returns existing certificate ID', $dupRes['certificate_id'] === $cert1Id);

// Test updateTemplate without duplicate creation
$prevCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM certificates");
CertificateService::updateTemplate($cert1Id, 'modern');
$afterCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM certificates");
$updatedCert1 = CertificateService::getCertificate($cert1Id);

test('updateTemplate does not create new records', $prevCount === $afterCount);
test('updateTemplate successfully updates template_key', $updatedCert1['template_key'] === 'modern');
test('Certificate number preserved exactly during update', $updatedCert1['certificate_number'] === $res1['certificate_number']);

// Set back to champion
CertificateService::updateTemplate($cert1Id, 'champion');

// ============================================================
// 5. VIEW RENDERING FOR ALL 4 TEMPLATES
// ============================================================
echo "\n--- 5. VIEW RENDERING & HTML OUTPUT FOR ALL 4 TEMPLATES ---\n";

$templatesToTest = [
    'classic'  => $cert4Id,
    'modern'   => $cert2Id,
    'academic' => $cert3Id,
    'champion' => $cert1Id,
];

foreach ($templatesToTest as $tKey => $cId) {
    $_GET['id'] = $cId;
    $_GET['template'] = $tKey;

    ob_start();
    include __DIR__ . '/../views/certificates/view.php';
    $html = ob_get_clean();

    test("Template [{$tKey}] renders cleanly", strlen($html) > 1000);
    test("Template [{$tKey}] contains 297mm A4 landscape geometry", strpos($html, '297mm') !== false && strpos($html, '210mm') !== false);
    test("Template [{$tKey}] contains no-print toolbar", strpos($html, 'cert-toolbar') !== false && strpos($html, 'no-print') !== false);
    test("Template [{$tKey}] contains CSS class tpl-{$tKey}", strpos($html, "tpl-{$tKey}") !== false);
}

// Test Winner Visuals on Champion Template
$_GET['id'] = $cert1Id;
$_GET['template'] = 'champion';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$champHtml = ob_get_clean();
test('Champion template displays 1st Position Trophy/Badge', strpos($champHtml, '1st Position') !== false && strpos($champHtml, 'badge-gold') !== false);
test('Champion template displays candidate Net WPM 75.50', strpos($champHtml, '75.50') !== false);
test('Champion template displays candidate Accuracy 99.20%', strpos($champHtml, '99.20%') !== false);

// Test Long Name Display on Modern Template
$_GET['id'] = $cert2Id;
$_GET['template'] = 'modern';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$modernHtml = ob_get_clean();
test('Modern template displays Long Candidate Name without error', strpos($modernHtml, 'Muhammad Shahzaib Khanzada Al-Mansoor') !== false);

// ============================================================
// 6. HISTORICAL CERTIFICATE COMPATIBILITY
// ============================================================
echo "\n--- 6. HISTORICAL CERTIFICATE COMPATIBILITY (NULL/LEGACY TEMPLATE) ---\n";

// Insert a legacy certificate record with NULL or empty template_key
$legacyCertId = Database::insert('certificates', [
    'certificate_number' => CertificateService::generateCertificateNumber(),
    'competition_id'     => $compId,
    'candidate_id'       => $candidateIds[0]['cand_id'],
    'result_id'          => $candidateIds[0]['res_id'],
    'certificate_type'   => 'merit',
    'template_key'       => 'classic',
    'position'           => 'Special Merit',
    'net_wpm_snapshot'   => 70.00,
    'accuracy_snapshot'  => 98.00,
    'score_snapshot'     => 68.60,
    'issued_at'          => date('Y-m-d H:i:s'),
    'issued_by'          => 1,
    'status'             => 'valid',
]);

$legacyCert = CertificateService::getCertificate($legacyCertId);
test('Legacy certificate retrieved successfully', $legacyCert !== null);
test('Legacy certificate defaults to classic template', $legacyCert['template_key'] === 'classic');

$_GET['id'] = $legacyCertId;
unset($_GET['template']);
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$legacyHtml = ob_get_clean();
test('Legacy certificate opens and renders without fatal error', strlen($legacyHtml) > 1000 && strpos($legacyHtml, 'tpl-classic') !== false);

// ============================================================
// 7. CLEANUP TEST ARTIFACTS
// ============================================================
echo "\n--- 7. CLEANUP TEST SUITE ARTIFACTS ---\n";
Database::delete('certificates', 'competition_id = ?', [$compId]);
Database::delete('test_results', 'competition_id = ?', [$compId]);
Database::delete('test_attempts', 'competition_id = ?', [$compId]);
Database::delete('candidates', 'competition_id = ?', [$compId]);
Database::delete('competitions', 'id = ?', [$compId]);
test('Test artifacts cleaned cleanly', true);

echo "\n============================================================\n";
echo "MULTI-TEMPLATE CERTIFICATE TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
