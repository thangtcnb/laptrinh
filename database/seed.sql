USE `laptrinh_db`;

-- 1. Insert Departments
INSERT INTO `departments` (`id`, `code`, `name`, `color_theme`, `icon`) VALUES
(1, 'BGD', 'Ban Giám đốc', 'purple', 'fa-shield-halved'),
(2, 'DTT', 'Phòng Đào tạo - KH & QLSV', 'emerald', 'fa-graduation-cap'),
(3, 'HCTV', 'Phòng Hành chính – Tài vụ', 'blue', 'fa-briefcase');

-- 2. Insert Users (Password: 123 hashed with bcrypt)
INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `role`, `department_id`) VALUES
-- SUPER ADMIN
(23, 'Super Admin', 'thangtcnb@gmail.com', '$2y$10$U5rp69GsmtWuIOSaMA9dN.NiOi4Vphs5liaLudaMdTqyqHtG7b1WW', 'SUPER_ADMIN', 1),

-- BAN GIÁM ĐỐC
(1, 'Bùi Thị Yến Linh', 'yenlinhbt@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'BGD', 1),
(2, 'Lê Xuân Lãm', 'lexuanlam@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'BGD', 1),

-- PHÒNG HÀNH CHÍNH – TÀI VỤ
(3, 'Trần Thị Bích Liên', 'tranthibichlien@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'TRUONG_PHONG', 3),
(4, 'Nguyễn Thị Phương Thảo', 'nguyenthiphuongthao@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'TRUONG_PHONG', 3),
(5, 'Trần Thị Tâm', 'tranthitam@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),
(6, 'Huỳnh Thị Anh Tùng', 'huynhthianhtung@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),
(7, 'Nguyễn Thị Kim Dung', 'nguyenthikimdung@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),
(8, 'Phạm Ngọc Thắng', 'phamngocthang@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),
(9, 'Đinh Thanh Hà', 'dinhthanhha@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),
(10, 'Bùi Trần Quyết Thắng', 'tranthang@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 3),

-- PHÒNG ĐÀO TẠO - KHOA HỌC VÀ QUẢN LÝ SINH VIÊN
(11, 'Phạm Hoài Nam', 'phamhoainam@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'TRUONG_PHONG', 2),
(12, 'Huỳnh Ngọc Nghiêm', 'huynhngocnghiem@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'TRUONG_PHONG', 2),
(13, 'Trần Quang Hải', 'tranquanghai@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'TRUONG_PHONG', 2),
(14, 'Phạm Thị Thu Hảo', 'phamthuhao@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(15, 'Tạ Thị Quỳnh Ngọc', 'tathiquynhngoc@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(16, 'Huỳnh Thị Thanh Ri', 'huynhthithanhri@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(17, 'Võ Văn Thảo', 'vovanthao@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(18, 'Nguyễn Thị Thu Thủy', 'nguyenthithuthuy@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(19, 'Diệp Quỳnh Trâm', 'diepquynhtram@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(20, 'Lê Thị Tuyết Dung', 'tuyetdung.le@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(21, 'Nguyễn Thị Quỳnh Duyên', 'nguyenquynhduyen@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2),
(22, 'Đặng Duy Thanh Hương', 'dangduythanhhuong@ufm.edu.vn', '$2y$10$nqS0vtSgJqksgXn8g2LeK.j9Nt40MF5zfx.x18ToNBMhSKYBBKqJC', 'NHAN_VIEN', 2);

-- 3. Insert Tasks
INSERT INTO `tasks` (`id`, `title`, `description`, `leader_comments`, `department_id`, `status`, `priority`, `progress`, `proof_link`, `start_date`, `due_date`, `created_by`, `created_at`) VALUES
(1, 'Tổ chức Hội thảo Định hướng Nghề nghiệp cho Sinh viên K23', 'Chuẩn bị hội trường, liên hệ diễn giả và thông báo sinh viên các lớp tham gia đầy đủ.', 'Ý kiến chỉ đạo: Cần hoàn tất công tác chuẩn bị trước ngày 15.', 2, 'DANG_THUC_HIEN', 'CAO', 60, 'https://drive.google.com/drive/folders/ufm_quangngai_demo1', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 5 DAY, 11, CURRENT_TIMESTAMP),
(2, 'Rà soát & Tổng hợp Danh sách Học bổng Khuyến khích Học tập Học kỳ II', 'Tổng hợp điểm rèn luyện và trung bình học tập các lớp khóa K22, K23.', NULL, 2, 'CHO_DUYET', 'TRUNG_BINH', 0, NULL, CURDATE(), CURDATE() + INTERVAL 7 DAY, 14, CURRENT_TIMESTAMP),
(3, 'Mua sắm Trang thiết bị Máy tính và Máy chiếu cho Các Phòng học Kỹ thuật', 'Lập dự toán chi tiết trình BGD duyệt kinh phí trang thiết bị phòng học Quảng Ngãi.', 'Đồng ý chủ trương, yêu cầu làm việc với phòng KH-TV.', 3, 'DANG_THUC_HIEN', 'KHAN_CAP', 40, 'https://drive.google.com/drive/folders/ufm_quangngai_demo2', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY, 3, CURRENT_TIMESTAMP),
(4, 'Xây dựng Kế hoạch Tuyển sinh Năm 2025 tại khu vực Duyên hải Nam Trung Bộ', 'Lập phương án tư vấn tuyển sinh trực tiếp tại các trường THPT tỉnh Quảng Ngãi.', NULL, 1, 'DANG_THUC_HIEN', 'KHAN_CAP', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_demo3', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 1, CURRENT_TIMESTAMP),
(5, 'Báo cáo Tổng kết Công tác Đào tạo và Quản lý Sinh viên năm học 2023 - 2024', 'Tổng hợp số liệu sinh viên tốt nghiệp, nợ môn và kết quả rèn luyện toàn Phân hiệu.', NULL, 2, 'CHO_BGD_DUYET', 'CAO', 90, 'https://drive.google.com/drive/folders/ufm_quangngai_demo4', CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 2 DAY, 11, CURRENT_TIMESTAMP),
(6, 'Kiểm tra & Bảo dưỡng Hệ thống Phòng máy Máy tính Phân hiệu Quảng Ngãi', 'Kiểm tra phần cứng, cài đặt phần mềm thi trực tuyến cho 3 phòng máy tính.', 'Đã nghiệm thu hoàn thành tốt.', 3, 'HOAN_THANH', 'TRUNG_BINH', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_demo5', CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 1 DAY, 8, CURRENT_TIMESTAMP);

-- 4. Insert Task Assignees
INSERT INTO `task_assignees` (`task_id`, `user_id`, `role_type`) VALUES
(1, 11, 'MAIN'),
(1, 14, 'COOP'),
(2, 14, 'MAIN'),
(2, 15, 'COOP'),
(3, 3, 'MAIN'),
(3, 8, 'COOP'),
(4, 1, 'MAIN'),
(4, 11, 'COOP'),
(5, 11, 'MAIN'),
(5, 12, 'COOP'),
(6, 8, 'MAIN');
