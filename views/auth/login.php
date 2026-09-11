<?php
/**
 * Login Page
 */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    CSRF::validateOrFail();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    $validator = new Validator($_POST);
    $validator->required('username', 'Username')
              ->required('password', 'Password');

    if ($validator->fails()) {
        $errors = $validator->errors();
    } else {
        $result = Auth::login($username, $password);

        if ($result['success']) {
            Session::flash('success', 'Welcome back, ' . e(Session::get('full_name')) . '!');

            // Check if password change is forced
            if (Session::get('force_password_change')) {
                redirect(url('change-password'));
            }

            if (Auth::isTeacher()) {
                redirect(url('teacher-dashboard'));
            } else {
                redirect(url('dashboard'));
            }
        } else {
            $errors['login'] = $result['message'];
        }
    }
}
?>

<form method="POST" action="<?= url('login') ?>" class="auth-form" id="loginForm" autocomplete="off">
    <?= CSRF::field() ?>

    <?php if (!empty($errors['login'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= e($errors['login']) ?>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="username" class="form-label">
            <i class="fas fa-user me-1"></i> Username
        </label>
        <input 
            type="text" 
            class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
            id="username" 
            name="username" 
            value="<?= e($_POST['username'] ?? '') ?>"
            placeholder="Enter your username"
            required
            autofocus
        >
        <?php if (isset($errors['username'])): ?>
            <div class="invalid-feedback"><?= e($errors['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="password" class="form-label">
            <i class="fas fa-lock me-1"></i> Password
        </label>
        <div class="input-group">
            <input 
                type="password" 
                class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                id="password" 
                name="password" 
                placeholder="Enter your password"
                required
            >
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <?php if (isset($errors['password'])): ?>
            <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
        <i class="fas fa-sign-in-alt me-2"></i> Sign In
    </button>
</form>
