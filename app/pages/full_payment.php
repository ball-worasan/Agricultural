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
  app_log('full_payment_database_file_missing', ['file' => $databaseFile]);
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
  app_log('full_payment_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    json_response(['success' => false, 'message' => 'System error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
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
  app_log('full_payment_session_error', ['error' => $e->getMessage()]);
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

// ----------------------------
// POST: บันทึกการชำระเต็มจำนวนพร้อมสลิป
if ($method === 'POST' && isset($_POST['full_payment'])) {
  $bookingId  = (int) ($_POST['booking_id'] ?? 0);
  $propertyId = (int) ($_POST['property_id'] ?? 0);

  if ($bookingId <= 0 || $propertyId <= 0) {
    json_response(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
  }

  // ตรวจสอบ booking
  $booking = Database::fetchOne(
    'SELECT b.id, b.user_id, b.property_id, b.booking_date, b.payment_status, b.booking_status, b.deposit_amount, b.total_amount, p.price AS annual_price FROM bookings b JOIN properties p ON p.id = b.property_id WHERE b.id = ? AND b.user_id = ? AND b.property_id = ?',
    [$bookingId, $userId, $propertyId]
  );

  if (!$booking) {
    json_response(['success' => false, 'message' => 'ไม่พบบันทึกการจอง'], 404);
  }

  if ((string)$booking['booking_status'] !== 'approved') {
    json_response(['success' => false, 'message' => 'ต้องได้รับการอนุมัติจากเจ้าของก่อนชำระเต็มจำนวน'], 400);
  }

  // ยอดคงเหลือ = total_amount - deposit_amount
  $total   = (int) $booking['total_amount'];
  $deposit = (int) $booking['deposit_amount'];
  $remain  = max(0, $total - $deposit);

  try {
    // อัปโหลดสลิป
    $slipImagePath = null;
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = APP_PATH . '/public/storage/uploads/slips';
      if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
          json_response(['success' => false, 'message' => 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้'], 500);
        }
      }

      $file = $_FILES['slip_file'];
      $fileTmpName = (string) ($file['tmp_name'] ?? '');

      if ($fileTmpName === '' || !is_uploaded_file($fileTmpName)) {
        json_response(['success' => false, 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง'], 400);
      }

      $size = (int) $file['size'];
      if ($size <= 0 || $size > 5 * 1024 * 1024) {
        json_response(['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'], 400);
      }

      $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'รองรับเฉพาะรูปภาพ (jpg, jpeg, png, gif, webp)'], 400);
      }

      // ตรวจสอบ MIME type
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($fileTmpName) ?: '';
      $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!in_array($mime, $allowedMimes, true)) {
        json_response(['success' => false, 'message' => 'ไฟล์ไม่ใช่รูปภาพที่รองรับ'], 400);
      }

      // ตรวจสอบว่าเป็นรูปจริง
      if (@getimagesize($fileTmpName) === false) {
        json_response(['success' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'], 400);
      }

      $random = bin2hex(random_bytes(8));
      $newName = sprintf('fullpay_%d_%d_%s_%s.%s', $userId, $propertyId, date('YmdHis'), $random, $ext);
      $path    = $uploadDir . '/' . $newName;

      if (!move_uploaded_file($fileTmpName, $path)) {
        app_log('full_payment_upload_failed', [
          'user_id' => $userId,
          'property_id' => $propertyId,
          'upload_path' => $path,
        ]);
        json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่'], 500);
      }

      $slipImagePath = '/storage/uploads/slips/' . $newName;
    } else {
      json_response(['success' => false, 'message' => 'กรุณาอัปโหลดสลิปก่อนยืนยัน'], 400);
    }

    // ใช้ transaction เพื่อความปลอดภัย
    Database::transaction(function () use ($bookingId, $userId, $propertyId, $remain, $slipImagePath) {
      // เพิ่มรายการชำระเงินเต็มจำนวนใน payments
      Database::execute(
        'INSERT INTO payments (booking_id, user_id, property_id, payment_type, amount, slip_image, payment_status, payment_date, created_at) VALUES (?, ?, ?, "full_payment", ?, ?, "pending", CURDATE(), NOW())',
        [$bookingId, $userId, $propertyId, $remain, $slipImagePath]
      );

      // อัปเดตสถานะการชำระเงินของ booking
      Database::execute(
        'UPDATE bookings SET payment_status = "full_paid", updated_at = NOW() WHERE id = ?',
        [$bookingId]
      );
    });

    app_log('full_payment_success', [
      'user_id' => $userId,
      'property_id' => $propertyId,
      'booking_id' => $bookingId,
    ]);

    json_response(['success' => true, 'message' => 'บันทึกการชำระเต็มจำนวนแล้ว รอการตรวจสอบ']);
  } catch (Throwable $e) {
    app_log('full_payment_error', [
      'user_id' => $userId,
      'property_id' => $propertyId ?? null,
      'booking_id' => $bookingId ?? null,
      'error' => $e->getMessage(),
    ]);

    json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกการชำระเงิน'], 500);
  }
}

// GET: แสดงหน้า
$propertyId = (int) ($_GET['property_id'] ?? 0);
$bookingId  = (int) ($_GET['booking_id'] ?? 0);

if ($propertyId <= 0 || $bookingId <= 0) {
  redirect('?page=history');
}

$data = Database::fetchOne(
  'SELECT b.id, b.user_id, b.property_id, b.booking_date, b.payment_status, b.booking_status, b.deposit_amount, b.total_amount, p.title, p.location, p.price AS annual_price FROM bookings b JOIN properties p ON p.id = b.property_id WHERE b.id = ? AND b.user_id = ? AND b.property_id = ?',
  [$bookingId, $userId, $propertyId]
);

if (!$data) {
?>
  <div class="container">
    <h1>ไม่พบข้อมูลการจอง</h1>
    <a href="?page=history">กลับประวัติ</a>
  </div>
<?php
  exit();
}

$total   = (int) $data['total_amount'];
$deposit = (int) $data['deposit_amount'];
$remain  = max(0, $total - $deposit);

?>

<div class="fullpay-container">
  <a href="?page=history" class="back-button minimal">ย้อนกลับ</a>
  <header class="fp-header">
    <h1>ชำระเงินเต็มจำนวน</h1>
    <p>ยอดคงเหลือหลังหักมัดจำ</p>
  </header>

  <section class="fp-section">
    <ul class="fp-list">
      <li><span class="label">พื้นที่:</span> <span class="value"><?php echo e($data['title']); ?></span></li>
      <li><span class="label">ที่ตั้ง:</span> <span class="value"><?php echo e($data['location']); ?></span></li>
      <li><span class="label">ยอดรวมปี:</span> <span class="value price">฿<?php echo number_format($total); ?></span></li>
      <li><span class="label">มัดจำแล้ว:</span> <span class="value price">฿<?php echo number_format($deposit); ?></span></li>
      <li><span class="label">ยอดคงเหลือ:</span> <span class="value price emphasis">฿<?php echo number_format($remain); ?></span></li>
    </ul>
  </section>

  <section class="fp-section">
    <h2 class="fp-subtitle">อัปโหลดสลิปการโอน</h2>
    <div class="fp-upload">
      <input type="file" id="slipFile" accept="image/*">
      <div id="slipPreview" class="fp-preview" hidden></div>
    </div>
    <div class="fp-actions">
      <button type="button" class="btn-primary" onclick="submitFullPayment()">ยืนยันการชำระ</button>
    </div>
  </section>
</div>

<script>
  const BOOKING_ID = <?php echo (int) $bookingId; ?>;
  const PROPERTY_ID = <?php echo (int) $propertyId; ?>;

  async function submitFullPayment() {
    const fileInput = document.getElementById('slipFile');
    if (!fileInput.files || fileInput.files.length === 0) {
      alert('กรุณาอัปโหลดสลิปก่อน');
      return;
    }

    if (!confirm('ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?')) {
      return;
    }

    const fd = new FormData();
    fd.append('full_payment', '1');
    fd.append('booking_id', String(BOOKING_ID));
    fd.append('property_id', String(PROPERTY_ID));
    fd.append('slip_file', fileInput.files[0]);

    try {
      const res = await fetch(window.location.href, {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (data.success) {
        alert('บันทึกการชำระเต็มจำนวนแล้ว รอการตรวจสอบ');
        window.location.href = '?page=history';
      } else {
        alert(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch (e) {
      alert('ส่งข้อมูลไม่สำเร็จ');
    }
  }

  document.getElementById('slipFile')?.addEventListener('change', function() {
    const preview = document.getElementById('slipPreview');
    if (!preview) return;
    preview.innerHTML = '';
    if (this.files && this.files[0]) {
      const f = this.files[0];
      if (f.size > 5 * 1024 * 1024) {
        alert('ไฟล์มีขนาดเกิน 5MB');
        this.value = '';
        preview.hidden = true;
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '220px';
        img.style.borderRadius = '6px';
        preview.appendChild(img);
      };
      reader.readAsDataURL(f);
      preview.hidden = false;
    } else {
      preview.hidden = true;
    }
  });
</script>