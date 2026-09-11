<?php
declare(strict_types=1);

/**
 * Microsoft Institute Typing Competition System
 * Comprehensive Integration Test Suite for:
 * - Candidate Multiple Attempts Handling
 * - Best Attempt Leaderboard Selection
 * - Live Monitor Modes (Active / Latest / All) & Unique Counters
 * - Mathematical 0.00 Net WPM Result & Participation Certificate Eligibility
 * - Mode C (All Completed) vs Mode D (Qualified) Certificate Generation
 * - Disqualified Candidate Exclusion
 * - Timed Out Attempt with Valid Result
 * - Authoritative Result Snapshot Consistency & Duplicate Prevention
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== INTEGRATION TEST SUITE: ATTEMPTS, MONITOR, LEADERBOARD & CERTIFICATES ===\n\n";

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
require_once ROOT_PATH . '/core/TypingCalculator.php';
require_once ROOT_PATH . '/core/RankingService.php';
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
// 1. SETUP CLEAN TEST COMPETITION & PARAGRAPH
// ---------------------------------------------------------
echo "--- 1. TEST SETUP: COMPETITION, CANDIDATES & SCENARIOS ---\n";

$compCode = 'TEST-INTEG-' . time();
$compId = 0;
$paraId = 0;

try {
    $compId = Database::insert('competitions', [
    'name'             => 'Integration Test Championship',
    'code'             => $compCode,
    'description'      => 'Automated test competition for multi-attempt, monitor, and cert validation',
    'competition_date' => date('Y-m-d'),
    'start_time'       => '09:00:00',
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

$paraText = "The quick brown fox jumps over the lazy dog. Programming clean systems requires discipline and accurate evaluation.";
$paraId = Database::insert('typing_paragraphs', [
    'title'      => 'Fox Test Paragraph',
    'content'    => $paraText,
    'word_count' => 17,
    'char_count' => mb_strlen($paraText),
    'difficulty' => 'easy',
    'status'     => 'active',
    'created_by' => 1,
]);

// Candidate A: 1 Completed Valid Attempt (High Score)
$candA = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => 'REG-A-' . time(),
    'full_name'           => 'Candidate Alpha (Single Attempt)',
    'roll_number'         => 'ROLL-A',
    'status'              => 'completed',
]);

$attA1 = Database::insert('test_attempts', [
    'candidate_id'           => $candA,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s', time() - 300),
    'expected_end_at'        => date('Y-m-d H:i:s'),
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
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => $paraText,
    'gross_wpm'              => 60.00,
    'net_wpm'                => 60.00,
    'accuracy'               => 100.00,
    'total_characters'       => mb_strlen($paraText),
    'correct_characters'     => mb_strlen($paraText),
    'incorrect_characters'   => 0,
    'missing_characters'     => 0,
    'extra_characters'       => 0,
    'total_words'            => 17,
    'correct_words'          => 17,
    'error_count'            => 0,
    'errors_per_minute'      => 0.00,
    'time_taken_seconds'     => 120,
    'score'                  => 60.00,
    'pass_fail'              => 'pass',
    'qualification_status'   => 'qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

// Candidate B: Multiple Completed Attempts (Attempt 1: low, Attempt 2: best, Attempt 3: medium)
$candB = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => 'REG-B-' . time(),
    'full_name'           => 'Candidate Bravo (Multi Attempt)',
    'roll_number'         => 'ROLL-B',
    'status'              => 'completed',
]);

// Attempt 1: Score 17.00
$attB1 = Database::insert('test_attempts', [
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s', time() - 600),
    'expected_end_at'        => date('Y-m-d H:i:s', time() - 300),
    'submitted_at'           => date('Y-m-d H:i:s', time() - 400),
    'time_taken_seconds'     => 200,
    'duration_seconds'       => 300,
    'status'                 => 'completed',
]);
$resB1 = Database::insert('test_results', [
    'attempt_id'             => $attB1,
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => substr($paraText, 0, 50),
    'gross_wpm'              => 25.00,
    'net_wpm'                => 20.00,
    'accuracy'               => 85.00,
    'error_count'            => 5,
    'time_taken_seconds'     => 200,
    'score'                  => 17.00,
    'pass_fail'              => 'fail',
    'qualification_status'   => 'not_qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

// Attempt 2: Best Score 48.00
$attB2 = Database::insert('test_attempts', [
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 2,
    'started_at'             => date('Y-m-d H:i:s', time() - 300),
    'expected_end_at'        => date('Y-m-d H:i:s'),
    'submitted_at'           => date('Y-m-d H:i:s', time() - 150),
    'time_taken_seconds'     => 150,
    'duration_seconds'       => 300,
    'status'                 => 'completed',
]);
$resB2 = Database::insert('test_results', [
    'attempt_id'             => $attB2,
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => $paraText,
    'gross_wpm'              => 52.00,
    'net_wpm'                => 50.00,
    'accuracy'               => 96.00,
    'error_count'            => 2,
    'time_taken_seconds'     => 150,
    'score'                  => 48.00,
    'pass_fail'              => 'pass',
    'qualification_status'   => 'qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

// Attempt 3: Score 32.20
$attB3 = Database::insert('test_attempts', [
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 3,
    'started_at'             => date('Y-m-d H:i:s', time() - 100),
    'expected_end_at'        => date('Y-m-d H:i:s', time() + 200),
    'submitted_at'           => date('Y-m-d H:i:s', time() - 10),
    'time_taken_seconds'     => 90,
    'duration_seconds'       => 300,
    'status'                 => 'completed',
]);
$resB3 = Database::insert('test_results', [
    'attempt_id'             => $attB3,
    'candidate_id'           => $candB,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => $paraText,
    'gross_wpm'              => 38.00,
    'net_wpm'                => 35.00,
    'accuracy'               => 92.00,
    'error_count'            => 3,
    'time_taken_seconds'     => 90,
    'score'                  => 32.20,
    'pass_fail'              => 'pass',
    'qualification_status'   => 'qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

// Candidate C: Completed with 0.00 Net WPM (Not Qualified)
$candC = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => 'REG-C-' . time(),
    'full_name'           => 'Candidate Charlie (Zero Net WPM)',
    'roll_number'         => 'ROLL-C',
    'status'              => 'completed',
]);
$attC1 = Database::insert('test_attempts', [
    'candidate_id'           => $candC,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s', time() - 120),
    'expected_end_at'        => date('Y-m-d H:i:s', time() + 180),
    'submitted_at'           => date('Y-m-d H:i:s'),
    'time_taken_seconds'     => 120,
    'duration_seconds'       => 300,
    'status'                 => 'completed',
]);
$resC1 = Database::insert('test_results', [
    'attempt_id'             => $attC1,
    'candidate_id'           => $candC,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => 'The qk',
    'gross_wpm'              => 1.00,
    'net_wpm'                => 0.00,
    'accuracy'               => 30.00,
    'error_count'            => 105,
    'errors_per_minute'      => 52.50,
    'time_taken_seconds'     => 120,
    'score'                  => 0.00,
    'pass_fail'              => 'fail',
    'qualification_status'   => 'not_qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

// Candidate D: Disqualified Candidate
$candD = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => 'REG-D-' . time(),
    'full_name'           => 'Candidate Delta (Disqualified)',
    'roll_number'         => 'ROLL-D',
    'status'              => 'disqualified',
]);
$attD1 = Database::insert('test_attempts', [
    'candidate_id'           => $candD,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s', time() - 200),
    'expected_end_at'        => date('Y-m-d H:i:s', time() + 100),
    'submitted_at'           => date('Y-m-d H:i:s'),
    'time_taken_seconds'     => 100,
    'duration_seconds'       => 300,
    'status'                 => 'disqualified',
]);

// Candidate E: Timed Out with Valid Result
$candE = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => 'REG-E-' . time(),
    'full_name'           => 'Candidate Echo (Timed Out)',
    'roll_number'         => 'ROLL-E',
    'status'              => 'completed',
]);
$attE1 = Database::insert('test_attempts', [
    'candidate_id'           => $candE,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s', time() - 300),
    'expected_end_at'        => date('Y-m-d H:i:s'),
    'submitted_at'           => date('Y-m-d H:i:s'),
    'time_taken_seconds'     => 300,
    'duration_seconds'       => 300,
    'status'                 => 'timed_out',
]);
$resE1 = Database::insert('test_results', [
    'attempt_id'             => $attE1,
    'candidate_id'           => $candE,
    'competition_id'         => $compId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraText,
    'final_typed_text'       => $paraText,
    'gross_wpm'              => 40.00,
    'net_wpm'                => 38.00,
    'accuracy'               => 95.00,
    'error_count'            => 2,
    'time_taken_seconds'     => 300,
    'score'                  => 36.10,
    'pass_fail'              => 'pass',
    'qualification_status'   => 'qualified',
    'scoring_version'        => '1.0',
    'calculated_at'          => date('Y-m-d H:i:s'),
]);

it('Setup created 5 distinct candidate test scenarios successfully', $candA > 0 && $candB > 0 && $candC > 0 && $candD > 0 && $candE > 0);

// ---------------------------------------------------------
// 2. LEADERBOARD BEST ATTEMPT & DISQUALIFIED EXCLUSION
// ---------------------------------------------------------
echo "\n--- 2. LEADERBOARD & BEST ATTEMPT ENGINE ---\n";

$leaderboard = RankingService::getLeaderboard($compId, false);
it('Default leaderboard returns exactly 4 candidates (Candidate D excluded due to disqualification)', count($leaderboard) === 4);

$candsOnLb = array_column($leaderboard, 'candidate_id');
it('Candidate B (Multi-attempt) appears exactly ONCE on default leaderboard', count(array_keys($candsOnLb, $candB)) === 1);

// Verify candidate B's best attempt was selected
$candBRow = null;
foreach ($leaderboard as $row) {
    if ((int)$row['candidate_id'] === $candB) {
        $candBRow = $row;
        break;
    }
}
it('Candidate B best attempt #2 was selected (Score: 48.00, Net WPM: 50.00)', 
    $candBRow !== null && (int)$candBRow['attempt_number'] === 2 && (float)$candBRow['score'] === 48.00
);

it('Candidate Alpha is Rank #1 (Score: 60.00, Gold medal)', 
    (int)$leaderboard[0]['candidate_id'] === $candA && (int)$leaderboard[0]['rank'] === 1 && $leaderboard[0]['medal'] !== null
);

it('Candidate Bravo is Rank #2 (Score: 48.00, Silver medal)', 
    (int)$leaderboard[1]['candidate_id'] === $candB && (int)$leaderboard[1]['rank'] === 2 && $leaderboard[1]['medal'] !== null
);

it('Candidate Echo (Timed Out) is Rank #3 (Score: 36.10, Bronze medal)', 
    (int)$leaderboard[2]['candidate_id'] === $candE && (int)$leaderboard[2]['rank'] === 3 && $leaderboard[2]['medal'] !== null
);

it('Candidate Charlie (0.00 Net WPM) is Rank #4', 
    (int)$leaderboard[3]['candidate_id'] === $candC && (int)$leaderboard[3]['rank'] === 4
);

// Verify getBestFinalizedResultForCandidate service method
$bestResultB = RankingService::getBestFinalizedResultForCandidate($candB, $compId);
it('RankingService::getBestFinalizedResultForCandidate returns Candidate B best attempt (#2, Score: 48.00) as single unit',
    $bestResultB !== null && (int)$bestResultB['id'] === $resB2 && (int)$bestResultB['attempt_id'] === $attB2 && (float)$bestResultB['score'] === 48.00 && (int)$bestResultB['candidate_id'] === $candB
);

// Verify cross-candidate isolation (no shared/leaked values)
$bestResultA = RankingService::getBestFinalizedResultForCandidate($candA, $compId);
$bestResultC = RankingService::getBestFinalizedResultForCandidate($candC, $compId);
it('Candidate Alpha and Candidate Bravo have completely isolated, distinct result rows',
    $bestResultA['id'] !== $bestResultB['id'] && (float)$bestResultA['score'] === 60.00 && (float)$bestResultB['score'] === 48.00
);
it('Candidate Charlie has independent 0.00 Net WPM result row',
    (float)$bestResultC['net_wpm'] === 0.00 && (float)$bestResultC['score'] === 0.00 && (int)$bestResultC['candidate_id'] === $candC
);

// Verify all attempts mode
$allLb = RankingService::getLeaderboard($compId, true);
it('Show All Attempts mode returns all 6 valid attempts across candidates', count($allLb) === 6);

// ---------------------------------------------------------
// 3. CANDIDATE TYPING PERFORMANCE CALCULATION VERIFICATION
// ---------------------------------------------------------
echo "\n--- 3. CANDIDATE TYPING PERFORMANCE CALCULATION VERIFICATION ---\n";

// TypingCalculator verification on Babar's exact stored snapshot text (partial typing)
$babarStoredResult = Database::fetch("SELECT * FROM test_results WHERE candidate_id = 116");
if ($babarStoredResult) {
    $babarRef = $babarStoredResult['original_text_snapshot'];
    $babarTyped = $babarStoredResult['final_typed_text'];
    $babarSeconds = (int)$babarStoredResult['time_taken_seconds'];

    $babarCalc = TypingCalculator::calculate($babarRef, $babarTyped, $babarSeconds, 0, []);
    it('Babar Gross WPM evaluates to 17.48', (float)$babarCalc['gross_wpm'] === 17.48);
    it('Babar Correct Characters evaluates to 148', (int)$babarCalc['correct_characters'] === 148);
    it('Babar Total Errors in typed content evaluates to 3', (int)$babarCalc['total_errors'] === 3);
    it('Babar Unreached Characters evaluates to 182 (not penalized as active errors)', (int)$babarCalc['unreached_characters'] === 182);
    it('Babar Net WPM evaluates accurately to 15.73 WPM', (float)$babarCalc['net_wpm'] === 15.73);
    it('Babar Accuracy on typed content evaluates to 98.01%', (float)$babarCalc['accuracy'] === 98.01);
    it('Babar Final Score evaluates to 15.42', (float)$babarCalc['final_score'] === 15.42);
    it('Babar Qualification evaluates to qualified', $babarCalc['qualification_status'] === 'qualified');
} else {
    it('Babar test result found', false);
}

// ---------------------------------------------------------
// 4. LIVE MONITOR TELEMETRY & VIEW MODES
// ---------------------------------------------------------
echo "\n--- 4. LIVE MONITOR TELEMETRY & VIEW MODES ---\n";

// Test Live Monitor API helper directly
$_GET['competition_id'] = $compId;
$_GET['view_mode'] = 'active';

// In active mode when no candidates are in_progress, returns latest attempt per candidate once
ob_start();
require ROOT_PATH . '/views/monitor/api.php';
$apiOutputActive = json_decode(ob_get_clean(), true);

it('Live Monitor API returns ok status', $apiOutputActive['status'] === 'ok');
it('Live Monitor Active Mode returns exactly 5 candidates (no duplicate attempts for Candidate B)', count($apiOutputActive['attempts']) === 5);
it('Live Monitor completed counter reflects 3 completed candidates (not inflated by retries)', (int)$apiOutputActive['counts']['completed'] === 3);
it('Live Monitor timed_out counter reflects 1 candidate', (int)$apiOutputActive['counts']['timed_out'] === 1);
it('Live Monitor disqualified counter reflects 1 candidate', (int)$apiOutputActive['counts']['disqualified'] === 1);

// Test Latest mode
$_GET['view_mode'] = 'latest';
ob_start();
require ROOT_PATH . '/views/monitor/api.php';
$apiOutputLatest = json_decode(ob_get_clean(), true);
it('Live Monitor Latest Mode returns 5 candidates', count($apiOutputLatest['attempts']) === 5);

// Test All Attempts mode
$_GET['view_mode'] = 'all';
ob_start();
require ROOT_PATH . '/views/monitor/api.php';
$apiOutputAll = json_decode(ob_get_clean(), true);
it('Live Monitor All Mode returns all 7 attempts including Candidate B history', count($apiOutputAll['attempts']) === 7);

// ---------------------------------------------------------
// 5. FINALIZATION & CERTIFICATE ELIGIBILITY
// ---------------------------------------------------------
echo "\n--- 5. CERTIFICATE GENERATION & ELIGIBILITY POLICIES ---\n";

// Finalize competition
RankingService::finalizeCompetition($compId, 1);
$compUpdated = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$compId]);
it('Competition finalized with results_finalized_at timestamp', !empty($compUpdated['results_finalized_at']));

// Verify final ranks persisted to test_results
$rankA = (int)Database::fetchColumn("SELECT rank FROM test_results WHERE id = ?", [$resA1]);
$rankB2 = (int)Database::fetchColumn("SELECT rank FROM test_results WHERE id = ?", [$resB2]);
$rankB1 = Database::fetchColumn("SELECT rank FROM test_results WHERE id = ?", [$resB1]);
it('Candidate Alpha test_results persisted rank #1', $rankA === 1);
it('Candidate Bravo best attempt test_results persisted rank #2', $rankB2 === 2);
it('Candidate Bravo secondary attempt test_results has NULL rank', $rankB1 === null);

// Test Mode C: All Completed Tests (Should include Candidate Charlie with 0.00 Net WPM)
$bulkModeC = CertificateService::bulkGenerate($compId, 'all_completed', [], 1, 'classic');
it('Mode C bulk generation generated certificates for all 4 completed/timed_out candidates', (int)$bulkModeC['generated'] === 4);

// Check Charlie's certificate
$certCharlie = Database::fetch("SELECT * FROM certificates WHERE candidate_id = ? AND competition_id = ?", [$candC, $compId]);
it('Candidate Charlie (0.00 Net WPM, Not Qualified) received Participation certificate under Mode C', 
    $certCharlie !== null && $certCharlie['certificate_type'] === 'participation' && (float)$certCharlie['net_wpm_snapshot'] === 0.00
);

// Check Alpha's certificate (Position 1st)
$certAlpha = Database::fetch("SELECT * FROM certificates WHERE candidate_id = ? AND competition_id = ?", [$candA, $compId]);
it('Candidate Alpha received 1st Position Achievement certificate with net_wpm_snapshot 60.00', 
    $certAlpha !== null && $certAlpha['certificate_type'] === 'position' && (float)$certAlpha['net_wpm_snapshot'] === 60.00 && $certAlpha['position'] === '1st Position'
);

// Check Bravo's certificate (Position 2nd using best attempt snapshot 50.00)
$certBravo = Database::fetch("SELECT * FROM certificates WHERE candidate_id = ? AND competition_id = ?", [$candB, $compId]);
it('Candidate Bravo certificate uses best attempt result_id #2 with net_wpm_snapshot 50.00', 
    $certBravo !== null && (int)$certBravo['result_id'] === $resB2 && (float)$certBravo['net_wpm_snapshot'] === 50.00
);

// Check Duplicate Generation Prevention
$bulkDup = CertificateService::bulkGenerate($compId, 'all_completed', [], 1, 'classic');
it('Duplicate certificate generation prevented (Generated: 0, Skipped: 4)', 
    (int)$bulkDup['generated'] === 0 && (int)$bulkDup['skipped'] === 4
);

// Verify Disqualified candidate was not issued a certificate
$certDelta = Database::fetch("SELECT * FROM certificates WHERE candidate_id = ? AND competition_id = ?", [$candD, $compId]);
it('Disqualified candidate Delta has NO certificate issued', $certDelta === null);

} finally {
    // ---------------------------------------------------------
    // 6. GUARANTEED CLEANUP OF TEST ARTIFACTS
    // ---------------------------------------------------------
    echo "\n--- 6. CLEANUP TEST DATA ---\n";
    if ($compId > 0) {
        Database::delete('certificates', 'competition_id = ?', [$compId]);
        Database::delete('test_results', 'competition_id = ?', [$compId]);
        Database::delete('test_progress', "attempt_id IN (SELECT id FROM test_attempts WHERE competition_id = ?)", [$compId]);
        Database::delete('security_events', 'competition_id = ?', [$compId]);
        Database::delete('test_attempts', 'competition_id = ?', [$compId]);
        Database::delete('attendance', 'competition_id = ?', [$compId]);
        Database::delete('candidates', 'competition_id = ?', [$compId]);
        Database::delete('test_settings', 'competition_id = ?', [$compId]);
        Database::delete('competitions', 'id = ?', [$compId]);
    }
    if ($paraId > 0) {
        Database::delete('typing_paragraphs', 'id = ?', [$paraId]);
    }
    it('Guaranteed cleanup of test records executed', true);
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "INTEGRATION TEST SUMMARY: {$passCount} PASSED / {$failCount} FAILED\n";
echo str_repeat('=', 60) . "\n";

if ($failCount > 0) {
    exit(1);
}
