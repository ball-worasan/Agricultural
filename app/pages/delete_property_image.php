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

$userId = (int)($user['id'] ?? 0);
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

$imageId    = (int)($_POST['image_id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);

if ($imageId <= 0 || $propertyId <= 0) {
  json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
}

try {
  $property = Database::fetchOne(
    'SELECT id FROM properties WHERE id = ? AND owner_id = ? LIMIT 1',
    [$propertyId, $userId]
  );
  if (!$property) {
    json_response(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบรูปภาพนี้'], 403);
  }

  $image = Database::fetchOne(
    'SELECT id, image_url FROM property_images WHERE id = ? AND property_id = ? LIMIT 1',
    [$imageId, $propertyId]
  );
  if (!$image) {
    json_response(['success' => false, 'message' => 'ไม่พบรูปภาพ'], 404);
  }

  $relativePath = (string)$image['image_url'];

  $projectRoot = defined('BASE_PATH')
    ? rtrim((string) BASE_PATH, '/')
    : dirname(__DIR__, 3);

  // ลบไฟล์จริง (เฉพาะ allowlist path)
  $allowedPrefix = '/storage/uploads/properties/';
  if ($relativePath !== '' && strpos($relativePath, $allowedPrefix) === 0) {
    $filePath = $projectRoot . $relativePath;
    if (is_file($filePath)) {
      @unlink($filePath);
    }
  }

  // ลบ record
  Database::execute('DELETE FROM property_images WHERE id = ?', [$imageId]);

  // ถ้าเป็น main_image ให้หาใหม่
  $current = Database::fetchOne('SELECT main_image FROM properties WHERE id = ? LIMIT 1', [$propertyId]);
  if ($current && (string)$current['main_image'] === $relativePath) {
    $newMain = Database::fetchOne(
      'SELECT image_url FROM property_images WHERE property_id = ? ORDER BY display_order LIMIT 1',
      [$propertyId]
    );
    $newMainUrl = $newMain ? (string)$newMain['image_url'] : null;
    Database::execute('UPDATE properties SET main_image = ? WHERE id = ?', [$newMainUrl, $propertyId]);
  }

  json_response(['success' => true, 'message' => 'ลบรูปภาพสำเร็จ']);
} catch (Throwable $e) {
  app_log('delete_property_image_error', ['property_id' => $propertyId, 'image_id' => $imageId, 'error' => $e->getMessage()]);
  json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบรูปภาพ กรุณาลองใหม่อีกครั้ง'], 500);
}
