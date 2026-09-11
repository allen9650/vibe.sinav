<?php
/**
 * Candidate Test Portal — Login / Access Screen
 */

$competitions = Database::fetchAll(
    "SELECT id, name, code, competition_date, venue FROM competitions WHERE status IN ('active', 'upcoming') ORDER BY id DESC"
);

$selectedCompId = (int)($_GET['competition_id'] ?? ($_POST['competition_id'] ?? 0));
if ($selectedCompId === 0 && !empty($competitions)) {
    $selectedCompId = (int)$competitions[0]['id'];
}

$competition = null;
$settings = null;
if ($selectedCompId > 0) {
    $competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$selectedCompId]);
    $settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$selectedCompId]);
}

$loginMode = $settings['candidate_login_mode'] ?? 'roll_number';

$error = '';
$rollNumber = trim($_POST['roll_number'] ?? '');
$regNumber = trim($_POST['registration_number'] ?? '');
$pin = trim($_POST['pin'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $compId = (int)($_POST['competition_id'] ?? 0);
    $comp = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$compId]);

    if (!$comp || !in_array($comp['status'], ['active', 'upcoming'], true)) {
        $error = 'Selected competition is currently not active for testing.';
    } else {
        $compSettings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$compId]);
        if (!$compSettings) {
            $error = 'Test settings have not been configured for this competition yet.';
        } else {
            // Check active assigned paragraphs
            $activeParasCount = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM competition_paragraphs WHERE competition_id = ? AND is_active = 1",
                [$compId]
            );

            if ($activeParasCount === 0) {
                $error = 'No test passages are currently active for this competition.';
            } else {
                $mode = $compSettings['candidate_login_mode'] ?? 'roll_number';
                $candidate = null;

                if ($mode === 'registration_pin') {
                    if ($regNumber === '') {
                        $error = 'Please enter your Registration Number.';
                    } else {
                        $candidate = Database::fetch(
                            "SELECT * FROM candidates WHERE competition_id = ? AND registration_number = ?",
                            [$compId, $regNumber]
                        );
                        if ($candidate && !empty($candidate['pin']) && $candidate['pin'] !== $pin) {
                            $error = 'Invalid candidate PIN. Please check your credentials.';
                            $candidate = null;
                        }
                    }
                } else {
                    // Mode A: Roll Number
                    if ($rollNumber === '') {
                        $error = 'Please enter your Roll Number.';
                    } else {
                        $candidate = Database::fetch(
                            "SELECT * FROM candidates WHERE competition_id = ? AND (roll_number = ? OR registration_number = ?)",
                            [$compId, $rollNumber, $rollNumber]
                        );
                    }
                }

                if (!$candidate && empty($error)) {
                    $error = 'Candidate not found in this competition. Please verify your roll number.';
                } elseif ($candidate) {
                    if ($candidate['status'] === 'disqualified') {
                        $error = 'You have been disqualified from this competition. Please contact the invigilator.';
                    } elseif ($candidate['status'] === 'absent') {
                        $error = 'You are marked absent for this test session.';
                    } else {
                        // Check attempt count
                        $maxAttempts = max(1, (int)$compSettings['max_attempts']);
                        $attemptsCount = (int)Database::fetchColumn(
                            "SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status IN ('completed', 'timed_out')",
                            [$candidate['id'], $compId]
                        );

                        // Check if candidate currently has an active in_progress attempt
                        $activeAttempt = Database::fetch(
                            "SELECT * FROM test_attempts WHERE candidate_id = ? AND competition_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1",
                            [$candidate['id'], $compId]
                        );

                        if ($activeAttempt) {
                            $now = time();
                            $expectedEnd = strtotime($activeAttempt['expected_end_at']);

                            if ($now < $expectedEnd) {
                                // Resume active attempt
                                Session::set('candidate_logged_in', true);
                                Session::set('candidate_id', $candidate['id']);
                                Session::set('candidate_name', $candidate['full_name']);
                                Session::set('candidate_roll', $candidate['roll_number']);
                                Session::set('candidate_reg', $candidate['registration_number']);
                                Session::set('candidate_comp_id', $compId);
                                Session::set('current_attempt_id', $activeAttempt['id']);

                                CSRF::regenerate();
                                redirectTo('test-screen');
                            } else {
                                // Expire old active attempt
                                Database::update('test_attempts', [
                                    'status'       => 'timed_out',
                                    'finished_at'  => $activeAttempt['expected_end_at'],
                                    'submitted_at' => $activeAttempt['expected_end_at'],
                                ], 'id = ?', [$activeAttempt['id']]);
                                $attemptsCount++;
                            }
                        }

                        if ($attemptsCount >= $maxAttempts) {
                            $error = "You have already completed the maximum allowed attempts ({$maxAttempts}) for this competition.";
                        } else {
                            // Set Candidate Portal Session
                            Session::set('candidate_logged_in', true);
                            Session::set('candidate_id', $candidate['id']);
                            Session::set('candidate_name', $candidate['full_name']);
                            Session::set('candidate_roll', $candidate['roll_number']);
                            Session::set('candidate_reg', $candidate['registration_number']);
                            Session::set('candidate_comp_id', $compId);

                            CSRF::regenerate();
                            redirectTo('test-instructions');
                        }
                    }
                }
            }
        }
    }
}

$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName = getSetting('system_name', "vibe.Sınav");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Test Portal — <?= e($systemName) ?></title>
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
        }
        .portal-card {
            background: #151c2e;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }
        .portal-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 24px;
            text-align: center;
        }
        .portal-body {
            padding: 28px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="portal-card">
                <div class="portal-header">
                    <div class="mb-2">
                        <i class="fas fa-keyboard fa-3x text-primary"></i>
                    </div>
                    <h4 class="text-light fw-bold mb-1"><?= e($systemName) ?></h4>
                    <p class="text-muted small mb-0"><?= e($instituteName) ?></p>
                </div>

                <div class="portal-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 small">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('test-portal') ?>" id="candidateLoginForm">
                        <?= CSRF::field() ?>

                        <!-- Competition Selector -->
                        <div class="mb-3">
                            <label for="competition_id" class="form-label small text-muted fw-bold">Competition</label>
                            <select class="form-select bg-dark text-light border-secondary" id="competition_id" name="competition_id" 
                                    onchange="window.location='<?= url('test-portal') ?>&competition_id=' + this.value">
                                <?php if (empty($competitions)): ?>
                                    <option value="">No active competitions found</option>
                                <?php else: ?>
                                    <?php foreach ($competitions as $cmp): ?>
                                        <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <?php if ($loginMode === 'registration_pin'): ?>
                            <!-- Mode B: Reg # + PIN -->
                            <div class="mb-3">
                                <label for="registration_number" class="form-label small text-muted fw-bold">Registration Number</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary font-monospace" 
                                       id="registration_number" name="registration_number" placeholder="e.g. MITC-2026-0001" 
                                       value="<?= e($regNumber) ?>" required autofocus>
                            </div>

                            <div class="mb-4">
                                <label for="pin" class="form-label small text-muted fw-bold">Candidate Security PIN</label>
                                <input type="password" class="form-control bg-dark text-light border-secondary" 
                                       id="pin" name="pin" placeholder="Enter PIN (if provided)" value="<?= e($pin) ?>">
                            </div>
                        <?php else: ?>
                            <!-- Mode A: Roll Number -->
                            <div class="mb-4">
                                <label for="roll_number" class="form-label small text-muted fw-bold">Roll Number / Reg #</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary form-control-lg font-monospace text-center" 
                                       id="roll_number" name="roll_number" placeholder="e.g. 101 or MITC-2026-0001" 
                                       value="<?= e($rollNumber) ?>" required autofocus style="font-size: 22px; letter-spacing: 2px;">
                                <small class="text-muted text-center d-block mt-1">Enter your assigned competition Roll Number</small>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold" id="btnContinueTest">
                                <i class="fas fa-arrow-right me-2"></i> Continue to Test
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3 pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                        <a href="<?= url('quiz-login') ?>" class="text-info small text-decoration-none fw-bold">
                            <i class="fas fa-tasks me-1"></i> Interactive Quiz Portal
                        </a>
                        <a href="<?= url('login') ?>" class="text-muted small text-decoration-none">
                            <i class="fas fa-user-shield me-1"></i> Staff Login
                        </a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3 text-muted small">
                Developed by Ahsan Raza
            </div>
        </div>
    </div>
</div>

</body>
</html>
