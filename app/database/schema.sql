-- ใช้ InnoDB + utf8mb4 เป็นค่าเริ่มต้นทั้งฐาน
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ลำดับการสร้าง: users -> properties -> property_images -> bookings
-- =========================
-- ตาราง users : ข้อมูลผู้ใช้
-- =========================
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  -- 190 เผื่อ index + utf8mb4
  `password` VARCHAR(255) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `address` TEXT NULL,
  `phone` VARCHAR(20) NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('member', 'admin') NOT NULL DEFAULT 'member',
  -- สถานะการใช้งาน (เผื่อ soft-delete/ระงับบัญชี)
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  -- สำหรับระบบในอนาคต
  `email_verified_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- UNIQUE constraint แยกชื่อให้ชัดเจน
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  -- index สำหรับ query role + active รวดเร็ว
  KEY `idx_users_role_active` (`role`, `is_active`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ==================================
-- ตาราง properties : พื้นที่เกษตรให้เช่า
-- ==================================
CREATE TABLE IF NOT EXISTS `properties` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  -- ขนาดพื้นที่เดิม (เก็บภาพรวมที่ผู้ใช้กรอก)
  `area` VARCHAR(50) NULL,
  `bedrooms` VARCHAR(50) NULL,
  `bathrooms` VARCHAR(50) NULL,
  -- ฟิลด์ด้านเกษตร
  `category` VARCHAR(100) DEFAULT NULL,
  -- เช่น ไร่นา/สวนผลไม้/แปลงผัก/เลี้ยงสัตว์
  `has_water` TINYINT(1) DEFAULT NULL,
  -- มีน้ำพร้อมใช้ (0/1)
  `has_electric` TINYINT(1) DEFAULT NULL,
  -- มีไฟฟ้าพร้อมใช้ (0/1)
  -- ขนาดแยกตาม ไร่-งาน-ตร.วา
  `area_rai` INT DEFAULT NULL,
  `area_ngan` INT DEFAULT NULL,
  `area_sqwa` INT DEFAULT NULL,
  -- ขนาดรวมเป็น "ตารางวา" เพื่อให้ sort/filter ง่าย (generated column)
  `area_total_sqwa` INT GENERATED ALWAYS AS (
    COALESCE(`area_rai`, 0) * 400 + COALESCE(`area_ngan`, 0) * 100 + COALESCE(`area_sqwa`, 0)
  ) STORED,
  `soil_type` VARCHAR(100) DEFAULT NULL,
  `irrigation` VARCHAR(100) DEFAULT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `status` ENUM('available', 'booked', 'sold') NOT NULL DEFAULT 'available',
  -- เปิด/ปิดประกาศ (ไม่ต้องลบ row จริง)
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  -- เผื่อฤดูกาล / กำหนดช่วงเวลาที่ให้เช่า
  `available_from` DATE NULL,
  `available_to` DATE NULL,
  `description` TEXT NULL,
  `main_image` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_properties_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE
  SET NULL ON UPDATE CASCADE,
    -- index สำหรับการค้นหา/จัดเรียง
    KEY `idx_properties_status` (`status`),
    KEY `idx_properties_province` (`province`),
    KEY `idx_properties_category` (`category`),
    KEY `idx_properties_price` (`price`),
    KEY `idx_properties_owner_status` (`owner_id`, `status`),
    KEY `idx_properties_area_total_sqwa` (`area_total_sqwa`),
    KEY `idx_properties_status_province` (`status`, `province`),
    -- ใช้ตรวจสอบ logic พื้นฐาน (MySQL 8+ ถึง enforce)
    CONSTRAINT `chk_properties_price_non_negative` CHECK (`price` >= 0),
    CONSTRAINT `chk_properties_available_range` CHECK (
      `available_from` IS NULL
      OR `available_to` IS NULL
      OR `available_from` <= `available_to`
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =======================================
-- ตาราง property_images : รูปภาพของพื้นที่
-- =======================================
CREATE TABLE IF NOT EXISTS `property_images` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  -- ใช้จัดลำดับรูป
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_property_images_property` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  -- index สำหรับดึงรูปตาม property และเรียงลำดับ
  KEY `idx_property_images_property` (`property_id`),
  KEY `idx_property_images_display` (`property_id`, `display_order`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ====================================
-- ตาราง bookings : ข้อมูลการจอง/เช่าพื้นที่
-- ====================================
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  -- วันที่ทำรายการจอง
  `booking_date` DATE NOT NULL,
  -- วันเริ่มต้น/สิ้นสุดการเช่าจริง (เผื่อต่อยอด)
  `rental_start_date` DATE NULL,
  `rental_end_date` DATE NULL,
  `payment_status` ENUM('waiting', 'deposit_success', 'full_paid') NOT NULL DEFAULT 'waiting',
  `booking_status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `deposit_amount` DECIMAL(12, 2) DEFAULT NULL,
  `total_amount` DECIMAL(12, 2) DEFAULT NULL,
  `slip_image` VARCHAR(255) DEFAULT NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_property` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  -- index สำหรับ query ที่ใช้บ่อย
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_property` (`property_id`),
  KEY `idx_bookings_payment_status` (`payment_status`),
  KEY `idx_bookings_booking_status` (`booking_status`),
  KEY `idx_bookings_user_status` (`user_id`, `booking_status`),
  KEY `idx_bookings_property_status` (`property_id`, `booking_status`),
  -- ป้องกันการจองซ้ำสำหรับ user/property/day เดียวกัน
  UNIQUE KEY `uq_bookings_user_property_date` (`user_id`, `property_id`, `booking_date`),
  -- Logic เชิงธุรกิจพื้นฐาน
  CONSTRAINT `chk_bookings_amount_non_negative` CHECK (
    (
      `deposit_amount` IS NULL
      OR `deposit_amount` >= 0
    )
    AND (
      `total_amount` IS NULL
      OR `total_amount` >= 0
    )
  ),
  CONSTRAINT `chk_bookings_deposit_lte_total` CHECK (
    `deposit_amount` IS NULL
    OR `total_amount` IS NULL
    OR `deposit_amount` <= `total_amount`
  ),
  CONSTRAINT `chk_bookings_rental_range` CHECK (
    `rental_start_date` IS NULL
    OR `rental_end_date` IS NULL
    OR `rental_start_date` <= `rental_end_date`
  )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ====================================
-- ตาราง contracts : ข้อมูลสัญญาเช่า
-- ====================================
CREATE TABLE IF NOT EXISTS `contracts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  -- ข้อมูลสัญญา
  `contract_number` VARCHAR(50) NOT NULL,
  -- เลขที่สัญญา
  `rental_period_months` INT NOT NULL DEFAULT 12,
  -- ระยะเวลาเช่า (เดือน)
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `monthly_rent` DECIMAL(12, 2) NOT NULL,
  -- ค่าเช่าต่อเดือน
  `deposit_amount` DECIMAL(12, 2) NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL,
  -- เงื่อนไขสัญญา
  `terms_and_conditions` TEXT NULL,
  -- ไฟล์สัญญา
  `contract_file` VARCHAR(255) DEFAULT NULL,
  -- PDF ที่เซ็นแล้ว
  `status` ENUM(
    'draft',
    'waiting_signature',
    'signed',
    'active',
    'expired',
    'terminated'
  ) NOT NULL DEFAULT 'draft',
  -- ลายเซ็น
  `tenant_signature` VARCHAR(255) DEFAULT NULL,
  -- ลายเซ็นผู้เช่า
  `owner_signature` VARCHAR(255) DEFAULT NULL,
  -- ลายเซ็นเจ้าของ
  `signed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_contracts_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_property` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uq_contracts_booking` (`booking_id`),
  UNIQUE KEY `uq_contracts_number` (`contract_number`),
  KEY `idx_contracts_user` (`user_id`),
  KEY `idx_contracts_property` (`property_id`),
  KEY `idx_contracts_status` (`status`),
  KEY `idx_contracts_dates` (`start_date`, `end_date`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ====================================
-- ตาราง payments : ประวัติการชำระเงินทั้งหมด
-- ====================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `contract_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  -- ประเภทการชำระ
  `payment_type` ENUM(
    'deposit',
    'full_payment',
    'monthly_rent',
    'refund',
    'penalty'
  ) NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `payment_method` ENUM('promptpay', 'bank_transfer', 'cash', 'other') NOT NULL DEFAULT 'promptpay',
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  -- เลขที่อ้างอิงธุรกรรม
  -- สลิปการโอน
  `slip_image` VARCHAR(255) DEFAULT NULL,
  `payment_status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
  `verified_by` BIGINT UNSIGNED DEFAULT NULL,
  -- admin ที่ตรวจสอบ
  `verified_at` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  `payment_date` DATE NOT NULL,
  -- วันที่ชำระ
  `due_date` DATE NULL,
  -- วันครบกำหนด (สำหรับค่าเช่ารายเดือน)
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE
  SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_property` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE
  SET NULL ON UPDATE CASCADE,
    KEY `idx_payments_booking` (`booking_id`),
    KEY `idx_payments_contract` (`contract_id`),
    KEY `idx_payments_user` (`user_id`),
    KEY `idx_payments_property` (`property_id`),
    KEY `idx_payments_type` (`payment_type`),
    KEY `idx_payments_status` (`payment_status`),
    KEY `idx_payments_date` (`payment_date`),
    CONSTRAINT `chk_payments_amount_positive` CHECK (`amount` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;