<?php
/**
 * Candidate Test Portal — Completion Screen
 */

if (!Session::get('candidate_logged_in') || !Session::get('candidate_id')) {
    redirectTo('test-portal');
}

$candidateId = (int)Session::get('candidate_id');
$competitionId = (int)Session::get('candidate_comp_id');
$attemptId = (int)Session::get('current_attempt_id');

$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
$attempt = Database::fetch("SELECT * FROM test_attempts WHERE id = ?", [$attemptId]);

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Submitted — <?= e($systemName) ?></title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        :root,
        [data-bs-theme="light"] {
            --comp-bg: #f8fafc;
            --comp-card: #ffffff;
            --comp-header: #f1f5f9;
            --comp-border: #cbd5e1;
            --comp-text: #0f172a;
            --comp-box: #f8fafc;
        }
        [data-bs-theme="dark"] {
            --comp-bg: #0b0f19;
            --comp-card: #151c2e;
            --comp-header: linear-gradient(135deg, #1e293b, #0f172a);
            --comp-border: rgba(255, 255, 255, 0.1);
            --comp-text: #e2e8f0;
            --comp-box: #0f172a;
        }

        body {
            background-color: var(--comp-bg);
            color: var(--comp-text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .completion-card {
            background: var(--comp-card);
            border: 1px solid var(--comp-border);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            text-align: center;
        }
        .completion-header {
            background: var(--comp-header);
            border-bottom: 1px solid var(--comp-border);
            padding: 36px 24px;
            position: relative;
        }
        .completion-body {
            padding: 30px;
        }
        .theme-toggle-floating {
            position: absolute;
            top: 15px;
            right: 15px;
        }
    </style>
</head>
<body>

<div class="completion-card">
    <div class="completion-header">
        <div class="theme-toggle-floating">
            <button type="button" class="btn btn-sm btn-outline-secondary theme-quick-toggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        <div class="mb-3">
            <i class="fas fa-check-circle fa-4x text-success"></i>
        </div>
        <h3 class="fw-bold mb-1">Test Submitted Successfully!</h3>
        <p class="text-muted small mb-0"><?= e($systemName) ?> &bull; <?= e($instituteName) ?></p>
    </div>

    <div class="completion-body">
        <div class="p-3 rounded border text-start mb-4 bg-body-tertiary">
            <div class="row g-2 small">
                <div class="col-6 text-muted">Candidate Name:</div>
                <div class="col-6 fw-bold text-end"><?= e($candidate['full_name'] ?? 'Candidate') ?></div>

                <div class="col-6 text-muted">Roll Number:</div>
                <div class="col-6 font-monospace text-primary text-end"><?= e($candidate['roll_number'] ?? '—') ?></div>

                <div class="col-6 text-muted">Competition:</div>
                <div class="col-6 text-end"><?= e($competition['name'] ?? 'Competition') ?></div>

                <div class="col-6 text-muted">Attempt Number:</div>
                <div class="col-6 text-info text-end">#<?= $attempt['attempt_number'] ?? 1 ?></div>

                <div class="col-6 text-muted">Started At:</div>
                <div class="col-6 text-end"><?= !empty($attempt['started_at']) ? formatDateTime($attempt['started_at']) : '—' ?></div>

                <div class="col-6 text-muted">Submitted At:</div>
                <div class="col-6 text-end"><?= !empty($attempt['submitted_at']) ? formatDateTime($attempt['submitted_at']) : formatDateTime(date('Y-m-d H:i:s')) ?></div>

                <div class="col-6 text-muted">Time Taken:</div>
                <div class="col-6 text-warning text-end fw-bold"><?= !empty($attempt['time_taken_seconds']) ? number_format($attempt['time_taken_seconds']) . ' seconds' : 'Completed' ?></div>
            </div>
        </div>

        <?php
        $settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);
        $resultVisible = (bool)($settings['result_visible'] ?? 1);
        ?>

        <div class="alert alert-info small mb-4">
            <i class="fas fa-info-circle me-1"></i>
            Your typing response has been authoritatively recorded on the server.
            <?php if ($resultVisible): ?>
                Your test score is ready for immediate review.
            <?php else: ?>
                Official results and rankings will be declared and published by the administration.
            <?php endif; ?>
        </div>

        <div class="d-grid gap-2">
            <?php if ($resultVisible): ?>
                <a href="<?= url('test-result') ?>" class="btn btn-success btn-lg fw-bold">
                    <i class="fas fa-chart-line me-1"></i> View Test Results & Score
                </a>
            <?php endif; ?>
            <a href="<?= url('candidate-logout') ?>" class="btn btn-outline-light">
                <i class="fas fa-sign-out-alt me-1"></i> Finish & Exit Portal
            </a>
        </div>

        <div class="text-center mt-3 text-muted small">
            <div><strong>vibe.Sınav</strong> Assessment System</div>
            <div>Developed by Ahsan Raza</div>
        </div>
    </div>
</div>

</body>
</html>
