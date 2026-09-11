<?php
/**
 * CSRF Protection
 * Token generation and validation
 */

class CSRF
{
    private const TOKEN_NAME = 'csrf_token';

    /**
     * Generate or get existing CSRF token
     */
    public static function token(): string
    {
        if (!Session::has(self::TOKEN_NAME)) {
            Session::set(self::TOKEN_NAME, bin2hex(random_bytes(32)));
        }
        return Session::get(self::TOKEN_NAME);
    }

    /**
     * Generate hidden input field
     */
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate CSRF token from request
     */
    public static function validate(?string $token = null): bool
    {
        $token = $token ?? ($_POST['csrf_token'] ?? '');
        $sessionToken = Session::get(self::TOKEN_NAME, '');

        if (empty($token) || empty($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Validate and throw on failure
     */
    public static function validateOrFail(?string $token = null): void
    {
        if (!self::validate($token)) {
            http_response_code(403);
            Session::flash('error', 'Invalid security token. Please try again.');
            
            $referer = $_SERVER['HTTP_REFERER'] ?? APP_URL;
            header('Location: ' . $referer);
            exit;
        }
    }

    /**
     * Regenerate token (after successful form submission)
     */
    public static function regenerate(): void
    {
        Session::set(self::TOKEN_NAME, bin2hex(random_bytes(32)));
    }
}
