<?php

declare(strict_types=1);

// ----------------------------
// Bootstrap paths
// ----------------------------
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

require_once APP_PATH . '/includes/helpers.php';
app_session_start();

// ----------------------------
// Basic config (ย้ายไป .env ได้ทีหลัง)
// ----------------------------
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Agricultural');
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Bangkok');
}

// Security headers (เรียกก่อน output)
send_security_headers();

/**
 * Routing table
 */
$routes = [
    'home' => [
        'title' => 'พื้นที่การเกษตรให้เช่า',
        'view'  => 'home',
        'css'   => ['home.css'],
    ],
    'signin' => [
        'title'      => 'เข้าสู่ระบบ',
        'view'       => 'signin',
        'css'        => ['signin.css'],
        'guest_only' => true,
    ],
    'signup' => [
        'title'      => 'สมัครสมาชิก',
        'view'       => 'signup',
        'css'        => ['signup.css'],
        'guest_only' => true,
    ],
    'profile' => [
        'title' => 'โปรไฟล์สมาชิกเกษตร',
        'view'  => 'profile',
        'css'   => ['profile.css'],
        'auth'  => true,
    ],
    'detail' => [
        'title' => 'รายละเอียดแปลงเกษตร',
        'view'  => 'detail',
        'css'   => ['detail.css'],
    ],
    'payment' => [
        'title' => 'ชำระมัดจำเช่าพื้นที่เกษตร',
        'view'  => 'payment',
        'css'   => ['payment.css'],
        'auth'  => true,
    ],
    'full_payment' => [
        'title' => 'ชำระเงินเต็มจำนวน',
        'view'  => 'full_payment',
        'css'   => ['full_payment.css'],
        'auth'  => true,
    ],
    'history' => [
        'title' => 'ประวัติการเช่าพื้นที่เกษตร',
        'view'  => 'history',
        'css'   => ['history.css'],
        'auth'  => true,
    ],
    'my_properties' => [
        'title' => 'พื้นที่ปล่อยเช่าของฉัน',
        'view'  => 'my_properties',
        'css'   => ['my_properties.css'],
        'auth'  => true,
    ],
    'property_bookings' => [
        'title' => 'รายการจองพื้นที่',
        'view'  => 'property_bookings',
        'css'   => ['property_bookings.css'],
        'auth'  => true,
    ],
    'add_property' => [
        'title' => 'เพิ่มพื้นที่ปล่อยเช่า',
        'view'  => 'add_property',
        'css'   => ['add_property.css'],
        'auth'  => true,
    ],
    'edit_property' => [
        'title' => 'แก้ไขพื้นที่ปล่อยเช่า',
        'view'  => 'edit_property',
        'css'   => ['add_property.css'],
        'auth'  => true,
    ],
    'delete_property' => [
        'title' => 'ลบพื้นที่',
        'view'  => 'delete_property',
        'auth'  => true,
    ],
    'delete_property_image' => [
        'title' => 'ลบรูปภาพ',
        'view'  => 'delete_property_image',
        'auth'  => true,
    ],
    'admin_dashboard' => [
        'title' => 'แดชบอร์ดผู้ดูแลระบบ',
        'view'  => 'admin_dashboard',
        'css'   => ['admin_dashboard.css'],
        'auth'  => true,
        'admin' => true,
    ],
    'payment_verification' => [
        'title' => 'ตรวจสอบการชำระเงิน',
        'view'  => 'payment_verification',
        'css'   => ['admin_dashboard.css'],
        'auth'  => true,
        'admin' => true,
    ],
    'notifications' => [
        'title' => 'การแจ้งเตือน',
        'view'  => 'notifications',
        'css'   => ['history.css'],
        'auth'  => true,
    ],
    'reports' => [
        'title' => 'รายงานและสถิติ',
        'view'  => 'reports',
        'css'   => ['admin_dashboard.css'],
        'auth'  => true,
        'admin' => true,
    ],
    'forgot_password' => [
        'title' => 'ลืมรหัสผ่าน',
        'view'  => 'forgot_password',
        'css'   => ['signin.css'],
        'guest_only' => true,
    ],
    'reset_password' => [
        'title' => 'รีเซ็ตรหัสผ่าน',
        'view'  => 'reset_password',
        'css'   => ['signin.css'],
        'guest_only' => true,
    ],
    'advanced_search' => [
        'title' => 'ค้นหาขั้นสูง',
        'view'  => 'advanced_search',
        'css'   => ['home.css'],
    ],
    'view_contract' => [
        'title' => 'ดูสัญญา',
        'view'  => 'view_contract',
        'css'   => ['contract.css'],
        'auth'  => true,
    ],
];

// ----------------------------
// Logout handler (CSRF-safe)
// - แนะนำให้ logout ผ่าน POST: action=logout + csrf
// - รองรับ GET เดิมได้ แต่ต้องส่ง csrf ใน query (?logout=1&csrf=...)
// ----------------------------
if (is_logout_request()) {
    if (!verify_csrf_token(request_csrf_token())) {
        flash('error', 'คำขอไม่ถูกต้อง (CSRF)'); // กันคนยิงลิงก์ logout เล่น ๆ
        redirect('?page=home');
    }
    handle_logout();
}

// ----------------------------
// Resolve + guard route
// ----------------------------
$page = resolve_page($_GET['page'] ?? 'home', $routes);
$route = $routes[$page];

guard_route($route);

// ----------------------------
// Assets
// ----------------------------
$title = $route['title'] ?? 'เว็บไซต์';

$baseCss = [
    '/css/variables.css',
    '/css/utilities.css',
    '/css/style.css',
    '/css/navbar.css',
];

$pageCss = build_page_css($route);

// view file
$viewFile = APP_PATH . '/pages/' . ($route['view'] ?? 'home') . '.php';

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> · <?= e(APP_NAME); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    <?php foreach ($baseCss as $href): ?>
        <link rel="stylesheet" href="<?= e($href); ?>">
    <?php endforeach; ?>

    <?php foreach ($pageCss as $href): ?>
        <link rel="stylesheet" href="<?= e($href); ?>">
    <?php endforeach; ?>

    <script src="/js/utilities.js" defer></script>
</head>

<body>
    <?php include APP_PATH . '/components/navbar.php'; ?>

    <main class="page-root">
        <?php render_flash_popup(); ?>

        <?php if (is_file($viewFile)): ?>
            <?php $currentPage = $page;
            include $viewFile; ?>
        <?php else: ?>
            <?php http_response_code(404); ?>
            <section class="container">
                <h1>ไม่พบหน้าที่ต้องการ (404)</h1>
                <p>หน้าที่คุณเรียกอาจถูกลบหรือย้ายไปแล้ว</p>
                <p><a href="?page=home">กลับหน้าหลัก</a></p>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>