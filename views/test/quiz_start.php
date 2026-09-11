<?php
/**
 * PTM Assessment System — Candidate Name Entry, T&C Agreement, and Assessment Summary
 */

$assessmentId = (int)($_GET['id'] ?? (Session::get('current_assessment_id') ?? 0));
$candidateId = (int)(Session::get('candidate_id') ?? 0);

if ($candidateId <= 0 || $assessmentId <= 0) {
    Session::flash('error', 'Session expired. Please enter through the assessment terminal.');
    redirectTo('quiz-login');
}

$assessment = AssessmentService::getAssessment($assessmentId);
if (!$assessment || $assessment['status'] !== 'published') {
    Session::flash('error', 'Assessment is not currently available for testing.');
    redirectTo('quiz-login');
}

$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);
if (!$candidate) {
    Session::flash('error', 'Workstation candidate profile not found.');
    redirectTo('quiz-login');
}

$questions = AssessmentService::getAssessmentQuestions($assessmentId);
$questionsCount = count($questions);

$error = '';
$studentName = trim($candidate['full_name'] ?? '');
// If default placeholder name, clear so student types their own
if (strpos($studentName, 'Workstation') !== false || strpos($studentName, 'Candidate (') !== false) {
    $studentName = '';
}

// Handle Start Test Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $enteredName = trim($_POST['student_name'] ?? '');
    $agreed = !empty($_POST['terms_agreed']);

    if ($enteredName === '') {
        $error = 'Please enter your Full Name before starting the test.';
    } elseif (!$agreed) {
        $error = 'You must agree to the examination terms and conditions.';
    } else {
        // Update candidate name in session and DB
        Session::set('candidate_name', $enteredName);

        // Start or resume candidate attempt with the verified student name
        try {
            $sessionData = AssessmentResultService::startOrResumeAttempt($assessmentId, $candidateId, $enteredName);
            redirectTo('quiz-screen&id=' . $assessmentId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Assessment — <?= e($assessment['title']) ?></title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        body { background: #0f172a; min-height: 100vh; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
        .start-card { max-width: 800px; margin: 2.5rem auto; background: #1e293b; border: 1px solid #334155; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.6); }
        .tc-box { max-height: 190px; overflow-y: auto; background: #0b0f19; border: 1px solid #334155; border-radius: 8px; padding: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="start-card p-4 p-md-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-3 mb-4">
            <div>
                <span class="badge bg-primary text-uppercase px-2 mb-1"><?= e($assessment['code']) ?></span>
                <h4 class="fw-bold text-light mb-1"><?= e($assessment['title']) ?></h4>
                <div class="small text-muted">
                    <span><i class="fas fa-university me-1 text-primary"></i><?= e($assessment['campus_name']) ?></span> • 
                    <span><i class="fas fa-book me-1 text-warning"></i><?= e($assessment['subject_name']) ?></span> • 
                    <span><i class="fas fa-user-tie me-1 text-info"></i>Teacher: <strong><?= e($assessment['teacher_name']) ?></strong></span>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary font-monospace px-2 py-1">Station: <?= e($candidate['roll_number']) ?></span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i><?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('quiz-start&id=' . $assessment['id']) ?>" id="startTestForm">
            <?= CSRF::field() ?>

            <!-- 1. Candidate Name Entry -->
            <div class="card bg-dark border-secondary p-3 mb-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-7">
                        <label for="student_name" class="form-label text-light fw-bold mb-1">
                            <i class="fas fa-user-edit text-primary me-1"></i> Enter Your Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="student_name" id="student_name" 
                               class="form-control form-control-lg bg-black text-light border-secondary fw-bold" 
                               placeholder="e.g. Muhammad Ali Khan" value="<?= e($studentName) ?>" required autofocus>
                        <div class="form-text text-muted small">Your name will appear on your examination scorecard and top banner.</div>
                    </div>
                    <div class="col-md-5">
                        <div class="p-2 rounded bg-black border border-secondary text-center">
                            <div class="small text-muted text-uppercase fw-bold">Assigned Class / Grade</div>
                            <div class="fs-5 fw-bold text-info"><?= e($assessment['class_grade']) ?></div>
                            <div class="text-muted small">(Configured by Teacher)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Assessment Summary Table -->
            <div class="mb-4">
                <h6 class="fw-bold text-light mb-2"><i class="fas fa-table me-2 text-primary"></i>Examination Structure & Rules</h6>
                <div class="table-responsive">
                    <table class="table table-dark table-bordered border-secondary align-middle mb-0 small">
                        <tbody>
                            <tr>
                                <th class="bg-black text-muted" style="width: 25%;">Subject</th>
                                <td class="fw-bold text-light"><?= e($assessment['subject_name']) ?></td>
                                <th class="bg-black text-muted" style="width: 25%;">Total Questions</th>
                                <td class="fw-bold text-primary"><?= $questionsCount ?> Questions</td>
                            </tr>
                            <tr>
                                <th class="bg-black text-muted">Time Allowed</th>
                                <td class="fw-bold text-warning font-monospace"><?= (int)$assessment['duration_minutes'] ?> Minutes</td>
                                <th class="bg-black text-muted">Total Marks</th>
                                <td class="fw-bold text-success"><?= (float)$assessment['total_marks'] ?> Marks</td>
                            </tr>
                            <tr>
                                <th class="bg-black text-muted">Passing Criteria</th>
                                <td><?= (float)$assessment['passing_percentage'] ?>% to pass</td>
                                <th class="bg-black text-muted">Negative Marking</th>
                                <td><?= !empty($assessment['negative_marking']) ? '<span class="text-danger">Enabled</span>' : '<span class="text-muted">Disabled (No penalty)</span>' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Terms & Conditions -->
            <div class="mb-4">
                <h6 class="fw-bold text-light mb-2"><i class="fas fa-shield-alt me-2 text-warning"></i>Terms & Examination Conduct</h6>
                <div class="tc-box text-muted">
                    <ol class="ps-3 mb-0">
                        <li class="mb-2"><strong>Stationary Workstation:</strong> You must remain at your assigned computer station for the entire duration of the test.</li>
                        <li class="mb-2"><strong>Fullscreen Enforcement:</strong> Fullscreen mode is enforced. Minimizing the browser or exiting fullscreen mode is logged as a security violation.</li>
                        <li class="mb-2"><strong>Focus Monitoring:</strong> Navigating away from the exam tab or clicking other applications will trigger an anomaly warning to the invigilator.</li>
                        <li class="mb-2"><strong>Clipboard Protection:</strong> Copying, cutting, and pasting are disabled.</li>
                        <li class="mb-2"><strong>Fill-in-the-Blank Instruction:</strong> For all fill-in-the-blank questions, please type your answers in <em>small case / lowercase letters</em>.</li>
                        <li class="mb-0"><strong>Server Countdown:</strong> The timer is server-authoritative. When the timer expires, your test will submit automatically.</li>
                    </ol>
                </div>
            </div>

            <!-- 4. I Agree Checkbox -->
            <div class="form-check p-3 bg-dark border border-secondary rounded mb-4">
                <input class="form-check-input ms-0 me-2" type="checkbox" name="terms_agreed" id="terms_agreed" value="1" required>
                <label class="form-check-label text-light fw-semibold" for="terms_agreed">
                    I have read, understood, and agree to the examination terms and code of conduct.
                </label>
            </div>

            <!-- 5. Start Test Button -->
            <div class="d-grid">
                <button type="submit" id="btnStartExam" class="btn btn-success btn-lg py-3 fw-bold" disabled>
                    <i class="fas fa-play-circle me-2"></i> Start Test
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('student_name');
    const termsCheck = document.getElementById('terms_agreed');
    const startBtn = document.getElementById('btnStartExam');

    function checkValidity() {
        const hasName = nameInput.value.trim().length > 0;
        const hasAgreed = termsCheck.checked;
        startBtn.disabled = !(hasName && hasAgreed);
    }

    nameInput.addEventListener('input', checkValidity);
    termsCheck.addEventListener('change', checkValidity);
    checkValidity();
});
</script>

</body>
</html>

