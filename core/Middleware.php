<?php
/**
 * Middleware
 * Route-level authentication and authorization guards
 */

class Middleware
{
    /**
     * Require authentication — redirect to login if not authenticated
     */
    public static function requireAuth(): void
    {
        if (!Session::isAuthenticated()) {
            Session::flash('warning', 'Please log in to access this page.');
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }

        // Check if password change is required
        if (Session::get('force_password_change') === true) {
            $currentPage = $_GET['page'] ?? '';
            if ($currentPage !== 'change-password' && $currentPage !== 'logout') {
                Session::flash('warning', 'You must change your password before continuing.');
                header('Location: ' . APP_URL . '/index.php?page=change-password');
                exit;
            }
        }
    }

    /**
     * Require a specific permission
     */
    public static function requirePermission(string $permissionSlug): void
    {
        self::requireAuth();

        if (!Auth::hasPermission($permissionSlug)) {
            self::forbidden();
        }
    }

    /**
     * Require a specific role
     */
    public static function requireRole(string $roleSlug): void
    {
        self::requireAuth();

        if (!Auth::hasRole($roleSlug)) {
            self::forbidden();
        }
    }

    /**
     * Require any of the given roles
     */
    public static function requireAnyRole(array $roleSlugs): void
    {
        self::requireAuth();

        if (!Auth::hasAnyRole($roleSlugs)) {
            self::forbidden();
        }
    }

    /**
     * Guest only — redirect to dashboard if authenticated
     */
    public static function guestOnly(): void
    {
        if (Session::isAuthenticated()) {
            header('Location: ' . APP_URL . '/index.php?page=dashboard');
            exit;
        }
    }

    /**
     * Handle forbidden access
     */
    private static function forbidden(): void
    {
        http_response_code(403);
        require VIEWS_PATH . '/errors/403.php';
        exit;
    }

    /**
     * Validate CSRF on POST requests
     */
    public static function validateCSRF(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::validateOrFail();
        }
    }
}
