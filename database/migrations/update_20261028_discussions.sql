USE `laptrinh_db`;
SET SQL_MODE = '';

-- Create discussions table for member discussion / chat feature
CREATE TABLE IF NOT EXISTS `discussions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
