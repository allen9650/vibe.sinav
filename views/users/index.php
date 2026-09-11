<?php
declare(strict_types=1);

/**
 * PTM Assessment System — User Management & Access Control Roster
 * Super Administrator Exclusive View.
 */

if (!Auth::isSuperAdmin()) {
    Session::flash('error', 'Access denied. Only Super Administrators can access User Management.');
    redirectTo('dashboard');
}

// Handle POST actions (e.g. Delete User, Toggle Status)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'delete_user' && $targetUserId > 0) {
        try {
            UserService::deleteUser($targetUserId);
            Session::flash('success', 'User account was successfully deleted.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        redirectTo('users');
    }

    if ($action === 'toggle_status' && $targetUserId > 0) {
        try {
            $userRecord = UserService::getUser($targetUserId);
            if ($userRecord) {
                if ($targetUserId === Auth::id()) {
                    throw new RuntimeException("You cannot deactivate your own active session account.");
                }
                $newStatus = ($userRecord['status'] === 'active') ? 'inactive' : 'active';
                UserService::updateUser($targetUserId, ['status' => $newStatus]);
                Session::flash('success', "User '{$userRecord['username']}' status changed to " . strtoupper($newStatus) . ".");
            }
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        redirectTo('users');
    }
}

// Filters & Query
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$filters = [];
if ($search !== '') {
    $filters['search'] = $search;
}
if ($roleFilter !== '') {
    $filters['role'] = $roleFilter;
}
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}

$users = UserService::getUsers($filters);
$allRoles = UserService::getAvailableRoles();

// Aggregate metrics for summary cards
$totalUsers = count($users);
$superAdminCount = 0;
$teacherCount = 0;
$subuserCount = 0;
$activeCount = 0;

foreach ($users as $u) {
    if ($u['role'] === 'super-admin') $superAdminCount++;
    if ($u['role'] === 'teacher') $teacherCount++;
    if ($u['role'] === 'subuser') $subuserCount++;
    if ($u['status'] === 'active') $activeCount++;
}
?>

<div class="container-fluid py-3">
    <!-- Header with Action -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">User Management</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1">
                <i class="fas fa-users-gear text-info me-2"></i> User & Subuser Management
            </h3>
            <p class="text-muted small mb-0">
                Superadmin exclusive console. Configure user accounts, assign granular permissions, and restrict subuser delete capabilities.
            </p>
        </div>
        <div>
            <a href="<?= url('users-create') ?>" class="btn btn-primary fw-semibold shadow-sm">
                <i class="fas fa-user-plus me-1"></i> Add New User
            </a>
        </div>
    </div>

    <!-- Quick Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-dark bg-opacity-50 border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Accounts</span>
                            <h4 class="fw-bold mb-0 mt-1"><?= $totalUsers ?></h4>
                        </div>
                        <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-dark bg-opacity-50 border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Restricted Subusers</span>
                            <h4 class="fw-bold mb-0 mt-1"><?= $subuserCount ?></h4>
                        </div>
                        <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-user-shield fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-dark bg-opacity-50 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Teachers / Examiners</span>
                            <h4 class="fw-bold mb-0 mt-1"><?= $teacherCount ?></h4>
                        </div>
                        <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-chalkboard-teacher fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-dark bg-opacity-50 border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Super Administrators</span>
                            <h4 class="fw-bold mb-0 mt-1"><?= $superAdminCount ?></h4>
                        </div>
                        <div class="rounded-circle p-3 bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-crown fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm bg-dark bg-opacity-50 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="users">
                
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" 
                               placeholder="Search by username or full name..." value="<?= e($search) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="role" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Roles</option>
                        <?php foreach ($allRoles as $rKey => $rLabel): ?>
                            <option value="<?= e($rKey) ?>" <?= $roleFilter === $rKey ? 'selected' : '' ?>>
                                <?= e($rLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                        <a href="<?= url('users') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border-0 shadow-sm bg-dark bg-opacity-50">
        <div class="card-header bg-transparent border-bottom border-secondary d-flex justify-content-between align-items-center py-3">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-id-badge text-primary me-2"></i> User Ledger
            </h5>
            <span class="badge bg-secondary font-monospace"><?= count($users) ?> accounts found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead class="text-uppercase small text-muted border-secondary" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                        <tr>
                            <th style="width: 260px;" class="ps-3">User & Identity</th>
                            <th style="width: 170px;">Role</th>
                            <th style="width: 170px;">Campus</th>
                            <th>Granular Permissions & Restrictions</th>
                            <th style="width: 110px;" class="text-center">Status</th>
                            <th style="width: 140px;" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
                                    No users found matching the selected filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $uId = (int)$u['id'];
                                $isSelf = ($uId === Auth::id());
                                $isSuperAdmin = ($u['role'] === 'super-admin');
                                $userPerms = UserService::getUserPermissions($uId);

                                // Check specific delete restrictions
                                $canDeleteAssessments = $isSuperAdmin || in_array('assessments.delete', $userPerms, true);
                                $canDeleteQuestions = $isSuperAdmin || in_array('questions.delete', $userPerms, true);
                                $canDeleteResults = $isSuperAdmin || in_array('results.delete', $userPerms, true);
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary bg-opacity-25 text-light fw-bold" style="width: 38px; height: 38px; font-size: 0.95rem;">
                                                <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-light">
                                                    <?= e($u['full_name']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge bg-info text-dark ms-1" style="font-size: 0.65rem;">YOU</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small font-monospace">@<?= e($u['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'super-admin'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger">
                                                <i class="fas fa-crown me-1 text-warning"></i> Super Admin
                                            </span>
                                        <?php elseif ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary">
                                                <i class="fas fa-user-gear me-1"></i> Administrator
                                            </span>
                                        <?php elseif ($u['role'] === 'teacher'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success">
                                                <i class="fas fa-chalkboard-teacher me-1"></i> Teacher / Examiner
                                            </span>
                                        <?php elseif ($u['role'] === 'subuser'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning">
                                                <i class="fas fa-user-lock me-1"></i> Subuser
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= e(ucfirst($u['role'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-light"><?= e($u['campus_name'] ?? 'Main Campus') ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= e($u['campus_code'] ?? 'KHP-MAIN') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($isSuperAdmin): ?>
                                            <span class="badge bg-purple-subtle text-light border border-info px-2 py-1">
                                                <i class="fas fa-asterisk text-warning me-1"></i> Full System Access (*)
                                            </span>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap align-items-center gap-1">
                                                <span class="badge bg-secondary bg-opacity-50 text-light border border-secondary" title="<?= count($userPerms) ?> permissions assigned">
                                                    <i class="fas fa-key me-1 text-info"></i> <?= count($userPerms) ?> Perms
                                                </span>

                                                <!-- Granular delete safety badges -->
                                                <?php if (!$canDeleteAssessments): ?>
                                                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50" title="Restricted: Cannot delete assessments">
                                                        <i class="fas fa-ban me-1"></i>No Assess Del
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-muted border border-secondary border-opacity-25" title="Has assessment delete capability">
                                                        <i class="fas fa-trash me-1"></i>Assess Del
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!$canDeleteQuestions): ?>
                                                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50" title="Restricted: Cannot delete questions">
                                                        <i class="fas fa-ban me-1"></i>No Qs Del
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-muted border border-secondary border-opacity-25" title="Has question delete capability">
                                                        <i class="fas fa-trash me-1"></i>Qs Del
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!$canDeleteResults): ?>
                                                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50" title="Restricted: Cannot delete or wipe candidate scores">
                                                        <i class="fas fa-ban me-1"></i>No Results Del
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-muted border border-secondary border-opacity-25" title="Has results delete capability">
                                                        <i class="fas fa-trash me-1"></i>Results Del
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Toggle status for user <?= e(addslashes($u['username'])) ?>?');">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $uId ?>">
                                            <button type="submit" class="btn btn-link p-0 text-decoration-none border-0" <?= $isSelf ? 'disabled title="Cannot change status of currently logged in user"' : '' ?>>
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <span class="badge bg-success bg-opacity-15 text-success border border-success px-2 py-1">
                                                        <i class="fas fa-circle-check me-1"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary text-muted border border-secondary px-2 py-1">
                                                        <i class="fas fa-circle-xmark me-1"></i> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <!-- Edit User -->
                                            <a href="<?= url('users-edit&id=' . $uId) ?>" class="btn btn-sm btn-outline-info py-1 px-2" title="Edit User & Permissions">
                                                <i class="fas fa-user-pen"></i>
                                            </a>

                                            <!-- Delete User -->
                                            <?php if ($isSelf): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" disabled title="You cannot delete your own active account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php elseif ($isSuperAdmin && $superAdminCount <= 1): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" disabled title="Cannot delete the sole Super Administrator">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete user account: <?= e(addslashes($u['username'])) ?> (<?= e(addslashes($u['full_name'])) ?>)? This action is irreversible.');">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete User">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

