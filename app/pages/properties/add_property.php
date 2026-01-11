<?php

declare(strict_types=1);

/**
 * app/pages/add_property.php (REFAC)
 * - เพิ่มพื้นที่ให้เช่า + อัปโหลดรูป (สูงสุด 10 รูป)
 * - กันพลาด: include, session, auth, admin
 * - validate: title, province/district relation, area size, price, deposit_percent
 * - upload: ตรวจ ext + mime + size (5MB) + ใช้ ImageService ถ้ามี (fallback move_uploaded_file)
 * - transaction: insert rental_area + insert area_image
 */

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile)) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลด Database</p></div>';
  return;
}
if (!is_file($helpersFile)) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลด Helpers</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

// optional
$imageServiceFile = APP_PATH . '/includes/services/ImageService.php';
if (is_file($imageServiceFile)) {
  require_once $imageServiceFile;
}

// ----------------------------
// session
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
// guards
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนเพิ่มรายการพื้นที่');
  redirect('?page=signin', 303);
  return;
}

if (defined('ROLE_ADMIN') && (int)($user['role'] ?? 0) === ROLE_ADMIN) {
  flash('error', 'ผู้ดูแลระบบไม่สามารถเพิ่มพื้นที่ได้');
  redirect('?page=admin_dashboard', 303);
  return;
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('add_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin', 303);
  return;
}

// verify user exists (กัน session ปลอม/ข้อมูลเก่า)
try {
  $userCheck = Database::fetchOne('SELECT user_id FROM users WHERE user_id = ? LIMIT 1', [$userId]);
  if (!$userCheck) {
    app_log('add_property_user_not_found', ['user_id' => $userId, 'session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่พบในระบบ กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin', 303);
    return;
  }
} catch (Throwable $e) {
  app_log('add_property_user_check_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
  flash('error', 'ไม่สามารถตรวจสอบข้อมูลผู้ใช้ได้');
  redirect('?page=signin', 303);
  return;
}

// ----------------------------
// constants
// ----------------------------
const MAX_PRICE_PER_YEAR = 999999.99;
const MAX_IMAGES = 10;
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

// ----------------------------
// helpers
// ----------------------------
$csrfToken = function_exists('csrf_token') ? csrf_token() : '';

$requireCsrf = static function (): void {
  $t = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($t)) {
    throw new RuntimeException('CSRF');
  }
};

$asInt = static function (mixed $v, int $default = 0): int {
  if (is_int($v)) return $v;
  if (is_string($v) && $v !== '' && preg_match('/^-?\d+$/', $v)) return (int)$v;
  if (is_numeric($v)) return (int)$v;
  return $default;
};

$validateAreaSize = static function (int $rai, int $ngan, int $sqwa): array {
  if ($rai < 0 || $ngan < 0 || $sqwa < 0) return ['ok' => false, 'msg' => 'ขนาดพื้นที่ต้องไม่ติดลบ'];
  if ($ngan > 99 || $sqwa > 99) return ['ok' => false, 'msg' => 'งานและตารางวาต้องไม่เกิน 99'];
  $size = (float)$rai + ($ngan / 4.0) + ($sqwa / 400.0);
  if ($size <= 0) return ['ok' => false, 'msg' => 'กรุณาระบุขนาดพื้นที่อย่างน้อย 0.01 ไร่'];
  return ['ok' => true, 'size' => $size];
};

$validatePrice = static function (string $priceStr): array {
  $priceStr = trim($priceStr);
  if ($priceStr === '' || !is_numeric($priceStr)) return ['ok' => false, 'msg' => 'กรุณากรอกราคาที่ถูกต้อง'];
  $price = (float)$priceStr;
  if ($price < 0) return ['ok' => false, 'msg' => 'ราคาต้องไม่ติดลบ'];
  if ($price > MAX_PRICE_PER_YEAR) return ['ok' => false, 'msg' => 'ราคาต้องไม่เกิน ' . number_format(MAX_PRICE_PER_YEAR, 2) . ' บาท'];
  return ['ok' => true, 'price' => $price];
};

$validateDepositPercent = static function (string $raw): array {
  $raw = trim($raw);
  if ($raw === '' || !is_numeric($raw)) return ['ok' => false, 'msg' => 'กรุณากรอกเปอร์เซ็นต์มัดจำเป็นตัวเลข'];
  $v = (float)$raw;
  if ($v < 0 || $v > 100) return ['ok' => false, 'msg' => 'เปอร์เซ็นต์มัดจำต้องอยู่ระหว่าง 0 - 100%'];
  return ['ok' => true, 'value' => $v];
};

$getUploadDir = static function (): string {
  $root = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(APP_PATH);
  return $root . '/public/storage/uploads/areas';
};

$ensureDir = static function (string $dir): void {
  if (is_dir($dir)) return;
  if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
    throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์อัปโหลด: ' . $dir);
  }
};

$toFileArray = static function (array $files): array {
  // แปลง $_FILES['images'] (multi) -> list of file objects
  $out = [];
  $names = $files['name'] ?? [];
  $tmp   = $files['tmp_name'] ?? [];
  $err   = $files['error'] ?? [];
  $size  = $files['size'] ?? [];

  if (!is_array($names)) return $out;

  $count = count($names);
  for ($i = 0; $i < $count; $i++) {
    $out[] = [
      'name' => (string)($names[$i] ?? ''),
      'tmp_name' => (string)($tmp[$i] ?? ''),
      'error' => (int)($err[$i] ?? UPLOAD_ERR_NO_FILE),
      'size' => (int)($size[$i] ?? 0),
      'index' => $i,
    ];
  }
  return $out;
};

$validateImageFile = static function (array $f): array {
  $name = (string)($f['name'] ?? '');
  $tmp  = (string)($f['tmp_name'] ?? '');
  $err  = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
  $size = (int)($f['size'] ?? 0);

  if ($err === UPLOAD_ERR_NO_FILE) return ['skip' => true];
  if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพบางไฟล์'];

  if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'msg' => "ไฟล์ {$name} อัปโหลดไม่ถูกต้อง"];
  if ($size <= 0) return ['ok' => false, 'msg' => "ไฟล์ {$name} ไม่ถูกต้อง"];
  if ($size > MAX_IMAGE_SIZE) return ['ok' => false, 'msg' => sprintf('รูปภาพ %s มีขนาดใหญ่เกิน %dMB', $name, (int)(MAX_IMAGE_SIZE / 1024 / 1024))];

  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));

  // NOTE: heic/heif ถ้าจะรองรับจริง ต้องมี lib ใน server + เพิ่ม mime ให้ตรง
  $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  if (!in_array($ext, $allowedExt, true)) return ['ok' => false, 'msg' => "รูปภาพ {$name} ไม่ใช่ไฟล์ที่รองรับ (jpg, jpeg, png, gif, webp)"];

  if (!class_exists('finfo')) return ['ok' => false, 'msg' => 'ระบบตรวจสอบไฟล์ไม่พร้อมใช้งาน (finfo)'];

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = (string)($finfo->file($tmp) ?: '');

  $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($mime, $allowedMimes, true)) return ['ok' => false, 'msg' => "ไฟล์ {$name} ไม่ใช่รูปภาพที่รองรับ"];

  if (@getimagesize($tmp) === false) return ['ok' => false, 'msg' => "ไฟล์ {$name} ไม่ใช่รูปภาพที่ถูกต้อง"];

  return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
};

$saveImage = static function (array $f, string $uploadDir): array {
  $name = (string)$f['name'];
  $tmp  = (string)$f['tmp_name'];
  $idx  = (int)($f['index'] ?? 0);

  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
  $random = bin2hex(random_bytes(8));
  $baseNameNoExt = sprintf('area_%s_%s_%d', $random, date('YmdHis'), $idx);

  // ใช้ ImageService (required)
  if (!class_exists('ImageService') || !method_exists('ImageService', 'uploadAndProcess')) {
    return ['ok' => false, 'msg' => 'ImageService ไม่พร้อมใช้งาน'];
  }

  try {
    $fileArray = [
      'tmp_name' => $tmp,
      'name' => $name,
      'size' => (int)$f['size'],
      'error' => UPLOAD_ERR_OK,
    ];

    $result = ImageService::uploadAndProcess(
      $fileArray,
      $uploadDir,
      '/storage/uploads/areas',
      $baseNameNoExt
    );

    return ['ok' => true, 'url' => $result['public_path']];
  } catch (Throwable $e) {
    app_log('add_property_imageservice_error', [
      'error' => $e->getMessage(),
      'file' => $name,
    ]);
    return ['ok' => false, 'msg' => "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้: " . $e->getMessage()];
  }
};

// ----------------------------
// load locations for select
// ----------------------------
$provinces = [];
$districts = [];
$loadError = null;

try {
  $provinces = Database::fetchAll('SELECT province_id, province_name FROM province ORDER BY province_name ASC');
  $districts = Database::fetchAll('SELECT district_id, district_name, province_id FROM district ORDER BY district_name ASC');
} catch (Throwable $e) {
  app_log('add_property_load_province_district_error', ['error' => $e->getMessage()]);
  $loadError = 'ไม่สามารถโหลดข้อมูลจังหวัด/อำเภอได้';
}

// ----------------------------
// handle POST
// ----------------------------
$errors = [];
if ($loadError) $errors[] = $loadError;

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
  // csrf
  try {
    $requireCsrf();
  } catch (Throwable $e) {
    $errors[] = 'คำขอไม่ถูกต้อง (CSRF)';
  }

  $title = trim((string)($_POST['title'] ?? ''));

  $provinceId = (int)(filter_input(INPUT_POST, 'province', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
  $districtId = (int)(filter_input(INPUT_POST, 'district', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);

  $area_rai  = (int)(filter_input(INPUT_POST, 'area_rai',  FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?? 0);
  $area_ngan = (int)(filter_input(INPUT_POST, 'area_ngan', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? 0);
  $area_sqwa = (int)(filter_input(INPUT_POST, 'area_sqwa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? 0);

  $priceRaw = (string)($_POST['price'] ?? '');
  $depositRaw = (string)($_POST['deposit_percent'] ?? '10');

  // validate: title
  if ($title === '' || mb_strlen($title) > 255) $errors[] = 'กรุณากรอกชื่อพื้นที่ (ไม่เกิน 255 ตัวอักษร)';

  // validate: location
  if ($provinceId <= 0) $errors[] = 'กรุณาเลือกจังหวัด';
  if ($districtId <= 0) $errors[] = 'กรุณาเลือกอำเภอ';

  // validate: district belongs province (กันยิง POST มั่ว)
  if ($provinceId > 0 && $districtId > 0) {
    try {
      $ok = Database::fetchOne(
        'SELECT district_id FROM district WHERE district_id = ? AND province_id = ? LIMIT 1',
        [$districtId, $provinceId]
      );
      if (!$ok) $errors[] = 'อำเภอที่เลือกไม่อยู่ในจังหวัดที่เลือก';
    } catch (Throwable $e) {
      app_log('add_property_validate_district_error', ['error' => $e->getMessage()]);
      $errors[] = 'ไม่สามารถตรวจสอบจังหวัด/อำเภอได้';
    }
  }

  // validate: price
  $price = 0.0;
  $vp = $validatePrice($priceRaw);
  if (!($vp['ok'] ?? false)) $errors[] = (string)($vp['msg'] ?? 'ราคาผิดพลาด');
  else $price = (float)$vp['price'];

  // validate: deposit percent
  $depositPercent = 10.0;
  $vd = $validateDepositPercent($depositRaw);
  if (!($vd['ok'] ?? false)) $errors[] = (string)($vd['msg'] ?? 'เปอร์เซ็นต์มัดจำผิดพลาด');
  else $depositPercent = (float)$vd['value'];

  // validate: area size
  $area_size = 0.0;
  $va = $validateAreaSize($area_rai, $area_ngan, $area_sqwa);
  if (!($va['ok'] ?? false)) $errors[] = (string)($va['msg'] ?? 'ขนาดพื้นที่ผิดพลาด');
  else $area_size = (float)$va['size'];

  // upload (optional)
  $uploadedUrls = [];
  $files = $_FILES['images'] ?? null;

  if (is_array($files) && isset($files['name']) && is_array($files['name']) && !empty($files['name'][0])) {
    $list = $toFileArray($files);

    // นับเฉพาะไฟล์ที่ไม่ใช่ NO_FILE
    $realCount = 0;
    foreach ($list as $f) {
      if ((int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $realCount++;
    }
    if ($realCount > MAX_IMAGES) $errors[] = 'สามารถอัปโหลดรูปภาพได้สูงสุด ' . MAX_IMAGES . ' รูป';

    if (empty($errors)) {
      $uploadDir = $getUploadDir();

      try {
        $ensureDir($uploadDir);
        if (!is_writable($uploadDir)) $errors[] = 'โฟลเดอร์อัปโหลดเขียนไม่ได้: ' . $uploadDir;
      } catch (Throwable $e) {
        app_log('add_property_upload_dir_error', ['dir' => $uploadDir, 'error' => $e->getMessage()]);
        $errors[] = $e->getMessage();
      }

      if (empty($errors)) {
        foreach ($list as $f) {
          $check = $validateImageFile($f);
          if (($check['skip'] ?? false) === true) continue;
          if (!($check['ok'] ?? false)) {
            $errors[] = (string)($check['msg'] ?? 'รูปภาพไม่ถูกต้อง');
            continue;
          }

          $saved = $saveImage($f, $uploadDir);
          if (!($saved['ok'] ?? false)) {
            $errors[] = (string)($saved['msg'] ?? 'อัปโหลดรูปไม่สำเร็จ');
            continue;
          }
          $uploadedUrls[] = (string)$saved['url'];
        }
      }
    }
  }

  // insert
  if (empty($errors)) {
    try {
      Database::transaction(function () use ($userId, $title, $price, $depositPercent, $area_size, $districtId, $uploadedUrls): void {
        Database::execute(
          'INSERT INTO rental_area (user_id, area_name, price_per_year, deposit_percent, area_size, district_id, area_status)
           VALUES (?, ?, ?, ?, ?, ?, "available")',
          [$userId, $title, $price, $depositPercent, $area_size, $districtId]
        );

        $areaId = (int)Database::connection()->lastInsertId();
        if ($areaId <= 0) throw new RuntimeException('ไม่สามารถสร้างรายการพื้นที่ได้');

        foreach ($uploadedUrls as $url) {
          Database::execute('INSERT INTO area_image (image_url, area_id) VALUES (?, ?)', [$url, $areaId]);
        }

        app_log('add_property_success', ['user_id' => $userId, 'area_id' => $areaId]);
      });

      flash('success', 'เพิ่มรายการพื้นที่เรียบร้อยแล้ว');
      redirect('?page=my_properties', 303);
      return;
    } catch (Throwable $e) {
      app_log('add_property_error', [
        'user_id' => $userId,
        'title' => $title,
        'price' => $price,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
      if (function_exists('app_debug_enabled') && app_debug_enabled()) {
        $errors[] = $e->getMessage();
      }
    }
  }
}

?>
<link rel="stylesheet" href="/css/pages/properties/add_property.css">

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
          <li><?= e((string)$err); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="property-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken); ?>">

    <div class="form-section">
      <h2 class="section-title">ข้อมูลพื้นฐาน</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="title">ชื่อพื้นที่ <span class="required">*</span></label>
          <input
            id="title"
            name="title"
            type="text"
            required
            maxlength="255"
            placeholder="เช่น ไร่ข้าว 5 ไร่ ใกล้คลองส่งน้ำ"
            value="<?= e((string)($_POST['title'] ?? '')); ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="province">จังหวัด <span class="required">*</span></label>
          <?php $oldProv = (int)($_POST['province'] ?? 0); ?>
          <select id="province" name="province" required>
            <option value="">-- เลือกจังหวัด --</option>
            <?php foreach ($provinces as $prov): ?>
              <option
                value="<?= e((string)($prov['province_id'] ?? '')); ?>"
                data-name="<?= e((string)($prov['province_name'] ?? '')); ?>"
                <?= $oldProv === (int)($prov['province_id'] ?? 0) ? 'selected' : ''; ?>>
                <?= e((string)($prov['province_name'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="district">อำเภอ <span class="required">*</span></label>
          <?php $oldDist = (int)($_POST['district'] ?? 0); ?>
          <select id="district" name="district" required disabled>
            <option value="">เลือกจังหวัดก่อน</option>
            <?php foreach ($districts as $dist): ?>
              <option
                value="<?= e((string)($dist['district_id'] ?? '')); ?>"
                data-province-id="<?= e((string)($dist['province_id'] ?? '')); ?>"
                <?= $oldDist === (int)($dist['district_id'] ?? 0) ? 'selected' : ''; ?>>
                <?= e((string)($dist['district_name'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="deposit_percent">เปอร์เซ็นต์มัดจำ (%) <span class="required">*</span></label>
          <input
            id="deposit_percent"
            name="deposit_percent"
            type="number"
            min="0"
            max="100"
            step="0.01"
            required
            value="<?= e((string)($_POST['deposit_percent'] ?? '10')); ?>">
          <small class="text-note">ระบุได้ 0 - 100% (ค่าเริ่มต้น 10%)</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ขนาดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="area_rai">ไร่</label>
          <input id="area_rai" name="area_rai" type="number" min="0" value="<?= e((string)($_POST['area_rai'] ?? 0)); ?>">
        </div>
        <div class="form-group">
          <label for="area_ngan">งาน</label>
          <input id="area_ngan" name="area_ngan" type="number" min="0" max="99" value="<?= e((string)($_POST['area_ngan'] ?? 0)); ?>">
        </div>
        <div class="form-group">
          <label for="area_sqwa">ตารางวา</label>
          <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99" value="<?= e((string)($_POST['area_sqwa'] ?? 0)); ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ราคา</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
          <input
            id="price"
            name="price"
            type="number"
            min="0"
            max="<?= e((string)MAX_PRICE_PER_YEAR); ?>"
            step="0.01"
            required
            placeholder="เช่น 15000"
            value="<?= e((string)($_POST['price'] ?? '')); ?>">
          <small class="text-note">ราคาสูงสุด <?= e(number_format(MAX_PRICE_PER_YEAR, 2)); ?> บาท</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">รูปภาพพื้นที่ (สูงสุด <?= (int)MAX_IMAGES; ?> รูป)</h2>

      <div class="upload-area">
        <input id="images" name="images[]" type="file" multiple accept="image/*" style="display:none;">
        <label for="images" class="upload-label">
          <div class="upload-text">คลิกเพื่อเลือกรูปภาพ</div>
          <div class="upload-hint">ไฟล์ละไม่เกิน <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024); ?>MB (สูงสุด <?= (int)MAX_IMAGES; ?> รูป)</div>
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

<script nonce="<?= e(csp_nonce()); ?>">
  (function() {
    "use strict";

    const province = document.getElementById("province");
    const district = document.getElementById("district");
    const preview = document.getElementById("imagePreview");
    const input = document.getElementById("images");

    function syncDistrict() {
      if (!province || !district) return;
      const provId = province.value;
      const opts = Array.from(district.querySelectorAll("option"));
      let any = false;

      opts.forEach((o) => {
        const pid = o.getAttribute("data-province-id");
        if (!pid) return;
        const show = provId && pid === provId;
        o.hidden = !show;
        if (show) any = true;
      });

      district.disabled = !provId || !any;

      // ถ้า selected ไม่ตรงจังหวัด ให้ reset
      const selected = district.options[district.selectedIndex];
      if (selected && selected.hidden) district.value = "";
      if (!provId) district.value = "";
    }

    if (province && district) {
      province.addEventListener("change", syncDistrict);
      // initial (กรณีมี old input)
      syncDistrict();
    }

    // simple preview
    function clearPreview() {
      if (!preview) return;
      preview.innerHTML = "";
    }

    function renderPreview(files) {
      if (!preview) return;
      clearPreview();
      const list = Array.from(files || []);
      list.slice(0, <?= (int)MAX_IMAGES; ?>).forEach((f) => {
        const url = URL.createObjectURL(f);
        const item = document.createElement("div");
        item.className = "image-preview-item";
        item.innerHTML = `<img src="${url}" alt="preview" loading="lazy">`;
        preview.appendChild(item);
      });
    }

    if (input) {
      input.addEventListener("change", () => {
        renderPreview(input.files);
      });
    }
  })();
</script>