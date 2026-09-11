<?php
/**
 * Candidate Test Portal — Background Auto-Save AJAX Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

if (!Session::get('candidate_logged_in') || !Session::get('candidate_id')) {
    echo json_encode(['status' => 'unauthorized', 'message' => 'Session expired.']);
    exit;
}

if (!CSRF::validate()) {
    echo json_encode(['status' => 'invalid_csrf', 'message' => 'Invalid token.']);
    exit;
}

$candidateId = (int)Session::get('candidate_id');
$attemptId = (int)($_POST['attempt_id'] ?? 0);
$typedText = (string)($_POST['typed_text'] ?? '');

$attempt = Database::fetch(
    "SELECT * FROM test_attempts WHERE id = ? AND candidate_id = ?",
    [$attemptId, $candidateId]
);

if (!$attempt) {
    echo json_encode(['status' => 'error', 'message' => 'Attempt not found.']);
    exit;
}

// Check expiration
$now = time();
$expectedEnd = strtotime($attempt['expected_end_at']);

if ($attempt['status'] !== 'in_progress' || $now > $expectedEnd) {
    echo json_encode(['status' => 'expired', 'message' => 'Test has ended.']);
    exit;
}

// Update test progress
$charCount = mb_strlen($typedText);

$progress = Database::fetch("SELECT id FROM test_progress WHERE attempt_id = ?", [$attemptId]);
if ($progress) {
    Database::update('test_progress', [
        'typed_text'       => $typedText,
        'current_position' => $charCount,
        'last_saved_at'    => date('Y-m-d H:i:s'),
    ], 'attempt_id = ?', [$attemptId]);
} else {
    Database::insert('test_progress', [
        'attempt_id'       => $attemptId,
        'typed_text'       => $typedText,
        'current_position' => $charCount,
    ]);
}

echo json_encode(['status' => 'ok', 'chars' => $charCount]);
exit;
