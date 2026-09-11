<?php
/**
 * PTM Assessment System — Create Question(s) View
 * Supports single and bulk question authoring with inherited metadata, difficulty, and marks.
 */

$subjects = QuestionService::getSubjects();
$initialSubjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : (!empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : ($subjects[0]['id'] ?? 1));

$errors = [];
$formData = [
    'subject_id'  => $initialSubjectId,
    'category_id' => $_POST['category_id'] ?? '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $subjectId = (int)($_POST['subject_id'] ?? $initialSubjectId);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $submitAction = $_POST['submit_action'] ?? 'save_all';

    // Extract raw questions array (supports bulk cards or fallback to single form)
    $rawQuestions = $_POST['questions'] ?? [];
    if (empty($rawQuestions) && !empty($_POST['question_text'])) {
        $rawQuestions = [
            0 => [
                'question_text'   => $_POST['question_text'] ?? '',
                'question_type'   => $_POST['question_type'] ?? 'single_choice',
                'difficulty'      => $_POST['difficulty'] ?? 'medium',
                'marks'           => $_POST['marks'] ?? '1.00',
                'negative_marks'  => $_POST['negative_marks'] ?? '0.00',
                'explanation'     => $_POST['explanation'] ?? '',
                'options'         => $_POST['options'] ?? [],
                'sc_options'      => $_POST['sc_options'] ?? $_POST['options'] ?? [],
                'mc_options'      => $_POST['mc_options'] ?? $_POST['options'] ?? [],
                'correct_single'  => $_POST['correct_single'] ?? 0,
                'correct_multi'   => $_POST['correct_multi'] ?? [],
                'tf_correct'      => $_POST['tf_correct'] ?? 'true',
                'yn_correct'      => $_POST['yn_correct'] ?? 'yes',
                'accepted_answers'=> $_POST['accepted_answers'] ?? '',
                'sa_model_answer' => $_POST['sa_model_answer'] ?? '',
            ]
        ];
    }

    $precedingDifficulty = 'medium';
    $precedingMarks = 1.00;
    $precedingNegativeMarks = 0.00;

    $questionsToCreate = [];
    $validCount = 0;

    foreach ($rawQuestions as $idx => $q) {
        $statement = trim((string)($q['question_text'] ?? ''));
        if ($statement === '') {
            continue; // Skip blank cards
        }

        $type = trim((string)($q['question_type'] ?? 'single_choice'));
        if (!array_key_exists($type, QuestionService::TYPES)) {
            $type = 'single_choice';
        }

        // Difficulty & Marks inheritance from preceding
        $inherit = !empty($q['inherit_preceding']) && $validCount > 0;
        if ($inherit) {
            $diff = $precedingDifficulty;
            $marks = $precedingMarks;
            $negMarks = $precedingNegativeMarks;
        } else {
            $diff = in_array(($q['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium';
            $marks = max(0.25, (float)($q['marks'] ?? 1.00));
            $negMarks = max(0.00, (float)($q['negative_marks'] ?? 0.00));

            // Set new preceding baseline for subsequent questions
            $precedingDifficulty = $diff;
            $precedingMarks = $marks;
            $precedingNegativeMarks = $negMarks;
        }

        // Parse options by type
        $options = [];
        switch ($type) {
            case 'single_choice':
                $opts = $q['sc_options'] ?? $q['options'] ?? [];
                $correctSingle = (int)($q['correct_single'] ?? 0);
                $hasCorrect = false;
                foreach ($opts as $optIdx => $optText) {
                    $optClean = trim((string)$optText);
                    if ($optClean === '') continue;
                    $isCorrect = ($correctSingle === (int)$optIdx) ? 1 : 0;
                    if ($isCorrect) {
                        $hasCorrect = true;
                    }
                    $options[] = [
                        'text'       => $optClean,
                        'is_correct' => $isCorrect,
                    ];
                }
                // Fallback: if no valid option was marked correct, default first option to correct
                if (!$hasCorrect && !empty($options)) {
                    $options[0]['is_correct'] = 1;
                }
                break;

            case 'multiple_choice':
                $opts = $q['mc_options'] ?? $q['options'] ?? [];
                $correctMulti = (array)($q['correct_multi'] ?? []);
                $hasCorrect = false;
                foreach ($opts as $optIdx => $optText) {
                    $optClean = trim((string)$optText);
                    if ($optClean === '') continue;
                    $isCorrect = (in_array((string)$optIdx, $correctMulti, true) || in_array((int)$optIdx, $correctMulti, true)) ? 1 : 0;
                    if ($isCorrect) {
                        $hasCorrect = true;
                    }
                    $options[] = [
                        'text'       => $optClean,
                        'is_correct' => $isCorrect,
                    ];
                }
                // Fallback: if no checkbox was marked, default first option to correct
                if (!$hasCorrect && !empty($options)) {
                    $options[0]['is_correct'] = 1;
                }
                break;

            case 'true_false':
                $options = ['correct_answer' => $q['tf_correct'] ?? 'true'];
                break;

            case 'yes_no':
                $correctVal = strtolower((string)($q['yn_correct'] ?? 'yes'));
                $options = [
                    ['text' => 'Yes', 'is_correct' => ($correctVal === 'yes') ? 1 : 0],
                    ['text' => 'No',  'is_correct' => ($correctVal === 'no')  ? 1 : 0],
                ];
                break;

            case 'fill_blank':
                $options = ['blanks' => trim((string)($q['accepted_answers'] ?? ''))];
                break;

            case 'short_answer':
                $options = ['model_answer' => trim((string)($q['sa_model_answer'] ?? ''))];
                break;
        }

        // Validate choice count for single and multiple choice
        if (in_array($type, ['single_choice', 'multiple_choice'], true) && count($options) < 2) {
            $typeLabel = QuestionService::TYPES[$type] ?? $type;
            $errors['general'] = "Question #" . ($validCount + 1) . " ({$typeLabel}) requires at least 2 answer choices.";
            break;
        }

        $imageFile = $_FILES["question_image_{$idx}"] ?? ($_FILES['question_image'] ?? null);

        $questionsToCreate[] = [
            'data' => [
                'subject_id'     => $subjectId,
                'category_id'    => $categoryId,
                'created_by'     => Auth::id() ?: 1,
                'question_text'  => $statement,
                'question_type'  => $type,
                'difficulty'     => $diff,
                'marks'          => $marks,
                'negative_marks' => $negMarks,
                'explanation'    => trim((string)($q['explanation'] ?? '')),
            ],
            'options'   => $options,
            'imageFile' => $imageFile,
        ];
        $validCount++;
    }

    if (!empty($errors['general'])) {
        // Validation error already set
    } elseif (empty($questionsToCreate)) {
        $errors['general'] = 'Please enter question statement text for at least one question.';
    } else {
        try {
            $createdIds = [];
            Database::beginTransaction();
            foreach ($questionsToCreate as $item) {
                $newId = QuestionService::createQuestion($item['data'], $item['options'], $item['imageFile']);
                $createdIds[] = $newId;
            }
            Database::commit();

            $msgCount = count($createdIds);
            if ($msgCount === 1) {
                Session::flash('success', "Question #{$createdIds[0]} added to the Question Bank successfully.");
            } else {
                Session::flash('success', "Bulk addition complete: {$msgCount} questions successfully added to the Question Bank!");
            }

            if ($submitAction === 'save_and_add_more') {
                redirectTo('questions-create&subject_id=' . $subjectId);
            } else {
                redirectTo('questions&subject_id=' . $subjectId);
            }
        } catch (Exception $e) {
            Database::rollback();
            $errors['general'] = $e->getMessage();
        }
    }
}

// Categories for initial subject
$categories = QuestionService::getCategoriesBySubject((int)$formData['subject_id']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="fas fa-layer-group text-primary me-2"></i>Add Question(s) — Bulk Authoring</h4>
        <p class="text-muted small mb-0">Select subject metadata once, then add questions in bulk with auto-inherited difficulty & marks.</p>
    </div>
    <a href="<?= url('questions') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to Question Bank
    </a>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= e($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('questions-create') ?>" enctype="multipart/form-data" id="bulkQuestionForm">
    <?= CSRF::field() ?>

    <!-- Shared Metadata Card (Applied once to all questions in this batch) -->
    <div class="card bg-dark border-primary mb-4 shadow-sm">
        <div class="card-header bg-dark border-secondary p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-light">
                <i class="fas fa-book me-2 text-primary"></i>Batch Subject & Scope <span class="badge bg-primary-subtle text-primary border ms-2">Applied to All Questions Below</span>
            </h6>
            <span class="badge bg-secondary-subtle text-info border">
                <i class="fas fa-globe me-1"></i> Global Question Bank
            </span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="subject_id" class="form-label small text-muted text-uppercase fw-bold">Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" id="subject_id" class="form-select bg-dark text-light border-secondary" required onchange="loadCategories(this.value)">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (int)$formData['subject_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="category_id" class="form-label small text-muted text-uppercase fw-bold">Topic / Unit / Category</label>
                    <select name="category_id" id="category_id" class="form-select bg-dark text-light border-secondary">
                        <option value="">General / None</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$formData['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="small text-muted mt-2">
                <i class="fas fa-info-circle text-info me-1"></i> You don't need to re-enter this metadata for each question. Click the <strong>"+"</strong> button at the center bottom to append questions in bulk.
            </div>
        </div>
    </div>

    <!-- Questions Container -->
    <div id="questionsContainer">
        <!-- Question Card #0 (Initial Question) -->
        <div class="card bg-dark border-secondary mb-4 shadow question-card" id="question_card_0" data-index="0">
            <div class="card-header bg-dark border-secondary p-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 fs-6 fw-bold question-number-badge">Question #1</span>
                    <span class="badge bg-secondary-subtle border text-light question-type-badge" id="type_badge_0">Single Choice</span>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-star text-warning me-1"></i> Initial Question (Baseline)
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small text-muted text-uppercase fw-bold">Question Statement <span class="text-danger">*</span></label>
                        <textarea name="questions[0][question_text]" id="q_statement_0" rows="3" 
                                  class="form-control bg-dark text-light border-secondary" 
                                  placeholder="Type the question statement here..." required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Question Type</label>
                        <select name="questions[0][question_type]" class="form-select bg-dark text-light border-secondary mb-2" onchange="switchCardQuestionType(0, this.value)">
                            <?php foreach (QuestionService::TYPES as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $key === 'single_choice' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-image me-1 text-info"></i> Attach Image (Optional)</label>
                        <input type="file" name="question_image_0" class="form-control form-control-sm bg-dark text-light border-secondary" accept="image/*">
                    </div>
                </div>

                <!-- Answer Options Container for Card 0 -->
                <div class="card bg-dark-subtle border-secondary mb-3">
                    <div class="card-header bg-transparent border-secondary py-2 px-3">
                        <h6 class="mb-0 small fw-bold text-light text-uppercase"><i class="fas fa-list-check me-2 text-primary"></i>Answer Options & Scoring Criteria</h6>
                    </div>
                    <div class="card-body p-3 options-container" id="options_container_0">
                        <!-- Single Choice -->
                        <div class="type-section type-section-single_choice">
                            <p class="small text-muted mb-2">Provide options and choose the correct radio button:</p>
                            <?php for ($i = 0; $i < 4; $i++): ?>
                            <div class="input-group input-group-sm mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="radio" name="questions[0][correct_single]" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> title="Mark as correct answer">
                                </div>
                                <input type="text" name="questions[0][sc_options][<?= $i ?>]" class="form-control bg-dark text-light border-secondary" placeholder="Option <?= $i + 1 ?>">
                            </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Multiple Choice -->
                        <div class="type-section type-section-multiple_choice d-none">
                            <p class="small text-muted mb-2">Check all checkboxes that represent correct answers:</p>
                            <?php for ($i = 0; $i < 4; $i++): ?>
                            <div class="input-group input-group-sm mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="checkbox" name="questions[0][correct_multi][]" value="<?= $i ?>" title="Mark as correct answer" disabled>
                                </div>
                                <input type="text" name="questions[0][mc_options][<?= $i ?>]" class="form-control bg-dark text-light border-secondary" placeholder="Option <?= $i + 1 ?>" disabled>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <!-- True / False -->
                        <div class="type-section type-section-true_false d-none">
                            <p class="small text-muted mb-2">Select the correct statement truth value:</p>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[0][tf_correct]" id="tf_true_0" value="true" checked disabled>
                                    <label class="form-check-label text-success fw-bold" for="tf_true_0"><i class="fas fa-check me-1"></i> True</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[0][tf_correct]" id="tf_false_0" value="false" disabled>
                                    <label class="form-check-label text-danger fw-bold" for="tf_false_0"><i class="fas fa-times me-1"></i> False</label>
                                </div>
                            </div>
                        </div>

                        <!-- Yes / No -->
                        <div class="type-section type-section-yes_no d-none">
                            <p class="small text-muted mb-2">Select the correct response:</p>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[0][yn_correct]" id="yn_yes_0" value="yes" checked disabled>
                                    <label class="form-check-label text-success fw-bold" for="yn_yes_0"><i class="fas fa-thumbs-up me-1"></i> Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[0][yn_correct]" id="yn_no_0" value="no" disabled>
                                    <label class="form-check-label text-danger fw-bold" for="yn_no_0"><i class="fas fa-thumbs-down me-1"></i> No</label>
                                </div>
                            </div>
                        </div>

                        <!-- Fill in the Blank -->
                        <div class="type-section type-section-fill_blank d-none">
                            <label class="form-label small text-muted text-uppercase fw-bold">Accepted Correct Answers</label>
                            <textarea name="questions[0][accepted_answers]" rows="2" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Comma-separated accepted answers (e.g. CPU, Central Processing Unit, processor)" disabled></textarea>
                        </div>

                        <!-- Short Answer / Written Response -->
                        <div class="type-section type-section-short_answer d-none">
                            <label class="form-label small text-muted text-uppercase fw-bold">Model Answer / Evaluation Rubric Guidelines</label>
                            <textarea name="questions[0][sa_model_answer]" rows="2" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Provide reference solution or key concepts for teacher grading..." disabled></textarea>
                            <div class="alert alert-info py-1 px-2 small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i> <strong>Subjective Question:</strong> Results will show pending review until the teacher marks it.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Difficulty & Marks Bar for Question 0 -->
                <div class="row g-3 align-items-center">
                    <div class="col-sm-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Difficulty <span class="text-danger">*</span></label>
                        <select name="questions[0][difficulty]" id="difficulty_0" class="form-select form-select-sm bg-dark text-light border-secondary card-diff-select" onchange="syncPrecedingSettings(0)">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Marks <span class="text-danger">*</span></label>
                        <input type="number" step="0.25" min="0.25" name="questions[0][marks]" id="marks_0" 
                               class="form-control form-control-sm bg-dark text-light border-secondary card-marks-input" 
                               value="1.00" required oninput="syncPrecedingSettings(0)">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Negative Marks</label>
                        <input type="number" step="0.25" min="0" name="questions[0][negative_marks]" id="neg_marks_0" 
                               class="form-control form-control-sm bg-dark text-light border-secondary card-neg-input" 
                               value="0.00" oninput="syncPrecedingSettings(0)">
                    </div>
                </div>

                <!-- Solution / Explanation -->
                <div class="mt-3">
                    <label class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-lightbulb text-warning me-1"></i> Explanation / Feedback (Optional)</label>
                    <input type="text" name="questions[0][explanation]" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Explain why the answer is correct...">
                </div>
            </div>
        </div>
    </div>

    <!-- Center Bottom Plus Button for Bulk Adding -->
    <div class="text-center my-4 py-3 border-top border-secondary border-opacity-50">
        <button type="button" class="btn btn-primary rounded-circle shadow-lg d-inline-flex align-items-center justify-content-center mb-2" 
                id="btnAddQuestionBlock" onclick="addQuestionCard()" style="width: 58px; height: 58px; font-size: 26px;" title="Add Another Question in Bulk (+)">
            <i class="fas fa-plus"></i>
        </button>
        <div>
            <span class="fw-bold text-light fs-6">Add Another Question</span>
        </div>
        <span class="text-muted small">
            <i class="fas fa-magic text-info me-1"></i> Inherits subject & automatically keeps preceding difficulty and marks unless changed.
        </span>
    </div>

    <!-- Bottom Action & Save Bar -->
    <div class="card bg-dark border-secondary p-3 mb-5 shadow sticky-bottom" style="z-index: 1000; bottom: 15px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <i class="fas fa-layer-group me-1"></i> <span id="summaryQuestionCount">1</span> Question(s)
                </span>
                <span class="text-muted small">Ready to save into Question Bank under selected subject</span>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('questions') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" name="submit_action" value="save_all" class="btn btn-primary fw-bold px-4">
                    <i class="fas fa-save me-1"></i> Save to Question Bank
                </button>
                <button type="submit" name="submit_action" value="save_and_add_more" class="btn btn-outline-info fw-bold">
                    <i class="fas fa-plus-circle me-1"></i> Save & Continue Adding
                </button>
            </div>
        </div>
    </div>
</form>

<script>
let questionCount = 1;

// Question types mapping
const QUESTION_TYPES = <?= json_encode(QuestionService::TYPES) ?>;

// Switch question type on specific card
function switchCardQuestionType(idx, type) {
    const container = document.getElementById('options_container_' + idx);
    const badge = document.getElementById('type_badge_' + idx);
    if (badge && QUESTION_TYPES[type]) {
        badge.textContent = QUESTION_TYPES[type];
    }
    if (!container) return;

    // Hide all type sections and disable their inputs so browser doesn't validate or submit them
    container.querySelectorAll('.type-section').forEach(sec => {
        sec.classList.add('d-none');
        sec.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = true;
        });
    });

    // Unhide the chosen type section and re-enable its inputs
    const target = container.querySelector('.type-section-' + type);
    if (target) {
        target.classList.remove('d-none');
        target.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = false;
        });
    }
}

// Get effective difficulty and marks from card with index
function getCardSettings(idx) {
    const card = document.getElementById('question_card_' + idx);
    if (!card) {
        return { difficulty: 'medium', marks: '1.00', negMarks: '0.00' };
    }
    const diffSelect = card.querySelector('.card-diff-select');
    const marksInput = card.querySelector('.card-marks-input');
    const negInput = card.querySelector('.card-neg-input');
    return {
        difficulty: diffSelect ? diffSelect.value : 'medium',
        marks: marksInput && marksInput.value !== '' ? marksInput.value : '1.00',
        negMarks: negInput && negInput.value !== '' ? negInput.value : '0.00'
    };
}

// Toggle inheritance from preceding question on card idx
function handleInheritToggle(idx, isChecked) {
    const customDiv = document.getElementById('custom_settings_' + idx);
    const badgeDiv = document.getElementById('inherit_badge_' + idx);
    if (isChecked) {
        if (customDiv) customDiv.classList.add('d-none');
        if (badgeDiv) badgeDiv.classList.remove('d-none');
        syncCardFromPreceding(idx);
    } else {
        if (customDiv) customDiv.classList.remove('d-none');
        if (badgeDiv) badgeDiv.classList.add('d-none');
    }
    syncAllSubsequent(idx);
}

// Sync card idx from its preceding active card
function syncCardFromPreceding(idx) {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    const currentCard = document.getElementById('question_card_' + idx);
    const currentPos = cards.indexOf(currentCard);
    if (currentPos <= 0) return;

    const prevCard = cards[currentPos - 1];
    const prevIdx = parseInt(prevCard.dataset.index, 10);
    const prevSettings = getCardSettings(prevIdx);

    const diffSelect = currentCard.querySelector('.card-diff-select');
    const marksInput = currentCard.querySelector('.card-marks-input');
    const negInput = currentCard.querySelector('.card-neg-input');

    if (diffSelect) diffSelect.value = prevSettings.difficulty;
    if (marksInput) marksInput.value = prevSettings.marks;
    if (negInput) negInput.value = prevSettings.negMarks;

    const label = document.getElementById('inherit_label_' + idx);
    if (label) {
        label.textContent = prevSettings.difficulty.toUpperCase() + ' • ' + prevSettings.marks + ' Marks';
    }
}

// When difficulty/marks change on card idx, sync all subsequent cards that inherit
function syncPrecedingSettings(fromIdx) {
    syncAllSubsequent(fromIdx);
}

function syncAllSubsequent(fromIdx) {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    let startSync = false;

    cards.forEach((card, pos) => {
        const cIdx = parseInt(card.dataset.index, 10);
        if (cIdx === fromIdx) {
            startSync = true;
            return;
        }
        if (startSync) {
            const inheritToggle = card.querySelector('.inherit-toggle-input');
            if (inheritToggle && inheritToggle.checked) {
                syncCardFromPreceding(cIdx);
            }
        }
    });
}

// Add a new question card dynamically
function addQuestionCard() {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    const lastCard = cards[cards.length - 1];
    const lastIdx = lastCard ? parseInt(lastCard.dataset.index, 10) : 0;
    const prevSettings = getCardSettings(lastIdx);

    // Also get last card's question type to carry over as convenient default
    const lastTypeSelect = lastCard ? lastCard.querySelector('select[name*="[question_type]"]') : null;
    const prevType = lastTypeSelect ? lastTypeSelect.value : 'single_choice';

    const newIdx = questionCount;
    questionCount++;

    const newCardHtml = `
        <div class="card bg-dark border-secondary mb-4 shadow question-card" id="question_card_${newIdx}" data-index="${newIdx}">
            <div class="card-header bg-dark border-secondary p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 fs-6 fw-bold question-number-badge">Question #${cards.length + 1}</span>
                    <span class="badge bg-secondary-subtle border text-light question-type-badge" id="type_badge_${newIdx}">${QUESTION_TYPES[prevType] || 'Single Choice'}</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQuestionCard(${newIdx})" title="Remove this question">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                <!-- Inheritance Bar -->
                <div class="card bg-dark-subtle border-secondary p-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input inherit-toggle-input" type="checkbox" role="switch" 
                                   id="inherit_toggle_${newIdx}" name="questions[${newIdx}][inherit_preceding]" value="1" checked 
                                   onchange="handleInheritToggle(${newIdx}, this.checked)">
                            <label class="form-check-label text-light small fw-semibold" for="inherit_toggle_${newIdx}">
                                <i class="fas fa-link text-info me-1"></i> Keep difficulty and marks from preceding question
                            </label>
                        </div>
                        <div id="inherit_badge_${newIdx}">
                            <span class="badge bg-secondary-subtle text-info border">
                                Preceding: <strong id="inherit_label_${newIdx}">${prevSettings.difficulty.toUpperCase()} • ${prevSettings.marks} Marks</strong>
                            </span>
                        </div>
                    </div>
                    <!-- Custom Settings (hidden by default when inheriting) -->
                    <div id="custom_settings_${newIdx}" class="row g-2 mt-2 d-none">
                        <div class="col-sm-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Difficulty</label>
                            <select name="questions[${newIdx}][difficulty]" id="difficulty_${newIdx}" class="form-select form-select-sm bg-dark text-light border-secondary card-diff-select" onchange="syncPrecedingSettings(${newIdx})">
                                <option value="easy" ${prevSettings.difficulty === 'easy' ? 'selected' : ''}>Easy</option>
                                <option value="medium" ${prevSettings.difficulty === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="hard" ${prevSettings.difficulty === 'hard' ? 'selected' : ''}>Hard</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Marks</label>
                            <input type="number" step="0.25" min="0.25" name="questions[${newIdx}][marks]" id="marks_${newIdx}" 
                                   class="form-control form-control-sm bg-dark text-light border-secondary card-marks-input" 
                                   value="${prevSettings.marks}" oninput="syncPrecedingSettings(${newIdx})">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Negative Marks</label>
                            <input type="number" step="0.25" min="0" name="questions[${newIdx}][negative_marks]" id="neg_marks_${newIdx}" 
                                   class="form-control form-control-sm bg-dark text-light border-secondary card-neg-input" 
                                   value="${prevSettings.negMarks}" oninput="syncPrecedingSettings(${newIdx})">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small text-muted text-uppercase fw-bold">Question Statement <span class="text-danger">*</span></label>
                        <textarea name="questions[${newIdx}][question_text]" id="q_statement_${newIdx}" rows="3" 
                                  class="form-control bg-dark text-light border-secondary" 
                                  placeholder="Type the question statement here..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Question Type</label>
                        <select name="questions[${newIdx}][question_type]" class="form-select bg-dark text-light border-secondary mb-2" onchange="switchCardQuestionType(${newIdx}, this.value)">
                            ${Object.entries(QUESTION_TYPES).map(([k, v]) => `
                                <option value="${k}" ${k === prevType ? 'selected' : ''}>${v}</option>
                            `).join('')}
                        </select>
                        <label class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-image me-1 text-info"></i> Attach Image (Optional)</label>
                        <input type="file" name="question_image_${newIdx}" class="form-control form-control-sm bg-dark text-light border-secondary" accept="image/*">
                    </div>
                </div>

                <!-- Answer Options Container -->
                <div class="card bg-dark-subtle border-secondary mb-3">
                    <div class="card-header bg-transparent border-secondary py-2 px-3">
                        <h6 class="mb-0 small fw-bold text-light text-uppercase"><i class="fas fa-list-check me-2 text-primary"></i>Answer Options & Scoring Criteria</h6>
                    </div>
                    <div class="card-body p-3 options-container" id="options_container_${newIdx}">
                        <!-- Single Choice -->
                        <div class="type-section type-section-single_choice">
                            <p class="small text-muted mb-2">Provide options and choose the correct radio button:</p>
                            ${[0,1,2,3].map(i => `
                            <div class="input-group input-group-sm mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="radio" name="questions[${newIdx}][correct_single]" value="${i}" ${i === 0 ? 'checked' : ''} title="Mark as correct answer">
                                </div>
                                <input type="text" name="questions[${newIdx}][sc_options][${i}]" class="form-control bg-dark text-light border-secondary" placeholder="Option ${i + 1}">
                            </div>
                            `).join('')}
                        </div>

                        <!-- Multiple Choice -->
                        <div class="type-section type-section-multiple_choice d-none">
                            <p class="small text-muted mb-2">Check all checkboxes that represent correct answers:</p>
                            ${[0,1,2,3].map(i => `
                            <div class="input-group input-group-sm mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="checkbox" name="questions[${newIdx}][correct_multi][]" value="${i}" title="Mark as correct answer">
                                </div>
                                <input type="text" name="questions[${newIdx}][mc_options][${i}]" class="form-control bg-dark text-light border-secondary" placeholder="Option ${i + 1}">
                            </div>
                            `).join('')}
                        </div>

                        <!-- True / False -->
                        <div class="type-section type-section-true_false d-none">
                            <p class="small text-muted mb-2">Select the correct statement truth value:</p>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[${newIdx}][tf_correct]" id="tf_true_${newIdx}" value="true" checked>
                                    <label class="form-check-label text-success fw-bold" for="tf_true_${newIdx}"><i class="fas fa-check me-1"></i> True</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[${newIdx}][tf_correct]" id="tf_false_${newIdx}" value="false">
                                    <label class="form-check-label text-danger fw-bold" for="tf_false_${newIdx}"><i class="fas fa-times me-1"></i> False</label>
                                </div>
                            </div>
                        </div>

                        <!-- Yes / No -->
                        <div class="type-section type-section-yes_no d-none">
                            <p class="small text-muted mb-2">Select the correct response:</p>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[${newIdx}][yn_correct]" id="yn_yes_${newIdx}" value="yes" checked>
                                    <label class="form-check-label text-success fw-bold" for="yn_yes_${newIdx}"><i class="fas fa-thumbs-up me-1"></i> Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[${newIdx}][yn_correct]" id="yn_no_${newIdx}" value="no">
                                    <label class="form-check-label text-danger fw-bold" for="yn_no_${newIdx}"><i class="fas fa-thumbs-down me-1"></i> No</label>
                                </div>
                            </div>
                        </div>

                        <!-- Fill in the Blank -->
                        <div class="type-section type-section-fill_blank d-none">
                            <label class="form-label small text-muted text-uppercase fw-bold">Accepted Correct Answers</label>
                            <textarea name="questions[${newIdx}][accepted_answers]" rows="2" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Comma-separated accepted answers (e.g. CPU, processor)"></textarea>
                        </div>

                        <!-- Short Answer -->
                        <div class="type-section type-section-short_answer d-none">
                            <label class="form-label small text-muted text-uppercase fw-bold">Model Answer / Evaluation Rubric Guidelines</label>
                            <textarea name="questions[${newIdx}][sa_model_answer]" rows="2" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Provide reference solution or key concepts for teacher grading..."></textarea>
                            <div class="alert alert-info py-1 px-2 small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i> <strong>Subjective Question:</strong> Results will show pending review until the teacher marks it.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Explanation -->
                <div class="mt-2">
                    <label class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-lightbulb text-warning me-1"></i> Explanation / Feedback (Optional)</label>
                    <input type="text" name="questions[${newIdx}][explanation]" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Explain why the answer is correct...">
                </div>
            </div>
        </div>
    `;

    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', newCardHtml);
    switchCardQuestionType(newIdx, prevType);
    updateQuestionsSummary();

    // Scroll to the new card and focus statement textarea
    const newTextarea = document.getElementById('q_statement_' + newIdx);
    if (newTextarea) {
        newTextarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => newTextarea.focus(), 300);
    }
}

// Remove a question card
function removeQuestionCard(idx) {
    const card = document.getElementById('question_card_' + idx);
    if (card) {
        card.remove();
        updateQuestionsSummary();
        syncAllSubsequent(0);
    }
}

// Re-index number badges and total count
function updateQuestionsSummary() {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    cards.forEach((card, i) => {
        const badge = card.querySelector('.question-number-badge');
        if (badge) {
            badge.textContent = 'Question #' + (i + 1);
        }
    });

    const count = cards.length;
    const countElem = document.getElementById('summaryQuestionCount');
    if (countElem) {
        countElem.textContent = count;
    }
}

// Dynamic categories loader
function loadCategories(subjectId) {
    const catSelect = document.getElementById('category_id');
    if (!catSelect) return;
    catSelect.innerHTML = '<option value="">Loading categories...</option>';
    
    fetch('<?= url("api-categories") ?>&subject_id=' + subjectId)
        .then(res => res.json())
        .then(data => {
            catSelect.innerHTML = '<option value="">General / None</option>';
            data.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.id;
                opt.textContent = cat.name;
                catSelect.appendChild(opt);
            });
        })
        .catch(() => {
            catSelect.innerHTML = '<option value="">General / None</option>';
        });
}

// Client-side initialization and submit validation
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Card 0 inputs based on its selected type
    const card0Type = document.querySelector('select[name="questions[0][question_type]"]');
    const initType = card0Type ? card0Type.value : 'single_choice';
    switchCardQuestionType(0, initType);

    // Form submit validation to prevent silent browser blocking and provide clear messages
    const form = document.getElementById('bulkQuestionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const cards = Array.from(document.querySelectorAll('.question-card'));
            let hasValidStatement = false;

            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                const idx = card.dataset.index;
                const statementElem = card.querySelector(`textarea[name="questions[${idx}][question_text]"]`);
                const typeElem = card.querySelector(`select[name="questions[${idx}][question_type]"]`);
                const statement = statementElem ? statementElem.value.trim() : '';
                const type = typeElem ? typeElem.value : 'single_choice';

                if (statement !== '') {
                    hasValidStatement = true;

                    if (type === 'single_choice') {
                        const optInputs = Array.from(card.querySelectorAll(`input[name^="questions[${idx}][sc_options]"], input[name^="questions[${idx}][options]"]`));
                        const filledOpts = optInputs.filter(inp => !inp.disabled && inp.value.trim() !== '');
                        if (filledOpts.length < 2) {
                            alert(`Question #${i + 1} (${QUESTION_TYPES[type] || 'Single Choice'}): Please provide at least 2 answer choices.`);
                            if (statementElem) statementElem.focus();
                            e.preventDefault();
                            return false;
                        }
                    } else if (type === 'multiple_choice') {
                        const optInputs = Array.from(card.querySelectorAll(`input[name^="questions[${idx}][mc_options]"], input[name^="questions[${idx}][options]"]`));
                        const filledOpts = optInputs.filter(inp => !inp.disabled && inp.value.trim() !== '');
                        if (filledOpts.length < 2) {
                            alert(`Question #${i + 1} (${QUESTION_TYPES[type] || 'Multiple Choice'}): Please provide at least 2 answer choices.`);
                            if (statementElem) statementElem.focus();
                            e.preventDefault();
                            return false;
                        }
                        const checked = card.querySelectorAll(`input[name="questions[${idx}][correct_multi][]"]:checked`);
                        if (checked.length === 0) {
                            const firstCheck = card.querySelector(`input[name="questions[${idx}][correct_multi][]"]`);
                            if (firstCheck) firstCheck.checked = true;
                        }
                    }
                }
            }

            if (!hasValidStatement) {
                alert('Please enter a question statement for at least one question.');
                const firstStatement = document.getElementById('q_statement_0');
                if (firstStatement) firstStatement.focus();
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>