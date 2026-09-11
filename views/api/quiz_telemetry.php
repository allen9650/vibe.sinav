<?php
/**
 * PTM Assessment System — Proctoring & Security Telemetry Endpoint
 */
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false]);
    exit;
}

$attemptId = (int)($input['attempt_id'] ?? 0);
$eventType = trim($input['event_type'] ?? 'suspicious_activity');
$metadata = (array)($input['metadata'] ?? []);

$attempt = Database::fetch("SELECT * FROM assessment_attempts WHERE id = ?", [$attemptId]);
$userId = Session::get('user_id') ?: null;
$candidateId = $attempt ? $attempt['candidate_id'] : Session::get('candidate_id');

// Append candidate ID to metadata
$metadata['candidate_id'] = $candidateId;
$metadata['attempt_id'] = $attemptId;

$durationSec = (int)($metadata['duration_seconds'] ?? 0);
$customDesc = trim((string)($input['description'] ?? ''));
if ($customDesc === '') {
    if ($durationSec > 0) {
        $customDesc = "Assessment violation: {$eventType} (away for {$durationSec}s) on attempt #{$attemptId}";
    } else {
        $customDesc = "Assessment violation: {$eventType} on attempt #{$attemptId}";
    }
}
$severity = in_array(strtolower($input['severity'] ?? ''), ['critical', 'high', 'medium', 'low'], true)
    ? strtolower($input['severity'])
    : (in_array($eventType, ['FULLSCREEN_EXIT', 'TAB_SWITCH', 'WINDOW_BLUR_ANOMALY'], true) ? 'high' : 'medium');

try {
    Database::insert('security_events', [
        'user_id'     => $userId,
        'attempt_id'  => $attemptId,
        'event_type'  => $eventType,
        'description' => $customDesc,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'metadata'    => json_encode($metadata),
        'severity'    => $severity,
    ]);

    // Check violation count for this attempt
    $violationsCount = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM security_events 
         WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.attempt_id')) = ?",
        [(string)$attemptId]
    );

    echo json_encode([
        'success'          => true,
        'violations_count' => $violationsCount,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;

