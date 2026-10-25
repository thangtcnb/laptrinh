-- Migration: Cap nhat CSDL ho tro Phe duyet hang loat (Batch Approval)
-- File: database/migrations/update_20261026_batch_approval.sql

-- Safe index creation for faster batch approval queries if not exists
SET @dbname = DATABASE();
SET @tablename = "tasks";
SET @columnname = "status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_name = @tablename
    AND table_schema = @dbname
    AND index_name = 'idx_tasks_status'
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_tasks_status ON tasks(status);"
));
PREPARE createIndexTasksStatus FROM @preparedStatement;
EXECUTE createIndexTasksStatus;
DEALLOCATE PREPARE createIndexTasksStatus;
