<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
  define('APP_PATH', BASE_PATH . '/app');
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('my_properties_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('my_properties_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('my_properties_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนจัดการพื้นที่ของคุณ');
  redirect('?page=signin');
}

// แอดมินไม่มีการจัดการพื้นที่ส่วนตัว (ใช้ admin_dashboard แทน)
if ((int) ($user['role'] ?? 0) === ROLE_ADMIN) {
  flash('error', 'ผู้ดูแลระบบจัดการพื้นที่ผ่านแดชบอร์ดแอดมิน');
  redirect('?page=admin_dashboard');
}

$userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('my_properties_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Fetch user properties with error handling
// ----------------------------
$myAreas = [];

try {
  $myAreas = Database::fetchAll(
    '
        SELECT
            ra.area_id,
            ra.area_name,
            ra.price_per_year,
            ra.deposit_percent,
            ra.area_size,
            ra.area_status,
            ra.created_at,
            d.district_name,
            p.province_name,
            COALESCE((
                SELECT COUNT(*)
                FROM area_image ai
                WHERE ai.area_id = ra.area_id
            ), 0) AS image_count,
            COALESCE((
                SELECT COUNT(*)
                FROM booking_deposit bd
                WHERE bd.area_id = ra.area_id
            ), 0) AS booking_count,
            (
                SELECT ai2.image_url
                FROM area_image ai2
                WHERE ai2.area_id = ra.area_id
                ORDER BY ai2.image_id ASC
                LIMIT 1
            ) AS main_image
        FROM rental_area ra
        JOIN district d ON ra.district_id = d.district_id
        JOIN province p ON d.province_id = p.province_id
        WHERE ra.user_id = ?
        ORDER BY ra.created_at DESC
        ',
    [$userId]
  );
} catch (Throwable $e) {
  app_log('my_properties_query_error', [
    'user_id' => $userId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
  $myAreas = [];
}

// mapping สถานะ → text / class (กันกรณี status แปลก ๆ)
$statusText = [
  'available'   => 'พร้อมให้เช่า',
  'reserved'    => 'รอตรวจสอบสลิป',
  'booked'      => 'ติดจอง',
  'unavailable' => 'ปิดให้เช่า',
];

$statusClass = [
  'available'   => 'status-available',
  'reserved'    => 'status-reserved',
  'booked'      => 'status-booked',
  'unavailable' => 'status-unavailable',
];

?>
<div class="my-properties-container">
  <div class="page-header">
    <div class="header-left">
      <h1>พื้นที่ของฉัน</h1>
      <p class="subtitle">จัดการรายการที่ปล่อยเช่า</p>
    </div>
    <a href="?page=add_property" class="btn-add-property">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>เพิ่มรายการ</span>
    </a>
  </div>

  <?php if (empty($myAreas)): ?>
    <div class="empty-state">
      <svg class="empty-icon" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
      </svg>
      <h3>ยังไม่มีรายการ</h3>
      <p>เริ่มต้นปล่อยเช่าพื้นที่ของคุณ</p>
      <a href="?page=add_property" class="btn-add-first">เพิ่มรายการแรก</a>
    </div>
  <?php else: ?>
    <div class="properties-grid">
      <?php foreach ($myAreas as $area):

        $areaId       = (int) $area['area_id'];
        $mainImageRaw = (string) ($area['main_image'] ?? '');
        $mainImage    = $mainImageRaw !== '' ? $mainImageRaw : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="24"%3ENo Image%3C/text%3E%3C/svg%3E';
        $price        = (float) $area['price_per_year'];
        $priceFormatted = number_format($price, 2);
        $bookingCount = (int) ($area['booking_count'] ?? 0);
        $imageCount   = (int) ($area['image_count'] ?? 0);
        $areaSize     = (float) ($area['area_size'] ?? 0);
        $deposit      = (float) ($area['deposit_percent'] ?? 0);

        $statusKey    = (string) ($area['area_status'] ?? 'available');
        $statusLabel  = $statusText[$statusKey] ?? 'ไม่ทราบสถานะ';
        $statusCss    = $statusClass[$statusKey] ?? 'status-unknown';

        $createdAtRaw = $area['created_at'] ?? null;
        $createdLabel = '-';
        if ($createdAtRaw) {
          try {
            $createdAt = new DateTimeImmutable((string) $createdAtRaw);
            $createdLabel = $createdAt->format('d/m/Y');
          } catch (Throwable $e) {
            $createdLabel = e((string) $createdAtRaw);
          }
        }
        $locationLabel = trim((string) ($area['district_name'] ?? ''));
        if (!empty($area['province_name'])) {
          $locationLabel .= ($locationLabel !== '' ? ', ' : '') . (string) $area['province_name'];
        }
      ?>
        <div class="property-card">
          <div class="card-image">
            <img
              src="<?= e($mainImage); ?>"
              alt="<?= e($area['area_name'] ?? 'พื้นที่เกษตรของฉัน'); ?>">
            <span class="status-badge <?= e($statusCss); ?>">
              <?= e($statusLabel); ?>
            </span>

            <?php if ($imageCount > 1): ?>
              <span class="image-count">
                <?= $imageCount; ?> รูป
              </span>
            <?php endif; ?>
          </div>

          <div class="card-content">
            <h3 class="property-title">
              <?= e($area['area_name']); ?>
            </h3>
            <p class="property-location">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <?= e($locationLabel !== '' ? $locationLabel : 'ไม่ระบุอำเภอ/จังหวัด'); ?>
            </p>

            <div class="property-meta">
              <div class="meta-item">
                <span class="meta-label">ราคา/ปี:</span>
                <span class="meta-value">฿<?= $priceFormatted; ?></span>
              </div>
              <div class="meta-item">
                <span class="meta-label">การจอง:</span>
                <span class="meta-value"><?= $bookingCount; ?> รายการ</span>
              </div>
            </div>

            <div class="property-stats">
              <div class="stat">
                <svg class="stat-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="stat-text"><?= e($createdLabel); ?></span>
              </div>
              <div class="stat">
                <svg class="stat-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                  <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span class="stat-text"><?= number_format($areaSize, 2); ?> ไร่</span>
              </div>
              <div class="stat">
                <svg class="stat-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <path d="M12 6v6l4 2"></path>
                </svg>
                <span class="stat-text">มัดจำ <?= rtrim(rtrim(number_format($deposit, 2), '0'), '.'); ?>%</span>
              </div>
            </div>

            <div class="card-actions">
              <a href="?page=detail&id=<?= $areaId; ?>" class="btn-action view">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span>ดูรายละเอียด</span>
              </a>

              <a href="?page=property_bookings&id=<?= $areaId; ?>" class="btn-action bookings">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>รายการจอง</span>
                <?php if ($bookingCount > 0): ?>
                  <span class="badge-count"><?= $bookingCount; ?></span>
                <?php endif; ?>
              </a>

              <a href="?page=edit_property&id=<?= $areaId; ?>" class="btn-action edit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>แก้ไข</span>
              </a>

              <button
                type="button"
                class="btn-action delete js-delete-area"
                data-area-id="<?= $areaId; ?>"
                data-area-name="<?= e($area['area_name']); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  <line x1="10" y1="11" x2="10" y2="17"></line>
                  <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <span>ลบ</span>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>