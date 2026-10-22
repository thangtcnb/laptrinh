<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$roleLabel = $currentUser['role'];
if ($roleLabel === 'TRUONG_PHONG') {
    $roleLabel = 'Trưởng phòng';
} elseif ($roleLabel === 'NHAN_VIEN') {
    $roleLabel = 'Nhân viên';
} elseif ($roleLabel === 'BGD') {
    $roleLabel = 'Ban Giám đốc';
} elseif ($roleLabel === 'SUPER_ADMIN') {
    $roleLabel = 'Super Admin';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('PAGE_TITLE') ? PAGE_TITLE . ' - ' . APP_NAME : APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr CSS & JS (dd/mm/yyyy datepicker integration) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        ufm: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            600: '#1d4ed8',
                            700: '#1e40af',
                            800: '#1e3a8a',
                            900: '#172554',
                        }
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof flatpickr !== 'undefined') {
                flatpickr.localize(flatpickr.l10ns.vn);
                flatpickr('input[type="date"]', {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "d/m/Y",
                    allowInput: true
                });
            }
        });
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 flex flex-col min-h-screen">

<!-- Top Header / Banner -->
<header class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white shadow-lg no-print border-b border-indigo-700">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-3.5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

            <!-- Branding -->
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center p-1 shadow-md">
                    <i class="fa-solid fa-graduation-cap text-3xl text-blue-900"></i>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-blue-200">
                        TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING
                    </div>
                    <div class="text-lg md:text-xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</span>
                    </div>
                    <div class="text-xs text-indigo-200 flex items-center gap-1 font-medium">
                        <i class="fa-solid fa-[#0088cc] fa-list-check"></i>
                        <span>Hệ thống Quản lý & Phân công Công việc Nội bộ</span>
                    </div>
                </div>
            </div>

            <!-- Current User Info Frame -->
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur-md px-3.5 py-2 rounded-xl border border-white/20">
                <div class="flex items-center gap-2 text-xs">
                    <!-- [Badge Role] [Full Name] • [Department Name] -->
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500 text-white shadow-sm">
                        <?= htmlspecialchars($roleLabel) ?>
                    </span>
                    <span class="font-bold text-white text-sm">
                        <?= htmlspecialchars($currentUser['fullname']) ?>
                    </span>
                    <span class="text-blue-200 font-medium">
                        • <?= htmlspecialchars($currentUser['department_name']) ?>
                    </span>
                </div>

                <div class="flex items-center gap-1.5 border-l border-white/20 pl-2">
                    <!-- Change Password Button (NGAY TRƯỚC nút Đăng xuất) -->
                    <a href="profile.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow" title="Đổi mật khẩu">
                        <i class="fa-solid fa-key"></i>
                        <span>Đổi mật khẩu</span>
                    </a>

                    <!-- Logout Button -->
                    <a href="logout.php" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow" title="Đăng xuất">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Đăng xuất</span>
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="bg-blue-950/80 border-t border-indigo-800/60 px-4 sm:px-6 lg:px-8">
        <div class="w-full flex items-center space-x-1 sm:space-x-4 py-2 overflow-x-auto">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            ?>
            <a href="index.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'index.php' ? 'bg-indigo-600 text-white shadow' : 'text-slate-200 hover:bg-white/10' ?>">
                <i class="fa-solid fa-house"></i>
                <span>Trang Chủ</span>
            </a>

            <a href="index.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'index.php' ? 'bg-indigo-600 text-white shadow' : 'text-slate-200 hover:bg-white/10' ?>">
                <i class="fa-solid fa-table-list"></i>
                <span>Bảng Danh Sách Công Việc</span>
            </a>

            <a href="batch_register.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'batch_register.php' ? 'bg-indigo-600 text-white shadow' : 'text-slate-200 hover:bg-white/10' ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                <span>Đăng Ký Công Việc Theo Tuần</span>
            </a>

            <a href="export.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'export.php' ? 'bg-indigo-600 text-white shadow' : 'text-slate-200 hover:bg-white/10' ?>">
                <i class="fa-solid fa-file-excel"></i>
                <span>Xuất Báo Cáo & In Ấn</span>
            </a>

            <?php if (isset($currentUser['role']) && $currentUser['role'] === 'SUPER_ADMIN'): ?>
                <a href="admin.php" class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'admin.php' ? 'bg-amber-500 text-white shadow' : 'bg-amber-500/80 hover:bg-amber-500 text-white' ?>">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Quản Trị Hệ Thống (Super Admin)</span>
                </a>
            <?php endif; ?>

        </div>
    </nav>
</header>

<main class="flex-grow w-full px-4 sm:px-6 lg:px-8 py-6">
