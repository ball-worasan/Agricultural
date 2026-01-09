<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
  define('APP_PATH', dirname(__DIR__, 2));
}

$helpersFile  = APP_PATH . '/includes/helpers.php';
$databaseFile = APP_PATH . '/config/database.php';

if (!is_file($helpersFile) || !is_file($databaseFile)) {
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ระบบยังไม่พร้อมใช้งาน</p></div>';
  return;
}

require_once $helpersFile;
require_once $databaseFile;

try {
  app_session_start();
} catch (Throwable $e) {
  app_log('profile_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

$sessionUser = current_user();
if ($sessionUser === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
}

$userId = (int)($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
if ($userId <= 0) {
  app_log('profile_invalid_session', ['user' => $sessionUser]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
  redirect('?page=signin');
}

// ----------------------------
// PRG success flags
// ----------------------------
$success = (string)($_GET['success'] ?? '');
if ($success === 'profile') {
  flash('success', 'อัปเดตข้อมูลส่วนตัวเรียบร้อย');
} elseif ($success === 'password') {
  flash('success', 'เปลี่ยนรหัสผ่านสำเร็จ');
}

// ----------------------------
// Handle POST actions
// ----------------------------
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
  // ---------- Update profile ----------
  if (isset($_POST['update_profile'])) {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $address  = trim((string)($_POST['address'] ?? ''));

    $phoneRaw = (string)($_POST['phone'] ?? '');
    $phone    = preg_replace('/\D+/', '', $phoneRaw) ?? '';

    if ($fullName === '') {
      flash('error', 'กรุณากรอกชื่อ-นามสกุล');
      redirect('?page=profile');
    }

    if ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) {
      flash('error', 'กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก');
      redirect('?page=profile');
    }

    // check duplicate phone (if provided)
    if ($phone !== '') {
      $dup = Database::fetchOne(
        'SELECT user_id FROM users WHERE phone = ? AND user_id != ? LIMIT 1',
        [$phone, $userId]
      );
      if ($dup) {
        flash('error', 'เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว');
        redirect('?page=profile');
      }
    }

    try {
      Database::transaction(function () use ($userId, $fullName, $address, $phone) {
        Database::execute(
          'UPDATE users SET full_name = ?, address = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?',
          [$fullName, $address, $phone !== '' ? $phone : null, $userId]
        );
      });

      // keep session in sync (เผื่อ navbar / avatar / ฯลฯ)
      $_SESSION['user']['full_name'] = $fullName;

      app_log('profile_update_success', ['user_id' => $userId, 'fields' => ['full_name', 'phone', 'address']]);

      redirect('?page=profile&success=profile');
    } catch (Throwable $e) {
      app_log('profile_update_error', [
        'user_id' => $userId,
        'full_name' => $fullName,
        'error' => $e->getMessage(),
      ]);
      flash('error', 'บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง');
      redirect('?page=profile');
    }
  }

  // ---------- Change password ----------
  if (isset($_POST['change_password'])) {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_new_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
      flash('error', 'กรุณากรอกข้อมูลให้ครบ');
      redirect('?page=profile');
    }

    if ($new !== $confirm) {
      flash('error', 'รหัสผ่านใหม่ไม่ตรงกัน');
      redirect('?page=profile');
    }

    if (strlen($new) < 8) {
      flash('error', 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
      redirect('?page=profile');
    }

    if ($new === $current) {
      flash('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
      redirect('?page=profile');
    }

    try {
      $row = Database::fetchOne('SELECT password FROM users WHERE user_id = ? LIMIT 1', [$userId]);
      if (!$row || !password_verify($current, (string)$row['password'])) {
        app_log('profile_password_mismatch', ['user_id' => $userId]);
        flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        redirect('?page=profile');
      }

      $hash = password_hash($new, PASSWORD_DEFAULT);
      if ($hash === false) {
        throw new RuntimeException('password_hash_failed');
      }

      Database::transaction(function () use ($userId, $hash) {
        Database::execute(
          'UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?',
          [$hash, $userId]
        );
      });

      app_log('profile_password_change_success', ['user_id' => $userId]);

      redirect('?page=profile&success=password');
    } catch (Throwable $e) {
      app_log('profile_change_password_error', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);
      flash('error', 'เปลี่ยนรหัสผ่านไม่สำเร็จ ลองใหม่อีกครั้ง');
      redirect('?page=profile');
    }
  }

  // Unknown POST
  flash('error', 'คำขอไม่ถูกต้อง');
  redirect('?page=profile');
}

// ----------------------------
// Load user data (GET)
// ----------------------------
$user = Database::fetchOne(
  'SELECT user_id, username, full_name, address, phone, role, created_at, updated_at
   FROM users WHERE user_id = ? LIMIT 1',
  [$userId]
);

if (!$user) {
  unset($_SESSION['user']);
  flash('error', 'ไม่พบข้อมูลผู้ใช้ กรุณาเข้าสู่ระบบใหม่');
  redirect('?page=signin');
}

// role text: รองรับทั้ง "admin"/"user" หรือ 1/0
$roleVal  = $user['role'] ?? '';
$isAdmin  = ((string)$roleVal === 'admin' || (string)$roleVal === 'ROLE_ADMIN' || (int)$roleVal === 1);
$roleText = $isAdmin ? 'ผู้ดูแลระบบ' : 'สมาชิก';

$fullNameForAvatar = trim((string)($user['full_name'] ?? $user['username'] ?? 'User'));
$profileImageUrl = 'https://ui-avatars.com/api/?name=' .
  urlencode($fullNameForAvatar) .
  '&size=200&background=1e40af&color=fff';

$createdAt = (string)($user['created_at'] ?? '');
$createdAtText = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '-';

?>
<?php render_flash_popup(); ?>

<div class="profile-container">
  <div class="profile-wrapper">
    <div class="profile-header">
      <h1>โปรไฟล์</h1>
      <p>จัดการข้อมูลส่วนตัวของคุณ</p>
    </div>

    <div class="profile-content">
      <div class="profile-picture-section">
        <div class="profile-picture">
          <img src="<?= e($profileImageUrl); ?>" alt="Profile Picture" id="profileImage">
        </div>
        <h2 class="profile-name"><?= e((string)($user['full_name'] ?? '')); ?></h2>
        <p class="profile-role"><?= e($roleText); ?></p>
      </div>

      <div class="profile-info-section">
        <div class="section-card">
          <h3>ข้อมูลส่วนตัว</h3>

          <!-- VIEW MODE -->
          <div id="profileView" class="profile-view-mode">
            <div class="info-grid">
              <div class="info-item">
                <label>ชื่อ-นามสกุล</label>
                <p><?= e((string)($user['full_name'] ?? '')); ?></p>
              </div>
              <div class="info-item">
                <label>เบอร์โทรศัพท์</label>
                <p><?= e((string)($user['phone'] ?? 'ไม่ได้ระบุ')); ?></p>
              </div>
              <div class="info-item">
                <label>ที่อยู่</label>
                <p><?= e((string)($user['address'] ?? 'ไม่ได้ระบุ')); ?></p>
              </div>
              <div class="info-item">
                <label>ชื่อผู้ใช้</label>
                <p><?= e((string)($user['username'] ?? '')); ?></p>
              </div>
              <div class="info-item">
                <label>สมาชิกตั้งแต่</label>
                <p><?= e($createdAtText); ?></p>
              </div>
            </div>

            <button type="button" class="btn-edit" id="editProfileBtn" aria-label="แก้ไขข้อมูล">แก้ไขข้อมูล</button>
          </div>

          <!-- EDIT MODE -->
          <form method="POST" id="profileForm" class="profile-edit-form hidden" novalidate>
            <input type="hidden" name="update_profile" value="1">

            <div class="info-grid">
              <div class="info-item">
                <label>ชื่อ-นามสกุล</label>
                <input type="text" name="full_name" value="<?= e((string)($user['full_name'] ?? '')); ?>" required class="edit-input">
              </div>

              <div class="info-item">
                <label>เบอร์โทรศัพท์</label>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value="<?= e((string)($user['phone'] ?? '')); ?>"
                  class="edit-input"
                  inputmode="numeric"
                  pattern="[0-9]{9,10}"
                  maxlength="10"
                  title="กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก">
              </div>

              <div class="info-item">
                <label>ที่อยู่</label>
                <textarea name="address" class="edit-input" rows="3"><?= e((string)($user['address'] ?? '')); ?></textarea>
              </div>

              <div class="info-item">
                <label>ชื่อผู้ใช้</label>
                <p><?= e((string)($user['username'] ?? '')); ?> <small>(ไม่สามารถเปลี่ยนได้)</small></p>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-save">บันทึกการเปลี่ยนแปลง</button>
              <button type="button" class="btn-cancel" id="cancelEditBtn">ยกเลิก</button>
            </div>
          </form>
        </div>

        <div class="section-card">
          <h3>เปลี่ยนรหัสผ่าน</h3>

          <form method="POST" class="password-form" novalidate>
            <input type="hidden" name="change_password" value="1">

            <div class="form-group">
              <label for="current_password">รหัสผ่านเดิม</label>
              <div class="password-input-wrapper">
                <input type="password" id="current_password" name="current_password" placeholder="กรอกรหัสผ่านเดิม" required autocomplete="current-password">
                <button type="button" class="toggle-password" data-target="current_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                  <span class="eye-icon">👁️</span>
                  <span class="eye-off-icon" style="display:none;">🙈</span>
                </button>
              </div>
            </div>

            <div class="password-row">
              <div class="form-group">
                <label for="new_password">รหัสผ่านใหม่</label>
                <div class="password-input-wrapper">
                  <input type="password" id="new_password" name="new_password" placeholder="กรอกรหัสผ่านใหม่" required minlength="8" autocomplete="new-password">
                  <button type="button" class="toggle-password" data-target="new_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                    <span class="eye-icon">👁️</span>
                    <span class="eye-off-icon" style="display:none;">🙈</span>
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label for="confirm_new_password">ยืนยันรหัสผ่านใหม่</label>
                <div class="password-input-wrapper">
                  <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="ยืนยันรหัสผ่านใหม่" required minlength="8" autocomplete="new-password">
                  <button type="button" class="toggle-password" data-target="confirm_new_password" aria-label="แสดง/ซ่อนรหัสผ่าน">
                    <span class="eye-icon">👁️</span>
                    <span class="eye-off-icon" style="display:none;">🙈</span>
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