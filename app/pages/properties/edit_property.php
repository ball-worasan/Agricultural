<?php
// app/pages/edit_property.php  (FULL FIXED)

declare(strict_types=1);

/**
 * FIXES
 * - ลบ inline AJAX delete ที่ยิงซ้ำ (เหลือให้ delete ผ่าน ?page=delete_property_image อย่างเดียว)
 * - แก้ bug: json_response แล้วไม่ return (ตอน action=delete_image)
 * - เอา option reserved ออก (เพราะ DB enum ไม่มี reserved)
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
if (is_file($imageServiceFile)) require_once $imageServiceFile;

// ----------------------------
// session
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
// guards
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนแก้ไขรายการพื้นที่');
  redirect('?page=signin', 303);
  return;
}

$userId   = (int)($user['user_id'] ?? $user['id'] ?? 0);
$userRole = (int)($user['role'] ?? 0);
$isAdmin  = defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN;

if ($userId <= 0) {
  app_log('edit_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin', 303);
  return;
}

// ----------------------------
// constants
// ----------------------------
const MAX_PRICE_PER_YEAR = 999999.99;
const MAX_IMAGES = 10;
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

// NOTE: DB enum รองรับแค่นี้
$allowedStatuses = ['available', 'booked', 'unavailable'];

// ----------------------------
// helpers
// ----------------------------
$isAjax = static function (): bool {
  $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  return $xrw === 'xmlhttprequest' || stripos($accept, 'application/json') !== false;
};

$csrfToken = function_exists('csrf_token') ? csrf_token() : '';

$requireCsrf = static function (): void {
  $t = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($t)) {
    throw new RuntimeException('CSRF');
  }
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

$validateDepositPercent = static function (string $raw): array {
  $raw = trim($raw);
  if ($raw === '' || !is_numeric($raw)) return ['ok' => false, 'msg' => 'กรุณากรอกเปอร์เซ็นต์มัดจำเป็นตัวเลข'];
  $v = (float)$raw;
  if ($v < 0 || $v > 100) return ['ok' => false, 'msg' => 'เปอร์เซ็นต์มัดจำต้องอยู่ระหว่าง 0 - 100%'];
  return ['ok' => true, 'value' => $v];
};

$validatePrice = static function (string $raw): array {
  $raw = trim($raw);
  if ($raw === '' || !is_numeric($raw)) return ['ok' => false, 'msg' => 'กรุณากรอกราคาที่ถูกต้อง'];
  $p = (float)$raw;
  if ($p < 0) return ['ok' => false, 'msg' => 'ราคาต้องไม่ติดลบ'];
  if ($p > MAX_PRICE_PER_YEAR) return ['ok' => false, 'msg' => 'ราคาต้องไม่เกิน ' . number_format(MAX_PRICE_PER_YEAR, 2) . ' บาท'];
  return ['ok' => true, 'price' => $p];
};

$validateAreaSize = static function (int $rai, int $ngan, int $sqwa): array {
  if ($rai < 0 || $ngan < 0 || $sqwa < 0) return ['ok' => false, 'msg' => 'ขนาดพื้นที่ต้องไม่ติดลบ'];
  if ($ngan > 99 || $sqwa > 99) return ['ok' => false, 'msg' => 'งานและตารางวาต้องไม่เกิน 99'];
  $size = (float)$rai + ($ngan / 4.0) + ($sqwa / 400.0);
  if ($size <= 0) return ['ok' => false, 'msg' => 'กรุณาระบุขนาดพื้นที่อย่างน้อย 0.01 ไร่'];
  return ['ok' => true, 'size' => $size];
};

$toFileArray = static function (array $files): array {
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
  $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  if (!in_array($ext, $allowedExt, true)) return ['ok' => false, 'msg' => "รูปภาพ {$name} ไม่ใช่ไฟล์ที่รองรับ (jpg, jpeg, png, gif, webp)"];

  if (!class_exists('finfo')) return ['ok' => false, 'msg' => 'ระบบตรวจสอบไฟล์ไม่พร้อมใช้งาน (finfo)'];

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = (string)($finfo->file($tmp) ?: '');

  $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($mime, $allowedMimes, true)) return ['ok' => false, 'msg' => "ไฟล์ {$name} ไม่ใช่รูปภาพที่รองรับ"];

  if (@getimagesize($tmp) === false) return ['ok' => false, 'msg' => "ไฟล์ {$name} ไม่ใช่รูปภาพที่ถูกต้อง"];

  return ['ok' => true, 'ext' => $ext];
};

$saveImage = static function (array $f, string $uploadDir): array {
  $name = (string)$f['name'];
  $tmp  = (string)$f['tmp_name'];
  $idx  = (int)($f['index'] ?? 0);

  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
  $random = bin2hex(random_bytes(8));
  $baseNameNoExt = sprintf('area_%s_%s_%d', $random, date('YmdHis'), $idx);

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
    app_log('edit_property_imageservice_error', [
      'error' => $e->getMessage(),
      'file' => $name,
    ]);
    return ['ok' => false, 'msg' => "ไม่สามารถอัปโหลดรูปภาพ {$name} ได้: " . $e->getMessage()];
  }
};

// ----------------------------
// validate area id
// ----------------------------
$areaId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? 0);
if ($areaId <= 0) {
  flash('error', 'ไม่พบรายการพื้นที่ที่ต้องการแก้ไข');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties', 303);
  return;
}

// ----------------------------
// load provinces/districts
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
// fetch area (admin vs owner)
// ----------------------------
$area = null;
$provinceId = 0;

try {
  if ($isAdmin) {
    $area = Database::fetchOne(
      'SELECT ra.area_id, ra.user_id, ra.area_name, ra.price_per_year, ra.deposit_percent, ra.area_size,
              ra.district_id, ra.area_status, ra.created_at, ra.updated_at, d.province_id
         FROM rental_area ra
         JOIN district d ON ra.district_id = d.district_id
        WHERE ra.area_id = ?
        LIMIT 1',
      [$areaId]
    );
  } else {
    $area = Database::fetchOne(
      'SELECT ra.area_id, ra.user_id, ra.area_name, ra.price_per_year, ra.deposit_percent, ra.area_size,
              ra.district_id, ra.area_status, ra.created_at, ra.updated_at, d.province_id
         FROM rental_area ra
         JOIN district d ON ra.district_id = d.district_id
        WHERE ra.area_id = ? AND ra.user_id = ?
        LIMIT 1',
      [$areaId, $userId]
    );
  }

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
  flash('error', $isAdmin ? 'ไม่พบรายการพื้นที่' : 'ไม่พบรายการพื้นที่ของคุณ');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties', 303);
  return;
}

// owner: ห้ามแก้ booked/unavailable
$currentStatus = (string)($area['area_status'] ?? '');
if (!$isAdmin && in_array($currentStatus, ['booked', 'unavailable'], true)) {
  flash('error', 'ไม่สามารถแก้ไขพื้นที่ที่ติดจองหรือปิดให้เช่าแล้วได้');
  redirect('?page=my_properties', 303);
  return;
}

// ----------------------------
// existing images
// ----------------------------
$existingImages = [];
try {
  $existingImages = Database::fetchAll(
    'SELECT image_id, area_id, image_url, created_at FROM area_image WHERE area_id = ? ORDER BY image_id ASC',
    [$areaId]
  );
} catch (Throwable $e) {
  app_log('edit_property_images_fetch_error', ['area_id' => $areaId, 'error' => $e->getMessage()]);
  $existingImages = [];
}

// ----------------------------
// area_size -> rai/ngan/sqwa
// ----------------------------
$defaultRai = 0;
$defaultNgan = 0;
$defaultSqwa = 0;

if (isset($area['area_size'])) {
  $size = (float)$area['area_size'];
  $defaultRai = (int)floor($size);
  $remainRai = $size - $defaultRai;

  $totalNgan = $remainRai * 4.0;
  $defaultNgan = (int)floor($totalNgan);
  if ($defaultNgan > 3) $defaultNgan = 3;

  $remainNgan = $totalNgan - $defaultNgan;
  $defaultSqwa = (int)round($remainNgan * 100.0);
  if ($defaultSqwa > 99) $defaultSqwa = 99;
}

$errors = [];

/**
 * IMPORTANT:
 * - ลบ “AJAX delete image” บนหน้านี้ออกแล้ว
 * - ให้ลบรูปผ่าน endpoint ?page=delete_property_image เท่านั้น (ดู edit_property.js)
 */

// ----------------------------
// POST: update
// ----------------------------
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && !isset($_POST['action'])) {
  try {
    $requireCsrf();
  } catch (Throwable $e) {
    $errors[] = 'คำขอไม่ถูกต้อง (CSRF)';
  }

  $title      = trim((string)($_POST['title'] ?? (string)($area['area_name'] ?? '')));
  $provinceIn = (int)(filter_input(INPUT_POST, 'province', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? $provinceId);
  $districtId = (int)(filter_input(INPUT_POST, 'district', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?? (int)($area['district_id'] ?? 0));

  $areaRai  = (int)(filter_input(INPUT_POST, 'area_rai',  FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?? $defaultRai);
  $areaNgan = (int)(filter_input(INPUT_POST, 'area_ngan', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? $defaultNgan);
  $areaSqwa = (int)(filter_input(INPUT_POST, 'area_sqwa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?? $defaultSqwa);

  $priceRaw   = (string)($_POST['price'] ?? (string)($area['price_per_year'] ?? ''));
  $depositRaw = (string)($_POST['deposit_percent'] ?? (string)($area['deposit_percent'] ?? '10'));

  $status = trim((string)($_POST['status'] ?? (string)($area['area_status'] ?? 'available')));
  if (!in_array($status, $allowedStatuses, true)) $status = 'available';

  if ($title === '' || mb_strlen($title) > 255) $errors[] = 'กรุณากรอกชื่อพื้นที่ (ไม่เกิน 255 ตัวอักษร)';
  if ($provinceIn <= 0) $errors[] = 'กรุณาเลือกจังหวัด';
  if ($districtId <= 0) $errors[] = 'กรุณาเลือกอำเภอ';

  // validate district belongs province
  if ($provinceIn > 0 && $districtId > 0) {
    try {
      $ok = Database::fetchOne(
        'SELECT district_id FROM district WHERE district_id = ? AND province_id = ? LIMIT 1',
        [$districtId, $provinceIn]
      );
      if (!$ok) $errors[] = 'อำเภอที่เลือกไม่อยู่ในจังหวัดที่เลือก';
    } catch (Throwable $e) {
      app_log('edit_property_validate_district_error', ['error' => $e->getMessage()]);
      $errors[] = 'ไม่สามารถตรวจสอบจังหวัด/อำเภอได้';
    }
  }

  $price = 0.0;
  $vp = $validatePrice($priceRaw);
  if (!($vp['ok'] ?? false)) $errors[] = (string)($vp['msg'] ?? 'ราคาผิดพลาด');
  else $price = (float)$vp['price'];

  $depositPercent = 10.0;
  $vd = $validateDepositPercent($depositRaw);
  if (!($vd['ok'] ?? false)) $errors[] = (string)($vd['msg'] ?? 'เปอร์เซ็นต์มัดจำผิดพลาด');
  else $depositPercent = (float)$vd['value'];

  $areaSize = 0.0;
  $va = $validateAreaSize($areaRai, $areaNgan, $areaSqwa);
  if (!($va['ok'] ?? false)) $errors[] = (string)($va['msg'] ?? 'ขนาดพื้นที่ผิดพลาด');
  else $areaSize = (float)$va['size'];

  // upload new images (max 10 total)
  $uploadedUrls = [];
  $files = $_FILES['images'] ?? null;

  $existingCount = count($existingImages);
  if (is_array($files) && isset($files['name']) && is_array($files['name']) && !empty($files['name'][0])) {
    $list = $toFileArray($files);

    $realCount = 0;
    foreach ($list as $f) {
      if ((int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $realCount++;
    }

    if ($existingCount + $realCount > MAX_IMAGES) {
      $errors[] = 'สามารถมีรูปภาพได้สูงสุด ' . MAX_IMAGES . ' รูป (ปัจจุบันมี ' . $existingCount . ' รูป)';
    }

    if (empty($errors)) {
      $uploadDir = $getUploadDir();

      try {
        $ensureDir($uploadDir);
        if (!is_writable($uploadDir)) $errors[] = 'โฟลเดอร์อัปโหลดเขียนไม่ได้: ' . $uploadDir;
      } catch (Throwable $e) {
        app_log('edit_property_upload_dir_error', ['dir' => $uploadDir, 'error' => $e->getMessage()]);
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

  if (empty($errors)) {
    try {
      Database::transaction(function () use (
        $isAdmin,
        $areaId,
        $userId,
        $title,
        $price,
        $depositPercent,
        $areaSize,
        $districtId,
        $status,
        $uploadedUrls
      ): void {
        if ($isAdmin) {
          Database::execute(
            'UPDATE rental_area
                SET area_name = ?, price_per_year = ?, deposit_percent = ?, area_size = ?, district_id = ?, area_status = ?, updated_at = CURRENT_TIMESTAMP
              WHERE area_id = ?',
            [$title, $price, $depositPercent, $areaSize, $districtId, $status, $areaId]
          );
        } else {
          Database::execute(
            'UPDATE rental_area
                SET area_name = ?, price_per_year = ?, deposit_percent = ?, area_size = ?, district_id = ?, area_status = ?, updated_at = CURRENT_TIMESTAMP
              WHERE area_id = ? AND user_id = ?',
            [$title, $price, $depositPercent, $areaSize, $districtId, $status, $areaId, $userId]
          );
        }

        foreach ($uploadedUrls as $url) {
          Database::execute('INSERT INTO area_image (image_url, area_id) VALUES (?, ?)', [$url, $areaId]);
        }
      });

      flash('success', 'บันทึกการแก้ไขรายการพื้นที่เรียบร้อยแล้ว');
      redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties', 303);
      return;
    } catch (Throwable $e) {
      app_log('edit_property_update_error', ['area_id' => $areaId, 'error' => $e->getMessage()]);
      $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
      if (function_exists('app_debug_enabled') && app_debug_enabled()) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // reload images after POST (error)
  try {
    $existingImages = Database::fetchAll(
      'SELECT image_id, area_id, image_url, created_at FROM area_image WHERE area_id = ? ORDER BY image_id ASC',
      [$areaId]
    );
  } catch (Throwable $e) {
    $existingImages = [];
  }
}

// ----------------------------
// view vars
// ----------------------------
$provinceIdForForm = (int)($_POST['province'] ?? $provinceId);
$districtIdForForm = (int)($_POST['district'] ?? (int)($area['district_id'] ?? 0));
$statusForForm     = (string)($_POST['status'] ?? (string)($area['area_status'] ?? 'available'));

$titleForForm = (string)($_POST['title'] ?? (string)($area['area_name'] ?? ''));
$priceForForm = (string)($_POST['price'] ?? (string)($area['price_per_year'] ?? ''));
$depositForForm = (string)($_POST['deposit_percent'] ?? (string)($area['deposit_percent'] ?? '10'));

$raiForForm  = (string)($_POST['area_rai'] ?? (string)$defaultRai);
$nganForForm = (string)($_POST['area_ngan'] ?? (string)$defaultNgan);
$sqwaForForm = (string)($_POST['area_sqwa'] ?? (string)$defaultSqwa);

$backHref = $isAdmin ? '?page=admin_dashboard' : '?page=my_properties';
?>
<link rel="stylesheet" href="/css/pages/properties/add_property.css">

<div class="add-property-container" data-area-id="<?= (int)$areaId; ?>">
  <div class="form-header">
    <a href="<?= e($backHref); ?>" class="btn-back"><?= $isAdmin ? '← กลับแดชบอร์ดแอดมิน' : '← กลับรายการของฉัน'; ?></a>
    <h1>แก้ไขรายการปล่อยเช่า</h1>
    <p class="form-subtitle">แก้ไขข้อมูลพื้นที่ของคุณ</p>
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
          <input id="title" name="title" type="text" required maxlength="255"
            value="<?= e($titleForForm); ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="province">จังหวัด <span class="required">*</span></label>
          <select id="province" name="province" required>
            <option value="">-- เลือกจังหวัด --</option>
            <?php foreach ($provinces as $prov): ?>
              <option
                value="<?= e((string)($prov['province_id'] ?? '')); ?>"
                data-name="<?= e((string)($prov['province_name'] ?? '')); ?>"
                <?= $provinceIdForForm === (int)($prov['province_id'] ?? 0) ? 'selected' : ''; ?>>
                <?= e((string)($prov['province_name'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="district">อำเภอ <span class="required">*</span></label>
          <select id="district" name="district" required <?= $provinceIdForForm > 0 ? '' : 'disabled'; ?>>
            <option value="">เลือกจังหวัดก่อน</option>
            <?php foreach ($districts as $dist): ?>
              <option
                value="<?= e((string)($dist['district_id'] ?? '')); ?>"
                data-province-id="<?= e((string)($dist['province_id'] ?? '')); ?>"
                <?= $districtIdForForm === (int)($dist['district_id'] ?? 0) ? 'selected' : ''; ?>>
                <?= e((string)($dist['district_name'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="deposit_percent">เปอร์เซ็นต์มัดจำ (%) <span class="required">*</span></label>
          <input id="deposit_percent" name="deposit_percent" type="number" min="0" max="100" step="0.01" required
            value="<?= e($depositForForm); ?>">
          <small class="text-note">ระบุได้ 0 - 100%</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ขนาดพื้นที่</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="area_rai">ไร่</label>
          <input id="area_rai" name="area_rai" type="number" min="0" value="<?= e($raiForForm); ?>">
        </div>
        <div class="form-group">
          <label for="area_ngan">งาน</label>
          <input id="area_ngan" name="area_ngan" type="number" min="0" max="99" value="<?= e($nganForForm); ?>">
        </div>
        <div class="form-group">
          <label for="area_sqwa">ตารางวา</label>
          <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99" value="<?= e($sqwaForForm); ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2 class="section-title">ราคาและสถานะ</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
          <input id="price" name="price" type="number" min="0" max="<?= e((string)MAX_PRICE_PER_YEAR); ?>" step="0.01" required
            value="<?= e($priceForForm); ?>">
        </div>

        <div class="form-group">
          <label for="status">สถานะ</label>
          <select id="status" name="status">
            <option value="available" <?= $statusForForm === 'available' ? 'selected' : ''; ?>>พร้อมให้เช่า</option>
            <option value="booked" <?= $statusForForm === 'booked' ? 'selected' : ''; ?>>ติดจอง</option>
            <option value="unavailable" <?= $statusForForm === 'unavailable' ? 'selected' : ''; ?>>ปิดให้เช่า</option>
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
              <img src="<?= e((string)$img['image_url']); ?>" alt="รูปภาพเดิมของพื้นที่">
              <button
                type="button"
                class="remove-existing-image js-remove-existing"
                data-image-id="<?= (int)$img['image_id']; ?>"
                aria-label="ลบรูปภาพ">
                ×
              </button>
            </div>
          <?php endforeach; ?>
        </div>
        <small class="text-note">ลบรูปภาพได้</small>
      </div>
    <?php endif; ?>

    <div class="form-section">
      <h2 class="section-title">เพิ่มรูปภาพ (รวมทั้งหมดไม่เกิน <?= (int)MAX_IMAGES; ?> รูป)</h2>

      <div class="upload-area">
        <input id="images" name="images[]" type="file" multiple accept="image/*" style="display:none;">
        <label for="images" class="upload-label">
          <div class="upload-text">คลิกเพื่อเลือกรูปภาพ</div>
          <div class="upload-hint">ไฟล์ละไม่เกิน <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024); ?>MB</div>
        </label>
      </div>

      <div id="imagePreview" class="image-preview-grid"></div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
      <a href="<?= e($backHref); ?>" class="btn-cancel">ยกเลิก</a>
    </div>
  </form>
</div>