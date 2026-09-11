<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Exam Attendance Management Roster
 * Displays and tracks real-time student attendance based on the name, roll number, class,
 * and workstation code entered by students upon sitting for their assessment examination.
 * Includes official printable attendance sign-off sheet and CSV export.
 */

Middleware::requireAuth();

$user = Auth::user();
$instituteName = getSetting('institute_name', 'vibe.Sınav');
$systemName = getSetting('system_name', 'vibe.Sınav');

// Fetch all assessments for dropdown selector
$assessments = Database::fetchAll(
    "SELECT a.id, a.title, a.target_class, a.status, a.duration_minutes, sub.name AS subject_name,
            u.username AS teacher_name, c.name AS campus_name,
            (SELECT COUNT(*) FROM assessment_attempts att WHERE att.assessment_id = a.id) AS total_attempts
     FROM assessments a
     LEFT JOIN subjects sub ON a.subject_id = sub.id
     LEFT JOIN users u ON a.created_by = u.id
     LEFT JOIN campuses c ON a.campus_id = c.id
     ORDER BY a.id DESC"
);

// Determine selected assessment ID
$selectedAssessmentId = (int)($_GET['assessment_id'] ?? ($_GET['id'] ?? 0));
if ($selectedAssessmentId <= 0 && !empty($assessments)) {
    // Default to latest published assessment or first assessment
    foreach ($assessments as $ass) {
        if ($ass['status'] === 'published') {
            $selectedAssessmentId = (int)$ass['id'];
            break;
        }
    }
    if ($selectedAssessmentId <= 0) {
        $selectedAssessmentId = (int)$assessments[0]['id'];
    }
}

// Find current assessment details
$selectedAssessment = null;
foreach ($assessments as $ass) {
    if ((int)$ass['id'] === $selectedAssessmentId) {
        $selectedAssessment = $ass;
        break;
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Fetch student attendance records for this assessment based on entered session data
$query = "SELECT 
              att.id AS attempt_id,
              att.assessment_id,
              att.station_id,
              att.student_name,
              att.student_roll_no,
              att.student_class,
              att.started_at,
              att.submitted_at,
              att.status AS attempt_status,
              att.needs_review,
              att.is_reviewed,
              ls.station_code,
              ls.ip_address,
              res.obtained_marks,
              res.total_marks,
              res.percentage,
              res.pass_fail,
              (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id) as answered_count
          FROM assessment_attempts att
          LEFT JOIN lab_stations ls ON att.station_id = ls.id
          LEFT JOIN assessment_results res ON att.id = res.attempt_id
          WHERE att.assessment_id = ?";

$params = [$selectedAssessmentId];

if ($search !== '') {
    $query .= " AND (att.student_name LIKE ? OR att.student_roll_no LIKE ? OR ls.station_code LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== '') {
    $query .= " AND att.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY ls.station_code ASC, att.student_roll_no ASC, att.id ASC";

$attendanceRecords = Database::fetchAll($query, $params);

// -------------------------------------------------------------
// HANDLE CSV EXPORT
// -------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selectedAssessment) {
    $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$selectedAssessment['title']);
    $filename = sprintf("Attendance_%s_%s_%s.csv", $selectedAssessmentId, $safeTitle, date('Ymd_His'));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Windows Excel
    fwrite($output, "\xEF\xBB\xBF");

    // CSV Headers
    fputcsv($output, [
        'S.No',
        'Workstation Code',
        'Roll Number',
        'Student Full Name',
        'Class / Grade',
        'Sign-in (Started At)',
        'Turn-in (Submitted At)',
        'Attendance Status',
        'IP Address',
        'Exam Duration',
        'Invigilator Verification'
    ]);

    $sno = 1;
    foreach ($attendanceRecords as $row) {
        $started = !empty($row['started_at']) ? date('Y-m-d h:i:s A', strtotime($row['started_at'])) : '—';
        $submitted = !empty($row['submitted_at']) ? date('Y-m-d h:i:s A', strtotime($row['submitted_at'])) : ($row['attempt_status'] === 'in_progress' ? 'IN PROGRESS' : '—');
        
        $statusLabel = match($row['attempt_status']) {
            'in_progress'  => 'PRESENT (IN PROGRESS)',
            'completed'    => 'PRESENT (COMPLETED)',
            'timed_out'    => 'PRESENT (TIMED OUT)',
            'disqualified' => 'DISQUALIFIED',
            default        => strtoupper((string)$row['attempt_status'])
        };

        $durationMins = '';
        if (!empty($row['started_at'])) {
            $end = !empty($row['submitted_at']) ? strtotime($row['submitted_at']) : time();
            $diff = max(0, $end - strtotime($row['started_at']));
            $durationMins = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
        }

        fputcsv($output, [
            $sno++,
            $row['station_code'] ?? 'Workstation',
            $row['student_roll_no'] ?? 'N/A',
            $row['student_name'] ?? 'N/A',
            $row['student_class'] ?? ($selectedAssessment['target_class'] ?? 'General'),
            $started,
            $submitted,
            $statusLabel,
            $row['ip_address'] ?? 'LAN Terminal',
            $durationMins,
            'VERIFIED'
        ]);
    }
    fclose($output);
    exit;
}

// Attendance Summary Stats
$totalAppeared = count($attendanceRecords);
$activeCount = 0;
$completedCount = 0;
$timedOutCount = 0;
$disqualifiedCount = 0;
$stationsSeen = [];

foreach ($attendanceRecords as $att) {
    if ($att['attempt_status'] === 'in_progress') $activeCount++;
    elseif ($att['attempt_status'] === 'completed') $completedCount++;
    elseif ($att['attempt_status'] === 'timed_out') $timedOutCount++;
    elseif ($att['attempt_status'] === 'disqualified') $disqualifiedCount++;

    if (!empty($att['station_code'])) {
        $stationsSeen[$att['station_code']] = true;
    }
}
$distinctStations = count($stationsSeen);
?>

<div class="d-print-none">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-warning text-dark px-2.5 py-1.5 fs-7"><i class="fas fa-clipboard-user me-1"></i>EXAM ROSTER</span>
                <h4 class="mb-0 fw-bold">Student Exam Attendance</h4>
            </div>
            <p class="text-muted small mb-0">
                Live attendance record captured dynamically from student entries at lab workstations during testing.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Assessment Switcher -->
            <form method="GET" action="<?= url('attendance') ?>" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="page" value="attendance">
                <select name="assessment_id" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()" style="min-width: 240px;">
                    <?php if (empty($assessments)): ?>
                        <option value="">No Assessments Found</option>
                    <?php else: ?>
                        <?php foreach ($assessments as $ass): ?>
                            <option value="<?= (int)$ass['id'] ?>" <?= ((int)$ass['id'] === $selectedAssessmentId) ? 'selected' : '' ?>>
                                <?= e($ass['title']) ?> (<?= e($ass['target_class']) ?>) [<?= (int)$ass['total_attempts'] ?> students]
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <!-- Export to CSV -->
            <a href="<?= url('attendance') ?>&assessment_id=<?= $selectedAssessmentId ?>&export=csv" class="btn btn-outline-success btn-sm" title="Download attendance list as CSV">
                <i class="fas fa-file-csv me-1"></i> Save List (CSV)
            </a>

            <!-- Print Attendance Sheet -->
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" title="Print official attendance roster">
                <i class="fas fa-print me-1"></i> Print Attendance Sheet
            </button>

            <!-- Live Monitor Link -->
            <a href="<?= url('monitoring&id=' . $selectedAssessmentId) ?>" class="btn btn-outline-danger btn-sm" title="View live proctoring telemetry">
                <i class="fas fa-satellite-dish me-1"></i> Live Monitor
            </a>
        </div>
    </div>

    <!-- Assessment Details & Summary Banner -->
    <?php if ($selectedAssessment): ?>
    <div class="card border mb-4 shadow-sm" style="background: var(--bg-surface, #1e222d);">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">
                        <i class="fas fa-file-signature me-2"></i><?= e($selectedAssessment['title']) ?>
                    </h5>
                    <div class="text-muted small">
                        Grade / Class: <strong class="text-light"><?= e($selectedAssessment['target_class'] ?? 'General') ?></strong> • 
                        Subject: <strong class="text-light"><?= e($selectedAssessment['subject_name'] ?? 'General') ?></strong> • 
                        Invigilator: <strong class="text-light"><?= e($selectedAssessment['teacher_name'] ?? 'Staff') ?></strong> • 
                        Duration: <strong><?= (int)$selectedAssessment['duration_minutes'] ?> mins</strong>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= ($selectedAssessment['status'] === 'published') ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                        <i class="fas <?= ($selectedAssessment['status'] === 'published') ? 'fa-satellite-dish' : 'fa-box-archive' ?> me-1"></i>
                        <?= strtoupper($selectedAssessment['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Total Candidates Attended</div>
                <div class="fs-2 fw-bold text-primary"><?= $totalAppeared ?></div>
                <div class="text-muted small">Entered Exam Portal</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Attempting Currently</div>
                <div class="fs-2 fw-bold text-info d-flex align-items-center justify-content-center gap-2">
                    <span class="spinner-grow spinner-grow-sm text-info" style="width: 8px; height: 8px;"></span>
                    <?= $activeCount ?>
                </div>
                <div class="text-muted small">Sitting at Workstations</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Exams Submitted</div>
                <div class="fs-2 fw-bold text-success"><?= $completedCount ?></div>
                <div class="text-muted small">Turned In Successfully</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Workstations Occupied</div>
                <div class="fs-2 fw-bold text-warning"><?= $distinctStations ?></div>
                <div class="text-muted small">Active Hardware Terminals</div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('attendance') ?>" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="attendance">
                <input type="hidden" name="assessment_id" value="<?= $selectedAssessmentId ?>">

                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" 
                               placeholder="Search student name, roll number, workstation..." value="<?= e($search) ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Attendance Statuses</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Present (In Progress / Attempting)</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Present (Completed / Submitted)</option>
                        <option value="timed_out" <?= $statusFilter === 'timed_out' ? 'selected' : '' ?>>Timed Out</option>
                        <option value="disqualified" <?= $statusFilter === 'disqualified' ? 'selected' : '' ?>>Disqualified</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $statusFilter !== ''): ?>
                        <a href="<?= url('attendance') ?>&assessment_id=<?= $selectedAssessmentId ?>" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- OFFICIAL PRINTABLE ATTENDANCE SHEET HEADER (PRINT ONLY)       -->
<!-- ============================================================= -->
<div class="d-none d-print-block mb-4 pb-2 border-bottom border-dark text-dark">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h3 class="fw-bold mb-0 text-uppercase"><?= e($instituteName) ?></h3>
            <div class="small fw-semibold text-muted">Official Examination Invigilation & Candidate Attendance Roster</div>
        </div>
        <div class="text-end small">
            <div>Date Printed: <strong><?= date('Y-m-d h:i A') ?></strong></div>
            <div>Generated by: <strong><?= e($user['full_name'] ?? 'Invigilator') ?></strong></div>
        </div>
    </div>

    <?php if ($selectedAssessment): ?>
    <div class="p-2 border border-secondary rounded bg-light mt-2 small">
        <div class="row g-2">
            <div class="col-4"><strong>Assessment:</strong> <?= e($selectedAssessment['title']) ?></div>
            <div class="col-4"><strong>Subject:</strong> <?= e($selectedAssessment['subject_name'] ?? 'General') ?></div>
            <div class="col-4"><strong>Class / Grade:</strong> <?= e($selectedAssessment['target_class'] ?? 'General') ?></div>
            <div class="col-4"><strong>Duration:</strong> <?= (int)$selectedAssessment['duration_minutes'] ?> Minutes</div>
            <div class="col-4"><strong>Teacher / Proctor:</strong> <?= e($selectedAssessment['teacher_name'] ?? 'Faculty') ?></div>
            <div class="col-4"><strong>Total Candidates:</strong> <?= $totalAppeared ?> Students</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- ATTENDANCE ROSTER TABLE (SCREEN & PRINT)                     -->
<!-- ============================================================= -->
<div class="card border shadow-sm">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center d-print-none">
        <h6 class="mb-0 fw-bold">
            <i class="fas fa-users me-2 text-primary"></i>Candidate Attendance Roster
        </h6>
        <span class="badge bg-secondary font-monospace"><?= count($attendanceRecords) ?> Records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="attendanceTable">
            <thead class="text-muted small text-uppercase border-bottom">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="min-width: 110px;">Workstation</th>
                    <th style="min-width: 120px;">Roll Number</th>
                    <th style="min-width: 170px;">Candidate Name</th>
                    <th style="min-width: 110px;">Class / Grade</th>
                    <th style="min-width: 130px;">Sign-in Time</th>
                    <th style="min-width: 130px;">Turn-in Time</th>
                    <th style="min-width: 140px;">Status</th>
                    <th class="d-none d-print-table-cell" style="min-width: 120px;">Signature</th>
                    <th class="text-end d-print-none" style="min-width: 90px;">Audit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendanceRecords)): ?>
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="fas fa-user-clock fa-2x mb-2 d-block opacity-50"></i>
                        No attendance records found for this assessment.
                        <div class="small mt-1">Candidates sitting at workstations will automatically populate this roster in real time.</div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $sno = 1; foreach ($attendanceRecords as $row): ?>
                    <?php 
                        $startedFmt = !empty($row['started_at']) ? date('h:i:s A', strtotime($row['started_at'])) : '—';
                        $submittedFmt = !empty($row['submitted_at']) ? date('h:i:s A', strtotime($row['submitted_at'])) : ($row['attempt_status'] === 'in_progress' ? 'In Progress' : '—');
                    ?>
                    <tr>
                        <td class="font-monospace small text-muted"><?= $sno++ ?></td>
                        <td>
                            <span class="badge bg-secondary font-monospace px-2 py-1 fs-7">
                                <i class="fas fa-desktop me-1 text-info"></i><?= e($row['station_code'] ?? 'Workstation') ?>
                            </span>
                        </td>
                        <td class="font-monospace fw-bold text-info"><?= e($row['student_roll_no']) ?></td>
                        <td>
                            <span class="fw-bold text-light"><?= e($row['student_name']) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-dark border text-light"><?= e($row['student_class'] ?? ($selectedAssessment['target_class'] ?? 'General')) ?></span>
                        </td>
                        <td class="font-monospace small text-muted"><?= $startedFmt ?></td>
                        <td class="font-monospace small text-muted"><?= $submittedFmt ?></td>
                        <td>
                            <?php if ($row['attempt_status'] === 'in_progress'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary">
                                    <span class="spinner-grow spinner-grow-sm me-1" style="width: 5px; height: 5px;"></span>Present (In Progress)
                                </span>
                            <?php elseif ($row['attempt_status'] === 'completed'): ?>
                                <span class="badge bg-success-subtle text-success border border-success">
                                    <i class="fas fa-check-circle me-1"></i>Present (Submitted)
                                </span>
                            <?php elseif ($row['attempt_status'] === 'timed_out'): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning">
                                    <i class="fas fa-clock me-1"></i>Timed Out
                                </span>
                            <?php elseif ($row['attempt_status'] === 'disqualified'): ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-ban me-1"></i>Disqualified
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= e($row['attempt_status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <!-- Signature line for physical paper printing -->
                        <td class="d-none d-print-table-cell text-center" style="border-bottom: 1px dashed #999;">
                            &nbsp;
                        </td>
                        <td class="text-end d-print-none">
                            <a href="<?= url('assessments-candidate-detail&attempt_id=' . $row['attempt_id']) ?>" class="btn btn-outline-info btn-sm" title="View Candidate Audit">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================= -->
<!-- OFFICIAL INVIGILATOR SIGN-OFF BLOCK (PRINT ONLY)              -->
<!-- ============================================================= -->
<div class="d-none d-print-block mt-5 pt-4 border-top border-dark text-dark">
    <div class="row">
        <div class="col-4">
            <div class="small fw-bold">Chief Invigilator:</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Signature & Date</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold">Lab Assistant / Tech Officer:</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Signature & Date</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold">Academic Supervisor / Principal:</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Official Stamp & Signature</div>
        </div>
    </div>
</div>
