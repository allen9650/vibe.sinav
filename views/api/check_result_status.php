<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Gated Result Poller API
 * Checks whether the instructor has released results to candidates.
 */

header('Content-Type: application/json; charset=utf-8');

$attemptId = (int)($_GET['attempt_id'] ?? 0);

if ($attemptId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
    exit;
}

$record = Database::fetch(
    "SELECT a.is_result_published, att.needs_review, att.is_reviewed 
     FROM assessment_attempts att
     JOIN assessments a ON att.assessment_id = a.id
     WHERE att.id = ?",
    [$attemptId]
);

if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Attempt record not found']);
    exit;
}

$isReviewPending = ((int)($record['needs_review'] ?? 0) === 1 && (int)($record['is_reviewed'] ?? 0) === 0);
$isPublished = ((int)$record['is_result_published'] === 1) && !$isReviewPending;

echo json_encode([
    'success'      => true,
    'attempt_id'   => $attemptId,
    'is_published' => $isPublished,
]);
exit;

