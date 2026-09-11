<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Teacher Dashboard
 * Lightweight, fast-loading, zero heavy charts, optimized for LAN.
 */

$teacherId = (int)Auth::id();
$campusId = (int)Auth::campusId();

// Compute Teacher Stats using actual database schema columns
$myAssessmentsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessments WHERE created_by = ? AND status != 'archived'",
    [$teacherId]
);

$publishedCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessments WHERE created_by = ? AND status = 'published'",
    [$teacherId]
);

$draftCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessments WHERE created_by = ? AND status = 'draft'",
    [$teacherId]
);

$questionsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM questions WHERE created_by = ?",
    [$teacherId]
);

$totalQuestionsInBank = (int)Database::fetchColumn("SELECT COUNT(*) FROM questions");

$totalAttempts = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessment_attempts att 
     JOIN assessments a ON att.assessment_id = a.id 
     WHERE a.created_by = ?",
    [$teacherId]
);

// Fetch recent assessments: either created by this teacher, or recent campus assessments if teacher hasn't created any yet
$recentAssessments = Database::fetchAll(
    "SELECT a.*, 
            s.name as subject_name,
            c.name as campus_name,
            (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.id) as questions_count,
            (SELECT COUNT(*) FROM assessment_attempts att WHERE att.assessment_id = a.id) as attempts_count,
            (SELECT ROUND(AVG(ar.percentage), 1) FROM assessment_results ar JOIN assessment_attempts att_sub ON ar.attempt_id = att_sub.id WHERE att_sub.assessment_id = a.id) as avg_percentage
     FROM assessments a
     JOIN subjects s ON a.subject_id = s.id
     JOIN campuses c ON a.campus_id = c.id
     WHERE (a.created_by = ? OR a.campus_id = ?) AND a.status != 'archived'
     ORDER BY (a.created_by = ?) DESC, a.id DESC LIMIT 8",
    [$teacherId, $campusId, $teacherId]
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>Teacher Dashboard</h4>
        <p class="text-muted small mb-0">
            Welcome, <strong><?= e(Auth::user()['full_name'] ?? 'Teacher') ?></strong> • 
            <span class="text-info"><?= e(Auth::campusName() ?: getSetting('default_campus_name', 'Main Campus')) ?></span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::hasPermission('assessments.create')): ?>
            <a href="<?= url('assessments-create') ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Create Assessment
            </a>
        <?php endif; ?>
        <?php if (Auth::hasPermission('questions.create')): ?>
            <a href="<?= url('questions-create') ?>" class="btn btn-outline-info btn-sm">
                <i class="fas fa-plus me-1"></i> Add Question
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="card border p-3 text-center shadow-sm">
            <div class="text-muted small text-uppercase fw-bold mb-1">My Assessments</div>
            <div class="fs-2 fw-bold"><?= $myAssessmentsCount ?></div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border p-3 text-center shadow-sm">
            <div class="text-muted small text-uppercase fw-bold mb-1">Published</div>
            <div class="fs-2 fw-bold text-success"><?= $publishedCount ?></div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border p-3 text-center shadow-sm">
            <div class="text-muted small text-uppercase fw-bold mb-1">Drafts</div>
            <div class="fs-2 fw-bold text-warning"><?= $draftCount ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border p-3 text-center shadow-sm">
            <div class="text-muted small text-uppercase fw-bold mb-1">Questions in Bank</div>
            <div class="fs-2 fw-bold text-info"><?= $totalQuestionsInBank ?></div>
            <div class="text-muted" style="font-size: 0.72rem;"><?= $questionsCount ?> authored by you</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border p-3 text-center shadow-sm">
            <div class="text-muted small text-uppercase fw-bold mb-1">Total Attempts</div>
            <div class="fs-2 fw-bold text-primary"><?= $totalAttempts ?></div>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div class="row g-3 mb-4">
    <?php if (Auth::hasPermission('assessments.create')): ?>
    <div class="col-md-3 col-6">
        <a href="<?= url('assessments-create') ?>" class="card border p-3 text-decoration-none hover-card d-flex align-items-center gap-3 shadow-sm h-100">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded"><i class="fas fa-plus-circle fa-lg"></i></div>
            <div>
                <div class="fw-bold text-light">Create Assessment</div>
                <div class="small text-muted">Configure new quiz or test</div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (Auth::hasPermission('questions.create')): ?>
    <div class="col-md-3 col-6">
        <a href="<?= url('questions-create') ?>" class="card border p-3 text-decoration-none hover-card d-flex align-items-center gap-3 shadow-sm h-100">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded"><i class="fas fa-pen-fancy fa-lg"></i></div>
            <div>
                <div class="fw-bold text-light">Add Question</div>
                <div class="small text-muted">Add to global question bank</div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <div class="col-md-3 col-6">
        <a href="<?= url('questions') ?>" class="card border p-3 text-decoration-none hover-card d-flex align-items-center gap-3 shadow-sm h-100">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded"><i class="fas fa-layer-group fa-lg"></i></div>
            <div>
                <div class="fw-bold text-light">Question Bank</div>
                <div class="small text-muted">Browse & filter questions</div>
            </div>
        </a>
    </div>

    <div class="col-md-3 col-6">
        <a href="<?= url('assessments') ?>" class="card border p-3 text-decoration-none hover-card d-flex align-items-center gap-3 shadow-sm h-100">
            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded"><i class="fas fa-poll fa-lg"></i></div>
            <div>
                <div class="fw-bold text-light">Assessments & Results</div>
                <div class="small text-muted">Check student scorecards</div>
            </div>
        </a>
    </div>
</div>

<!-- Recent Assessments Table -->
<div class="card border shadow-sm">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Recent Assessments</h6>
        <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="border-bottom text-muted small text-uppercase">
                <tr>
                    <th>Assessment Title</th>
                    <th>Class / Grade</th>
                    <th>Subject</th>
                    <th>Questions</th>
                    <th>Attempts</th>
                    <th>Average Score</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAssessments)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        No assessments created yet.<br>
                        <?php if (Auth::hasPermission('assessments.create')): ?>
                            <a href="<?= url('assessments-create') ?>" class="btn btn-primary btn-sm mt-3"><i class="fas fa-plus me-1"></i> Create Your First Assessment</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recentAssessments as $ass): ?>
                    <tr>
                        <td class="fw-semibold">
                            <a href="<?= url('assessments-builder&id=' . $ass['id']) ?>" class="text-decoration-none text-light hover-primary">
                                <?= e($ass['title']) ?>
                            </a>
                            <?php if ((int)$ass['created_by'] === $teacherId): ?>
                                <span class="badge bg-primary text-dark ms-1" style="font-size: 0.65rem;">Yours</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= e($ass['target_class'] ?? '—') ?></span></td>
                        <td class="text-muted small"><?= e($ass['subject_name'] ?? '—') ?></td>
                        <td><span class="badge bg-dark border text-info"><?= (int)$ass['questions_count'] ?> Qs</span></td>
                        <td>
                            <a href="<?= url('assessments-results&id=' . $ass['id']) ?>" class="badge bg-dark border text-decoration-none text-light">
                                <?= (int)$ass['attempts_count'] ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($ass['avg_percentage'] !== null): ?>
                                <span class="fw-bold <?= (float)$ass['avg_percentage'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= (float)$ass['avg_percentage'] ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No data</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ass['status'] === 'published'): ?>
                                <span class="badge bg-success-subtle text-success border border-success">Published</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <?php if (Auth::hasPermission('assessments.edit') || (int)($ass['created_by'] ?? 0) === $teacherId || Auth::isAdmin()): ?>
                                <a href="<?= url('assessments-edit&id=' . $ass['id']) ?>" class="btn btn-outline-warning" title="Edit Assessment Settings">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?= url('assessments-builder&id=' . $ass['id']) ?>" class="btn btn-outline-primary" title="Question Builder">
                                    <i class="fas fa-puzzle-piece"></i>
                                </a>
                                <a href="<?= url('assessments-results&id=' . $ass['id']) ?>" class="btn btn-outline-success" title="Results & Reports">
                                    <i class="fas fa-poll"></i>
                                </a>
                                <?php if (Auth::hasPermission('assessments.delete') || (int)($ass['created_by'] ?? 0) === $teacherId || Auth::isSuperAdmin()): ?>
                                <form method="POST" action="<?= url('assessments') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this assessment?');">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="assessment_id" value="<?= $ass['id'] ?>">
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
