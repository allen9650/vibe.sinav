<?php
/**
 * System Settings & Campus Management Page
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = trim($_POST['action'] ?? 'save_settings');

    // Action 1: Add Campus
    if ($action === 'add_campus') {
        $campusName   = trim($_POST['campus_name'] ?? '');
        $campusCode   = strtoupper(trim($_POST['campus_code'] ?? ''));
        $campusStatus = in_array($_POST['campus_status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['campus_status'] : 'active';

        if (empty($campusName)) {
            Session::flash('error', 'Campus Name is required.');
        } elseif (empty($campusCode)) {
            Session::flash('error', 'Campus Code is required.');
        } else {
            $existing = Database::fetch("SELECT id FROM campuses WHERE code = ?", [$campusCode]);
            if ($existing) {
                Session::flash('error', "A campus with code '{$campusCode}' already exists.");
            } else {
                $newId = Database::insert('campuses', [
                    'name'   => $campusName,
                    'code'   => $campusCode,
                    'status' => $campusStatus,
                ]);
                AuditLog::log('campus_created', 'campuses', "Created campus '{$campusName}' ({$campusCode})", Auth::id() ?: 1);
                Session::flash('success', "Campus '{$campusName}' created successfully.");
            }
        }
        CSRF::regenerate();
        redirect(url('settings') . '#campusSection');
    }

    // Action 2: Edit Campus
    if ($action === 'edit_campus') {
        $campusId     = (int)($_POST['campus_id'] ?? 0);
        $campusName   = trim($_POST['campus_name'] ?? '');
        $campusCode   = strtoupper(trim($_POST['campus_code'] ?? ''));
        $campusStatus = in_array($_POST['campus_status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['campus_status'] : 'active';

        $campus = Database::fetch("SELECT * FROM campuses WHERE id = ?", [$campusId]);
        if (!$campus) {
            Session::flash('error', 'Campus not found.');
        } elseif (empty($campusName)) {
            Session::flash('error', 'Campus Name is required.');
        } elseif (empty($campusCode)) {
            Session::flash('error', 'Campus Code is required.');
        } else {
            $existing = Database::fetch("SELECT id FROM campuses WHERE code = ? AND id != ?", [$campusCode, $campusId]);
            if ($existing) {
                Session::flash('error', "Another campus with code '{$campusCode}' already exists.");
            } else {
                Database::update('campuses', [
                    'name'   => $campusName,
                    'code'   => $campusCode,
                    'status' => $campusStatus,
                ], 'id = ?', [$campusId]);

                // Sync default campus settings if this was the default
                if ((int)getSetting('default_campus_id', '1') === $campusId) {
                    updateSetting('default_campus_name', $campusName);
                    updateSetting('default_campus_code', $campusCode);
                }

                // If currently logged in user belongs to this campus, refresh session
                if (Auth::campusId() === $campusId) {
                    Session::set('campus_name', $campusName);
                    Session::set('campus_code', $campusCode);
                }

                AuditLog::log('campus_updated', 'campuses', "Updated campus '{$campusName}' ({$campusCode})", Auth::id() ?: 1);
                Session::flash('success', "Campus '{$campusName}' updated successfully.");
            }
        }
        CSRF::regenerate();
        redirect(url('settings') . '#campusSection');
    }

    // Action 3: Toggle Campus Status
    if ($action === 'toggle_campus_status') {
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $campus = Database::fetch("SELECT * FROM campuses WHERE id = ?", [$campusId]);
        if ($campus) {
            $newStatus = ($campus['status'] === 'active') ? 'inactive' : 'active';
            Database::update('campuses', ['status' => $newStatus], 'id = ?', [$campusId]);
            AuditLog::log('campus_status_toggled', 'campuses', "Changed campus #{$campusId} status to {$newStatus}", Auth::id() ?: 1);
            Session::flash('success', "Campus '{$campus['name']}' status changed to " . ucfirst($newStatus) . ".");
        }
        CSRF::regenerate();
        redirect(url('settings') . '#campusSection');
    }

    // Action 4: Delete Campus
    if ($action === 'delete_campus') {
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $totalCampuses = (int)Database::fetchColumn("SELECT COUNT(*) FROM campuses");
        if ($totalCampuses <= 1) {
            Session::flash('error', 'Cannot delete the only remaining campus in the system.');
        } else {
            $assessmentsCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM assessments WHERE campus_id = ?", [$campusId]);
            $usersCount       = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE campus_id = ?", [$campusId]);

            if ($assessmentsCount > 0 || $usersCount > 0) {
                Session::flash('error', "Cannot delete campus: it is currently linked to {$assessmentsCount} assessment(s) and {$usersCount} user(s). You can set its status to Inactive instead.");
            } else {
                $campus = Database::fetch("SELECT * FROM campuses WHERE id = ?", [$campusId]);
                Database::delete('campuses', 'id = ?', [$campusId]);
                AuditLog::log('campus_deleted', 'campuses', "Deleted campus '{$campus['name']}' (ID: {$campusId})", Auth::id() ?: 1);
                Session::flash('success', "Campus deleted successfully.");
            }
        }
        CSRF::regenerate();
        redirect(url('settings') . '#campusSection');
    }

    // Default Action: Save System Settings
    $settingsToUpdate = [
        'institute_name'         => trim($_POST['institute_name'] ?? ''),
        'system_name'            => trim($_POST['system_name'] ?? ''),
        'default_campus_id'      => (string)(int)($_POST['default_campus_id'] ?? 1),
        'timezone'               => trim($_POST['timezone'] ?? 'Asia/Karachi'),
        'institute_address'      => trim($_POST['institute_address'] ?? ''),
        'institute_phone'        => trim($_POST['institute_phone'] ?? ''),
        'institute_email'        => trim($_POST['institute_email'] ?? ''),
        'cert_signature_1_title' => trim($_POST['cert_signature_1_title'] ?? 'Competition Coordinator'),
        'cert_signature_1_name'  => trim($_POST['cert_signature_1_name'] ?? ''),
        'cert_signature_2_title' => trim($_POST['cert_signature_2_title'] ?? 'Director / Principal'),
        'cert_signature_2_name'        => trim($_POST['cert_signature_2_name'] ?? ''),
        'cert_signature_3_title'       => trim($_POST['cert_signature_3_title'] ?? 'Examination Controller'),
        'cert_signature_3_name'        => trim($_POST['cert_signature_3_name'] ?? ''),
        'proctoring_anomaly_timer'     => (string)max(1, (int)($_POST['proctoring_anomaly_timer'] ?? 5)),
        'proctoring_detect_blur'       => !empty($_POST['proctoring_detect_blur']) ? '1' : '0',
        'proctoring_strict_fullscreen' => !empty($_POST['proctoring_strict_fullscreen']) ? '1' : '0',
    ];

    // Validate required fields
    if (empty($settingsToUpdate['institute_name'])) {
        Session::flash('error', 'School / Institute name is required.');
    } elseif (empty($settingsToUpdate['system_name'])) {
        Session::flash('error', 'System name is required.');
    } else {
        // Sync default campus name and code
        $defCamp = Database::fetch("SELECT name, code FROM campuses WHERE id = ?", [(int)$settingsToUpdate['default_campus_id']]);
        if ($defCamp) {
            $settingsToUpdate['default_campus_name'] = $defCamp['name'];
            $settingsToUpdate['default_campus_code'] = $defCamp['code'];
        }

        // Get old values for audit
        $oldValues = [];
        foreach (array_keys($settingsToUpdate) as $key) {
            $oldValues[$key] = getSetting($key, '');
        }

        // Update each setting
        foreach ($settingsToUpdate as $key => $value) {
            updateSetting($key, $value);
        }

        // Handle logo upload
        if (isset($_FILES['institute_logo']) && $_FILES['institute_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['institute_logo'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowedTypes)) {
                Session::flash('error', 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG.');
            } elseif ($file['size'] > $maxSize) {
                Session::flash('error', 'File too large. Maximum size: 2MB.');
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . time() . '.' . $ext;
                $uploadDir = UPLOADS_PATH . '/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    // Delete old logo
                    $oldLogo = getSetting('institute_logo', '');
                    if ($oldLogo && file_exists(UPLOADS_PATH . '/' . $oldLogo)) {
                        unlink(UPLOADS_PATH . '/' . $oldLogo);
                    }
                    updateSetting('institute_logo', $filename);
                    @copy($uploadDir . $filename, ROOT_PATH . '/favicon.ico');
                    @copy($uploadDir . $filename, ROOT_PATH . '/public/favicon.ico');
                }
            }
        }

        AuditLog::log('settings_updated', 'settings', 'System settings updated', null, $oldValues, $settingsToUpdate);

        Session::flash('success', 'Settings updated successfully.');
        CSRF::regenerate();
        redirect(url('settings'));
    }
}

// Available timezones
$timezones = [
    'Asia/Karachi'    => 'Asia/Karachi (PKT, +05:00)',
    'Asia/Kolkata'    => 'Asia/Kolkata (IST, +05:30)',
    'Asia/Dubai'      => 'Asia/Dubai (GST, +04:00)',
    'Asia/Riyadh'     => 'Asia/Riyadh (AST, +03:00)',
    'Asia/Shanghai'   => 'Asia/Shanghai (CST, +08:00)',
    'Asia/Tokyo'      => 'Asia/Tokyo (JST, +09:00)',
    'UTC'             => 'UTC (+00:00)',
    'Europe/London'   => 'Europe/London (GMT, +00:00)',
    'America/New_York' => 'America/New_York (EST, -05:00)',
];

$currentTimezone = getSetting('timezone', 'Asia/Karachi');
$currentLogo = getSetting('institute_logo', '');
$defaultCampusId = (int)getSetting('default_campus_id', '1');

// Campuses list with relational stats
$campusesList = Database::fetchAll("
    SELECT c.*,
           (SELECT COUNT(*) FROM assessments a WHERE a.campus_id = c.id) AS assessment_count,
           (SELECT COUNT(*) FROM users u WHERE u.campus_id = c.id) AS user_count
    FROM campuses c
    ORDER BY c.id ASC
");
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-cog me-2"></i> System Settings
            </h1>
            <p class="page-subtitle mb-0">Configure School / Institute name, campus branches, and system preferences</p>
        </div>
        <div class="d-flex gap-2">
            <a href="#campusSection" class="btn btn-outline-info btn-sm">
                <i class="fas fa-university me-1"></i> Manage Campuses (<?= count($campusesList) ?>)
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="<?= url('settings') ?>" enctype="multipart/form-data" id="settingsForm">
            <input type="hidden" name="action" value="save_settings">
            <?= CSRF::field() ?>

            <!-- School & Institute Information -->
            <div class="content-card mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-school me-2 text-primary"></i> School / Institute Information</h5>
                    <span class="badge bg-primary bg-opacity-25 text-primary">Global Identity</span>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="institute_name" class="form-label fw-bold">School / Institute Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" id="institute_name" name="institute_name"
                               value="<?= e(getSetting('institute_name', INSTITUTE_NAME_DEFAULT)) ?>" required
                               placeholder="e.g. Cambridge Grammar School, Army Public School, etc.">
                        <small class="text-muted">The official name of the school or institute. Displayed across the top navigation, candidate exam interfaces, assessment certificates, and report center.</small>
                    </div>

                    <div class="mb-3">
                        <label for="default_campus_id" class="form-label fw-bold">Primary Default Campus</label>
                        <select class="form-select" id="default_campus_id" name="default_campus_id">
                            <?php foreach ($campusesList as $camp): ?>
                                <option value="<?= $camp['id'] ?>" <?= $defaultCampusId === (int)$camp['id'] ? 'selected' : '' ?>>
                                    <?= e($camp['name']) ?> (<?= e($camp['code']) ?>) <?= $camp['status'] === 'inactive' ? '[Inactive]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Default campus assigned when creating new assessments, test sessions, and users.</small>
                    </div>

                    <div class="mb-3">
                        <label for="institute_logo" class="form-label fw-bold">School / Institute Logo</label>
                        <?php if ($currentLogo): ?>
                            <div class="mb-2 p-2 bg-dark rounded border border-secondary d-inline-block">
                                <img src="<?= upload(e($currentLogo)) ?>" alt="Current Logo" class="settings-logo-preview" style="max-height: 55px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="institute_logo" name="institute_logo"
                               accept="image/*">
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF, WEBP, SVG. Maximum file size: 2MB.</small>
                    </div>

                    <div class="mb-3">
                        <label for="institute_address" class="form-label fw-bold">Address</label>
                        <textarea class="form-control" id="institute_address" name="institute_address"
                                  rows="2" placeholder="e.g. Main Campus, Education City, Karachi, Pakistan"><?= e(getSetting('institute_address', '')) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="institute_phone" class="form-label fw-bold">Phone Number</label>
                            <input type="text" class="form-control" id="institute_phone" name="institute_phone"
                                   value="<?= e(getSetting('institute_phone', '')) ?>" placeholder="e.g. +92 300 1234567">
                        </div>
                        <div class="col-md-6">
                            <label for="institute_email" class="form-label fw-bold">Official Email</label>
                            <input type="email" class="form-control" id="institute_email" name="institute_email"
                                   value="<?= e(getSetting('institute_email', '')) ?>" placeholder="e.g. exam@school.edu.pk">
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Configuration -->
            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5><i class="fas fa-sliders-h me-2 text-info"></i> System Configuration</h5>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="system_name" class="form-label fw-bold">System Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="system_name" name="system_name"
                               value="<?= e(getSetting('system_name', SYSTEM_NAME_DEFAULT)) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="timezone" class="form-label fw-bold">System Timezone</label>
                        <select class="form-select" id="timezone" name="timezone">
                            <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?= e($tz) ?>" <?= $currentTimezone === $tz ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Exam Integrity & Proctoring Anomaly Configuration -->
            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5><i class="fas fa-shield-alt me-2 text-danger"></i> Exam Integrity & Proctoring Telemetry</h5>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label for="proctoring_anomaly_timer" class="form-label fw-bold">Window Blur / Away Alert Timer Threshold <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="proctoring_anomaly_timer" name="proctoring_anomaly_timer" min="1" max="300"
                                   value="<?= e(getSetting('proctoring_anomaly_timer', '5')) ?>" required>
                            <span class="input-group-text fw-bold">Seconds</span>
                        </div>
                        <small class="text-muted">If a candidate switches tabs or minimizes the exam window for longer than this limit, a violation alert warning modal pops up and is logged into the Security Violations telemetry.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="proctoring_detect_blur" name="proctoring_detect_blur" value="1" <?= getSetting('proctoring_detect_blur', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="proctoring_detect_blur">Log Focus Loss / Window Blur Events</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="proctoring_strict_fullscreen" name="proctoring_strict_fullscreen" value="1" <?= getSetting('proctoring_strict_fullscreen', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="proctoring_strict_fullscreen">Enforce Strict Fullscreen Requirement</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificate Signatures Configuration -->
            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5><i class="fas fa-signature me-2 text-warning"></i> Certificate Signatures & Authority</h5>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="cert_signature_1_title" class="form-label fw-semibold">Left Signature Title</label>
                            <input type="text" class="form-control" id="cert_signature_1_title" name="cert_signature_1_title"
                                   value="<?= e(getSetting('cert_signature_1_title', 'Competition Coordinator')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cert_signature_1_name" class="form-label fw-semibold">Left Signatory Name (Optional)</label>
                            <input type="text" class="form-control" id="cert_signature_1_name" name="cert_signature_1_name"
                                   value="<?= e(getSetting('cert_signature_1_name', '')) ?>" placeholder="e.g. Prof. Ahmed Ali">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="cert_signature_2_title" class="form-label fw-semibold">Right Signature Title</label>
                            <input type="text" class="form-control" id="cert_signature_2_title" name="cert_signature_2_title"
                                   value="<?= e(getSetting('cert_signature_2_title', 'Director / Principal')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cert_signature_2_name" class="form-label fw-semibold">Right Signatory Name (Optional)</label>
                            <input type="text" class="form-control" id="cert_signature_2_name" name="cert_signature_2_name"
                                   value="<?= e(getSetting('cert_signature_2_name', '')) ?>" placeholder="e.g. Dr. Muhammad Tariq">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cert_signature_3_title" class="form-label fw-semibold">Center Seal / Controller Title</label>
                            <input type="text" class="form-control" id="cert_signature_3_title" name="cert_signature_3_title"
                                   value="<?= e(getSetting('cert_signature_3_title', 'Examination Controller')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="cert_signature_3_name" class="form-label fw-semibold">Center Signatory Name (Optional)</label>
                            <input type="text" class="form-control" id="cert_signature_3_name" name="cert_signature_3_name"
                                   value="<?= e(getSetting('cert_signature_3_name', '')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary px-4" id="saveSettingsBtn">
                    <i class="fas fa-save me-2"></i> Save Settings
                </button>
                <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Campus Management Section -->
        <div class="content-card mb-4" id="campusSection">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-university me-2 text-info"></i> Campus & Branch Management</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCampusModal">
                    <i class="fas fa-plus me-1"></i> Add Campus
                </button>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle">
                        <thead class="table-dark small text-uppercase">
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Campus Name</th>
                                <th>Code</th>
                                <th class="text-center" style="width: 110px;">Assessments</th>
                                <th class="text-center" style="width: 90px;">Users</th>
                                <th class="text-center" style="width: 95px;">Status</th>
                                <th class="text-end" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campusesList as $camp): ?>
                                <tr>
                                    <td class="text-muted small font-monospace"><?= (int)$camp['id'] ?></td>
                                    <td>
                                        <strong><?= e($camp['name']) ?></strong>
                                        <?php if ((int)$camp['id'] === $defaultCampusId): ?>
                                            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 ms-1" title="Primary default campus">
                                                <i class="fas fa-star me-1"></i>Default
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary font-monospace"><?= e($camp['code']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= (int)$camp['assessment_count'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= (int)$camp['user_count'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($camp['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-outline-light btn-sm edit-campus-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editCampusModal"
                                                    data-id="<?= (int)$camp['id'] ?>"
                                                    data-name="<?= e($camp['name']) ?>"
                                                    data-code="<?= e($camp['code']) ?>"
                                                    data-status="<?= e($camp['status']) ?>"
                                                    title="Edit Campus">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>

                                            <!-- Toggle Status Form -->
                                            <form method="POST" action="<?= url('settings') ?>" class="d-inline"
                                                  onsubmit="return confirm('Toggle active/inactive status for this campus?');">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="toggle_campus_status">
                                                <input type="hidden" name="campus_id" value="<?= (int)$camp['id'] ?>">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>

                                            <!-- Delete Form -->
                                            <?php if ((int)$camp['assessment_count'] === 0 && (int)$camp['user_count'] === 0 && count($campusesList) > 1): ?>
                                                <form method="POST" action="<?= url('settings') ?>" class="d-inline"
                                                      onsubmit="return confirm('Are you sure you want to permanently delete this campus?');">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="delete_campus">
                                                    <input type="hidden" name="campus_id" value="<?= (int)$camp['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Campus">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Information Card -->
    <div class="col-lg-4">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-info-circle me-2"></i> Settings Overview</h5>
            </div>
            <div class="card-body-custom">
                <div class="text-muted small">
                    <p class="mb-2"><strong><i class="fas fa-school text-primary me-1"></i> School / Institute Name:</strong> Displayed on candidate test portals, header bars, result slips, and attendance registries.</p>
                    <p class="mb-2"><strong><i class="fas fa-university text-info me-1"></i> Campuses:</strong> Multiple physical or logical branches. Automatically populates assessment creation and user assignment forms.</p>
                    <p class="mb-2"><strong><i class="fas fa-shield-alt text-danger me-1"></i> Proctoring Telemetry:</strong> Configures tab-switch limits and anti-cheating alerts displayed under the Security Violations log.</p>
                    <p class="mb-0"><strong><i class="fas fa-clock text-warning me-1"></i> Timezone:</strong> Affects all exam start/end schedules and timestamp recordings.</p>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-link me-2"></i> Quick Links</h5>
            </div>
            <div class="card-body-custom">
                <div class="d-grid gap-2">
                    <a href="<?= url('security-events') ?>" class="btn btn-outline-danger btn-sm text-start">
                        <i class="fas fa-shield-alt me-2"></i> Security Violations Tab
                    </a>
                    <a href="<?= url('assessments-create') ?>" class="btn btn-outline-primary btn-sm text-start">
                        <i class="fas fa-plus me-2"></i> Create Assessment
                    </a>
                    <a href="<?= url('users') ?>" class="btn btn-outline-info btn-sm text-start">
                        <i class="fas fa-users-cog me-2"></i> User Management
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Campus -->
<div class="modal fade" id="addCampusModal" tabindex="-1" aria-labelledby="addCampusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <form method="POST" action="<?= url('settings') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add_campus">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="addCampusModalLabel"><i class="fas fa-plus me-2 text-primary"></i> Add New Campus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_campus_name" class="form-label fw-semibold">Campus Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="new_campus_name" name="campus_name" required placeholder="e.g. City Campus, North Wing, Main Campus">
                    </div>
                    <div class="mb-3">
                        <label for="new_campus_code" class="form-label fw-semibold">Campus Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-light border-secondary text-uppercase" id="new_campus_code" name="campus_code" required placeholder="e.g. CITY, NORTH, MAIN">
                        <small class="text-muted">Unique short uppercase code for roll numbers and filters.</small>
                    </div>
                    <div class="mb-3">
                        <label for="new_campus_status" class="form-label fw-semibold">Status</label>
                        <select class="form-select bg-dark text-light border-secondary" id="new_campus_status" name="campus_status">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Add Campus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Campus -->
<div class="modal fade" id="editCampusModal" tabindex="-1" aria-labelledby="editCampusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <form method="POST" action="<?= url('settings') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="edit_campus">
                <input type="hidden" name="campus_id" id="edit_campus_id" value="">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editCampusModalLabel"><i class="fas fa-pencil-alt me-2 text-info"></i> Edit Campus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_campus_name" class="form-label fw-semibold">Campus Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_campus_name" name="campus_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_campus_code" class="form-label fw-semibold">Campus Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-light border-secondary text-uppercase" id="edit_campus_code" name="campus_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_campus_status" class="form-label fw-semibold">Status</label>
                        <select class="form-select bg-dark text-light border-secondary" id="edit_campus_status" name="campus_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-check me-1"></i> Update Campus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-campus-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_campus_id').value = this.getAttribute('data-id');
            document.getElementById('edit_campus_name').value = this.getAttribute('data-name');
            document.getElementById('edit_campus_code').value = this.getAttribute('data-code');
            document.getElementById('edit_campus_status').value = this.getAttribute('data-status');
        });
    });
});
</script>
