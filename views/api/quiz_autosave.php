<?php
/**
 * PTM Assessment System — Delta Autosave API Endpoint
 * Extremely lightweight (< 1 KB payload, sub-5ms write)
 */
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$attemptId = (int)($input['attempt_id'] ?? 0);
$questionId = (int)($input['question_id'] ?? 0);
$answerData = (array)($input['answer_data'] ?? []);

if ($attemptId <= 0 || $questionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing attempt or question ID']);
    exit;
}

$saved = AssessmentResultService::saveAnswerDelta($attemptId, $questionId, $answerData);

echo json_encode([
    'success'   => $saved,
    'saved_at'  => date('H:i:s'),
    'timestamp' => time(),
]);
exit;

