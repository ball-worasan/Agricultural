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
    flash('error', 'กรุณาเข้าสู่ระบบก่อนแก้ไขรายการพื้นที่');
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('edit_property_invalid_user', ['session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
}

// รับ property id
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($propertyId <= 0) {
    flash('error', 'ไม่พบรายการพื้นที่ที่ต้องการแก้ไข');
    redirect('?page=my_properties');
}

$errors  = [];
$success = false;

// รายชื่อจังหวัด (share กับหน้าอื่นได้)
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

$categories = [
    'ไร่นา',
    'สวนผลไม้',
    'แปลงผัก',
    'เลี้ยงสัตว์',
    'สวนผสม',
    'อื่นๆ',
];

$allowedStatuses = [
    'available',
    'booked',
    'sold',
];

// ดึงข้อมูลพื้นที่ของ user เอง
try {
    $property = Database::fetchOne(
        'SELECT * FROM properties WHERE id = ? AND owner_id = ? LIMIT 1',
        [$propertyId, $userId]
    );

    if (!$property) {
        flash('error', 'ไม่พบรายการพื้นที่ของคุณ');
        redirect('?page=my_properties');
    }
} catch (Throwable $e) {
    app_log('edit_property_fetch_error', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'error'       => $e->getMessage(),
    ]);
    flash('error', 'ไม่สามารถโหลดข้อมูลพื้นที่ได้ กรุณาลองใหม่อีกครั้ง');
    redirect('?page=my_properties');
}

// ดึงรูปภาพที่มีอยู่
try {
    $existingImages = Database::fetchAll(
        'SELECT * FROM property_images WHERE property_id = ? ORDER BY display_order',
        [$propertyId]
    );
} catch (Throwable $e) {
    app_log('edit_property_fetch_images_error', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'error'       => $e->getMessage(),
    ]);
    $existingImages = [];
}

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim((string) ($_POST['title'] ?? $property['title'] ?? ''));
    $category     = trim((string) ($_POST['category'] ?? $property['category'] ?? ''));
    $location     = trim((string) ($_POST['location'] ?? $property['location'] ?? ''));
    $province     = trim((string) ($_POST['province'] ?? $property['province'] ?? ''));
    $area         = trim((string) ($_POST['area'] ?? $property['area'] ?? ''));
    $area_rai     = max(0, (int) ($_POST['area_rai'] ?? $property['area_rai'] ?? 0));
    $area_ngan    = max(0, (int) ($_POST['area_ngan'] ?? $property['area_ngan'] ?? 0));
    $area_sqwa    = max(0, (int) ($_POST['area_sqwa'] ?? $property['area_sqwa'] ?? 0));
    $priceRaw     = trim((string) ($_POST['price'] ?? (string) ($property['price'] ?? '')));
    $description  = trim((string) ($_POST['description'] ?? $property['description'] ?? ''));
    $soil_type    = trim((string) ($_POST['soil_type'] ?? $property['soil_type'] ?? ''));
    $irrigation   = trim((string) ($_POST['irrigation'] ?? $property['irrigation'] ?? ''));
    $has_water    = isset($_POST['has_water']) ? 1 : (int) ($property['has_water'] ?? 0);
    $has_electric = isset($_POST['has_electric']) ? 1 : (int) ($property['has_electric'] ?? 0);
    $status       = trim((string) ($_POST['status'] ?? $property['status'] ?? 'available'));

    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'available';
    }

    // Validation
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

    // จัดการรูปภาพใหม่
    $uploadedImages = [];

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $imageCount    = count($_FILES['images']['name']);
        $existingCount = count($existingImages);

        if ($existingCount + $imageCount > 10) {
            $errors[] = 'สามารถมีรูปภาพได้สูงสุด 10 รูป (ปัจจุบันมี ' . $existingCount . ' รูป)';
        } else {
            // ใช้ storage/uploads/properties เป็นที่เก็บไฟล์จริง
            if (defined('BASE_PATH')) {
                $uploadDir = BASE_PATH . '/storage/uploads/properties/';
            } else {
                // fallback ถ้าถูกรันเดี่ยว ๆ
                $uploadDir = dirname(__DIR__, 3) . '/storage/uploads/properties/';
            }

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    app_log('edit_property_upload_mkdir_failed', [
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

                    if ($fileSize > 5 * 1024 * 1024) {
                        $errors[] = "รูปภาพ {$originalName} มีขนาดใหญ่เกิน 5MB";
                        continue;
                    }

                    $fileType = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($fileType, $allowedTypes, true)) {
                        $errors[] = "รูปภาพ {$originalName} ไม่ใช่ไฟล์รูปภาพที่รองรับ";
                        continue;
                    }

                    if (!is_uploaded_file($tmpName)) {
                        $errors[] = "ไฟล์ {$originalName} ไม่ได้ถูกอัปโหลดผ่านฟอร์มอย่างถูกต้อง";
                        continue;
                    }

                    $newFileName = uniqid('prop_', true) . '_' . time() . '_' . $i . '.' . $fileType;
                    $destination = $uploadDir . $newFileName;

                    if (move_uploaded_file($tmpName, $destination)) {
                        // เสิร์ฟผ่าน /storage/... (มี symlink จาก public → storage)
                        $uploadedImages[] = '/storage/uploads/properties/' . $newFileName;
                    } else {
                        $errors[] = "ไม่สามารถอัปโหลดรูปภาพ {$originalName} ได้";
                    }
                }
            }
        }
    }

    // ถ้าไม่มี error ให้อัปเดตข้อมูล
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
                // หา main_image (ถ้ามีรูปใหม่ใช้รูปแรก ไม่งั้นใช้รูปเดิม)
                $mainImage = null;
                if (!empty($uploadedImages)) {
                    $mainImage = $uploadedImages[0];
                } elseif (!empty($existingImages)) {
                    $mainImage = $existingImages[0]['image_url'] ?? null;
                }

                Database::execute(
                    '
                    UPDATE properties 
                    SET title = ?, 
                        category = ?, 
                        location = ?, 
                        province = ?, 
                        area = ?, 
                        area_rai = ?, 
                        area_ngan = ?, 
                        area_sqwa = ?, 
                        price = ?, 
                        description = ?, 
                        soil_type = ?, 
                        irrigation = ?, 
                        has_water = ?, 
                        has_electric = ?, 
                        main_image = ?, 
                        status = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                    ',
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
                        $propertyId,
                    ]
                );

                // เพิ่มรูปภาพใหม่
                $nextOrder = count($existingImages) + 1;
                foreach ($uploadedImages as $imageUrl) {
                    Database::execute(
                        '
                        INSERT INTO property_images (property_id, image_url, display_order)
                        VALUES (?, ?, ?)
                        ',
                        [$propertyId, $imageUrl, $nextOrder++]
                    );
                }
            });

            $success = true;

            app_log('edit_property_success', [
                'user_id'     => $userId,
                'property_id' => $propertyId,
            ]);

            flash('success', 'บันทึกการแก้ไขรายการพื้นที่เรียบร้อยแล้ว');
            redirect('?page=my_properties&msg=updated');
        } catch (Throwable $e) {
            app_log('edit_property_update_error', [
                'user_id'     => $userId,
                'property_id' => $propertyId,
                'error'       => $e->getMessage(),
            ]);
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
        }
    }

    // reload รูปภาพใหม่ในกรณี error (กันกรณีมีการลบ/เพิ่มที่อื่น)
    if (!empty($errors)) {
        try {
            $existingImages = Database::fetchAll(
                'SELECT * FROM property_images WHERE property_id = ? ORDER BY display_order',
                [$propertyId]
            );
        } catch (Throwable $e) {
            $existingImages = [];
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
        <h1>แก้ไขรายการปล่อยเช่า</h1>
        <p class="form-subtitle">แก้ไขข้อมูลพื้นที่ของคุณ</p>
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
                        value="<?= e($_POST['title'] ?? $property['title'] ?? ''); ?>"
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
                        $selectedCategory = $_POST['category'] ?? $property['category'] ?? '';
                        foreach ($categories as $cat):
                        ?>
                            <option
                                value="<?= e($cat); ?>"
                                <?= $selectedCategory === $cat ? 'selected' : ''; ?>><?= e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="province">จังหวัด <span class="required">*</span></label>
                    <select id="province" name="province" required>
                        <option value="">-- เลือกจังหวัด --</option>
                        <?php
                        $selectedProvince = $_POST['province'] ?? $property['province'] ?? '';
                        foreach ($thaiProvinces as $prov):
                        ?>
                            <option
                                value="<?= e($prov); ?>"
                                <?= $selectedProvince === $prov ? 'selected' : ''; ?>><?= e($prov); ?></option>
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
                        value="<?= e($_POST['location'] ?? $property['location'] ?? ''); ?>"
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
                        value="<?= e((string) ($_POST['area_rai'] ?? $property['area_rai'] ?? 0)); ?>"
                        min="0"
                        placeholder="0">
                </div>
                <div class="form-group">
                    <label for="area_ngan">งาน</label>
                    <input
                        type="number"
                        id="area_ngan"
                        name="area_ngan"
                        value="<?= e((string) ($_POST['area_ngan'] ?? $property['area_ngan'] ?? 0)); ?>"
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
                        value="<?= e((string) ($_POST['area_sqwa'] ?? $property['area_sqwa'] ?? 0)); ?>"
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
                        value="<?= e($_POST['area'] ?? $property['area'] ?? ''); ?>"
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
                        value="<?= e($_POST['soil_type'] ?? $property['soil_type'] ?? ''); ?>"
                        placeholder="เช่น ดินร่วน, ดินเหนียว, ดินทราย">
                </div>
                <div class="form-group">
                    <label for="irrigation">ระบบชลประทาน</label>
                    <input
                        type="text"
                        id="irrigation"
                        name="irrigation"
                        value="<?= e($_POST['irrigation'] ?? $property['irrigation'] ?? ''); ?>"
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
                            <?= (isset($_POST['has_water']) || !empty($property['has_water'])) ? 'checked' : ''; ?>>
                        มีน้ำพร้อมใช้
                    </label>
                    <label>
                        <input
                            type="checkbox"
                            name="has_electric"
                            value="1"
                            <?= (isset($_POST['has_electric']) || !empty($property['has_electric'])) ? 'checked' : ''; ?>>
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
                        value="<?= e($_POST['price'] ?? (string) ($property['price'] ?? '')); ?>"
                        min="0"
                        step="0.01"
                        placeholder="เช่น 50000"
                        required>
                </div>
                <div class="form-group">
                    <label for="status">สถานะ</label>
                    <select id="status" name="status">
                        <?php
                        $statuses = [
                            'available' => 'ว่าง',
                            'booked'    => 'ติดจอง',
                            'sold'      => 'ขายแล้ว',
                        ];
                        $selectedStatus = $_POST['status'] ?? $property['status'] ?? 'available';
                        foreach ($statuses as $value => $label):
                        ?>
                            <option
                                value="<?= e($value); ?>"
                                <?= $selectedStatus === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                        required><?= e($_POST['description'] ?? $property['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- รูปภาพที่มีอยู่ -->
        <?php if (!empty($existingImages)): ?>
            <div class="form-section">
                <h2 class="section-title">รูปภาพที่มีอยู่ (<?= count($existingImages); ?> รูป)</h2>
                <div class="existing-images-grid">
                    <?php foreach ($existingImages as $img): ?>
                        <div class="existing-image-item" data-image-id="<?= (int) $img['id']; ?>">
                            <img src="<?= e($img['image_url']); ?>" alt="Property Image">
                            <button
                                type="button"
                                class="remove-existing-image"
                                onclick="removeExistingImage(<?= (int) $img['id']; ?>)">
                                ×
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- อัปโหลดรูปภาพใหม่ -->
        <div class="form-section">
            <h2 class="section-title">เพิ่มรูปภาพ (สูงสุด 10 รูปรวมกับรูปเดิม)</h2>

            <div class="upload-area">
                <input
                    type="file"
                    id="images"
                    name="images[]"
                    multiple
                    accept="image/*,image/heif,image/heic"
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
            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
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

            const existingCount = document.querySelectorAll('.existing-image-item').length;
            const totalCount = existingCount + selectedFiles.length + files.length;

            if (totalCount > 10) {
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
            updateFileInput();
        };

        window.removeImage = function(index) {
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }

            selectedFiles.splice(index, 1);
            rebuildPreview();
            updateFileInput();
        };

        function rebuildPreview() {
            const previewContainer = document.getElementById('imagePreview');
            if (!previewContainer) return;

            previewContainer.innerHTML = '';

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
        }

        function updateFileInput() {
            const input = document.getElementById('images');
            if (!input) return;

            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
        }

        window.removeExistingImage = function(imageId) {
            if (!confirm('คุณต้องการลบรูปภาพนี้ใช่หรือไม่?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_image');
            formData.append('image_id', String(imageId));
            formData.append('property_id', String(<?= (int) $propertyId; ?>));

            fetch('?page=delete_property_image', {
                    method: 'POST',
                    body: formData
                })
                .then(async function(response) {
                    const rawText = await response.text();

                    let data;
                    try {
                        data = rawText ? JSON.parse(rawText) : null;
                    } catch (e) {
                        console.error('delete_property_image: raw response is not valid JSON:', rawText);
                        throw new Error('INVALID_JSON');
                    }

                    if (!data) {
                        throw new Error('EMPTY_RESPONSE');
                    }

                    if (!response.ok || !data.success) {
                        const msg = data.message || 'ไม่สามารถลบรูปภาพได้';
                        throw new Error(msg);
                    }

                    const imageItem = document.querySelector('[data-image-id="' + imageId + '"]');
                    if (imageItem && imageItem.parentNode) {
                        imageItem.parentNode.removeChild(imageItem);
                    }

                    alert(data.message || 'ลบรูปภาพสำเร็จ');
                })
                .catch(function(error) {
                    console.error('removeExistingImage error:', error);
                    alert('เกิดข้อผิดพลาดในการลบรูปภาพ: ' + (error.message || 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'));
                });
        };
    })();
</script>