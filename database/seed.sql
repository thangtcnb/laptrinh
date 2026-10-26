USE `laptrinh_db`;

-- 1. Insert Departments
INSERT INTO `departments` (`id`, `code`, `name`, `color_theme`, `icon`) VALUES
(1, 'BGD', 'Ban Giám đốc', 'purple', 'fa-shield-halved'),
(2, 'DTT', 'Phòng Đào tạo - KH & QLSV', 'emerald', 'fa-graduation-cap'),
(3, 'HCTV', 'Phòng Hành chính – Tài vụ', 'blue', 'fa-briefcase');

-- 2. Insert Users (Password: '123' for normal users/BGD/Leaders; '123456' for Super Admin)
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

-- 3. Insert 50 Tasks (Spanning all 3 departments & 22 users)
-- 3. Insert 50 Tasks (Spanning all 3 departments & 22 users, including sample pending assignment & rejection tasks)
INSERT INTO `tasks` (`id`, `title`, `description`, `leader_comments`, `leader_comment_user_id`, `rejection_reason`, `department_id`, `status`, `priority`, `progress`, `proof_link`, `start_date`, `due_date`, `created_by`, `created_at`) VALUES
-- BAN GIÁM ĐỐC (Dept 1)
(1, 'Xây dựng Kế hoạch Tuyển sinh Năm 2025 tại khu vực Duyên hải Nam Trung Bộ', 'Lập phương án tư vấn tuyển sinh trực tiếp tại các trường THPT tỉnh Quảng Ngãi và phụ cận.', 'Ý kiến chỉ đạo (Lãnh đạo Phân hiệu): Đẩy mạnh truyền thông số và liên kết THPT địa phương.', 1, NULL, 1, 'DANG_THUC_HIEN', 'URGENT', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_task_01', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 1, CURRENT_TIMESTAMP),
(2, 'Tổ chức Cuộc họp Giao ban Ban Giám đốc Phân hiệu Định kỳ Tháng 11', 'Đánh giá tiến độ công tác tháng qua và triển khai nhiệm vụ trọng tâm học kỳ mới.', 'BGD yêu cầu các phòng gửi báo cáo trước họp 2 ngày.', 1, NULL, 1, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_02', CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 2 DAY, 1, CURRENT_TIMESTAMP),
(3, 'Duyệt Phương án Phối hợp Đào tạo và Liên kết Doanh nghiệp Tỉnh Quảng Ngãi', 'Xây dựng mạng lưới doanh nghiệp thực tập và tài trợ học bổng cho sinh viên Phân hiệu.', 'Đồng ý chủ trương. Giao Phòng ĐT-KH&QLSV chủ trì triển khai.', 2, NULL, 1, 'DANG_THUC_HIEN', 'NORMAL', 60, 'https://drive.google.com/drive/folders/ufm_quangngai_task_03', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 12 DAY, 2, CURRENT_TIMESTAMP),
(4, 'Làm việc với UBND Tỉnh Quảng Ngãi về Mở rộng Cơ sở Vật chất Phân hiệu', 'Trình đề xuất quy hoạch đất đai và hỗ trợ hạ tầng giáo dục tại Quảng Ngãi.', 'Yêu cầu phòng HCTV chuẩn bị hồ sơ pháp lý chi tiết.', 2, NULL, 1, 'CHO_BGD_DUYET', 'URGENT', 30, NULL, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 15 DAY, 2, CURRENT_TIMESTAMP),
(5, 'Chỉ đạo Rà soát Công tác Đảm bảo Chất lượng Đào tạo và Kiểm định Cơ sở Giáo dục', 'Kiểm tra quy trình giảng dạy, hồ sơ minh chứng phục vụ kiểm định chất lượng.', 'Cần hoàn tất tự đánh giá trước quý I/2025.', 1, NULL, 1, 'DANG_THUC_HIEN', 'NORMAL', 50, 'https://drive.google.com/drive/folders/ufm_quangngai_task_05', CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 20 DAY, 1, CURRENT_TIMESTAMP),
(6, 'Phê duyệt Dự toán Ngân sách Đào tạo và Thu chi Phân hiệu Học kỳ II', 'Duyệt dự toán kinh phí giảng dạy, cơ sở vật chất và hoạt động phong trào.', 'Đã thông qua Thường trực BGD.', 2, NULL, 1, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_06', CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 5 DAY, 2, CURRENT_TIMESTAMP),

-- PHÒNG ĐÀO TẠO - KH & QLSV (Dept 2)
(7, 'Tổ chức Hội thảo Định hướng Nghề nghiệp cho Sinh viên K23', 'Chuẩn bị hội trường, liên hệ diễn giả và thông báo sinh viên các lớp tham gia đầy đủ.', 'Ý kiến chỉ đạo: Cần hoàn tất công tác chuẩn bị trước ngày 15.', 11, NULL, 2, 'DANG_THUC_HIEN', 'NORMAL', 65, 'https://drive.google.com/drive/folders/ufm_quangngai_task_07', CURDATE() - INTERVAL 6 DAY, CURDATE() + INTERVAL 4 DAY, 11, CURRENT_TIMESTAMP),
(8, 'Rà soát & Tổng hợp Danh sách Học bổng Khuyến khích Học tập Học kỳ II', 'Tổng hợp điểm rèn luyện và trung bình học tập các lớp khóa K22, K23.', 'Trưởng phòng chỉ đạo: Rà soát kỹ danh sách sinh viên nợ môn.', 11, NULL, 2, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 7 DAY, 14, CURRENT_TIMESTAMP),
(9, 'Báo cáo Tổng kết Công tác Đào tạo và Quản lý Sinh viên năm học 2023 - 2024', 'Tổng hợp số liệu sinh viên tốt nghiệp, nợ môn và kết quả rèn luyện toàn Phân hiệu.', 'Đã trình BGD cho ý kiến chỉnh sửa.', 11, NULL, 2, 'CHO_BGD_DUYET', 'NORMAL', 90, 'https://drive.google.com/drive/folders/ufm_quangngai_task_09', CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 2 DAY, 11, CURRENT_TIMESTAMP),
(10, 'Lập Thời khóa biểu và Bố trí Giảng đường cho Học kỳ II Khóa K22, K23, K24', 'Xếp lịch học, lịch giảng viên thỉnh giảng và phân bổ phòng học hợp lý.', 'Trưởng phòng: Ưu tiên các môn thực hành máy tính.', 12, NULL, 2, 'DANG_THUC_HIEN', 'URGENT', 75, 'https://drive.google.com/drive/folders/ufm_quangngai_task_10', CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 3 DAY, 12, CURRENT_TIMESTAMP),
(11, 'Tổ chức Kỳ thi Kết thúc Học kỳ I Năm học 2024 - 2025 tại Phân hiệu Quảng Ngãi', 'Lập danh sách thi, in đề thi bảo mật, phân công cán bộ coi thi và chấm thi.', 'Chỉ đạo: Đảm bảo nghiêm túc, an toàn bảo mật tuyệt đối.', 13, NULL, 2, 'DANG_THUC_HIEN', 'URGENT', 40, 'https://drive.google.com/drive/folders/ufm_quangngai_task_11', CURDATE() - INTERVAL 4 DAY, CURDATE() + INTERVAL 10 DAY, 13, CURRENT_TIMESTAMP),
(12, 'Tiếp nhận và Xử lý Đơn xin Miễn giảm, Hoãn thi và Chuyển điểm của Sinh viên', 'Giải quyết các nguyện vọng sinh viên theo đúng quy chế đào tạo tín chỉ.', 'Cần giải quyết đúng hạn trong vòng 3 ngày làm việc.', 11, NULL, 2, 'DANG_THUC_HIEN', 'NORMAL', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_task_12', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 2 DAY, 15, CURRENT_TIMESTAMP),
(13, 'Tổng hợp Báo cáo NCKH Sinh viên và Đăng ký Đề tài Cấp Phân hiệu Năm 2024', 'Nghiệm thu các đề tài nghiên cứu khoa học của sinh viên và trao giải.', 'Trưởng phòng: Chuẩn bị khen thưởng cho các đề tài xuất sắc.', 11, NULL, 2, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_13', CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 3 DAY, 16, CURRENT_TIMESTAMP),
(14, 'Tổ chức Tuần sinh hoạt Công dân đầu khóa cho Tân sinh viên Khóa K24', 'Phổ biến quy chế đào tạo, chuẩn đầu ra và nội quy Phân hiệu Quảng Ngãi.', 'Đã hoàn thành xuất sắc, 100% tân sinh viên tham gia.', 11, NULL, 2, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_14', CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 10 DAY, 17, CURRENT_TIMESTAMP),
(15, 'Rà soát Điều kiện Tốt nghiệp và Lập Danh sách Sinh viên Đủ điều kiện Xét Tốt nghiệp', 'Thống kê chứng chỉ tiếng Anh, tin học, quốc phòng và số tín chỉ tích lũy.', 'Trưởng phòng: Kiểm tra kỹ chuẩn đầu ra ngoại ngữ.', 11, NULL, 2, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 8 DAY, 18, CURRENT_TIMESTAMP),
(16, 'Cấp phát Thẻ sinh viên, Giấy chứng nhận và Bảng điểm cho Sinh viên các Khóa', 'In ấn và phát trả hồ sơ giấy tờ hành chính sinh viên đúng quy định.', 'Đang thực hiện phát tại bộ phận 1 cửa.', 11, NULL, 2, 'CHO_XAC_NHAN', 'NORMAL', 0, NULL, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 5 DAY, 11, CURRENT_TIMESTAMP),
(17, 'Triển khai Đánh giá Điểm rèn luyện Sinh viên Học kỳ I qua Hệ thống Quản lý', 'Hướng dẫn Cố vấn học tập và Lớp trưởng chấm điểm rèn luyện trực tuyến.', 'Chỉ đạo: Đôn đốc các lớp hoàn thành đúng hạn.', 11, NULL, 2, 'DANG_THUC_HIEN', 'NORMAL', 55, 'https://drive.google.com/drive/folders/ufm_quangngai_task_17', CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 6 DAY, 20, CURRENT_TIMESTAMP),
(18, 'Phối hợp Tổ chức Ngày hội Việc làm Job Fair 2024 cho Sinh viên Sắp Tốt nghiệp', 'Mời các doanh nghiệp ngân hàng, tài chính, du lịch tại Quảng Ngãi tham gia.', 'Lãnh đạo phê duyệt chương trình chi tiết.', 11, NULL, 2, 'CHO_BGD_DUYET', 'NORMAL', 85, 'https://drive.google.com/drive/folders/ufm_quangngai_task_18', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 5 DAY, 21, CURRENT_TIMESTAMP),
(19, 'Quản lý Nội trú Ký túc xá và Kiểm tra An ninh Trật tự khu vực Sinh viên', 'Rà soát số lượng sinh viên ở KTX và phối hợp Công an phường giữ an ninh.', NULL, NULL, 'Trùng thời gian đi công tác đợt kiểm định chất lượng', 2, 'TU_CHOI_CHO_TP_XAC_NHAN', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 14 DAY, 11, CURRENT_TIMESTAMP),
(20, 'Cập nhật Dữ liệu Mã định danh và Thông tin Sinh viên lên CSDL Bộ GD&ĐT', 'Đồng bộ hóa dữ liệu tuyển sinh và quản lý sinh viên lên cổng thông tin Bộ.', 'Trưởng phòng: Đảm bảo độ chính xác 100%.', 11, NULL, 2, 'DANG_THUC_HIEN', 'URGENT', 90, 'https://drive.google.com/drive/folders/ufm_quangngai_task_20', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 1 DAY, 14, CURRENT_TIMESTAMP),
(21, 'Biên soạn và Triển khai Sổ tay Sinh viên Năm học 2024 - 2025', 'Cập nhật các quy định mới về học bổng, kỷ luật và hỗ trợ sinh viên.', NULL, NULL, NULL, 2, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_21', CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 15 DAY, 15, CURRENT_TIMESTAMP),
(22, 'Tổ chức Lễ Khai giảng và Trao Học bổng Đầu Năm học cho Sinh viên Xuất sắc', 'Lập kịch bản chương trình, đón tiếp khách mời và trao khen thưởng.', 'Đã diễn ra trang trọng đúng kế hoạch.', 11, NULL, 2, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_22', CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 12 DAY, 11, CURRENT_TIMESTAMP),
(23, 'Báo cáo Tình hình Sinh viên Tạm hoãn Học tập, Thôi học và Cảnh báo Học tập', 'Thống kê gửi Ban Giám đốc và Trường chính để theo dõi chất lượng.', 'Cần gửi báo cáo trước ngày 20 tháng này.', 12, NULL, 2, 'CHO_BGD_DUYET', 'NORMAL', 70, 'https://drive.google.com/drive/folders/ufm_quangngai_task_23', CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 3 DAY, 12, CURRENT_TIMESTAMP),
(24, 'Kiểm tra Đôn đốc Việc Giảng dạy đúng Tiết và Tiến độ Chương trình của Giảng viên', 'Dự giờ, kiểm tra sổ đầu bài và ghi nhận phản ánh của sinh viên.', NULL, NULL, NULL, 2, 'DANG_THUC_HIEN', 'NORMAL', 50, 'https://drive.google.com/drive/folders/ufm_quangngai_task_24', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 13, CURRENT_TIMESTAMP),
(25, 'Hướng dẫn Sinh viên Đăng ký Học phần Tín chỉ qua Portal Đào tạo', 'Mở cổng đăng ký môn học và giải quyết các sự cố nghẽn mạng.', 'Đã hỗ trợ xử lý kịp thời cho sinh viên.', 11, NULL, 2, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_25', CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 4 DAY, 16, CURRENT_TIMESTAMP),
(26, 'Phối hợp với Tỉnh Đoàn Quảng Ngãi Tổ chức Phong trào "Sinh viên 5 Tốt"', 'Tuyên truyền tiêu chí và xét chọn các cá nhân sinh viên tiêu biểu.', NULL, NULL, NULL, 2, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 15 DAY, 17, CURRENT_TIMESTAMP),
(27, 'Đón tiếp Hội đồng Đánh giá Ngoại Kiểm định Chất lượng Chương trình Đào tạo', 'Chuẩn bị phòng họp, minh chứng và điều kiện hậu cần cho đoàn kiểm định.', 'Trưởng phòng chỉ đạo: Chuẩn bị chu đáo mọi khâu.', 11, NULL, 2, 'DANG_THUC_HIEN', 'URGENT', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_task_27', CURDATE() - INTERVAL 14 DAY, CURDATE() + INTERVAL 2 DAY, 18, CURRENT_TIMESTAMP),
(28, 'Tổng hợp Đề xuất Mua sắm Giáo trình, Tài liệu Tham khảo cho Thư viện Phân hiệu', 'Thu thập danh mục sách từ bộ môn và trình kinh phí bổ sung thư viện.', NULL, NULL, NULL, 2, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 10 DAY, 19, CURRENT_TIMESTAMP),

-- PHÒNG HÀNH CHÍNH – TÀI VỤ (Dept 3)
(29, 'Mua sắm Trang thiết bị Máy tính và Máy chiếu cho Các Phòng học Kỹ thuật', 'Lập dự toán chi tiết trình BGD duyệt kinh phí trang thiết bị phòng học Quảng Ngãi.', 'Đồng ý chủ trương, yêu cầu làm việc với phòng KH-TV.', 3, NULL, 3, 'DANG_THUC_HIEN', 'URGENT', 40, 'https://drive.google.com/drive/folders/ufm_quangngai_task_29', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY, 3, CURRENT_TIMESTAMP),
(30, 'Kiểm tra & Bảo dưỡng Hệ thống Phòng máy Máy tính Phân hiệu Quảng Ngãi', 'Kiểm tra phần cứng, cài đặt phần mềm thi trực tuyến cho 3 phòng máy tính.', 'Đã nghiệm thu hoàn thành tốt.', 3, NULL, 3, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_30', CURDATE() - INTERVAL 15 DAY, CURDATE() - INTERVAL 1 DAY, 8, CURRENT_TIMESTAMP),
(31, 'Thu Học phí, Lệ phí và Quản lý Thu chi Học kỳ I Năm học 2024 - 2025', 'Thông báo thời hạn thu học phí, xuất hóa đơn điện tử cho sinh viên.', 'Trưởng phòng: Đôn đốc sinh viên hoàn thành nghĩa vụ học phí.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 85, 'https://drive.google.com/drive/folders/ufm_quangngai_task_31', CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 5 DAY, 4, CURRENT_TIMESTAMP),
(32, 'Lập Báo cáo Bảng lương, Phụ cấp và Bảo hiểm Xã hội Tháng 11 cho Cán bộ Giảng viên', 'Tính toán lương, phụ cấp ưu đãi giảng dạy và đóng BHXH đúng ngày.', 'Đã phê duyệt chi trả lương.', 3, NULL, 3, 'HOAN_THANH', 'URGENT', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_32', CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 1 DAY, 5, CURRENT_TIMESTAMP),
(33, 'Thực hiện Công tác Văn thư, Lưu trữ Công văn Đến/Đi và Quản lý Con dấu', 'Tiếp nhận công văn từ Bộ/Trường, vào sổ lưu trữ và chuyển giao các phòng.', NULL, NULL, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 60, 'https://drive.google.com/drive/folders/ufm_quangngai_task_33', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 15 DAY, 6, CURRENT_TIMESTAMP),
(34, 'Bảo trì, Sửa chữa Hệ thống Đèn chiếu sáng, Máy điều hòa các Phòng học và Văn phòng', 'Sửa chữa hỏng hóc điện nước, thay bóng đèn hỏng tại giảng đường A, B.', 'Trưởng phòng: Hoàn thành ngay trong tuần.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 70, 'https://drive.google.com/drive/folders/ufm_quangngai_task_34', CURDATE() - INTERVAL 4 DAY, CURDATE() + INTERVAL 2 DAY, 7, CURRENT_TIMESTAMP),
(35, 'Kiểm kê Tài sản, Cơ sở Vật chất và Trang thiết bị Phân hiệu Cuối năm 2024', 'Lập hội đồng kiểm kê máy tính, bàn ghế, thiết bị văn phòng toàn cơ sở.', 'Trưởng phòng chỉ đạo: Kiểm kê tỉ mỉ, đối chiếu sổ tài sản.', 3, NULL, 3, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 12 DAY, 8, CURRENT_TIMESTAMP),
(36, 'Báo cáo Quyết toán Ngân sách Thu chi Tài chính Năm 2024 Trình BGD và Trường', 'Tổng hợp hóa đơn chứng từ, đối chiếu số dư ngân hàng và lập báo cáo tài chính.', 'Chờ Ban Giám đốc thông qua.', 3, NULL, 3, 'CHO_BGD_DUYET', 'NORMAL', 88, 'https://drive.google.com/drive/folders/ufm_quangngai_task_36', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 4 DAY, 9, CURRENT_TIMESTAMP),
(37, 'Quản trị Hệ thống Mạng LAN, Wi-Fi và Server Dữ liệu Phân hiệu Quảng Ngãi', 'Nâng cấp băng thông Internet, khắc phục sự cố rớt mạng tại các phòng làm việc.', 'Đã xử lý xong sự cố đường truyền Wi-Fi giảng đường.', 3, NULL, 3, 'HOAN_THANH', 'URGENT', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_37', CURDATE() - INTERVAL 8 DAY, CURDATE() - INTERVAL 1 DAY, 10, CURRENT_TIMESTAMP),
(38, 'Phối hợp Bảo vệ An ninh Trật tự, Phòng cháy Chữa cháy khu vực Phân hiệu', 'Phối hợp Công an P. Chánh Lộ kiểm tra công tác PCCC và tập huấn cán bộ.', NULL, NULL, NULL, 3, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 9 DAY, 3, CURRENT_TIMESTAMP),
(39, 'Lập Dự toán Chi phí Điện, Nước, Dịch vụ Vệ sinh và Bảo vệ Học kỳ II', 'Thống kê hợp đồng cung ứng dịch vụ và trình kế hoạch kinh phí hoạt động.', NULL, NULL, NULL, 3, 'CHO_BGD_DUYET', 'NORMAL', 40, 'https://drive.google.com/drive/folders/ufm_quangngai_task_39', CURDATE() - INTERVAL 6 DAY, CURDATE() + INTERVAL 6 DAY, 4, CURRENT_TIMESTAMP),
(40, 'Triển khai Cài đặt Phần mềm Quản lý Văn bản E-Office cho Cán bộ Nhân viên', 'Cấp tài khoản và tập huấn sử dụng e-Office cho các đơn vị phòng ban.', 'Trưởng phòng: Đảm bảo 100% cán bộ sử dụng thành thạo.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 75, 'https://drive.google.com/drive/folders/ufm_quangngai_task_40', CURDATE() - INTERVAL 9 DAY, CURDATE() + INTERVAL 3 DAY, 10, CURRENT_TIMESTAMP),
(41, 'Tiếp nhận và Phát hành Công văn, Thông báo Chỉ đạo của BGD đến các Phòng ban', 'Soạn thảo văn bản hành chính và gửi chuyển tiếp qua hệ thống email nội bộ.', NULL, NULL, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 50, 'https://drive.google.com/drive/folders/ufm_quangngai_task_41', CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 7 DAY, 6, CURRENT_TIMESTAMP),
(42, 'Phối hợp Tổ chức Khám sức khỏe Định kỳ cho Cán bộ Viên chức và Người lao động', 'Liên hệ Bệnh viện Đa khoa tỉnh Quảng Ngãi lập danh sách và lịch khám.', 'BGD đã duyệt kinh phí khám sức khỏe.', 1, NULL, 3, 'CHO_BGD_DUYET', 'NORMAL', 30, NULL, CURDATE() - INTERVAL 4 DAY, CURDATE() + INTERVAL 11 DAY, 5, CURRENT_TIMESTAMP),
(43, 'Sửa chữa, Cải tạo Khu nhà Hiệu bộ và Phòng làm việc Cán bộ Giảng viên', 'Sơn lại tường hỏng, xử lý chống thấm mái nhà và thay gạch vỡ.', NULL, NULL, NULL, 3, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 15 DAY, 7, CURRENT_TIMESTAMP),
(44, 'Mua sắm Bổ sung Nước uống, Vật tư Y tế và Tủ thuốc Phân hiệu', 'Mua vật tư y tế sơ cứu ban đầu và nước uống đóng chai phục vụ giảng dạy.', NULL, NULL, NULL, 3, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 5 DAY, 8, CURRENT_TIMESTAMP),
(45, 'Thanh toán Chi phí Giảng dạy Thỉnh giảng cho Giảng viên Mời giảng Học kỳ I', 'Đối chiếu giờ dạy thỉnh giảng và làm thủ tục chuyển khoản thanh toán.', 'Trưởng phòng: Xử lý gấp cho giảng viên ngoại tỉnh.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 80, 'https://drive.google.com/drive/folders/ufm_quangngai_task_45', CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 2 DAY, 9, CURRENT_TIMESTAMP),
(46, 'Rà soát Hợp đồng Lao động, Nâng lương Định kỳ cho Cán bộ Nhân viên', 'Lập hồ sơ nâng bậc lương thường xuyên và nâng lương trước thời hạn.', 'Trưởng phòng đã phê duyệt danh sách.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 60, 'https://drive.google.com/drive/folders/ufm_quangngai_task_46', CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 7 DAY, 3, CURRENT_TIMESTAMP),
(47, 'Chuẩn bị Xe công tác và Trợ giúp Hậu cần cho Đoàn công tác Trường về làm việc', 'Bố trí xe đưa đón sân bay Chu Lai/Đà Nẵng và đặt phòng nghỉ cho đoàn.', 'Đã hoàn thành công tác hậu cần chu đáo.', 3, NULL, 3, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_47', CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 3 DAY, 6, CURRENT_TIMESTAMP),
(48, 'Cập nhật Danh mục Tài sản Cố định trên Phần mềm Quản lý Tài sản Công', 'Nhập dữ liệu máy móc, ô tô, nhà cửa đất đai vào phần mềm CSDL Quốc gia.', NULL, NULL, NULL, 3, 'CHO_DUYET', 'NORMAL', 0, NULL, CURDATE(), CURDATE() + INTERVAL 10 DAY, 7, CURRENT_TIMESTAMP),
(49, 'Nâng cấp Hệ thống Camera Giám sát An ninh tại các Cổng và Hành lang Phân hiệu', 'Lắp bổ sung 6 mắt camera IP độ phân giải cao giám sát ký túc xá và cổng chính.', 'Trưởng phòng: Tiến hành nghiệm thu kỹ thuật.', 3, NULL, 3, 'DANG_THUC_HIEN', 'NORMAL', 75, 'https://drive.google.com/drive/folders/ufm_quangngai_task_49', CURDATE() - INTERVAL 11 DAY, CURDATE() + INTERVAL 1 DAY, 10, CURRENT_TIMESTAMP),
(50, 'Tổng hợp Thi đua Khen thưởng Cán bộ Viên chức Cuối Năm học 2023 - 2024', 'Lập danh sách Chiến sĩ thi đua cơ sở, Lao động tiên tiến trình Hội đồng trường.', 'Đã hoàn tất báo cáo thi đua khen thưởng.', 3, NULL, 3, 'HOAN_THANH', 'NORMAL', 100, 'https://drive.google.com/drive/folders/ufm_quangngai_task_50', CURDATE() - INTERVAL 22 DAY, CURDATE() - INTERVAL 5 DAY, 4, CURRENT_TIMESTAMP);

-- 4. Insert Task Assignees (Mapping for all 50 tasks across 22 users)
INSERT INTO `task_assignees` (`task_id`, `user_id`, `role_type`) VALUES
-- Tasks 1-6 (BGD)
(1, 1, 'MAIN'), (1, 11, 'COOP'), (1, 3, 'COOP'),
(2, 1, 'MAIN'), (2, 2, 'COOP'),
(3, 2, 'MAIN'), (3, 11, 'COOP'),
(4, 2, 'MAIN'), (4, 3, 'COOP'),
(5, 1, 'MAIN'), (5, 12, 'COOP'),
(6, 2, 'MAIN'), (6, 4, 'COOP'),

-- Tasks 7-28 (DTT)
(7, 11, 'MAIN'), (7, 14, 'COOP'), (7, 15, 'COOP'),
(8, 14, 'MAIN'), (8, 15, 'COOP'),
(9, 11, 'MAIN'), (9, 12, 'COOP'), (9, 13, 'COOP'),
(10, 12, 'MAIN'), (10, 16, 'COOP'),
(11, 13, 'MAIN'), (11, 17, 'COOP'), (11, 18, 'COOP'),
(12, 15, 'MAIN'), (12, 19, 'COOP'),
(13, 16, 'MAIN'), (13, 20, 'COOP'),
(14, 17, 'MAIN'), (14, 21, 'COOP'),
(15, 18, 'MAIN'), (15, 22, 'COOP'),
(16, 19, 'MAIN'), (16, 14, 'COOP'),
(17, 20, 'MAIN'), (17, 15, 'COOP'),
(18, 21, 'MAIN'), (18, 11, 'COOP'), (18, 16, 'COOP'),
(19, 22, 'MAIN'), (19, 17, 'COOP'),
(20, 14, 'MAIN'), (20, 18, 'COOP'),
(21, 15, 'MAIN'), (21, 19, 'COOP'),
(22, 11, 'MAIN'), (22, 20, 'COOP'), (22, 21, 'COOP'),
(23, 12, 'MAIN'), (23, 22, 'COOP'),
(24, 13, 'MAIN'), (24, 14, 'COOP'),
(25, 16, 'MAIN'), (25, 15, 'COOP'),
(26, 17, 'MAIN'), (26, 18, 'COOP'),
(27, 18, 'MAIN'), (27, 11, 'COOP'), (27, 12, 'COOP'),
(28, 19, 'MAIN'), (28, 20, 'COOP'),

-- Tasks 29-50 (HCTV)
(29, 3, 'MAIN'), (29, 8, 'COOP'), (29, 10, 'COOP'),
(30, 8, 'MAIN'), (30, 10, 'COOP'),
(31, 4, 'MAIN'), (31, 5, 'COOP'), (31, 9, 'COOP'),
(32, 5, 'MAIN'), (32, 4, 'COOP'),
(33, 6, 'MAIN'), (33, 7, 'COOP'),
(34, 7, 'MAIN'), (34, 8, 'COOP'),
(35, 8, 'MAIN'), (35, 9, 'COOP'),
(36, 9, 'MAIN'), (36, 3, 'COOP'), (36, 4, 'COOP'),
(37, 10, 'MAIN'), (37, 8, 'COOP'),
(38, 3, 'MAIN'), (38, 6, 'COOP'),
(39, 4, 'MAIN'), (39, 7, 'COOP'),
(40, 10, 'MAIN'), (40, 5, 'COOP'), (40, 6, 'COOP'),
(41, 6, 'MAIN'), (41, 7, 'COOP'),
(42, 5, 'MAIN'), (42, 3, 'COOP'),
(43, 7, 'MAIN'), (43, 8, 'COOP'),
(44, 8, 'MAIN'), (44, 6, 'COOP'),
(45, 9, 'MAIN'), (45, 4, 'COOP'),
(46, 3, 'MAIN'), (46, 5, 'COOP'),
(47, 6, 'MAIN'), (47, 7, 'COOP'), (47, 8, 'COOP'),
(48, 7, 'MAIN'), (48, 9, 'COOP'),
(49, 10, 'MAIN'), (49, 3, 'COOP'),
(50, 4, 'MAIN'), (50, 5, 'COOP'), (50, 6, 'COOP');
