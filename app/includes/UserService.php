<?php

declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

/**
 * User Service
 * จัดการผู้ใช้ - ลืมรหัสผ่าน, เปลี่ยนรหัส, Email verification
 */
class UserService
{
  /**
   * สร้าง token สำหรับ reset password
   */
  public static function createPasswordResetToken(string $email): ?string
  {
    try {
      $user = Database::fetchOne(
        'SELECT id FROM users WHERE email = ? LIMIT 1',
        [$email]
      );

      if (!$user) {
        // ไม่บอกว่า email ไม่มีในระบบ (security)
        return null;
      }

      $token = bin2hex(random_bytes(32));
      $expiresAt = (new DateTimeImmutable())->modify('+1 hour')->format('Y-m-d H:i:s');

      Database::execute(
        'UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?',
        [$token, $expiresAt, (int)$user['id']]
      );

      return $token;
    } catch (Throwable $e) {
      app_log('password_reset_token_error', [
        'email' => $email,
        'error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * ตรวจสอบและ reset password
   */
  public static function resetPassword(string $token, string $newPassword): bool
  {
    try {
      $user = Database::fetchOne(
        'SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires_at > NOW() LIMIT 1',
        [$token]
      );

      if (!$user) {
        return false;
      }

      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      if ($hashedPassword === false) {
        return false;
      }

      Database::execute(
        'UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?',
        [$hashedPassword, (int)$user['id']]
      );

      return true;
    } catch (Throwable $e) {
      app_log('password_reset_error', ['error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * เปลี่ยนรหัสผ่าน
   */
  public static function changePassword(int $userId, string $currentPassword, string $newPassword): array
  {
    try {
      $user = Database::fetchOne(
        'SELECT password FROM users WHERE id = ? LIMIT 1',
        [$userId]
      );

      if (!$user || !password_verify($currentPassword, (string)$user['password'])) {
        return ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
      }

      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      if ($hashedPassword === false) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้ารหัสรหัสผ่าน'];
      }

      Database::execute(
        'UPDATE users SET password = ? WHERE id = ?',
        [$hashedPassword, $userId]
      );

      return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'];
    } catch (Throwable $e) {
      app_log('change_password_error', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);
      return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
    }
  }

  /**
   * สร้าง email verification token
   */
  public static function createEmailVerificationToken(int $userId): ?string
  {
    try {
      $token = bin2hex(random_bytes(32));

      Database::execute(
        'UPDATE users SET email_verification_token = ? WHERE id = ?',
        [$token, $userId]
      );

      return $token;
    } catch (Throwable $e) {
      app_log('email_verification_token_error', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * ตรวจสอบ email
   */
  public static function verifyEmail(string $token): bool
  {
    try {
      $user = Database::fetchOne(
        'SELECT id FROM users WHERE email_verification_token = ? LIMIT 1',
        [$token]
      );

      if (!$user) {
        return false;
      }

      Database::execute(
        'UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?',
        [(int)$user['id']]
      );

      return true;
    } catch (Throwable $e) {
      app_log('email_verification_error', ['error' => $e->getMessage()]);
      return false;
    }
  }
}
