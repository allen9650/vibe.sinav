<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Assessments Index View
 */

$search = trim((string)($_GET['search'] ?? ''));
$subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$status = trim((string)($_GET['status'] ?? ''));

// Handle POST actions: Launch, Stop session & Delete
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $action = $_POST['action'] ?? '';
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);

    if ($action === 'launch_session' || $action === 'toggle_publish') {
        $result = AssessmentService::launchAssessment($assessmentId);
        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        redirectTo('assessments');
    }

    if ($action === 'stop_session') {
        $result = AssessmentService::stopAssessment($assessmentId);
        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        redirectTo('assessments');
    }

    if ($action === 'delete') {
        try {
            $deleted = AssessmentService::deleteAssessment($assessmentId);
            if ($deleted) {
                Session::flash('success', 'Assessment was successfully deleted.');
            } else {
                Session::flash('error', 'Assessment not found or could not be deleted.');
            }
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        redirectTo('assessments');
    }
}

$subjects = QuestionService::getSubjects();

$filters = [
    'search'     => $search ?: null,
    'subject_id' => $subjectId ?: null,
    'status'     => $status ?: null,
];

$assessments = AssessmentService::getAssessments($filters);
$liveAssessment = AssessmentService::getActiveLaunchedAssessment();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1 fw-bold"><i class="fas fa-tasks text-primary me-2"></i>Assessments & Quizzes</h4>
        <p class="text-muted small mb-0">Create, configure, attach questions, launch examinations to computer lab, and review results.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('test-start') ?>" target="_blank" class="btn btn-outline-info btn-sm fw-semibold">
            <i class="fas fa-desktop me-1"></i> Open Student Workstation Screen
        </a>
        <a href="<?= url('assessments-create') ?>" class="btn btn-primary btn-sm fw-bold">
            <i class="fas fa-plus me-1"></i> Create Assessment
        </a>
    </div>
</div>

<!-- Live Assessment Lab Status Banner -->
<?php if ($liveAssessment): ?>
    <div class="card bg-success bg-opacity-10 border border-success mb-4 shadow-sm">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success px-3 py-2 fs-6 fw-bold text-uppercase">
                    <i class="fas fa-satellite-dish me-2"></i> LIVE SESSION ACTIVE
                </span>
                <div>
                    <h5 class="fw-bold mb-0"><?= e($liveAssessment['title']) ?></h5>
                    <div class="small text-muted">
                        Subject: <span class="text-info fw-semibold"><?= e($liveAssessment['subject_name']) ?></span> | 
                        Class: <span class="fw-semibold"><?= e($liveAssessment['target_class']) ?></span> |
                        Duration: <span class="text-warning fw-semibold"><?= (int)$liveAssessment['duration_minutes'] ?> Mins</span> |
                        Questions: <span class="fw-semibold"><?= (int)$liveAssessment['questions_count'] ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= url('test-start&id=' . $liveAssessment['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i> View as Student
                </a>
                <form method="POST" action="<?= url('assessments') ?>" class="d-inline">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="stop_session">
                    <input type="hidden" name="assessment_id" value="<?= $liveAssessment['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3">
                        <i class="fas fa-stop me-1"></i> Stop Exam Session
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-secondary bg-opacity-25 text-info">
                    <i class="fas fa-desktop fa-2x"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6">Lab Workstations are in Waiting State ("Workstation Terminal Ready")</div>
                    <div class="small text-muted">No examination is currently launched for the lab. Click <strong>Launch Exam</strong> on any assessment below to open it to all workstations. <em>(Only 1 exam runs at a time)</em></div>
                </div>
            </div>
            <a href="<?= url('test-start') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-eye me-1"></i> Check Terminal Screen
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Filters Bar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('assessments') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="assessments">

            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search by title, target class/grade..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published / Live</option>
                </select>
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-secondary btn-sm w-100" title="Apply Filter"><i class="fas fa-filter"></i></button>
                <?php if ($search || $subjectId || $status): ?>
                    <a href="<?= url('assessments') ?>" class="btn btn-outline-danger btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Assessments Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="text-muted small text-uppercase">
                <tr>
                    <th>Assessment Title</th>
                    <th>Subject & Class</th>
                    <th>Duration & Marks</th>
                    <th>Questions</th>
                    <th>Allocated Devices</th>
                    <th>Lab Session Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assessments)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary d-block"></i>
                        No assessments found matching your filter.<br>
                        <a href="<?= url('assessments-create') ?>" class="btn btn-primary btn-sm mt-3"><i class="fas fa-plus me-1"></i> Create First Assessment</a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($assessments as $a): ?>
                    <?php $isLive = ($a['status'] === 'published'); ?>
                    <tr class="<?= $isLive ? 'table-success bg-opacity-10' : '' ?>">
                        <td>
                            <div class="fw-bold mb-1 d-flex align-items-center flex-wrap gap-1">
                                <a href="<?= url('assessments-edit&id=' . $a['id']) ?>" class="text-decoration-none" style="color: var(--text-primary);" title="Click to edit assessment settings">
                                    <?= e($a['title']) ?>
                                </a>
                                <?php if ($isLive): ?>
                                    <span class="badge bg-success ms-1 small">LIVE NOW</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted font-monospace">
                                <span class="badge bg-secondary-subtle border">ID #<?= (int)$a['id'] ?></span>
                                <span class="ms-1 text-info"><i class="fas fa-user-tie me-1"></i><?= e(!empty($a['teacher_full_name']) ? $a['teacher_full_name'] : ($a['creator_username'] ?? 'Faculty')) ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold mb-1"><i class="fas fa-book me-1 text-primary"></i><?= e($a['subject_name']) ?></div>
                            <div class="small text-muted"><i class="fas fa-graduation-cap me-1 text-warning"></i><?= e($a['target_class'] ?? 'General') ?><?= !empty($a['section']) ? ' (' . e($a['section']) . ')' : '' ?></div>
                        </td>
                        <td>
                            <div class="small"><i class="fas fa-clock me-1 text-info"></i><?= (int)$a['duration_minutes'] ?> mins</div>
                            <div class="small text-success fw-bold"><i class="fas fa-star me-1"></i><?= number_format((float)$a['total_marks'], 2) ?> marks</div>
                            <div class="small text-muted">Pass: <?= number_format((float)$a['passing_percentage'], 1) ?>%</div>
                        </td>
                        <td>
                            <a href="<?= url('assessments-builder&id=' . $a['id']) ?>" class="badge bg-dark border border-secondary text-info text-decoration-none px-2 py-1" title="Manage questions in builder">
                                <i class="fas fa-puzzle-piece me-1"></i><?= (int)$a['questions_count'] ?> questions
                            </a>
                        </td>
                        <td>
                            <a href="<?= url('assessments-devices&id=' . $a['id']) ?>" class="badge bg-dark border border-secondary text-light text-decoration-none px-2 py-1" title="Manage lab stations">
                                <i class="fas fa-desktop me-1"></i><?= (int)$a['allocated_stations'] ?> PCs
                            </a>
                        </td>
                        <td>
                            <?php if ($isLive): ?>
                                <div class="d-flex flex-column gap-1" style="min-width: 135px;">
                                    <span class="badge bg-success px-2 py-1"><i class="fas fa-satellite-dish me-1"></i> LIVE IN LAB</span>
                                    <form method="POST" action="<?= url('assessments') ?>" class="mt-1">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="stop_session">
                                        <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold py-1" title="Stop this exam session">
                                            <i class="fas fa-stop me-1"></i> Stop Session
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-1" style="min-width: 135px;">
                                    <span class="badge bg-secondary px-2 py-1 text-muted">Draft / Idle</span>
                                    <form method="POST" action="<?= url('assessments') ?>" class="mt-1">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="launch_session">
                                        <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-1" title="Launch this exam to all lab computers">
                                            <i class="fas fa-rocket me-1"></i> Launch Exam
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if (Auth::hasPermission('assessments.edit') || (int)($a['created_by'] ?? 0) === (int)Auth::id() || Auth::isAdmin()): ?>
                                <a href="<?= url('assessments-edit&id=' . $a['id']) ?>" class="btn btn-outline-warning" title="Edit Assessment Settings">
                                    <i class="fas fa-pencil-alt me-1"></i> Edit
                                </a>
                                <?php endif; ?>
                                <a href="<?= url('assessments-builder&id=' . $a['id']) ?>" class="btn btn-primary" title="Question Builder">
                                    <i class="fas fa-puzzle-piece me-1"></i> Builder
                                </a>
                                <a href="<?= url('assessments-devices&id=' . $a['id']) ?>" class="btn btn-outline-info" title="Allocate Lab Devices">
                                    <i class="fas fa-desktop"></i>
                                </a>
                                <a href="<?= url('monitoring&id=' . $a['id']) ?>" class="btn btn-outline-danger" title="Live Telemetry & Proctor Monitor">
                                    <i class="fas fa-satellite-dish"></i>
                                </a>
                                <a href="<?= url('assessments-results&id=' . $a['id']) ?>" class="btn btn-outline-success" title="Results & Reports">
                                    <i class="fas fa-chart-column"></i>
                                </a>

                                <?php if (Auth::hasPermission('assessments.delete') || (int)($a['created_by'] ?? 0) === (int)Auth::id() || Auth::isSuperAdmin()): ?>
                                <form method="POST" action="<?= url('assessments') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this assessment? All associated attempts and records will be deleted.');">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete Assessment">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
