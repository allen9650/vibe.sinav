<?php
/**
 * Profile Page
 */
$user = Auth::freshUser();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('full_name', 'Full Name')
              ->maxLength('full_name', 100, 'Full Name')
              ->email('email', 'Email');

    if ($validator->fails()) {
        Session::flash('error', $validator->firstError());
    } else {
        $oldValues = ['full_name' => $user['full_name'], 'email' => $user['email'], 'phone' => $user['phone']];

        Database::update('users', [
            'full_name' => $fullName,
            'email'     => $email ?: null,
            'phone'     => $phone ?: null,
        ], 'id = ?', [$user['id']]);

        // Update session
        Session::set('full_name', $fullName);
        Session::set('email', $email);

        AuditLog::log('profile_updated', 'users', "Profile updated", $user['id'], $oldValues, [
            'full_name' => $fullName, 'email' => $email, 'phone' => $phone
        ]);

        Session::flash('success', 'Profile updated successfully.');
        CSRF::regenerate();
        redirect(url('profile'));
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="dashboard-header mb-4">
            <h1 class="page-title">
                <i class="fas fa-user-circle me-2"></i> My Profile
            </h1>
        </div>

        <div class="row g-4">
            <!-- Profile Info Card -->
            <div class="col-md-4">
                <div class="content-card text-center">
                    <div class="card-body-custom py-4">
                        <div class="profile-avatar mb-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4><?= e($user['full_name']) ?></h4>
                        <span class="badge bg-primary"><?= e($user['role_name']) ?></span>
                        <hr>
                        <ul class="info-list text-start">
                            <li>
                                <span class="info-label">Username</span>
                                <span class="info-value"><?= e($user['username']) ?></span>
                            </li>
                            <li>
                                <span class="info-label">Last Login</span>
                                <span class="info-value"><?= formatDateTime($user['last_login']) ?></span>
                            </li>
                            <li>
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?= formatDate($user['created_at']) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-md-8">
                <div class="content-card">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-edit me-2"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="<?= url('profile') ?>" id="profileForm">
                            <?= CSRF::field() ?>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                                <small class="text-muted">Username cannot be changed.</small>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                                <a href="<?= url('change-password') ?>" class="btn btn-outline-warning">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
