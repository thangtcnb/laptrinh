USE `laptrinh_db`;
SET SQL_MODE = '';

-- 1. Alter users.role ENUM to include 'PHO_PHONG' and make department_id nullable
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('BGD', 'TRUONG_PHONG', 'PHO_PHONG', 'NHAN_VIEN', 'HO_TRO', 'SUPER_ADMIN') NOT NULL DEFAULT 'NHAN_VIEN';
ALTER TABLE `users` MODIFY COLUMN `department_id` INT NULL DEFAULT NULL;

-- 2. Update role to 'PHO_PHONG' for 3 specified personnel
UPDATE `users`
SET `role` = 'PHO_PHONG'
WHERE `email` IN (
    'tranquanghai@ufm.edu.vn',
    'huynhngocnghiem@ufm.edu.vn',
    'nguyenthiphuongthao@ufm.edu.vn'
) OR `fullname` IN (
    'Trần Quang Hải',
    'Huỳnh Ngọc Nghiêm',
    'Nguyễn Thị Phương Thảo'
);

-- 3. Insert 4 new users with password '123' (hashed via bcrypt)
INSERT INTO `users` (`fullname`, `email`, `password`, `role`, `department_id`) VALUES
('Nguyễn Bích Hường', 'nguyenbichhuong@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'HO_TRO', 3),
('Mai Thị Hoàng', 'maithihoang@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'HO_TRO', NULL),
('Đỗ Thị Ngọc Hương', 'huongdothingoc@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'HO_TRO', NULL),
('Lê Thị Lan Anh', 'leanh@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', NULL)
ON DUPLICATE KEY UPDATE
    `fullname` = VALUES(`fullname`),
    `role` = VALUES(`role`),
    `department_id` = VALUES(`department_id`);
