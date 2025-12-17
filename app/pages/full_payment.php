<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user   = current_user();

if ($user === null) {
    if ($method === 'POST') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    if ($method === 'POST') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    redirect('?page=signin');
}

// POST: บันทึกการชำระเต็มจำนวนพร้อมสลิป
if ($method === 'POST' && isset($_POST['full_payment'])) {
    $bookingId  = (int) ($_POST['booking_id'] ?? 0);
    $propertyId = (int) ($_POST['property_id'] ?? 0);

    if ($bookingId <= 0 || $propertyId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ตรวจสอบ booking
    $booking = Database::fetchOne(
        'SELECT b.*, p.price AS annual_price FROM bookings b JOIN properties p ON p.id = b.property_id WHERE b.id = ? AND b.user_id = ? AND b.property_id = ?',
        [$bookingId, $userId, $propertyId]
    );

    if (!$booking) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ไม่พบบันทึกการจอง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ((string)$booking['booking_status'] !== 'approved') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ต้องได้รับการอนุมัติจากเจ้าของก่อนชำระเต็มจำนวน'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ยอดคงเหลือ = total_amount - deposit_amount
    $total   = (int) $booking['total_amount'];
    $deposit = (int) $booking['deposit_amount'];
    $remain  = max(0, $total - $deposit);

    // อัปโหลดสลิป
    $slipImagePath = null;
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = APP_PATH . '/public/storage/uploads/slips';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['slip_file'];
        $size = (int) $file['size'];
        if ($size > 5 * 1024 * 1024) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed, true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะรูปภาพ'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $newName = sprintf('fullpay_%d_%d_%s.%s', $userId, $propertyId, date('YmdHis'), $ext);
        $path    = $uploadDir . '/' . $newName;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $slipImagePath = '/storage/uploads/slips/' . $newName;
        }
    }

    // เพิ่มรายการชำระเงินเต็มจำนวนใน payments
    Database::execute(
        'INSERT INTO payments (booking_id, user_id, property_id, payment_type, amount, slip_image, status, created_at) VALUES (?, ?, ?, "full_payment", ?, ?, "pending", NOW())',
        [$bookingId, $userId, $propertyId, $remain, $slipImagePath]
    );

    // อัปเดตสถานะการชำระเงินของ booking
    Database::execute(
        'UPDATE bookings SET payment_status = "full_paid", updated_at = NOW() WHERE id = ?',
        [$bookingId]
    );

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'บันทึกการชำระเต็มจำนวนแล้ว รอการตรวจสอบ'], JSON_UNESCAPED_UNICODE);
    exit();
}

// GET: แสดงหน้า
$propertyId = (int) ($_GET['property_id'] ?? 0);
$bookingId  = (int) ($_GET['booking_id'] ?? 0);

if ($propertyId <= 0 || $bookingId <= 0) {
    redirect('?page=history');
}

$data = Database::fetchOne(
    'SELECT b.*, p.title, p.location, p.price AS annual_price FROM bookings b JOIN properties p ON p.id = b.property_id WHERE b.id = ? AND b.user_id = ? AND b.property_id = ?',
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

    const fd = new FormData();
    fd.append('full_payment', '1');
    fd.append('booking_id', String(BOOKING_ID));
    fd.append('property_id', String(PROPERTY_ID));
    fd.append('slip_file', fileInput.files[0]);

    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
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

document.getElementById('slipFile')?.addEventListener('change', function(){
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
