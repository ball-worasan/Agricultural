<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/UserService.php';

app_session_start();

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') {
    flash('error', 'Token ไม่ถูกต้อง');
    redirect('?page=forgot_password');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$error = '';
$success = '';

if ($method === 'POST') {
    csrf_require();
    
    $newPassword = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirm'] ?? '');
    
    if ($newPassword === '' || strlen($newPassword) < 8) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } else {
        if (UserService::resetPassword($token, $newPassword)) {
            flash('success', 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
            redirect('?page=signin');
        } else {
            $error = 'Token ไม่ถูกต้องหรือหมดอายุแล้ว';
        }
    }
}

?>
<div class="reset-password-container">
    <div class="reset-password-wrapper">
        <div class="reset-password-content">
            <div class="reset-password-header">
                <h1>รีเซ็ตรหัสผ่าน</h1>
                <p>กรุณากรอกรหัสผ่านใหม่</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error); ?></div>
            <?php endif; ?>

            <form action="?page=reset_password&token=<?= e($token); ?>" method="POST" class="reset-password-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="password">รหัสผ่านใหม่</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)"
                        required
                        minlength="8"
                        autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="password_confirm">ยืนยันรหัสผ่าน</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        placeholder="กรอกรหัสผ่านอีกครั้ง"
                        required
                        minlength="8"
                        autocomplete="new-password">
                </div>

                <button type="submit" class="btn-submit">รีเซ็ตรหัสผ่าน</button>
            </form>

            <div class="reset-password-footer">
                <p><a href="?page=signin">กลับไปเข้าสู่ระบบ</a></p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('password_confirm')?.addEventListener('input', function() {
    const password = document.getElementById('password');
    const confirm = this;
    
    if (password.value !== confirm.value) {
        confirm.setCustomValidity('รหัสผ่านไม่ตรงกัน');
    } else {
        confirm.setCustomValidity('');
    }
});
</script>

