USE laptrinh_db;
SET SQL_MODE = '';

-- Migration: Overdue Task Email Log Table
-- Migration Date: 2026-10-27

CREATE TABLE IF NOT EXISTS `overdue_email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `sent_date` DATE NOT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_overdue_notice_per_day` (`task_id`, `user_id`, `sent_date`),
    KEY `idx_sent_date` (`sent_date`),
    CONSTRAINT `fk_overdue_email_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_overdue_email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
