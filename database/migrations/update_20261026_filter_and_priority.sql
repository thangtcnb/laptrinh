USE laptrinh_db;
SET SQL_MODE = '';

-- Migration: update_20261026_filter_and_priority.sql
-- Simplify task priority ENUM to 2 levels: NORMAL ('Bình thường') and URGENT ('Khẩn cấp')

UPDATE `tasks` SET `priority` = 'NORMAL' WHERE `priority` IN ('THAP', 'TRUNG_BINH', 'CAO');
UPDATE `tasks` SET `priority` = 'URGENT' WHERE `priority` = 'KHAN_CAP';

ALTER TABLE `tasks` MODIFY COLUMN `priority` ENUM('NORMAL', 'URGENT') NOT NULL DEFAULT 'NORMAL';
