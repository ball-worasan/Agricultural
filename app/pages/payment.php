<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้ง include และเปิดตรง ๆ
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------
// helper JSON response (กันประกาศซ้ำ)
// ----------------------
if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// ----------------------
// ตรวจสอบการล็อกอิน (แยก GET/POST)
// ----------------------
$user = current_user();

if ($user === null) {
    if ($method === 'POST') {
        json_response([
            'success' => false,
            'message' => 'กรุณาเข้าสู่ระบบ',
        ], 401);
    }

    // GET ปล่อยให้ไปหน้า signin ปกติ
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('payment_invalid_user', ['session_user' => $user]);

    if ($method === 'POST') {
        json_response([
            'success' => false,
            'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง',
        ], 401);
    }

    redirect('?page=signin');
}

// ----------------------
// จัดการ POST: ยืนยันการชำระมัดจำ (AJAX)
// ----------------------
if ($method === 'POST' && isset($_POST['update_payment'])) {
    $propertyId  = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
    $bookingDate = trim((string) ($_POST['booking_date'] ?? ''));

    if ($propertyId <= 0 || $bookingDate === '') {
        json_response([
            'success' => false,
            'message' => 'ข้อมูลคำขอไม่ถูกต้อง',
        ], 400);
    }

    try {
        // จัดการอัปโหลดสลิป
        $slipImagePath = null;

        if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = APP_PATH . '/public/storage/uploads/slips';
            
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['slip_file'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];

            // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
            if ($fileSize > 5 * 1024 * 1024) {
                json_response([
                    'success' => false,
                    'message' => 'ไฟล์มีขนาดเกิน 5MB',
                ], 400);
            }

            // ตรวจสอบนามสกุลไฟล์
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedExtensions, true)) {
                json_response([
                    'success' => false,
                    'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)',
                ], 400);
            }

            // สร้างชื่อไฟล์ใหม่ป้องกันชื่อซ้ำ
            $newFileName = sprintf(
                'slip_%d_%d_%s.%s',
                $userId,
                $propertyId,
                date('YmdHis'),
                $fileExtension
            );

            $uploadPath = $uploadDir . '/' . $newFileName;

            // ย้ายไฟล์
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $slipImagePath = '/storage/uploads/slips/' . $newFileName;
            } else {
                app_log('slip_upload_failed', [
                    'user_id'     => $userId,
                    'property_id' => $propertyId,
                    'upload_path' => $uploadPath,
                ]);
            }
        }

        // หา booking ล่าสุดของ user-พื้นที่-วันที่นี้ ที่ยังไม่ยกเลิก
        $booking = Database::fetchOne(
            '
            SELECT id, payment_status, booking_status 
            FROM bookings
            WHERE user_id = ? 
              AND property_id = ? 
              AND booking_date = ?
              AND booking_status != "cancelled"
            ORDER BY created_at DESC
            LIMIT 1
            ',
            [$userId, $propertyId, $bookingDate]
        );

        if (!$booking) {
            json_response([
                'success' => false,
                'message' => 'ไม่พบบันทึกการจองสำหรับอัปเดต',
            ], 404);
        }

        if ((string) $booking['payment_status'] !== 'waiting') {
            json_response([
                'success' => false,
                'message' => 'สถานะการชำระเงินไม่อยู่ในสถานะรอชำระ',
            ], 400);
        }

        // อัปเดตสถานะและสลิป (ถ้ามี)
        if ($slipImagePath) {
            Database::execute(
                '
                UPDATE bookings
                SET payment_status = "deposit_success",
                    slip_image = ?,
                    updated_at = NOW()
                WHERE id = ?
                ',
                [$slipImagePath, (int) $booking['id']]
            );
        } else {
            Database::execute(
                '
                UPDATE bookings
                SET payment_status = "deposit_success",
                    updated_at = NOW()
                WHERE id = ?
                ',
                [(int) $booking['id']]
            );
        }

        app_log('payment_update_success', [
            'user_id'      => $userId,
            'property_id'  => $propertyId,
            'booking_id'   => $booking['id'],
            'booking_date' => $bookingDate,
            'slip_image'   => $slipImagePath,
        ]);

        json_response([
            'success' => true,
            'message' => 'อัปเดตสถานะการชำระเงินเป็นมัดจำสำเร็จแล้ว',
        ]);
    } catch (Throwable $e) {
        app_log('payment_update_error', [
            'user_id'      => $userId,
            'property_id'  => $propertyId,
            'booking_date' => $bookingDate,
            'error'        => $e->getMessage(),
        ]);

        json_response([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการอัปเดตการชำระเงิน',
        ], 500);
    }
}

// ----------------------
// จากตรงนี้เป็น GET: แสดงหน้า payment
// ----------------------

// รับพารามิเตอร์
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$day        = isset($_GET['day']) ? (int) $_GET['day'] : 0;
$month      = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0-based
$year       = isset($_GET['year']) ? (int) $_GET['year'] : 0;

if ($propertyId <= 0 || $day <= 0 || $year <= 0) {
    redirect('?page=home');
}

// ดึงข้อมูลพื้นที่
$item = Database::fetchOne('SELECT * FROM properties WHERE id = ?', [$propertyId]);

if (!$item) {
?>
    <div class="container">
        <h1>ไม่พบข้อมูลพื้นที่</h1>
        <a href="?page=home">กลับหน้าหลัก</a>
    </div>
<?php
    exit();
}

// ห้ามเจ้าของจองพื้นที่ตัวเอง
if ((int) $item['owner_id'] === $userId) {
    redirect('?page=detail&id=' . $propertyId . '&error=owner');
}

// ตรวจสอบสถานะพื้นที่ (ต้องยังว่าง/ติดจองเท่านั้น)
$allowedStatuses = ['available', 'booked'];
if (!in_array((string) $item['status'], $allowedStatuses, true)) {
?>
    <div class="container">
        <h1>ไม่สามารถจองพื้นที่นี้ได้</h1>
        <p>สถานะปัจจุบัน: <?php echo e((string) $item['status']); ?></p>
        <a href="?page=detail&id=<?php echo (int) $propertyId; ?>">กลับไปหน้ารายละเอียด</a>
    </div>
<?php
    exit();
}

// สร้างวันที่แบบไทย + booking_date
$monthNames = [
    'มกราคม',
    'กุมภาพันธ์',
    'มีนาคม',
    'เมษายน',
    'พฤษภาคม',
    'มิถุนายน',
    'กรกฎาคม',
    'สิงหาคม',
    'กันยายน',
    'ตุลาคม',
    'พฤศจิกายน',
    'ธันวาคม',
];

if (!isset($monthNames[$month])) {
    // กันกรณีค่า month เพี้ยน
    $month = 0;
}

$buddhistYear = $year + 543;
$fullDate     = sprintf('%d %s %d', $day, $monthNames[$month], $buddhistYear);
$bookingDate  = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);

// คำนวณมัดจำ
$annualPriceRaw = (int) $item['price'];
$depositRaw     = max(0, (int) ceil($annualPriceRaw / 12)); // กันค่าติดลบ
$deposit        = number_format($depositRaw);

// ตรวจสอบว่ามีการจองนี้อยู่แล้วหรือยัง
$existingBooking = Database::fetchOne(
    '
    SELECT id 
    FROM bookings 
    WHERE user_id = ? 
      AND property_id = ? 
      AND booking_date = ? 
      AND booking_status != "cancelled"
    ORDER BY created_at DESC
    LIMIT 1
    ',
    [$userId, $propertyId, $bookingDate]
);

// ถ้ายังไม่มี ให้สร้างการจองใหม่
if (!$existingBooking) {
    Database::execute(
        '
        INSERT INTO bookings 
            (user_id, property_id, booking_date, payment_status, booking_status, deposit_amount, total_amount, created_at)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, NOW())
        ',
        [$userId, $propertyId, $bookingDate, 'waiting', 'pending', $depositRaw, $annualPriceRaw]
    );
}
?>

<div class="payment-container">
    <a href="?page=detail&id=<?php echo (int) $propertyId; ?>" class="back-button minimal">ย้อนกลับ</a>

    <header class="payment-header compact" role="banner">
        <h1 class="payment-title">ชำระมัดจำเช่าพื้นที่เกษตร</h1>
        <p class="payment-subtitle">กรุณาชำระภายใน 60 นาที และอัปโหลดสลิป</p>
    </header>

    <div class="payment-grid">
        <section class="payment-section" aria-labelledby="bookingHeading">
            <h2 id="bookingHeading" class="section-heading">ข้อมูลการจอง</h2>
            <ul class="booking-list" role="list">
                <li>
                    <span class="bl-label">รหัส:</span>
                    <span class="bl-value ref-code">
                        #<?php echo str_pad((string) $propertyId, 6, '0', STR_PAD_LEFT); ?>
                    </span>
                </li>
                <li><span class="bl-label">พื้นที่:</span><span class="bl-value"><?php echo e($item['title']); ?></span></li>
                <li><span class="bl-label">ที่ตั้ง:</span><span class="bl-value"><?php echo e($item['location']); ?></span></li>
                <li><span class="bl-label">วันที่นัด:</span><span class="bl-value"><?php echo e($fullDate); ?></span></li>
                <li class="deposit-row">
                    <span class="bl-label">มัดจำ:</span>
                    <span class="bl-value price">฿<?php echo e($deposit); ?></span>
                </li>
            </ul>
            <div class="inline-note">* มัดจำเท่ากับค่าเช่าเดือนแรก</div>
        </section>

        <section class="payment-section" aria-labelledby="payHeading">
            <h2 id="payHeading" class="section-heading">ช่องทางชำระเงิน</h2>

            <div class="qr-box">
                <img
                    src="https://promptpay.io/0641365430/<?php echo (int) $depositRaw; ?>.png"
                    alt="QR PromptPay"
                    class="qr-img"
                    loading="lazy">
            </div>

            <div class="pay-meta">
                <div><span class="meta-label">PromptPay:</span> <span class="meta-value">064-136-5430</span></div>
                <div><span class="meta-label">จำนวนเงิน:</span> <span class="meta-value price">฿<?php echo e($deposit); ?></span></div>
                <div><span class="meta-label">เวลาคงเหลือ:</span> <span class="meta-value" id="timeRemaining">60:00</span></div>
            </div>

            <div class="upload-slip clean">
                <label for="slipFile" class="upload-label">📎 อัปโหลดสลิปการโอน</label>
                <input type="file" id="slipFile" accept="image/*" class="upload-input">
                <div id="slipPreview" class="slip-preview" hidden></div>
            </div>

            <div class="quick-hints">
                <small>
                    💡 กรุณาชำระเงินภายใน
                    <strong id="timeRemainingText">60 นาที</strong>
                    และอัปโหลดสลิปเพื่อยืนยันการจอง
                </small>
            </div>

            <div class="action-row">
                <button type="button" class="btn-confirm-payment" onclick="confirmPayment()">✅ ยืนยันการชำระเงิน</button>
                <button type="button" class="btn-cancel-payment" onclick="cancelPayment()">❌ ยกเลิกการจอง</button>
            </div>
        </section>
    </div>
</div>

<script>
    const PROPERTY_ID = <?php echo (int) $propertyId; ?>;
    const BOOKING_DATE = '<?php echo $bookingDate; ?>';

    async function confirmPayment() {
        const slipInput = document.getElementById('slipFile');
        if (!slipInput.files || slipInput.files.length === 0) {
            alert('กรุณาอัปโหลดสลิปการโอนก่อนยืนยัน');
            return;
        }

        if (!confirm('ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('update_payment', '1');
            formData.append('property_id', String(PROPERTY_ID));
            formData.append('booking_date', BOOKING_DATE);
            formData.append('slip_file', slipInput.files[0]);

            const res = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            let data = null;
            try {
                data = await res.json();
            } catch (e) {
                // ถ้า parse JSON ไม่ได้ แต่ status OK ก็ถือว่าโอเค
            }

            if (data && data.success) {
                alert('✅ การจองเสร็จสมบูรณ์!\nระบบจะตรวจสอบสลิปและอนุมัติภายใน 5-10 นาที');
            } else if (data && data.message) {
                alert('ℹ️ ' + data.message);
            } else {
                alert('✅ การจองบันทึกแล้ว (สถานะจะอัปเดตเมื่อระบบตรวจสอบสลิป)');
            }

            window.location.href = '?page=history';
        } catch (err) {
            console.error('confirmPayment error:', err);
            alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }

    function cancelPayment() {
        if (confirm('คุณต้องการยกเลิกการจองใช่หรือไม่?')) {
            // ยกเลิกผ่าน flow หน้า history
            window.location.href = '?page=history';
        }
    }

    // Timer
    (function() {
        let timeLeft = 60 * 60; // 60 นาที
        const timeRemainingEl = document.getElementById('timeRemaining');
        const timeRemainingTextEl = document.getElementById('timeRemainingText');

        if (!timeRemainingEl) return;

        const countdown = setInterval(() => {
            timeLeft--;

            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const mmss = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

            timeRemainingEl.textContent = mmss;
            if (timeRemainingTextEl) {
                timeRemainingTextEl.textContent = `${minutes} นาที`;
            }

            if (timeLeft <= 0) {
                clearInterval(countdown);
                alert('หมดเวลาชำระเงิน การจองอาจถูกยกเลิกโดยอัตโนมัติ');
                window.location.href = '?page=history';
            } else if (timeLeft <= 60) {
                timeRemainingEl.style.color = 'var(--status-sold-text)';
            } else if (timeLeft <= 300) {
                timeRemainingEl.style.color = 'var(--status-booked-text)';
            }
        }, 1000);
    })();

    // Slip preview
    document.getElementById('slipFile')?.addEventListener('change', function() {
        const preview = document.getElementById('slipPreview');
        if (!preview) return;

        preview.innerHTML = '';
        if (this.files && this.files[0]) {
            const file = this.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert('ไฟล์มีขนาดเกิน 5MB');
                this.value = '';
                preview.hidden = true;
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '200px';
                img.style.borderRadius = '6px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
            preview.hidden = false;
        } else {
            preview.hidden = true;
        }
    });
</script>