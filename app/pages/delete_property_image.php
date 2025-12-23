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
  app_log('delete_property_image_database_file_missing', ['file' => $databaseFile]);
  json_response(['success' => false, 'message' => 'System error'], 500);
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('delete_property_image_helpers_file_missing', ['file' => $helpersFile]);
  json_response(['success' => false, 'message' => 'System error'], 500);
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
  app_log('delete_property_image_session_error', ['error' => $e->getMessage()]);
  json_response(['success' => false, 'message' => 'Session error'], 500);
  return;
}

// ----------------------------
// Validate request method
// ----------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง (method ไม่รองรับ)'], 405);
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  return;
}

$userId = (int)($user['user_id'] ?? $user['id'] ?? 0);
if ($userId <= 0) {
  app_log('delete_property_image_invalid_user', ['session_user' => $user]);
  json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
  return;
}

// ----------------------------
// CSRF protection
// ----------------------------
try {
  csrf_require();
} catch (Throwable $e) {
  app_log('delete_property_image_csrf_error', ['error' => $e->getMessage()]);
  json_response(['success' => false, 'message' => 'CSRF ไม่ถูกต้อง'], 403);
  return;
}

// ----------------------------
// Validate action
// ----------------------------
$action = (string)($_POST['action'] ?? '');
if ($action !== 'delete_image') {
  json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], 400);
  return;
}

$imageId = (int)($_POST['image_id'] ?? 0);
$areaId  = (int)($_POST['area_id'] ?? 0);

if ($imageId <= 0 || $areaId <= 0) {
  json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
  return;
}

try {
  $area = Database::fetchOne(
    'SELECT area_id FROM rental_area WHERE area_id = ? AND user_id = ? LIMIT 1',
    [$areaId, $userId]
  );
  if (!$area) {
    json_response(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบรูปภาพนี้'], 403);
    return;
  }

  $image = Database::fetchOne(
    'SELECT image_id, image_url FROM area_image WHERE image_id = ? AND area_id = ? LIMIT 1',
    [$imageId, $areaId]
  );
  if (!$image) {
    json_response(['success' => false, 'message' => 'ไม่พบรูปภาพ'], 404);
    return;
  }

  $relativePath = (string)$image['image_url'];

  $projectRoot = defined('BASE_PATH')
    ? rtrim((string) BASE_PATH, '/')
    : dirname(__DIR__, 3);

  // ลบไฟล์จริง (เฉพาะ allowlist path)
  $allowedPrefix = '/storage/uploads/areas/';
  $fileDeleted = true;
  if ($relativePath !== '' && strpos($relativePath, $allowedPrefix) === 0) {
    $filePath = $projectRoot . $relativePath;
    if (is_file($filePath)) {
      if (!@unlink($filePath)) {
        $fileDeleted = false;
        app_log('delete_property_image_file_unlink_failed', ['file_path' => $filePath]);
      }
    }
    // ถ้าไฟล์ไม่มี แค่เดินต่อ
  }

  // ลบ record จากฐานข้อมูล
  Database::execute('DELETE FROM area_image WHERE image_id = ?', [$imageId]);

  json_response(['success' => true, 'message' => 'ลบรูปภาพสำเร็จ']);
} catch (Throwable $e) {
  app_log('delete_property_image_error', ['area_id' => $areaId, 'image_id' => $imageId, 'error' => $e->getMessage()]);
  json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบรูปภาพ กรุณาลองใหม่อีกครั้ง'], 500);
}
