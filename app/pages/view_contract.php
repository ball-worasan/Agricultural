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
  app_log('view_contract_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('view_contract_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$contractServiceFile = APP_PATH . '/includes/ContractService.php';
if (!is_file($contractServiceFile)) {
  app_log('view_contract_service_missing', ['file' => $contractServiceFile]);
  // Continue without contract service
}

require_once $databaseFile;
require_once $helpersFile;
if (is_file($contractServiceFile)) {
  require_once $contractServiceFile;
}

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('view_contract_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
  app_log('view_contract_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Validate contract ID
// ----------------------------
$contractId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($contractId <= 0) {
  flash('error', 'ไม่พบข้อมูลสัญญา');
  redirect('?page=history');
}

// ----------------------------
// Fetch contract data with error handling
// ----------------------------
$contract = null;
try {
  $contract = Database::fetchOne(
  'SELECT c.*, 
            u.full_name AS tenant_name, u.phone AS tenant_phone,
            o.full_name AS owner_name, o.phone AS owner_phone,
            ra.area_name, ra.area_size
     FROM contract c
     JOIN booking_deposit bd ON c.booking_id = bd.booking_id
     JOIN users u ON bd.user_id = u.user_id
     JOIN rental_area ra ON bd.area_id = ra.area_id
     JOIN users o ON ra.user_id = o.user_id
     WHERE c.contract_id = ? AND (bd.user_id = ? OR ra.user_id = ?)',
    [$contractId, $userId, $userId]
  );
} catch (Throwable $e) {
  app_log('view_contract_fetch_error', [
    'contract_id' => $contractId,
    'user_id' => $userId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
}

if (!$contract) {
  flash('error', 'ไม่พบสัญญาหรือคุณไม่มีสิทธิ์เข้าถึง');
  redirect('?page=history');
}

// ดาวน์โหลด PDF
if (isset($_GET['download']) && $_GET['download'] === '1') {
  $pdfPath = ContractService::downloadPDF($contractId, $userId);
  if ($pdfPath && file_exists(APP_PATH . '/public' . $pdfPath)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="contract_' . $contractId . '.pdf"');
    readfile(APP_PATH . '/public' . $pdfPath);
    exit;
  } else {
    flash('error', 'ไม่พบไฟล์สัญญา');
    redirect('?page=view_contract&id=' . $contractId);
  }
}

$statusLabels = [
  'draft' => 'ร่าง',
  'waiting_signature' => 'รอการลงนาม',
  'signed' => 'ลงนามแล้ว',
  'active' => 'ใช้งานอยู่',
  'expired' => 'หมดอายุ',
  'terminated' => 'สิ้นสุด',
];

?>
<div class="contract-view-container">
  <div class="page-header">
    <h1>สัญญาเช่าพื้นที่เกษตร</h1>
    <div class="header-actions">
      <a href="?page=view_contract&id=<?= $contractId; ?>&download=1" class="btn-download">ดาวน์โหลด PDF</a>
      <a href="?page=history" class="back-link">← กลับประวัติ</a>
    </div>
  </div>

  <div class="contract-details">
    <div class="contract-info-section">
      <h2>ข้อมูลสัญญา</h2>
      <div class="info-grid">
        <div class="info-item">
          <label>เลขที่สัญญา</label>
          <p><?= e($contract['contract_number']); ?></p>
        </div>
        <div class="info-item">
          <label>สถานะ</label>
          <p><?= e($statusLabels[$contract['status']] ?? $contract['status']); ?></p>
        </div>
        <div class="info-item">
          <label>ระยะเวลาเช่า</label>
          <p><?= (int)$contract['rental_period_months']; ?> เดือน</p>
        </div>
        <div class="info-item">
          <label>วันที่เริ่มสัญญา</label>
          <p><?= date('d/m/Y', strtotime($contract['start_date'])); ?></p>
        </div>
        <div class="info-item">
          <label>วันที่สิ้นสุดสัญญา</label>
          <p><?= date('d/m/Y', strtotime($contract['end_date'])); ?></p>
        </div>
      </div>
    </div>

    <div class="contract-parties-section">
      <h2>คู่สัญญา</h2>
      <div class="parties-grid">
        <div class="party-card">
          <h3>ผู้ให้เช่า</h3>
          <p><strong>ชื่อ:</strong> <?= e($contract['owner_name']); ?></p>
          <p><strong>เบอร์โทร:</strong> <?= e($contract['owner_phone'] ?? '-'); ?></p>
        </div>
        <div class="party-card">
          <h3>ผู้เช่า</h3>
          <p><strong>ชื่อ:</strong> <?= e($contract['tenant_name']); ?></p>
          <p><strong>เบอร์โทร:</strong> <?= e($contract['tenant_phone'] ?? '-'); ?></p>
        </div>
      </div>
    </div>

    <div class="contract-property-section">
      <h2>ข้อมูลพื้นที่</h2>
      <div class="info-grid">
        <div class="info-item">
          <label>ชื่อพื้นที่</label>
          <p><?= e($contract['property_title']); ?></p>
        </div>
        <div class="info-item">
          <label>ที่ตั้ง</label>
          <p><?= e($contract['location'] . ', ' . $contract['province']); ?></p>
        </div>
      </div>
    </div>

    <div class="contract-financial-section">
      <h2>ข้อมูลการเงิน</h2>
      <div class="info-grid">
        <div class="info-item">
          <label>ค่าเช่าต่อเดือน</label>
          <p>฿<?= number_format((float)$contract['monthly_rent'], 2); ?></p>
        </div>
        <div class="info-item">
          <label>เงินมัดจำ</label>
          <p>฿<?= number_format((float)$contract['deposit_amount'], 2); ?></p>
        </div>
        <div class="info-item">
          <label>ยอดรวมทั้งปี</label>
          <p>฿<?= number_format((float)$contract['total_amount'], 2); ?></p>
        </div>
      </div>
    </div>

    <?php if (!empty($contract['terms_and_conditions'])): ?>
      <div class="contract-terms-section">
        <h2>เงื่อนไขและข้อตกลง</h2>
        <div class="terms-content">
          <?= nl2br(e($contract['terms_and_conditions'])); ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>