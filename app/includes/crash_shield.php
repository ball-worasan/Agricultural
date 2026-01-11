<?php

declare(strict_types=1);

/**
 * Crash Shield
 * - Starts output buffering early
 * - Converts selected errors to exceptions
 * - Catches uncaught throwables + fatal shutdown
 * - Emits safe HTML/JSON responses
 */

ob_start();

if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__, 2));
}

// -----------------------------------------------------------------------------
// Fallback logger (when app_log not available yet)
// -----------------------------------------------------------------------------
$__fallbackLogFile = BASE_PATH . '/storage/logs/php_fatal.log';

$__ensureLogDir = static function () use ($__fallbackLogFile): void {
  $dir = dirname($__fallbackLogFile);
  if (is_dir($dir)) return;
  @mkdir($dir, 0775, true);
};

$__fallbackLogWrite = static function (string $tag, array $ctx = []) use ($__fallbackLogFile, $__ensureLogDir): void {
  $__ensureLogDir();

  $line = '[' . date('c') . '] ' . $tag;
  if ($ctx) {
    $json = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $line .= ' ' . ($json !== false ? $json : '{}');
  }
  $line .= PHP_EOL;

  @file_put_contents($__fallbackLogFile, $line, FILE_APPEND);
};

// -----------------------------------------------------------------------------
// Response helpers
// -----------------------------------------------------------------------------
$__clearBuffers = static function (): void {
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
};

$__safeHeader = static function (string $name, string $value): void {
  if (!headers_sent()) {
    @header($name . ': ' . $value);
  }
};

$__wantJson = static function (): bool {
  // Respect explicit Accept
  $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
  if (strpos($accept, 'application/json') !== false) return true;

  // XHR/fetch
  $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  if ($xrw === 'xmlhttprequest') return true;

  // Content-Type JSON
  $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($ct, 'application/json') !== false) return true;

  // API path heuristic (optional)
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  if (strpos($uri, '/api') === 0) return true;

  return false;
};

$__renderFail = static function (
  string $title,
  string $message,
  string $refId,
  bool $json
) use ($__safeHeader, $__clearBuffers): void {
  if (!headers_sent()) {
    http_response_code(500);
    $__safeHeader('Cache-Control', 'no-store, max-age=0');
    $__safeHeader('X-Content-Type-Options', 'nosniff');
    $__safeHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    if ($json) {
      $__safeHeader('Content-Type', 'application/json; charset=utf-8');
    } else {
      $__safeHeader('Content-Type', 'text/html; charset=utf-8');
    }
  }

  $__clearBuffers();

  if ($json) {
    echo json_encode([
      'success' => false,
      'message' => $message,
      'ref' => $refId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
  }

  $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

  echo '<!doctype html><html lang="th"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>' . $esc($title) . '</title></head>';
  echo '<body style="font-family:sans-serif;max-width:720px;margin:40px auto;padding:0 16px">';
  echo '<h1>' . $esc($title) . '</h1>';
  echo '<p>' . $esc($message) . '</p>';
  echo '<p><a href="?page=home">กลับหน้าหลัก</a></p>';
  echo '<small>Ref: ' . $esc($refId) . '</small>';
  echo '</body></html>';
};

// -----------------------------------------------------------------------------
// Error -> Exception (เลือกแค่ที่ควรทำให้ล้ม)
// -----------------------------------------------------------------------------
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) return false;

  // Fatal-ish + recoverable only (ไม่จับ warning ทั่วไป เพื่อไม่ให้ล้มง่ายเกิน)
  $throwable = [
    E_USER_ERROR,
    E_RECOVERABLE_ERROR,
    E_CORE_ERROR,
    E_COMPILE_ERROR,
  ];

  if (!in_array($severity, $throwable, true)) {
    // let PHP handle it (and it will be logged by ini settings)
    return false;
  }

  throw new ErrorException($message, 0, $severity, $file, $line);
});

// -----------------------------------------------------------------------------
// Uncaught exception handler
// -----------------------------------------------------------------------------
set_exception_handler(static function (Throwable $e) use ($__fallbackLogWrite, $__renderFail, $__wantJson, $__clearBuffers): void {
  $isProd = function_exists('app_is_production') ? app_is_production() : true;
  $ref = bin2hex(random_bytes(4));
  $json = $__wantJson();

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

  if ($isProd) {
    $__renderFail('ระบบขัดข้องชั่วคราว', 'ลองใหม่อีกครั้ง หรือกลับหน้าหลัก', $ref, $json);
    exit;
  }

  // Dev: show details
  if ($json) {
    $__renderFail('Error', (string)$e, $ref, true);
    exit;
  }

  $__clearBuffers();
  http_response_code(500);
  @header('Content-Type: text/plain; charset=utf-8');
  echo (string)$e;
  exit;
});

// -----------------------------------------------------------------------------
// Shutdown handler for fatal errors
// -----------------------------------------------------------------------------
register_shutdown_function(static function () use ($__fallbackLogWrite, $__renderFail, $__wantJson): void {
  $err = error_get_last();
  if (!$err) return;

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array((int)($err['type'] ?? 0), $fatalTypes, true)) return;

  $isProd = function_exists('app_is_production') ? app_is_production() : true;
  $ref = bin2hex(random_bytes(4));
  $json = $__wantJson();

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

  if ($isProd) {
    $__renderFail('ระบบขัดข้องชั่วคราว', 'ลองใหม่อีกครั้ง หรือกลับหน้าหลัก', $ref, $json);
  } else {
    $__renderFail('Fatal error', (string)($ctx['message'] ?? 'Fatal'), $ref, $json);
  }
});
