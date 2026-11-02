-- Migration: Ensure CHO_TIEP_NHAN exists in tasks.status ENUM and fix blank statuses
-- File: database/migrations/update_20261102_fix_coop_status.sql

USE `laptrinh_db`;

-- 1. Modify ENUM status in tasks table to include 'CHO_TIEP_NHAN'
ALTER TABLE `tasks`
MODIFY COLUMN `status` ENUM(
    'CHO_BGD_DUYET',
    'CHO_DUYET',
    'CHO_XAC_NHAN',
    'CHO_TIEP_NHAN',
    'DANG_THUC_HIEN',
    'HOAN_THANH',
    'TU_CHOI_CHO_TP_XAC_NHAN',
    'YEU_CAU_CHINH_SUA'
) NOT NULL DEFAULT 'CHO_DUYET';

-- 2. Update any blank or null statuses for tasks
UPDATE `tasks` SET `status` = 'CHO_TIEP_NHAN' WHERE `status` = '' OR `status` IS NULL;
