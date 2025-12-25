<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 2));
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('detail_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('detail_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$notificationServiceFile = APP_PATH . '/includes/NotificationService.php';
if (!is_file($notificationServiceFile)) {
  app_log('detail_notification_service_missing', ['file' => $notificationServiceFile]);
  // เดินต่อหากไม่มี notification service
}

require_once $databaseFile;
require_once $helpersFile;
if (is_file($notificationServiceFile)) {
  require_once $notificationServiceFile;
}

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('detail_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------

// ตรวจสอบรหัสพื้นที่
// ----------------------------
$id = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]) ?? 0);
if ($id <= 0) {
  flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการ');
  redirect('?page=home');
}

// ----------------------------
// Helper functions
// ----------------------------
$fetchArea = static function (int $areaId): ?array {
  try {
    $row = Database::fetchOne(
      'SELECT ra.area_id, ra.user_id, ra.area_name, ra.price_per_year, ra.deposit_percent, ra.area_size, ra.area_status,
              d.district_name, p.province_name
       FROM rental_area ra
       JOIN district d ON ra.district_id = d.district_id
       JOIN province p ON d.province_id = p.province_id
       WHERE ra.area_id = ?
       LIMIT 1',
      [$areaId]
    );
    return $row ?: null;
  } catch (Throwable $e) {
    app_log('property_detail_fetch_error', [
      'area_id' => $areaId,
      'error'   => $e->getMessage(),
      'trace'   => $e->getTraceAsString(),
    ]);
    return null;
  }
};

$fetchAreaImages = static function (int $areaId): array {
  try {
    $images = Database::fetchAll(
      'SELECT image_url FROM area_image WHERE area_id = ? ORDER BY image_id',
      [$areaId]
    );
    $urls = array_values(array_filter(array_map(
      static fn($v) => is_string($v) ? trim($v) : '',
      array_column($images, 'image_url')
    )));
    return $urls;
  } catch (Throwable $e) {
    app_log('property_detail_fetch_images_error', [
      'area_id' => $areaId,
      'error'   => $e->getMessage(),
    ]);
    return [];
  }
};

$fetchUserPhone = static function (int $userId): ?string {
  try {
    $userRow = Database::fetchOne(
      'SELECT phone FROM users WHERE user_id = ? LIMIT 1',
      [$userId]
    );
    $phone = $userRow['phone'] ?? null;
    return is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
  } catch (Throwable $e) {
    app_log('property_detail_fetch_user_phone_error', [
      'user_id' => $userId,
      'error'   => $e->getMessage(),
    ]);
    return null;
  }
};

// ----------------------------
// ดึงข้อมูลพื้นที่แบบกันพลาด
// ----------------------------
$item = $fetchArea($id);

if (!$item) {
  flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการ');
  redirect('?page=home');
}

// ----------------------------
// ดึงรูปพื้นที่แบบกันพลาด
// ----------------------------
$imageUrls = $fetchAreaImages($id);

// ถ้ายังไม่มีให้ใช้ SVG placeholder
if (empty($imageUrls)) {
  $svgPlaceholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="24"%3ENo Image%3C/text%3E%3C/svg%3E';
  $imageUrls = [$svgPlaceholder];
}

// กำหนดสถานะ
$statusText = [
  'available'   => 'พร้อมให้เช่า',
  'booked'      => 'ติดจอง',
  'unavailable' => 'ปิดให้เช่า',
];

$statusClass = [
  'available'   => 'status-available',
  'booked'      => 'status-booked',
  'unavailable' => 'status-unavailable',
];

$rawStatus = (string)($item['area_status'] ?? 'available');
// รองรับกรณีสถานะเป็นข้อความไทยในบางชุดข้อมูล
if (!array_key_exists($rawStatus, $statusText)) {
  $rawLower = mb_strtolower($rawStatus);
  if (mb_strpos($rawLower, 'จอง') !== false) {
    $rawStatus = 'booked';
  } elseif (mb_strpos($rawLower, 'พร้อม') !== false) {
    $rawStatus = 'available';
  } elseif (mb_strpos($rawLower, 'ปิด') !== false) {
    $rawStatus = 'unavailable';
  } else {
    $rawStatus = 'available';
  }
}
$currentStatus = $rawStatus;

// คำนวณมัดจำ / ราคา
$priceRaw       = (float) ($item['price_per_year'] ?? 0);
$depositPercent = (float) ($item['deposit_percent'] ?? 0);
$depositRaw     = $priceRaw * $depositPercent / 100.0;
$deposit        = number_format($depositRaw, 2);
$priceFormatted = number_format($priceRaw, 2);

// ตรวจสอบว่าเป็นเจ้าของหรือไม่ + เตรียมข้อมูล user ที่ล็อกอิน
$isOwner       = false;
$isAdmin       = false;
$loggedInUser  = current_user();
$userFullName  = 'ไม่ระบุ';
$userPhoneText = 'ไม่ระบุ';

if ($loggedInUser !== null) {
  $currentUserId = (int) ($loggedInUser['user_id'] ?? $loggedInUser['id'] ?? 0);
  $ownerId       = (int) ($item['user_id'] ?? 0);
  $isOwner       = $currentUserId > 0 && $currentUserId === $ownerId;
  $userRole      = (int) ($loggedInUser['role'] ?? 0);
  $isAdmin       = ($userRole === ROLE_ADMIN);

  $fullName = (string) ($loggedInUser['full_name'] ?? '');
  $userFullName = $fullName !== '' ? $fullName : 'ไม่ระบุ';

  $phoneFromSession = $loggedInUser['phone'] ?? null;
  if (is_string($phoneFromSession) && trim($phoneFromSession) !== '') {
    $userPhoneText = trim($phoneFromSession);
  } elseif ($currentUserId > 0) {
    $fromDb = $fetchUserPhone($currentUserId);
    if ($fromDb !== null) {
      $userPhoneText = $fromDb;
    }
  }
}

$titleText    = (string) ($item['area_name'] ?? '');
$locationText = trim((string) ($item['district_name'] ?? ''));
if (!empty($item['province_name'])) {
  $locationText .= ($locationText !== '' ? ', ' : '') . (string)$item['province_name'];
}

// ขนาดพื้นที่ แสดงเป็นทศนิยมไร่
$areaSizeLabel = number_format((float)($item['area_size'] ?? 0), 2) . ' ไร่';
$descText = 'ไม่มีรายละเอียดเพิ่มเติม';

?>
<div
  class="detail-container compact"
  data-images='<?= e(json_encode($imageUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
  data-area-id="<?= (int)$id; ?>"
  data-status="<?= e($currentStatus); ?>"
  data-is-admin="<?= $isAdmin ? '1' : '0'; ?>">
  <div class="detail-wrapper">
    <div class="detail-topbar">
      <a href="?page=home" class="back-button minimal" aria-label="กลับหน้ารายการ">ย้อนกลับ</a>
      <div class="topbar-right">
        <h1 class="detail-title"><?= e($titleText); ?></h1>
        <?php if ($locationText !== ''): ?>
          <span class="meta-location">📍 <?= e($locationText); ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="detail-content">
      <div class="detail-left">
        <div class="image-gallery">
          <div class="main-image-wrapper">
            <img
              data-src="<?= e($imageUrls[0]); ?>"
              alt="<?= e($titleText !== '' ? $titleText : 'ภาพพื้นที่'); ?>"
              id="mainImage"
              class="main-image"
              loading="lazy"
              style="background: var(--skeleton-bg);">

            <?php if (count($imageUrls) > 1): ?>
              <button type="button" class="gallery-nav prev js-gallery-nav" data-direction="-1" aria-label="รูปก่อนหน้า">‹</button>
              <button type="button" class="gallery-nav next js-gallery-nav" data-direction="1" aria-label="รูปถัดไป">›</button>
              <div class="image-counter" id="imageCounter">
                1 / <?= (int) count($imageUrls); ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if (count($imageUrls) > 1): ?>
            <div class="thumbs" id="thumbs">
              <?php foreach ($imageUrls as $i => $u): ?>
                <img
                  data-src="<?= e($u); ?>"
                  class="thumb <?= $i === 0 ? 'active' : ''; ?> js-thumb"
                  data-index="<?= (int) $i; ?>"
                  alt="ตัวอย่างรูปที่ <?= (int) ($i + 1); ?>"
                  loading="lazy"
                  style="background: var(--skeleton-bg);">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="description-box" id="descriptionBox">
          <h2>รายละเอียด</h2>
          <p><?= nl2br(e($descText)); ?></p>
        </div>

        <div class="date-selection" id="dateSection" style="display: none;">
          <h3>เลือกวันที่นัดหมาย</h3>
          <div class="date-picker">
            <select id="daySelect" class="date-select">
              <?php for ($d = 1; $d <= 31; $d++): ?>
                <option value="<?= $d; ?>"><?= $d; ?></option>
              <?php endfor; ?>
            </select>

            <select id="monthSelect" class="date-select">
              <?php
              $thaiMonths = [
                'มกราคม',
                'กุมภาพันธ์',
                'มีนาคม',
                'เมษายน',
                'พฤษภาคม',
                'มิถุนายน',
                'กรกฎาคม',
                'สิงหาคม',
                'กันยายน',
                'ตุลาคม',
                'พฤศจิกายน',
                'ธันวาคม',
              ];
              foreach ($thaiMonths as $i => $month): ?>
                <option value="<?= (int) $i; ?>"><?= e($month); ?></option>
              <?php endforeach; ?>
            </select>

            <select id="yearSelect" class="date-select">
              <?php
              $currentYear = (int) date('Y');
              for ($y = $currentYear; $y <= $currentYear + 2; $y++): ?>
                <option value="<?= (int) $y; ?>"><?= (int) ($y + 543); ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="date-preview" id="datePreview"></div>
        </div>
      </div>

      <div class="detail-right">
        <div class="info-box">
          <h2 id="boxTitle">ข้อมูลพื้นที่</h2>

          <!-- ทำให้ CSS ติดจริง -->
          <div id="userBookingInfo" class="user-booking-info" style="display: none;">
            <div class="user-info-item">
              <strong>ผู้จอง (คุณ):</strong> <?= e($userFullName); ?>
            </div>
            <div class="user-info-item">
              <strong>เบอร์ติดต่อ:</strong> <?= e($userPhoneText); ?>
            </div>
          </div>

          <div id="specsBox">
            <div class="spec-item">
              <span class="spec-label">📐 ขนาดพื้นที่:</span>
              <span class="spec-value"><?= e($areaSizeLabel); ?></span>
            </div>
          </div>

          <div id="statusBox" class="status-row">
            <span class="status-label">สถานะ:</span>
            <span class="status-badge <?= e($statusClass[$currentStatus]); ?>">
              <?= e($statusText[$currentStatus]); ?>
            </span>
          </div>

          <div class="price-section">
            <div class="price-label">ราคาเช่า (ต่อปี)</div>
            <div class="price-value">฿<?= e($priceFormatted); ?></div>
            <div class="deposit-info">มัดจำ: ฿<?= e($deposit); ?></div>
          </div>

          <div id="normalButtons">
            <?php if ($isOwner): ?>
              <div class="owner-notice">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>นี่คือรายการของคุณ</span>
              </div>
              <a href="?page=edit_property&id=<?= (int) $id; ?>" class="btn-book btn-edit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>แก้ไขรายการ</span>
              </a>

            <?php elseif ($isAdmin): ?>
              <div class="admin-notice" style="padding: 0.75rem 1rem; background: rgba(102, 126, 234, 0.15); border: 1px solid rgba(102, 126, 234, 0.3); border-radius: var(--radius-sm); color: var(--primary-color); font-size: 0.9rem; margin-top: 0.8rem;">
                👤 ผู้ดูแลระบบไม่สามารถจองไได้ (ดูแลเพาะจัดการ)
              </div>
              <a href="?page=admin_dashboard" class="btn-book" style="background: var(--text-secondary); margin-top: 0.5rem;">
                ⚙️ ไปยังแดชบอร์ดแอดมิน
              </a>

            <?php elseif ($currentStatus === 'available'): ?>

              <?php if ($loggedInUser !== null): ?>
                <button type="button" class="btn-book js-show-booking">📝 จองพื้นที่เช่า</button>
              <?php else: ?>
                <a href="?page=signin" class="btn-book">🔐 เข้าสู่ระบบเพื่อจอง</a>
              <?php endif; ?>

            <?php else: ?>

              <button type="button" class="btn-book" disabled style="opacity: 0.5; cursor: not-allowed;">
                <?= $currentStatus === 'booked' ? 'ติดจองแล้ว' : 'ปิดให้เช่า'; ?>
              </button>

            <?php endif; ?>
          </div>

          <div id="bookingActions" style="display: none;">
            <button type="button" class="btn-confirm js-confirm-booking">ยืนยันการจอง</button>
            <button type="button" class="btn-cancel js-cancel-booking">❌ ยกเลิก</button>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>