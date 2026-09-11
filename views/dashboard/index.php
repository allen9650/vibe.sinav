<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Master Administrative Dashboard
 * Live metrics, workstation readiness, and active assessment telemetry.
 */

$user = Auth::user();

// If a teacher landed on system dashboard, forward seamlessly to teacher dashboard
if (Auth::isTeacher()) {
    redirect(url('teacher-dashboard'));
}

// Fetch live metrics from clean database schema
$totalAssessments = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessments");
$totalStations = (int)Database::fetchColumn("SELECT COUNT(*) FROM lab_stations WHERE status = 'active'");
$totalQuestions = (int)Database::fetchColumn("SELECT COUNT(*) FROM questions");
$activeAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessment_attempts WHERE status = 'in_progress'");
$completedAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessment_attempts WHERE status = 'completed'");

// Tabulated Scorecard Metrics
$avgPercentage = (float)(Database::fetchColumn("SELECT ROUND(AVG(percentage), 2) FROM assessment_results") ?: 0.00);
$passedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessment_results WHERE pass_fail = 'pass'");
$failedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessment_results WHERE pass_fail = 'fail'");

// Recent assessments
$recentAssessments = Database::fetchAll(
    "SELECT a.*, s.name AS subject_name, c.name AS campus_name,
            (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.id) AS question_count,
            (SELECT COUNT(*) FROM assessment_stations ast WHERE ast.assessment_id = a.id) AS allocated_stations
     FROM assessments a
     JOIN subjects s ON a.subject_id = s.id
     JOIN campuses c ON a.campus_id = c.id
     ORDER BY a.id DESC LIMIT 5"
);

// Recent audit logs
$recentLogs = AuditLog::getLogs([], 5);
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="page-title fw-bold text-light mb-1">
                <i class="fas fa-gauge-high text-primary me-2"></i> Assessment Command Center
            </h2>
            <p class="page-subtitle text-muted mb-0">
                Welcome back, <strong><?= e($user['full_name']) ?></strong>
                <span class="badge bg-primary ms-2"><?= e($user['role_name']) ?></span>
                <span class="badge bg-dark border border-secondary text-info ms-1"><?= e($user['campus_name'] ?? 'Main Campus') ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('assessments-create') ?>" class="btn btn-primary fw-bold">
                <i class="fas fa-plus me-1"></i> New Assessment
            </a>
            <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> All Assessments
            </a>
        </div>
    </div>
</div>

<!-- Key Metric Cards -->
<div class="row g-4 mb-4">
    <!-- Total Assessments -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-dark border-secondary h-100 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-primary bg-opacity-20 text-primary fs-3">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-light mb-0 font-monospace"><?= $totalAssessments ?></h3>
                    <span class="text-muted small text-uppercase fw-semibold">Total Assessments</span>
                </div>
            </div>
            <div class="card-footer bg-secondary bg-opacity-10 border-secondary py-2">
                <a href="<?= url('assessments') ?>" class="text-decoration-none small text-info d-flex justify-content-between align-items-center">
                    <span>Manage Assessments</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Active Lab Stations -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-dark border-secondary h-100 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-info bg-opacity-20 text-info fs-3">
                    <i class="fas fa-desktop"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-light mb-0 font-monospace"><?= $totalStations ?></h3>
                    <span class="text-muted small text-uppercase fw-semibold">Lab Workstations</span>
                </div>
            </div>
            <div class="card-footer bg-secondary bg-opacity-10 border-secondary py-2">
                <span class="small text-muted">All terminals online & ready</span>
            </div>
        </div>
    </div>

    <!-- Live Exam Sessions -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-dark border-secondary h-100 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-warning bg-opacity-20 text-warning fs-3">
                    <i class="fas fa-spinner <?= $activeAttempts > 0 ? 'fa-pulse' : '' ?>"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-warning mb-0 font-monospace"><?= $activeAttempts ?></h3>
                    <span class="text-muted small text-uppercase fw-semibold">Active Sessions</span>
                </div>
            </div>
            <div class="card-footer bg-secondary bg-opacity-10 border-secondary py-2">
                <span class="small text-muted"><?= $completedAttempts ?> tests completed</span>
            </div>
        </div>
    </div>

    <!-- Question Bank Count -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-dark border-secondary h-100 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-success bg-opacity-20 text-success fs-3">
                    <i class="fas fa-database"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-light mb-0 font-monospace"><?= $totalQuestions ?></h3>
                    <span class="text-muted small text-uppercase fw-semibold">Question Bank</span>
                </div>
            </div>
            <div class="card-footer bg-secondary bg-opacity-10 border-secondary py-2">
                <a href="<?= url('questions') ?>" class="text-decoration-none small text-success d-flex justify-content-between align-items-center">
                    <span>Manage Questions</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Performance KPIs Strip -->
<div class="card bg-dark border-secondary mb-4 shadow-sm">
    <div class="card-body py-3">
        <div class="row g-3 text-center align-items-center">
            <div class="col-6 col-md-3 border-end border-secondary">
                <span class="text-muted small d-block text-uppercase fw-bold">Average Exam Score</span>
                <strong class="fs-3 text-warning font-monospace"><?= number_format($avgPercentage, 2) ?>%</strong>
            </div>
            <div class="col-6 col-md-3 border-end border-secondary">
                <span class="text-muted small d-block text-uppercase fw-bold">Evaluated Submissions</span>
                <strong class="fs-3 text-info font-monospace"><?= $completedAttempts ?></strong>
            </div>
            <div class="col-6 col-md-3 border-end border-secondary">
                <span class="text-muted small d-block text-uppercase fw-bold">Candidates Passed</span>
                <strong class="fs-3 text-success font-monospace"><?= $passedCount ?></strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted small d-block text-uppercase fw-bold">Candidates Failed</span>
                <strong class="fs-3 text-danger font-monospace"><?= $failedCount ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Recent Assessments Table & Audit Feed -->
<div class="row g-4">
    <!-- Active Assessments -->
    <div class="col-lg-8">
        <div class="card bg-dark border-secondary shadow-sm">
            <div class="card-header bg-secondary bg-opacity-10 border-secondary d-flex justify-content-between align-items-center">
                <h5 class="card-title text-light fw-bold mb-0">
                    <i class="fas fa-clipboard-list text-primary me-2"></i> Recent Assessments
                </h5>
                <a href="<?= url('assessments') ?>" class="small text-info text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead class="table-secondary text-uppercase small">
                            <tr>
                                <th>Assessment Title</th>
                                <th>Class</th>
                                <th>Questions</th>
                                <th>Devices</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAssessments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No assessments created yet. Click "New Assessment" to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAssessments as $ass): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-light"><?= e($ass['title']) ?></div>
                                            <div class="small text-muted"><?= e($ass['subject_name']) ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= e($ass['target_class']) ?></span></td>
                                        <td class="font-monospace"><?= (int)$ass['question_count'] ?> Qs</td>
                                        <td class="font-monospace text-info"><?= (int)$ass['allocated_stations'] ?> PCs</td>
                                        <td>
                                            <?php if ($ass['status'] === 'published'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success">PUBLISHED</span>
                                            <?php elseif ($ass['status'] === 'draft'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning">DRAFT</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">ARCHIVED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('assessments-devices&id=' . $ass['id']) ?>" class="btn btn-outline-info btn-sm" title="Allocate Devices">
                                                <i class="fas fa-desktop"></i>
                                            </a>
                                            <a href="<?= url('assessments-results&id=' . $ass['id']) ?>" class="btn btn-outline-success btn-sm" title="View Results">
                                                <i class="fas fa-chart-column"></i>
                                            </a>
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

    <!-- Quick Info & System Readiness -->
    <div class="col-lg-4">
        <div class="card bg-dark border-secondary shadow-sm mb-4">
            <div class="card-header bg-secondary bg-opacity-10 border-secondary">
                <h5 class="card-title text-light fw-bold mb-0">
                    <i class="fas fa-shield-halved text-success me-2"></i> Lab Status
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small">
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Central Server Mode:</span>
                        <span class="badge bg-success-subtle text-success border border-success">Offline LAN Mode</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">External CDNs:</span>
                        <span class="badge bg-info-subtle text-info border border-info">0 (100% Local Assets)</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Autoloading & Core:</span>
                        <span class="badge bg-success">Active & Healthy</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Database Engine:</span>
                        <span class="text-light font-monospace">MySQL / MariaDB (InnoDB)</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Recent Audit Trail -->
        <div class="card bg-dark border-secondary shadow-sm">
            <div class="card-header bg-secondary bg-opacity-10 border-secondary">
                <h6 class="card-title text-light fw-bold mb-0">
                    <i class="fas fa-clock-rotate-left text-warning me-2"></i> Recent Security Audit
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush bg-transparent">
                    <?php if (empty($recentLogs)): ?>
                        <div class="p-3 text-center text-muted small">No audit activity recorded.</div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="list-group-item bg-transparent text-light border-secondary small">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-info"><?= e($log['action']) ?></strong>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= e(date('H:i:s', strtotime($log['created_at']))) ?></span>
                                </div>
                                <div class="text-muted text-truncate" style="max-width: 260px;"><?= e($log['description'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
