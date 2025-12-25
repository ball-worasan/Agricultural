<?php

declare(strict_types=1);

// ป้องกันการดักจับ error ที่ไม่คาดคิด
require_once __DIR__ . '/../app/includes/crash_shield.php';

// โหลด bootstrap ของโปรเจกต์
$ctx = require __DIR__ . '/../app/bootstrap/bootstrap.php';

// แตกตัวแปรจาก ctx
$page = (string)$ctx['page'];
$route = (array)$ctx['route'];
$viewFile = (string)$ctx['viewFile'];
$pageCss = (array)$ctx['pageCss'];
$pageJs = (array)$ctx['pageJs'];
$title = (string)$ctx['title'];

// กำหนด CSS พื้นฐาน
$baseCss = [
  '/css/variables.css',
  '/css/base.css',
  '/css/navbar.css',
];

// กำหนด JS พื้นฐาน
$baseJs = [
  '/js/app.core.js',
  '/js/app.flash.js',
  '/js/app.navbar.js',
  '/js/app.js',
];

$pageCss = array_values(array_unique($pageCss));
$pageJs = array_values(array_unique($pageJs));

$cspNonce = function_exists('csp_nonce') ? csp_nonce() : '';

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

  <!-- โหลด CSS พื้นฐาน -->
  <?php foreach ($baseCss as $href): ?>
    <link rel="stylesheet" href="<?= e($href); ?>">
  <?php endforeach; ?>

  <!-- โหลด CSS ของหน้านั้นๆ -->
  <?php foreach ($pageCss as $href): ?>
    <link rel="stylesheet" href="<?= e($href); ?>">
  <?php endforeach; ?>

  <script nonce="<?= e($cspNonce); ?>">
    window.APP = {
      page: <?= json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    };
  </script>

  <!-- โหลด JS พื้นฐาน (ต้องพร้อมก่อนสคริปต์ของหน้า) -->
  <?php foreach ($baseJs as $src): ?>
    <script src="<?= e($src); ?>" defer></script>
  <?php endforeach; ?>

  <!-- โหลด JS ของหน้านั้นๆ (defer เพื่อใช้ util จาก base JS) -->
  <?php foreach ($pageJs as $src): ?>
    <script src="<?= e($src); ?>" defer></script>
  <?php endforeach; ?>

</head>

<body>
  <?php
  // แสดง navbar
  try {
    include APP_PATH . '/components/navbar.php';
  } catch (Throwable $e) {
    app_log('navbar_error', ['error' => $e->getMessage()]);
  }
  ?>

  <main class="page-root">
    <!-- แสดง flash popup -->
    <?php render_flash_popup(); ?>

    <!-- แสดง content ของหน้านั้นๆ -->
    <?php if (is_file($viewFile)): ?>
      <?php
      try {
        $currentPage = $page;
        include $viewFile;
      } catch (Throwable $e) {
        app_log('view_error', ['view_file' => $viewFile, 'error' => $e->getMessage()]);
        http_response_code(500);
      ?>

        <!-- แสดง error ของหน้านั้นๆ -->
        <section class="error-section container" role="alert" aria-live="polite">
          <h1 class="error-title">เกิดข้อผิดพลาด</h1>
          <p class="error-text">ไม่สามารถโหลดหน้านี้ได้ กรุณาลองใหม่อีกครั้ง</p>
          <div class="error-actions">
            <a href="?page=home" class="btn btn-primary">กลับหน้าหลัก</a>
          </div>
        </section>
      <?php } ?>

    <?php else: ?>
      <!-- แสดง error ของหน้านั้นๆ -->
      <?php http_response_code(404); ?>
      <section class="error-section container" role="alert" aria-live="polite">
        <h1 class="error-title">ไม่พบหน้าที่ต้องการ (404)</h1>
        <p class="error-text">หน้าที่คุณเรียกอาจถูกลบหรือย้ายไปแล้ว</p>
        <div class="error-actions">
          <a href="?page=home" class="btn btn-primary">กลับหน้าหลัก</a>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>

</html>

<?php
// ล้าง output buffer
if (ob_get_level() > 0) {
  @ob_end_flush();
}
