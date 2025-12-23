<?php

declare(strict_types=1);

// กำหนด BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// กำหนด APP_PATH
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

// Load constants
$constantsFile = APP_PATH . '/config/constants.php';
if (!is_file($constantsFile)) {
    throw new RuntimeException('Constants file not found: ' . $constantsFile);
}
require_once $constantsFile;

// Load helpers
$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
    throw new RuntimeException('Helpers file not found: ' . $helpersFile);
}
require_once $helpersFile;

// Error reporting (หลัง crash shield แล้ว)
$isProduction = function_exists('app_is_production') ? app_is_production() : true;
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $isProduction ? '0' : '1');

// เริ่ม session
app_session_start();

// กำหนดค่าพื้นฐาน
if (!defined('APP_NAME')) define('APP_NAME', 'Agricultural');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Bangkok');

// ตั้งค่า timezone
try {
    date_default_timezone_set(APP_TIMEZONE);
} catch (Throwable $e) {
    date_default_timezone_set('UTC');
    if (function_exists('app_log')) {
        app_log('timezone_set_failed', ['requested' => APP_TIMEZONE, 'error' => $e->getMessage()]);
    }
}

// ตั้งค่า security headers
try {
    send_security_headers();
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('security_headers_error', ['error' => $e->getMessage()]);
    }
}

// โหลดไฟล์ routes
$routesFile = APP_PATH . '/config/routes.php';
if (!is_file($routesFile)) {
    throw new RuntimeException('Routes file not found: ' . $routesFile);
}
$routes = require $routesFile;
if (!is_array($routes)) {
    throw new RuntimeException('Routes must return array');
}

// จัดการการออกจากระบบ (CSRF-safe)
try {
    if (is_logout_request()) {
        if (!verify_csrf_token(request_csrf_token())) {
            flash('error', 'คำขอไม่ถูกต้อง (CSRF)');
            redirect('?page=home');
        }
        handle_logout(); // จะ redirect/exit เองตามของคุณ
    }
} catch (Throwable $e) {
    app_log('logout_error', ['error' => $e->getMessage()]);
    flash('error', 'เกิดข้อผิดพลาดในการออกจากระบบ');
    redirect('?page=home');
}

// แก้ไข + ป้องกัน route
$page = 'home';
$route = $routes['home'] ?? ['title' => 'เว็บไซต์', 'view' => 'home', 'css' => [], 'js' => []];

$page = resolve_page($_GET['page'] ?? 'home', $routes);

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'home';
}

$route = $routes[$page];

// ตรวจสอบและเพิ่ม js key ถ้าขาด
if (!isset($route['js'])) {
    $route['js'] = [];
}

guard_route($route);

// กำหนดค่า assets
$title  = $route['title'] ?? 'เว็บไซต์';
$pageCss = build_page_css($route);
$pageJs  = build_page_js($route);

// กำหนดค่า view
$viewFile = APP_PATH . '/pages/' . ($route['view'] ?? 'home') . '.php';

return compact('page', 'route', 'viewFile', 'pageCss', 'pageJs', 'title', 'routes');
