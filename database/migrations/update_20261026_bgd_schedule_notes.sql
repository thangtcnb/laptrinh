USE `laptrinh_db`;
SET SQL_MODE = '';

-- Create bgd_weekly_notes table for storing notes per week/year
CREATE TABLE IF NOT EXISTS `bgd_weekly_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `week_number` INT NOT NULL,
  `year` INT NOT NULL,
  `note_content` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_week_year` (`week_number`, `year`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe migration to add optional notes column to bgd_schedules if needed
SET @dbname = DATABASE();
SET @tablename = "bgd_schedules";
SET @columnname = "notes";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE bgd_schedules ADD COLUMN notes TEXT DEFAULT NULL AFTER location;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
