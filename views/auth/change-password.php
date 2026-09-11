<?php
/**
 * Change Password Page
 */
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validateOrFail();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    $validator = new Validator($_POST);
    $validator->required('current_password', 'Current Password')
              ->required('new_password', 'New Password')
              ->minLength('new_password', PASSWORD_MIN_LENGTH, 'New Password')
              ->required('confirm_password', 'Confirm Password')
              ->matches('confirm_password', 'new_password', 'Confirm Password', 'New Password');

    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        $result = Auth::changePassword(Session::get('user_id'), $currentPassword, $newPassword);

        if ($result['success']) {
            Session::flash('success', $result['message']);
            CSRF::regenerate();
            redirect(url('dashboard'));
        } else {
            $errors['password'] = $result['message'];
        }
    }
}

$isForced = Session::get('force_password_change');
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="content-card">
            <div class="card-header-custom">
                <h2><i class="fas fa-key me-2"></i> Change Password</h2>
                <?php if ($isForced): ?>
                    <p class="text-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        You must change your password before continuing.
                    </p>
                <?php endif; ?>
            </div>

            <div class="card-body-custom">
                <?php if (!empty($errors['password'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= e($errors['password']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('change-password') ?>" id="changePasswordForm">
                    <?= CSRF::field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                                   id="current_password" name="current_password" required>
                        </div>
                        <?php if (isset($errors['current_password'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                   id="new_password" name="new_password" required
                                   minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        </div>
                        <?php if (isset($errors['new_password'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['new_password']) ?></div>
                        <?php endif; ?>
                        <div class="password-requirements mt-2">
                            <small class="text-muted">Password must contain:</small>
                            <ul class="requirements-list">
                                <li id="req-length"><i class="fas fa-circle"></i> At least <?= PASSWORD_MIN_LENGTH ?> characters</li>
                                <li id="req-upper"><i class="fas fa-circle"></i> One uppercase letter</li>
                                <li id="req-lower"><i class="fas fa-circle"></i> One lowercase letter</li>
                                <li id="req-number"><i class="fas fa-circle"></i> One number</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                            <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                   id="confirm_password" name="confirm_password" required>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                            <i class="fas fa-save me-2"></i> Change Password
                        </button>
                        <?php if (!$isForced): ?>
                            <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
