<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/ImageService.php';

app_session_start();

$user = current_user();
if ($user === null) {
    flash('error', 'กรุณาเข้าสู่ระบบก่อนเพิ่มรายการพื้นที่');
    redirect('?page=signin');
}

$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('add_property_invalid_user', ['session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
}

$errors = [];

// รายชื่อจังหวัด
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_require(); // ✅ CSRF ทุก POST

    $title        = trim((string)($_POST['title'] ?? ''));
    $category     = trim((string)($_POST['category'] ?? ''));
    $location     = trim((string)($_POST['location'] ?? ''));
    $province     = trim((string)($_POST['province'] ?? ''));
    $area         = trim((string)($_POST['area'] ?? ''));
    $area_rai     = max(0, (int)($_POST['area_rai'] ?? 0));
    $area_ngan    = max(0, (int)($_POST['area_ngan'] ?? 0));
    $area_sqwa    = max(0, (int)($_POST['area_sqwa'] ?? 0));
    $priceRaw     = trim((string)($_POST['price'] ?? ''));
    $description  = trim((string)($_POST['description'] ?? ''));
    $soil_type    = trim((string)($_POST['soil_type'] ?? ''));
    $irrigation   = trim((string)($_POST['irrigation'] ?? ''));
    $has_water    = isset($_POST['has_water']) ? 1 : 0;
    $has_electric = isset($_POST['has_electric']) ? 1 : 0;

    if ($title === '')      $errors[] = 'กรุณากรอกชื่อพื้นที่';
    if ($location === '')   $errors[] = 'กรุณากรอกที่ตั้ง';
    if ($province === '')   $errors[] = 'กรุณาเลือกจังหวัด';
    if ($description === '') $errors[] = 'กรุณากรอกรายละเอียดเพิ่มเติมของพื้นที่';

    $price = 0.0;
    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        $errors[] = 'กรุณากรอกราคาที่ถูกต้อง';
    } else {
        $price = (float)$priceRaw;
        if ($price < 0) $errors[] = 'ราคาต้องไม่ติดลบ';
    }

    // upload
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $imageCount = count($_FILES['images']['name']);
        if ($imageCount > 10) {
            $errors[] = 'สามารถอัปโหลดรูปภาพได้สูงสุด 10 รูป';
        } else {
            $projectRoot = defined('BASE_PATH')
                ? rtrim((string) BASE_PATH, '/')
                : dirname(__DIR__, 3); // /app/pages -> /sirinat

            $uploadDir = $projectRoot . '/storage/uploads/properties';

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
                    $newName = 'prop_' . $random . '_' . time() . '_' . $i . '.' . $ext;
                    $dest = $uploadDir . '/' . $newName;

                    // ใช้ ImageService เพื่อ resize และ optimize
                    $processed = ImageService::uploadAndProcess(
                        [
                            'tmp_name' => $tmp,
                            'name' => $name,
                            'size' => $size,
                        ],
                        $uploadDir,
                        $newName
                    );

                    if ($processed) {
                        $uploadedImages[] = '/storage/uploads/properties/' . $newName;
                    } else {
                        // Fallback: อัปโหลดแบบเดิมถ้า ImageService ล้มเหลว
                        if (move_uploaded_file($tmp, $dest)) {
                            $uploadedImages[] = '/storage/uploads/properties/' . $newName;
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
            Database::transaction(function ($pdo) use (
                $userId,
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
                $uploadedImages
            ) {
                $mainImage = $uploadedImages[0] ?? null;

                Database::execute(
                    'INSERT INTO properties
                        (owner_id,title,category,location,province,area,area_rai,area_ngan,area_sqwa,price,description,soil_type,irrigation,has_water,has_electric,main_image,status,created_at)
                     VALUES
                        (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, "available", NOW())',
                    [
                        $userId,
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
                        $mainImage
                    ]
                );

                $propertyId = (int)$pdo->lastInsertId();

                foreach ($uploadedImages as $idx => $url) {
                    Database::execute(
                        'INSERT INTO property_images (property_id,image_url,display_order) VALUES (?,?,?)',
                        [$propertyId, $url, $idx + 1]
                    );
                }
            });

            flash('success', 'เพิ่มรายการพื้นที่เรียบร้อยแล้ว');
            redirect('?page=my_properties');
        } catch (Throwable $e) {
            app_log('add_property_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
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
        <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

        <div class="form-section">
            <h2 class="section-title">ข้อมูลพื้นฐาน</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">ชื่อพื้นที่ <span class="required">*</span></label>
                    <input id="title" name="title" type="text" required value="<?= e($_POST['title'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">ประเภทพื้นที่</label>
                    <select id="category" name="category">
                        <option value="">-- เลือกประเภท --</option>
                        <?php $oldCat = $_POST['category'] ?? ''; ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $oldCat === $cat ? 'selected' : ''; ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="province">จังหวัด <span class="required">*</span></label>
                    <select id="province" name="province" required>
                        <option value="">-- เลือกจังหวัด --</option>
                        <?php $oldProv = $_POST['province'] ?? ''; ?>
                        <?php foreach ($thaiProvinces as $prov): ?>
                            <option value="<?= e($prov) ?>" <?= $oldProv === $prov ? 'selected' : ''; ?>><?= e($prov) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">ที่ตั้ง/ทำเล <span class="required">*</span></label>
                    <input id="location" name="location" type="text" required value="<?= e($_POST['location'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">ขนาดพื้นที่</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="area_rai">ไร่</label>
                    <input id="area_rai" name="area_rai" type="number" min="0" value="<?= e((string)($_POST['area_rai'] ?? 0)) ?>">
                </div>
                <div class="form-group">
                    <label for="area_ngan">งาน</label>
                    <input id="area_ngan" name="area_ngan" type="number" min="0" max="3" value="<?= e((string)($_POST['area_ngan'] ?? 0)) ?>">
                </div>
                <div class="form-group">
                    <label for="area_sqwa">ตารางวา</label>
                    <input id="area_sqwa" name="area_sqwa" type="number" min="0" max="99" value="<?= e((string)($_POST['area_sqwa'] ?? 0)) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="area">หรือระบุเป็น</label>
                    <input id="area" name="area" type="text" value="<?= e($_POST['area'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">รายละเอียดพื้นที่</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="soil_type">ประเภทดิน</label>
                    <input id="soil_type" name="soil_type" type="text" value="<?= e($_POST['soil_type'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="irrigation">ระบบชลประทาน</label>
                    <input id="irrigation" name="irrigation" type="text" value="<?= e($_POST['irrigation'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group checkbox-group">
                    <label><input type="checkbox" name="has_water" value="1" <?= isset($_POST['has_water']) ? 'checked' : ''; ?>> มีน้ำพร้อมใช้</label>
                    <label><input type="checkbox" name="has_electric" value="1" <?= isset($_POST['has_electric']) ? 'checked' : ''; ?>> มีไฟฟ้าพร้อมใช้</label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">ราคาและรายละเอียด</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
                    <input id="price" name="price" type="number" min="0" step="0.01" required value="<?= e($_POST['price'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="description">รายละเอียดเพิ่มเติม <span class="required">*</span></label>
                    <textarea id="description" name="description" rows="6" required><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">รูปภาพพื้นที่ (สูงสุด 10 รูป)</h2>

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
            <button type="submit" class="btn-submit">เพิ่มรายการ</button>
            <a href="?page=my_properties" class="btn-cancel">ยกเลิก</a>
        </div>
    </form>
</div>

<script>
    (function() {
        'use strict';
        let selectedFiles = [];

        window.previewImages = function(event) {
            const files = Array.prototype.slice.call(event.target.files || []);
            const preview = document.getElementById('imagePreview');
            if (!preview) return;

            if (selectedFiles.length + files.length > 10) {
                alert('สามารถอัปโหลดรูปภาพได้สูงสุด 10 รูป');
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
    })();
</script>