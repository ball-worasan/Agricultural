<?php

declare(strict_types=1);

// ----------------------------
// Polyfill for IDE (บางที intelephense งอแง)
// ----------------------------
if (!defined('JSON_UNESCAPED_UNICODE')) {
  define('JSON_UNESCAPED_UNICODE', 256);
}

// ----------------------------
// App env helpers
// ----------------------------
if (!function_exists('app_env')) {
  function app_env(string $key, ?string $default = null): ?string
  {
    // ใช้ Database::env ถ้ามี (กัน include order)
    if (class_exists('Database') && method_exists('Database', 'env')) {
      return Database::env($key, $default);
    }

    $v = getenv($key);
    return ($v !== false) ? (string)$v : $default;
  }
}

if (!function_exists('app_is_production')) {
  function app_is_production(): bool
  {
    $env = strtolower((string)(app_env('APP_ENV', 'local') ?? 'local'));
    return in_array($env, ['prod', 'production'], true);
  }
}

if (!function_exists('app_debug_enabled')) {
  function app_debug_enabled(): bool
  {
    $v = strtolower((string)(app_env('APP_DEBUG', 'false') ?? 'false'));
    return in_array($v, ['1', 'true', 'yes', 'on'], true) && !app_is_production();
  }
}


// ----------------------------
// Session
// ----------------------------
function app_session_start(): void
{
  static $started = false;
  if ($started || session_status() === PHP_SESSION_ACTIVE) {
    $started = true;
    return;
  }

  if (headers_sent()) {
    if (function_exists('app_log')) app_log('session.headers_already_sent');
    return;
  }

  // harden session defaults
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.cookie_httponly', '1');

  $isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
  );

  $params = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ];

  session_set_cookie_params($params);
  session_start();

  if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
  }
}

if (!function_exists('csp_nonce')) {
  function csp_nonce(): string
  {
    static $nonce = null;
    if (is_string($nonce) && $nonce !== '') return $nonce;

    // base64 สำหรับ CSP nonce
    $nonce = base64_encode(random_bytes(16));
    return $nonce;
  }
}

// ----------------------------
// Security headers
// ----------------------------
if (!function_exists('send_security_headers')) {
  function send_security_headers(): void
  {
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $nonce = csp_nonce();

    header(
      "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' data: https:; " .
        "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "script-src 'self' 'nonce-{$nonce}'; " .
        "frame-ancestors 'none';"
    );
  }
}

// ----------------------------
// HTML escape
// ----------------------------
if (!function_exists('e')) {
  function e($value): string
  {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

// ----------------------------
// Date helpers
// ----------------------------
if (!function_exists('buddhist_date')) {
  function buddhist_date(string $date): string
  {
    $ts = strtotime($date);
    if ($ts === false) return '';
    $year = (int)date('Y', $ts) + 543;
    return date('d/m/', $ts) . $year;
  }
}

if (!function_exists('thai_full_date')) {
  function thai_full_date(int $day, int $monthZeroBased, int $yearAD): string
  {
    $months = [
      'มกราคม',
      'กุมภาพันธ์',
      'มีนาคม',
      'เมษายน',
      'พฤษภาคม',
      'มิถุนายน',
      'กรกฎาคม',
      'สิงหาคม',
      'กันยายน',
      'ตุลาคม',
      'พฤศจิกายน',
      'ธันวาคม',
    ];
    $monthName = $months[$monthZeroBased] ?? '';
    return $day . ' ' . $monthName . ' ' . ($yearAD + 543);
  }
}

if (!function_exists('now')) {
  function now(?string $timezone = 'Asia/Bangkok'): DateTimeImmutable
  {
    $tzName = ($timezone !== null && $timezone !== '') ? $timezone : 'UTC';
    try {
      $tz = new DateTimeZone($tzName);
    } catch (Exception $e) {
      $tz = new DateTimeZone('UTC');
    }
    return new DateTimeImmutable('now', $tz);
  }
}

// ----------------------------
// Auth helpers
// ----------------------------
if (!function_exists('current_user')) {
  function current_user(): ?array
  {
    app_session_start();
    $u = $_SESSION['user'] ?? null;
    return is_array($u) ? $u : null;
  }
}

if (!function_exists('is_authenticated')) {
  function is_authenticated(): bool
  {
    return current_user() !== null;
  }
}

if (!function_exists('is_admin')) {
  function is_admin(): bool
  {
    $user = current_user();
    return is_array($user) && (($user['role'] ?? null) === 'admin');
  }
}

// ----------------------------
// Redirect
// ----------------------------
if (!function_exists('redirect')) {
  function redirect(string $url, int $statusCode = 302): void
  {
    if (!headers_sent()) {
      header('Location: ' . $url, true, $statusCode);
      exit;
    }

    // HTML escape สำหรับ meta refresh
    $safeUrl = e($url);

    // JS escape ที่ถูกต้องสำหรับ string ใน <script>
    $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    echo '<!doctype html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
    echo '<script>window.location.replace(' . $jsUrl . ');</script>';
    echo '</head><body>';
    echo '<p>กำลังเปลี่ยนหน้าไปยัง <a href="' . $safeUrl . '">' . $safeUrl . '</a> ...</p>';
    echo '</body></html>';
    exit;
  }
}

// ----------------------------
// Flash + Old input
// ----------------------------
if (!function_exists('flash')) {
  function flash(string $key, ?string $message = null): ?string
  {
    app_session_start();
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
      $_SESSION['_flash'] = [];
    }

    if ($message !== null) {
      $_SESSION['_flash'][$key] = $message;
      return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
  }
}

if (!function_exists('store_old_input')) {
  function store_old_input(array $input): void
  {
    app_session_start();
    $_SESSION['_old_input'] = $input;
  }
}

if (!function_exists('old')) {
  function old(string $key, string $default = ''): string
  {
    app_session_start();
    $data = (isset($_SESSION['_old_input']) && is_array($_SESSION['_old_input']))
      ? $_SESSION['_old_input']
      : [];

    $val = $data[$key] ?? $default;
    return e((string)$val);
  }
}

// ----------------------------
// Flash popup renderer
// ----------------------------
if (!function_exists('render_flash_popup')) {
  function render_flash_popup(): void
  {
    static $renderedOnce = false;
    if ($renderedOnce) return; // กันซ้อน
    $renderedOnce = true;

    $success = flash('success');
    $error   = flash('error');

    $type = null;
    $message = null;

    if (is_string($success) && $success !== '') {
      $type = 'success';
      $message = $success;
    } elseif (is_string($error) && $error !== '') {
      $type = 'error';
      $message = $error;
    }

    if ($type === null || $message === null) return;

    echo '<div class="flash-popup" role="alert" aria-live="polite" data-type="' . e($type) . '">
            <div class="flash-popup__bar"></div>
            <div class="flash-popup__content">
                <svg class="flash-popup__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
    if ($type === 'success') {
      echo '<path d="M20 6L9 17l-5-5" />';
    } else {
      echo '<circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12" y2="16" />';
    }
    echo '</svg>
                <div class="flash-popup__msg">' . e($message) . '</div>
            </div>
            <div class="flash-popup__actions">
                <button type="button" class="flash-popup__close" aria-label="ปิด">ปิด</button>
                <span class="flash-popup__count" aria-hidden="true"></span>
            </div>
        </div>';
  }
}


// ----------------------------
// Logger
// ----------------------------
if (!function_exists('app_log')) {
  function app_log(string $event, array $context = []): void
  {
    $flags = (int)JSON_UNESCAPED_UNICODE;

    $json = json_encode($context, $flags);
    if ($json === false) $json = '{}';

    $app = defined('APP_NAME') ? (string)APP_NAME : 'app';
    error_log('[' . $app . '][' . $event . '] ' . $json);
  }
}

// ----------------------------
// JSON response (ประกาศครั้งเดียวพอ)
// ----------------------------
if (!function_exists('json_response')) {
  function json_response(array $payload, int $statusCode = 200): void
  {
    if (!headers_sent()) {
      http_response_code($statusCode);
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store');
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $json = json_encode($payload, $flags);

    echo ($json !== false) ? $json : '{"success":false,"message":"JSON encode failed"}';
    exit;
  }
}

// ----------------------------
// CSRF (ชื่อให้สอดคล้องกัน + alias กันของเก่าพัง)
// ----------------------------
if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    app_session_start();
    $t = $_SESSION['_csrf'] ?? '';
    return is_string($t) ? $t : '';
  }
}

if (!function_exists('csrf_from_request')) {
  function csrf_from_request(): ?string
  {
    $t = $_POST['csrf'] ?? ($_GET['csrf'] ?? null);
    return is_string($t) ? $t : null;
  }
}

if (!function_exists('csrf_verify')) {
  function csrf_verify(?string $token): bool
  {
    app_session_start();

    $sessionToken = $_SESSION['_csrf'] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '') return false;
    if (!is_string($token) || $token === '') return false;

    return hash_equals($sessionToken, $token);
  }
}

if (!function_exists('csrf_require')) {
  function csrf_require(?string $token = null): void
  {
    $token = $token ?? csrf_from_request();
    if (csrf_verify($token)) return;

    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $xrw    = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

    if (stripos($accept, 'application/json') !== false || strtolower($xrw) === 'xmlhttprequest') {
      json_response(['success' => false, 'message' => 'CSRF ไม่ถูกต้อง'], 419);
    }

    flash('error', 'CSRF ไม่ถูกต้อง');
    redirect($_SERVER['HTTP_REFERER'] ?? '?page=home');
  }
}

/**
 * Alias เพื่อรองรับโค้ดเดิม (เช่น payment.php เรียก verify_csrf)
 * Intelephense จะไม่ร้องแล้ว
 */
if (!function_exists('verify_csrf')) {
  function verify_csrf(?string $token): bool
  {
    return csrf_verify($token);
  }
}

if (!function_exists('verify_csrf_token')) {
  function verify_csrf_token(?string $token): bool
  {
    return csrf_verify($token);
  }
}

if (!function_exists('request_csrf_token')) {
  function request_csrf_token(): ?string
  {
    return csrf_from_request();
  }
}


// ----------------------------
// Router utilities (ใช้ใน index.php)
// ----------------------------
if (!function_exists('resolve_page')) {
  function resolve_page($pageParam, array $routes): string
  {
    $raw = is_string($pageParam) ? $pageParam : 'home';
    $raw = strtolower($raw);

    if (!preg_match('/^[a-z0-9_]+$/', $raw)) {
      return 'home';
    }
    return array_key_exists($raw, $routes) ? $raw : 'home';
  }
}

if (!function_exists('build_page_css')) {
  function build_page_css(array $route): array
  {
    $pageCss = [];
    $cssList = $route['css'] ?? [];
    if (!is_array($cssList)) return $pageCss;

    foreach ($cssList as $cssFile) {
      if (!is_string($cssFile) || $cssFile === '') continue;
      $pageCss[] = '/css/' . ltrim($cssFile, '/');
    }
    return $pageCss;
  }
}

if (!function_exists('build_page_js')) {
  function build_page_js(array $route): array
  {
    $pageJs = [];
    $jsList = $route['js'] ?? [];
    if (!is_array($jsList)) return $pageJs;

    foreach ($jsList as $jsFile) {
      if (!is_string($jsFile) || $jsFile === '') continue;
      $pageJs[] = '/js/' . ltrim($jsFile, '/');
    }
    return $pageJs;
  }
}


if (!function_exists('guard_route')) {
  function guard_route(array $route): void
  {
    // auth
    if (!empty($route['auth']) && !is_authenticated()) {
      flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าหน้านี้');
      store_old_input($_GET);
      redirect('?page=signin');
    }

    // guest_only
    if (!empty($route['guest_only']) && is_authenticated()) {
      redirect('?page=home');
    }

    // admin
    if (!empty($route['admin']) && !is_admin()) {
      flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
      redirect('?page=home');
    }
  }
}

// ----------------------------
// Logout utilities
// ----------------------------
if (!function_exists('is_logout_request')) {
  function is_logout_request(): bool
  {
    // Only POST action=logout
    return (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
      && (($_POST['action'] ?? '') === 'logout');
  }
}

if (!function_exists('handle_logout')) {
  function handle_logout(): void
  {
    app_session_start();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();

      // PHP 7.3+ รองรับ array options (มี samesite)
      if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        setcookie(session_name(), '', [
          'expires'  => time() - 42000,
          'path'     => $params['path'] ?? '/',
          'domain'   => $params['domain'] ?? '',
          'secure'   => (bool)($params['secure'] ?? false),
          'httponly' => (bool)($params['httponly'] ?? true),
          'samesite' => $params['samesite'] ?? 'Lax',
        ]);
      } else {
        setcookie(
          session_name(),
          '',
          time() - 42000,
          $params['path'] ?? '/',
          $params['domain'] ?? '',
          (bool)($params['secure'] ?? false),
          (bool)($params['httponly'] ?? true)
        );
        @ini_set('session.cookie_samesite', 'Lax');
      }
    }


    session_destroy();

    app_log('user_logout', [
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    redirect('?page=home');
  }
}
