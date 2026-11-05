-- Migration: Tạo bảng Nhật Ký Thay Đổi Hệ Thống (System Changelogs)
CREATE TABLE IF NOT EXISTS `system_changelogs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(50) NULL COMMENT 'Phiên bản ví dụ: v2.5, Update 04/11',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề nội dung thay đổi',
    `description` TEXT NOT NULL COMMENT 'Chi tiết các thay đổi / tính năng mới',
    `category` VARCHAR(50) DEFAULT 'FEATURE' COMMENT 'FEATURE, ENHANCEMENT, BUGFIX, UI, SECURITY',
    `released_date` DATE NOT NULL COMMENT 'Ngày áp dụng thay đổi',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`released_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
