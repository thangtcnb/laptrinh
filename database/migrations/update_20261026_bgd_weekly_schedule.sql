USE `laptrinh_db`;

-- Create bgd_schedules table for BGD Weekly Schedule module if not exists
CREATE TABLE IF NOT EXISTS `bgd_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_date` DATE NOT NULL UNIQUE,
  `content` TEXT DEFAULT NULL,
  `participants` TEXT DEFAULT NULL,
  `chairperson` VARCHAR(255) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe migration checks in case bgd_schedules was created under earlier schema
SET @dbname = DATABASE();
SET @tablename = "bgd_schedules";

-- Make old bgd_user_id column nullable if exists
SET @columnname = "bgd_user_id";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "ALTER TABLE bgd_schedules MODIFY COLUMN bgd_user_id INT NULL;",
  "SELECT 1"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make old event_time column nullable if exists
SET @columnname = "event_time";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "ALTER TABLE bgd_schedules MODIFY COLUMN event_time DATETIME NULL;",
  "SELECT 1"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add schedule_date column if missing
SET @columnname = "schedule_date";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE bgd_schedules ADD COLUMN schedule_date DATE NOT NULL AFTER id;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add participants column if missing
SET @columnname = "participants";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE bgd_schedules ADD COLUMN participants TEXT DEFAULT NULL AFTER content;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add chairperson column if missing
SET @columnname = "chairperson";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE bgd_schedules ADD COLUMN chairperson VARCHAR(255) DEFAULT NULL AFTER participants;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
