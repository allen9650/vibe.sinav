<?php
/**
 * PTM Assessment System — Quiz Final Submission Handler
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirectTo('quiz-login');
}

CSRF::validateOrFail();

$attemptId = (int)($_POST['attempt_id'] ?? 0);
$isTimeout = !empty($_POST['is_timeout']);

if ($attemptId <= 0) {
    Session::flash('error', 'Invalid submission attempt.');
    redirectTo('quiz-login');
}

// Ensure any un-autosaved answers from the client payload are saved before final evaluation
if (!empty($_POST['all_answers_payload'])) {
    $allAnswers = json_decode($_POST['all_answers_payload'], true);
    if (is_array($allAnswers)) {
        foreach ($allAnswers as $qId => $ansData) {
            if (is_array($ansData)) {
                AssessmentResultService::saveAnswerDelta($attemptId, (int)$qId, $ansData);
            }
        }
    }
}

try {
    $result = AssessmentResultService::submitAttempt($attemptId, $isTimeout);
    Session::set('completed_attempt_id', $attemptId);
    redirectTo('quiz-result&attempt_id=' . $attemptId);
} catch (Exception $e) {
    error_log('Quiz submission error for attempt #' . $attemptId . ': ' . $e->getMessage());
    Session::flash('error', 'Submission error: ' . $e->getMessage());
    redirectTo('quiz-login');
}

