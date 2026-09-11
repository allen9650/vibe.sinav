<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Workstation Candidate Intake & Identity Screen
 * Clean, high-contrast, distraction-free modal card.
 * Supports manual "Enter Workstation Code" (without IP) or auto-resolution.
 */

// 1. Resolve Active Launched Assessment
$campusId = 1;
$activeAss = AssessmentService::getActiveLaunchedAssessment($campusId);

$paramId = (int)($_GET['id'] ?? 0);
if ($paramId > 0) {
    $candidateAss = AssessmentService::getAssessment($paramId);
    if ($candidateAss && $candidateAss['status'] === 'published') {
        $assessment = $candidateAss;
    } else {
        $assessment = null;
    }
} else {
    $assessment = $activeAss;
}

$campusId = (int)($assessment['campus_id'] ?? 1);

// 2. Resolve or suggest Workstation Code
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$stationParam = trim((string)($_GET['station'] ?? ''));

$station = null;
if ($stationParam !== '') {
    $station = StationService::resolveStation($stationParam);
}
if (!$station && $clientIp !== '127.0.0.1' && $clientIp !== '::1') {
    $station = StationService::resolveStation($clientIp);
}
if (!$station) {
    $station = Database::fetch("SELECT * FROM lab_stations WHERE campus_id = ? AND status = 'active' ORDER BY LENGTH(station_code) ASC, station_code ASC LIMIT 1", [$campusId]);
}

// Fetch all available station codes for datalist suggestions
$availableStations = Database::fetchAll(
    "SELECT station_code FROM lab_stations WHERE campus_id = ? AND status = 'active' ORDER BY LENGTH(station_code) ASC, station_code ASC",
    [$campusId]
);

$currentStationCode = $station['station_code'] ?? ($stationParam ?: 'PC-01');

$errorMessage = Session::getFlash('error')[0]['message'] ?? '';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $enteredStationCode = trim((string)($_POST['workstation_code'] ?? ''));
    if ($enteredStationCode === '') {
        $enteredStationCode = $currentStationCode;
    }

    $studentName = trim((string)($_POST['student_name'] ?? ''));
    $studentRoll = trim((string)($_POST['student_roll_no'] ?? ''));
    $verifiedSeat = !empty($_POST['seat_verified']);
    $agreedInstructions = !empty($_POST['agree_instructions']);

    if (!$assessment || $assessment['status'] !== 'published') {
        $errorMessage = "This assessment is not currently active.";
    } elseif ($studentName === '') {
        $errorMessage = "Please enter your Full Name.";
    } elseif ($studentRoll === '') {
        $errorMessage = "Please enter your Roll / Seat Number.";
    } elseif (!$verifiedSeat) {
        $errorMessage = "You must confirm that this is your assigned seat.";
    } elseif (!$agreedInstructions) {
        $errorMessage = "You must read and agree to the Student Instructions and Rules / Guidelines before starting.";
    } else {
        try {
            // Assign workstation within the allocated device limit
            $resolvedStation = StationService::assignStationToAssessment((int)$assessment['id'], $enteredStationCode, $campusId);
            $stationId = (int)$resolvedStation['id'];

            $sessionData = ExamExecutionService::startAttempt(
                (int)$assessment['id'],
                $stationId,
                $studentName,
                $studentRoll
            );

            // Store session
            $attemptId = (int)($sessionData['attempt_id'] ?? $sessionData['attempt']['id']);
            Session::set('current_attempt_id', $attemptId);
            Session::set('current_assessment_id', $assessment['id']);
            Session::set('station_id', $stationId);
            Session::set('station_code', $resolvedStation['station_code']);
            Session::set('student_name', $studentName);
            Session::set('student_roll_no', $studentRoll);

            redirectTo('test-screen&attempt_id=' . $attemptId);
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$instituteName = 'Assessment System';
$instituteLogo = '';
$logoUrl = null;

try {
    $instituteName = function_exists('getSetting') ? getSetting('institute_name', 'Assessment System') : 'Assessment System';
    $instituteLogo = function_exists('getSetting') ? getSetting('institute_logo', '') : '';
    if (!empty($instituteLogo) && file_exists(UPLOADS_PATH . '/' . $instituteLogo)) {
        $logoUrl = upload($instituteLogo);
    }
} catch (Throwable $e) {
    // Keep defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workstation Intake — <?= e($instituteName) ?></title>
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
            --text-muted: #64748b;
            --accent-blue: #2563eb;
            --accent-green: #10b981;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            --badge-bg: #e2e8f0;
            --badge-border: #cbd5e1;
            --badge-text: #1e293b;
            --verify-box-bg: #f8fafc;
            --verify-box-border: #e2e8f0;
            --station-pill-bg: #e0f2fe;
            --station-pill-text: #0369a1;
            --station-pill-border: #7dd3fc;
        }

        [data-bs-theme="dark"] {
            color-scheme: dark;
            --bg-primary: #0a0e17;
            --card-bg: #131b2e;
            --header-bg: #1a233a;
            --border-color: #263352;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --input-bg: #0b1120;
            --input-border: #334155;
            --input-text: #f8fafc;
            --badge-bg: #0f172a;
            --badge-border: #334155;
            --badge-text: #cbd5e1;
            --verify-box-bg: rgba(11, 17, 32, 0.6);
            --verify-box-border: #334155;
            --station-pill-bg: #1e293b;
            --station-pill-text: #38bdf8;
            --station-pill-border: #0284c7;
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
        .intake-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 680px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .intake-header {
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
        }
        .intake-body {
            padding: 2rem;
        }
        .badge-read-only {
            background: var(--badge-bg);
            border: 1px solid var(--badge-border);
            color: var(--badge-text);
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
        }
        .form-label-custom {
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.04em;
        }
        .form-control-custom {
            background-color: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important;
            color: var(--input-text) !important;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        .form-control-custom:focus {
            background-color: var(--input-bg) !important;
            border-color: var(--accent-blue) !important;
            color: var(--input-text) !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25) !important;
        }
        .station-pill {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            background: var(--station-pill-bg);
            color: var(--station-pill-text);
            border: 1px solid var(--station-pill-border);
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
        }
        .verify-box {
            background: var(--verify-box-bg);
            border: 1px solid var(--verify-box-border);
            border-radius: 8px;
            padding: 1rem;
        }
        .btn-start {
            background-color: var(--accent-green);
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 0.9rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-start:hover:not(:disabled) {
            background-color: #059669;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
        }
        .btn-start:disabled {
            background-color: #64748b;
            color: #cbd5e1;
            opacity: 0.6;
            cursor: not-allowed;
        }
        .terminal-logo {
            min-width: 84px;
            max-width: 150px;
            height: 84px;
            border-radius: 18px;
            background: linear-gradient(135deg, #4f46e5, #06b6d4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: #ffffff;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.25);
            animation: terminalSlideDown 0.7s cubic-bezier(0.16, 1, 0.3, 1), terminalFloat 3.5s ease-in-out infinite 0.7s;
            padding: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .terminal-logo.has-custom-logo {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25), 0 0 20px rgba(99, 102, 241, 0.2);
        }
        [data-bs-theme="light"] .terminal-logo.has-custom-logo {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.12);
        }
        .terminal-logo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.2));
        }
        @keyframes terminalSlideDown {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        @keyframes terminalFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-6px);
            }
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

<div class="intake-card">
    <?php if (!$assessment || $assessment['status'] !== 'published'): ?>
        <!-- No Assessment Available Screen with Auto-Refresh -->
        <div class="p-5 text-center">
            <div class="mb-3 d-flex justify-content-center">
                <div class="terminal-logo <?= $logoUrl ? 'has-custom-logo' : '' ?>" title="<?= e($instituteName) ?>">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="<?= e($instituteName) ?> Logo" class="terminal-logo-img">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap"></i>
                    <?php endif; ?>
                </div>
            </div>
            <h4 class="fw-bold mb-2" style="color: var(--text-main);">Workstation Terminal Ready</h4>
            <div class="station-pill d-inline-block font-monospace mb-3">
                <?= e($currentStationCode) ?>
            </div>
            <p class="text-muted mx-auto mb-4" style="max-width: 420px;">
                No examination is currently launched for this laboratory. This workstation is waiting for the instructor to open the session.
            </p>
            <div class="small text-info d-flex align-items-center justify-content-center gap-2">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Auto-checking for examination launch every 5 seconds...</span>
            </div>
        </div>
        <script>
            setTimeout(() => window.location.reload(), 5000);
        </script>
    <?php else: ?>
        <!-- Active Assessment Intake Modal -->
        <div class="intake-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="<?= e($instituteName) ?>" style="height: 28px; max-width: 80px; object-fit: contain;">
                    <?php endif; ?>
                    <span class="station-pill font-monospace" id="headerStationBadge">
                        <i class="fas fa-desktop me-2"></i><?= e($currentStationCode) ?>
                    </span>
                </div>
                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fw-semibold">
                    <i class="fas fa-circle-check me-1"></i> Assessment Ready
                </span>
            </div>

            <h4 class="fw-bold mb-2" style="color: var(--text-main);"><?= e($assessment['title']) ?></h4>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge-read-only">
                    <i class="fas fa-book me-1 text-primary"></i> <?= e($assessment['subject_name']) ?>
                </span>
                <span class="badge-read-only">
                    <i class="fas fa-graduation-cap me-1 text-warning"></i> <?= e($assessment['target_class']) ?>
                </span>
                <span class="badge-read-only font-monospace">
                    <i class="fas fa-clock me-1 text-info"></i> <?= (int)$assessment['duration_minutes'] ?> Minutes
                </span>
                <span class="badge-read-only font-monospace">
                    <i class="fas fa-star me-1 text-success"></i> <?= number_format((float)$assessment['total_marks'], 2) ?> Marks
                </span>
            </div>
        </div>

        <div class="intake-body">
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger py-2 small mb-4" role="alert">
                    <i class="fas fa-triangle-exclamation me-2"></i><?= e($errorMessage) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('test-start&id=' . $assessment['id']) ?>" id="intakeForm">
                <?= CSRF::field() ?>

                <!-- Workstation Code Option (Enter Workstation Code without IP) -->
                <div class="mb-3">
                    <label for="workstation_code" class="form-label form-label-custom small text-uppercase d-flex justify-content-between">
                        <span><i class="fas fa-desktop text-info me-1"></i> Enter Workstation Code <span class="text-danger">*</span></span>
                        <span class="text-muted fw-normal" style="font-size: 0.75rem;">(No IP address required)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text text-info" style="background: var(--badge-bg); border-color: var(--input-border);"><i class="fas fa-terminal"></i></span>
                        <input type="text" name="workstation_code" id="workstation_code" 
                               class="form-control form-control-custom font-monospace fw-bold text-uppercase" 
                               value="<?= e($currentStationCode) ?>" 
                               placeholder="e.g. PC-01 or 4" 
                               list="stationDatalist" 
                               required>
                    </div>
                    <datalist id="stationDatalist">
                        <?php foreach ($availableStations as $ast): ?>
                            <option value="<?= e($ast['station_code']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text text-muted small">Enter the number or ID on your computer sticker (e.g. PC-01, PC-04).</div>
                </div>

                <!-- Student Full Name -->
                <div class="mb-3">
                    <label for="student_name" class="form-label form-label-custom small text-uppercase">
                        Candidate Full Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="student_name" id="student_name" 
                           class="form-control form-control-custom" 
                           placeholder="Enter your full name" required autofocus>
                    <div class="form-text text-muted small">Name will be permanently stamped on your examination scorecard.</div>
                </div>

                <!-- Roll / Seat Number -->
                <div class="mb-4">
                    <label for="student_roll_no" class="form-label form-label-custom small text-uppercase">
                        Roll Number / Seat ID <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="student_roll_no" id="student_roll_no" 
                           class="form-control form-control-custom font-monospace" 
                           placeholder="e.g. 2026-CS-042" required>
                    <div class="form-text text-muted small">Must match your student ID badge.</div>
                </div>

                <!-- Seat Verification Checkbox -->
                <div class="verify-box mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="seat_verified" id="seat_verified" value="1" required>
                        <label class="form-check-label small fw-semibold" style="color: var(--text-main);" for="seat_verified">
                            I verify that this is my assigned seat (<span class="text-info font-monospace" id="verifiedStationText"><?= e($currentStationCode) ?></span>).
                        </label>
                    </div>
                </div>

                <!-- Assessment Instructions & Examination Rules Box -->
                <?php
                $studentInstructions = !empty(trim((string)($assessment['instructions'] ?? ''))) 
                    ? trim((string)$assessment['instructions']) 
                    : "1. Read each question carefully before selecting or entering your answer.\n2. You can navigate between questions freely using the question palette on the right.\n3. Your answers are automatically saved in real time to the server.\n4. Complete all required questions before clicking 'Submit Assessment'.\n5. Once submitted, answers cannot be edited or re-attempted.";

                $rulesGuidelines = !empty(trim((string)($assessment['rules_guidelines'] ?? '')))
                    ? trim((string)$assessment['rules_guidelines'])
                    : "1. Fullscreen Enforcement: You must remain in fullscreen mode throughout the examination.\n2. Tab Switching Prohibited: Switching browser tabs, minimizing the window, or opening external apps is logged as a violation.\n3. Security Locks: Clipboard copy/cut/paste and right-click menus are disabled.\n4. Timer Rule: The test timer runs server-side and will auto-submit when time expires.\n5. Malpractice: Any detected malpractice or excessive violations will lead to immediate disqualification.";
                ?>
                <div class="instructions-panel mb-4 p-3 rounded" style="background: var(--bg-primary); border: 1px solid var(--border-color);">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="small fw-bold text-uppercase mb-0" style="color: var(--text-main);">
                            <i class="fas fa-file-contract text-primary me-1"></i> Assessment Instructions & Rules
                        </h6>
                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Review & Agree</span>
                    </div>

                    <!-- Student Instructions -->
                    <div class="mb-2">
                        <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">
                            <i class="fas fa-graduation-cap text-info me-1"></i> Student Instructions:
                        </div>
                        <div class="p-2 rounded font-monospace small" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); max-height: 110px; overflow-y: auto; white-space: pre-wrap; font-size: 0.78rem; line-height: 1.45;"><?= e($studentInstructions) ?></div>
                    </div>

                    <!-- Rules / Guidelines -->
                    <div class="mb-3">
                        <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">
                            <i class="fas fa-shield-alt text-danger me-1"></i> Rules / Guidelines:
                        </div>
                        <div class="p-2 rounded font-monospace small" style="background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary); max-height: 110px; overflow-y: auto; white-space: pre-wrap; font-size: 0.78rem; line-height: 1.45;"><?= e($rulesGuidelines) ?></div>
                    </div>

                    <!-- I Agree Checkbox -->
                    <div class="form-check pt-2 border-top" style="border-color: var(--border-color) !important;">
                        <input class="form-check-input" type="checkbox" name="agree_instructions" id="agree_instructions" value="1" required>
                        <label class="form-check-label small fw-bold" style="color: var(--accent-blue, #2563eb); cursor: pointer;" for="agree_instructions">
                            <i class="fas fa-check-circle me-1"></i> I agree to the Student Instructions & Examination Rules
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" id="btnStart" class="btn btn-start" disabled>
                        <i class="fas fa-play me-2"></i> Start Assessment
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    <div class="text-center mt-3 text-muted small">
        <div><strong>vibe.Sınav</strong> Assessment System</div>
        <div>Developed by Ahsan Raza</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stationInput = document.getElementById('workstation_code');
    const headerStationBadge = document.getElementById('headerStationBadge');
    const verifiedStationText = document.getElementById('verifiedStationText');
    const nameInput = document.getElementById('student_name');
    const rollInput = document.getElementById('student_roll_no');
    const seatCheck = document.getElementById('seat_verified');
    const agreeCheck = document.getElementById('agree_instructions');
    const startBtn = document.getElementById('btnStart');

    function updateStationDisplay() {
        if (!stationInput) return;
        let code = stationInput.value.trim();
        if (/^\d+$/.test(code)) {
            code = 'PC-' + code.padStart(2, '0');
        } else if (/^pc[-_\s]?\d+$/i.test(code)) {
            const num = code.replace(/\D/g, '');
            code = 'PC-' + num.padStart(2, '0');
        } else if (code) {
            code = code.toUpperCase();
        } else {
            code = 'PC-01';
        }

        if (headerStationBadge) {
            headerStationBadge.innerHTML = '<i class="fas fa-desktop me-2"></i>' + code;
        }
        if (verifiedStationText) {
            verifiedStationText.textContent = code;
        }
    }

    function validate() {
        const hasStation = stationInput && stationInput.value.trim().length > 0;
        const hasName = nameInput && nameInput.value.trim().length > 1;
        const hasRoll = rollInput && rollInput.value.trim().length > 0;
        const isSeatChecked = seatCheck && seatCheck.checked;
        const isAgreeChecked = agreeCheck && agreeCheck.checked;
        if (startBtn) {
            startBtn.disabled = !(hasStation && hasName && hasRoll && isSeatChecked && isAgreeChecked);
        }
    }

    if (stationInput) {
        stationInput.addEventListener('input', function() {
            updateStationDisplay();
            validate();
        });
        stationInput.addEventListener('change', updateStationDisplay);
    }
    if (nameInput) nameInput.addEventListener('input', validate);
    if (rollInput) rollInput.addEventListener('input', validate);
    if (seatCheck) seatCheck.addEventListener('change', validate);
    if (agreeCheck) agreeCheck.addEventListener('change', validate);
});
</script>

</body>
</html>
