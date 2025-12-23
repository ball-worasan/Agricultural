<?php

declare(strict_types=1);

// กำหนดค่า current page
$currentPage = (string)($page ?? ($_GET['page'] ?? 'home'));
$onHome = ($currentPage === 'home');
$activePage = $currentPage;

// ตรวจสอบว่า user เป็น authenticated หรือไม่
$user = current_user();
$userId = is_array($user) ? (int)($user['user_id'] ?? $user['id'] ?? 0) : 0;
$isAuthenticated = $userId > 0;

// ตรวจสอบว่า user เป็น admin หรือไม่
$isAdmin = ($isAuthenticated && function_exists('is_admin')) ? (bool)is_admin() : false;

// กำหนดค่า display name
$displayName = 'บัญชีของฉัน';
if ($isAuthenticated) {
  $fullName = trim((string)($user['full_name'] ?? ''));

  if ($fullName !== '') {
    $displayName = $fullName;
  } else {
    $uname = (string)($user['username'] ?? '');
    if ($uname !== '') $displayName = $uname;
  }
}

// กำหนดค่า instance ids
$navInstanceId = 'nav-' . substr(md5($currentPage . '|' . ($userId ?: 'guest') . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 8);
$accountBtnId  = 'accountBtn-' . $navInstanceId;
$accountMenuId = 'accountMenu-' . $navInstanceId;

// กำหนดค่า menu items
$authMenuItems = [];
$guestMenuItems = [];

if ($isAuthenticated) {
  $authMenuItems = [
    ['href' => '?page=home',          'label' => 'รายการพื้นที่เกษตรให้เช่า',    'page' => 'home',          'highlight' => false],
    ['href' => '?page=my_properties', 'label' => 'พื้นที่ปล่อยเช่าของฉัน',      'page' => 'my_properties', 'highlight' => false],
    ['href' => '?page=history',       'label' => 'ประวัติการเช่าพื้นที่เกษตร', 'page' => 'history',       'highlight' => false],
    ['href' => '?page=profile', 'label' => 'ข้อมูลสมาชิก', 'page' => 'profile', 'highlight' => false],
  ];

  if ($isAdmin) {
    $authMenuItems[] = [
      'href' => '?page=admin_dashboard',
      'label' => 'แดชบอร์ดผู้ดูแล',
      'page' => 'admin_dashboard',
      'highlight' => true,
    ];
  }
} else {
  $guestMenuItems = [
    ['href' => '?page=signin', 'label' => 'เข้าสู่ระบบ',  'page' => 'signin'],
    ['href' => '?page=signup', 'label' => 'สมัครสมาชิก', 'page' => 'signup'],
  ];
}

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
        <?php if ($isAuthenticated): ?>
          <button
            type="button"
            class="account-btn"
            id="<?= e($accountBtnId); ?>"
            data-account-btn="true"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="<?= e($accountMenuId); ?>"
            aria-label="เมนูบัญชีของ <?= e($displayName); ?>">
            <?= e($displayName); ?>
          </button>

          <div
            class="account-menu"
            id="<?= e($accountMenuId); ?>"
            role="menu"
            hidden
            data-account-menu="true"
            data-menu-root="account">
            <?php foreach ($authMenuItems as $item): ?>
              <?php
              $isActive = (($item['page'] ?? '') === $activePage);
              $classes = ['menu-item'];
              if (!empty($item['highlight'])) $classes[] = 'highlight';
              if ($isActive) $classes[] = 'is-active';
              ?>
              <a
                class="<?= e(implode(' ', $classes)); ?>"
                href="<?= e((string)($item['href'] ?? '?page=home')); ?>"
                role="menuitem"
                data-menu-item="true">
                <?= e((string)($item['label'] ?? 'เมนู')); ?>
              </a>
            <?php endforeach; ?>

            <!-- Logout: POST + CSRF -->
            <form
              method="post"
              action="?page=<?= e($currentPage); ?>"
              class="menu-item-form"
              data-menu-item="true"
              role="none">
              <input type="hidden" name="action" value="logout">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

              <button type="submit" class="menu-item danger menu-btn" role="menuitem">
                ออกจากระบบ
              </button>
            </form>
          </div>

        <?php else: ?>
          <button
            type="button"
            class="account-btn"
            id="<?= e($accountBtnId); ?>"
            data-account-btn="true"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="<?= e($accountMenuId); ?>"
            aria-label="เมนูบัญชีสำหรับผู้ใช้ทั่วไป">
            เมนู
          </button>

          <div
            class="account-menu"
            id="<?= e($accountMenuId); ?>"
            role="menu"
            hidden
            data-account-menu="true"
            data-menu-root="account">
            <?php foreach ($guestMenuItems as $item): ?>
              <?php
              $isActive = (($item['page'] ?? '') === $activePage);
              $classes = ['menu-item'];
              if ($isActive) $classes[] = 'is-active';
              ?>
              <a
                class="<?= e(implode(' ', $classes)); ?>"
                href="<?= e((string)($item['href'] ?? '?page=home')); ?>"
                role="menuitem"
                data-menu-item="true">
                <?= e((string)($item['label'] ?? 'เมนู')); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>