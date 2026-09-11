<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Live Proctoring Telemetry & Interactive Proctoring API
 * Ultra-lightweight LAN telemetry, candidate attempt inspection, subjective question grading,
 * and live exam proctor actions.
 */

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'telemetry');
$assessmentId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// Auto-detect latest active assessment if not provided
if ($assessmentId <= 0) {
    $detected = Database::fetch(
        "SELECT id FROM assessments WHERE status = 'published' ORDER BY id DESC LIMIT 1"
    );
    if (!$detected) {
        $detected = Database::fetch("SELECT id FROM assessments ORDER BY id DESC LIMIT 1");
    }
    $assessmentId = $detected ? (int)$detected['id'] : 0;
}

// -------------------------------------------------------------
// ACTION: Force Submit In-Progress Attempt
// -------------------------------------------------------------
if ($action === 'force_submit') {
    $attemptId = (int)($_POST['attempt_id'] ?? ($_GET['attempt_id'] ?? 0));
    if ($attemptId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
        exit;
    }
    try {
        require_once CORE_PATH . '/ExamExecutionService.php';
        $res = ExamExecutionService::submitExam($attemptId, []);
        AuditLog::log('attempt_force_submitted', 'assessments', "Teacher force-submitted attempt #$attemptId", Auth::id());
        echo json_encode(['success' => true, 'message' => 'Attempt submitted successfully.', 'result' => $res]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Disqualify Candidate Attempt
// -------------------------------------------------------------
if ($action === 'disqualify_candidate') {
    $attemptId = (int)($_POST['attempt_id'] ?? ($_GET['attempt_id'] ?? 0));
    if ($attemptId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
        exit;
    }
    try {
        Database::update('assessment_attempts', ['status' => 'disqualified'], 'id = ?', [$attemptId]);
        AuditLog::log('candidate_disqualified', 'assessments', "Invigilator disqualified attempt #$attemptId", Auth::id());
        echo json_encode(['success' => true, 'message' => "Candidate attempt #$attemptId disqualified."]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Get Attempt Inspection (Full Scorecard & Questions)
// -------------------------------------------------------------
if ($action === 'get_attempt_inspection') {
    $attemptId = (int)($_GET['attempt_id'] ?? 0);
    if ($attemptId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
        exit;
    }
    try {
        $scorecard = AssessmentResultService::getCandidateScorecard($attemptId);
        if (!$scorecard) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Attempt details not found']);
            exit;
        }
        echo json_encode(['success' => true, 'scorecard' => $scorecard]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Get Attempt Review Questions (Subjective Questions)
// -------------------------------------------------------------
if ($action === 'get_attempt_review') {
    $attemptId = (int)($_GET['attempt_id'] ?? 0);
    if ($attemptId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid attempt ID']);
        exit;
    }
    try {
        $scorecard = AssessmentResultService::getCandidateScorecard($attemptId);
        if (!$scorecard) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Attempt not found']);
            exit;
        }

        // Filter for subjective questions or questions flagged for review
        $reviewQuestions = [];
        foreach ($scorecard['questions'] as $q) {
            $isSubjective = in_array($q['question_type'], ['fill_blank', 'short_answer'], true);
            if ($isSubjective || (int)($q['needs_review'] ?? 0) === 1) {
                $reviewQuestions[] = $q;
            }
        }

        echo json_encode([
            'success'          => true,
            'attempt_id'       => $attemptId,
            'candidate_name'   => $scorecard['candidate_name'] ?? $scorecard['student_name'],
            'roll_number'      => $scorecard['roll_number'] ?? $scorecard['student_roll_no'],
            'station_code'     => $scorecard['station_code'] ?? 'Workstation',
            'review_questions' => $reviewQuestions,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: Save Subjective Question Grades
// -------------------------------------------------------------
if ($action === 'save_review') {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $attemptId = (int)($payload['attempt_id'] ?? 0);
    $grades = $payload['grades'] ?? [];
    $finalize = !empty($payload['finalize']);

    if ($attemptId <= 0 || empty($grades)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing attempt ID or grades']);
        exit;
    }

    try {
        $userId = (int)Auth::id();
        foreach ($grades as $g) {
            $qId = (int)($g['question_id'] ?? 0);
            $marks = (float)($g['marks_awarded'] ?? 0.0);
            $comment = trim((string)($g['teacher_comment'] ?? ''));
            if ($qId > 0) {
                AssessmentResultService::gradeQuestion($attemptId, $qId, $marks, $comment, $userId);
            }
        }

        if ($finalize) {
            AssessmentResultService::finalizeCandidateResult($attemptId, $userId);
        }

        echo json_encode([
            'success'   => true,
            'message'   => $finalize ? 'Review saved and result finalized.' : 'Marks saved successfully.',
            'finalized' => $finalize,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// DEFAULT ACTION: Live Proctor Telemetry Feed
// -------------------------------------------------------------
if ($assessmentId <= 0) {
    echo json_encode([
        'success'        => true,
        'assessment_id'  => 0,
        'candidates'     => [],
        'stats'          => [
            'active'         => 0,
            'completed'      => 0,
            'pending_review' => 0,
            'violations'     => 0,
            'workstations'   => 0,
        ],
        'formatted_time' => date('h:i:s A'),
    ]);
    exit;
}

$assessment = Database::fetch(
    "SELECT a.*, camp.name AS campus_name, sub.name AS subject_name, u.username AS teacher_name
     FROM assessments a
     LEFT JOIN campuses camp ON a.campus_id = camp.id
     LEFT JOIN subjects sub ON a.subject_id = sub.id
     LEFT JOIN users u ON a.created_by = u.id
     WHERE a.id = ?",
    [$assessmentId]
);

if (!$assessment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assessment not found']);
    exit;
}

// Total questions in this assessment
$totalQuestions = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
    [$assessmentId]
);

// Fetch all attempts for this assessment
$attempts = Database::fetchAll(
    "SELECT att.id AS attempt_id,
            att.assessment_id,
            att.station_id,
            att.student_name,
            att.student_roll_no,
            att.student_class,
            att.started_at,
            att.expected_end_at,
            att.submitted_at,
            att.status AS attempt_status,
            att.needs_review,
            att.is_reviewed,
            ls.station_code,
            res.total_marks AS res_total_marks,
            res.obtained_marks,
            res.percentage,
            res.pass_fail,
            (SELECT COUNT(*) FROM assessment_answers aa 
             WHERE aa.attempt_id = att.id 
             AND (aa.selected_option_ids IS NOT NULL OR (aa.text_answer IS NOT NULL AND aa.text_answer != ''))) AS answered_count,
            (SELECT COUNT(*) FROM assessment_answers aa 
             WHERE aa.attempt_id = att.id 
             AND aa.is_correct = 1) AS total_correct,
            (SELECT COUNT(*) FROM assessment_answers aa 
             WHERE aa.attempt_id = att.id 
             AND aa.needs_review = 1) AS pending_review_count
     FROM assessment_attempts att
     LEFT JOIN lab_stations ls ON att.station_id = ls.id
     LEFT JOIN assessment_results res ON att.id = res.attempt_id
     WHERE att.assessment_id = ?
     ORDER BY att.id DESC",
    [$assessmentId]
);

$now = time();
$candidates = [];
$activeCount = 0;
$completedCount = 0;
$pendingReviewCount = 0;
$totalViolationsCount = 0;
$workstationsSeen = [];

foreach ($attempts as $att) {
    $attemptId = (int)$att['attempt_id'];
    $expectedEnd = strtotime($att['expected_end_at'] ?? 'now');
    $startedTs = strtotime($att['started_at'] ?? 'now');
    $submittedTs = !empty($att['submitted_at']) ? strtotime($att['submitted_at']) : null;

    $remaining = max(0, $expectedEnd - $now);
    $elapsedSecs = ($submittedTs !== null) ? max(0, $submittedTs - $startedTs) : max(0, $now - $startedTs);

    $status = $att['attempt_status'];
    if ($status === 'in_progress') {
        $activeCount++;
    } elseif (in_array($status, ['completed', 'timed_out'], true)) {
        $completedCount++;
    }

    $needsReview = ((int)($att['needs_review'] ?? 0) === 1 && (int)($att['is_reviewed'] ?? 0) === 0);
    if ($needsReview) {
        $pendingReviewCount++;
    }

    // Violation breakdown
    $violationsRaw = Database::fetchAll(
        "SELECT event_type, COUNT(*) as count 
         FROM security_events 
         WHERE attempt_id = ? OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.attempt_id')) = ?
         GROUP BY event_type",
        [$attemptId, (string)$attemptId]
    );

    $vMap = [
        'tab_switch'      => 0,
        'window_blur'     => 0,
        'fullscreen_exit' => 0,
        'clipboard'       => 0,
        'total'           => 0,
    ];

    foreach ($violationsRaw as $v) {
        $type = strtoupper($v['event_type']);
        $cnt = (int)$v['count'];
        $vMap['total'] += $cnt;
        $totalViolationsCount += $cnt;

        if (str_contains($type, 'TAB')) {
            $vMap['tab_switch'] += $cnt;
        } elseif (str_contains($type, 'BLUR')) {
            $vMap['window_blur'] += $cnt;
        } elseif (str_contains($type, 'FULLSCREEN')) {
            $vMap['fullscreen_exit'] += $cnt;
        } else {
            $vMap['clipboard'] += $cnt;
        }
    }

    $pcName = !empty($att['station_code']) ? $att['station_code'] : ($att['station_id'] ? ('Station #' . $att['station_id']) : 'Workstation');
    $workstationsSeen[$pcName] = true;

    // Marks & percentage
    $maxMarks = (float)($att['res_total_marks'] ?? $assessment['total_marks'] ?? 0.0);
    $obtainedMarks = (float)($att['obtained_marks'] ?? 0.0);
    $percentage = ($att['percentage'] !== null) 
        ? (float)$att['percentage'] 
        : (($maxMarks > 0) ? round(($obtainedMarks / $maxMarks) * 100, 1) : 0.0);

    $candidates[] = [
        'attempt_id'           => $attemptId,
        'pc_name'              => $pcName,
        'student_name'         => $att['student_name'],
        'roll_number'          => $att['student_roll_no'],
        'student_class'        => $att['student_class'],
        'status'               => $status,
        'started_at_fmt'       => date('h:i:s A', $startedTs),
        'submitted_at_fmt'     => $submittedTs ? date('h:i:s A', $submittedTs) : null,
        'remaining_secs'       => $remaining,
        'remaining_fmt'        => sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60),
        'elapsed_fmt'          => sprintf('%02d:%02d', floor($elapsedSecs / 60), $elapsedSecs % 60),
        'answered_count'       => (int)$att['answered_count'],
        'total_questions'      => $totalQuestions,
        'total_correct'        => (int)$att['total_correct'],
        'obtained_marks'       => $obtainedMarks,
        'total_marks'          => $maxMarks,
        'percentage'           => $percentage,
        'pass_fail'            => $att['pass_fail'] ?? ($percentage >= (float)$assessment['passing_percentage'] ? 'pass' : 'fail'),
        'needs_review'         => $needsReview,
        'is_reviewed'          => ((int)($att['is_reviewed'] ?? 0) === 1),
        'pending_review_count' => (int)$att['pending_review_count'],
        'violations'           => $vMap,
    ];
}

echo json_encode([
    'success'              => true,
    'timestamp'            => time(),
    'formatted_time'       => date('h:i:s A'),
    'assessment_id'        => $assessmentId,
    'assessment_title'     => $assessment['title'],
    'class_grade'          => $assessment['target_class'],
    'subject_name'         => $assessment['subject_name'] ?? 'General',
    'teacher_name'         => $assessment['teacher_name'] ?? 'Instructor',
    'is_result_published'  => ((int)$assessment['is_result_published'] === 1),
    'stats'                => [
        'active'         => $activeCount,
        'completed'      => $completedCount,
        'pending_review' => $pendingReviewCount,
        'violations'     => $totalViolationsCount,
        'workstations'   => count($workstationsSeen),
    ],
    'candidates'           => $candidates,
]);
exit;
