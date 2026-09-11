<?php
/**
 * PTM Assessment System — Create Assessment View
 * 4 Structured Sections:
 * 1. Basic Information (Title, Type, Subject, Class, Section, Campus, Teacher, Description)
 * 2. Schedule (Start Date, Start Time, End Date, End Time, Duration [Mandatory])
 * 3. Assessment Settings (Total Marks, Passing Marks, Shuffle Questions, Shuffle Options)
 * 4. Instructions (Student Instructions, Rules / Guidelines)
 */

$subjects = QuestionService::getSubjects();
$campuses = QuestionService::getCampuses();
$teachers = AssessmentService::getTeachers();
$classes  = AssessmentService::getStandardClasses();
$sections = AssessmentService::getStandardSectionsList();
$types    = AssessmentService::getAssessmentTypes();

$defaultStudentInstructions = "1. Read each question carefully before selecting or entering your answer.\n2. You can navigate between questions freely using the question palette on the right.\n3. Your answers are automatically saved in real time to the server.\n4. Complete all required questions before clicking 'Submit Assessment'.\n5. Once submitted, answers cannot be edited or re-attempted.";

$defaultRulesGuidelines = "1. Fullscreen Enforcement: You must remain in fullscreen mode throughout the examination.\n2. Tab Switching Prohibited: Switching browser tabs, minimizing the window, or opening external apps is logged as a violation.\n3. Security Locks: Clipboard copy/cut/paste and right-click menus are disabled.\n4. Timer Rule: The test timer runs server-side and will auto-submit when time expires.\n5. Academic Integrity: Any detected malpractice or excessive violations will lead to immediate disqualification.";

$errors = [];
$formData = [
    // 1. Basic Information
    'title'               => $_POST['title'] ?? '',
    'assessment_type'     => $_POST['assessment_type'] ?? 'quiz',
    'subject_id'          => $_POST['subject_id'] ?? ($subjects[0]['id'] ?? 1),
    'class'               => $_POST['class'] ?? 'Grade 1',
    'custom_class'        => $_POST['custom_class'] ?? '',
    'section'             => $_POST['section'] ?? 'Section A',
    'custom_section'      => $_POST['custom_section'] ?? '',
    'campus_id'           => $_POST['campus_id'] ?? (Auth::campusId() ?: ($campuses[0]['id'] ?? 1)),
    'teacher_id'          => $_POST['teacher_id'] ?? (Auth::id() ?: ($teachers[0]['id'] ?? 1)),
    'description'         => $_POST['description'] ?? '',

    // 2. Schedule
    'start_date'          => $_POST['start_date'] ?? '',
    'start_time'          => $_POST['start_time'] ?? '',
    'end_date'            => $_POST['end_date'] ?? '',
    'end_time'            => $_POST['end_time'] ?? '',
    'duration_minutes'    => $_POST['duration_minutes'] ?? '30',

    // 3. Assessment Settings
    'total_marks'         => $_POST['total_marks'] ?? '100.00',
    'passing_marks'       => $_POST['passing_marks'] ?? '40.00',
    'shuffle_questions'   => isset($_POST['shuffle_questions']) ? 1 : (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 0 : 1),
    'shuffle_options'     => isset($_POST['shuffle_options']) ? 1 : (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 0 : 1),

    // 4. Instructions
    'instructions'        => $_POST['instructions'] ?? $defaultStudentInstructions,
    'rules_guidelines'    => $_POST['rules_guidelines'] ?? $defaultRulesGuidelines,
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    if (empty(trim($formData['title']))) {
        $errors['title'] = 'Assessment title is required.';
    }

    $duration = (int)$formData['duration_minutes'];
    if ($duration <= 0) {
        $errors['duration_minutes'] = 'Duration is mandatory and must be at least 1 minute (controls the exam timer).';
    }

    if (empty($errors)) {
        try {
            $newId = AssessmentService::createAssessment($formData);
            Session::flash('success', "Assessment '{$formData['title']}' created successfully! Now attach questions from the Question Bank.");
            redirectTo('assessments-builder&id=' . $newId);
        } catch (Exception $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('assessments') ?>" class="text-decoration-none text-muted">Assessments</a></li>
                <li class="breadcrumb-item active text-primary" aria-current="page">Create Assessment</li>
            </ol>
        </nav>
        <h4 class="mb-1 fw-bold text-light"><i class="fas fa-plus-circle text-primary me-2"></i>Create New Assessment</h4>
        <p class="text-muted small mb-0">Fill out basic details, schedule, scoring parameters, and student instructions.</p>
    </div>
    <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to Assessments
    </a>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= e($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('assessments-create') ?>" id="createAssessmentForm">
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
                    <!-- Assessment Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label small text-muted text-uppercase fw-bold">
                            Assessment Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" id="title" 
                               class="form-control bg-dark text-light border-secondary <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               placeholder="e.g. Computer Science Term Examination 2026" 
                               value="<?= e($formData['title']) ?>" required autofocus>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php else: ?>
                            <div class="form-text text-muted small">A clear, descriptive title displayed on candidate scorecards and attendance sheets.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Row: Type, Subject, Class, Section -->
                    <div class="row g-3 mb-3">
                        <!-- Assessment Type -->
                        <div class="col-md-3">
                            <label for="assessment_type" class="form-label small text-muted text-uppercase fw-bold">Assessment Type</label>
                            <select name="assessment_type" id="assessment_type" class="form-select bg-dark text-light border-secondary">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $formData['assessment_type'] === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="col-md-3">
                            <label for="subject_id" class="form-label small text-muted text-uppercase fw-bold">
                                Subject <span class="text-danger">*</span>
                            </label>
                            <select name="subject_id" id="subject_id" class="form-select bg-dark text-light border-secondary" required>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (int)$formData['subject_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Class -->
                        <div class="col-md-3">
                            <label for="class" class="form-label small text-muted text-uppercase fw-bold">
                                Class <span class="text-danger">*</span>
                            </label>
                            <?php 
                            $selectedClass = $formData['class'];
                            $isCustomClass = !empty($selectedClass) && !array_key_exists($selectedClass, $classes);
                            ?>
                            <select name="class" id="class" class="form-select bg-dark text-light border-secondary" required onchange="toggleCustomClass(this)">
                                <?php foreach ($classes as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= ($selectedClass === $val && !$isCustomClass) ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($isCustomClass): ?>
                                    <option value="<?= e($selectedClass) ?>" selected><?= e($selectedClass) ?> (Custom)</option>
                                <?php endif; ?>
                                <option value="__custom__" <?= $isCustomClass ? 'selected' : '' ?>>+ Custom Class / Level...</option>
                            </select>
                            <div id="custom_class_wrap" class="mt-2 <?= $isCustomClass ? '' : 'd-none' ?>">
                                <input type="text" name="custom_class" id="custom_class" 
                                       class="form-control bg-dark text-light border-secondary form-control-sm" 
                                       placeholder="Enter custom class (e.g. O-Levels, Prep)" 
                                       value="<?= $isCustomClass ? e($selectedClass) : e($formData['custom_class']) ?>">
                            </div>
                        </div>

                        <!-- Section -->
                        <div class="col-md-3">
                            <label for="section" class="form-label small text-muted text-uppercase fw-bold">
                                Section <span class="text-danger">*</span>
                            </label>
                            <?php 
                            $selectedSection = $formData['section'];
                            $isCustomSection = !empty($selectedSection) && !in_array($selectedSection, $sections, true);
                            ?>
                            <select name="section" id="section" class="form-select bg-dark text-light border-secondary" required onchange="toggleCustomSection(this)">
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= e($sec) ?>" <?= ($selectedSection === $sec && !$isCustomSection) ? 'selected' : '' ?>>
                                        <?= e($sec) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($isCustomSection): ?>
                                    <option value="<?= e($selectedSection) ?>" selected><?= e($selectedSection) ?> (Custom)</option>
                                <?php endif; ?>
                                <option value="__custom__" <?= $isCustomSection ? 'selected' : '' ?>>+ Custom Section...</option>
                            </select>
                            <div id="custom_section_wrap" class="mt-2 <?= $isCustomSection ? '' : 'd-none' ?>">
                                <input type="text" name="custom_section" id="custom_section" 
                                       class="form-control bg-dark text-light border-secondary form-control-sm" 
                                       placeholder="Enter custom section (e.g. Section F)" 
                                       value="<?= $isCustomSection ? e($selectedSection) : e($formData['custom_section']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Row: Campus & Teacher -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="campus_id" class="form-label small text-muted text-uppercase fw-bold">Campus</label>
                            <?php if (Auth::isAdmin()): ?>
                                <select name="campus_id" id="campus_id" class="form-select bg-dark text-light border-secondary">
                                    <?php foreach ($campuses as $camp): ?>
                                        <option value="<?= $camp['id'] ?>" <?= (int)$formData['campus_id'] === (int)$camp['id'] ? 'selected' : '' ?>>
                                            <?= e($camp['name']) ?> (<?= e($camp['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-dark text-muted border-secondary" value="<?= e(Auth::campusName() ?: 'Assigned Campus') ?>" readonly>
                                <input type="hidden" name="campus_id" value="<?= Auth::campusId() ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label small text-muted text-uppercase fw-bold">Teacher / Instructor</label>
                            <select name="teacher_id" id="teacher_id" class="form-select bg-dark text-light border-secondary">
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= (int)$formData['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= e(ucfirst($t['username'])) ?> (<?= e(ucfirst(str_replace('-', ' ', $t['role']))) ?><?= !empty($t['campus_name']) ? ' - ' . e($t['campus_name']) : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-0">
                        <label for="description" class="form-label small text-muted text-uppercase fw-bold">Description</label>
                        <textarea name="description" id="description" rows="2" 
                                  class="form-control bg-dark text-light border-secondary" 
                                  placeholder="Provide a brief summary or syllabus coverage of this assessment..."><?= e($formData['description']) ?></textarea>
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
                        Set optional opening and closing dates/times. The <strong>Duration is strictly mandatory</strong> as it enforces the server countdown timer.
                    </p>

                    <!-- Start Date & Start Time -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="start_date" class="form-label small text-muted text-uppercase fw-bold">
                                Start Date <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="date" name="start_date" id="start_date" 
                                   class="form-control bg-dark text-light border-secondary" 
                                   value="<?= e($formData['start_date']) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="start_time" class="form-label small text-muted text-uppercase fw-bold">
                                Start Time <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="time" name="start_time" id="start_time" 
                                   class="form-control bg-dark text-light border-secondary" 
                                   value="<?= e($formData['start_time']) ?>">
                        </div>
                    </div>

                    <!-- End Date & End Time -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="end_date" class="form-label small text-muted text-uppercase fw-bold">
                                End Date <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="date" name="end_date" id="end_date" 
                                   class="form-control bg-dark text-light border-secondary" 
                                   value="<?= e($formData['end_date']) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="end_time" class="form-label small text-muted text-uppercase fw-bold">
                                End Time <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="time" name="end_time" id="end_time" 
                                   class="form-control bg-dark text-light border-secondary" 
                                   value="<?= e($formData['end_time']) ?>">
                        </div>
                    </div>

                    <!-- Duration (Mandatory) -->
                    <div class="mb-0">
                        <label for="duration_minutes" class="form-label small text-muted text-uppercase fw-bold d-flex justify-content-between">
                            <span>Duration (Minutes) <span class="text-danger">*</span></span>
                            <span class="badge bg-danger text-uppercase" style="font-size: 0.65rem;">Mandatory (Controls Timer)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-stopwatch"></i></span>
                            <input type="number" min="1" max="300" name="duration_minutes" id="duration_minutes" 
                                   class="form-control bg-dark text-light border-secondary <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" 
                                   value="<?= e($formData['duration_minutes']) ?>" required>
                            <span class="input-group-text bg-secondary border-secondary text-light">Minutes</span>
                        </div>
                        <?php if (isset($errors['duration_minutes'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['duration_minutes']) ?></div>
                        <?php else: ?>
                            <div class="form-text text-muted small">Controls the authoritative timer countdown on candidate screens. Default: 30 mins.</div>
                        <?php endif; ?>
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
                    <p class="text-muted small mb-3">
                        Define grading criteria and test behavior parameters.
                    </p>

                    <!-- Total Marks & Passing Marks -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="total_marks" class="form-label small text-muted text-uppercase fw-bold">Total Marks</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-award"></i></span>
                                <input type="number" step="0.5" min="1" name="total_marks" id="total_marks" 
                                       class="form-control bg-dark text-light border-secondary" 
                                       value="<?= e($formData['total_marks']) ?>" oninput="updatePassingRate()">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="passing_marks" class="form-label small text-muted text-uppercase fw-bold">Passing Marks</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-check-circle"></i></span>
                                <input type="number" step="0.5" min="0" name="passing_marks" id="passing_marks" 
                                       class="form-control bg-dark text-light border-secondary" 
                                       value="<?= e($formData['passing_marks']) ?>" oninput="updatePassingRate()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-secondary bg-opacity-25 border border-secondary">
                            <span class="small text-muted"><i class="fas fa-percentage text-info me-1"></i> Calculated Passing Threshold:</span>
                            <span class="badge bg-info text-dark font-monospace fw-bold" id="passingRateBadge">40.0% Required</span>
                        </div>
                    </div>

                    <!-- Shuffle Questions & Shuffle Options -->
                    <div class="p-3 rounded border border-secondary bg-secondary bg-opacity-10">
                        <h6 class="small text-muted text-uppercase fw-bold mb-3"><i class="fas fa-random text-primary me-1"></i>Randomization Options</h6>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffle_questions" value="1" <?= !empty($formData['shuffle_questions']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-light fw-semibold small" for="shuffle_questions">
                                Shuffle Questions
                            </label>
                            <div class="text-muted" style="font-size: 0.75rem;">Randomizes the question sequence for each candidate to prevent adjacent cheating.</div>
                        </div>

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="shuffle_options" id="shuffle_options" value="1" <?= !empty($formData['shuffle_options']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-light fw-semibold small" for="shuffle_options">
                                Shuffle Options
                            </label>
                            <div class="text-muted" style="font-size: 0.75rem;">Randomizes multiple-choice answer choices for each question.</div>
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
                        <strong>Candidate Notice:</strong> Both Student Instructions and Rules / Guidelines will be presented on the student's 
                        <strong>Workstation Intake Screen</strong> prior to starting the test. The candidate must check 
                        <strong>"I Agree"</strong> before the test can begin.
                    </div>

                    <div class="row g-4">
                        <!-- Student Instructions -->
                        <div class="col-md-6">
                            <label for="instructions" class="form-label small text-muted text-uppercase fw-bold d-flex justify-content-between">
                                <span><i class="fas fa-graduation-cap text-primary me-1"></i> Student Instructions</span>
                                <span class="text-muted fw-normal" style="font-size: 0.75rem;">Displayed to candidates</span>
                            </label>
                            <textarea name="instructions" id="instructions" rows="7" 
                                      class="form-control bg-dark text-light border-secondary font-monospace small" 
                                      placeholder="Enter instructions for the students..."><?= e($formData['instructions']) ?></textarea>
                            <div class="form-text text-muted small">Guidelines on navigation, time usage, and answering instructions.</div>
                        </div>

                        <!-- Rules / Guidelines -->
                        <div class="col-md-6">
                            <label for="rules_guidelines" class="form-label small text-muted text-uppercase fw-bold d-flex justify-content-between">
                                <span><i class="fas fa-shield-alt text-danger me-1"></i> Rules / Guidelines</span>
                                <span class="text-muted fw-normal" style="font-size: 0.75rem;">Disciplinary & proctoring terms</span>
                            </label>
                            <textarea name="rules_guidelines" id="rules_guidelines" rows="7" 
                                      class="form-control bg-dark text-light border-secondary font-monospace small" 
                                      placeholder="Enter examination rules and proctoring guidelines..."><?= e($formData['rules_guidelines']) ?></textarea>
                            <div class="form-text text-muted small">Fullscreen enforcement, anti-cheating, tab switch prohibitions, and penalties.</div>
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
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold">
                        Proceed to Question Selection <i class="fas fa-arrow-right ms-2"></i>
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
