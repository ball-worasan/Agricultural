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
  app_log('payment_verification_database_file_missing', ['file' => $databaseFile]);
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
  app_log('payment_verification_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'System error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
  return;
}

$paymentServiceFile = APP_PATH . '/includes/PaymentService.php';
if (!is_file($paymentServiceFile)) {
  app_log('payment_verification_service_missing', ['file' => $paymentServiceFile]);
  // Continue without payment service
}

require_once $databaseFile;
require_once $helpersFile;
if (is_file($paymentServiceFile)) {
  require_once $paymentServiceFile;
}

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('payment_verification_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'Session error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  }
  return;
}

// ----------------------------
// Check admin authentication
// ----------------------------
$user = current_user();
if ($user === null || ($user['role'] ?? 0) !== ROLE_ADMIN) {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'Unauthorized'], 403);
  } else {
    flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('?page=signin');
  }
  return;
}

// ----------------------------
// Get request method and admin ID
// ----------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$adminId = (int) ($user['id'] ?? 0);

// POST: อนุมัติ/ปฏิเสธการชำระเงิน
if ($method === 'POST' && isset($_POST['verify_payment'])) {
  csrf_require();

  $paymentId = (int) ($_POST['payment_id'] ?? 0);
  $approved = isset($_POST['approved']) && $_POST['approved'] === '1';
  $reason = trim((string) ($_POST['reason'] ?? ''));

  if ($paymentId <= 0) {
    json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
  }

  if (!$approved && $reason === '') {
    json_response(['success' => false, 'message' => 'กรุณาระบุเหตุผลในการปฏิเสธ'], 400);
  }

  if (PaymentService::verifyPayment($paymentId, $adminId, $approved, $reason ?: null)) {
    json_response([
      'success' => true,
      'message' => $approved ? 'อนุมัติการชำระเงินเรียบร้อยแล้ว' : 'ปฏิเสธการชำระเงินเรียบร้อยแล้ว',
    ]);
  } else {
    json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดำเนินการ'], 500);
  }
}

// GET: แสดงรายการรอตรวจสอบ
try {
  $pendingPayments = Database::fetchAll(
    'SELECT 
            p.payment_id, p.contract_id, p.amount, 
            p.slip_image, p.payment_date, p.created_at, p.status,
            u.user_id, u.full_name, u.phone,
            ra.area_id, ra.area_name,
            bd.booking_id, bd.booking_date
         FROM payment p
         JOIN contract c ON p.contract_id = c.contract_id
         JOIN booking_deposit bd ON c.booking_id = bd.booking_id
         JOIN users u ON bd.user_id = u.user_id
         JOIN rental_area ra ON bd.area_id = ra.area_id
         WHERE p.status = "pending"
         ORDER BY p.created_at DESC'
  );
} catch (Throwable $e) {
  app_log('payment_verification_fetch_error', ['error' => $e->getMessage()]);
  $pendingPayments = [];
}

?>
<div class="payment-verification-container">
  <div class="page-header">
    <h1>ตรวจสอบการชำระเงิน</h1>
    <a href="?page=admin_dashboard" class="back-link">← กลับแดชบอร์ด</a>
  </div>

  <?php if (empty($pendingPayments)): ?>
    <div class="empty-state">
      <p>ไม่มีรายการรอตรวจสอบ</p>
    </div>
  <?php else: ?>
    <div class="payments-list">
      <?php foreach ($pendingPayments as $payment): ?>
        <div class="payment-card">
          <div class="payment-header">
            <div class="payment-info">
              <h3>การชำระเงิน #<?= e((string)$payment['id']); ?></h3>
              <p><strong>ประเภท:</strong> <?= e($payment['payment_type']); ?></p>
              <p><strong>จำนวน:</strong> ฿<?= number_format((float)$payment['amount'], 2); ?></p>
              <p><strong>ผู้ชำระ:</strong> <?= e($payment['full_name']); ?></p>
              <p><strong>พื้นที่:</strong> <?= e($payment['property_title']); ?></p>
              <p><strong>วันที่ชำระ:</strong> <?= date('d/m/Y', strtotime($payment['payment_date'])); ?></p>
            </div>
            <?php if ($payment['slip_image']): ?>
              <div class="slip-preview">
                <img src="<?= e($payment['slip_image']); ?>" alt="สลิปการโอน" onclick="showSlipModal('<?= e($payment['slip_image']); ?>')">
              </div>
            <?php endif; ?>
          </div>
          <div class="payment-actions">
            <form method="POST" class="verify-form" onsubmit="return verifyPayment(event, <?= (int)$payment['id']; ?>, true)">
              <input type="hidden" name="verify_payment" value="1">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="payment_id" value="<?= (int)$payment['id']; ?>">
              <input type="hidden" name="approved" value="1">
              <button type="submit" class="btn-approve">✓ อนุมัติ</button>
            </form>
            <form method="POST" class="verify-form" onsubmit="return verifyPayment(event, <?= (int)$payment['id']; ?>, false)">
              <input type="hidden" name="verify_payment" value="1">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="payment_id" value="<?= (int)$payment['id']; ?>">
              <input type="hidden" name="approved" value="0">
              <input type="text" name="reason" placeholder="เหตุผลในการปฏิเสธ" required class="reject-reason">
              <button type="submit" class="btn-reject">✗ ปฏิเสธ</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="slipModal" class="modal" onclick="closeModal(event)">
  <div class="modal-content">
    <button type="button" class="modal-close" onclick="closeModal(event)">×</button>
    <img id="slipModalImage" src="" alt="สลิปการโอน">
  </div>
</div>

<script>
  function showSlipModal(imageUrl) {
    const modal = document.getElementById('slipModal');
    const img = document.getElementById('slipModalImage');
    if (modal && img) {
      img.src = imageUrl;
      modal.style.display = 'block';
    }
  }

  function closeModal(event) {
    if (event.target.id === 'slipModal' || event.target.classList.contains('modal-close')) {
      document.getElementById('slipModal').style.display = 'none';
    }
  }

  async function verifyPayment(event, paymentId, approved) {
    event.preventDefault();

    const form = event.target.closest('form');
    if (!form) return false;

    if (!approved) {
      const reason = form.querySelector('input[name="reason"]').value.trim();
      if (!reason) {
        alert('กรุณาระบุเหตุผลในการปฏิเสธ');
        return false;
      }
    }

    if (!confirm(approved ? 'ยืนยันการอนุมัติการชำระเงินนี้?' : 'ยืนยันการปฏิเสธการชำระเงินนี้?')) {
      return false;
    }

    try {
      const formData = new FormData(form);
      const res = await fetch(window.location.href, {
        method: 'POST',
        body: formData
      });

      const data = await res.json();

      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'));
      }
    } catch (err) {
      console.error('verifyPayment error:', err);
      alert('เกิดข้อผิดพลาดในการส่งข้อมูล');
    }

    return false;
  }
</script>