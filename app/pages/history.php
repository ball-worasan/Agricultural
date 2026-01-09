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

$databaseFile = APP_PATH . '/config/database.php';
if (!is_file($databaseFile)) {
  app_log('history_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('history_helpers_file_missing', ['file' => $helpersFile]);
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
  app_log('history_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน (รองรับ AJAX action)
// ----------------------------
$isAjax = (static function (): bool {
  $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  return $xrw === 'xmlhttprequest' || stripos($accept, 'application/json') !== false;
})();

$user = current_user();
if ($user === null) {
  if ($isAjax && isset($_GET['action'])) {
    json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  }
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
}

$userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('history_invalid_user', ['session_user' => $user]);
  if ($isAjax && isset($_GET['action'])) {
    json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
  }
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Guard: แอดมินไม่มีประวัติการจองของตัวเอง
// ----------------------------
$userRole = (int)($user['role'] ?? 0);
if ($userRole === ROLE_ADMIN) {
  if ($isAjax && isset($_GET['action'])) {
    json_response(['success' => false, 'message' => 'ผู้ดูแลระบบไม่มีประวัติการจอง'], 403);
  }
  flash('error', 'ผู้ดูแลระบบไม่มีประวัติการจองของตัวเอง');
  redirect('?page=admin_dashboard');
}

// ----------------------------
// จัดการคำขอ AJAX
// ----------------------------
if (isset($_GET['action'])) {
  $action = (string) ($_GET['action'] ?? '');

  if ($action === 'get_booking') {
    $propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($propertyId <= 0) {
      json_response([
        'success' => false,
        'message' => 'ข้อมูลไม่ถูกต้อง',
      ], 400);
    }

    try {
      $booking = Database::fetchOne(
        '
                SELECT booking_id, user_id, area_id, booking_date, deposit_status, 
                       deposit_amount, created_at
                FROM booking_deposit 
                WHERE user_id = ? 
                  AND area_id = ? 
                  AND deposit_status = "pending"
                ORDER BY created_at DESC 
                LIMIT 1
                ',
        [$userId, $propertyId]
      );

      if ($booking) {
        json_response([
          'success' => true,
          'booking' => $booking,
        ]);
      }

      json_response([
        'success' => false,
        'message' => 'ไม่พบข้อมูลการจอง',
      ], 404);
    } catch (Throwable $e) {
      app_log('history_get_booking_error', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'error'       => $e->getMessage(),
      ]);

      json_response([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลการจอง',
      ], 500);
    }
  }

  if ($action === 'cancel_booking' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = isset($_POST['booking_id'])
      ? (int) $_POST['booking_id']
      : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

    if ($bookingId <= 0) {
      json_response([
        'success' => false,
        'message' => 'ข้อมูลไม่ถูกต้อง',
      ], 400);
    }

    try {
      // ตรวจสอบว่าเป็นการจองของผู้ใช้นี้และยังรอการอนุมัติ พร้อมดึง area_id
      $booking = Database::fetchOne(
        '
                SELECT booking_id, area_id 
                FROM booking_deposit 
                WHERE booking_id = ? 
                  AND user_id = ? 
                  AND deposit_status = "pending"
                LIMIT 1
                ',
        [$bookingId, $userId]
      );

      if (!$booking) {
        json_response([
          'success' => false,
          'message' => 'ไม่พบการจองหรือไม่สามารถยกเลิกได้',
        ], 404);
      }

      $areaId = (int)($booking['area_id'] ?? 0);

      Database::transaction(function () use ($bookingId, $areaId) {
        Database::execute(
          '
                UPDATE booking_deposit 
                SET deposit_status = "rejected", updated_at = CURRENT_TIMESTAMP 
                WHERE booking_id = ?
                ',
          [$bookingId]
        );

        if ($areaId > 0) {
          Database::execute(
            '
                UPDATE rental_area 
                SET area_status = "available", updated_at = CURRENT_TIMESTAMP 
                WHERE area_id = ? AND area_status IN ("booked", "unavailable")
                ',
            [$areaId]
          );
        }
      });

      app_log('history_cancel_booking_success', [
        'user_id'    => $userId,
        'booking_id' => $bookingId,
        'area_id'    => $areaId,
      ]);

      json_response([
        'success' => true,
        'message' => 'ยกเลิกการจองสำเร็จ',
        'booking_id' => $bookingId,
        'area_id' => $areaId,
      ]);
    } catch (Throwable $e) {
      app_log('history_cancel_booking_error', [
        'user_id'    => $userId,
        'booking_id' => $bookingId,
        'error'      => $e->getMessage(),
      ]);

      json_response([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
      ], 500);
    }
  }

  // action ไม่ถูกต้อง
  json_response([
    'success' => false,
    'message' => 'คำขอไม่ถูกต้อง',
  ], 400);
}

// ---------- ดึงประวัติการจอง ----------

try {
  $bookings = Database::fetchAll(
    'SELECT 
        bd.booking_id, bd.area_id, bd.user_id, bd.booking_date, bd.deposit_amount, bd.deposit_status,
        bd.created_at, bd.updated_at,
        c.contract_id, c.start_date AS contract_start, c.end_date AS contract_end,
        COALESCE(ra.area_name, "พื้นที่ถูกลบ") AS area_name,
        COALESCE(ra.price_per_year, 0) AS price_per_year,
        COALESCE(ra.deposit_percent, 10) AS deposit_percent,
        COALESCE(ra.area_status, "ไม่ระบุ") AS area_status,
        COALESCE(d.district_name, "ไม่ระบุ") AS district_name,
        COALESCE(p.province_name, "ไม่ระบุ") AS province_name
     FROM booking_deposit bd
     LEFT JOIN rental_area ra ON bd.area_id = ra.area_id
      LEFT JOIN contract c ON c.booking_id = bd.booking_id
     LEFT JOIN district d ON ra.district_id = d.district_id
     LEFT JOIN province p ON d.province_id = p.province_id
     WHERE bd.user_id = ?
     ORDER BY bd.created_at DESC',
    [$userId]
  );
} catch (Throwable $e) {
  app_log('history_fetch_bookings_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
  $bookings = [];
}

function depositStatusBadgeClass(string $status): string
{
  return match ($status) {
    'pending' => 'badge-deposit-pending',
    'approved' => 'badge-deposit-approved',
    'rejected' => 'badge-deposit-rejected',
    default => 'badge-deposit-unknown',
  };
}

function depositStatusLabel(string $status): string
{
  return match ($status) {
    'pending' => 'รออนุมัติ',
    'approved' => 'อนุมัติแล้ว',
    'rejected' => 'ปฏิเสธ',
    default => 'ไม่ทราบ',
  };
}

// สรุปตัวนับสถานะ
$summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($bookings as $b) {
  $status = (string)($b['deposit_status'] ?? 'pending');
  if (isset($summary[$status])) {
    $summary[$status]++;
  }
}
?>
<div class="history-container">
  <div class="history-header">
    <h1 class="history-title">📚 ประวัติการจองพื้นที่เกษตรของฉัน</h1>
    <div class="history-summary-cards">
      <div class="summary-card sc-pending" title="รออนุมัติ">
        <span class="sc-label">รออนุมัติ</span><span class="sc-value"><?= $summary['pending'] ?></span>
      </div>
      <div class="summary-card sc-approved" title="อนุมัติแล้ว">
        <span class="sc-label">อนุมัติ</span><span class="sc-value"><?= $summary['approved'] ?></span>
      </div>
      <div class="summary-card sc-rejected" title="ปฏิเสธ">
        <span class="sc-label">ปฏิเสธ</span><span class="sc-value"><?= $summary['rejected'] ?></span>
      </div>
    </div>
  </div>

  <div class="filters-card">
    <div class="filters-row">
      <div class="filter-group">
        <label for="statusFilter">สถานะ:</label>
        <select id="statusFilter" class="status-filter" aria-label="กรองตามสถานะ">
          <option value="all">ทั้งหมด</option>
          <option value="pending">รออนุมัติ</option>
          <option value="approved">อนุมัติแล้ว</option>
          <option value="rejected">ปฏิเสธ</option>
        </select>
      </div>
      <div class="filter-group grow">
        <label for="textFilter">ค้นหา:</label>
        <input type="text" id="textFilter" class="text-filter" placeholder="พิมพ์ชื่อพื้นที่..." aria-label="ค้นหาชื่อพื้นที่" />
      </div>
      <div class="filter-actions">
        <button type="button" class="filter-reset" id="resetFilters">รีเซ็ต</button>
      </div>
    </div>
  </div>

  <?php if (empty($bookings)): ?>
    <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px; margin: 20px 0;">
      <h2 style="color: #999;">ยังไม่มีประวัติการจอง</h2>
      <p style="color: #999;">คุณยังไม่เคยจองพื้นที่เช่า</p>
      <a href="?page=home" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;">🏠 ดูพื้นที่เช่า</a>
    </div>
  <?php else: ?>
    <div class="booking-cards" id="bookingCards">
      <?php foreach ($bookings as $b): ?>
        <?php
        $status = (string)($b['deposit_status'] ?? 'pending');
        $statusClass = depositStatusBadgeClass($status);
        $statusLabel = depositStatusLabel($status);
        $depositAmount = (float)($b['deposit_amount'] ?? 0);
        $pricePerYear = (float)($b['price_per_year'] ?? 0);
        $depositPercent = (float)($b['deposit_percent'] ?? 10);
        $bookingDate = $b['booking_date'] ?? null;
        $bookingDateLabel = $bookingDate ? date('d/m/Y', strtotime($bookingDate)) : '-';
        $hasContract = !empty($b['contract_id']);
        ?>
        <div
          class="booking-card"
          data-status="<?= e($status); ?>"
          data-title="<?= e($b['area_name']); ?>">
          <div class="booking-card-header">
            <div>
              <h3 class="booking-title"><?= e($b['area_name']); ?></h3>
              <p class="booking-location"><?= e($b['district_name']); ?>, <?= e($b['province_name']); ?></p>
            </div>
            <span class="status-badge <?= e($statusClass); ?>"><?= e($statusLabel); ?></span>
          </div>

          <div class="booking-card-body">
            <div class="booking-card-field">
              <span class="field-label">วันที่จอง:</span>
              <span class="field-value"><?= e($bookingDateLabel); ?></span>
            </div>
            <div class="booking-card-field">
              <span class="field-label">ราคาต่อปี:</span>
              <span class="field-value">฿<?= number_format($pricePerYear, 2); ?></span>
            </div>
            <div class="booking-card-field">
              <span class="field-label">มัดจำ (<?= (int)$depositPercent ?>%):</span>
              <span class="field-value price">฿<?= number_format($depositAmount, 2); ?></span>
            </div>
            <div class="booking-card-field">
              <span class="field-label">สถานะพื้นที่:</span>
              <span class="field-value"><?= e(
                                          ($b['area_status'] === 'available') ? 'พร้อมให้เช่า'
                                            : (($b['area_status'] === 'booked') ? 'ติดจอง'
                                              : (($b['area_status'] === 'available') ? 'จองไว้' : 'ปิดให้เช่า'))
                                        ); ?></span>
            </div>
          </div>

          <div class="booking-card-actions">
            <?php if ($status === 'pending'): ?>
              <button type="button" class="action-btn cancel" data-action="cancel" data-id="<?= (int)$b['booking_id']; ?>" title="ยกเลิก">❌ ยกเลิก</button>
            <?php elseif ($status === 'approved' && $hasContract): ?>
              <button type="button" class="action-btn view" data-action="viewContract" data-id="<?= (int)$b['booking_id']; ?>" title="ดูรายละเอียดสัญญา">📄 ดูรายละเอียดสัญญา</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>