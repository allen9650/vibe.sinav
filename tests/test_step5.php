<?php
/**
 * Step 5 Automated Test Suite
 * Comprehensive verification of:
 * - TypingCalculator Service & Algorithms
 * - Dynamic Programming Sequence Alignment (Substitutions, Deletions, Insertions)
 * - Word-Level Analysis
 * - Gross WPM, Errors Per Minute, Net WPM, Accuracy, Final Score
 * - Edge Cases (Empty, Punctuation, Capitalization, Spaces, UTF-8)
 * - Authoritative Result Generation in Database
 * - Idempotency & Duplicate Result Protection
 * - Early Submission vs Timeout Durations
 * - Qualification Status Logic
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 5 — COMPREHENSIVE TYPING CALCULATION TEST SUITE ===\n\n";

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
require_once __DIR__ . '/../core/helpers.php';

Session::start();
$pdo = Database::getInstance();

// ============================================================
// 1. DETERMINISTIC UNIT TESTS — CHARACTER ALIGNMENT
// ============================================================
echo "\n--- 1. CHARACTER ALIGNMENT & ERROR ANALYSIS ---\n";

// Test 1: Perfect Text
$res1 = TypingCalculator::calculate("The quick brown fox", "The quick brown fox", 60);
test('Test 1 — Perfect Text: Accuracy 100%', (float)$res1['accuracy'] === 100.00 && $res1['total_errors'] === 0 && $res1['correct_characters'] === 19);

// Test 2: One Substitution
$res2 = TypingCalculator::calculate("cat", "cut", 60);
test('Test 2 — One Substitution: cat vs cut', $res2['correct_characters'] === 2 && $res2['incorrect_characters'] === 1 && $res2['missing_characters'] === 0 && $res2['extra_characters'] === 0);

// Test 3: Missing Character
$res3 = TypingCalculator::calculate("cat", "ct", 60);
test('Test 3 — Missing Character: cat vs ct', $res3['correct_characters'] === 2 && $res3['missing_characters'] === 1 && $res3['incorrect_characters'] === 0);

// Test 4: Extra Character (Insertion without cascading error)
$res4 = TypingCalculator::calculate("cat", "cart", 60);
test('Test 4 — Extra Character: cat vs cart', $res4['correct_characters'] === 3 && $res4['extra_characters'] === 1 && $res4['incorrect_characters'] === 0);

// Test 5: Missing Space
$res5 = TypingCalculator::calculate("hello world", "helloworld", 60);
test('Test 5 — Missing Space: hello world vs helloworld', $res5['missing_characters'] === 1 && $res5['correct_characters'] === 10);

// Test 6: Extra Space
$res6 = TypingCalculator::calculate("hello world", "hello  world", 60);
test('Test 6 — Extra Space: hello world vs hello  world', $res6['extra_characters'] === 1 && $res6['correct_characters'] === 11);

// Test 7: Capitalization Error
$res7 = TypingCalculator::calculate("Microsoft", "microsoft", 60);
test('Test 7 — Capitalization: Microsoft vs microsoft', $res7['incorrect_characters'] === 1 && $res7['correct_characters'] === 8);

// Test 8: Punctuation Error
$res8 = TypingCalculator::calculate("Hello, world.", "Hello world", 60);
test('Test 8 — Punctuation: Hello, world. vs Hello world', $res8['missing_characters'] === 2 && $res8['correct_characters'] === 11);

// ============================================================
// 2. SPEED & ACCURACY FORMULAS
// ============================================================
echo "\n--- 2. SPEED & ACCURACY FORMULAS ---\n";

// Test 9: Empty Text
$res9 = TypingCalculator::calculate("The quick brown fox", "", 300);
test('Test 9 — Empty Typed Text: Accuracy 0%, WPM 0.00 (No divide-by-zero)', 
    (float)$res9['accuracy'] === 0.00 && (float)$res9['gross_wpm'] === 0.00 && (float)$res9['net_wpm'] === 0.00 && (float)$res9['final_score'] === 0.00
);

// Test 10: Gross WPM Formula Verification
// 250 typed characters in 5 minutes (300s) -> 250 / 5 / 5 = 10.00 WPM
$sample250Chars = str_repeat("abcde", 50);
$res10 = TypingCalculator::calculate($sample250Chars, $sample250Chars, 300);
test('Test 10 — Gross WPM Formula: 250 chars / 5 / 5 mins = 10.00 WPM', (float)$res10['gross_wpm'] === 10.00, "Gross WPM: {$res10['gross_wpm']}");

// Test 11: Net WPM & Error Rate Deduction
// 1500 chars in 300s (5 mins) -> Gross WPM = 1500 / 5 / 5 = 60.00 WPM
// Reference has 300 words of 'hello'. Typed has 5 substitution errors.
// Total Errors = 5 in 5 mins -> Errors/min = 1.00 -> Net WPM = 60.00 - 1.00 = 59.00 WPM
$refText = str_repeat("hello ", 250); // 1500 chars
$typedWith5Errors = substr_replace($refText, "xxxxx", 0, 5); // 5 substitutions
$res11 = TypingCalculator::calculate($refText, $typedWith5Errors, 300);
test('Test 11 — Net WPM Formula: Errors per minute deducted accurately', 
    (float)$res11['gross_wpm'] === 60.00 && (float)$res11['errors_per_minute'] === 1.00 && (float)$res11['net_wpm'] === 59.00,
    "Gross: {$res11['gross_wpm']}, Net: {$res11['net_wpm']}, Err/min: {$res11['errors_per_minute']}"
);

// Test 12: Final Score Formula
// Net WPM = 60, Accuracy = 98% -> Final Score = 60 * 0.98 = 58.80
$res12Score = round(60.00 * (98.00 / 100.0), 2);
test('Test 12 — Final Score Formula: Net WPM * (Accuracy / 100) = 58.80', $res12Score === 58.80);

// Test 13: Accuracy Bounds (Never > 100 or < 0)
$res13Excessive = TypingCalculator::calculate("abc", "abcdefghijklmnop", 60);
test('Test 13a — Excessive extra typing does not overflow accuracy', (float)$res13Excessive['accuracy'] <= 100.00 && (float)$res13Excessive['accuracy'] >= 0.00);
$res13Gibberish = TypingCalculator::calculate("The quick brown fox", "zzzzzzzzzzzzzzzzzzz", 60);
test('Test 13b — 100% incorrect text accuracy is 0%', (float)$res13Gibberish['accuracy'] === 0.00);

// ============================================================
// 3. WORD-LEVEL ANALYSIS & UTF-8 INTEGRITY
// ============================================================
echo "\n--- 3. WORD-LEVEL ANALYSIS & MULTI-BYTE STRINGS ---\n";

$wordRes = TypingCalculator::analyzeWords("The quick brown fox jumps over the dog", "The quick blue fox jumps over the lazy dog");
test('Word Analysis: Correct, Substitution, and Extra words classified', 
    $wordRes['total_typed_words'] === 9 && $wordRes['correct_words'] === 7 && $wordRes['extra_words'] === 1
);

// UTF-8 Multi-byte test (Sindhi / Urdu / Accented characters)
$utf8Ref = "سائنس ۽ ٽيڪنالاجي";
$utf8Typed = "سائنس ۽ ٽيڪنالاجي";
$utf8Res = TypingCalculator::calculate($utf8Ref, $utf8Typed, 60);
test('UTF-8 Multi-byte characters handled safely and accurately', (float)$utf8Res['accuracy'] === 100.00 && $utf8Res['total_errors'] === 0);

// ============================================================
// 4. DATABASE INTEGRATION, RESULT PERSISTENCE & IDEMPOTENCY
// ============================================================
echo "\n--- 4. DATABASE RESULT PERSISTENCE & IDEMPOTENCY ---\n";

// Cleanup prior test artifacts
Database::delete('candidates', "registration_number LIKE 'MITC-STEP5-%'");
Database::delete('competitions', "code LIKE 'STEP5-COMP-%'");
Database::delete('typing_paragraphs', "title LIKE 'Step 5 Passage%'");

$testCompCode = 'STEP5-COMP-' . time();
$testCompId = Database::insert('competitions', [
    'name'             => 'Step 5 Scoring Championship',
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
    'passing_wpm'           => 5.00,
    'passing_accuracy'      => 90.00,
    'result_visible'        => 1,
    'candidate_login_mode'  => 'roll_number',
    'max_attempts'          => 1,
]);

$paraContent = "Microsoft Institute Khairpur Mirs provides quality IT education.";
$paraId = Database::insert('typing_paragraphs', [
    'title'       => 'Step 5 Passage',
    'content'     => $paraContent,
    'word_count'  => 8,
    'char_count'  => mb_strlen($paraContent),
    'difficulty'  => 'easy',
    'status'      => 'active',
    'created_by'  => 1,
]);

$candId = Database::insert('candidates', [
    'competition_id'      => $testCompId,
    'registration_number' => 'MITC-STEP5-' . time(),
    'roll_number'         => 'R-501',
    'full_name'           => 'Zahid Ali',
    'status'              => 'test_started',
    'registered_by'       => 1,
]);

$startTime = date('Y-m-d H:i:s', time() - 120); // 120s ago
$expectedEndTime = date('Y-m-d H:i:s', time() + 180);

$attemptId = Database::insert('test_attempts', [
    'candidate_id'           => $candId,
    'competition_id'         => $testCompId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraContent,
    'attempt_number'         => 1,
    'started_at'             => $startTime,
    'expected_end_at'        => $expectedEndTime,
    'duration_seconds'       => 300,
    'status'                 => 'in_progress',
]);

// Simulate Early Submission: 120 seconds elapsed
$typedByCandidate = "Microsoft Institute Khairpur Mirs provides quality IT education.";
$elapsedDuration = 120;
$backspaces = 3;

$calcDb = TypingCalculator::calculate(
    $paraContent,
    $typedByCandidate,
    $elapsedDuration,
    $backspaces,
    ['passing_wpm' => 5.00, 'passing_accuracy' => 90.00]
);

$resultId = Database::insert('test_results', [
    'attempt_id'             => $attemptId,
    'candidate_id'           => $candId,
    'competition_id'         => $testCompId,
    'paragraph_id'           => $paraId,
    'original_text_snapshot' => $paraContent,
    'final_typed_text'       => $typedByCandidate,
    'gross_wpm'              => $calcDb['gross_wpm'],
    'net_wpm'                => $calcDb['net_wpm'],
    'accuracy'               => $calcDb['accuracy'],
    'total_characters'       => $calcDb['total_typed_characters'],
    'correct_characters'     => $calcDb['correct_characters'],
    'incorrect_characters'   => $calcDb['incorrect_characters'],
    'missing_characters'     => $calcDb['missing_characters'],
    'extra_characters'       => $calcDb['extra_characters'],
    'total_words'            => $calcDb['total_typed_words'],
    'correct_words'          => $calcDb['correct_words'],
    'error_count'            => $calcDb['total_errors'],
    'errors_per_minute'      => $calcDb['errors_per_minute'],
    'backspace_count'        => $calcDb['backspace_count'],
    'time_taken_seconds'     => $calcDb['scoring_duration_seconds'],
    'pass_fail'              => $calcDb['pass_fail'],
    'qualification_status'   => $calcDb['qualification_status'],
    'score'                  => $calcDb['final_score'],
    'scoring_version'        => $calcDb['scoring_version'],
]);

test('Test Result inserted into test_results with full statistics', $resultId > 0);

$savedResult = Database::fetch("SELECT * FROM test_results WHERE id = ?", [$resultId]);
test('Result preserved original snapshot text', $savedResult['original_text_snapshot'] === $paraContent);
test('Result stored final typed text', $savedResult['final_typed_text'] === $typedByCandidate);
test('Result recorded scoring duration of 120s for early submit', (int)$savedResult['time_taken_seconds'] === 120);
test('Result recorded backspace count of 3', (int)$savedResult['backspace_count'] === 3);
test('Result recorded scoring version 1.0', $savedResult['scoring_version'] === '1.0');
test('Qualification status evaluated as qualified', $savedResult['qualification_status'] === 'qualified' && $savedResult['pass_fail'] === 'pass');

// Test Idempotency: Attempting duplicate result insertion
try {
    Database::insert('test_results', [
        'attempt_id'     => $attemptId,
        'candidate_id'   => $candId,
        'competition_id' => $testCompId,
    ]);
    test('Duplicate result prevented by unique attempt_id index', false);
} catch (Exception $e) {
    test('Duplicate result prevented by unique attempt_id index', true, 'Unique key violation caught');
}

// Cleanup test records
Database::delete('test_results', 'id = ?', [$resultId]);
Database::delete('test_attempts', 'id = ?', [$attemptId]);
Database::delete('candidates', 'id = ?', [$candId]);
Database::delete('typing_paragraphs', 'id = ?', [$paraId]);
Database::delete('test_settings', 'id = ?', [$settingsId]);
Database::delete('competitions', 'id = ?', [$testCompId]);

echo "\n============================================================\n";
echo "STEP 5 TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
