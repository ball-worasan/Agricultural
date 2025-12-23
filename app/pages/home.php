<?php

declare(strict_types=1);

// ต้องมี APP_PATH ก่อน
if (!defined('APP_PATH')) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>APP_PATH ไม่ถูกกำหนด</p></div>';
  return;
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('home_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่พบไฟล์ Database.php</p></div>';
  return;
}

require_once $databaseFile;

if (!class_exists('Database')) {
  app_log('home_database_class_missing_after_require', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>โหลด Database.php แล้วแต่ยังไม่มีคลาส</p></div>';
  return;
}

$user   = current_user();
$userId = isset($user['user_id']) ? (int)$user['user_id'] : (isset($user['id']) ? (int)$user['id'] : null);

if (!defined('PROPERTIES_PER_PAGE')) {
  define('PROPERTIES_PER_PAGE', 24);
}

$pgParam     = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$currentPage = $pgParam > 0 ? $pgParam : 1;
$offset      = ($currentPage - 1) * PROPERTIES_PER_PAGE;

$totalRow = 0;
try {
  $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM rental_area");
  $totalRow = isset($row['cnt']) ? (int)$row['cnt'] : 0;
} catch (Throwable $e) {
  app_log('home_count_error', [
    'message' => $e->getMessage(),
  ]);
  $totalRow = 0;
}

$totalPages = max(1, (int)ceil($totalRow / PROPERTIES_PER_PAGE));
if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
  $offset = ($currentPage - 1) * PROPERTIES_PER_PAGE;
}

$items = [];

try {
  $limit  = (int)PROPERTIES_PER_PAGE;
  $offset = max(0, (int)$offset);

  $pdo = Database::connection();

  // ดึงรายการพื้นที่เช่า พร้อมจังหวัด/อำเภอ + รูปแรก
  $sql = "
    SELECT
      ra.area_id,
      ra.user_id,
      ra.area_name,
      ra.price_per_year,
      ra.deposit_percent,
      ra.area_size,
      ra.area_status,
      ra.district_id,
      d.district_name,
      p.province_name,
      COALESCE(ai.image_url, '/images/placeholder.jpg') AS main_image
    FROM rental_area ra
    INNER JOIN district d ON d.district_id = ra.district_id
    INNER JOIN province p ON p.province_id = d.province_id
    LEFT JOIN (
      SELECT area_id, MIN(image_id) AS min_img
      FROM area_image
      GROUP BY area_id
    ) aim ON aim.area_id = ra.area_id
    LEFT JOIN area_image ai ON ai.area_id = ra.area_id AND ai.image_id = aim.min_img
    ORDER BY ra.area_id DESC
    LIMIT :offset, :limit
  ";

  $stmt = $pdo->prepare($sql);
  if ($stmt === false) {
    throw new RuntimeException('Failed to prepare home list query');
  }

  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
  $stmt->execute();

  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  app_log('home_page_query_error', [
    'message' => $e->getMessage(),
  ]);
  $items = [];
}

// รายชื่อจังหวัด/อำเภอจากฐานข้อมูล
$provinces = [];
$districts = [];
try {
  $provinces = Database::fetchAll('SELECT province_id, province_name FROM province ORDER BY province_name ASC');
  $districts = Database::fetchAll('SELECT district_id, district_name, province_id FROM district ORDER BY district_name ASC');
} catch (Throwable $e) {
  app_log('home_province_district_load_error', ['message' => $e->getMessage()]);
}

?>
<div class="home-container" data-page="home">

  <div class="filter-section">
    <div class="filter-left">
      <div class="filter-group">
        <label for="province">จังหวัด</label>
        <select id="province" name="province">
          <option value="">เลือกจังหวัด</option>
          <?php foreach ($provinces as $prov): ?>
            <option value="<?= e($prov['province_id'] ?? ''); ?>" data-name="<?= e($prov['province_name'] ?? ''); ?>"><?= e($prov['province_name'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="district">อำเภอ</label>
        <select id="district" name="district" disabled>
          <option value="">เลือกจังหวัดก่อน</option>
          <?php foreach ($districts as $dist): ?>
            <option value="<?= e($dist['district_id'] ?? ''); ?>" data-province-id="<?= e($dist['province_id'] ?? ''); ?>"><?= e($dist['district_name'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="price">ราคาเช่า (บาท/ปี)</label>
        <select id="price" name="price">
          <option value="">ทั้งหมด</option>
          <option value="0-10000">0 - 10,000</option>
          <option value="10000-20000">10,000 - 20,000</option>
          <option value="20000-30000">20,000 - 30,000</option>
          <option value="30000-50000">30,000 - 50,000</option>
          <option value="50000-100000">50,000 - 100,000</option>
          <option value="100000-200000">100,000 - 200,000</option>
          <option value="200000-500000">200,000 - 500,000</option>
          <option value="500000-1000000">500,000 - 1,000,000</option>
        </select>
      </div>
    </div>

    <div class="filter-right">
      <div class="filter-group">
        <label for="sort">เรียงตาม</label>
        <select id="sort" name="sort">
          <option value="price-low">ราคาต่ำ-สูง</option>
          <option value="price-high">ราคาสูง-ต่ำ</option>
        </select>
      </div>
    </div>
  </div>

  <div class="items-section compact" id="itemsContainer">
    <?php if (empty($items)): ?>
      <div id="homeEmptyState" class="empty-state">
        <div class="empty-state-icon">🔎</div>
        <div class="empty-state-title">ไม่พบรายการที่ตรงกับเงื่อนไข</div>
        <div class="empty-state-desc">ลองเปลี่ยนตัวกรอง หรือพิมพ์คำค้นใหม่อีกครั้ง</div>
      </div>
    <?php else: ?>
      <?php foreach ($items as $item):

        $areaId = isset($item['area_id']) ? (int)$item['area_id'] : 0;
        if ($areaId <= 0) continue;

        $priceRaw   = isset($item['price_per_year']) ? (float)$item['price_per_year'] : 0.0;
        $depositPct = isset($item['deposit_percent']) ? (float)$item['deposit_percent'] : 0.0;
        $depositRaw = (int)round($priceRaw * $depositPct / 100.0);
        $areaStatus = isset($item['area_status']) ? (string)$item['area_status'] : '';

        $isBooked = (mb_strpos($areaStatus, 'ติดจอง') !== false);
        $ownerId  = isset($item['user_id']) ? (int)$item['user_id'] : null;
        $isOwner  = ($userId !== null && $ownerId !== null && $ownerId === $userId);

        $cardClass = $isBooked ? 'item-card booked' : 'item-card';

        $mainImage = (string)($item['main_image'] ?? '/images/placeholder.jpg');
        if ($mainImage === '') $mainImage = '/images/placeholder.jpg';

        // ไม่มี created_at ในสคีมาใหม่ แสดงวันที่เป็น '-' และใช้ไม่สำหรับ sort
        $dataDate    = '1970-01-01';
        $displayDate = '-';

        $province = isset($item['province_name']) ? (string)$item['province_name'] : '';
        $district = isset($item['district_name']) ? (string)$item['district_name'] : '';
        $titleText = isset($item['area_name']) ? (string)$item['area_name'] : '';
        $locationText = $district !== '' || $province !== ''
          ? trim(($district !== '' ? $district : '') . ($province !== '' ? ', ' . $province : ''))
          : '';
      ?>
        <a
          href="?page=detail&id=<?= $areaId; ?>"
          class="<?= e($cardClass); ?>"
          style="text-decoration:none;color:inherit;"
          data-province="<?= e($province); ?>"
          data-district="<?= e($district); ?>"
          data-district-id="<?= isset($item['district_id']) ? (int)$item['district_id'] : 0; ?>"
          data-price="<?= (int)$priceRaw; ?>"
          data-deposit="<?= (int)$depositRaw; ?>"
          data-date="<?= e($dataDate); ?>">

          <div class="card-badges">
            <?php if ($isOwner): ?>
              <span class="badge badge-owner">ของคุณ</span>
            <?php endif; ?>
            <?php if ($isBooked): ?>
              <span class="badge badge-booked">ติดจอง</span>
            <?php else: ?>
              <span class="badge badge-available">พร้อมเช่า</span>
            <?php endif; ?>
          </div>

          <div class="item-image">
            <img
              src="<?= e($mainImage); ?>"
              alt="<?= e($titleText !== '' ? $titleText : 'พื้นที่เกษตรให้เช่า'); ?>"
              loading="lazy"
              decoding="async"
              style="background: var(--skeleton-bg);">
          </div>

          <div class="item-details">
            <h3 class="item-title"><?= e($titleText); ?></h3>
            <p class="item-location">
              <?= e($locationText); ?>
            </p>

            <div class="item-meta">
              <span class="meta-price"><?= number_format($priceRaw); ?> บาท/ปี</span>
              <span class="meta-deposit">มัดจำ ~<?= number_format($depositRaw); ?> บาท (<?= number_format($depositPct, 2); ?>%)</span>
              <span class="meta-date"><?= e($displayDate); ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a class="page-link" href="?page=home&pg=<?= $currentPage - 1; ?>">ก่อนหน้า</a>
      <?php endif; ?>

      <span class="page-info">
        หน้า <?= (int)$currentPage; ?> / <?= (int)$totalPages; ?>
      </span>

      <?php if ($currentPage < $totalPages): ?>
        <a class="page-link" href="?page=home&pg=<?= $currentPage + 1; ?>">ถัดไป</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>