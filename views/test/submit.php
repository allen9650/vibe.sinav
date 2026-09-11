<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Exam Terminal Submission Handler
 * Receives final exam payload, evaluates authoritatively, and redirects to result scorecard.
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirectTo('test-start');
}

CSRF::validateOrFail();

$attemptId = (int)($_POST['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    Session::flash('error', 'Invalid submission attempt ID.');
    redirectTo('test-start');
}

// Decode fallback answers payload if passed
$allAnswers = [];
if (!empty($_POST['all_answers_payload'])) {
    $decoded = json_decode((string)$_POST['all_answers_payload'], true);
    if (is_array($decoded)) {
        $allAnswers = $decoded;
    }
}

try {
    $result = ExamExecutionService::submitExam($attemptId, $allAnswers);
    redirectTo('test-result&attempt_id=' . $attemptId);
} catch (Throwable $e) {
    Session::flash('error', 'Submission error: ' . $e->getMessage());
    redirectTo('test-start');
}
