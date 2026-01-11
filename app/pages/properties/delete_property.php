<?php

declare(strict_types=1);

/**
 * delete_property.php (REFAC)
 * - POST only
 * - CSRF verify
 * - Owner/Admin authorization
 * - Atomic delete (transaction + FOR UPDATE)
 * - Delete physical files allowlist only
 * - Redirect (page flow) + supports admin
 */

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
if (!defined('APP_PATH'))  define('APP_PATH', BASE_PATH . '/app');

$databaseFile = APP_PATH . '/config/database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile) || !is_file($helpersFile)) {
  error_log('delete_property_bootstrap_missing');
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>System error</p></div>';
  return;
}

require_once $databaseFile;
require_once $helpersFile;

try {
  app_session_start();
} catch (Throwable $e) {
  app_log('delete_property_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// Method
// ----------------------------
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
  flash('error', 'คำขอไม่ถูกต้อง (method)');
  redirect('?page=my_properties');
  return;
}

// ----------------------------
// Auth
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อนลบรายการพื้นที่');
  redirect('?page=signin');
  return;
}

$userId   = (int)($user['user_id'] ?? $user['id'] ?? 0);
$userRole = (int)($user['role'] ?? 0);
$isAdmin  = defined('ROLE_ADMIN') && $userRole === ROLE_ADMIN;

if ($userId <= 0) {
  app_log('delete_property_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
  return;
}

// ----------------------------
// CSRF
// ----------------------------
$csrf = (string)($_POST['_csrf'] ?? '');
if (!function_exists('csrf_verify') || !csrf_verify($csrf)) {
  flash('error', 'คำขอไม่ถูกต้อง (CSRF)');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
  return;
}

// ----------------------------
// Input
// ----------------------------
$areaId = (int)($_POST['area_id'] ?? 0);
if ($areaId <= 0) {
  flash('error', 'ไม่พบข้อมูลพื้นที่ที่ต้องการลบ');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
  return;
}

// ----------------------------
// Delete (atomic)
// ----------------------------
$publicRoot    = rtrim((string)BASE_PATH, '/') . '/public';
$allowedPrefix = '/storage/uploads/'; // allowlist

try {
  Database::transaction(function () use (
    $areaId,
    $userId,
    $isAdmin,
    $publicRoot,
    $allowedPrefix
  ) {
    // lock area + auth
    if ($isAdmin) {
      $area = Database::fetchOne(
        'SELECT area_id, user_id, area_status
           FROM rental_area
          WHERE area_id = ?
          LIMIT 1
          FOR UPDATE',
        [$areaId]
      );
    } else {
      $area = Database::fetchOne(
        'SELECT area_id, user_id, area_status
           FROM rental_area
          WHERE area_id = ? AND user_id = ?
          LIMIT 1
          FOR UPDATE',
        [$areaId, $userId]
      );
    }

    if (!$area) {
      throw new RuntimeException('AREA_NOT_FOUND');
    }

    // guard: non-admin cannot delete booked/unavailable
    $status = (string)($area['area_status'] ?? '');
    if (!$isAdmin && in_array($status, ['booked', 'unavailable'], true)) {
      throw new RuntimeException('AREA_LOCKED_STATUS');
    }

    // lock images
    $images = Database::fetchAll(
      'SELECT image_url
         FROM area_image
        WHERE area_id = ?
        FOR UPDATE',
      [$areaId]
    );

    // collect files (unique) allowlist only
    $filePaths = [];
    foreach ($images as $img) {
      $rel = trim((string)($img['image_url'] ?? ''));
      if ($rel !== '' && strpos($rel, $allowedPrefix) === 0) {
        $filePaths[$publicRoot . $rel] = true;
      }
    }

    // delete DB rows first (ลด orphan rows)
    Database::execute('DELETE FROM area_image WHERE area_id = ?', [$areaId]);

    // booking_deposit: ถ้ามี FK cascade ไม่ต้องลบ แต่ลบไว้ก็ได้
    Database::execute('DELETE FROM booking_deposit WHERE area_id = ?', [$areaId]);

    // delete area
    if ($isAdmin) {
      Database::execute('DELETE FROM rental_area WHERE area_id = ?', [$areaId]);
    } else {
      Database::execute('DELETE FROM rental_area WHERE area_id = ? AND user_id = ?', [$areaId, $userId]);
    }

    // delete files after DB (ใน transaction ก็ได้ แต่ถ้า unlink fail DB จะ rollback ไม่ได้อยู่ดี)
    // ใช้ ImageService ถ้ามี
    if (class_exists('ImageService') && method_exists('ImageService', 'deleteImages')) {
      $deleted = ImageService::deleteImages(array_keys($filePaths));
      $total = count($filePaths);
      if ($deleted < $total) {
        app_log('delete_property_partial_file_deletion', [
          'area_id' => $areaId,
          'deleted' => $deleted,
          'total' => $total,
        ]);
      }
    } else {
      // fallback: manual delete
      foreach (array_keys($filePaths) as $abs) {
        if (is_file($abs)) {
          if (!@unlink($abs)) {
            app_log('delete_property_file_unlink_failed', ['abs' => $abs, 'area_id' => $areaId]);
          }
        }
      }
    }
  });

  app_log('delete_property_success', ['user_id' => $userId, 'area_id' => $areaId]);

  flash('success', 'ลบรายการพื้นที่เรียบร้อยแล้ว');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
} catch (Throwable $e) {
  $msg = $e->getMessage();

  if ($msg === 'AREA_NOT_FOUND') {
    flash('error', 'ไม่พบรายการพื้นที่ที่ต้องการลบ');
    redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
    return;
  }

  if ($msg === 'AREA_LOCKED_STATUS') {
    flash('error', 'ไม่สามารถลบพื้นที่ที่ติดจองหรือปิดให้เช่าแล้วได้');
    redirect('?page=my_properties');
    return;
  }

  app_log('delete_property_error', [
    'user_id' => $userId,
    'area_id' => $areaId,
    'error'   => $e->getMessage(),
    'trace'   => $e->getTraceAsString(),
  ]);

  flash('error', 'เกิดข้อผิดพลาดในการลบรายการ กรุณาลองใหม่อีกครั้ง');
  redirect($isAdmin ? '?page=admin_dashboard' : '?page=my_properties');
}
