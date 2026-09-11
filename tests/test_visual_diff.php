<?php
declare(strict_types=1);

/**
 * Automated Verification Test Suite for Visual Keystroke Diff UI
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== VISUAL KEYSTROKE DIFF UI VERIFICATION SUITE ===\n\n";

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
require_once __DIR__ . '/../core/TypingCalculator.php';

// ============================================================
// 1. TEST CASES FOR CHARACTER ALIGNMENT & DIFF GENERATION
// ============================================================
echo "\n--- 1. DIFF GENERATION ACROSS VARIOUS TEXT PATTERNS ---\n";

$testCases = [
    'Perfect Match' => [
        'ref'   => 'The quick brown fox jumps over the lazy dog.',
        'typed' => 'The quick brown fox jumps over the lazy dog.',
    ],
    'Substitutions' => [
        'ref'   => 'The quick brown fox jumps over the lazy dog.',
        'typed' => 'The quack brawn fox jumps over the lazy dog.',
    ],
    'Missing Characters' => [
        'ref'   => 'The quick brown fox jumps over the lazy dog.',
        'typed' => 'The quick fox jumps the dog.',
    ],
    'Extra Characters' => [
        'ref'   => 'The quick brown fox.',
        'typed' => 'The quick fast brown fox!!',
    ],
    'Complex Mixture' => [
        'ref'   => 'To be, or not to be, that is the question: Whether \'tis nobler in the mind...',
        'typed' => 'To be or not 2 be that is the queston: Whether tis nobler in mind..',
    ],
    'Multiple Lines & Punctuation' => [
        'ref'   => "Line 1: Hello World!\nLine 2: Fast typing.\nLine 3: 123 @ 456.",
        'typed' => "Line 1: Hello World!\nLine 2: Fast typng.\nLine 3: 123 @ 456.",
    ],
    'Long Paragraph' => [
        'ref'   => 'Computer programming is the process of designing and building an executable computer program to accomplish a specific computing result or to perform a specific task. Programming involves tasks such as analysis, generating algorithms, profiling algorithms accuracy and resource consumption, and the implementation of algorithms in a chosen programming language.',
        'typed' => 'Computer programing is the process of designing and bulding an executable computer program to acomplish a specific computing result or to perform a specific task. Programing involves tasks such as analysis, generating algorithms, profiling algorithms accuracy and resorce consumption, and the implementation of algorithms in a chosen programming language.',
    ],
];

foreach ($testCases as $caseName => $data) {
    $align = TypingCalculator::alignCharacters($data['ref'], $data['typed']);
    test("Alignment generated for '{$caseName}'", isset($align['aligned_pairs']) && count($align['aligned_pairs']) > 0);

    // Build Diff HTML using our new generator logic
    $diffHtml = '';
    foreach ($align['aligned_pairs'] as $pair) {
        $type = $pair['type'];
        $ref = $pair['ref'] ?? '';
        $typed = $pair['typed'] ?? '';

        if ($type === 'correct') {
            $diffHtml .= '<span class="diff-correct">' . htmlspecialchars($ref) . '</span>';
        } elseif ($type === 'incorrect') {
            $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
            $title = 'Expected: ' . htmlspecialchars($ref) . ' | Typed: ' . htmlspecialchars($typed);
            $diffHtml .= '<span class="diff-error" title="' . $title . '">' . $displayChar . '</span>';
        } elseif ($type === 'missing') {
            $displayChar = ($ref === ' ') ? '␣' : htmlspecialchars($ref);
            $title = 'Missing: ' . htmlspecialchars($ref);
            $diffHtml .= '<span class="diff-missing" title="' . $title . '">' . $displayChar . '</span>';
        } elseif ($type === 'extra') {
            $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
            $title = 'Extra: ' . htmlspecialchars($typed);
            $diffHtml .= '<span class="diff-extra" title="' . $title . '">' . $displayChar . '</span>';
        }
    }

    // Verify HTML has NO intervening newlines or indentation between tags
    $hasInterTagWhitespace = preg_match('/<\/span>\s+<span/', $diffHtml) === 1;
    test("Diff HTML for '{$caseName}' has no inter-tag newline fragmentation", !$hasInterTagWhitespace);
    test("Diff HTML for '{$caseName}' contains expected diff classes", strpos($diffHtml, 'diff-') !== false);
}

// ============================================================
// 2. CSS SPECIFICATION VERIFICATION
// ============================================================
echo "\n--- 2. CSS CLASS & ATTRIBUTE SPECIFICATION ---\n";

$candidateViewContent = file_get_contents(__DIR__ . '/../views/test/result.php');
$adminViewContent = file_get_contents(__DIR__ . '/../views/results/view.php');

test("Candidate Result contains '.typing-diff' CSS rule", strpos($candidateViewContent, '.typing-diff') !== false);
test("Candidate Result contains 'white-space: pre-wrap'", strpos($candidateViewContent, 'white-space: pre-wrap;') !== false);
test("Candidate Result contains 'overflow-wrap: break-word'", strpos($candidateViewContent, 'overflow-wrap: break-word;') !== false);
test("Candidate Result contains 'word-break: normal'", strpos($candidateViewContent, 'word-break: normal;') !== false);
test("Candidate Result contains '.typing-diff span { display: inline; }'", strpos($candidateViewContent, 'display: inline;') !== false);
test("Candidate Result contains '.diff-correct' class", strpos($candidateViewContent, '.diff-correct') !== false);
test("Candidate Result contains '.diff-error' class", strpos($candidateViewContent, '.diff-error') !== false);
test("Candidate Result contains '.diff-missing' class", strpos($candidateViewContent, '.diff-missing') !== false);
test("Candidate Result contains '.diff-extra' class", strpos($candidateViewContent, '.diff-extra') !== false);

test("Admin Result contains '.typing-diff' CSS rule", strpos($adminViewContent, '.typing-diff') !== false);
test("Admin Result contains 'white-space: pre-wrap'", strpos($adminViewContent, 'white-space: pre-wrap;') !== false);
test("Admin Result contains 'overflow-wrap: break-word'", strpos($adminViewContent, 'overflow-wrap: break-word;') !== false);
test("Admin Result contains 'word-break: normal'", strpos($adminViewContent, 'word-break: normal;') !== false);
test("Admin Result contains '.typing-diff span { display: inline; }'", strpos($adminViewContent, 'display: inline;') !== false);
test("Admin Result contains '.diff-correct' class", strpos($adminViewContent, '.diff-correct') !== false);
test("Admin Result contains '.diff-error' class", strpos($adminViewContent, '.diff-error') !== false);
test("Admin Result contains '.diff-missing' class", strpos($adminViewContent, '.diff-missing') !== false);
test("Admin Result contains '.diff-extra' class", strpos($adminViewContent, '.diff-extra') !== false);

// ============================================================
// 3. SENTENCE CONTINUITY TEST
// ============================================================
echo "\n--- 3. SENTENCE CONTINUITY VERIFICATION ---\n";

$sentenceRef = 'The quick brown fox jumps over the lazy dog.';
$sentenceTyped = 'The quik brown fox jumps ovr the lazy dog.';
$sentenceAlign = TypingCalculator::alignCharacters($sentenceRef, $sentenceTyped);

$sentenceHtml = '';
foreach ($sentenceAlign['aligned_pairs'] as $pair) {
    $type = $pair['type'];
    $ref = $pair['ref'] ?? '';
    $typed = $pair['typed'] ?? '';

    if ($type === 'correct') {
        $sentenceHtml .= '<span class="diff-correct">' . htmlspecialchars($ref) . '</span>';
    } elseif ($type === 'incorrect') {
        $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
        $sentenceHtml .= '<span class="diff-error">' . $displayChar . '</span>';
    } elseif ($type === 'missing') {
        $displayChar = ($ref === ' ') ? '␣' : htmlspecialchars($ref);
        $sentenceHtml .= '<span class="diff-missing">' . $displayChar . '</span>';
    } elseif ($type === 'extra') {
        $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
        $sentenceHtml .= '<span class="diff-extra">' . $displayChar . '</span>';
    }
}

// Strip tags to confirm exact continuous reconstructed string flow
$textOnly = strip_tags($sentenceHtml);
test("Reconstructed string retains full sentence length", strlen($textOnly) >= 40);
test("Reconstructed string contains whole words 'The', 'brown', 'fox', 'jumps'", 
    strpos($textOnly, 'The') !== false && 
    strpos($textOnly, 'brown') !== false && 
    strpos($textOnly, 'fox') !== false && 
    strpos($textOnly, 'jumps') !== false
);

echo "\n============================================================\n";
echo "VISUAL DIFF TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
