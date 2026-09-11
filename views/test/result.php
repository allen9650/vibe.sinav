<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Gated Candidate Scorecard Screen
 * Renders waiting screen if withheld (is_result_published == 0) or full scorecard if released (is_result_published == 1).
 */

$attemptId = (int)($_GET['attempt_id'] ?? (Session::get('current_attempt_id') ?? 0));

if ($attemptId <= 0) {
    redirectTo('test-start');
}

// Fetch attempt and assessment details
$attempt = Database::fetch(
    "SELECT att.*, a.title, a.target_class, a.duration_minutes, a.total_marks AS assessment_total, 
            a.passing_percentage, a.is_result_published,
            s.station_code, sub.name AS subject_name
     FROM assessment_attempts att
     JOIN assessments a ON att.assessment_id = a.id
     JOIN lab_stations s ON att.station_id = s.id
     JOIN subjects sub ON a.subject_id = sub.id
     WHERE att.id = ?",
    [$attemptId]
);

if (!$attempt) {
    Session::flash('error', 'Attempt record not found.');
    redirectTo('test-start');
}

// Check gated result publication policy
$isPublished = ((int)$attempt['is_result_published'] === 1);
$isReviewPending = ((int)($attempt['needs_review'] ?? 0) === 1 && (int)($attempt['is_reviewed'] ?? 0) === 0);

if (!$isPublished || $isReviewPending) {
    // Render the waiting view
    require __DIR__ . '/result-waiting.php';
    return;
}

// Fetch official result record
$result = Database::fetch(
    "SELECT * FROM assessment_results WHERE attempt_id = ?",
    [$attemptId]
);

// If results haven't been generated yet, evaluate now
if (!$result) {
    try {
        $result = ExamExecutionService::submitExam($attemptId);
    } catch (Throwable) {
        $result = [
            'total_marks'    => $attempt['assessment_total'],
            'obtained_marks' => 0.00,
            'percentage'     => 0.00,
            'pass_fail'      => 'fail',
        ];
    }
}

// Fetch question level breakdown
$answers = Database::fetchAll(
    "SELECT aa.*, q.question_text, q.question_type, q.marks AS max_marks
     FROM assessment_answers aa
     JOIN questions q ON aa.question_id = q.id
     WHERE aa.attempt_id = ?
     ORDER BY aa.id ASC",
    [$attemptId]
);

$isPassed = ($result['pass_fail'] === 'pass');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Scorecard — <?= e($attempt['title']) ?></title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        :root,
        [data-bs-theme="light"] {
            color-scheme: light;
            --bg-primary: #f1f5f9;
            --card-bg: #ffffff;
            --header-bg: #f8fafc;
            --border-color: #cbd5e1;
            --text-main: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent-green: #059669;
            --accent-danger: #dc2626;
            --summary-box-bg: #f8fafc;
            --station-badge-bg: #e0f2fe;
            --station-badge-text: #0369a1;
            --station-badge-border: #7dd3fc;
        }

        [data-bs-theme="dark"] {
            color-scheme: dark;
            --bg-primary: #0a0e17;
            --card-bg: #131b2e;
            --header-bg: #1a233a;
            --border-color: #263352;
            --text-main: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent-green: #10b981;
            --accent-danger: #ef4444;
            --summary-box-bg: rgba(15, 23, 42, 0.6);
            --station-badge-bg: #1e293b;
            --station-badge-text: #38bdf8;
            --station-badge-border: #0284c7;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-main);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            padding: 2.5rem 1rem;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .scorecard-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .scorecard-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .scorecard-header {
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 2rem;
        }
        .score-pill {
            font-size: 2.5rem;
            font-weight: 800;
            font-family: monospace;
            line-height: 1;
        }
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            --bs-table-border-color: var(--border-color);
        }
        .station-badge {
            font-family: monospace;
            font-size: 1rem;
            font-weight: 700;
            background: var(--station-badge-bg);
            color: var(--station-badge-text);
            border: 1px solid var(--station-badge-border);
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
        }
    </style>
</head>
<body class="position-relative">

<!-- Quick Theme Toggle in Top Corner -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <button type="button" class="btn btn-sm btn-outline-secondary theme-quick-toggle d-flex align-items-center gap-2 shadow-sm" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-main);" title="Toggle Light / Dark Mode">
        <i class="fas fa-sun text-warning"></i>
        <span class="small d-none d-sm-inline">Theme</span>
    </button>
</div>

<div class="scorecard-container">
    <div class="scorecard-card mb-4">
        <!-- Header -->
        <div class="scorecard-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="station-badge">
                    <i class="fas fa-desktop me-1"></i><?= e($attempt['station_code']) ?>
                </span>
                <span class="badge <?= $isPassed ? 'bg-success' : 'bg-danger' ?> px-3 py-2 fs-6 fw-bold text-uppercase">
                    <?= $isPassed ? '<i class="fas fa-check-circle me-1"></i> Examination Passed' : '<i class="fas fa-times-circle me-1"></i> Examination Failed' ?>
                </span>
            </div>
            <h3 class="fw-bold mb-1" style="color: var(--text-main);"><?= e($attempt['title']) ?></h3>
            <div class="small" style="color: var(--text-muted);">
                Subject: <span class="fw-semibold" style="color: var(--text-main);"><?= e($attempt['subject_name']) ?></span> | 
                Class: <span class="fw-semibold" style="color: var(--text-main);"><?= e($attempt['target_class']) ?></span>
            </div>
        </div>

        <!-- Student & Score Summary -->
        <div class="p-4 border-bottom border-secondary border-opacity-25">
            <div class="row g-4 align-items-center">
                <div class="col-md-7">
                    <div class="mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Candidate Name:</span>
                        <div class="fs-5 fw-bold" style="color: var(--text-main);"><?= e($attempt['student_name']) ?></div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Roll / Seat Number:</span>
                        <div class="fs-6 font-monospace text-info fw-bold"><?= e($attempt['student_roll_no']) ?></div>
                    </div>
                    <div class="small text-muted">
                        Submitted on <?= e($attempt['submitted_at'] ?? date('Y-m-d H:i')) ?>
                    </div>
                </div>

                <div class="col-md-5 text-md-end">
                    <div class="p-3 rounded d-inline-block text-center" style="background: var(--summary-box-bg); border: 1px solid var(--border-color); min-width: 200px;">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Obtained Score</div>
                        <div class="score-pill <?= $isPassed ? 'text-success' : 'text-danger' ?> mb-1">
                            <?= number_format((float)$result['obtained_marks'], 2) ?>
                        </div>
                        <div class="small text-muted font-monospace">
                            out of <?= number_format((float)$result['total_marks'], 2) ?> (<?= number_format((float)$result['percentage'], 2) ?>%)
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                            Passing threshold: <?= (float)$attempt['passing_percentage'] ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown by Question -->
        <div class="p-4">
            <h5 class="fw-bold mb-3" style="color: var(--text-main);">
                <i class="fas fa-list-check me-2 text-primary"></i> Question Performance Breakdown
            </h5>
            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th style="width: 50px;">#</th>
                            <th>Question</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                            <th style="width: 120px;" class="text-end">Marks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($answers as $i => $ans): ?>
                            <?php $correct = ((int)($ans['is_correct'] ?? 0) === 1); ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <div class="small fw-medium text-truncate" style="color: var(--text-main); max-width: 450px;">
                                        <?= e($ans['question_text']) ?>
                                    </div>
                                    <span class="badge bg-secondary bg-opacity-25 text-muted" style="font-size: 0.7rem;">
                                        <?= e(str_replace('_', ' ', (string)$ans['question_type'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($correct): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">
                                            <i class="fas fa-check"></i> Correct
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">
                                            <i class="fas fa-xmark"></i> Incorrect
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace fw-bold <?= $correct ? 'text-success' : 'text-muted' ?>">
                                    <?= number_format((float)$ans['marks_obtained'], 2) ?> / <?= number_format((float)$ans['max_marks'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-3 text-center" style="background: var(--summary-box-bg); border-top: 1px solid var(--border-color);">
            <div class="text-muted small mb-1">
                <i class="fas fa-lock me-1 text-secondary"></i> System Verified Official Examination Ledger • <strong>vibe.Sınav</strong>
            </div>
            <div class="text-muted small">Developed by Ahsan Raza</div>
        </div>
    </div>
</div>

</body>
</html>
