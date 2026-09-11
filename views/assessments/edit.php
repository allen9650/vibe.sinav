<?php
/**
 * PTM Assessment System — Edit Assessment Settings View
 * 4 Structured Sections:
 * 1. Basic Information (Title, Type, Subject, Class, Section, Campus, Teacher, Description)
 * 2. Schedule (Start Date, Start Time, End Date, End Time, Duration [Mandatory])
 * 3. Assessment Settings (Total Marks, Passing Marks, Shuffle Questions, Shuffle Options)
 * 4. Instructions (Student Instructions, Rules / Guidelines)
 */

$id = (int)($_GET['id'] ?? 0);
$assessment = AssessmentService::getAssessment($id);

if (!$assessment) {
    Session::flash('error', 'Assessment not found.');
    redirectTo('assessments');
}

$subjects = QuestionService::getSubjects();
$campuses = QuestionService::getCampuses();
$teachers = AssessmentService::getTeachers();
$classes  = AssessmentService::getStandardClasses();
$sections = AssessmentService::getStandardSectionsList();
$types    = AssessmentService::getAssessmentTypes();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? 'update';
    if ($action === 'delete') {
        try {
            AssessmentService::deleteAssessment($id);
            Session::flash('success', 'Assessment was successfully deleted.');
            redirectTo('assessments');
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    } else {
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $errors['title'] = 'Assessment title cannot be empty.';
        }

        $duration = (int)($_POST['duration_minutes'] ?? 0);
        if ($duration <= 0) {
            $errors['duration_minutes'] = 'Duration is mandatory and must be at least 1 minute.';
        }

        if (empty($errors)) {
            try {
                AssessmentService::updateAssessment($id, $_POST);
                Session::flash('success', 'Assessment settings updated successfully.');
                redirectTo('assessments');
            } catch (Throwable $e) {
                $errors['general'] = $e->getMessage();
            }
        }

        // Overlay submitted POST values onto $assessment so form inputs are preserved on error
        foreach ($_POST as $k => $v) {
            if (is_scalar($v)) {
                $assessment[$k] = $v;
            }
        }
    }
}

// Prepare current values
$currentClass = $assessment['target_class'] ?? 'Grade 1';
$isCustomClass = !empty($currentClass) && !array_key_exists($currentClass, $classes);

$currentSection = $assessment['section'] ?? 'Section A';
$isCustomSection = !empty($currentSection) && !in_array($currentSection, $sections, true);

$currentType = $assessment['assessment_type'] ?? 'quiz';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('assessments') ?>" class="text-decoration-none text-muted">Assessments</a></li>
                <li class="breadcrumb-item active text-primary" aria-current="page">Edit Settings</li>
            </ol>
        </nav>
        <h4 class="mb-1 fw-bold text-light"><i class="fas fa-cog text-primary me-2"></i>Edit Assessment Settings</h4>
        <p class="text-muted small mb-0">Modify identity, schedule window, grading parameters, and exam instructions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('assessments-builder&id=' . $assessment['id']) ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-puzzle-piece me-1"></i> Question Builder
        </a>
        <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Assessments
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= e($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('assessments-edit&id=' . $assessment['id']) ?>" id="editAssessmentForm">
    <?= CSRF::field() ?>

    <div class="row g-4">
        <!-- ================================================================= -->
        <!-- 1. BASIC INFORMATION                                              -->
        <!-- ================================================================= -->
        <div class="col-12">
            <div class="card bg-dark border-secondary shadow-sm">
                <div class="card-header bg-dark border-secondary p-3 d-flex align-items-center">
                    <span class="badge bg-primary me-2 px-2 py-1">1</span>
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-info-circle text-primary me-2"></i>Basic Information</h6>
                </div>
                <div class="card-body p-4">
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label small text-muted text-uppercase fw-bold">Assessment Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control bg-dark text-light border-secondary <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($assessment['title']) ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Type, Subject, Class, Section -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="assessment_type" class="form-label small text-muted text-uppercase fw-bold">Assessment Type</label>
                            <select name="assessment_type" id="assessment_type" class="form-select bg-dark text-light border-secondary">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $currentType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="subject_id" class="form-label small text-muted text-uppercase fw-bold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="subject_id" class="form-select bg-dark text-light border-secondary" required>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (int)$assessment['subject_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="class" class="form-label small text-muted text-uppercase fw-bold">Class <span class="text-danger">*</span></label>
                            <select name="class" id="class" class="form-select bg-dark text-light border-secondary" required onchange="toggleCustomClass(this)">
                                <?php foreach ($classes as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= ($currentClass === $val && !$isCustomClass) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                                <?php if ($isCustomClass): ?>
                                    <option value="<?= e($currentClass) ?>" selected><?= e($currentClass) ?> (Custom)</option>
                                <?php endif; ?>
                                <option value="__custom__" <?= $isCustomClass ? 'selected' : '' ?>>+ Custom Class / Level...</option>
                            </select>
                            <div id="custom_class_wrap" class="mt-2 <?= $isCustomClass ? '' : 'd-none' ?>">
                                <input type="text" name="custom_class" id="custom_class" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Enter custom class" value="<?= $isCustomClass ? e($currentClass) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="section" class="form-label small text-muted text-uppercase fw-bold">Section <span class="text-danger">*</span></label>
                            <select name="section" id="section" class="form-select bg-dark text-light border-secondary" required onchange="toggleCustomSection(this)">
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= e($sec) ?>" <?= ($currentSection === $sec && !$isCustomSection) ? 'selected' : '' ?>><?= e($sec) ?></option>
                                <?php endforeach; ?>
                                <?php if ($isCustomSection): ?>
                                    <option value="<?= e($currentSection) ?>" selected><?= e($currentSection) ?> (Custom)</option>
                                <?php endif; ?>
                                <option value="__custom__" <?= $isCustomSection ? 'selected' : '' ?>>+ Custom Section...</option>
                            </select>
                            <div id="custom_section_wrap" class="mt-2 <?= $isCustomSection ? '' : 'd-none' ?>">
                                <input type="text" name="custom_section" id="custom_section" class="form-control bg-dark text-light border-secondary form-control-sm" placeholder="Enter custom section" value="<?= $isCustomSection ? e($currentSection) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Campus & Teacher -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="campus_id" class="form-label small text-muted text-uppercase fw-bold">Campus</label>
                            <?php if (Auth::isAdmin()): ?>
                                <select name="campus_id" id="campus_id" class="form-select bg-dark text-light border-secondary">
                                    <?php foreach ($campuses as $camp): ?>
                                        <option value="<?= $camp['id'] ?>" <?= (int)$assessment['campus_id'] === (int)$camp['id'] ? 'selected' : '' ?>><?= e($camp['name']) ?> (<?= e($camp['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-dark text-muted border-secondary" value="<?= e($assessment['campus_name'] ?? 'Assigned Campus') ?>" readonly>
                                <input type="hidden" name="campus_id" value="<?= $assessment['campus_id'] ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label small text-muted text-uppercase fw-bold">Teacher / Instructor</label>
                            <select name="teacher_id" id="teacher_id" class="form-select bg-dark text-light border-secondary">
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= (int)$assessment['created_by'] === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= e(ucfirst($t['username'])) ?> (<?= e(ucfirst(str_replace('-', ' ', $t['role']))) ?><?= !empty($t['campus_name']) ? ' - ' . e($t['campus_name']) : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-0">
                        <label for="description" class="form-label small text-muted text-uppercase fw-bold">Description</label>
                        <textarea name="description" id="description" rows="2" class="form-control bg-dark text-light border-secondary"><?= e($assessment['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 2. SCHEDULE                                                       -->
        <!-- ================================================================= -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-header bg-dark border-secondary p-3 d-flex align-items-center">
                    <span class="badge bg-info text-dark me-2 px-2 py-1">2</span>
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-calendar-alt text-info me-2"></i>Schedule</h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Set optional opening and closing dates/times. <strong>Duration is strictly mandatory</strong>.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="start_date" class="form-label small text-muted text-uppercase fw-bold">Start Date <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control bg-dark text-light border-secondary" value="<?= e($assessment['start_date'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="start_time" class="form-label small text-muted text-uppercase fw-bold">Start Time <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="time" name="start_time" id="start_time" class="form-control bg-dark text-light border-secondary" value="<?= e($assessment['start_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="end_date" class="form-label small text-muted text-uppercase fw-bold">End Date <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="date" name="end_date" id="end_date" class="form-control bg-dark text-light border-secondary" value="<?= e($assessment['end_date'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="end_time" class="form-label small text-muted text-uppercase fw-bold">End Time <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="time" name="end_time" id="end_time" class="form-control bg-dark text-light border-secondary" value="<?= e($assessment['end_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="duration_minutes" class="form-label small text-muted text-uppercase fw-bold d-flex justify-content-between">
                            <span>Duration (Minutes) <span class="text-danger">*</span></span>
                            <span class="badge bg-danger text-uppercase" style="font-size: 0.65rem;">Mandatory (Controls Timer)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-stopwatch"></i></span>
                            <input type="number" min="1" max="300" name="duration_minutes" id="duration_minutes" class="form-control bg-dark text-light border-secondary <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" value="<?= (int)$assessment['duration_minutes'] ?>" required>
                            <span class="input-group-text bg-secondary border-secondary text-light">Minutes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 3. ASSESSMENT SETTINGS                                            -->
        <!-- ================================================================= -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-header bg-dark border-secondary p-3 d-flex align-items-center">
                    <span class="badge bg-warning text-dark me-2 px-2 py-1">3</span>
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-sliders-h text-warning me-2"></i>Assessment Settings</h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Define grading parameters and test delivery behavior.</p>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="total_marks" class="form-label small text-muted text-uppercase fw-bold">Total Marks</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-award"></i></span>
                                <input type="number" step="0.5" min="1" name="total_marks" id="total_marks" class="form-control bg-dark text-light border-secondary" value="<?= (float)$assessment['total_marks'] ?>" oninput="updatePassingRate()">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="passing_marks" class="form-label small text-muted text-uppercase fw-bold">Passing Marks</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-check-circle"></i></span>
                                <input type="number" step="0.5" min="0" name="passing_marks" id="passing_marks" class="form-control bg-dark text-light border-secondary" value="<?= (float)($assessment['passing_marks'] ?? (($assessment['total_marks'] * $assessment['passing_percentage']) / 100)) ?>" oninput="updatePassingRate()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-secondary bg-opacity-25 border border-secondary">
                            <span class="small text-muted"><i class="fas fa-percentage text-info me-1"></i> Calculated Passing Threshold:</span>
                            <span class="badge bg-info text-dark font-monospace fw-bold" id="passingRateBadge"><?= number_format((float)$assessment['passing_percentage'], 1) ?>% Required</span>
                        </div>
                    </div>

                    <div class="p-3 rounded border border-secondary bg-secondary bg-opacity-10">
                        <h6 class="small text-muted text-uppercase fw-bold mb-3"><i class="fas fa-random text-primary me-1"></i>Randomization Options</h6>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffle_questions" value="1" <?= !empty($assessment['shuffle_questions']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-light fw-semibold small" for="shuffle_questions">Shuffle Questions</label>
                            <div class="text-muted" style="font-size: 0.75rem;">Randomizes question order for each candidate.</div>
                        </div>

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="shuffle_options" id="shuffle_options" value="1" <?= !empty($assessment['shuffle_options']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-light fw-semibold small" for="shuffle_options">Shuffle Options</label>
                            <div class="text-muted" style="font-size: 0.75rem;">Randomizes multiple-choice options order.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 4. INSTRUCTIONS                                                   -->
        <!-- ================================================================= -->
        <div class="col-12">
            <div class="card bg-dark border-secondary shadow-sm">
                <div class="card-header bg-dark border-secondary p-3 d-flex align-items-center">
                    <span class="badge bg-success me-2 px-2 py-1">4</span>
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-file-contract text-success me-2"></i>Instructions & Examination Rules</h6>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info border-info bg-opacity-10 py-2 small mb-4">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        These instructions and rules are shown to candidates at Workstation Intake. Candidates must agree before starting.
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="instructions" class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-graduation-cap text-primary me-1"></i> Student Instructions</label>
                            <textarea name="instructions" id="instructions" rows="7" class="form-control bg-dark text-light border-secondary font-monospace small"><?= e($assessment['instructions'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="rules_guidelines" class="form-label small text-muted text-uppercase fw-bold"><i class="fas fa-shield-alt text-danger me-1"></i> Rules / Guidelines</label>
                            <textarea name="rules_guidelines" id="rules_guidelines" rows="7" class="form-control bg-dark text-light border-secondary font-monospace small"><?= e($assessment['rules_guidelines'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- ACTION BUTTONS                                                    -->
        <!-- ================================================================= -->
        <div class="col-12">
            <div class="card bg-dark border-secondary p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex gap-2">
                        <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <?php if (Auth::hasPermission('assessments.delete') || (int)($assessment['created_by'] ?? 0) === (int)Auth::id() || Auth::isSuperAdmin()): ?>
                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to permanently delete this assessment? All associated attempts, answers, and telemetry records will be permanently removed.');">
                            <i class="fas fa-trash-alt me-1"></i> Delete Assessment
                        </button>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="action" value="update" class="btn btn-primary btn-lg px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Save Assessment Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function toggleCustomClass(selectElem) {
    const wrap = document.getElementById('custom_class_wrap');
    const input = document.getElementById('custom_class');
    if (selectElem.value === '__custom__') {
        if (wrap) wrap.classList.remove('d-none');
        if (input) input.focus();
    } else {
        if (wrap) wrap.classList.add('d-none');
    }
}

function toggleCustomSection(selectElem) {
    const wrap = document.getElementById('custom_section_wrap');
    const input = document.getElementById('custom_section');
    if (selectElem.value === '__custom__') {
        if (wrap) wrap.classList.remove('d-none');
        if (input) input.focus();
    } else {
        if (wrap) wrap.classList.add('d-none');
    }
}

function updatePassingRate() {
    const total = parseFloat(document.getElementById('total_marks').value) || 0;
    const passing = parseFloat(document.getElementById('passing_marks').value) || 0;
    const badge = document.getElementById('passingRateBadge');
    if (!badge) return;

    if (total > 0 && passing >= 0) {
        const pct = Math.min(100, Math.max(0, (passing / total) * 100));
        badge.textContent = pct.toFixed(1) + '% Required (' + passing + '/' + total + ')';
    } else {
        badge.textContent = '0.0% Required';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePassingRate();
});
</script>
