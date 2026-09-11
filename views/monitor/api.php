<?php
/**
 * Live Test Monitor — Polling JSON API Endpoint
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!Session::isAuthenticated() || !Auth::hasPermission('live_tests.view')) {
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$competitionId = (int)($_GET['competition_id'] ?? 0);
$viewMode = trim($_GET['view_mode'] ?? 'active');
if (!in_array($viewMode, ['active', 'latest', 'all'], true)) {
    $viewMode = 'active';
}

$where = ["1=1"];
$params = [];

if ($competitionId > 0) {
    $where[] = "a.competition_id = ?";
    $params[] = $competitionId;
}

$whereSql = implode(' AND ', $where);

// Calculate authoritative summary counters (unique candidate scope)
$compParams = $competitionId > 0 ? [$competitionId] : [];
$compCond = $competitionId > 0 ? "AND competition_id = ?" : "";

$inProgressCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM test_attempts WHERE status = 'in_progress' {$compCond}",
    $compParams
);

$completedCount = (int)Database::fetchColumn(
    "SELECT COUNT(DISTINCT candidate_id) FROM test_attempts WHERE status = 'completed' {$compCond}",
    $compParams
);

$timedOutCount = (int)Database::fetchColumn(
    "SELECT COUNT(DISTINCT candidate_id) FROM test_attempts WHERE status = 'timed_out' {$compCond}",
    $compParams
);

$disqualifiedCount = (int)Database::fetchColumn(
    "SELECT COUNT(DISTINCT candidate_id) FROM test_attempts WHERE status = 'disqualified' {$compCond}",
    $compParams
);

$counts = [
    'in_progress'  => $inProgressCount,
    'completed'    => $completedCount,
    'timed_out'    => $timedOutCount,
    'disqualified' => $disqualifiedCount,
];

// Fetch attempts based on view mode
$attempts = [];

if ($viewMode === 'all') {
    // Show all attempt history
    $sql = "
        SELECT 
            a.id AS attempt_id,
            a.candidate_id,
            a.competition_id,
            a.attempt_number,
            a.started_at,
            a.expected_end_at,
            a.finished_at,
            a.duration_seconds,
            a.status AS attempt_status,
            c.full_name AS candidate_name,
            c.roll_number,
            c.registration_number,
            c.status AS candidate_status,
            cmp.name AS competition_name,
            cmp.code AS competition_code,
            p.current_position,
            p.typed_text,
            p.last_saved_at,
            (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = a.id) AS violation_count
        FROM test_attempts a
        JOIN candidates c ON a.candidate_id = c.id
        JOIN competitions cmp ON a.competition_id = cmp.id
        LEFT JOIN test_progress p ON a.id = p.attempt_id
        WHERE {$whereSql}
        ORDER BY 
            CASE 
                WHEN a.status = 'in_progress' THEN 1 
                WHEN a.status = 'pending' THEN 2
                WHEN a.status = 'disqualified' THEN 3
                ELSE 4 
            END,
            a.id DESC
        LIMIT 100
    ";
    $attempts = Database::fetchAll($sql, $params);

} elseif ($viewMode === 'latest') {
    // Show single latest attempt per candidate
    $subWhere = $competitionId > 0 ? "WHERE competition_id = ?" : "";
    $sql = "
        SELECT 
            a.id AS attempt_id,
            a.candidate_id,
            a.competition_id,
            a.attempt_number,
            a.started_at,
            a.expected_end_at,
            a.finished_at,
            a.duration_seconds,
            a.status AS attempt_status,
            c.full_name AS candidate_name,
            c.roll_number,
            c.registration_number,
            c.status AS candidate_status,
            cmp.name AS competition_name,
            cmp.code AS competition_code,
            p.current_position,
            p.typed_text,
            p.last_saved_at,
            (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = a.id) AS violation_count
        FROM test_attempts a
        INNER JOIN (
            SELECT candidate_id, MAX(id) AS max_attempt_id
            FROM test_attempts
            {$subWhere}
            GROUP BY candidate_id
        ) latest ON a.id = latest.max_attempt_id
        JOIN candidates c ON a.candidate_id = c.id
        JOIN competitions cmp ON a.competition_id = cmp.id
        LEFT JOIN test_progress p ON a.id = p.attempt_id
        WHERE {$whereSql}
        ORDER BY 
            CASE 
                WHEN a.status = 'in_progress' THEN 1 
                WHEN a.status = 'pending' THEN 2
                WHEN a.status = 'disqualified' THEN 3
                ELSE 4 
            END,
            a.id DESC
        LIMIT 100
    ";
    $attempts = Database::fetchAll($sql, array_merge($compParams, $params));

} else {
    // Check if there are active (in_progress, pending) sessions
    $activeCountNow = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM test_attempts a WHERE {$whereSql} AND a.status IN ('in_progress', 'pending')",
        $params
    );

    if ($activeCountNow > 0) {
        $activeSql = "
            SELECT 
                a.id AS attempt_id,
                a.candidate_id,
                a.competition_id,
                a.attempt_number,
                a.started_at,
                a.expected_end_at,
                a.finished_at,
                a.duration_seconds,
                a.status AS attempt_status,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                c.status AS candidate_status,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                p.current_position,
                p.typed_text,
                p.last_saved_at,
                (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = a.id) AS violation_count
            FROM test_attempts a
            JOIN candidates c ON a.candidate_id = c.id
            JOIN competitions cmp ON a.competition_id = cmp.id
            LEFT JOIN test_progress p ON a.id = p.attempt_id
            WHERE {$whereSql}
              AND (
                  a.status IN ('in_progress', 'pending')
                  OR (a.status IN ('timed_out', 'disqualified') AND a.started_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE))
              )
            ORDER BY 
                CASE 
                    WHEN a.status = 'in_progress' THEN 1 
                    WHEN a.status = 'pending' THEN 2
                    WHEN a.status = 'disqualified' THEN 3
                    ELSE 4 
                END,
                a.id DESC
            LIMIT 100
        ";
        $attempts = Database::fetchAll($activeSql, $params);
    } else {
        // When no active candidates exist, show latest attempt per candidate (once per candidate)
        $subWhere = $competitionId > 0 ? "WHERE competition_id = ?" : "";
        $fallbackSql = "
            SELECT 
                a.id AS attempt_id,
                a.candidate_id,
                a.competition_id,
                a.attempt_number,
                a.started_at,
                a.expected_end_at,
                a.finished_at,
                a.duration_seconds,
                a.status AS attempt_status,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                c.status AS candidate_status,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                p.current_position,
                p.typed_text,
                p.last_saved_at,
                (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = a.id) AS violation_count
            FROM test_attempts a
            INNER JOIN (
                SELECT candidate_id, MAX(id) AS max_attempt_id
                FROM test_attempts
                {$subWhere}
                GROUP BY candidate_id
            ) latest ON a.id = latest.max_attempt_id
            JOIN candidates c ON a.candidate_id = c.id
            JOIN competitions cmp ON a.competition_id = cmp.id
            LEFT JOIN test_progress p ON a.id = p.attempt_id
            WHERE {$whereSql}
            ORDER BY 
                CASE 
                    WHEN a.status = 'in_progress' THEN 1 
                    WHEN a.status = 'pending' THEN 2
                    WHEN a.status = 'disqualified' THEN 3
                    ELSE 4 
                END,
                a.id DESC
            LIMIT 100
        ";
        $attempts = Database::fetchAll($fallbackSql, array_merge($compParams, $params));
    }
}

$now = time();
$data = [];

foreach ($attempts as $row) {
    $status = $row['attempt_status'];
    $expectedEnd = strtotime($row['expected_end_at']);
    $remainingSeconds = max(0, $expectedEnd - $now);

    // Detect disconnection: active attempt with no auto-save in last 20 seconds
    $isDisconnected = false;
    if ($status === 'in_progress') {
        $lastSaved = $row['last_saved_at'] ? strtotime($row['last_saved_at']) : strtotime($row['started_at']);
        if ($now - $lastSaved > 20) {
            $isDisconnected = true;
        }
    }

    $charCount = (int)($row['current_position'] ?? mb_strlen($row['typed_text'] ?? ''));

    $data[] = [
        'attempt_id'        => (int)$row['attempt_id'],
        'candidate_name'    => $row['candidate_name'],
        'roll_number'       => $row['roll_number'] ?: $row['registration_number'],
        'competition_code'  => $row['competition_code'],
        'attempt_number'    => (int)$row['attempt_number'],
        'started_at'        => formatDateTime($row['started_at']),
        'remaining_seconds' => $remainingSeconds,
        'remaining_display' => sprintf('%02d:%02d', floor($remainingSeconds / 60), $remainingSeconds % 60),
        'char_count'        => $charCount,
        'violation_count'   => (int)$row['violation_count'],
        'status'            => $status,
        'is_disconnected'   => $isDisconnected,
        'last_activity'     => $row['last_saved_at'] ? formatDateTime($row['last_saved_at']) : '—',
    ];
}

$counts['total'] = count($data);

echo json_encode([
    'status'      => 'ok',
    'view_mode'   => $viewMode,
    'counts'      => $counts,
    'attempts'    => $data,
    'server_time' => date('Y-m-d H:i:s'),
]);
if (!defined('TESTING_MODE')) {
    exit;
}
