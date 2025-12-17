<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้ง include และเปิดตรง ๆ
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/NotificationService.php';
require_once APP_PATH . '/includes/NotificationService.php';

app_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// json_response ถูกประกาศไว้ใน helpers.php แล้ว ไม่ต้องประกาศซ้ำ

// ----------------------
// ตรวจสอบการล็อกอิน (แยก GET/POST)
// ----------------------
$user = current_user();

if ($user === null) {
    if ($method === 'POST') {
        json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
    }
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

$csrfToken = csrf_token();

// ----------------------
// POST: ยืนยันการชำระมัดจำ (AJAX)
// ----------------------
if ($method === 'POST' && isset($_POST['update_payment'])) {
    // CSRF
    $postedCsrf = (string) ($_POST['csrf'] ?? '');
    if (!verify_csrf($postedCsrf)) {
        json_response(['success' => false, 'message' => 'CSRF ไม่ถูกต้อง'], 403);
    }

    $propertyId  = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
    $bookingDate = trim((string) ($_POST['booking_date'] ?? ''));

    if ($propertyId <= 0 || $bookingDate === '') {
        json_response(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
    }

    // validate bookingDate รูปแบบ YYYY-MM-DD + เป็นวันจริง
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $bookingDate);
    $dtErrors = DateTimeImmutable::getLastErrors();
    if (!$dt || ($dtErrors['warning_count'] ?? 0) > 0 || ($dtErrors['error_count'] ?? 0) > 0) {
        json_response(['success' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง'], 400);
    }

    try {
        // อัปโหลดสลิป: ตรวจ ext + mime + เป็นรูปจริง + สุ่มชื่อไฟล์
        $slipImagePath = null;

        if (isset($_FILES['slip_file']) && ($_FILES['slip_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = APP_PATH . '/public/storage/uploads/slips';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmpName = (string) ($_FILES['slip_file']['tmp_name'] ?? '');
            $fileSize    = (int) ($_FILES['slip_file']['size'] ?? 0);
            $fileName    = (string) ($_FILES['slip_file']['name'] ?? '');

            if ($fileTmpName === '' || !is_uploaded_file($fileTmpName)) {
                json_response(['success' => false, 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง'], 400);
            }

            if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                json_response(['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'], 400);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedExtensions, true)) {
                json_response(['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)'], 400);
            }

            // ตรวจ MIME จาก finfo
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($fileTmpName) ?: '';
            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
            ];
            if (!in_array($mime, $allowedMimes, true)) {
                json_response(['success' => false, 'message' => 'ไฟล์ไม่ใช่รูปภาพที่รองรับ'], 400);
            }

            // ตรวจว่าเป็นรูปจริง (กันไฟล์ปลอม)
            if (@getimagesize($fileTmpName) === false) {
                json_response(['success' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'], 400);
            }

            $random = bin2hex(random_bytes(8));
            $newFileName = sprintf(
                'slip_%d_%d_%s_%s.%s',
                $userId,
                $propertyId,
                date('YmdHis'),
                $random,
                $fileExtension
            );

            $uploadPath = $uploadDir . '/' . $newFileName;

            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $slipImagePath = '/storage/uploads/slips/' . $newFileName;
            } else {
                app_log('slip_upload_failed', [
                    'user_id'     => $userId,
                    'property_id' => $propertyId,
                    'upload_path' => $uploadPath,
                ]);
                json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่'], 500);
            }
        } else {
            json_response(['success' => false, 'message' => 'กรุณาอัปโหลดสลิปก่อนยืนยัน'], 400);
        }

        // ทำธุรกรรม: เช็คสถานะ booking + เช็ค property + อัปเดตให้ atomic
        Database::transaction(function () use ($userId, $propertyId, $bookingDate, $slipImagePath) {
            // lock booking ล่าสุด
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
                FOR UPDATE
                ',
                [$userId, $propertyId, $bookingDate]
            );

            if (!$booking) {
                json_response(['success' => false, 'message' => 'ไม่พบบันทึกการจองสำหรับอัปเดต'], 404);
            }

            if ((string) $booking['booking_status'] !== 'pending') {
                json_response(['success' => false, 'message' => 'สถานะการจองไม่อยู่ในสถานะ pending'], 400);
            }

            if ((string) $booking['payment_status'] !== 'waiting') {
                json_response(['success' => false, 'message' => 'สถานะการชำระเงินไม่อยู่ในสถานะรอชำระ'], 400);
            }

            // lock property ด้วย กัน race
            $prop = Database::fetchOne(
                'SELECT id, status, owner_id FROM properties WHERE id = ? LIMIT 1 FOR UPDATE',
                [$propertyId]
            );

            if (!$prop) {
                json_response(['success' => false, 'message' => 'ไม่พบข้อมูลพื้นที่'], 404);
            }

            if ((int) ($prop['owner_id'] ?? 0) === $userId) {
                json_response(['success' => false, 'message' => 'ไม่สามารถจองพื้นที่ของตัวเองได้'], 400);
            }

            $allowedStatuses = ['available', 'booked'];
            if (!in_array((string) ($prop['status'] ?? ''), $allowedStatuses, true)) {
                json_response(['success' => false, 'message' => 'พื้นที่ไม่อยู่ในสถานะที่จองได้'], 400);
            }

            // อัปเดต booking -> deposit_success + slip
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

            // ถ้า property ยัง available ให้ยกเป็น booked (กันคนอื่นแย่ง)
            if ((string) ($prop['status'] ?? '') === 'available') {
                Database::execute(
                    'UPDATE properties SET status = "booked", updated_at = NOW() WHERE id = ?',
                    [$propertyId]
                );
            }

            // แจ้งเตือนเจ้าของพื้นที่
            $propertyInfo = Database::fetchOne(
                'SELECT owner_id, title FROM properties WHERE id = ?',
                [$propertyId]
            );
            
            if ($propertyInfo) {
                NotificationService::notifyPaymentReceived(
                    (int)$propertyInfo['owner_id'],
                    (int)$booking['id'],
                    (float)$booking['deposit_amount']
                );
            }

            app_log('payment_update_success', [
                'user_id'      => $userId,
                'property_id'  => $propertyId,
                'booking_id'   => (int) $booking['id'],
                'booking_date' => $bookingDate,
                'slip_image'   => $slipImagePath,
            ]);
        });

        json_response([
            'success' => true,
            'message' => 'อัปเดตสถานะการชำระเงินเป็นมัดจำสำเร็จแล้ว',
        ]);
    } catch (Throwable $e) {
        app_log('payment_update_error', [
            'user_id'      => $userId,
            'property_id'  => $propertyId ?? null,
            'booking_date' => $bookingDate ?? null,
            'error'        => $e->getMessage(),
        ]);

        json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตการชำระเงิน'], 500);
    }
}

// ----------------------
// GET: แสดงหน้า payment
// ----------------------
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$day        = isset($_GET['day']) ? (int) $_GET['day'] : 0;
$month      = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0-based
$year       = isset($_GET['year']) ? (int) $_GET['year'] : 0;

// CSRF จาก detail (กันคนเดามือเปล่า)
$getCsrf = (string) ($_GET['csrf'] ?? '');
if (!verify_csrf($getCsrf)) {
    // ไม่ต้องโชว์ error ยาว ๆ ให้แฮกเกอร์อ่าน
    redirect('?page=detail&id=' . (int) $propertyId . '&error=csrf');
}

if ($propertyId <= 0 || $day <= 0 || $year <= 0) {
    redirect('?page=home');
}

// month 0..11
if ($month < 0 || $month > 11) {
    redirect('?page=detail&id=' . (int) $propertyId . '&error=month');
}

// validate วันที่เป็นวันจริง + ต้องเป็นอนาคต (>= พรุ่งนี้)
try {
    $selectedDate = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month + 1, $day));
    $today = new DateTimeImmutable('today');
    $minDate = $today->modify('+1 day');

    if ($selectedDate < $minDate) {
        redirect('?page=detail&id=' . (int) $propertyId . '&error=date');
    }
} catch (Throwable $e) {
    redirect('?page=detail&id=' . (int) $propertyId . '&error=date');
}

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

$buddhistYear = $year + 543;
$fullDate     = sprintf('%d %s %d', $day, $monthNames[$month], $buddhistYear);
$bookingDate  = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);

// ดึงข้อมูลพื้นที่
$item = Database::fetchOne(
    'SELECT id, owner_id, title, location, province, price, status, is_active FROM properties WHERE id = ?',
    [$propertyId]
);

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
if ((int) ($item['owner_id'] ?? 0) === $userId) {
    redirect('?page=detail&id=' . $propertyId . '&error=owner');
}

// ตรวจสอบสถานะพื้นที่ (ต้องยังว่าง/ติดจองเท่านั้น)
$allowedStatuses = ['available', 'booked'];
if (!in_array((string) ($item['status'] ?? ''), $allowedStatuses, true)) {
?>
    <div class="container">
        <h1>ไม่สามารถจองพื้นที่นี้ได้</h1>
        <p>สถานะปัจจุบัน: <?php echo e((string) ($item['status'] ?? '')); ?></p>
        <a href="?page=detail&id=<?php echo (int) $propertyId; ?>">กลับไปหน้ารายละเอียด</a>
    </div>
<?php
    exit();
}

// คำนวณมัดจำ
$annualPriceRaw = (int) ($item['price'] ?? 0);
$depositRaw     = max(0, (int) ceil($annualPriceRaw / 12)); // กันค่าติดลบ
$deposit        = number_format($depositRaw);

// สร้าง booking แบบ atomic + กันซ้ำ (ใช้ transaction + lock)
try {
    Database::transaction(function () use ($userId, $propertyId, $bookingDate, $depositRaw, $annualPriceRaw) {
        // lock property กัน race
        $prop = Database::fetchOne(
            'SELECT id, status, owner_id FROM properties WHERE id = ? LIMIT 1 FOR UPDATE',
            [$propertyId]
        );

        if (!$prop) {
            // ใช้ redirect ไม่ได้ใน transaction ง่าย ๆ -> โยน exception ให้ catch ข้างล่างจัด
            throw new RuntimeException('Property not found');
        }

        if ((int) ($prop['owner_id'] ?? 0) === $userId) {
            throw new RuntimeException('Owner cannot book own property');
        }

        $allowedStatuses = ['available', 'booked'];
        if (!in_array((string) ($prop['status'] ?? ''), $allowedStatuses, true)) {
            throw new RuntimeException('Property status invalid');
        }

        // มี booking อยู่แล้วไหม (ยังไม่ cancelled)
        $existingBooking = Database::fetchOne(
            '
            SELECT id, payment_status, booking_status
            FROM bookings
            WHERE user_id = ?
              AND property_id = ?
              AND booking_date = ?
              AND booking_status != "cancelled"
            ORDER BY created_at DESC
            LIMIT 1
            FOR UPDATE
            ',
            [$userId, $propertyId, $bookingDate]
        );

        if (!$existingBooking) {
            $bookingId = Database::execute(
                '
                INSERT INTO bookings
                    (user_id, property_id, booking_date, payment_status, booking_status, deposit_amount, total_amount, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, NOW())
                ',
                [$userId, $propertyId, $bookingDate, 'waiting', 'pending', $depositRaw, $annualPriceRaw]
            );
            
            // แจ้งเตือนเจ้าของพื้นที่
            $propertyTitle = Database::fetchOne(
                'SELECT title FROM properties WHERE id = ?',
                [$propertyId]
            );
            
            if ($propertyTitle && (int)($prop['owner_id'] ?? 0) > 0) {
                NotificationService::notifyNewBooking(
                    (int)$prop['owner_id'],
                    (int)Database::lastInsertId(),
                    (string)$propertyTitle['title']
                );
            }
        } else {
            // ถ้าเขามาเปิดซ้ำแล้วเคยจ่ายแล้ว ก็ไม่ควร “เหมือนเริ่มใหม่”
            $ps = (string) ($existingBooking['payment_status'] ?? '');
            if ($ps === 'deposit_success') {
                // ปล่อยให้ดูหน้าได้ แต่ปุ่มยืนยันจะโดน server block อยู่แล้ว (payment_status ไม่ใช่ waiting)
            }
        }

        // ถ้ายัง available ให้ set booked ตั้งแต่เริ่ม (กันคนอื่นมาเปิด payment แข่ง)
        if ((string) ($prop['status'] ?? '') === 'available') {
            Database::execute(
                'UPDATE properties SET status = "booked", updated_at = NOW() WHERE id = ?',
                [$propertyId]
            );
        }
    });
} catch (Throwable $e) {
    app_log('payment_create_booking_error', [
        'user_id' => $userId,
        'property_id' => $propertyId,
        'booking_date' => $bookingDate,
        'error' => $e->getMessage(),
    ]);
    redirect('?page=detail&id=' . (int) $propertyId . '&error=booking');
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
                    <span class="bl-value ref-code">#<?php echo str_pad((string) $propertyId, 6, '0', STR_PAD_LEFT); ?></span>
                </li>
                <li><span class="bl-label">พื้นที่:</span><span class="bl-value"><?php echo e((string) ($item['title'] ?? '')); ?></span></li>
                <li><span class="bl-label">ที่ตั้ง:</span><span class="bl-value"><?php echo e((string) ($item['location'] ?? '')); ?></span></li>
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
                <button type="button" class="btn-confirm-payment" onclick="confirmPayment()">ยืนยันการชำระเงิน</button>
                <button type="button" class="btn-cancel-payment" onclick="cancelPayment()">❌ ยกเลิกการจอง</button>
            </div>
        </section>
    </div>
</div>

<script>
    const PROPERTY_ID = <?php echo (int) $propertyId; ?>;
    const BOOKING_DATE = <?php echo json_encode($bookingDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    async function confirmPayment() {
        const slipInput = document.getElementById('slipFile');
        if (!slipInput?.files || slipInput.files.length === 0) {
            alert('กรุณาอัปโหลดสลิปการโอนก่อนยืนยัน');
            return;
        }

        if (!confirm('ยืนยันว่าคุณได้ชำระเงินและอัปโหลดสลิปเรียบร้อยแล้ว?')) return;

        try {
            const formData = new FormData();
            formData.append('update_payment', '1');
            formData.append('csrf', CSRF_TOKEN);
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
            } catch (e) {}

            if (data?.success) {
                alert('การจองเสร็จสมบูรณ์!\nระบบจะตรวจสอบสลิปและอนุมัติภายใน 5-10 นาที');
                window.location.href = '?page=history';
                return;
            }

            // มี message จาก server
            if (data?.message) {
                alert('ℹ️ ' + data.message);
                return;
            }

            // fallback
            if (!res.ok) {
                alert('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                return;
            }

            alert('บันทึกแล้ว (ระบบจะตรวจสอบสลิป)');
            window.location.href = '?page=history';
        } catch (err) {
            console.error('confirmPayment error:', err);
            alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }

    function cancelPayment() {
        // ตอนนี้แค่พาไป history (ยังไม่ได้ “ยกเลิกจริง” ใน DB)
        // ถ้าจะให้ยกเลิกจริง เดี๋ยวผมเพิ่ม endpoint cancel_booking ให้ได้
        if (confirm('คุณต้องการยกเลิกการจองใช่หรือไม่?')) {
            window.location.href = '?page=history';
        }
    }

    // Timer (client-side only)
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
            if (timeRemainingTextEl) timeRemainingTextEl.textContent = `${minutes} นาที`;

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