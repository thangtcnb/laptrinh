<?php
session_start();
define('PAGE_TITLE', 'Trao Đổi');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// 1. AJAX Endpoint: Fetch Message List
if (isset($_GET['action']) && $_GET['action'] === 'fetch_messages') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $sqlMessages = "
            SELECT d.id, d.task_id, d.user_id, d.message, d.created_at,
                   u.fullname, u.email, u.role, dep.name AS department_name, dep.code AS department_code
            FROM discussions d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN departments dep ON u.department_id = dep.id
            WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY d.created_at ASC, d.id ASC
        ";
        $stmtMsg = $pdo->prepare($sqlMessages);
        $stmtMsg->execute();
        $rawMessages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        $messages = [];
        foreach ($rawMessages as $msg) {
            $roleName = $msg['role'];
            if ($roleName === 'BGD') $roleName = 'Ban Giám đốc';
            elseif ($roleName === 'TRUONG_PHONG') $roleName = 'Trưởng phòng';
            elseif ($roleName === 'NHAN_VIEN') $roleName = 'Nhân viên';
            elseif ($roleName === 'HO_TRO') $roleName = 'Hỗ trợ';
            elseif ($roleName === 'SUPER_ADMIN') $roleName = 'Super Admin';

            $messages[] = [
                'id' => (int)$msg['id'],
                'task_id' => $msg['task_id'] ? (int)$msg['task_id'] : null,
                'user_id' => (int)$msg['user_id'],
                'fullname' => $msg['fullname'],
                'role_label' => $roleName,
                'department_name' => $msg['department_name'] ?? '',
                'message' => $msg['message'],
                'created_at_formatted' => date('H:i d/m/Y', strtotime($msg['created_at'])),
                'is_self' => ((int)$msg['user_id'] === (int)$currentUser['id'])
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $messages,
            'current_user_id' => (int)$currentUser['id'],
            'is_super_admin' => ($currentUser['role'] === 'SUPER_ADMIN')
        ]);
    } catch (\PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi truy vấn CSDL: ' . $e->getMessage()
        ]);
    }
    exit;
}

// 2. Handle POST Request: Send Message & Delete Message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if ($action === 'delete_message') {
        header('Content-Type: application/json; charset=utf-8');
        if ($currentUser['role'] !== 'SUPER_ADMIN') {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác xóa ý kiến trao đổi!']);
            exit;
        }

        $messageId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mã tin nhắn không hợp lệ!']);
            exit;
        }

        try {
            $stmtDel = $pdo->prepare("DELETE FROM discussions WHERE id = ?");
            $stmtDel->execute([$messageId]);

            logActivity($pdo, $currentUser['id'], 'DELETE_DISCUSSION', 'Đã xóa ý kiến trao đổi ID #' . $messageId);

            echo json_encode(['success' => true, 'message' => 'Đã xóa ý kiến trao đổi thành công!']);
            exit;
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa ý kiến trao đổi: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? $_POST['content'] ?? '');
        $taskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;

        if (empty($message)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung ý kiến trao đổi!']);
                exit;
            }
            $_SESSION['flash_error'] = "Vui lòng nhập nội dung ý kiến trao đổi!";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO discussions (task_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$taskId, $currentUser['id'], $message]);
                logActivity($pdo, $currentUser['id'], 'SEND_DISCUSSION', 'Đã gửi ý kiến trao đổi trong hệ thống');

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'Gửi ý kiến thành công!']);
                    exit;
                }
                $_SESSION['flash_success'] = "Gửi ý kiến trao đổi thành công!";
            } catch (\PDOException $e) {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi lưu ý kiến: ' . $e->getMessage()]);
                    exit;
                }
                $_SESSION['flash_error'] = "Có lỗi xảy ra khi lưu ý kiến: " . $e->getMessage();
            }
        }

        header("Location: discussion.php");
        exit;
    }
}

// Flash alerts for direct POST page reloads
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Query initial messages for SSR render fallback
$sqlInitial = "
    SELECT d.id, d.task_id, d.user_id, d.message, d.created_at,
           u.fullname, u.email, u.role, dep.name AS department_name, dep.code AS department_code
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dep ON u.department_id = dep.id
    WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY d.created_at ASC, d.id ASC
";
$stmtInit = $pdo->prepare($sqlInitial);
$stmtInit->execute();
$initialMessages = $stmtInit->fetchAll(PDO::FETCH_ASSOC);

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
            <div class="bg-teal-950/80 border border-teal-700/60 rounded-xl px-4 py-2.5 text-center shadow-inner">
                <span class="text-xs text-teal-300 font-medium block">Tổng số lượt trao đổi</span>
                <span id="totalMessageCount" class="text-xl font-extrabold text-white"><?= count($initialMessages) ?></span>
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
                        Danh sách ý kiến thảo luận của toàn thể thành viên theo thời gian (Cập nhật tự động thời gian thực).
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Tự động cập nhật</span>
                </span>
                <button onclick="scrollToBottom(true)" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition flex items-center gap-1.5" title="Cuộn xuống ý kiến mới nhất">
                    <i class="fa-solid fa-arrow-down"></i>
                    <span class="hidden sm:inline">Xuống cuối</span>
                </button>
            </div>
        </div>

        <!-- 1. KHUNG HIỂN THỊ CHÍNH (MAIN CHAT BOX) -->
        <div id="chatMainBox" class="h-[480px] overflow-y-auto p-4 sm:p-6 space-y-3.5 bg-slate-50/60 border-b border-slate-200">
            <!-- Messages rendered dynamically via JS / PHP fallback -->
            <?php if (empty($initialMessages)): ?>
                <div id="emptyChatNotice" class="h-full flex flex-col items-center justify-center text-slate-400 py-12">
                    <i class="fa-solid fa-comments text-5xl mb-3 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-600">Chưa có ý kiến trao đổi nào trong hệ thống.</p>
                    <p class="text-xs text-slate-400 mt-1">Hãy gõ nội dung vào khung bên dưới để gửi ý kiến đầu tiên!</p>
                </div>
            <?php else: ?>
                <?php foreach ($initialMessages as $msg):
                    $isSelf = ((int)$msg['user_id'] === (int)$currentUser['id']);
                    $roleName = $msg['role'];
                    if ($roleName === 'BGD') $roleName = 'Ban Giám đốc';
                    elseif ($roleName === 'TRUONG_PHONG') $roleName = 'Trưởng phòng';
                    elseif ($roleName === 'NHAN_VIEN') $roleName = 'Nhân viên';
                    elseif ($roleName === 'HO_TRO') $roleName = 'Hỗ trợ';
                    elseif ($roleName === 'SUPER_ADMIN') $roleName = 'Super Admin';
                    $timeFormatted = date('H:i d/m/Y', strtotime($msg['created_at']));
                ?>
                    <div class="chat-item flex flex-col space-y-1.5 p-3.5 rounded-2xl border transition <?= $isSelf ? 'bg-teal-50/70 border-teal-200/90 ml-4 sm:ml-12 shadow-xs' : 'bg-white border-slate-200/90 mr-4 sm:mr-12 shadow-xs' ?>" data-id="<?= (int)$msg['id'] ?>">
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
                            <?php if ($currentUser['role'] === 'SUPER_ADMIN'): ?>
                                <button type="button" onclick="deleteMessage(<?= (int)$msg['id'] ?>)" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1 rounded-md transition text-xs flex items-center gap-1 font-semibold" title="Xóa tin nhắn này">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <span class="hidden sm:inline text-[11px]">Xóa</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Định dạng chuẩn: [Tên thành viên] ([Thời gian]): [Nội dung ý kiến] -->
                        <div class="text-xs leading-relaxed text-slate-800 pt-0.5">
                            <span class="font-extrabold <?= $isSelf ? 'text-teal-900' : 'text-slate-900' ?>">[<?= htmlspecialchars($msg['fullname']) ?>]</span>
                            <span class="text-slate-500 font-normal ml-0.5">(<?= $timeFormatted ?>):</span>
                            <span class="font-medium text-slate-800 whitespace-pre-wrap ml-1.5"><?= nl2br(htmlspecialchars($msg['message'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 2. KHUNG NHẬP LIỆU (INPUT CHAT BOX) -->
        <div class="p-4 sm:p-5 bg-white">
            <form id="chatForm" onsubmit="handleSendMessage(event)" class="space-y-3">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="ajax" value="1">

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="chatContentInput" class="block text-xs font-bold text-slate-700">
                            Gửi Ý Kiến Trao Đổi <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] text-slate-500 font-medium">
                            Định danh: <strong class="text-teal-800"><?= htmlspecialchars($currentUser['fullname']) ?></strong> (<?= htmlspecialchars($currentUser['department_name'] ?? '') ?>)
                        </span>
                    </div>

                    <div class="relative">
                        <textarea
                            id="chatContentInput"
                            name="message"
                            rows="3"
                            required
                            placeholder="Gõ nội dung ý kiến trao đổi tại đây... (Nhấn Enter để gửi, Shift+Enter để xuống dòng)"
                            class="w-full p-3.5 pr-28 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none font-medium leading-relaxed resize-y"
                        ></textarea>

                        <div class="absolute right-3 bottom-3 flex items-center gap-2">
                            <button
                                type="submit"
                                id="btnSendMessage"
                                class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md transition flex items-center gap-1.5 border border-teal-500 disabled:opacity-50"
                            >
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Gửi</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1">
                    <span class="italic"><i class="fa-solid fa-circle-info mr-1 text-teal-600"></i>Ý kiến sau khi gửi sẽ hiển thị ngay lập tức lên Khung lớn phía trên và đồng bộ đến tất cả thành viên.</span>
                </div>
            </form>
        </div>

    </div>

</div>

<script>
let lastMessageCount = 0;
let isFirstLoad = true;

function scrollToBottom(force = false) {
    const chatBox = document.getElementById('chatMainBox');
    if (!chatBox) return;

    // Scroll if forced or if user is near bottom (within 120px)
    const isNearBottom = (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 120;
    if (force || isNearBottom || isFirstLoad) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

let isSuperAdmin = <?= json_encode($currentUser['role'] === 'SUPER_ADMIN') ?>;

function renderMessages(messages, currentUserId, isSuperAdminFlag) {
    if (typeof isSuperAdminFlag !== 'undefined') {
        isSuperAdmin = isSuperAdminFlag;
    }

    const chatBox = document.getElementById('chatMainBox');
    const totalCountEl = document.getElementById('totalMessageCount');

    if (totalCountEl) {
        totalCountEl.textContent = messages.length;
    }

    if (!messages || messages.length === 0) {
        chatBox.innerHTML = `
            <div id="emptyChatNotice" class="h-full flex flex-col items-center justify-center text-slate-400 py-12">
                <i class="fa-solid fa-comments text-5xl mb-3 text-slate-300"></i>
                <p class="text-sm font-semibold text-slate-600">Chưa có ý kiến trao đổi nào trong vòng 7 ngày gần đây.</p>
                <p class="text-xs text-slate-400 mt-1">Hãy gõ nội dung vào khung bên dưới để gửi ý kiến đầu tiên!</p>
            </div>`;
        return;
    }

    let html = '';
    messages.forEach(msg => {
        const isSelf = (msg.is_self || msg.user_id === currentUserId);
        const bgClass = isSelf ? 'bg-teal-50/70 border-teal-200/90 ml-4 sm:ml-12 shadow-xs' : 'bg-white border-slate-200/90 mr-4 sm:mr-12 shadow-xs';
        const roleBadgeBg = isSelf ? 'bg-teal-700 text-white' : 'bg-slate-700 text-white';
        const nameColor = isSelf ? 'text-teal-900' : 'text-slate-900';

        const departmentTag = msg.department_name ? `
            <span class="text-[11px] font-semibold text-slate-600">
                <i class="fa-solid fa-building text-slate-400 mr-1"></i>${escapeHtml(msg.department_name)}
            </span>` : '';

        const deleteBtnHtml = isSuperAdmin ? `
            <button type="button" onclick="deleteMessage(${msg.id})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1 rounded-md transition text-xs flex items-center gap-1 font-semibold" title="Xóa tin nhắn này">
                <i class="fa-solid fa-trash-can"></i>
                <span class="hidden sm:inline text-[11px]">Xóa</span>
            </button>` : '';

        // Standard line structure: [Tên thành viên] ([Thời gian]): [Nội dung ý kiến]
        html += `
            <div class="chat-item flex flex-col space-y-1.5 p-3.5 rounded-2xl border transition ${bgClass}" data-id="${msg.id}">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs pb-1.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold ${roleBadgeBg}">
                            ${escapeHtml(msg.role_label)}
                        </span>
                        ${departmentTag}
                    </div>
                    ${deleteBtnHtml}
                </div>
                <div class="text-xs leading-relaxed text-slate-800 pt-0.5">
                    <span class="font-extrabold ${nameColor}">[${escapeHtml(msg.fullname)}]</span>
                    <span class="text-slate-500 font-normal ml-0.5">(${escapeHtml(msg.created_at_formatted)}):</span>
                    <span class="font-medium text-slate-800 whitespace-pre-wrap ml-1.5">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</span>
                </div>
            </div>
        `;
    });

    chatBox.innerHTML = html;

    if (messages.length !== lastMessageCount) {
        scrollToBottom(isFirstLoad);
        lastMessageCount = messages.length;
    }

    if (isFirstLoad) {
        isFirstLoad = false;
    }
}

function fetchMessages() {
    fetch('discussion.php?action=fetch_messages', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(res => {
        if (res.success && res.data) {
            renderMessages(res.data, res.current_user_id, res.is_super_admin);
        }
    })
    .catch(err => {
        console.error("Lỗi tải tin nhắn trao đổi:", err);
    });
}

function deleteMessage(messageId) {
    if (!messageId) return;

    const performDelete = () => {
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('id', messageId);
        formData.append('ajax', '1');

        fetch('discussion.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                fetchMessages();
            } else {
                if (typeof sysAlert === 'function') {
                    sysAlert(res.message || 'Có lỗi xảy ra khi xóa ý kiến.');
                } else {
                    alert(res.message || 'Có lỗi xảy ra khi xóa ý kiến.');
                }
            }
        })
        .catch(err => {
            console.error("Lỗi xóa tin nhắn:", err);
            if (typeof sysAlert === 'function') {
                sysAlert('Lỗi kết nối máy chủ khi xóa ý kiến!');
            } else {
                alert('Lỗi kết nối máy chủ khi xóa ý kiến!');
            }
        });
    };

    if (typeof sysConfirm === 'function') {
        sysConfirm('Bạn có chắc chắn muốn xóa ý kiến trao đổi này không?', performDelete);
    } else if (confirm('Bạn có chắc chắn muốn xóa ý kiến trao đổi này không?')) {
        performDelete();
    }
}

function handleSendMessage(e) {
    e.preventDefault();
    const textarea = document.getElementById('chatContentInput');
    const submitBtn = document.getElementById('btnSendMessage');
    const messageText = textarea ? textarea.value.trim() : '';

    if (!messageText) {
        if (typeof sysAlert === 'function') {
            sysAlert('Vui lòng nhập nội dung ý kiến trao đổi!');
        } else {
            alert('Vui lòng nhập nội dung ý kiến trao đổi!');
        }
        return;
    }

    if (submitBtn) submitBtn.disabled = true;

    const formData = new FormData(document.getElementById('chatForm'));

    fetch('discussion.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (submitBtn) submitBtn.disabled = false;
        if (res.success) {
            textarea.value = '';
            fetchMessages();
            setTimeout(() => scrollToBottom(true), 100);
        } else {
            if (typeof sysAlert === 'function') {
                sysAlert(res.message || 'Có lỗi xảy ra khi gửi ý kiến.');
            } else {
                alert(res.message || 'Có lỗi xảy ra khi gửi ý kiến.');
            }
        }
    })
    .catch(err => {
        if (submitBtn) submitBtn.disabled = false;
        console.error("Lỗi gửi tin nhắn:", err);
        if (typeof sysAlert === 'function') {
            sysAlert('Lỗi kết nối máy chủ khi gửi ý kiến!');
        } else {
            alert('Lỗi kết nối máy chủ khi gửi ý kiến!');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('chatContentInput');

    if (textarea) {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSendMessage(e);
            }
        });
    }

    // Initial load and scroll down
    fetchMessages();
    setTimeout(() => scrollToBottom(true), 200);

    // Auto-refresh / Real-time Polling every 3 seconds
    setInterval(fetchMessages, 3000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
