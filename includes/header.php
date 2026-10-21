<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);
$allUsersList = getUsers($pdo);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('PAGE_TITLE') ? PAGE_TITLE . ' - ' . APP_NAME : APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5">
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

            <!-- Current User info & Quick Switcher -->
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur-md px-3.5 py-2 rounded-xl border border-white/20">
                <div class="text-right text-xs">
                    <div class="font-bold text-white text-sm flex items-center justify-end gap-1.5">
                        <i class="fa-solid fa-user-circle text-blue-300"></i>
                        <span><?= htmlspecialchars($currentUser['fullname']) ?></span>
                    </div>
                    <div class="text-blue-200 font-medium">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-semibold bg-white/20 text-white">
                            <?= htmlspecialchars($currentUser['role']) ?>
                        </span>
                        <span> • <?= htmlspecialchars($currentUser['department_name']) ?></span>
                    </div>
                </div>

                <!-- Quick Switch Dropdown for Demo/Testing -->
                <div class="relative group">
                    <button class="bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1 shadow transition cursor-pointer" title="Chuyển tài khoản demo">
                        <i class="fa-solid fa-repeat"></i>
                        <span class="hidden sm:inline">Đổi tài khoản</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <!-- Dropdown menu -->
                    <div class="absolute right-0 top-full mt-2 w-64 bg-white text-slate-800 rounded-xl shadow-2xl border border-slate-200 py-2 hidden group-hover:block z-50">
                        <div class="px-3 py-1.5 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Chuyển nhanh tài khoản demo
                        </div>
                        <?php foreach ($allUsersList as $u): ?>
                            <a href="?switch_user_id=<?= $u['id'] ?>" class="block px-3 py-2 text-xs hover:bg-indigo-50 transition <?= $u['id'] == $currentUser['id'] ? 'bg-indigo-100/70 font-bold border-l-4 border-indigo-600' : '' ?>">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($u['fullname']) ?></div>
                                <div class="text-[11px] text-slate-500">
                                    <span class="font-semibold text-indigo-600"><?= $u['role'] ?></span> - <?= htmlspecialchars($u['department_name']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="logout.php" class="bg-rose-600 hover:bg-rose-500 text-white px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1" title="Đăng xuất">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>

        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="bg-blue-950/80 border-t border-indigo-800/60 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center space-x-1 sm:space-x-4 py-2 overflow-x-auto">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            ?>
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
        </div>
    </nav>
</header>

<main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
