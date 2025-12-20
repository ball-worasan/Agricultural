-- ใช้ InnoDB + utf8mb4 เป็นค่าเริ่มต้นทั้งฐาน
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- เคลียร์ฐานข้อมูล: ปิด FK, ลบตารางทั้งหมดที่เกี่ยว แล้วเปิด FK กลับ
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `Payment`,
`Contract`,
`Booking_Deposit`,
`Area_Image`,
`Rental_Area`,
`District`,
`Province`,
`Fee`,
`User`,
`users`,
`properties`,
`property_images`,
`bookings`,
`contracts`,
`payments`;
SET FOREIGN_KEY_CHECKS = 1;
-- ===============================
-- ตาราง Province : จังหวัด
-- ===============================
CREATE TABLE IF NOT EXISTS `Province` (
  `province_id` INT PRIMARY KEY AUTO_INCREMENT,
  `province_name` VARCHAR(100) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ===============================
-- ตาราง District : อำเภอ
-- ===============================
CREATE TABLE IF NOT EXISTS `District` (
  `district_id` INT PRIMARY KEY AUTO_INCREMENT,
  `district_name` VARCHAR(50) NOT NULL,
  `province_id` INT NOT NULL,
  CONSTRAINT `fk_district_province` FOREIGN KEY (`province_id`) REFERENCES `Province`(`province_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ===============================
-- ตาราง User : ผู้ใช้งานระบบ
-- ===============================
CREATE TABLE IF NOT EXISTS `User` (
  `user_id` INT PRIMARY KEY AUTO_INCREMENT,
  `full_name` VARCHAR(50) NOT NULL,
  `username` VARCHAR(10) NOT NULL,
  `password` VARCHAR(8) NOT NULL,
  `role` INT NOT NULL,
  `phone` VARCHAR(10) NOT NULL,
  `address` TEXT NOT NULL,
  UNIQUE KEY `uq_user_username` (`username`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Rental_Area : พื้นที่ให้เช่า
-- ======================================
CREATE TABLE IF NOT EXISTS `Rental_Area` (
  `area_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `area_name` VARCHAR(50) NOT NULL,
  `price_per_year` DECIMAL(8, 2) NOT NULL,
  `deposit_percent` DECIMAL(4, 2) NOT NULL,
  `area_size` DECIMAL(10, 2) NOT NULL,
  `district_id` INT NOT NULL,
  `area_status` VARCHAR(50) NOT NULL,
  CONSTRAINT `fk_rental_area_user` FOREIGN KEY (`user_id`) REFERENCES `User`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rental_area_district` FOREIGN KEY (`district_id`) REFERENCES `District`(`district_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Area_Image : ภาพพื้นที่
-- ======================================
CREATE TABLE IF NOT EXISTS `Area_Image` (
  `image_id` INT PRIMARY KEY AUTO_INCREMENT,
  `image_url` VARCHAR(255) NOT NULL,
  `area_id` INT NOT NULL,
  CONSTRAINT `fk_area_image_rental_area` FOREIGN KEY (`area_id`) REFERENCES `Rental_Area`(`area_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Booking_Deposit : การจอง
-- ======================================
CREATE TABLE IF NOT EXISTS `Booking_Deposit` (
  `booking_id` INT PRIMARY KEY AUTO_INCREMENT,
  `area_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `deposit_amount` DECIMAL(8, 2) NOT NULL,
  `deposit_status` ENUM('approved', 'rejected') NOT NULL,
  CONSTRAINT `fk_booking_area` FOREIGN KEY (`area_id`) REFERENCES `Rental_Area`(`area_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `User`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Contract : สัญญาเช่า
-- ======================================
CREATE TABLE IF NOT EXISTS `Contract` (
  `contract_id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `price_per_year` DECIMAL(8, 2) NOT NULL,
  `terms` TEXT NULL,
  `contract_file` VARCHAR(255) NULL,
  CONSTRAINT `fk_contract_booking` FOREIGN KEY (`booking_id`) REFERENCES `Booking_Deposit`(`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Payment : การชำระเงิน
-- ======================================
CREATE TABLE IF NOT EXISTS `Payment` (
  `payment_id` INT PRIMARY KEY AUTO_INCREMENT,
  `contract_id` INT NOT NULL,
  `amount` DECIMAL(8, 2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_time` TIME NOT NULL,
  `net_amount` DECIMAL(8, 2) NOT NULL,
  `slip_image` VARCHAR(255) NULL,
  CONSTRAINT `fk_payment_contract` FOREIGN KEY (`contract_id`) REFERENCES `Contract`(`contract_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ======================================
-- ตาราง Fee : ค่าธรรมเนียม
-- ======================================
CREATE TABLE IF NOT EXISTS `Fee` (
  `fee_id` INT PRIMARY KEY AUTO_INCREMENT,
  `fee_rate` DECIMAL(5, 2) NOT NULL,
  `account_number` VARCHAR(20) NOT NULL,
  `account_name` VARCHAR(50) NOT NULL,
  `bank_name` VARCHAR(50) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;