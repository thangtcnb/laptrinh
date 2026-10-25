-- ========================================================================
-- Migration: update_20261025_approval_priority.sql
-- Ngày tạo: 25/10/2026
-- Mục đích: Bổ bổ sung trạng thái 'YEU_CAU_CHINH_SUA' vào cột ENUM status của bảng tasks.
--           Cập nhật quy trình phê duyệt công việc của Trưởng phòng và Ban Giám đốc,
--           đồng thời đảm bảo bảo toàn dữ liệu hiện có không bị xóa hay gián đoạn.
-- ========================================================================

USE `laptrinh_db`;

ALTER TABLE `tasks`
MODIFY COLUMN `status` ENUM(
    'CHO_BGD_DUYET',
    'CHO_DUYET',
    'CHO_XAC_NHAN',
    'DANG_THUC_HIEN',
    'HOAN_THANH',
    'TU_CHOI_CHO_TP_XAC_NHAN',
    'YEU_CAU_CHINH_SUA'
) NOT NULL DEFAULT 'CHO_DUYET';
