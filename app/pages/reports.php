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
  app_log('reports_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('reports_helpers_file_missing', ['file' => $helpersFile]);
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
  app_log('reports_session_error', ['error' => $e->getMessage()]);
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
// ดึงรายงานแบบกันพลาด
// ----------------------------
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // เริ่มต้นเดือน
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

try {
  // รายได้รวม
  $revenueStats = Database::fetchOne(
    'SELECT 
            SUM(CASE WHEN payment_type = "deposit" THEN amount ELSE 0 END) AS total_deposits,
            SUM(CASE WHEN payment_type = "full_payment" THEN amount ELSE 0 END) AS total_full_payments,
            SUM(CASE WHEN payment_type = "monthly_rent" THEN amount ELSE 0 END) AS total_monthly_rent,
            SUM(amount) AS total_revenue,
            COUNT(*) AS total_transactions
         FROM payments 
         WHERE payment_status = "verified" 
           AND payment_date BETWEEN ? AND ?',
    [$dateFrom, $dateTo]
  );

  // สถิติการจอง
  $bookingStats = Database::fetchOne(
    'SELECT 
            COUNT(*) AS total_bookings,
            SUM(CASE WHEN booking_status = "approved" THEN 1 ELSE 0 END) AS approved_bookings,
            SUM(CASE WHEN booking_status = "pending" THEN 1 ELSE 0 END) AS pending_bookings,
            SUM(CASE WHEN booking_status = "rejected" THEN 1 ELSE 0 END) AS rejected_bookings
         FROM bookings 
         WHERE DATE(created_at) BETWEEN ? AND ?',
    [$dateFrom, $dateTo]
  );

  // รายได้รายเดือน (6 เดือนล่าสุด)
  $monthlyRevenue = Database::fetchAll(
    'SELECT 
            DATE_FORMAT(payment_date, "%Y-%m") AS month,
            SUM(amount) AS revenue
         FROM payments 
         WHERE payment_status = "verified"
           AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(payment_date, "%Y-%m")
         ORDER BY month ASC'
  );

  // พื้นที่ที่ได้รับความนิยม
  $popularProperties = Database::fetchAll(
    'SELECT 
            p.id, p.title, p.location, p.province,
            COUNT(b.id) AS booking_count,
            SUM(CASE WHEN b.booking_status = "approved" THEN 1 ELSE 0 END) AS approved_count
         FROM properties p
         LEFT JOIN bookings b ON p.id = b.property_id
         WHERE b.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
         GROUP BY p.id
         ORDER BY booking_count DESC
         LIMIT 10'
  );
} catch (Throwable $e) {
  app_log('reports_fetch_error', ['error' => $e->getMessage()]);
  $revenueStats = ['total_deposits' => 0, 'total_full_payments' => 0, 'total_monthly_rent' => 0, 'total_revenue' => 0, 'total_transactions' => 0];
  $bookingStats = ['total_bookings' => 0, 'approved_bookings' => 0, 'pending_bookings' => 0, 'rejected_bookings' => 0];
  $monthlyRevenue = [];
  $popularProperties = [];
}

?>
<div class="reports-container">
  <div class="page-header">
    <h1>รายงานและสถิติ</h1>
    <a href="?page=admin_dashboard" class="back-link">← กลับแดชบอร์ด</a>
  </div>

  <div class="report-filters">
    <form method="GET" action="?page=reports">
      <label>จากวันที่: <input type="date" name="date_from" value="<?= e($dateFrom); ?>"></label>
      <label>ถึงวันที่: <input type="date" name="date_to" value="<?= e($dateTo); ?>"></label>
      <button type="submit">ดูรายงาน</button>
    </form>
  </div>

  <!-- สถิติรายได้ -->
  <div class="report-section">
    <h2>รายได้ (<?= date('d/m/Y', strtotime($dateFrom)); ?> - <?= date('d/m/Y', strtotime($dateTo)); ?>)</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">มัดจำ</div>
        <div class="stat-value">฿<?= number_format((float)($revenueStats['total_deposits'] ?? 0), 2); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ชำระเต็มจำนวน</div>
        <div class="stat-value">฿<?= number_format((float)($revenueStats['total_full_payments'] ?? 0), 2); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ค่าเช่ารายเดือน</div>
        <div class="stat-value">฿<?= number_format((float)($revenueStats['total_monthly_rent'] ?? 0), 2); ?></div>
      </div>
      <div class="stat-card highlight">
        <div class="stat-label">รายได้รวม</div>
        <div class="stat-value">฿<?= number_format((float)($revenueStats['total_revenue'] ?? 0), 2); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">จำนวนธุรกรรม</div>
        <div class="stat-value"><?= number_format((int)($revenueStats['total_transactions'] ?? 0)); ?></div>
      </div>
    </div>
  </div>

  <!-- สถิติการจอง -->
  <div class="report-section">
    <h2>สถิติการจอง</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">การจองทั้งหมด</div>
        <div class="stat-value"><?= number_format((int)($bookingStats['total_bookings'] ?? 0)); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">อนุมัติแล้ว</div>
        <div class="stat-value"><?= number_format((int)($bookingStats['approved_bookings'] ?? 0)); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">รอดำเนินการ</div>
        <div class="stat-value"><?= number_format((int)($bookingStats['pending_bookings'] ?? 0)); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ปฏิเสธ</div>
        <div class="stat-value"><?= number_format((int)($bookingStats['rejected_bookings'] ?? 0)); ?></div>
      </div>
    </div>
  </div>

  <!-- กราฟรายได้รายเดือน -->
  <?php if (!empty($monthlyRevenue)): ?>
    <div class="report-section">
      <h2>รายได้รายเดือน (6 เดือนล่าสุด)</h2>
      <div class="chart-container">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
  <?php endif; ?>

  <!-- พื้นที่ยอดนิยม -->
  <?php if (!empty($popularProperties)): ?>
    <div class="report-section">
      <h2>พื้นที่ยอดนิยม (3 เดือนล่าสุด)</h2>
      <table class="report-table">
        <thead>
          <tr>
            <th>ลำดับ</th>
            <th>ชื่อพื้นที่</th>
            <th>ที่ตั้ง</th>
            <th>จำนวนการจอง</th>
            <th>อนุมัติแล้ว</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($popularProperties as $index => $prop): ?>
            <tr>
              <td><?= $index + 1; ?></td>
              <td><?= e($prop['title']); ?></td>
              <td><?= e($prop['location'] . ', ' . $prop['province']); ?></td>
              <td><?= number_format((int)$prop['booking_count']); ?></td>
              <td><?= number_format((int)$prop['approved_count']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($monthlyRevenue)): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
      const monthlyData = <?= json_encode($monthlyRevenue, JSON_UNESCAPED_UNICODE); ?>;

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: monthlyData.map(d => {
            const [year, month] = d.month.split('-');
            const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            return thaiMonths[parseInt(month) - 1] + ' ' + (parseInt(year) + 543);
          }),
          datasets: [{
            label: 'รายได้ (บาท)',
            data: monthlyData.map(d => parseFloat(d.revenue)),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return '฿' + value.toLocaleString();
                }
              }
            }
          }
        }
      });
    }
  </script>
<?php endif; ?>