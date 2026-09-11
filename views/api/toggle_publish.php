<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Result Publication Toggle API
 * Toggles candidate scorecard visibility between withheld and released.
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
    $data = $_POST;
}

$assessmentId = (int)($data['assessment_id'] ?? 0);
if ($assessmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid assessment ID is required']);
    exit;
}

$assessment = Database::fetch("SELECT id, is_result_published FROM assessments WHERE id = ?", [$assessmentId]);
if (!$assessment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assessment not found']);
    exit;
}

$currentStatus = ((int)$assessment['is_result_published'] === 1);
$newStatus = isset($data['target_status']) ? (bool)$data['target_status'] : !$currentStatus;

try {
    AssessmentService::toggleResultPublish($assessmentId, $newStatus);
    echo json_encode([
        'success'      => true,
        'is_published' => $newStatus,
        'message'      => $newStatus ? 'Results have been released to candidates.' : 'Results are now withheld from candidates.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
exit;

