<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้ง include และเปิดตรง ๆ (dev)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ถ้าล็อกอินแล้ว ไม่ต้องเข้าหน้านี้
if (is_authenticated()) {
    redirect('?page=home');
}

/**
 * brute-force protection แบบ session (ปรับให้ "window" เคลียร์จริง)
 */
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

/**
 * User repository
 */
if (!class_exists('UserRepository')) {
    class UserRepository
    {
        public function findByUsername(string $username): ?array
        {
            $user = Database::fetchOne(
                'SELECT id, username, email, firstname, lastname, role, password
                 FROM users
                 WHERE username = ?
                 LIMIT 1',
                [$username]
            );

            return $user ?: null;
        }

        public function updateLastLogin(int $userId): void
        {
            try {
                Database::execute(
                    'UPDATE users SET last_login_at = NOW() WHERE id = ?',
                    [$userId]
                );
            } catch (Throwable $e) {
                app_log('update_last_login_failed', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}

/**
 * Auth service
 */
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

$rateLimiter = new LoginRateLimiter();
$userRepo = new UserRepository();
$auth = new AuthService($userRepo, $rateLimiter);

// ---------- Controller: POST → PRG ----------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = isset($_POST['username']) ? (string) $_POST['username'] : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    // อย่า store_old_input ทั้งก้อน เพราะมันจะ “เก็บ password” ด้วย
    store_old_input([
        'username' => $username,
    ]);

    // CSRF (เอากลับมาเถอะ)
    csrf_require();

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

        app_log('login_failed', [
            'username' => $username,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
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
        'email'     => (string)($user['email'] ?? ''),
        'firstname' => (string)($user['firstname'] ?? ''),
        'lastname'  => (string)($user['lastname'] ?? ''),
        'role'      => (string)($user['role'] ?? 'user'),
    ];

    $userRepo->updateLastLogin((int) $user['id']);

    $_SESSION['_old_input'] = [];

    app_log('login_success', [
        'user_id'  => (int)$user['id'],
        'username' => (string)($user['username'] ?? ''),
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    flash('success', 'เข้าสู่ระบบสำเร็จ');
    redirect('?page=home');
}

// ---------- View ----------
?>
<div class="signin-container">
    <div class="signin-wrapper">
        <div class="signin-content">
            <div class="signin-header">
                <h1>เข้าสู่ระบบ</h1>
                <p>ยินดีต้อนรับกลับสู่พื้นที่เกษตรของสิริณัฐ</p>
            </div>

            <form action="?page=signin" method="POST" class="signin-form" novalidate>
                <!-- CSRF -->
                <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="กรอกชื่อผู้ใช้"
                        required
                        value="<?= old('username'); ?>"
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
                <p><a href="?page=forgot_password">ลืมรหัสผ่าน?</a></p>
            </div>

            <div class="demo-credentials">
                <small>สำหรับทดสอบ: demo / password123 (ถ้ามีสร้าง user นี้ไว้)</small>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';

        var passwordInput = document.getElementById('password');
        var toggleButton = document.querySelector('.toggle-password');
        var eyeIcon = document.querySelector('.eye-icon');
        var eyeOffIcon = document.querySelector('.eye-off-icon');
        var signinForm = document.querySelector('.signin-form');
        var usernameInput = document.getElementById('username');

        if (toggleButton && passwordInput && eyeIcon && eyeOffIcon) {
            toggleButton.addEventListener('click', function() {
                var isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                eyeIcon.style.display = isPassword ? 'none' : 'block';
                eyeOffIcon.style.display = isPassword ? 'block' : 'none';
            });
        }

        var isSubmitting = false;

        if (signinForm) {
            signinForm.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return;
                }

                var username = usernameInput ? usernameInput.value.trim() : '';
                var password = passwordInput ? passwordInput.value : '';

                if (!username || !password) {
                    e.preventDefault();
                    alert('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
                    return;
                }

                isSubmitting = true;
                var submitBtn = signinForm.querySelector('.btn-signin');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="btn-loader"></span> กำลังเข้าสู่ระบบ...';
                }

                setTimeout(function() {
                    var inputs = signinForm.querySelectorAll('input');
                    for (var i = 0; i < inputs.length; i++) {
                        // กัน user แก้ค่าระหว่างส่ง
                        inputs[i].disabled = true;
                    }
                }, 100);
            });
        }
    })();
</script>