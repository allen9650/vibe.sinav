<?php
/**
 * Application Configuration
 * Central configuration constants and settings
 */

// Load environment
require_once __DIR__ . '/env.php';
loadEnv(dirname(__DIR__) . '/.env');

// Application
define('APP_NAME', env('APP_NAME', 'vibe.Sınav'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_URL', env('APP_URL', 'http://localhost/ptmtest'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Karachi'));

// Paths
define('BASE_PATH', dirname(__DIR__));
define('ROOT_PATH', BASE_PATH);
define('CONFIG_PATH', BASE_PATH . '/config');
define('CORE_PATH', BASE_PATH . '/core');
define('VIEWS_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

// Session
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 30)); // minutes
define('SESSION_NAME', env('SESSION_NAME', 'ptm_session'));

// Security
define('LOGIN_MAX_ATTEMPTS', (int) env('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int) env('LOGIN_LOCKOUT_MINUTES', 15));
define('PASSWORD_MIN_LENGTH', (int) env('PASSWORD_MIN_LENGTH', 8));

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting based on environment
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/error.log');
}

// Institute defaults
define('INSTITUTE_NAME_DEFAULT', 'vibe.Sınav');
define('SYSTEM_NAME_DEFAULT', 'vibe.Sınav');
define('TIMEZONE_DEFAULT', 'Asia/Karachi');
