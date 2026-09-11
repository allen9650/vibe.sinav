<?php
declare(strict_types=1);

/**
 * Direct Test: Practical Certificate Generation & Net WPM Verification
 */

echo "=== PRACTICAL CERTIFICATE GENERATION TEST ===\n\n";

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

// Clean any old certificate for candidate 44 in competition 11
Database::delete('certificates', 'competition_id = ? AND candidate_id = ?', [11, 44]);

echo "1. PREVIEW ELIGIBILITY CHECK:\n";
$leaderboard = RankingService::getLeaderboard(11, false);
echo "Best Candidate Record on Leaderboard:\n";
foreach ($leaderboard as $entry) {
    echo "- Name: {$entry['candidate_name']} | Net WPM: {$entry['net_wpm']} | Accuracy: {$entry['accuracy']}% | Status: {$entry['qualification_status']}\n";
}

echo "\n2. BULK GENERATION (Mode C — All Completed Tests, Template: Classic):\n";
$bulkRes = CertificateService::bulkGenerate(11, 'all_completed', [], 1, 'classic');
echo "Generation Result: Generated={$bulkRes['generated']}, Skipped={$bulkRes['skipped']}\n";

$cert = Database::fetch("SELECT * FROM certificates WHERE competition_id = 11 AND candidate_id = 44");
if ($cert) {
    echo "\n[PASS] Certificate successfully created in database!\n";
    echo "- Certificate #: {$cert['certificate_number']}\n";
    echo "- Candidate ID: {$cert['candidate_id']}\n";
    echo "- Result ID: {$cert['result_id']}\n";
    echo "- Template Key: {$cert['template_key']}\n";
    echo "- Net WPM Snapshot: {$cert['net_wpm_snapshot']}\n";
    echo "- Accuracy Snapshot: {$cert['accuracy_snapshot']}%\n";
    echo "- Score Snapshot: {$cert['score_snapshot']}\n";
    echo "- Status: {$cert['status']}\n";
} else {
    echo "\n[FAIL] Certificate row was not found in database!\n";
    exit(1);
}

echo "\n3. DUPLICATE GENERATION PREVENTION TEST:\n";
$secondRes = CertificateService::bulkGenerate(11, 'all_completed', [], 1, 'classic');
echo "Second Generation Result: Generated={$secondRes['generated']}, Skipped={$secondRes['skipped']}\n";
if ($secondRes['generated'] === 0 && $secondRes['skipped'] >= 1) {
    echo "[PASS] Duplicate generation prevented gracefully.\n";
} else {
    echo "[FAIL] Duplicate generation was not properly skipped!\n";
    exit(1);
}

echo "\n4. RENDER CERTIFICATE VIEW TEST:\n";
$_GET['id'] = $cert['id'];
$_GET['template'] = 'classic';
ob_start();
include __DIR__ . '/../views/certificates/view.php';
$html = ob_get_clean();

if (strpos($html, $cert['certificate_number']) !== false && strpos($html, 'tpl-classic') !== false) {
    echo "[PASS] Certificate view rendered cleanly with Certificate #{$cert['certificate_number']}!\n";
} else {
    echo "[FAIL] Certificate view rendering failed!\n";
    exit(1);
}

echo "\n=== ALL PRACTICAL GENERATION CHECKS PASSED ===\n";
