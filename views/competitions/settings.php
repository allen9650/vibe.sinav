<?php
/**
 * Competition Management — Test Settings Module
 */

$competitionId = (int)($_GET['id'] ?? ($_POST['competition_id'] ?? 0));
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

// Fetch existing settings or initialize defaults
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);

if (!$settings) {
    $settingsId = Database::insert('test_settings', [
        'competition_id'        => $competitionId,
        'duration_minutes'      => 5,
        'duration_seconds'      => 300,
        'passing_wpm'           => 30,
        'passing_accuracy'      => 90.00,
        'allow_backspace'       => 1,
        'show_timer'            => 1,
        'show_wpm_live'         => 1,
        'show_accuracy_live'    => 1,
        'show_errors_live'      => 1,
        'fullscreen_required'   => 1,
        'detect_tab_switch'     => 1,
        'detect_window_blur'    => 1,
        'disable_copy_paste'    => 1,
        'disable_right_click'   => 1,
        'max_violations'        => 3,
        'violation_action'      => 'warning',
        'auto_submit'           => 1,
        'auto_save_interval'    => 5,
        'result_visible'        => 1,
        'leaderboard_visible'   => 0,
        'paragraph_mode'        => 'fixed',
        'candidate_login_mode'  => 'roll_number',
        'max_attempts'          => 1,
    ]);
    $settings = Database::fetch("SELECT * FROM test_settings WHERE id = ?", [$settingsId]);
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? 'save_settings';

    // Quick Apply Defaults
    if ($action === 'apply_defaults') {
        Database::update('test_settings', [
            'duration_minutes'      => 5,
            'duration_seconds'      => 300,
            'passing_wpm'           => 30,
            'passing_accuracy'      => 90.00,
            'allow_backspace'       => 1,
            'show_timer'            => 1,
            'show_wpm_live'         => 1,
            'show_accuracy_live'    => 1,
            'show_errors_live'      => 1,
            'fullscreen_required'   => 1,
            'detect_tab_switch'     => 1,
            'detect_window_blur'    => 1,
            'disable_copy_paste'    => 1,
            'disable_right_click'   => 1,
            'max_violations'        => 3,
            'violation_action'      => 'warning',
            'auto_submit'           => 1,
            'auto_save_interval'    => 5,
            'result_visible'        => 1,
            'leaderboard_visible'   => 0,
            'candidate_login_mode'  => 'roll_number',
            'max_attempts'          => 1,
        ], 'competition_id = ?', [$competitionId]);

        $oldValues = [
            'duration_seconds' => $settings['duration_seconds'] ?? null,
            'passing_wpm'      => $settings['passing_wpm'] ?? null,
            'passing_accuracy' => $settings['passing_accuracy'] ?? null,
            'max_attempts'     => $settings['max_attempts'] ?? null,
        ];
        $newValues = [
            'competition_id'   => $competitionId,
            'duration_seconds' => 300,
            'passing_wpm'      => 30,
            'passing_accuracy' => 90.00,
            'max_attempts'     => 1,
        ];
        AuditLog::log('test_settings_defaults_applied', 'test_settings', "Applied default test settings to competition #{$competitionId}", Session::get('user_id'), $oldValues, $newValues);
        Session::flash('success', 'Sensible default test settings applied successfully.');
        CSRF::regenerate();
        redirectTo('competitions-settings&id=' . $competitionId);
    }

    // Save Custom Settings
    $durationMode = $_POST['duration_preset'] ?? '300';
    if ($durationMode === 'custom') {
        $durationSeconds = max(10, (int)($_POST['custom_duration_seconds'] ?? 300));
    } else {
        $durationSeconds = max(10, (int)$durationMode);
    }
    $durationMinutes = ceil($durationSeconds / 60);

    $passingWpm = max(0, (int)($_POST['passing_wpm'] ?? 0));
    $passingAccuracy = max(0, min(100, (float)($_POST['passing_accuracy'] ?? 90.00)));
    $maxAttempts = max(1, (int)($_POST['max_attempts'] ?? 1));
    $autoSaveInterval = max(2, min(60, (int)($_POST['auto_save_interval'] ?? 5)));
    $maxViolations = max(0, (int)($_POST['max_violations'] ?? 3));
    $violationAction = in_array($_POST['violation_action'] ?? '', ['log_only', 'warning', 'auto_disqualify'], true) ? $_POST['violation_action'] : 'warning';
    $candidateLoginMode = in_array($_POST['candidate_login_mode'] ?? '', ['roll_number', 'registration_pin'], true) ? $_POST['candidate_login_mode'] : 'roll_number';

    if ($durationSeconds <= 0) {
        $errors['duration'] = 'Test duration must be greater than 0 seconds.';
    }

    if (empty($errors)) {
        $oldValues = [
            'duration_seconds'     => $settings['duration_seconds'] ?? null,
            'passing_wpm'          => $settings['passing_wpm'] ?? null,
            'passing_accuracy'     => $settings['passing_accuracy'] ?? null,
            'max_attempts'         => $settings['max_attempts'] ?? null,
            'candidate_login_mode' => $settings['candidate_login_mode'] ?? null,
        ];
        $newValues = [
            'competition_id'       => $competitionId,
            'duration_seconds'     => $durationSeconds,
            'passing_wpm'          => $passingWpm,
            'passing_accuracy'     => $passingAccuracy,
            'max_attempts'         => $maxAttempts,
            'candidate_login_mode' => $candidateLoginMode,
        ];

        Database::update('test_settings', [
            'duration_minutes'      => $durationMinutes,
            'duration_seconds'      => $durationSeconds,
            'passing_wpm'           => $passingWpm,
            'passing_accuracy'      => $passingAccuracy,
            'allow_backspace'       => isset($_POST['allow_backspace']) ? 1 : 0,
            'show_timer'            => 1,
            'show_wpm_live'         => isset($_POST['show_wpm_live']) ? 1 : 0,
            'show_accuracy_live'    => isset($_POST['show_accuracy_live']) ? 1 : 0,
            'show_errors_live'      => isset($_POST['show_errors_live']) ? 1 : 0,
            'fullscreen_required'   => isset($_POST['fullscreen_required']) ? 1 : 0,
            'detect_tab_switch'     => isset($_POST['detect_tab_switch']) ? 1 : 0,
            'detect_window_blur'    => isset($_POST['detect_window_blur']) ? 1 : 0,
            'disable_copy_paste'    => isset($_POST['disable_copy_paste']) ? 1 : 0,
            'disable_right_click'   => isset($_POST['disable_right_click']) ? 1 : 0,
            'max_violations'        => $maxViolations,
            'violation_action'      => $violationAction,
            'auto_submit'           => isset($_POST['auto_submit']) ? 1 : 0,
            'auto_save_interval'    => $autoSaveInterval,
            'result_visible'        => isset($_POST['result_visible']) ? 1 : 0,
            'leaderboard_visible'   => isset($_POST['leaderboard_visible']) ? 1 : 0,
            'candidate_login_mode'  => $candidateLoginMode,
            'max_attempts'          => $maxAttempts,
        ], 'competition_id = ?', [$competitionId]);

        AuditLog::log('test_settings_updated', 'test_settings', 
            "Updated test settings for competition #{$competitionId} ({$durationSeconds}s duration, {$passingAccuracy}% min acc, {$maxAttempts} max attempts)", 
            Session::get('user_id'), 
            $oldValues,
            $newValues
        );

        Session::flash('success', 'Competition test settings saved successfully.');
        CSRF::regenerate();
        redirectTo('competitions-settings&id=' . $competitionId);
    }
}

// Reload fresh settings
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);
$currentDuration = (int)$settings['duration_seconds'];
$isPreset = in_array($currentDuration, [60, 120, 180, 300, 600, 900], true);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0">Competition Test Settings</h1>
            <span class="badge bg-dark border border-secondary"><?= e($competition['code']) ?></span>
        </div>
        <p class="page-subtitle"><?= e($competition['name']) ?> — Configure timing, validation rules, anti-cheating, and typing options</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="<?= url('competitions-settings') ?>&id=<?= $competition['id'] ?>" class="d-inline"
              onsubmit="return confirm('Apply standard recommended test defaults to this competition?');">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="apply_defaults">
            <input type="hidden" name="competition_id" value="<?= $competition['id'] ?>">
            <button type="submit" class="btn btn-outline-warning">
                <i class="fas fa-magic me-1"></i> Apply Sensible Defaults
            </button>
        </form>
        <a href="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-paragraph me-1"></i> Paragraphs
        </a>
        <a href="<?= url('competitions-view') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-trophy me-1"></i> View Competition
        </a>
    </div>
</div>

<form method="POST" action="<?= url('competitions-settings') ?>&id=<?= $competition['id'] ?>" id="testSettingsForm">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="competition_id" value="<?= $competition['id'] ?>">

    <div class="row g-4">
        <!-- 1. Timing & Candidate Authentication -->
        <div class="col-lg-6">
            <div class="content-card mb-4 h-100">
                <div class="card-header-custom">
                    <h5><i class="fas fa-stopwatch me-2 text-warning"></i> 1. Timing & Access Mode</h5>
                </div>
                <div class="card-body-custom">
                    <!-- Duration Selector -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Test Duration <span class="text-danger">*</span></label>
                        <select class="form-select mb-2" id="duration_preset" name="duration_preset" onchange="toggleCustomDuration()">
                            <option value="60" <?= $currentDuration === 60 ? 'selected' : '' ?>>1 Minute (60 seconds)</option>
                            <option value="120" <?= $currentDuration === 120 ? 'selected' : '' ?>>2 Minutes (120 seconds)</option>
                            <option value="180" <?= $currentDuration === 180 ? 'selected' : '' ?>>3 Minutes (180 seconds)</option>
                            <option value="300" <?= $currentDuration === 300 ? 'selected' : '' ?>>5 Minutes (300 seconds) — Recommended</option>
                            <option value="600" <?= $currentDuration === 600 ? 'selected' : '' ?>>10 Minutes (600 seconds)</option>
                            <option value="900" <?= $currentDuration === 900 ? 'selected' : '' ?>>15 Minutes (900 seconds)</option>
                            <option value="custom" <?= !$isPreset ? 'selected' : '' ?>>Custom Duration (in seconds)</option>
                        </select>

                        <div id="customDurationBox" style="<?= $isPreset ? 'display: none;' : '' ?>">
                            <div class="input-group">
                                <span class="input-group-text">Seconds</span>
                                <input type="number" class="form-control" name="custom_duration_seconds" 
                                       value="<?= $currentDuration ?>" min="10" max="7200" placeholder="e.g. 240">
                            </div>
                            <small class="text-muted">Enter duration between 10 and 7200 seconds.</small>
                        </div>
                    </div>

                    <!-- Candidate Login Mode -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Candidate Login Mode <span class="text-danger">*</span></label>
                        <select class="form-select" name="candidate_login_mode">
                            <option value="roll_number" <?= $settings['candidate_login_mode'] === 'roll_number' ? 'selected' : '' ?>>
                                Mode A — Roll Number Only (Fast & Direct)
                            </option>
                            <option value="registration_pin" <?= $settings['candidate_login_mode'] === 'registration_pin' ? 'selected' : '' ?>>
                                Mode B — Registration Number + PIN (Two-Factor Verification)
                            </option>
                        </select>
                        <small class="text-muted">Controls how candidates authenticate into the test portal.</small>
                    </div>

                    <!-- Max Attempts -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Maximum Attempts Allowed <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="max_attempts" value="<?= (int)$settings['max_attempts'] ?>" min="1" max="10" required>
                        <small class="text-muted">Standard official competition is 1 attempt per candidate.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Result Rules & Thresholds -->
        <div class="col-lg-6">
            <div class="content-card mb-4 h-100">
                <div class="card-header-custom">
                    <h5><i class="fas fa-award me-2 text-success"></i> 2. Qualification & Scoring Rules</h5>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Minimum Accuracy % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="passing_accuracy" 
                                       value="<?= e($settings['passing_accuracy']) ?>" min="0" max="100" step="0.5" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Default: 90% accuracy</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Minimum Net WPM</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="passing_wpm" 
                                       value="<?= (int)$settings['passing_wpm'] ?>" min="0" max="300" required>
                                <span class="input-group-text">WPM</span>
                            </div>
                            <small class="text-muted">Minimum speed to qualify</small>
                        </div>
                    </div>

                    <hr class="border-secondary">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Auto-Save & Submission</label>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="auto_submit" name="auto_submit" value="1" 
                                   <?= $settings['auto_submit'] ? 'checked' : '' ?>>
                            <label class="form-check-label text-light" for="auto_submit">
                                Auto-Submit on Timer End (Server authoritative)
                            </label>
                        </div>

                        <div class="input-group mb-2">
                            <span class="input-group-text">Auto-Save Interval</span>
                            <input type="number" class="form-control" name="auto_save_interval" 
                                   value="<?= (int)$settings['auto_save_interval'] ?>" min="2" max="60" required>
                            <span class="input-group-text">seconds</span>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="result_visible" name="result_visible" value="1" 
                                       <?= $settings['result_visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label text-light small" for="result_visible">
                                    Result Visible to Candidate
                                </label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="leaderboard_visible" name="leaderboard_visible" value="1" 
                                       <?= $settings['leaderboard_visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label text-light small" for="leaderboard_visible">
                                    Live Public Leaderboard
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Typing Experience Options -->
        <div class="col-lg-6">
            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5><i class="fas fa-keyboard me-2 text-info"></i> 3. Candidate Typing Experience</h5>
                </div>
                <div class="card-body-custom">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="allow_backspace" name="allow_backspace" value="1" 
                               <?= $settings['allow_backspace'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="allow_backspace">
                            <strong>Allow Backspace Key</strong> (Enables correcting previous mistakes)
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="show_wpm_live" name="show_wpm_live" value="1" 
                               <?= $settings['show_wpm_live'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="show_wpm_live">
                            <strong>Show Live WPM Meter</strong> during test
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="show_accuracy_live" name="show_accuracy_live" value="1" 
                               <?= $settings['show_accuracy_live'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="show_accuracy_live">
                            <strong>Show Live Accuracy %</strong> during test
                        </label>
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_errors_live" name="show_errors_live" value="1" 
                               <?= $settings['show_errors_live'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="show_errors_live">
                            <strong>Show Live Error Counter</strong>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Security & Anti-Cheating Controls -->
        <div class="col-lg-6">
            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5><i class="fas fa-shield-alt me-2 text-danger"></i> 4. Anti-Cheating & Integrity Rules</h5>
                </div>
                <div class="card-body-custom">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="fullscreen_required" name="fullscreen_required" value="1" 
                               <?= $settings['fullscreen_required'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="fullscreen_required">
                            <strong>Enforce Fullscreen Mode</strong>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="detect_tab_switch" name="detect_tab_switch" value="1" 
                               <?= $settings['detect_tab_switch'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="detect_tab_switch">
                            <strong>Detect Tab Switching / Window Blur</strong>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="disable_copy_paste" name="disable_copy_paste" value="1" 
                               <?= $settings['disable_copy_paste'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="disable_copy_paste">
                            <strong>Disable Copy, Paste & Cut</strong>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="disable_right_click" name="disable_right_click" value="1" 
                               <?= $settings['disable_right_click'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="disable_right_click">
                            <strong>Disable Right-Click Context Menu</strong>
                        </label>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Max Security Violations</label>
                            <input type="number" class="form-control form-control-sm" name="max_violations" 
                                   value="<?= (int)$settings['max_violations'] ?>" min="0" max="20" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Violation Action</label>
                            <select class="form-select form-select-sm" name="violation_action">
                                <option value="warning" <?= $settings['violation_action'] === 'warning' ? 'selected' : '' ?>>Warning Popup</option>
                                <option value="log_only" <?= $settings['violation_action'] === 'log_only' ? 'selected' : '' ?>>Log Only (Silent)</option>
                                <option value="auto_disqualify" <?= $settings['violation_action'] === 'auto_disqualify' ? 'selected' : '' ?>>Auto Disqualify Attempt</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <a href="<?= url('competitions-view') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-times me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg" id="btnSaveTestSettings">
                <i class="fas fa-save me-1"></i> Save Test Settings
            </button>
        </div>
    </div>
</form>

<script>
function toggleCustomDuration() {
    const preset = document.getElementById('duration_preset').value;
    document.getElementById('customDurationBox').style.display = preset === 'custom' ? 'block' : 'none';
}
</script>
