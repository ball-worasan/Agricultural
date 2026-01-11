<?php

declare(strict_types=1);

/**
 * Signin page (controller + view)
 * Assumes bootstrap already loaded helpers + database and session is available.
 */

app_session_start();

// Already logged in
if (is_authenticated()) {
  redirect('?page=home', 303);
}

// -----------------------------------------------------------------------------
// Small helpers
// -----------------------------------------------------------------------------
$redirectSignin = static function (string $qs = ''): void {
  $url = '?page=signin' . ($qs !== '' ? '&' . ltrim($qs, '&') : '');
  redirect($url, 303);
};

$failGeneric = static function (): void {
  flash('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
};

$csrfRequire = static function (): void {
  $token = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($token)) {
    app_log('signin_csrf_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    flash('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
    redirect('?page=signin', 303);
  }
};

$sessionRegenerateSafe = static function (): void {
  if (session_status() !== PHP_SESSION_ACTIVE) return;
  // โปรเจกต์คุณเคยเรียก session_regenerate_safe() แต่ไม่มีจริง -> กัน fatal
  @session_regenerate_id(true);
};

// -----------------------------------------------------------------------------
// Login Rate Limiter (namespaced to this page)
// -----------------------------------------------------------------------------
if (!class_exists('SigninLoginRateLimiter')) {
  class SigninLoginRateLimiter
  {
    private const SESSION_ATTEMPTS = 'signin_login_attempts';
    private const SESSION_WINDOW_START = 'signin_login_window_start';

    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    private function resetIfExpired(int $now): void
    {
      $start = isset($_SESSION[self::SESSION_WINDOW_START]) ? (int)$_SESSION[self::SESSION_WINDOW_START] : 0;
      if ($start <= 0 || ($now - $start) >= self::WINDOW_SECONDS) {
        $_SESSION[self::SESSION_WINDOW_START] = $now;
        $_SESSION[self::SESSION_ATTEMPTS] = 0;
      }
    }

    public function canAttempt(): bool
    {
      $now = time();
      $this->resetIfExpired($now);
      $attempts = isset($_SESSION[self::SESSION_ATTEMPTS]) ? (int)$_SESSION[self::SESSION_ATTEMPTS] : 0;
      return $attempts < self::MAX_ATTEMPTS;
    }

    public function registerFailedAttempt(): void
    {
      $now = time();
      $this->resetIfExpired($now);

      $current = isset($_SESSION[self::SESSION_ATTEMPTS]) ? (int)$_SESSION[self::SESSION_ATTEMPTS] : 0;
      $_SESSION[self::SESSION_ATTEMPTS] = $current + 1;

      if (empty($_SESSION[self::SESSION_WINDOW_START])) {
        $_SESSION[self::SESSION_WINDOW_START] = $now;
      }
    }

    public function reset(): void
    {
      unset($_SESSION[self::SESSION_ATTEMPTS], $_SESSION[self::SESSION_WINDOW_START]);
    }

    public function remainingWaitSeconds(): int
    {
      $now = time();
      $this->resetIfExpired($now);

      $attempts = isset($_SESSION[self::SESSION_ATTEMPTS]) ? (int)$_SESSION[self::SESSION_ATTEMPTS] : 0;
      if ($attempts < self::MAX_ATTEMPTS) return 0;

      $start = isset($_SESSION[self::SESSION_WINDOW_START]) ? (int)$_SESSION[self::SESSION_WINDOW_START] : 0;
      $remain = self::WINDOW_SECONDS - ($now - $start);
      return $remain > 0 ? $remain : 0;
    }
  }
}

// -----------------------------------------------------------------------------
// Repository + Auth service (namespaced)
// -----------------------------------------------------------------------------
if (!class_exists('SigninUserRepository')) {
  class SigninUserRepository
  {
    public function findByUsername(string $username): ?array
    {
      $row = Database::fetchOne(
        'SELECT user_id AS id, username, full_name, role, password
           FROM users
          WHERE username = ?
          LIMIT 1',
        [$username]
      );

      return $row ?: null;
    }

    public function updateLastLogin(int $userId): void
    {
      // no-op (schema doesn't have last_login_at)
    }
  }
}

if (!class_exists('SigninAuthService')) {
  class SigninAuthService
  {
    public function __construct(
      private SigninUserRepository $users,
      private SigninLoginRateLimiter $rateLimiter
    ) {}

    /** @return array{success:bool,message:?string,user:?array} */
    public function login(string $username, string $password): array
    {
      $username = trim($username);
      $password = (string)$password;

      if ($username === '' || $password === '') {
        return ['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'user' => null];
      }

      // basic validation (avoid weird chars + reduce load)
      if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $this->rateLimiter->registerFailedAttempt();
        return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'user' => null];
      }

      try {
        $user = $this->users->findByUsername($username);
      } catch (Throwable $e) {
        app_log('signin_query_error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง', 'user' => null];
      }

      if (!$user || !isset($user['password']) || !password_verify($password, (string)$user['password'])) {
        $this->rateLimiter->registerFailedAttempt();
        return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'user' => null];
      }

      $this->rateLimiter->reset();
      return ['success' => true, 'message' => null, 'user' => $user];
    }
  }
}

// -----------------------------------------------------------------------------
// Controller (POST -> PRG)
// -----------------------------------------------------------------------------
$rateLimiter = new SigninLoginRateLimiter();
$userRepo = new SigninUserRepository();
$auth = new SigninAuthService($userRepo, $rateLimiter);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $csrfRequire();

  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  store_old_input(['username' => $username]);

  if (!$rateLimiter->canAttempt()) {
    $wait = $rateLimiter->remainingWaitSeconds();
    $wait = $wait > 0 ? $wait : 10;

    flash('error', 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที');
    $redirectSignin();
  }

  $result = $auth->login($username, $password);

  if (!$result['success']) {
    // ใช้ข้อความ generic เป็น default
    $msg = (string)($result['message'] ?? '');
    if ($msg === '') $msg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    flash('error', $msg);

    $usernameLog = mb_substr($username, 0, 50);
    app_log('signin_failed', [
      'username' => preg_replace('/[^a-zA-Z0-9_]/', '', $usernameLog),
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $redirectSignin();
  }

  $user = $result['user'] ?? null;
  if (!is_array($user) || empty($user['id'])) {
    flash('error', 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ');
    $redirectSignin();
  }

  // harden session after login
  $sessionRegenerateSafe();
  if (function_exists('csrf_rotate')) {
    csrf_rotate();
  }

  $_SESSION['user'] = [
    'id'        => (int)$user['id'],
    'username'  => (string)($user['username'] ?? ''),
    'full_name' => (string)($user['full_name'] ?? ''),
    'role'      => (int)($user['role'] ?? ROLE_MEMBER),
  ];

  $userRepo->updateLastLogin((int)$user['id']);
  $_SESSION['_old_input'] = [];

  app_log('signin_success', [
    'user_id'  => (int)$user['id'],
    'username' => (string)($user['username'] ?? ''),
    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
  ]);

  flash('success', 'เข้าสู่ระบบสำเร็จ');
  redirect('?page=home', 303);
}

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<div class="signin-container" data-page="signin">
  <div class="signin-wrapper">
    <div class="signin-hero">
      <div class="hero-content">
        <h2>ยินดีต้อนรับ</h2>
        <p>เข้าสู่ระบบเพื่อจัดการพื้นที่เกษตรของคุณ</p>
        <div class="hero-features">
          <div class="feature-item">✓ เข้าสู่ระบบได้ง่าย</div>
          <div class="feature-item">✓ จัดการข้อมูลอย่างปลอดภัย</div>
          <div class="feature-item">✓ ดูและจัดการรายการพื้นที่ของคุณ</div>
        </div>
      </div>
    </div>

    <div class="signin-form-wrapper">
      <div class="signin-content">
        <div class="signin-header">
          <h1>เข้าสู่ระบบ</h1>
          <p>ยินดีต้อนรับกลับสู่พื้นที่เกษตรของสิริณัฐ</p>
        </div>

        <form action="?page=signin" method="POST" class="signin-form" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrf); ?>">

          <div class="form-group">
            <label for="username">ชื่อผู้ใช้</label>
            <input
              type="text"
              id="username"
              name="username"
              placeholder="กรอกชื่อผู้ใช้"
              required
              value="<?= e(old('username')); ?>"
              autocomplete="username"
              pattern="[a-zA-Z0-9_]{3,20}"
              title="ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร (a-z, A-Z, 0-9, _ เท่านั้น)">
          </div>

          <div class="form-group">
            <label for="password">รหัสผ่าน</label>
            <div class="password-input-wrapper">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="กรอกรหัสผ่าน"
                required
                autocomplete="current-password">
              <button type="button" class="toggle-password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-signin">เข้าสู่ระบบ</button>
        </form>

        <div class="signin-footer">
          <p>ยังไม่มีบัญชี? <a href="?page=signup">สมัครสมาชิก</a></p>
        </div>
      </div>
    </div>
  </div>
</div>