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
  app_log('delete_property_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('delete_property_helpers_file_missing', ['file' => $helpersFile]);
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
  app_log('delete_property_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนลบรายการพื้นที่');
  redirect('?page=signin');
}

$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
  app_log('delete_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// Validate request method
// ----------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  flash('error', 'คำขอไม่ถูกต้อง');
  redirect('?page=my_properties');
}

// ----------------------------
// CSRF protection
// ----------------------------
try {
  csrf_require();
} catch (Throwable $e) {
  app_log('delete_property_csrf_error', ['error' => $e->getMessage()]);
  flash('error', 'คำขอไม่ถูกต้อง (CSRF)');
  redirect('?page=my_properties');
}

$propertyId = (int)($_POST['property_id'] ?? 0);
if ($propertyId <= 0) {
  flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการลบ');
  redirect('?page=my_properties');
}

try {
  // ✅ ต้องเป็นของ user นี้เท่านั้น
  $property = Database::fetchOne(
    'SELECT id, owner_id, main_image FROM properties WHERE id = ? LIMIT 1',
    [$propertyId]
  );

  if (!$property || (int)$property['owner_id'] !== $userId) {
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

  // ✅ โฟลเดอร์ไฟล์จริงอยู่ใต้ public
  $publicRoot = APP_PATH . '/public';

  // ✅ allowlist: ลบได้เฉพาะไฟล์ใน /storage/uploads/
  $allowedPrefix = '/storage/uploads/';

  $filePaths = [];

  foreach ($images as $img) {
    $rel = (string)($img['image_url'] ?? '');
    if ($rel !== '' && strpos($rel, $allowedPrefix) === 0) {
      $filePaths[$publicRoot . $rel] = true;
    }
  }

  $main = (string)($property['main_image'] ?? '');
  if ($main !== '' && strpos($main, $allowedPrefix) === 0) {
    $filePaths[$publicRoot . $main] = true;
  }

  Database::transaction(function () use ($propertyId, $filePaths, $userId) {
    // ลบไฟล์จริง (ถ้ามี)
    foreach (array_keys($filePaths) as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    // ลบรูปภาพจากฐานข้อมูล
    Database::execute('DELETE FROM property_images WHERE property_id = ?', [$propertyId]);

    // ลบการจองที่อ้างถึง property นี้
    Database::execute('DELETE FROM bookings WHERE property_id = ?', [$propertyId]);

    // ลบ property
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

  flash('error', 'เกิดข้อผิดพลาดในการลบรายการ กรุณาลองใหม่อีกครั้ง');
  redirect('?page=my_properties');
}
