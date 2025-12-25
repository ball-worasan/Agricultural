<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2)); // /sirinat
}
if (!defined('APP_PATH')) {
  define('APP_PATH', BASE_PATH . '/app');
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('add_property_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('add_property_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$imageServiceFile = APP_PATH . '/includes/ImageService.php';
if (!is_file($imageServiceFile)) {
  app_log('add_property_image_service_missing', ['file' => $imageServiceFile]);
  // Continue without image service
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
  app_log('add_property_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนเพิ่มรายการพื้นที่');
  redirect('?page=signin');
}

// แอดมินไม่สามารถเพิ่มพื้นที่ได้
if ((int) ($user['role'] ?? 0) === ROLE_ADMIN) {
  flash('error', 'ผู้ดูแลระบบไม่สามารถเพิ่มพื้นที่ได้');
  redirect('?page=admin_dashboard');
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('add_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ตรวจสอบว่า user_id นี้มีอยู่จริงในฐานข้อมูลหรือไม่
try {
  $userCheck = Database::fetchOne('SELECT user_id FROM users WHERE user_id = ? LIMIT 1', [$userId]);
  if (!$userCheck) {
    app_log('add_property_user_not_found', ['user_id' => $userId, 'session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่พบในระบบ กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
  }
} catch (Throwable $e) {
  app_log('add_property_user_check_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
  flash('error', 'ไม่สามารถตรวจสอบข้อมูลผู้ใช้ได้');
  redirect('?page=signin');
}

// ----------------------------
// Constants & Helpers
// ----------------------------
define('MAX_PRICE_PER_YEAR', 999999.99);
define('MAX_IMAGES', 10);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

$validateAreaSize = static function (int $rai, int $ngan, int $sqwa): array {
  if ($rai < 0 || $ngan < 0 || $sqwa < 0) {
    return ['valid' => false, 'message' => 'ขนาดพื้นที่ต้องไม่ติดลบ'];
  }
  if ($ngan > 99 || $sqwa > 99) {
    return ['valid' => false, 'message' => 'งานและตารางวาต้องไม่เกิน 99'];
  }
  $size = (float)$rai + ($ngan / 4.0) + ($sqwa / 400.0);
  return ['valid' => true, 'size' => $size];
};

$validatePrice = static function (string $priceStr): array {
  if ($priceStr === '' || !is_numeric($priceStr)) {
    return ['valid' => false, 'message' => 'กรุณากรอกราคาที่ถูกต้อง'];
  }
  $price = (float)$priceStr;
  if ($price < 0) {
    return ['valid' => false, 'message' => 'ราคาต้องไม่ติดลบ'];
  }
  if ($price > MAX_PRICE_PER_YEAR) {
    return ['valid' => false, 'message' => 'ราคาต้องไม่เกิน ' . number_format(MAX_PRICE_PER_YEAR, 2) . ' บาท'];
  }
  return ['valid' => true, 'price' => $price];
};

// ----------------------------
// Initialize variables
// ----------------------------
$errors = [];

// ----------------------------
// Provinces & Districts from DB
// ----------------------------
$provinces = [];
$districts = [];
try {
  $provinces = Database::fetchAll('SELECT province_id, province_name FROM province ORDER BY province_name ASC');
  $districts = Database::fetchAll('SELECT district_id, district_name, province_id FROM district ORDER BY district_name ASC');
} catch (Throwable $e) {
  app_log('add_property_load_province_district_error', ['error' => $e->getMessage()]);
  $errors[] = 'ไม่สามารถโหลดข้อมูลจังหวัด/อำเภอได้: ' . $e->getMessage();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $title        = trim((string)($_POST['title'] ?? ''));
  $provinceId   = filter_input(INPUT_POST, 'province', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0;
  $districtId   = filter_input(INPUT_POST, 'district', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0;
  $area_rai     = filter_input(INPUT_POST, 'area_rai', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?? 0;
  $area_ngan    = filter_input(INPUT_POST, 'area_ngan', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? 0;
  $area_sqwa    = filter_input(INPUT_POST, 'area_sqwa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? 0;
  $priceRaw     = trim((string)($_POST['price'] ?? ''));
  $depositPercent = 10.0;

  // Validate
  if ($title === '' || mb_strlen($title) > 255) $errors[] = 'กรุณากรอกชื่อพื้นที่ (ไม่เกิน 255 ตัวอักษร)';
  if ($provinceId <= 0) $errors[] = 'กรุณาเลือกจังหวัด';
  if ($districtId <= 0) $errors[] = 'กรุณาเลือกอำเภอ';

  $priceValidation = $validatePrice($priceRaw);
  if (!$priceValidation['valid']) {
    $errors[] = $priceValidation['message'];
    $price = 0.0;
  } else {
    $price = (float)$priceValidation['price'];
  }

  $areaSizeValidation = $validateAreaSize($area_rai, $area_ngan, $area_sqwa);
  if (!$areaSizeValidation['valid']) {
    $errors[] = $areaSizeValidation['message'];
    $area_size = 0.0;
  } else {
    $area_size = (float)$areaSizeValidation['size'];
  }

  // upload
  $uploadedImages = [];
  if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $imageCount = count($_FILES['images']['name']);
    if ($imageCount > MAX_IMAGES) {
      $errors[] = 'สามารถอัปโหลดรูปภาพได้สูงสุด ' . MAX_IMAGES . ' รูป';
    } else {
      $projectRoot = defined('BASE_PATH')
        ? rtrim((string) BASE_PATH, '/')
        : dirname(__DIR__, 3); // /app/pages -> /sirinat

      $uploadDir = $projectRoot . '/public/storage/uploads/areas';

      if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        app_log('upload_mkdir_failed', [
          'upload_dir' => $uploadDir,
          'project_root' => $projectRoot,
          'error' => error_get_last(),
          'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
        ]);
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

          if ($size > MAX_IMAGE_SIZE) {
            $errors[] = sprintf('รูปภาพ %s มีขนาดใหญ่เกิน %dMB', e($name), MAX_IMAGE_SIZE / 1024 / 1024);
            continue;
          }

          $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
          if (!in_array($ext, $allowedExt, true)) {
            $errors[] = sprintf('รูปภาพ %s ไม่ใช่ไฟล์รูปภาพที่รองรับ', e($name));
            continue;
          }

          if (!is_uploaded_file($tmp)) {
            $errors[] = sprintf('ไฟล์ %s ไม่ได้ถูกอัปโหลดผ่านฟอร์มอย่างถูกต้อง', e($name));
            continue;
          }

          // Verify MIME type
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime = finfo_file($finfo, $tmp);
          $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
          if (!in_array($mime, $allowedMimes, true)) {
            $errors[] = sprintf('ไฟล์ %s ไม่ใช่รูปภาพที่รองรับ', e($name));
            continue;
          }

          $random = bin2hex(random_bytes(8));
          $newName = 'area_' . $random . '_' . time() . '_' . $i . '.' . $ext;
          $dest = $uploadDir . '/' . $newName;

          // ใช้ ImageService เพื่อ resize และ optimize
          try {
            $processed = ImageService::uploadAndProcess(
              [
                'tmp_name' => $tmp,
                'name' => $name,
                'size' => $size,
              ],
              $uploadDir,
              $newName
            );

            if ($processed && file_exists($processed)) {
              // ImageService ส่งกลับ absolute path
              $uploadedImages[] = '/storage/uploads/areas/' . $newName;
            } else {
              // Fallback: อัปโหลดแบบเดิมถ้า ImageService ล้มเหลว
              if (move_uploaded_file($tmp, $dest)) {
                $uploadedImages[] = '/storage/uploads/areas/' . $newName;
              } else {
                $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้";
              }
            }
          } catch (Throwable $e) {
            app_log('image_service_error', [
              'name' => $name,
              'error' => $e->getMessage()
            ]);
            // Fallback: อัปโหลดแบบเดิม
            if (move_uploaded_file($tmp, $dest)) {
              $uploadedImages[] = '/storage/uploads/areas/' . $newName;
            } else {
              $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้";
            }
          }
        }
      }
    }
  }

  if (empty($errors)) {
    try {
      $pdo = Database::connection();
      $pdo->beginTransaction();

      try {
        // เพิ่มรายการพื้นที่เช่า ตามสคีมาใหม่
        Database::execute(
          'INSERT INTO rental_area (user_id, area_name, price_per_year, deposit_percent, area_size, district_id, area_status)
           VALUES (?, ?, ?, ?, ?, ?, ?)',
          [
            $userId,
            $title,
            $price,
            $depositPercent,
            $area_size,
            $districtId,
            'available'
          ]
        );

        $areaId = (int)$pdo->lastInsertId();

        if ($areaId <= 0) {
          throw new RuntimeException('ไม่สามารถดึง area_id หลังการแทรกข้อมูลได้');
        }

        foreach ($uploadedImages as $url) {
          Database::execute(
            'INSERT INTO area_image (image_url, area_id) VALUES (?, ?)',
            [$url, $areaId]
          );
        }

        $pdo->commit();

        flash('success', 'เพิ่มรายการพื้นที่เรียบร้อยแล้ว');
        redirect('?page=my_properties');
      } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
      }
    } catch (Throwable $e) {
      app_log('add_property_error', [
        'user_id' => $userId,
        'title' => $title,
        'price' => $price,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
    }
  }
}
?>

<link rel="stylesheet" href="/css/add-property.css">

<div class="add-property-container">
  <div class="form-header">
    <a href="?page=my_properties" class="btn-back">← กลับรายการของฉัน</a>
    <h1>เพิ่มรายการปล่อยเช่า</h1>
    <p class="form-subtitle">กรอกข้อมูลพื้นที่ที่ต้องการปล่อยเช่า</p>
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

    <div class="form-section">
      <h2 class="section-title">ข้อมูลพื้นฐาน</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="title">ชื่อพื้นที่ <span class="required">*</span></label>
          <input id="title" name="title" type="text" required placeholder="เช่น ไร่ข้าว 5 ไร่ ใกล้คลองส่งน้ำ" value="<?= e($_POST['title'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="province">จังหวัด <span class="required">*</span></label>
          <select id="province" name="province" required>
            <option value="">-- เลือกจังหวัด --</option>
            <?php $oldProv = (int)($_POST['province'] ?? 0); ?>
            <?php foreach ($provinces as $prov): ?>
              <option value="<?= e((string)$prov['province_id']) ?>" data-name="<?= e($prov['province_name']) ?>" <?= $oldProv === (int)$prov['province_id'] ? 'selected' : ''; ?>><?= e($prov['province_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="district">อำเภอ <span class="required">*</span></label>
          <select id="district" name="district" required disabled>
            <option value="">เลือกจังหวัดก่อน</option>
            <?php $oldDist = (int)($_POST['district'] ?? 0); ?>
            <?php foreach ($districts as $dist): ?>
              <option value="<?= e((string)$dist['district_id']) ?>" data-province-id="<?= e((string)$dist['province_id']) ?>" <?= $oldDist === (int)$dist['district_id'] ? 'selected' : ''; ?>><?= e($dist['district_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="deposit_percent">เปอร์เซ็นต์มัดจำ (%) <span class="required">*</span></label>
          <input id="deposit_percent" name="deposit_percent" type="number" value="10" readonly disabled>
          <small class="text-note">กำหนดคงที่ 10% ไม่สามารถแก้ไขได้</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ขนาดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="area_rai">ไร่</label>
          <input id="area_rai" name="area_rai" type="number" min="0" placeholder="เช่น 5" value="<?= e((string)($_POST['area_rai'] ?? 0)) ?>">
        </div>
        <div class="form-group">
          <label for="area_ngan">งาน</label>
          <input id="area_ngan" name="area_ngan" type="number" min="0" max="99" placeholder="เช่น 2" value="<?= e((string)($_POST['area_ngan'] ?? 0)) ?>">
        </div>
        <div class="form-group">
          <label for="area_sqwa">ตารางวา</label>
          <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99" placeholder="เช่น 50" value="<?= e((string)($_POST['area_sqwa'] ?? 0)) ?>">
        </div>
      </div>

    </div>
    <div class="form-section">
      <h2 class="section-title">ราคาและรายละเอียด</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
          <input id="price" name="price" type="number" min="0" max="999999.99" step="0.01" required placeholder="เช่น 15000" value="<?= e($_POST['price'] ?? '') ?>">
          <small class="text-note">ราคาสูงสุด 999,999.99 บาท</small>
        </div>
      </div>

      <!-- ไม่มีช่องรายละเอียดในสคีมาใหม่ -->
    </div>

    <div class="form-section">
      <h2 class="section-title">รูปภาพพื้นที่ (สูงสุด 10 รูป)</h2>

      <div class="upload-area">
        <input id="images" name="images[]" type="file" multiple accept="image/*" style="display:none;">
        <label for="images" class="upload-label">
          <div class="upload-text">คลิกเพื่อเลือกรูปภาพ</div>
          <div class="upload-hint">ไฟล์ละไม่เกิน 5MB (สูงสุด 10 รูป)</div>
        </label>
      </div>

      <div id="imagePreview" class="image-preview-grid"></div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-submit">เพิ่มรายการ</button>
      <a href="?page=my_properties" class="btn-cancel">ยกเลิก</a>
    </div>
  </form>
</div>