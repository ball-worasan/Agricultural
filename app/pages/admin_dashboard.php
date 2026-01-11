<?php

declare(strict_types=1);

/**
 * app/pages/admin_dashboard.php (REFAC)
 * - แยก concerns: guard, load stats, handle POST actions
 * - ใช้ flash + redirect (ไม่ส่ง msg/type ผ่าน URL)
 * - แก้บั๊ก save_fee: ลบ $effectiveFrom ที่ไม่เคยประกาศ
 * - confirm ใช้ data-confirm (ให้ JS จัดการ)
 * - slip modal ใช้ data-slip-url + JS
 * 
 * NOTE: bootstrap.php โหลด constants, helpers, database แล้ว ไม่ต้อง require ซ้ำ
 */

function guard_admin(): array
{
  $user = current_user();
  if ($user === null || (int)($user['role'] ?? 0) !== ROLE_ADMIN) {
    flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('?page=signin');
  }
  return $user;
}

function safe_delete_uploaded_file(string $imageUrl): void
{
  // ลบเฉพาะไฟล์ที่อยู่ใน path ที่เราคุมได้
  if ($imageUrl === '') return;

  // รองรับทั้ง "/storage/..." และ "storage/..."
  if ($imageUrl[0] !== '/') $imageUrl = '/' . $imageUrl;

  $allowedPrefix = '/storage/uploads/areas/';
  if (strpos($imageUrl, $allowedPrefix) !== 0) return;

  $abs = dirname(APP_PATH) . $imageUrl;
  if (is_file($abs)) {
    @unlink($abs);
  }
}

function load_stats(): array
{
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
    // รวม query สถานะพื้นที่ให้จบในครั้งเดียว
    $row = Database::fetchOne('
      SELECT
        COUNT(*) AS total,
        SUM(area_status = "available") AS available,
        SUM(area_status = "booked") AS booked,
        SUM(area_status = "unavailable") AS unavailable
      FROM rental_area
    ');
    $stats['total_areas']       = (int)($row['total'] ?? 0);
    $stats['available_areas']   = (int)($row['available'] ?? 0);
    $stats['booked_areas']      = (int)($row['booked'] ?? 0);
    $stats['unavailable_areas'] = (int)($row['unavailable'] ?? 0);

    $row = Database::fetchOne('SELECT COUNT(*) AS count FROM users');
    $stats['total_users'] = (int)($row['count'] ?? 0);

    $row = Database::fetchOne('
      SELECT
        COUNT(*) AS total,
        SUM(deposit_status="pending") AS pending,
        SUM(deposit_status="approved") AS approved
      FROM booking_deposit
    ');
    $stats['total_bookings']    = (int)($row['total'] ?? 0);
    $stats['pending_bookings']  = (int)($row['pending'] ?? 0);
    $stats['approved_bookings'] = (int)($row['approved'] ?? 0);
  } catch (Throwable $e) {
    app_log('admin_stats_error', ['error' => $e->getMessage()]);
  }

  return $stats;
}

function load_revenue(): array
{
  $revenue = ['total_deposit' => 0.0, 'total_revenue' => 0.0, 'total_fee_revenue' => 0.0];
  try {
    $depositRow = Database::fetchOne(
      'SELECT SUM(deposit_amount) AS total_deposit FROM booking_deposit WHERE deposit_status = "approved"'
    );
    $paymentRow = Database::fetchOne(
      'SELECT SUM(net_amount) AS total_net FROM payment WHERE status = "confirmed"'
    );
    // คำนวณรายได้จากค่าธรรมเนียม (amount - net_amount)
    $feeRow = Database::fetchOne(
      'SELECT SUM(amount - net_amount) AS total_fee FROM payment WHERE status = "confirmed"'
    );

    $revenue['total_deposit'] = (float)($depositRow['total_deposit'] ?? 0);
    $revenue['total_revenue'] = (float)($paymentRow['total_net'] ?? 0);
    $revenue['total_fee_revenue'] = (float)($feeRow['total_fee'] ?? 0);
  } catch (Throwable $e) {
    app_log('admin_revenue_error', ['error' => $e->getMessage()]);
  }
  return $revenue;
}

function handle_post_actions(array $user): void
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

  // ถ้าคุณมี csrf_verify() ใน helpers แนะนำเปิดใช้:
  // if (function_exists('csrf_verify')) csrf_verify();

  $action = (string)($_POST['action'] ?? '');

  try {
    if ($action === 'delete_area') {
      $areaId = (int)($_POST['area_id'] ?? 0);
      if ($areaId <= 0) throw new RuntimeException('Invalid area id');

      Database::transaction(function () use ($areaId) {
        $images = Database::fetchAll('SELECT image_url FROM area_image WHERE area_id = ?', [$areaId]);
        foreach ($images as $img) {
          $url = (string)($img['image_url'] ?? '');
          safe_delete_uploaded_file($url);
        }

        // FK cascade จะจัดการ area_image / booking_deposit (ถ้าตั้งไว้)
        Database::execute('DELETE FROM rental_area WHERE area_id = ?', [$areaId]);
      });

      flash('success', 'ลบพื้นที่เรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'update_area_status') {
      $areaId = (int)($_POST['area_id'] ?? 0);
      $status = (string)($_POST['status'] ?? 'available');

      $allowed = ['available', 'booked', 'unavailable'];
      if (!in_array($status, $allowed, true)) $status = 'available';
      if ($areaId <= 0) throw new RuntimeException('Invalid area id');

      Database::execute('UPDATE rental_area SET area_status = ? WHERE area_id = ?', [$status, $areaId]);

      flash('success', 'อัปเดตสถานะพื้นที่เรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'update_deposit_status') {
      $bookingId     = (int)($_POST['booking_id'] ?? 0);
      $depositStatus = (string)($_POST['deposit_status'] ?? 'pending');

      $allowed = ['pending', 'approved', 'rejected'];
      if (!in_array($depositStatus, $allowed, true)) $depositStatus = 'pending';
      if ($bookingId <= 0) throw new RuntimeException('Invalid booking id');

      Database::transaction(function () use ($bookingId, $depositStatus) {
        Database::execute(
          'UPDATE booking_deposit SET deposit_status = ? WHERE booking_id = ?',
          [$depositStatus, $bookingId]
        );

        $b = Database::fetchOne('SELECT area_id FROM booking_deposit WHERE booking_id = ?', [$bookingId]);
        $areaId = (int)($b['area_id'] ?? 0);

        if ($areaId > 0) {
          if ($depositStatus === 'approved') {
            Database::execute('UPDATE rental_area SET area_status = "booked" WHERE area_id = ?', [$areaId]);
          } elseif ($depositStatus === 'rejected') {
            Database::execute('UPDATE rental_area SET area_status = "available" WHERE area_id = ?', [$areaId]);
          }
        }
      });

      flash('success', 'อัปเดตสถานะการมัดจำเรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'delete_booking') {
      $bookingId = (int)($_POST['booking_id'] ?? 0);
      if ($bookingId <= 0) throw new RuntimeException('Invalid booking id');

      Database::transaction(function () use ($bookingId) {
        $b = Database::fetchOne('SELECT area_id FROM booking_deposit WHERE booking_id = ?', [$bookingId]);
        $areaId = (int)($b['area_id'] ?? 0);

        Database::execute('DELETE FROM booking_deposit WHERE booking_id = ?', [$bookingId]);

        if ($areaId > 0) {
          Database::execute('UPDATE rental_area SET area_status = "available" WHERE area_id = ?', [$areaId]);
        }
      });

      flash('success', 'ลบการจองเรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'delete_user') {
      $userIdToDelete = (int)($_POST['user_id'] ?? 0);

      if ($userIdToDelete <= 0) throw new RuntimeException('Invalid user id');

      if ($userIdToDelete === (int)($user['user_id'] ?? 0)) {
        flash('error', 'ไม่สามารถลบผู้ใช้ที่กำลังล็อกอินอยู่ได้');
        redirect('?page=admin_dashboard');
      }

      Database::execute('DELETE FROM users WHERE user_id = ?', [$userIdToDelete]);
      flash('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'save_fee') {
      $feeRate       = (float)($_POST['fee_rate'] ?? 0);
      $accountNumber = trim((string)($_POST['account_number'] ?? ''));
      $accountName   = trim((string)($_POST['account_name'] ?? ''));
      $bankName      = trim((string)($_POST['bank_name'] ?? ''));

      if ($feeRate < 0 || $feeRate > 100) throw new RuntimeException('Invalid fee_rate');
      if ($accountNumber === '' || $accountName === '' || $bankName === '') {
        throw new RuntimeException('Missing fee fields');
      }

      $existingFee = Database::fetchOne('SELECT fee_id FROM fee LIMIT 1');

      if ($existingFee) {
        Database::execute(
          'UPDATE fee
           SET fee_rate = ?, account_number = ?, account_name = ?, bank_name = ?, updated_at = CURRENT_TIMESTAMP
           WHERE fee_id = ?',
          [$feeRate, $accountNumber, $accountName, $bankName, (int)$existingFee['fee_id']]
        );
        flash('success', 'อัปเดตค่าธรรมเนียมสำเร็จ');
      } else {
        Database::execute(
          'INSERT INTO fee (fee_rate, account_number, account_name, bank_name)
           VALUES (?, ?, ?, ?)',
          [$feeRate, $accountNumber, $accountName, $bankName]
        );
        flash('success', 'บันทึกค่าธรรมเนียมสำเร็จ');
      }

      redirect('?page=admin_dashboard');
    }

    if ($action === 'update_payment_status') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      $paymentStatus = (string)($_POST['payment_status'] ?? 'pending');

      $allowed = ['pending', 'confirmed', 'failed'];
      if (!in_array($paymentStatus, $allowed, true)) $paymentStatus = 'pending';
      if ($paymentId <= 0) throw new RuntimeException('Invalid payment id');

      Database::transaction(function () use ($paymentId, $paymentStatus) {
        // อัปเดตสถานะการชำระเงิน
        Database::execute(
          'UPDATE payment SET status = ? WHERE payment_id = ?',
          [$paymentStatus, $paymentId]
        );

        // ถ้าอนุมัติการชำระเงินแล้ว ให้อัปเดตสถานะพื้นที่เป็น unavailable
        if ($paymentStatus === 'confirmed') {
          // ดึง area_id จาก payment -> contract -> booking_deposit
          $paymentData = Database::fetchOne(
            'SELECT c.booking_id 
             FROM payment p
             JOIN contract c ON p.contract_id = c.contract_id
             WHERE p.payment_id = ?
             LIMIT 1',
            [$paymentId]
          );

          if ($paymentData) {
            $bookingId = (int)($paymentData['booking_id'] ?? 0);

            if ($bookingId > 0) {
              $bookingData = Database::fetchOne(
                'SELECT area_id FROM booking_deposit WHERE booking_id = ? LIMIT 1',
                [$bookingId]
              );

              if ($bookingData) {
                $areaId = (int)($bookingData['area_id'] ?? 0);

                if ($areaId > 0) {
                  // อัปเดตสถานะพื้นที่เป็น unavailable (ชำระเงินครบแล้ว)
                  Database::execute(
                    'UPDATE rental_area SET area_status = "unavailable" WHERE area_id = ?',
                    [$areaId]
                  );
                }
              }
            }
          }
        }
      });

      flash('success', 'อัปเดตสถานะการชำระเงินเรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    if ($action === 'delete_payment') {
      $paymentId = (int)($_POST['payment_id'] ?? 0);
      if ($paymentId <= 0) throw new RuntimeException('Invalid payment id');

      Database::execute('DELETE FROM payment WHERE payment_id = ?', [$paymentId]);

      flash('success', 'ลบข้อมูลการชำระเงินเรียบร้อยแล้ว');
      redirect('?page=admin_dashboard');
    }

    // action ไม่รู้จัก
    flash('error', 'คำสั่งไม่ถูกต้อง');
    redirect('?page=admin_dashboard');
  } catch (Throwable $e) {
    app_log('admin_action_error', ['action' => $action, 'error' => $e->getMessage()]);
    flash('error', 'เกิดข้อผิดพลาดในการดำเนินการ');
    redirect('?page=admin_dashboard');
  }
}

$user = guard_admin();
handle_post_actions($user);

$stats   = load_stats();
$revenue = load_revenue();

// ---------- ดึงข้อมูลล่าสุด ----------
try {
  $recentPayments = Database::fetchAll('
    SELECT
      p.payment_id,
      p.contract_id,
      p.amount,
      p.payment_date,
      p.payment_time,
      p.net_amount,
      p.slip_image,
      p.status,
      p.created_at,
      c.booking_id,
      bd.user_id,
      u.full_name,
      ra.area_name
    FROM payment p
    JOIN contract c ON p.contract_id = c.contract_id
    JOIN booking_deposit bd ON c.booking_id = bd.booking_id
    LEFT JOIN users u ON bd.user_id = u.user_id
    LEFT JOIN rental_area ra ON bd.area_id = ra.area_id
    ORDER BY p.created_at DESC
    LIMIT 20
  ');
} catch (Throwable $e) {
  app_log('admin_recent_payments_error', ['error' => $e->getMessage()]);
  $recentPayments = [];
}

try {
  $recentProperties = Database::fetchAll('
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
  ');
} catch (Throwable $e) {
  app_log('admin_recent_properties_error', ['error' => $e->getMessage()]);
  $recentProperties = [];
}

try {
  $recentBookings = Database::fetchAll('
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
  ');
} catch (Throwable $e) {
  app_log('admin_recent_bookings_error', ['error' => $e->getMessage()]);
  $recentBookings = [];
}

try {
  $allUsers = Database::fetchAll('
    SELECT user_id, username, full_name, phone, address,
           account_number, bank_name, account_name, role, created_at
    FROM users
    ORDER BY created_at DESC
  ');
} catch (Throwable $e) {
  app_log('admin_all_users_error', ['error' => $e->getMessage()]);
  $allUsers = [];
}

try {
  $currentFee = Database::fetchOne('
    SELECT fee_id, fee_rate, account_number, account_name, bank_name, created_at, updated_at
    FROM fee
    LIMIT 1
  ');
} catch (Throwable $e) {
  app_log('admin_fee_fetch_error', ['error' => $e->getMessage()]);
  $currentFee = null;
}
?>

<div class="admin-dashboard" data-page="admin-dashboard">
  <div class="admin-header">
    <h1>🎛️ แดชบอร์ดผู้ดูแลระบบ</h1>
    <div class="header-actions">
      <a href="?page=home" class="btn-back">← กลับหน้าหลัก</a>
    </div>
  </div>

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
    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
      <div class="stat-icon">📊</div>
      <div class="stat-info">
        <div class="stat-label">รายได้จากค่าธรรมเนียม</div>
        <div class="stat-value">฿<?= number_format($revenue['total_fee_revenue']); ?></div>
      </div>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <div class="admin-tabs">
    <button class="tab-btn active" type="button" data-tab="properties">🏡 จัดการพื้นที่</button>
    <button class="tab-btn" type="button" data-tab="bookings">📋 จัดการการจอง</button>
    <button class="tab-btn" type="button" data-tab="payments">💳 จัดการชำระเงิน</button>
    <button class="tab-btn" type="button" data-tab="users">👥 จัดการผู้ใช้</button>
    <button class="tab-btn" type="button" data-tab="settings">⚙️ ตั้งค่า</button>
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
              <td><?= e((string)$prop['area_id']); ?></td>
              <td><?= e((string)$prop['area_name']); ?></td>
              <td><?= e((string)($prop['district_name'] ?? 'ไม่ระบุ')); ?></td>
              <td><?= e((string)($prop['province_name'] ?? 'ไม่ระบุ')); ?></td>
              <td>฿<?= number_format((float)$prop['price_per_year']); ?></td>
              <td>
                <form method="POST" class="js-auto-submit">
                  <input type="hidden" name="action" value="update_area_status">
                  <input type="hidden" name="area_id" value="<?= (int)$prop['area_id']; ?>">
                  <select name="status" class="status-select js-auto-submit-select">
                    <option value="available" <?= $prop['area_status'] === 'available' ? 'selected' : ''; ?>>ว่าง</option>
                    <option value="booked" <?= $prop['area_status'] === 'booked' ? 'selected' : ''; ?>>ติดจอง</option>
                    <option value="unavailable" <?= $prop['area_status'] === 'unavailable' ? 'selected' : ''; ?>>ปิดให้เช่า</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string)$prop['created_at'])); ?></td>
              <td class="actions">
                <a href="?page=detail&id=<?= (int)$prop['area_id']; ?>" class="btn-action view" title="ดูรายละเอียด">👁️</a>
                <a href="?page=edit_property&id=<?= (int)$prop['area_id']; ?>" class="btn-action edit" title="แก้ไข">✏️</a>
                <form method="POST" class="confirm-form" data-confirm="ยืนยันการลบพื้นที่นี้?">
                  <input type="hidden" name="action" value="delete_area">
                  <input type="hidden" name="area_id" value="<?= (int)$prop['area_id']; ?>">
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
            $hasSlip = ($paymentSlip !== '');
          ?>
            <tr>
              <td><?= e((string)$booking['booking_id']); ?></td>
              <td><?= e((string)$booking['full_name']); ?></td>
              <td><?= e((string)$booking['area_name']); ?></td>
              <td><?= date('d/m/Y', strtotime((string)$booking['booking_date'])); ?></td>
              <td>฿<?= number_format((float)$booking['deposit_amount']); ?></td>
              <td>
                <?php if ($hasSlip): ?>
                  <button
                    type="button"
                    class="btn-view-slip js-view-slip"
                    data-slip-url="<?= e($paymentSlip); ?>"
                    data-booking-id="<?= e((string)$booking['booking_id']); ?>"
                    title="ดูสลิป">📄 ดูสลิป</button>
                <?php else: ?>
                  <span class="status-badge" style="background:#f5f5f5;color:#999;">ไม่มีสลิป</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" class="js-auto-submit">
                  <input type="hidden" name="action" value="update_deposit_status">
                  <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id']; ?>">
                  <select name="deposit_status" class="status-select js-deposit-status">
                    <option value="pending" <?= $booking['deposit_status'] === 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="approved" <?= $booking['deposit_status'] === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                    <option value="rejected" <?= $booking['deposit_status'] === 'rejected' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string)$booking['created_at'])); ?></td>
              <td class="actions">
                <form method="POST" class="confirm-form" data-confirm="ยืนยันการลบการจองนี้?">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id']; ?>">
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

  <!-- Tab: Payments -->
  <div id="tab-payments" class="tab-content">
    <div class="section-header">
      <h2>รายการชำระเงินเต็มจำนวน</h2>
    </div>
    <div class="table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ผู้เช่า</th>
            <th>พื้นที่</th>
            <th>วันที่ชำระ</th>
            <th>จำนวนเงิน</th>
            <th>สลิป</th>
            <th>สถานะ</th>
            <th>วันที่สร้าง</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentPayments as $payment):
            $slipImage = (string)($payment['slip_image'] ?? '');
            $hasSlip = ($slipImage !== '');
          ?>
            <tr>
              <td><?= e((string)$payment['payment_id']); ?></td>
              <td><?= e((string)$payment['full_name']); ?></td>
              <td><?= e((string)$payment['area_name']); ?></td>
              <td><?= date('d/m/Y H:i', strtotime((string)$payment['payment_date'] . ' ' . (string)$payment['payment_time'])); ?></td>
              <td>฿<?= number_format((float)$payment['amount']); ?></td>
              <td>
                <?php if ($hasSlip): ?>
                  <button
                    type="button"
                    class="btn-view-slip js-view-slip"
                    data-slip-url="<?= e($slipImage); ?>"
                    data-payment-id="<?= e((string)$payment['payment_id']); ?>"
                    title="ดูสลิป">📄 ดูสลิป</button>
                <?php else: ?>
                  <span class="status-badge" style="background:#f5f5f5;color:#999;">ไม่มีสลิป</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" class="js-auto-submit">
                  <input type="hidden" name="action" value="update_payment_status">
                  <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id']; ?>">
                  <select name="payment_status" class="status-select js-auto-submit-select">
                    <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                    <option value="confirmed" <?= $payment['status'] === 'confirmed' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                    <option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                  </select>
                </form>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string)$payment['created_at'])); ?></td>
              <td class="actions">
                <form method="POST" class="confirm-form" data-confirm="ยืนยันการลบข้อมูลการชำระเงินนี้?">
                  <input type="hidden" name="action" value="delete_payment">
                  <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id']; ?>">
                  <button type="submit" class="btn-action delete" title="ลบ">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($recentPayments)): ?>
            <tr>
              <td colspan="9" class="text-muted" style="text-align:center;">ยังไม่มีรายการชำระเงิน</td>
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
              <td><?= e((string)$u['user_id']); ?></td>
              <td><?= e((string)$u['full_name']); ?></td>
              <td><?= e((string)($u['username'] ?? '')); ?></td>
              <td><?= e((string)($u['phone'] ?? '')); ?></td>
              <td>
                <span class="badge badge-<?= (int)($u['role'] ?? 0) === ROLE_ADMIN ? 'admin' : 'user'; ?>">
                  <?= (int)($u['role'] ?? 0) === ROLE_ADMIN ? 'ผู้ดูแล' : 'สมาชิก'; ?>
                </span>
              </td>
              <td><?= date('d/m/Y H:i', strtotime((string)$u['created_at'])); ?></td>
              <td class="actions">
                <button type="button" class="btn-action view js-view-user-detail"
                  data-user-id="<?= (int)$u['user_id']; ?>"
                  data-user-name="<?= e((string)$u['full_name']); ?>"
                  data-username="<?= e((string)($u['username'] ?? '')); ?>"
                  data-phone="<?= e((string)($u['phone'] ?? '')); ?>"
                  data-address="<?= e((string)($u['address'] ?? '')); ?>"
                  data-email="<?= e((string)($u['email'] ?? '')); ?>"
                  data-account-number="<?= e((string)($u['account_number'] ?? '')); ?>"
                  data-bank-name="<?= e((string)($u['bank_name'] ?? '')); ?>"
                  data-account-name="<?= e((string)($u['account_name'] ?? '')); ?>"
                  data-role="<?= (int)($u['role'] ?? 0) === ROLE_ADMIN ? 'ผู้ดูแล' : 'สมาชิก'; ?>"
                  data-created-at="<?= date('d/m/Y H:i', strtotime((string)$u['created_at'])); ?>"
                  title="ดูรายละเอียด">👁️</button>
                <?php if ((int)$u['user_id'] !== (int)($user['user_id'] ?? 0)): ?>
                  <form method="POST" class="confirm-form" data-confirm="ยืนยันการลบผู้ใช้นี้?" style="display: inline;">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                    <button type="submit" class="btn-action delete" title="ลบ">🗑️</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted" style="margin-left: 0.5rem;">ตัวเอง</span>
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
      <p style="font-size:0.9rem;color:var(--text-secondary);margin-top:0.5rem;">📌 ระบบรองรับค่าธรรมเนียมเพียง 1 ชุดเท่านั้น</p>
    </div>

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
              <td><?= number_format((float)$currentFee['fee_rate'], 2); ?>%</td>
              <td><?= e((string)$currentFee['account_number']); ?></td>
              <td><?= e((string)$currentFee['account_name']); ?></td>
              <td><?= e((string)$currentFee['bank_name']); ?></td>
              <td><?= date('d/m/Y H:i', strtotime((string)$currentFee['updated_at'])); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info" style="background:rgba(102,126,234,0.1);border:1px solid rgba(102,126,234,0.3);padding:1rem;border-radius:0.5rem;margin-bottom:1rem;">
        <strong>ℹ️ ยังไม่มีข้อมูลค่าธรรมเนียม</strong> กรุณากรอกข้อมูลด้านล่างเพื่อเพิ่มข้อมูล
      </div>
    <?php endif; ?>

    <div class="section-header">
      <h3><?= $currentFee ? '✏️ แก้ไขข้อมูลค่าธรรมเนียม' : '➕ เพิ่มข้อมูลค่าธรรมเนียม'; ?></h3>
    </div>

    <form method="POST" class="settings-form" data-validate="fee">
      <input type="hidden" name="action" value="save_fee">
      <div class="form-row">
        <label>ค่าธรรมเนียม (%) <span style="color:red;">*</span></label>
        <input type="number" step="0.01" min="0" max="100" name="fee_rate" value="<?= $currentFee ? e((string)$currentFee['fee_rate']) : ''; ?>" required>
        <small style="color:var(--text-secondary);">ระบุเป็นเปอร์เซ็นต์ เช่น 5.00 หมายถึง 5%</small>
      </div>
      <div class="form-row">
        <label>เลขบัญชี/พร้อมเพย์ <span style="color:red;">*</span></label>
        <input type="text" name="account_number" value="<?= $currentFee ? e((string)$currentFee['account_number']) : ''; ?>" placeholder="เช่น 0641365430 หรือ 123-4-56789-0" required>
        <small style="color:var(--text-secondary);">สามารถใส่เบอร์พร้อมเพย์หรือเลขบัญชีธนาคารได้</small>
      </div>
      <div class="form-row">
        <label>ชื่อบัญชี <span style="color:red;">*</span></label>
        <input type="text" name="account_name" value="<?= $currentFee ? e((string)$currentFee['account_name']) : ''; ?>" placeholder="เช่น นายสมชาย ใจดี" required>
      </div>
      <div class="form-row">
        <label>ธนาคาร <span style="color:red;">*</span></label>
        <input type="text" name="bank_name" value="<?= $currentFee ? e((string)$currentFee['bank_name']) : ''; ?>" placeholder="เช่น ธนาคารกสิกรไทย" required>
      </div>
      <button type="submit" class="btn btn-primary">💾 บันทึก</button>
    </form>
  </div>
</div>

<!-- Modal สำหรับแสดงสลิป -->
<div id="slipModal" class="modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h2>ตรวจสอบสลิปการโอนเงิน</h2>
      <button class="modal-close js-close-slip" type="button" aria-label="ปิด">&times;</button>
    </div>
    <div class="modal-body">
      <div class="slip-preview">
        <img id="slipImage" src="" alt="สลิปการโอนเงิน" style="max-width:100%;height:auto;border-radius:8px;">
      </div>
      <div class="slip-info" style="margin-top:1rem;">
        <p><strong>รหัสการจอง:</strong> <span id="slipBookingId"></span></p>
        <p id="slipPaymentIdRow" style="display:none;"><strong>รหัสการชำระเงิน:</strong> <span id="slipPaymentId"></span></p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary js-close-slip" type="button">ปิด</button>
    </div>
  </div>
</div>

<!-- Modal สำหรับแสดงรายละเอียดผู้ใช้ -->
<div id="userDetailModal" class="modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" style="max-width: 600px;">
    <div class="modal-header">
      <h2>👤 รายละเอียดผู้ใช้</h2>
      <button class="modal-close js-close-user-detail" type="button" aria-label="ปิด">&times;</button>
    </div>
    <div class="modal-body">
      <div class="user-detail-content">
        <div class="detail-section">
          <h3>ข้อมูลทั่วไป</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <label>รหัสผู้ใช้:</label>
              <span id="userDetailId"></span>
            </div>
            <div class="detail-item">
              <label>ชื่อ-นามสกุล:</label>
              <span id="userDetailName"></span>
            </div>
            <div class="detail-item">
              <label>ชื่อผู้ใช้:</label>
              <span id="userDetailUsername"></span>
            </div>
            <div class="detail-item">
              <label>เบอร์โทร:</label>
              <span id="userDetailPhone"></span>
            </div>
            <div class="detail-item">
              <label>อีเมล:</label>
              <span id="userDetailEmail"></span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
              <label>ที่อยู่:</label>
              <span id="userDetailAddress"></span>
            </div>
            <div class="detail-item">
              <label>สิทธิ์:</label>
              <span id="userDetailRole"></span>
            </div>
            <div class="detail-item">
              <label>วันที่สมัคร:</label>
              <span id="userDetailCreated"></span>
            </div>
          </div>
        </div>

        <div class="detail-section" style="margin-top: 1.5rem;">
          <h3>ข้อมูลบัญชีธนาคาร</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <label>เลขบัญชี/พร้อมเพย์:</label>
              <span id="userDetailAccountNumber"></span>
            </div>
            <div class="detail-item">
              <label>ชื่อธนาคาร:</label>
              <span id="userDetailBankName"></span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
              <label>ชื่อบัญชี:</label>
              <span id="userDetailAccountName"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary js-close-user-detail" type="button">ปิด</button>
    </div>
  </div>
</div>

<style>
  .user-detail-content {
    padding: 0.5rem;
  }

  .detail-section h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--primary-color, #667eea);
    border-bottom: 2px solid var(--primary-color, #667eea);
    padding-bottom: 0.5rem;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .detail-item label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-secondary, #666);
  }

  .detail-item span {
    font-size: 0.95rem;
    color: var(--text-primary, #333);
    word-break: break-word;
  }

  @media (max-width: 640px) {
    .detail-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
</div>