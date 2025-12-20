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
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
  app_log('history_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
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
                SELECT id, user_id, property_id, booking_date, payment_status, booking_status, 
                       deposit_amount, total_amount, slip_image, created_at
                FROM bookings 
                WHERE user_id = ? 
                  AND property_id = ? 
                  AND payment_status = "waiting"
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
    $bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($bookingId <= 0) {
      json_response([
        'success' => false,
        'message' => 'ข้อมูลไม่ถูกต้อง',
      ], 400);
    }

    try {
      // ตรวจสอบว่าเป็นการจองของผู้ใช้นี้และยังไม่ได้ชำระเงิน
      $booking = Database::fetchOne(
        '
                SELECT id 
                FROM bookings 
                WHERE id = ? 
                  AND user_id = ? 
                  AND payment_status = "waiting"
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

      Database::execute(
        '
                UPDATE bookings 
                SET booking_status = "cancelled", updated_at = NOW() 
                WHERE id = ?
                ',
        [$bookingId]
      );

      app_log('history_cancel_booking_success', [
        'user_id'    => $userId,
        'booking_id' => $bookingId,
      ]);

      json_response([
        'success' => true,
        'message' => 'ยกเลิกการจองสำเร็จ',
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
    '
        SELECT 
            b.id, b.user_id, b.property_id, b.booking_date, b.rental_start_date, b.rental_end_date,
            b.payment_status, b.booking_status, b.deposit_amount, b.total_amount, 
            b.slip_image, b.rejection_reason, b.created_at, b.updated_at,
            p.title, p.price 
        FROM bookings b 
        JOIN properties p ON b.property_id = p.id 
        WHERE b.user_id = ? 
          AND b.booking_status != "cancelled"
        ORDER BY b.created_at DESC
        ',
    [$userId]
  );
} catch (Throwable $e) {
  app_log('history_fetch_bookings_error', [
    'user_id' => $userId,
    'error'   => $e->getMessage(),
  ]);
  $bookings = [];
}

function paymentBadgeClass(string $status): string
{
  return $status === 'waiting' ? 'badge-pay-wait' : 'badge-pay-ok';
}

function bookingBadgeClass(string $status): string
{
  if ($status === 'pending') {
    return 'badge-book-pending';
  }

  if ($status === 'approved') {
    return 'badge-book-approved';
  }

  if ($status === 'rejected') {
    return 'badge-book-rejected';
  }

  return 'badge-book-other';
}

// สรุปตัวนับสถานะ
$summary = [
  'waiting'         => 0,
  'deposit_success' => 0,
  'pending'         => 0,
  'approved'        => 0,
  'rejected'        => 0,
];

foreach ($bookings as $b) {
  $pay = (string) ($b['payment_status'] ?? '');
  $book = (string) ($b['booking_status'] ?? '');

  if (isset($summary[$pay])) {
    $summary[$pay]++;
  }
  if (isset($summary[$book])) {
    $summary[$book]++;
  }
}
?>
<div class="history-container">
  <div class="history-header">
    <h1 class="history-title">📚 ประวัติการจอง / เช่าพื้นที่เกษตรของฉัน</h1>
    <div class="history-summary-cards">
      <div class="summary-card sc-wait" title="รอการชำระเงิน">
        <span class="sc-label">รอชำระ</span><span class="sc-value"><?= $summary['waiting'] ?></span>
      </div>
      <div class="summary-card sc-deposit" title="มัดจำสำเร็จ">
        <span class="sc-label">มัดจำ</span><span class="sc-value"><?= $summary['deposit_success'] ?></span>
      </div>
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
          <option value="waiting">รอการชำระเงิน</option>
          <option value="deposit_success">มัดจำสำเร็จ</option>
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
    <!-- Desktop: Table View -->
    <div class="history-table-wrapper">
      <table class="booking-table" id="bookingTable">
        <thead>
          <tr>
            <th>รหัส</th>
            <th>ชื่อพื้นที่</th>
            <th>วันที่จอง</th>
            <th>สถานะชำระเงิน</th>
            <th>สถานะการจอง</th>
            <th>จำนวนเงิน</th>
            <th>ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <?php
            $payStatus     = (string) ($b['payment_status'] ?? '');
            $bookStatus    = (string) ($b['booking_status'] ?? '');
            $payClass      = paymentBadgeClass($payStatus);
            $bookClass     = bookingBadgeClass($bookStatus);
            $payLabel      = $payStatus === 'waiting' ? 'รอการชำระเงิน' : 'มัดจำสำเร็จ';
            $bookLabel     = $bookStatus === 'pending'
              ? 'รออนุมัติ'
              : ($bookStatus === 'approved'
                ? 'อนุมัติแล้ว'
                : ($bookStatus === 'rejected' ? 'ปฏิเสธ' : e($bookStatus)));
            $totalAmount   = $b['total_amount'] ?? $b['price'] ?? 0;
            $priceFormatted = number_format((float) $totalAmount);
            ?>
            <tr
              data-pay="<?= e($payStatus); ?>"
              data-book="<?= e($bookStatus); ?>"
              data-title="<?= e($b['title']); ?>">
              <td><span class="ref-code">#<?= str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT); ?></span></td>
              <td class="title-cell"><strong><?= e($b['title']); ?></strong></td>
              <td><span class="date-cell"><?= buddhist_date($b['booking_date']); ?></span></td>
              <td><span class="badge <?= e($payClass); ?>" data-status="<?= e($payStatus); ?>"><?= e($payLabel); ?></span></td>
              <td><span class="badge <?= e($bookClass); ?>" data-status="<?= e($bookStatus); ?>"><?= e($bookLabel); ?></span></td>
              <td><strong>฿<?= $priceFormatted; ?></strong></td>
              <td class="actions-cell">
                <?php if ($payStatus === 'waiting'): ?>
                  <button type="button" class="action-btn pay" data-action="pay" data-id="<?= (int) $b['property_id']; ?>" title="ชำระเงิน">💳 ชำระ</button>
                  <button type="button" class="action-btn cancel" data-action="cancel" data-id="<?= (int) $b['id']; ?>" title="ยกเลิก">❌</button>
                <?php elseif ($bookStatus === 'pending'): ?>
                  <button type="button" class="action-btn view" data-action="view" data-id="<?= (int) $b['property_id']; ?>" title="ดูรายละเอียด">👁️ ดู</button>
                <?php elseif ($bookStatus === 'approved'): ?>
                  <button type="button" class="action-btn continue" data-action="continue" data-id="<?= (int) $b['id']; ?>" title="ขั้นตอนต่อไป">➡️ ต่อไป</button>
                <?php elseif ($bookStatus === 'rejected'): ?>
                  <button type="button" class="action-btn reason" data-action="reason" data-id="<?= (int) $b['id']; ?>" title="ดูเหตุผล">❓ เหตุผล</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile: Card View -->
    <div class="booking-cards" id="bookingCards">
      <?php foreach ($bookings as $b): ?>
        <?php
        $payStatus      = (string) ($b['payment_status'] ?? '');
        $bookStatus     = (string) ($b['booking_status'] ?? '');
        $payClass       = paymentBadgeClass($payStatus);
        $bookClass      = bookingBadgeClass($bookStatus);
        $payLabel       = $payStatus === 'waiting' ? 'รอการชำระเงิน' : 'มัดจำสำเร็จ';
        $bookLabel      = $bookStatus === 'pending'
          ? 'รออนุมัติ'
          : ($bookStatus === 'approved'
            ? 'อนุมัติแล้ว'
            : ($bookStatus === 'rejected' ? 'ปฏิเสธ' : e($bookStatus)));
        $totalAmount    = $b['total_amount'] ?? $b['price'] ?? 0;
        $priceFormatted = number_format((float) $totalAmount);
        ?>
        <div
          class="booking-card"
          data-pay="<?= e($payStatus); ?>"
          data-book="<?= e($bookStatus); ?>"
          data-title="<?= e($b['title']); ?>">
          <div class="booking-card-header">
            <div>
              <div class="booking-card-title"><?= e($b['title']); ?></div>
              <div class="booking-card-ref">#<?= str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>
          </div>

          <div class="booking-card-body">
            <div class="booking-card-field">
              <div class="booking-card-label">วันที่จอง</div>
              <div class="booking-card-value"><?= buddhist_date($b['booking_date']); ?></div>
            </div>
            <div class="booking-card-field">
              <div class="booking-card-label">จำนวนเงิน</div>
              <div class="booking-card-value">฿<?= $priceFormatted; ?></div>
            </div>
            <div class="booking-card-field">
              <div class="booking-card-label">ชำระเงิน</div>
              <div class="booking-card-value">
                <span class="badge <?= e($payClass); ?>" data-status="<?= e($payStatus); ?>"><?= e($payLabel); ?></span>
              </div>
            </div>
            <div class="booking-card-field">
              <div class="booking-card-label">สถานะ</div>
              <div class="booking-card-value">
                <span class="badge <?= e($bookClass); ?>" data-status="<?= e($bookStatus); ?>"><?= e($bookLabel); ?></span>
              </div>
            </div>
          </div>

          <div class="booking-card-actions">
            <?php if ($payStatus === 'waiting'): ?>
              <button type="button" class="action-btn pay" data-action="pay" data-id="<?= (int) $b['property_id']; ?>" title="ชำระเงิน">💳 ชำระ</button>
              <button type="button" class="action-btn cancel" data-action="cancel" data-id="<?= (int) $b['id']; ?>" title="ยกเลิก">❌ ยกเลิก</button>
            <?php elseif ($bookStatus === 'pending'): ?>
              <button type="button" class="action-btn view" data-action="view" data-id="<?= (int) $b['property_id']; ?>" title="ดูรายละเอียด">👁️ ดูรายละเอียด</button>
            <?php elseif ($bookStatus === 'approved'): ?>
              <button type="button" class="action-btn continue" data-action="continue" data-id="<?= (int) $b['id']; ?>" title="ขั้นตอนต่อไป">➡️ ขั้นตอนต่อไป</button>
            <?php elseif ($bookStatus === 'rejected'): ?>
              <button type="button" class="action-btn reason" data-action="reason" data-id="<?= (int) $b['id']; ?>" title="ดูเหตุผล">❓ ดูเหตุผล</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  const statusFilter = document.getElementById('statusFilter');
  const textFilter = document.getElementById('textFilter');
  const tableRows = Array.from(document.querySelectorAll('#bookingTable tbody tr'));
  const cardItems = Array.from(document.querySelectorAll('#bookingCards .booking-card'));

  function applyFilters() {
    const s = statusFilter.value.toLowerCase();
    const t = textFilter.value.toLowerCase();

    const filterFn = (el) => {
      const pay = (el.dataset.pay || '').toLowerCase();
      const book = (el.dataset.book || '').toLowerCase();
      const title = (el.dataset.title || '').toLowerCase();

      let show = true;
      if (s !== 'all') {
        show = (pay === s) || (book === s);
      }
      if (show && t) {
        show = title.includes(t);
      }
      el.style.display = show ? '' : 'none';
    };

    tableRows.forEach(filterFn);
    cardItems.forEach(filterFn);
  }

  if (statusFilter && textFilter) {
    statusFilter.addEventListener('change', applyFilters);
    textFilter.addEventListener('input', applyFilters);

    document.getElementById('resetFilters')?.addEventListener('click', () => {
      statusFilter.value = 'all';
      textFilter.value = '';
      applyFilters();
    });
  }

  document.querySelectorAll('.action-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('disabled')) return;

      const act = btn.dataset.action;
      const id = btn.dataset.id;

      switch (act) {
        case 'pay': {
          fetch(`?page=history&action=get_booking&id=${encodeURIComponent(id)}`)
            .then((res) => res.json())
            .then((data) => {
              if (data && data.success && data.booking) {
                const b = data.booking;
                const d = new Date(b.booking_date);
                window.location =
                  `?page=payment&id=${encodeURIComponent(id)}&day=${d.getDate()}&month=${d.getMonth()}&year=${d.getFullYear()}`;
              } else {
                alert(data && data.message ? data.message : 'ไม่พบข้อมูลการจอง');
              }
            })
            .catch(() => {
              const tomorrow = new Date();
              tomorrow.setDate(tomorrow.getDate() + 1);
              window.location =
                `?page=payment&id=${encodeURIComponent(id)}&day=${tomorrow.getDate()}&month=${tomorrow.getMonth()}&year=${tomorrow.getFullYear()}`;
            });
          break;
        }
        case 'cancel': {
          if (!confirm(`คุณต้องการยกเลิกการจอง #${id} ใช่หรือไม่?\n\nการยกเลิกจะไม่สามารถกู้คืนได้`)) {
            return;
          }

          fetch(`?page=history&action=cancel_booking&id=${encodeURIComponent(id)}`, {
              method: 'POST',
            })
            .then((res) => res.json())
            .then((data) => {
              if (data && data.success) {
                alert('ยกเลิกการจองสำเร็จ');
                window.location.reload();
              } else {
                alert('เกิดข้อผิดพลาด: ' + (data && data.message ? data.message : 'ไม่สามารถยกเลิกได้'));
              }
            })
            .catch(() => {
              alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
          break;
        }
        case 'view':
          window.location = `?page=detail&id=${encodeURIComponent(id)}`;
          break;
        case 'continue':
          alert('ไปขั้นตอนต่อไป (พัฒนาในอนาคต)');
          break;
        case 'reason':
          // ดึงเหตุผลจาก query parameter หรือแสดงข้อความทั่วไป
          const reason = new URLSearchParams(window.location.search).get('reason') || 'เอกสารไม่ครบถ้วน';
          alert('เหตุผล: ' + reason);
          break;
      }
    });
  });
</script>