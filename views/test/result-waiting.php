<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Gated Result Waiting Screen
 * Displays submission confirmation while instructor withholds results.
 * Includes non-intrusive polling every 5 seconds to auto-reveal scorecards upon release.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Submitted — vibe.Sınav</title>
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
            --border-color: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --ledger-bg: #f8fafc;
            --station-badge-bg: #e0f2fe;
            --station-badge-text: #0369a1;
            --station-badge-border: #7dd3fc;
        }

        [data-bs-theme="dark"] {
            color-scheme: dark;
            --bg-primary: #0a0e17;
            --card-bg: #131b2e;
            --border-color: #263352;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --ledger-bg: rgba(15, 23, 42, 0.6);
            --station-badge-bg: #1e293b;
            --station-badge-text: #38bdf8;
            --station-badge-border: #0284c7;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-main);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .waiting-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 620px;
            overflow: hidden;
            text-align: center;
            padding: 3rem 2.5rem;
            transition: all 0.2s ease;
        }
        .ledger-box {
            background: var(--ledger-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem 1.25rem;
        }
        .station-badge {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 700;
            background: var(--station-badge-bg);
            color: var(--station-badge-text);
            border: 1px solid var(--station-badge-border);
            padding: 0.3rem 0.85rem;
            border-radius: 6px;
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

<div class="waiting-card">
    <div class="mb-4">
        <span class="d-inline-flex p-4 rounded-circle bg-success bg-opacity-10 text-success mb-3">
            <i class="fas fa-circle-check fa-4x"></i>
        </span>
        <h3 class="fw-bold mb-1" style="color: var(--text-main);">Test Submitted Successfully</h3>
        <p class="text-muted small">Your examination session has been authoritatively recorded and locked.</p>
    </div>

    <!-- Candidate Identification Ledger -->
    <div class="ledger-box mb-4 text-start">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small text-uppercase fw-bold">Candidate:</span>
            <span class="fw-bold" style="color: var(--text-main);"><?= e($attempt['student_name']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small text-uppercase fw-bold">Roll / Seat Number:</span>
            <span class="text-info font-monospace fw-bold"><?= e($attempt['student_roll_no']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small text-uppercase fw-bold">Workstation:</span>
            <span class="station-badge"><?= e($attempt['station_code']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small text-uppercase fw-bold">Submitted At:</span>
            <span class="text-light small font-monospace"><?= e($attempt['submitted_at'] ?? date('Y-m-d H:i:s')) ?></span>
        </div>
    </div>

    <?php if (!empty($attempt['needs_review']) && empty($attempt['is_reviewed'])): ?>
    <!-- Subjective Manual Review Notice -->
    <div class="alert alert-warning py-3 px-4 border-warning border-opacity-50 bg-warning bg-opacity-10 text-start mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-clipboard-check fa-2x text-warning"></i>
            <div>
                <div class="fw-bold text-warning">Written Responses Under Teacher Review</div>
                <div class="small text-muted">
                    Your assessment includes questions requiring instructor evaluation (Fill in the Blanks / Written Responses). Results are withheld while your teacher grades your responses. Your scorecard will appear automatically once finalized.
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Instructor Withheld Notice -->
    <div class="alert alert-info py-3 px-4 border-info border-opacity-50 bg-info bg-opacity-10 text-start mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-lock fa-2x text-info"></i>
            <div>
                <div class="fw-bold" style="color: var(--text-main);">Results Withheld by Instructor</div>
                <div class="small text-muted">
                    Your exam has been submitted and recorded. Results are withheld by the instructor until all students have completed the test session.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Real-time Poller -->
    <div class="small text-muted d-flex align-items-center justify-content-center gap-2">
        <span class="spinner-border spinner-border-sm text-info"></span>
        <span id="pollStatusText"><?= (!empty($attempt['needs_review']) && empty($attempt['is_reviewed'])) ? 'Waiting for instructor to evaluate written responses...' : 'Listening for instructor result release...' ?></span>
    </div>
</div>

<div class="text-center mt-3 text-muted small">
    <div><strong>vibe.Sınav</strong> Assessment System</div>
    <div>Developed by Ahsan Raza</div>
</div>

<script>
(function() {
    const attemptId = <?= (int)$attempt['id'] ?>;
    const pollUrl = '<?= url("api-check-result-status") ?>&attempt_id=' + attemptId;

    function checkStatus() {
        fetch(pollUrl)
            .then(res => res.json())
            .then(data => {
                if (data.is_published) {
                    const statusText = document.getElementById('pollStatusText');
                    if (statusText) statusText.textContent = 'Results released! Loading scorecard...';
                    setTimeout(() => window.location.reload(), 800);
                }
            })
            .catch(err => console.log('Waiting poll error:', err));
    }

    // Poll every 5 seconds
    setInterval(checkStatus, 5000);
})();
</script>

</body>
</html>

