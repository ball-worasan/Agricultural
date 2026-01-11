<?php

declare(strict_types=1);

/**
 * Signup page (controller + view)
 * Assumes bootstrap already loaded helpers + database.
 */

app_session_start();

if (is_authenticated()) {
  redirect('?page=home', 303);
}

// -----------------------------------------------------------------------------
// Rate limiter (namespaced)
// -----------------------------------------------------------------------------
if (!class_exists('SignupRateLimiter')) {
  class SignupRateLimiter
  {
    private const SESSION_KEY    = 'signup_attempts';
    private const WINDOW_KEY     = 'signup_window_started_at';
    private const MAX_ATTEMPTS   = 5;
    private const WINDOW_SECONDS = 300;

    private function resetIfExpired(int $now): void
    {
      $start = isset($_SESSION[self::WINDOW_KEY]) ? (int)$_SESSION[self::WINDOW_KEY] : 0;
      if ($start === 0 || ($now - $start) > self::WINDOW_SECONDS) {
        $_SESSION[self::SESSION_KEY] = 0;
        $_SESSION[self::WINDOW_KEY]  = $now;
      }
    }

    public function canAttempt(): bool
    {
      $now = time();
      $this->resetIfExpired($now);
      $attempts = isset($_SESSION[self::SESSION_KEY]) ? (int)$_SESSION[self::SESSION_KEY] : 0;
      return $attempts < self::MAX_ATTEMPTS;
    }

    public function registerFailedAttempt(): void
    {
      $now = time();
      $this->resetIfExpired($now);
      $attempts = isset($_SESSION[self::SESSION_KEY]) ? (int)$_SESSION[self::SESSION_KEY] : 0;
      $_SESSION[self::SESSION_KEY] = $attempts + 1;
    }

    public function reset(): void
    {
      unset($_SESSION[self::SESSION_KEY], $_SESSION[self::WINDOW_KEY]);
    }

    public function remainingWaitSeconds(): int
    {
      $now = time();
      $this->resetIfExpired($now);

      $attempts = isset($_SESSION[self::SESSION_KEY]) ? (int)$_SESSION[self::SESSION_KEY] : 0;
      if ($attempts < self::MAX_ATTEMPTS) return 0;

      $start = isset($_SESSION[self::WINDOW_KEY]) ? (int)$_SESSION[self::WINDOW_KEY] : 0;
      $remain = self::WINDOW_SECONDS - ($now - $start);
      return $remain > 0 ? $remain : 0;
    }
  }
}

// -----------------------------------------------------------------------------
// Repository + Service (namespaced)
// -----------------------------------------------------------------------------
if (!class_exists('SignupRepository')) {
  class SignupRepository
  {
    public function findDuplicate(string $username, string $phone): ?array
    {
      // กันไว้: ถ้า phone ว่าง ให้เช็คเฉพาะ username
      if ($phone === '') {
        $row = Database::fetchOne(
          'SELECT username, phone FROM users WHERE username = ? LIMIT 1',
          [$username]
        );
        return $row ?: null;
      }

      $row = Database::fetchOne(
        'SELECT username, phone FROM users WHERE username = ? OR phone = ? LIMIT 1',
        [$username, $phone]
      );

      return $row ?: null;
    }

    public function createUser(array $data): void
    {
      $ok = Database::execute(
        'INSERT INTO users (username, password, full_name, address, phone, role)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
          $data['username'],
          $data['password_hash'],
          $data['full_name'],
          $data['address'],
          $data['phone'],
          $data['role'],
        ]
      );

      if ($ok <= 0) {
        throw new RuntimeException('insert_failed');
      }
    }
  }
}

if (!class_exists('SignupService')) {
  class SignupService
  {
    public function __construct(private SignupRepository $users) {}

    /** @return array{success:bool,message:?string, normalized?:array} */
    public function register(array $input): array
    {
      $firstName = trim((string)($input['firstname'] ?? ''));
      $lastName  = trim((string)($input['lastname'] ?? ''));
      $fullName  = trim($firstName . ' ' . $lastName);
      $address   = trim((string)($input['address'] ?? ''));

      $phoneRaw = (string)($input['phone'] ?? '');
      $phone    = preg_replace('/\D/', '', $phoneRaw) ?? '';

      $username = trim((string)($input['username'] ?? ''));

      $password        = (string)($input['password'] ?? '');
      $passwordConfirm = (string)($input['password_confirm'] ?? '');

      $missing = [];
      if ($firstName === '') $missing[] = 'ชื่อ';
      if ($lastName === '') $missing[] = 'นามสกุล';
      if ($username === '') $missing[] = 'ชื่อผู้ใช้';
      if ($password === '') $missing[] = 'รหัสผ่าน';
      if ($passwordConfirm === '') $missing[] = 'ยืนยันรหัสผ่าน';
      if ($address === '') $missing[] = 'ที่อยู่';
      if ($phone === '') $missing[] = 'เบอร์โทรศัพท์';

      if ($missing) {
        return ['success' => false, 'message' => 'กรุณากรอก: ' . implode(', ', $missing)];
      }

      if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return ['success' => false, 'message' => 'ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร และใช้ได้เฉพาะ a-z, A-Z, 0-9, _'];
      }

      if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
        return ['success' => false, 'message' => 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)'];
      }

      if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'รหัสผ่านไม่ตรงกัน'];
      }

      if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'];
      }

      try {
        $dup = $this->users->findDuplicate($username, $phone);
      } catch (Throwable $e) {
        app_log('signup_duplicate_check_error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ กรุณาลองใหม่อีกครั้ง'];
      }

      if ($dup) {
        $fields = [];
        if (!empty($dup['username']) && (string)$dup['username'] === $username) $fields[] = 'ชื่อผู้ใช้';
        if (!empty($dup['phone']) && preg_replace('/\D/', '', (string)$dup['phone']) === $phone) $fields[] = 'เบอร์โทรศัพท์';

        return ['success' => false, 'message' => $fields ? implode(' หรือ ', $fields) . ' นี้ถูกใช้งานแล้ว' : 'ข้อมูลนี้ถูกใช้งานแล้ว'];
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      if ($hash === false) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้ารหัสรหัสผ่าน'];
      }

      $data = [
        'full_name'     => $fullName,
        'address'       => $address,
        'phone'         => $phone,
        'username'      => $username,
        'password_hash' => $hash,
        'role'          => ROLE_MEMBER,
      ];

      try {
        Database::transaction(function () use ($data) {
          $this->users->createUser($data);
        });
      } catch (Throwable $e) {
        app_log('signup_create_user_error', ['username' => $data['username'], 'error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง'];
      }

      return ['success' => true, 'message' => null, 'normalized' => $data];
    }
  }
}

// -----------------------------------------------------------------------------
// Controller (POST -> PRG)
// -----------------------------------------------------------------------------
$rateLimiter = new SignupRateLimiter();
$service = new SignupService(new SignupRepository());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  store_old_input([
    'firstname' => (string)($_POST['firstname'] ?? ''),
    'lastname'  => (string)($_POST['lastname'] ?? ''),
    'address'   => (string)($_POST['address'] ?? ''),
    'phone'     => (string)($_POST['phone'] ?? ''),
    'username'  => (string)($_POST['username'] ?? ''),
  ]);

  $token = (string)($_POST['_csrf'] ?? '');
  if (!csrf_verify($token)) {
    app_log('signup_csrf_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    flash('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
    redirect('?page=signup', 303);
  }

  if (!$rateLimiter->canAttempt()) {
    $wait = $rateLimiter->remainingWaitSeconds();
    $wait = ($wait > 0) ? $wait : 30;

    flash('error', 'คุณพยายามสมัครสมาชิกหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที');
    redirect('?page=signup', 303);
  }

  $result = $service->register($_POST);

  if (!$result['success']) {
    $rateLimiter->registerFailedAttempt();

    $message = is_string($result['message']) && $result['message'] !== ''
      ? $result['message']
      : 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง';

    flash('error', $message);

    app_log('signup_failed', [
      'username' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_POST['username'] ?? '')),
      'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    redirect('?page=signup', 303);
  }

  $rateLimiter->reset();
  $_SESSION['_old_input'] = [];

  if (function_exists('csrf_rotate')) {
    csrf_rotate();
  }

  app_log('signup_success', [
    'username' => (string)($result['normalized']['username'] ?? ($_POST['username'] ?? null)),
    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
  ]);

  flash('success', 'สมัครสมาชิกสำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว');
  redirect('?page=signin', 303);
}

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
$csrf = csrf_token();

?>
<div class="signup-container">
  <div class="signup-wrapper">
    <div class="signup-hero">
      <div class="hero-content">
        <h2>สมัครสมาชิก</h2>
        <p>เป็นส่วนหนึ่งของชุมชนพื้นที่เกษตรแล้ววันนี้</p>
        <div class="hero-features">
          <div class="feature-item">✓ ปล่อยเช่าพื้นที่ของคุณ</div>
          <div class="feature-item">✓ ค้นหาแปลงเกษตรที่เหมาะสม</div>
          <div class="feature-item">✓ จัดการการจองได้อย่างง่ายดาย</div>
        </div>
      </div>
    </div>

    <div class="signup-form-wrapper">
      <div class="signup-content">
        <div class="signup-header">
          <h1>สมัครสมาชิก</h1>
          <p>เริ่มต้นปล่อยเช่าหรือหาแปลงเกษตรที่เหมาะกับคุณ</p>
        </div>

        <form action="?page=signup" method="POST" class="signup-form" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrf); ?>">

          <div class="form-row">
            <div class="form-group">
              <label for="firstname">ชื่อ</label>
              <input type="text" id="firstname" name="firstname" placeholder="กรอกชื่อ" required
                value="<?= e(old('firstname')); ?>" autocomplete="given-name">
            </div>
            <div class="form-group">
              <label for="lastname">นามสกุล</label>
              <input type="text" id="lastname" name="lastname" placeholder="กรอกนามสกุล" required
                value="<?= e(old('lastname')); ?>" autocomplete="family-name">
            </div>
          </div>

          <div class="form-group">
            <label for="address">ที่อยู่</label>
            <input type="text" id="address" name="address" placeholder="กรอกที่อยู่" required
              value="<?= e(old('address')); ?>" autocomplete="street-address">
          </div>

          <div class="form-group">
            <label for="phone">เบอร์โทรศัพท์</label>
            <input type="tel" id="phone" name="phone" placeholder="กรอกเบอร์โทรศัพท์" required
              value="<?= e(old('phone')); ?>" autocomplete="tel"
              pattern="[0-9]{9,10}" title="กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก">
          </div>

          <div class="form-group">
            <label for="username">ชื่อผู้ใช้</label>
            <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required
              value="<?= e(old('username')); ?>" autocomplete="username"
              pattern="[a-zA-Z0-9_]{3,20}" minlength="3" maxlength="20"
              title="ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร (a-z, A-Z, 0-9, _ เท่านั้น)">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="password">รหัสผ่าน</label>
              <div class="password-input-wrapper">
                <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required minlength="6" autocomplete="new-password">
                <button type="button" class="toggle-password" data-target="password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                  <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>

            <div class="form-group">
              <label for="password_confirm">ยืนยันรหัสผ่าน</label>
              <div class="password-input-wrapper">
                <input type="password" id="password_confirm" name="password_confirm" placeholder="ยืนยันรหัสผ่าน" required minlength="6" autocomplete="new-password">
                <button type="button" class="toggle-password" data-target="password_confirm" aria-label="แสดง/ซ่อนรหัสผ่าน">
                  <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y1="23"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-signup">สมัครสมาชิก</button>
        </form>

        <div class="signup-footer">
          <p>มีบัญชีอยู่แล้ว? <a href="?page=signin">เข้าสู่ระบบ</a></p>
        </div>
      </div>
    </div>
  </div>
</div>