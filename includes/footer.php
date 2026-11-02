</main>

<footer class="bg-slate-900 text-slate-400 border-t border-slate-800 text-xs py-6 mt-10 no-print">
    <div class="w-full px-4 sm:px-6 lg:px-8 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <div class="font-bold text-slate-200">
                PHÂN HIỆU TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING TẠI TỈNH QUẢNG NGÃI
            </div>
            <div class="text-slate-400 mt-0.5">
                Địa chỉ: Số 02 Lê Quí Đôn, xã Tư Nghĩa, tỉnh Quảng Ngãi
            </div>
        </div>
        <div class="text-slate-500 text-right">
            <div>Hệ thống Quản lý công việc Nội bộ - Phiên bản thử nghiệm</div>
            <div>&copy; 2026 Phân hiệu UFM Quảng Ngãi. All rights reserved.</div>
        </div>
    </div>
</footer>

<script>
/**
 * Tự động xử lý khi click vào các liên kết có chứa drive.google.com:
 * 1. Chặn toàn bộ hành vi mở tab mới hoặc reload bên trong ứng dụng (e.preventDefault(), e.stopPropagation())
 * 2. Giữ nguyên trang web hiện tại chạy bình thường bên trong ứng dụng
 * 3. Gửi yêu cầu ngầm qua bridge (hidden iframe với URL http://open-external/?url=...)
 *    để ứng dụng Desktop Python kích hoạt mở trực tiếp bằng trình duyệt Google Chrome bên ngoài
 * 4. Fallback: Nếu mở trên trình duyệt web thông thường, mở tab mới bình thường
 */
function handleDriveGoogleLink(url, event) {
    if (event) {
        if (typeof event.preventDefault === 'function') event.preventDefault();
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
    }
    if (!url) return false;

    // Chuẩn hóa giao thức https:// nếu thiếu
    var targetUrl = url.trim();
    if (!/^https?:\/\//i.test(targetUrl)) {
        targetUrl = 'https://' + targetUrl;
    }

    // Kiểm tra xem đang chạy trong Desktop App (PyQt6 WebEngine) hay trình duyệt web thông thường
    var isDesktopApp = (window.IS_DESKTOP_APP === true) || 
                       (navigator.userAgent && (navigator.userAgent.indexOf('UFMTaskApp') !== -1 || navigator.userAgent.indexOf('QtWebEngine') !== -1));

    // 1. Tạo iframe ẩn gửi yêu cầu ngầm tới bridge để ứng dụng Python mở Chrome
    var hiddenFrame = document.createElement('iframe');
    hiddenFrame.style.display = 'none';
    hiddenFrame.style.width = '0px';
    hiddenFrame.style.height = '0px';
    hiddenFrame.style.border = 'none';
    hiddenFrame.src = 'http://open-external/?url=' + encodeURIComponent(targetUrl);
    document.body.appendChild(hiddenFrame);

    setTimeout(function() {
        if (hiddenFrame && hiddenFrame.parentNode) {
            hiddenFrame.parentNode.removeChild(hiddenFrame);
        }
    }, 1000);

    // 2. Nếu người dùng đang dùng trình duyệt ngoài bình thường (không phải Desktop app QtWebEngine):
    if (!isDesktopApp) {
        window.open(targetUrl, '_blank', 'noopener,noreferrer');
    }

    return false;
}

// Lắng nghe sự kiện click toàn trang cho bất kỳ liên kết <a> nào có chứa drive.google.com
document.addEventListener('click', function(e) {
    var a = e.target.closest('a');
    if (!a) return;

    var href = a.getAttribute('href') || '';
    if (href.indexOf('drive.google.com') !== -1) {
        // Chặn ngay lập tức hành vi mặc định để không mở tab mới bên trong ứng dụng
        e.preventDefault();
        e.stopPropagation();
        handleDriveGoogleLink(href, e);
    }
}, true);
</script>
</body>
</html>