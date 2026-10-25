-- Migration file for System Settings (Login Background) & Task Logs (Timeline History)
USE `laptrinh_db`;

-- 1. System Settings table for storing application level configurations (e.g., login background image)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default login_bg if not exists
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('login_bg', '');

-- 2. Task Logs table for storing detailed workflow processing history / timeline log per task
CREATE TABLE IF NOT EXISTS `task_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
