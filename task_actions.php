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

// 2. APPROVE / LEADER DIRECTIVE COMMENTS (RBAC)
if ($action === 'save_leader_comment' || $action === 'approve') {
    $taskId = (int)($_REQUEST['task_id'] ?? 0);
    $newStatus = $_REQUEST['status'] ?? 'DANG_THUC_HIEN';
    $leaderComment = trim($_REQUEST['leader_comments'] ?? '');

    // Check task info
    $stmt = $pdo->prepare("SELECT department_id FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        $_SESSION['flash_error'] = "Không tìm thấy công việc!";
        header("Location: index.php");
        exit;
    }

    $canApprove = false;
    if ($currentUser['role'] === 'BGD') {
        $canApprove = true;
    } elseif ($currentUser['role'] === 'TRUONG_PHONG' && $task['department_id'] == $currentUser['department_id']) {
        $canApprove = true;
    }

    if ($canApprove) {
        $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = ?, leader_comments = ?, leader_comment_user_id = ? WHERE id = ?");
        $stmtUpdate->execute([$newStatus, $leaderComment ?: null, $currentUser['id'], $taskId]);
        $_SESSION['flash_success'] = "Đã lưu ý kiến chỉ đạo và cập nhật phê duyệt!";
    } else {
        $_SESSION['flash_error'] = "Bạn chỉ có quyền cho ý kiến/duyệt các công việc thuộc phòng ban của mình!";
    }

    header("Location: index.php");
    exit;
}

// 3. DELETE TASK (RBAC)
if ($action === 'delete') {
    $taskId = (int)($_GET['task_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT created_by, status, department_id, title FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        $_SESSION['flash_error'] = "Không tìm thấy công việc!";
        header("Location: index.php");
        exit;
    }

    $canDelete = false;
    if ($currentUser['role'] === 'SUPER_ADMIN' || $task['created_by'] == $currentUser['id']) {
        // Cá nhân được quyền xóa công việc do chính mình đã tạo/đăng ký hoặc Super Admin
        $canDelete = true;
    }

    if ($canDelete) {
        $stmtDelete = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmtDelete->execute([$taskId]);
        logActivity($pdo, $currentUser['id'], 'DELETE_TASK', "Đã xóa công việc ID #$taskId: {$task['title']}");
        $_SESSION['flash_success'] = "Đã xóa công việc thành công!";
    } else {
        $_SESSION['flash_error'] = "Bạn chỉ có quyền xóa công việc do chính mình đã tạo!";
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
    $status = $_POST['status'] ?? (in_array($currentUser['role'], ['NHAN_VIEN', 'HO_TRO']) ? 'CHO_DUYET' : 'DANG_THUC_HIEN');
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
        // Edit existing task permission check
        $stmtExisting = $pdo->prepare("SELECT created_by, status FROM tasks WHERE id = ?");
        $stmtExisting->execute([$taskId]);
        $existingTask = $stmtExisting->fetch();

        if (!$existingTask) {
            $_SESSION['flash_error'] = "Không tìm thấy công việc!";
            header("Location: index.php");
            exit;
        }

        // Cá nhân được quyền sửa công việc do chính mình tạo/đăng ký
        if ($currentUser['role'] !== 'SUPER_ADMIN' && $existingTask['created_by'] != $currentUser['id']) {
            $_SESSION['flash_error'] = "Bạn chỉ có quyền chỉnh sửa công việc do chính mình đã tạo!";
            header("Location: index.php");
            exit;
        }

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

        // Workflow Notification: When Trưởng phòng assigns a new task to a Nhân viên
        if ($currentUser['role'] === 'TRUONG_PHONG' && $mainAssignee !== $currentUser['id']) {
            $stmtSetPending = $pdo->prepare("UPDATE tasks SET status = 'CHO_XAC_NHAN' WHERE id = ?");
            $stmtSetPending->execute([$taskId]);
        }
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

// 5. RESPOND TO ALL ASSIGNED TASKS (BATCH ACCEPTANCE) BY NHAN VIEN
if ($action === 'respond_all_assigned_tasks') {
    $taskIds = $_POST['task_ids'] ?? [];
    $generalComment = trim($_POST['general_comment'] ?? '');
    $comments = $_POST['comments'] ?? [];

    if (!empty($taskIds) && is_array($taskIds)) {
        $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = 'DANG_THUC_HIEN', comment_feedback = ? WHERE id = ?");

        $stmtCheck = $pdo->prepare("
            SELECT t.id, t.title FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE t.id = ? AND ta.user_id = ? AND t.status = 'CHO_XAC_NHAN'
        ");

        $acceptedCount = 0;
        foreach ($taskIds as $taskId) {
            $taskId = (int)$taskId;
            $stmtCheck->execute([$taskId, $currentUser['id']]);
            $task = $stmtCheck->fetch();
            if ($task) {
                $indivComment = trim($comments[$taskId] ?? '');
                $feedback = !empty($indivComment) ? $indivComment : $generalComment;
                $stmtUpdate->execute([$feedback ?: null, $taskId]);
                logActivity($pdo, $currentUser['id'], 'ACCEPT_TASK', "Đã đồng ý tiếp nhận công việc ID #$taskId: {$task['title']}" . ($feedback ? ". Ý kiến khác: $feedback" : ""));
                $acceptedCount++;
            }
        }

        if ($acceptedCount > 0) {
            $_SESSION['flash_success'] = "Đã đồng ý tiếp nhận hàng loạt $acceptedCount công việc thành công!";
        }
    }

    header("Location: index.php");
    exit;
}

// 6. RESPOND TO ASSIGNED TASK (ACCEPT SINGLE TASK) BY NHAN VIEN
if ($action === 'respond_assigned_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $commentFeedback = trim($_POST['comment_feedback'] ?? '');

    // Check task existence & assignment
    $stmtCheck = $pdo->prepare("
        SELECT t.* FROM tasks t
        JOIN task_assignees ta ON t.id = ta.task_id
        WHERE t.id = ? AND ta.user_id = ?
    ");
    $stmtCheck->execute([$taskId, $currentUser['id']]);
    $task = $stmtCheck->fetch();

    if (!$task) {
        $_SESSION['flash_error'] = "Không tìm thấy công việc được giao!";
        header("Location: index.php");
        exit;
    }

    $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = 'DANG_THUC_HIEN', comment_feedback = ? WHERE id = ?");
    $stmtUpdate->execute([$commentFeedback ?: null, $taskId]);
    logActivity($pdo, $currentUser['id'], 'ACCEPT_TASK', "Đã đồng ý tiếp nhận công việc ID #$taskId: {$task['title']}" . ($commentFeedback ? ". Ý kiến khác: $commentFeedback" : ""));
    $_SESSION['flash_success'] = "Bạn đã tiếp nhận công việc thành công!";

    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
