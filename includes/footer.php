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
 * 1. Gọi hàm mở trình duyệt mặc định của hệ thống thông qua window.open
 * 2. Hoặc chuyển hướng URL trực tiếp tại window chính (window.location.href)
 *    để trình duyệt / môi trường Desktop WebEngine bắt sự kiện mở trình duyệt ngoài (Chrome).
 */
function handleDriveGoogleLink(url, event) {
    if (event) {
        if (typeof event.preventDefault === 'function') event.preventDefault();
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
    }
    if (!url) return false;

    // Đảm bảo có tiền tố giao thức https://
    var targetUrl = url.trim();
    if (!/^https?:\/\//i.test(targetUrl)) {
        targetUrl = 'https://' + targetUrl;
    }

    var isWindowOpened = false;
    try {
        var win = window.open(targetUrl, '_blank', 'noopener,noreferrer');
        if (win && !win.closed && typeof win.closed !== 'undefined') {
            isWindowOpened = true;
        }
    } catch (err) {
        isWindowOpened = false;
    }

    // Nếu không mở được cửa sổ mới (hoặc trong ứng dụng Desktop / WebView chặn popup),
    // chuyển hướng URL trực tiếp tại window chính
    if (!isWindowOpened) {
        window.location.href = targetUrl;
    }
    return false;
}

// Lắng nghe sự kiện click toàn trang cho bất kỳ liên kết <a> nào có chứa drive.google.com
document.addEventListener('click', function(e) {
    var a = e.target.closest('a');
    if (!a) return;

    var href = a.getAttribute('href') || '';
    if (href.indexOf('drive.google.com') !== -1) {
        handleDriveGoogleLink(href, e);
    }
}, true);
</script>
</body>
</html>
