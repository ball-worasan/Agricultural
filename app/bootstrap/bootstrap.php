<?php

declare(strict_types=1);

/**
 * Bootstrap: load core, configure env/runtime, resolve route, return rendering context.
 */

// -----------------------------------------------------------------------------
// Path constants
// -----------------------------------------------------------------------------
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

// -----------------------------------------------------------------------------
// Safe require helper
// -----------------------------------------------------------------------------
$requireFile = static function (string $path, string $label): void {
    if (!is_file($path)) {
        throw new RuntimeException($label . ' not found: ' . $path);
    }
    require_once $path;
};

// -----------------------------------------------------------------------------
// Load core (constants + helpers + database)
// -----------------------------------------------------------------------------
$requireFile(APP_PATH . '/config/constants.php', 'Constants file');
$requireFile(APP_PATH . '/includes/helpers.php', 'Helpers file');
$requireFile(APP_PATH . '/config/database.php', 'Database class');

// -----------------------------------------------------------------------------
// Runtime config
// -----------------------------------------------------------------------------
$configureRuntime = static function (): void {
    // error reporting
    $isProd = function_exists('app_is_production') ? app_is_production() : true;
    $debug = function_exists('app_debug_enabled') ? app_debug_enabled() : (!$isProd);

    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('display_errors', $debug ? '1' : '0');

    // app constants from env if available
    if (!defined('APP_NAME')) {
        $name = function_exists('app_env_string') ? app_env_string('APP_NAME', 'SirinatApp') : 'SirinatApp';
        define('APP_NAME', $name);
    }
    if (!defined('APP_TIMEZONE')) {
        $tz = function_exists('app_env_string') ? app_env_string('APP_TIMEZONE', 'Asia/Bangkok') : 'Asia/Bangkok';
        define('APP_TIMEZONE', $tz);
    }

    // locale (constants.php has APP_LOCALE const; keep it simple)
    if (!defined('APP_LOCALE')) {
        define('APP_LOCALE', 'th');
    }

    // session
    if (function_exists('app_session_start')) {
        app_session_start();
    }

    // timezone
    try {
        date_default_timezone_set(APP_TIMEZONE);
    } catch (Throwable $e) {
        date_default_timezone_set('UTC');
        if (function_exists('app_log')) {
            app_log('timezone_set_failed', ['requested' => APP_TIMEZONE, 'error' => $e->getMessage()]);
        }
    }

    // security headers
    try {
        if (function_exists('send_security_headers')) {
            send_security_headers();
        }
    } catch (Throwable $e) {
        if (function_exists('app_log')) {
            app_log('security_headers_error', ['error' => $e->getMessage()]);
        }
    }
};

$configureRuntime();

// -----------------------------------------------------------------------------
// Load routes
// -----------------------------------------------------------------------------
$routesPath = APP_PATH . '/config/routes.php';
$routes = require $routesPath;

if (!is_array($routes)) {
    throw new RuntimeException('Routes must return array: ' . $routesPath);
}

if (!isset($routes['home']) || !is_array($routes['home'])) {
    $routes['home'] = [
        'title' => 'เว็บไซต์',
        'view'  => 'home',
        'css'   => [],
        'js'    => [],
    ];
}

// -----------------------------------------------------------------------------
// Logout handling (early exit)
// -----------------------------------------------------------------------------
try {
    if (function_exists('is_logout_request') && is_logout_request()) {
        if (function_exists('handle_logout')) {
            handle_logout(); // should redirect/exit
        }

        // If handler missing, fail safe
        if (function_exists('flash')) {
            flash('error', 'ไม่สามารถออกจากระบบได้ (missing handler)');
        }
        if (function_exists('redirect')) {
            redirect('?page=home', 303);
        }
        exit;
    }
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('logout_error', ['error' => $e->getMessage()]);
    }
    if (function_exists('flash')) {
        flash('error', 'เกิดข้อผิดพลาดในการออกจากระบบ');
    }
    if (function_exists('redirect')) {
        redirect('?page=home', 303);
    }
    http_response_code(500);
    exit;
}

// -----------------------------------------------------------------------------
// Page resolve + guard
// -----------------------------------------------------------------------------
$requestedPage = $_GET['page'] ?? 'home';

$page = function_exists('resolve_page')
    ? resolve_page($requestedPage, $routes)
    : (is_string($requestedPage) && $requestedPage !== '' ? strtolower($requestedPage) : 'home');

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'home';
}

$route = $routes[$page];

// normalize route keys
$route['title'] = isset($route['title']) ? (string)$route['title'] : 'เว็บไซต์';
$route['view']  = isset($route['view']) ? (string)$route['view'] : 'home';
$route['css']   = (isset($route['css']) && is_array($route['css'])) ? $route['css'] : [];
$route['js']    = (isset($route['js']) && is_array($route['js'])) ? $route['js'] : [];

// auth/admin/guest guard
if (function_exists('guard_route')) {
    guard_route($route);
}

// -----------------------------------------------------------------------------
// Assets + viewFile
// -----------------------------------------------------------------------------
$title = $route['title'];

$pageCss = function_exists('build_page_css') ? build_page_css($route) : $route['css'];
$pageJs  = function_exists('build_page_js')  ? build_page_js($route)  : $route['js'];

$viewFile = APP_PATH . '/pages/' . $route['view'] . '.php';

return compact('page', 'route', 'viewFile', 'pageCss', 'pageJs', 'title', 'routes');
