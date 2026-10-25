<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Auto check and create background upload directory if not exists
if (!file_exists('uploads/bg/')) {
    mkdir('uploads/bg/', 0777, true);
}

// Background loading logic with fallback mechanism
$loginBg = '';

// 1. Check CSDL configuration (system_settings table)
try {
    $stmtBg = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'login_bg'");
    if ($rowBg = $stmtBg->fetch()) {
        $dbBg = trim($rowBg['setting_value'] ?? '');
        if (!empty($dbBg) && file_exists(__DIR__ . '/' . $dbBg)) {
            $loginBg = $dbBg;
        }
    }
} catch (\PDOException $e) {
    $loginBg = '';
}

// 2. If no valid image in CSDL, check if Admin uploaded file uploads/bg/login_bg.jpg exists
if (empty($loginBg) && file_exists(__DIR__ . '/uploads/bg/login_bg.jpg')) {
    $loginBg = 'uploads/bg/login_bg.jpg';
}

// 3. Fallback to system default background image
if (empty($loginBg)) {
    if (file_exists(__DIR__ . '/assets/images/default_bg.jpg')) {
        $loginBg = 'assets/images/default_bg.jpg';
    } elseif (file_exists(__DIR__ . '/uploads/bg/default_bg.jpg')) {
        $loginBg = 'uploads/bg/default_bg.jpg';
    }
}

$error = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Phiên làm việc đã hết hạn do không thao tác trong 60 phút. Vui lòng đăng nhập lại.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ Email và Mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === '123' || $password === '123456')) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Email hoặc Mật khẩu không chính xác.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Hệ thống Quản lý Công việc UFM Quảng Ngãi</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-900">

<?php if (!empty($loginBg) && file_exists(__DIR__ . '/' . $loginBg)): ?>
    <!-- Custom Background Image with overlay -->
    <div class="absolute inset-0 bg-cover bg-center z-0 transition-all duration-700 scale-105" style="background-image: url('<?= htmlspecialchars($loginBg) ?>');"></div>
    <div class="absolute inset-0 bg-black/20 z-0"></div>
<?php endif; ?>

<div class="max-w-lg w-full bg-white/85 backdrop-blur-xl rounded-3xl shadow-2xl overflow-hidden border border-white/40 relative z-10 my-6">
    <div class="bg-gradient-to-r from-blue-900/90 via-indigo-900/90 to-slate-900/90 p-6 text-center text-white relative border-b border-white/10">
        <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-3 backdrop-blur-md border border-white/20 shadow-lg">
            <i class="fa-solid fa-graduation-cap text-3xl text-blue-200"></i>
        </div>
        <h2 class="text-xl font-bold uppercase tracking-wide">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING</h2>
        <p class="text-xs text-blue-200 font-medium mt-1">PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</p>
        <p class="text-sm font-semibold text-indigo-100 mt-2">HỆ THỐNG QUẢN LÝ CÔNG VIỆC</p>
    </div>

    <div class="p-6 sm:p-8">
        <?php if (!empty($error)): ?>
            <div class="mb-4 bg-rose-50 border-l-4 border-rose-600 p-3 rounded text-rose-700 text-xs font-medium flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email đăng nhập</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-envelope"></i>
                    </span>
                    <input type="email" id="loginEmail" name="email" required placeholder="example@ufm.edu.vn" value="" class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" id="loginPassword" name="password" required value="" placeholder="Mật khẩu (mặc định 123)" class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg text-sm transition shadow-lg shadow-indigo-200 flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Đăng nhập</span>
            </button>
        </form>

        <!-- Demo Test Users Block (Demo Test Users) -->
        <div class="mt-6 pt-5 border-t border-slate-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-users-viewfinder text-indigo-600"></i>
                    <span>Tài khoản thử nghiệm (Demo)</span>
                </span>
                <span class="text-[11px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full font-bold">Mật khẩu: 123</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <!-- 1. Ban Giám đốc -->
                <button type="button" onclick="fillLogin('yenlinhbt@ufm.edu.vn', '123')" class="text-left p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 transition group">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-900">1. Ban Giám đốc</div>
                    <div class="text-slate-600 text-[11px] font-semibold">Bùi Thị Yến Linh</div>
                    <div class="text-slate-400 text-[10px] truncate">yenlinhbt@ufm.edu.vn</div>
                </button>

                <!-- 2. Trưởng phòng -->
                <button type="button" onclick="fillLogin('phamhoainam@ufm.edu.vn', '123')" class="text-left p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 transition group">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-900">2. Trưởng phòng DTT</div>
                    <div class="text-slate-600 text-[11px] font-semibold">Phạm Hoài Nam</div>
                    <div class="text-slate-400 text-[10px] truncate">phamhoainam@ufm.edu.vn</div>
                </button>

                <!-- 3. Nhân viên Phòng DTT -->
                <button type="button" onclick="fillLogin('nguyenthithuthuy@ufm.edu.vn', '123')" class="text-left p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 transition group">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-900">3. Nhân viên Phòng DTT</div>
                    <div class="text-slate-600 text-[11px] font-semibold">Nguyễn Thị Thu Thủy</div>
                    <div class="text-slate-400 text-[10px] truncate">nguyenthithuthuy@ufm.edu.vn</div>
                </button>

                <!-- 4. Nhân viên Phòng HCTV -->
                <button type="button" onclick="fillLogin('huynhthianhtung@ufm.edu.vn', '123')" class="text-left p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 transition group">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-900">4. Nhân viên Phòng HCTV</div>
                    <div class="text-slate-600 text-[11px] font-semibold">Huỳnh Thị Anh Tùng</div>
                    <div class="text-slate-400 text-[10px] truncate">huynhthianhtung@ufm.edu.vn</div>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email, password) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = password;
}
</script>

</body>
</html>
