<?php
/**
 * PTM Assessment System — Lab Workstation & Assessment Access
 * Automatically detects published assessments and routes candidate to name entry & T&C screen.
 */

$publishedAssessments = Database::fetchAll(
    "SELECT a.id, a.title, a.code, a.duration_minutes, a.total_marks, a.class_grade,
            c.name as campus_name, s.name as subject_name, u.full_name as teacher_name
     FROM assessments a
     JOIN campuses c ON a.campus_id = c.id
     JOIN subjects s ON a.subject_id = s.id
     JOIN users u ON a.teacher_id = u.id
     WHERE a.status = 'published'
     ORDER BY a.id DESC"
);

$selectedAssessmentId = (int)($_GET['assessment_id'] ?? ($_POST['assessment_id'] ?? ($publishedAssessments[0]['id'] ?? 0)));
$error = '';
$rollNumber = trim($_GET['pc'] ?? ($_POST['roll_number'] ?? ''));

// If candidate form posted
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $rollNumber = trim($_POST['roll_number'] ?? '');

    if ($assessmentId <= 0) {
        $error = 'Please select a valid assessment.';
    } elseif ($rollNumber === '') {
        $error = 'Please enter your Workstation / Roll Number.';
    } else {
        // Find or auto-register candidate station by roll number
        $candidate = Database::fetch(
            "SELECT * FROM candidates WHERE roll_number = ? LIMIT 1",
            [$rollNumber]
        );

        if (!$candidate) {
            // Auto-create station record if in lab mode
            $compId = (int)Database::fetchColumn("SELECT id FROM competitions LIMIT 1") ?: 1;
            $candId = Database::insert('candidates', [
                'competition_id'      => $compId,
                'registration_number' => 'STATION-' . strtoupper($rollNumber),
                'roll_number'         => strtoupper($rollNumber),
                'full_name'           => 'Candidate (' . strtoupper($rollNumber) . ')',
                'status'              => 'registered',
            ]);
            $candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candId]);
        }

        // Set session and redirect to Start Test / Name & T&C page
        Session::set('candidate_id', $candidate['id']);
        Session::set('candidate_roll', $candidate['roll_number']);
        Session::set('candidate_name', $candidate['full_name']);
        Session::set('candidate_logged_in', true);
        Session::set('current_assessment_id', $assessmentId);

        redirectTo('quiz-start&id=' . $assessmentId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Assessment Portal — PTM</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .station-card { width: 100%; max-width: 520px; background: #1e293b; border: 1px solid #334155; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.6); }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="station-card mx-auto p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-2" style="width: 70px; height: 70px;">
                <i class="fas fa-desktop fa-2x"></i>
            </div>
            <h4 class="fw-bold text-light mb-1">PTM Assessment Terminal</h4>
            <p class="text-muted small mb-0">Computer Lab Examination Workstation</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i><?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($publishedAssessments)): ?>
            <!-- No active assessment state with auto-poll -->
            <div class="text-center py-4">
                <div class="mb-3 text-warning">
                    <i class="fas fa-satellite-dish fa-3x animate-pulse"></i>
                </div>
                <h5 class="fw-bold text-light mb-2">No Assessment Available</h5>
                <p class="text-muted small mb-4">
                    There is currently no active examination pushed by the teacher.<br>
                    Please wait at your workstation. This screen will automatically refresh when the test is launched.
                </p>
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                <span class="text-muted small font-monospace">Listening for assessment launch...</span>
            </div>

            <script>
                // Auto-refresh every 5 seconds to detect newly pushed assessments
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
            </script>
        <?php else: ?>
            <!-- Active assessment detected! -->
            <form method="POST" action="<?= url('quiz-login') ?>">
                <?= CSRF::field() ?>

                <div class="p-3 bg-dark border border-primary border-opacity-50 rounded mb-4">
                    <div class="small text-muted text-uppercase fw-bold mb-1"><i class="fas fa-satellite-dish text-success me-1"></i> Active Examination Found</div>
                    <select name="assessment_id" id="assessment_id" class="form-select bg-dark text-light border-secondary fw-bold" required>
                        <?php foreach ($publishedAssessments as $ass): ?>
                            <option value="<?= $ass['id'] ?>" <?= $selectedAssessmentId === (int)$ass['id'] ? 'selected' : '' ?>>
                                <?= e($ass['title']) ?> (<?= e($ass['class_grade']) ?> • <?= (int)$ass['duration_minutes'] ?>m)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="roll_number" class="form-label small text-muted text-uppercase fw-bold">Workstation / Roll ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-secondary border-secondary text-light"><i class="fas fa-id-badge"></i></span>
                        <input type="text" name="roll_number" id="roll_number" 
                               class="form-control bg-dark text-light border-secondary text-uppercase fw-bold font-monospace" 
                               placeholder="e.g. PC-01 or 101" value="<?= e($rollNumber) ?>" required autofocus>
                    </div>
                    <div class="form-text text-muted small">Enter your assigned computer lab seat / workstation ID.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold mb-3">
                    Continue to Assessment <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
            <a href="<?= url('test-portal') ?>" class="text-muted small text-decoration-none">
                <i class="fas fa-keyboard me-1"></i> Typing Test Mode
            </a>
            <a href="<?= url('login') ?>" class="text-muted small text-decoration-none">
                <i class="fas fa-user-shield me-1"></i> Staff Login
            </a>
        </div>
    </div>
</div>

</body>
</html>
