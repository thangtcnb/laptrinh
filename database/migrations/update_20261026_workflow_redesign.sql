-- Migration: Redesign Workflow, multi-user acceptance, and status configuration
-- File: database/migrations/update_20261026_workflow_redesign.sql

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

-- 2. Add target_status column to tasks table if it does not exist
SET @dbname = DATABASE();
SET @tablename = "tasks";
SET @columnname = "target_status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_name = @tablename
    AND table_schema = @dbname
    AND column_name = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN target_status ENUM('CHO_BGD_DUYET', 'DANG_THUC_HIEN') NULL DEFAULT NULL AFTER status;"
));
PREPARE addTargetStatusColumn FROM @preparedStatement;
EXECUTE addTargetStatusColumn;
DEALLOCATE PREPARE addTargetStatusColumn;

-- 3. Add is_accepted and accepted_at columns to task_assignees table
SET @tablename = "task_assignees";
SET @columnname = "is_accepted";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_name = @tablename
    AND table_schema = @dbname
    AND column_name = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE task_assignees ADD COLUMN is_accepted TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN accepted_at DATETIME NULL DEFAULT NULL;"
));
PREPARE addIsAcceptedColumn FROM @preparedStatement;
EXECUTE addIsAcceptedColumn;
DEALLOCATE PREPARE addIsAcceptedColumn;
