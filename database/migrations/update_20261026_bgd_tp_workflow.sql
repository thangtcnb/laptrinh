-- Migration script for BGD & Department Head Workflow Adjustments
-- File: database/migrations/update_20261026_bgd_tp_workflow.sql

USE laptrinh_db;

-- 1. Ensure tasks created/assigned by BGD or TRUONG_PHONG currently in CHO_TIEP_NHAN have target_status set to 'DANG_THUC_HIEN'
UPDATE tasks t
JOIN users u ON t.created_by = u.id
SET t.target_status = 'DANG_THUC_HIEN'
WHERE t.status = 'CHO_TIEP_NHAN'
  AND (u.role IN ('BGD', 'TRUONG_PHONG', 'SUPER_ADMIN') OR t.target_status IS NULL OR t.target_status = 'CHO_BGD_DUYET');

-- 2. Automatically transition CHO_TIEP_NHAN tasks where all assignees have already accepted to 'DANG_THUC_HIEN'
UPDATE tasks t
SET t.status = 'DANG_THUC_HIEN'
WHERE t.status = 'CHO_TIEP_NHAN'
  AND t.id NOT IN (
      SELECT DISTINCT task_id
      FROM task_assignees
      WHERE is_accepted = 0 OR is_accepted IS NULL
  );
