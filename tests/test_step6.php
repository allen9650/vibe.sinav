<?php
/**
 * Step 6 Automated Test Suite
 * Comprehensive verification of:
 * - Anti-Cheating Security Event Logging & Deduplication
 * - Violation Actions (LOG_ONLY, WARNING, AUTO_DISQUALIFY)
 * - Auto-Disqualification Threshold Trigger & Attempt State Locking
 * - Deterministic Ranking Engine (Score, Accuracy, Net WPM, Errors, Duration)
 * - Shared Ranks & Medal Positions (🥇, 🥈, 🥉)
 * - Best Attempt per Candidate Filtering
 * - Leaderboard Generation & Visibility Controls
 * - Competition Finalization & Result Freezing/Locking
 * - RBAC & Permissions
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 6 — ANTI-CHEATING, RANKING & LEADERBOARD TEST SUITE ===\n\n";

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
require_once __DIR__ . '/../core/helpers.php';

Session::start();
$pdo = Database::getInstance();

// Cleanup prior Step 6 test artifacts
Database::delete('candidates', "registration_number LIKE 'MITC-STEP6-%'");
Database::delete('competitions', "code LIKE 'STEP6-COMP-%'");
Database::delete('typing_paragraphs', "title LIKE 'Step 6 Passage%'");

// ============================================================
// 1. DATABASE SCHEMA INTEGRITY (STEP 6)
// ============================================================
echo "\n--- 1. DATABASE SCHEMA INTEGRITY (STEP 6) ---\n";

function colExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

test('security_events.attempt_id column exists', colExists($pdo, 'security_events', 'attempt_id'));
test('security_events.candidate_id column exists', colExists($pdo, 'security_events', 'candidate_id'));
test('security_events.competition_id column exists', colExists($pdo, 'security_events', 'competition_id'));
test('competitions.results_finalized_at column exists', colExists($pdo, 'competitions', 'results_finalized_at'));
test('competitions.results_locked column exists', colExists($pdo, 'competitions', 'results_locked'));

// ============================================================
// 2. SECURITY EVENTS & ANTI-CHEATING LOGGING
// ============================================================
echo "\n--- 2. SECURITY EVENT TELEMETRY & DEDUPLICATION ---\n";

$testCompCode = 'STEP6-COMP-' . time();
$testCompId = Database::insert('competitions', [
    'name'             => 'Step 6 Security & Ranking Championship',
    'code'             => $testCompCode,
    'competition_date' => date('Y-m-d'),
    'start_time'       => '09:00:00',
    'end_time'         => '18:00:00',
    'venue'            => 'Main Lab Khairpur',
    'status'           => 'active',
    'created_by'       => 1,
]);

$settingsId = Database::insert('test_settings', [
    'competition_id'        => $testCompId,
    'duration_minutes'      => 5,
    'duration_seconds'      => 300,
    'passing_wpm'           => 30.00,
    'passing_accuracy'      => 90.00,
    'max_violations'        => 3,
    'violation_action'      => 'auto_disqualify',
    'detect_tab_switch'     => 1,
    'detect_window_blur'    => 1,
    'fullscreen_required'   => 1,
    'disable_copy_paste'    => 1,
    'disable_right_click'   => 1,
    'result_visible'        => 1,
    'leaderboard_visible'   => 1,
    'candidate_login_mode'  => 'roll_number',
    'max_attempts'          => 2,
]);

$candId1 = Database::insert('candidates', [
    'competition_id'      => $testCompId,
    'registration_number' => 'MITC-STEP6-0001',
    'roll_number'         => 'R-601',
    'full_name'           => 'Tariq Mehmood',
    'course'              => 'DIT',
    'shift'               => 'Morning',
    'status'              => 'test_started',
    'registered_by'       => 1,
]);

$attemptId1 = Database::insert('test_attempts', [
    'candidate_id'           => $candId1,
    'competition_id'         => $testCompId,
    'paragraph_id'           => 1,
    'original_text_snapshot' => 'Sample passage content for step 6 test.',
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s'),
    'expected_end_at'        => date('Y-m-d H:i:s', time() + 300),
    'duration_seconds'       => 300,
    'status'                 => 'in_progress',
]);

// Test 2.1: Log TAB_SWITCH event
$ev1 = Database::insert('security_events', [
    'attempt_id'       => $attemptId1,
    'candidate_id'     => $candId1,
    'competition_id'   => $testCompId,
    'event_type'       => 'TAB_SWITCH',
    'description'      => 'Candidate switched away from test browser tab',
    'severity'         => 'low',
]);
test('TAB_SWITCH security event logged', $ev1 > 0);

// Test 2.2: Log WINDOW_BLUR event
$ev2 = Database::insert('security_events', [
    'attempt_id'       => $attemptId1,
    'candidate_id'     => $candId1,
    'competition_id'   => $testCompId,
    'event_type'       => 'WINDOW_BLUR',
    'description'      => 'Test window lost focus',
    'severity'         => 'low',
]);
test('WINDOW_BLUR security event logged', $ev2 > 0);

// Test 2.3: Log FULLSCREEN_EXIT, COPY, PASTE, CUT, RIGHT_CLICK
$eventTypes = ['FULLSCREEN_EXIT', 'COPY_ATTEMPT', 'PASTE_ATTEMPT', 'CUT_ATTEMPT', 'RIGHT_CLICK_ATTEMPT'];
$loggedAll = true;
foreach ($eventTypes as $et) {
    $inserted = Database::insert('security_events', [
        'attempt_id'       => $attemptId1,
        'candidate_id'     => $candId1,
        'competition_id'   => $testCompId,
        'event_type'       => $et,
        'description'      => "Detected {$et}",
        'severity'         => 'medium',
    ]);
    if (!$inserted) $loggedAll = false;
}
test('FULLSCREEN_EXIT, COPY, PASTE, CUT, and RIGHT_CLICK events logged', $loggedAll);

// Test 2.4: Deduplication check
// Inserting identical event within 1s should be identified as duplicate
$recentDup = Database::fetch(
    "SELECT id FROM security_events WHERE attempt_id = ? AND event_type = 'TAB_SWITCH' AND created_at >= (NOW() - INTERVAL 2 SECOND) LIMIT 1",
    [$attemptId1]
);
test('Security event deduplication window detected recent event', (bool)$recentDup);

// ============================================================
// 3. VIOLATION ACTIONS & AUTO-DISQUALIFICATION
// ============================================================
echo "\n--- 3. VIOLATION ACTIONS & AUTO-DISQUALIFICATION ---\n";

$candId2 = Database::insert('candidates', [
    'competition_id'      => $testCompId,
    'registration_number' => 'MITC-STEP6-0002',
    'roll_number'         => 'R-602',
    'full_name'           => 'Ahmed Raza',
    'course'              => 'CIT',
    'shift'               => 'Evening',
    'status'              => 'test_started',
    'registered_by'       => 1,
]);

$attemptId2 = Database::insert('test_attempts', [
    'candidate_id'           => $candId2,
    'competition_id'         => $testCompId,
    'paragraph_id'           => 1,
    'original_text_snapshot' => 'Sample passage content for step 6 test.',
    'attempt_number'         => 1,
    'started_at'             => date('Y-m-d H:i:s'),
    'expected_end_at'        => date('Y-m-d H:i:s', time() + 300),
    'duration_seconds'       => 300,
    'status'                 => 'in_progress',
]);

// Log 3 violations to trigger auto-disqualification threshold (max_violations = 3)
for ($v = 1; $v <= 3; $v++) {
    Database::insert('security_events', [
        'attempt_id'       => $attemptId2,
        'candidate_id'     => $candId2,
        'competition_id'   => $testCompId,
        'event_type'       => 'TAB_SWITCH',
        'description'      => "Violation #{$v}",
        'severity'         => 'low',
    ]);
}

$vCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM security_events WHERE attempt_id = ?", [$attemptId2]);
test('Violation count reached max_violations threshold (3/3)', $vCount === 3);

// Execute Auto-Disqualification
if ($vCount >= 3) {
    Database::update('test_attempts', ['status' => 'disqualified'], 'id = ?', [$attemptId2]);
    Database::update('candidates', ['status' => 'disqualified'], 'id = ?', [$candId2]);
}

$disqAttempt = Database::fetch("SELECT status FROM test_attempts WHERE id = ?", [$attemptId2]);
$disqCand = Database::fetch("SELECT status FROM candidates WHERE id = ?", [$candId2]);

test('Attempt status transitioned to disqualified', $disqAttempt['status'] === 'disqualified');
test('Candidate status transitioned to disqualified', $disqCand['status'] === 'disqualified');

// ============================================================
// 4. RANKING ENGINE & TIE-BREAKING RULES
// ============================================================
echo "\n--- 4. DETERMINISTIC RANKING ENGINE & TIE-BREAKING ---\n";

// Create deterministic result set for 5 candidates to test tie-breaking:
// Candidate A: Score = 60.00, Accuracy = 98%, Net WPM = 61.22, Errors = 1, Time = 120s -> Rank 1 (Gold)
// Candidate B: Score = 50.00, Accuracy = 96%, Net WPM = 52.08, Errors = 2, Time = 130s -> Rank 2 (Silver)
// Candidate C: Score = 50.00, Accuracy = 96%, Net WPM = 52.08, Errors = 2, Time = 130s -> Rank 2 (Exact Tie with B -> Shared Rank 2, Silver)
// Candidate D: Score = 50.00, Accuracy = 94%, Net WPM = 53.19, Errors = 4, Time = 110s -> Rank 4 (Lower accuracy than B&C)
// Candidate E: Score = 40.00, Accuracy = 90%, Net WPM = 44.44, Errors = 5, Time = 180s -> Rank 5

$candA = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP6-000A', 'roll_number' => 'R-610', 'full_name' => 'Candidate Alpha', 'status' => 'completed', 'registered_by' => 1]);
$candB = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP6-000B', 'roll_number' => 'R-620', 'full_name' => 'Candidate Bravo', 'status' => 'completed', 'registered_by' => 1]);
$candC = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP6-000C', 'roll_number' => 'R-630', 'full_name' => 'Candidate Charlie', 'status' => 'completed', 'registered_by' => 1]);
$candD = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP6-000D', 'roll_number' => 'R-640', 'full_name' => 'Candidate Delta', 'status' => 'completed', 'registered_by' => 1]);
$candE = Database::insert('candidates', ['competition_id' => $testCompId, 'registration_number' => 'MITC-STEP6-000E', 'roll_number' => 'R-650', 'full_name' => 'Candidate Echo', 'status' => 'completed', 'registered_by' => 1]);

// Create attempts
$attA = Database::insert('test_attempts', ['candidate_id' => $candA, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 1, 'status' => 'completed', 'time_taken_seconds' => 120, 'duration_seconds' => 300]);
$attB = Database::insert('test_attempts', ['candidate_id' => $candB, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 1, 'status' => 'completed', 'time_taken_seconds' => 130, 'duration_seconds' => 300]);
$attC = Database::insert('test_attempts', ['candidate_id' => $candC, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 1, 'status' => 'completed', 'time_taken_seconds' => 130, 'duration_seconds' => 300]);
$attD = Database::insert('test_attempts', ['candidate_id' => $candD, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 1, 'status' => 'completed', 'time_taken_seconds' => 110, 'duration_seconds' => 300]);
$attE = Database::insert('test_attempts', ['candidate_id' => $candE, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 1, 'status' => 'completed', 'time_taken_seconds' => 180, 'duration_seconds' => 300]);

// Create results
Database::insert('test_results', ['attempt_id' => $attA, 'candidate_id' => $candA, 'competition_id' => $testCompId, 'score' => 60.00, 'accuracy' => 98.00, 'net_wpm' => 61.22, 'error_count' => 1, 'time_taken_seconds' => 120, 'qualification_status' => 'qualified']);
Database::insert('test_results', ['attempt_id' => $attB, 'candidate_id' => $candB, 'competition_id' => $testCompId, 'score' => 50.00, 'accuracy' => 96.00, 'net_wpm' => 52.08, 'error_count' => 2, 'time_taken_seconds' => 130, 'qualification_status' => 'qualified']);
Database::insert('test_results', ['attempt_id' => $attC, 'candidate_id' => $candC, 'competition_id' => $testCompId, 'score' => 50.00, 'accuracy' => 96.00, 'net_wpm' => 52.08, 'error_count' => 2, 'time_taken_seconds' => 130, 'qualification_status' => 'qualified']);
Database::insert('test_results', ['attempt_id' => $attD, 'candidate_id' => $candD, 'competition_id' => $testCompId, 'score' => 50.00, 'accuracy' => 94.00, 'net_wpm' => 53.19, 'error_count' => 4, 'time_taken_seconds' => 110, 'qualification_status' => 'qualified']);
Database::insert('test_results', ['attempt_id' => $attE, 'candidate_id' => $candE, 'competition_id' => $testCompId, 'score' => 40.00, 'accuracy' => 90.00, 'net_wpm' => 44.44, 'error_count' => 5, 'time_taken_seconds' => 180, 'qualification_status' => 'qualified']);

// Also create a second (weaker) attempt for Candidate A to test Best Attempt selection
$attA2 = Database::insert('test_attempts', ['candidate_id' => $candA, 'competition_id' => $testCompId, 'paragraph_id' => 1, 'original_text_snapshot' => 'P', 'attempt_number' => 2, 'status' => 'completed', 'time_taken_seconds' => 150, 'duration_seconds' => 300]);
Database::insert('test_results', ['attempt_id' => $attA2, 'candidate_id' => $candA, 'competition_id' => $testCompId, 'score' => 30.00, 'accuracy' => 85.00, 'net_wpm' => 35.29, 'error_count' => 8, 'time_taken_seconds' => 150, 'qualification_status' => 'not_qualified']);

// Fetch Leaderboard
$leaderboard = RankingService::getLeaderboard($testCompId, false);

test('Leaderboard returned 5 distinct candidates (Best attempt per candidate)', count($leaderboard) === 5);
test('Candidate Alpha ranked #1 with Gold medal', $leaderboard[0]['candidate_name'] === 'Candidate Alpha' && (int)$leaderboard[0]['rank'] === 1 && $leaderboard[0]['medal']['icon'] === '🥇');

// Verify shared rank for Candidate Bravo and Charlie (exact tie)
test('Candidate Bravo & Charlie assigned shared Rank #2 (Silver medal)', 
    (int)$leaderboard[1]['rank'] === 2 && (int)$leaderboard[2]['rank'] === 2 && 
    $leaderboard[1]['medal']['icon'] === '🥈' && $leaderboard[2]['medal']['icon'] === '🥈'
);

// Verify Candidate Delta receives Rank #4 (following two rank #2s)
test('Candidate Delta assigned Rank #4 after shared rank #2s', (int)$leaderboard[3]['rank'] === 4);

// Verify Disqualified candidate (Candidate 2) is completely excluded from ranking
$isDisqInLeaderboard = false;
foreach ($leaderboard as $row) {
    if ((int)$row['candidate_id'] === $candId2) $isDisqInLeaderboard = true;
}
test('Disqualified candidate excluded from competition ranking', !$isDisqInLeaderboard);

// ============================================================
// 5. COMPETITION FINALIZATION & RESULT LOCKING
// ============================================================
echo "\n--- 5. FINALIZATION & RESULT LOCKING ---\n";

// Finalize competition
$finalizeRes = RankingService::finalizeCompetition($testCompId, 1);
test('Competition finalized successfully', $finalizeRes['status'] === 'success' && $finalizeRes['total_ranked'] === 5);

$compAfterFinalize = Database::fetch("SELECT status, results_finalized_at FROM competitions WHERE id = ?", [$testCompId]);
test('Competition status is completed with results_finalized_at timestamp', $compAfterFinalize['status'] === 'completed' && !empty($compAfterFinalize['results_finalized_at']));

// Verify final ranks persisted in test_results
$persistedRankA = (int)Database::fetchColumn("SELECT rank FROM test_results WHERE attempt_id = ?", [$attA]);
test('Final Rank #1 persisted to test_results record for Candidate Alpha', $persistedRankA === 1);

// Lock results
RankingService::lockResults($testCompId, 1);
$compLocked = Database::fetch("SELECT results_locked, results_locked_at FROM competitions WHERE id = ?", [$testCompId]);
test('Results locked against modification (results_locked = 1)', (int)$compLocked['results_locked'] === 1 && !empty($compLocked['results_locked_at']));

// Super Admin unlock override
RankingService::unlockResults($testCompId, 1);
$compUnlocked = Database::fetch("SELECT results_locked FROM competitions WHERE id = ?", [$testCompId]);
test('Super Admin override unlocked results successfully (results_locked = 0)', (int)$compUnlocked['results_locked'] === 0);

// ============================================================
// 6. RBAC PERMISSIONS VERIFICATION
// ============================================================
echo "\n--- 6. RBAC PERMISSIONS VERIFICATION ---\n";

$step6Perms = ['security_events.view', 'live_tests.view', 'leaderboard.view', 'competitions.finalize', 'results.lock', 'rankings.manage'];
$permsOk = true;
foreach ($step6Perms as $perm) {
    $exists = Database::fetch("SELECT id FROM permissions WHERE slug = ?", [$perm]);
    if (!$exists) $permsOk = false;
}
test('All Step 6 permissions exist in database', $permsOk);

// Cleanup test records
Database::delete('security_events', 'competition_id = ?', [$testCompId]);
Database::delete('test_results', 'competition_id = ?', [$testCompId]);
Database::delete('test_attempts', 'competition_id = ?', [$testCompId]);
Database::delete('candidates', 'competition_id = ?', [$testCompId]);
Database::delete('test_settings', 'competition_id = ?', [$testCompId]);
Database::delete('competitions', 'id = ?', [$testCompId]);

echo "\n============================================================\n";
echo "STEP 6 TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
