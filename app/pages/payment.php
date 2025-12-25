<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 3));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// NOTE: ห้ามใช้ app_log ก่อน include helpers.php
$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  error_log('payment_database_file_missing: ' . $databaseFile);
  http_response_code(500);
  if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error'], JSON_UNESCAPED_UNICODE);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  }
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  error_log('payment_helpers_file_missing: ' . $helpersFile);
  http_response_code(500);
  if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error'], JSON_UNESCAPED_UNICODE);
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
  app_log('payment_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'Session error'], 500);
  } else {
    echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  }
  return;
}

// ----------------------------
// Guard: ต้องล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  }
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
  return;
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('payment_invalid_user', ['session_user' => $user]);
  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง'], 401);
  }
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
  return;
}

// ----------------------------
// Guard: แอดมินไม่สามารถจองหรือชำระเงินได้
// ----------------------------
$userRole = (int)($user['role'] ?? 0);
if ($userRole === ROLE_ADMIN) {
  flash('error', 'ผู้ดูแลระบบไม่สามารถจองหรือชำระเงินได้');
  redirect('?page=admin_dashboard');
  return;
}

// ----------------------------
// Tiny error helper (ห้าม exit ใน transaction)
// ----------------------------
if (!class_exists('HttpFail')) {
  final class HttpFail extends RuntimeException
  {
    public int $status;
    public function __construct(int $status, string $message)
    {
      parent::__construct($message);
      $this->status = $status;
    }
  }
}

// ----------------------------
// Upload helpers
// ----------------------------
$validateSlipUpload = static function (): array {
  if (!isset($_FILES['slip_file']) || ($_FILES['slip_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return ['success' => false, 'message' => 'กรุณาอัปโหลดสลิปก่อนยืนยัน'];
  }

  $fileTmpName = (string)($_FILES['slip_file']['tmp_name'] ?? '');
  $fileSize    = (int)($_FILES['slip_file']['size'] ?? 0);
  $fileName    = (string)($_FILES['slip_file']['name'] ?? '');

  if ($fileTmpName === '' || !is_uploaded_file($fileTmpName)) {
    return ['success' => false, 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง'];
  }

  if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
    return ['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'];
  }

  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  if (!in_array($fileExtension, $allowedExtensions, true)) {
    return ['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)'];
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($fileTmpName) ?: '';
  $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($mime, $allowedMimes, true)) {
    return ['success' => false, 'message' => 'ไฟล์ไม่ใช่รูปภาพที่รองรับ'];
  }

  if (@getimagesize($fileTmpName) === false) {
    return ['success' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'];
  }

  return [
    'success'   => true,
    'tmp_name'  => $fileTmpName,
    'size'      => $fileSize,
    'extension' => $fileExtension,
  ];
};

$uploadSlip = static function (int $userId, int $areaId, string $tmpName, string $extension): ?string {
  // APP_PATH = /home/worasan/projects/sirinat (project root)
  // ดังนั้น APP_PATH . '/storage/uploads/slips' จะเป็น /home/worasan/projects/sirinat/storage/uploads/slips
  $uploadDir = APP_PATH . '/storage/uploads/slips';

  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
      app_log('slip_upload_dir_create_failed', ['upload_dir' => $uploadDir]);
      return null;
    }
  }

  $random = bin2hex(random_bytes(8));
  $newFileName = sprintf(
    'slip_%d_%d_%s_%s.%s',
    $userId,
    $areaId,
    date('YmdHis'),
    $random,
    $extension
  );

  $uploadPath = $uploadDir . '/' . $newFileName;

  if (move_uploaded_file($tmpName, $uploadPath)) {
    return '/storage/uploads/slips/' . $newFileName;
  }

  app_log('slip_upload_failed', ['upload_path' => $uploadPath]);
  return null;
};

// ----------------------
// POST: ยืนยันการชำระมัดจำ (AJAX)
// ----------------------
if ($method === 'POST' && isset($_POST['update_payment'])) {
  $areaId      = isset($_POST['area_id']) ? (int)$_POST['area_id'] : 0;
  $bookingDate = trim((string)($_POST['booking_date'] ?? ''));

  if ($areaId <= 0 || $bookingDate === '') {
    json_response(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
  }

  // validate bookingDate: YYYY-MM-DD และเป็นวันจริง
  $dt = DateTimeImmutable::createFromFormat('Y-m-d', $bookingDate);
  $dtErrors = DateTimeImmutable::getLastErrors();
  if (!$dt || ($dtErrors['warning_count'] ?? 0) > 0 || ($dtErrors['error_count'] ?? 0) > 0) {
    json_response(['success' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง'], 400);
  }

  try {
    $validation = $validateSlipUpload();
    if (!$validation['success']) {
      json_response(['success' => false, 'message' => (string)$validation['message']], 400);
    }

    $slipImagePath = $uploadSlip($userId, $areaId, (string)$validation['tmp_name'], (string)$validation['extension']);
    if ($slipImagePath === null) {
      json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่'], 500);
    }

    // คำนวณมัดจำ (ใช้ % เดียวกับหน้า GET)
    $areaData = Database::fetchOne(
      'SELECT price_per_year FROM rental_area WHERE area_id = ?',
      [$areaId]
    );
    if (!$areaData) {
      json_response(['success' => false, 'message' => 'ไม่พบข้อมูลพื้นที่'], 404);
    }

    $annualPrice = (float)($areaData['price_per_year'] ?? 0);
    $depositAmount = max(0, (int)ceil($annualPrice * 10 / 100));

    // ทำให้ atomic
    Database::transaction(function () use ($userId, $areaId, $bookingDate, $slipImagePath, $depositAmount) {
      // ตรวจสอบว่ามี booking ซ้ำหรือไม่ (ป้องกัน duplicate)
      $existingBooking = Database::fetchOne(
        '
          SELECT booking_id, deposit_status
          FROM booking_deposit
          WHERE user_id = ?
            AND area_id = ?
            AND booking_date = ?
            AND deposit_status != "rejected"
          LIMIT 1
          FOR UPDATE
        ',
        [$userId, $areaId, $bookingDate]
      );

      if ($existingBooking) {
        throw new HttpFail(400, 'คุณได้จองพื้นที่นี้ในวันที่เดียวกันแล้ว');
      }

      // lock area กัน race
      $area = Database::fetchOne(
        'SELECT area_id, area_status, user_id FROM rental_area WHERE area_id = ? LIMIT 1 FOR UPDATE',
        [$areaId]
      );

      if (!$area) {
        throw new HttpFail(404, 'ไม่พบข้อมูลพื้นที่');
      }

      if ((int)($area['user_id'] ?? 0) === $userId) {
        throw new HttpFail(400, 'ไม่สามารถจองพื้นที่ของตัวเองได้');
      }

      // ต้องยังว่างเท่านั้น
      $status = (string)($area['area_status'] ?? '');
      if ($status !== 'available') {
        throw new HttpFail(400, 'พื้นที่ไม่ว่างแล้ว');
      }

      // สร้าง booking_deposit ใหม่พร้อม payment_slip
      // NOTE: สร้างหลังจาก user โอนเงิน + upload slip + กดยืนยันเท่านั้น
      Database::execute(
        '
          INSERT INTO booking_deposit (area_id, user_id, booking_date, deposit_amount, deposit_status, payment_slip)
          VALUES (?, ?, ?, ?, "pending", ?)
        ',
        [$areaId, $userId, $bookingDate, $depositAmount, $slipImagePath]
      );

      $newBookingId = (int)Database::connection()->lastInsertId();

      // ล็อกพื้นที่เป็น booked (จองแล้ว - รอตรวจสอบ)
      Database::execute(
        'UPDATE rental_area SET area_status = "booked", updated_at = CURRENT_TIMESTAMP WHERE area_id = ?',
        [$areaId]
      );

      app_log('payment_submitted_success', [
        'user_id'      => $userId,
        'area_id'      => $areaId,
        'booking_id'   => $newBookingId,
        'booking_date' => $bookingDate,
      ]);
    });

    json_response([
      'success' => true,
      'message' => 'ส่งสลิปเรียบร้อยแล้ว (รอตรวจสอบ)',
    ]);
  } catch (HttpFail $e) {
    app_log('payment_http_fail', [
      'user_id' => $userId,
      'area_id' => $areaId ?? null,
      'status'  => $e->status,
      'message' => $e->getMessage(),
    ]);
    json_response(['success' => false, 'message' => $e->getMessage()], $e->status);
  } catch (Throwable $e) {
    app_log('payment_update_error', [
      'user_id'      => $userId,
      'area_id'      => $areaId ?? null,
      'booking_date' => $bookingDate ?? null,
      'error'        => $e->getMessage(),
      'trace'        => $e->getTraceAsString(),
    ]);

    // ใน development ให้แสดง error detail
    $message = 'เกิดข้อผิดพลาดในการอัปเดตการชำระเงิน';
    if (app_debug_enabled()) {
      $message .= ': ' . $e->getMessage();
    }

    json_response(['success' => false, 'message' => $message], 500);
  }
}

// ----------------------
// GET: แสดงหน้า payment
// ----------------------
$areaId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$day    = (int)(filter_input(INPUT_GET, 'day', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$month  = (int)(filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 11]]) ?? 0);
$year   = (int)(filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2020]]) ?? 0);

if ($areaId <= 0 || $day <= 0 || $year <= 0) {
  redirect('?page=home');
}

if ($month < 0 || $month > 11) {
  redirect('?page=detail&id=' . (int)$areaId . '&error=month');
}

// validate วันที่เป็นวันจริง + ต้องเป็นอนาคต (>= พรุ่งนี้)
try {
  $selectedDate = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month + 1, $day));
  $today = new DateTimeImmutable('today');
  $minDate = $today->modify('+1 day');

  if ($selectedDate < $minDate) {
    redirect('?page=detail&id=' . (int)$areaId . '&error=date');
  }
} catch (Throwable $e) {
  redirect('?page=detail&id=' . (int)$areaId . '&error=date');
}

// label วันไทย
$monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$buddhistYear = $year + 543;
$fullDate     = sprintf('%d %s %d', $day, $monthNames[$month], $buddhistYear);
$bookingDate  = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);

// ดึงข้อมูลพื้นที่
$item = Database::fetchOne(
  'SELECT area_id, user_id, area_name, price_per_year, area_status FROM rental_area WHERE area_id = ?',
  [$areaId]
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
if ((int)($item['user_id'] ?? 0) === $userId) {
  redirect('?page=detail&id=' . (int)$areaId . '&error=owner');
}

// ตรวจสอบสถานะพื้นที่ (ต้องยังว่างเท่านั้น)
if ((string)($item['area_status'] ?? '') !== 'available') {
?>
  <div class="container">
    <h1>ไม่สามารถจองพื้นที่นี้ได้</h1>
    <p>สถานะปัจจุบัน: <?php echo e((string)($item['area_status'] ?? '')); ?></p>
    <a href="?page=detail&id=<?php echo (int)$areaId; ?>">กลับไปหน้ารายละเอียด</a>
  </div>
<?php
  exit();
}

// คำนวณมัดจำ
$annualPriceRaw = (float)($item['price_per_year'] ?? 0);
$depositPercent = 10;
$depositRaw     = max(0, (int)ceil($annualPriceRaw * $depositPercent / 100));
$deposit        = number_format($depositRaw);

// ตั้งเวลาหมดอายุ 60 นาทีจากตอนนี้ (สำหรับ countdown timer)
$now = new DateTimeImmutable('now');
$expiresAtIso = $now->modify('+60 minutes')->format(DATE_ATOM);

?>
<div class="payment-container">
  <a href="?page=detail&id=<?php echo (int)$areaId; ?>" class="back-button minimal">ย้อนกลับ</a>

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
          <span class="bl-value ref-code">#<?php echo str_pad((string)$areaId, 6, '0', STR_PAD_LEFT); ?></span>
        </li>
        <li><span class="bl-label">พื้นที่:</span><span class="bl-value"><?php echo e((string)($item['area_name'] ?? '')); ?></span></li>
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
          src="https://promptpay.io/0641365430/<?php echo (int)$depositRaw; ?>.png"
          alt="QR PromptPay"
          class="qr-img"
          loading="lazy">
      </div>

      <div class="pay-meta">
        <div><span class="meta-label">PromptPay:</span> <span class="meta-value">064-136-5430</span></div>
        <div><span class="meta-label">จำนวนเงิน:</span> <span class="meta-value price">฿<?php echo e($deposit); ?></span></div>
        <div><span class="meta-label">เวลาคงเหลือ:</span> <span class="meta-value" id="timeRemaining">--:--</span></div>
      </div>

      <div class="upload-slip clean">
        <label for="slipFile" class="upload-label">📎 อัปโหลดสลิปการโอน</label>
        <input type="file" id="slipFile" name="slip_file" accept="image/*" class="upload-input">
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
        <button type="button" class="btn-confirm-payment" disabled>ยืนยันการชำระเงิน</button>
        <button type="button" class="btn-cancel-payment">❌ ยกเลิกการจอง</button>
      </div>
    </section>
  </div>
</div>

<script nonce="<?php echo e(csp_nonce()); ?>">
  // JS จะอ่านจากตรงนี้
  window.PAYMENT_DATA = <?php echo json_encode([
                          'areaId'      => $areaId,
                          'bookingDate' => $bookingDate,
                          'expiresAt'   => $expiresAtIso ?? '',
                        ], JSON_UNESCAPED_UNICODE); ?>;
</script>