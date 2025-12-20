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
  app_log('advanced_search_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('advanced_search_helpers_file_missing', ['file' => $helpersFile]);
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
  app_log('advanced_search_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// รับค่าจาก query string
$province = trim((string) ($_GET['province'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$minPrice = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
$hasWater = isset($_GET['has_water']) ? (int) $_GET['has_water'] : null;
$hasElectric = isset($_GET['has_electric']) ? (int) $_GET['has_electric'] : null;
$minArea = isset($_GET['min_area']) ? (int) $_GET['min_area'] : null;
$maxArea = isset($_GET['max_area']) ? (int) $_GET['max_area'] : null;
$searchText = trim((string) ($_GET['q'] ?? ''));

// สร้าง WHERE clause
$whereConditions = ['(is_active = 1 OR is_active IS NULL)', 'status IN ("available", "booked")'];
$params = [];

if ($province !== '') {
  $whereConditions[] = 'province = ?';
  $params[] = $province;
}

if ($category !== '') {
  $whereConditions[] = 'category = ?';
  $params[] = $category;
}

if ($minPrice !== null && $minPrice > 0) {
  $whereConditions[] = 'price >= ?';
  $params[] = $minPrice;
}

if ($maxPrice !== null && $maxPrice > 0) {
  $whereConditions[] = 'price <= ?';
  $params[] = $maxPrice;
}

if ($hasWater !== null) {
  $whereConditions[] = 'has_water = ?';
  $params[] = $hasWater;
}

if ($hasElectric !== null) {
  $whereConditions[] = 'has_electric = ?';
  $params[] = $hasElectric;
}

if ($minArea !== null && $minArea > 0) {
  $whereConditions[] = 'area_total_sqwa >= ?';
  $params[] = $minArea;
}

if ($maxArea !== null && $maxArea > 0) {
  $whereConditions[] = 'area_total_sqwa <= ?';
  $params[] = $maxArea;
}

if ($searchText !== '') {
  $whereConditions[] = '(title LIKE ? OR location LIKE ? OR description LIKE ?)';
  $searchPattern = '%' . $searchText . '%';
  $params[] = $searchPattern;
  $params[] = $searchPattern;
  $params[] = $searchPattern;
}

$whereClause = implode(' AND ', $whereConditions);

// นับจำนวน
$totalRow = 0;
try {
  $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM properties WHERE {$whereClause}", $params);
  $totalRow = (int) ($row['cnt'] ?? 0);
} catch (Throwable $e) {
  app_log('advanced_search_count_error', ['error' => $e->getMessage()]);
  $totalRow = 0;
}

// Pagination
$currentPage = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
$perPage = 24;
$offset = ($currentPage - 1) * $perPage;
$totalPages = max(1, (int) ceil($totalRow / $perPage));

// ดึงข้อมูล
$items = [];
$imagesByProperty = [];

try {
  $items = Database::fetchAll(
    "SELECT id, owner_id, title, location, province, category, has_water, has_electric, price, status, main_image, description, created_at 
         FROM properties 
         WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
  );

  // ดึงรูปภาพ
  if (!empty($items)) {
    $ids = array_map('intval', array_column($items, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $allImages = Database::fetchAll(
      "SELECT property_id, image_url FROM property_images WHERE property_id IN ({$placeholders}) ORDER BY property_id, display_order",
      $ids
    );

    foreach ($allImages as $img) {
      $pid = (int) $img['property_id'];
      $imagesByProperty[$pid][] = (string) $img['image_url'];
    }
  }
} catch (Throwable $e) {
  app_log('advanced_search_query_error', ['error' => $e->getMessage()]);
  $items = [];
}

// รายชื่อจังหวัดและหมวดหมู่
$thaiProvinces = [
  'กระบี่',
  'กรุงเทพมหานคร',
  'กาญจนบุรี',
  'กาฬสินธุ์',
  'กำแพงเพชร',
  'ขอนแก่น',
  'จันทบุรี',
  'ฉะเชิงเทรา',
  'ชลบุรี',
  'ชัยนาท',
  'ชัยภูมิ',
  'ชุมพร',
  'เชียงราย',
  'เชียงใหม่',
  'ตรัง',
  'ตราด',
  'ตาก',
  'นครนายก',
  'นครปฐม',
  'นครพนม',
  'นครราชสีมา',
  'นครศรีธรรมราช',
  'นครสวรรค์',
  'นนทบุรี',
  'นราธิวาส',
  'น่าน',
  'บึงกาฬ',
  'บุรีรัมย์',
  'ปทุมธานี',
  'ประจวบคีรีขันธ์',
  'ปราจีนบุรี',
  'ปัตตานี',
  'พระนครศรีอยุธยา',
  'พังงา',
  'พัทลุง',
  'พิจิตร',
  'พิษณุโลก',
  'เพชรบุรี',
  'เพชรบูรณ์',
  'แพร่',
  'พะเยา',
  'ภูเก็ต',
  'มหาสารคาม',
  'มุกดาหาร',
  'แม่ฮ่องสอน',
  'ยโสธร',
  'ยะลา',
  'ร้อยเอ็ด',
  'ระนอง',
  'ระยอง',
  'ราชบุรี',
  'ลพบุรี',
  'ลำปาง',
  'ลำพูน',
  'เลย',
  'ศรีสะเกษ',
  'สกลนคร',
  'สงขลา',
  'สตูล',
  'สมุทรปราการ',
  'สมุทรสงคราม',
  'สมุทรสาคร',
  'สระแก้ว',
  'สระบุรี',
  'สิงห์บุรี',
  'สุโขทัย',
  'สุพรรณบุรี',
  'สุราษฎร์ธานี',
  'สุรินทร์',
  'หนองคาย',
  'หนองบัวลำพู',
  'อ่างทอง',
  'อุดรธานี',
  'อุทัยธานี',
  'อุตรดิตถ์',
  'อุบลราชธานี',
  'อำนาจเจริญ',
];

$categories = ['ไร่นา', 'สวนผลไม้', 'แปลงผัก', 'เลี้ยงสัตว์'];

?>
<div class="advanced-search-container">
  <div class="search-header">
    <h1>ค้นหาขั้นสูง</h1>
    <a href="?page=home" class="back-link">← กลับหน้าหลัก</a>
  </div>

  <div class="search-form-section">
    <form method="GET" action="?page=advanced_search" class="advanced-search-form">
      <input type="hidden" name="page" value="advanced_search">

      <div class="form-row">
        <div class="form-group">
          <label for="q">ค้นหา</label>
          <input type="text" id="q" name="q" value="<?= e($searchText); ?>" placeholder="ชื่อพื้นที่, ที่ตั้ง, รายละเอียด">
        </div>

        <div class="form-group">
          <label for="province">จังหวัด</label>
          <select id="province" name="province">
            <option value="">ทั้งหมด</option>
            <?php foreach ($thaiProvinces as $prov): ?>
              <option value="<?= e($prov); ?>" <?= $province === $prov ? 'selected' : ''; ?>><?= e($prov); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="category">หมวดหมู่</label>
          <select id="category" name="category">
            <option value="">ทั้งหมด</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat); ?>" <?= $category === $cat ? 'selected' : ''; ?>><?= e($cat); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="min_price">ราคาต่ำสุด (บาท/ปี)</label>
          <input type="number" id="min_price" name="min_price" value="<?= $minPrice !== null ? (int)$minPrice : ''; ?>" min="0" step="1000">
        </div>

        <div class="form-group">
          <label for="max_price">ราคาสูงสุด (บาท/ปี)</label>
          <input type="number" id="max_price" name="max_price" value="<?= $maxPrice !== null ? (int)$maxPrice : ''; ?>" min="0" step="1000">
        </div>

        <div class="form-group">
          <label for="min_area">ขนาดพื้นที่ต่ำสุด (ตร.วา)</label>
          <input type="number" id="min_area" name="min_area" value="<?= $minArea !== null ? (int)$minArea : ''; ?>" min="0">
        </div>

        <div class="form-group">
          <label for="max_area">ขนาดพื้นที่สูงสุด (ตร.วา)</label>
          <input type="number" id="max_area" name="max_area" value="<?= $maxArea !== null ? (int)$maxArea : ''; ?>" min="0">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group checkbox-group">
          <label>
            <input type="checkbox" name="has_water" value="1" <?= $hasWater === 1 ? 'checked' : ''; ?>>
            มีน้ำพร้อมใช้
          </label>
        </div>

        <div class="form-group checkbox-group">
          <label>
            <input type="checkbox" name="has_electric" value="1" <?= $hasElectric === 1 ? 'checked' : ''; ?>>
            มีไฟฟ้าพร้อมใช้
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-search">ค้นหา</button>
        <a href="?page=advanced_search" class="btn-reset">รีเซ็ต</a>
      </div>
    </form>
  </div>

  <div class="search-results">
    <div class="results-header">
      <h2>ผลการค้นหา (<?= number_format($totalRow); ?> รายการ)</h2>
    </div>

    <?php if (empty($items)): ?>
      <div class="empty-state">
        <p>ไม่พบผลการค้นหา</p>
      </div>
    <?php else: ?>
      <div class="items-section compact">
        <?php foreach ($items as $item):
          $propertyId = (int) $item['id'];
          $images = $imagesByProperty[$propertyId] ?? [];
          $mainImage = !empty($item['main_image']) ? $item['main_image'] : (!empty($images) ? $images[0] : 'https://via.placeholder.com/400x300?text=No+Image');
        ?>
          <a href="?page=detail&id=<?= $propertyId; ?>" class="item-card">
            <div class="item-image">
              <img data-src="<?= e($mainImage); ?>" alt="<?= e($item['title']); ?>" loading="lazy">
            </div>
            <div class="item-details">
              <h3><?= e($item['title']); ?></h3>
              <p class="item-location"><?= e($item['location'] . ', ' . $item['province']); ?></p>
              <div class="item-meta">
                <span class="meta-price">฿<?= number_format((int)$item['price']); ?>/ปี</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($currentPage > 1): ?>
            <a href="?page=advanced_search&<?= http_build_query(array_merge($_GET, ['pg' => $currentPage - 1])); ?>" class="page-link">ก่อนหน้า</a>
          <?php endif; ?>
          <span class="page-info">หน้า <?= $currentPage; ?> / <?= $totalPages; ?></span>
          <?php if ($currentPage < $totalPages): ?>
            <a href="?page=advanced_search&<?= http_build_query(array_merge($_GET, ['pg' => $currentPage + 1])); ?>" class="page-link">ถัดไป</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>