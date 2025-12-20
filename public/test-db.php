<?php

declare(strict_types=1);

// ป้องกันการดักจับ error ที่ไม่คาดคิด
require_once __DIR__ . '/../app/includes/crash_shield.php';

// โหลด bootstrap ของโปรเจกต์
$ctx = require __DIR__ . '/../app/bootstrap/bootstrap_test_db.php';

// แตกตัวแปรจาก ctx
$isJson = (bool)($ctx['isJson'] ?? false);
$isDebug = (bool)($ctx['isDebug'] ?? false);
$status = (array)($ctx['status'] ?? []);
$testQuery = $ctx['testQuery'] ?? null;
$errorException = $ctx['errorException'] ?? null;

// แสดงผลเป็น JSON
if ($isJson) {
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
  }

  $response = [
    'ok'             => (bool)($status['ok'] ?? false),
    'database'       => $status['database'] ?? null,
    'host'           => $status['host'] ?? null,
    'driver'         => $status['driver'] ?? null,
    'server_version' => $status['server_version'] ?? null,
    'ping'           => (bool)($status['ping'] ?? false),
    'ping_time_ms'   => $status['ping_time_ms'] ?? null,
    'tested_query'   => $testQuery,
    'error'          => $isDebug ? ($status['error'] ?? null) : null,
    'timestamp'      => date('c'),
    'debug'          => $isDebug,
  ];

  $flags = 0;
  if (defined('JSON_UNESCAPED_UNICODE')) $flags |= (int)constant('JSON_UNESCAPED_UNICODE');
  if (defined('JSON_UNESCAPED_SLASHES')) $flags |= (int)constant('JSON_UNESCAPED_SLASHES');
  if (defined('JSON_PRETTY_PRINT') && $isDebug) $flags |= (int)constant('JSON_PRETTY_PRINT');

  $json = json_encode($response, $flags);
  if ($json === false) {
    throw new RuntimeException('JSON encoding failed');
  }

  echo $json;

  if (ob_get_level() > 0) {
    @ob_end_flush();
  }
  exit;
}

// แสดงผลเป็น HTML
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
  header('Cache-Control: no-store, max-age=0');
}

require __DIR__ . '/../app/pages/test-db.view.php';

if (ob_get_level() > 0) {
  @ob_end_flush();
}
