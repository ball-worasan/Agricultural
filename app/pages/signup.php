<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้งกรณีถูก include ผ่าน index.php และถูกเปิดตรง ๆ (dev)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2)); // จาก /app/public/pages → /app
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ถ้าล็อกอินอยู่แล้วไม่ต้องสมัครซ้ำ → เด้งกลับ home
if (is_authenticated()) {
    redirect('?page=home');
}

/**
 * จำกัดการสมัคร (กันสแปมง่าย ๆ)
 * - นับเฉพาะ "attempt ที่ fail" จะตรงความหมายกว่า
 * - ถ้าสมัครสำเร็จให้ reset ไม่งั้นสมัครถูก ๆ 5 รอบก็โดนบล็อกเอง (ตลกแต่เจ็บ)
 */
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

/**
 * User repository
 */
if (!class_exists('SignupUserRepository')) {
    class SignupUserRepository
    {
        public function findDuplicate(string $username, string $email, ?string $phone): ?array
        {
            $sql    = 'SELECT username, email, phone FROM users WHERE username = ? OR email = ?';
            $params = [$username, $email];

            if ($phone !== null && $phone !== '') {
                $sql .= ' OR phone = ?';
                $params[] = $phone;
            }

            $sql .= ' LIMIT 1';

            $row = Database::fetchOne($sql, $params);
            return $row ?: null;
        }

        public function createUser(array $data): bool
        {
            return Database::execute(
                'INSERT INTO users 
                    (username, email, password, firstname, lastname, address, phone, role, created_at) 
                 VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $data['username'],
                    $data['email'],
                    $data['password_hash'],
                    $data['firstname'],
                    $data['lastname'],
                    $data['address'],
                    ($data['phone'] !== '') ? $data['phone'] : null,
                    $data['role'] ?? 'member',
                ]
            ) > 0;
        }
    }
}

/**
 * Registration service
 */
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
            $firstname = isset($input['firstname']) ? trim((string) $input['firstname']) : '';
            $lastname  = isset($input['lastname']) ? trim((string) $input['lastname']) : '';
            $address   = isset($input['address']) ? trim((string) $input['address']) : '';

            $phoneRaw = isset($input['phone']) ? (string) $input['phone'] : '';
            $phone    = preg_replace('/\D/', '', $phoneRaw) ?? '';

            $username = isset($input['username']) ? trim((string) $input['username']) : '';
            $email    = isset($input['email']) ? strtolower(trim((string) $input['email'])) : '';

            $password        = isset($input['password']) ? (string) $input['password'] : '';
            $passwordConfirm = isset($input['password_confirm']) ? (string) $input['password_confirm'] : '';

            // required fields
            if ($firstname === '' || $lastname === '' || $username === '' || $email === '' || $password === '') {
                return ['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน'];
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                return ['success' => false, 'message' => 'ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร และประกอบด้วย a-z, A-Z, 0-9, _ เท่านั้น'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'];
            }

            if ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) {
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
                $dupPhone  = ($phone !== '') ? $phone : null;
                $duplicate = $this->users->findDuplicate($username, $email, $dupPhone);
            } catch (Throwable $e) {
                app_log('signup_duplicate_check_error', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ กรุณาลองใหม่อีกครั้ง'];
            }

            if ($duplicate) {
                $fields = [];

                if (!empty($duplicate['username']) && $duplicate['username'] === $username) $fields[] = 'ชื่อผู้ใช้';
                if (!empty($duplicate['email']) && strtolower((string)$duplicate['email']) === $email) $fields[] = 'อีเมล';
                if ($phone !== '' && !empty($duplicate['phone']) && preg_replace('/\D/', '', (string)$duplicate['phone']) === $phone) {
                    $fields[] = 'เบอร์โทรศัพท์';
                }

                return [
                    'success' => false,
                    'message' => !empty($fields) ? implode(' หรือ ', $fields) . 'นี้ถูกใช้งานแล้ว' : 'ข้อมูลนี้ถูกใช้งานแล้ว',
                ];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้ารหัสรหัสผ่าน'];
            }

            $data = [
                'firstname'     => $firstname,
                'lastname'      => $lastname,
                'address'       => $address,
                'phone'         => $phone,
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $hashedPassword,
                'role'          => 'member',
            ];

            try {
                $ok = $this->users->createUser($data);
                if (!$ok) {
                    return ['success' => false, 'message' => 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
                }
            } catch (Throwable $e) {
                app_log('signup_create_user_error', ['error' => $e->getMessage()]);
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

// ---------- Controller: POST → PRG ----------
$rateLimiter = new RegistrationRateLimiter();
$userRepo    = new SignupUserRepository();
$service     = new RegistrationService($userRepo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    store_old_input($_POST);

    if (!$rateLimiter->canAttempt()) {
        $wait = $rateLimiter->remainingWaitSeconds();
        $wait = ($wait > 0) ? $wait : 30;

        flash('error', 'คุณพยายามสมัครสมาชิกหลายครั้งเกินไป กรุณาลองใหม่อีกครั้งใน ' . $wait . ' วินาที');
        redirect('?page=signup');
    }

    // CSRF verification removed per request

    $result = $service->register($_POST);

    if (!$result['success']) {
        $rateLimiter->registerFailedAttempt();

        $message = (!empty($result['message'])) ? (string)$result['message'] : 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง';
        flash('error', $message);

        app_log('signup_failed', [
            'username' => $_POST['username'] ?? null,
            'email'    => $_POST['email'] ?? null,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        redirect('?page=signup');
    }

    // success
    $rateLimiter->reset();
    $_SESSION['_old_input'] = [];

    app_log('signup_success', [
        'username' => $_POST['username'] ?? null,
        'email'    => $_POST['email'] ?? null,
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
                            value="<?= old('firstname'); ?>" autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label for="lastname">นามสกุล</label>
                        <input type="text" id="lastname" name="lastname" placeholder="กรอกนามสกุล" required
                            value="<?= old('lastname'); ?>" autocomplete="family-name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">ที่อยู่ (เลือกกรอก)</label>
                    <input type="text" id="address" name="address" placeholder="กรอกที่อยู่ (ถ้ามี)"
                        value="<?= old('address'); ?>" autocomplete="street-address">
                </div>

                <div class="form-group">
                    <label for="phone">เบอร์โทรศัพท์ (เลือกกรอก)</label>
                    <input type="tel" id="phone" name="phone" placeholder="กรอกเบอร์โทรศัพท์"
                        value="<?= old('phone'); ?>" autocomplete="tel"
                        pattern="[0-9]{9,10}" title="กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก">
                </div>

                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required
                        value="<?= old('username'); ?>" autocomplete="username"
                        pattern="[a-zA-Z0-9_]{3,20}" minlength="3" maxlength="20"
                        title="ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร (a-z, A-Z, 0-9, _ เท่านั้น)">
                </div>

                <div class="form-group">
                    <label for="email">อีเมล</label>
                    <input type="email" id="email" name="email" placeholder="กรอกอีเมล" required
                        value="<?= old('email'); ?>" autocomplete="email">
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

<script>
    (function() {
        'use strict';

        var toggleButtons = document.querySelectorAll('.toggle-password');
        for (var i = 0; i < toggleButtons.length; i++) {
            (function(button) {
                var targetId = button.getAttribute('data-target');
                var input = document.getElementById(targetId);
                var eyeIcon = button.querySelector('.eye-icon');
                var eyeOffIcon = button.querySelector('.eye-off-icon');
                if (!input || !eyeIcon || !eyeOffIcon) return;

                button.addEventListener('click', function() {
                    var isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    eyeIcon.style.display = isPassword ? 'none' : 'block';
                    eyeOffIcon.style.display = isPassword ? 'block' : 'none';
                });
            })(toggleButtons[i]);
        }

        var signupForm = document.querySelector('.signup-form');
        var isSubmitting = false;

        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return;
                }

                var firstname = (document.getElementById('firstname')?.value || '').trim();
                var lastname = (document.getElementById('lastname')?.value || '').trim();
                var username = (document.getElementById('username')?.value || '').trim();
                var email = (document.getElementById('email')?.value || '').trim();
                var phone = (document.getElementById('phone')?.value || '').trim();
                var password = (document.getElementById('password')?.value || '');
                var passwordConfirm = (document.getElementById('password_confirm')?.value || '');

                if (!firstname || !lastname || !username || !email || !password) {
                    e.preventDefault();
                    alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                    return;
                }
                if (password.length < 6) {
                    e.preventDefault();
                    alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                    return;
                }
                if (password !== passwordConfirm) {
                    e.preventDefault();
                    alert('รหัสผ่านไม่ตรงกัน');
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    e.preventDefault();
                    alert('รูปแบบอีเมลไม่ถูกต้อง');
                    return;
                }
                if (phone && !/^[0-9]{9,10}$/.test(phone)) {
                    e.preventDefault();
                    alert('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)');
                    return;
                }
                if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                    e.preventDefault();
                    alert('ชื่อผู้ใช้ต้องมีความยาว 3-20 ตัวอักษร และประกอบด้วย a-z, A-Z, 0-9, _ เท่านั้น');
                    return;
                }

                isSubmitting = true;
                var submitBtn = signupForm.querySelector('.btn-signup');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="btn-loader"></span> กำลังสมัครสมาชิก...';
                }
            });
        }

        // limit เบอร์โทร
        var phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                var v = this.value.replace(/[^0-9]/g, '');
                if (v.length > 10) v = v.slice(0, 10);
                this.value = v;
            });
        }

        // limit username
        var usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                var v = this.value.replace(/[^a-zA-Z0-9_]/g, '');
                if (v.length > 20) v = v.slice(0, 20);
                this.value = v;
            });
        }
    })();
</script>