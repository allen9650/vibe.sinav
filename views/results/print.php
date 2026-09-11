<?php
/**
 * Official Individual Candidate Result Print Sheet (A4 Layout)
 */

Middleware::requirePermission('results.view');

$resultId = (int)($_GET['id'] ?? 0);
$result = Database::fetch("
    SELECT 
        r.*,
        c.full_name AS candidate_name,
        c.father_name,
        c.roll_number,
        c.registration_number,
        c.course,
        c.shift,
        c.batch,
        c.branch,
        cmp.name AS competition_name,
        cmp.code AS competition_code,
        cmp.competition_date,
        cmp.venue,
        a.attempt_number,
        a.started_at,
        a.submitted_at,
        a.duration_seconds AS configured_duration
    FROM test_results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN competitions cmp ON r.competition_id = cmp.id
    JOIN test_attempts a ON r.attempt_id = a.id
    WHERE r.id = ?
", [$resultId]);

if (!$result) {
    Session::flash('error', 'Result record not found.');
    redirectTo('results');
}

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet — <?= e($result['candidate_name']) ?> (<?= e($result['roll_number']) ?>)</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .result-sheet {
            background: #fff;
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header-box {
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .kpi-box {
            border: 1px solid #000;
            padding: 12px;
            text-align: center;
            background: #fdfdfd;
        }
        .signature-line {
            border-top: 1px dashed #000;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            font-size: 13px;
        }
        @media print {
            body { background: transparent; padding: 0; }
            .result-sheet { box-shadow: none; border: 2px solid #000; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center mb-3 no-print">
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4">
        <i class="fas fa-print me-1"></i> Print Result Sheet
    </button>
    <button onclick="window.close()" class="btn btn-outline-secondary btn-sm ms-2">
        <i class="fas fa-times me-1"></i> Close
    </button>
</div>

<div class="result-sheet">
    <div class="header-box">
        <h3 class="fw-bold mb-1 text-uppercase"><?= e($instituteName) ?></h3>
        <h5 class="fw-bold text-secondary mb-1">OFFICIAL TYPING COMPETITION RESULT</h5>
        <div class="small text-muted font-monospace">
            <?= e($result['competition_name']) ?> (<?= e($result['competition_code']) ?>) &bull; Date: <?= formatDate($result['competition_date']) ?>
        </div>
    </div>

    <!-- Candidate Info Table -->
    <div class="mb-4">
        <table class="table table-bordered table-sm mb-0">
            <tbody>
                <tr>
                    <td class="bg-light fw-bold" style="width: 20%;">Candidate Name:</td>
                    <td class="fw-bold" style="width: 30%;"><?= e($result['candidate_name']) ?></td>
                    <td class="bg-light fw-bold" style="width: 20%;">Roll Number:</td>
                    <td class="font-monospace fw-bold" style="width: 30%;"><?= e($result['roll_number'] ?: '—') ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">Father Name:</td>
                    <td><?= e($result['father_name'] ?: '—') ?></td>
                    <td class="bg-light fw-bold">Registration #:</td>
                    <td class="font-monospace"><?= e($result['registration_number']) ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">Course / Shift:</td>
                    <td><?= e($result['course'] ?: '—') ?> (<?= e($result['shift'] ?: '—') ?>)</td>
                    <td class="bg-light fw-bold">Attempt #:</td>
                    <td>Attempt #<?= (int)$result['attempt_number'] ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- KPI Scores Row -->
    <div class="row g-2 mb-4">
        <div class="col-3">
            <div class="kpi-box">
                <div class="small text-muted text-uppercase">Net Typing Speed</div>
                <div class="fs-4 fw-bold text-primary font-monospace"><?= number_format($result['net_wpm'], 2) ?></div>
                <small>Words Per Minute</small>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="small text-muted text-uppercase">Accuracy %</div>
                <div class="fs-4 fw-bold text-success font-monospace"><?= number_format($result['accuracy'], 2) ?>%</div>
                <small><?= (int)$result['correct_characters'] ?> / <?= (int)$result['total_characters'] ?> Chars</small>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="small text-muted text-uppercase">Final Score</div>
                <div class="fs-4 fw-bold text-dark font-monospace"><?= number_format($result['score'] ?? 0, 2) ?></div>
                <small>Rank: <?= $result['rank'] ? '#' . $result['rank'] : 'Provisional' ?></small>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="small text-muted text-uppercase">Qualification</div>
                <div class="fs-5 fw-bold text-<?= $result['qualification_status'] === 'qualified' ? 'success' : 'danger' ?> mt-1">
                    <?= strtoupper($result['qualification_status']) ?>
                </div>
                <small>Official Status</small>
            </div>
        </div>
    </div>

    <!-- Statistical Breakdown Table -->
    <div class="mb-4">
        <h6 class="fw-bold border-bottom pb-1 mb-2">Performance & Keystroke Breakdown</h6>
        <table class="table table-bordered table-sm mb-0">
            <tbody>
                <tr>
                    <td class="bg-light" style="width: 25%;">Gross Speed (WPM):</td>
                    <td class="font-monospace fw-bold" style="width: 25%;"><?= number_format($result['gross_wpm'], 2) ?></td>
                    <td class="bg-light" style="width: 25%;">Scoring Duration:</td>
                    <td class="font-monospace" style="width: 25%;"><?= (int)$result['time_taken_seconds'] ?> seconds</td>
                </tr>
                <tr>
                    <td class="bg-light">Total Characters Typed:</td>
                    <td class="font-monospace"><?= number_format($result['total_characters']) ?></td>
                    <td class="bg-light">Total Errors:</td>
                    <td class="font-monospace text-danger fw-bold"><?= (int)$result['error_count'] ?></td>
                </tr>
                <tr>
                    <td class="bg-light">Correct Characters:</td>
                    <td class="font-monospace text-success"><?= number_format($result['correct_characters']) ?></td>
                    <td class="bg-light">Substitutions (Incorrect):</td>
                    <td class="font-monospace text-danger"><?= (int)$result['incorrect_characters'] ?></td>
                </tr>
                <tr>
                    <td class="bg-light">Missing Characters:</td>
                    <td class="font-monospace text-warning"><?= (int)$result['missing_characters'] ?></td>
                    <td class="bg-light">Extra Insertions:</td>
                    <td class="font-monospace text-info"><?= (int)$result['extra_characters'] ?></td>
                </tr>
                <tr>
                    <td class="bg-light">Backspaces Count:</td>
                    <td class="font-monospace"><?= (int)$result['backspace_count'] ?></td>
                    <td class="bg-light">Scoring Engine:</td>
                    <td class="font-monospace">v<?= e($result['scoring_version'] ?? '1.0') ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="row pt-4">
        <div class="col-4">
            <div class="signature-line">
                Candidate Signature
            </div>
        </div>
        <div class="col-4">
            <div class="signature-line">
                Invigilator Signature
            </div>
        </div>
        <div class="col-4">
            <div class="signature-line">
                Authorized Officer / Controller
            </div>
        </div>
    </div>

    <div class="text-center small text-muted mt-4 pt-2 border-top">
        System Generated Result Sheet &bull; <?= e($systemName) ?> &bull; Developed by Ahsan Raza &bull; <?= date('Y-m-d H:i:s') ?>
    </div>
</div>

</body>
</html>
