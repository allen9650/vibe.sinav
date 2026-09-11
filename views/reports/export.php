<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Assessment Reports Export Engine
 * Generates and streams:
 * 1. CSV Spreadsheet (RFC 4180 with UTF-8 BOM)
 * 2. Standalone Offline HTML Executive Dossier & Report Card
 */

Middleware::requireAuth();

$assessmentId = (int)($_GET['id'] ?? ($_GET['assessment_id'] ?? 0));
$format = strtolower(trim($_GET['format'] ?? 'csv'));

// Auto-detect latest assessment if not specified
if ($assessmentId <= 0) {
    $detected = Database::fetch("SELECT id FROM assessments WHERE status = 'published' ORDER BY id DESC LIMIT 1");
    if (!$detected) {
        $detected = Database::fetch("SELECT id FROM assessments ORDER BY id DESC LIMIT 1");
    }
    $assessmentId = $detected ? (int)$detected['id'] : 0;
}

if ($assessmentId <= 0) {
    Session::flash('error', 'Valid assessment ID is required.');
    redirectTo('reports');
}

$assessment = Database::fetch(
    "SELECT a.*, camp.name AS campus_name, sub.name AS subject_name, u.username AS teacher_name
     FROM assessments a
     LEFT JOIN campuses camp ON a.campus_id = camp.id
     LEFT JOIN subjects sub ON a.subject_id = sub.id
     LEFT JOIN users u ON a.created_by = u.id
     WHERE a.id = ?",
    [$assessmentId]
);

if (!$assessment) {
    Session::flash('error', 'Assessment not found.');
    redirectTo('reports');
}

$totalQuestionsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
    [$assessmentId]
);

// Fetch all student attempts and results ranked by score
$rawRecords = Database::fetchAll(
    "SELECT 
         att.id AS attempt_id,
         att.student_roll_no,
         att.student_name,
         att.student_class,
         att.started_at,
         att.submitted_at,
         att.status AS attempt_status,
         ls.station_code,
         ls.ip_address,
         COALESCE(res.total_marks, a.total_marks, 0.00) AS total_marks,
         COALESCE(res.obtained_marks, (SELECT SUM(aa.marks_obtained) FROM assessment_answers aa WHERE aa.attempt_id = att.id), 0.00) AS obtained_marks,
         res.percentage,
         res.pass_fail,
         res.generated_at,
         (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id AND (aa.selected_option_ids IS NOT NULL OR (aa.text_answer IS NOT NULL AND aa.text_answer != ''))) AS answered_count,
         (SELECT COUNT(*) FROM assessment_answers aa WHERE aa.attempt_id = att.id AND aa.is_correct = 1) AS correct_count,
         (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = att.id OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.attempt_id')) = CAST(att.id AS CHAR)) AS violations_count
     FROM assessment_attempts att
     JOIN assessments a ON att.assessment_id = a.id
     LEFT JOIN lab_stations ls ON att.station_id = ls.id
     LEFT JOIN assessment_results res ON att.id = res.attempt_id
     WHERE att.assessment_id = ?
     ORDER BY obtained_marks DESC, att.student_roll_no ASC",
    [$assessmentId]
);

$passThreshold = (float)($assessment['passing_percentage'] ?? 40.0);
$records = [];
$rank = 1;
$passedCount = 0;
$failedCount = 0;
$percentages = [];
$scores = [];

$gradeDistribution = [
    'A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
];

foreach ($rawRecords as $r) {
    $maxM = (float)$r['total_marks'];
    $obtM = (float)$r['obtained_marks'];
    $pct = ($r['percentage'] !== null) 
        ? (float)$r['percentage'] 
        : (($maxM > 0) ? round(($obtM / $maxM) * 100, 1) : 0.0);

    $grade = 'F';
    if ($pct >= 90.0) $grade = 'A+';
    elseif ($pct >= 80.0) $grade = 'A';
    elseif ($pct >= 70.0) $grade = 'B';
    elseif ($pct >= 60.0) $grade = 'C';
    elseif ($pct >= 50.0) $grade = 'D';
    else $grade = 'F';

    $gradeDistribution[$grade]++;

    $passFail = $r['pass_fail'] ?? ($pct >= $passThreshold ? 'pass' : 'fail');
    if ($passFail === 'pass') $passedCount++;
    else $failedCount++;

    $percentages[] = $pct;
    $scores[] = $obtM;

    $duration = '—';
    if (!empty($r['started_at'])) {
        $end = !empty($r['submitted_at']) ? strtotime($r['submitted_at']) : time();
        $diff = max(0, $end - strtotime($r['started_at']));
        $duration = sprintf('%dm %ds', floor($diff / 60), $diff % 60);
    }

    $records[] = array_merge($r, [
        'rank'         => $rank++,
        'computed_pct' => $pct,
        'grade'        => $grade,
        'final_status' => $passFail,
        'duration_fmt' => $duration,
    ]);
}

$totalCandidates = count($records);
$classAvg = !empty($percentages) ? round(array_sum($percentages) / count($percentages), 1) : 0.0;
$passRate = $totalCandidates > 0 ? round(($passedCount / $totalCandidates) * 100, 1) : 0.0;
$highScore = !empty($scores) ? max($scores) : 0.0;
$lowScore = !empty($scores) ? min($scores) : 0.0;

$instituteName = getSetting('institute_name', 'vibe.Sınav');
$systemName = getSetting('system_name', 'vibe.Sınav');
$safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$assessment['title']);

// =============================================================
// FORMAT 1: CSV SPREADSHEET
// =============================================================
if ($format === 'csv') {
    $filename = sprintf("Assessment_Report_%s_%s_%s.csv", $assessmentId, $safeTitle, date('Ymd_His'));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    // CSV Headers
    fputcsv($output, [
        'Rank',
        'Workstation Code',
        'Roll Number',
        'Candidate Full Name',
        'Class / Grade',
        'Obtained Marks',
        'Total Marks',
        'Percentage (%)',
        'Letter Grade',
        'Result Status',
        'Correct Answers',
        'Questions Attempted',
        'Total Questions',
        'Time Taken',
        'Security Violations',
        'Exam Start Time',
        'Exam Submit Time'
    ]);

    foreach ($records as $row) {
        fputcsv($output, [
            $row['rank'],
            $row['station_code'] ?? 'Workstation',
            $row['student_roll_no'] ?? 'N/A',
            $row['student_name'] ?? 'N/A',
            $row['student_class'] ?? ($assessment['target_class'] ?? 'General'),
            number_format((float)$row['obtained_marks'], 2),
            number_format((float)$row['total_marks'], 2),
            number_format((float)$row['computed_pct'], 1) . '%',
            $row['grade'],
            strtoupper((string)$row['final_status']),
            $row['correct_count'],
            $row['answered_count'],
            $totalQuestionsCount,
            $row['duration_fmt'],
            $row['violations_count'],
            $row['started_at'] ?? '—',
            $row['submitted_at'] ?? '—',
        ]);
    }
    fclose($output);
    exit;
}

// =============================================================
// FORMAT 2: STANDALONE OFFLINE HTML EXECUTIVE DOSSIER
// =============================================================
$filename = sprintf("PTM_Executive_Dossier_%s_%s_%s.html", $assessmentId, $safeTitle, date('Ymd_His'));

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($assessment['title']) ?> — Executive Assessment Dossier</title>
    <?= getFaviconTag() ?>
    <style>
        :root {
            --primary: #4361ee;
            --success: #2ec4b6;
            --danger: #e71d36;
            --warning: #ff9f1c;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: #f1f5f9; color: var(--text-main); line-height: 1.5; padding: 30px 20px; }
        .dossier-container { max-width: 1080px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 40px; border: 1px solid var(--border); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 25px; }
        .brand-title { font-size: 24px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
        .brand-sub { font-size: 13px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
        .meta-box { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 18px; margin-bottom: 25px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .meta-item .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 2px; }
        .meta-item .val { font-size: 15px; font-weight: 700; color: var(--text-main); }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .kpi-card { background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center; }
        .kpi-label { font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 5px; }
        .kpi-val { font-size: 28px; font-weight: 800; color: var(--primary); }
        .kpi-sub { font-size: 12px; color: var(--text-muted); }
        .grades-strip { display: flex; gap: 10px; margin-bottom: 30px; }
        .grade-box { flex: 1; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 12px; text-align: center; }
        .grade-badge { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 12px; font-weight: 800; color: white; background: #64748b; margin-bottom: 5px; }
        .grade-badge.Ap { background: #10b981; }
        .grade-badge.A { background: #06b6d4; }
        .grade-badge.B { background: #3b82f6; }
        .grade-badge.C { background: #f59e0b; }
        .grade-badge.D { background: #6b7280; }
        .grade-badge.F { background: #ef4444; }
        .table-responsive { width: 100%; overflow-x: auto; margin-bottom: 35px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        th { background: #f8fafc; color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; padding: 12px 10px; border-bottom: 2px solid var(--border); }
        td { padding: 12px 10px; border-bottom: 1px solid var(--border); }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-pass { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-station { font-family: monospace; background: #e2e8f0; color: #334155; font-size: 12px; }
        .rank-top { font-weight: 800; color: #d97706; }
        .print-btn { background: var(--primary); color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: 700; border-radius: 6px; cursor: pointer; }
        .footer-signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--border); text-align: center; }
        .sign-line { margin-top: 45px; border-top: 1px solid #475569; padding-top: 6px; font-size: 12px; font-weight: 700; color: var(--text-muted); }
        @media print {
            body { background: #ffffff; padding: 0; }
            .dossier-container { box-shadow: none; border: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="dossier-container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="brand-title"><?= htmlspecialchars($instituteName) ?></div>
                <div class="brand-sub">Official Academic Performance & Candidate Merit Dossier</div>
            </div>
            <div style="text-align: right;">
                <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Generated on <?= date('F d, Y • h:i A') ?></div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-box">
            <div class="meta-item">
                <div class="label">Assessment Title</div>
                <div class="val"><?= htmlspecialchars($assessment['title']) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Subject</div>
                <div class="val"><?= htmlspecialchars($assessment['subject_name'] ?? 'General') ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Target Class / Grade</div>
                <div class="val"><?= htmlspecialchars($assessment['target_class'] ?? 'General') ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Examiner / Teacher</div>
                <div class="val"><?= htmlspecialchars($assessment['teacher_name'] ?? 'Faculty') ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Total Marks / Pass %</div>
                <div class="val"><?= number_format((float)$assessment['total_marks'], 2) ?> marks (<?= number_format((float)$assessment['passing_percentage'], 1) ?>%)</div>
            </div>
            <div class="meta-item">
                <div class="label">Duration & Questions</div>
                <div class="val"><?= (int)$assessment['duration_minutes'] ?> mins • <?= $totalQuestionsCount ?> Questions</div>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Candidates</div>
                <div class="kpi-val"><?= $totalCandidates ?></div>
                <div class="kpi-sub">Students Appeared</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Class Average</div>
                <div class="kpi-val" style="color: #06b6d4;"><?= $classAvg ?>%</div>
                <div class="kpi-sub">Overall Mean Score</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Overall Pass Rate</div>
                <div class="kpi-val" style="color: <?= $passRate >= 50 ? '#10b981' : '#ef4444' ?>;"><?= $passRate ?>%</div>
                <div class="kpi-sub"><?= $passedCount ?> Passed • <?= $failedCount ?> Failed</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Highest Score</div>
                <div class="kpi-val" style="color: #f59e0b;"><?= $highScore ?></div>
                <div class="kpi-sub">Top Mark Earned</div>
            </div>
        </div>

        <!-- Grade Spectrum Breakdown -->
        <h4 style="font-size: 14px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 12px;">Grade Distribution Breakdown</h4>
        <div class="grades-strip">
            <?php foreach (['A+' => 'Ap', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'F' => 'F'] as $lbl => $cssClass): ?>
                <?php $cnt = $gradeDistribution[$lbl]; $pct = $totalCandidates > 0 ? round(($cnt / $totalCandidates) * 100, 1) : 0; ?>
                <div class="grade-box">
                    <span class="grade-badge <?= $cssClass ?>"><?= $lbl ?></span>
                    <div style="font-size: 18px; font-weight: 800;"><?= $cnt ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= $pct ?>%</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Merit Roster Table -->
        <h4 style="font-size: 14px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 12px;">Official Candidate-Wise Merit Roster</h4>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">Rank</th>
                        <th>Workstation</th>
                        <th>Roll Number</th>
                        <th>Candidate Full Name</th>
                        <th>Class</th>
                        <th>Score / Total</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Result</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 30px;">No candidate attempts recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td class="<?= $row['rank'] <= 3 ? 'rank-top' : '' ?>">#<?= $row['rank'] ?></td>
                                <td><span class="badge badge-station"><?= htmlspecialchars($row['station_code'] ?? 'Workstation') ?></span></td>
                                <td style="font-family: monospace; font-weight: 700;"><?= htmlspecialchars($row['student_roll_no']) ?></td>
                                <td style="font-weight: 700;"><?= htmlspecialchars($row['student_name']) ?></td>
                                <td><?= htmlspecialchars($row['student_class'] ?? ($assessment['target_class'] ?? 'General')) ?></td>
                                <td style="font-family: monospace; font-weight: 700;"><?= number_format((float)$row['obtained_marks'], 2) ?> / <?= number_format((float)$row['total_marks'], 2) ?></td>
                                <td style="font-weight: 700;"><?= $row['computed_pct'] ?>%</td>
                                <td><strong><?= $row['grade'] ?></strong></td>
                                <td><span class="badge <?= $row['final_status'] === 'pass' ? 'badge-pass' : 'badge-fail' ?>"><?= strtoupper($row['final_status']) ?></span></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= $row['duration_fmt'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Signatures -->
        <div class="footer-signatures">
            <div>
                <div class="sign-line">Lead Invigilator</div>
            </div>
            <div>
                <div class="sign-line">Head of Examination</div>
            </div>
            <div>
                <div class="sign-line">Academic Dean / Principal</div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit;
