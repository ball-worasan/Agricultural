<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 2));
}

$databaseFile = APP_PATH . '/config/database.php';
if (!is_file($databaseFile)) {
  app_log('signin_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('signin_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
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
  app_log('signin_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสถานะล็อกอิน
// ----------------------------
if (is_authenticated()) {
  redirect('?page=home');
}

// ----------------------------
// จำกัดความถี่การเข้าสู่ระบบ
// ----------------------------
if (!class_exists('LoginRateLimiter')) {
  class LoginRateLimiter
  {
    private const SESSION_ATTEMPTS = 'login_attempts';
    private const SESSION_WINDOW_START = 'login_window_start';

    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    private function resetIfExpired(int $now): void
    {
      $windowStart = isset($_SESSION[self::SESSION_WINDOW_START])
        ? (int) $_SESSION[self::SESSION_WINDOW_START]
        : 0;

      // ถ้ายังไม่เคยเริ่ม window หรือ window หมดอายุ -> reset
      if ($windowStart <= 0 || ($now - $windowStart) >= self::WINDOW_SECONDS) {
        $_SESSION[self::SESSION_WINDOW_START] = $now;
        $_SESSION[self::SESSION_ATTEMPTS] = 0;
      }
    }

    public function canAttempt(): bool
    {
      $now = time();
      $this->resetIfExpired($now);

      $attempts = isset($_SESSION[self::SESSION_ATTEMPTS])
        ? (int) $_SESSION[self::SESSION_ATTEMPTS]
        : 0;

      return $attempts < self::MAX_ATTEMPTS;
    }

    public function registerFailedAttempt(): void
    {
      $now = time();
      $this->resetIfExpired($now);

      $current = isset($_SESSION[self::SESSION_ATTEMPTS])
        ? (int) $_SESSION[self::SESSION_ATTEMPTS]
        : 0;

      $_SESSION[self::SESSION_ATTEMPTS] = $current + 1;

      // ถ้าเพิ่งเริ่ม window ให้ set start
      if (empty($_SESSION[self::SESSION_WINDOW_START])) {
        $_SESSION[self::SESSION_WINDOW_START] = $now;
      }
    }

    public function reset(): void
    {
      // login ผ่าน -> เคลียร์ทุกอย่าง
      unset($_SESSION[self::SESSION_ATTEMPTS], $_SESSION[self::SESSION_WINDOW_START]);
    }

    public function remainingWaitSeconds(): int
    {
      $now = time();
      $this->resetIfExpired($now);

      $attempts = isset($_SESSION[self::SESSION_ATTEMPTS])
        ? (int) $_SESSION[self::SESSION_ATTEMPTS]
        : 0;

      if ($attempts < self::MAX_ATTEMPTS) {
        return 0;
      }

      $windowStart = isset($_SESSION[self::SESSION_WINDOW_START])
        ? (int) $_SESSION[self::SESSION_WINDOW_START]
        : 0;

      $elapsed = $now - $windowStart;
      $remain = self::WINDOW_SECONDS - $elapsed;

      return $remain > 0 ? $remain : 0;
    }
  }
}

// ----------------------------
// ที่เก็บข้อมูลผู้ใช้
// ----------------------------
if (!class_exists('UserRepository')) {
  class UserRepository
  {
    public function findByUsername(string $username): ?array
    {
      $user = Database::fetchOne(
        'SELECT user_id AS id, username, full_name, role, password
                 FROM users
                 WHERE username = ?
                 LIMIT 1',
        [$username]
      );

      return $user ?: null;
    }

    public function updateLastLogin(int $userId): void
    {
      // Schema ไม่มี last_login_at column ข้ามไป
      // ถ้าต้องการเพิ่มได้ใน migration ต่อไป
    }
  }
}

// ----------------------------
// บริการยืนยันตัวตน
// ----------------------------
if (!class_exists('AuthService')) {
  class AuthService
  {
    private UserRepository $users;
    private LoginRateLimiter $rateLimiter;

    public function __construct(UserRepository $users, LoginRateLimiter $rateLimiter)
    {
      $this->users = $users;
      $this->rateLimiter = $rateLimiter;
    }

    /**
     * @return array{success:bool,message:?string,user:?array}
     */
    public function login(string $username, string $password): array
    {
      $username = trim($username);

      if ($username === '' || $password === '') {
        return [
          'success' => false,
          'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน',
          'user'    => null,
        ];
      }

      // เบสิก validate username (ไม่ leak รายละเอียด)
      if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $this->rateLimiter->registerFailedAttempt();
        return [
          'success' => false,
          'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
          'user'    => null,
        ];
      }

      try {
        $user = $this->users->findByUsername($username);
      } catch (Throwable $e) {
        app_log('login_query_error', ['error' => $e->getMessage()]);
        return [
          'success' => false,
          'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง',
          'user'    => null,
        ];
      }

      if (!$user || !isset($user['password']) || !password_verify($password, (string)$user['password'])) {
        $this->rateLimiter->registerFailedAttempt();

        return [
          'success' => false,
          'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
          'user'    => null,
        ];
      }

      // ผ่านแล้ว reset attempt
      $this->rateLimiter->reset();

      return [
        'success' => true,
        'message' => null,
        'user'    => $user,
      ];
    }
  }
}

// ----------------------------
// ตั้งค่าบริการ
// ----------------------------
$rateLimiter = new LoginRateLimiter();
$userRepo = new UserRepository();
$auth = new AuthService($userRepo, $rateLimiter);

// ----------------------------
// รับคำขอ POST (PRG)
// ----------------------------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
  $password = isset($_POST['password']) ? trim((string) $_POST['password']) : '';

  store_old_input(['username' => $username]);

  if (!$rateLimiter->canAttempt()) {
    $wait = $rateLimiter->remainingWaitSeconds();
    $wait = $wait > 0 ? $wait : 10;

    flash('error', 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที');
    redirect('?page=signin');
  }

  $result = $auth->login($username, $password);

  if (!$result['success']) {
    $message = isset($result['message']) && $result['message'] !== ''
      ? $result['message']
      : 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';

    flash('error', $message);

    $usernameLog = mb_substr(trim($username), 0, 50);
    app_log('signin_failed', [
      'username' => preg_replace('/[^a-zA-Z0-9_]/', '', $usernameLog),
      'reason' => 'invalid_credentials',
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    redirect('?page=signin');
  }

  $user = $result['user'] ?? null;
  if (!is_array($user) || empty($user['id'])) {
    flash('error', 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ');
    redirect('?page=signin');
  }

  // ป้องกัน session fixation
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }

  $_SESSION['user'] = [
    'id'        => (int)$user['id'],
    'username'  => (string)($user['username'] ?? ''),
    'full_name' => (string)($user['full_name'] ?? ''),
    'role'      => (int)($user['role'] ?? ROLE_MEMBER),
  ];

  $userRepo->updateLastLogin((int) $user['id']);

  $_SESSION['_old_input'] = [];

  app_log('signin_success', [
    'user_id'  => (int)$user['id'],
    'username' => (string)($user['username'] ?? ''),
    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
  ]);

  flash('success', 'เข้าสู่ระบบสำเร็จ');
  redirect('?page=home');
}

// ----------------------------
// เรนเดอร์หน้า
// ----------------------------
?>
<div class="signin-container" data-page="signin">

  <div class="signin-wrapper">
    <div class="signin-hero">
      <div class="hero-content">
        <h2>ยินดีต้อนรับ</h2>
        <p>เข้าสู่ระบบเพื่อติดต่อพื้นที่เกษตรของคุณ</p>
        <div class="hero-features">
          <div class="feature-item">✓ เข้าสู่ระบบได้ง่ายดาย</div>
          <div class="feature-item">✓ จัดการคุณของคุณอย่างปลอดภัย</div>
          <div class="feature-item">✓ ส่วนทำให้ล่าวเช่าของคุณ</div>
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
                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" style="display: none;">
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