<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/includes/crash_shield.php';

$ctx = require __DIR__ . '/../app/bootstrap/bootstrap.php';

$page     = (string)($ctx['page'] ?? 'home');
$route    = (array)($ctx['route'] ?? []);
$viewFile = (string)($ctx['viewFile'] ?? '');
$pageCss  = (array)($ctx['pageCss'] ?? []);
$pageJs   = (array)($ctx['pageJs'] ?? []);
$title    = (string)($ctx['title'] ?? 'Untitled');

/**
 * Normalize + dedupe asset lists
 */
$normalizeAssets = static function (array $assets): array {
  $assets = array_map('strval', $assets);
  $assets = array_map('trim', $assets);
  $assets = array_values(array_filter($assets, static fn($v) => $v !== ''));

  // ป้องกันแอบยัด scheme แปลก ๆ (เช่น javascript:, data:)
  $assets = array_values(array_filter($assets, static function (string $v): bool {
    return str_starts_with($v, '/') || str_starts_with($v, 'http://') || str_starts_with($v, 'https://');
  }));

  return array_values(array_unique($assets));
};

$baseCss = [
  '/css/variables.css',
  '/css/base.css',
  '/css/navbar.css',
];

$baseJs = [
  '/js/app.core.js',
  '/js/app.flash.js',
  '/js/app.navbar.js',
  '/js/app.js',
];

$pageCss = $normalizeAssets($pageCss);
$pageJs  = $normalizeAssets($pageJs);

$renderCssLinks = static function (array $hrefs): void {
  foreach ($hrefs as $href) {
    echo '<link rel="stylesheet" href="' . e($href) . '">' . PHP_EOL;
  }
};

$renderDeferredScripts = static function (array $srcs, string $nonce = ''): void {
  // ตอนนี้ project ใช้ nonce เฉพาะ inline script
  // ถ้าต้องการใส่ nonce ให้ external script ด้วย: เพิ่ม nonce attr ที่ tag ได้เลย
  foreach ($srcs as $src) {
    echo '<script src="' . e($src) . '" defer></script>' . PHP_EOL;
  }
};

$cspNonce = function_exists('csp_nonce') ? (string)csp_nonce() : '';
$lang = defined('APP_LOCALE') ? (APP_LOCALE === 'th' ? 'th' : (string)APP_LOCALE) : 'th';

?>
<!DOCTYPE html>
<html lang="<?= e($lang); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title); ?> · <?= e(APP_NAME); ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Base CSS -->
  <?php $renderCssLinks($baseCss); ?>

  <!-- Page CSS -->
  <?php $renderCssLinks($pageCss); ?>

  <!-- Inline bootstrap data -->
  <script nonce="<?= e($cspNonce); ?>">
    window.APP = {
      page: <?= json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    };
  </script>

  <!-- Base JS -->
  <?php $renderDeferredScripts($baseJs, $cspNonce); ?>

  <!-- Page JS -->
  <?php $renderDeferredScripts($pageJs, $cspNonce); ?>
</head>

<body>
  <?php
  // Navbar (กันตาย ไม่ให้ทั้งหน้าพังเพราะ component เดียว)
  try {
    include APP_PATH . '/components/navbar.php';
  } catch (Throwable $e) {
    app_log('navbar_error', ['error' => $e->getMessage()]);
  }
  ?>

  <main class="page-root">
    <?php render_flash_popup(); ?>

    <?php if (is_file($viewFile)): ?>
      <?php
      try {
        $currentPage = $page;
        include $viewFile;
      } catch (Throwable $e) {
        app_log('view_error', ['view_file' => $viewFile, 'error' => $e->getMessage()]);
        http_response_code(500);
      ?>
        <section class="error-section container" role="alert" aria-live="polite">
          <h1 class="error-title">เกิดข้อผิดพลาด</h1>
          <p class="error-text">ไม่สามารถโหลดหน้านี้ได้ กรุณาลองใหม่อีกครั้ง</p>
          <div class="error-actions">
            <a href="?page=home" class="btn btn-primary">กลับหน้าหลัก</a>
          </div>
        </section>
      <?php } ?>
    <?php else: ?>
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
if (ob_get_level() > 0) {
  @ob_end_flush();
}
