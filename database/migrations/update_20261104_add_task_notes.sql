USE `laptrinh_db`;
SET SQL_MODE = '';

-- Safe migration to add notes column to tasks table
SET @dbname = DATABASE();
SET @tablename = "tasks";
SET @columnname = "notes";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_name = @tablename
    AND table_schema = @dbname
    AND column_name = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN notes TEXT DEFAULT NULL COMMENT 'Ghi chu tien do hoac ket qua cua nguoi thuc hien' AFTER proof_link;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
