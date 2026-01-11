<?php

/**
 * ไฟล์ทดสอบ ImageService
 * เข้าถึงผ่าน: http://sirinat.local/test_image_service.php
 */

require_once __DIR__ . '/../app/includes/services/ImageService.php';

// กำหนด BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบ ImageService</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }

        h2 {
            color: #555;
            margin-top: 30px;
        }

        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .status-ok {
            color: #4CAF50;
            font-weight: bold;
        }

        .status-error {
            color: #f44336;
            font-weight: bold;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
        }

        .info-grid dt {
            font-weight: bold;
            color: #666;
        }

        .info-grid dd {
            margin: 0;
        }

        pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border-left: 4px solid #4CAF50;
        }

        .test-form {
            margin: 20px 0;
        }

        .test-form input[type="file"] {
            padding: 10px;
        }

        .test-form button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .test-form button:hover {
            background: #45a049;
        }

        .preview {
            max-width: 300px;
            margin: 10px 0;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <h1>🔧 ทดสอบระบบ ImageService</h1>

    <div class="section">
        <h2>📊 การตรวจสอบระบบ (Diagnostics)</h2>
        <?php
        $diagnostics = ImageService::runDiagnostics();
        ?>
        <dl class="info-grid">
            <dt>PHP Version:</dt>
            <dd><?= htmlspecialchars($diagnostics['php_version']) ?></dd>

            <dt>GD Extension:</dt>
            <dd class="<?= $diagnostics['gd_loaded'] ? 'status-ok' : 'status-error' ?>">
                <?= $diagnostics['gd_loaded'] ? '✓ โหลดแล้ว' : '✗ ไม่พบ' ?>
            </dd>

            <dt>Fileinfo Extension:</dt>
            <dd class="<?= $diagnostics['finfo_available'] ? 'status-ok' : 'status-error' ?>">
                <?= $diagnostics['finfo_available'] ? '✓ พร้อมใช้งาน' : '✗ ไม่พบ' ?>
            </dd>

            <dt>Max Upload Size:</dt>
            <dd><?= htmlspecialchars($diagnostics['max_upload_size']) ?></dd>

            <dt>Max POST Size:</dt>
            <dd><?= htmlspecialchars($diagnostics['max_post_size']) ?></dd>

            <dt>Memory Limit:</dt>
            <dd><?= htmlspecialchars($diagnostics['memory_limit']) ?></dd>
        </dl>
    </div>

    <div class="section">
        <h2>📁 สถานะโฟลเดอร์อัพโหลด</h2>
        <?php foreach ($diagnostics['directories'] as $key => $info): ?>
            <div style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                <strong><?= htmlspecialchars($key) ?>:</strong>
                <span class="<?= $info['status'] === 'ok' ? 'status-ok' : 'status-error' ?>">
                    <?= $info['status'] === 'ok' ? '✓' : '✗' ?>
                </span>
                <br>
                <small style="color: #666;">
                    Path: <?= htmlspecialchars($info['path']) ?><br>
                    Exists: <?= $info['exists'] ? 'Yes' : 'No' ?> |
                    Writable: <?= $info['writable'] ? 'Yes' : 'No' ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>📤 ทดสอบการอัพโหลด</h2>
        <form method="POST" enctype="multipart/form-data" class="test-form">
            <div>
                <label for="test_image">เลือกรูปภาพ (ไม่เกิน 5MB):</label><br>
                <input type="file" id="test_image" name="test_image" accept="image/*" required>
            </div>
            <br>
            <button type="submit" name="test_upload">ทดสอบอัพโหลด</button>
        </form>

        <?php
        if (isset($_POST['test_upload']) && isset($_FILES['test_image'])) {
            echo '<h3>ผลการทดสอบ:</h3>';

            $file = $_FILES['test_image'];
            $uploadDir = BASE_PATH . '/public/storage/uploads/areas';

            // ทดสอบ validation
            $validation = ImageService::validateUpload($file);

            if (!$validation['ok']) {
                echo '<p class="status-error">✗ การตรวจสอบไฟล์ล้มเหลว: ' . htmlspecialchars($validation['message']) . '</p>';
            } else {
                echo '<p class="status-ok">✓ ไฟล์ผ่านการตรวจสอบ</p>';
                echo '<pre>' . print_r($validation, true) . '</pre>';

                // ทดสอบอัพโหลด
                $testFilename = 'test_' . time();
                $result = ImageService::uploadAndProcess(
                    $file,
                    $uploadDir,
                    '/storage/uploads/areas',
                    $testFilename
                );

                if ($result['ok']) {
                    echo '<p class="status-ok">✓ อัพโหลดสำเร็จ!</p>';
                    echo '<p>URL: <a href="' . htmlspecialchars($result['public_path']) . '" target="_blank">' .
                        htmlspecialchars($result['public_path']) . '</a></p>';

                    // แสดงรูปที่อัพโหลด
                    echo '<img src="' . htmlspecialchars($result['public_path']) . '" class="preview" alt="Uploaded image">';

                    // ดึงข้อมูลรูปภาพ
                    $imageInfo = ImageService::getImageInfo($result['public_path']);
                    if ($imageInfo) {
                        echo '<h4>ข้อมูลรูปภาพ:</h4>';
                        echo '<pre>' . print_r($imageInfo, true) . '</pre>';
                    }
                } else {
                    echo '<p class="status-error">✗ อัพโหลดล้มเหลว: ' . htmlspecialchars($result['message'] ?? 'Unknown error') . '</p>';
                }
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>🖼️ รูปภาพที่อัพโหลดไว้</h2>
        <?php
        $uploadDir = BASE_PATH . '/public/storage/uploads/areas';
        if (is_dir($uploadDir)) {
            $files = array_diff(scandir($uploadDir), ['.', '..']);

            if (empty($files)) {
                echo '<p style="color: #999;">ยังไม่มีรูปภาพที่อัพโหลด</p>';
            } else {
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">';
                foreach ($files as $file) {
                    $publicPath = '/storage/uploads/areas/' . $file;
                    $info = ImageService::getImageInfo($publicPath);

                    if ($info) {
                        echo '<div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
                        echo '<img src="' . htmlspecialchars($publicPath) . '" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">';
                        echo '<div style="font-size: 12px; margin-top: 5px;">';
                        echo '<strong>' . htmlspecialchars($file) . '</strong><br>';
                        echo $info['width'] . 'x' . $info['height'] . ' | ';
                        echo number_format($info['size'] / 1024, 1) . ' KB';
                        echo '</div>';
                        echo '</div>';
                    }
                }
                echo '</div>';
            }
        } else {
            echo '<p class="status-error">โฟลเดอร์ uploads/areas ไม่พบ</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>📝 วิธีใช้งาน ImageService</h2>
        <h3>1. อัพโหลดรูปภาพ:</h3>
        <pre><?php echo htmlspecialchars(
                    <<<'PHP'
$result = ImageService::uploadAndProcess(
    $_FILES['image'],              // ไฟล์จากฟอร์ม
    '/path/to/upload/directory',   // โฟลเดอร์ที่จะเก็บไฟล์
    '/storage/uploads/areas',       // URL path สำหรับเข้าถึง
    'my_image_name'                 // ชื่อไฟล์ (ไม่ต้องใส่ .jpg)
);

if ($result['ok']) {
    $imageUrl = $result['public_path'];  // เช่น /storage/uploads/areas/my_image_name.jpg
    // บันทึก $imageUrl ลงฐานข้อมูล
}
PHP
                ); ?></pre>

        <h3>2. ดึงรูปภาพมาแสดง:</h3>
        <pre><?php echo htmlspecialchars(
                    <<<'PHP'
// ใน HTML/PHP
$imageUrl = '/storage/uploads/areas/my_image_name.jpg'; // จากฐานข้อมูล
echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="Image">';

// ตรวจสอบว่ารูปมีอยู่จริง
if (ImageService::imageExists($imageUrl)) {
    // แสดงรูป
}

// ดึงข้อมูลรูป
$info = ImageService::getImageInfo($imageUrl);
// $info['width'], $info['height'], $info['size'], etc.
PHP
                ); ?></pre>

        <h3>3. ลบรูปภาพ:</h3>
        <pre><?php echo htmlspecialchars(
                    <<<'PHP'
$imageUrl = '/storage/uploads/areas/my_image_name.jpg';
$absolutePath = ImageService::publicPathToAbsolute($imageUrl);
ImageService::deleteImage($absolutePath);
PHP
                ); ?></pre>
    </div>

</body>

</html>