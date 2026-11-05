-- Migration: Reconcile and synchronize task statuses with assignees acceptance status
-- File: database/migrations/update_20261105_reconcile_coop_status.sql

USE `laptrinh_db`;

-- 1. Restore acceptance for assignees who already accepted according to task_logs (reversing old task edit wiping)
UPDATE task_assignees ta
JOIN task_logs tl ON ta.task_id = tl.task_id AND ta.user_id = tl.user_id AND tl.action = 'ACCEPT_TASK'
SET ta.is_accepted = 1,
    ta.accepted_at = COALESCE(ta.accepted_at, tl.created_at)
WHERE ta.is_accepted = 0 OR ta.is_accepted IS NULL;

-- 2. Update generic 'Trưởng phòng HCTV' to actual name 'Trần Thị Bích Liên'
UPDATE users SET fullname = 'Trần Thị Bích Liên' WHERE fullname = 'Trưởng phòng HCTV' OR email = 'tp';

-- 3. Auto-transition tasks stuck in CHO_TIEP_NHAN to DANG_THUC_HIEN (or target_status) when all assignees have accepted
UPDATE tasks t
SET status = CASE WHEN t.target_status = 'CHO_BGD_DUYET' THEN 'CHO_BGD_DUYET' ELSE 'DANG_THUC_HIEN' END
WHERE t.status = 'CHO_TIEP_NHAN'
  AND EXISTS (SELECT 1 FROM task_assignees WHERE task_id = t.id)
  AND NOT EXISTS (
      SELECT 1 FROM task_assignees ta
      WHERE ta.task_id = t.id AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
  );

-- 4. Ensure tasks that have unaccepted assignees and are in execution state wait in CHO_TIEP_NHAN
UPDATE tasks t
SET status = 'CHO_TIEP_NHAN', target_status = 'DANG_THUC_HIEN'
WHERE t.status = 'DANG_THUC_HIEN'
  AND EXISTS (
      SELECT 1 FROM task_assignees ta
      WHERE ta.task_id = t.id AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
  );
