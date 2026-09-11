<?php
/**
 * PTM Assessment System — Question Bank Index View
 */

$subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$type = trim($_GET['type'] ?? '');
$difficulty = trim($_GET['difficulty'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Handle Delete Question Action
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $action = $_POST['action'] ?? '';
    $questionId = (int)($_POST['question_id'] ?? 0);

    if ($action === 'delete') {
        if (!Auth::hasPermission('questions.delete')) {
            Session::flash('error', 'Permission denied: You do not have permission to delete questions.');
            redirectTo('questions');
        }
        try {
            QuestionService::deleteQuestion($questionId);
            Session::flash('success', 'Question was removed from the Question Bank.');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        redirectTo('questions');
    }
}

// Fetch filter options
$subjects = QuestionService::getSubjects();
$categories = $subjectId ? QuestionService::getCategoriesBySubject($subjectId) : [];

// Fetch filtered questions
$filterParams = [
    'subject_id'    => $subjectId ?: null,
    'category_id'   => $categoryId ?: null,
    'question_type' => $type ?: null,
    'difficulty'    => $difficulty ?: null,
    'search'        => $search ?: null,
];

$allMatching = QuestionService::getQuestions($filterParams);
$totalQuestions = count($allMatching);
$totalPages = ceil($totalQuestions / $perPage);

$filterParams['limit'] = $perPage;
$filterParams['offset'] = $offset;
$questions = QuestionService::getQuestions($filterParams);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="fas fa-layer-group text-primary me-2"></i>Question Bank</h4>
        <p class="text-muted small mb-0">Central repository of reusable global assessment questions across all subjects and grades.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('questions-create') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Question
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('questions') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="questions">

            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search questions..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-2">
                <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= $s['questions_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($categories)): ?>
            <div class="col-md-2">
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Question Types</option>
                    <?php foreach (QuestionService::TYPES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="difficulty" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?= $difficulty === 'easy' ? 'selected' : '' ?>>Easy</option>
                    <option value="medium" <?= $difficulty === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="hard" <?= $difficulty === 'hard' ? 'selected' : '' ?>>Hard</option>
                </select>
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-secondary btn-sm w-100" title="Filter"><i class="fas fa-filter"></i></button>
                <?php if ($search || $subjectId || $categoryId || $type || $difficulty): ?>
                    <a href="<?= url('questions') ?>" class="btn btn-outline-danger btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Question List Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center p-3">
        <span class="small text-muted">Showing <strong><?= count($questions) ?></strong> of <strong><?= $totalQuestions ?></strong> questions</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="text-muted small text-uppercase">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Question Description</th>
                    <th>Subject & Category</th>
                    <th style="width: 140px;">Type</th>
                    <th style="width: 100px;">Difficulty</th>
                    <th style="width: 80px;">Marks</th>
                    <th style="width: 120px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                        No questions found matching the selected criteria.<br>
                        <a href="<?= url('questions-create') ?>" class="btn btn-primary btn-sm mt-3"><i class="fas fa-plus me-1"></i> Add Your First Question</a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td class="font-monospace small text-muted"><?= $q['id'] ?></td>
                        <td>
                            <div class="fw-semibold mb-1">
                                <?= e(mb_strimwidth($q['question_text'], 0, 95, '...')) ?>
                                <?php if (!empty($q['image_path'])): ?>
                                    <span class="badge bg-info ms-1" title="Has attached image"><i class="fas fa-image"></i> Image</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                <span class="me-2"><i class="fas fa-user-edit me-1"></i><?= e($q['creator_name'] ?? 'Faculty') ?></span>
                                <span class="badge bg-secondary-subtle text-info border"><i class="fas fa-globe me-1"></i>Global</span>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-semibold text-primary"><?= e($q['subject_name'] ?? 'General') ?></div>
                            <div class="small text-muted"><?= e($q['category_name'] ?? 'General') ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle border text-info">
                                <?= e(QuestionService::TYPES[$q['question_type']] ?? $q['question_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $diff = $q['difficulty'] ?? 'medium';
                            $diffBadge = match($diff) {
                                'easy'   => 'bg-success-subtle text-success border-success',
                                'medium' => 'bg-warning-subtle text-warning border-warning',
                                'hard'   => 'bg-danger-subtle text-danger border-danger',
                                default  => 'bg-secondary'
                            };
                            ?>
                            <span class="badge border <?= $diffBadge ?>"><?= ucfirst($diff) ?></span>
                        </td>
                        <td>
                            <span class="fw-bold text-success">+<?= number_format((float)$q['marks'], 2) ?></span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= url('questions-edit&id=' . $q['id']) ?>" class="btn btn-outline-secondary" title="Edit Question">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (Auth::hasPermission('questions.delete')): ?>
                                <form method="POST" action="<?= url('questions') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete Question">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-dark border-secondary p-3 d-flex justify-content-center">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link bg-dark border-secondary text-light" 
                           href="<?= url('questions&p=' . $p . ($subjectId ? '&subject_id=' . $subjectId : '') . ($type ? '&type=' . $type : '') . ($difficulty ? '&difficulty=' . $difficulty : '') . ($search ? '&search=' . urlencode($search) : '')) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

