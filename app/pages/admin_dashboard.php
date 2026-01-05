<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 2));
}

$databaseFile = APP_PATH . '/config/database.php';
if (!is_file($databaseFile)) {
  app_log('admin_dashboard_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('admin_dashboard_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
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
  app_log('admin_dashboard_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์แอดมิน
// ----------------------------
$user = current_user();

if ($user === null || ($user['role'] ?? 0) !== ROLE_ADMIN) {
  flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
  redirect('?page=signin');
}

// ----------------------------
// ดึงสถิติแบบกันพลาด
// ----------------------------
$stats = [
  'total_areas'           => 0,
  'available_areas'       => 0,
  'booked_areas'          => 0,
  'unavailable_areas'     => 0,
  'total_users'           => 0,
  'total_bookings'        => 0,
  'pending_bookings'      => 0,
  'approved_bookings'     => 0,
];

try {
  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM rental_area');
  $stats['total_areas'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM rental_area WHERE area_status = "available"');
  $stats['available_areas'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM rental_area WHERE area_status = "booked"');
  $stats['booked_areas'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM rental_area WHERE area_status = "unavailable"');
  $stats['unavailable_areas'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM users');
  $stats['total_users'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM booking_deposit');
  $stats['total_bookings'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM booking_deposit WHERE deposit_status = "pending"');
  $stats['pending_bookings'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM booking_deposit WHERE deposit_status = "approved"');
  $stats['approved_bookings'] = (int) ($row['count'] ?? 0);
} catch (Throwable $e) {
  app_log('admin_stats_error', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
}

// รายได้
try {
  $depositRow = Database::fetchOne(
    'SELECT SUM(deposit_amount) AS total_deposit FROM booking_deposit WHERE deposit_status = "approved"'
  );
  $paymentRow = Database::fetchOne(
    'SELECT SUM(net_amount) AS total_net FROM payment WHERE status = "confirmed"'
  );
  $revenue = [
    'total_deposit' => (float) ($depositRow['total_deposit'] ?? 0),
    'total_revenue' => (float) ($paymentRow['total_net'] ?? 0),
  ];
} catch (Throwable $e) {
  app_log('admin_revenue_error', ['error' => $e->getMessage()]);
  $revenue = ['total_deposit' => 0.0, 'total_revenue' => 0.0];
}

// ---------- จัดการคำสั่ง ----------
$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');

  try {
    if ($action === 'delete_area') {
      $areaId = (int) ($_POST['area_id'] ?? 0);

      // ลบไฟล์รูปภาพจริง (optional)
      $images = Database::fetchAll(
        'SELECT image_url FROM area_image WHERE area_id = ?',
        [$areaId]
      );
      foreach ($images as $img) {
        $url = (string) ($img['image_url'] ?? '');
        if ($url !== '') {
          $filePath = dirname(APP_PATH) . $url; // image_url เช่น /storage/uploads/areas/xxx.jpg
          if (is_file($filePath)) {
            @unlink($filePath);
          }
        }
      }

      // FK cascade จะลบ area_image และ booking_deposit ให้อัตโนมัติ
      Database::execute('DELETE FROM rental_area WHERE area_id = ?', [$areaId]);

      $message     = 'ลบพื้นที่เรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'update_area_status') {
      $areaId = (int) ($_POST['area_id'] ?? 0);
      $status = (string) ($_POST['status'] ?? 'available');

      $allowedStatus = ['available', 'booked', 'unavailable'];
      if (!in_array($status, $allowedStatus, true)) {
        $status = 'available';
      }

      Database::execute(
        'UPDATE rental_area SET area_status = ? WHERE area_id = ?',
        [$status, $areaId]
      );

      $message     = 'อัปเดตสถานะพื้นที่เรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'update_deposit_status') {
      $bookingId     = (int) ($_POST['booking_id'] ?? 0);
      $depositStatus = (string) ($_POST['deposit_status'] ?? 'pending');

      $allowed = ['pending', 'approved', 'rejected'];
      if (!in_array($depositStatus, $allowed, true)) {
        $depositStatus = 'pending';
      }

      // อัปเดต booking_deposit
      Database::execute(
        'UPDATE booking_deposit SET deposit_status = ? WHERE booking_id = ?',
        [$depositStatus, $bookingId]
      );

      // ปรับสถานะพื้นที่ให้สอดคล้อง
      $b = Database::fetchOne('SELECT area_id FROM booking_deposit WHERE booking_id = ?', [$bookingId]);
      $areaId = (int) ($b['area_id'] ?? 0);
      if ($areaId > 0) {
        if ($depositStatus === 'approved') {
          Database::execute('UPDATE rental_area SET area_status = "booked" WHERE area_id = ?', [$areaId]);
        } elseif ($depositStatus === 'rejected') {
          Database::execute('UPDATE rental_area SET area_status = "available" WHERE area_id = ?', [$areaId]);
        }
      }

      $message     = 'อัปเดตสถานะการมัดจำเรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'delete_booking') {
      $bookingId = (int) ($_POST['booking_id'] ?? 0);
      $b = Database::fetchOne('SELECT area_id FROM booking_deposit WHERE booking_id = ?', [$bookingId]);
      $areaId = (int) ($b['area_id'] ?? 0);

      Database::execute('DELETE FROM booking_deposit WHERE booking_id = ?', [$bookingId]);

      if ($areaId > 0) {
        Database::execute('UPDATE rental_area SET area_status = "available" WHERE area_id = ?', [$areaId]);
      }

      $message     = 'ลบการจองเรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'delete_user') {
      $userIdToDelete = (int) ($_POST['user_id'] ?? 0);

      // กันลบตัวเอง
      if ($userIdToDelete !== (int) ($user['user_id'] ?? 0)) {
        Database::execute('DELETE FROM users WHERE user_id = ?', [$userIdToDelete]);

        $message     = 'ลบผู้ใช้เรียบร้อยแล้ว';
        $messageType = 'success';
      } else {
        $message     = 'ไม่สามารถลบผู้ใช้ที่กำลังล็อกอินอยู่ได้';
        $messageType = 'error';
      }
    } elseif ($action === 'save_fee') {
      try {
        $feeRate = (float) ($_POST['fee_rate'] ?? 0);
        $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
        $accountName   = trim((string) ($_POST['account_name'] ?? ''));
        $bankName      = trim((string) ($_POST['bank_name'] ?? ''));

        if ($feeRate < 0 || $feeRate > 100 || $accountNumber === '' || $accountName === '' || $bankName === '' || $effectiveFrom === '') {
          throw new RuntimeException('Invalid fee data');
        }

        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $existingFee = Database::fetchOne('SELECT fee_id FROM fee LIMIT 1');

        if ($existingFee) {
          // อัปเดตข้อมูลแถวแรก
          Database::execute(
            'UPDATE fee SET fee_rate = ?, account_number = ?, account_name = ?, bank_name = ?, updated_at = CURRENT_TIMESTAMP WHERE fee_id = ?',
            [$feeRate, $accountNumber, $accountName, $bankName, (int)$existingFee['fee_id']]
          );
          $message = 'อัปเดตค่าธรรมเนียมสำเร็จ';
        } else {
          // เพิ่มข้อมูลใหม่
          Database::execute(
            'INSERT INTO fee (fee_rate, account_number, account_name, bank_name) VALUES (?, ?, ?, ?)',
            [$feeRate, $accountNumber, $accountName, $bankName]
          );
          $message = 'บันทึกค่าธรรมเนียมสำเร็จ';
        }

        $messageType = 'success';
      } catch (Throwable $e) {
        app_log('admin_save_fee_error', ['error' => $e->getMessage()]);
        $message = 'เกิดข้อผิดพลาดในการบันทึกค่าธรรมเนียม';
        $messageType = 'error';
      }
    }
  } catch (Throwable $e) {
    app_log('admin_action_error', [
      'action' => $action,
      'error'  => $e->getMessage(),
    ]);
    $message     = 'เกิดข้อผิดพลาดในการดำเนินการ';
    $messageType = 'error';
  }

  header('Location: ?page=admin_dashboard&msg=' . urlencode($message) . '&type=' . urlencode($messageType));
  exit();
}

// ---------- แสดงข้อความจาก URL ----------
if (isset($_GET['msg'])) {
  $message     = (string) $_GET['msg'];
  $messageType = (string) ($_GET['type'] ?? 'info');
}

// ---------- ดึงข้อมูลล่าสุด ----------
try {
  $recentProperties = Database::fetchAll(
    '
      SELECT 
        ra.area_id,
        ra.user_id AS owner_id,
        ra.area_name,
        ra.price_per_year,
        ra.area_status,
        ra.created_at,
        d.district_name,
        p.province_name
      FROM rental_area ra
      LEFT JOIN district d ON ra.district_id = d.district_id
      LEFT JOIN province p ON d.province_id = p.province_id
      ORDER BY ra.created_at DESC
      LIMIT 10
    '
  );
} catch (Throwable $e) {
  app_log('admin_recent_properties_error', ['error' => $e->getMessage()]);
  $recentProperties = [];
}

try {
  $recentBookings = Database::fetchAll(
    '
      SELECT 
        bd.booking_id,
        bd.area_id,
        bd.user_id,
        bd.booking_date,
        bd.deposit_status,
        bd.deposit_amount,
        bd.payment_slip,
        bd.created_at,
        ra.area_name,
        u.full_name
      FROM booking_deposit bd
      LEFT JOIN rental_area ra ON bd.area_id = ra.area_id
      LEFT JOIN users u ON bd.user_id = u.user_id
      ORDER BY bd.created_at DESC
      LIMIT 10
    '
  );
} catch (Throwable $e) {
  app_log('admin_recent_bookings_error', ['error' => $e->getMessage()]);
  $recentBookings = [];
}

try {
  $allUsers = Database::fetchAll(
    'SELECT user_id, username, full_name, phone, role, created_at FROM users ORDER BY created_at DESC'
  );
} catch (Throwable $e) {
  app_log('admin_all_users_error', ['error' => $e->getMessage()]);
  $allUsers = [];
}
?>

<div class="admin-dashboard">
  <div class="admin-header">
    <h1>🎛️ แดชบอร์ดผู้ดูแลระบบ</h1>
    <div class="header-actions">
      <a href="?page=payment_verification" class="btn-action">ตรวจสอบการชำระเงิน</a>
      <a href="?page=reports" class="btn-action">รายงานและสถิติ</a>
      <a href="?page=home" class="btn-back">← กลับหน้าหลัก</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= e($messageType); ?>">
      <?= e($message); ?>
    </div>
  <?php endif; ?>

  <!-- สถิติรวม -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">🏡</div>
      <div class="stat-info">
        <div class="stat-label">พื้นที่ทั้งหมด</div>
        <div class="stat-value"><?= number_format($stats['total_areas']); ?></div>
      </div>
    </div>
    <div class="stat-card available">
      <div class="stat-icon">✅</div>
      <div class="stat-info">
        <div class="stat-label">พื้นที่ว่าง</div>
        <div class="stat-value"><?= number_format($stats['available_areas']); ?></div>
      </div>
    </div>
    <div class="stat-card booked">
      <div class="stat-icon">📋</div>
      <div class="stat-info">
        <div class="stat-label">ติดจอง</div>
        <div class="stat-value"><?= number_format($stats['booked_areas']); ?></div>
      </div>
    </div>
    <div class="stat-card sold">
      <div class="stat-icon">🔒</div>
      <div class="stat-info">
        <div class="stat-label">ปิดให้เช่า</div>
        <div class="stat-value"><?= number_format($stats['unavailable_areas']); ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-info">
        <div class="stat-label">ผู้ใช้ทั้งหมด</div>
        <div class="stat-value"><?= number_format($stats['total_users']); ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📅</div>
      <div class="stat-info">
        <div class="stat-label">การจองทั้งหมด</div>
        <div class="stat-value"><?= number_format($stats['total_bookings']); ?></div>
      </div>
    </div>
    <div class="stat-card pending">
      <div class="stat-icon">⏳</div>
      <div class="stat-info">
        <div class="stat-label">รอดำเนินการ</div>
        <div class="stat-value"><?= number_format($stats['pending_bookings']); ?></div>
      </div>
    </div>
    <div class="stat-card revenue">
      <div class="stat-icon">💰</div>
      <div class="stat-info">
        <div class="stat-label">รายได้รวม</div>
        <div class="stat-value">฿<?= number_format($revenue['total_revenue']); ?></div>
      </div>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <div class="admin-tabs">
    <button class="tab-btn active" data-tab="properties">🏡 จัดการพื้นที่</button>
    <button class="tab-btn" data-tab="bookings">📋 จัดการการจอง</button>
    <button class="tab-btn" data-tab="users">👥 จัดการผู้ใช้</button>
    <button class="tab-btn" data-tab="settings">⚙️ ตั้งค่า</button>
  </div>

  <!-- Tab: Properties -->
  <div id="tab-properties" class="tab-content active">
    <div class="section-header">
      <h2>รายการพื้นที่เกษตร</h2>
    </div>
    <div class="table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อพื้นที่</th>
            <th>อำเภอ</th>
            <th>จังหวัด</th>
            <th>ราคา/ปี</th>
            <th>สถานะ</th>
            <th>วันที่สร้าง</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentProperties as $prop): ?>
            <tr>
              <td><?= e((string) $prop['area_id']); ?></td>
              <td><?= e((string) $prop['area_name']); ?></td>
              <td><?= e((string) ($prop['district_name'] ?? 'ไม่ระบุ')); ?></td>
              <td><?= e((string) ($prop['province_name'] ?? 'ไม่ระบุ')); ?></td>
              <td>฿<?= number_format((float) $prop['price_per_year']); ?></td>
              <td>
                <form method="POST" style="display:inline;" class="auto-submit-form">
                  <input type="hidden" name="action" value="update_area_status">
                  <input type="hidden" name="area_id" value="<?= (int) $prop['area_id']; ?>">
                  <select name="status" class="status-select auto-submit">
                    <option value="available" <?= $prop['area_status'] === 'available' ? 'selected' : ''; ?>>ว่าง</option>
                    <option value="booked" <?= $prop['area_status'] === 'booked'    ? 'selected' : ''; ?>>ติดจอง</option>
                    <option value="unavailable" <?= $prop['area_status'] === 'unavailable' ? 'selected' : ''; ?>>ปิดให้เช่า</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $prop['created_at'])); ?></td>
              <td class="actions">
                <a href="?page=detail&id=<?= (int) $prop['area_id']; ?>" class="btn-action view" title="ดูรายละเอียด">👁️</a>
                <a href="?page=edit_property&id=<?= (int) $prop['area_id']; ?>" class="btn-action edit" title="แก้ไข">✏️</a>
                <form method="POST" style="display:inline;" class="confirm-form" data-confirm="ยืนยันการลบพื้นที่นี้?">
                  <input type="hidden" name="action" value="delete_area">
                  <input type="hidden" name="area_id" value="<?= (int) $prop['area_id']; ?>">
                  <button type="submit" class="btn-action delete" title="ลบ">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($recentProperties)): ?>
            <tr>
              <td colspan="8" class="text-muted" style="text-align:center;">ยังไม่มีรายการพื้นที่</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tab: Bookings -->
  <div id="tab-bookings" class="tab-content">
    <div class="section-header">
      <h2>รายการจอง</h2>
    </div>
    <div class="table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ผู้จอง</th>
            <th>พื้นที่</th>
            <th>วันที่นัด</th>
            <th>มัดจำ</th>
            <th>สลิป</th>
            <th>สถานะมัดจำ</th>
            <th>วันที่จอง</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentBookings as $booking): 
            $paymentSlip = (string)($booking['payment_slip'] ?? '');
            $hasSlip = !empty($paymentSlip);
          ?>
            <tr>
              <td><?= e((string) $booking['booking_id']); ?></td>
              <td>
                <?= e((string) $booking['full_name']); ?>
              </td>
              <td><?= e((string) $booking['area_name']); ?></td>
              <td><?= date('d/m/Y', strtotime((string) $booking['booking_date'])); ?></td>
              <td>฿<?= number_format((float) $booking['deposit_amount']); ?></td>
              <td>
                <?php if ($hasSlip): ?>
                  <button type="button" class="btn-view-slip" data-slip-url="<?= e($paymentSlip); ?>" data-booking-id="<?= e((string) $booking['booking_id']); ?>" title="ดูสลิป">
                    📄 ดูสลิป
                  </button>
                <?php else: ?>
                  <span class="status-badge" style="background: #f5f5f5; color: #999;">ไม่มีสลิป</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:inline;" class="auto-submit-form">
                  <input type="hidden" name="action" value="update_deposit_status">
                  <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id']; ?>">
                  <select name="deposit_status" class="status-select auto-submit">
                    <option value="pending" <?= $booking['deposit_status'] === 'pending'   ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="approved" <?= $booking['deposit_status'] === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                    <option value="rejected" <?= $booking['deposit_status'] === 'rejected' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $booking['created_at'])); ?></td>
              <td class="actions">
                <form method="POST" style="display:inline;" class="confirm-form" data-confirm="ยืนยันการลบการจองนี้?">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id']; ?>">
                  <button type="submit" class="btn-action delete" title="ลบ">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($recentBookings)): ?>
            <tr>
              <td colspan="9" class="text-muted" style="text-align:center;">ยังไม่มีรายการจอง</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tab: Users -->
  <div id="tab-users" class="tab-content">
    <div class="section-header">
      <h2>รายการผู้ใช้</h2>
    </div>
    <div class="table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อ-นามสกุล</th>
            <th>ชื่อผู้ใช้</th>
            <th>เบอร์โทร</th>
            <th>สิทธิ์</th>
            <th>วันที่สมัคร</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allUsers as $u): ?>
            <tr>
              <td><?= e((string) $u['user_id']); ?></td>
              <td><?= e((string) $u['full_name']); ?></td>
              <td><?= e((string) ($u['username'] ?? '')); ?></td>
              <td><?= e((string) ($u['phone'] ?? '')); ?></td>
              <td>
                <span class="badge badge-<?= (int)($u['role'] ?? 0) === ROLE_ADMIN ? 'admin' : 'user'; ?>">
                  <?= (int)($u['role'] ?? 0) === ROLE_ADMIN ? 'ผู้ดูแล' : 'สมาชิก'; ?>
                </span>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $u['created_at'])); ?></td>
              <td class="actions">
                <?php if ((int) $u['user_id'] !== (int) ($user['user_id'] ?? 0)): ?>
                  <form method="POST" style="display:inline;" class="confirm-form" data-confirm="ยืนยันการลบผู้ใช้นี้?">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id']; ?>">
                    <button type="submit" class="btn-action delete" title="ลบ">🗑️</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">ตัวเอง</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($allUsers)): ?>
            <tr>
              <td colspan="7" class="text-muted" style="text-align:center;">ยังไม่มีผู้ใช้</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tab: Settings -->
  <div id="tab-settings" class="tab-content">
    <div class="section-header">
      <h2>ตั้งค่าระบบ (ค่าธรรมเนียมและบัญชี)</h2>
      <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">📌 ระบบรองรับค่าธรรมเนียมเพียง 1 ชุดเท่านั้น</p>
    </div>
    <?php
    try {
      $currentFee = Database::fetchOne('SELECT fee_id, fee_rate, account_number, account_name, bank_name, created_at, updated_at FROM fee LIMIT 1');
    } catch (Throwable $e) {
      app_log('admin_fee_fetch_error', ['error' => $e->getMessage()]);
      $currentFee = null;
    }
    ?>

    <?php if ($currentFee): ?>
      <div class="table-container">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ค่าธรรมเนียม (%)</th>
              <th>เลขบัญชี/พร้อมเพย์</th>
              <th>ชื่อบัญชี</th>
              <th>ธนาคาร</th>
              <th>อัปเดตล่าสุด</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= number_format((float) $currentFee['fee_rate'], 2); ?>%</td>
              <td><?= e((string) $currentFee['account_number']); ?></td>
              <td><?= e((string) $currentFee['account_name']); ?></td>
              <td><?= e((string) $currentFee['bank_name']); ?></td>
              <td><?= date('d/m/Y H:i', strtotime((string) $currentFee['updated_at'])); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info" style="background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
        <strong>ℹ️ ยังไม่มีข้อมูลค่าธรรมเนียม</strong> กรุณากรอกข้อมูลด้านล่างเพื่อเพิ่มข้อมูล
      </div>
    <?php endif; ?>

    <div class="section-header">
      <h3><?= $currentFee ? '✏️ แก้ไขข้อมูลค่าธรรมเนียม' : '➕ เพิ่มข้อมูลค่าธรรมเนียม'; ?></h3>
    </div>
    <form method="POST" class="settings-form">
      <input type="hidden" name="action" value="save_fee">
      <div class="form-row">
        <label>ค่าธรรมเนียม (%) <span style="color: red;">*</span></label>
        <input type="number" step="0.01" min="0" max="100" name="fee_rate" value="<?= $currentFee ? e((string)$currentFee['fee_rate']) : ''; ?>" required>
        <small style="color: var(--text-secondary);">ระบุเป็นเปอร์เซ็นต์ เช่น 5.00 หมายถึง 5%</small>
      </div>
      <div class="form-row">
        <label>เลขบัญชี/พร้อมเพย์ <span style="color: red;">*</span></label>
        <input type="text" name="account_number" value="<?= $currentFee ? e((string)$currentFee['account_number']) : ''; ?>" placeholder="เช่น 0641365430 หรือ 123-4-56789-0" required>
        <small style="color: var(--text-secondary);">สามารถใส่เบอร์พร้อมเพย์หรือเลขบัญชีธนาคารได้</small>
      </div>
      <div class="form-row">
        <label>ชื่อบัญชี <span style="color: red;">*</span></label>
        <input type="text" name="account_name" value="<?= $currentFee ? e((string)$currentFee['account_name']) : ''; ?>" placeholder="เช่น นายสมชาย ใจดี" required>
      </div>
      <div class="form-row">
        <label>ธนาคาร <span style="color: red;">*</span></label>
        <input type="text" name="bank_name" value="<?= $currentFee ? e((string)$currentFee['bank_name']) : ''; ?>" placeholder="เช่น ธนาคารกสิกรไทย" required>
      </div>
      <button type="submit" class="btn btn-primary"><?= $currentFee ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มข้อมูล'; ?></button>
    </form>
  </div>
</div>

<!-- Modal สำหรับแสดงสลิป -->
<div id="slipModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>ตรวจสอบสลิปการโอนเงิน</h2>
      <button class="modal-close" id="closeSlipBtn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="slip-preview">
        <img id="slipImage" src="" alt="สลิปการโอนเงิน" style="max-width: 100%; height: auto; border-radius: 8px;">
      </div>
      <div class="slip-info" style="margin-top: 1rem;">
        <p><strong>รหัสการจอง:</strong> <span id="slipBookingId"></span></p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="closeSlipFooterBtn">ปิด</button>
    </div>
  </div>
</div>

<style>
  /* Modal สไตล์ */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
  }

  .modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
  }

  .modal-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--text-primary);
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.3s;
  }

  .modal-close:hover {
    background: #f5f5f5;
    color: #333;
  }

  .modal-body {
    padding: 1.5rem;
  }

  .slip-preview {
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f9f9f9;
    border-radius: 8px;
    padding: 1rem;
    min-height: 300px;
  }

  .slip-preview img {
    max-width: 100%;
    height: auto;
  }

  .slip-info {
    background: #f5f5f5;
    padding: 1rem;
    border-radius: 6px;
  }

  .slip-info p {
    margin: 0.5rem 0;
    color: var(--text-secondary);
  }

  .modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
  }

  .btn-view-slip {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
  }

  .btn-view-slip:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
  }

  .btn-secondary {
    background: #f5f5f5;
    color: var(--text-primary);
    border: 1px solid #ddd;
    padding: 0.6rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
  }

  .btn-secondary:hover {
    background: #e9e9e9;
  }

  @media (max-width: 768px) {
    .modal-content {
      width: 95%;
    }

    .modal-header {
      padding: 1rem;
    }

    .modal-body {
      padding: 1rem;
    }
  }
</style>

<script>
  /* ใช้สไตล์เดิม + นิดหน่อย แทบไม่แตะ */
  <?= '' /* keep your CSS as-is, already ok */ ?>
</script>

<script>
  function switchTab(evt, tabName) {
    const tabs = document.querySelectorAll('.tab-content');

    tabs.forEach((tab) => tab.classList.remove('active'));

    const btns = document.querySelectorAll('.tab-btn');
    btns.forEach((btn) => btn.classList.remove('active'));

    const targetTab = document.getElementById('tab-' + tabName);
    if (targetTab) targetTab.classList.add('active');

    if (evt && evt.currentTarget) {
      evt.currentTarget.classList.add('active');
    }
  }

  // ฟังก์ชันสำหรับแสดง Modal สลิป
  function openSlipModal(slipUrl, bookingId) {
    const modal = document.getElementById('slipModal');
    const img = document.getElementById('slipImage');
    const bookingIdSpan = document.getElementById('slipBookingId');

    img.src = slipUrl;
    bookingIdSpan.textContent = bookingId;
    modal.classList.add('show');
  }

  function closeSlipModal() {
    const modal = document.getElementById('slipModal');
    modal.classList.remove('show');
  }

  // ปิด Modal เมื่อคลิกนอกพื้นที่
  window.addEventListener('click', function(event) {
    const modal = document.getElementById('slipModal');
    if (event.target === modal) {
      closeSlipModal();
    }
  });

  // ปิด Modal ด้วย ESC key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
      closeSlipModal();
    }
  });
</script>