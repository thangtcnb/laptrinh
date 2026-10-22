CREATE DATABASE IF NOT EXISTS `laptrinh_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `laptrinh_db`;

-- 1. Departments table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `color_theme` VARCHAR(50) DEFAULT 'blue',
    `icon` VARCHAR(50) DEFAULT 'fa-building'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('BGD', 'TRUONG_PHONG', 'NHAN_VIEN') NOT NULL DEFAULT 'NHAN_VIEN',
    `department_id` INT NOT NULL,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tasks table
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `leader_comments` TEXT NULL,
    `department_id` INT NOT NULL,
    `status` ENUM('CHO_BGD_DUYET', 'CHO_DUYET', 'DANG_THUC_HIEN', 'HOAN_THANH') NOT NULL DEFAULT 'CHO_DUYET',
    `priority` ENUM('THAP', 'TRUNG_BINH', 'CAO', 'KHAN_CAP') NOT NULL DEFAULT 'TRUNG_BINH',
    `progress` INT NOT NULL DEFAULT 0,
    `proof_link` VARCHAR(500) NULL,
    `start_date` DATE NULL,
    `due_date` DATE NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Task Assignees table
CREATE TABLE IF NOT EXISTS `task_assignees` (
    `task_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role_type` ENUM('MAIN', 'COOP') NOT NULL DEFAULT 'MAIN',
    PRIMARY KEY (`task_id`, `user_id`),
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
