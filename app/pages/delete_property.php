<?php

declare(strict_types=1);

// ใช้ฐานข้อมูล: ลบ properties และ property_images
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2)); // /app
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ตรวจสอบการล็อกอินด้วย helper กลาง
$user = current_user();
if ($user === null) {
    flash('error', 'กรุณาเข้าสู่ระบบก่อนลบรายการพื้นที่');
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    app_log('delete_property_invalid_user', ['session_user' => $user]);
    flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
    redirect('?page=signin');
}

// ยอมรับแค่ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?page=my_properties');
}

$propertyId = (int) ($_POST['property_id'] ?? 0);

if ($propertyId <= 0) {
    flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการลบ');
    redirect('?page=my_properties');
}

try {
    // ตรวจสอบว่าเป็นของ user คนนี้จริงไหม + ดึง main_image
    $property = Database::fetchOne(
        'SELECT id, owner_id, main_image FROM properties WHERE id = ? LIMIT 1',
        [$propertyId]
    );

    if (!$property || (int) $property['owner_id'] !== $userId) {
        app_log('delete_property_unauthorized', [
            'user_id'     => $userId,
            'property_id' => $propertyId,
        ]);

        flash('error', 'คุณไม่มีสิทธิ์ลบรายการนี้');
        redirect('?page=my_properties');
    }

    // ดึงรูปทั้งหมดของ property นี้
    $images = Database::fetchAll(
        'SELECT image_url FROM property_images WHERE property_id = ?',
        [$propertyId]
    );

    // เตรียม path สำหรับลบไฟล์จริง
    if (defined('BASE_PATH')) {
        $basePath = BASE_PATH;
    } else {
        // fallback กรณีรันเดี่ยว
        $basePath = dirname(__DIR__, 2);
    }

    // รวม path ที่ต้องลบทั้งหมด (กันซ้ำ)
    $filePaths = [];

    foreach ($images as $img) {
        if (!empty($img['image_url'])) {
            $filePaths[$basePath . $img['image_url']] = true;
        }
    }

    if (!empty($property['main_image'])) {
        $filePaths[$basePath . $property['main_image']] = true;
    }

    Database::transaction(function () use ($propertyId, $filePaths, $userId) {
        // ลบไฟล์จริง (ถ้ามีอยู่)
        foreach (array_keys($filePaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        // ลบรูปภาพจากฐานข้อมูล
        Database::execute(
            'DELETE FROM property_images WHERE property_id = ?',
            [$propertyId]
        );

        // ลบการจองที่อ้างถึง property นี้
        Database::execute(
            'DELETE FROM bookings WHERE property_id = ?',
            [$propertyId]
        );

        // ลบตัว property จริง
        Database::execute(
            'DELETE FROM properties WHERE id = ? AND owner_id = ?',
            [$propertyId, $userId]
        );
    });

    app_log('delete_property_success', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
    ]);

    flash('success', 'ลบรายการพื้นที่เรียบร้อยแล้ว');
    redirect('?page=my_properties');
} catch (Throwable $e) {
    app_log('delete_property_error', [
        'user_id'     => $userId,
        'property_id' => $propertyId,
        'error'       => $e->getMessage(),
    ]);

    // ใช้ flash + redirect ไปหน้า list ให้ popup แสดง error แทน query string ยาว ๆ
    flash('error', 'เกิดข้อผิดพลาดในการลบรายการ กรุณาลองใหม่อีกครั้ง');
    redirect('?page=my_properties');
}
