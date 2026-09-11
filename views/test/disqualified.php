<?php
/**
 * Candidate Test Portal — Test Disqualified Screen
 */

$candidateId = (int)Session::get('candidate_id');
$competitionId = (int)Session::get('candidate_comp_id');
$attemptId = (int)Session::get('current_attempt_id');

$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
$attempt = Database::fetch("SELECT * FROM test_attempts WHERE id = ?", [$attemptId]);

$violationsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM security_events WHERE attempt_id = ?",
    [$attemptId]
);

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Disqualified — <?= e($systemName) ?></title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
    <style>
        body {
            background-color: #0b0f19;
            color: #e2e8f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .disqualify-card {
            background: #151c2e;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.15);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            text-align: center;
        }
        .disqualify-header {
            background: linear-gradient(135deg, #450a0a, #1e1b4b);
            border-bottom: 1px solid rgba(239, 68, 68, 0.2);
            padding: 36px 24px;
        }
        .disqualify-body {
            padding: 30px;
        }
    </style>
</head>
<body>

<div class="disqualify-card">
    <div class="disqualify-header">
        <div class="mb-3">
            <i class="fas fa-ban fa-4x text-danger"></i>
        </div>
        <h3 class="text-danger fw-bold mb-1">TEST DISQUALIFIED</h3>
        <p class="text-muted small mb-0"><?= e($systemName) ?> &bull; <?= e($instituteName) ?></p>
    </div>

    <div class="disqualify-body">
        <div class="p-3 bg-dark rounded border border-danger border-opacity-50 text-start mb-4">
            <div class="row g-2 small">
                <div class="col-6 text-muted">Candidate Name:</div>
                <div class="col-6 fw-bold text-light text-end"><?= e($candidate['full_name'] ?? 'Candidate') ?></div>

                <div class="col-6 text-muted">Roll Number:</div>
                <div class="col-6 font-monospace text-primary text-end"><?= e($candidate['roll_number'] ?? '—') ?></div>

                <div class="col-6 text-muted">Competition:</div>
                <div class="col-6 text-light text-end"><?= e($competition['name'] ?? 'Competition') ?></div>

                <div class="col-6 text-muted">Violations Recorded:</div>
                <div class="col-6 text-danger text-end fw-bold font-monospace"><?= $violationsCount ?> violation(s)</div>

                <div class="col-6 text-muted">Status:</div>
                <div class="col-6 text-danger text-end fw-bold"><i class="fas fa-times-circle me-1"></i> Disqualified</div>
            </div>
        </div>

        <div class="alert alert-danger small mb-4 text-start">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>Notice:</strong> Your typing test session was automatically terminated because the configured security violation limit (tab switching, window focus loss, or prohibited actions) was reached.
        </div>

        <div class="d-grid gap-2">
            <a href="<?= url('candidate-logout') ?>" class="btn btn-outline-danger btn-lg fw-bold">
                <i class="fas fa-sign-out-alt me-1"></i> Exit Portal
            </a>
        </div>
        <div class="text-center mt-3 text-muted small">
            Developed by Ahsan Raza
        </div>
    </div>
</div>

</body>
</html>
