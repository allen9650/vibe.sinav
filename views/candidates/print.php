<?php
/**
 * Candidate Management — Printable Candidate List
 */

$competitionId = (int)($_GET['competition_id'] ?? 0);
$course = trim($_GET['course'] ?? '');
$shift = trim($_GET['shift'] ?? '');

$competition = null;
if ($competitionId > 0) {
    $competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
}

$where = ['1=1'];
$params = [];

if ($competitionId > 0) {
    $where[] = 'c.competition_id = ?';
    $params[] = $competitionId;
}

if ($course !== '') {
    $where[] = 'c.course = ?';
    $params[] = $course;
}

if ($shift !== '') {
    $where[] = 'c.shift = ?';
    $params[] = $shift;
}

$whereClause = implode(' AND ', $where);

$candidates = Database::fetchAll(
    "SELECT c.*, cmp.name as competition_name, cmp.code as competition_code, cmp.competition_date, cmp.venue,
            att.status as attendance_status
     FROM candidates c
     JOIN competitions cmp ON c.competition_id = cmp.id
     LEFT JOIN attendance att ON c.id = att.candidate_id AND att.competition_id = c.competition_id
     WHERE {$whereClause}
     ORDER BY c.roll_number ASC, c.registration_number ASC",
    $params
);

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate List — <?= e($competition ? $competition['name'] : 'All Competitions') ?></title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        body {
            background-color: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .print-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .institute-title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .system-subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .competition-title {
            font-size: 18px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 4px;
        }
        .meta-info {
            font-size: 13px;
            color: #555;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-print th, .table-print td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        .table-print th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center !important; }
        .attendance-box {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 1px solid #000;
            margin-right: 4px;
            vertical-align: middle;
        }
        .print-actions {
            margin-bottom: 20px;
            text-align: right;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            font-size: 12px;
        }
        @media print {
            .print-actions, .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            @page {
                margin: 15mm;
                size: A4 portrait;
            }
        }
    </style>
</head>
<body>

    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print me-1"></i> Print / Save PDF
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">
            <i class="fas fa-times me-1"></i> Close
        </button>
    </div>

    <!-- Official Header -->
    <div class="print-header">
        <div class="institute-title"><?= e($instituteName) ?></div>
        <div class="system-subtitle"><?= e($systemName) ?></div>
        <?php if ($competition): ?>
            <div class="competition-title"><?= e($competition['name']) ?> (<?= e($competition['code']) ?>)</div>
            <div class="meta-info">
                <strong>Date:</strong> <?= formatDate($competition['competition_date'], 'l, d F Y') ?>
                <?php if ($competition['venue']): ?>
                    &nbsp;|&nbsp; <strong>Venue:</strong> <?= e($competition['venue']) ?>
                <?php endif; ?>
                <?php if ($course): ?>
                    &nbsp;|&nbsp; <strong>Course:</strong> <?= e($course) ?>
                <?php endif; ?>
                <?php if ($shift): ?>
                    &nbsp;|&nbsp; <strong>Shift:</strong> <?= e(ucfirst($shift)) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="competition-title">All Candidates Master List</div>
        <?php endif; ?>
        <div class="meta-info mt-1">
            <strong>Total Candidates:</strong> <?= count($candidates) ?>
            &nbsp;|&nbsp; <strong>Printed:</strong> <?= date('d M Y, h:i A') ?>
        </div>
    </div>

    <!-- Candidates Table -->
    <table class="table-print">
        <thead>
            <tr>
                <th width="40" class="text-center">Sr.</th>
                <th width="75">Roll #</th>
                <th width="115">Reg #</th>
                <th>Candidate Name</th>
                <th>Father Name</th>
                <th width="90">Course</th>
                <th width="70">Shift</th>
                <th width="60" class="text-center">Seat #</th>
                <th width="95" class="text-center">Attendance</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($candidates)): ?>
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No candidates found for this selection.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($candidates as $i => $cand): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td><strong><?= e($cand['roll_number'] ?: '—') ?></strong></td>
                        <td style="font-family: monospace;"><?= e($cand['registration_number']) ?></td>
                        <td><strong><?= e($cand['full_name']) ?></strong></td>
                        <td><?= e($cand['father_name'] ?: '—') ?></td>
                        <td><?= e($cand['course'] ?: '—') ?></td>
                        <td><?= e(ucfirst($cand['shift'] ?? '')) ?></td>
                        <td class="text-center"><?= e($cand['seat_number'] ?: '—') ?></td>
                        <td class="text-center">
                            <?php if ($cand['attendance_status'] === 'present'): ?>
                                <span style="font-weight: bold; color: green;">[✓] Present</span>
                            <?php elseif ($cand['attendance_status'] === 'absent'): ?>
                                <span style="font-weight: bold; color: red;">[✗] Absent</span>
                            <?php else: ?>
                                <span class="attendance-box"></span> P &nbsp; <span class="attendance-box"></span> A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature Block -->
    <div class="signature-section">
        <div class="signature-line">
            Prepared By
        </div>
        <div class="signature-line">
            Invigilator In-charge
        </div>
        <div class="signature-line">
            Head of Institute / Center Supt.
        </div>
    </div>

    <div class="text-center small text-muted mt-4 pt-2 border-top">
        <?= e($systemName) ?> &bull; Developed by Ahsan Raza &bull; <?= date('Y-m-d H:i:s') ?>
    </div>

</body>
</html>
