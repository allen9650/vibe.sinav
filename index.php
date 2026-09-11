<?php
/**
 * Marr Typing Competition System
 * Front Controller — All requests route through here
 */

// Prevent caching of authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Load configuration
require_once __DIR__ . '/config/app.php';

// Load core classes
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Session.php';
require_once CORE_PATH . '/CSRF.php';
require_once CORE_PATH . '/AuditLog.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/Middleware.php';
require_once CORE_PATH . '/Validator.php';
require_once CORE_PATH . '/TypingCalculator.php';
require_once CORE_PATH . '/RankingService.php';
require_once CORE_PATH . '/ResultService.php';
require_once CORE_PATH . '/ReportService.php';
require_once CORE_PATH . '/CertificateService.php';
require_once CORE_PATH . '/BackupService.php';
require_once CORE_PATH . '/QuestionService.php';
require_once CORE_PATH . '/AssessmentService.php';
require_once CORE_PATH . '/UserService.php';
require_once CORE_PATH . '/AnswerEvaluator.php';
require_once CORE_PATH . '/AssessmentResultService.php';
require_once CORE_PATH . '/helpers.php';

// Core class autoloader fallback
spl_autoload_register(function (string $class): void {
    $file = CORE_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Start session
Session::start();

// Load routes
$routes = require __DIR__ . '/routes/web.php';

// Determine requested page
$page = $_GET['page'] ?? ($_POST['page'] ?? '');

// Default page: redirect to dashboard if logged in, else login
if (empty($page)) {
    if (Session::isAuthenticated()) {
        if (Auth::isTeacher()) {
            redirect(url('teacher-dashboard'));
        } else {
            redirect(url('dashboard'));
        }
    } else {
        redirect(url('login'));
    }
}

// Check if route exists
if (!isset($routes[$page])) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    require VIEWS_PATH . '/errors/404.php';
    exit;
}

$route = $routes[$page];

// Apply middleware
switch ($route['middleware'] ?? null) {
    case 'auth':
        Middleware::requireAuth();
        break;
    case 'guest':
        Middleware::guestOnly();
        break;
}

// Check permissions
if (!empty($route['permission'])) {
    if (!Auth::hasPermission($route['permission'])) {
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require VIEWS_PATH . '/errors/403.php';
        exit;
    }
}

// Check roles
if (!empty($route['roles'])) {
    if (!Auth::hasAnyRole($route['roles'])) {
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require VIEWS_PATH . '/errors/403.php';
        exit;
    }
}

// Set page title
$pageTitle = $route['title'] ?? 'Typing Competition';

// Load the view
$viewFile = VIEWS_PATH . '/' . $route['view'];
if (!file_exists($viewFile)) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    require VIEWS_PATH . '/errors/404.php';
    exit;
}

// Determine layout
$layout = $route['layout'] ?? null;

if ($layout === 'admin') {
    // Buffer the view content for inclusion in layout
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require VIEWS_PATH . '/layouts/admin.php';
} elseif ($layout === 'auth') {
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require VIEWS_PATH . '/layouts/auth.php';
} else {
    // No layout (e.g., logout handler)
    require $viewFile;
}
