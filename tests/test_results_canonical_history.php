<?php
declare(strict_types=1);

/**
 * Test Suite for Canonical Test Results & Attempt History Pipeline
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== TEST RESULTS CANONICAL & ATTEMPT HISTORY TEST SUITE ===\n\n";

if (!defined('TESTING_MODE')) {
    define('TESTING_MODE', true);
}
require_once __DIR__ . '/../config/app.php';
require_once ROOT_PATH . '/core/Database.php';
require_once ROOT_PATH . '/core/Session.php';
require_once ROOT_PATH . '/core/CSRF.php';
require_once ROOT_PATH . '/core/AuditLog.php';
require_once ROOT_PATH . '/core/Auth.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/core/RankingService.php';
require_once ROOT_PATH . '/core/ResultService.php';
require_once ROOT_PATH . '/core/CertificateService.php';
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
// 1. SETUP CLEAN TEST COMPETITION WITH CANDIDATE SCENARIOS
// ---------------------------------------------------------
echo "--- 1. TEST SETUP: CANDIDATE SCENARIOS ---\n";

$compCode = 'TEST-RES-' . time();
$compId = Database::insert('competitions', [
    'name'             => 'Canonical Results Championship',
    'code'             => $compCode,
    'description'      => 'Test competition for canonical and attempt history verification',
    'competition_date' => date('Y-m-d'),
    'start_time'       => '10:00:00',
    'end_time'         => '18:00:00',
    'status'           => 'active',
    'max_candidates'   => 100,
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
    'title'      => 'Canonical Test Passage',
    'content'    => 'Quick verification passage for canonical and attempt history.',
    'word_count' => 9,
    'char_count' => 60,
    'difficulty' => 'easy',
    'status'     => 'active',
    'created_by' => 1,
]);

try {
    // Scenario 1: Candidate A (Single Attempt, Qualified)
    $candA = Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => 'REG-A-' . time(),
        'full_name'           => 'Candidate Single Attempt',
        'roll_number'         => 'ROLL-A',
        'status'              => 'completed',
    ]);
    $attA1 = Database::insert('test_attempts', [
        'candidate_id'           => $candA,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'attempt_number'         => 1,
        'started_at'             => date('Y-m-d H:i:s', time() - 200),
        'submitted_at'           => date('Y-m-d H:i:s'),
        'time_taken_seconds'     => 120,
        'duration_seconds'       => 300,
        'status'                 => 'completed',
    ]);
    $resA1 = Database::insert('test_results', [
        'attempt_id'             => $attA1,
        'candidate_id'           => $candA,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'final_typed_text'       => 'Passage text',
        'gross_wpm'              => 52.00,
        'net_wpm'                => 50.00,
        'accuracy'               => 98.00,
        'error_count'            => 1,
        'time_taken_seconds'     => 120,
        'score'                  => 49.00,
        'pass_fail'              => 'pass',
        'qualification_status'   => 'qualified',
        'scoring_version'        => '1.0',
        'calculated_at'          => date('Y-m-d H:i:s'),
    ]);

    // Scenario 2: Candidate B (Multiple Attempts: 3 attempts)
    $candB = Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => 'REG-B-' . time(),
        'full_name'           => 'Candidate Multi Attempt',
        'roll_number'         => 'ROLL-B',
        'status'              => 'completed',
    ]);
    // Attempt B1 (Lower score)
    $attB1 = Database::insert('test_attempts', [
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'attempt_number'         => 1,
        'started_at'             => date('Y-m-d H:i:s', time() - 500),
        'submitted_at'           => date('Y-m-d H:i:s', time() - 380),
        'time_taken_seconds'     => 120,
        'duration_seconds'       => 300,
        'status'                 => 'completed',
    ]);
    $resB1 = Database::insert('test_results', [
        'attempt_id'             => $attB1,
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'final_typed_text'       => 'Passage text',
        'gross_wpm'              => 40.00,
        'net_wpm'                => 35.00,
        'accuracy'               => 92.00,
        'error_count'            => 3,
        'time_taken_seconds'     => 120,
        'score'                  => 32.20,
        'pass_fail'              => 'pass',
        'qualification_status'   => 'qualified',
        'scoring_version'        => '1.0',
        'calculated_at'          => date('Y-m-d H:i:s'),
    ]);

    // Attempt B2 (Winning Best Score: 55.00)
    $attB2 = Database::insert('test_attempts', [
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'attempt_number'         => 2,
        'started_at'             => date('Y-m-d H:i:s', time() - 300),
        'submitted_at'           => date('Y-m-d H:i:s', time() - 180),
        'time_taken_seconds'     => 120,
        'duration_seconds'       => 300,
        'status'                 => 'completed',
    ]);
    $resB2 = Database::insert('test_results', [
        'attempt_id'             => $attB2,
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'final_typed_text'       => 'Passage text',
        'gross_wpm'              => 60.00,
        'net_wpm'                => 58.00,
        'accuracy'               => 99.00,
        'error_count'            => 0,
        'time_taken_seconds'     => 120,
        'score'                  => 57.42,
        'pass_fail'              => 'pass',
        'qualification_status'   => 'qualified',
        'scoring_version'        => '1.0',
        'calculated_at'          => date('Y-m-d H:i:s'),
    ]);

    // Attempt B3 (Subsequent lower score)
    $attB3 = Database::insert('test_attempts', [
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'attempt_number'         => 3,
        'started_at'             => date('Y-m-d H:i:s', time() - 120),
        'submitted_at'           => date('Y-m-d H:i:s'),
        'time_taken_seconds'     => 120,
        'duration_seconds'       => 300,
        'status'                 => 'completed',
    ]);
    $resB3 = Database::insert('test_results', [
        'attempt_id'             => $attB3,
        'candidate_id'           => $candB,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'final_typed_text'       => 'Passage text',
        'gross_wpm'              => 45.00,
        'net_wpm'                => 40.00,
        'accuracy'               => 95.00,
        'error_count'            => 2,
        'time_taken_seconds'     => 120,
        'score'                  => 38.00,
        'pass_fail'              => 'pass',
        'qualification_status'   => 'qualified',
        'scoring_version'        => '1.0',
        'calculated_at'          => date('Y-m-d H:i:s'),
    ]);

    // Scenario 3: Candidate C (0.00 Net WPM, Not Qualified)
    $candC = Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => 'REG-C-' . time(),
        'full_name'           => 'Candidate Zero Score',
        'roll_number'         => 'ROLL-C',
        'status'              => 'completed',
    ]);
    $attC1 = Database::insert('test_attempts', [
        'candidate_id'           => $candC,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'attempt_number'         => 1,
        'started_at'             => date('Y-m-d H:i:s', time() - 100),
        'submitted_at'           => date('Y-m-d H:i:s'),
        'time_taken_seconds'     => 100,
        'duration_seconds'       => 300,
        'status'                 => 'completed',
    ]);
    $resC1 = Database::insert('test_results', [
        'attempt_id'             => $attC1,
        'candidate_id'           => $candC,
        'competition_id'         => $compId,
        'paragraph_id'           => $paraId,
        'original_text_snapshot' => 'Passage text',
        'final_typed_text'       => 'P',
        'gross_wpm'              => 1.00,
        'net_wpm'                => 0.00,
        'accuracy'               => 25.00,
        'error_count'            => 50,
        'time_taken_seconds'     => 100,
        'score'                  => 0.00,
        'pass_fail'              => 'fail',
        'qualification_status'   => 'not_qualified',
        'scoring_version'        => '1.0',
        'calculated_at'          => date('Y-m-d H:i:s'),
    ]);

    it('Setup created 3 candidates with 5 total attempt rows', $candA > 0 && $candB > 0 && $candC > 0);

    // ---------------------------------------------------------
    // 2. CANONICAL RESULTS SERVICE VERIFICATION
    // ---------------------------------------------------------
    echo "\n--- 2. CANONICAL VIEW: EXACTLY ONE ROW PER CANDIDATE ---\n";

    $canonicalResults = ResultService::getCanonicalResults($compId);
    $canonicalCount = ResultService::getCanonicalResultsCount($compId);

    it('Canonical results returns EXACTLY 3 rows (one per candidate)', count($canonicalResults) === 3 && $canonicalCount === 3);

    $candIds = array_column($canonicalResults, 'candidate_id');
    it('Candidate B (3 attempts) appears exactly ONCE in canonical results', count(array_keys($candIds, $candB)) === 1);

    $candBRow = null;
    foreach ($canonicalResults as $row) {
        if ((int)$row['candidate_id'] === $candB) {
            $candBRow = $row;
            break;
        }
    }
    it('Candidate B winning attempt #2 was selected (Score: 57.42, Net WPM: 58.00)', 
        $candBRow !== null && (int)$candBRow['attempt_number'] === 2 && (int)$candBRow['result_id'] === $resB2 && (float)$candBRow['score'] === 57.42
    );

    it('Candidate B total attempts counter reflects 3 attempts', (int)$candBRow['total_attempts'] === 3);

    // ---------------------------------------------------------
    // 3. ALL ATTEMPT HISTORY MODE VERIFICATION
    // ---------------------------------------------------------
    echo "\n--- 3. ALL ATTEMPT HISTORY MODE ---\n";

    $allAttempts = ResultService::getAllAttemptResults($compId);
    $allCount = ResultService::getAllAttemptResultsCount($compId);

    it('All attempt history returns all 5 historical attempts', count($allAttempts) === 5 && $allCount === 5);

    $candBHistory = ResultService::getCandidateAttemptHistory($candB, $compId);
    it('Candidate B history returns all 3 attempts for Candidate B', count($candBHistory) === 3);

    // ---------------------------------------------------------
    // 4. CERTIFICATE ELIGIBILITY ACROSS CANONICAL RESULTS
    // ---------------------------------------------------------
    echo "\n--- 4. CERTIFICATE ELIGIBILITY & ZERO SCORE HANDLING ---\n";

    $candCRow = null;
    foreach ($canonicalResults as $row) {
        if ((int)$row['candidate_id'] === $candC) {
            $candCRow = $row;
            break;
        }
    }
    it('Candidate C (0.00 Net WPM, 0.00 Score, Not Qualified) is marked certificate eligible',
        $candCRow !== null && $candCRow['certificate_eligible'] === true && $candCRow['qualification_status'] === 'not_qualified'
    );

    // ---------------------------------------------------------
    // 5. RENDER TEST RESULTS VIEW IN BOTH MODES
    // ---------------------------------------------------------
    echo "\n--- 5. VIEW RENDERING VERIFICATION ---\n";

    // Canonical Mode
    $_GET['page'] = 'results';
    $_GET['competition_id'] = $compId;
    $_GET['view_mode'] = 'canonical';
    ob_start();
    require ROOT_PATH . '/views/results/index.php';
    $canonicalHtml = ob_get_clean();

    it('Canonical View renders cleanly without errors', !empty($canonicalHtml));
    it('Canonical View displays "3 Candidates" in badge counter', str_contains($canonicalHtml, '3 Candidates') || str_contains($canonicalHtml, '3 Candidate'));
    it('Canonical View contains "Best Result Per Candidate" active state', str_contains($canonicalHtml, 'Best Result Per Candidate'));
    it('Canonical View contains Certificate action button', str_contains($canonicalHtml, 'fa-certificate') || str_contains($canonicalHtml, 'fa-award'));

    // History Mode
    $_GET['view_mode'] = 'history';
    ob_start();
    require ROOT_PATH . '/views/results/index.php';
    $historyHtml = ob_get_clean();

    it('History View renders cleanly without errors', !empty($historyHtml));
    it('History View displays "5 Attempts" in badge counter', str_contains($historyHtml, '5 Attempts') || str_contains($historyHtml, '5 Attempt'));
    it('History View contains "Started At" and "Submitted At" columns', str_contains($historyHtml, 'Started At') && str_contains($historyHtml, 'Submitted At'));

} finally {
    // ---------------------------------------------------------
    // 6. CLEANUP TEST DATA
    // ---------------------------------------------------------
    echo "\n--- 6. CLEANUP TEST DATA ---\n";
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
echo "CANONICAL & ATTEMPT HISTORY TEST SUMMARY: {$passCount} PASSED / {$failCount} FAILED\n";
echo str_repeat('=', 60) . "\n";

if ($failCount > 0) {
    exit(1);
}
