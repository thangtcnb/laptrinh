USE `laptrinh_db`;

-- 1. Safely update users role ENUM to include 'HO_TRO'
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('BGD', 'TRUONG_PHONG', 'NHAN_VIEN', 'HO_TRO', 'SUPER_ADMIN') NOT NULL DEFAULT 'NHAN_VIEN';

-- 2. Safely add comment_feedback column to tasks if it does not exist
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DELIMITER //
CREATE PROCEDURE AddColumnIfNotExists()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'laptrinh_db' AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'comment_feedback'
    ) THEN
        ALTER TABLE `tasks` ADD COLUMN `comment_feedback` TEXT DEFAULT NULL COMMENT 'Y kien khac, de xuat hoac vuong mac cua nhan vien khi nhan cong viec';
    END IF;
END //
DELIMITER ;

CALL AddColumnIfNotExists();
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
