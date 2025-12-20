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
  app_log('notifications_database_file_missing', ['file' => $databaseFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$helpersFile = APP_PATH . '/includes/helpers.php';
if (!is_file($helpersFile)) {
  app_log('notifications_helpers_file_missing', ['file' => $helpersFile]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  return;
}

$notificationServiceFile = APP_PATH . '/includes/NotificationService.php';
if (!is_file($notificationServiceFile)) {
  app_log('notifications_service_missing', ['file' => $notificationServiceFile]);
  // เดินต่อหากไม่มี notification service
}

require_once $databaseFile;
require_once $helpersFile;
if (is_file($notificationServiceFile)) {
  require_once $notificationServiceFile;
}

// ----------------------------
// เริ่มเซสชัน
// ----------------------------
try {
  app_session_start();
} catch (Throwable $e) {
  app_log('notifications_session_error', ['error' => $e->getMessage()]);
  http_response_code(500);
  echo '<div class="container"><h1>เกิดข้อผิดพลาด</h1><p>ไม่สามารถเริ่มเซสชันได้</p></div>';
  return;
}

// ----------------------------
// เช็กสิทธิ์ล็อกอิน
// ----------------------------
$user = current_user();
if ($user === null) {
  flash('error', 'กรุณาเข้าสู่ระบบก่อน');
  redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
  app_log('notifications_invalid_user', ['session_user' => $user]);
  flash('error', 'ข้อมูลผู้ใช้ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
  redirect('?page=signin');
}

// ----------------------------
// อ่านเมธอดคำขอ
// ----------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// POST: ทำเครื่องหมายว่าอ่านแล้ว
if ($method === 'POST') {
  csrf_require();

  $action = (string) ($_POST['action'] ?? '');

  if ($action === 'mark_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
      NotificationService::markAsRead($notificationId, $userId);
    }
    json_response(['success' => true]);
  } elseif ($action === 'mark_all_read') {
    NotificationService::markAllAsRead($userId);
    json_response(['success' => true]);
  }
}

// GET: แสดงการแจ้งเตือน
$unreadCount = NotificationService::getUnreadCount($userId);
$notifications = NotificationService::getAll($userId, 50);

$typeLabels = [
  'booking' => 'การจอง',
  'payment' => 'การชำระเงิน',
  'contract' => 'สัญญา',
  'system' => 'ระบบ',
  'message' => 'ข้อความ',
];

?>
<div class="notifications-container">
  <div class="page-header">
    <h1>การแจ้งเตือน</h1>
    <?php if ($unreadCount > 0): ?>
      <button type="button" class="btn-mark-all-read" onclick="markAllAsRead()">
        ทำเครื่องหมายว่าอ่านทั้งหมด
      </button>
    <?php endif; ?>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <p>ยังไม่มีการแจ้งเตือน</p>
    </div>
  <?php else: ?>
    <div class="notifications-list">
      <?php foreach ($notifications as $notif): ?>
        <div class="notification-item <?= (int)$notif['is_read'] === 0 ? 'unread' : ''; ?>"
          data-id="<?= (int)$notif['id']; ?>"
          onclick="markAsRead(<?= (int)$notif['id']; ?>, '<?= e($notif['link'] ?? ''); ?>')">
          <div class="notification-icon">
            <?php
            $icon = match ($notif['type']) {
              'booking' => '📋',
              'payment' => '💰',
              'contract' => '📄',
              'system' => '⚙️',
              'message' => '💬',
              default => '🔔',
            };
            echo $icon;
            ?>
          </div>
          <div class="notification-content">
            <div class="notification-header">
              <h3><?= e($notif['title']); ?></h3>
              <span class="notification-type"><?= e($typeLabels[$notif['type']] ?? $notif['type']); ?></span>
            </div>
            <p class="notification-message"><?= nl2br(e($notif['message'])); ?></p>
            <span class="notification-time"><?= date('d/m/Y H:i', strtotime($notif['created_at'])); ?></span>
          </div>
          <?php if ((int)$notif['is_read'] === 0): ?>
            <span class="unread-badge"></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  async function markAsRead(notificationId, link) {
    try {
      const formData = new FormData();
      formData.append('action', 'mark_read');
      formData.append('csrf', CSRF_TOKEN);
      formData.append('notification_id', String(notificationId));

      await fetch(window.location.href, {
        method: 'POST',
        body: formData
      });

      const item = document.querySelector(`[data-id="${notificationId}"]`);
      if (item) {
        item.classList.remove('unread');
        const badge = item.querySelector('.unread-badge');
        if (badge) badge.remove();
      }

      if (link && link !== '') {
        window.location.href = link;
      }
    } catch (err) {
      console.error('markAsRead error:', err);
    }
  }

  async function markAllAsRead() {
    if (!confirm('ทำเครื่องหมายว่าอ่านทั้งหมด?')) return;

    try {
      const formData = new FormData();
      formData.append('action', 'mark_all_read');
      formData.append('csrf', CSRF_TOKEN);

      const res = await fetch(window.location.href, {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        document.querySelectorAll('.notification-item').forEach(item => {
          item.classList.remove('unread');
          const badge = item.querySelector('.unread-badge');
          if (badge) badge.remove();
        });
      }
    } catch (err) {
      console.error('markAllAsRead error:', err);
    }
  }
</script>