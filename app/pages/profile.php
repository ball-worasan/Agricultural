<?php

declare(strict_types=1);

// ให้ไฟล์นี้ทำงานได้ทั้งกรณีถูก include ผ่าน index.php และถูกเปิดตรง ๆ (dev)
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2)); // จาก /app/public/pages → /app
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';

app_session_start();

// ตรวจสอบการล็อกอินด้วย helper กลาง
$currentUser = current_user();
if ($currentUser === null) {
    redirect('?page=signin');
}

$userId = (int) ($currentUser['id'] ?? 0);
if ($userId <= 0) {
    app_log('profile_invalid_user', ['session_user' => $currentUser]);
    redirect('?page=signin');
}

$message     = '';
$messageType = '';

// ---------- อัปโหลดรูปโปรไฟล์ ----------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['profile_image'])
    && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
) {
    $uploadDir = dirname(__DIR__, 2) . '/uploads/profiles/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        $message     = 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดรูปภาพได้';
        $messageType = 'error';
    } else {
        $file        = $_FILES['profile_image'];
        $allowedTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heif',
            'image/heic',
        ];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if ($file['error'] === UPLOAD_ERR_OK) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true) && !in_array($file['type'], $allowedTypes, true)) {
                $message     = 'อนุญาตเฉพาะไฟล์รูปภาพ (JPEG, PNG, GIF, WEBP, HEIF)';
                $messageType = 'error';
            } elseif ((int) $file['size'] > $maxSize) {
                $message     = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
                $messageType = 'error';
            } else {
                $extension   = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
                $newFileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // ลบรูปเดิมถ้ามี
                    $oldImage = Database::fetchOne('SELECT profile_image FROM users WHERE id = ?', [$userId]);

                    if (
                        $oldImage
                        && !empty($oldImage['profile_image'])
                        && strpos((string) $oldImage['profile_image'], 'ui-avatars.com') === false
                    ) {
                        $oldPath = dirname(__DIR__, 2) . $oldImage['profile_image'];
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    $imagePath = '/uploads/profiles/' . $newFileName;

                    Database::execute(
                        'UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?',
                        [$imagePath, $userId]
                    );

                    // redirect กัน refresh แล้วอัปโหลดซ้ำ
                    redirect('?page=profile&success=image');
                } else {
                    $message     = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (ไม่สามารถย้ายไฟล์ได้)';
                    $messageType = 'error';
                }
            }
        } else {
            $message     = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (error code: ' . (int) $file['error'] . ')';
            $messageType = 'error';
        }
    }
}

// ---------- เปลี่ยนรหัสผ่าน ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_new_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message     = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message     = 'รหัสผ่านใหม่ไม่ตรงกัน';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message     = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        $messageType = 'error';
    } else {
        try {
            $userRow = Database::fetchOne('SELECT password FROM users WHERE id = ?', [$userId]);

            if ($userRow && password_verify($currentPassword, (string) $userRow['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                Database::execute(
                    'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
                    [$hashedPassword, $userId]
                );

                $message     = 'เปลี่ยนรหัสผ่านสำเร็จ';
                $messageType = 'success';
            } else {
                $message     = 'รหัสผ่านเดิมไม่ถูกต้อง';
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            app_log('profile_change_password_error', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $message     = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
            $messageType = 'error';
        }
    }
}

// ---------- อัปเดตข้อมูลส่วนตัว ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim((string) ($_POST['firstname'] ?? ''));
    $lastname  = trim((string) ($_POST['lastname'] ?? ''));
    $address   = trim((string) ($_POST['address'] ?? ''));
    $phone     = trim((string) ($_POST['phone'] ?? ''));

    if ($firstname === '' || $lastname === '') {
        $message     = 'กรุณากรอกชื่อและนามสกุล';
        $messageType = 'error';
    } else {
        try {
            if ($phone !== '') {
                $existingPhone = Database::fetchOne(
                    'SELECT id FROM users WHERE phone = ? AND id != ?',
                    [$phone, $userId]
                );

                if ($existingPhone) {
                    $message     = 'เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว';
                    $messageType = 'error';
                } else {
                    Database::execute(
                        '
                        UPDATE users 
                        SET firstname = ?, lastname = ?, address = ?, phone = ?, updated_at = NOW() 
                        WHERE id = ?
                        ',
                        [$firstname, $lastname, $address, $phone, $userId]
                    );
                    $_SESSION['user']['firstname'] = $firstname;
                    $_SESSION['user']['lastname']  = $lastname;

                    $message     = 'อัปเดตข้อมูลสำเร็จ';
                    $messageType = 'success';
                }
            } else {
                Database::execute(
                    '
                    UPDATE users 
                    SET firstname = ?, lastname = ?, address = ?, phone = ?, updated_at = NOW() 
                    WHERE id = ?
                    ',
                    [$firstname, $lastname, $address, $phone, $userId]
                );
                $_SESSION['user']['firstname'] = $firstname;
                $_SESSION['user']['lastname']  = $lastname;

                $message     = 'อัปเดตข้อมูลสำเร็จ';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            app_log('profile_update_error', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $message     = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
            $messageType = 'error';
        }
    }
}

// ---------- ดึงข้อมูลผู้ใช้ล่าสุดจากฐานข้อมูล ----------
$user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);

if (!$user) {
    app_log('profile_user_not_found', ['user_id' => $userId]);
    unset($_SESSION['user']);
    redirect('?page=signin');
}

// สร้าง URL รูปโปรไฟล์
$profileImageUrl = !empty($user['profile_image'])
    ? (string) $user['profile_image']
    : 'https://ui-avatars.com/api/?name=' . urlencode((string) $user['firstname'] . '+' . (string) $user['lastname']) . '&size=200&background=667eea&color=fff&bold=true';

$roleText = ((string) $user['role'] === 'admin') ? 'ผู้ดูแลระบบ' : 'สมาชิก';
?>

<?php if ($message !== ''): ?>
    <div class="profile-message <?= e($messageType); ?>" id="profileMessage">
        <?= e($message); ?>
    </div>
<?php endif; ?>

<div class="profile-container">
    <div class="profile-wrapper">
        <div class="profile-header">
            <h1>โปรไฟล์</h1>
            <p>จัดการข้อมูลส่วนตัวของคุณ</p>
        </div>

        <div class="profile-content">
            <!-- Profile Picture Section -->
            <div class="profile-picture-section">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="profile-picture">
                        <img src="<?= e($profileImageUrl); ?>" alt="Profile Picture" id="profileImage">
                        <div class="picture-overlay">
                            <label for="uploadPicture" class="upload-btn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                            </label>
                            <input
                                type="file"
                                id="uploadPicture"
                                name="profile_image"
                                accept="image/jpeg,image/png,image/gif,image/webp,image/heif,image/heic"
                                style="display: none;">
                        </div>
                    </div>
                    <h2 class="profile-name"><?= e((string) $user['firstname'] . ' ' . (string) $user['lastname']); ?></h2>
                    <p class="profile-role"><?= e($roleText); ?></p>
                </form>
            </div>

            <!-- Profile Information -->
            <div class="profile-info-section">
                <div class="section-card">
                    <h3>ข้อมูลส่วนตัว</h3>
                    <form method="POST" id="profileForm" style="display: none;">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ชื่อ</label>
                                <input type="text" name="firstname" value="<?= e((string) $user['firstname']); ?>" required class="edit-input">
                            </div>
                            <div class="info-item">
                                <label>นามสกุล</label>
                                <input type="text" name="lastname" value="<?= e((string) $user['lastname']); ?>" required class="edit-input">
                            </div>
                            <div class="info-item">
                                <label>ที่อยู่</label>
                                <textarea name="address" class="edit-input" rows="3"><?= e((string) ($user['address'] ?? '')); ?></textarea>
                            </div>
                            <div class="info-item">
                                <label>เบอร์โทรศัพท์</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?= e((string) ($user['phone'] ?? '')); ?>"
                                    class="edit-input"
                                    pattern="[0-9]{9,10}"
                                    title="กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก">
                            </div>
                            <div class="info-item">
                                <label>ชื่อผู้ใช้</label>
                                <p><?= e((string) $user['username']); ?> <small>(ไม่สามารถเปลี่ยนได้)</small></p>
                            </div>
                            <div class="info-item">
                                <label>อีเมล</label>
                                <p><?= e((string) $user['email']); ?> <small>(ไม่สามารถเปลี่ยนได้)</small></p>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">บันทึกการเปลี่ยนแปลง</button>
                            <button type="button" class="btn-cancel" onclick="cancelEdit()">ยกเลิก</button>
                        </div>
                    </form>

                    <div id="profileView">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ชื่อ - นามสกุล</label>
                                <p><?= e((string) $user['firstname'] . ' ' . (string) $user['lastname']); ?></p>
                            </div>
                            <div class="info-item">
                                <label>ที่อยู่</label>
                                <p><?= e((string) ($user['address'] ?? 'ไม่ได้ระบุ')); ?></p>
                            </div>
                            <div class="info-item">
                                <label>เบอร์โทรศัพท์</label>
                                <p><?= e((string) ($user['phone'] ?? 'ไม่ได้ระบุ')); ?></p>
                            </div>
                            <div class="info-item">
                                <label>อีเมล</label>
                                <p><?= e((string) $user['email']); ?></p>
                            </div>
                            <div class="info-item">
                                <label>ชื่อผู้ใช้</label>
                                <p><?= e((string) $user['username']); ?></p>
                            </div>
                            <div class="info-item">
                                <label>สมาชิกตั้งแต่</label>
                                <p><?= date('d/m/Y', strtotime((string) $user['created_at'])); ?></p>
                            </div>
                        </div>
                        <button class="btn-edit" onclick="showEdit()">แก้ไขข้อมูล</button>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="section-card">
                    <h3>เปลี่ยนรหัสผ่าน</h3>
                    <form action="" method="POST" class="password-form">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label for="current_password">รหัสผ่านเดิม</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="current_password" name="current_password" placeholder="กรอกรหัสผ่านเดิม" required>
                                <button type="button" class="toggle-password" data-target="current_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
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

                        <div class="password-row">
                            <div class="form-group">
                                <label for="new_password">รหัสผ่านใหม่</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="new_password" name="new_password" placeholder="กรอกรหัสผ่านใหม่" required minlength="6">
                                    <button type="button" class="toggle-password" data-target="new_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_new_password">ยืนยันรหัสผ่านใหม่</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="ยืนยันรหัสผ่านใหม่" required minlength="6">
                                    <button type="button" class="toggle-password" data-target="confirm_new_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-change-password">เปลี่ยนรหัสผ่าน</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        'use strict';

        function showEdit() {
            const view = document.getElementById('profileView');
            const form = document.getElementById('profileForm');
            if (!view || !form) return;
            view.style.display = 'none';
            form.style.display = 'block';
        }

        function cancelEdit() {
            const view = document.getElementById('profileView');
            const form = document.getElementById('profileForm');
            if (!view || !form) return;
            form.style.display = 'none';
            view.style.display = 'block';
        }

        window.showEdit = showEdit;
        window.cancelEdit = cancelEdit;

        const msg = document.getElementById('profileMessage');
        if (msg) {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, 5000);
        }

        const uploadInput = document.getElementById('uploadPicture');
        const uploadForm = document.getElementById('uploadForm');
        const profileImage = document.getElementById('profileImage');

        if (uploadInput && uploadForm && profileImage) {
            uploadInput.addEventListener('change', function() {
                if (!this.files || !this.files[0]) return;
                const file = this.files[0];
                const maxSize = 5 * 1024 * 1024;
                const allowed = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'image/heif',
                    'image/heic',
                ];

                if (!allowed.includes(file.type)) {
                    alert('อนุญาตเฉพาะไฟล์รูปภาพ (JPEG, PNG, GIF, WEBP, HEIF)');
                    this.value = '';
                    return;
                }

                if (file.size > maxSize) {
                    alert('ขนาดไฟล์ต้องไม่เกิน 5MB');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    profileImage.src = e.target?.result || profileImage.src;
                };
                reader.readAsDataURL(file);
                uploadForm.submit();
            });
        }

        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const firstname = this.querySelector('[name="firstname"]')?.value.trim() || '';
                const lastname = this.querySelector('[name="lastname"]')?.value.trim() || '';
                const phone = this.querySelector('[name="phone"]')?.value.trim() || '';

                if (!firstname || !lastname) {
                    e.preventDefault();
                    alert('กรุณากรอกชื่อและนามสกุล');
                    return false;
                }

                if (phone && !/^[0-9]{9,10}$/.test(phone)) {
                    e.preventDefault();
                    alert('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (9-10 หลัก)');
                    return false;
                }

                return true;
            });
        }

        const passwordForm = document.querySelector('.password-form');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const currentPassword = this.querySelector('[name="current_password"]')?.value || '';
                const newPassword = this.querySelector('[name="new_password"]')?.value || '';
                const confirmPassword = this.querySelector('[name="confirm_new_password"]')?.value || '';

                if (!currentPassword || !newPassword || !confirmPassword) {
                    e.preventDefault();
                    alert('กรุณากรอกข้อมูลให้ครบถ้วน');
                    return false;
                }

                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                    return false;
                }

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('รหัสผ่านใหม่ไม่ตรงกัน');
                    return false;
                }

                if (newPassword === currentPassword) {
                    e.preventDefault();
                    alert('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
                    return false;
                }

                return true;
            });
        }

        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const passwordInput = targetId ? document.getElementById(targetId) : null;
                const eyeIcon = button.querySelector('.eye-icon');
                const eyeOffIcon = button.querySelector('.eye-off-icon');

                if (!passwordInput || !eyeIcon || !eyeOffIcon) return;

                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                eyeIcon.style.display = isPassword ? 'none' : 'block';
                eyeOffIcon.style.display = isPassword ? 'block' : 'none';
            });
        });

        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });
        }
    })();
</script>