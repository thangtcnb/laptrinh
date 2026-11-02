USE `laptrinh_db`;
SET SQL_MODE = '';

-- Migration: Add Truong Phong account (username: tp / password: tp)
-- Department: 3 (Phòng Hành chính – Tài vụ)
INSERT INTO `users` (`fullname`, `email`, `password`, `role`, `department_id`)
VALUES (
    'Trưởng phòng HCTV',
    'tp',
    '$2y$10$71WiWQKWK9jIU05VNPq23O/uacRWwQ89qt2yUjRha7IeTxaXKS.y2',
    'TRUONG_PHONG',
    3
)
ON DUPLICATE KEY UPDATE
    `fullname` = VALUES(`fullname`),
    `password` = VALUES(`password`),
    `role` = 'TRUONG_PHONG',
    `department_id` = 3;
