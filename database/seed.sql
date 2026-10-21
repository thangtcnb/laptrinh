USE `laptrinh_db`;

-- 1. Insert Departments
INSERT INTO `departments` (`id`, `code`, `name`, `color_theme`, `icon`) VALUES
(1, 'BGD', 'Ban Giám đốc', 'purple', 'fa-shield-halved'),
(2, 'DTT', 'Phòng Đào tạo - KH & QLSV', 'emerald', 'fa-graduation-cap'),
(3, 'HCTV', 'Phòng Hành chính – Tài vụ', 'blue', 'fa-briefcase');

-- 2. Insert Users (Password: 123456 hashed with bcrypt)
-- $2y$10$e.1sIq7U0G/M3aT3S3pP9OaWp34dJtXp2Wq1T4pW5yM2h3.K5x7/. (or we accept 123456 directly in auth)
INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `role`, `department_id`) VALUES
(1, 'TS. Nguyễn Văn A', 'bgd@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BGD', 1),
(2, 'ThS. Phạm Ngọc Thắng', 'thangpn@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TRUONG_PHONG', 2),
(3, 'ThS. Nguyễn Thị B', 'nguyenb@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TRUONG_PHONG', 2),
(4, 'Huỳnh Thị Anh Tùng', 'tungha@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NHAN_VIEN', 2),
(5, 'Lê Văn C', 'levanc@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NHAN_VIEN', 2),
(6, 'ThS. Trần Văn D', 'trand@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TRUONG_PHONG', 3),
(7, 'Nguyễn Thị E', 'nguyene@ufm.edu.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NHAN_VIEN', 3);

-- 3. Insert Tasks
INSERT INTO `tasks` (`id`, `title`, `description`, `department_id`, `status`, `priority`, `progress`, `proof_link`, `start_date`, `due_date`, `created_by`, `created_at`) VALUES
(1, 'Tổ chức Hội thảo Định hướng Nghề nghiệp cho Sinh viên K23', 'Chuẩn bị hội trường, liên hệ diễn giả và thông báo sinh viên các lớp tham gia đầy đủ.', 2, 'DANG_THUC_HIEN', 'CAO', 60, 'https://drive.google.com/drive/folders/ufm_quangngai_demo1', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 5 DAY, 2, CURRENT_TIMESTAMP),
(2, 'Rà soát & Tổng hợp Danh sách Học bổng Khuyến khích Học tập Học kỳ II', 'Tổng hợp điểm rèn luyện và trung bình học tập các lớp khóa K22, K23.', 2, 'CHO_DUYET', 'TRUNG_BINH', 0, NULL, CURDATE(), CURDATE() + INTERVAL 7 DAY, 4, CURRENT_TIMESTAMP),
(3, 'Mua sắm Trang thiết bị Máy tính và Máy chiếu cho Các Phòng học Kỹ thuật', 'Lập dự toán chi tiết trình BGD duyệt kinh phí trang thiết bị phòng học Quảng Ngãi.', 3, 'DANG_THUC_HIEN', 'KHAN_CAP', 40, 'https://drive.google.com/drive/folders/ufm_quangngai_demo2', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY, 6, CURRENT_TIMESTAMP),
(4, 'Xây dựng Kế hoạch Tuyển sinh Năm 2025 tại khu vực Duyên hải Nam Trung Bộ', 'Lập phương án tư vấn tuyển sinh trực tiếp tại các trường THPT tỉnh Quảng Ngãi.', 1, 'DANG_THUC_HIEN', 'KHAN_CAP', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_demo3', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 1, CURRENT_TIMESTAMP),
(5, 'Báo cáo Tổng kết Công tác Đào tạo và Quản lý Sinh viên năm học 2023 - 2024', 'Tổng hợp số liệu sinh viên tốt nghiệp, nợ môn và kết quả rèn luyện toàn Phân hiệu.', 2, 'CHO_BGD_DUYET', 'CAO', 90, 'https://drive.google.com/drive/folders/ufm_quangngai_demo4', CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 2 DAY, 2, CURRENT_TIMESTAMP),
(6, 'Kiểm tra & Bảo dưỡng Hệ thống Phòng máy Máy tính Phân hiệu Quảng Ngãi', 'Kiểm tra phần cứng, cài đặt phần mềm thi trực tuyến cho 3 phòng máy tính.', 3, 'HOAN_THANH', 'TRUNG_BINH', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_demo5', CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 1 DAY, 7, CURRENT_TIMESTAMP);

-- 4. Insert Task Assignees
INSERT INTO `task_assignees` (`task_id`, `user_id`, `role_type`) VALUES
(1, 2, 'MAIN'),
(1, 4, 'COOP'),
(2, 4, 'MAIN'),
(2, 5, 'COOP'),
(3, 6, 'MAIN'),
(3, 7, 'COOP'),
(4, 1, 'MAIN'),
(4, 2, 'COOP'),
(5, 2, 'MAIN'),
(5, 3, 'COOP'),
(6, 7, 'MAIN');
