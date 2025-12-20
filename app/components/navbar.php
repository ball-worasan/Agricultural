<?php

declare(strict_types=1);

// กำหนดค่า current page
$currentPage = (string)($page ?? ($_GET['page'] ?? 'home'));
$onHome = ($currentPage === 'home');
$activePage = $currentPage;

// ตรวจสอบว่า user เป็น authenticated หรือไม่
$user = current_user();
$isAuthenticated = is_array($user) && !empty($user['id']);
$userId = $isAuthenticated ? (int)($user['id'] ?? 0) : 0;

// ตรวจสอบว่า user เป็น admin หรือไม่
$isAdmin = ($isAuthenticated && function_exists('is_admin')) ? (bool)is_admin() : false;

// กำหนดค่า display name
$displayName = 'บัญชีของฉัน';
if ($isAuthenticated) {
  $first = trim((string)($user['firstname'] ?? ''));
  $last  = trim((string)($user['lastname'] ?? ''));
  $fullName = trim($first . ' ' . $last);

  if ($fullName !== '') {
    $displayName = $fullName;
  } else {
    $uname = (string)($user['username'] ?? '');
    if ($uname !== '') $displayName = $uname;
  }
}

// กำหนดค่า unread notifications
$unreadCount = 0;

if ($isAuthenticated && $userId > 0) {
  try {
    // กำหนดค่า ttl ของ cache
    $ttlSeconds = 30;
    $now = time();

    $cacheKey = 'navbar_unread_cache_' . $userId;
    $cache = $_SESSION[$cacheKey] ?? null;

    $cacheValid =
      is_array($cache)
      && (int)($cache['user_id'] ?? 0) === $userId
      && isset($cache['count'], $cache['ts'])
      && ($now - (int)$cache['ts']) <= $ttlSeconds;

    if ($cacheValid) {
      $unreadCount = (int)$cache['count'];
    } else {
      // กำหนดค่า service แบบ lazy (เฉพาะตอนต้องใช้จริง)
      $svcFile = APP_PATH . '/includes/NotificationService.php';
      if (is_file($svcFile)) {
        require_once $svcFile;
      }

      if (class_exists('NotificationService') && method_exists('NotificationService', 'getUnreadCount')) {
        $unreadCount = (int) NotificationService::getUnreadCount($userId);
      } else {
        $unreadCount = 0;
      }

      $_SESSION[$cacheKey] = [
        'user_id' => $userId,
        'count'   => $unreadCount,
        'ts'      => $now,
      ];
    }
  } catch (Throwable $e) {
    if (function_exists('app_log')) {
      app_log('navbar_unread_count_failed', ['error' => $e->getMessage()]);
    }
    $unreadCount = 0;
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
    [
      'href' => '?page=notifications',
      'label' => 'การแจ้งเตือน' . ($unreadCount > 0 ? " ({$unreadCount})" : ''),
      'page' => 'notifications',
      'highlight' => ($unreadCount > 0),
    ],
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