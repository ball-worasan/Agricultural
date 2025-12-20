<?php

declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

/**
 * Payment Service
 * จัดการการชำระเงินทั้งหมด
 */
class PaymentService
{
  /**
   * ตรวจสอบและอนุมัติสลิปการชำระเงิน
   */
  public static function verifyPayment(int $paymentId, int $adminId, bool $approved, ?string $reason = null): bool
  {
    try {
      Database::transaction(function () use ($paymentId, $adminId, $approved, $reason) {
        $payment = Database::fetchOne(
          'SELECT id, user_id, booking_id, amount, payment_status FROM payments WHERE id = ?',
          [$paymentId]
        );

        if (!$payment || (string)$payment['payment_status'] !== 'pending') {
          throw new RuntimeException('Payment not found or already processed');
        }

        $status = $approved ? 'verified' : 'rejected';

        Database::execute(
          'UPDATE payments SET payment_status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?',
          [$status, $approved ? $adminId : null, $approved ? null : $reason, $paymentId]
        );

        // ถ้าอนุมัติ ให้อัปเดต booking status
        if ($approved) {
          $bookingId = (int)$payment['booking_id'];
          Database::execute(
            'UPDATE bookings SET payment_status = "deposit_success" WHERE id = ? AND payment_status = "waiting"',
            [$bookingId]
          );

          // แจ้งเตือนผู้ใช้
          NotificationService::notifyPaymentVerified(
            (int)$payment['user_id'],
            (float)$payment['amount'],
            true
          );
        } else {
          // แจ้งเตือนผู้ใช้
          NotificationService::notifyPaymentVerified(
            (int)$payment['user_id'],
            (float)$payment['amount'],
            false
          );
        }
      });

      return true;
    } catch (Throwable $e) {
      app_log('payment_verify_error', [
        'payment_id' => $paymentId,
        'admin_id' => $adminId,
        'error' => $e->getMessage(),
      ]);
      return false;
    }
  }

  /**
   * สร้างตารางการชำระรายเดือน
   */
  public static function createMonthlySchedule(int $contractId, int $bookingId, int $userId, int $propertyId, float $monthlyAmount, int $months): bool
  {
    try {
      Database::transaction(function () use ($contractId, $bookingId, $userId, $propertyId, $monthlyAmount, $months) {
        $contract = Database::fetchOne(
          'SELECT start_date, end_date FROM contracts WHERE id = ?',
          [$contractId]
        );

        if (!$contract) {
          throw new RuntimeException('Contract not found');
        }

        $startDate = new DateTimeImmutable($contract['start_date']);

        for ($i = 1; $i <= $months; $i++) {
          $dueDate = $startDate->modify("+{$i} months")->format('Y-m-d');

          Database::execute(
            'INSERT INTO payment_schedules (contract_id, booking_id, user_id, property_id, due_date, amount, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$contractId, $bookingId, $userId, $propertyId, $dueDate, $monthlyAmount]
          );
        }
      });

      return true;
    } catch (Throwable $e) {
      app_log('payment_schedule_create_error', [
        'contract_id' => $contractId,
        'error' => $e->getMessage(),
      ]);
      return false;
    }
  }

  /**
   * ดึงรายการที่ครบกำหนดชำระ
   */
  public static function getDuePayments(int $daysAhead = 7): array
  {
    try {
      $endDate = (new DateTimeImmutable())->modify("+{$daysAhead} days")->format('Y-m-d');

      return Database::fetchAll(
        'SELECT ps.*, u.email, u.firstname, u.lastname, p.title AS property_title
                 FROM payment_schedules ps
                 JOIN users u ON ps.user_id = u.id
                 JOIN properties p ON ps.property_id = p.id
                 WHERE ps.payment_status = "pending"
                   AND ps.due_date <= ?
                   AND ps.due_date >= CURDATE()
                 ORDER BY ps.due_date ASC',
        [$endDate]
      );
    } catch (Throwable $e) {
      app_log('payment_due_fetch_error', ['error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * สร้างการคืนเงิน
   */
  public static function createRefund(int $bookingId, int $userId, int $propertyId, float $amount, string $reason): bool
  {
    try {
      Database::transaction(function () use ($bookingId, $userId, $propertyId, $amount, $reason) {
        Database::execute(
          'INSERT INTO payments (booking_id, user_id, property_id, payment_type, amount, payment_status, payment_date, notes, created_at) 
                     VALUES (?, ?, ?, "refund", ?, "pending", CURDATE(), ?, NOW())',
          [$bookingId, $userId, $propertyId, $amount, $reason]
        );

        NotificationService::create(
          $userId,
          'payment',
          'การคืนเงิน',
          "มีการคืนเงินจำนวน " . number_format($amount, 2) . " บาท\nเหตุผล: {$reason}",
          "?page=history"
        );
      });

      return true;
    } catch (Throwable $e) {
      app_log('refund_create_error', [
        'booking_id' => $bookingId,
        'error' => $e->getMessage(),
      ]);
      return false;
    }
  }
}
