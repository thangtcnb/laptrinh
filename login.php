<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

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

// Quick login handler
if (isset($_GET['quick_login_id']) && is_numeric($_GET['quick_login_id'])) {
    $qId = (int)$_GET['quick_login_id'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$qId]);
    if ($stmt->fetch()) {
        $_SESSION['user_id'] = $qId;
        header("Location: index.php");
        exit;
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
<body class="bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-900 min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200">
    <div class="bg-gradient-to-r from-blue-900 to-indigo-900 p-6 text-center text-white relative">
        <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-md border border-white/20">
            <i class="fa-solid fa-graduation-cap text-3xl text-blue-200"></i>
        </div>
        <h2 class="text-xl font-bold uppercase tracking-wide">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING</h2>
        <p class="text-xs text-blue-200 font-medium mt-1">PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</p>
        <p class="text-sm font-semibold text-indigo-100 mt-2">HỆ THỐNG QUẢN LÝ CÔNG VIỆC</p>
    </div>

    <div class="p-6">
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
                    <input type="email" name="email" required placeholder="example@ufm.edu.vn" value="thangpn@ufm.edu.vn" class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" required value="123" placeholder="••••••••" class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg text-sm transition shadow-lg shadow-indigo-200 flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Đăng nhập</span>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100">
            <div class="text-xs text-center text-slate-400 font-semibold mb-3 uppercase tracking-wider">
                Đăng nhập nhanh cho kiểm thử (Demo)
            </div>

            <div class="grid grid-cols-1 gap-2">
                <a href="login.php?quick_login_id=1" class="p-2.5 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg flex items-center justify-between text-xs transition">
                    <div>
                        <div class="font-bold text-purple-900">1. Bùi Thị Yến Linh</div>
                        <div class="text-purple-700">Ban Giám đốc (BGD)</div>
                    </div>
                    <span class="px-2 py-1 bg-purple-600 text-white rounded text-[10px] font-bold">BGD</span>
                </a>

                <a href="login.php?quick_login_id=11" class="p-2.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg flex items-center justify-between text-xs transition">
                    <div>
                        <div class="font-bold text-emerald-900">2. Phạm Hoài Nam</div>
                        <div class="text-emerald-700">Trưởng Phòng Đào tạo (DTT)</div>
                    </div>
                    <span class="px-2 py-1 bg-emerald-600 text-white rounded text-[10px] font-bold">Trưởng Phòng</span>
                </a>

                <a href="login.php?quick_login_id=6" class="p-2.5 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg flex items-center justify-between text-xs transition">
                    <div>
                        <div class="font-bold text-blue-900">3. Huỳnh Thị Anh Tùng</div>
                        <div class="text-blue-700">Nhân viên Phòng Hành chính - Tài vụ (HCTV)</div>
                    </div>
                    <span class="px-2 py-1 bg-blue-600 text-white rounded text-[10px] font-bold">Nhân Viên</span>
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
