<?php
session_start();
define('PAGE_TITLE', 'Trao Đổi');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Handle POST request - Send new discussion message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_message') {
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) {
            $_SESSION['flash_error'] = "Vui lòng nhập nội dung ý kiến trao đổi!";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO discussions (user_id, content, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$currentUser['id'], $content]);
                logActivity($pdo, $currentUser['id'], 'SEND_DISCUSSION', 'Đã gửi ý kiến trao đổi trong hệ thống');
                $_SESSION['flash_success'] = "Gửi ý kiến trao đổi thành công!";
            } catch (\PDOException $e) {
                $_SESSION['flash_error'] = "Có lỗi xảy ra khi lưu ý kiến: " . $e->getMessage();
            }
        }
        header("Location: discussion.php#latest");
        exit;
    }
}

// Flash alerts
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Query all discussion messages ordered by created_at ASC (chronological chat stream)
$sqlMessages = "
    SELECT d.*, u.fullname, u.email, u.role, dep.name AS department_name, dep.code AS department_code
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dep ON u.department_id = dep.id
    ORDER BY d.created_at ASC, d.id ASC
";
$stmtMsg = $pdo->prepare($sqlMessages);
$stmtMsg->execute();
$messages = $stmtMsg->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full max-w-6xl mx-auto space-y-6">

    <!-- Header Banner Title -->
    <div class="bg-gradient-to-r from-teal-900 via-indigo-900 to-slate-900 rounded-2xl p-6 text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4 border border-teal-700/50">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-500/20 text-teal-200 border border-teal-400/30 text-xs font-semibold mb-2">
                <i class="fa-solid fa-comments text-teal-300"></i>
                <span>Phân Hệ Thảo Luận & Trao Đổi Cán Bộ</span>
            </div>
            <h2 class="text-xl md:text-2xl font-black tracking-tight text-teal-200">
                KHU VỰC TRAO ĐỔI & THẢO LUẬN THÀNH VIÊN
            </h2>
            <p class="text-xs text-teal-100/80 mt-1 leading-relaxed">
                Kênh thông tin trao đổi, thảo luận công việc và đóng góp ý kiến giữa Ban Giám đốc, Trưởng phòng và cán bộ nhân viên Phân hiệu Quảng Ngãi.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <div class="bg-teal-950/80 border border-teal-700/60 rounded-xl px-4 py-2.5 text-center">
                <span class="text-xs text-teal-300 font-medium block">Tổng số lượt trao đổi</span>
                <span class="text-xl font-extrabold text-white"><?= count($messages) ?></span>
            </div>
        </div>
    </div>

    <!-- Flash Notifications -->
    <?php if ($flashSuccess): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm text-emerald-800 text-sm font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-sm text-rose-800 text-sm font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 text-lg"></i>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Main Container: Chat Display Box & Input Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">

        <!-- Card Header -->
        <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-800 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-message text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                        Khung Hiển Thị Ý Kiến Trao Đổi Nội Bộ
                    </h3>
                    <p class="text-[11px] text-slate-500">
                        Danh sách các ý kiến thảo luận của toàn thể thành viên theo thứ tự thời gian.
                    </p>
                </div>
            </div>
            <button onclick="scrollToBottom()" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition flex items-center gap-1.5" title="Cuộn xuống ý kiến mới nhất">
                <i class="fa-solid fa-arrow-down"></i>
                <span class="hidden sm:inline">Xuống cuối</span>
            </button>
        </div>

        <!-- 1. KHUNG HIỂN THỊ CHÍNH (MAIN CHAT BOX) -->
        <div id="chatMainBox" class="h-[480px] overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50/50 border-b border-slate-200">
            <?php if (empty($messages)): ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-400 py-12">
                    <i class="fa-solid fa-comments text-5xl mb-3 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-600">Chưa có ý kiến trao đổi nào trong hệ thống.</p>
                    <p class="text-xs text-slate-400 mt-1">Hãy gõ nội dung vào khung bên dưới để là người đầu tiên gửi ý kiến!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $idx => $msg):
                    $isSelf = ((int)$msg['user_id'] === (int)$currentUser['id']);

                    // Role label mapping
                    $roleName = $msg['role'];
                    if ($roleName === 'BGD') $roleName = 'Ban Giám đốc';
                    elseif ($roleName === 'TRUONG_PHONG') $roleName = 'Trưởng phòng';
                    elseif ($roleName === 'NHAN_VIEN') $roleName = 'Nhân viên';
                    elseif ($roleName === 'HO_TRO') $roleName = 'Hỗ trợ';
                    elseif ($roleName === 'SUPER_ADMIN') $roleName = 'Super Admin';

                    $timeFormatted = date('H:i d/m/Y', strtotime($msg['created_at']));
                ?>
                    <!-- Standard Chat Line Structure: [Tên thành viên]: [Nội dung ý kiến] -->
                    <div class="flex flex-col space-y-1.5 p-3.5 rounded-2xl border transition <?= $isSelf ? 'bg-teal-50/70 border-teal-200/90 ml-4 sm:ml-12 shadow-xs' : 'bg-white border-slate-200/90 mr-4 sm:mr-12 shadow-xs' ?>">

                        <!-- Header Info: Role, Dept, Time -->
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs pb-1.5 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $isSelf ? 'bg-teal-700 text-white' : 'bg-slate-700 text-white' ?>">
                                    <?= htmlspecialchars($roleName) ?>
                                </span>
                                <?php if (!empty($msg['department_name'])): ?>
                                    <span class="text-[11px] font-semibold text-slate-600">
                                        <i class="fa-solid fa-building text-slate-400 mr-1"></i><?= htmlspecialchars($msg['department_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                <i class="fa-regular fa-clock text-slate-400"></i>
                                <?= $timeFormatted ?>
                            </span>
                        </div>

                        <!-- Chat Message Output standard structure: [Tên thành viên]: [Nội dung ý kiến] -->
                        <div class="text-xs leading-relaxed text-slate-800 pt-1">
                            <span class="font-extrabold <?= $isSelf ? 'text-teal-900' : 'text-slate-900' ?>">
                                [<?= htmlspecialchars($msg['fullname']) ?>]:
                            </span>
                            <span class="font-medium text-slate-800 whitespace-pre-wrap ml-1"><?= nl2br(htmlspecialchars($msg['content'])) ?></span>
                        </div>

                    </div>
                <?php endforeach; ?>
                <div id="latest"></div>
            <?php endif; ?>
        </div>

        <!-- 2. KHUNG NHẬP LIỆU (INPUT CHAT BOX) -->
        <div class="p-4 sm:p-5 bg-white">
            <form method="POST" action="discussion.php" id="chatForm" class="space-y-3">
                <input type="hidden" name="action" value="send_message">

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="chatContentInput" class="block text-xs font-bold text-slate-700">
                            Gửi Ý Kiến Trao Đổi <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">
                            Định danh: <strong class="text-teal-800"><?= htmlspecialchars($currentUser['fullname']) ?></strong> (<?= htmlspecialchars($currentUser['department_name'] ?? '') ?>)
                        </span>
                    </div>

                    <div class="relative">
                        <textarea
                            id="chatContentInput"
                            name="content"
                            rows="3"
                            required
                            placeholder="Gõ nội dung ý kiến trao đổi tại đây... (Nhấn Enter để gửi, Shift+Enter để xuống dòng)"
                            class="w-full p-3.5 pr-28 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none font-medium leading-relaxed resize-y"
                        ></textarea>

                        <div class="absolute right-3 bottom-3 flex items-center gap-2">
                            <button
                                type="submit"
                                class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md transition flex items-center gap-1.5 border border-teal-500"
                            >
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Gửi</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1">
                    <span class="italic"><i class="fa-solid fa-circle-info mr-1 text-teal-600"></i>Ý kiến sau khi gửi sẽ hiển thị ngay lập tức lên Khung lớn phía trên.</span>
                </div>
            </form>
        </div>

    </div>

</div>

<script>
function scrollToBottom() {
    const chatBox = document.getElementById('chatMainBox');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();

    const textarea = document.getElementById('chatContentInput');
    const form = document.getElementById('chatForm');

    if (textarea && form) {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (textarea.value.trim().length > 0) {
                    form.submit();
                } else {
                    sysAlert('Vui lòng nhập nội dung ý kiến trao đổi!');
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
