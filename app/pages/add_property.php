<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้งกรณีถูก include ผ่าน index.php และถูกเปิดตรง ๆ (dev)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2)); // จาก /app/public/pages → /app
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ตรวจสอบการล็อกอินด้วย helper กลาง
$user = current_user();
if ($user === null) {
    flash('error', 'กรุณาเข้าสู่ระบบก่อนเพิ่มรายการพื้นที่');
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('add_property_invalid_user', ['session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
}

$errors  = [];
$success = false;

// รายชื่อจังหวัด (ใช้ร่วมกับหน้าอื่น)
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

// category ที่ระบบรองรับ
$categories = [
    'ไร่นา',
    'สวนผลไม้',
    'แปลงผัก',
    'เลี้ยงสัตว์',
    'สวนผสม',
    'อื่นๆ',
];

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // เก็บ input ไว้เผื่ออยากใช้ old() แบบ PRG ในอนาคต
    // ตอนนี้เรายัง render หน้าเดิมตรง ๆ เลยใช้ $_POST ได้อยู่
    $title        = trim((string) ($_POST['title'] ?? ''));
    $category     = trim((string) ($_POST['category'] ?? ''));
    $location     = trim((string) ($_POST['location'] ?? ''));
    $province     = trim((string) ($_POST['province'] ?? ''));
    $area         = trim((string) ($_POST['area'] ?? ''));
    $area_rai     = max(0, (int) ($_POST['area_rai'] ?? 0));
    $area_ngan    = max(0, (int) ($_POST['area_ngan'] ?? 0));
    $area_sqwa    = max(0, (int) ($_POST['area_sqwa'] ?? 0));
    $priceRaw     = trim((string) ($_POST['price'] ?? ''));
    $description  = trim((string) ($_POST['description'] ?? ''));
    $soil_type    = trim((string) ($_POST['soil_type'] ?? ''));
    $irrigation   = trim((string) ($_POST['irrigation'] ?? ''));
    $has_water    = isset($_POST['has_water']) ? 1 : 0;
    $has_electric = isset($_POST['has_electric']) ? 1 : 0;

    // Validation พื้นฐาน
    if ($title === '') {
        $errors[] = 'กรุณากรอกชื่อพื้นที่';
    }

    if ($location === '') {
        $errors[] = 'กรุณากรอกที่ตั้ง';
    }

    if ($province === '') {
        $errors[] = 'กรุณาเลือกจังหวัด';
    }

    $price = 0.0;
    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        $errors[] = 'กรุณากรอกราคาที่ถูกต้อง';
    } else {
        $price = (float) $priceRaw;
        if ($price < 0) {
            $errors[] = 'ราคาต้องไม่ติดลบ';
        }
    }

    if ($description === '') {
        $errors[] = 'กรุณากรอกรายละเอียดเพิ่มเติมของพื้นที่';
    }

    // ตรวจสอบรูปภาพ
    $uploadedImages = [];

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $imageCount = count($_FILES['images']['name']);

        if ($imageCount > 10) {
            $errors[] = 'สามารถอัปโหลดรูปภาพได้สูงสุด 10 รูป';
        } else {
            // ใช้ storage/uploads/properties เป็นที่เก็บไฟล์จริง
            if (defined('BASE_PATH')) {
                $uploadDir = BASE_PATH . '/storage/uploads/properties/';
            } else {
                // fallback ถ้าไฟล์นี้ถูกรันเดี่ยว ๆ (เดา project root จากโครงสร้าง)
                $uploadDir = dirname(__DIR__, 3) . '/storage/uploads/properties/';
            }

            // พยายามสร้างโฟลเดอร์ (ถ้ายังไม่มี)
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    app_log('upload_mkdir_failed', [
                        'dir'   => $uploadDir,
                        'error' => error_get_last(),
                    ]);
                    $errors[] = 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดรูปภาพได้ (โปรดแจ้งผู้ดูแลระบบ)';
                }
            }

            if (empty($errors)) {
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heif', 'heic'];

                for ($i = 0; $i < $imageCount; $i++) {
                    $errorCode = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

                    if ($errorCode === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($errorCode !== UPLOAD_ERR_OK) {
                        $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพบางไฟล์';
                        continue;
                    }

                    $tmpName      = $_FILES['images']['tmp_name'][$i] ?? '';
                    $originalName = $_FILES['images']['name'][$i] ?? '';
                    $fileSize     = (int) ($_FILES['images']['size'][$i] ?? 0);

                    // ตรวจสอบขนาดไฟล์ (สูงสุด 5MB)
                    if ($fileSize > 5 * 1024 * 1024) {
                        $errors[] = "รูปภาพ {$originalName} มีขนาดใหญ่เกิน 5MB";
                        continue;
                    }

                    // ตรวจสอบประเภทไฟล์จากนามสกุล
                    $fileType = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($fileType, $allowedTypes, true)) {
                        $errors[] = "รูปภาพ {$originalName} ไม่ใช่ไฟล์รูปภาพที่รองรับ";
                        continue;
                    }

                    // สร้างชื่อไฟล์ใหม่
                    $newFileName = uniqid('prop_', true) . '_' . time() . '_' . $i . '.' . $fileType;
                    $destination = $uploadDir . $newFileName;

                    if (!is_uploaded_file($tmpName)) {
                        $errors[] = "ไฟล์ {$originalName} ไม่ได้ถูกอัปโหลดผ่านฟอร์มอย่างถูกต้อง";
                        continue;
                    }

                    if (move_uploaded_file($tmpName, $destination)) {
                        // path ที่เก็บลง DB (เอาไว้ให้ front ใช้ src)
                        $uploadedImages[] = '/storage/uploads/properties/' . $newFileName;
                    } else {
                        $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$originalName} ได้";
                    }
                }
            }
        }
    }

    // ถ้าไม่มี error ให้บันทึกข้อมูล
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
                    '
                    INSERT INTO properties 
                        (owner_id, title, category, location, province, area, area_rai, area_ngan, area_sqwa, price, description, soil_type, irrigation, has_water, has_electric, main_image, status, created_at)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "available", NOW())
                    ',
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
                        $mainImage,
                    ]
                );

                $propertyId = (int) $pdo->lastInsertId();

                // บันทึกรูปภาพทั้งหมด
                foreach ($uploadedImages as $index => $imageUrl) {
                    Database::execute(
                        '
                        INSERT INTO property_images (property_id, image_url, display_order)
                        VALUES (?, ?, ?)
                        ',
                        [$propertyId, $imageUrl, $index + 1]
                    );
                }
            });

            $success = true;

            app_log('add_property_success', [
                'user_id' => $userId,
                'title'   => $title,
            ]);

            flash('success', 'เพิ่มรายการพื้นที่เรียบร้อยแล้ว');
            redirect('?page=my_properties');
        } catch (Throwable $e) {
            app_log('add_property_error', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>

<div class="add-property-container">
    <div class="form-header">
        <a href="?page=my_properties" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>กลับรายการของฉัน</span>
        </a>
        <h1>เพิ่มรายการปล่อยเช่า</h1>
        <p class="form-subtitle">กรอกข้อมูลพื้นที่ที่ต้องการปล่อยเช่า</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>พบข้อผิดพลาด:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="property-form">
        <!-- ข้อมูลพื้นฐาน -->
        <div class="form-section">
            <h2 class="section-title">ข้อมูลพื้นฐาน</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">ชื่อพื้นที่ <span class="required">*</span></label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= e($_POST['title'] ?? ''); ?>"
                        placeholder="เช่น ไร่นาสวยงาม 5 ไร่ ใกล้แม่น้ำ"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">ประเภทพื้นที่</label>
                    <select id="category" name="category">
                        <option value="">-- เลือกประเภท --</option>
                        <?php
                        $oldCategory = $_POST['category'] ?? '';
                        foreach ($categories as $cat):
                        ?>
                            <option
                                value="<?= e($cat); ?>"
                                <?= $oldCategory === $cat ? 'selected' : ''; ?>><?= e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="province">จังหวัด <span class="required">*</span></label>
                    <select id="province" name="province" required>
                        <option value="">-- เลือกจังหวัด --</option>
                        <?php
                        $oldProvince = $_POST['province'] ?? '';
                        foreach ($thaiProvinces as $prov):
                        ?>
                            <option
                                value="<?= e($prov); ?>"
                                <?= $oldProvince === $prov ? 'selected' : ''; ?>><?= e($prov); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">ที่ตั้ง/ทำเล <span class="required">*</span></label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="<?= e($_POST['location'] ?? ''); ?>"
                        placeholder="เช่น ต.บางกะดี อ.เมือง"
                        required>
                </div>
            </div>
        </div>

        <!-- ขนาดพื้นที่ -->
        <div class="form-section">
            <h2 class="section-title">ขนาดพื้นที่</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="area_rai">ไร่</label>
                    <input
                        type="number"
                        id="area_rai"
                        name="area_rai"
                        value="<?= e((string) ($_POST['area_rai'] ?? 0)); ?>"
                        min="0"
                        placeholder="0">
                </div>
                <div class="form-group">
                    <label for="area_ngan">งาน</label>
                    <input
                        type="number"
                        id="area_ngan"
                        name="area_ngan"
                        value="<?= e((string) ($_POST['area_ngan'] ?? 0)); ?>"
                        min="0"
                        max="3"
                        placeholder="0">
                </div>
                <div class="form-group">
                    <label for="area_sqwa">ตารางวา</label>
                    <input
                        type="number"
                        id="area_sqwa"
                        name="area_sqwa"
                        value="<?= e((string) ($_POST['area_sqwa'] ?? 0)); ?>"
                        min="0"
                        max="99"
                        placeholder="0">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="area">หรือระบุเป็น (ตัวอย่าง: 5-2-50 ไร่)</label>
                    <input
                        type="text"
                        id="area"
                        name="area"
                        value="<?= e($_POST['area'] ?? ''); ?>"
                        placeholder="เช่น 5 ไร่ หรือ 2-1-30 ไร่">
                </div>
            </div>
        </div>

        <!-- รายละเอียดเพิ่มเติม -->
        <div class="form-section">
            <h2 class="section-title">รายละเอียดพื้นที่</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="soil_type">ประเภทดิน</label>
                    <input
                        type="text"
                        id="soil_type"
                        name="soil_type"
                        value="<?= e($_POST['soil_type'] ?? ''); ?>"
                        placeholder="เช่น ดินร่วน, ดินเหนียว, ดินทราย">
                </div>
                <div class="form-group">
                    <label for="irrigation">ระบบชลประทาน</label>
                    <input
                        type="text"
                        id="irrigation"
                        name="irrigation"
                        value="<?= e($_POST['irrigation'] ?? ''); ?>"
                        placeholder="เช่น มีระบบสปริงเกลอร์, น้ำบ่อบาดาล">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group checkbox-group">
                    <label>
                        <input
                            type="checkbox"
                            name="has_water"
                            value="1"
                            <?= isset($_POST['has_water']) ? 'checked' : ''; ?>>
                        มีน้ำพร้อมใช้
                    </label>
                    <label>
                        <input
                            type="checkbox"
                            name="has_electric"
                            value="1"
                            <?= isset($_POST['has_electric']) ? 'checked' : ''; ?>>
                        มีไฟฟ้าพร้อมใช้
                    </label>
                </div>
            </div>
        </div>

        <!-- ราคาและรายละเอียด -->
        <div class="form-section">
            <h2 class="section-title">ราคาและรายละเอียด</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">ราคาเช่าต่อปี (บาท) <span class="required">*</span></label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        value="<?= e($_POST['price'] ?? ''); ?>"
                        min="0"
                        step="0.01"
                        placeholder="เช่น 50000"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="description">รายละเอียดเพิ่มเติม <span class="required">*</span></label>
                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        placeholder="อธิบายรายละเอียดพื้นที่, สภาพแวดล้อม, ข้อดี, เงื่อนไขการเช่า ฯลฯ"
                        required><?= e($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- อัปโหลดรูปภาพ -->
        <div class="form-section">
            <h2 class="section-title">รูปภาพพื้นที่ (สูงสุด 10 รูป)</h2>

            <div class="upload-area">
                <input
                    type="file"
                    id="images"
                    name="images[]"
                    multiple
                    accept="image/*"
                    onchange="previewImages(event)"
                    style="display: none;">
                <label for="images" class="upload-label">
                    <svg class="upload-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <div class="upload-text">คลิกเพื่อเลือกรูปภาพ</div>
                    <div class="upload-hint">รองรับ JPG, PNG, GIF, WebP, HEIF (ไฟล์ละไม่เกิน 5MB)</div>
                </label>
            </div>

            <div id="imagePreview" class="image-preview-grid"></div>
        </div>

        <!-- ปุ่มส่งฟอร์ม -->
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
            const previewContainer = document.getElementById('imagePreview');

            if (!previewContainer) return;

            // จำกัดไม่เกิน 10 รูป
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
                const fileIndex = selectedFiles.length - 1;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML =
                        '<img src="' + e.target.result + '" alt="Preview">' +
                        '<button type="button" class="remove-image" data-index="' + fileIndex + '">×</button>';
                    previewContainer.appendChild(div);

                    const btn = div.querySelector('.remove-image');
                    if (btn) {
                        btn.addEventListener('click', function() {
                            const idx = parseInt(btn.getAttribute('data-index') || '0', 10);
                            window.removeImage(idx);
                        });
                    }
                };
                reader.readAsDataURL(file);
            });

            updateFileInput();
        };

        window.removeImage = function(index) {
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }

            selectedFiles.splice(index, 1);

            const previewContainer = document.getElementById('imagePreview');
            if (!previewContainer) return;
            previewContainer.innerHTML = '';

            // rebuild preview + index mapping
            selectedFiles.forEach(function(file, idx) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML =
                        '<img src="' + e.target.result + '" alt="Preview">' +
                        '<button type="button" class="remove-image" data-index="' + idx + '">×</button>';
                    previewContainer.appendChild(div);

                    const btn = div.querySelector('.remove-image');
                    if (btn) {
                        btn.addEventListener('click', function() {
                            const btnIdx = parseInt(btn.getAttribute('data-index') || '0', 10);
                            window.removeImage(btnIdx);
                        });
                    }
                };
                reader.readAsDataURL(file);
            });

            updateFileInput();
        };

        function updateFileInput() {
            const input = document.getElementById('images');
            if (!input) return;

            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
        }
    })();
</script>