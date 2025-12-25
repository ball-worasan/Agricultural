<?php

declare(strict_types=1);

// ----------------------------
// โหลดไฟล์แบบกันพลาด
// ----------------------------
if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 2));
}

$databaseFile = APP_PATH . '/config/Database.php';
if (!is_file($databaseFile)) {
  app_log('signup_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('signup_helpers_file_missing', ['file' => $helpersFile]);
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
  app_log('signup_session_error', ['error' => $e->getMessage()]);
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

if (!class_exists('RegistrationRateLimiter')) {
  class RegistrationRateLimiter
  {
    private const SESSION_KEY    = 'signup_attempts';
    private const WINDOW_KEY     = 'signup_window_started_at';
    private const MAX_ATTEMPTS   = 5;
    private const WINDOW_SECONDS = 300; // 5 นาที

    public function canAttempt(): bool
    {
      $attempts = isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : 0;
      $start    = isset($_SESSION[self::WINDOW_KEY]) ? (int) $_SESSION[self::WINDOW_KEY] : 0;
      $now      = time();

      // เริ่มหน้าต่างใหม่
      if ($start === 0 || ($now - $start) > self::WINDOW_SECONDS) {
        $_SESSION[self::SESSION_KEY] = 0;
        $_SESSION[self::WINDOW_KEY]  = $now;
        return true;
      }

      return $attempts < self::MAX_ATTEMPTS;
    }

    public function registerFailedAttempt(): void
    {
      $attempts = isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : 0;
      $_SESSION[self::SESSION_KEY] = $attempts + 1;

      if (empty($_SESSION[self::WINDOW_KEY])) {
        $_SESSION[self::WINDOW_KEY] = time();
      }
    }

    public function reset(): void
    {
      $_SESSION[self::SESSION_KEY]   = 0;
      $_SESSION[self::WINDOW_KEY]    = time();
    }

    public function remainingWaitSeconds(): int
    {
      $start = isset($_SESSION[self::WINDOW_KEY]) ? (int) $_SESSION[self::WINDOW_KEY] : 0;
      if ($start === 0) return 0;

      $now  = time();
      $diff = $now - $start;

      if ($diff >= self::WINDOW_SECONDS) return 0;

      return self::WINDOW_SECONDS - $diff;
    }
  }
}

if (!class_exists('SignupUserRepository')) {
  class SignupUserRepository
  {
    public function findDuplicate(string $username, string $phone): ?array
    {
      $sql    = 'SELECT username, phone FROM users WHERE username = ? OR phone = ?';
      $params = [$username, $phone];

      $row = Database::fetchOne($sql, $params);
      return $row ?: null;
    }

    public function createUser(array $data): bool
    {
      return Database::execute(
        'INSERT INTO users 
                    (username, password, full_name, address, phone, role) 
                 VALUES 
                    (?, ?, ?, ?, ?, ?)',
        [
          $data['username'],
          $data['password_hash'],
          $data['full_name'],
          $data['address'],
          $data['phone'],
          $data['role'] ?? 2,
        ]
      ) > 0;
    }
  }
}

if (!class_exists('RegistrationService')) {
  class RegistrationService
  {
    /** @var SignupUserRepository */
    private $users;

    public function __construct(SignupUserRepository $users)
    {
      $this->users = $users;
    }

    /**
     * @param array $input
     * @return array{success:bool,message:?string, normalized?:array}
     */
    public function register(array $input): array
    {
      $firstName = isset($input['firstname']) ? trim((string) $input['firstname']) : '';
      $lastName  = isset($input['lastname']) ? trim((string) $input['lastname']) : '';
      $fullName  = trim($firstName . ' ' . $lastName);
      $address   = isset($input['address']) ? trim((string) $input['address']) : '';

      $phoneRaw = isset($input['phone']) ? (string) $input['phone'] : '';
      $phone    = preg_replace('/\D/', '', $phoneRaw) ?? '';

      $username = isset($input['username']) ? trim((string) $input['username']) : '';

      $password        = isset($input['password']) ? trim((string) $input['password']) : '';
      $passwordConfirm = isset($input['password_confirm']) ? trim((string) $input['password_confirm']) : '';

      // required fields
      $missingFields = [];
      if ($firstName === '') $missingFields[] = 'ชื่อ';
      if ($lastName === '') $missingFields[] = 'นามสกุล';
      if ($username === '') $missingFields[] = 'ชื่อผู้ใช้';
      if ($password === '') $missingFields[] = 'รหัสผ่าน';
      if ($passwordConfirm === '') $missingFields[] = 'ยืนยันรหัสผ่าน';
      if ($address === '') $missingFields[] = 'ที่อยู่';
      if ($phone === '') $missingFields[] = 'เบอร์โทรศัพท์';

      if (!empty($missingFields)) {
        app_log('signup_validation_missing_fields', [
          'missing' => $missingFields,
          'received' => [
            'firstname' => $firstName !== '' ? 'ok' : 'empty',
            'lastname' => $lastName !== '' ? 'ok' : 'empty',
            'username' => $username !== '' ? 'ok' : 'empty',
            'password' => $password !== '' ? 'has_value' : 'empty',
            'password_confirm' => $passwordConfirm !== '' ? 'has_value' : 'empty',
            'address' => $address !== '' ? 'ok' : 'empty',
            'phone' => $phone !== '' ? 'ok' : 'empty',
          ]
        ]);
        return ['success' => false, 'message' => 'กรุณากรอก: ' . implode(', ', $missingFields)];
      }

      if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return ['success' => false, 'message' => 'ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร และประกอบด้วย a-z, A-Z, 0-9, _ เท่านั้น'];
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

      // duplicate check
      try {
        $duplicate = $this->users->findDuplicate($username, $phone);
      } catch (Throwable $e) {
        app_log('signup_duplicate_check_error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ กรุณาลองใหม่อีกครั้ง'];
      }

      if ($duplicate) {
        $fields = [];

        if (!empty($duplicate['username']) && $duplicate['username'] === $username) $fields[] = 'ชื่อผู้ใช้';
        if (!empty($duplicate['phone']) && preg_replace('/\D/', '', (string)$duplicate['phone']) === $phone) {
          $fields[] = 'เบอร์โทรศัพท์';
        }

        return [
          'success' => false,
          'message' => !empty($fields) ? implode(' หรือ ', $fields) . ' นี้ถูกใช้งานแล้ว' : 'ข้อมูลนี้ถูกใช้งานแล้ว',
        ];
      }

      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      if ($hashedPassword === false) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้ารหัสรหัสผ่าน'];
      }

      $data = [
        'full_name'     => $fullName,
        'address'       => $address,
        'phone'         => $phone,
        'username'      => $username,
        'password_hash' => $hashedPassword,
        'role'          => ROLE_MEMBER,
      ];

      try {
        Database::transaction(function () use ($data) {
          $ok = Database::execute(
            'INSERT INTO users 
                        (username, password, full_name, address, phone, role) 
                     VALUES 
                        (?, ?, ?, ?, ?, ?)',
            [
              $data['username'],
              $data['password_hash'],
              $data['full_name'],
              $data['address'],
              $data['phone'],
              $data['role'] ?? 2,
            ]
          );
          if ($ok <= 0) {
            throw new RuntimeException('insert_failed');
          }
        });
      } catch (Throwable $e) {
        app_log('signup_create_user_error', [
          'username' => $data['username'] ?? null,
          'error' => $e->getMessage(),
        ]);
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง'];
      }

      return [
        'success'    => true,
        'message'    => null,
        'normalized' => $data,
      ];
    }
  }
}

$rateLimiter = new RegistrationRateLimiter();
$userRepo    = new SignupUserRepository();
$service     = new RegistrationService($userRepo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  store_old_input([
    'firstname' => $_POST['firstname'] ?? '',
    'lastname'  => $_POST['lastname'] ?? '',
    'address'   => $_POST['address'] ?? '',
    'phone'     => $_POST['phone'] ?? '',
    'username'  => $_POST['username'] ?? '',
  ]);

  if (!$rateLimiter->canAttempt()) {
    $wait = $rateLimiter->remainingWaitSeconds();
    $wait = ($wait > 0) ? $wait : 30;

    flash('error', 'คุณพยายามสมัครสมาชิกหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที');
    redirect('?page=signup');
  }

  $result = $service->register($_POST);

  if (!$result['success']) {
    $rateLimiter->registerFailedAttempt();

    $message = (!empty($result['message'])) ? (string)$result['message'] : 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง';
    flash('error', $message);

    app_log('signup_failed', [
      'username' => $_POST['username'] ?? null,
      'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    redirect('?page=signup');
  }

  // success
  $rateLimiter->reset();
  $_SESSION['_old_input'] = [];

  app_log('signup_success', [
    'username' => $_POST['username'] ?? null,
    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
  ]);

  flash('success', 'สมัครสมาชิกสำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว');
  redirect('?page=signin');
}

?>
<div class="signup-container">
  <div class="signup-wrapper">
    <div class="signup-content">
      <div class="signup-header">
        <h1>สมัครสมาชิก</h1>
        <p>เริ่มต้นปล่อยเช่าหรือหาแปลงเกษตรที่เหมาะกับคุณ</p>
      </div>

      <form action="?page=signup" method="POST" class="signup-form" novalidate>
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
                  <line x1="1" y1="1" x2="23" y2="23"></line>
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