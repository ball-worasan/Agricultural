<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/UserService.php';

app_session_start();

// ถ้าล็อกอินแล้ว redirect
if (is_authenticated()) {
    redirect('?page=home');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$error = '';
$success = '';

if ($method === 'POST') {
    csrf_require();
    
    $email = trim((string) ($_POST['email'] ?? ''));
    
    if ($email === '') {
        $error = 'กรุณากรอกอีเมล';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } else {
        $token = UserService::createPasswordResetToken($email);
        
        if ($token) {
            // ในระบบจริงควรส่งอีเมล แต่ตอนนี้แค่แสดง token (สำหรับทดสอบ)
            $resetLink = "?page=reset_password&token={$token}";
            $success = "ลิงก์รีเซ็ตรหัสผ่าน: <a href=\"{$resetLink}\">คลิกที่นี่</a><br><small>ในระบบจริงจะส่งลิงก์ไปยังอีเมลของคุณ</small>";
            
            app_log('password_reset_requested', ['email' => $email]);
        } else {
            // ไม่บอกว่า email ไม่มีในระบบ
            $success = 'หากอีเมลนี้มีในระบบ จะส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณ';
        }
    }
}

?>
<div class="forgot-password-container">
    <div class="forgot-password-wrapper">
        <div class="forgot-password-content">
            <div class="forgot-password-header">
                <h1>ลืมรหัสผ่าน</h1>
                <p>กรุณากรอกอีเมลที่ใช้สมัครสมาชิก</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success; ?></div>
            <?php endif; ?>

            <form action="?page=forgot_password" method="POST" class="forgot-password-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="email">อีเมล</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="กรอกอีเมล"
                        required
                        autocomplete="email"
                        value="<?= old('email'); ?>">
                </div>

                <button type="submit" class="btn-submit">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
            </form>

            <div class="forgot-password-footer">
                <p><a href="?page=signin">กลับไปเข้าสู่ระบบ</a></p>
            </div>
        </div>
    </div>
</div>

