-- Migration: Them column comment_feedback cho phep nhan vien gui y kien khac / de xuat
-- Ngay tao: 2026-10-23

USE laptrinh_db;

ALTER TABLE tasks
ADD COLUMN comment_feedback TEXT DEFAULT NULL COMMENT 'Y kien khac, de xuat hoac vuong mac cua nhan vien khi nhan cong viec';
