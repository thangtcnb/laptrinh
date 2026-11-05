-- Migration: Reconcile and synchronize task statuses with assignees acceptance status
-- File: database/migrations/update_20261105_reconcile_coop_status.sql

USE `laptrinh_db`;

-- 1. Remove obsolete 'tp' account completely
DELETE FROM task_assignees WHERE user_id IN (SELECT id FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn') OR user_id = 24;
DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn') OR user_id = 24;
DELETE FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn' OR id = 24;

-- 2. Remove coop assignees from task 'Sắp xếp Hồ sơ', ensure Trần Thị Tâm is MAIN assignee
DELETE FROM task_assignees
WHERE role_type = 'COOP'
  AND task_id IN (SELECT id FROM tasks WHERE title LIKE '%Sắp xếp%hồ sơ%' OR title LIKE '%Sắp xếp%Hồ sơ%');

UPDATE task_assignees ta
JOIN tasks t ON ta.task_id = t.id
SET ta.role_type = 'MAIN', ta.is_accepted = 1, ta.accepted_at = COALESCE(ta.accepted_at, NOW())
WHERE (t.title LIKE '%Sắp xếp%hồ sơ%' OR t.title LIKE '%Sắp xếp%Hồ sơ%');

UPDATE tasks
SET status = 'DANG_THUC_HIEN'
WHERE (title LIKE '%Sắp xếp%hồ sơ%' OR title LIKE '%Sắp xếp%Hồ sơ%')
  AND status = 'CHO_TIEP_NHAN';

-- 3. Restore acceptance for assignees who already accepted according to task_logs (reversing old task edit wiping)
UPDATE task_assignees ta
JOIN task_logs tl ON ta.task_id = tl.task_id AND ta.user_id = tl.user_id AND tl.action = 'ACCEPT_TASK'
SET ta.is_accepted = 1,
    ta.accepted_at = COALESCE(ta.accepted_at, tl.created_at)
WHERE ta.is_accepted = 0 OR ta.is_accepted IS NULL;

-- 4. Auto-transition tasks stuck in CHO_TIEP_NHAN to DANG_THUC_HIEN (or target_status) when all assignees have accepted
UPDATE tasks t
SET status = CASE WHEN t.target_status = 'CHO_BGD_DUYET' THEN 'CHO_BGD_DUYET' ELSE 'DANG_THUC_HIEN' END
WHERE t.status = 'CHO_TIEP_NHAN'
  AND EXISTS (SELECT 1 FROM task_assignees WHERE task_id = t.id)
  AND NOT EXISTS (
      SELECT 1 FROM task_assignees ta
      WHERE ta.task_id = t.id AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
  );

-- 5. Ensure tasks that have unaccepted assignees and are in execution state wait in CHO_TIEP_NHAN
UPDATE tasks t
SET status = 'CHO_TIEP_NHAN', target_status = 'DANG_THUC_HIEN'
WHERE t.status = 'DANG_THUC_HIEN'
  AND EXISTS (
      SELECT 1 FROM task_assignees ta
      WHERE ta.task_id = t.id AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
  );
