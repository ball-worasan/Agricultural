<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/ContractService.php';

app_session_start();

$user = current_user();
if ($user === null) {
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
$contractId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($contractId <= 0) {
    redirect('?page=history');
}

// ดึงข้อมูลสัญญา
$contract = Database::fetchOne(
    'SELECT c.*, 
            u.firstname AS tenant_firstname, u.lastname AS tenant_lastname,
            u.email AS tenant_email, u.phone AS tenant_phone,
            o.firstname AS owner_firstname, o.lastname AS owner_lastname,
            o.email AS owner_email, o.phone AS owner_phone,
            p.title AS property_title, p.location, p.province
     FROM contracts c
     JOIN users u ON c.user_id = u.id
     JOIN users o ON c.owner_id = o.id
     JOIN properties p ON c.property_id = p.id
     WHERE c.id = ? AND (c.user_id = ? OR c.owner_id = ?)',
    [$contractId, $userId, $userId]
);

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
                    <p><strong>ชื่อ:</strong> <?= e($contract['owner_firstname'] . ' ' . $contract['owner_lastname']); ?></p>
                    <p><strong>อีเมล:</strong> <?= e($contract['owner_email']); ?></p>
                    <p><strong>เบอร์โทร:</strong> <?= e($contract['owner_phone'] ?? '-'); ?></p>
                </div>
                <div class="party-card">
                    <h3>ผู้เช่า</h3>
                    <p><strong>ชื่อ:</strong> <?= e($contract['tenant_firstname'] . ' ' . $contract['tenant_lastname']); ?></p>
                    <p><strong>อีเมล:</strong> <?= e($contract['tenant_email']); ?></p>
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

