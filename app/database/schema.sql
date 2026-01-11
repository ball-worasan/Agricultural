-- ใช้ InnoDB + utf8mb4 เป็นค่าเริ่มต้นทั้งฐาน
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- เคลียร์ฐานข้อมูล: ปิด FK, ลบตารางทั้งหมดที่เกี่ยว แล้วเปิด FK กลับ
SET FOREIGN_KEY_CHECKS = 0;
-- ลบทั้งชื่อเล็ก/ใหญ่ กันปัญหา tablespace ค้างจาก schema เก่า
DROP TABLE IF EXISTS `payment`,
`Payment`,
`contract`,
`Contract`,
`booking_deposit`,
`Booking_Deposit`,
`area_image`,
`Area_Image`,
`rental_area`,
`Rental_Area`,
`district`,
`District`,
`province`,
`Province`,
`fee`,
`Fee`,
`users`,
`User`,
`properties`,
`property_images`,
`bookings`,
`contracts`,
`payments`;
SET FOREIGN_KEY_CHECKS = 1;
-- ===============================
-- ตาราง Province : จังหวัด
-- ===============================
CREATE TABLE IF NOT EXISTS `province` (
  `province_id` INT PRIMARY KEY AUTO_INCREMENT,
  `province_name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ===============================
-- ตาราง District : อำเภอ
-- ===============================
CREATE TABLE IF NOT EXISTS `district` (
  `district_id` INT PRIMARY KEY AUTO_INCREMENT,
  `district_name` VARCHAR(50) NOT NULL,
  `province_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_district_province` (`province_id`),
  CONSTRAINT `fk_district_province` FOREIGN KEY (`province_id`) REFERENCES `province`(`province_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ===============================
-- ตาราง User : ผู้ใช้งานระบบ
-- ===============================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT PRIMARY KEY AUTO_INCREMENT,
  `full_name` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` INT NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT NOT NULL,
  `account_number` VARCHAR(50) NULL COMMENT 'เลขบัญชีธนาคาร/พร้อมเพย์',
  `bank_name` VARCHAR(100) NULL COMMENT 'ชื่อธนาคาร',
  `account_name` VARCHAR(100) NULL COMMENT 'ชื่อบัญชีเจ้าของเลขบัญชี',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_user_username` (`username`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Rental_Area : พื้นที่ให้เช่า
-- ======================================
CREATE TABLE IF NOT EXISTS `rental_area` (
  `area_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `area_name` VARCHAR(50) NOT NULL,
  `price_per_year` DECIMAL(8, 2) NOT NULL,
  `deposit_percent` DECIMAL(5, 2) NOT NULL,
  `area_size` DECIMAL(10, 2) NOT NULL,
  `district_id` INT NOT NULL,
  `area_status` ENUM('available', 'booked', 'unavailable') NOT NULL DEFAULT 'available',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (
    `deposit_percent` >= 0
    AND `deposit_percent` <= 100
  ),
  KEY `idx_rental_area_status` (`area_status`),
  KEY `idx_rental_area_district` (`district_id`),
  CONSTRAINT `fk_rental_area_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rental_area_district` FOREIGN KEY (`district_id`) REFERENCES `district`(`district_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Area_Image : ภาพพื้นที่
-- ======================================
CREATE TABLE IF NOT EXISTS `area_image` (
  `image_id` INT PRIMARY KEY AUTO_INCREMENT,
  `image_url` VARCHAR(255) NOT NULL,
  `area_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_area_image_area` (`area_id`),
  CONSTRAINT `fk_area_image_rental_area` FOREIGN KEY (`area_id`) REFERENCES `rental_area`(`area_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Booking_Deposit : การจอง
-- ======================================
CREATE TABLE IF NOT EXISTS `booking_deposit` (
  `booking_id` INT PRIMARY KEY AUTO_INCREMENT,
  `area_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `deposit_amount` DECIMAL(8, 2) NOT NULL,
  `deposit_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `payment_slip` VARCHAR(255) NULL COMMENT 'Path to uploaded payment slip image',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_booking_area_date` (`area_id`, `booking_date`),
  KEY `idx_booking_area` (`area_id`),
  KEY `idx_booking_user` (`user_id`),
  CONSTRAINT `fk_booking_area` FOREIGN KEY (`area_id`) REFERENCES `rental_area`(`area_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Contract : สัญญาเช่า
-- ======================================
CREATE TABLE IF NOT EXISTS `contract` (
  `contract_id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `price_per_year` DECIMAL(8, 2) NOT NULL,
  `terms` TEXT NULL,
  `contract_file` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_contract_booking` (`booking_id`),
  CONSTRAINT `fk_contract_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking_deposit`(`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Payment : การชำระเงิน
-- ======================================
CREATE TABLE IF NOT EXISTS `payment` (
  `payment_id` INT PRIMARY KEY AUTO_INCREMENT,
  `contract_id` INT NOT NULL,
  `amount` DECIMAL(8, 2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_time` TIME NOT NULL,
  `net_amount` DECIMAL(8, 2) NOT NULL,
  `slip_image` VARCHAR(255) NULL,
  `status` ENUM('pending', 'confirmed', 'failed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_payment_contract_date` (`contract_id`, `payment_date`),
  KEY `idx_payment_contract` (`contract_id`),
  CONSTRAINT `fk_payment_contract` FOREIGN KEY (`contract_id`) REFERENCES `contract`(`contract_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Fee : ค่าธรรมเนียม
-- ======================================
CREATE TABLE IF NOT EXISTS `fee` (
  `fee_id` INT PRIMARY KEY AUTO_INCREMENT,
  `fee_rate` DECIMAL(5, 2) NOT NULL,
  `account_number` VARCHAR(20) NOT NULL,
  `account_name` VARCHAR(50) NOT NULL,
  `bank_name` VARCHAR(50) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (
    `fee_rate` >= 0
    AND `fee_rate` <= 100
  ),
  UNIQUE KEY `uq_fee_account` (`account_number`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;