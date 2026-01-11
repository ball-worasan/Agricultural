<?php

declare(strict_types=1);

// current page
$currentPage = (string)($page ?? ($_GET['page'] ?? 'home'));
$onHome = ($currentPage === 'home');

// auth state
$user = current_user();
$userId = 0;

if (is_array($user)) {
  // support multiple keys
  $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
}

$isAuthenticated = $userId > 0;
$isAdmin = $isAuthenticated && function_exists('is_admin') ? (bool)is_admin() : false;

// display name
$displayName = 'บัญชีของฉัน';
if ($isAuthenticated && is_array($user)) {
  $fullName = trim((string)($user['full_name'] ?? ''));
  $username = trim((string)($user['username'] ?? ''));
  $displayName = $fullName !== '' ? $fullName : ($username !== '' ? $username : 'บัญชีของฉัน');
}

// stable-ish ids (ไม่ต้องใส่ IP)
$seed = $currentPage . '|' . ($userId ?: 'guest');
$navInstanceId = 'nav-' . substr(hash('sha256', $seed), 0, 10);
$accountBtnId  = 'accountBtn-' . $navInstanceId;
$accountMenuId = 'accountMenu-' . $navInstanceId;

$menuClass = static function (string $itemPage, bool $highlight, string $activePage): string {
  $classes = ['menu-item'];
  if ($highlight) $classes[] = 'highlight';
  if ($itemPage === $activePage) $classes[] = 'is-active';
  return implode(' ', $classes);
};

// build menu lists
$authMenu = [
  ['href' => '?page=home', 'label' => 'รายการพื้นที่เกษตรให้เช่า', 'page' => 'home', 'highlight' => false],
];

if ($isAuthenticated) {
  if (!$isAdmin) {
    $authMenu[] = ['href' => '?page=my_properties', 'label' => 'พื้นที่ปล่อยเช่าของฉัน', 'page' => 'my_properties', 'highlight' => false];
    $authMenu[] = ['href' => '?page=history', 'label' => 'ประวัติการเช่าพื้นที่เกษตร', 'page' => 'history', 'highlight' => false];
  }

  $authMenu[] = ['href' => '?page=profile', 'label' => 'ข้อมูลสมาชิก', 'page' => 'profile', 'highlight' => false];

  if ($isAdmin) {
    $authMenu[] = ['href' => '?page=admin_dashboard', 'label' => 'แดชบอร์ดผู้ดูแล', 'page' => 'admin_dashboard', 'highlight' => true];
  }
}

$guestMenu = [
  ['href' => '?page=signin', 'label' => 'เข้าสู่ระบบ', 'page' => 'signin'],
  ['href' => '?page=signup', 'label' => 'สมัครสมาชิก', 'page' => 'signup'],
];

// csrf token for logout (expects csrf_token() exists)
$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<nav class="navbar" role="navigation" aria-label="แถบนำทางหลัก" data-nav-id="<?= e($navInstanceId); ?>">
  <div class="nav-inner">
    <div class="nav-left">
      <a href="?page=home" class="brand" aria-label="ไปหน้าหลัก">
        สิริณัฐ · พื้นที่การเกษตรให้เช่า
      </a>
    </div>

    <div class="nav-center">
      <?php if ($onHome): ?>
        <input
          type="search"
          id="globalSearch"
          class="nav-search"
          placeholder="ค้นหาแปลงเกษตรหรือทำเล..."
          aria-label="ค้นหารายการพื้นที่เกษตร"
          autocomplete="off" />
      <?php endif; ?>
    </div>

    <div class="nav-right">
      <div class="nav-account">
        <button
          type="button"
          class="account-btn"
          id="<?= e($accountBtnId); ?>"
          data-account-btn="true"
          aria-haspopup="true"
          aria-expanded="false"
          aria-controls="<?= e($accountMenuId); ?>"
          aria-label="<?= $isAuthenticated ? ('เมนูบัญชีของ ' . e($displayName)) : 'เมนูบัญชีสำหรับผู้ใช้ทั่วไป'; ?>">
          <?= e($isAuthenticated ? $displayName : 'เมนู'); ?>
        </button>

        <div
          class="account-menu"
          id="<?= e($accountMenuId); ?>"
          role="menu"
          hidden
          data-account-menu="true"
          data-menu-root="account">

          <?php if ($isAuthenticated): ?>
            <?php foreach ($authMenu as $item): ?>
              <?php
              $href = (string)($item['href'] ?? '?page=home');
              $label = (string)($item['label'] ?? 'เมนู');
              $itemPage = (string)($item['page'] ?? '');
              $highlight = (bool)($item['highlight'] ?? false);
              ?>
              <a
                class="<?= e($menuClass($itemPage, $highlight, $currentPage)); ?>"
                href="<?= e($href); ?>"
                role="menuitem"
                data-menu-item="true">
                <?= e($label); ?>
              </a>
            <?php endforeach; ?>

            <div class="menu-divider" role="separator" aria-hidden="true"></div>

            <!-- Logout: POST + CSRF -->
            <form
              method="post"
              action="?page=<?= e($currentPage); ?>"
              class="menu-item-form"
              data-menu-item="true"
              role="none">
              <input type="hidden" name="action" value="logout">
              <input type="hidden" name="_csrf" value="<?= e($csrf); ?>">

              <button type="submit" class="menu-item danger menu-btn" role="menuitem">
                ออกจากระบบ
              </button>
            </form>

          <?php else: ?>
            <?php foreach ($guestMenu as $item): ?>
              <?php
              $href = (string)($item['href'] ?? '?page=home');
              $label = (string)($item['label'] ?? 'เมนู');
              $itemPage = (string)($item['page'] ?? '');
              ?>
              <a
                class="<?= e($menuClass($itemPage, false, $currentPage)); ?>"
                href="<?= e($href); ?>"
                role="menuitem"
                data-menu-item="true">
                <?= e($label); ?>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</nav>