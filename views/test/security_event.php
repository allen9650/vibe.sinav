<?php
/**
 * Candidate Test Portal — Anti-Cheating Security Event AJAX Endpoint
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
$attemptId = (int)($_POST['attempt_id'] ?? Session::get('current_attempt_id'));
$eventType = strtoupper(trim((string)($_POST['event_type'] ?? 'OTHER')));
$details = trim((string)($_POST['event_details'] ?? ''));

// Validate allowed event types
$allowedTypes = [
    'TAB_SWITCH',
    'WINDOW_BLUR',
    'FULLSCREEN_EXIT',
    'COPY_ATTEMPT',
    'PASTE_ATTEMPT',
    'CUT_ATTEMPT',
    'RIGHT_CLICK_ATTEMPT',
    'MULTIPLE_TAB_WARNING',
    'DEVTOOLS_SUSPECTED',
    'OTHER',
];

if (!in_array($eventType, $allowedTypes, true)) {
    $eventType = 'OTHER';
}

$attempt = Database::fetch(
    "SELECT * FROM test_attempts WHERE id = ? AND candidate_id = ?",
    [$attemptId, $candidateId]
);

if (!$attempt) {
    echo json_encode(['status' => 'error', 'message' => 'Attempt not found.']);
    exit;
}

// Reject events for already finished/disqualified attempts
if ($attempt['status'] !== 'in_progress') {
    echo json_encode(['status' => 'inactive', 'attempt_status' => $attempt['status']]);
    exit;
}

$competitionId = (int)$attempt['competition_id'];
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]) ?: [];
$maxViolations = max(1, (int)($settings['max_violations'] ?? 3));
$violationAction = $settings['violation_action'] ?? 'warning';

// 1. Deduplication Window: Check if same event logged in last 1.0 second
$recentSameEvent = Database::fetch(
    "SELECT id FROM security_events WHERE attempt_id = ? AND event_type = ? AND created_at >= (NOW() - INTERVAL 1 SECOND) LIMIT 1",
    [$attemptId, $eventType]
);

if (!$recentSameEvent) {
    // Determine severity
    $severity = 'low';
    if (in_array($eventType, ['COPY_ATTEMPT', 'PASTE_ATTEMPT', 'CUT_ATTEMPT', 'FULLSCREEN_EXIT'], true)) {
        $severity = 'medium';
    }
    if (in_array($eventType, ['DEVTOOLS_SUSPECTED', 'MULTIPLE_TAB_WARNING'], true)) {
        $severity = 'high';
    }

    Database::insert('security_events', [
        'attempt_id'       => $attemptId,
        'candidate_id'     => $candidateId,
        'competition_id'   => $competitionId,
        'event_type'       => $eventType,
        'description'      => "Security event {$eventType} detected during test attempt #{$attemptId}",
        'event_details'    => $details,
        'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'user_agent'       => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500),
        'severity'         => $severity,
    ]);
}

// Count total unique security events for this attempt
$totalViolations = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM security_events WHERE attempt_id = ?",
    [$attemptId]
);

// 2. Check Disqualification Trigger
if ($violationAction === 'auto_disqualify' && $totalViolations >= $maxViolations) {
    Database::beginTransaction();
    try {
        Database::update('test_attempts', [
            'status'             => 'disqualified',
            'finished_at'        => date('Y-m-d H:i:s'),
            'submitted_at'       => date('Y-m-d H:i:s'),
            'time_taken_seconds' => max(1, time() - strtotime($attempt['started_at'])),
        ], 'id = ?', [$attemptId]);

        Database::update('candidates', ['status' => 'disqualified'], 'id = ?', [$candidateId]);

        Database::commit();

        AuditLog::log('candidate_auto_disqualified', 'security_events', 
            "Candidate #{$candidateId} auto-disqualified on attempt #{$attemptId} after {$totalViolations} violations (Max: {$maxViolations})", 
            null, 
            null, 
            ['candidate_id' => $candidateId, 'attempt_id' => $attemptId, 'violations' => $totalViolations]
        );

        echo json_encode([
            'status'        => 'disqualified',
            'violations'    => $totalViolations,
            'max_violations'=> $maxViolations,
            'message'       => 'Test ended. You have exceeded the security violation limit.',
            'redirect_url'  => url('test-disqualified'),
        ]);
        exit;
    } catch (Exception $e) {
        Database::rollback();
    }
}

echo json_encode([
    'status'          => 'ok',
    'action'          => $violationAction,
    'violations'      => $totalViolations,
    'max_violations'  => $maxViolations,
    'message'         => "Security warning: {$eventType} recorded (Violation {$totalViolations} of {$maxViolations})",
]);
exit;
