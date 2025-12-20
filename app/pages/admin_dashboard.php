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

if ($user === null || ($user['role'] ?? '') !== 'admin') {
  flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
  redirect('?page=signin');
}

// ----------------------------
// ดึงสถิติแบบกันพลาด
// ----------------------------
$stats = [
  'total_properties'      => 0,
  'available_properties'  => 0,
  'booked_properties'     => 0,
  'sold_properties'       => 0,
  'total_users'           => 0,
  'total_bookings'        => 0,
  'pending_bookings'      => 0,
  'confirmed_bookings'    => 0,
];

try {
  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM properties');
  $stats['total_properties'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM properties WHERE status = "available"');
  $stats['available_properties'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM properties WHERE status = "booked"');
  $stats['booked_properties'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM properties WHERE status = "sold"');
  $stats['sold_properties'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM users');
  $stats['total_users'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM bookings');
  $stats['total_bookings'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM bookings WHERE booking_status = "pending"');
  $stats['pending_bookings'] = (int) ($row['count'] ?? 0);

  $row = Database::fetchOne('SELECT COUNT(*) AS count FROM bookings WHERE booking_status = "approved"');
  $stats['confirmed_bookings'] = (int) ($row['count'] ?? 0);
} catch (Throwable $e) {
  app_log('admin_stats_error', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
}

// รายได้
try {
  $revenueRow = Database::fetchOne(
    '
        SELECT 
            SUM(deposit_amount) AS total_deposit, 
            SUM(total_amount)   AS total_revenue 
        FROM bookings 
        WHERE payment_status IN ("deposit_success", "full_paid")
        '
  );
  $revenue = [
    'total_deposit' => (float) ($revenueRow['total_deposit'] ?? 0),
    'total_revenue' => (float) ($revenueRow['total_revenue'] ?? 0),
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
    if ($action === 'delete_property') {
      $propertyId = (int) ($_POST['property_id'] ?? 0);

      // ลบไฟล์รูปภาพจริง (ถ้าต้องการ)
      $images = Database::fetchAll(
        'SELECT image_url FROM property_images WHERE property_id = ?',
        [$propertyId]
      );
      foreach ($images as $img) {
        if (!empty($img['image_url'])) {
          $filePath = APP_PATH . $img['image_url'];
          if (is_file($filePath)) {
            @unlink($filePath);
          }
        }
      }

      Database::execute('DELETE FROM property_images WHERE property_id = ?', [$propertyId]);
      Database::execute('DELETE FROM bookings        WHERE property_id = ?', [$propertyId]);
      Database::execute('DELETE FROM properties      WHERE id = ?',          [$propertyId]);

      $message     = 'ลบพื้นที่เรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'update_property_status') {
      $propertyId = (int) ($_POST['property_id'] ?? 0);
      $status     = (string) ($_POST['status'] ?? 'available');

      $allowedStatus = ['available', 'booked', 'sold'];
      if (!in_array($status, $allowedStatus, true)) {
        $status = 'available';
      }

      Database::execute(
        'UPDATE properties SET status = ? WHERE id = ?',
        [$status, $propertyId]
      );

      $message     = 'อัปเดตสถานะพื้นที่เรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'update_booking_status') {
      $bookingId     = (int) ($_POST['booking_id'] ?? 0);
      $bookingStatus = (string) ($_POST['booking_status'] ?? 'pending');
      $paymentStatus = isset($_POST['payment_status']) ? (string) $_POST['payment_status'] : null;

      if ($paymentStatus !== null) {
        Database::execute(
          'UPDATE bookings SET booking_status = ?, payment_status = ? WHERE id = ?',
          [$bookingStatus, $paymentStatus, $bookingId]
        );
      } else {
        Database::execute(
          'UPDATE bookings SET booking_status = ? WHERE id = ?',
          [$bookingStatus, $bookingId]
        );
      }

      $message     = 'อัปเดตสถานะการจองเรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'delete_booking') {
      $bookingId = (int) ($_POST['booking_id'] ?? 0);

      Database::execute('DELETE FROM bookings WHERE id = ?', [$bookingId]);

      $message     = 'ลบการจองเรียบร้อยแล้ว';
      $messageType = 'success';
    } elseif ($action === 'delete_user') {
      $userIdToDelete = (int) ($_POST['user_id'] ?? 0);

      // กันลบตัวเอง
      if ($userIdToDelete !== (int) $user['id']) {
        Database::execute('DELETE FROM bookings WHERE user_id = ?', [$userIdToDelete]);
        Database::execute('DELETE FROM users    WHERE id = ?',      [$userIdToDelete]);

        $message     = 'ลบผู้ใช้เรียบร้อยแล้ว';
        $messageType = 'success';
      } else {
        $message     = 'ไม่สามารถลบผู้ใช้ที่กำลังล็อกอินอยู่ได้';
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
    'SELECT id, owner_id, title, location, province, price, status, created_at FROM properties ORDER BY created_at DESC LIMIT 10'
  );
} catch (Throwable $e) {
  app_log('admin_recent_properties_error', ['error' => $e->getMessage()]);
  $recentProperties = [];
}

try {
  $recentBookings = Database::fetchAll(
    '
        SELECT 
            b.id, b.user_id, b.property_id, b.booking_date, b.payment_status, b.booking_status,
            b.deposit_amount, b.total_amount, b.slip_image, b.created_at,
            p.title  AS property_title, 
            u.firstname, 
            u.lastname, 
            u.email 
        FROM bookings b 
        LEFT JOIN properties p ON b.property_id = p.id 
        LEFT JOIN users     u ON b.user_id     = u.id 
        ORDER BY b.created_at DESC 
        LIMIT 10
        '
  );
} catch (Throwable $e) {
  app_log('admin_recent_bookings_error', ['error' => $e->getMessage()]);
  $recentBookings = [];
}

try {
  $allUsers = Database::fetchAll(
    'SELECT id, username, email, firstname, lastname, phone, role, created_at FROM users ORDER BY created_at DESC'
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
        <div class="stat-value"><?= number_format($stats['total_properties']); ?></div>
      </div>
    </div>
    <div class="stat-card available">
      <div class="stat-icon">✅</div>
      <div class="stat-info">
        <div class="stat-label">พื้นที่ว่าง</div>
        <div class="stat-value"><?= number_format($stats['available_properties']); ?></div>
      </div>
    </div>
    <div class="stat-card booked">
      <div class="stat-icon">📋</div>
      <div class="stat-info">
        <div class="stat-label">ติดจอง</div>
        <div class="stat-value"><?= number_format($stats['booked_properties']); ?></div>
      </div>
    </div>
    <div class="stat-card sold">
      <div class="stat-icon">🔒</div>
      <div class="stat-info">
        <div class="stat-label">ขายแล้ว</div>
        <div class="stat-value"><?= number_format($stats['sold_properties']); ?></div>
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
    <button class="tab-btn active" onclick="switchTab(event, 'properties')">🏡 จัดการพื้นที่</button>
    <button class="tab-btn" onclick="switchTab(event, 'bookings')">📋 จัดการการจอง</button>
    <button class="tab-btn" onclick="switchTab(event, 'users')">👥 จัดการผู้ใช้</button>
  </div>

  <!-- Tab: Properties -->
  <div id="tab-properties" class="tab-content active">
    <div class="section-header">
      <h2>รายการพื้นที่เกษตร</h2>
      <a href="?page=add_property" class="btn btn-primary">+ เพิ่มพื้นที่ใหม่</a>
    </div>
    <div class="table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อพื้นที่</th>
            <th>ทำเล</th>
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
              <td><?= e((string) $prop['id']); ?></td>
              <td><?= e((string) $prop['title']); ?></td>
              <td><?= e((string) $prop['location']); ?></td>
              <td><?= e((string) $prop['province']); ?></td>
              <td>฿<?= number_format((float) $prop['price']); ?></td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="update_property_status">
                  <input type="hidden" name="property_id" value="<?= (int) $prop['id']; ?>">
                  <select name="status" onchange="this.form.submit()" class="status-select">
                    <option value="available" <?= $prop['status'] === 'available' ? 'selected' : ''; ?>>ว่าง</option>
                    <option value="booked" <?= $prop['status'] === 'booked'    ? 'selected' : ''; ?>>ติดจอง</option>
                    <option value="sold" <?= $prop['status'] === 'sold'      ? 'selected' : ''; ?>>ขายแล้ว</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $prop['created_at'])); ?></td>
              <td class="actions">
                <a href="?page=detail&id=<?= (int) $prop['id']; ?>" class="btn-action view" title="ดูรายละเอียด">👁️</a>
                <a href="?page=edit_property&id=<?= (int) $prop['id']; ?>" class="btn-action edit" title="แก้ไข">✏️</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบพื้นที่นี้?');">
                  <input type="hidden" name="action" value="delete_property">
                  <input type="hidden" name="property_id" value="<?= (int) $prop['id']; ?>">
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
            <th>สถานะการจอง</th>
            <th>สถานะชำระเงิน</th>
            <th>วันที่จอง</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentBookings as $booking): ?>
            <tr>
              <td><?= e((string) $booking['id']); ?></td>
              <td>
                <?= e((string) $booking['firstname'] . ' ' . (string) $booking['lastname']); ?>
                <br><small><?= e((string) $booking['email']); ?></small>
              </td>
              <td><?= e((string) $booking['property_title']); ?></td>
              <td><?= date('d/m/Y', strtotime((string) $booking['booking_date'])); ?></td>
              <td>฿<?= number_format((float) $booking['deposit_amount']); ?></td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="update_booking_status">
                  <input type="hidden" name="booking_id" value="<?= (int) $booking['id']; ?>">
                  <select name="booking_status" onchange="this.form.submit()" class="status-select">
                    <option value="pending" <?= $booking['booking_status'] === 'pending'   ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="approved" <?= $booking['booking_status'] === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                    <option value="rejected" <?= $booking['booking_status'] === 'rejected' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                    <option value="cancelled" <?= $booking['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                  </select>
                </form>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="update_booking_status">
                  <input type="hidden" name="booking_id" value="<?= (int) $booking['id']; ?>">
                  <input type="hidden" name="booking_status" value="<?= e((string) $booking['booking_status']); ?>">
                  <select name="payment_status" onchange="this.form.submit()" class="status-select">
                    <option value="waiting" <?= $booking['payment_status'] === 'waiting'         ? 'selected' : ''; ?>>รอชำระ</option>
                    <option value="deposit_success" <?= $booking['payment_status'] === 'deposit_success' ? 'selected' : ''; ?>>ชำระมัดจำแล้ว</option>
                    <option value="full_paid" <?= $booking['payment_status'] === 'full_paid'            ? 'selected' : ''; ?>>ชำระครบแล้ว</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $booking['created_at'])); ?></td>
              <td class="actions">
                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบการจองนี้?');">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= (int) $booking['id']; ?>">
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
            <th>อีเมล</th>
            <th>เบอร์โทร</th>
            <th>สิทธิ์</th>
            <th>วันที่สมัคร</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allUsers as $u): ?>
            <tr>
              <td><?= e((string) $u['id']); ?></td>
              <td><?= e((string) $u['firstname'] . ' ' . (string) $u['lastname']); ?></td>
              <td><?= e((string) $u['email']); ?></td>
              <td><?= e((string) ($u['phone'] ?? '')); ?></td>
              <td>
                <span class="badge badge-<?= $u['role'] === 'admin' ? 'admin' : 'user'; ?>">
                  <?= $u['role'] === 'admin' ? 'ผู้ดูแล' : 'สมาชิก'; ?>
                </span>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string) $u['created_at'])); ?></td>
              <td class="actions">
                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบผู้ใช้นี้?');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id']; ?>">
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
</div>

<style>
  /* ใช้สไตล์เดิม + นิดหน่อย แทบไม่แตะ */
  <?= '' /* keep your CSS as-is, already ok */ ?>
</style>

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
</script>