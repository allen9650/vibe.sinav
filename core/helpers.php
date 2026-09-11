<?php
/**
 * Helper Functions
 * Common utility functions used across the application
 */

/**
 * Escape output for XSS protection
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get dynamic base URL supporting both Apache subdirectories and PHP built-in server
 */
function getBaseUrl(): string
{
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : str_replace('\\', '/', $scriptDir);
        return rtrim("{$protocol}://{$host}{$scriptDir}", '/');
    }
    return rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/MS%20Typing', '/');
}

/**
 * Get base URL
 */
function url(string $path = ''): string
{
    return getBaseUrl() . '/index.php' . ($path ? '?page=' . ltrim($path, '/') : '');
}

/**
 * Get asset URL
 */
function asset(string $path): string
{
    return getBaseUrl() . '/public/assets/' . ltrim($path, '/');
}

/**
 * Get upload URL
 */
function upload(string $path): string
{
    return getBaseUrl() . '/public/uploads/' . ltrim($path, '/');
}

/**
 * Get logo URL or null if none
 */
function getLogoUrl(): ?string
{
    static $resolved = false;
    static $url = null;

    if ($resolved) {
        return $url;
    }

    try {
        $logoFile = function_exists('getSetting') ? getSetting('institute_logo', '') : '';
        if (!empty($logoFile) && file_exists(UPLOADS_PATH . '/' . $logoFile)) {
            $url = upload($logoFile);
        }
    } catch (Throwable $e) {
        $url = null;
    }

    $resolved = true;
    return $url;
}

/**
 * Get favicon link tags for HTML head
 */
function getFaviconTag(): string
{
    $logoUrl = getLogoUrl();
    if ($logoUrl) {
        $ext = strtolower(pathinfo(parse_url($logoUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $type = match($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            default => 'image/png'
        };
        $esc = e($logoUrl);
        return '<link rel="icon" type="' . $type . '" href="' . $esc . '">' . "\n"
             . '    <link rel="shortcut icon" href="' . $esc . '">' . "\n"
             . '    <link rel="apple-touch-icon" href="' . $esc . '">';
    }
    return '';
}

/**
 * Redirect to a URL
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Redirect to a page
 */
function redirectTo(string $page): never
{
    redirect(url($page));
}

/**
 * Get system setting from database
 */
function getSetting(string $key, mixed $default = '', bool $forceRefresh = false): string
{
    static $cache = [];
    $strDefault = (string)$default;

    if (!$forceRefresh && isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $value = Database::fetchColumn(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        $cache[$key] = ($value !== false && $value !== null) ? (string)$value : $strDefault;
    } catch (Exception $e) {
        $cache[$key] = $strDefault;
    }

    return $cache[$key];
}

/**
 * Update system setting
 */
function updateSetting(string $key, string $value): void
{
    Database::update(
        'system_settings',
        ['setting_value' => $value, 'updated_by' => Session::get('user_id')],
        'setting_key = ?',
        [$key]
    );

    // Invalidate and refresh cache
    getSetting($key, $value, true);
}

/**
 * Format date/time
 */
function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (!$date) return '—';
    return date($format, strtotime($date));
}

/**
 * Format date/time with time
 */
function formatDateTime(?string $date, string $format = 'd M Y, h:i A'): string
{
    if (!$date) return '—';
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 */
function timeAgo(?string $datetime): string
{
    if (!$datetime) return '—';

    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Generate breadcrumb HTML
 */
function breadcrumb(array $items): string
{
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">';
    $count = count($items);
    $i = 0;

    foreach ($items as $label => $link) {
        $i++;
        if ($i === $count) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . e($label) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . e($link) . '">' . e($label) . '</a></li>';
        }
    }

    $html .= '</ol></nav>';
    return $html;
}

/**
 * Get client IP address
 */
function getClientIP(): string
{
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    // If forwarded, take the first IP
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return $ip;
}

/**
 * Check if current page matches
 */
function isActivePage(string $page): bool
{
    $currentPage = $_GET['page'] ?? 'dashboard';
    if ($currentPage === $page) {
        return true;
    }
    if (str_starts_with($currentPage, $page . '-')) {
        return true;
    }
    return false;
}

/**
 * Get active class for navigation
 */
function activeClass(string $page): string
{
    return isActivePage($page) ? 'active' : '';
}
