CREATE DATABASE IF NOT EXISTS `laptrinh_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `laptrinh_db`;

-- SQL khoi tao bang discussions
CREATE TABLE IF NOT EXISTS discussions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cap nhat an toan neu bang discussions da ton tai theo schema cu
SET @dbname = DATABASE();
SET @tablename = "discussions";

-- Check va add task_id
SET @columnname = "task_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE discussions ADD COLUMN task_id INT DEFAULT NULL AFTER id;"
));
PREPARE add_col FROM @preparedStatement;
EXECUTE add_col;
DEALLOCATE PREPARE add_col;

-- Check va add message
SET @columnname = "message";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE discussions ADD COLUMN message TEXT NOT NULL AFTER user_id;"
));
PREPARE add_col FROM @preparedStatement;
EXECUTE add_col;
DEALLOCATE PREPARE add_col;

-- Sync giu lieu tu content sang message neu co cot content
SET @columnname = "content";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
  ) > 0,
  "UPDATE discussions SET message = content WHERE (message IS NULL OR message = '') AND content IS NOT NULL AND content != '';",
  "SELECT 1"
));
PREPARE sync_msg FROM @preparedStatement;
EXECUTE sync_msg;
DEALLOCATE PREPARE sync_msg;
