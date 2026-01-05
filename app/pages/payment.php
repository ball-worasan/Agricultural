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
$databaseFile = APP_PATH . '/config/database.php';
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
if (defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN) {
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
// Fee helpers
// ----------------------------
$getActiveFee = static function (): ?array {
  try {
    $fee = Database::fetchOne(
      'SELECT fee_id, fee_rate, account_number, account_name, bank_name
         FROM fee
        ORDER BY fee_id DESC
        LIMIT 1'
    );
    return $fee ?: null;
  } catch (Throwable $e) {
    app_log('fee_fetch_error', ['error' => $e->getMessage()]);
    return null;
  }
};

$getFeeRate = static function () use ($getActiveFee): float {
  $fee = $getActiveFee();
  $rate = (float)($fee['fee_rate'] ?? 0);
  return max(0.0, $rate);
};

$isPromptPay = static function (?array $fee): bool {
  if (!$fee) return true; // default เป็นพร้อมเพย์

  $bankName = strtolower(trim((string)($fee['bank_name'] ?? '')));
  $accountNumber = trim((string)($fee['account_number'] ?? ''));

  if (in_array($bankName, ['promptpay', 'พร้อมเพย์', 'prompt pay', ''], true)) {
    return true;
  }

  if (preg_match('/^0\d{9}$/', $accountNumber)) {
    return true;
  }

  return false;
};

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

  // ✅ ให้ตรงกับ JS: 5MB
  if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
    return ['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'];
  }

  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  if (!in_array($fileExtension, $allowedExtensions, true)) {
    return ['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)'];
  }

  if (!class_exists('finfo')) {
    return ['success' => false, 'message' => 'ระบบตรวจสอบไฟล์ไม่พร้อมใช้งาน (finfo)'];
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
  // เก็บไฟล์สลิปใต้ public ให้เสิร์ฟได้ตรง path /storage/uploads/slips
  $projectRoot = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(APP_PATH);
  $uploadDir = $projectRoot . '/public/storage/uploads/slips';

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
// POST: ยืนยันการชำระ (AJAX)
// ----------------------
if ($method === 'POST' && isset($_POST['update_payment'])) {
  $flow = (string)($_POST['flow'] ?? 'deposit');

  // ===== flow: full (กันพัง + ตอบชัดเจน) =====
  if ($flow === 'full') {
    $contractId = (int)($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
      json_response(['success' => false, 'message' => 'ข้อมูลสัญญาไม่ถูกต้อง'], 400);
    }

    // validate slip upload (กันส่งมั่ว)
    $validation = $validateSlipUpload();
    if (!$validation['success']) {
      json_response(['success' => false, 'message' => (string)$validation['message']], 400);
    }

    // ตอนนี้ยังไม่เห็น schema เก็บ slip ของสัญญา -> เลยตอบกลับชัด ๆ
    json_response([
      'success' => false,
      'message' => 'โหมดชำระเงินสัญญายังไม่ได้ผูกกับฐานข้อมูล (ต้องเพิ่มตาราง/คอลัมน์รองรับการเก็บสลิปและสถานะ)',
    ], 400);
  }

  // ===== flow: deposit (ของเดิม) =====
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

    $message = 'เกิดข้อผิดพลาดในการอัปเดตการชำระเงิน';
    if (function_exists('app_debug_enabled') && app_debug_enabled()) {
      $message .= ': ' . $e->getMessage();
    }

    json_response(['success' => false, 'message' => $message], 500);
  }
}

// ----------------------
// GET: เตรียมข้อมูลหน้า (render ทีเดียว ไม่ซ้อน 2 รอบ)
// ----------------------
$areaId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$day    = (int)(filter_input(INPUT_GET, 'day', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$month  = (int)(filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 11]]) ?? 0);
$year   = (int)(filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2020]]) ?? 0);

$contractId = (int)(filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$flow = $contractId > 0 ? 'full' : 'deposit';

// shared: fee account
$feeData = $getActiveFee();
$isPromptPayAccount = $isPromptPay($feeData);

$accountNumber = $feeData ? trim((string)($feeData['account_number'] ?? '0641365430')) : '0641365430';
$accountName   = $feeData ? trim((string)($feeData['account_name'] ?? 'ระบบจองพื้นที่เกษตร')) : 'ระบบจองพื้นที่เกษตร';
$bankName      = ($feeData && !$isPromptPayAccount) ? trim((string)($feeData['bank_name'] ?? '')) : '';

$expiresAtIso = (new DateTimeImmutable('now'))->modify('+60 minutes')->format(DATE_ATOM);

// vars for view
$pageTitle = '';
$displayAreaName = '';
$dateLabel = '';
$dateText = '';
$amountForDisplay = 0.0;     // show
$amountForQrInt = 0;         // promptpay.io ชอบเป็น int
$bookingDate = null;
$item = null;
$contractRow = null;

if ($flow === 'full') {
  if ($contractId <= 0) redirect('?page=history');

  $contractRow = Database::fetchOne(
    'SELECT c.contract_id, c.total_price, c.price_per_year, c.start_date, c.end_date,
            bd.deposit_amount, bd.deposit_status, bd.booking_date, bd.user_id AS tenant_id,
            ra.area_name
       FROM contract c
       JOIN booking_deposit bd ON c.booking_id = bd.booking_id
       JOIN rental_area ra      ON bd.area_id = ra.area_id
      WHERE c.contract_id = ?
      LIMIT 1',
    [$contractId]
  );

  if (!$contractRow) redirect('?page=history');
  if ((int)$contractRow['tenant_id'] !== $userId) redirect('?page=history');
  if ((string)$contractRow['deposit_status'] !== 'approved') redirect('?page=history');

  $baseTotal = (float)($contractRow['total_price'] ?? $contractRow['price_per_year'] ?? 0);
  $feeRate   = $getFeeRate();
  $feeAmount = round($baseTotal * $feeRate / 100, 2);
  $depositAmt = (float)($contractRow['deposit_amount'] ?? 0);
  $amountDue = max(0.0, $baseTotal + $feeAmount - $depositAmt);

  $pageTitle = 'ชำระเงินสัญญา';
  $displayAreaName = (string)($contractRow['area_name'] ?? '');
  $dateLabel = 'สัญญา';
  $dateText = sprintf('%s ถึง %s', (string)($contractRow['start_date'] ?? ''), (string)($contractRow['end_date'] ?? ''));
  $amountForDisplay = $amountDue;
  $amountForQrInt = (int)ceil($amountDue);
  $bookingDate = (string)($contractRow['booking_date'] ?? null);
} else {
  // deposit flow
  if ($areaId <= 0 || $day <= 0 || $year <= 0) redirect('?page=home');
  if ($month < 0 || $month > 11) redirect('?page=detail&id=' . (int)$areaId . '&error=month');

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

  $item = Database::fetchOne(
    'SELECT area_id, user_id, area_name, price_per_year, area_status FROM rental_area WHERE area_id = ?',
    [$areaId]
  );

  if (!$item) {
    echo '<div class="container"><h1>ไม่พบข้อมูลพื้นที่</h1><a href="?page=home">กลับหน้าหลัก</a></div>';
    exit();
  }

  // ห้ามเจ้าของจองพื้นที่ตัวเอง
  if ((int)($item['user_id'] ?? 0) === $userId) {
    redirect('?page=detail&id=' . (int)$areaId . '&error=owner');
  }

  // ต้องยังว่างเท่านั้น
  if ((string)($item['area_status'] ?? '') !== 'available') {
    echo '<div class="container"><h1>ไม่สามารถจองพื้นที่นี้ได้</h1><p>สถานะปัจจุบัน: ' . e((string)($item['area_status'] ?? '')) . '</p><a href="?page=detail&id=' . (int)$areaId . '">กลับไปหน้ารายละเอียด</a></div>';
    exit();
  }

  $annualPriceRaw = (float)($item['price_per_year'] ?? 0);
  $depositRaw = max(0, (int)ceil($annualPriceRaw * 10 / 100));

  $pageTitle = 'ชำระเงินมัดจำ';
  $displayAreaName = (string)($item['area_name'] ?? '');
  $dateLabel = 'วันที่นัด';
  $dateText = $fullDate;
  $amountForDisplay = (float)$depositRaw;
  $amountForQrInt = (int)$depositRaw;
}

?>
<div class="payment-container">
  <div class="payment-wrapper">
    <a href="<?php echo $flow === 'deposit'
                ? ('?page=detail&id=' . (int)$areaId)
                : ('?page=history'); ?>"
      class="back-link">← ย้อนกลับ</a>

    <header class="payment-hero">
      <h1><?php echo e($pageTitle); ?></h1>

      <div class="booking-summary">
        <div class="summary-item">
          <span class="label">พื้นที่:</span>
          <strong><?php echo e($displayAreaName); ?></strong>
        </div>

        <div class="summary-item">
          <span class="label"><?php echo e($dateLabel); ?>:</span>
          <strong><?php echo e($dateText); ?></strong>
        </div>

        <div class="summary-item highlight">
          <span class="label">ยอดชำระ:</span>
          <strong class="amount">฿<?php echo e(number_format($amountForDisplay, 0)); ?></strong>
        </div>
      </div>

      <div class="timer-box">
        <span class="timer-icon">⏱</span>
        <span>เวลาคงเหลือ: <strong id="timeRemaining">--:--</strong></span>
      </div>
    </header>

    <div class="payment-steps">
      <div class="step">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3><?php echo $isPromptPayAccount ? 'สแกน QR Code' : 'โอนเงินเข้าบัญชี'; ?></h3>

          <div class="qr-wrapper">
            <?php if ($isPromptPayAccount): ?>
              <img
                src="https://promptpay.io/<?php echo e($accountNumber); ?>/<?php echo (int)$amountForQrInt; ?>.png"
                alt="QR PromptPay"
                class="qr-code"
                loading="lazy">
              <div class="qr-info">
                <div>ชื่อบัญชี: <strong><?php echo e($accountName); ?></strong></div>
                <div>พร้อมเพย์: <strong><?php echo e(substr($accountNumber, 0, 3) . '-' . substr($accountNumber, 3, 3) . '-' . substr($accountNumber, 6)); ?></strong></div>
                <div>จำนวน: <strong class="amount">฿<?php echo e(number_format($amountForDisplay, 0)); ?></strong></div>
              </div>
            <?php else: ?>
              <div class="qr-info" style="text-align: left; width: 100%;">
                <div style="margin-bottom: 0.75rem;">ชื่อบัญชี: <strong><?php echo e($accountName); ?></strong></div>
                <div style="margin-bottom: 0.75rem;">ธนาคาร: <strong><?php echo e($bankName); ?></strong></div>
                <div style="margin-bottom: 0.75rem;">เลขบัญชี: <strong><?php echo e($accountNumber); ?></strong></div>
                <div>จำนวน: <strong class="amount">฿<?php echo e(number_format($amountForDisplay, 0)); ?></strong></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="step">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3>อัปโหลดสลิปการโอน</h3>

          <div class="upload-zone">
            <label for="slipFile" class="upload-btn">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              <span>เลือกไฟล์สลิป</span>
            </label>

            <input type="file" id="slipFile" name="slip_file" accept="image/*" class="upload-input">
            <div id="slipPreview" class="slip-preview" hidden></div>
          </div>
        </div>
      </div>

      <div class="step">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3>ยืนยันการชำระเงิน</h3>

          <div class="action-buttons">
            <button
              type="button"
              class="btn-confirm"
              data-flow="<?php echo e($flow); ?>"
              <?php if ($flow === 'full'): ?>
              data-contract-id="<?php echo (int)$contractId; ?>"
              <?php endif; ?>
              <?php if ($flow === 'deposit'): ?>
              data-area-id="<?php echo (int)$areaId; ?>"
              data-booking-date="<?php echo e((string)$bookingDate); ?>"
              <?php endif; ?>
              disabled>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span><?php echo $flow === 'full' ? 'ยืนยันการชำระเงินสัญญา' : 'ยืนยันการชำระเงิน'; ?></span>
            </button>

            <button type="button" class="btn-cancel">
              <span>ยกเลิก</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="payment-note">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="16" x2="12" y2="12"></line>
        <line x1="12" y1="8" x2="12.01" y2="8"></line>
      </svg>
      <span>กรุณาชำระเงินและอัปโหลดสลิปภายในเวลาที่กำหนด เพื่อยืนยันการทำรายการ</span>
    </div>
  </div>
</div>

<script nonce="<?php echo e(csp_nonce()); ?>">
  window.PAYMENT_DATA = <?php echo json_encode([
                          'flow'        => $flow,
                          'areaId'      => $flow === 'deposit' ? $areaId : 0,
                          'bookingDate' => $flow === 'deposit' ? $bookingDate : null,
                          'contractId'  => $flow === 'full' ? $contractId : null,
                          'expiresAt'   => $expiresAtIso ?? '',
                        ], JSON_UNESCAPED_UNICODE); ?>;
</script>