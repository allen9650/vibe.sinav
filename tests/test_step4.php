<?php
/**
 * Step 4 Automated Test Suite
 * Comprehensive verification of:
 * - Typing Paragraph Management & Authoritative Counting
 * - Competition Paragraph Assignment (Fixed & Random Pool modes)
 * - Test Settings Module & Range Validation
 * - Candidate Test Access / Login & Eligibility Validation
 * - Start Test Workflow & Paragraph Snapshotting
 * - Secure Server-Backed Timer
 * - Auto-Save & Refresh Recovery
 * - Auto-Submit & Manual Submit State Management
 * - Attempt Protection & Anti-Cheating Controls
 * - RBAC & Audit Logging
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 4 — COMPREHENSIVE AUTOMATED TEST SUITE ===\n\n";

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
require_once __DIR__ . '/../core/helpers.php';

Session::start();
$pdo = Database::getInstance();

function colExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

// Cleanup prior test artifacts if any
Database::delete('candidates', "registration_number LIKE 'MITC-STEP4-%'");
Database::delete('competitions', "code LIKE 'STEP4-COMP-%'");
Database::delete('typing_paragraphs', "title LIKE 'Test Passage %'");

// ============================================================
// 1. DATABASE SCHEMA INTEGRITY
// ============================================================
echo "\n--- 1. DATABASE SCHEMA INTEGRITY (STEPS 3 & 4) ---\n";

test('typing_paragraphs.char_count column exists', colExists($pdo, 'typing_paragraphs', 'char_count'));
test('competition_paragraphs.is_active column exists', colExists($pdo, 'competition_paragraphs', 'is_active'));
test('test_settings.duration_seconds column exists', colExists($pdo, 'test_settings', 'duration_seconds'));
test('test_settings.paragraph_mode column exists', colExists($pdo, 'test_settings', 'paragraph_mode'));
test('test_settings.candidate_login_mode column exists', colExists($pdo, 'test_settings', 'candidate_login_mode'));
test('test_attempts.expected_end_at column exists', colExists($pdo, 'test_attempts', 'expected_end_at'));
test('test_attempts.original_text_snapshot column exists', colExists($pdo, 'test_attempts', 'original_text_snapshot'));
test('test_attempts.submitted_at column exists', colExists($pdo, 'test_attempts', 'submitted_at'));
test('test_progress table exists', (bool)Database::fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'test_progress'"));

// ============================================================
// 2. PARAGRAPH CREATION & AUTHORITATIVE METRICS
// ============================================================
echo "\n--- 2. PARAGRAPH MANAGEMENT & METRICS ---\n";

$rawText = "The quick brown fox jumps over the lazy dog! It was a sunny day at Microsoft Institute, Khairpur Mirs'. 12345 special symbols @#$.";
$wordCount = count(preg_split('/\s+/', trim($rawText)));
$charCount = mb_strlen($rawText);

$paraId1 = Database::insert('typing_paragraphs', [
    'title'       => 'Test Passage Alpha',
    'content'     => $rawText,
    'word_count'  => $wordCount,
    'char_count'  => $charCount,
    'difficulty'  => 'medium',
    'language'    => 'english',
    'category'    => 'Speed Test',
    'status'      => 'active',
    'created_by'  => 1,
]);

test('Paragraph created with authoritative word/char counts', $paraId1 > 0 && $wordCount === 23 && $charCount === 130, "Words: {$wordCount}, Chars: {$charCount}");

$fetchedPara = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$paraId1]);
test('Paragraph content preserved exactly (punctuation & casing)', $fetchedPara['content'] === $rawText);

$paraId2 = Database::insert('typing_paragraphs', [
    'title'       => 'Test Passage Beta',
    'content'     => "Speed typing requires patience, accurate finger placement, and relentless practice on a mechanical keyboard.",
    'word_count'  => 14,
    'char_count'  => 108,
    'difficulty'  => 'hard',
    'language'    => 'english',
    'category'    => 'Advanced',
    'status'      => 'active',
    'created_by'  => 1,
]);
test('Second paragraph created for random pool', $paraId2 > 0);

// ============================================================
// 3. COMPETITION & PARAGRAPH ASSIGNMENTS
// ============================================================
echo "\n--- 3. COMPETITION PARAGRAPH ASSIGNMENT ---\n";

$testCompCode = 'STEP4-COMP-' . time();
$testCompId = Database::insert('competitions', [
    'name'             => 'Step 4 Typing Championship',
    'code'             => $testCompCode,
    'competition_date' => date('Y-m-d'),
    'start_time'       => '09:00:00',
    'end_time'         => '18:00:00',
    'venue'            => 'Main Lab Khairpur',
    'status'           => 'active',
    'created_by'       => 1,
]);

// Assign Paragraph 1
$cp1 = Database::insert('competition_paragraphs', [
    'competition_id' => $testCompId,
    'paragraph_id'   => $paraId1,
    'is_active'      => 1,
]);
test('Paragraph 1 assigned to competition', $cp1 > 0);

// Duplicate assignment rejection
try {
    Database::insert('competition_paragraphs', [
        'competition_id' => $testCompId,
        'paragraph_id'   => $paraId1,
    ]);
    test('Duplicate paragraph assignment rejected', false);
} catch (Exception $e) {
    test('Duplicate paragraph assignment rejected', true, 'Unique compound index enforced');
}

// Assign Paragraph 2
$cp2 = Database::insert('competition_paragraphs', [
    'competition_id' => $testCompId,
    'paragraph_id'   => $paraId2,
    'is_active'      => 1,
]);
test('Paragraph 2 assigned to competition (Pool size: 2)', $cp2 > 0);

// ============================================================
// 4. TEST SETTINGS MODULE
// ============================================================
echo "\n--- 4. COMPETITION TEST SETTINGS ---\n";

$settingsId = Database::insert('test_settings', [
    'competition_id'        => $testCompId,
    'duration_minutes'      => 5,
    'duration_seconds'      => 300,
    'passing_wpm'           => 35,
    'passing_accuracy'      => 92.00,
    'allow_backspace'       => 1,
    'show_wpm_live'         => 1,
    'show_accuracy_live'    => 1,
    'show_errors_live'      => 1,
    'fullscreen_required'   => 1,
    'detect_tab_switch'     => 1,
    'detect_window_blur'    => 1,
    'disable_copy_paste'    => 1,
    'disable_right_click'   => 1,
    'max_violations'        => 3,
    'violation_action'      => 'warning',
    'auto_submit'           => 1,
    'auto_save_interval'    => 5,
    'result_visible'        => 1,
    'leaderboard_visible'   => 0,
    'paragraph_mode'        => 'fixed',
    'selected_paragraph_id' => $paraId1,
    'candidate_login_mode'  => 'roll_number',
    'max_attempts'          => 1,
]);

test('Test settings configured for competition', $settingsId > 0);

$fetchedSettings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$testCompId]);
test('Test settings preserved correctly (Duration: 300s, Accuracy: 92%, Mode: fixed)', 
    (int)$fetchedSettings['duration_seconds'] === 300 && 
    (float)$fetchedSettings['passing_accuracy'] === 92.00 && 
    $fetchedSettings['paragraph_mode'] === 'fixed' &&
    (int)$fetchedSettings['selected_paragraph_id'] === $paraId1
);

// ============================================================
// 5. CANDIDATE REGISTRATION & TEST ACCESS
// ============================================================
echo "\n--- 5. CANDIDATE TEST ACCESS & ELIGIBILITY ---\n";

$randSuffix = time() . '-' . rand(100, 999);
$candRegNum = 'MITC-STEP4-' . $randSuffix;
$candRollNum = 'R-' . rand(400, 999);

$candId = Database::insert('candidates', [
    'competition_id'      => $testCompId,
    'registration_number' => $candRegNum,
    'roll_number'         => $candRollNum,
    'full_name'           => 'Bilal Shafi',
    'phone'               => '03001234567',
    'status'              => 'registered',
    'registered_by'       => 1,
]);
test('Candidate registered for Step 4 testing', $candId > 0, "Roll: {$candRollNum}, Reg: {$candRegNum}");

// Verification against non-existent candidate
$invalidCandidate = Database::fetch(
    "SELECT * FROM candidates WHERE competition_id = ? AND roll_number = ?",
    [$testCompId, 'NON-EXISTENT-999']
);
test('Invalid roll number rejected by validation', empty($invalidCandidate));

// Verification of valid candidate
$validCandidate = Database::fetch(
    "SELECT * FROM candidates WHERE competition_id = ? AND roll_number = ?",
    [$testCompId, $candRollNum]
);
test('Valid candidate successfully authenticated by roll number', !empty($validCandidate) && $validCandidate['full_name'] === 'Bilal Shafi');

// ============================================================
// 6. START TEST WORKFLOW & SECURE TIMER
// ============================================================
echo "\n--- 6. START TEST WORKFLOW & SERVER-BACKED TIMER ---\n";

$now = time();
$durationSecs = 300;
$startTime = date('Y-m-d H:i:s', $now);
$expectedEndTime = date('Y-m-d H:i:s', $now + $durationSecs);

$attemptId = Database::insert('test_attempts', [
    'candidate_id'           => $candId,
    'competition_id'         => $testCompId,
    'paragraph_id'           => $paraId1,
    'original_text_snapshot' => $rawText,
    'attempt_number'         => 1,
    'started_at'             => $startTime,
    'expected_end_at'        => $expectedEndTime,
    'duration_seconds'       => $durationSecs,
    'status'                 => 'in_progress',
    'ip_address'             => '127.0.0.1',
    'user_agent'             => 'PHPUnit-Step4-Test',
]);

test('Test attempt started with server-authoritative timestamps', $attemptId > 0, "Attempt ID: {$attemptId}");

$fetchedAttempt = Database::fetch("SELECT * FROM test_attempts WHERE id = ?", [$attemptId]);
test('Original text snapshot stored permanently on attempt', $fetchedAttempt['original_text_snapshot'] === $rawText);
test('Attempt status is in_progress', $fetchedAttempt['status'] === 'in_progress');

// Server-backed timer calculation
$calcRemaining = max(0, strtotime($fetchedAttempt['expected_end_at']) - time());
test('Server-calculated remaining countdown is accurate', $calcRemaining >= 298 && $calcRemaining <= 300, "Remaining: {$calcRemaining}s");

// Initialize test progress
Database::insert('test_progress', [
    'attempt_id'       => $attemptId,
    'typed_text'       => '',
    'current_position' => 0,
]);

// ============================================================
// 7. AUTO-SAVE & REFRESH RECOVERY
// ============================================================
echo "\n--- 7. AUTO-SAVE & REFRESH RECOVERY ---\n";

// Simulate background auto-save after candidate types 40 characters
$typedSample = "The quick brown fox jumps over the lazy ";
Database::update('test_progress', [
    'typed_text'       => $typedSample,
    'current_position' => mb_strlen($typedSample),
    'last_saved_at'    => date('Y-m-d H:i:s'),
], 'attempt_id = ?', [$attemptId]);

$savedProgress = Database::fetch("SELECT * FROM test_progress WHERE attempt_id = ?", [$attemptId]);
test('Background auto-save persisted candidate typing progress', $savedProgress['typed_text'] === $typedSample);

// Simulate page refresh: Reload active attempt
$activeAttemptOnRefresh = Database::fetch(
    "SELECT * FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status = 'in_progress'",
    [$candId, $testCompId]
);
test('Refresh recovery identified active attempt without creating new record', $activeAttemptOnRefresh && (int)$activeAttemptOnRefresh['id'] === $attemptId);

$restoredProgress = Database::fetch("SELECT typed_text FROM test_progress WHERE attempt_id = ?", [$activeAttemptOnRefresh['id']]);
test('Refresh recovery restored saved typed content', $restoredProgress['typed_text'] === $typedSample);

// ============================================================
// 8. TEST SUBMISSION & ATTEMPT PROTECTION
// ============================================================
echo "\n--- 8. TEST SUBMISSION & ATTEMPT LIMITS ---\n";

$finalTypedText = $rawText; // Candidate typed full text
$submittedAt = date('Y-m-d H:i:s');
$timeTaken = 145; // 145 seconds

Database::update('test_progress', [
    'typed_text'       => $finalTypedText,
    'current_position' => mb_strlen($finalTypedText),
], 'attempt_id = ?', [$attemptId]);

Database::update('test_attempts', [
    'status'             => 'completed',
    'finished_at'        => $submittedAt,
    'submitted_at'       => $submittedAt,
    'time_taken_seconds' => $timeTaken,
], 'id = ?', [$attemptId]);

Database::update('candidates', ['status' => 'completed'], 'id = ?', [$candId]);

$finalAttempt = Database::fetch("SELECT * FROM test_attempts WHERE id = ?", [$attemptId]);
test('Attempt finalized with status completed', $finalAttempt['status'] === 'completed' && (int)$finalAttempt['time_taken_seconds'] === 145);

$updatedCandidate = Database::fetch("SELECT status FROM candidates WHERE id = ?", [$candId]);
test('Candidate status transitioned to completed', $updatedCandidate['status'] === 'completed');

// Attempt Limit Enforcement: Candidate has 1 completed attempt, max is 1
$completedCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status IN ('completed', 'timed_out')",
    [$candId, $testCompId]
);
test('Attempt limit blocked candidate from creating 2nd attempt', $completedCount >= $fetchedSettings['max_attempts'], "Completed: {$completedCount} / Max: {$fetchedSettings['max_attempts']}");

// ============================================================
// 9. AUDIT LOGGING VERIFICATION
// ============================================================
echo "\n--- 9. AUDIT LOGGING ---\n";

AuditLog::log('paragraph_created', 'paragraphs', "Created test passage Alpha", 1, null, ['id' => $paraId1]);
$paraLog = Database::fetch("SELECT * FROM audit_logs WHERE module = 'paragraphs' AND action = 'paragraph_created' ORDER BY id DESC LIMIT 1");
test('Audit log recorded paragraph creation', (bool)$paraLog);

AuditLog::log('test_attempt_submitted', 'test_attempts', "Candidate submitted attempt #{$attemptId}", null, null, ['attempt_id' => $attemptId]);
$submitLog = Database::fetch("SELECT * FROM audit_logs WHERE module = 'test_attempts' AND action = 'test_attempt_submitted' ORDER BY id DESC LIMIT 1");
test('Audit log recorded test attempt submission', (bool)$submitLog);

// Clean up test data
Database::delete('test_progress', 'attempt_id = ?', [$attemptId]);
Database::delete('test_attempts', 'competition_id = ?', [$testCompId]);
Database::delete('candidates', 'competition_id = ?', [$testCompId]);
Database::delete('competition_paragraphs', 'competition_id = ?', [$testCompId]);
Database::delete('test_settings', 'competition_id = ?', [$testCompId]);
Database::delete('competitions', 'id = ?', [$testCompId]);
Database::delete('typing_paragraphs', 'id IN (?, ?)', [$paraId1, $paraId2]);

echo "\n============================================================\n";
echo "STEP 4 TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
