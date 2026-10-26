USE `laptrinh_db`;
SET SQL_MODE = '';

-- Safe Migration for Overdue Tasks & Weekly Schedule Form Optimization
-- Migration Date: 2026-10-26

-- Ensure index on due_date and status in tasks table for fast overdue task queries
SET @dbname = DATABASE();
SET @tablename = "tasks";
SET @indexname = "idx_tasks_due_status";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
  "SELECT 1;",
  "CREATE INDEX idx_tasks_due_status ON tasks(due_date, status);"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure index on schedule_date in bgd_schedules table for fast weekly schedule queries
SET @tablename = "bgd_schedules";
SET @indexname = "idx_bgd_schedules_date";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
  "SELECT 1;",
  "CREATE INDEX idx_bgd_schedules_date ON bgd_schedules(schedule_date);"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
