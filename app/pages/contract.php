<?php

declare(strict_types=1);

/**
 * contract.php (REFAC)
 * - Read contract / Create contract (Owner/Admin only)
 * - Booking must be approved
 * - Safe bootstrap + session + guards
 * - Upload optional PDF

 */

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile) || !is_file($helpersFile)) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// Fallback CSRF helpers for static analyzers (intelephense)
if (!function_exists('csrf_field') && function_exists('csrf_token')) {
  function csrf_field(): string
  {
    return '<input type="hidden" name="_csrf" value="' . e((string)csrf_token()) . '">';
  }
}
if (!function_exists('csrf_token_field') && function_exists('csrf_token')) {
  function csrf_token_field(): string
  {
    return csrf_field();
  }
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$termMonths = 12;

// ----------------------------
// Session
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('contract_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// Auth
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
  return;
}

$userId   = (int)($user['user_id'] ?? $user['id'] ?? 0);
$userRole = (int)($user['role'] ?? 0);
$isAdmin  = defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN;

if ($userId <= 0) {
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
  return;
}

// ----------------------------
// Input
// ----------------------------
$bookingId = (int)(
  filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
  ?? filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
  ?? 0
);

if ($bookingId <= 0) {
  flash('error', 'ไม่พบบุ๊กกิ้งที่ต้องการทำสัญญา');
  redirect('?page=history');
  return;
}

// ----------------------------
// Fetch booking (area + owner + tenant)
// ----------------------------
try {
  $booking = Database::fetchOne(
    'SELECT
        bd.booking_id, bd.area_id, bd.user_id AS tenant_id, bd.booking_date, bd.deposit_status, bd.deposit_amount,
        ra.area_name, ra.area_size, ra.price_per_year, ra.user_id AS owner_id,
        d.district_name, p.province_name,
        uo.full_name AS owner_name, uo.phone AS owner_phone,
        ut.full_name AS tenant_name, ut.phone AS tenant_phone
     FROM booking_deposit bd
     JOIN rental_area ra ON bd.area_id = ra.area_id
     JOIN district d      ON ra.district_id = d.district_id
     JOIN province p      ON d.province_id = p.province_id
     JOIN users uo ON ra.user_id = uo.user_id
     JOIN users ut ON bd.user_id = ut.user_id
     WHERE bd.booking_id = ?
     LIMIT 1',
    [$bookingId]
  );
} catch (Throwable $e) {
  app_log('contract_fetch_booking_error', ['booking_id' => $bookingId, 'error' => $e->getMessage()]);
  $booking = null;
}

if (!$booking) {
  flash('error', 'ไม่พบข้อมูลการจอง');
  redirect('?page=history');
  return;
}

$areaId   = (int)($booking['area_id'] ?? 0);
$ownerId  = (int)($booking['owner_id'] ?? 0);
$tenantId = (int)($booking['tenant_id'] ?? 0);

$isOwner  = ($userId === $ownerId);
$isTenant = ($userId === $tenantId);

// Admin/Owner/Tenant ดูได้ แต่ “สร้างสัญญา” ได้แค่ Owner/Admin
if (!$isAdmin && !$isOwner && !$isTenant) {
  flash('error', 'คุณไม่มีสิทธิ์เข้าถึงรายการนี้');
  redirect('?page=history');
  return;
}

$canCreateContract = ($isAdmin || $isOwner);

$depositStatus = (string)($booking['deposit_status'] ?? 'pending');
if ($depositStatus !== 'approved') {
  flash('error', 'การจองยังไม่ได้รับการอนุมัติ ไม่สามารถทำสัญญาได้');
  redirect('?page=history');
  return;
}

// ----------------------------
// Existing contract + payment status
// ----------------------------
try {
  $contract = Database::fetchOne(
    'SELECT c.contract_id, c.start_date, c.end_date, c.price_per_year, c.terms, c.contract_file, c.created_at,
            p.payment_id, p.status AS payment_status
       FROM contract c
       LEFT JOIN payment p ON p.contract_id = c.contract_id AND p.status IN ("pending", "confirmed")
      WHERE c.booking_id = ?
      LIMIT 1',
    [$bookingId]
  );
} catch (Throwable $e) {
  app_log('contract_existing_fetch_error', ['booking_id' => $bookingId, 'error' => $e->getMessage()]);
  $contract = null;
}

$paymentStatus = '';
if ($contract) {
  $paymentStatus = (string)($contract['payment_status'] ?? '');
}

// ----------------------------
// Fee + totals
// ----------------------------
try {
  $feeData = Database::fetchOne('SELECT fee_rate FROM fee LIMIT 1', []);
  $feeRate = (float)($feeData['fee_rate'] ?? 0);
} catch (Throwable $e) {
  app_log('contract_fee_fetch_error', ['error' => $e->getMessage()]);
  $feeRate = 0.0;
}

$areaName      = (string)($booking['area_name'] ?? '');
$areaSizeRai   = (float)($booking['area_size'] ?? 0); // NOTE: เก็บเป็น “ไร่” (ทศนิยม)
$pricePerYear  = (float)($booking['price_per_year'] ?? 0);
$depositAmount = (float)($booking['deposit_amount'] ?? 0);

$ownerName  = (string)($booking['owner_name'] ?? '');
$ownerPhone = (string)($booking['owner_phone'] ?? '');
$tenantName  = (string)($booking['tenant_name'] ?? '');
$tenantPhone = (string)($booking['tenant_phone'] ?? '');

$feeAmount    = $pricePerYear * ($feeRate / 100.0);
$totalDue     = $pricePerYear; // ผู้เช่าต้องชำระเฉพาะค่าเช่าเท่านั้น
$remainingDue = $totalDue - $depositAmount; // คงเหลือหลังหักมัดจำ

$defaultStart = date('Y-m-d');
$defaultEnd   = (new DateTimeImmutable($defaultStart))->modify('+' . $termMonths . ' months')->format('Y-m-d');

$errors = [];

$defaultTerms = "1. ผู้เช่าตกลงเช่าพื้นที่เพื่อทำเกษตรกรรมตามที่ตกลงเท่านั้น\n\n2. ผู้เช่าต้องดูแลรักษาพื้นที่ให้อยู่ในสภาพดี ไม่ทำลายหรือเปลี่ยนแปลงโครงสร้างโดยไม่ได้รับอนุญาต\n\n3. ค่าเช่าต้องชำระตามกำหนด หากล่าช้าเกิน 7 วัน อาจมีค่าปรับ 5% ต่อเดือน\n\n4. การเลิกสัญญาก่อนกำหนดต้องแจ้งล่วงหน้า 30 วัน เงินมัดจำอาจไม่ถูกคืนตามสภาพพื้นที่\n\n5. ผู้ให้เช่าสามารถเข้าตรวจสอบพื้นที่ได้โดยแจ้งล่วงหน้า 3 วัน\n\n6. เมื่อสิ้นสุดสัญญา ผู้เช่าต้องส่งมอบพื้นที่คืนในสภาพเดิม มิฉะนั้นเงินมัดจำจะถูกหักเพื่อซ่อมแซม\n\n7. การต่ออายุสัญญาต้องตกลงกันล่วงหน้าอย่างน้อย 60 วัน\n\n8. กรณีเหตุสุดวิสัย คู่สัญญาไม่ต้องรับผิดชอบต่อความเสียหายที่เกิดขึ้น";

// ----------------------------
// Create contract (POST)
// ----------------------------
$isCreatePost = ($method === 'POST' && isset($_POST['create_contract']));

if ($isCreatePost) {
  if (!$canCreateContract) {
    flash('error', 'คุณไม่มีสิทธิ์สร้างสัญญา เฉพาะผู้ปล่อยเช่าเท่านั้น');
    redirect('?page=history');
    return;
  }

  if ($contract) {
    // กันกดซ้ำ: ถ้ามีแล้วไปหน้าชำระเงิน/ดูสัญญาได้เลย
    flash('success', 'มีสัญญาอยู่แล้ว');
    redirect('?page=payment&contract_id=' . (int)$contract['contract_id'] . '&mode=full');
    return;
  }

  $startDate = trim((string)($_POST['start_date'] ?? ''));
  $terms     = trim((string)($_POST['terms'] ?? ''));

  $startObj = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
  $startErr = DateTimeImmutable::getLastErrors();

  if (!$startObj || ($startErr['warning_count'] ?? 0) > 0 || ($startErr['error_count'] ?? 0) > 0) {
    $errors[] = 'รูปแบบวันที่ไม่ถูกต้อง';
  }

  $endObj = $startObj ? $startObj->modify('+' . $termMonths . ' months') : null;
  if (!$endObj) {
    $errors[] = 'ไม่สามารถคำนวณวันสิ้นสุดสัญญาได้';
  }

  // Optional: upload PDF
  $contractFilePath = null;

  if (isset($_FILES['contract_file']) && ($_FILES['contract_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['contract_file'];

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      $errors[] = 'อัปโหลดไฟล์สัญญาไม่สำเร็จ';
    } else {
      $maxSize = 10 * 1024 * 1024; // 10MB
      $allowedTypes = ['application/pdf'];

      $tmp = (string)($file['tmp_name'] ?? '');
      $size = (int)($file['size'] ?? 0);

      if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'ไฟล์สัญญาไม่ถูกต้อง';
      } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmp) : null;

        if (!in_array((string)$mimeType, $allowedTypes, true)) {
          $errors[] = 'รองรับเฉพาะไฟล์ PDF';
        }
        if ($size > $maxSize) {
          $errors[] = 'ไฟล์สัญญาต้องไม่เกิน 10MB';
        }
      }

      if (empty($errors)) {
        $uploadDir = BASE_PATH . '/public/storage/uploads/contracts';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
          $errors[] = 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้';
        }

        if (empty($errors)) {
          $rand = bin2hex(random_bytes(8));
          $newFileName = sprintf('contract_%d_%s_%s.pdf', $bookingId, date('YmdHis'), $rand);
          $destination = $uploadDir . '/' . $newFileName;

          if (move_uploaded_file($tmp, $destination)) {
            $contractFilePath = '/storage/uploads/contracts/' . $newFileName;
          } else {
            $errors[] = 'อัปโหลดไฟล์ไม่สำเร็จ';
          }
        }
      }
    }
  }

  if (empty($errors)) {
    try {
      $newContractId = Database::transaction(function (PDO $pdo) use (
        $bookingId,
        $startObj,
        $endObj,
        $pricePerYear,
        $terms,
        $contractFilePath
      ) {
        Database::execute(
          'INSERT INTO contract (booking_id, start_date, end_date, price_per_year, terms, contract_file, created_at)
           VALUES (?, ?, ?, ?, ?, ?, NOW())',
          [
            $bookingId,
            $startObj->format('Y-m-d'),
            $endObj->format('Y-m-d'),
            $pricePerYear,
            $terms,
            $contractFilePath,
          ]
        );

        return (int)$pdo->lastInsertId();
      });

      app_log('contract_created', ['booking_id' => $bookingId, 'contract_id' => $newContractId, 'user_id' => $userId]);
      flash('success', 'สร้างสัญญาเช่า 1 ปีเรียบร้อยแล้ว');
      redirect('?page=property_bookings&id=' . $areaId);
      return;
    } catch (Throwable $e) {
      app_log('contract_create_error', ['booking_id' => $bookingId, 'error' => $e->getMessage()]);
      $errors[] = 'เกิดข้อผิดพลาดในการสร้างสัญญา: ' . $e->getMessage();
    }
  }

  // keep user inputs on error
  if ($startObj) {
    $defaultStart = $startObj->format('Y-m-d');
    $defaultEnd   = $startObj->modify('+' . $termMonths . ' months')->format('Y-m-d');
  }
}

?>
<div class="contract-container">
  <div class="contract-wrapper">
    <a href="?page=history" class="back-link">← กลับประวัติการจอง</a>

    <header class="contract-hero">
      <div class="hero-head">
        <div>
          <p class="eyebrow">สัญญาเช่า 1 ปี</p>
          <h1>สรุปสัญญาเช่าพื้นที่</h1>
          <p class="subtitle">พื้นที่: <?= e($areaName); ?> · Booking #<?= (int)$bookingId; ?></p>
        </div>
        <div class="hero-badges">
          <span class="badge">ผู้ให้เช่า: <?= e($ownerName); ?></span>
          <span class="badge">ผู้เช่า: <?= e($tenantName); ?></span>
        </div>
      </div>

      <div class="hero-stats">
        <div class="stat">
          <span class="stat-label">จังหวัด / อำเภอ</span>
          <strong class="stat-value"><?= e(($booking['province_name'] ?? '-') . ' · ' . ($booking['district_name'] ?? '-')); ?></strong>
        </div>
        <div class="stat">
          <span class="stat-label">ค่าเช่าต่อปี</span>
          <strong class="stat-value">฿<?= number_format($pricePerYear, 2); ?></strong>
        </div>
        <div class="stat">
          <span class="stat-label">ค่าธรรมเนียม (หักจากผู้ให้เช่า)</span>
          <strong class="stat-value"><?= $feeRate > 0 ? ('฿' . number_format($feeAmount, 2) . ' (' . number_format($feeRate, 2) . '%)') : 'ไม่มี'; ?></strong>
        </div>
        <div class="stat">
          <span class="stat-label">ผู้เช่าต้องชำระ</span>
          <strong class="stat-value">฿<?= number_format($totalDue, 2); ?></strong>
        </div>
      </div>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <strong>พบข้อผิดพลาด:</strong>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="info-cards">
      <div class="info-card">
        <div class="card-head">
          <span class="chip">พื้นที่</span>
          <h3><?= e($areaName); ?></h3>
        </div>
        <ul class="card-list">
          <li><span>รหัส Booking</span><strong>#<?= str_pad((string)$bookingId, 6, '0', STR_PAD_LEFT); ?></strong></li>
          <li><span>จังหวัด / อำเภอ</span><strong><?= e(($booking['province_name'] ?? '-') . ' · ' . ($booking['district_name'] ?? '-')); ?></strong></li>
          <li><span>ขนาดพื้นที่</span><strong><?= $areaSizeRai > 0 ? e(number_format($areaSizeRai, 2) . ' ไร่') : '-'; ?></strong></li>
          <li><span>ค่าเช่าต่อปี</span><strong>฿<?= number_format($pricePerYear, 2); ?></strong></li>
          <li><span>ค่าธรรมเนียม (หักผู้ให้เช่า)</span><strong><?= $feeRate > 0 ? ('฿' . number_format($feeAmount, 2)) : 'ไม่มี'; ?></strong></li>
          <li><span>มัดจำที่ชำระ</span><strong>฿<?= number_format($depositAmount, 2); ?></strong></li>
          <li><span>ผู้เช่าต้องชำระรวม</span><strong>฿<?= number_format($totalDue, 2); ?></strong></li>
          <li><span>คงเหลือหลังหักมัดจำ</span><strong>฿<?= number_format($remainingDue, 2); ?></strong></li>
        </ul>
      </div>

      <div class="info-card">
        <div class="card-head">
          <span class="chip">คู่สัญญา</span>
          <h3>ผู้ให้เช่า · ผู้เช่า</h3>
        </div>
        <ul class="card-list two-col">
          <li>
            <span>ผู้ให้เช่า</span>
            <div class="person">
              <strong><?= e($ownerName); ?></strong>
              <small><?= e($ownerPhone); ?></small>
            </div>
          </li>
          <li>
            <span>ผู้เช่า</span>
            <div class="person">
              <strong><?= e($tenantName); ?></strong>
              <small><?= e($tenantPhone); ?></small>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <?php if ($contract): ?>
      <section class="section-card">
        <div class="section-head">
          <span class="step-label">สถานะสัญญา</span>
          <p class="section-subtitle">สัญญาที่สร้างแล้ว</p>
        </div>

        <div class="details-grid">
          <div>
            <label>เริ่มสัญญา</label>
            <p><?= e((string)($contract['start_date'] ?? '-')); ?></p>
          </div>
          <div>
            <label>สิ้นสุด</label>
            <p><?= e((string)($contract['end_date'] ?? '-')); ?></p>
          </div>
          <div>
            <label>ค่าเช่าต่อปี (ผู้เช่าชำระ)</label>
            <p>฿<?= number_format((float)($contract['price_per_year'] ?? 0), 2); ?></p>
          </div>
          <div>
            <label>ค่าธรรมเนียม (หักผู้ให้เช่า)</label>
            <p><?= $feeRate > 0 ? ('฿' . number_format($feeAmount, 2)) : 'ไม่มี'; ?></p>
          </div>
          <div>
            <label>รวมผู้เช่าต้องชำระ</label>
            <p>฿<?= number_format($totalDue, 2); ?></p>
          </div>
          <div>
            <label>สร้างเมื่อ</label>
            <p><?= e((string)($contract['created_at'] ?? '-')); ?></p>
          </div>
        </div>

        <?php if (!empty($contract['terms'])): ?>
          <div class="form-group" style="margin-top: 1rem;">
            <label>เงื่อนไขเพิ่มเติม</label>
            <textarea rows="8" readonly><?= e((string)$contract['terms']); ?></textarea>
          </div>
        <?php endif; ?>

        <?php if (!empty($contract['contract_file'])): ?>
          <p class="download-link">
            <a href="<?= e((string)$contract['contract_file']); ?>" target="_blank" rel="noopener">📄 ดาวน์โหลดไฟล์สัญญา</a>
          </p>
        <?php endif; ?>

        <?php if ($isTenant): ?>
          <div class="form-actions">
            <?php if ($paymentStatus === 'confirmed'): ?>
              <button class="btn-submit" disabled title="ชำระแล้ว">✅ ชำระแล้ว</button>
            <?php elseif ($paymentStatus === 'pending'): ?>
              <button class="btn-submit" disabled title="รอตรวจสอบ">⏳ รอตรวจสอบ</button>
            <?php else: ?>
              <a href="?page=payment&contract_id=<?= (int)$contract['contract_id']; ?>&mode=full" class="btn-submit">ไปหน้าชำระเงิน</a>
            <?php endif; ?>
            <a href="?page=history" class="btn-cancel">กลับประวัติการจอง</a>
          </div>
        <?php else: ?>
          <div class="form-actions">
            <a href="?page=history" class="btn-cancel">กลับประวัติการจอง</a>
          </div>
        <?php endif; ?>
      </section>

    <?php elseif ($canCreateContract): ?>
      <form method="POST" enctype="multipart/form-data" class="contract-form">
        <input type="hidden" name="create_contract" value="1">
        <input type="hidden" name="booking_id" value="<?= (int)$bookingId; ?>">
        <?php if (function_exists('csrf_field')): ?>
          <?= csrf_field(); ?>
        <?php elseif (function_exists('csrf_token_field')): ?>
          <?= csrf_token_field(); ?>
        <?php endif; ?>

        <section class="section-card">
          <div class="section-head">
            <span class="step-label">ขั้นตอนที่ 1</span>
            <h3>ตั้งค่าวันที่สัญญา</h3>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="start_date">วันที่เริ่มสัญญา</label>
              <input id="start_date" name="start_date" type="date" required value="<?= e($defaultStart); ?>">
            </div>
            <div class="form-group">
              <label for="end_date">สิ้นสุด (อัตโนมัติ +12 เดือน)</label>
              <input id="end_date" type="date" value="<?= e($defaultEnd); ?>" readonly>
            </div>
          </div>
        </section>

        <section class="section-card">
          <div class="section-head">
            <span class="step-label">ขั้นตอนที่ 2</span>
            <h3>สรุปค่าเช่า (ค่าธรรมเนียมจะหักจากผู้ให้เช่า)</h3>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>ค่าเช่าต่อปี (ผู้เช่าชำระ)</label>
              <input type="text" value="฿<?= number_format($pricePerYear, 2); ?>" readonly>
            </div>
            <div class="form-group">
              <label>ค่าธรรมเนียม (หักจากผู้ให้เช่า)</label>
              <input type="text" value="<?= $feeRate > 0 ? ('฿' . number_format($feeAmount, 2) . ' (' . number_format($feeRate, 2) . '%)') : 'ไม่มี'; ?>" readonly>
            </div>
            <div class="form-group">
              <label>ผู้เช่าต้องชำระทั้งสิ้น</label>
              <input type="text" value="฿<?= number_format($totalDue, 2); ?>" readonly>
            </div>
          </div>
        </section>

        <section class="section-card">
          <div class="section-head">
            <span class="step-label">ขั้นตอนที่ 3</span>
            <h3>เงื่อนไขเพิ่มเติม</h3>
          </div>
          <div class="form-group">
            <label for="terms">รายละเอียดเงื่อนไข</label>
            <textarea id="terms" name="terms" rows="8" placeholder="ระบุเงื่อนไขหรือบันทึกเพิ่มเติม (ถ้ามี)"><?= e((string)($_POST['terms'] ?? $defaultTerms)); ?></textarea>
          </div>
        </section>

        <section class="section-card">
          <div class="section-head">
            <span class="step-label">ขั้นตอนที่ 4</span>
            <h3>แนบไฟล์สัญญา (เลือกได้)</h3>
          </div>
          <div class="form-group">
            <label for="contract_file">ไฟล์ PDF (ไม่เกิน 10MB)</label>
            <input id="contract_file" name="contract_file" type="file" accept="application/pdf">
          </div>
        </section>

        <div class="form-actions">
          <button type="submit" class="btn-submit">สร้างสัญญาและไปชำระเงิน</button>
          <a href="?page=history" class="btn-cancel">ยกเลิก</a>
        </div>
      </form>

    <?php else: ?>
      <div class="alert alert-error">
        <strong>ข้อสังเกต:</strong>
        <p>เฉพาะผู้ปล่อยเช่าเท่านั้นที่สามารถสร้างสัญญาได้ คุณดูได้อย่างเดียว</p>
      </div>

      <section class="section-card">
        <div class="section-head">
          <span class="step-label">สถานะการจอง</span>
          <p class="section-subtitle">รอการสร้างสัญญา</p>
        </div>
        <div class="details-grid">
          <div><label>ผู้ปล่อยเช่า</label>
            <p><?= e($ownerName); ?></p>
          </div>
          <div><label>ผู้เช่า</label>
            <p><?= e($tenantName); ?></p>
          </div>
          <div><label>ค่าเช่าต่อปี</label>
            <p>฿<?= number_format($pricePerYear, 2); ?></p>
          </div>
          <div><label>มัดจำที่ชำระ</label>
            <p>฿<?= number_format($depositAmount, 2); ?></p>
          </div>
        </div>
        <p style="margin-top: 1rem; color: var(--text-secondary);">⏳ รอให้ผู้ปล่อยเช่าสร้างสัญญาเช่า</p>
      </section>
    <?php endif; ?>
  </div>
</div>

<script>
  (function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    if (!startDateInput || !endDateInput) return;

    const addMonths = (date, months) => {
      const d = new Date(date.getTime());
      const day = d.getDate();
      d.setMonth(d.getMonth() + months);

      // กันเด้งเดือน (เช่น 31 -> เดือนที่ไม่มี 31)
      if (d.getDate() < day) d.setDate(0);
      return d;
    };

    const updateEndDate = () => {
      const startVal = startDateInput.value;
      if (!startVal) return;

      const start = new Date(startVal + 'T00:00:00');
      const end = addMonths(start, 12);
      endDateInput.value = end.toISOString().slice(0, 10);
    };

    startDateInput.addEventListener('change', updateEndDate);
  })();
</script>