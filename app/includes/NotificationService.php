<?php

declare(strict_types=1);

/**
 * Notification Service
 * จัดการการแจ้งเตือนในระบบ
 */
class NotificationService
{
    /**
     * สร้างการแจ้งเตือน
     *
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string|null $link
     * @return bool
     */
    public static function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): bool {
        try {
            Database::execute(
                'INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$userId, $type, $title, $message, $link]
            );
            return true;
        } catch (Throwable $e) {
            app_log('notification_create_error', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * แจ้งเตือนเมื่อมีการจองใหม่
     */
    public static function notifyNewBooking(int $ownerId, int $bookingId, string $propertyTitle): void
    {
        self::create(
            $ownerId,
            'booking',
            'มีการจองใหม่',
            "มีผู้ใช้จองพื้นที่: {$propertyTitle}",
            "?page=property_bookings&id=" . $bookingId
        );
    }

    /**
     * แจ้งเตือนเมื่อการจองได้รับการอนุมัติ
     */
    public static function notifyBookingApproved(int $userId, string $propertyTitle): void
    {
        self::create(
            $userId,
            'booking',
            'การจองได้รับการอนุมัติ',
            "การจองพื้นที่: {$propertyTitle} ได้รับการอนุมัติแล้ว",
            "?page=history"
        );
    }

    /**
     * แจ้งเตือนเมื่อการจองถูกปฏิเสธ
     */
    public static function notifyBookingRejected(int $userId, string $propertyTitle, string $reason): void
    {
        self::create(
            $userId,
            'booking',
            'การจองถูกปฏิเสธ',
            "การจองพื้นที่: {$propertyTitle} ถูกปฏิเสธ\nเหตุผล: {$reason}",
            "?page=history"
        );
    }

    /**
     * แจ้งเตือนเมื่อมีการชำระเงิน
     */
    public static function notifyPaymentReceived(int $ownerId, int $paymentId, float $amount): void
    {
        self::create(
            $ownerId,
            'payment',
            'ได้รับเงินชำระ',
            "ได้รับเงินชำระจำนวน " . number_format($amount, 2) . " บาท",
            "?page=admin_dashboard"
        );
    }

    /**
     * แจ้งเตือนเมื่อการชำระเงินได้รับการตรวจสอบ
     */
    public static function notifyPaymentVerified(int $userId, float $amount, bool $approved): void
    {
        $title = $approved ? 'การชำระเงินได้รับการอนุมัติ' : 'การชำระเงินถูกปฏิเสธ';
        $message = $approved 
            ? "การชำระเงินจำนวน " . number_format($amount, 2) . " บาท ได้รับการอนุมัติแล้ว"
            : "การชำระเงินจำนวน " . number_format($amount, 2) . " บาท ถูกปฏิเสธ";
        
        self::create(
            $userId,
            'payment',
            $title,
            $message,
            "?page=history"
        );
    }

    /**
     * แจ้งเตือนเมื่อสัญญาได้รับการอนุมัติ
     */
    public static function notifyContractApproved(int $userId, string $contractNumber): void
    {
        self::create(
            $userId,
            'contract',
            'สัญญาได้รับการอนุมัติ',
            "สัญญาเลขที่ {$contractNumber} ได้รับการอนุมัติแล้ว",
            "?page=history"
        );
    }

    /**
     * แจ้งเตือนเมื่อค่าเช่าครบกำหนด
     */
    public static function notifyRentDue(int $userId, string $propertyTitle, string $dueDate, float $amount): void
    {
        self::create(
            $userId,
            'payment',
            'ค่าเช่าครบกำหนด',
            "ค่าเช่าพื้นที่: {$propertyTitle} ครบกำหนดชำระในวันที่ {$dueDate}\nจำนวน: " . number_format($amount, 2) . " บาท",
            "?page=history"
        );
    }

    /**
     * ดึงการแจ้งเตือนที่ยังไม่อ่าน
     */
    public static function getUnreadCount(int $userId): int
    {
        try {
            $row = Database::fetchOne(
                'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
                [$userId]
            );
            return (int) ($row['cnt'] ?? 0);
        } catch (Throwable $e) {
            app_log('notification_count_error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * ดึงการแจ้งเตือนทั้งหมด
     */
    public static function getAll(int $userId, int $limit = 20, int $offset = 0): array
    {
        try {
            return Database::fetchAll(
                'SELECT id, type, title, message, link, is_read, read_at, created_at 
                 FROM notifications 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?',
                [$userId, $limit, $offset]
            );
        } catch (Throwable $e) {
            app_log('notification_fetch_error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * ทำเครื่องหมายว่าอ่านแล้ว
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            Database::execute(
                'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?',
                [$notificationId, $userId]
            );
            return true;
        } catch (Throwable $e) {
            app_log('notification_mark_read_error', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ทำเครื่องหมายว่าอ่านทั้งหมด
     */
    public static function markAllAsRead(int $userId): bool
    {
        try {
            Database::execute(
                'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
                [$userId]
            );
            return true;
        } catch (Throwable $e) {
            app_log('notification_mark_all_read_error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

