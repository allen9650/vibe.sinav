<?php
/**
 * PTM Assessment System — Teacher Assessment Preview
 */

$id = (int)($_GET['id'] ?? 0);
$assessment = AssessmentService::getAssessment($id);

if (!$assessment) {
    die("Assessment not found.");
}

$questions = AssessmentService::getAssessmentQuestions($id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= e($assessment['title']) ?> — PTM Assessment</title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        :root,
        [data-bs-theme="light"] {
            --preview-bg: #f8fafc;
            --preview-text: #0f172a;
            --preview-banner-bg: #ffffff;
            --preview-card-bg: #ffffff;
            --preview-border: #cbd5e1;
            --preview-opt-bg: #f8fafc;
            --preview-opt-border: #cbd5e1;
            --preview-solution-bg: #f0fdf4;
        }
        [data-bs-theme="dark"] {
            --preview-bg: #090d16;
            --preview-text: #f8fafc;
            --preview-banner-bg: #0f172a;
            --preview-card-bg: #111827;
            --preview-border: #1e293b;
            --preview-opt-bg: #172033;
            --preview-opt-border: #334155;
            --preview-solution-bg: rgba(16, 185, 129, 0.1);
        }

        body { 
            background-color: var(--preview-bg); 
            color: var(--preview-text); 
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .preview-banner { 
            background: var(--preview-banner-bg); 
            border-bottom: 2px solid var(--primary, #4f46e5); 
            box-shadow: var(--shadow-sm);
        }
        .q-card { 
            background: var(--preview-card-bg); 
            border: 1px solid var(--preview-border); 
            border-radius: 10px; 
            margin-bottom: 1.5rem; 
            box-shadow: var(--shadow-sm);
        }
        .solution-box { 
            background: var(--preview-solution-bg); 
            border-left: 4px solid #10b981; 
            border-radius: 6px;
            padding: 0.85rem 1rem; 
            margin-top: 1rem; 
        }
        .option-preview-box {
            background-color: var(--preview-opt-bg);
            border: 1px solid var(--preview-opt-border);
            border-radius: 8px;
        }
    </style>
</head>
<body>

<header class="preview-banner py-3 px-4 mb-4">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark"><i class="fas fa-eye me-1"></i>TEACHER PREVIEW MODE</span>
            <h4 class="mb-0 fw-bold"><?= e($assessment['title']) ?></h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggleKeys" onchange="toggleAnswers(this.checked)" checked>
                <label class="form-check-label text-primary small fw-semibold" for="toggleKeys">Show Solution Key</label>
            </div>
            <!-- Quick Theme Switcher Button -->
            <button type="button" class="btn btn-sm btn-outline-secondary theme-quick-toggle d-flex align-items-center gap-1" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i> Close Preview</button>
        </div>
    </div>
</header>

<main class="container py-2">
    <!-- Exam Identity Card -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-4 text-center">
            <h5 class="fw-bold text-primary mb-1"><?= e($assessment['campus_name']) ?></h5>
            <h3 class="fw-bold mb-2"><?= e($assessment['title']) ?></h3>
            <div class="d-flex flex-wrap justify-content-center gap-4 text-muted small">
                <span><i class="fas fa-user-tie me-1 text-info"></i>Teacher: <strong><?= e($assessment['teacher_name']) ?></strong></span>
                <span><i class="fas fa-book me-1 text-warning"></i>Subject: <strong><?= e($assessment['subject_name']) ?></strong></span>
                <span><i class="fas fa-graduation-cap me-1 text-secondary"></i>Class: <strong><?= e($assessment['class_grade']) ?></strong></span>
                <span><i class="fas fa-clock me-1 text-primary"></i>Duration: <strong><?= (int)$assessment['duration_minutes'] ?> Minutes</strong></span>
                <span><i class="fas fa-star me-1 text-success"></i>Total Marks: <strong><?= (float)$assessment['total_marks'] ?></strong></span>
                <span><i class="fas fa-list-ol me-1 text-info"></i>Questions: <strong><?= count($questions) ?></strong></span>
            </div>
            <?php if (!empty($assessment['instructions'])): ?>
                <hr class="my-3">
                <div class="text-start p-3 rounded border font-monospace small bg-body-tertiary">
                    <strong class="text-primary">Candidate Instructions:</strong><br>
                    <?= nl2br(e($assessment['instructions'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Question Cards -->
    <?php if (empty($questions)): ?>
        <div class="alert alert-warning text-center">This assessment does not have any questions yet.</div>
    <?php else: ?>
        <?php foreach ($questions as $i => $q): 
            $fullQ = QuestionService::getQuestion((int)$q['question_id']);
        ?>
        <div class="q-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6">Question <?= $i + 1 ?> of <?= count($questions) ?></span>
                    <span class="badge bg-dark border border-secondary text-info"><?= e(QuestionService::TYPES[$q['question_type']] ?? $q['question_type']) ?></span>
                    <span class="badge bg-dark border border-secondary"><?= ucfirst($q['difficulty']) ?></span>
                </div>
                <div class="text-success fw-bold">
                    +<?= (float)$q['active_marks'] ?> Marks
                </div>
            </div>

            <div class="fs-5 mb-3">
                <?= nl2br(e($q['question_text'])) ?>
            </div>

            <?php if (!empty($fullQ['image_path'])): ?>
                <div class="mb-3 text-center">
                    <img src="<?= asset($fullQ['image_path']) ?>" alt="Question Diagram" class="img-fluid rounded border border-secondary" style="max-height: 280px;">
                </div>
            <?php endif; ?>

            <!-- Answer Options Display -->
            <div class="options-view mb-3">
                <?php if ($q['question_type'] === 'single_choice' || $q['question_type'] === 'multiple_choice'): ?>
                    <div class="row g-2">
                        <?php foreach ($fullQ['options'] as $opt): ?>
                        <div class="col-md-6">
                            <div class="p-2 rounded border option-preview-box d-flex align-items-center gap-2 option-row <?= !empty($opt['is_correct']) ? 'is-correct-opt' : '' ?>">
                                <i class="far <?= $q['question_type'] === 'single_choice' ? 'fa-circle' : 'fa-square' ?> text-muted"></i>
                                <span><?= e($opt['option_text']) ?></span>
                                <?php if (!empty($opt['is_correct'])): ?>
                                    <span class="badge bg-success ms-auto solution-tag"><i class="fas fa-check"></i> Correct</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['question_type'] === 'true_false' || $q['question_type'] === 'yes_no'): ?>
                    <div class="d-flex gap-3">
                        <?php foreach ($fullQ['options'] as $opt): ?>
                        <div class="p-2 px-3 rounded border option-preview-box d-flex align-items-center gap-2 option-row <?= !empty($opt['is_correct']) ? 'is-correct-opt' : '' ?>">
                            <i class="far fa-circle text-muted"></i>
                            <span class="fw-bold"><?= e($opt['option_text']) ?></span>
                            <?php if (!empty($opt['is_correct'])): ?>
                                <span class="badge bg-success ms-2 solution-tag"><i class="fas fa-check"></i> Correct</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['question_type'] === 'fill_blank'): ?>
                    <div class="p-3 option-preview-box rounded border">
                        <input type="text" class="form-control text-muted" placeholder="Candidate will type text answer here..." disabled>
                        <div class="mt-2 text-success small solution-tag">
                            <i class="fas fa-key me-1"></i> Accepted answers: <strong><?= e($fullQ['options'][0]['accepted_answers'] ?? '') ?></strong>
                        </div>
                    </div>

                <?php elseif ($q['question_type'] === 'matching'): ?>
                    <div class="p-3 option-preview-box rounded border">
                        <div class="row g-2">
                            <?php foreach ($fullQ['options'] as $opt): ?>
                            <div class="col-md-6">
                                <div class="p-2 rounded border option-preview-box d-flex justify-content-between align-items-center">
                                    <span><?= e($opt['option_text']) ?></span>
                                    <i class="fas fa-arrow-right text-muted mx-2"></i>
                                    <span class="badge bg-primary-subtle text-primary border border-primary"><?= e($opt['match_target']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($q['question_type'] === 'ordering'): ?>
                    <div class="p-3 option-preview-box rounded border">
                        <ol class="mb-0 ps-3">
                            <?php foreach ($fullQ['options'] as $opt): ?>
                                <li class="mb-1"><?= e($opt['option_text']) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                <?php elseif ($q['question_type'] === 'typing'): ?>
                    <div class="p-3 option-preview-box rounded border border-warning font-monospace text-muted small">
                        <div class="text-warning fw-bold mb-1"><i class="fas fa-keyboard me-1"></i>Typing Passage Test: <?= e($fullQ['typing_paragraph']['title'] ?? 'Passage') ?></div>
                        <?= nl2br(e($fullQ['typing_paragraph']['content'] ?? 'Typing content...')) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Solution & Explanation box -->
            <?php if (!empty($q['explanation'])): ?>
            <div class="solution-box solution-tag">
                <div class="text-success small fw-bold mb-1"><i class="fas fa-lightbulb me-1"></i>Solution Explanation:</div>
                <div class="small text-muted"><?= nl2br(e($q['explanation'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<script>
function toggleAnswers(show) {
    document.querySelectorAll('.solution-tag').forEach(el => {
        el.style.display = show ? '' : 'none';
    });
    document.querySelectorAll('.is-correct-opt').forEach(el => {
        if (show) {
            el.classList.add('border-success');
        } else {
            el.classList.remove('border-success');
        }
    });
}
</script>

</body>
</html>

