<?php
session_start();
define('PAGE_TITLE', 'Trao Đổi');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$isChangelogAdmin = ($currentUser['role'] === 'SUPER_ADMIN' || strtolower($currentUser['email'] ?? '') === 'thangtcnb@gmail.com');

// Đảm bảo bảng system_changelogs luôn tồn tại
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_changelogs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `version` VARCHAR(50) NULL COMMENT 'Phiên bản',
            `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề thay đổi',
            `description` TEXT NOT NULL COMMENT 'Chi tiết thay đổi',
            `category` VARCHAR(50) DEFAULT 'FEATURE' COMMENT 'FEATURE, ENHANCEMENT, BUGFIX, SECURITY, UI',
            `released_date` DATE NOT NULL COMMENT 'Ngày áp dụng',
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`released_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Dọn dẹp dữ liệu mẫu tự động trước đây để Super Admin toàn quyền tự nhập tay
    $pdo->exec("DELETE FROM `system_changelogs` WHERE `created_by` IS NULL AND `version` IN ('v2.5', 'v2.4', 'v2.3')");
} catch (\PDOException $e) {
    // ignore
}

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
            elseif ($roleName === 'PHO_PHONG') $roleName = 'Phó phòng';
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

    // Xử lý thêm / sửa nhật ký thay đổi hệ thống
    if ($action === 'save_changelog') {
        if (!$isChangelogAdmin) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
                exit;
            }
            $_SESSION['flash_error'] = "Bạn không có quyền thực hiện thao tác này!";
            header("Location: discussion.php");
            exit;
        }

        $id = (int)($_POST['changelog_id'] ?? 0);
        $version = trim($_POST['version'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'FEATURE');
        $releasedDate = trim($_POST['released_date'] ?? date('Y-m-d'));

        if (empty($title) || empty($description)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tiêu đề và nội dung thay đổi!']);
                exit;
            }
            $_SESSION['flash_error'] = "Vui lòng nhập đầy đủ tiêu đề và nội dung thay đổi!";
            header("Location: discussion.php");
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE system_changelogs SET version = ?, title = ?, description = ?, category = ?, released_date = ? WHERE id = ?");
            $stmt->execute([$version ?: null, $title, $description, $category, $releasedDate, $id]);
            logActivity($pdo, $currentUser['id'], 'UPDATE_CHANGELOG', "Cập nhật nhật ký thay đổi hệ thống ID #$id: $title");
            $msg = "Cập nhật nhật ký thay đổi hệ thống thành công!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO system_changelogs (version, title, description, category, released_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$version ?: null, $title, $description, $category, $releasedDate, $currentUser['id']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $currentUser['id'], 'CREATE_CHANGELOG', "Thêm nhật ký thay đổi hệ thống ID #$newId: $title");
            $msg = "Thêm mới nhật ký thay đổi hệ thống thành công!";
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }
        $_SESSION['flash_success'] = $msg;
        header("Location: discussion.php");
        exit;
    }

    // Xử lý xóa nhật ký thay đổi hệ thống
    if ($action === 'delete_changelog') {
        if (!$isChangelogAdmin) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa nhật ký thay đổi hệ thống!']);
                exit;
            }
            $_SESSION['flash_error'] = "Bạn không có quyền xóa nhật ký thay đổi hệ thống!";
            header("Location: discussion.php");
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM system_changelogs WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, $currentUser['id'], 'DELETE_CHANGELOG', "Đã xóa nhật ký thay đổi hệ thống ID #$id");
            $msg = "Đã xóa nhật ký thay đổi hệ thống thành công!";
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_success'] = $msg;
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

// Truy vấn danh sách Nhật ký thay đổi hệ thống cho tất cả người dùng
$changelogs = [];
try {
    $stmtLogs = $pdo->query("SELECT * FROM system_changelogs ORDER BY released_date DESC, id DESC");
    $changelogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $changelogs = [];
}

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
                    elseif ($roleName === 'PHO_PHONG') $roleName = 'Phó phòng';
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

    <!-- ==================== BẢNG: NHẬT KÍ THAY ĐỔI HỆ THỐNG ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Header bảng -->
        <div class="p-4 sm:p-5 border-b border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-teal-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-teal-500/20 border border-teal-400/40 text-teal-300 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-clock-rotate-left text-base"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm sm:text-base font-extrabold uppercase tracking-wide text-white">
                            NHẬT KÍ THAY ĐỔI HỆ THỐNG
                        </h3>
                        <span class="px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 text-[11px] font-bold border border-teal-500/30">
                            <?= count($changelogs) ?> bản ghi
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-300 mt-0.5">
                        Theo dõi toàn bộ lịch sử cải tiến, tính năng mới và các bản cập nhật của Hệ thống Quản lý Công việc.
                    </p>
                </div>
            </div>

            <?php if ($isChangelogAdmin): ?>
                <button type="button" onclick="openChangelogModal()" class="px-3.5 py-2 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 shrink-0 border border-teal-400/40">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Thêm Thay Đổi Mới</span>
                </button>
            <?php endif; ?>
        </div>

        <!-- Bảng danh sách nhật ký -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4 w-12 text-center">STT</th>
                        <th class="py-3 px-4 w-28 text-center">Phiên bản</th>
                        <th class="py-3 px-4 w-28 text-center">Ngày áp dụng</th>
                        <th class="py-3 px-4 w-32 text-center">Phân loại</th>
                        <th class="py-3 px-4 min-w-[320px]">Nội dung thay đổi & nâng cấp</th>
                        <?php if ($isChangelogAdmin): ?>
                            <th class="py-3 px-4 w-24 text-center">Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-800">
                    <?php if (empty($changelogs)): ?>
                        <tr>
                            <td colspan="<?= $isChangelogAdmin ? 6 : 5 ?>" class="py-8 text-center text-slate-400 italic">
                                <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                                Chưa có nhật ký thay đổi hệ thống nào được ghi nhận.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($changelogs as $idx => $log): ?>
                            <?php
                            $cat = $log['category'] ?? 'FEATURE';
                            if ($cat === 'FEATURE') {
                                $catBadge = '<span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">Tính năng mới</span>';
                            } elseif ($cat === 'ENHANCEMENT') {
                                $catBadge = '<span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-blue-100 text-blue-800 border border-blue-300">Cải tiến</span>';
                            } elseif ($cat === 'BUGFIX') {
                                $catBadge = '<span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300">Sửa lỗi</span>';
                            } elseif ($cat === 'SECURITY') {
                                $catBadge = '<span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-800 border border-rose-300">Bảo mật</span>';
                            } else {
                                $catBadge = '<span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-800 border border-slate-300">' . htmlspecialchars($cat) . '</span>';
                            }
                            ?>
                            <tr class="hover:bg-teal-50/20 transition">
                                <td class="py-3.5 px-4 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-block px-2.5 py-1 rounded-lg bg-slate-900 text-white font-mono font-bold text-[11px] shadow-xs">
                                        <?= htmlspecialchars($log['version'] ?: 'v' . ($idx + 1) . '.0') ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-semibold text-slate-600 whitespace-nowrap">
                                    <i class="fa-regular fa-calendar-days text-slate-400 mr-1"></i>
                                    <?= date('d/m/Y', strtotime($log['released_date'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?= $catBadge ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-extrabold text-slate-900 text-xs mb-1 flex items-center gap-1.5">
                                        <i class="fa-solid fa-angles-right text-teal-600 text-[10px]"></i>
                                        <span><?= htmlspecialchars($log['title']) ?></span>
                                    </div>
                                    <div class="text-[11px] text-slate-600 leading-relaxed whitespace-pre-line bg-slate-50 p-2.5 rounded-xl border border-slate-200/70 font-normal">
                                        <?= nl2br(htmlspecialchars($log['description'])) ?>
                                    </div>
                                </td>
                                <?php if ($isChangelogAdmin): ?>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" onclick='editChangelog(<?= htmlspecialchars(json_encode($log), ENT_QUOTES, "UTF-8") ?>)' class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg transition" title="Chỉnh sửa">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button type="button" onclick="deleteChangelog(<?= (int)$log['id'] ?>)" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg transition" title="Xóa">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ==================== MODAL: THÊM / SỬA NHẬT KÍ THAY ĐỔI (DÀNH CHO SUPER ADMIN) ==================== -->
<?php if ($isChangelogAdmin): ?>
<div id="changelogModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 id="changelogModalTitle" class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-teal-600"></i>
                <span>Thêm Nhật Ký Thay Đổi Mới</span>
            </h3>
            <button onclick="closeChangelogModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="discussion.php" class="space-y-3.5">
            <input type="hidden" name="action" value="save_changelog">
            <input type="hidden" name="changelog_id" id="form_changelog_id" value="0">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phiên bản</label>
                    <input type="text" name="version" id="form_changelog_version" placeholder="vd: v2.5, Update 04/11" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Ngày áp dụng <span class="text-rose-500">*</span></label>
                    <input type="date" name="released_date" id="form_changelog_date" required value="<?= date('Y-m-d') ?>" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 outline-none bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Phân loại thay đổi</label>
                <select name="category" id="form_changelog_category" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 outline-none bg-white font-medium">
                    <option value="FEATURE">Tính năng mới (Feature)</option>
                    <option value="ENHANCEMENT">Cải tiến tính năng (Enhancement)</option>
                    <option value="BUGFIX">Sửa lỗi (Bug fix)</option>
                    <option value="SECURITY">Bảo mật (Security)</option>
                    <option value="UI">Giao diện (UI/UX)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tiêu đề thay đổi <span class="text-rose-500">*</span></label>
                <input type="text" name="title" id="form_changelog_title" required placeholder="Tóm tắt ngắn gọn nội dung thay đổi..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 outline-none font-semibold">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nội dung chi tiết thay đổi <span class="text-rose-500">*</span></label>
                <textarea name="description" id="form_changelog_desc" rows="4" required placeholder="Ghi chi tiết các nội dung cập nhật, gạch đầu dòng từng mục..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-teal-500 outline-none leading-relaxed"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeChangelogModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow">Lưu Nhật Ký</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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

// ==================== QUẢN LÝ NHẬT KÍ THAY ĐỔI HỆ THỐNG ====================
function openChangelogModal() {
    const modal = document.getElementById('changelogModal');
    if (!modal) return;
    document.getElementById('form_changelog_id').value = '0';
    document.getElementById('form_changelog_version').value = '';
    document.getElementById('form_changelog_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('form_changelog_category').value = 'FEATURE';
    document.getElementById('form_changelog_title').value = '';
    document.getElementById('form_changelog_desc').value = '';
    document.getElementById('changelogModalTitle').querySelector('span').innerText = 'Thêm Nhật Ký Thay Đổi Mới';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeChangelogModal() {
    const modal = document.getElementById('changelogModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function editChangelog(log) {
    const modal = document.getElementById('changelogModal');
    if (!modal) return;
    document.getElementById('form_changelog_id').value = log.id;
    document.getElementById('form_changelog_version').value = log.version || '';
    document.getElementById('form_changelog_date').value = log.released_date || '<?= date('Y-m-d') ?>';
    document.getElementById('form_changelog_category').value = log.category || 'FEATURE';
    document.getElementById('form_changelog_title').value = log.title || '';
    document.getElementById('form_changelog_desc').value = log.description || '';
    document.getElementById('changelogModalTitle').querySelector('span').innerText = 'Chỉnh Sửa Nhật Ký Thay Đổi #' + log.id;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function deleteChangelog(id) {
    if (!id) return;
    const confirmDelete = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'discussion.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_changelog">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    };

    if (typeof sysConfirm === 'function') {
        sysConfirm('Bạn có chắc chắn muốn xóa bản ghi nhật ký thay đổi này không?', confirmDelete);
    } else if (confirm('Bạn có chắc chắn muốn xóa bản ghi nhật ký thay đổi này không?')) {
        confirmDelete();
    }
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
