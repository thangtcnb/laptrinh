<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$action = $_REQUEST['action'] ?? '';

// 1. UPDATE PROGRESS & PROOF LINK
if ($action === 'update_progress') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $progress = min(100, max(0, (int)($_POST['progress'] ?? 0)));
    $proofLink = trim($_POST['proof_link'] ?? '');

    // Auto update status to HOAN_THANH if progress reaches 100%, else DANG_THUC_HIEN if > 0 and was CHO_DUYET/CHO_BGD_DUYET
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $currentStatus = $stmt->fetchColumn();

    $newStatus = $currentStatus;
    if ($progress === 100) {
        $newStatus = 'HOAN_THANH';
    } elseif ($progress > 0 && ($currentStatus === 'CHO_DUYET' || $currentStatus === 'CHO_BGD_DUYET')) {
        $newStatus = 'DANG_THUC_HIEN';
    }

    $stmtUpdate = $pdo->prepare("UPDATE tasks SET progress = ?, proof_link = ?, status = ? WHERE id = ?");
    $stmtUpdate->execute([$progress, $proofLink ?: null, $newStatus, $taskId]);

    $_SESSION['flash_success'] = "Đã cập nhật tiến độ (" . $progress . "%) và minh chứng thành công!";
    header("Location: index.php");
    exit;
}

// 2. APPROVE TASK (RBAC)
if ($action === 'approve') {
    $taskId = (int)($_GET['task_id'] ?? 0);
    $newStatus = $_GET['status'] ?? 'DANG_THUC_HIEN';

    if (!in_array($newStatus, ['DANG_THUC_HIEN', 'HOAN_THANH', 'CHO_DUYET'])) {
        $newStatus = 'DANG_THUC_HIEN';
    }

    $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$newStatus, $taskId]);

    $_SESSION['flash_success'] = "Đã phê duyệt công việc thành công!";
    header("Location: index.php");
    exit;
}

// 3. DELETE TASK (RBAC)
if ($action === 'delete') {
    $taskId = (int)($_GET['task_id'] ?? 0);

    // Permission check
    if ($currentUser['role'] === 'BGD' || $currentUser['role'] === 'TRUONG_PHONG') {
        $stmtDelete = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmtDelete->execute([$taskId]);
        $_SESSION['flash_success'] = "Đã xóa công việc thành công!";
    } else {
        $_SESSION['flash_error'] = "Bạn không có quyền xóa công việc này!";
    }
    header("Location: index.php");
    exit;
}

// 4. CREATE / EDIT SINGLE TASK
if ($action === 'save_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $departmentId = (int)($_POST['department_id'] ?? $currentUser['department_id']);
    $priority = $_POST['priority'] ?? 'TRUNG_BINH';
    $status = $_POST['status'] ?? ($currentUser['role'] === 'NHAN_VIEN' ? 'CHO_DUYET' : 'DANG_THUC_HIEN');
    $startDate = $_POST['start_date'] ?: null;
    $dueDate = $_POST['due_date'] ?: null;
    $mainAssignee = (int)($_POST['main_assignee'] ?? 0);
    $coopAssignees = $_POST['coop_assignees'] ?? [];

    if (empty($title)) {
        $_SESSION['flash_error'] = "Tiêu đề công việc không được để trống.";
        header("Location: index.php");
        exit;
    }

    if ($taskId > 0) {
        // Edit existing task
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET title = ?, description = ?, department_id = ?, priority = ?, status = ?, start_date = ?, due_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $departmentId, $priority, $status, $startDate, $dueDate, $taskId]);

        // Reassign assignees
        $pdo->prepare("DELETE FROM task_assignees WHERE task_id = ?")->execute([$taskId]);
    } else {
        // Insert new task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, department_id, status, priority, progress, start_date, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $departmentId, $status, $priority, $startDate, $dueDate, $currentUser['id']]);
        $taskId = $pdo->lastInsertId();
    }

    // Insert MAIN assignee
    if ($mainAssignee > 0) {
        $stmtMain = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role_type) VALUES (?, ?, 'MAIN') ON DUPLICATE KEY UPDATE role_type = 'MAIN'");
        $stmtMain->execute([$taskId, $mainAssignee]);
    }

    // Insert COOP assignees
    if (is_array($coopAssignees)) {
        $stmtCoop = $pdo->prepare("INSERT IGNORE INTO task_assignees (task_id, user_id, role_type) VALUES (?, ?, 'COOP')");
        foreach ($coopAssignees as $coopId) {
            $coopId = (int)$coopId;
            if ($coopId > 0 && $coopId !== $mainAssignee) {
                $stmtCoop->execute([$taskId, $coopId]);
            }
        }
    }

    $_SESSION['flash_success'] = "Đã lưu công việc thành công!";
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
