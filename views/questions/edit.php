<?php
/**
 * PTM Assessment System — Edit Question View
 */

$id = (int)($_GET['id'] ?? 0);
$question = QuestionService::getQuestion($id);

if (!$question) {
    Session::flash('error', 'Question not found.');
    redirectTo('questions');
}

$subjects = QuestionService::getSubjects();
$paragraphs = [];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $text = trim($_POST['question_text'] ?? '');
    $type = trim($_POST['question_type'] ?? 'single_choice');

    if ($text === '') {
        $errors['question_text'] = 'Question text cannot be empty.';
    }

    if (empty($errors)) {
        try {
            $options = [];

            switch ($type) {
                case 'single_choice':
                case 'multiple_choice':
                    $opts = $_POST['options'] ?? [];
                    $correctIndex = $_POST['correct_single'] ?? 0;
                    $correctMulti = $_POST['correct_multi'] ?? [];

                    foreach ($opts as $idx => $optText) {
                        $isCorrect = 0;
                        if ($type === 'single_choice') {
                            $isCorrect = ((int)$correctIndex === (int)$idx) ? 1 : 0;
                        } else {
                            $isCorrect = in_array((string)$idx, (array)$correctMulti, true) ? 1 : 0;
                        }
                        $options[] = [
                            'text' => trim($optText),
                            'is_correct' => $isCorrect,
                        ];
                    }
                    break;

                case 'true_false':
                    $options = ['correct' => $_POST['tf_correct'] ?? 'true'];
                    break;

                case 'yes_no':
                    $options = ['correct' => $_POST['yn_correct'] ?? 'yes'];
                    break;

                case 'fill_blank':
                    $options = ['accepted_answers' => trim($_POST['accepted_answers'] ?? '')];
                    break;

                case 'matching':
                    $items = $_POST['match_item'] ?? [];
                    $targets = $_POST['match_target'] ?? [];
                    foreach ($items as $idx => $item) {
                        $options[] = [
                            'item' => trim($item),
                            'target' => trim($targets[$idx] ?? ''),
                        ];
                    }
                    break;

                case 'ordering':
                    $orderItems = $_POST['order_items'] ?? [];
                    foreach ($orderItems as $item) {
                        if (trim($item) !== '') {
                            $options[] = trim($item);
                        }
                    }
                    break;

                case 'typing':
                    break;
            }

            $updateData = [
                'subject_id'          => (int)$_POST['subject_id'],
                'category_id'         => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'question_text'       => $text,
                'question_type'       => $type,
                'difficulty'          => $_POST['difficulty'] ?? 'medium',
                'marks'               => max(0.25, (float)($_POST['marks'] ?? 1.00)),
                'negative_marks'      => max(0.00, (float)($_POST['negative_marks'] ?? 0.00)),
                'partial_scoring'     => !empty($_POST['partial_scoring']) ? 1 : 0,
                'explanation'         => trim($_POST['explanation'] ?? ''),
                'typing_paragraph_id' => !empty($_POST['typing_paragraph_id']) ? (int)$_POST['typing_paragraph_id'] : null,
                'status'              => $_POST['status'] ?? 'active',
                'remove_image'        => !empty($_POST['remove_image']),
            ];

            $imageFile = $_FILES['question_image'] ?? null;

            QuestionService::updateQuestion($id, $updateData, $options, $imageFile);
            Session::flash('success', "Question #$id updated successfully.");
            redirectTo('questions');
        } catch (Exception $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

// Prepare current options
$existingOptions = $question['options'] ?? [];
$categories = QuestionService::getCategoriesBySubject((int)$question['subject_id']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="fas fa-edit text-primary me-2"></i>Edit Question #<?= $question['id'] ?></h4>
        <p class="text-muted small mb-0">Modify question details, answer options, and scoring rules.</p>
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

<form method="POST" action="<?= url('questions-edit&id=' . $question['id']) ?>" enctype="multipart/form-data" id="questionForm">
    <?= CSRF::field() ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-pen me-2 text-primary"></i>Question Content</h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label for="question_type" class="form-label small text-muted text-uppercase fw-bold">Question Type</label>
                        <select name="question_type" id="question_type" class="form-select bg-dark text-light border-secondary" onchange="switchQuestionType(this.value)">
                            <?php foreach (QuestionService::TYPES as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $question['question_type'] === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="question_text" class="form-label small text-muted text-uppercase fw-bold">Question Statement <span class="text-danger">*</span></label>
                        <textarea name="question_text" id="question_text" rows="3" 
                                  class="form-control bg-dark text-light border-secondary <?= isset($errors['question_text']) ? 'is-invalid' : '' ?>" 
                                  required><?= e($question['question_text']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="question_image" class="form-label small text-muted text-uppercase fw-bold">
                            <i class="fas fa-image me-1 text-info"></i> Attached Image
                        </label>
                        <?php if (!empty($question['image_path'])): ?>
                            <div class="d-flex align-items-center gap-3 p-2 bg-black rounded border border-secondary mb-2">
                                <img src="<?= asset($question['image_path']) ?>" alt="Question Image" style="max-height: 80px; max-width: 120px;" class="rounded">
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                        <label class="form-check-label text-danger small" for="remove_image">Remove this image</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="question_image" id="question_image" class="form-control bg-dark text-light border-secondary" accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Dynamic Options Section -->
            <div class="card bg-dark border-secondary mb-4" id="optionsCard">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-list-check me-2 text-primary"></i>Answer Options</h6>
                </div>
                <div class="card-body p-3">

                    <!-- Single Choice -->
                    <div id="type_single_choice" class="type-section <?= $question['question_type'] !== 'single_choice' ? 'd-none' : '' ?>">
                        <div id="singleChoiceContainer">
                            <?php
                            $scOpts = $question['question_type'] === 'single_choice' ? $existingOptions : [];
                            for ($i = 0; $i < 4; $i++):
                                $textVal = $scOpts[$i]['option_text'] ?? '';
                                $isCorr = !empty($scOpts[$i]['is_correct']);
                            ?>
                            <div class="input-group mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="radio" name="correct_single" value="<?= $i ?>" <?= $isCorr ? 'checked' : ($i === 0 && empty($scOpts) ? 'checked' : '') ?>>
                                </div>
                                <input type="text" name="options[<?= $i ?>]" class="form-control bg-dark text-light border-secondary" placeholder="Option <?= $i + 1 ?>" value="<?= e($textVal) ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Multiple Choice -->
                    <div id="type_multiple_choice" class="type-section <?= $question['question_type'] !== 'multiple_choice' ? 'd-none' : '' ?>">
                        <div id="multiChoiceContainer">
                            <?php
                            $mcOpts = $question['question_type'] === 'multiple_choice' ? $existingOptions : [];
                            for ($i = 0; $i < 4; $i++):
                                $textVal = $mcOpts[$i]['option_text'] ?? '';
                                $isCorr = !empty($mcOpts[$i]['is_correct']);
                            ?>
                            <div class="input-group mb-2">
                                <div class="input-group-text bg-dark border-secondary">
                                    <input class="form-check-input mt-0" type="checkbox" name="correct_multi[]" value="<?= $i ?>" <?= $isCorr ? 'checked' : '' ?>>
                                </div>
                                <input type="text" name="options[<?= $i ?>]" class="form-control bg-dark text-light border-secondary" placeholder="Option <?= $i + 1 ?>" value="<?= e($textVal) ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- True / False -->
                    <div id="type_true_false" class="type-section <?= $question['question_type'] !== 'true_false' ? 'd-none' : '' ?>">
                        <?php
                        $tfVal = 'true';
                        if ($question['question_type'] === 'true_false') {
                            foreach ($existingOptions as $opt) {
                                if (!empty($opt['is_correct']) && strtolower($opt['option_text']) === 'false') {
                                    $tfVal = 'false';
                                }
                            }
                        }
                        ?>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="tf_true" value="true" <?= $tfVal === 'true' ? 'checked' : '' ?>>
                                <label class="form-check-label text-success fw-bold" for="tf_true"><i class="fas fa-check me-1"></i> True</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="tf_false" value="false" <?= $tfVal === 'false' ? 'checked' : '' ?>>
                                <label class="form-check-label text-danger fw-bold" for="tf_false"><i class="fas fa-times me-1"></i> False</label>
                            </div>
                        </div>
                    </div>

                    <!-- Yes / No -->
                    <div id="type_yes_no" class="type-section <?= $question['question_type'] !== 'yes_no' ? 'd-none' : '' ?>">
                        <?php
                        $ynVal = 'yes';
                        if ($question['question_type'] === 'yes_no') {
                            foreach ($existingOptions as $opt) {
                                if (!empty($opt['is_correct']) && strtolower($opt['option_text']) === 'no') {
                                    $ynVal = 'no';
                                }
                            }
                        }
                        ?>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="yn_correct" id="yn_yes" value="yes" <?= $ynVal === 'yes' ? 'checked' : '' ?>>
                                <label class="form-check-label text-success fw-bold" for="yn_yes"><i class="fas fa-thumbs-up me-1"></i> Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="yn_correct" id="yn_no" value="no" <?= $ynVal === 'no' ? 'checked' : '' ?>>
                                <label class="form-check-label text-danger fw-bold" for="yn_no"><i class="fas fa-thumbs-down me-1"></i> No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Fill in Blank -->
                    <div id="type_fill_blank" class="type-section <?= $question['question_type'] !== 'fill_blank' ? 'd-none' : '' ?>">
                        <label for="accepted_answers" class="form-label small text-muted text-uppercase fw-bold">Accepted Correct Answers</label>
                        <textarea name="accepted_answers" id="accepted_answers" rows="2" class="form-control bg-dark text-light border-secondary"><?= e($existingOptions[0]['accepted_answers'] ?? '') ?></textarea>
                    </div>

                    <!-- Matching -->
                    <div id="type_matching" class="type-section <?= $question['question_type'] !== 'matching' ? 'd-none' : '' ?>">
                        <div id="matchingContainer">
                            <?php
                            $matchOpts = $question['question_type'] === 'matching' ? $existingOptions : [];
                            for ($i = 0; $i < 3; $i++):
                                $item = $matchOpts[$i]['option_text'] ?? '';
                                $target = $matchOpts[$i]['match_target'] ?? '';
                            ?>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <input type="text" name="match_item[<?= $i ?>]" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= e($item) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="match_target[<?= $i ?>]" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= e($target) ?>">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Ordering -->
                    <div id="type_ordering" class="type-section <?= $question['question_type'] !== 'ordering' ? 'd-none' : '' ?>">
                        <div id="orderingContainer">
                            <?php
                            $ordOpts = $question['question_type'] === 'ordering' ? $existingOptions : [];
                            for ($i = 0; $i < 4; $i++):
                                $stepVal = $ordOpts[$i]['option_text'] ?? '';
                            ?>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-secondary border-secondary text-light"><?= $i + 1 ?></span>
                                <input type="text" name="order_items[<?= $i ?>]" class="form-control bg-dark text-light border-secondary" value="<?= e($stepVal) ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Typing -->
                    <div id="type_typing" class="type-section <?= $question['question_type'] !== 'typing' ? 'd-none' : '' ?>">
                        <select name="typing_paragraph_id" class="form-select bg-dark text-light border-secondary">
                            <?php foreach ($paragraphs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (int)$question['typing_paragraph_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['title']) ?> (<?= $p['word_count'] ?> words, <?= ucfirst($p['difficulty']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>

            <!-- Solution / Explanation -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-lightbulb me-2 text-warning"></i>Solution Explanation</h6>
                </div>
                <div class="card-body p-3">
                    <textarea name="explanation" rows="2" class="form-control bg-dark text-light border-secondary"><?= e($question['explanation']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-cog me-2 text-primary"></i>Metadata & Marks</h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label for="subject_id" class="form-label small text-muted text-uppercase fw-bold">Subject</label>
                        <select name="subject_id" id="subject_id" class="form-select bg-dark text-light border-secondary" required onchange="loadCategories(this.value)">
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (int)$question['subject_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label small text-muted text-uppercase fw-bold">Topic / Category</label>
                        <select name="category_id" id="category_id" class="form-select bg-dark text-light border-secondary">
                            <option value="">General / None</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (int)$question['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Global Scope Indicator -->
                    <div class="mb-3 p-2 rounded bg-secondary bg-opacity-10 border border-secondary text-secondary small">
                        <i class="fas fa-globe text-info me-1"></i> <span class="text-light fw-semibold">Global Question:</span> Shared across all classes, sections, and exams.
                    </div>

                    <div class="mb-3">
                        <label for="difficulty" class="form-label small text-muted text-uppercase fw-bold">Difficulty</label>
                        <select name="difficulty" id="difficulty" class="form-select bg-dark text-light border-secondary">
                            <option value="easy" <?= $question['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                            <option value="medium" <?= $question['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="hard" <?= $question['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="marks" class="form-label small text-muted text-uppercase fw-bold">Marks</label>
                            <input type="number" step="0.25" min="0.25" name="marks" id="marks" 
                                   class="form-control bg-dark text-light border-secondary" value="<?= (float)$question['marks'] ?>" required>
                        </div>
                        <div class="col-6">
                            <label for="negative_marks" class="form-label small text-muted text-uppercase fw-bold">Negative Marks</label>
                            <input type="number" step="0.25" min="0" name="negative_marks" id="negative_marks" 
                                   class="form-control bg-dark text-light border-secondary" value="<?= (float)$question['negative_marks'] ?>">
                        </div>
                    </div>

                    <div class="form-check mb-4" id="partialScoringBlock">
                        <input class="form-check-input" type="checkbox" name="partial_scoring" id="partial_scoring" value="1" <?= !empty($question['partial_scoring']) ? 'checked' : '' ?>>
                        <label class="form-check-label small text-light" for="partial_scoring">
                            Enable Partial Credit Scoring
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-save me-1"></i> Update Question
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function switchQuestionType(type) {
    document.querySelectorAll('.type-section').forEach(el => el.classList.add('d-none'));
    const target = document.getElementById('type_' + type);
    if (target) target.classList.remove('d-none');

    const partialBlock = document.getElementById('partialScoringBlock');
    if (type === 'multiple_choice' || type === 'matching') {
        partialBlock.classList.remove('d-none');
    } else {
        partialBlock.classList.add('d-none');
    }
}

function loadCategories(subjectId) {
    const catSelect = document.getElementById('category_id');
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
</script>

