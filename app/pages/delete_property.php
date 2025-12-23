<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
  define('APP_PATH', BASE_PATH . '/app');
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

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
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

$areaId = (int)($_POST['area_id'] ?? 0);
if ($areaId <= 0) {
  flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการลบ');
  redirect('?page=my_properties');
}

try {
  // ✅ ต้องเป็นของ user นี้เท่านั้น
  $area = Database::fetchOne(
    'SELECT area_id, user_id FROM rental_area WHERE area_id = ? AND user_id = ? LIMIT 1',
    [$areaId, $userId]
  );

  if (!$area || (int)$area['user_id'] !== $userId) {
    app_log('delete_property_unauthorized', [
      'user_id'  => $userId,
      'area_id' => $areaId,
    ]);
    flash('error', 'คุณไม่มีสิทธิ์ลบรายการนี้');
    redirect('?page=my_properties');
  }

  // ดึงรูปทั้งหมดของพื้นที่นี้
  $images = Database::fetchAll(
    'SELECT image_url FROM area_image WHERE area_id = ?',
    [$areaId]
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

  Database::transaction(function () use ($areaId, $filePaths, $userId) {
    // ลบไฟล์จริง (ถ้ามี)
    foreach (array_keys($filePaths) as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    // ลบรูปภาพจากฐานข้อมูล
    Database::execute('DELETE FROM area_image WHERE area_id = ?', [$areaId]);

    // ลบการจองที่อ้างถึงพื้นที่นี้ (มี ON DELETE CASCADE แต่ลบไว้อุ่นใจ)
    Database::execute('DELETE FROM booking_deposit WHERE area_id = ?', [$areaId]);

    // ลบพื้นที่
    Database::execute(
      'DELETE FROM rental_area WHERE area_id = ? AND user_id = ?',
      [$areaId, $userId]
    );
  });

  app_log('delete_property_success', [
    'user_id' => $userId,
    'area_id' => $areaId,
  ]);

  flash('success', 'ลบรายการพื้นที่เรียบร้อยแล้ว');
  redirect('?page=my_properties');
} catch (Throwable $e) {
  app_log('delete_property_error', [
    'user_id' => $userId,
    'area_id' => $areaId,
    'error'   => $e->getMessage(),
  ]);

  flash('error', 'เกิดข้อผิดพลาดในการลบรายการ กรุณาลองใหม่อีกครั้ง');
  redirect('?page=my_properties');
}
