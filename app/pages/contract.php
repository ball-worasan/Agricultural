<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Helper for JSON response
if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// ตรวจสอบการล็อกอิน
$user = current_user();
if ($user === null) {
    if ($method === 'POST') {
        json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
    }
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    if ($method === 'POST') {
        json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
    }
    redirect('?page=signin');
}

// ----------------------
// POST: สร้างหรืออัปเดตสัญญา
// ----------------------
if ($method === 'POST' && isset($_POST['create_contract'])) {
    $bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
    $rentalPeriodMonths = isset($_POST['rental_period_months']) ? (int) $_POST['rental_period_months'] : 12;
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $termsAndConditions = trim((string) ($_POST['terms_and_conditions'] ?? ''));

    if ($bookingId <= 0 || $startDate === '') {
        json_response(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน'], 400);
    }

    try {
        // ตรวจสอบว่า booking นี้เป็นของผู้ใช้จริงและอนุมัติแล้ว
        $booking = Database::fetchOne(
            '
            SELECT b.*, p.owner_id, p.title AS property_title, p.price 
            FROM bookings b
            JOIN properties p ON b.property_id = p.id
            WHERE b.id = ? AND b.user_id = ? AND b.booking_status = "approved"
            ',
            [$bookingId, $userId]
        );

        if (!$booking) {
            json_response(['success' => false, 'message' => 'ไม่พบการจองหรือยังไม่ได้รับการอนุมัติ'], 404);
        }

        // ตรวจสอบว่ามีสัญญาอยู่แล้วหรือไม่
        $existingContract = Database::fetchOne(
            'SELECT id FROM contracts WHERE booking_id = ?',
            [$bookingId]
        );

        if ($existingContract) {
            json_response(['success' => false, 'message' => 'สัญญานี้ถูกสร้างไว้แล้ว'], 400);
        }

        $propertyId = (int) $booking['property_id'];
        $ownerId = (int) $booking['owner_id'];
        $depositAmount = (float) $booking['deposit_amount'];
        $totalAmount = (float) $booking['total_amount'];
        
        // คำนวณค่าเช่าต่อเดือน
        $monthlyRent = $rentalPeriodMonths > 0 ? ceil($totalAmount / $rentalPeriodMonths) : 0;

        // คำนวณวันสิ้นสุดสัญญา
        $startDateObj = new DateTimeImmutable($startDate);
        $endDateObj = $startDateObj->modify("+{$rentalPeriodMonths} months");
        $endDate = $endDateObj->format('Y-m-d');

        // สร้างเลขที่สัญญา
        $contractNumber = sprintf('CON-%04d-%s', $bookingId, date('Ymd'));

        // จัดการอัปโหลดไฟล์สัญญา (ถ้ามี)
        $contractFile = null;
        if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = APP_PATH . '/public/storage/uploads/contracts';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['contract_file'];
            $allowedTypes = ['application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10MB

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                json_response(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ PDF'], 400);
            }

            if ($file['size'] > $maxSize) {
                json_response(['success' => false, 'message' => 'ไฟล์ใหญ่เกิน 10MB'], 400);
            }

            $newFileName = sprintf(
                'contract_%d_%s.pdf',
                $bookingId,
                date('YmdHis')
            );

            $destination = $uploadDir . '/' . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $contractFile = '/storage/uploads/contracts/' . $newFileName;
            }
        }

        // สร้างสัญญา
        Database::execute(
            '
            INSERT INTO contracts (
                booking_id, user_id, property_id, owner_id,
                contract_number, rental_period_months,
                start_date, end_date,
                monthly_rent, deposit_amount, total_amount,
                terms_and_conditions, contract_file,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ',
            [
                $bookingId, $userId, $propertyId, $ownerId,
                $contractNumber, $rentalPeriodMonths,
                $startDate, $endDate,
                $monthlyRent, $depositAmount, $totalAmount,
                $termsAndConditions, $contractFile,
                'draft'
            ]
        );

        app_log('contract_created', [
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'contract_number' => $contractNumber,
        ]);

        json_response([
            'success' => true,
            'message' => 'สร้างสัญญาเรียบร้อยแล้ว',
            'contract_number' => $contractNumber,
        ]);
    } catch (Throwable $e) {
        app_log('contract_create_error', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage(),
        ]);

        json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างสัญญา'], 500);
    }
}

// ----------------------
// GET: แสดงหน้าสร้างสัญญา
// ----------------------
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;

if ($bookingId <= 0) {
    redirect('?page=history');
}

// ดึงข้อมูล booking
$booking = Database::fetchOne(
    '
    SELECT 
        b.*,
        p.title AS property_title,
        p.location,
        p.province,
        p.price,
        p.area_rai,
        p.area_ngan,
        p.area_sqwa,
        p.category,
        p.owner_id,
        u.firstname AS owner_firstname,
        u.lastname AS owner_lastname,
        u.email AS owner_email,
        u.phone AS owner_phone
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u ON p.owner_id = u.id
    WHERE b.id = ? AND b.user_id = ? AND b.booking_status = "approved"
    ',
    [$bookingId, $userId]
);

if (!$booking) {
    flash('error', 'ไม่พบการจองหรือยังไม่ได้รับการอนุมัติ');
    redirect('?page=history');
}

// ตรวจสอบว่ามีสัญญาอยู่แล้วหรือไม่
$existingContract = Database::fetchOne(
    'SELECT * FROM contracts WHERE booking_id = ?',
    [$bookingId]
);

if ($existingContract) {
    // ถ้ามีสัญญาแล้ว redirect ไปหน้าดูสัญญา
    redirect('?page=view_contract&id=' . (int) $existingContract['id']);
}

$propertyTitle = $booking['property_title'] ?? '';
$location = $booking['location'] ?? '';
$province = $booking['province'] ?? '';
$annualPrice = (float) ($booking['price'] ?? 0);
$depositAmount = (float) ($booking['deposit_amount'] ?? 0);
$totalAmount = (float) ($booking['total_amount'] ?? 0);

$ownerName = trim(($booking['owner_firstname'] ?? '') . ' ' . ($booking['owner_lastname'] ?? ''));
$ownerEmail = $booking['owner_email'] ?? '';
$ownerPhone = $booking['owner_phone'] ?? '';

// ข้อมูลผู้เช่า
$tenantName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$tenantEmail = $user['email'] ?? '';
$tenantPhone = $user['phone'] ?? '';

// ข้อมูลพื้นที่
$areaText = '';
if (!empty($booking['area_rai']) || !empty($booking['area_ngan']) || !empty($booking['area_sqwa'])) {
    $parts = [];
    if (!empty($booking['area_rai'])) $parts[] = $booking['area_rai'] . ' ไร่';
    if (!empty($booking['area_ngan'])) $parts[] = $booking['area_ngan'] . ' งาน';
    if (!empty($booking['area_sqwa'])) $parts[] = $booking['area_sqwa'] . ' ตารางวา';
    $areaText = implode(' ', $parts);
}

$category = $booking['category'] ?? '';

// เงื่อนไขสัญญามาตรฐาน
$defaultTerms = "1. ผู้เช่าตกลงเช่าพื้นที่เกษตรเพื่อทำการเกษตรเท่านั้น

2. ผู้เช่าต้องดูแลรักษาพื้นที่ให้อยู่ในสภาพดี ไม่ทำลาย หรือเปลี่ยนแปลงโครงสร้างโดยไม่ได้รับอนุญาต

3. ค่าเช่าจะต้องชำระทุกวันที่ 1 ของเดือน หากชำระล่าช้าเกิน 7 วัน จะต้องเสียค่าปรับ 5% ต่อเดือน

4. หากผู้เช่าประสงค์จะเลิกสัญญาก่อนกำหนด ต้องแจ้งล่วงหน้า 30 วัน และเงินมัดจำจะไม่ถูกคืน

5. ผู้ให้เช่าสงวนสิทธิ์ในการเข้าตรวจสอบพื้นที่ได้โดยแจ้งล่วงหน้า 3 วัน

6. เมื่อสิ้นสุดสัญญา ผู้เช่าต้องส่งมอบพื้นที่คืนในสภาพเดิม มิฉะนั้นเงินมัดจำจะถูกหักเพื่อซ่อมแซม

7. การต่ออายุสัญญาต้องตกลงกันล่วงหน้าอย่างน้อย 60 วัน

8. กรณีเกิดเหตุสุดวิสัย เช่น ภัยธรรมชาติ คู่สัญญาทั้งสองฝ่ายจะไม่รับผิดชอบต่อกัน";

?>
<div class="contract-container">
    <div class="page-header">
        <a href="?page=history" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>กลับไปประวัติการเช่า</span>
        </a>
    </div>

    <div class="contract-header">
        <h1>สร้างสัญญาเช่าพื้นที่เกษตร</h1>
        <p class="subtitle">กรุณากรอกข้อมูลเพื่อสร้างสัญญาเช่า</p>
    </div>

    <form id="contractForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="create_contract" value="1">
        <input type="hidden" name="booking_id" value="<?= $bookingId; ?>">

        <div class="contract-sections">
            <!-- ข้อมูลพื้นที่ -->
            <div class="section-card">
                <h3>ข้อมูลพื้นที่เกษตรที่เช่า</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>ชื่อพื้นที่</label>
                        <p><?= e($propertyTitle); ?></p>
                    </div>
                    <div class="info-item">
                        <label>ที่ตั้ง</label>
                        <p><?= e($location . ', ' . $province); ?></p>
                    </div>
                    <?php if ($areaText): ?>
                        <div class="info-item">
                            <label>ขนาดพื้นที่</label>
                            <p><?= e($areaText); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($category): ?>
                        <div class="info-item">
                            <label>ประเภท</label>
                            <p><?= e($category); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ข้อมูลผู้ให้เช่า -->
            <div class="section-card">
                <h3>ข้อมูลผู้ให้เช่า</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>ชื่อ-นามสกุล</label>
                        <p><?= e($ownerName); ?></p>
                    </div>
                    <div class="info-item">
                        <label>อีเมล</label>
                        <p><?= e($ownerEmail); ?></p>
                    </div>
                    <div class="info-item">
                        <label>เบอร์โทร</label>
                        <p><?= e($ownerPhone); ?></p>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลผู้เช่า -->
            <div class="section-card">
                <h3>ข้อมูลผู้เช่า</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>ชื่อ-นามสกุล</label>
                        <p><?= e($tenantName); ?></p>
                    </div>
                    <div class="info-item">
                        <label>อีเมล</label>
                        <p><?= e($tenantEmail); ?></p>
                    </div>
                    <div class="info-item">
                        <label>เบอร์โทร</label>
                        <p><?= e($tenantPhone); ?></p>
                    </div>
                </div>
            </div>

            <!-- รายละเอียดสัญญา -->
            <div class="section-card">
                <h3>รายละเอียดการเช่า</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date">วันที่เริ่มสัญญา <span class="required">*</span></label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            min="<?= date('Y-m-d'); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="rental_period_months">ระยะเวลาเช่า (เดือน) <span class="required">*</span></label>
                        <select id="rental_period_months" name="rental_period_months" required>
                            <option value="6">6 เดือน</option>
                            <option value="12" selected>12 เดือน</option>
                            <option value="24">24 เดือน</option>
                            <option value="36">36 เดือน</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>ค่าเช่ารวมทั้งปี</label>
                        <p class="amount-display">฿<?= number_format($totalAmount); ?></p>
                    </div>

                    <div class="form-group full-width">
                        <label>เงินมัดจำที่ชำระแล้ว</label>
                        <p class="amount-display">฿<?= number_format($depositAmount); ?></p>
                    </div>
                </div>
            </div>

            <!-- เงื่อนไขสัญญา -->
            <div class="section-card">
                <h3>เงื่อนไขและข้อตกลง</h3>
                <div class="form-group">
                    <label for="terms_and_conditions">เงื่อนไขสัญญา</label>
                    <textarea
                        id="terms_and_conditions"
                        name="terms_and_conditions"
                        rows="12"><?= e($defaultTerms); ?></textarea>
                    <small>คุณสามารถแก้ไขเงื่อนไขตามความเหมาะสม</small>
                </div>
            </div>

            <!-- อัปโหลดไฟล์สัญญา (ถ้ามี) -->
            <div class="section-card">
                <h3>อัปโหลดไฟล์สัญญา (ถ้ามีการเตรียมไว้ล่วงหน้า)</h3>
                <div class="form-group">
                    <label for="contract_file">ไฟล์สัญญา PDF</label>
                    <input
                        type="file"
                        id="contract_file"
                        name="contract_file"
                        accept="application/pdf">
                    <small>รองรับเฉพาะไฟล์ PDF ขนาดไม่เกิน 10MB</small>
                </div>
            </div>

            <!-- ข้อตกลง -->
            <div class="section-card agreement-section">
                <label class="checkbox-label">
                    <input type="checkbox" id="agree" required>
                    <span>ข้าพเจ้ายอมรับเงื่อนไขและข้อตกลงทั้งหมดที่ระบุไว้ในสัญญานี้</span>
                </label>
            </div>

            <!-- ปุ่มดำเนินการ -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    สร้างสัญญา
                </button>
                <a href="?page=history" class="btn-cancel">ยกเลิก</a>
            </div>
        </div>
    </form>
</div>

<script>
    (function() {
        'use strict';

        const form = document.getElementById('contractForm');
        if (!form) return;

        // คำนวณวันสิ้นสุดสัญญาอัตโนมัติ
        const startDateInput = document.getElementById('start_date');
        const periodSelect = document.getElementById('rental_period_months');

        function updateEndDate() {
            const startDate = startDateInput.value;
            const months = parseInt(periodSelect.value, 10);

            if (startDate && months) {
                const start = new Date(startDate);
                const end = new Date(start);
                end.setMonth(end.getMonth() + months);

                // แสดงวันสิ้นสุดให้ผู้ใช้เห็น (ถ้ามี element)
                const endDateDisplay = document.getElementById('end_date_display');
                if (endDateDisplay) {
                    endDateDisplay.textContent = end.toLocaleDateString('th-TH');
                }
            }
        }

        if (startDateInput && periodSelect) {
            startDateInput.addEventListener('change', updateEndDate);
            periodSelect.addEventListener('change', updateEndDate);
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const agreeCheckbox = document.getElementById('agree');
            if (!agreeCheckbox || !agreeCheckbox.checked) {
                alert('กรุณายอมรับเงื่อนไขก่อนดำเนินการ');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'กำลังสร้างสัญญา...';
            }

            try {
                const formData = new FormData(form);
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    alert('สร้างสัญญาเรียบร้อยแล้ว\nเลขที่สัญญา: ' + (data.contract_number || ''));
                    window.location.href = '?page=history';
                } else {
                    alert('⚠️ ' + (data.message || 'เกิดข้อผิดพลาด'));
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'สร้างสัญญา';
                    }
                }
            } catch (err) {
                console.error('Form submission error:', err);
                alert('เกิดข้อผิดพลาดในการส่งข้อมูล กรุณาลองใหม่อีกครั้ง');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'สร้างสัญญา';
                }
            }
        });
    })();
</script>
