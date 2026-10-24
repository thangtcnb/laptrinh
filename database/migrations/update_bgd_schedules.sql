USE `laptrinh_db`;

-- Create bgd_schedules table for BGD Meeting Schedule module
CREATE TABLE IF NOT EXISTS `bgd_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bgd_user_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `event_time` DATETIME NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bgd_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
