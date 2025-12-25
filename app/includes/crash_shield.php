<?php

declare(strict_types=1);

// เริ่ม output buffering
ob_start();

if (!defined('BASE_PATH')) {
  // กำหนด BASE_PATH
  define('BASE_PATH', dirname(__DIR__, 2));
}

// กำหนดไฟล์ log ของ fatal error
$__fallbackLogFile = BASE_PATH . '/storage/logs/php_fatal.log';

// ตรวจสอบว่า request เป็น JSON หรือไม่
$__wantJson = static function (): bool {
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  $xhr = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

  return stripos($accept, 'application/json') !== false
    || stripos($xhr, 'xmlhttprequest') !== false
    || str_starts_with($uri, '/api')
    || ($method !== 'GET');
};

// ตรวจสอบว่า header ถูกส่งออกไปหรือไม่
$__safeHeader = static function (string $name, string $value): void {
  if (!headers_sent()) {
    @header($name . ': ' . $value);
  }
};

// เขียน log ของ fatal error ลงไฟล์
$__fallbackLogWrite = static function (string $tag, array $ctx = []) use ($__fallbackLogFile): void {
  $line = '[' . date('c') . '] ' . $tag;
  if (!empty($ctx)) {
    $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  $line .= PHP_EOL;
  @file_put_contents($__fallbackLogFile, $line, FILE_APPEND);
};

// แสดง error ของหน้านั้นๆ
$__clearBuffers = static function (): void {
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
};

$__renderFail = static function (string $title, string $message, string $refId, bool $json) use ($__safeHeader, $__clearBuffers): void {
  if (!headers_sent()) {
    http_response_code(500);
    if ($json) {
      $__safeHeader('Content-Type', 'application/json; charset=utf-8');
    } else {
      $__safeHeader('Content-Type', 'text/html; charset=utf-8');
    }
    $__safeHeader('Cache-Control', 'no-store, max-age=0');
    $__safeHeader('X-Content-Type-Options', 'nosniff');
  }

  // ล้าง output buffer
  $__clearBuffers();

  // แสดง error เป็น JSON
  if ($json) {
    echo json_encode([
      'success' => false,
      'message' => $message,
      'ref' => $refId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
  }

  // แสดง error เป็น HTML
  echo '<!doctype html><html lang="th"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>';
  echo '<body style="font-family:sans-serif;max-width:720px;margin:40px auto;padding:0 16px">';
  echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
  echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
  echo '<p><a href="?page=home">กลับหน้าหลัก</a></p>';
  echo '<small>Ref: ' . htmlspecialchars($refId, ENT_QUOTES, 'UTF-8') . '</small>';
  echo '</body></html>';
};

// error -> exception
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) return false;

  // โยนเฉพาะ warning+ (หรือปรับตามใจ)
  $throwable = [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_WARNING, E_USER_WARNING];
  if (!in_array($severity, $throwable, true)) return true;

  throw new ErrorException($message, 0, $severity, $file, $line);
});

// uncaught throwable
set_exception_handler(static function (Throwable $e) use ($__fallbackLogWrite, $__renderFail, $__wantJson, $__clearBuffers): void {
  $isProd = function_exists('app_is_production') ? app_is_production() : true;
  $ref = bin2hex(random_bytes(4));

  $ctx = [
    'ref' => $ref,
    'type' => get_class($e),
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ];

  if (function_exists('app_log')) {
    app_log('unhandled_exception', $ctx);
  } else {
    $__fallbackLogWrite('unhandled_exception', $ctx);
  }

  $json = $__wantJson();

  if ($isProd) {
    $__renderFail('ระบบขัดข้องชั่วคราว', 'ลองใหม่อีกครั้ง หรือกลับหน้าหลัก', $ref, $json);
  } else {
    if ($json) {
      $__renderFail('Error', (string)$e, $ref, true);
    } else {
      $__clearBuffers();
      http_response_code(500);
      @header('Content-Type: text/plain; charset=utf-8');
      echo (string)$e;
    }
  }
  exit;
});

// fatal shutdown
register_shutdown_function(static function () use ($__fallbackLogWrite, $__renderFail, $__wantJson, $__clearBuffers): void {
  $err = error_get_last();
  if (!$err) return;

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array((int)($err['type'] ?? 0), $fatalTypes, true)) return;

  $isProd = function_exists('app_is_production') ? app_is_production() : true;
  $ref = bin2hex(random_bytes(4));

  $ctx = [
    'ref' => $ref,
    'type' => $err['type'] ?? null,
    'message' => $err['message'] ?? '',
    'file' => $err['file'] ?? '',
    'line' => $err['line'] ?? 0,
  ];

  if (function_exists('app_log')) {
    app_log('fatal_shutdown', $ctx);
  } else {
    $__fallbackLogWrite('fatal_shutdown', $ctx);
  }

  $json = $__wantJson();
  if ($isProd) {
    $__renderFail('ระบบขัดข้องชั่วคราว', 'ลองใหม่อีกครั้ง หรือกลับหน้าหลัก', $ref, $json);
  } else {
    $__renderFail('Fatal error', ($ctx['message'] ?? 'Fatal'), $ref, $json);
  }
});
