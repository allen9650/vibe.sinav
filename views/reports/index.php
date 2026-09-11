<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Reports Center & Candidate-Wise Score Analytics
 * Comprehensive performance auditing, score analytics, grade distribution,
 * candidate-wise merit ledger, CSV export, and printable/downloadable executive dossier.
 */

Middleware::requireAuth();

$user = Auth::user();
$instituteName = getSetting('institute_name', 'vibe.Sınav');
$systemName = getSetting('system_name', 'vibe.Sınav');

// Fetch all assessments for dropdown selector
$assessments = Database::fetchAll(
    "SELECT a.id, a.title, a.target_class, a.status, a.duration_minutes, a.total_marks, a.passing_percentage,
            sub.name AS subject_name, u.username AS teacher_name, c.name AS campus_name,
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
$gradeFilter = trim($_GET['grade'] ?? '');
$resultFilter = trim($_GET['result_filter'] ?? '');

// Fetch total questions for this assessment
$totalQuestionsCount = 0;
if ($selectedAssessmentId > 0) {
    $totalQuestionsCount = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
        [$selectedAssessmentId]
    );
}

// Query all attempts and results for this assessment
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
              COALESCE(res.total_marks, a.total_marks, 0.00) AS total_marks,
              COALESCE(res.obtained_marks, (SELECT SUM(aa.marks_obtained) FROM assessment_answers aa WHERE aa.attempt_id = att.id), 0.00) AS obtained_marks,
              res.percentage,
              res.pass_fail,
              res.generated_at,
              (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id AND (aa.selected_option_ids IS NOT NULL OR (aa.text_answer IS NOT NULL AND aa.text_answer != ''))) AS answered_count,
              (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id AND aa.is_correct = 1) AS correct_count,
              (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = att.id OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.attempt_id')) = CAST(att.id AS CHAR)) AS violations_count
          FROM assessment_attempts att
          JOIN assessments a ON att.assessment_id = a.id
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

$query .= " ORDER BY obtained_marks DESC, att.student_roll_no ASC";

$rawRecords = Database::fetchAll($query, $params);

// Calculate Letter Grades & Tabulate Records
$records = [];
$rankCounter = 1;
$passThreshold = (float)($selectedAssessment['passing_percentage'] ?? 40.0);

$gradeCounts = [
    'A+' => 0, // >= 90%
    'A'  => 0, // 80 - 89.9%
    'B'  => 0, // 70 - 79.9%
    'C'  => 0, // 60 - 69.9%
    'D'  => 0, // 50 - 59.9%
    'F'  => 0, // < 50%
];

$passedCount = 0;
$failedCount = 0;
$scoresList = [];
$percentagesList = [];
$totalDurationSecs = 0;

foreach ($rawRecords as $r) {
    $maxM = (float)$r['total_marks'];
    $obtM = (float)$r['obtained_marks'];
    
    $pct = ($r['percentage'] !== null) 
        ? (float)$r['percentage'] 
        : (($maxM > 0) ? round(($obtM / $maxM) * 100, 1) : 0.0);

    // Determine letter grade
    $letterGrade = 'F';
    if ($pct >= 90.0) $letterGrade = 'A+';
    elseif ($pct >= 80.0) $letterGrade = 'A';
    elseif ($pct >= 70.0) $letterGrade = 'B';
    elseif ($pct >= 60.0) $letterGrade = 'C';
    elseif ($pct >= 50.0) $letterGrade = 'D';
    else $letterGrade = 'F';

    // Filter by grade if requested
    if ($gradeFilter !== '' && $letterGrade !== $gradeFilter) {
        continue;
    }

    $passFail = $r['pass_fail'] ?? ($pct >= $passThreshold ? 'pass' : 'fail');
    
    // Filter by result if requested
    if ($resultFilter !== '' && $passFail !== $resultFilter) {
        continue;
    }

    $gradeCounts[$letterGrade]++;
    if ($passFail === 'pass') {
        $passedCount++;
    } else {
        $failedCount++;
    }

    $scoresList[] = $obtM;
    $percentagesList[] = $pct;

    // Duration calculation
    $durationFormatted = '—';
    if (!empty($r['started_at'])) {
        $endTs = !empty($r['submitted_at']) ? strtotime($r['submitted_at']) : time();
        $diff = max(0, $endTs - strtotime($r['started_at']));
        $totalDurationSecs += $diff;
        $durationFormatted = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
    }

    $records[] = array_merge($r, [
        'rank'               => $rankCounter++,
        'calculated_pct'     => $pct,
        'letter_grade'       => $letterGrade,
        'final_pass_fail'    => $passFail,
        'duration_formatted' => $durationFormatted,
    ]);
}

$totalAppeared = count($records);
$passRate = $totalAppeared > 0 ? round(($passedCount / $totalAppeared) * 100, 1) : 0.0;
$classAverage = !empty($percentagesList) ? round(array_sum($percentagesList) / count($percentagesList), 1) : 0.0;
$highestScore = !empty($scoresList) ? max($scoresList) : 0.0;
$lowestScore = !empty($scoresList) ? min($scoresList) : 0.0;
$avgDurationMins = $totalAppeared > 0 ? round(($totalDurationSecs / $totalAppeared) / 60, 1) : 0;
?>

<!-- ============================================================= -->
<!-- SCREEN DISPLAY CONTROLS & HEADER                              -->
<!-- ============================================================= -->
<div class="d-print-none">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success px-2.5 py-1.5 fs-7"><i class="fas fa-chart-pie me-1"></i>REPORT CENTER</span>
                <h4 class="mb-0 fw-bold">Assessment Performance & Candidate Merit Ledger</h4>
            </div>
            <p class="text-muted small mb-0">
                Detailed candidate-wise scores, ranking, accuracy, and official institutional performance reports.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Assessment Switcher -->
            <form method="GET" action="<?= url('reports') ?>" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="page" value="reports">
                <select name="assessment_id" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()" style="min-width: 250px;">
                    <?php if (empty($assessments)): ?>
                        <option value="">No Assessments Found</option>
                    <?php else: ?>
                        <?php foreach ($assessments as $ass): ?>
                            <option value="<?= (int)$ass['id'] ?>" <?= ((int)$ass['id'] === $selectedAssessmentId) ? 'selected' : '' ?>>
                                <?= e($ass['title']) ?> (<?= e($ass['target_class']) ?>) [<?= (int)$ass['total_attempts'] ?> records]
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <!-- Download CSV -->
            <a href="<?= url('reports-export') ?>&id=<?= $selectedAssessmentId ?>&format=csv" class="btn btn-outline-success btn-sm" title="Download candidate results spreadsheet (CSV)">
                <i class="fas fa-file-csv me-1"></i> Download CSV
            </a>

            <!-- Download HTML Executive Report Dossier -->
            <a href="<?= url('reports-export') ?>&id=<?= $selectedAssessmentId ?>&format=html" class="btn btn-outline-primary btn-sm" title="Download complete standalone HTML report dossier">
                <i class="fas fa-file-code me-1"></i> Download HTML Dossier
            </a>

            <!-- Print / Save as PDF -->
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()" title="Print or save as PDF">
                <i class="fas fa-print me-1"></i> Print / Save PDF
            </button>

            <!-- Attendance & Monitor Links -->
            <a href="<?= url('attendance&assessment_id=' . $selectedAssessmentId) ?>" class="btn btn-outline-warning btn-sm" title="View Exam Attendance Roster">
                <i class="fas fa-clipboard-user me-1"></i> Attendance
            </a>
        </div>
    </div>

    <!-- Assessment Metadata Header Card -->
    <?php if ($selectedAssessment): ?>
    <div class="card border mb-4 shadow-sm" style="background: var(--bg-surface, #1e222d);">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">
                        <i class="fas fa-graduation-cap me-2"></i><?= e($selectedAssessment['title']) ?>
                    </h5>
                    <div class="text-muted small">
                        Class / Grade: <strong class="text-light"><?= e($selectedAssessment['target_class'] ?? 'General') ?></strong> • 
                        Subject: <strong class="text-light"><?= e($selectedAssessment['subject_name'] ?? 'General') ?></strong> • 
                        Faculty / Invigilator: <strong class="text-light"><?= e($selectedAssessment['teacher_name'] ?? 'Faculty') ?></strong> • 
                        Total Marks: <strong><?= number_format((float)$selectedAssessment['total_marks'], 2) ?></strong> • 
                        Passing Mark: <strong><?= number_format((float)$selectedAssessment['passing_percentage'], 1) ?>%</strong>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark border text-info px-3 py-2">
                        <i class="fas fa-puzzle-piece me-1"></i><?= $totalQuestionsCount ?> Questions
                    </span>
                    <span class="badge <?= ($selectedAssessment['status'] === 'published') ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                        <?= strtoupper($selectedAssessment['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Executive KPI Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Total Candidates</div>
                <div class="fs-2 fw-bold text-primary"><?= $totalAppeared ?></div>
                <div class="text-muted small">Evaluated Exams</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Class Average</div>
                <div class="fs-2 fw-bold text-info"><?= $classAverage ?>%</div>
                <div class="text-muted small">Overall Mean Score</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-4 col-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Overall Pass Rate</div>
                <div class="fs-2 fw-bold <?= $passRate >= 50 ? 'text-success' : 'text-danger' ?>"><?= $passRate ?>%</div>
                <div class="text-muted small"><?= $passedCount ?> Passed • <?= $failedCount ?> Failed</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Score Spectrum</div>
                <div class="fs-2 fw-bold text-warning"><?= $highestScore ?> / <?= $lowestScore ?></div>
                <div class="text-muted small">Highest Mark / Lowest Mark</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6 col-12">
            <div class="card border p-3 text-center shadow-sm h-100">
                <div class="text-muted small text-uppercase fw-bold mb-1">Avg Time Spent</div>
                <div class="fs-2 fw-bold text-light"><?= $avgDurationMins ?>m</div>
                <div class="text-muted small">Per Candidate</div>
            </div>
        </div>
    </div>

    <!-- Grade Spectrum Distribution Bar -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-header border-bottom py-2 fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-chart-column me-2 text-primary"></i>Grade & Performance Distribution</span>
            <span class="small text-muted">Pass Threshold: <?= $passThreshold ?>%</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-2 text-center">
                <?php foreach (['A+' => 'success', 'A' => 'info', 'B' => 'primary', 'C' => 'warning', 'D' => 'secondary', 'F' => 'danger'] as $g => $color): ?>
                <?php 
                    $count = $gradeCounts[$g];
                    $pctBar = $totalAppeared > 0 ? round(($count / $totalAppeared) * 100, 1) : 0;
                ?>
                <div class="col">
                    <div class="p-2 border border-secondary rounded bg-dark">
                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="badge bg-<?= $color ?>"><?= $g ?></span>
                            <span class="font-monospace fw-bold text-light"><?= $count ?></span>
                        </div>
                        <div class="progress bg-secondary" style="height: 6px;">
                            <div class="progress-bar bg-<?= $color ?>" style="width: <?= $pctBar ?>%;"></div>
                        </div>
                        <div class="text-muted mt-1" style="font-size: 0.72rem;"><?= $pctBar ?>% of class</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('reports') ?>" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="reports">
                <input type="hidden" name="assessment_id" value="<?= $selectedAssessmentId ?>">

                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" 
                               placeholder="Search candidate name, roll number, workstation..." value="<?= e($search) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="grade" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Letter Grades</option>
                        <option value="A+" <?= $gradeFilter === 'A+' ? 'selected' : '' ?>>Grade A+ (90% - 100%)</option>
                        <option value="A" <?= $gradeFilter === 'A' ? 'selected' : '' ?>>Grade A (80% - 89%)</option>
                        <option value="B" <?= $gradeFilter === 'B' ? 'selected' : '' ?>>Grade B (70% - 79%)</option>
                        <option value="C" <?= $gradeFilter === 'C' ? 'selected' : '' ?>>Grade C (60% - 69%)</option>
                        <option value="D" <?= $gradeFilter === 'D' ? 'selected' : '' ?>>Grade D (50% - 59%)</option>
                        <option value="F" <?= $gradeFilter === 'F' ? 'selected' : '' ?>>Grade F (Below 50%)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="result_filter" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Statuses</option>
                        <option value="pass" <?= $resultFilter === 'pass' ? 'selected' : '' ?>>Passed Only</option>
                        <option value="fail" <?= $resultFilter === 'fail' ? 'selected' : '' ?>>Failed Only</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $gradeFilter !== '' || $resultFilter !== ''): ?>
                        <a href="<?= url('reports') ?>&assessment_id=<?= $selectedAssessmentId ?>" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- OFFICIAL DOSSIER PRINT HEADER (PRINT ONLY)                   -->
<!-- ============================================================= -->
<div class="d-none d-print-block mb-4 pb-3 border-bottom border-dark text-dark">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h2 class="fw-bold mb-0 text-uppercase"><?= e($instituteName) ?></h2>
            <h5 class="text-secondary mb-0">OFFICIAL ASSESSMENT SCORECARD & MERIT DOSSIER</h5>
        </div>
        <div class="text-end small">
            <div>Report Date: <strong><?= date('F d, Y') ?></strong></div>
            <div>Generated by: <strong><?= e($user['full_name'] ?? 'Administrator') ?></strong></div>
            <div>System: <strong><?= e($systemName) ?></strong></div>
        </div>
    </div>

    <?php if ($selectedAssessment): ?>
    <div class="p-3 border border-dark rounded bg-light mt-2 small">
        <div class="row g-2">
            <div class="col-4"><strong>Assessment Title:</strong> <?= e($selectedAssessment['title']) ?></div>
            <div class="col-4"><strong>Subject:</strong> <?= e($selectedAssessment['subject_name'] ?? 'General') ?></div>
            <div class="col-4"><strong>Class / Grade:</strong> <?= e($selectedAssessment['target_class'] ?? 'General') ?></div>
            <div class="col-4"><strong>Total Candidates:</strong> <?= $totalAppeared ?> Students</div>
            <div class="col-4"><strong>Class Average:</strong> <?= $classAverage ?>%</div>
            <div class="col-4"><strong>Pass Rate:</strong> <?= $passRate ?>% (<?= $passedCount ?> Passed / <?= $failedCount ?> Failed)</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- CANDIDATE-WISE MERIT & SCORE ROSTER TABLE                     -->
<!-- ============================================================= -->
<div class="card border shadow-sm">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center d-print-none">
        <h6 class="mb-0 fw-bold">
            <i class="fas fa-trophy me-2 text-warning"></i>Candidate-Wise Merit & Performance Roster (Ranked by Score)
        </h6>
        <span class="badge bg-secondary font-monospace"><?= count($records) ?> Candidates</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="reportsTable">
            <thead class="text-muted small text-uppercase border-bottom">
                <tr>
                    <th style="width: 60px;">Rank</th>
                    <th style="min-width: 100px;">Workstation</th>
                    <th style="min-width: 110px;">Roll Number</th>
                    <th style="min-width: 170px;">Candidate Name</th>
                    <th style="min-width: 100px;">Class</th>
                    <th style="min-width: 120px;">Questions</th>
                    <th style="min-width: 130px;">Score & Total</th>
                    <th style="min-width: 100px;">Percentage</th>
                    <th style="min-width: 70px;">Grade</th>
                    <th style="min-width: 90px;">Result</th>
                    <th class="d-none d-print-table-cell" style="min-width: 100px;">Signature</th>
                    <th class="text-end d-print-none" style="min-width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="12" class="text-center py-5 text-muted">
                        <i class="fas fa-poll fa-2x mb-2 d-block opacity-50"></i>
                        No candidate results found for this assessment.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <?php 
                        $isPassed = ($r['final_pass_fail'] === 'pass');
                        $gradeBadgeColor = match($r['letter_grade']) {
                            'A+' => 'success',
                            'A'  => 'info',
                            'B'  => 'primary',
                            'C'  => 'warning',
                            'D'  => 'secondary',
                            default => 'danger'
                        };
                        $rankBadge = match($r['rank']) {
                            1 => '<span class="badge bg-warning text-dark"><i class="fas fa-crown me-1"></i>#1</span>',
                            2 => '<span class="badge bg-secondary text-light">#2</span>',
                            3 => '<span class="badge bg-secondary-subtle text-light">#3</span>',
                            default => '<span class="font-monospace small text-muted">#' . $r['rank'] . '</span>'
                        };
                    ?>
                    <tr>
                        <td class="fw-bold"><?= $rankBadge ?></td>
                        <td>
                            <span class="badge bg-secondary font-monospace px-2 py-1 fs-7">
                                <i class="fas fa-desktop me-1 text-info"></i><?= e($r['station_code'] ?? 'Workstation') ?>
                            </span>
                        </td>
                        <td class="font-monospace fw-bold text-info"><?= e($r['student_roll_no']) ?></td>
                        <td>
                            <span class="fw-bold text-light"><?= e($r['student_name']) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-dark border text-light"><?= e($r['student_class'] ?? ($selectedAssessment['target_class'] ?? 'General')) ?></span>
                        </td>
                        <td>
                            <span class="small font-monospace text-muted">
                                <i class="fas fa-check text-success me-1"></i><?= $r['correct_count'] ?> / <?= $totalQuestionsCount ?>
                            </span>
                        </td>
                        <td>
                            <span class="font-monospace fw-bold <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                                <?= number_format((float)$r['obtained_marks'], 2) ?>
                            </span>
                            <span class="small text-muted font-monospace">/ <?= number_format((float)$r['total_marks'], 2) ?></span>
                        </td>
                        <td>
                            <span class="font-monospace fw-bold"><?= $r['calculated_pct'] ?>%</span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $gradeBadgeColor ?>"><?= $r['letter_grade'] ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $isPassed ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                <i class="fas <?= $isPassed ? 'fa-check' : 'fa-times' ?> me-1"></i>
                                <?= strtoupper($r['final_pass_fail']) ?>
                            </span>
                        </td>
                        <!-- Verification column for official print dossier -->
                        <td class="d-none d-print-table-cell text-center" style="border-bottom: 1px dashed #999;">
                            &nbsp;
                        </td>
                        <td class="text-end d-print-none">
                            <a href="<?= url('assessments-candidate-detail&attempt_id=' . $r['attempt_id']) ?>" class="btn btn-outline-info btn-sm" title="View Full Candidate Audit">
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
<!-- OFFICIAL INSTITUTIONAL SIGN-OFF FOOTER (PRINT ONLY)           -->
<!-- ============================================================= -->
<div class="d-none d-print-block mt-5 pt-4 border-top border-dark text-dark">
    <div class="row text-center">
        <div class="col-4">
            <div class="small fw-bold">Evaluator / Teacher</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Signature & Date</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold">Head of Examination</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Signature & Date</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold">Principal / Dean</div>
            <div class="mt-4 border-top border-dark pt-1 small text-muted">Official Seal & Approval</div>
        </div>
    </div>
</div>
