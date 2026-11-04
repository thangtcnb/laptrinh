<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$roleLabel = $currentUser['role'];
if ($roleLabel === 'TRUONG_PHONG') {
    $roleLabel = 'Trưởng phòng';
} elseif ($roleLabel === 'PHO_PHONG') {
    $roleLabel = 'Phó phòng';
} elseif ($roleLabel === 'NHAN_VIEN') {
    $roleLabel = 'Nhân viên';
} elseif ($roleLabel === 'HO_TRO') {
    $roleLabel = 'Hỗ trợ';
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
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        // Global Helper Functions for SweetAlert2 Dialogs
        function sysAlert(message, title = "Hệ thống Quản lý Công việc", icon = "warning") {
            return Swal.fire({
                title: title,
                html: message,
                icon: icon,
                confirmButtonColor: '#1e3a8a',
                confirmButtonText: 'Đóng'
            });
        }

        function sysConfirm(message, onConfirm, title = "Hệ thống Quản lý Công việc", icon = "question") {
            Swal.fire({
                title: title,
                html: message,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#1e3a8a',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        }

        function sysConfirmLink(event, element, message, title = "Hệ thống Quản lý Công việc") {
            event.preventDefault();
            const url = element.getAttribute('href');
            Swal.fire({
                title: title,
                html: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        }

        function sysConfirmForm(event, formElement, message, title = "Hệ thống Quản lý Công việc") {
            event.preventDefault();
            Swal.fire({
                title: title,
                html: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    formElement.submit();
                }
            });
            return false;
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
                    <?php
                    // Retrieve recent notifications for current user
                    $headerNotifs = [];
                    $unreadNotifsCount = 0;
                    if (!empty($currentUser['id'])) {
                        try {
                            $stmtHNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, id DESC LIMIT 10");
                            $stmtHNotif->execute([$currentUser['id']]);
                            $headerNotifs = $stmtHNotif->fetchAll();
                            foreach ($headerNotifs as $hn) {
                                if (!$hn['is_read']) $unreadNotifsCount++;
                            }
                        } catch (\PDOException $e) {
                            $headerNotifs = [];
                        }
                    }
                    ?>
                    <!-- Notification Bell Dropdown -->
                    <div class="relative">
                        <button type="button" onclick="toggleNotifDropdown()" id="notifBellBtn" class="relative p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition flex items-center justify-center" title="Thông báo hệ thống">
                            <i class="fa-solid fa-bell text-sm"></i>
                            <?php if ($unreadNotifsCount > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] font-extrabold w-4 h-4 rounded-full flex items-center justify-center animate-pulse shadow-sm">
                                    <?= $unreadNotifsCount > 9 ? '9+' : $unreadNotifsCount ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="notifDropdownMenu" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 z-50 text-slate-800 overflow-hidden transform transition-all">
                            <div class="p-3 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                                <div class="flex items-center gap-1.5 font-bold text-xs">
                                    <i class="fa-solid fa-bell text-amber-400"></i>
                                    <span>Thông báo</span>
                                    <?php if ($unreadNotifsCount > 0): ?>
                                        <span class="bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold ml-1"><?= $unreadNotifsCount ?> mới</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($unreadNotifsCount > 0): ?>
                                    <a href="task_actions.php?action=mark_notifications_read" class="text-[11px] text-indigo-300 hover:text-white font-medium underline">
                                        Đã đọc tất cả
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                <?php if (count($headerNotifs) > 0): ?>
                                    <?php foreach ($headerNotifs as $hn): ?>
                                        <?php
                                        $isUnread = !$hn['is_read'];
                                        $targetLink = $hn['task_id'] ? "index.php?task_id={$hn['task_id']}" : "#";
                                        $isReject = (strpos($hn['title'], 'từ chối') !== false || strpos($hn['content'], 'từ chối') !== false);
                                        ?>
                                        <a href="<?= htmlspecialchars($targetLink) ?>" class="block p-3 hover:bg-slate-50 transition <?= $isUnread ? ($isReject ? 'bg-rose-50/70 border-l-2 border-rose-500' : 'bg-blue-50/60 border-l-2 border-indigo-500') : '' ?>">
                                            <div class="flex items-start gap-2.5">
                                                <div class="w-7 h-7 rounded-lg <?= $isReject ? 'bg-rose-100 text-rose-600' : 'bg-indigo-100 text-indigo-600' ?> flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">
                                                    <i class="fa-solid <?= $isReject ? 'fa-circle-xmark' : 'fa-clipboard-list' ?>"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-bold text-xs text-slate-900 flex items-center justify-between">
                                                        <span class="truncate <?= $isReject ? 'text-rose-900' : '' ?>"><?= htmlspecialchars($hn['title'] ?: 'Thông báo công việc') ?></span>
                                                        <span class="text-[10px] text-slate-400 font-normal shrink-0 ml-1"><?= date('H:i d/m', strtotime($hn['created_at'])) ?></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-600 mt-0.5 leading-snug line-clamp-2">
                                                        <?= htmlspecialchars($hn['content']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-6 text-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-inbox text-2xl mb-1 text-slate-300"></i>
                                        <p>Không có thông báo nào.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <script>
                    function toggleNotifDropdown() {
                        const m = document.getElementById('notifDropdownMenu');
                        if (m) m.classList.toggle('hidden');
                    }
                    document.addEventListener('click', function(e) {
                        const btn = document.getElementById('notifBellBtn');
                        const menu = document.getElementById('notifDropdownMenu');
                        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
                            menu.classList.add('hidden');
                        }
                    });
                    </script>

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
    <nav class="bg-slate-950/90 border-t border-indigo-800/60 px-4 sm:px-6 lg:px-8">
        <div class="w-full flex items-center space-x-1 sm:space-x-2 py-2 overflow-x-auto">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            ?>
            <!-- Tab 1: Trang chủ (Xanh Dương) -->
            <a href="index.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'index.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-900/50' : 'bg-blue-950/40 text-blue-200 hover:bg-blue-800/50 border border-blue-800/40' ?>">
                <i class="fa-solid fa-house"></i>
                <span>Trang Chủ</span>
            </a>

            <!-- Tab 2: Đăng ký công việc (Xanh Lá) -->
            <a href="batch_register.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'batch_register.php' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-900/50' : 'bg-emerald-950/40 text-emerald-200 hover:bg-emerald-800/50 border border-emerald-800/40' ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                <span>Đăng Ký Công Việc</span>
            </a>

            <!-- Tab 3: Lịch công tác (Tím) -->
            <a href="bgd_schedule.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'bgd_schedule.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-900/50' : 'bg-purple-950/40 text-purple-200 hover:bg-purple-800/50 border border-purple-800/40' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Lịch Công Tác</span>
            </a>

            <!-- Tab 4: Trao đổi (Xanh Lam) -->
            <a href="discussion.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'discussion.php' ? 'bg-teal-600 text-white shadow-md shadow-teal-900/50' : 'bg-teal-950/40 text-teal-200 hover:bg-teal-800/50 border border-teal-800/40' ?>">
                <i class="fa-solid fa-comments"></i>
                <span>Trao Đổi</span>
            </a>

            <!-- Tab 5: Xuất báo cáo (Cam) -->
            <a href="export.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'export.php' ? 'bg-amber-600 text-white shadow-md shadow-amber-900/50' : 'bg-amber-950/40 text-amber-200 hover:bg-amber-800/50 border border-amber-800/40' ?>">
                <i class="fa-solid fa-file-excel"></i>
                <span>Xuất Báo Cáo & In Ấn</span>
            </a>

            <!-- Tab 6: Nhật ký công việc (Trưởng phòng) -->
            <?php if (isset($currentUser['role']) && in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG', 'SUPER_ADMIN'])): ?>
                <a href="tp_report.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'tp_report.php' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-900/50' : 'bg-indigo-950/40 text-indigo-200 hover:bg-indigo-800/50 border border-indigo-800/40' ?>">
                    <i class="fa-solid fa-file-word"></i>
                    <span>Nhật Ký Công Việc</span>
                </a>
            <?php endif; ?>

            <!-- Tab 5: Super Admin -->
            <?php if (isset($currentUser['role']) && $currentUser['role'] === 'SUPER_ADMIN'): ?>
                <a href="admin.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-1.5 <?= $currentPage === 'admin.php' ? 'bg-rose-600 text-white shadow-md shadow-rose-900/50' : 'bg-rose-950/40 text-rose-200 hover:bg-rose-800/50 border border-rose-800/40' ?>">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Quản Trị Hệ Thống (Super Admin)</span>
                </a>
            <?php endif; ?>

        </div>
    </nav>
</header>

<main class="flex-grow w-full px-4 sm:px-6 lg:px-8 py-6">
