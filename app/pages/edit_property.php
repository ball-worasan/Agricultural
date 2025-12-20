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

require_once $databaseFile;
require_once $helpersFile;

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

$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
  app_log('edit_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Validate property ID
// ----------------------------
$propertyId = (int)($_GET['id'] ?? 0);
if ($propertyId <= 0) {
  flash('error', 'ไม่พบรายการพื้นที่ที่ต้องการแก้ไข');
  redirect('?page=my_properties');
}

// ----------------------------
// Fetch property data with error handling
// ----------------------------
$property = null;
try {
  $property = Database::fetchOne(
    'SELECT id, owner_id, title, location, province, area, area_rai, area_ngan, area_sqwa, category, has_water, has_electric, soil_type, irrigation, price, status, is_active, available_from, available_to, description, main_image, created_at, updated_at FROM properties WHERE id = ? AND owner_id = ? LIMIT 1',
    [$propertyId, $userId]
  );
} catch (Throwable $e) {
  app_log('edit_property_fetch_error', [
    'property_id' => $propertyId,
    'user_id' => $userId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);
}

if (!$property) {
  flash('error', 'ไม่พบรายการพื้นที่ของคุณ');
  redirect('?page=my_properties');
}

// ----------------------------
// Fetch existing images with error handling
// ----------------------------
$existingImages = [];
try {
  $existingImages = Database::fetchAll(
    'SELECT id, property_id, image_url, display_order, created_at FROM property_images WHERE property_id = ? ORDER BY display_order',
    [$propertyId]
  );
} catch (Throwable $e) {
  app_log('edit_property_images_fetch_error', [
    'property_id' => $propertyId,
    'error' => $e->getMessage(),
  ]);
  $existingImages = [];
}

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

$categories = ['ไร่นา', 'สวนผลไม้', 'แปลงผัก', 'เลี้ยงสัตว์', 'สวนผสม', 'อื่นๆ'];

$allowedStatuses = ['available', 'booked', 'sold'];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  csrf_require(); // ✅ CSRF

  $title        = trim((string)($_POST['title'] ?? $property['title'] ?? ''));
  $category     = trim((string)($_POST['category'] ?? $property['category'] ?? ''));
  $location     = trim((string)($_POST['location'] ?? $property['location'] ?? ''));
  $province     = trim((string)($_POST['province'] ?? $property['province'] ?? ''));
  $area         = trim((string)($_POST['area'] ?? $property['area'] ?? ''));
  $area_rai     = max(0, (int)($_POST['area_rai'] ?? $property['area_rai'] ?? 0));
  $area_ngan    = max(0, (int)($_POST['area_ngan'] ?? $property['area_ngan'] ?? 0));
  $area_sqwa    = max(0, (int)($_POST['area_sqwa'] ?? $property['area_sqwa'] ?? 0));
  $priceRaw     = trim((string)($_POST['price'] ?? (string)($property['price'] ?? '')));
  $description  = trim((string)($_POST['description'] ?? $property['description'] ?? ''));
  $soil_type    = trim((string)($_POST['soil_type'] ?? $property['soil_type'] ?? ''));
  $irrigation   = trim((string)($_POST['irrigation'] ?? $property['irrigation'] ?? ''));
  $has_water    = isset($_POST['has_water']) ? 1 : (int)($property['has_water'] ?? 0);
  $has_electric = isset($_POST['has_electric']) ? 1 : (int)($property['has_electric'] ?? 0);
  $status       = trim((string)($_POST['status'] ?? $property['status'] ?? 'available'));

  if (!in_array($status, $allowedStatuses, true)) $status = 'available';

  if ($title === '') $errors[] = 'กรุณากรอกชื่อพื้นที่';
  if ($location === '') $errors[] = 'กรุณากรอกที่ตั้ง';
  if ($province === '') $errors[] = 'กรุณาเลือกจังหวัด';
  if ($description === '') $errors[] = 'กรุณากรอกรายละเอียดเพิ่มเติมของพื้นที่';

  $price = 0.0;
  if ($priceRaw === '' || !is_numeric($priceRaw)) {
    $errors[] = 'กรุณากรอกราคาที่ถูกต้อง';
  } else {
    $price = (float)$priceRaw;
    if ($price < 0) $errors[] = 'ราคาต้องไม่ติดลบ';
  }

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
        : dirname(__DIR__, 3); // /app/pages -> /sirinat

      $uploadDir = $projectRoot . '/storage/uploads/properties';

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

          $newName = uniqid('prop_', true) . '_' . time() . '_' . $i . '.' . $ext;
          $dest = $uploadDir . $newName;

          if (move_uploaded_file($tmp, $dest)) {
            $uploadedImages[] = '/storage/uploads/properties/' . $newName;
          } else {
            $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้";
          }
        }
      }
    }
  }

  if (empty($errors)) {
    try {
      Database::transaction(function ($pdo) use (
        $propertyId,
        $title,
        $category,
        $location,
        $province,
        $area,
        $area_rai,
        $area_ngan,
        $area_sqwa,
        $price,
        $description,
        $soil_type,
        $irrigation,
        $has_water,
        $has_electric,
        $status,
        $uploadedImages,
        $existingImages
      ) {
        $mainImage = null;
        if (!empty($uploadedImages)) $mainImage = $uploadedImages[0];
        elseif (!empty($existingImages)) $mainImage = $existingImages[0]['image_url'] ?? null;

        Database::execute(
          'UPDATE properties
                        SET title=?, category=?, location=?, province=?, area=?, area_rai=?, area_ngan=?, area_sqwa=?,
                            price=?, description=?, soil_type=?, irrigation=?, has_water=?, has_electric=?, main_image=?,
                            status=?, updated_at=NOW()
                     WHERE id=?',
          [
            $title,
            $category,
            $location,
            $province,
            $area,
            $area_rai,
            $area_ngan,
            $area_sqwa,
            $price,
            $description,
            $soil_type,
            $irrigation,
            $has_water,
            $has_electric,
            $mainImage,
            $status,
            $propertyId
          ]
        );

        $nextOrder = count($existingImages) + 1;
        foreach ($uploadedImages as $url) {
          Database::execute(
            'INSERT INTO property_images (property_id,image_url,display_order) VALUES (?,?,?)',
            [$propertyId, $url, $nextOrder++]
          );
        }
      });

      flash('success', 'บันทึกการแก้ไขรายการพื้นที่เรียบร้อยแล้ว');
      redirect('?page=my_properties');
    } catch (Throwable $e) {
      app_log('edit_property_update_error', ['property_id' => $propertyId, 'error' => $e->getMessage()]);
      $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
    }
  }

  // reload images when error
  $existingImages = Database::fetchAll(
    'SELECT id, property_id, image_url, display_order, created_at FROM property_images WHERE property_id = ? ORDER BY display_order',
    [$propertyId]
  );
}
?>

<link rel="stylesheet" href="/css/add-property.css">

<div class="add-property-container">
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
            value="<?= e($_POST['title'] ?? $property['title'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category">ประเภทพื้นที่</label>
          <?php $selCat = $_POST['category'] ?? $property['category'] ?? ''; ?>
          <select id="category" name="category">
            <option value="">-- เลือกประเภท --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $selCat === $cat ? 'selected' : ''; ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="province">จังหวัด <span class="required">*</span></label>
          <?php $selProv = $_POST['province'] ?? $property['province'] ?? ''; ?>
          <select id="province" name="province" required>
            <option value="">-- เลือกจังหวัด --</option>
            <?php foreach ($thaiProvinces as $prov): ?>
              <option value="<?= e($prov) ?>" <?= $selProv === $prov ? 'selected' : ''; ?>><?= e($prov) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="location">ที่ตั้ง/ทำเล <span class="required">*</span></label>
          <input id="location" name="location" type="text" required
            value="<?= e($_POST['location'] ?? $property['location'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ขนาดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="area_rai">ไร่</label>
          <input id="area_rai" name="area_rai" type="number" min="0"
            value="<?= e((string)($_POST['area_rai'] ?? $property['area_rai'] ?? 0)) ?>">
        </div>
        <div class="form-group">
          <label for="area_ngan">งาน</label>
          <input id="area_ngan" name="area_ngan" type="number" min="0" max="3"
            value="<?= e((string)($_POST['area_ngan'] ?? $property['area_ngan'] ?? 0)) ?>">
        </div>
        <div class="form-group">
          <label for="area_sqwa">ตารางวา</label>
          <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99"
            value="<?= e((string)($_POST['area_sqwa'] ?? $property['area_sqwa'] ?? 0)) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="area">หรือระบุเป็น</label>
          <input id="area" name="area" type="text"
            value="<?= e($_POST['area'] ?? $property['area'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">รายละเอียดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="soil_type">ประเภทดิน</label>
          <input id="soil_type" name="soil_type" type="text"
            value="<?= e($_POST['soil_type'] ?? $property['soil_type'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="irrigation">ระบบชลประทาน</label>
          <input id="irrigation" name="irrigation" type="text"
            value="<?= e($_POST['irrigation'] ?? $property['irrigation'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group checkbox-group">
          <label>
            <input type="checkbox" name="has_water" value="1"
              <?= (isset($_POST['has_water']) || !empty($property['has_water'])) ? 'checked' : ''; ?>>
            มีน้ำพร้อมใช้
          </label>
          <label>
            <input type="checkbox" name="has_electric" value="1"
              <?= (isset($_POST['has_electric']) || !empty($property['has_electric'])) ? 'checked' : ''; ?>>
            มีไฟฟ้าพร้อมใช้
          </label>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ราคาและรายละเอียด</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
          <input id="price" name="price" type="number" min="0" step="0.01" required
            value="<?= e($_POST['price'] ?? (string)($property['price'] ?? '')) ?>">
        </div>

        <div class="form-group">
          <label for="status">สถานะ</label>
          <?php $selStatus = $_POST['status'] ?? $property['status'] ?? 'available'; ?>
          <select id="status" name="status">
            <option value="available" <?= $selStatus === 'available' ? 'selected' : ''; ?>>ว่าง</option>
            <option value="booked" <?= $selStatus === 'booked' ? 'selected' : ''; ?>>ติดจอง</option>
            <option value="sold" <?= $selStatus === 'sold' ? 'selected' : ''; ?>>ขายแล้ว</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="description">รายละเอียดเพิ่มเติม <span class="required">*</span></label>
          <textarea id="description" name="description" rows="6" required><?= e($_POST['description'] ?? $property['description'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <?php if (!empty($existingImages)): ?>
      <div class="form-section">
        <h2 class="section-title">รูปภาพที่มีอยู่ (<?= count($existingImages); ?> รูป)</h2>
        <div class="existing-images-grid">
          <?php foreach ($existingImages as $img): ?>
            <div class="existing-image-item" data-image-id="<?= (int)$img['id']; ?>">
              <img src="<?= e((string)$img['image_url']); ?>" alt="Property Image">
              <button type="button" class="remove-existing-image"
                onclick="removeExistingImage(<?= (int)$img['id']; ?>)">
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
        <input id="images" name="images[]" type="file" multiple accept="image/*" onchange="previewImages(event)" style="display:none;">
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

<script>
  (function() {
    'use strict';

    let selectedFiles = [];
    const csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const propertyId = <?= (int)$propertyId; ?>;

    window.previewImages = function(event) {
      const files = Array.prototype.slice.call(event.target.files || []);
      const preview = document.getElementById('imagePreview');
      if (!preview) return;

      const existingCount = document.querySelectorAll('.existing-image-item').length;
      const total = existingCount + selectedFiles.length + files.length;
      if (total > 10) {
        alert('สามารถมีรูปภาพได้สูงสุด 10 รูป (ปัจจุบันมี ' + existingCount + ' รูปเดิม)');
        return;
      }

      files.forEach(function(file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('ไฟล์ ' + file.name + ' มีขนาดใหญ่เกิน 5MB');
          return;
        }
        selectedFiles.push(file);
      });

      rebuildPreview();
      syncInput();
    };

    window.removeImage = function(idx) {
      if (idx < 0 || idx >= selectedFiles.length) return;
      selectedFiles.splice(idx, 1);
      rebuildPreview();
      syncInput();
    };

    function rebuildPreview() {
      const preview = document.getElementById('imagePreview');
      if (!preview) return;
      preview.innerHTML = '';

      selectedFiles.forEach(function(file, idx) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const div = document.createElement('div');
          div.className = 'preview-item';
          div.innerHTML =
            '<img src="' + e.target.result + '" alt="Preview">' +
            '<button type="button" class="remove-image" data-index="' + idx + '">×</button>';
          preview.appendChild(div);

          const btn = div.querySelector('.remove-image');
          if (btn) btn.addEventListener('click', function() {
            window.removeImage(parseInt(btn.getAttribute('data-index') || '0', 10));
          });
        };
        reader.readAsDataURL(file);
      });
    }

    function syncInput() {
      const input = document.getElementById('images');
      if (!input) return;
      const dt = new DataTransfer();
      selectedFiles.forEach(f => dt.items.add(f));
      input.files = dt.files;
    }

    window.removeExistingImage = function(imageId) {
      if (!confirm('คุณต้องการลบรูปภาพนี้ใช่หรือไม่?')) return;

      const formData = new FormData();
      formData.append('action', 'delete_image');
      formData.append('image_id', String(imageId));
      formData.append('property_id', String(propertyId));
      formData.append('csrf', csrfToken); // ✅ สำคัญมาก ไม่งั้น 419

      fetch('?page=delete_property_image', {
          method: 'POST',
          body: formData
        })
        .then(async (res) => {
          const txt = await res.text();
          let data = null;
          try {
            data = txt ? JSON.parse(txt) : null;
          } catch (e) {}
          if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : 'ไม่สามารถลบรูปภาพได้');
          }
          const item = document.querySelector('[data-image-id="' + imageId + '"]');
          if (item && item.parentNode) item.parentNode.removeChild(item);
          alert(data.message || 'ลบรูปภาพสำเร็จ');
        })
        .catch((err) => {
          console.error(err);
          alert('เกิดข้อผิดพลาด: ' + (err.message || 'เชื่อมต่อไม่ได้'));
        });
    };
  })();
</script>