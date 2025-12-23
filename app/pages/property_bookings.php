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
  app_log('property_bookings_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'System error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('property_bookings_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'System error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
  return;
}

$notificationServiceFile = APP_PATH . '/includes/NotificationService.php';
if (!is_file($notificationServiceFile)) {
  app_log('property_bookings_notification_service_missing', ['file' => $notificationServiceFile]);
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
  app_log('property_bookings_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'Session error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  }
  return;
}

// ----------------------------
// อ่านเมธอดคำขอ
// ----------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  } else {
    flash('error', 'กรุณาเข้าสู่ระบบก่อน');
    redirect('?page=signin');
  }
  return;
}

$userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
  } else {
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
  }
  return;
}

// ----------------------
// POST: อนุมัติ/ปฏิเสธการจอง
// ----------------------
if ($method === 'POST') {
  $action    = trim((string) ($_POST['action'] ?? ''));
  $bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
  $reason    = trim((string) ($_POST['reason'] ?? ''));

  if ($bookingId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    json_response(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
  }

  try {
    // ดึงข้อมูล booking + ตรวจสอบว่าเป็นเจ้าของพื้นที่จริง
    $booking = Database::fetchOne(
      'SELECT bd.*, ra.user_id, ra.area_name
       FROM booking_deposit bd
       JOIN rental_area ra ON bd.area_id = ra.area_id
       WHERE bd.booking_id = ?',
      [$bookingId]
    );

    if (!$booking) {
      json_response(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง'], 404);
    }

    if ((int)($booking['user_id'] ?? 0) !== $userId) {
      json_response(['success' => false, 'message' => 'คุณไม่มีสิทธิ์จัดการการจองนี้'], 403);
    }

    if ($action === 'approve') {
      // อนุมัติการจอง
      $areaId = (int)$booking['area_id'];

      // 1. อัปเดต booking นี้เป็น approved
      Database::execute(
        'UPDATE booking_deposit SET deposit_status = "approved", updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?',
        [$bookingId]
      );

      // 2. อัปเดตสถานะพื้นที่เป็น booked
      Database::execute(
        'UPDATE rental_area SET area_status = "booked", updated_at = CURRENT_TIMESTAMP WHERE area_id = ?',
        [$areaId]
      );

      // 3. ปฏิเสธการจองอื่น ๆ ที่ pending สำหรับพื้นที่เดียวกัน
      Database::execute(
        'UPDATE booking_deposit SET deposit_status = "rejected", updated_at = CURRENT_TIMESTAMP 
         WHERE area_id = ? AND booking_id != ? AND deposit_status = "pending"',
        [$areaId, $bookingId]
      );

      app_log('booking_approved', [
        'booking_id' => $bookingId,
        'area_id' => $areaId,
        'owner_id' => $userId,
      ]);

      json_response([
        'success' => true,
        'message' => 'อนุมัติการจองเรียบร้อยแล้ว พื้นที่ถูกอัปเดตเป็น "ติดจอง"',
      ]);
    } elseif ($action === 'reject') {
      // ปฏิเสธการจอง
      // Note: booking_deposit doesn't have a rejection_reason field, so just update status
      Database::execute(
        'UPDATE booking_deposit SET deposit_status = "rejected", updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?',
        [$bookingId]
      );

      app_log('booking_rejected', [
        'booking_id' => $bookingId,
        'owner_id' => $userId,
      ]);

      json_response([
        'success' => true,
        'message' => 'ปฏิเสธการจองเรียบร้อยแล้ว',
      ]);
    }
  } catch (Throwable $e) {
    app_log('booking_action_error', [
      'action'     => $action,
      'booking_id' => $bookingId,
      'error'      => $e->getMessage(),
    ]);

    json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดำเนินการ'], 500);
  }
}

// ----------------------
// GET: แสดงรายการจอง
// ----------------------
$areaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($areaId <= 0) {
  redirect('?page=my_properties');
}

// ตรวจสอบว่าเป็นเจ้าของพื้นที่
$area = Database::fetchOne(
  'SELECT area_id, user_id, area_name, price_per_year, deposit_percent, area_status FROM rental_area WHERE area_id = ? AND user_id = ?',
  [$areaId, $userId]
);

if (!$area) {
  flash('error', 'ไม่พบพื้นที่หรือคุณไม่มีสิทธิ์เข้าถึง');
  redirect('?page=my_properties');
}

// ดึงรายการจองทั้งหมดของพื้นที่นี้
$bookings = [];
try {
  $bookings = Database::fetchAll(
    'SELECT 
        bd.booking_id, bd.area_id, bd.user_id, bd.booking_date, bd.deposit_amount, bd.deposit_status,
        bd.created_at, bd.updated_at,
        u.full_name, u.username, u.phone
     FROM booking_deposit bd
     JOIN users u ON bd.user_id = u.user_id
     WHERE bd.area_id = ?
     ORDER BY bd.booking_date DESC',
    [$areaId]
  );
} catch (Throwable $e) {
  app_log('property_bookings_query_error', ['area_id' => $areaId, 'error' => $e->getMessage()]);
  $bookings = [];
}

// mapping สถานะ
$statusText = [
  'pending' => 'รออนุมัติ',
  'approved' => 'อนุมัติแล้ว',
  'rejected' => 'ปฏิเสธ',
];

$statusClass = [
  'pending' => 'status-pending',
  'approved' => 'status-approved',
  'rejected' => 'status-rejected',
];

?>
<div class="property-bookings-container">
  <div class="page-header">
    <a href="?page=my_properties" class="back-link">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      <span>กลับไปพื้นที่ของฉัน</span>
    </a>
  </div>

  <div class="property-header">
    <div class="property-info">
      <h1><?= e($area['area_name']); ?></h1>
      <div class="property-meta">
        <span class="meta-item">ราคา/ปี: <strong>฿<?= number_format((float)$area['price_per_year']); ?></strong></span>
        <span class="meta-item">สถานะ: <strong><?= e($area['area_status'] === 'available' ? 'พร้อมให้เช่า' : ($area['area_status'] === 'booked' ? 'ติดจอง' : 'ปิดให้เช่า')); ?></strong></span>
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
          $bid = (int)$booking['booking_id'];
          $bookingStatus = (string)($booking['deposit_status'] ?? 'pending');
          $statusLabel = $statusText[$bookingStatus] ?? 'ไม่ทราบ';
          $statusCss = $statusClass[$bookingStatus] ?? 'status-unknown';

          $depositAmount = (float)($booking['deposit_amount'] ?? 0);
          $bookingDateRaw = $booking['booking_date'] ?? null;
          $bookingDateLabel = $bookingDateRaw ? date('d/m/Y', strtotime($bookingDateRaw)) : '-';

          $userFullName = trim((string)($booking['full_name'] ?? ''));
          if ($userFullName === '') $userFullName = 'ผู้ใช้ #' . (int)$booking['user_id'];

          $userPhone = trim((string)($booking['phone'] ?? '-'));
          if ($userPhone === '') $userPhone = '-';

          $userName = trim((string)($booking['username'] ?? ''));
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
                    <?= e($userName ? '@' . $userName : 'ไม่มี'); ?>
                  </p>
                  <?php if ($userPhone !== '-'): ?>
                    <p class="user-contact">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                      </svg>
                      <?= e($userPhone); ?>
                    </p>
                  <?php endif; ?>
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
                <span class="detail-label">มัดจำ (<?= (int)$area['deposit_percent'] ?>%):</span>
                <span class="detail-value price">฿<?= number_format($depositAmount, 2); ?></span>
              </div>
            </div>

            <?php if ($bookingStatus === 'pending'): ?>
              <div class="booking-actions">
                <button
                  type="button"
                  class="btn-action approve"
                  onclick="approveBooking(<?= $bid; ?>)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  <span>อนุมัติ</span>
                </button>
                <button
                  type="button"
                  class="btn-action reject"
                  onclick="rejectBooking(<?= $bid; ?>)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                  <span>ปฏิเสธ</span>
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal สำหรับดูสลิปขนาดใหญ่ -->
<div id="slipModal" class="modal" onclick="closeSlipModal(event)">
  <div class="modal-content">
    <button type="button" class="modal-close" onclick="closeSlipModal(event)">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
    <img id="slipModalImage" src="" alt="สลิปการโอน">
  </div>
</div>