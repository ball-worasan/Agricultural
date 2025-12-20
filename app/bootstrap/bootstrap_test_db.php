<?php

declare(strict_types=1);

// กำหนด BASE_PATH/APP_PATH
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2));
}

// กำหนด APP_PATH
if (!defined('APP_PATH')) {
  define('APP_PATH', BASE_PATH . '/app');
}

// โหลดไฟล์ Database.php และ helpers.php
$databaseFile = APP_PATH . '/config/Database.php';
$helpersFile  = APP_PATH . '/includes/helpers.php';

if (!is_file($databaseFile)) {
  throw new RuntimeException('Database config file not found: ' . $databaseFile);
}
if (!is_file($helpersFile)) {
  throw new RuntimeException('Helpers file not found: ' . $helpersFile);
}

require_once $databaseFile;
require_once $helpersFile;

// ตั้งค่า error reporting
$isProduction = function_exists('app_is_production') ? app_is_production() : true;
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $isProduction ? '0' : '1');

// ตั้งค่า timezone
if (!defined('APP_TIMEZONE')) {
  define('APP_TIMEZONE', 'Asia/Bangkok');
}

// ตั้งค่า timezone
try {
  $tz = Database::env('APP_TIMEZONE', APP_TIMEZONE) ?? APP_TIMEZONE;
  date_default_timezone_set((string)$tz);
} catch (Throwable $e) {
  try {
    date_default_timezone_set(APP_TIMEZONE);
  } catch (Throwable) {
    date_default_timezone_set('UTC');
  }
  if (function_exists('app_log')) {
    app_log('test_db_timezone_set_failed', [
      'requested' => $tz ?? APP_TIMEZONE,
      'error' => $e->getMessage(),
    ]);
  }
}

// ตั้งค่า format และ debug
$format = strtolower((string)($_GET['format'] ?? 'html'));
$isJson = ($format === 'json') || (PHP_SAPI === 'cli');
$isDebug = function_exists('app_debug_enabled') ? (bool)app_debug_enabled() : false;

// ตรวจสอบสถานะของฐานข้อมูล
$status = null;
$testQuery = null;
$errorException = null;

try {
  $status = Database::health();

  if (!empty($status['ok'])) {
    try {
      $testQuery = Database::fetchOne(
        'SELECT DATABASE() AS db_name, NOW() AS server_time, VERSION() AS version'
      );
    } catch (Throwable $queryError) {
      if (function_exists('app_log')) {
        app_log('test_db_query_error', ['error' => $queryError->getMessage()]);
      }
      $testQuery = null;
    }
  }
} catch (Throwable $e) {
  $status = [
    'ok'             => false,
    'driver'         => 'unknown',
    'host'           => null,
    'database'       => null,
    'server_version' => null,
    'ping'           => false,
    'ping_time_ms'   => null,
    'error'          => $e->getMessage(),
  ];
  $testQuery = null;
  $errorException = $e;

  if (function_exists('app_log')) {
    // เขียน log ของการตรวจสอบสถานะของฐานข้อมูล
    app_log('test_db_health_check_failed', [
      'error' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ]);
  }
}

return compact('isJson', 'isDebug', 'status', 'testQuery', 'errorException');
