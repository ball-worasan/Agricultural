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
    json_response([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบ',
    ], 401);
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('delete_property_image_invalid_user', ['session_user' => $user]);
    json_response([
        'success' => false,
        'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง',
    ], 401);
}

// ตรวจสอบ method + action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'คำขอไม่ถูกต้อง (method ไม่รองรับ)',
    ], 405);
}

$action = $_POST['action'] ?? '';
if ($action !== 'delete_image') {
    json_response([
        'success' => false,
        'message' => 'คำขอไม่ถูกต้อง',
    ], 400);
}

$imageId    = (int) ($_POST['image_id'] ?? 0);
$propertyId = (int) ($_POST['property_id'] ?? 0);

if ($imageId <= 0 || $propertyId <= 0) {
    json_response([
        'success' => false,
        'message' => 'ข้อมูลไม่ถูกต้อง',
    ], 400);
}

try {
    // ตรวจสอบสิทธิ์: พื้นที่ต้องเป็นของ user นี้
    $property = Database::fetchOne(
        'SELECT id FROM properties WHERE id = ? AND owner_id = ? LIMIT 1',
        [$propertyId, $userId]
    );

    if (!$property) {
        json_response([
            'success' => false,
            'message' => 'ไม่มีสิทธิ์ลบรูปภาพนี้',
        ], 403);
    }

    // ดึงข้อมูลรูปภาพ
    $image = Database::fetchOne(
        'SELECT id, image_url FROM property_images WHERE id = ? AND property_id = ? LIMIT 1',
        [$imageId, $propertyId]
    );

    if (!$image) {
        json_response([
            'success' => false,
            'message' => 'ไม่พบรูปภาพ',
        ], 404);
    }

    $relativePath = (string) $image['image_url'];

    if ($relativePath !== '') {
        // image_url เป็น /storage/uploads/properties/xxx.jpg
        if (defined('BASE_PATH')) {
            $filePath = rtrim(BASE_PATH, '/') . $relativePath;
        } else {
            $filePath = rtrim(APP_PATH, '/') . $relativePath;
        }

        if (is_file($filePath)) {
            if (!@unlink($filePath)) {
                app_log('delete_property_image_unlink_failed', [
                    'user_id'     => $userId,
                    'property_id' => $propertyId,
                    'image_id'    => $imageId,
                    'file_path'   => $filePath,
                    'error'       => error_get_last(),
                ]);
                // log ไว้ แต่ไม่ต้อง fail ทั้ง process
            }
        }
    }

    // ลบ record จากฐานข้อมูล
    Database::execute(
        'DELETE FROM property_images WHERE id = ?',
        [$imageId]
    );

    // ถ้ารูปนี้เป็น main_image ให้หาใหม่
    $currentProperty = Database::fetchOne(
        'SELECT main_image FROM properties WHERE id = ? LIMIT 1',
        [$propertyId]
    );

    if ($currentProperty && $currentProperty['main_image'] === $image['image_url']) {
        $newMainImageRow = Database::fetchOne(
            'SELECT image_url 
             FROM property_images 
             WHERE property_id = ? 
             ORDER BY display_order 
             LIMIT 1',
            [$propertyId]
        );

        $newMainImageUrl = $newMainImageRow ? $newMainImageRow['image_url'] : null;

        Database::execute(
            'UPDATE properties SET main_image = ? WHERE id = ?',
            [$newMainImageUrl, $propertyId]
        );
    }

    app_log('delete_property_image_success', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'image_id'    => $imageId,
        'image_url'   => $image['image_url'],
    ]);

    json_response([
        'success' => true,
        'message' => 'ลบรูปภาพสำเร็จ',
    ]);
} catch (Throwable $e) {
    app_log('delete_property_image_error', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'image_id'    => $imageId,
        'error'       => $e->getMessage(),
    ]);

    json_response([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการลบรูปภาพ กรุณาลองใหม่อีกครั้ง',
    ], 500);
}
