<?php

declare(strict_types=1);

if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__, 2));
}

require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/NotificationService.php';

app_session_start();

$user = current_user();
if ($user === null) {
    redirect('?page=signin');
}

$userId = (int) ($user['id'] ?? 0);
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
                        $icon = match($notif['type']) {
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

