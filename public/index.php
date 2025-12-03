<?php

declare(strict_types=1);

// กำหนด BASE_PATH / APP_PATH ให้ทั้งโปรเจกต์ใช้ร่วมกัน
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

// Helpers หลักของทั้งระบบ
require_once APP_PATH . '/includes/helpers.php';

// เปิด session ผ่าน helper (กันเรียกซ้ำ)
app_session_start();

/**
 * Routing table: กำหนด meta แต่ละหน้า
 * - title: ชื่อหน้า (สำหรับ <title>)
 * - view:  ชื่อไฟล์ใน app/pages/{view}.php
 * - css:   รายการไฟล์ CSS เฉพาะหน้าที่ต้องโหลดเพิ่ม
 * - auth:  true ถ้าต้องล็อกอินก่อน
 * - admin: true ถ้าเฉพาะ admin เท่านั้น
 * - guest_only: true ถ้าหน้านี้สำหรับคนที่ยังไม่ล็อกอิน (เช่น signin/signup)
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
        'css'   => ['add_property.css'], // ใช้ style เดียวกับ add_property
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
];

// อ่าน page จาก query string + sanitize + whitelist
$pageParam = isset($_GET['page']) ? (string) $_GET['page'] : 'home';
// อนุญาตเฉพาะ a-z, 0-9, และ _
if (!preg_match('/^[a-z0-9_]+$/', $pageParam)) {
    $pageParam = 'home';
}

$page = array_key_exists($pageParam, $routes) ? $pageParam : 'home';
$route = $routes[$page];

// จัดการ logout แบบรวมศูนย์
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    // ล้างข้อมูล session ให้หมด
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
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

    session_destroy();

    // log ไว้เผื่อ debug ย้อนหลัง
    app_log('user_logout', [
        'ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
        'page' => $page,
    ]);

    // กลับหน้า home
    redirect('?page=home');
}

// Guard: ถ้าหน้านี้ต้องล็อกอิน แต่ยังไม่ล็อกอิน → เด้งไป signin
if (!empty($route['auth']) && !is_authenticated()) {
    flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าหน้านี้');
    store_old_input($_GET);
    redirect('?page=signin');
}

// Guard: ถ้าเป็นหน้า guest_only แต่ user ล็อกอินแล้ว → เด้งกลับ home
if (!empty($route['guest_only']) && is_authenticated()) {
    redirect('?page=home');
}

// Guard: ถ้าหน้านี้เฉพาะ admin
if (!empty($route['admin']) && !is_admin()) {
    flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('?page=home');
}

// กำหนด title
$title = isset($route['title']) ? $route['title'] : 'เว็บไซต์';

// base CSS ทุกหน้าต้องใช้
$baseCss = [
    '/css/variables.css',
    '/css/utilities.css',
    '/css/style.css',
    '/css/navbar.css',
];

// CSS เฉพาะหน้า
$pageCss = [];
if (!empty($route['css']) && is_array($route['css'])) {
    foreach ($route['css'] as $cssFile) {
        $pageCss[] = '/css/' . ltrim($cssFile, '/');
    }
}

// path ไฟล์ view
$viewFile = APP_PATH . '/pages/' . $route['view'] . '.php';

// flash message (อ่านครั้งเดียวหมด)
$flashSuccess = flash('success');
$flashError   = flash('error');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> · ศิรินาถ</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap"
        rel="stylesheet">

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

        <?php
        if (is_file($viewFile)) {
            $currentPage = $page;
            include $viewFile;
        } else {
            http_response_code(404);
        ?>
            <section class="container">
                <h1>ไม่พบหน้าที่ต้องการ (404)</h1>
                <p>หน้าที่คุณเรียกอาจถูกลบหรือย้ายไปแล้ว</p>
                <p><a href="?page=home">กลับหน้าหลัก</a></p>
            </section>
        <?php
        }
        ?>
    </main>

</body>

</html>