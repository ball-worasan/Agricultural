-- ====================================
-- ตาราง notifications : ระบบแจ้งเตือน
-- ====================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('booking', 'payment', 'contract', 'system', 'message') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ====================================
-- เพิ่มคอลัมน์ในตาราง users สำหรับ email verification
-- ====================================
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email_verification_token` VARCHAR(100) NULL AFTER `email_verified_at`,
ADD COLUMN IF NOT EXISTS `password_reset_token` VARCHAR(100) NULL AFTER `password_reset_token`,
ADD COLUMN IF NOT EXISTS `password_reset_expires_at` DATETIME NULL AFTER `password_reset_token`;

-- ====================================
-- ตาราง payment_schedules : ตารางการชำระรายเดือน
-- ====================================
CREATE TABLE IF NOT EXISTS `payment_schedules` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME NULL,
  `payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `late_fee` DECIMAL(12, 2) DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payment_schedules_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_schedules_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_schedules_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_schedules_property` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_schedules_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  KEY `idx_payment_schedules_contract` (`contract_id`),
  KEY `idx_payment_schedules_due_date` (`due_date`),
  KEY `idx_payment_schedules_status` (`payment_status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ====================================
-- เพิ่มคอลัมน์ในตาราง contracts สำหรับลายเซ็น
-- ====================================
ALTER TABLE `contracts`
ADD COLUMN IF NOT EXISTS `tenant_signed_at` DATETIME NULL AFTER `tenant_signature`,
ADD COLUMN IF NOT EXISTS `owner_signed_at` DATETIME NULL AFTER `owner_signature`,
ADD COLUMN IF NOT EXISTS `pdf_file_path` VARCHAR(500) NULL AFTER `contract_file`;

