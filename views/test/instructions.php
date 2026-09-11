<?php
/**
 * Candidate Test Portal — Instruction & Start Test Screen
 */

if (!Session::get('candidate_logged_in') || !Session::get('candidate_id')) {
    redirectTo('test-portal');
}

$candidateId = (int)Session::get('candidate_id');
$competitionId = (int)Session::get('candidate_comp_id');

$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ? AND competition_id = ?", [$candidateId, $competitionId]);
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);

if (!$candidate || !$competition || !$settings) {
    Session::flash('error', 'Session error. Please login again.');
    redirectTo('test-portal');
}

// Calculate attempt number
$completedAttempts = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status IN ('completed', 'timed_out')",
    [$candidateId, $competitionId]
);
$currentAttemptNum = $completedAttempts + 1;
$maxAttempts = max(1, (int)$settings['max_attempts']);

if ($completedAttempts >= $maxAttempts) {
    Session::flash('error', "Maximum attempt limit ({$maxAttempts}) reached for this competition.");
    redirectTo('test-portal');
}

// Check if candidate already has an active in_progress attempt
$activeAttempt = Database::fetch(
    "SELECT * FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1",
    [$candidateId, $competitionId]
);

if ($activeAttempt) {
    $now = time();
    $end = strtotime($activeAttempt['expected_end_at']);
    if ($now < $end) {
        Session::set('current_attempt_id', $activeAttempt['id']);
        redirectTo('test-screen');
    }
}

$error = '';

// Handle Start Test POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'start_test') {
    CSRF::validateOrFail();

    Database::beginTransaction();
    try {
        // Re-check for duplicate active attempt to prevent double-clicks
        $existingActive = Database::fetch(
            "SELECT id FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status = 'in_progress' FOR UPDATE",
            [$candidateId, $competitionId]
        );

        if ($existingActive) {
            Database::commit();
            Session::set('current_attempt_id', $existingActive['id']);
            redirectTo('test-screen');
        }

        // Paragraph Assignment
        $assignedParagraphId = null;
        if ($settings['paragraph_mode'] === 'fixed' && !empty($settings['selected_paragraph_id'])) {
            $assignedParagraphId = (int)$settings['selected_paragraph_id'];
        } else {
            // Random Pool Mode: Randomly select one active assigned paragraph
            $pool = Database::fetchAll(
                "SELECT paragraph_id FROM competition_paragraphs WHERE competition_id = ? AND is_active = 1",
                [$competitionId]
            );
            if (!empty($pool)) {
                $randomIndex = array_rand($pool);
                $assignedParagraphId = (int)$pool[$randomIndex]['paragraph_id'];
            }
        }

        // Fallback if no paragraph found in pool
        if (!$assignedParagraphId) {
            $firstPara = Database::fetchColumn("SELECT id FROM typing_paragraphs WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            $assignedParagraphId = (int)$firstPara;
        }

        $paragraph = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$assignedParagraphId]);
        if (!$paragraph) {
            throw new Exception("Unable to assign typing test passage.");
        }

        $durationSeconds = max(10, (int)$settings['duration_seconds']);
        $startTime = date('Y-m-d H:i:s');
        $endTime = date('Y-m-d H:i:s', time() + $durationSeconds);

        // Insert new test attempt
        $attemptId = Database::insert('test_attempts', [
            'candidate_id'           => $candidateId,
            'competition_id'         => $competitionId,
            'paragraph_id'           => $assignedParagraphId,
            'original_text_snapshot' => $paragraph['content'],
            'attempt_number'         => $currentAttemptNum,
            'started_at'             => $startTime,
            'expected_end_at'        => $endTime,
            'duration_seconds'       => $durationSeconds,
            'status'                 => 'in_progress',
            'ip_address'             => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent'             => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500),
        ]);

        // Initialize test progress row
        Database::insert('test_progress', [
            'attempt_id'       => $attemptId,
            'typed_text'       => '',
            'current_position' => 0,
            'correct_chars'    => 0,
            'incorrect_chars'  => 0,
            'total_keystrokes' => 0,
        ]);

        // Update candidate status to test_started
        Database::update('candidates', ['status' => 'test_started'], 'id = ?', [$candidateId]);

        Database::commit();

        Session::set('current_attempt_id', $attemptId);
        CSRF::regenerate();
        redirectTo('test-screen');
    } catch (Exception $e) {
        Database::rollback();
        $error = 'Failed to launch test session: ' . $e->getMessage();
    }
}

$durationMins = round($settings['duration_seconds'] / 60, 1);
$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Instructions — <?= e($systemName) ?></title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
    <style>
        body {
            background-color: #0b0f19;
            color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 30px 15px;
        }
        .instruction-card {
            background: #151c2e;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.55);
            max-width: 840px;
            margin: 0 auto;
            overflow: hidden;
        }
        .instruction-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px 30px;
        }
        .instruction-header h3 {
            color: #ffffff;
            font-weight: 700;
        }
        .instruction-header .sub-brand {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .instruction-body {
            padding: 30px;
        }
        .candidate-meta-badge {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            padding: 14px 18px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        }
        .candidate-meta-label {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .candidate-meta-value {
            color: #ffffff;
            font-weight: 700;
        }
        .guidelines-card {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .guidelines-card h5 {
            color: #fbbf24;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .guidelines-card ol li {
            color: #cbd5e1;
            margin-bottom: 12px;
            line-height: 1.65;
            font-size: 0.95rem;
        }
        .guidelines-card ol li:last-child {
            margin-bottom: 0;
        }
        .guidelines-card ol li strong {
            color: #ffffff;
        }
        .agreement-box {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .agreement-box:hover {
            border-color: rgba(255, 255, 255, 0.25);
        }
        .agreement-box input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            flex-shrink: 0;
        }
        .agreement-box label {
            color: #f8fafc;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            margin-bottom: 0;
            user-select: none;
        }
        .btn-start-test {
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="instruction-card">
    <div class="instruction-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-keyboard text-primary me-2"></i> <?= e($systemName) ?></h3>
            <div class="sub-brand"><i class="fas fa-trophy text-warning me-1"></i> <?= e($competition['name']) ?> &nbsp;<span class="text-secondary">|</span>&nbsp; <span class="font-monospace text-light"><?= e($competition['code']) ?></span></div>
        </div>
        <div>
            <a href="<?= url('candidate-logout') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Exit
            </a>
        </div>
    </div>

    <div class="instruction-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 mb-4 small">
                <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Candidate Profile Summary -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="candidate-meta-badge">
                    <span class="candidate-meta-label">Candidate Name</span>
                    <span class="candidate-meta-value fs-6"><?= e($candidate['full_name']) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="candidate-meta-badge">
                    <span class="candidate-meta-label">Roll Number</span>
                    <span class="candidate-meta-value text-primary fs-5 font-monospace"><?= e($candidate['roll_number'] ?: '—') ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="candidate-meta-badge">
                    <span class="candidate-meta-label">Test Duration</span>
                    <span class="candidate-meta-value text-warning fs-5"><i class="fas fa-clock me-1"></i> <?= $durationMins ?> min(s)</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="candidate-meta-badge">
                    <span class="candidate-meta-label">Attempt</span>
                    <span class="candidate-meta-value text-info fs-6">#<?= $currentAttemptNum ?> of <?= $maxAttempts ?></span>
                </div>
            </div>
        </div>

        <!-- Official Guidelines -->
        <div class="guidelines-card">
            <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i> Important Test Instructions & Guidelines</h5>
            <ol class="mb-0 ps-3">
                <li><strong>Exact Typing:</strong> Type the reference passage shown on screen exactly as presented, paying attention to capitalization, punctuation, and spacing.</li>
                <li><strong>Live Timer:</strong> The countdown timer will start immediately after you click <em>Start Typing Test</em>.</li>
                <li><strong>Automatic Submission:</strong> When the timer expires, typing will be locked and your test will be submitted automatically by the server.</li>
                <li><strong>No Page Refreshing:</strong> Do not refresh or close the browser tab during an active test. If refreshed, your timer will continue from the original server start time.</li>
                <?php if ($settings['fullscreen_required']): ?>
                    <li><strong>Fullscreen Enforcement:</strong> Fullscreen mode is required during the test. Exiting fullscreen may count as a security violation.</li>
                <?php endif; ?>
                <?php if ($settings['disable_copy_paste']): ?>
                    <li><strong>Anti-Cheating Policy:</strong> Copying, pasting, cutting text, and right-clicking are strictly prohibited.</li>
                <?php endif; ?>
                <?php if ($settings['allow_backspace']): ?>
                    <li><strong>Correction:</strong> The Backspace key is enabled to correct previous typing mistakes.</li>
                <?php else: ?>
                    <li><strong>No Backspace:</strong> Backspace is disabled for this test. Maintain steady typing flow.</li>
                <?php endif; ?>
            </ol>
        </div>

        <!-- Start Test Form -->
        <form method="POST" action="<?= url('test-instructions') ?>" id="startTestForm">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="start_test">

            <div class="agreement-box" onclick="document.getElementById('readAgreement').click()">
                <input class="form-check-input mt-0" type="checkbox" id="readAgreement" onclick="event.stopPropagation()" onchange="toggleStartButton(this)">
                <label for="readAgreement">
                    I have read and understood all the competition rules and instructions stated above.
                </label>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-success btn-lg fw-bold py-3 btn-start-test" id="btnStartTest" disabled>
                    <i class="fas fa-play me-2"></i> START TYPING TEST NOW
                </button>
            </div>
        </form>
    </div>
    <div class="text-center mt-4 text-muted small">
        Developed by Ahsan Raza
    </div>
</div>

<script>
function toggleStartButton(chk) {
    document.getElementById('btnStartTest').disabled = !chk.checked;
}
</script>

</body>
</html>
