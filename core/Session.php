<?php
/**
 * Secure Session Handler
 * Manages session lifecycle with security features
 */

class Session
{
    private static bool $started = false;

    /**
     * Start session with secure configuration
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        if (!headers_sent()) {
            // Secure session configuration
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', (string)(SESSION_LIFETIME * 60));

            // Set secure cookie if HTTPS
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            ini_set('session.cookie_secure', $isSecure ? '1' : '0');

            session_name(SESSION_NAME);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        self::$started = true;

        // Check session timeout
        self::checkTimeout();

        // Validate session fingerprint
        self::validateFingerprint();
    }

    /**
     * Check if session has timed out
     */
    private static function checkTimeout(): void
    {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > (SESSION_LIFETIME * 60)) {
                self::destroy();
                return;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Generate and validate session fingerprint
     */
    private static function validateFingerprint(): void
    {
        $fingerprint = self::generateFingerprint();

        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== $fingerprint) {
                // Possible session hijacking
                self::destroy();
                return;
            }
        } else {
            $_SESSION['fingerprint'] = $fingerprint;
        }
    }

    /**
     * Generate session fingerprint from client info
     */
    private static function generateFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $userAgent . '|ptm_salt_2024');
    }

    /**
     * Regenerate session ID (on login, privilege change)
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
            $_SESSION['fingerprint'] = self::generateFingerprint();
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            @session_destroy();
        }
        self::$started = false;
    }

    /**
     * Get session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove session value
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Check if session has key
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Flash message — set
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash_messages'][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Flash message — get and clear
     */
    public static function getFlash(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}
