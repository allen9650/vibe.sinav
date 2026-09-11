<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Teacher Results & Reports View
 * Real-time results roster, dynamic class average, AJAX result publication toggle, and CSV streaming.
 */

$assessmentId = (int)($_GET['id'] ?? 0);
if ($assessmentId <= 0) {
    Session::flash('error', 'Valid assessment ID is required.');
    redirectTo('assessments');
}

$assessment = AssessmentService::getAssessment($assessmentId);
if (!$assessment) {
    Session::flash('error', 'Assessment not found.');
    redirectTo('assessments');
}

// Handle POST actions: Reset Attempt / Delete Result
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_result' || $action === 'reset_attempt') {
        if (!Auth::hasPermission('results.delete')) {
            Session::flash('error', 'Permission denied: You do not have permission to delete or reset candidate results.');
            redirectTo('assessments-results&id=' . $assessmentId);
        }
        
        $attemptId = (int)($_POST['attempt_id'] ?? 0);
        if ($attemptId > 0) {
            Database::delete('assessment_results', 'attempt_id = ?', [$attemptId]);
            Database::delete('assessment_answers', 'attempt_id = ?', [$attemptId]);
            Database::delete('security_events', 'attempt_id = ?', [$attemptId]);
            Database::delete('assessment_attempts', 'id = ?', [$attemptId]);
            
            if (class_exists('AuditLog')) {
                AuditLog::log('result_deleted', 'assessment_results', "Deleted/Reset attempt #{$attemptId} for assessment #{$assessmentId}", Auth::id() ?: 1);
            }
            Session::flash('success', "Candidate attempt #{$attemptId} has been deleted and wiped. Station is now clear for re-attempt.");
            redirectTo('assessments-results&id=' . $assessmentId);
        }
    }
}

// Fetch all allocated stations and student results
$roster = Database::fetchAll(
    "SELECT 
         s.station_code,
         s.ip_address,
         ast.status AS station_status,
         att.id AS attempt_id,
         att.student_name,
         att.student_roll_no,
         att.student_class,
         att.started_at,
         att.submitted_at,
         att.status AS attempt_status,
         att.needs_review,
         att.is_reviewed,
         att.reviewed_by,
         att.reviewed_at,
         res.total_marks,
         res.obtained_marks,
         res.percentage,
         res.pass_fail,
         (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id AND (aa.selected_option_ids IS NOT NULL OR (aa.text_answer IS NOT NULL AND aa.text_answer != ''))) AS answered_count,
         (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = att.id) AS violations_count
     FROM assessment_stations ast
     JOIN lab_stations s ON ast.station_id = s.id
     LEFT JOIN assessment_attempts att 
       ON ast.assessment_id = att.assessment_id AND ast.station_id = att.station_id
     LEFT JOIN assessment_results res ON att.id = res.attempt_id
     WHERE ast.assessment_id = ?
     ORDER BY s.station_code ASC",
    [$assessmentId]
);

// Calculate Summary Statistics
$totalAllocated = count($roster);
$attendanceCount = 0;
$submittedCount = 0;
$pendingReviewCount = 0;
$passedCount = 0;
$failedCount = 0;
$sumPercentage = 0.00;

foreach ($roster as $r) {
    if (!empty($r['attempt_id'])) {
        $attendanceCount++;
        if ($r['attempt_status'] === 'completed' || $r['attempt_status'] === 'timed_out') {
            $submittedCount++;
            if ((int)($r['needs_review'] ?? 0) === 1 && (int)($r['is_reviewed'] ?? 0) === 0) {
                $pendingReviewCount++;
            }
            $pct = (float)($r['percentage'] ?? 0);
            $sumPercentage += $pct;
            if ($r['pass_fail'] === 'pass') {
                $passedCount++;
            } else {
                $failedCount++;
            }
        }
    }
}

$classAverage = ($submittedCount > 0) ? round($sumPercentage / $submittedCount, 2) : 0.00;
$isPublished = ((int)$assessment['is_result_published'] === 1);
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb & Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('assessments') ?>" class="text-decoration-none text-muted">Assessments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Results & Reports</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0">
                <i class="fas fa-square-poll-vertical text-success me-2"></i> Results & Performance Ledger
            </h3>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('assessments-devices&id=' . $assessmentId) ?>" class="btn btn-outline-info">
                <i class="fas fa-desktop me-1"></i> Manage Devices
            </a>
            <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Assessment Overview & Summary Header -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-center mb-4">
                <div class="col-lg-6">
                    <h4 class="fw-bold mb-1"><?= e($assessment['title']) ?></h4>
                    <div class="text-muted small">
                        Subject: <span class="text-info fw-semibold"><?= e($assessment['subject_name']) ?></span> | 
                        Target Class: <span class="fw-semibold"><?= e($assessment['target_class']) ?></span> | 
                        Campus: <span class="text-muted"><?= e($assessment['campus_name']) ?></span>
                    </div>
                </div>
                <!-- Action Bar -->
                <div class="col-lg-6 text-lg-end d-flex flex-wrap justify-content-lg-end gap-2">
                    <!-- Toggle Publish Button -->
                    <button type="button" class="btn <?= $isPublished ? 'btn-warning' : 'btn-success' ?> fw-bold" 
                            id="btnTogglePublish" onclick="toggleResultPublication(<?= $assessmentId ?>)">
                        <i class="fas <?= $isPublished ? 'fa-lock' : 'fa-bullhorn' ?> me-1"></i>
                        <span id="btnTogglePublishText"><?= $isPublished ? 'Withhold Results from Students' : 'Release Results to Students' ?></span>
                    </button>

                    <!-- Download CSV Report Button -->
                    <a href="<?= url('assessments-export&id=' . $assessmentId) ?>" class="btn btn-primary fw-semibold">
                        <i class="fas fa-file-csv me-1"></i> Download CSV Report
                    </a>

                    <!-- Real-time Refresh Button -->
                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()" title="Refresh Roster">
                        <i class="fas fa-rotate"></i>
                    </button>
                </div>
            </div>

            <!-- Metric KPI Summary Cards -->
            <div class="row g-3">
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="p-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded text-center">
                        <div class="text-muted small text-uppercase fw-bold">Attendance</div>
                        <div class="h4 fw-bold mb-0 font-monospace">
                            <?= $attendanceCount ?> <span class="fs-6 text-muted">/ <?= $totalAllocated ?></span>
                        </div>
                        <div class="small text-muted" style="font-size: 0.75rem;"><?= $totalAllocated - $attendanceCount ?> Idle Stations</div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-4 col-6">
                    <div class="p-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded text-center">
                        <div class="text-muted small text-uppercase fw-bold">Submitted</div>
                        <div class="h4 fw-bold text-info mb-0 font-monospace">
                            <?= $submittedCount ?>
                        </div>
                        <div class="small text-muted" style="font-size: 0.75rem;"><?= $attendanceCount - $submittedCount ?> In Progress</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-6">
                    <div class="p-3 bg-secondary bg-opacity-10 border <?= $pendingReviewCount > 0 ? 'border-warning' : 'border-secondary border-opacity-25' ?> rounded text-center">
                        <div class="text-muted small text-uppercase fw-bold">Pending Reviews</div>
                        <div class="h4 fw-bold <?= $pendingReviewCount > 0 ? 'text-warning' : 'text-muted' ?> mb-0 font-monospace">
                            <?= $pendingReviewCount ?> <span class="fs-6 text-muted">attempt(s)</span>
                        </div>
                        <div class="small <?= $pendingReviewCount > 0 ? 'text-warning' : 'text-muted' ?>" style="font-size: 0.75rem;">
                            <?= $pendingReviewCount > 0 ? 'Subjective answers awaiting grading' : 'All reviews finalized' ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 col-6">
                    <div class="p-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded text-center">
                        <div class="text-muted small text-uppercase fw-bold">Class Avg</div>
                        <div class="h4 fw-bold text-warning mb-0 font-monospace">
                            <?= number_format($classAverage, 2) ?>%
                        </div>
                        <div class="small text-muted" style="font-size: 0.75rem;">Benchmark: <?= (float)$assessment['passing_percentage'] ?>%</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="p-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded text-center">
                        <div class="text-muted small text-uppercase fw-bold">Pass Rate</div>
                        <div class="h4 fw-bold text-success mb-0 font-monospace">
                            <?= $submittedCount > 0 ? round(($passedCount / $submittedCount) * 100, 1) : 0 ?>%
                        </div>
                        <div class="small text-muted" style="font-size: 0.75rem;"><?= $passedCount ?> Passed • <?= $failedCount ?> Failed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabular Candidate Roster -->
    <div class="card border shadow-sm">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold mb-0">
                <i class="fas fa-list-numeric text-primary me-2"></i> Workstation Candidate Ledger
            </h5>
            <span class="badge bg-secondary font-monospace">
                <?= count($roster) ?> Workstations Monitored
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-secondary text-uppercase small" style="letter-spacing: 0.05em;">
                        <tr>
                            <th style="width: 100px;">Station</th>
                            <th style="width: 130px;">Roll No</th>
                            <th>Student Full Name</th>
                            <th style="width: 100px;" class="text-center">Answered</th>
                            <th style="width: 90px;" class="text-end">Total</th>
                            <th style="width: 90px;" class="text-end">Obtained</th>
                            <th style="width: 90px;" class="text-end">Score %</th>
                            <th style="width: 120px;" class="text-center">Integrity</th>
                            <th style="width: 220px;" class="text-center">Evaluation / Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roster)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-desktop fa-2x mb-3 d-block text-secondary"></i>
                                    No workstations allocated yet. Use the <a href="<?= url('assessments-devices&id=' . $assessmentId) ?>" class="text-info">Device Allocation</a> tab to open stations.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roster as $row): ?>
                                <?php
                                $isAttended = !empty($row['attempt_id']);
                                $isDone = in_array($row['attempt_status'] ?? '', ['completed', 'timed_out'], true);
                                $isPass = ($row['pass_fail'] ?? '') === 'pass';
                                $needsReview = $isDone && ((int)($row['needs_review'] ?? 0) === 1 && (int)($row['is_reviewed'] ?? 0) === 0);
                                $isReviewed = $isDone && ((int)($row['needs_review'] ?? 0) === 1 && (int)($row['is_reviewed'] ?? 0) === 1);
                                $violations = (int)($row['violations_count'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-50 border border-secondary font-monospace text-info px-2 py-1">
                                            <?= e($row['station_code']) ?>
                                        </span>
                                    </td>
                                    <td class="font-monospace">
                                        <?= $isAttended ? e($row['student_roll_no']) : '<span class="text-muted small">UNATTENDED</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($isAttended): ?>
                                            <div class="fw-bold"><?= e($row['student_name']) ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                IP: <?= e($row['ip_address'] ?? 'DHCP') ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Station ready / waiting for student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <?php if ($isAttended): ?>
                                            <span class="badge bg-secondary">
                                                <?= (int)$row['answered_count'] ?> Qs
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        <?= isset($row['total_marks']) ? number_format((float)$row['total_marks'], 2) : '—' ?>
                                    </td>
                                    <td class="text-end font-monospace fw-bold <?= $isDone ? ($needsReview ? 'text-warning' : ($isPass ? 'text-success' : 'text-danger')) : 'text-muted' ?>">
                                        <?= isset($row['obtained_marks']) ? number_format((float)$row['obtained_marks'], 2) : '—' ?>
                                    </td>
                                    <td class="text-end font-monospace fw-bold <?= $isDone ? ($needsReview ? 'text-warning' : ($isPass ? 'text-success' : 'text-danger')) : 'text-muted' ?>">
                                        <?= isset($row['percentage']) ? number_format((float)$row['percentage'], 2) . '%' : '—' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($violations > 0): ?>
                                            <a href="<?= url('assessments-candidate-detail&attempt_id=' . $row['attempt_id']) ?>#proctoringSection" class="badge bg-danger bg-opacity-15 text-danger border border-danger text-decoration-none px-2 py-1" title="Security & Proctoring Violations Logged">
                                                <i class="fas fa-triangle-exclamation me-1"></i><?= $violations ?> Logged
                                            </a>
                                        <?php elseif ($isAttended): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 px-2 py-1">
                                                <i class="fas fa-shield-check me-1"></i>Clean
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!$isAttended): ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-muted border border-secondary border-opacity-25">
                                                IDLE
                                            </span>
                                        <?php elseif (!$isDone): ?>
                                            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary">
                                                <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span> IN PROGRESS
                                            </span>
                                        <?php elseif ($needsReview): ?>
                                            <a href="<?= url('assessments-candidate-detail&attempt_id=' . $row['attempt_id']) ?>" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                                                <i class="fas fa-clipboard-check"></i> Pending Review (Click Review)
                                            </a>
                                        <?php elseif ($isReviewed): ?>
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <span class="badge bg-<?= $isPass ? 'success' : 'danger' ?>-subtle text-<?= $isPass ? 'success' : 'danger' ?> border border-<?= $isPass ? 'success' : 'danger' ?> px-2 py-1">
                                                    <?= $isPass ? '<i class="fas fa-check me-1"></i>PASS' : '<i class="fas fa-xmark me-1"></i>FAIL' ?>
                                                </span>
                                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1"><i class="fas fa-check-double me-1"></i>Reviewed</span>
                                                <a href="<?= url('assessments-candidate-detail&attempt_id=' . $row['attempt_id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Response Audit / Re-grade"><i class="fas fa-eye"></i></a>
                                                <?php if (Auth::hasPermission('results.delete')): ?>
                                                    <form method="POST" action="" class="d-inline ms-1" onsubmit="return confirm('Wipe and reset this candidate result? The workstation will be freed for a re-attempt.');">
                                                        <?= CSRF::field() ?>
                                                        <input type="hidden" name="action" value="delete_result">
                                                        <input type="hidden" name="attempt_id" value="<?= (int)$row['attempt_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete / Reset Result">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <span class="badge bg-<?= $isPass ? 'success' : 'danger' ?>-subtle text-<?= $isPass ? 'success' : 'danger' ?> border border-<?= $isPass ? 'success' : 'danger' ?> px-2 py-1">
                                                    <?= $isPass ? '<i class="fas fa-check me-1"></i>PASS' : '<i class="fas fa-xmark me-1"></i>FAIL' ?>
                                                </span>
                                                <a href="<?= url('assessments-candidate-detail&attempt_id=' . $row['attempt_id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Response Audit"><i class="fas fa-eye"></i></a>
                                                <?php if (Auth::hasPermission('results.delete')): ?>
                                                    <form method="POST" action="" class="d-inline ms-1" onsubmit="return confirm('Wipe and reset this candidate result? The workstation will be freed for a re-attempt.');">
                                                        <?= CSRF::field() ?>
                                                        <input type="hidden" name="action" value="delete_result">
                                                        <input type="hidden" name="attempt_id" value="<?= (int)$row['attempt_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete / Reset Result">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleResultPublication(assessmentId) {
    const btn = document.getElementById('btnTogglePublish');
    const textSpan = document.getElementById('btnTogglePublishText');
    if (!btn) return;

    btn.disabled = true;

    fetch('<?= url("api-toggle-publish") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            assessment_id: assessmentId,
            csrf_token: '<?= CSRF::token() ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            if (data.is_published) {
                btn.className = 'btn btn-warning fw-bold';
                btn.innerHTML = '<i class="fas fa-lock me-1"></i> <span id="btnTogglePublishText">Withhold Results from Students</span>';
            } else {
                btn.className = 'btn btn-success fw-bold';
                btn.innerHTML = '<i class="fas fa-bullhorn me-1"></i> <span id="btnTogglePublishText">Release Results to Students</span>';
            }
        } else {
            alert('Failed to update result publication status: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error('Toggle error:', err);
        alert('Network error while toggling results.');
    });
}
</script>
