<?php

declare(strict_types=1);

/**
 * property_bookings.php (REFAC)
 * - GET only (read-only)
 * - Owner/Admin authorization
 * - Safe bootstrap + session + guards
 * - Cleaner status mapping + date formatting
 */

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile) || !is_file($helpersFile)) {
  error_log('property_bookings_bootstrap_missing');
  http_response_code(500);

  if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error'], JSON_UNESCAPED_UNICODE);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// ----------------------------
// Session
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('property_bookings_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// Read-only: GET only
// ----------------------------
if ($method !== 'GET') {
  json_response(['success' => false, 'message' => 'Method not allowed'], 405);
  return;
}

// ----------------------------
// Auth
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
  return;
}

$userId   = (int)($user['user_id'] ?? $user['id'] ?? 0);
$userRole = (int)($user['role'] ?? 0);
$isAdmin  = defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN;

if ($userId <= 0) {
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
  return;
}

// ----------------------------
// Input
// ----------------------------
$areaId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
if ($areaId <= 0) {
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
  return;
}

// ----------------------------
// Fetch area (owner/admin)
// ----------------------------
try {
  if ($isAdmin) {
    $area = Database::fetchOne(
      'SELECT area_id, user_id, area_name, price_per_year, deposit_percent, area_status
         FROM rental_area
        WHERE area_id = ?
        LIMIT 1',
      [$areaId]
    );
  } else {
    $area = Database::fetchOne(
      'SELECT area_id, user_id, area_name, price_per_year, deposit_percent, area_status
         FROM rental_area
        WHERE area_id = ? AND user_id = ?
        LIMIT 1',
      [$areaId, $userId]
    );
  }
} catch (Throwable $e) {
  app_log('property_bookings_area_fetch_error', ['area_id' => $areaId, 'user_id' => $userId, 'error' => $e->getMessage()]);
  $area = null;
}

if (!$area) {
  flash('error', 'ไม่พบพื้นที่หรือคุณไม่มีสิทธิ์เข้าถึง');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
  return;
}

// ----------------------------
// Bookings
// ----------------------------
try {
  $bookings = Database::fetchAll(
    'SELECT
        bd.booking_id, bd.area_id, bd.user_id, bd.booking_date, bd.deposit_amount, bd.deposit_status,
        bd.created_at, bd.updated_at,
        u.full_name, u.username, u.phone
     FROM booking_deposit bd
     JOIN users u ON bd.user_id = u.user_id
     WHERE bd.area_id = ?
     ORDER BY bd.booking_date DESC, bd.created_at DESC',
    [$areaId]
  );
} catch (Throwable $e) {
  app_log('property_bookings_query_error', ['area_id' => $areaId, 'error' => $e->getMessage()]);
  $bookings = [];
}

// ----------------------------
// Mappings
// ----------------------------
$statusText = [
  'pending'  => 'รออนุมัติ',
  'approved' => 'อนุมัติแล้ว',
  'rejected' => 'ปฏิเสธ',
];

$statusClass = [
  'pending'  => 'status-pending',
  'approved' => 'status-approved',
  'rejected' => 'status-rejected',
];

$areaStatusLabel = match ((string)($area['area_status'] ?? '')) {
  'available'   => 'พร้อมให้เช่า',
  'booked'      => 'ติดจอง',
  'unavailable' => 'ปิดให้เช่า',
  'reserved'    => 'รอตรวจสอบสลิป',
  default       => 'ไม่ระบุ',
};

?>
<div class="property-bookings-container">
  <div class="page-header">
    <?php $backHref = $isAdmin ? '?page=admin_dashboard' : '?page=my_properties'; ?>
    <a href="<?= e($backHref); ?>" class="back-link">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      <span><?= $isAdmin ? 'กลับไปแดชบอร์ดแอดมิน' : 'กลับไปพื้นที่ของฉัน'; ?></span>
    </a>
  </div>

  <div class="property-header">
    <div class="property-info">
      <h1><?= e((string)($area['area_name'] ?? '')); ?></h1>
      <div class="property-meta">
        <span class="meta-item">
          ราคา/ปี: <strong>฿<?= number_format((float)($area['price_per_year'] ?? 0)); ?></strong>
        </span>
        <span class="meta-item">
          สถานะพื้นที่: <strong><?= e($areaStatusLabel); ?></strong>
        </span>
      </div>
    </div>
  </div>

  <div class="bookings-section">
    <div class="section-header">
      <h2>รายการจองทั้งหมด (<?= count($bookings); ?> รายการ)</h2>
    </div>

    <?php if (empty($bookings)): ?>
      <div class="empty-state">
        <svg class="empty-icon" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <h3>ยังไม่มีการจอง</h3>
        <p>เมื่อมีผู้สนใจจองพื้นที่นี้ จะแสดงรายการที่นี่</p>
      </div>
    <?php else: ?>
      <div class="bookings-list">
        <?php foreach ($bookings as $booking):
          $bookingStatus = (string)($booking['deposit_status'] ?? 'pending');
          $statusLabel   = $statusText[$bookingStatus] ?? 'ไม่ทราบ';
          $statusCss     = $statusClass[$bookingStatus] ?? 'status-unknown';

          $depositAmount = (float)($booking['deposit_amount'] ?? 0);

          $bookingDateRaw   = (string)($booking['booking_date'] ?? '');
          $bookingDateLabel = '-';
          if ($bookingDateRaw !== '') {
            try {
              $bookingDateLabel = (new DateTimeImmutable($bookingDateRaw))->format('d/m/Y');
            } catch (Throwable $e) {
              $bookingDateLabel = '-';
            }
          }

          $userFullName = trim((string)($booking['full_name'] ?? ''));
          if ($userFullName === '') $userFullName = 'ผู้ใช้ #' . (int)($booking['user_id'] ?? 0);

          $userPhone = trim((string)($booking['phone'] ?? ''));
          $userPhoneLabel = $userPhone !== '' ? $userPhone : '-';

          $userName = trim((string)($booking['username'] ?? ''));
          $userNameLabel = $userName !== '' ? '@' . $userName : 'ไม่มี';

          $profileImageUrl = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&size=60&background=667eea&color=fff&bold=true';
        ?>
          <div class="booking-card">
            <div class="booking-header">
              <div class="user-info">
                <div class="user-avatar">
                  <img src="<?= e($profileImageUrl); ?>" alt="<?= e($userFullName); ?>">
                </div>
                <div class="user-details">
                  <h3 class="user-name"><?= e($userFullName); ?></h3>

                  <p class="user-contact">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <?= e($userNameLabel); ?>
                  </p>

                  <p class="user-contact">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <?= e($userPhoneLabel); ?>
                  </p>
                </div>
              </div>

              <div class="booking-status">
                <span class="status-badge <?= e($statusCss); ?>"><?= e($statusLabel); ?></span>
              </div>
            </div>

            <div class="booking-details">
              <div class="detail-row">
                <span class="detail-label">วันที่จอง:</span>
                <span class="detail-value"><?= e($bookingDateLabel); ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">มัดจำ (<?= (int)($area['deposit_percent'] ?? 0); ?>%):</span>
                <span class="detail-value price">฿<?= number_format($depositAmount, 2); ?></span>
              </div>
            </div>

            <?php if ($bookingStatus === 'approved'): ?>
              <div class="booking-actions">
                <a href="?page=contract&booking_id=<?= (int)$booking['booking_id']; ?>" class="btn-create-contract">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <line x1="9" y1="16" x2="15" y2="16"></line>
                  </svg>
                  สร้างสัญญา
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>