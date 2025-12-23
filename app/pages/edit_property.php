<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
  define('APP_PATH', BASE_PATH . '/app');
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('edit_property_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('edit_property_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$imageServiceFile = APP_PATH . '/includes/ImageService.php';
if (!is_file($imageServiceFile)) {
  app_log('edit_property_image_service_missing', ['file' => $imageServiceFile]);
  // เดินต่อแม้ไม่มี image service
}

require_once $databaseFile;
require_once $helpersFile;
if (is_file($imageServiceFile)) {
  require_once $imageServiceFile;
}

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('edit_property_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนแก้ไขรายการพื้นที่');
  redirect('?page=signin');
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('edit_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Validate area ID
// ----------------------------
$areaId = (int)($_GET['id'] ?? 0);
if ($areaId <= 0) {
  flash('error', 'ไม่พบรายการพื้นที่ที่ต้องการแก้ไข');
  redirect('?page=my_properties');
}

// ----------------------------
// Provinces & Districts
// ----------------------------
$provinces = [];
$districts = [];
try {
  $provinces = Database::fetchAll('SELECT province_id, province_name FROM province ORDER BY province_name ASC');
  $districts = Database::fetchAll('SELECT district_id, district_name, province_id FROM district ORDER BY district_name ASC');
} catch (Throwable $e) {
  app_log('edit_property_load_geo_error', ['error' => $e->getMessage()]);
}

// ----------------------------
// Fetch area data
// ----------------------------
$area = null;
$provinceId = 0;
try {
  $area = Database::fetchOne(
    'SELECT ra.area_id, ra.user_id, ra.area_name, ra.price_per_year, ra.deposit_percent, ra.area_size, ra.district_id, ra.area_status,
              ra.created_at, ra.updated_at, d.province_id
        FROM rental_area ra
        JOIN district d ON ra.district_id = d.district_id
        WHERE ra.area_id = ? AND ra.user_id = ?
        LIMIT 1',
    [$areaId, $userId]
  );
  $provinceId = (int)($area['province_id'] ?? 0);
} catch (Throwable $e) {
  app_log('edit_property_fetch_error', [
    'area_id' => $areaId,
    'user_id' => $userId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
}

if (!$area) {
  flash('error', 'ไม่พบรายการพื้นที่ของคุณ');
  redirect('?page=my_properties');
}

// ----------------------------
// Fetch existing images
// ----------------------------
$existingImages = [];
try {
  $existingImages = Database::fetchAll(
    'SELECT image_id, area_id, image_url, created_at FROM area_image WHERE area_id = ? ORDER BY image_id ASC',
    [$areaId]
  );
} catch (Throwable $e) {
  app_log('edit_property_images_fetch_error', [
    'area_id' => $areaId,
    'error' => $e->getMessage(),
  ]);
  $existingImages = [];
}

// ----------------------------
// Helper: แปลง area_size → ไร่/งาน/ตร.วา
// ----------------------------
$defaultRai = 0;
$defaultNgan = 0;
$defaultSqwa = 0;
if (isset($area['area_size'])) {
  $size = (float)$area['area_size'];
  $defaultRai = (int)floor($size);
  $remain = $size - $defaultRai;
  $defaultNgan = (int)floor($remain * 4); // 1 งาน = 1/4 ไร่
  $defaultSqwa = (int)round(($remain * 4 - $defaultNgan) * 100); // 1 งาน = 100 ตร.วา
}

$allowedStatuses = ['available', 'booked', 'unavailable'];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  csrf_require();

  $title        = trim((string)($_POST['title'] ?? $area['area_name'] ?? ''));
  $provinceId   = (int)($_POST['province'] ?? $provinceId);
  $districtId   = (int)($_POST['district'] ?? $area['district_id'] ?? 0);
  $areaRai      = max(0, (int)($_POST['area_rai'] ?? $defaultRai));
  $areaNgan     = max(0, (int)($_POST['area_ngan'] ?? $defaultNgan));
  $areaSqwa     = max(0, (int)($_POST['area_sqwa'] ?? $defaultSqwa));
  $priceRaw     = trim((string)($_POST['price'] ?? (string)($area['price_per_year'] ?? '')));
  $status       = trim((string)($_POST['status'] ?? $area['area_status'] ?? 'available'));

  if (!in_array($status, $allowedStatuses, true)) {
    $status = 'available';
  }

  $depositPercent = 10.0; // fixed per business rule

  if ($title === '') $errors[] = 'กรุณากรอกชื่อพื้นที่';
  if ($provinceId <= 0) $errors[] = 'กรุณาเลือกจังหวัด';
  if ($districtId <= 0) $errors[] = 'กรุณาเลือกอำเภอ';

  $price = 0.0;
  if ($priceRaw === '' || !is_numeric($priceRaw)) {
    $errors[] = 'กรุณากรอกราคาที่ถูกต้อง';
  } else {
    $price = (float)$priceRaw;
    if ($price < 0) $errors[] = 'ราคาต้องไม่ติดลบ';
  }

  $areaSize = (float)$areaRai + ($areaNgan / 4.0) + ($areaSqwa / 400.0);

  // upload new images
  $uploadedImages = [];
  if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $imageCount = count($_FILES['images']['name']);
    $existingCount = count($existingImages);

    if ($existingCount + $imageCount > 10) {
      $errors[] = 'สามารถมีรูปภาพได้สูงสุด 10 รูป (ปัจจุบันมี ' . $existingCount . ' รูป)';
    } else {
      $projectRoot = defined('BASE_PATH')
        ? rtrim((string) BASE_PATH, '/')
        : dirname(__DIR__, 3);

      $uploadDir = $projectRoot . '/public/storage/uploads/areas';

      if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $errors[] = 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดรูปภาพได้: mkdir() failed: ' . $uploadDir;
      }

      if (!is_writable($uploadDir)) {
        $errors[] = 'โฟลเดอร์อัปโหลดเขียนไม่ได้: ' . $uploadDir;
      }

      if (empty($errors)) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heif', 'heic'];

        for ($i = 0; $i < $imageCount; $i++) {
          $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
          if ($err === UPLOAD_ERR_NO_FILE) continue;
          if ($err !== UPLOAD_ERR_OK) {
            $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพบางไฟล์';
            continue;
          }

          $tmp  = (string)($_FILES['images']['tmp_name'][$i] ?? '');
          $name = (string)($_FILES['images']['name'][$i] ?? '');
          $size = (int)($_FILES['images']['size'][$i] ?? 0);

          if ($size > 5 * 1024 * 1024) {
            $errors[] = "รูปภาพ {$name} มีขนาดใหญ่เกิน 5MB";
            continue;
          }

          $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
          if (!in_array($ext, $allowedExt, true)) {
            $errors[] = "รูปภาพ {$name} ไม่ใช่ไฟล์รูปภาพที่รองรับ";
            continue;
          }

          if (!is_uploaded_file($tmp)) {
            $errors[] = "ไฟล์ {$name} ไม่ได้ถูกอัปโหลดผ่านฟอร์มอย่างถูกต้อง";
            continue;
          }

          $random = bin2hex(random_bytes(8));
          $newName = 'area_' . $random . '_' . time() . '_' . $i . '.' . $ext;
          $dest = $uploadDir . '/' . $newName;

          $uploadedOk = false;
          if (class_exists('ImageService') && method_exists('ImageService', 'uploadAndProcess')) {
            $uploadedOk = ImageService::uploadAndProcess(
              [
                'tmp_name' => $tmp,
                'name' => $name,
                'size' => $size,
              ],
              $uploadDir,
              $newName
            );
          }

          if (!$uploadedOk) {
            $uploadedOk = move_uploaded_file($tmp, $dest);
          }

          if ($uploadedOk) {
            $uploadedImages[] = '/storage/uploads/areas/' . $newName;
          } else {
            $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้";
          }
        }
      }
    }
  }

  if (empty($errors)) {
    try {
      Database::transaction(function () use (
        $areaId,
        $userId,
        $title,
        $price,
        $depositPercent,
        $areaSize,
        $districtId,
        $status,
        $uploadedImages
      ) {
        Database::execute(
          'UPDATE rental_area
              SET area_name = ?, price_per_year = ?, deposit_percent = ?, area_size = ?, district_id = ?, area_status = ?, updated_at = CURRENT_TIMESTAMP
              WHERE area_id = ? AND user_id = ?',
          [
            $title,
            $price,
            $depositPercent,
            $areaSize,
            $districtId,
            $status,
            $areaId,
            $userId,
          ]
        );

        foreach ($uploadedImages as $url) {
          Database::execute(
            'INSERT INTO area_image (image_url, area_id) VALUES (?, ?)',
            [$url, $areaId]
          );
        }
      });

      flash('success', 'บันทึกการแก้ไขรายการพื้นที่เรียบร้อยแล้ว');
      redirect('?page=my_properties');
    } catch (Throwable $e) {
      app_log('edit_property_update_error', ['area_id' => $areaId, 'error' => $e->getMessage()]);
      $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
    }
  }

  // reload images when error
  $existingImages = Database::fetchAll(
    'SELECT image_id, area_id, image_url, created_at FROM area_image WHERE area_id = ? ORDER BY image_id ASC',
    [$areaId]
  );
}
?>



<div
  class="add-property-container"
  data-csrf="<?= e(csrf_token()); ?>"
  data-area-id="<?= (int)$areaId; ?>">
  <div class="form-header">
    <a href="?page=my_properties" class="btn-back">← กลับรายการของฉัน</a>
    <h1>แก้ไขรายการปล่อยเช่า</h1>
    <p class="form-subtitle">แก้ไขข้อมูลพื้นที่ของคุณ</p>
  </div>

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

  <form method="POST" enctype="multipart/form-data" class="property-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

    <div class="form-section">
      <h2 class="section-title">ข้อมูลพื้นฐาน</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="title">ชื่อพื้นที่ <span class="required">*</span></label>
          <input id="title" name="title" type="text" required
            value="<?= e($_POST['title'] ?? $area['area_name'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="province">จังหวัด <span class="required">*</span></label>
          <?php $selProv = (int)($_POST['province'] ?? $provinceId); ?>
          <select id="province" name="province" required>
            <option value="">-- เลือกจังหวัด --</option>
            <?php foreach ($provinces as $prov): ?>
              <option value="<?= e((string)$prov['province_id']); ?>" data-name="<?= e($prov['province_name']); ?>" <?= $selProv === (int)$prov['province_id'] ? 'selected' : ''; ?>>
                <?= e($prov['province_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="district">อำเภอ <span class="required">*</span></label>
          <?php $selDist = (int)($_POST['district'] ?? $area['district_id'] ?? 0); ?>
          <select id="district" name="district" required <?= $selProv > 0 ? '' : 'disabled'; ?>>
            <option value="">เลือกจังหวัดก่อน</option>
            <?php foreach ($districts as $dist): ?>
              <option value="<?= e((string)$dist['district_id']); ?>" data-province-id="<?= e((string)$dist['province_id']); ?>" <?= $selDist === (int)$dist['district_id'] ? 'selected' : ''; ?>>
                <?= e($dist['district_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="deposit_percent">เปอร์เซ็นต์มัดจำ (%) <span class="required">*</span></label>
          <input id="deposit_percent" type="number" value="10" readonly disabled>
          <small class="text-note">กำหนดคงที่ 10% ไม่สามารถแก้ไขได้</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ขนาดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="area_rai">ไร่</label>
          <input id="area_rai" name="area_rai" type="number" min="0"
            value="<?= e((string)($_POST['area_rai'] ?? $defaultRai)); ?>">
        </div>
        <div class="form-group">
          <label for="area_ngan">งาน</label>
          <input id="area_ngan" name="area_ngan" type="number" min="0" max="3"
            value="<?= e((string)($_POST['area_ngan'] ?? $defaultNgan)); ?>">
        </div>
        <div class="form-group">
          <label for="area_sqwa">ตารางวา</label>
          <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99"
            value="<?= e((string)($_POST['area_sqwa'] ?? $defaultSqwa)); ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ราคาและรายละเอียด</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
          <input id="price" name="price" type="number" min="0" step="0.01" required
            value="<?= e($_POST['price'] ?? (string)($area['price_per_year'] ?? '')) ?>">
        </div>

        <div class="form-group">
          <label for="status">สถานะ</label>
          <?php $selStatus = $_POST['status'] ?? $area['area_status'] ?? 'available'; ?>
          <select id="status" name="status">
            <option value="available" <?= $selStatus === 'available' ? 'selected' : ''; ?>>พร้อมให้เช่า</option>
            <option value="booked" <?= $selStatus === 'booked' ? 'selected' : ''; ?>>ติดจอง</option>
            <option value="unavailable" <?= $selStatus === 'unavailable' ? 'selected' : ''; ?>>ปิดให้เช่า</option>
          </select>
        </div>
      </div>
    </div>

    <?php if (!empty($existingImages)): ?>
      <div class="form-section">
        <h2 class="section-title">รูปภาพเดิม</h2>
        <div class="existing-images-grid" id="existingImages">
          <?php foreach ($existingImages as $img): ?>
            <div class="existing-image-item" data-image-id="<?= (int)$img['image_id']; ?>">
              <img src="<?= e($img['image_url']); ?>" alt="รูปภาพเดิมของพื้นที่">
              <button type="button" class="remove-existing-image js-remove-existing" data-image-id="<?= (int)$img['image_id']; ?>">
                ×
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-section">
      <h2 class="section-title">เพิ่มรูปภาพ (สูงสุด 10 รูปรวมกับรูปเดิม)</h2>

      <div class="upload-area">
        <input id="images" name="images[]" type="file" multiple accept="image/*" style="display:none;">
        <label for="images" class="upload-label">
          <div class="upload-text">คลิกเพื่อเลือกรูปภาพ</div>
          <div class="upload-hint">ไฟล์ละไม่เกิน 5MB</div>
        </label>
      </div>

      <div id="imagePreview" class="image-preview-grid"></div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
      <a href="?page=my_properties" class="btn-cancel">ยกเลิก</a>
    </div>
  </form>
</div>