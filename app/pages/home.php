<?php

declare(strict_types=1);

// ต้องมี APP_PATH ก่อน
if (!defined('APP_PATH')) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>APP_PATH ไม่ถูกกำหนด</p></div>';
  return;
}

$databaseFile = APP_PATH . '/config/database.php';
if (!is_file($databaseFile)) {
  app_log('home_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่พบไฟล์ Database</p></div>';
  return;
}

require_once $databaseFile;

if (!class_exists('Database')) {
  app_log('home_database_class_missing_after_require', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>โหลด Database แล้วแต่ยังไม่มีคลาส</p></div>';
  return;
}

$user   = current_user();
$userId = isset($user['user_id']) ? (int)$user['user_id'] : (isset($user['id']) ? (int)$user['id'] : null);

// ตั้งค่าจำนวนรายการต่อหน้าแบบยืดหยุ่น
defined('PROPERTIES_PER_PAGE') || define('PROPERTIES_PER_PAGE', 12);

// ฟังก์ชันช่วยโหลดข้อมูลแบบเป็นสัดส่วน
$fetchTotalAreas = static function (): int {
  try {
    $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM rental_area");
    return isset($row['cnt']) ? (int)$row['cnt'] : 0;
  } catch (Throwable $e) {
    app_log('home_count_error', ['message' => $e->getMessage()]);
    return 0;
  }
};

/**
 * @return array<int, array<string, mixed>>
 */
$fetchAreasPage = static function (int $offset, int $limit): array {
  try {
    $pdo = Database::connection();

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
        ra.created_at,
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
      WHERE ra.area_status IN ('available', 'booked')
      ORDER BY ra.area_id DESC
      LIMIT :offset, :limit
    ";

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
      throw new RuntimeException('Failed to prepare home list query');
    }

    // ป้องกันค่าติดลบ/ผิดประเภท
    $stmt->bindValue(':offset', max(0, (int)$offset), PDO::PARAM_INT);
    $stmt->bindValue(':limit',  max(1, (int)$limit),  PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($items) ? $items : [];
  } catch (Throwable $e) {
    app_log('home_page_query_error', ['message' => $e->getMessage()]);
    return [];
  }
};

$fetchLocations = static function (): array {
  $provinces = [];
  $districts = [];
  try {
    $provinces = Database::fetchAll('SELECT province_id, province_name FROM province ORDER BY province_name ASC');
    $districts = Database::fetchAll('SELECT district_id, district_name, province_id FROM district ORDER BY district_name ASC');
  } catch (Throwable $e) {
    app_log('home_province_district_load_error', ['message' => $e->getMessage()]);
  }
  return [$provinces, $districts];
};

// รับ pg ด้วยฟิลเตอร์ และกำหนดค่าเริ่มต้น
$pgParam = (int)(filter_input(INPUT_GET, 'pg', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]) ?? 1);
$currentPage = $pgParam > 0 ? $pgParam : 1;
$offset      = ($currentPage - 1) * PROPERTIES_PER_PAGE;

$totalRow = $fetchTotalAreas();

$totalPages = max(1, (int)ceil($totalRow / PROPERTIES_PER_PAGE));
if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
  $offset = ($currentPage - 1) * PROPERTIES_PER_PAGE;
}

// โหลดรายการสำหรับหน้าปัจจุบัน
$items = $fetchAreasPage(max(0, (int)$offset), (int)PROPERTIES_PER_PAGE);

// รายชื่อจังหวัด/อำเภอจากฐานข้อมูล
[$provinces, $districts] = $fetchLocations();

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

        // เช็คสถานะตาม enum ใน database: 'available', 'booked', 'unavailable'
        $isBooked = ($areaStatus === 'booked');
        $ownerId  = isset($item['user_id']) ? (int)$item['user_id'] : null;
        $isOwner  = ($userId !== null && $ownerId !== null && $ownerId === $userId);

        $cardClass = $isBooked ? 'item-card booked' : 'item-card';

        // ตรวจสอบรูปภาพและใช้ SVG placeholder ถ้าไม่มีรูป
        $mainImage = (string)($item['main_image'] ?? '');
        $svgPlaceholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="24"%3ENo Image%3C/text%3E%3C/svg%3E';

        // ถ้าไม่มีรูปหรือเป็น placeholder.jpg ให้ใช้ SVG
        if ($mainImage === '' || $mainImage === '/images/placeholder.jpg' || stripos((string)$mainImage, 'placeholder') !== false) {
          $mainImage = $svgPlaceholder;
        }

        // แสดงวันที่สร้างจริงจากฐานข้อมูล
        $createdAt = isset($item['created_at']) ? (string)$item['created_at'] : '';
        $dataDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '1970-01-01';
        $displayDate = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '-';

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
              📍<?= e($locationText); ?>
            </p>

            <div class="item-meta">
              <span class="meta-date">🕐 <?= e($displayDate); ?></span>
              <span class="meta-deposit">มัดจำ ~<?= number_format($depositRaw); ?> บาท (<?= number_format($depositPct, 2); ?>%)</span>
              <span class="meta-price"><?= number_format($priceRaw); ?> บาท/ปี</span>
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