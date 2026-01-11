<?php

declare(strict_types=1);

/**
 * app/pages/payment.php (FULL)
 *
 * รองรับ 2 flow:
 * 1) deposit  : ชำระเงินมัดจำ (จากหน้า detail เลือกวัน) -> อัปโหลดสลิป -> สร้าง booking_deposit + ล็อกพื้นที่เป็น booked
 * 2) full     : ชำระเงินเต็มสัญญา (จาก history/contract) -> ตอนนี้ “ยังไม่ผูก DB” (ตอบ error ชัดเจน) *คุณค่อยต่อ schema ทีหลัง*
 *
 * Security:
 * - require login
 * - admin ห้ามจอง/จ่าย
 * - CSRF สำหรับ POST (AJAX)
 * - validate upload (size/ext/mime/imagesize)
 * - transaction + FOR UPDATE กัน race condition
 */

if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 3));
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// ------------------------------------------------------------
// Load bootstrap files (ห้าม app_log ก่อน include helpers)
// ------------------------------------------------------------
$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile) || !is_file($helpersFile)) {
  error_log('payment_bootstrap_missing: ' . $databaseFile . ' | ' . $helpersFile);
  http_response_code(500);

  if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'System error'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ระบบยังไม่พร้อมใช้งาน</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// ------------------------------------------------------------
// Start session
// ------------------------------------------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('payment_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);

  if ($method === 'POST') {
    json_response(['success' => false, 'message' => 'Session error'], 500);
  }

  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ------------------------------------------------------------
// Guard auth
// ------------------------------------------------------------
$user = current_user();
if ($user === null) {
  if ($method === 'POST') json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin', 303);
  return;
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('payment_invalid_user', ['session_user' => $user]);
  if ($method === 'POST') json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่'], 401);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
  redirect('?page=signin', 303);
  return;
}

// admin cannot pay/book
$userRole = (int)($user['role'] ?? 0);
if (defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN) {
  flash('error', 'ผู้ดูแลระบบไม่สามารถจองหรือชำระเงินได้');
  redirect('?page=admin_dashboard', 303);
  return;
}

// ------------------------------------------------------------
// Tiny exception for early-fail in transaction
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// CSRF helper (POST only)
// ------------------------------------------------------------
$requireCsrf = static function (): void {
  $token = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($token)) {
    app_log('payment_csrf_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง (CSRF)'], 403);
  }
};

// ------------------------------------------------------------
// Fee helpers
// ------------------------------------------------------------
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
  if (!$fee) return true; // default promptpay
  $bankName = strtolower(trim((string)($fee['bank_name'] ?? '')));
  $accountNumber = trim((string)($fee['account_number'] ?? ''));

  if (in_array($bankName, ['promptpay', 'พร้อมเพย์', 'prompt pay', ''], true)) return true;

  // ถ้าเป็นเบอร์มือถือ 10 หลักขึ้นต้น 0 -> ถือว่า promptpay
  if (preg_match('/^0\d{9}$/', $accountNumber)) return true;

  return false;
};

// ------------------------------------------------------------
// Upload helpers (slip)
// ------------------------------------------------------------
$validateSlipUpload = static function (): array {
  if (!isset($_FILES['slip_file']) || (int)($_FILES['slip_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return ['success' => false, 'message' => 'กรุณาอัปโหลดสลิปก่อนยืนยัน'];
  }

  $tmp = (string)($_FILES['slip_file']['tmp_name'] ?? '');
  $size = (int)($_FILES['slip_file']['size'] ?? 0);
  $name = (string)($_FILES['slip_file']['name'] ?? '');

  if ($tmp === '' || !is_uploaded_file($tmp)) {
    return ['success' => false, 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง'];
  }

  if ($size <= 0 || $size > 5 * 1024 * 1024) {
    return ['success' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'];
  }

  $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    return ['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)'];
  }

  if (!class_exists('finfo')) {
    return ['success' => false, 'message' => 'ระบบตรวจสอบไฟล์ไม่พร้อมใช้งาน (finfo)'];
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = (string)($finfo->file($tmp) ?: '');
  $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($mime, $allowedMimes, true)) {
    return ['success' => false, 'message' => 'ไฟล์ไม่ใช่รูปภาพที่รองรับ'];
  }

  if (@getimagesize($tmp) === false) {
    return ['success' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'];
  }

  return ['success' => true, 'tmp_name' => $tmp, 'extension' => $ext, 'size' => $size];
};

$uploadSlip = static function (int $userId, int $areaId, string $tmpName, string $extension): ?string {
  $projectRoot = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(APP_PATH);
  $uploadDir   = $projectRoot . '/public/storage/uploads/slips';

  // ensure directory exists
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    app_log('slip_upload_dir_create_failed', ['upload_dir' => $uploadDir]);
    return null;
  }

  $random = bin2hex(random_bytes(8));
  $baseNameNoExt = sprintf(
    'slip_%d_%d_%s_%s',
    $userId,
    $areaId,
    date('YmdHis'),
    $random
  );

  $size = (int)@filesize($tmpName);
  $generatedName = $baseNameNoExt . '.' . $extension;

  // ใช้ ImageService ถ้ามี
  if (class_exists('ImageService') && method_exists('ImageService', 'uploadAndProcess')) {
    try {
      $fileArray = [
        'tmp_name' => $tmpName,
        'name'     => $generatedName,
        'size'     => $size,
        'error'    => UPLOAD_ERR_OK,
      ];

      $result = ImageService::uploadAndProcess(
        $fileArray,
        $uploadDir,
        '/storage/uploads/slips',
        $baseNameNoExt
      );

      if (!empty($result['public_path'])) {
        return $result['public_path'];
      }

      json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ'], 400);
    } catch (Throwable $e) {
      app_log('slip_upload_imageservice_error', ['error' => $e->getMessage()]);
      // fallback to manual
    }
  }

  // fallback: manual upload
  $uploadPath = $uploadDir . '/' . $generatedName;

  if (move_uploaded_file($tmpName, $uploadPath)) {
    return '/storage/uploads/slips/' . $generatedName;
  }

  app_log('slip_upload_failed', ['upload_path' => $uploadPath]);
  return null;
};

// ------------------------------------------------------------
// POST: update_payment (AJAX)
// ------------------------------------------------------------
if ($method === 'POST' && isset($_POST['update_payment'])) {
  // CSRF for ALL payment POST
  $requireCsrf();

  $flow = (string)($_POST['flow'] ?? 'deposit');

  // ============== flow full (สร้าง payment record) ==============
  if ($flow === 'full') {
    $contractId = (int)($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
      json_response(['success' => false, 'message' => 'ข้อมูลสัญญาไม่ถูกต้อง'], 400);
    }

    // validate slip upload
    $validation = $validateSlipUpload();
    if (!$validation['success']) {
      json_response(['success' => false, 'message' => (string)$validation['message']], 400);
    }

    try {
      // fetch contract + verify ownership
      $contract = Database::fetchOne(
        'SELECT c.contract_id, c.booking_id, c.price_per_year,
                bd.user_id AS tenant_id, bd.area_id, bd.deposit_amount, bd.deposit_status
         FROM contract c
         JOIN booking_deposit bd ON c.booking_id = bd.booking_id
         WHERE c.contract_id = ?
         LIMIT 1',
        [$contractId]
      );

      if (!$contract) {
        json_response(['success' => false, 'message' => 'ไม่พบข้อมูลสัญญา'], 404);
      }

      if ((int)($contract['tenant_id'] ?? 0) !== $userId) {
        json_response(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ชำระเงินสัญญานี้'], 403);
      }

      if ((string)($contract['deposit_status'] ?? '') !== 'approved') {
        json_response(['success' => false, 'message' => 'การจองยังไม่ได้รับการอนุมัติ'], 400);
      }

      // ตรวจสอบ payment status ที่มีอยู่แล้ว (ป้องกันชำระซ้ำ)
      $existingPayment = Database::fetchOne(
        'SELECT payment_id, status FROM payment WHERE contract_id = ? AND status IN ("pending", "confirmed") LIMIT 1',
        [$contractId]
      );

      if ($existingPayment) {
        $paymentStatus = (string)($existingPayment['status'] ?? '');
        json_response([
          'success' => false,
          'message' => $paymentStatus === 'confirmed' 
            ? 'คุณชำระเงินสัญญานี้แล้ว' 
            : 'การชำระเงินสัญญานี้กำลังรอตรวจสอบ กรุณารอการติดต่อกลับ',
        ], 400);
      }

      // upload slip
      $areaId = (int)($contract['area_id'] ?? 0);
      $slipPath = $uploadSlip($userId, $areaId, (string)$validation['tmp_name'], (string)$validation['extension']);
      if ($slipPath === null) {
        json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ'], 500);
      }

      // calculate full amount due
      $baseTotal = (float)($contract['price_per_year'] ?? 0);
      $feeRate = $getFeeRate();
      $feeAmount = round($baseTotal * $feeRate / 100, 2);
      $depositAmt = (float)($contract['deposit_amount'] ?? 0);
      $amountDue = max(0.0, $baseTotal - $depositAmt); // เฉพาะค่าเช่า ไม่รวมค่าธรรมเนียม

      // create payment record
      Database::transaction(function (PDO $pdo) use ($contractId, $amountDue, $slipPath, $feeRate): int {
        $paymentDate = date('Y-m-d');
        $paymentTime = date('H:i:s');
        $netAmount = round($amountDue * (100 - $feeRate) / 100, 2); // after fee deduction

        Database::execute(
          'INSERT INTO payment (contract_id, amount, payment_date, payment_time, net_amount, slip_image, status, created_at)
           VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())',
          [
            $contractId,
            $amountDue,
            $paymentDate,
            $paymentTime,
            $netAmount,
            $slipPath,
          ]
        );

        return (int)$pdo->lastInsertId();
      });

      app_log('payment_full_submitted', [
        'user_id' => $userId,
        'contract_id' => $contractId,
        'amount' => $amountDue,
        'slip' => $slipPath,
      ]);

      json_response([
        'success' => true,
        'message' => 'ส่งสลิปอนุมัติเรียบร้อยแล้ว (รอตรวจสอบ)',
        'contract_id' => $contractId,
        'redirect' => '?page=history',
      ]);
    } catch (Throwable $e) {
      app_log('payment_full_error', [
        'user_id' => $userId,
        'contract_id' => $contractId,
        'error' => $e->getMessage(),
      ]);

      json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
    }
  }

  // ============== flow deposit ==============
  $areaId      = (int)($_POST['area_id'] ?? 0);
  $bookingDate = trim((string)($_POST['booking_date'] ?? ''));

  if ($areaId <= 0 || $bookingDate === '') {
    json_response(['success' => false, 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
  }

  // validate bookingDate format
  $dt = DateTimeImmutable::createFromFormat('Y-m-d', $bookingDate);
  $errs = DateTimeImmutable::getLastErrors();
  if (!$dt || ($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0) {
    json_response(['success' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง'], 400);
  }

  try {
    $validation = $validateSlipUpload();
    if (!$validation['success']) {
      json_response(['success' => false, 'message' => (string)$validation['message']], 400);
    }

    $slipPath = $uploadSlip($userId, $areaId, (string)$validation['tmp_name'], (string)$validation['extension']);
    if ($slipPath === null) {
      json_response(['success' => false, 'message' => 'อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่'], 500);
    }

    // คำนวณมัดจำ (10%)
    $areaData = Database::fetchOne('SELECT price_per_year FROM rental_area WHERE area_id = ? LIMIT 1', [$areaId]);
    if (!$areaData) {
      json_response(['success' => false, 'message' => 'ไม่พบข้อมูลพื้นที่'], 404);
    }

    $annualPrice = (float)($areaData['price_per_year'] ?? 0);
    $depositAmount = max(0, (int)ceil($annualPrice * 10 / 100));

    Database::transaction(function () use ($userId, $areaId, $bookingDate, $slipPath, $depositAmount): void {
      // booking ซ้ำ (lock)
      $existing = Database::fetchOne(
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
      if ($existing) {
        throw new HttpFail(400, 'คุณได้จองพื้นที่นี้ในวันที่เดียวกันแล้ว');
      }

      // lock area กัน race
      $area = Database::fetchOne(
        'SELECT area_id, area_status, user_id FROM rental_area WHERE area_id = ? LIMIT 1 FOR UPDATE',
        [$areaId]
      );
      if (!$area) throw new HttpFail(404, 'ไม่พบข้อมูลพื้นที่');

      if ((int)($area['user_id'] ?? 0) === $userId) {
        throw new HttpFail(400, 'ไม่สามารถจองพื้นที่ของตัวเองได้');
      }

      $status = (string)($area['area_status'] ?? '');
      if ($status !== 'available') {
        throw new HttpFail(400, 'พื้นที่ไม่ว่างแล้ว');
      }

      // create booking_deposit
      Database::execute(
        '
          INSERT INTO booking_deposit (area_id, user_id, booking_date, deposit_amount, deposit_status, payment_slip)
          VALUES (?, ?, ?, ?, "pending", ?)
        ',
        [$areaId, $userId, $bookingDate, $depositAmount, $slipPath]
      );

      $newBookingId = (int)Database::connection()->lastInsertId();

      // update area -> booked (รอตรวจสอบ)
      Database::execute(
        'UPDATE rental_area SET area_status = "booked", updated_at = CURRENT_TIMESTAMP WHERE area_id = ?',
        [$areaId]
      );

      app_log('payment_submitted_success', [
        'user_id' => $userId,
        'area_id' => $areaId,
        'booking_id' => $newBookingId,
        'booking_date' => $bookingDate,
      ]);
    });

    json_response(['success' => true, 'message' => 'ส่งสลิปเรียบร้อยแล้ว (รอตรวจสอบ)']);
  } catch (HttpFail $e) {
    app_log('payment_http_fail', [
      'user_id' => $userId,
      'area_id' => $areaId ?? null,
      'status' => $e->status,
      'message' => $e->getMessage(),
    ]);
    json_response(['success' => false, 'message' => $e->getMessage()], $e->status);
  } catch (Throwable $e) {
    app_log('payment_update_error', [
      'user_id' => $userId,
      'area_id' => $areaId ?? null,
      'booking_date' => $bookingDate ?? null,
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString(),
    ]);

    $msg = 'เกิดข้อผิดพลาดในการอัปเดตการชำระเงิน';
    if (function_exists('app_debug_enabled') && app_debug_enabled()) $msg .= ': ' . $e->getMessage();

    json_response(['success' => false, 'message' => $msg], 500);
  }
}

// ------------------------------------------------------------
// GET: render page (single render)
// ------------------------------------------------------------
$areaId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$day    = (int)(filter_input(INPUT_GET, 'day', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$month  = (int)(filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 11]]) ?? 0);
$year   = (int)(filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2020]]) ?? 0);

$contractId = (int)(filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
$flow = ($contractId > 0) ? 'full' : 'deposit';

// fee info for view
$feeData = $getActiveFee();
$isPromptPayAccount = $isPromptPay($feeData);

$accountNumber = $feeData ? trim((string)($feeData['account_number'] ?? '0641365430')) : '0641365430';
$accountName   = $feeData ? trim((string)($feeData['account_name'] ?? 'ระบบจองพื้นที่เกษตร')) : 'ระบบจองพื้นที่เกษตร';
$bankName      = ($feeData && !$isPromptPayAccount) ? trim((string)($feeData['bank_name'] ?? '')) : '';

$expiresAtIso = (new DateTimeImmutable('now'))->modify('+60 minutes')->format(DATE_ATOM);

// vars
$pageTitle = '';
$displayAreaName = '';
$dateLabel = '';
$dateText = '';
$amountForDisplay = 0.0;
$amountForQrInt = 0;
$bookingDate = null;

$item = null;
$contractRow = null;

if ($flow === 'full') {
  if ($contractId <= 0) redirect('?page=history', 303);

  $contractRow = Database::fetchOne(
    'SELECT c.contract_id, c.price_per_year, c.start_date, c.end_date,
            bd.deposit_amount, bd.deposit_status, bd.booking_date, bd.user_id AS tenant_id,
            ra.area_name,
            p.payment_id, p.status AS payment_status
       FROM contract c
       JOIN booking_deposit bd ON c.booking_id = bd.booking_id
       JOIN rental_area ra      ON bd.area_id = ra.area_id
       LEFT JOIN payment p      ON p.contract_id = c.contract_id AND p.status IN ("pending", "confirmed")
      WHERE c.contract_id = ?
      LIMIT 1',
    [$contractId]
  );

  if (!$contractRow) redirect('?page=history', 303);
  if ((int)($contractRow['tenant_id'] ?? 0) !== $userId) redirect('?page=history', 303);
  if ((string)($contractRow['deposit_status'] ?? '') !== 'approved') redirect('?page=history', 303);

  $baseTotal  = (float)($contractRow['price_per_year'] ?? 0);
  $feeRate    = $getFeeRate();
  $feeAmount  = round($baseTotal * $feeRate / 100, 2);
  $depositAmt = (float)($contractRow['deposit_amount'] ?? 0);

  $amountDue = max(0.0, $baseTotal - $depositAmt); // เฉพาะค่าเช่า ไม่รวมค่าธรรมเนียม (หัก owner)

  // ตรวจสอบว่าชำระแล้วหรือยัง
  $paymentStatus = (string)($contractRow['payment_status'] ?? '');
  if ($paymentStatus !== '') {
    $statusMsg = $paymentStatus === 'confirmed' 
      ? 'คุณชำระเงินสัญญานี้แล้ว' 
      : 'การชำระเงินสัญญานี้กำลังรอตรวจสอบ';
    flash('info', $statusMsg);
    redirect('?page=history', 303);
  }

  $pageTitle = 'ชำระเงินสัญญา';
  $displayAreaName = (string)($contractRow['area_name'] ?? '');
  $dateLabel = 'สัญญา';
  $dateText = sprintf('%s ถึง %s', (string)($contractRow['start_date'] ?? ''), (string)($contractRow['end_date'] ?? ''));
  $amountForDisplay = $amountDue;
  $amountForQrInt = (int)ceil($amountDue);
  $bookingDate = (string)($contractRow['booking_date'] ?? null);
} else {
  // deposit flow
  if ($areaId <= 0 || $day <= 0 || $year <= 0) redirect('?page=home', 303);
  if ($month < 0 || $month > 11) redirect('?page=detail&id=' . (int)$areaId . '&error=month', 303);

  // validate date: ต้องเป็นวันจริง และ >= พรุ่งนี้
  try {
    $selectedDate = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month + 1, $day));
    $today = new DateTimeImmutable('today');
    $minDate = $today->modify('+1 day');
    if ($selectedDate < $minDate) {
      redirect('?page=detail&id=' . (int)$areaId . '&error=date', 303);
    }
  } catch (Throwable $e) {
    redirect('?page=detail&id=' . (int)$areaId . '&error=date', 303);
  }

  $monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
  $buddhistYear = $year + 543;
  $fullDate = sprintf('%d %s %d', $day, $monthNames[$month], $buddhistYear);
  $bookingDate = sprintf('%04d-%02d-%02d', $year, $month + 1, $day);

  $item = Database::fetchOne(
    'SELECT area_id, user_id, area_name, price_per_year, area_status
       FROM rental_area
      WHERE area_id = ?
      LIMIT 1',
    [$areaId]
  );

  if (!$item) {
    echo '<div class="container"><h1>ไม่พบข้อมูลพื้นที่</h1><a href="?page=home">กลับหน้าหลัก</a></div>';
    exit;
  }

  if ((int)($item['user_id'] ?? 0) === $userId) {
    redirect('?page=detail&id=' . (int)$areaId . '&error=owner', 303);
  }

  if ((string)($item['area_status'] ?? '') !== 'available') {
    echo '<div class="container"><h1>ไม่สามารถจองพื้นที่นี้ได้</h1><p>สถานะปัจจุบัน: ' . e((string)($item['area_status'] ?? '')) . '</p><a href="?page=detail&id=' . (int)$areaId . '">กลับไปหน้ารายละเอียด</a></div>';
    exit;
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

// csrf for JS + (optional) form
$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<div class="payment-container" data-page="payment">
  <div class="payment-wrapper">
    <a
      href="<?php echo $flow === 'deposit'
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
              <div class="qr-info" style="text-align:left;width:100%;">
                <div style="margin-bottom:0.75rem;">ชื่อบัญชี: <strong><?php echo e($accountName); ?></strong></div>
                <div style="margin-bottom:0.75rem;">ธนาคาร: <strong><?php echo e($bankName); ?></strong></div>
                <div style="margin-bottom:0.75rem;">เลขบัญชี: <strong><?php echo e($accountNumber); ?></strong></div>
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

          <!-- CSRF token for JS -->
          <input type="hidden" id="csrfToken" value="<?php echo e($csrf); ?>">
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
              id="btnConfirmPayment"
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

            <a
              href="<?php echo $flow === 'deposit'
                      ? ('?page=detail&id=' . (int)$areaId)
                      : ('?page=history'); ?>"
              class="btn-cancel"
              style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">
              <span>ยกเลิก</span>
            </a>
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
                          'csrf'        => $csrf,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>