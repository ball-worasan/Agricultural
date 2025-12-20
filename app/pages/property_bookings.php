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

$userId = (int) ($user['id'] ?? 0);
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
      '
            SELECT b.*, p.owner_id, p.title AS property_title
            FROM bookings b
            JOIN properties p ON b.property_id = p.id
            WHERE b.id = ?
            ',
      [$bookingId]
    );

    if (!$booking) {
      json_response(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง'], 404);
    }

    if ((int) $booking['owner_id'] !== $userId) {
      json_response(['success' => false, 'message' => 'คุณไม่มีสิทธิ์จัดการการจองนี้'], 403);
    }

    if ($action === 'approve') {
      // อนุมัติการจอง
      $propertyId = (int) $booking['property_id'];

      // 1. อัปเดต booking นี้เป็น approved
      Database::execute(
        '
                UPDATE bookings
                SET booking_status = "approved",
                    updated_at = NOW()
                WHERE id = ?
                ',
        [$bookingId]
      );

      // 2. อัปเดตสถานะพื้นที่เป็น booked
      Database::execute(
        '
                UPDATE properties
                SET status = "booked",
                    updated_at = NOW()
                WHERE id = ?
                ',
        [$propertyId]
      );

      // 3. ปฏิเสธการจองอื่น ๆ ที่ pending สำหรับพื้นที่เดียวกัน
      Database::execute(
        '
                UPDATE bookings
                SET booking_status = "rejected",
                    rejection_reason = "มีผู้เช่าคนอื่นได้รับการอนุมัติแล้ว",
                    updated_at = NOW()
                WHERE property_id = ?
                  AND id != ?
                  AND booking_status = "pending"
                ',
        [$propertyId, $bookingId]
      );

      // ดึงข้อมูลผู้จองและชื่อพื้นที่
      $bookingUser = Database::fetchOne(
        'SELECT user_id FROM bookings WHERE id = ?',
        [$bookingId]
      );
      $propertyInfo = Database::fetchOne(
        'SELECT title FROM properties WHERE id = ?',
        [$propertyId]
      );

      if ($bookingUser && $propertyInfo) {
        NotificationService::notifyBookingApproved(
          (int)$bookingUser['user_id'],
          (string)$propertyInfo['title']
        );
      }

      app_log('booking_approved', [
        'booking_id'  => $bookingId,
        'property_id' => $propertyId,
        'owner_id'    => $userId,
      ]);

      json_response([
        'success' => true,
        'message' => 'อนุมัติการจองเรียบร้อยแล้ว พื้นที่ถูกอัปเดตเป็น "ติดจอง"',
      ]);
    } elseif ($action === 'reject') {
      // ปฏิเสธการจอง
      if ($reason === '') {
        json_response(['success' => false, 'message' => 'กรุณาระบุเหตุผลในการปฏิเสธ'], 400);
      }

      Database::execute(
        '
                UPDATE bookings
                SET booking_status = "rejected",
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
                ',
        [$reason, $bookingId]
      );

      // ดึงข้อมูลผู้จองและชื่อพื้นที่
      $bookingUser = Database::fetchOne(
        'SELECT user_id FROM bookings WHERE id = ?',
        [$bookingId]
      );
      $propertyInfo = Database::fetchOne(
        'SELECT title FROM properties WHERE id = ?',
        [(int)$booking['property_id']]
      );

      if ($bookingUser && $propertyInfo) {
        NotificationService::notifyBookingRejected(
          (int)$bookingUser['user_id'],
          (string)$propertyInfo['title'],
          $reason
        );
      }

      app_log('booking_rejected', [
        'booking_id' => $bookingId,
        'owner_id'   => $userId,
        'reason'     => $reason,
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
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($propertyId <= 0) {
  redirect('?page=my_properties');
}

// ตรวจสอบว่าเป็นเจ้าของพื้นที่
$property = Database::fetchOne(
  'SELECT id, owner_id, title, location, province, price, status FROM properties WHERE id = ? AND owner_id = ?',
  [$propertyId, $userId]
);

if (!$property) {
  flash('error', 'ไม่พบพื้นที่หรือคุณไม่มีสิทธิ์เข้าถึง');
  redirect('?page=my_properties');
}

// ดึงรายการจองทั้งหมดของพื้นที่นี้
$bookings = [];
try {
  $bookings = Database::fetchAll(
    '
        SELECT 
            b.id, b.user_id, b.property_id, b.booking_date, b.rental_start_date, b.rental_end_date,
            b.payment_status, b.booking_status, b.deposit_amount, b.total_amount,
            b.slip_image, b.rejection_reason, b.created_at, b.updated_at,
            u.firstname,
            u.lastname,
            u.email,
            u.phone,
            u.profile_image
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.property_id = ?
        ORDER BY 
            CASE b.booking_status
                WHEN "pending" THEN 1
                WHEN "approved" THEN 2
                WHEN "rejected" THEN 3
                WHEN "cancelled" THEN 4
                ELSE 5
            END,
            b.created_at DESC
        ',
    [$propertyId]
  );
} catch (Throwable $e) {
  app_log('property_bookings_query_error', [
    'property_id' => $propertyId,
    'error'       => $e->getMessage(),
  ]);
  $bookings = [];
}

// mapping สถานะ
$statusText = [
  'pending'   => 'รอตรวจสอบ',
  'approved'  => 'อนุมัติแล้ว',
  'rejected'  => 'ปฏิเสธ',
  'cancelled' => 'ยกเลิก',
];

$statusClass = [
  'pending'   => 'status-pending',
  'approved'  => 'status-approved',
  'rejected'  => 'status-rejected',
  'cancelled' => 'status-cancelled',
];

$paymentText = [
  'waiting'         => 'รอชำระเงิน',
  'deposit_success' => 'ชำระมัดจำแล้ว',
  'full_paid'       => 'ชำระครบแล้ว',
];

$paymentClass = [
  'waiting'         => 'payment-waiting',
  'deposit_success' => 'payment-deposit',
  'full_paid'       => 'payment-full',
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
      <h1><?= e($property['title']); ?></h1>
      <p class="property-location">
        <?= e($property['location']); ?>
        <?php if (!empty($property['province'])): ?>
          , <?= e($property['province']); ?>
        <?php endif; ?>
      </p>
      <div class="property-meta">
        <span class="meta-item">ราคา/ปี: <strong>฿<?= number_format((float) $property['price']); ?></strong></span>
        <span class="meta-item">สถานะ: <strong><?= e($property['status']); ?></strong></span>
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
          $bid           = (int) $booking['id'];
          $bookingStatus = (string) ($booking['booking_status'] ?? 'pending');
          $paymentStatus = (string) ($booking['payment_status'] ?? 'waiting');

          $statusLabel = $statusText[$bookingStatus] ?? 'ไม่ทราบ';
          $statusCss   = $statusClass[$bookingStatus] ?? 'status-unknown';

          $paymentLabel = $paymentText[$paymentStatus] ?? 'ไม่ทราบ';
          $paymentCss   = $paymentClass[$paymentStatus] ?? 'payment-unknown';

          $depositAmount = (float) ($booking['deposit_amount'] ?? 0);
          $totalAmount   = (float) ($booking['total_amount'] ?? 0);

          $bookingDateRaw = $booking['booking_date'] ?? null;
          $bookingDateLabel = '-';
          if ($bookingDateRaw) {
            try {
              $dt = new DateTimeImmutable((string) $bookingDateRaw);
              $bookingDateLabel = $dt->format('d/m/Y');
            } catch (Throwable $e) {
              $bookingDateLabel = e((string) $bookingDateRaw);
            }
          }

          $createdAtRaw = $booking['created_at'] ?? null;
          $createdAtLabel = '-';
          if ($createdAtRaw) {
            try {
              $dt = new DateTimeImmutable((string) $createdAtRaw);
              $createdAtLabel = $dt->format('d/m/Y H:i');
            } catch (Throwable $e) {
              $createdAtLabel = e((string) $createdAtRaw);
            }
          }

          $slipImage = isset($booking['slip_image']) && $booking['slip_image']
            ? (string) $booking['slip_image']
            : null;

          $userFullName = trim(($booking['firstname'] ?? '') . ' ' . ($booking['lastname'] ?? ''));
          $userEmail    = $booking['email'] ?? '-';
          $userPhone    = $booking['phone'] ?? '-';

          // สร้าง URL รูปโปรไฟล์
          $profileImageUrl = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&size=120&background=667eea&color=fff&bold=true';
          if (!empty($booking['profile_image'])) {
            $imagePath = (string) $booking['profile_image'];
            // ถ้าเป็น URL เต็ม (http/https) ใช้เลย
            if (strpos($imagePath, 'http') === 0) {
              $profileImageUrl = $imagePath;
            } else {
              // ถ้าเป็น path สัมพัทธ์ ให้ใช้เลย
              $profileImageUrl = $imagePath;
            }
          }

          $rejectionReason = isset($booking['rejection_reason']) && $booking['rejection_reason']
            ? (string) $booking['rejection_reason']
            : null;
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
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <?= e($userEmail); ?>
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
                <span class="payment-badge <?= e($paymentCss); ?>"><?= e($paymentLabel); ?></span>
              </div>
            </div>

            <div class="booking-details">
              <div class="detail-row">
                <span class="detail-label">วันที่จอง:</span>
                <span class="detail-value"><?= e($bookingDateLabel); ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">ยื่นคำขอเมื่อ:</span>
                <span class="detail-value"><?= e($createdAtLabel); ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">มัดจำ:</span>
                <span class="detail-value price">฿<?= number_format($depositAmount); ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">ยอดรวม:</span>
                <span class="detail-value price">฿<?= number_format($totalAmount); ?></span>
              </div>

              <?php if ($slipImage): ?>
                <div class="slip-section">
                  <span class="detail-label">สลิปการโอน:</span>
                  <div class="slip-preview">
                    <img
                      src="<?= e($slipImage); ?>"
                      alt="สลิปการโอน"
                      class="slip-thumbnail"
                      onclick="showSlipModal('<?= e($slipImage); ?>')">
                    <button
                      type="button"
                      class="btn-view-slip"
                      onclick="showSlipModal('<?= e($slipImage); ?>')">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                      <span>ดูสลิปขนาดใหญ่</span>
                    </button>
                  </div>
                </div>
              <?php else: ?>
                <div class="detail-row">
                  <span class="detail-label">สลิปการโอน:</span>
                  <span class="detail-value muted">ยังไม่ได้อัปโหลด</span>
                </div>
              <?php endif; ?>

              <?php if ($rejectionReason): ?>
                <div class="rejection-reason">
                  <strong>เหตุผลที่ปฏิเสธ:</strong> <?= e($rejectionReason); ?>
                </div>
              <?php endif; ?>
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

            <?php if ($bookingStatus === 'approved'): ?>
              <div class="booking-actions">
                <a
                  class="btn-action approve"
                  href="?page=contract&booking_id=<?= $bid; ?>">
                  📄 สร้างสัญญา
                </a>
                <?php if ($paymentStatus !== 'full_paid'): ?>
                  <a
                    class="btn-action"
                    href="?page=full_payment&property_id=<?= (int)$propertyId; ?>&booking_id=<?= $bid; ?>">
                    💳 ชำระเต็มจำนวน
                  </a>
                <?php endif; ?>
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

<script>
  (function() {
    'use strict';

    window.showSlipModal = function(imageUrl) {
      const modal = document.getElementById('slipModal');
      const img = document.getElementById('slipModalImage');
      if (modal && img) {
        img.src = imageUrl;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    };

    window.closeSlipModal = function(event) {
      if (event.target.id === 'slipModal' || event.currentTarget.classList.contains('modal-close')) {
        const modal = document.getElementById('slipModal');
        if (modal) {
          modal.classList.remove('active');
          document.body.style.overflow = '';
        }
      }
    };

    window.approveBooking = async function(bookingId) {
      if (!confirm('ยืนยันการอนุมัติการจองนี้?\n\nพื้นที่จะถูกอัปเดตเป็น "ติดจอง" และการจองอื่น ๆ จะถูกปฏิเสธอัตโนมัติ')) {
        return;
      }

      try {
        const body = new URLSearchParams({
          action: 'approve',
          booking_id: String(bookingId)
        });

        const res = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await res.json();

        if (data.success) {
          alert('' + data.message);
          window.location.reload();
        } else {
          alert('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'));
        }
      } catch (err) {
        console.error('approveBooking error:', err);
        alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
      }
    };

    window.rejectBooking = async function(bookingId) {
      const reason = prompt('กรุณาระบุเหตุผลในการปฏิเสธ:');
      if (!reason || reason.trim() === '') {
        alert('กรุณาระบุเหตุผลในการปฏิเสธ');
        return;
      }

      try {
        const body = new URLSearchParams({
          action: 'reject',
          booking_id: String(bookingId),
          reason: reason.trim()
        });

        const res = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await res.json();

        if (data.success) {
          alert('' + data.message);
          window.location.reload();
        } else {
          alert('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'));
        }
      } catch (err) {
        console.error('rejectBooking error:', err);
        alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
      }
    };

    // ปิด modal เมื่อกด ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeSlipModal({
          target: {
            id: 'slipModal'
          }
        });
      }
    });

    // จัดการรูปภาพที่โหลดไม่ได้
    document.querySelectorAll('.user-avatar img').forEach(function(img) {
      img.addEventListener('error', function() {
        // ถ้ารูปโหลดไม่ได้ ให้ใช้ ui-avatars
        const alt = this.getAttribute('alt') || 'User';
        this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(alt) + '&size=120&background=667eea&color=fff&bold=true';
      });
    });
  })();
</script>