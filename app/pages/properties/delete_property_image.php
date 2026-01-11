<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile) || !is_file($helpersFile)) {
  error_log('delete_property_image_bootstrap_missing');
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'message' => 'System error'], JSON_UNESCAPED_UNICODE);
  return;
}

require_once $databaseFile;
require_once $helpersFile;

try {
  app_session_start();
} catch (Throwable $e) {
  app_log('delete_property_image_session_error', ['error' => $e->getMessage()]);
  json_response(['success' => false, 'message' => 'Session error'], 500);
  return;
}

// Method
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
  json_response(['success' => false, 'message' => 'Method not allowed'], 405);
  return;
}

// CSRF
$csrf = (string)($_POST['_csrf'] ?? '');
if (!function_exists('csrf_verify') || !csrf_verify($csrf)) {
  app_log('delete_property_image_csrf_fail', ['csrf' => $csrf ? 'sent' : 'missing']);
  json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง (CSRF)'], 403);
  return;
}

// Auth
$user = current_user();
if ($user === null) {
  json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
  return;
}

$userId   = (int)($user['user_id'] ?? $user['id'] ?? 0);
$userRole = (int)($user['role'] ?? 0);
$isAdmin  = defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN;

if ($userId <= 0) {
  app_log('delete_property_image_invalid_user', ['session_user' => $user]);
  json_response(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 401);
  return;
}

// Input
$action  = (string)($_POST['action'] ?? '');
$imageId = (int)($_POST['image_id'] ?? 0);
$areaId  = (int)($_POST['area_id'] ?? 0);

if ($action !== 'delete_image') {
  json_response(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], 400);
  return;
}

if ($imageId <= 0 || $areaId <= 0) {
  json_response(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
  return;
}

// Delete
$allowedPrefix = '/storage/uploads/areas/';
$projectRoot = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(APP_PATH);
$publicRoot  = $projectRoot . '/public';

try {
  $result = Database::transaction(function () use (
    $isAdmin,
    $userId,
    $areaId,
    $imageId,
    $allowedPrefix,
    $publicRoot
  ): array {
    // lock area + auth
    if ($isAdmin) {
      $area = Database::fetchOne(
        'SELECT area_id FROM rental_area WHERE area_id = ? LIMIT 1 FOR UPDATE',
        [$areaId]
      );
      if (!$area) throw new RuntimeException('AREA_NOT_FOUND');
    } else {
      $area = Database::fetchOne(
        'SELECT area_id FROM rental_area WHERE area_id = ? AND user_id = ? LIMIT 1 FOR UPDATE',
        [$areaId, $userId]
      );
      if (!$area) throw new RuntimeException('FORBIDDEN');
    }

    // lock image (idempotent: ถ้าไม่เจอถือว่าลบไปแล้ว)
    $image = Database::fetchOne(
      'SELECT image_id, image_url
         FROM area_image
        WHERE image_id = ? AND area_id = ?
        LIMIT 1
        FOR UPDATE',
      [$imageId, $areaId]
    );
    if (!$image) {
      return [
        'already_deleted' => true,
        'image_url' => null,
        'file_deleted' => true,
      ];
    }

    $url = trim((string)($image['image_url'] ?? ''));
    $fileDeleted = true;

    Database::execute(
      'DELETE FROM area_image WHERE image_id = ? AND area_id = ?',
      [$imageId, $areaId]
    );

    if ($url !== '' && strpos($url, $allowedPrefix) === 0) {
      if (class_exists('ImageService') && method_exists('ImageService', 'deleteImage')) {
        $abs = ImageService::publicPathToAbsolute($url);
        $fileDeleted = ImageService::deleteImage($abs);
        if (!$fileDeleted) {
          app_log('delete_property_image_file_delete_failed', ['url' => $url, 'abs' => $abs]);
        }
      } else {
        $abs = $publicRoot . $url;
        if (is_file($abs) && !@unlink($abs)) {
          $fileDeleted = false;
          app_log('delete_property_image_file_unlink_failed', ['abs' => $abs]);
        }
      }
    }

    return [
      'image_url' => $url,
      'file_deleted' => $fileDeleted,
      'already_deleted' => false,
    ];
  });

  app_log('delete_property_image_success', [
    'area_id' => $areaId,
    'image_id' => $imageId,
    'file_deleted' => $result['file_deleted'] ?? null,
    'already_deleted' => $result['already_deleted'] ?? false,
  ]);

  json_response([
    'success' => true,
    'message' => ($result['already_deleted'] ?? false) ? 'รูปนี้ถูกลบไปแล้ว' : 'ลบรูปภาพสำเร็จ',
    'area_id' => $areaId,
    'image_id' => $imageId,
    'file_deleted' => (bool)($result['file_deleted'] ?? true),
    'already_deleted' => (bool)($result['already_deleted'] ?? false),
  ], 200);
} catch (Throwable $e) {
  $msg = $e->getMessage();

  if ($msg === 'FORBIDDEN') {
    json_response(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบรูปภาพนี้'], 403);
    return;
  }
  if ($msg === 'AREA_NOT_FOUND') {
    json_response(['success' => false, 'message' => 'ไม่พบพื้นที่ที่ระบุ'], 404);
    return;
  }

  app_log('delete_property_image_error', [
    'area_id' => $areaId,
    'image_id' => $imageId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
  ]);

  json_response(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบรูปภาพ กรุณาลองใหม่อีกครั้ง'], 500);
}
