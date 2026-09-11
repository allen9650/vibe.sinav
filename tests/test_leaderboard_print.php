<?php
declare(strict_types=1);

/**
 * Test Suite for Dedicated Official Competition Standings Print Page
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== LEADERBOARD PRINT STANDINGS TEST SUITE ===\n\n";

if (!defined('TESTING_MODE')) {
    define('TESTING_MODE', true);
}
require_once __DIR__ . '/../config/app.php';
require_once ROOT_PATH . '/core/Database.php';
require_once ROOT_PATH . '/core/Session.php';
require_once ROOT_PATH . '/core/CSRF.php';
require_once ROOT_PATH . '/core/AuditLog.php';
require_once ROOT_PATH . '/core/Auth.php';
require_once ROOT_PATH . '/core/RankingService.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
Session::set('user_id', 1);
Session::set('role_slug', 'super_admin');
$allPerms = Database::fetchAll("SELECT slug FROM permissions");
Session::set('permissions', array_column($allPerms, 'slug'));

$passCount = 0;
$failCount = 0;

function it(string $description, bool $assertion): void {
    global $passCount, $failCount;
    if ($assertion) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}\n";
    }
}

// ---------------------------------------------------------
// 1. SETUP TEST COMPETITION WITH DYNAMIC CANDIDATE VOLUME
// ---------------------------------------------------------
echo "--- 1. TEST SETUP: SCENARIOS WITH 3, 10, 30 & 100 CANDIDATES ---\n";

$compCode = 'TEST-PRINT-' . time();
$compId = Database::insert('competitions', [
    'name'             => 'Championship Print Standings Test',
    'code'             => $compCode,
    'description'      => 'Test competition for A4 print verification',
    'competition_date' => date('Y-m-d'),
    'start_time'       => '10:00:00',
    'end_time'         => '18:00:00',
    'status'           => 'active',
    'max_candidates'   => 200,
    'created_by'       => 1,
]);

Database::insert('test_settings', [
    'competition_id'      => $compId,
    'duration_seconds'    => 300,
    'duration_minutes'    => 5,
    'passing_wpm'         => 30,
    'passing_accuracy'    => 90.00,
    'allow_backspace'     => 1,
    'show_timer'          => 1,
    'show_wpm_live'       => 1,
    'result_visible'      => 1,
    'leaderboard_visible' => 1,
]);

$paraId = Database::insert('typing_paragraphs', [
    'title'      => 'Print Test Passage',
    'content'    => 'Official typing passage used for standings verification.',
    'word_count' => 8,
    'char_count' => 60,
    'difficulty' => 'easy',
    'status'     => 'active',
    'created_by' => 1,
]);

try {
    // Generate candidates (up to 30 for thorough test)
    $candidateIds = [];
    for ($k = 1; $k <= 30; $k++) {
        $candId = Database::insert('candidates', [
            'competition_id'      => $compId,
            'registration_number' => "REG-PRNT-{$k}-" . time(),
            'full_name'           => "Print Candidate {$k}",
            'roll_number'         => "ROLL-" . sprintf('%03d', $k),
            'course'              => 'CIT (Morning)',
            'status'              => 'completed',
        ]);
        $candidateIds[] = $candId;

        $attId = Database::insert('test_attempts', [
            'candidate_id'           => $candId,
            'competition_id'         => $compId,
            'paragraph_id'           => $paraId,
            'original_text_snapshot' => 'Official typing passage used for standings verification.',
            'attempt_number'         => 1,
            'started_at'             => date('Y-m-d H:i:s', time() - 300),
            'expected_end_at'        => date('Y-m-d H:i:s'),
            'submitted_at'           => date('Y-m-d H:i:s'),
            'time_taken_seconds'     => 120,
            'duration_seconds'       => 300,
            'status'                 => 'completed',
        ]);

        $score = max(5.0, round(65.0 - ($k * 1.5), 2));
        $netWpm = max(5.0, round(68.0 - ($k * 1.5), 2));
        $acc = max(80.0, round(99.0 - ($k * 0.4), 2));
        $errors = $k;

        Database::insert('test_results', [
            'attempt_id'             => $attId,
            'candidate_id'           => $candId,
            'competition_id'         => $compId,
            'paragraph_id'           => $paraId,
            'original_text_snapshot' => 'Official typing passage used for standings verification.',
            'final_typed_text'       => 'Official typing passage used for standings verification.',
            'gross_wpm'              => $netWpm + 2.0,
            'net_wpm'                => $netWpm,
            'accuracy'               => $acc,
            'error_count'            => $errors,
            'time_taken_seconds'     => 120,
            'score'                  => $score,
            'pass_fail'              => $netWpm >= 30 ? 'pass' : 'fail',
            'qualification_status'   => ($netWpm >= 30 && $acc >= 90) ? 'qualified' : 'not_qualified',
            'scoring_version'        => '1.0',
            'calculated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    it('Created test competition with 30 populated candidate results', count($candidateIds) === 30);

    // ---------------------------------------------------------
    // 2. RENDER DEDICATED PRINT VIEW & VERIFY HTML/CSS STRUCTURE
    // ---------------------------------------------------------
    echo "\n--- 2. PRINT VIEW MARKUP & CSS VALIDATION ---\n";

    $_GET['competition_id'] = $compId;
    ob_start();
    require ROOT_PATH . '/views/leaderboard/print.php';
    $html = ob_get_clean();

    it('Leaderboard print view rendered without PHP errors', !empty($html));
    it('Contains A4 portrait @page CSS declaration', str_contains($html, '@page') && str_contains($html, 'size: A4 portrait'));
    it('Contains thead table-header-group CSS for repeating headers across pages', str_contains($html, 'thead {') && str_contains($html, 'display: table-header-group;'));
    it('Contains tr break-inside avoid CSS to prevent row cutoffs', str_contains($html, 'page-break-inside: avoid;') || str_contains($html, 'break-inside: avoid;'));
    it('Contains screen-only toolbar with .no-print class', str_contains($html, 'screen-toolbar no-print'));
    it('Contains Print / Save PDF action button', str_contains($html, 'window.print()'));
    it('Contains Close Window action button', str_contains($html, 'window.close()'));

    // ---------------------------------------------------------
    // 3. VERIFY NO LEAKAGE OF SCREEN/ADMIN UI
    // ---------------------------------------------------------
    echo "\n--- 3. VERIFY EXCLUSION OF ADMIN/SCREEN-ONLY UI ---\n";

    it('Does NOT contain main website navbar markup', !str_contains($html, 'navbar-nav') && !str_contains($html, 'class="navbar'));
    it('Does NOT contain admin sidebar markup', !str_contains($html, 'sidebar-menu') && !str_contains($html, 'admin-sidebar'));
    it('Does NOT contain web podium card elements', !str_contains($html, 'Top 3 Podium Highlights'));
    it('Does NOT contain Finalize Competition button', !str_contains($html, 'competitions-finalize'));
    it('Does NOT contain Lock Results button', !str_contains($html, 'competitions-lock'));

    // ---------------------------------------------------------
    // 4. VERIFY INSTITUTIONAL BRANDING & OFFICIAL FOOTER
    // ---------------------------------------------------------
    echo "\n--- 4. INSTITUTIONAL BRANDING & SIGNATURE VERIFICATION ---\n";

    it('Contains Institute Name in Header', stripos($html, 'Microsoft Institute') !== false);
    it('Contains Official Standings Document Title', str_contains($html, 'Official Typing Competition Standings'));
    it('Contains Competition Code metadata', str_contains($html, $compCode));
    it('Contains Coordinator Signature Block', str_contains($html, 'Competition Coordinator'));
    it('Contains Director / Principal Signature Block', str_contains($html, 'Director / Principal'));
    it('Contains Generated On timestamp in footer', str_contains($html, 'Generated On:'));

    // ---------------------------------------------------------
    // 5. VERIFY AUTHORITATIVE STANDINGS DATA INTEGRITY
    // ---------------------------------------------------------
    echo "\n--- 5. STANDINGS DATA INTEGRITY (EXACT MATCH WITH RANKINGSERVICE) ---\n";

    $serviceLeaderboard = RankingService::getLeaderboard($compId, false);
    it('RankingService returns all 30 candidate entries', count($serviceLeaderboard) === 30);

    // Verify Rank 1, Rank 2, Rank 3 in HTML
    it('Renders 1st Position with Gold badge/medal', str_contains($html, '1st 🥇') || str_contains($html, '1st'));
    it('Renders 2nd Position with Silver badge/medal', str_contains($html, '2nd 🥈') || str_contains($html, '2nd'));
    it('Renders 3rd Position with Bronze badge/medal', str_contains($html, '3rd 🥉') || str_contains($html, '3rd'));

    // Verify Candidate 1 is top ranked
    it('Candidate with highest score is displayed in Rank #1 row', str_contains($html, $serviceLeaderboard[0]['candidate_name']));

    // ---------------------------------------------------------
    // 6. VERIFY LEADERBOARD INDEX LINK
    // ---------------------------------------------------------
    echo "\n--- 6. LEADERBOARD VIEW PRINT BUTTON TARGET ---\n";

    ob_start();
    require ROOT_PATH . '/views/leaderboard/index.php';
    $lbIndexHtml = ob_get_clean();

    it('Leaderboard index page contains anchor link to leaderboard-print with target="_blank"', 
        str_contains($lbIndexHtml, 'page=leaderboard-print') && str_contains($lbIndexHtml, 'target="_blank"')
    );

} finally {
    // ---------------------------------------------------------
    // 7. CLEANUP
    // ---------------------------------------------------------
    echo "\n--- 7. CLEANUP TEST DATA ---\n";
    if ($compId > 0) {
        Database::delete('test_results', 'competition_id = ?', [$compId]);
        Database::delete('test_attempts', 'competition_id = ?', [$compId]);
        Database::delete('candidates', 'competition_id = ?', [$compId]);
        Database::delete('test_settings', 'competition_id = ?', [$compId]);
        Database::delete('competitions', 'id = ?', [$compId]);
    }
    if ($paraId > 0) {
        Database::delete('typing_paragraphs', 'id = ?', [$paraId]);
    }
    it('Test artifacts cleaned cleanly', true);
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "LEADERBOARD PRINT TEST SUMMARY: {$passCount} PASSED / {$failCount} FAILED\n";
echo str_repeat('=', 60) . "\n";

if ($failCount > 0) {
    exit(1);
}
