<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$action = $_REQUEST['action'] ?? '';

// Helper function to handle attachment files upload
function handleFileUploads($pdo, $taskId, $userId) {
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
        return;
    }

    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'zip', 'rar'];
    $maxSizeBytes = 25 * 1024 * 1024; // 25 MB

    $uploadedCount = 0;
    $errors = [];

    $files = $_FILES['attachments'];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $originalName = basename($files['name'][$i]);
        $fileSize = (int)$files['size'][$i];
        $tmpName = $files['tmp_name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Lỗi khi tải file '$originalName'.";
            continue;
        }

        if (!in_array($ext, $allowedExts)) {
            $errors[] = "File '$originalName' không đúng định dạng cho phép (pdf, doc, docx, xls, xlsx, png, jpg, zip, rar).";
            continue;
        }

        if ($fileSize > $maxSizeBytes) {
            $errors[] = "File '$originalName' vượt quá dung lượng tối đa 25 MB.";
            continue;
        }

        // Target directory: uploads/documents/YYYY/MM/
        $yearMonthDir = 'uploads/documents/' . date('Y/m');
        if (!is_dir(__DIR__ . '/' . $yearMonthDir)) {
            mkdir(__DIR__ . '/' . $yearMonthDir, 0777, true);
        }

        // Target filename with timestamp/random hash prefix
        $hashPrefix = date('Ymd_His') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 6);
        $safeFileName = $hashPrefix . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
        $relativePath = $yearMonthDir . '/' . $safeFileName;
        $fullPath = __DIR__ . '/' . $relativePath;

        if (move_uploaded_file($tmpName, $fullPath)) {
            $stmt = $pdo->prepare("
                INSERT INTO task_attachments (task_id, file_name, file_path, file_size, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $originalName, $relativePath, $fileSize, $userId]);
            $uploadedCount++;
        } else {
            $errors[] = "Không thể lưu file '$originalName' trên server.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode('<br>', $errors);
    }

    if ($uploadedCount > 0) {
        logTaskTimeline($pdo, $taskId, $userId, 'UPLOAD_ATTACHMENT', "Đã tải lên $uploadedCount văn bản/tài liệu chỉ đạo");
    }
}

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

    logTaskTimeline($pdo, $taskId, $currentUser['id'], 'UPDATE_PROGRESS', "Cập nhật tiến độ $progress%" . ($proofLink ? " kèm link minh chứng" : ""));

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
    $stmt = $pdo->prepare("SELECT department_id, status FROM tasks WHERE id = ?");
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
        if ($newStatus === 'CHO_BGD_DUYET') {
            $stmtUnaccepted = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
            $stmtUnaccepted->execute([$taskId]);
            $unacceptedCount = (int)$stmtUnaccepted->fetchColumn();

            if ($unacceptedCount > 0) {
                $actualStatus = 'CHO_TIEP_NHAN';
                $targetStatus = 'CHO_BGD_DUYET';
            } else {
                $actualStatus = 'CHO_BGD_DUYET';
                $targetStatus = 'CHO_BGD_DUYET';
            }
        } else {
            $actualStatus = $newStatus;
            $targetStatus = null;
        }

        $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = ?, target_status = ?, leader_comments = ?, leader_comment_user_id = ? WHERE id = ?");
        $stmtUpdate->execute([$actualStatus, $targetStatus, $leaderComment ?: null, $currentUser['id'], $taskId]);

        $statusName = $actualStatus === 'DANG_THUC_HIEN' ? 'Đã duyệt' : ($actualStatus === 'CHO_TIEP_NHAN' ? 'Chờ tiếp nhận (Trình BGD)' : ($actualStatus === 'CHO_BGD_DUYET' ? 'Trình BGD duyệt' : 'Yêu cầu chỉnh sửa'));
        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'LEADER_APPROVAL', "Cập nhật trạng thái: $statusName" . ($leaderComment ? ". Ý kiến: $leaderComment" : ""));

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
        // Delete physical attachment files
        $stmtFiles = $pdo->prepare("SELECT file_path FROM task_attachments WHERE task_id = ?");
        $stmtFiles->execute([$taskId]);
        $attachments = $stmtFiles->fetchAll();
        foreach ($attachments as $att) {
            $fullPath = __DIR__ . '/' . $att['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

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

// 4. DELETE SINGLE ATTACHMENT FILE (RBAC)
if ($action === 'delete_attachment') {
    $attachmentId = (int)($_REQUEST['attachment_id'] ?? 0);

    $stmtAtt = $pdo->prepare("
        SELECT ta.*, t.created_by AS task_creator
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.id = ?
    ");
    $stmtAtt->execute([$attachmentId]);
    $attachment = $stmtAtt->fetch();

    if (!$attachment) {
        $_SESSION['flash_error'] = "Không tìm thấy file văn bản đính kèm!";
        header("Location: index.php");
        exit;
    }

    $canDeleteAtt = ($currentUser['role'] === 'SUPER_ADMIN' || $attachment['task_creator'] == $currentUser['id'] || $attachment['uploaded_by'] == $currentUser['id']);

    if ($canDeleteAtt) {
        $fullPath = __DIR__ . '/' . $attachment['file_path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $stmtDel = $pdo->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmtDel->execute([$attachmentId]);

        logTaskTimeline($pdo, $attachment['task_id'], $currentUser['id'], 'DELETE_ATTACHMENT', "Đã xóa văn bản đính kèm: {$attachment['file_name']}");
        $_SESSION['flash_success'] = "Đã xóa file đính kèm thành công!";
    } else {
        $_SESSION['flash_error'] = "Bạn không có quyền xóa file đính kèm này!";
    }

    header("Location: index.php");
    exit;
}

// 5. CREATE / EDIT SINGLE TASK
if ($action === 'save_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $departmentId = in_array($currentUser['role'], ['TRUONG_PHONG', 'NHAN_VIEN', 'HO_TRO'])
        ? $currentUser['department_id']
        : (int)($_POST['department_id'] ?? $currentUser['department_id']);
    $priority = $_POST['priority'] ?? 'NORMAL';
    if (!in_array($priority, ['NORMAL', 'URGENT'])) {
        $priority = 'NORMAL';
    }

    // Default status logic by role:
    $targetStatus = null;
    if (in_array($currentUser['role'], ['NHAN_VIEN', 'HO_TRO'])) {
        $status = 'CHO_DUYET';
        $targetStatus = null;
    } elseif ($currentUser['role'] === 'TRUONG_PHONG') {
        $status = 'CHO_TIEP_NHAN';
        $targetStatus = 'DANG_THUC_HIEN';
    } elseif ($currentUser['role'] === 'BGD') {
        $status = 'CHO_TIEP_NHAN';
        $targetStatus = 'DANG_THUC_HIEN';
    } else {
        // SUPER_ADMIN
        $status = $_POST['status'] ?? 'CHO_DUYET';
        $targetStatus = ($status === 'CHO_TIEP_NHAN') ? 'DANG_THUC_HIEN' : null;
    }

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
        $stmtExisting = $pdo->prepare("SELECT created_by, status, target_status FROM tasks WHERE id = ?");
        $stmtExisting->execute([$taskId]);
        $existingTask = $stmtExisting->fetch();

        if (!$existingTask) {
            $_SESSION['flash_error'] = "Không tìm thấy công việc!";
            header("Location: index.php");
            exit;
        }

        // Cá nhân được quyền sửa công việc do chính mình tạo/đăng ký hoặc SUPER_ADMIN
        if ($currentUser['role'] !== 'SUPER_ADMIN' && $existingTask['created_by'] != $currentUser['id']) {
            $_SESSION['flash_error'] = "Bạn chỉ có quyền chỉnh sửa công việc do chính mình đã tạo!";
            header("Location: index.php");
            exit;
        }

        // On edit, keep existing status & target_status if standard user, unless super admin
        if ($currentUser['role'] !== 'SUPER_ADMIN') {
            $status = $existingTask['status'];
            $targetStatus = $existingTask['target_status'];
        }

        // Edit existing task
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET title = ?, description = ?, department_id = ?, priority = ?, status = ?, target_status = ?, start_date = ?, due_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $departmentId, $priority, $status, $targetStatus, $startDate, $dueDate, $taskId]);

        // Reassign assignees
        $pdo->prepare("DELETE FROM task_assignees WHERE task_id = ?")->execute([$taskId]);
        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'UPDATE_TASK', "Chỉnh sửa thông tin công việc");
    } else {
        // Insert new task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, department_id, status, target_status, priority, progress, start_date, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $departmentId, $status, $targetStatus, $priority, $startDate, $dueDate, $currentUser['id']]);
        $taskId = $pdo->lastInsertId();

        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'CREATE_TASK', "Đăng ký/Tạo mới công việc");
    }

    // Insert MAIN assignee
    if ($mainAssignee > 0) {
        $isAcceptedMain = ($mainAssignee === $currentUser['id'] && $status !== 'CHO_TIEP_NHAN' && $status !== 'CHO_XAC_NHAN') ? 1 : 0;
        $acceptedAtMain = $isAcceptedMain ? date('Y-m-d H:i:s') : null;

        $stmtMain = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role_type, is_accepted, accepted_at) VALUES (?, ?, 'MAIN', ?, ?) ON DUPLICATE KEY UPDATE role_type = 'MAIN'");
        $stmtMain->execute([$taskId, $mainAssignee, $isAcceptedMain, $acceptedAtMain]);
    }

    // Insert COOP assignees
    if (is_array($coopAssignees)) {
        $stmtCoop = $pdo->prepare("INSERT IGNORE INTO task_assignees (task_id, user_id, role_type, is_accepted, accepted_at) VALUES (?, ?, 'COOP', ?, ?)");
        foreach ($coopAssignees as $coopId) {
            $coopId = (int)$coopId;
            if ($coopId > 0 && $coopId !== $mainAssignee) {
                $isAcceptedCoop = ($coopId === $currentUser['id'] && $status !== 'CHO_TIEP_NHAN' && $status !== 'CHO_XAC_NHAN') ? 1 : 0;
                $acceptedAtCoop = $isAcceptedCoop ? date('Y-m-d H:i:s') : null;
                $stmtCoop->execute([$taskId, $coopId, $isAcceptedCoop, $acceptedAtCoop]);
            }
        }
    }

    // Gửi thông báo tự động cho Trưởng/Phó phòng khi BGD giao việc cho Nhân viên thuộc phòng ban
    if ($currentUser['role'] === 'BGD') {
        $allAssigneeIds = [];
        if ($mainAssignee > 0) {
            $allAssigneeIds[] = $mainAssignee;
        }
        if (is_array($coopAssignees)) {
            foreach ($coopAssignees as $cId) {
                $cId = (int)$cId;
                if ($cId > 0 && !in_array($cId, $allAssigneeIds)) {
                    $allAssigneeIds[] = $cId;
                }
            }
        }

        if (!empty($allAssigneeIds)) {
            $inClause = implode(',', array_fill(0, count($allAssigneeIds), '?'));
            $stmtAssignees = $pdo->prepare("SELECT id, fullname, role, department_id FROM users WHERE id IN ($inClause)");
            $stmtAssignees->execute($allAssigneeIds);
            $assignedUsers = $stmtAssignees->fetchAll();

            $stmtLeaders = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND role IN ('TRUONG_PHONG', 'PHO_PHONG')");
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, task_id, title, content, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");

            foreach ($assignedUsers as $empUser) {
                // Kiểm tra nếu là Nhân viên hoặc Hỗ trợ thuộc phòng ban
                if (in_array($empUser['role'], ['NHAN_VIEN', 'HO_TRO'])) {
                    $stmtLeaders->execute([$empUser['department_id']]);
                    $leaders = $stmtLeaders->fetchAll();

                    $notifTitle = 'Thông báo BGD giao việc cho nhân viên phòng';
                    $notifContent = "BGD vừa giao công việc '" . $title . "' cho nhân viên " . $empUser['fullname'] . " trong phòng của bạn.";

                    foreach ($leaders as $leader) {
                        $stmtNotif->execute([$leader['id'], $taskId, $notifTitle, $notifContent]);
                    }
                }
            }
        }
    }

    // Process File Uploads (Văn bản chỉ đạo đính kèm)
    handleFileUploads($pdo, $taskId, $currentUser['id']);

    if (empty($_SESSION['flash_error'])) {
        $_SESSION['flash_success'] = "Đã lưu công việc thành công!";
    }
    header("Location: index.php");
    exit;
}

// 6. RESPOND TO ALL ASSIGNED TASKS (BATCH ACCEPTANCE) BY ASSIGNED USERS
if ($action === 'respond_all_assigned_tasks') {
    $taskIds = $_POST['task_ids'] ?? [];
    $generalComment = trim($_POST['general_comment'] ?? '');
    $comments = $_POST['comments'] ?? [];

    if (!empty($taskIds) && is_array($taskIds)) {
        $stmtCheck = $pdo->prepare("
            SELECT t.* FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE t.id = ? AND ta.user_id = ?
        ");

        $stmtAccept = $pdo->prepare("UPDATE task_assignees SET is_accepted = 1, accepted_at = NOW() WHERE task_id = ? AND user_id = ?");
        $stmtFeedback = $pdo->prepare("UPDATE tasks SET comment_feedback = ? WHERE id = ?");
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
        $stmtUpdateStatus = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");

        $acceptedCount = 0;
        foreach ($taskIds as $taskId) {
            $taskId = (int)$taskId;
            $stmtCheck->execute([$taskId, $currentUser['id']]);
            $task = $stmtCheck->fetch();

            if ($task) {
                $indivComment = trim($comments[$taskId] ?? '');
                $feedback = !empty($indivComment) ? $indivComment : $generalComment;

                $stmtAccept->execute([$taskId, $currentUser['id']]);
                if (!empty($feedback)) {
                    $stmtFeedback->execute([$feedback, $taskId]);
                }

                $stmtCount->execute([$taskId]);
                $unacceptedCount = (int)$stmtCount->fetchColumn();

                if ($unacceptedCount === 0) {
                    $nextStatus = !empty($task['target_status']) ? $task['target_status'] : 'DANG_THUC_HIEN';
                    $stmtUpdateStatus->execute([$nextStatus, $taskId]);
                    logTaskTimeline($pdo, $taskId, $currentUser['id'], 'ACCEPT_TASK', "Tất cả người được giao việc đã đồng ý tiếp nhận. Chuyển sang: $nextStatus" . ($feedback ? ". Ý kiến: $feedback" : ""));
                } else {
                    logTaskTimeline($pdo, $taskId, $currentUser['id'], 'ACCEPT_TASK', "{$currentUser['fullname']} đã đồng ý tiếp nhận (Đang chờ thành viên khác)" . ($feedback ? ". Ý kiến: $feedback" : ""));
                }

                logActivity($pdo, $currentUser['id'], 'ACCEPT_TASK', "Đã đồng ý tiếp nhận công việc ID #$taskId: {$task['title']}");
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

// BATCH APPROVAL OF TASKS BY LEADERS (TRƯỞNG PHÒNG & BGD)
if ($action === 'batch_approve_tasks') {
    $taskIds = $_POST['task_ids'] ?? [];
    $generalComment = trim($_POST['leader_comments_general'] ?? '');

    if (!in_array($currentUser['role'], ['TRUONG_PHONG', 'BGD', 'SUPER_ADMIN'])) {
        $_SESSION['flash_error'] = "Bạn không có quyền thực hiện chức năng phê duyệt hàng loạt!";
        header("Location: index.php");
        exit;
    }

    if (empty($taskIds) || !is_array($taskIds)) {
        $_SESSION['flash_error'] = "Vui lòng chọn ít nhất một công việc để phê duyệt!";
        header("Location: index.php");
        exit;
    }

    $approvedCount = 0;
    $stmtCheck = $pdo->prepare("SELECT id, title, department_id, status, leader_comments FROM tasks WHERE id = ?");

    $stmtUpdateWithComment = $pdo->prepare("
        UPDATE tasks
        SET status = 'DANG_THUC_HIEN', leader_comments = ?, leader_comment_user_id = ?
        WHERE id = ?
    ");

    $stmtUpdateKeepComment = $pdo->prepare("
        UPDATE tasks
        SET status = 'DANG_THUC_HIEN', leader_comment_user_id = ?
        WHERE id = ?
    ");

    foreach ($taskIds as $taskId) {
        $taskId = (int)$taskId;
        $stmtCheck->execute([$taskId]);
        $task = $stmtCheck->fetch();

        if (!$task) {
            continue;
        }

        // RBAC validation:
        // - BGD / SUPER_ADMIN: can approve any pending task (CHO_BGD_DUYET / CHO_DUYET)
        // - TRUONG_PHONG: can approve pending tasks in their department (CHO_DUYET)
        $canApprove = false;
        if (in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
            if ($task['status'] === 'CHO_BGD_DUYET' || $task['status'] === 'CHO_DUYET') {
                $canApprove = true;
            }
        } elseif ($currentUser['role'] === 'TRUONG_PHONG') {
            if ($task['status'] === 'CHO_DUYET' && $task['department_id'] == $currentUser['department_id']) {
                $canApprove = true;
            }
        }

        if ($canApprove) {
            if (!empty($generalComment)) {
                $stmtUpdateWithComment->execute([$generalComment, $currentUser['id'], $taskId]);
            } else {
                $stmtUpdateKeepComment->execute([$currentUser['id'], $taskId]);
            }
            $approvedCount++;
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'BATCH_APPROVE', "Phê duyệt hàng loạt" . ($generalComment ? ". Ý kiến: $generalComment" : ""));
            logActivity($pdo, $currentUser['id'], 'BATCH_APPROVE_TASK', "Đã phê duyệt công việc ID #$taskId: {$task['title']}");
        }
    }

    if ($approvedCount > 0) {
        $_SESSION['flash_success'] = "Đã phê duyệt hàng loạt $approvedCount công việc thành công!";
    } else {
        $_SESSION['flash_error'] = "Không có công việc nào được phê duyệt (kiểm tra lại quyền hoặc trạng thái công việc).";
    }

    header("Location: index.php");
    exit;
}

// 7. RESPOND TO ASSIGNED TASK (ACCEPT SINGLE TASK) BY ASSIGNED USER
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

    // Mark current user as accepted
    $stmtAccept = $pdo->prepare("UPDATE task_assignees SET is_accepted = 1, accepted_at = NOW() WHERE task_id = ? AND user_id = ?");
    $stmtAccept->execute([$taskId, $currentUser['id']]);

    // Save feedback if provided
    if (!empty($commentFeedback)) {
        $pdo->prepare("UPDATE tasks SET comment_feedback = ? WHERE id = ?")->execute([$commentFeedback, $taskId]);
    }

    // Check remaining unaccepted assignees
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
    $stmtCount->execute([$taskId]);
    $unacceptedCount = (int)$stmtCount->fetchColumn();

    if ($unacceptedCount === 0) {
        // All assignees have accepted! Move to target_status or DANG_THUC_HIEN
        $nextStatus = !empty($task['target_status']) ? $task['target_status'] : 'DANG_THUC_HIEN';
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$nextStatus, $taskId]);

        $statusLabel = $nextStatus === 'CHO_BGD_DUYET' ? 'Chờ BGD duyệt' : 'Đang thực hiện';
        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'ACCEPT_TASK', "Tất cả người được giao việc đã đồng ý tiếp nhận. Công việc chuyển sang trạng thái: $statusLabel" . ($commentFeedback ? ". Ý kiến: $commentFeedback" : ""));
        logActivity($pdo, $currentUser['id'], 'ACCEPT_TASK', "Đã đồng ý tiếp nhận công việc ID #$taskId: {$task['title']}");
        $_SESSION['flash_success'] = "Tất cả người thực hiện đã tiếp nhận công việc. Công việc chuyển sang trạng thái $statusLabel!";
    } else {
        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'ACCEPT_TASK', "{$currentUser['fullname']} đã đồng ý tiếp nhận công việc (Đang chờ các thành viên khác xác nhận)" . ($commentFeedback ? ". Ý kiến: $commentFeedback" : ""));
        logActivity($pdo, $currentUser['id'], 'ACCEPT_TASK', "Đã đồng ý tiếp nhận công việc ID #$taskId: {$task['title']}");
        $_SESSION['flash_success'] = "Bạn đã tiếp nhận công việc thành công. Đang chờ các người phối hợp khác xác nhận!";
    }

    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
