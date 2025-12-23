<?php

declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

/**
 * Contract Service
 * จัดการสัญญาเช่า
 */
class ContractService
{
  /**
   * อนุมัติสัญญา
   */
  public static function approveContract(int $contractId, int $adminId): bool
  {
    try {
      Database::transaction(function () use ($contractId, $adminId) {
        $contract = Database::fetchOne(
          'SELECT id, user_id, contract_number, status FROM contracts WHERE id = ?',
          [$contractId]
        );

        if (!$contract || (string)$contract['status'] !== 'waiting_signature') {
          throw new RuntimeException('Contract not found or invalid status');
        }

        Database::execute(
          'UPDATE contracts SET status = "active", signed_at = NOW() WHERE id = ?',
          [$contractId]
        );

        NotificationService::notifyContractApproved(
          (int)$contract['user_id'],
          (string)$contract['contract_number']
        );
      });

      return true;
    } catch (Throwable $e) {
      app_log('contract_approve_error', [
        'contract_id' => $contractId,
        'admin_id' => $adminId,
        'error' => $e->getMessage(),
      ]);
      return false;
    }
  }

  /**
   * สร้าง PDF สัญญา (placeholder - ต้องใช้ library เช่น TCPDF หรือ DomPDF)
   */
  public static function generatePDF(int $contractId): ?string
  {
    try {
      $contract = Database::fetchOne(
        'SELECT c.*, u.full_name AS tenant_name, u.phone AS tenant_phone,
                        o.full_name AS owner_name, o.phone AS owner_phone,
                        ra.area_name, ra.area_size
                 FROM contract c
                 JOIN users u ON c.booking_id = u.user_id
                 JOIN users o ON ra.user_id = o.user_id
                 JOIN rental_area ra ON c.booking_id = ra.area_id
                 WHERE c.contract_id = ?',
        [$contractId]
      );

      if (!$contract) {
        return null;
      }

      // TODO: ใช้ library สร้าง PDF จริง
      // ตอนนี้แค่ return path placeholder
      $pdfPath = '/storage/uploads/contracts/contract_' . $contractId . '.pdf';

      // อัปเดต path ในฐานข้อมูล
      Database::execute(
        'UPDATE contracts SET pdf_file_path = ? WHERE id = ?',
        [$pdfPath, $contractId]
      );

      return $pdfPath;
    } catch (Throwable $e) {
      app_log('contract_pdf_generate_error', [
        'contract_id' => $contractId,
        'error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * ดาวน์โหลดสัญญา PDF
   */
  public static function downloadPDF(int $contractId, int $userId): ?string
  {
    $contract = Database::fetchOne(
      'SELECT pdf_file_path, user_id, owner_id FROM contracts WHERE id = ?',
      [$contractId]
    );

    if (!$contract) {
      return null;
    }

    // ตรวจสอบสิทธิ์
    if ((int)$contract['user_id'] !== $userId && (int)$contract['owner_id'] !== $userId) {
      return null;
    }

    $pdfPath = $contract['pdf_file_path'];
    if (empty($pdfPath)) {
      // สร้าง PDF ถ้ายังไม่มี
      $pdfPath = self::generatePDF($contractId);
    }

    return $pdfPath;
  }
}
