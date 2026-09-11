<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Real-Time Authoritative Autosave API
 * Delta answer persistence with timeout enforcement.
 */

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$attemptId = (int)($data['attempt_id'] ?? 0);
$questionId = (int)($data['question_id'] ?? 0);
$answer = $data['answer'] ?? null;

if ($attemptId <= 0 || $questionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid attempt_id and question_id are required']);
    exit;
}

try {
    ExamExecutionService::saveDeltaAnswer($attemptId, $questionId, $answer);
    echo json_encode([
        'success'  => true,
        'saved_at' => date('H:i:s'),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
exit;

