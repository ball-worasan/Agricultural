<?php

declare(strict_types=1);

/**
 * app/pages/history.php (FULL)
 * - หน้าประวัติการจองของผู้ใช้ (สมาชิกเท่านั้น)
 * - รองรับ AJAX actions:
 *    - GET  ?page=history&action=get_booking&id=AREA_ID
 *    - POST ?page=history&action=cancel_booking   (ส่ง booking_id + _csrf)
 * - PRG + flash ตามเดิม (แต่ action เป็น ajax จะตอบ json)
 */

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

// ------------------------------------------------------------
// bootstrap files
// ------------------------------------------------------------
$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile)) {
  app_log('history_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}
if (!is_file($helpersFile)) {
  app_log('history_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// ------------------------------------------------------------
// session
// ------------------------------------------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('history_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ------------------------------------------------------------
// ajax detector
// ------------------------------------------------------------
$isAjax = (static function (): bool {
  $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  return $xrw === 'xmlhttprequest' || stripos($accept, 'application/json') !== false;
})();

// ------------------------------------------------------------
// auth guard (with ajax support)
// ------------------------------------------------------------
$user = current_user();
if ($user === null) {
  if ($isAjax && isset($_GET['action'])) json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin', 303);
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('history_invalid_user', ['session_user' => $user]);
  if ($isAjax && isset($_GET['action'])) json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin', 303);
}

// admin no history
$userRole = (int)($user['role'] ?? 0);
if (defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN) {
  if ($isAjax && isset($_GET['action'])) json_response(['success' => false, 'message' => 'ผู้ดูแลระบบไม่มีประวัติการจอง'], 403);
  flash('error', 'ผู้ดูแลระบบไม่มีประวัติการจองของตัวเอง');
  redirect('?page=admin_dashboard', 303);
}

// ------------------------------------------------------------
// CSRF helper for POST ajax
// ------------------------------------------------------------
$requireCsrf = static function (): void {
  $token = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($token)) {
    json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง (CSRF)'], 403);
  }
};

// ------------------------------------------------------------
// AJAX actions
// ------------------------------------------------------------
$action = (string)($_GET['action'] ?? '');

if ($action !== '') {
  if ($action === 'get_booking') {
    $propertyId = (int)($_GET['id'] ?? 0);
    if ($propertyId <= 0) {
      json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
    }

    try {
      $booking = Database::fetchOne(
        '
          SELECT booking_id, user_id, area_id, booking_date, deposit_status,
                 deposit_amount, created_at, updated_at, payment_slip
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
        json_response(['success' => true, 'booking' => $booking]);
      }

      json_response(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง'], 404);
    } catch (Throwable $e) {
      app_log('history_get_booking_error', [
        'user_id' => $userId,
        'property_id' => $propertyId,
        'error' => $e->getMessage(),
      ]);
      json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลการจอง'], 500);
    }
  }

  if ($action === 'cancel_booking') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
      json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    // CSRF required
    $requireCsrf();

    $bookingId = (int)($_POST['booking_id'] ?? ($_GET['id'] ?? 0));
    if ($bookingId <= 0) {
      json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
    }

    try {
      // ดึง booking + area_id และ lock แถว booking กันยิงซ้ำ
      $booking = Database::fetchOne(
        '
          SELECT booking_id, area_id
          FROM booking_deposit
          WHERE booking_id = ?
            AND user_id = ?
            AND deposit_status = "pending"
          LIMIT 1
          FOR UPDATE
        ',
        [$bookingId, $userId]
      );

      if (!$booking) {
        json_response(['success' => false, 'message' => 'ไม่พบการจองหรือไม่สามารถยกเลิกได้'], 404);
      }

      $areaId = (int)($booking['area_id'] ?? 0);

      Database::transaction(function () use ($bookingId, $areaId): void {
        Database::execute(
          '
            UPDATE booking_deposit
            SET deposit_status = "rejected", updated_at = CURRENT_TIMESTAMP
            WHERE booking_id = ?
          ',
          [$bookingId]
        );

        // คืนสถานะพื้นที่เป็น available เฉพาะเคสที่ยัง booked/unavailable
        if ($areaId > 0) {
          Database::execute(
            '
              UPDATE rental_area
              SET area_status = "available", updated_at = CURRENT_TIMESTAMP
              WHERE area_id = ?
                AND area_status IN ("booked", "unavailable")
            ',
            [$areaId]
          );
        }
      });

      app_log('history_cancel_booking_success', [
        'user_id' => $userId,
        'booking_id' => $bookingId,
        'area_id' => $areaId,
      ]);

      json_response([
        'success' => true,
        'message' => 'ยกเลิกการจองสำเร็จ',
        'booking_id' => $bookingId,
        'area_id' => $areaId,
      ]);
    } catch (Throwable $e) {
      app_log('history_cancel_booking_error', [
        'user_id' => $userId,
        'booking_id' => $bookingId,
        'error' => $e->getMessage(),
      ]);

      json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
    }
  }

  json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], 400);
}

// ------------------------------------------------------------
// Helpers for view
// ------------------------------------------------------------
function depositStatusBadgeClass(string $status): string
{
  return match ($status) {
    'pending'  => 'badge-deposit-pending',
    'approved' => 'badge-deposit-approved',
    'rejected' => 'badge-deposit-rejected',
    default    => 'badge-deposit-unknown',
  };
}

function depositStatusLabel(string $status): string
{
  return match ($status) {
    'pending'  => 'รออนุมัติ',
    'approved' => 'อนุมัติแล้ว',
    'rejected' => 'ปฏิเสธ',
    default    => 'ไม่ทราบ',
  };
}

// ------------------------------------------------------------
// Fetch bookings
// ------------------------------------------------------------
try {
  $bookings = Database::fetchAll(
    'SELECT
        bd.booking_id, bd.area_id, bd.user_id, bd.booking_date, bd.deposit_amount, bd.deposit_status,
        bd.payment_slip, bd.created_at, bd.updated_at,
        c.contract_id, c.start_date AS contract_start, c.end_date AS contract_end,
        COALESCE(ra.area_name, "พื้นที่ถูกลบ") AS area_name,
        COALESCE(ra.price_per_year, 0) AS price_per_year,
        COALESCE(ra.deposit_percent, 10) AS deposit_percent,
        COALESCE(ra.area_status, "ไม่ระบุ") AS area_status,
        COALESCE(d.district_name, "ไม่ระบุ") AS district_name,
        COALESCE(p.province_name, "ไม่ระบุ") AS province_name,
        py.payment_id, py.status AS payment_status
     FROM booking_deposit bd
     LEFT JOIN rental_area ra ON bd.area_id = ra.area_id
     LEFT JOIN contract c     ON c.booking_id = bd.booking_id
     LEFT JOIN district d     ON ra.district_id = d.district_id
     LEFT JOIN province p     ON d.province_id = p.province_id
     LEFT JOIN payment py     ON py.contract_id = c.contract_id AND py.status IN ("pending", "confirmed")
     WHERE bd.user_id = ?
     ORDER BY bd.created_at DESC',
    [$userId]
  );
} catch (Throwable $e) {
  app_log('history_fetch_bookings_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
  $bookings = [];
}

// summary counters
$summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($bookings as $b) {
  $st = (string)($b['deposit_status'] ?? 'pending');
  if (isset($summary[$st])) $summary[$st]++;
}

$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<div class="history-container" data-page="history">
  <div class="history-header">
    <h1 class="history-title">📚 ประวัติการจองพื้นที่เกษตรของฉัน</h1>

    <div class="history-summary-cards">
      <div class="summary-card sc-pending" title="รออนุมัติ">
        <span class="sc-label">รออนุมัติ</span><span class="sc-value"><?= (int)$summary['pending']; ?></span>
      </div>
      <div class="summary-card sc-approved" title="อนุมัติแล้ว">
        <span class="sc-label">อนุมัติ</span><span class="sc-value"><?= (int)$summary['approved']; ?></span>
      </div>
      <div class="summary-card sc-rejected" title="ปฏิเสธ">
        <span class="sc-label">ปฏิเสธ</span><span class="sc-value"><?= (int)$summary['rejected']; ?></span>
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
    <div style="text-align:center;padding:60px 20px;background:white;border-radius:8px;margin:20px 0;">
      <h2 style="color:#999;">ยังไม่มีประวัติการจอง</h2>
      <p style="color:#999;">คุณยังไม่เคยจองพื้นที่เช่า</p>
      <a href="?page=home" style="display:inline-block;margin-top:20px;padding:12px 24px;background:#667eea;color:white;text-decoration:none;border-radius:6px;">
        🏠 ดูพื้นที่เช่า
      </a>
    </div>
  <?php else: ?>
    <input type="hidden" id="csrfToken" value="<?= e($csrf); ?>">

    <div class="booking-cards" id="bookingCards">
      <?php foreach ($bookings as $b): ?>
        <?php
        $status = (string)($b['deposit_status'] ?? 'pending');
        $statusClass = depositStatusBadgeClass($status);
        $statusText  = depositStatusLabel($status);

        $depositAmount  = (float)($b['deposit_amount'] ?? 0);
        $pricePerYear   = (float)($b['price_per_year'] ?? 0);
        $depositPercent = (float)($b['deposit_percent'] ?? 10);

        $bookingDate = (string)($b['booking_date'] ?? '');
        $bookingDateLabel = $bookingDate !== '' ? date('d/m/Y', strtotime($bookingDate)) : '-';

        $hasContract = !empty($b['contract_id']);
        $paymentStatus = (string)($b['payment_status'] ?? '');
        $areaStatus = (string)($b['area_status'] ?? 'ไม่ระบุ');

        $areaStatusLabel =
          $areaStatus === 'available' ? 'พร้อมให้เช่า'
          : ($areaStatus === 'booked' ? 'ติดจอง'
            : ($areaStatus === 'unavailable' ? 'ปิดให้เช่า' : e($areaStatus)));
        ?>

        <div class="booking-card"
          data-status="<?= e($status); ?>"
          data-title="<?= e((string)($b['area_name'] ?? '')); ?>">

          <div class="booking-card-header">
            <div>
              <h3 class="booking-title"><?= e((string)($b['area_name'] ?? '')); ?></h3>
              <p class="booking-location"><?= e((string)($b['district_name'] ?? '')); ?>, <?= e((string)($b['province_name'] ?? '')); ?></p>
            </div>
            <span class="status-badge <?= e($statusClass); ?>"><?= e($statusText); ?></span>
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
              <span class="field-label">มัดจำ (<?= (int)$depositPercent; ?>%):</span>
              <span class="field-value price">฿<?= number_format($depositAmount, 2); ?></span>
            </div>

            <div class="booking-card-field">
              <span class="field-label">สถานะพื้นที่:</span>
              <span class="field-value"><?= e($areaStatusLabel); ?></span>
            </div>
          </div>

          <div class="booking-card-actions">
            <?php if ($status === 'pending'): ?>
              <button
                type="button"
                class="action-btn cancel js-cancel-booking"
                data-booking-id="<?= (int)($b['booking_id'] ?? 0); ?>"
                title="ยกเลิก">
                ❌ ยกเลิก
              </button>

            <?php elseif ($status === 'approved' && $hasContract): ?>
              <a
                class="action-btn view"
                href="?page=contract&booking_id=<?= (int)$b['booking_id']; ?>"
                title="ดูรายละเอียดสัญญา"
                style="text-decoration:none;">
                📄 ดูรายละเอียดสัญญา
              </a>

              <!-- ชำระเงิน: disabled ถ้ามี payment pending/confirmed -->
              <?php if ($paymentStatus === 'confirmed'): ?>
                <button class="action-btn pay" disabled title="ชำระแล้ว">
                  ✅ ชำระแล้ว
                </button>
              <?php elseif ($paymentStatus === 'pending'): ?>
                <button class="action-btn pay" disabled title="รอตรวจสอบ">
                  ⏳ รอตรวจสอบ
                </button>
              <?php else: ?>
                <a
                  class="action-btn pay"
                  href="?page=payment&contract_id=<?= (int)$b['contract_id']; ?>"
                  title="ชำระเงินเต็มสัญญา"
                  style="text-decoration:none;">
                  💳 ชำระเงิน
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script nonce="<?= e(csp_nonce()); ?>">
  // minimal filter + cancel booking ajax
  (function() {
    "use strict";

    const statusFilter = document.getElementById("statusFilter");
    const textFilter = document.getElementById("textFilter");
    const resetBtn = document.getElementById("resetFilters");
    const cardsWrap = document.getElementById("bookingCards");
    const csrfToken = document.getElementById("csrfToken")?.value || "";

    function applyFilters() {
      if (!cardsWrap) return;
      const status = statusFilter?.value || "all";
      const q = (textFilter?.value || "").trim().toLowerCase();

      const cards = cardsWrap.querySelectorAll(".booking-card");
      cards.forEach((card) => {
        const st = card.getAttribute("data-status") || "";
        const title = (card.getAttribute("data-title") || "").toLowerCase();

        const okStatus = status === "all" ? true : st === status;
        const okText = q === "" ? true : title.includes(q);

        card.style.display = okStatus && okText ? "" : "none";
      });
    }

    statusFilter?.addEventListener("change", applyFilters);
    textFilter?.addEventListener("input", applyFilters);
    resetBtn?.addEventListener("click", () => {
      if (statusFilter) statusFilter.value = "all";
      if (textFilter) textFilter.value = "";
      applyFilters();
    });

    document.addEventListener("click", async (e) => {
      const btn = e.target.closest(".js-cancel-booking");
      if (!btn) return;

      const bookingId = parseInt(btn.getAttribute("data-booking-id") || "0", 10);
      if (!bookingId) return;

      if (!confirm("ยืนยันยกเลิกการจองนี้?")) return;

      btn.disabled = true;

      try {
        const fd = new FormData();
        fd.append("booking_id", String(bookingId));
        fd.append("_csrf", csrfToken);

        const res = await fetch("?page=history&action=cancel_booking", {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
          },
          body: fd,
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
          alert((data && data.message) ? data.message : "ยกเลิกไม่สำเร็จ");
          btn.disabled = false;
          return;
        }

        // simple UX: reload for fresh list
        window.location.reload();
      } catch (err) {
        console.error(err);
        alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
        btn.disabled = false;
      }
    });
  })();
</script>