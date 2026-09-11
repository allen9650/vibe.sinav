<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Add New User & Configure Permissions
 * Super Administrator Exclusive View.
 */

if (!Auth::isSuperAdmin()) {
    Session::flash('error', 'Access denied. Only Super Administrators can create user accounts.');
    redirectTo('dashboard');
}

$campuses = Database::fetchAll("SELECT id, name, code FROM campuses ORDER BY id ASC");
$allPermissionsGrouped = UserService::getAllPermissions();
$availableRoles = UserService::getAvailableRoles();

$errors = [];
$formData = [
    'username'  => '',
    'full_name' => '',
    'role'      => 'subuser',
    'campus_id' => !empty($campuses) ? (int)$campuses[0]['id'] : 1,
    'status'    => 'active',
];
$selectedPermissions = UserService::getDefaultPermissionsForRole('subuser');

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $formData['username'] = trim((string)($_POST['username'] ?? ''));
    $formData['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $formData['role'] = trim((string)($_POST['role'] ?? 'subuser'));
    $formData['campus_id'] = (int)($_POST['campus_id'] ?? 1);
    $formData['status'] = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
    $password = (string)($_POST['password'] ?? '');

    $submittedPermissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
        ? array_map('strval', $_POST['permissions'])
        : [];
    $selectedPermissions = $submittedPermissions;

    try {
        $createData = [
            'username'  => $formData['username'],
            'full_name' => $formData['full_name'],
            'password'  => $password,
            'role'      => $formData['role'],
            'campus_id' => $formData['campus_id'],
            'status'    => $formData['status'],
        ];

        $newUserId = UserService::createUser($createData, $selectedPermissions);
        Session::flash('success', "User '{$formData['username']}' was created successfully with assigned permissions.");
        redirectTo('users');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>

<div class="container-fluid py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('users') ?>" class="text-decoration-none text-muted">User Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add New User</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1">
                <i class="fas fa-user-plus text-primary me-2"></i> Add New User & Configure Permissions
            </h3>
            <p class="text-muted small mb-0">
                Register a new administrator, teacher, or restricted subuser with granular capability controls.
            </p>
        </div>
        <div>
            <a href="<?= url('users') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Users
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i> Could not create user:</h6>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="createUserForm">
        <?= CSRF::field() ?>

        <div class="row g-4">
            <!-- Left Column: User Profile & Credentials -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm bg-dark bg-opacity-50 sticky-lg-top" style="top: 85px; z-index: 10;">
                    <div class="card-header bg-transparent border-bottom border-secondary py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-id-card text-info me-2"></i> Account Credentials
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control bg-dark text-light border-secondary" 
                                   placeholder="e.g. John Doe / Prof. Ali" value="<?= e($formData['full_name']) ?>" required>
                        </div>

                        <!-- Username -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted">@</span>
                                <input type="text" name="username" class="form-control bg-dark text-light border-secondary" 
                                       placeholder="e.g. teacher_ali" value="<?= e($formData['username']) ?>" 
                                       pattern="^[a-zA-Z0-9_\-\.]+$" minlength="3" required>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.72rem;">Letters, numbers, underscores, and hyphens (min 3 chars).</div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordInput" class="form-control bg-dark text-light border-secondary" 
                                       placeholder="Minimum 6 characters" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('passwordInput', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Primary Role -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Primary Role <span class="text-danger">*</span></label>
                            <select name="role" id="roleSelector" class="form-select bg-dark text-light border-secondary" onchange="handleRoleChange(this.value)">
                                <?php foreach ($availableRoles as $rKey => $rLabel): ?>
                                    <option value="<?= e($rKey) ?>" <?= $formData['role'] === $rKey ? 'selected' : '' ?>>
                                        <?= e($rLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted" style="font-size: 0.72rem;">
                                Selecting a role will configure recommended permission presets.
                            </div>
                        </div>

                        <!-- Campus Allocation -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Assigned Campus <span class="text-danger">*</span></label>
                            <select name="campus_id" class="form-select bg-dark text-light border-secondary">
                                <?php foreach ($campuses as $camp): ?>
                                    <option value="<?= (int)$camp['id'] ?>" <?= (int)$formData['campus_id'] === (int)$camp['id'] ? 'selected' : '' ?>>
                                        <?= e($camp['name']) ?> (<?= e($camp['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Account Status -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-light">Account Status</label>
                            <select name="status" class="form-select bg-dark text-light border-secondary">
                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Can Login)</option>
                                <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Login Disabled)</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-4 pt-2 border-top border-secondary">
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-save me-1"></i> Create User Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Granular Permission Controls -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm bg-dark bg-opacity-50">
                    <div class="card-header bg-transparent border-bottom border-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                        <div>
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-shield-halved text-warning me-2"></i> Granular Permissions Matrix
                            </h5>
                            <span class="text-muted small">Configure exact subuser capabilities and deletion restrictions</span>
                        </div>
                        <!-- Preset Action Buttons -->
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-warning" onclick="applyPreset('subuser_nodelete')">
                                <i class="fas fa-user-lock me-1"></i> Subuser (No Delete)
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="applyPreset('teacher')">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Teacher
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="applyPreset('admin')">
                                <i class="fas fa-user-shield me-1"></i> Admin
                            </button>
                            <button type="button" class="btn btn-outline-light" onclick="toggleAllCheckboxes(true)">
                                <i class="fas fa-check-double me-1"></i> All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCheckboxes(false)">
                                <i class="fas fa-xmark me-1"></i> None
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <!-- Super Admin Alert Banner (hidden unless super-admin selected) -->
                        <div id="superAdminAlert" class="alert alert-info border-info d-none mb-3">
                            <i class="fas fa-crown text-warning me-2"></i>
                            <strong>Super Administrator Role Selected:</strong> Super Admins automatically bypass all permission gates with global system privileges (<code>*</code>). Checkbox selections below are optional and ignored for superadmins.
                        </div>

                        <!-- Subuser restriction explanation banner -->
                        <div class="alert alert-secondary border-secondary py-2 px-3 mb-4 small">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <strong>Subuser Delete Safety Rules:</strong> Leaving <code>assessments.delete</code>, <code>questions.delete</code>, or <code>results.delete</code> unchecked guarantees that this user will be completely restricted from deleting assessments, questions, or candidate results across the entire application.
                        </div>

                        <!-- Permission Groups -->
                        <div class="d-flex flex-column gap-3" id="permissionsContainer">
                            <?php foreach ($allPermissionsGrouped as $moduleGroup => $modulePerms): ?>
                                <div class="card border border-secondary border-opacity-50 bg-dark bg-opacity-25 rounded-3">
                                    <div class="card-header bg-dark bg-opacity-50 border-bottom border-secondary border-opacity-50 py-2 px-3 d-flex justify-content-between align-items-center">
                                        <div class="fw-bold text-light small text-uppercase" style="letter-spacing: 0.05em;">
                                            <?php if (strpos($moduleGroup, 'Assess') !== false): ?>
                                                <i class="fas fa-tasks text-info me-1"></i>
                                            <?php elseif (strpos($moduleGroup, 'Question') !== false): ?>
                                                <i class="fas fa-layer-group text-primary me-1"></i>
                                            <?php elseif (strpos($moduleGroup, 'Result') !== false): ?>
                                                <i class="fas fa-poll text-success me-1"></i>
                                            <?php elseif (strpos($moduleGroup, 'Attendance') !== false): ?>
                                                <i class="fas fa-clipboard-user text-warning me-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-satellite-dish text-danger me-1"></i>
                                            <?php endif; ?>
                                            <?= e($moduleGroup) ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" style="font-size: 0.72rem;" onclick="toggleGroupCheckboxes(this, true)">Select</button>
                                            <span class="text-muted mx-1" style="font-size: 0.72rem;">•</span>
                                            <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" style="font-size: 0.72rem;" onclick="toggleGroupCheckboxes(this, false)">Clear</button>
                                        </div>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row g-3">
                                            <?php foreach ($modulePerms as $slug => $perm): ?>
                                                <?php
                                                $isChecked = in_array($slug, $selectedPermissions, true);
                                                $isDeletePerm = (strpos($slug, '.delete') !== false);
                                                ?>
                                                <div class="col-md-6">
                                                    <div class="p-2.5 rounded border <?= $isDeletePerm ? 'border-danger border-opacity-50 bg-danger bg-opacity-10' : 'border-secondary border-opacity-25 bg-dark bg-opacity-25' ?> h-100">
                                                        <div class="form-check mb-1">
                                                            <input class="form-check-input perm-checkbox" type="checkbox" 
                                                                   name="permissions[]" value="<?= e($slug) ?>" id="perm_<?= e(str_replace('.', '_', $slug)) ?>"
                                                                   data-slug="<?= e($slug) ?>"
                                                                   <?= $isChecked ? 'checked' : '' ?>>
                                                            <label class="form-check-label fw-semibold text-light small" for="perm_<?= e(str_replace('.', '_', $slug)) ?>">
                                                                <?= e($perm['name']) ?>
                                                                <?php if ($isDeletePerm): ?>
                                                                    <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;">
                                                                        <i class="fas fa-triangle-exclamation me-1"></i>DELETE ACTION
                                                                    </span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                        <div class="text-muted ms-4" style="font-size: 0.73rem;">
                                                            <?= e($perm['desc']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const PRESETS = {
    'subuser_nodelete': [
        'assessments.view', 'assessments.create', 'assessments.edit',
        'questions.view', 'questions.create', 'questions.edit',
        'results.view',
        'attendance.view', 'attendance.manage',
        'reports.view',
        'monitoring.view'
    ],
    'teacher': [
        'assessments.view', 'assessments.create', 'assessments.edit', 'assessments.publish',
        'questions.view', 'questions.create', 'questions.edit',
        'results.view',
        'attendance.view', 'attendance.manage',
        'reports.view', 'reports.export',
        'monitoring.view'
    ],
    'admin': [
        'assessments.view', 'assessments.create', 'assessments.edit', 'assessments.publish',
        'questions.view', 'questions.create', 'questions.edit',
        'results.view', 'results.export',
        'attendance.view', 'attendance.manage',
        'reports.view', 'reports.export',
        'monitoring.view'
    ]
};

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPass ? 'text' : 'password');
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
}

function handleRoleChange(role) {
    const superAdminAlert = document.getElementById('superAdminAlert');
    if (role === 'super-admin') {
        superAdminAlert.classList.remove('d-none');
        toggleAllCheckboxes(true);
    } else {
        superAdminAlert.classList.add('d-none');
        if (role === 'teacher') {
            applyPreset('teacher');
        } else if (role === 'admin') {
            applyPreset('admin');
        } else {
            applyPreset('subuser_nodelete');
        }
    }
}

function applyPreset(presetKey) {
    const allowed = PRESETS[presetKey] || [];
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = allowed.includes(cb.dataset.slug);
    });
}

function toggleAllCheckboxes(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = checked;
    });
}

function toggleGroupCheckboxes(btn, checked) {
    const card = btn.closest('.card');
    if (!card) return;
    card.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = checked;
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    handleRoleChange(document.getElementById('roleSelector').value);
});
</script>

