<?php

declare(strict_types=1);

app_session_start();

/**
 * Profile page
 * - Requires auth (route guard should handle it, but keep defensive checks)
 * - Supports: update profile, change password (PRG)
 * - Uses CSRF for both forms
 */

$sessionUser = current_user();
if ($sessionUser === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin', 303);
}

$userId = (int)($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
if ($userId <= 0) {
  app_log('profile_invalid_session', ['user' => $sessionUser]);
  unset($_SESSION['user']);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
  redirect('?page=signin', 303);
}

// -----------------------------------------------------------------------------
// PRG success flags
// -----------------------------------------------------------------------------
$success = (string)($_GET['success'] ?? '');
if ($success === 'profile') {
  flash('success', 'อัปเดตข้อมูลส่วนตัวเรียบร้อย');
} elseif ($success === 'password') {
  flash('success', 'เปลี่ยนรหัสผ่านสำเร็จ');
}

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
$requireCsrf = static function (): void {
  $token = (string)($_POST['_csrf'] ?? '');
  if (!function_exists('csrf_verify') || !csrf_verify($token)) {
    flash('error', 'คำขอไม่ถูกต้อง (CSRF)');
    redirect('?page=profile', 303);
  }
};

$normalizePhone = static function (string $raw): string {
  $digits = preg_replace('/\D+/', '', $raw);
  return is_string($digits) ? $digits : '';
};

$redirectProfile = static function (string $qs = ''): void {
  $url = '?page=profile' . ($qs !== '' ? '&' . ltrim($qs, '&') : '');
  redirect($url, 303);
};

// -----------------------------------------------------------------------------
// Handle POST
// -----------------------------------------------------------------------------
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
  // CSRF for all profile POSTs
  $requireCsrf();

  // ---------- Update profile ----------
  if (isset($_POST['update_profile'])) {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $address  = trim((string)($_POST['address'] ?? ''));
    $phone    = $normalizePhone((string)($_POST['phone'] ?? ''));

    if ($fullName === '') {
      flash('error', 'กรุณากรอกชื่อ-นามสกุล');
      $redirectProfile();
    }

    if ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) {
      flash('error', 'กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก');
      $redirectProfile();
    }

    try {
      Database::transaction(function () use ($userId, $fullName, $address, $phone): void {
        // duplicate phone check (inside tx)
        if ($phone !== '') {
          $dup = Database::fetchOne(
            'SELECT user_id FROM users WHERE phone = ? AND user_id != ? LIMIT 1',
            [$phone, $userId]
          );
          if ($dup) {
            throw new RuntimeException('duplicate_phone');
          }
        }

        Database::execute(
          'UPDATE users
             SET full_name = ?, address = ?, phone = ?, updated_at = CURRENT_TIMESTAMP
           WHERE user_id = ?',
          [$fullName, $address, ($phone !== '' ? $phone : null), $userId]
        );
      });

      // sync session
      if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['full_name'] = $fullName;
      }

      app_log('profile_update_success', ['user_id' => $userId]);
      $redirectProfile('success=profile');
    } catch (Throwable $e) {
      if ($e instanceof RuntimeException && $e->getMessage() === 'duplicate_phone') {
        flash('error', 'เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว');
        $redirectProfile();
      }

      app_log('profile_update_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
      flash('error', 'บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง');
      $redirectProfile();
    }
  }

  // ---------- Change password ----------
  if (isset($_POST['change_password'])) {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_new_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
      flash('error', 'กรุณากรอกข้อมูลให้ครบ');
      $redirectProfile();
    }

    if ($new !== $confirm) {
      flash('error', 'รหัสผ่านใหม่ไม่ตรงกัน');
      $redirectProfile();
    }

    if (strlen($new) < 8) {
      flash('error', 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
      $redirectProfile();
    }

    if ($new === $current) {
      flash('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
      $redirectProfile();
    }

    try {
      $row = Database::fetchOne('SELECT password FROM users WHERE user_id = ? LIMIT 1', [$userId]);
      if (!$row || !password_verify($current, (string)($row['password'] ?? ''))) {
        app_log('profile_password_mismatch', ['user_id' => $userId]);
        flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        $redirectProfile();
      }

      $hash = password_hash($new, PASSWORD_DEFAULT);
      if ($hash === false) {
        throw new RuntimeException('password_hash_failed');
      }

      Database::transaction(function () use ($userId, $hash): void {
        Database::execute(
          'UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?',
          [$hash, $userId]
        );
      });

      app_log('profile_password_change_success', ['user_id' => $userId]);

      session_regenerate_safe(true);
      csrf_rotate();

      $redirectProfile('success=password');
    } catch (Throwable $e) {
      app_log('profile_change_password_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
      flash('error', 'เปลี่ยนรหัสผ่านไม่สำเร็จ ลองใหม่อีกครั้ง');
      $redirectProfile();
    }
  }

  // Unknown POST
  flash('error', 'คำขอไม่ถูกต้อง');
  $redirectProfile();
}

// -----------------------------------------------------------------------------
// Load user data (GET)
// -----------------------------------------------------------------------------
$user = Database::fetchOne(
  'SELECT user_id, username, full_name, address, phone, role, created_at, updated_at
     FROM users
    WHERE user_id = ?
    LIMIT 1',
  [$userId]
);

if (!$user) {
  unset($_SESSION['user']);
  flash('error', 'ไม่พบข้อมูลผู้ใช้ กรุณาเข้าสู่ระบบใหม่');
  redirect('?page=signin', 303);
}

// role (use constants)
$roleId = (int)($user['role'] ?? ROLE_MEMBER);
$roleText = ($roleId === ROLE_ADMIN) ? 'ผู้ดูแลระบบ' : 'สมาชิก';

// avatar
$avatarName = trim((string)($user['full_name'] ?? ''));
if ($avatarName === '') $avatarName = (string)($user['username'] ?? 'User');

$profileImageUrl = 'https://ui-avatars.com/api/?name=' .
  urlencode($avatarName) .
  '&size=200&background=1e40af&color=fff';

$createdAt = (string)($user['created_at'] ?? '');
$createdAtText = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '-';

// csrf token for forms
$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<?php render_flash_popup(); ?>

<div class="profile-container" data-page="profile">
  <div class="profile-wrapper">
    <div class="profile-header">
      <h1>โปรไฟล์</h1>
      <p>จัดการข้อมูลส่วนตัวของคุณ</p>
    </div>

    <div class="profile-content">
      <div class="profile-picture-section">
        <div class="profile-picture">
          <img src="<?= e($profileImageUrl); ?>" alt="รูปโปรไฟล์" id="profileImage">
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
            <input type="hidden" name="_csrf" value="<?= e($csrf); ?>">
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
            <input type="hidden" name="_csrf" value="<?= e($csrf); ?>">
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