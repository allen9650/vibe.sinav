<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Authentication & RBAC Service
 * Supports device-allocated, server-authoritative role evaluation without legacy table joins.
 */
class Auth
{
    /**
     * Attempt to log in a user by username and password.
     */
    public static function login(string $username, string $password): array
    {
        $username = trim($username);

        // Find user in users table
        $user = Database::fetch(
            "SELECT u.*, c.name AS campus_name, c.code AS campus_code
             FROM users u
             LEFT JOIN campuses c ON u.campus_id = c.id
             WHERE u.username = ?",
            [$username]
        );

        if (!$user) {
            if (class_exists('AuditLog')) {
                AuditLog::log('login_failed', 'auth', "Failed login attempt for username: $username");
            }
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Verify password against password_hash or legacy password column
        $passwordHash = (string)($user['password_hash'] ?? ($user['password'] ?? ''));
        if (!password_verify($password, $passwordHash)) {
            if (class_exists('AuditLog')) {
                AuditLog::log('login_failed', 'auth', "Wrong password for user: {$user['username']}", (int)$user['id']);
            }
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Check if user is inactive
        if (isset($user['status']) && $user['status'] === 'inactive') {
            if (class_exists('AuditLog')) {
                AuditLog::log('login_failed', 'auth', "Inactive account login blocked: {$user['username']}", (int)$user['id']);
            }
            return ['success' => false, 'message' => 'Your account is deactivated. Please contact the Super Administrator.'];
        }

        // Regenerate session for security
        Session::regenerate();

        $role = (string)($user['role'] ?? 'teacher');
        $availableRoles = [
            'super-admin' => 'Super Administrator',
            'admin'       => 'Administrator',
            'teacher'     => 'Teacher / Examiner',
            'subuser'     => 'Restricted Subuser',
        ];
        $roleName = $availableRoles[$role] ?? ucfirst($role);

        // Store user data in session
        Session::set('user_id', (int)$user['id']);
        Session::set('username', $user['username']);
        Session::set('full_name', !empty($user['full_name']) ? $user['full_name'] : ucfirst($user['username']));
        Session::set('email', $user['email'] ?? ($user['username'] . '@ptm.local'));
        Session::set('role', $role);
        Session::set('role_slug', $role);
        Session::set('role_name', $roleName);
        $defaultCampusId = (int)(function_exists('getSetting') ? getSetting('default_campus_id', '1') : 1);
        $defaultCampusName = function_exists('getSetting') ? getSetting('default_campus_name', 'Main Campus') : 'Main Campus';
        $defaultCampusCode = function_exists('getSetting') ? getSetting('default_campus_code', 'MAIN') : 'MAIN';

        Session::set('campus_id', !empty($user['campus_id']) ? (int)$user['campus_id'] : $defaultCampusId);
        Session::set('campus_name', !empty($user['campus_name']) ? $user['campus_name'] : $defaultCampusName);
        Session::set('campus_code', !empty($user['campus_code']) ? $user['campus_code'] : $defaultCampusCode);
        Session::set('force_password_change', false);
        Session::set('login_time', time());

        // Cache role-based / user-specific permissions
        if ($role === 'super-admin') {
            Session::set('permissions', ['*']);
        } else {
            $userPerms = Database::fetchAll(
                "SELECT permission_slug FROM user_permissions WHERE user_id = ?",
                [(int)$user['id']]
            );
            if (!empty($userPerms)) {
                $slugs = array_column($userPerms, 'permission_slug');
                if (!in_array('dashboard.view', $slugs, true)) {
                    $slugs[] = 'dashboard.view';
                }
                if ($role === 'teacher' && !in_array('teacher.dashboard', $slugs, true)) {
                    $slugs[] = 'teacher.dashboard';
                }
                Session::set('permissions', $slugs);
            } else {
                $rolePerms = Database::fetchAll(
                    "SELECT p.slug FROM role_permissions rp 
                     JOIN roles r ON r.id = rp.role_id 
                     JOIN permissions p ON p.id = rp.permission_id 
                     WHERE r.slug = ?",
                    [$role]
                );
                $defaults = UserService::getDefaultPermissionsForRole($role);
                $roleSlugs = !empty($rolePerms) ? array_column($rolePerms, 'slug') : [];
                $merged = array_values(array_unique(array_merge($defaults, $roleSlugs)));
                Session::set('permissions', $merged);
            }
        }

        if (class_exists('AuditLog')) {
            AuditLog::log('login_success', 'auth', "User logged in: {$user['username']}", (int)$user['id']);
        }

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user'    => $user,
        ];
    }

    /**
     * Log out the current user
     */
    public static function logout(): void
    {
        $userId = Session::get('user_id');
        $username = Session::get('username');

        if ($userId && class_exists('AuditLog')) {
            AuditLog::log('logout', 'auth', "User logged out: {$username}", (int)$userId);
        }

        Session::destroy();
    }

    /**
     * Change password for a user
     */
    public static function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = Database::fetch("SELECT id, username, password_hash FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $passwordHash = (string)($user['password_hash'] ?? '');
        if (!password_verify($currentPassword, $passwordHash)) {
            AuditLog::log('password_change_failed', 'auth', "Wrong current password for user: {$user['username']}", $userId);
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $validation = self::validatePassword($newPassword);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::update('users', [
            'password_hash' => $newHash,
        ], 'id = ?', [$userId]);

        AuditLog::log('password_changed', 'auth', "Password changed for user: {$user['username']}", $userId);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * Validate password strength
     */
    public static function validatePassword(string $password): array
    {
        if (strlen($password) < 6) {
            return ['valid' => false, 'message' => 'Password must be at least 6 characters long.'];
        }
        return ['valid' => true, 'message' => 'Password is valid.'];
    }

    /**
     * Get the currently authenticated user ID
     */
    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    /**
     * Get the currently authenticated user details
     */
    public static function user(): ?array
    {
        if (!Session::isAuthenticated()) {
            return null;
        }

        return [
            'id'          => Session::get('user_id'),
            'username'    => Session::get('username'),
            'full_name'   => Session::get('full_name'),
            'email'       => Session::get('email'),
            'role'        => Session::get('role'),
            'role_slug'   => Session::get('role_slug'),
            'role_name'   => Session::get('role_name'),
            'campus_id'   => Session::get('campus_id'),
            'campus_name' => Session::get('campus_name'),
            'campus_code' => Session::get('campus_code'),
        ];
    }

    /**
     * Get campus ID of currently logged in user
     */
    public static function campusId(): ?int
    {
        return Session::get('campus_id');
    }

    /**
     * Get campus name of currently logged in user
     */
    public static function campusName(): ?string
    {
        return Session::get('campus_name');
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return Session::isAuthenticated();
    }

    /**
     * Get active role slug of currently logged in user
     */
    public static function getRole(): string
    {
        return (string)(Session::get('role_slug') ?: (Session::get('role') ?: ''));
    }

    /**
     * Check if user is a Teacher
     */
    public static function isTeacher(): bool
    {
        return self::getRole() === 'teacher';
    }

    /**
     * Check if user is Super Admin
     */
    public static function isSuperAdmin(): bool
    {
        return self::getRole() === 'super-admin';
    }

    /**
     * Check if user is Admin or Super Admin
     */
    public static function isAdmin(): bool
    {
        return in_array(self::getRole(), ['super-admin', 'admin'], true);
    }

    /**
     * Get fresh user record from database
     */
    public static function freshUser(): ?array
    {
        if (!Session::isAuthenticated()) {
            return null;
        }

        $user = Database::fetch(
            "SELECT u.*, c.name AS campus_name, c.code AS campus_code
             FROM users u
             LEFT JOIN campuses c ON u.campus_id = c.id
             WHERE u.id = ?",
            [Session::get('user_id')]
        );

        if ($user) {
            $user['role_slug'] = $user['role'] ?? 'teacher';
            $user['role_name'] = ($user['role_slug'] === 'super-admin') ? 'Super Administrator' : 'Teacher / Examiner';
        }

        return $user;
    }

    /**
     * Check if user has a specific permission
     */
    public static function hasPermission(string $permissionSlug): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }

        // Overview & dashboard views are open to all authenticated users
        if ($permissionSlug === 'dashboard.view' || $permissionSlug === 'teacher.dashboard') {
            return true;
        }

        $permissions = Session::get('permissions', []);
        return in_array('*', $permissions, true) || in_array($permissionSlug, $permissions, true);
    }

    /**
     * Check if user can perform an action (alias for hasPermission)
     */
    public static function can(string $permissionSlug): bool
    {
        return self::hasPermission($permissionSlug);
    }

    /**
     * Check if user is a Restricted Subuser
     */
    public static function isSubuser(): bool
    {
        return self::getRole() === 'subuser';
    }

    /**
     * Check if user has a specific role
     */
    public static function hasRole(string $roleSlug): bool
    {
        return self::getRole() === $roleSlug;
    }

    /**
     * Check if user has any of the given roles
     */
    public static function hasAnyRole(array $roleSlugs): bool
    {
        return in_array(self::getRole(), $roleSlugs, true);
    }
}
