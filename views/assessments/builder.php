<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Interactive Quiz & Assessment Builder
 * Attach questions, reorder questions, generate random sets, and calculate marks dynamically.
 */

$assessmentId = (int)($_GET['id'] ?? 0);
$assessment = AssessmentService::getAssessment($assessmentId);

if (!$assessment) {
    Session::flash('error', 'Assessment not found.');
    redirectTo('assessments');
}

// Handle builder actions (Add questions, Remove, Reorder, Add Random)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_selected') {
        $selectedIds = $_POST['selected_questions'] ?? [];
        $added = AssessmentService::addQuestions($assessmentId, (array)$selectedIds);
        Session::flash('success', "Attached {$added} question(s) to the assessment.");
        redirectTo('assessments-builder&id=' . $assessmentId);
    }

    if ($action === 'add_random') {
        $count = max(1, (int)($_POST['random_count'] ?? 10));
        $added = AssessmentService::addRandomQuestions(
            $assessmentId,
            (int)$assessment['subject_id'],
            null,
            [],
            $count
        );

        if ($added > 0) {
            Session::flash('success', "Randomly selected and attached {$added} question(s) from the Question Bank.");
        } else {
            Session::flash('warning', "No additional questions available in the question bank for this subject.");
        }
        redirectTo('assessments-builder&id=' . $assessmentId);
    }

    if ($action === 'remove') {
        $qid = (int)($_POST['question_id'] ?? 0);
        try {
            AssessmentService::removeQuestion($assessmentId, $qid);
            Session::flash('success', 'Question removed from assessment successfully.');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to remove question: ' . $e->getMessage());
        }
        redirectTo('assessments-builder&id=' . $assessmentId);
    }

    if ($action === 'reorder') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        AssessmentService::reorderQuestion($assessmentId, $qid, $direction);
        redirectTo('assessments-builder&id=' . $assessmentId);
    }

    if ($action === 'quick_add_question') {
        $qText = trim((string)($_POST['question_text'] ?? ''));
        $qType = trim((string)($_POST['question_type'] ?? 'single_choice'));
        $qMarks = max(0.25, (float)($_POST['marks'] ?? 1.00));

        if ($qText === '') {
            Session::flash('error', 'Question text cannot be empty.');
            redirectTo('assessments-builder&id=' . $assessmentId);
        }

        $options = [];
        if ($qType === 'single_choice') {
            $correctIdx = (int)($_POST['sc_correct'] ?? 0);
            $optTexts = $_POST['sc_options'] ?? [];
            foreach ($optTexts as $idx => $t) {
                if (trim((string)$t) !== '') {
                    $options[] = [
                        'text' => trim((string)$t),
                        'is_correct' => ($idx === $correctIdx) ? 1 : 0
                    ];
                }
            }
        } elseif ($qType === 'multiple_choice') {
            $correctIndices = $_POST['mc_correct'] ?? [];
            $optTexts = $_POST['mc_options'] ?? [];
            foreach ($optTexts as $idx => $t) {
                if (trim((string)$t) !== '') {
                    $options[] = [
                        'text' => trim((string)$t),
                        'is_correct' => in_array((string)$idx, (array)$correctIndices, true) ? 1 : 0
                    ];
                }
            }
        } elseif ($qType === 'true_false') {
            $options = [
                'correct_answer' => ($_POST['tf_correct'] ?? 'true') === 'false' ? 'false' : 'true'
            ];
        } elseif ($qType === 'fill_blank') {
            $options = [
                'blanks' => trim((string)($_POST['fb_answers'] ?? ''))
            ];
        } elseif ($qType === 'short_answer') {
            $options = [
                'model_answer' => trim((string)($_POST['sa_model_answer'] ?? ''))
            ];
        }

        try {
            $newQId = QuestionService::createQuestion([
                'subject_id'    => (int)$assessment['subject_id'],
                'question_text' => $qText,
                'question_type' => $qType,
                'marks'         => $qMarks,
                'created_by'    => Auth::id() ?: 1,
            ], $options);

            // Automatically attach to this assessment
            AssessmentService::addQuestions($assessmentId, [$newQId]);

            Session::flash('success', "Question created and attached to assessment successfully!");
        } catch (Throwable $e) {
            Session::flash('error', "Failed to create question: " . $e->getMessage());
        }
        redirectTo('assessments-builder&id=' . $assessmentId);
    }
}

// Fetch currently attached questions
$assignedQuestions = AssessmentService::getAssessmentQuestions($assessmentId);
$assignedIds = array_column($assignedQuestions, 'question_id');

// Filter available questions in the question bank for this subject
$filterType = trim((string)($_GET['bank_type'] ?? ''));
$filterSearch = trim((string)($_GET['bank_search'] ?? ''));

$bankFilters = [
    'subject_id'    => (int)$assessment['subject_id'],
    'question_type' => $filterType ?: null,
    'search'        => $filterSearch ?: null,
    'exclude_ids'   => $assignedIds,
];

$availableQuestions = QuestionService::getQuestions($bankFilters);
?>

<!-- Header & Identity Banner -->
<div class="card border mb-4 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="mb-0 fw-bold"><?= e($assessment['title']) ?></h4>
                    <span class="badge bg-secondary-subtle border">ID #<?= (int)$assessment['id'] ?></span>
                    <?php if ($assessment['status'] === 'published'): ?>
                        <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-check-circle me-1"></i>Published</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning"><i class="fas fa-pencil-alt me-1"></i>Draft</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted d-flex flex-wrap gap-3 mt-2">
                    <span><i class="fas fa-university me-1 text-primary"></i><strong><?= e($assessment['campus_name'] ?? 'Main') ?></strong></span>
                    <span><i class="fas fa-user-tie me-1 text-info"></i>Author: <strong><?= e($assessment['creator_username'] ?? 'Faculty') ?></strong></span>
                    <span><i class="fas fa-book me-1 text-warning"></i>Subject: <strong><?= e($assessment['subject_name'] ?? 'General') ?></strong></span>
                    <span><i class="fas fa-graduation-cap me-1 text-secondary"></i>Class: <strong><?= e($assessment['target_class'] ?? ($assessment['class_grade'] ?? 'General')) ?></strong></span>
                    <span><i class="fas fa-clock me-1 text-info"></i>Duration: <strong><?= (int)$assessment['duration_minutes'] ?>m</strong></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('assessments-edit&id=' . $assessment['id']) ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fas fa-pencil-alt me-1"></i> Edit Settings
                </a>
                <a href="<?= url('assessments-devices&id=' . $assessment['id']) ?>" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-desktop me-1"></i> Allocate Devices
                </a>
                <a href="<?= url('assessments-results&id=' . $assessment['id']) ?>" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-chart-column me-1"></i> Results
                </a>
                <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Selected Questions & Ordering -->
    <div class="col-lg-7">
        <div class="card border shadow-sm">
            <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-list-ol me-2 text-primary"></i>Attached Questions (<?= count($assignedQuestions) ?>)
                    </h6>
                </div>
                <div class="text-end">
                    <span class="badge bg-success-subtle text-success border border-success fs-6">
                        Total: <?= number_format((float)$assessment['total_marks'], 2) ?> Marks
                    </span>
                </div>
            </div>

            <div class="card-body p-3">
                <?php if (empty($assignedQuestions)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 text-secondary d-block"></i>
                        No questions attached yet to this assessment.<br>
                        Select questions from the right panel or click <strong>Auto-Generate Pool</strong>.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="sortableList">
                        <?php foreach ($assignedQuestions as $idx => $q): ?>
                        <div class="list-group-item p-3 mb-2 rounded border">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="d-flex align-items-start gap-2 flex-grow-1">
                                    <span class="badge bg-secondary rounded-pill mt-1 fw-bold"><?= $idx + 1 ?></span>
                                    <div>
                                        <div class="fw-semibold mb-1">
                                            <?= e($q['question_text']) ?>
                                        </div>
                                        <div class="small text-muted d-flex flex-wrap gap-2">
                                            <span class="badge bg-dark border text-info"><?= e(QuestionService::TYPES[$q['question_type']] ?? $q['question_type']) ?></span>
                                            <span class="text-success fw-bold">+<?= number_format((float)$q['marks'], 2) ?> marks</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Move Up / Move Down Actions -->
                                <div class="d-flex align-items-center gap-1">
                                    <?php if ($idx > 0): ?>
                                        <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>" class="d-inline">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="reorder">
                                            <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Move Up">
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 disabled opacity-25"><i class="fas fa-arrow-up"></i></button>
                                    <?php endif; ?>

                                    <?php if ($idx < count($assignedQuestions) - 1): ?>
                                        <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>" class="d-inline">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="reorder">
                                            <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Move Down">
                                                <i class="fas fa-arrow-down"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 disabled opacity-25"><i class="fas fa-arrow-down"></i></button>
                                    <?php endif; ?>

                                    <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>" class="d-inline" onsubmit="return confirm('Remove question from assessment?');">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Question Bank Checkbox Selector & Random Generator -->
    <div class="col-lg-5">
        <!-- Action Shortcuts: Quick Add & Random Selector -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="card border shadow-sm h-100 bg-primary-subtle border-primary-subtle">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-bolt fa-2x text-primary mb-2"></i>
                        <h6 class="fw-bold mb-1">Quick Add Question</h6>
                        <p class="small text-muted mb-2">Create & attach immediately</p>
                        <button type="button" class="btn btn-primary btn-sm w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#quickAddModal">
                            <i class="fas fa-plus-circle me-1"></i> Add Question
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="card border shadow-sm h-100 bg-warning-subtle border-warning-subtle">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center align-items-center">
                        <i class="fas fa-dice fa-2x text-warning mb-2"></i>
                        <h6 class="fw-bold mb-1">Random Pool</h6>
                        <p class="small text-muted mb-2">Auto-attach from bank</p>
                        <button type="button" class="btn btn-warning btn-sm w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#randomModal">
                            <i class="fas fa-magic me-1"></i> Auto-Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Question Bank Checkbox Selector -->
        <div class="card border shadow-sm">
            <div class="card-header border-bottom p-3">
                <h6 class="mb-1 fw-bold"><i class="fas fa-check-square me-2 text-primary"></i>Question Bank Selector</h6>
                <p class="small text-muted mb-0">Select questions below and click <strong>Add Selected</strong>.</p>
            </div>

            <!-- Filter Controls -->
            <div class="card-body p-3 border-bottom">
                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <select id="filterBankType" class="form-select form-select-sm" onchange="applyBankFilter()">
                            <option value="">All Question Types</option>
                            <?php foreach (QuestionService::TYPES as $tKey => $tLabel): ?>
                                <option value="<?= $tKey ?>" <?= $filterType === $tKey ? 'selected' : '' ?>><?= e($tLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" id="filterBankSearch" class="form-control" placeholder="Search available questions..." value="<?= e($filterSearch) ?>">
                    <button class="btn btn-secondary" onclick="applyBankFilter()"><i class="fas fa-search"></i></button>
                </div>
            </div>

            <!-- Checkbox Table Form -->
            <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>" id="bulkAddForm">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add_selected">

                <div class="card-body p-2 d-flex justify-content-between align-items-center border-bottom">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleAllCheckboxes(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleAllCheckboxes(false)">Clear</button>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Add Selected
                    </button>
                </div>

                <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 small">
                        <tbody>
                            <?php if (empty($availableQuestions)): ?>
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">
                                    No available questions found in the Question Bank matching filters.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($availableQuestions as $bq): ?>
                                <tr>
                                    <td style="width: 35px;" class="text-center">
                                        <input type="checkbox" name="selected_questions[]" value="<?= $bq['id'] ?>" class="form-check-input q-checkbox">
                                    </td>
                                    <td>
                                        <div class="fw-semibold mb-1"><?= e(mb_strimwidth($bq['question_text'], 0, 75, '...')) ?></div>
                                        <div class="text-muted d-flex gap-2" style="font-size: 0.75rem;">
                                            <span><?= e(QuestionService::TYPES[$bq['question_type']] ?? $bq['question_type']) ?></span>
                                            <span>•</span>
                                            <span class="text-success">+<?= number_format((float)$bq['marks'], 2) ?>m</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Random Question Selector -->
<div class="modal fade" id="randomModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add_random">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fas fa-magic text-warning me-2"></i>Auto-Attach Questions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        The system will randomly select unattached questions for <strong><?= e($assessment['subject_name']) ?></strong> and attach them directly to this assessment.
                    </p>

                    <div class="mb-3">
                        <label for="random_count" class="form-label small text-muted text-uppercase fw-bold">Number of Questions to Attach</label>
                        <input type="number" name="random_count" id="random_count" class="form-control bg-dark text-light border-secondary" min="1" max="100" value="5" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i> Attach Random Questions</button>
                </div>
            </form>
        </div>
    </div>
<!-- Modal: Quick Add Question -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('assessments-builder&id=' . $assessment['id']) ?>" id="quickAddQuestionForm">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="quick_add_question">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-bolt text-primary me-2"></i>Quick Add & Attach Question
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="qa_type" class="form-label small text-muted text-uppercase fw-bold">Question Type</label>
                            <select name="question_type" id="qa_type" class="form-select" onchange="switchQuickAddType(this.value)">
                                <option value="single_choice">Single Choice (Radio)</option>
                                <option value="multiple_choice">Multiple Choice (Checkboxes)</option>
                                <option value="true_false">True / False</option>
                                <option value="fill_blank">Fill in the Blank</option>
                                <option value="short_answer">Short Answer / Written Response (Held for Review)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="qa_marks" class="form-label small text-muted text-uppercase fw-bold">Marks</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-award"></i></span>
                                <input type="number" name="marks" id="qa_marks" class="form-control" step="0.25" min="0.25" max="100" value="1.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="qa_text" class="form-label small text-muted text-uppercase fw-bold">Question Statement <span class="text-danger">*</span></label>
                        <textarea name="question_text" id="qa_text" rows="3" class="form-control" placeholder="Type question statement here..." required></textarea>
                    </div>

                    <!-- 1. Single Choice Options -->
                    <div id="qa_sec_single_choice" class="qa-section">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-2">Options (Select radio for correct answer)</label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="sc_correct" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> title="Mark as correct">
                            </div>
                            <input type="text" name="sc_options[<?= $i ?>]" class="form-control" placeholder="Option <?= chr(65 + $i) ?>" <?= $i < 2 ? 'required' : '' ?>>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- 2. Multiple Choice Options -->
                    <div id="qa_sec_multiple_choice" class="qa-section d-none">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-2">Options (Check all correct answers)</label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="checkbox" name="mc_correct[]" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> title="Mark as correct">
                            </div>
                            <input type="text" name="mc_options[<?= $i ?>]" class="form-control" placeholder="Option <?= chr(65 + $i) ?>">
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- 3. True / False Options -->
                    <div id="qa_sec_true_false" class="qa-section d-none">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-2">Select Correct Answer</label>
                        <div class="d-flex gap-4 p-2 border rounded">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="qa_tf_true" value="true" checked>
                                <label class="form-check-label fw-bold text-success" for="qa_tf_true"><i class="fas fa-check me-1"></i> True</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="qa_tf_false" value="false">
                                <label class="form-check-label fw-bold text-danger" for="qa_tf_false"><i class="fas fa-times me-1"></i> False</label>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Fill in the Blank Options -->
                    <div id="qa_sec_fill_blank" class="qa-section d-none">
                        <label for="qa_fb_answers" class="form-label small text-muted text-uppercase fw-bold mb-1">Accepted Correct Answer(s)</label>
                        <input type="text" name="fb_answers" id="qa_fb_answers" class="form-control" placeholder="e.g. CPU, Central Processing Unit (comma-separated)">
                        <div class="form-text small text-muted">Students' answers are compared case-insensitively. Teacher can also award partial marks during review.</div>
                    </div>

                    <!-- 5. Short Answer / Written Response -->
                    <div id="qa_sec_short_answer" class="qa-section d-none">
                        <label for="qa_sa_model" class="form-label small text-muted text-uppercase fw-bold mb-1">Model Answer / Evaluation Rubric (Optional)</label>
                        <textarea name="sa_model_answer" id="qa_sa_model" rows="3" class="form-control" placeholder="Provide reference solution or grading criteria for teachers to review against..."></textarea>
                        <div class="alert alert-info py-2 px-3 small mt-2 mb-0">
                            <i class="fas fa-user-shield me-1"></i> <strong>Subjective Review Required:</strong> Result is automatically withheld until the instructor reviews and awards marks.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Create & Attach Question
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchQuickAddType(type) {
    document.querySelectorAll('.qa-section').forEach(el => el.classList.add('d-none'));
    const target = document.getElementById('qa_sec_' + type);
    if (target) {
        target.classList.remove('d-none');
    }
}

function toggleAllCheckboxes(status) {
    document.querySelectorAll('.q-checkbox').forEach(cb => cb.checked = status);
}

function applyBankFilter() {
    const type = document.getElementById('filterBankType').value;
    const search = document.getElementById('filterBankSearch').value;

    let urlStr = '<?= url("assessments-builder&id=" . $assessment["id"]) ?>';
    if (type) urlStr += '&bank_type=' + encodeURIComponent(type);
    if (search) urlStr += '&bank_search=' + encodeURIComponent(search);

    window.location.href = urlStr;
}
</script>
