<?php

declare(strict_types=1);

/**
 * Contract Service
 * จัดการสัญญาเช่า
 */
class ContractService
{
  /** @return array<string,mixed>|null */
  private static function fetchContractForApproval(int $contractId): ?array
  {
    return Database::fetchOne(
      'SELECT id, user_id, contract_number, status FROM contracts WHERE id = ?',
      [$contractId]
    );
  }

  /**
   * อนุมัติสัญญา
   */
  public static function approveContract(int $contractId, int $adminId): bool
  {
    try {
      Database::transaction(function () use ($contractId, $adminId) {
        $contract = self::fetchContractForApproval($contractId);

        if (!$contract || (string) $contract['status'] !== 'waiting_signature') {
          throw new RuntimeException('Contract not found or invalid status');
        }

        Database::execute(
          'UPDATE contracts SET status = "active", signed_at = NOW() WHERE id = ?',
          [$contractId]
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
        'SELECT c.id AS contract_id, c.booking_id, c.contract_file,
                tenant.full_name AS tenant_name, tenant.phone AS tenant_phone,
                owner.full_name AS owner_name, owner.phone AS owner_phone,
                ra.area_name, ra.area_size
         FROM contracts c
         JOIN booking_deposit bd ON c.booking_id = bd.booking_id
         JOIN rental_area ra ON bd.area_id = ra.area_id
         JOIN users tenant ON bd.user_id = tenant.user_id
         JOIN users owner ON ra.user_id = owner.user_id
         WHERE c.id = ?',
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
        'UPDATE contracts SET contract_file = ? WHERE id = ?',
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
      'SELECT c.contract_file, bd.user_id AS tenant_id, ra.user_id AS owner_id
       FROM contracts c
       JOIN booking_deposit bd ON c.booking_id = bd.booking_id
       JOIN rental_area ra ON bd.area_id = ra.area_id
       WHERE c.id = ?',
      [$contractId]
    );

    if (!$contract) {
      return null;
    }

    // ตรวจสอบสิทธิ์: ผู้เช่า หรือ เจ้าของพื้นที่เท่านั้น
    if ((int) $contract['tenant_id'] !== $userId && (int) $contract['owner_id'] !== $userId) {
      return null;
    }

    $pdfPath = (string) ($contract['contract_file'] ?? '');
    if ($pdfPath === '') {
      // สร้าง PDF ถ้ายังไม่มี
      $pdfPath = (string) (self::generatePDF($contractId) ?? '');
    }

    return $pdfPath !== '' ? $pdfPath : null;
  }
}
