<?php
/**
 * Compact Thermal Result Slip (80mm Receipt Layout)
 */

Middleware::requirePermission('results.view');

$resultId = (int)($_GET['id'] ?? 0);
$result = Database::fetch("
    SELECT 
        r.*,
        c.full_name AS candidate_name,
        c.roll_number,
        c.registration_number,
        c.course,
        cmp.name AS competition_name,
        cmp.code AS competition_code,
        cmp.competition_date
    FROM test_results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN competitions cmp ON r.competition_id = cmp.id
    WHERE r.id = ?
", [$resultId]);

if (!$result) {
    Session::flash('error', 'Result record not found.');
    redirectTo('results');
}

$instituteName = getSetting('institute_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Slip — <?= e($result['roll_number']) ?></title>
    <?= getFaviconTag() ?>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
        }
        .slip-container {
            max-width: 280px;
            margin: 0 auto;
            border: 1px dashed #000;
            padding: 12px;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .border-top { border-top: 1px dashed #000; margin-top: 8px; padding-top: 8px; }
        .border-bottom { border-bottom: 1px dashed #000; margin-bottom: 8px; padding-bottom: 8px; }
        .row-line { display: flex; justify-content: space-between; margin-bottom: 4px; }
        @media print {
            body { padding: 0; }
            .slip-container { border: none; width: 100%; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="text-center no-print" style="margin-bottom: 15px;">
    <button onclick="window.print()">[ Print Slip ]</button>
    <button onclick="window.close()">[ Close ]</button>
</div>

<div class="slip-container">
    <div class="text-center border-bottom">
        <div class="fw-bold" style="font-size: 14px;"><?= e($instituteName) ?></div>
        <div>TYPING COMPETITION RESULT</div>
        <small><?= e($result['competition_code']) ?> &bull; <?= formatDate($result['competition_date']) ?></small>
    </div>

    <div class="row-line">
        <span>Roll Number:</span>
        <strong class="fw-bold"><?= e($result['roll_number'] ?: $result['registration_number']) ?></strong>
    </div>
    <div class="row-line">
        <span>Candidate:</span>
        <strong class="fw-bold"><?= e($result['candidate_name']) ?></strong>
    </div>
    <div class="row-line">
        <span>Course:</span>
        <span><?= e($result['course'] ?: '—') ?></span>
    </div>

    <div class="border-top border-bottom">
        <div class="row-line">
            <span>Net Speed:</span>
            <strong class="fw-bold" style="font-size: 15px;"><?= number_format($result['net_wpm'], 2) ?> WPM</strong>
        </div>
        <div class="row-line">
            <span>Accuracy:</span>
            <strong class="fw-bold"><?= number_format($result['accuracy'], 2) ?>%</strong>
        </div>
        <div class="row-line">
            <span>Gross Speed:</span>
            <span><?= number_format($result['gross_wpm'], 2) ?> WPM</span>
        </div>
        <div class="row-line">
            <span>Total Errors:</span>
            <span><?= (int)$result['error_count'] ?></span>
        </div>
        <div class="row-line">
            <span>Final Score:</span>
            <strong class="fw-bold"><?= number_format($result['score'] ?? 0, 2) ?></strong>
        </div>
        <div class="row-line">
            <span>Final Rank:</span>
            <strong class="fw-bold"><?= $result['rank'] ? '#' . $result['rank'] : 'Provisional' ?></strong>
        </div>
        <div class="row-line">
            <span>Status:</span>
            <strong class="fw-bold"><?= strtoupper($result['qualification_status']) ?></strong>
        </div>
    </div>

    <div class="text-center" style="font-size: 11px; margin-top: 10px;">
        <div>*** OFFICIAL RESULT SLIP ***</div>
        <div>Generated: <?= date('Y-m-d H:i:s') ?></div>
        <div style="margin-top: 5px; color: #555;">Developed by Ahsan Raza</div>
    </div>
</div>

</body>
</html>
