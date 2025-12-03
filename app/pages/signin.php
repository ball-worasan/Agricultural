<?php

declare(strict_types=1);

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ถ้ามีคนเรียกไฟล์นี้ตรง ๆ นอก router
if (is_authenticated()) {
    redirect('?page=home');
}

/**
 * brute-force protection แบบง่าย ด้วย session
 */
if (!class_exists('LoginRateLimiter')) {
    class LoginRateLimiter
    {
        private const SESSION_ATTEMPTS     = 'login_attempts';
        private const SESSION_LAST_ATTEMPT = 'last_login_attempt';
        private const MAX_ATTEMPTS   = 5;
        private const WINDOW_SECONDS = 60; // 1 นาที

        public function canAttempt(): bool
        {
            $attempts = isset($_SESSION[self::SESSION_ATTEMPTS])
                ? (int) $_SESSION[self::SESSION_ATTEMPTS]
                : 0;

            $last = isset($_SESSION[self::SESSION_LAST_ATTEMPT])
                ? (int) $_SESSION[self::SESSION_LAST_ATTEMPT]
                : 0;

            $now = time();

            if ($attempts >= self::MAX_ATTEMPTS && ($now - $last) < self::WINDOW_SECONDS) {
                return false;
            }

            return true;
        }

        public function registerFailedAttempt(): void
        {
            $current = isset($_SESSION[self::SESSION_ATTEMPTS])
                ? (int) $_SESSION[self::SESSION_ATTEMPTS]
                : 0;

            $_SESSION[self::SESSION_ATTEMPTS]     = $current + 1;
            $_SESSION[self::SESSION_LAST_ATTEMPT] = time();
        }

        public function reset(): void
        {
            $_SESSION[self::SESSION_ATTEMPTS]     = 0;
            $_SESSION[self::SESSION_LAST_ATTEMPT] = time();
        }

        public function remainingWaitSeconds(): int
        {
            $attempts = isset($_SESSION[self::SESSION_ATTEMPTS])
                ? (int) $_SESSION[self::SESSION_ATTEMPTS]
                : 0;

            $last = isset($_SESSION[self::SESSION_LAST_ATTEMPT])
                ? (int) $_SESSION[self::SESSION_LAST_ATTEMPT]
                : 0;

            $now = time();

            if ($attempts < self::MAX_ATTEMPTS) {
                return 0;
            }

            $diff = $now - $last;
            if ($diff >= self::WINDOW_SECONDS) {
                return 0;
            }

            return self::WINDOW_SECONDS - $diff;
        }
    }
}

/**
 * จัดการอ่านข้อมูล user จาก DB
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
                // ไม่ต้องทำอะไรต่อ ปล่อยเงียบ แค่ log
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
        /** @var UserRepository */
        private $users;

        /** @var LoginRateLimiter */
        private $rateLimiter;

        public function __construct(UserRepository $users, LoginRateLimiter $rateLimiter)
        {
            $this->users       = $users;
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

            // รูปแบบ username เบสิก (ไม่ leak ว่าผิด pattern หรือไม่)
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

            if (!$user || !password_verify($password, $user['password'])) {
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
$userRepo    = new UserRepository();
$auth        = new AuthService($userRepo, $rateLimiter);

// ---------- Controller: POST → PRG ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // เก็บ input ก่อนเผื่อ redirect กลับ
    store_old_input($_POST);

    if (!$rateLimiter->canAttempt()) {
        $wait = $rateLimiter->remainingWaitSeconds();
        $wait = $wait > 0 ? $wait : 10;

        $msg = 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที';
        flash('error', $msg);
        redirect('?page=signin');
    }

    // CSRF verification removed per request

    $username = isset($_POST['username']) ? (string) $_POST['username'] : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $result = $auth->login($username, $password);

    if (!$result['success']) {
        $message = isset($result['message']) && $result['message'] !== ''
            ? $result['message']
            : 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';

        flash('error', $message);

        // log เมื่อ login fail
        app_log('login_failed', [
            'username' => $username,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        redirect('?page=signin');
    }

    // success
    $user = $result['user'];

    // ป้องกัน session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'firstname' => $user['firstname'],
        'lastname'  => $user['lastname'],
        'role'      => $user['role'],
    ];

    // update last_login_at
    $userRepo->updateLastLogin((int) $user['id']);

    // ล้าง old input ไม่ให้ติดหน้าอื่น
    $_SESSION['_old_input'] = [];

    // log success
    app_log('login_success', [
        'user_id'  => $user['id'],
        'username' => $user['username'],
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
                <p>ยินดีต้อนรับกลับสู่พื้นที่เกษตรของศิรินาถ</p>
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
                        inputs[i].disabled = true;
                    }
                }, 100);
            });
        }
    })();
</script>