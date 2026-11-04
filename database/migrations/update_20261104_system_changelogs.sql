-- Migration: Tạo bảng Nhật Ký Thay Đổi Hệ Thống (System Changelogs)
CREATE TABLE IF NOT EXISTS `system_changelogs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(50) NULL COMMENT 'Phiên bản ví dụ: v2.5, Update 04/11',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề nội dung thay đổi',
    `description` TEXT NOT NULL COMMENT 'Chi tiết các thay đổi / tính năng mới',
    `category` VARCHAR(50) DEFAULT 'FEATURE' COMMENT 'FEATURE, ENHANCEMENT, BUGFIX, UI, SECURITY',
    `released_date` DATE NOT NULL COMMENT 'Ngày áp dụng thay đổi',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`released_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm một số bản ghi gần nhất làm dữ liệu mẫu
INSERT INTO `system_changelogs` (`version`, `title`, `description`, `category`, `released_date`)
VALUES
('v2.5', 'Tích hợp Hồ sơ Minh chứng Google Drive & Tự động làm mới thời gian thực', '1. Tự động lưu bản sao minh chứng vào thư mục D:\\web\\minhchung và đồng bộ trực tiếp lên Google Drive qua Webhook.\n2. Tự động lấy link xem trực tiếp trả về giao diện người dùng.\n3. Cơ chế Auto-Refresh thời gian thực cập nhật trạng thái công việc khi có phát sinh dữ liệu mới.', 'FEATURE', '2026-11-04'),
('v2.4', 'Nâng cấp tính năng Từ chối phê duyệt công việc cho Trưởng phòng', '1. Bổ sung nút Từ chối phê duyệt trong cả form duyệt đơn lẻ và duyệt hàng loạt.\n2. Trưởng phòng nhập ý kiến chỉ đạo lý do từ chối.\n3. Nhân viên nhận thông báo và có quyền chỉnh sửa lại (tự động gửi lại Chờ duyệt) hoặc xóa công việc.', 'FEATURE', '2026-11-04'),
('v2.3', 'Khóa hạn chót sau khi Trưởng phòng duyệt & Bổ sung ô Ghi chú', '1. Khi Trưởng phòng đã phê duyệt, nhân viên không được phép tự ý thay đổi hạn chót (due date).\n2. Thêm ô Ghi chú của người thực hiện trong form Cập nhật tiến độ & Hồ sơ minh chứng.', 'ENHANCEMENT', '2026-11-04');
