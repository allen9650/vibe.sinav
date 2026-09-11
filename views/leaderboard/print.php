<?php
/**
 * Official Competition Standings — Dedicated Print View (A4 Portrait)
 * Marr Typing Competition System
 */

$selectedCompId = (int)($_GET['competition_id'] ?? 0);
$allAttempts = (bool)($_GET['all_attempts'] ?? 0);

if ($selectedCompId === 0) {
    $firstComp = Database::fetchColumn("SELECT id FROM competitions ORDER BY id DESC LIMIT 1");
    $selectedCompId = $firstComp ? (int)$firstComp : 0;
}

$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$selectedCompId]);
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$selectedCompId]) ?: [];

if (!$competition) {
    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'><h3>Competition not found.</h3><p><a href='javascript:window.close()'>Close</a></p></div>";
    exit;
}

// Fetch authoritative standings via RankingService
$leaderboard = RankingService::getLeaderboard($selectedCompId, $allAttempts);

$isFinalized = !empty($competition['results_finalized_at']);
$isLocked = !empty($competition['results_locked']);

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
$instituteAddress = getSetting('institute_address', '');

$certLogo = getSetting('cert_logo', '');
$instituteLogo = getSetting('institute_logo', '');
$logoPath = !empty($certLogo) ? $certLogo : $instituteLogo;
$hasLogo = false;
$logoUrl = '';
if (!empty($logoPath)) {
    if (file_exists(ROOT_PATH . '/public/' . ltrim($logoPath, '/'))) {
        $hasLogo = true;
        $logoUrl = asset(ltrim($logoPath, '/'));
    }
}

// Pre-calculate summary metrics
$totalRanked = count($leaderboard);
$qualifiedCount = 0;
$topWpm = 0.00;
foreach ($leaderboard as $row) {
    if ($row['qualification_status'] === 'qualified') {
        $qualifiedCount++;
    }
    if ((float)$row['net_wpm'] > $topWpm) {
        $topWpm = (float)$row['net_wpm'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Standings — <?= e($competition['name']) ?> (<?= e($competition['code']) ?>)</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        /* Document Setup: A4 Portrait */
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: 'Segoe UI', Arial, -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 24px 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .screen-toolbar {
            max-width: 210mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .print-document {
            background: #ffffff;
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm 15mm;
            border: 1px solid #cbd5e1;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* Header Layout */
        .doc-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .doc-header-logo {
            flex-shrink: 0;
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .doc-header-logo img {
            max-width: 75px;
            max-height: 75px;
            object-fit: contain;
        }

        .doc-header-logo .fallback-crest {
            width: 70px;
            height: 70px;
            background: #0f172a;
            color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .doc-header-text {
            flex-grow: 1;
            text-align: center;
        }

        .institute-title {
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }

        .document-title {
            font-size: 13pt;
            font-weight: 700;
            color: #0284c7;
            margin: 0 0 4px 0;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .institute-sub {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }

        /* Competition Metadata Card */
        .meta-strip {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 2fr 1fr 1.2fr 1fr;
            gap: 8px 14px;
            font-size: 9.5pt;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }

        .meta-value {
            font-weight: 600;
            color: #0f172a;
        }

        .meta-value.status-final {
            color: #15803d;
            font-weight: 700;
        }

        .meta-value.status-prov {
            color: #b45309;
            font-weight: 700;
        }

        /* Standings Table */
        .standings-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 9pt;
        }

        .standings-table thead {
            display: table-header-group;
        }

        .standings-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .standings-table th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.4px;
            padding: 7px 6px;
            border: 1px solid #0f172a;
            text-align: center;
        }

        .standings-table th.col-name {
            text-align: left;
            padding-left: 8px;
        }

        .standings-table td {
            padding: 6px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
            color: #0f172a;
        }

        .standings-table td.col-name {
            text-align: left;
            padding-left: 8px;
            font-weight: 600;
        }

        .standings-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .standings-table tbody tr.top-1 {
            background-color: #fef9c3 !important; /* Gold soft highlight */
        }

        .standings-table tbody tr.top-2 {
            background-color: #f1f5f9 !important; /* Silver soft highlight */
        }

        .standings-table tbody tr.top-3 {
            background-color: #ffedd5 !important; /* Bronze soft highlight */
        }

        .rank-badge {
            font-weight: 800;
            font-size: 9pt;
        }

        .rank-1 { color: #b45309; }
        .rank-2 { color: #475569; }
        .rank-3 { color: #c2410c; }

        .qual-badge {
            font-weight: 700;
            font-size: 7.5pt;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            text-transform: uppercase;
        }

        .qual-pass {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .qual-fail {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        /* Summary Stats Footer */
        .summary-bar {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 14px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5pt;
            color: #334155;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Signatures Section */
        .signatures-section {
            margin-top: 35px;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .sign-box {
            text-align: center;
            width: 220px;
        }

        .sign-line {
            border-top: 1.5px solid #0f172a;
            margin-bottom: 6px;
            padding-top: 4px;
        }

        .sign-title {
            font-size: 9pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }

        .sign-sub {
            font-size: 7.5pt;
            color: #64748b;
        }

        /* Official Doc Footer */
        .doc-footer {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #94a3b8;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .screen-toolbar {
                display: none !important;
            }

            .print-document {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            /* Ensure background colors print cleanly */
            .standings-table th {
                background-color: #1e293b !important;
                color: #ffffff !important;
            }

            .standings-table tbody tr.top-1 {
                background-color: #fef9c3 !important;
            }
            .standings-table tbody tr.top-2 {
                background-color: #f1f5f9 !important;
            }
            .standings-table tbody tr.top-3 {
                background-color: #ffedd5 !important;
            }

            .qual-pass {
                background-color: #dcfce7 !important;
                color: #166534 !important;
            }
            .qual-fail {
                background-color: #f1f5f9 !important;
                color: #475569 !important;
            }
        }
    </style>
</head>
<body>

<!-- Screen-Only Toolbar -->
<div class="screen-toolbar no-print">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary text-white"><i class="fas fa-file-invoice me-1"></i> A4 Standings Sheet</span>
        <span class="text-muted small">|</span>
        <span class="text-secondary small">Competition: <strong><?= e($competition['name']) ?></strong></span>
        <?php if ($allAttempts): ?>
            <span class="badge bg-info text-dark ms-2">All Attempts Mode</span>
        <?php else: ?>
            <span class="badge bg-secondary text-light ms-2">Best Attempt Per Candidate</span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
            <i class="fas fa-print me-1"></i> Print / Save PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm px-3">
            <i class="fas fa-times me-1"></i> Close
        </button>
    </div>
</div>

<!-- Official Printable Document -->
<div class="print-document">

    <!-- Header -->
    <header class="doc-header">
        <div class="doc-header-logo">
            <?php if ($hasLogo): ?>
                <img src="<?= $logoUrl ?>" alt="Logo">
            <?php else: ?>
                <div class="fallback-crest">
                    <i class="fas fa-keyboard"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="doc-header-text">
            <h1 class="institute-title"><?= e($instituteName) ?></h1>
            <div class="document-title">Official Typing Competition Standings</div>
            <p class="institute-sub"><?= e($instituteAddress) ?></p>
        </div>
        <div class="doc-header-logo" style="visibility: <?= $hasLogo ? 'visible' : 'hidden' ?>;">
            <?php if ($hasLogo): ?>
                <img src="<?= $logoUrl ?>" alt="Logo" style="filter: grayscale(100%) opacity(0.85);">
            <?php endif; ?>
        </div>
    </header>

    <!-- Competition Info Strip -->
    <section class="meta-strip">
        <div class="meta-item">
            <span class="meta-label">Competition Name</span>
            <span class="meta-value"><?= e($competition['name']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Code</span>
            <span class="meta-value font-monospace"><?= e($competition['code']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Date & Time</span>
            <span class="meta-value"><?= formatDate($competition['competition_date']) ?><?= !empty($competition['start_time']) ? ' (' . substr($competition['start_time'], 0, 5) . ')' : '' ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Results Status</span>
            <?php if ($isFinalized): ?>
                <span class="meta-value status-final"><i class="fas fa-check-circle me-1"></i> FINAL RESULTS</span>
            <?php else: ?>
                <span class="meta-value status-prov"><i class="fas fa-clock me-1"></i> PROVISIONAL</span>
            <?php endif; ?>
        </div>
    </section>

    <!-- Rankings Table -->
    <table class="standings-table">
        <thead>
            <tr>
                <th style="width: 46px;">Rank</th>
                <th class="col-name" style="width: 170px;">Candidate Name</th>
                <th style="width: 60px;">Roll #</th>
                <th style="width: 100px;">Course / Shift</th>
                <th style="width: 58px;">Gross WPM</th>
                <th style="width: 58px;">Net WPM</th>
                <th style="width: 55px;">Accuracy</th>
                <th style="width: 45px;">Errors</th>
                <th style="width: 58px;">Score</th>
                <th style="width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($leaderboard)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        No completed test results recorded for this competition.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($leaderboard as $row): ?>
                    <?php
                    $rankNum = (int)$row['rank'];
                    $topClass = match($rankNum) {
                        1 => 'top-1',
                        2 => 'top-2',
                        3 => 'top-3',
                        default => '',
                    };

                    $rankDisplay = match($rankNum) {
                        1 => '<span class="rank-badge rank-1">1st 🥇</span>',
                        2 => '<span class="rank-badge rank-2">2nd 🥈</span>',
                        3 => '<span class="rank-badge rank-3">3rd 🥉</span>',
                        default => '<span class="rank-badge text-secondary">#' . $rankNum . '</span>',
                    };

                    $isPass = ($row['qualification_status'] === 'qualified');
                    ?>
                    <tr class="<?= $topClass ?>">
                        <td><?= $rankDisplay ?></td>
                        <td class="col-name"><?= e($row['candidate_name']) ?></td>
                        <td class="font-monospace fw-bold"><?= e($row['roll_number'] ?: '—') ?></td>
                        <td class="small text-muted"><?= e($row['course'] ?: '—') ?><?= !empty($row['shift']) ? ' (' . e($row['shift']) . ')' : '' ?></td>
                        <td class="font-monospace"><?= number_format((float)$row['gross_wpm'], 2) ?></td>
                        <td class="font-monospace fw-bold text-dark"><?= number_format((float)$row['net_wpm'], 2) ?></td>
                        <td class="font-monospace"><?= number_format((float)$row['accuracy'], 2) ?>%</td>
                        <td class="font-monospace"><?= (int)$row['error_count'] ?></td>
                        <td class="font-monospace fw-bold"><?= number_format((float)$row['score'], 2) ?></td>
                        <td>
                            <?php if ($isPass): ?>
                                <span class="qual-badge qual-pass">QUALIFIED</span>
                            <?php else: ?>
                                <span class="qual-badge qual-fail">NOT QUALIFIED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Summary Statistics Bar -->
    <div class="summary-bar">
        <span><strong>Total Ranked:</strong> <?= $totalRanked ?> candidate(s)</span>
        <span><strong>Qualified:</strong> <?= $qualifiedCount ?> (<?= $totalRanked > 0 ? round(($qualifiedCount / $totalRanked) * 100, 1) : 0 ?>%)</span>
        <span><strong>Top Speed:</strong> <?= number_format($topWpm, 2) ?> WPM</span>
        <span><strong>Selection Mode:</strong> <?= $allAttempts ? 'All Attempts Included' : 'Best Attempt Per Candidate' ?></span>
    </div>

    <!-- Official Signatures Area -->
    <div class="signatures-section">
        <div class="sign-box">
            <div class="sign-line"></div>
            <div class="sign-title">Competition Coordinator</div>
            <div class="sign-sub">Department of Information Technology</div>
        </div>

        <div class="sign-box">
            <div class="sign-line"></div>
            <div class="sign-title">Director / Principal</div>
            <div class="sign-sub"><?= e($instituteName) ?></div>
        </div>
    </div>

    <!-- Document Footer -->
    <footer class="doc-footer">
        <span>Generated On: <?= date('d M Y, h:i A') ?></span>
        <span>Official Verification Code: <?= strtoupper(substr(md5($selectedCompId . $competition['code'] . ($competition['results_finalized_at'] ?? 'live')), 0, 12)) ?></span>
        <span><?= e($systemName) ?> • Developed by Ahsan Raza</span>
    </footer>

</div>

</body>
</html>
