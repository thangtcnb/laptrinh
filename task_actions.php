<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$action = $_REQUEST['action'] ?? '';
// 0. CHECK DATA VERSION (FOR AUTO-REFRESH)
if ($action === 'check_data_version') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'version' => getSystemDataVersion($pdo)
    ]);
    exit;
}

// 0.0 SWITCH VIEW AS (FOR SUPER ADMIN TO VIEW AS ANY USER)
if ($action === 'switch_view_as') {
    if (!empty($_SESSION['is_real_admin'])) {
        $targetUserId = (int)($_POST['target_user_id'] ?? $_GET['target_user_id'] ?? 0);
        if ($targetUserId > 0) {
            $_SESSION['view_as_user_id'] = $targetUserId;
            $stmtTarget = $pdo->prepare("SELECT fullname, role FROM users WHERE id = ?");
            $stmtTarget->execute([$targetUserId]);
            $tUser = $stmtTarget->fetch();
            $targetName = $tUser ? $tUser['fullname'] : "ID #$targetUserId";
            $_SESSION['flash_success'] = "Đang xem hệ thống với tư cách: <strong>{$targetName}</strong>";
        } else {
            unset($_SESSION['view_as_user_id']);
            $_SESSION['flash_success'] = "Đã trở về góc nhìn Quản trị viên cấp cao (Super Admin)";
        }
    }
    header("Location: " . (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
    exit;
}

if ($action === 'exit_view_as') {
    unset($_SESSION['view_as_user_id']);
    $_SESSION['flash_success'] = "Đã thoát chế độ giả lập, trở về tài khoản Super Admin.";
    header("Location: index.php");
    exit;
}

// 0.1 UPLOAD PROOF FILE DIRECTLY & SYNC GOOGLE DRIVE
if ($action === 'upload_proof_file') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$currentUser) {
        echo json_encode(['success' => false, 'error' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.']);
        exit;
    }

    if (empty($_FILES['proof_file'])) {
        echo json_encode(['success' => false, 'error' => 'Vui lòng chọn tệp tin Hồ sơ Minh chứng để tải lên.']);
        exit;
    }

    $taskId = (int)($_POST['task_id'] ?? 0);
    $res = uploadProofFileToDrive($_FILES['proof_file']);

    if (!empty($res['success']) && $taskId > 0) {
        logTaskTimeline($pdo, $taskId, $currentUser['id'], 'UPLOAD_PROOF', "Đã tải lên tệp Hồ sơ Minh chứng '{$res['fileName']}' và đồng bộ lên Google Drive");
        logActivity($pdo, $currentUser['id'], 'UPLOAD_PROOF', "Đã tải lên Hồ sơ Minh chứng cho công việc ID #$taskId: {$res['fileName']}");
    }

    echo json_encode($res);
    exit;
}

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
    $notes = trim($_POST['notes'] ?? '');

    // Chỉ tải lên khi chưa có link minh chứng (tránh upload lần 2 gây nhân bản file và làm đơ/delay nút Lưu)
    if (empty($proofLink) && !empty($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $uploadRes = uploadProofFileToDrive($_FILES['proof_file']);
        if (!empty($uploadRes['success'])) {
            $proofLink = $uploadRes['fileUrl'];
        }
    }
    if (!empty($proofLink) && !preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $proofLink)) {
        $proofLink = 'https://' . $proofLink;
    }

    // Auto update status to HOAN_THANH if progress reaches 100%, else DANG_THUC_HIEN if < 100% or was pending approval
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $currentStatus = $stmt->fetchColumn();

    $requestedStatus = trim($_POST['status'] ?? '');
    $newStatus = $currentStatus;

    if (!empty($requestedStatus) && in_array($requestedStatus, ['DANG_THUC_HIEN', 'HOAN_THANH'])) {
        $newStatus = $requestedStatus;
        if ($newStatus === 'HOAN_THANH' && $progress < 100) {
            $progress = 100;
        } elseif ($newStatus === 'DANG_THUC_HIEN' && $progress >= 100) {
            $progress = 90;
        }
    } else {
        if ($progress === 100) {
            $newStatus = 'HOAN_THANH';
        } elseif ($progress < 100 && $currentStatus === 'HOAN_THANH') {
            // Cho phép nhân viên sửa tiến độ từ 100% về dưới 100% và tự động chuyển lại trạng thái DANG_THUC_HIEN
            $newStatus = 'DANG_THUC_HIEN';
        } elseif ($progress > 0 && in_array($currentStatus, ['CHO_DUYET', 'CHO_BGD_DUYET', 'CHO_TIEP_NHAN', 'CHO_XAC_NHAN'])) {
            $newStatus = 'DANG_THUC_HIEN';
        }
    }

    try {
        $stmtUpdate = $pdo->prepare("UPDATE tasks SET progress = ?, proof_link = ?, notes = ?, status = ? WHERE id = ?");
        $stmtUpdate->execute([$progress, $proofLink ?: null, $notes ?: null, $newStatus, $taskId]);
    } catch (\PDOException $e) {
        $stmtUpdate = $pdo->prepare("UPDATE tasks SET progress = ?, proof_link = ?, status = ? WHERE id = ?");
        $stmtUpdate->execute([$progress, $proofLink ?: null, $newStatus, $taskId]);
    }

    $timelineDesc = "Cập nhật tiến độ $progress%";
    if ($currentStatus === 'HOAN_THANH' && $newStatus === 'DANG_THUC_HIEN') {
        $timelineDesc = "Điều chỉnh tiến độ về $progress% và chuyển trạng thái về Đang thực hiện";
    } elseif ($currentStatus !== 'HOAN_THANH' && $newStatus === 'HOAN_THANH') {
        $timelineDesc = "Hoàn thành công việc: Cập nhật tiến độ 100%";
    }
    if (!empty($notes)) {
        $timelineDesc .= " (Ghi chú: $notes)";
    }
    if (!empty($proofLink)) {
        $timelineDesc .= " kèm Hồ sơ Minh chứng";
    }

    logTaskTimeline($pdo, $taskId, $currentUser['id'], 'UPDATE_PROGRESS', $timelineDesc);
    $_SESSION['flash_success'] = "Đã cập nhật tiến độ (" . $progress . "%" . ($newStatus === 'HOAN_THANH' ? ' - Hoàn thành' : ' - Đang thực hiện') . "), ghi chú và Hồ sơ Minh chứng thành công!";

    
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
    } elseif (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG']) && $task['department_id'] == $currentUser['department_id']) {
        $canApprove = true;
    }

    if ($canApprove) {
        $stmtUnaccepted = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
        $stmtUnaccepted->execute([$taskId]);
        $unacceptedCount = (int)$stmtUnaccepted->fetchColumn();

        if ($newStatus === 'CHO_BGD_DUYET') {
            if ($unacceptedCount > 0) {
                $actualStatus = 'CHO_TIEP_NHAN';
                $targetStatus = 'CHO_BGD_DUYET';
            } else {
                $actualStatus = 'CHO_BGD_DUYET';
                $targetStatus = 'CHO_BGD_DUYET';
            }
        } elseif ($newStatus === 'DANG_THUC_HIEN') {
            if ($unacceptedCount > 0) {
                $actualStatus = 'CHO_TIEP_NHAN';
                $targetStatus = 'DANG_THUC_HIEN';
            } else {
                $actualStatus = 'DANG_THUC_HIEN';
                $targetStatus = null;
            }
        } else {
            $actualStatus = $newStatus;
            $targetStatus = null;
        }

        $stmtUpdate = $pdo->prepare("UPDATE tasks SET status = ?, target_status = ?, leader_comments = ?, leader_comment_user_id = ? WHERE id = ?");
        $stmtUpdate->execute([$actualStatus, $targetStatus, $leaderComment ?: null, $currentUser['id'], $taskId]);

        if ($actualStatus === 'YEU_CAU_CHINH_SUA') {
            // Gửi thông báo đến nhân viên đăng ký công việc khi bị từ chối
            $stmtTaskCreator = $pdo->prepare("SELECT title, created_by FROM tasks WHERE id = ?");
            $stmtTaskCreator->execute([$taskId]);
            $tcInfo = $stmtTaskCreator->fetch();
            if ($tcInfo && $tcInfo['created_by']) {
                $notifTitle = "Công việc bị từ chối phê duyệt";
                $reasonStr = $leaderComment ? "Ý kiến chỉ đạo: " . $leaderComment : "Trưởng phòng chưa chấp nhận công việc này.";
                $notifContent = "Công việc '{$tcInfo['title']}' đã bị Trưởng phòng từ chối phê duyệt. $reasonStr. Bạn có thể chỉnh sửa lại nội dung hoặc xóa công việc này.";
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, task_id, title, content, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                $stmtNotif->execute([$tcInfo['created_by'], $taskId, $notifTitle, $notifContent]);
            }
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'REJECT_TASK', "Trưởng phòng từ chối phê duyệt" . ($leaderComment ? ". Ý kiến: $leaderComment" : ""));
            $_SESSION['flash_success'] = "Đã từ chối phê duyệt công việc. Nhân viên đã nhận được thông báo để chỉnh sửa hoặc xóa công việc!";
        } else {
            $statusName = $actualStatus === 'DANG_THUC_HIEN' ? 'Đã duyệt' : ($actualStatus === 'CHO_TIEP_NHAN' ? 'Chờ tiếp nhận (Trình BGD)' : ($actualStatus === 'CHO_BGD_DUYET' ? 'Trình BGD duyệt' : 'Đã duyệt'));
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'LEADER_APPROVAL', "Cập nhật trạng thái: $statusName" . ($leaderComment ? ". Ý kiến: $leaderComment" : ""));
            $_SESSION['flash_success'] = "Đã lưu ý kiến chỉ đạo và cập nhật phê duyệt thành công!";
        }
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
    $departmentId = in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG', 'NHAN_VIEN', 'HO_TRO'])
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
    } elseif (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG'])) {
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

    $startDateRaw = trim($_POST['start_date'] ?? '');
    $startDate = null;
    if (!empty($startDateRaw)) {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $startDateRaw, $m)) {
            $startDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw)) {
            $startDate = $startDateRaw;
        } else {
            $ts = strtotime($startDateRaw);
            $startDate = $ts ? date('Y-m-d', $ts) : null;
        }
    }
    $dueDateRaw = trim($_POST['due_date'] ?? '');
    $dueDate = null;
    if (!empty($dueDateRaw)) {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dueDateRaw, $m)) {
            $dueDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDateRaw)) {
            $dueDate = $dueDateRaw;
        } else {
            $ts = strtotime($dueDateRaw);
            $dueDate = $ts ? date('Y-m-d', $ts) : null;
        }
    }
    $mainAssignee = (int)($_POST['main_assignee'] ?? 0);
    $coopAssignees = $_POST['coop_assignees'] ?? [];

    if (empty($title)) {
        $_SESSION['flash_error'] = "Tiêu đề công việc không được để trống.";
        header("Location: index.php");
        exit;
    }

    if ($taskId > 0) {
        // Edit existing task permission check
        $stmtExisting = $pdo->prepare("SELECT created_by, status, target_status, due_date FROM tasks WHERE id = ?");
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

        // Khóa hạn chót: Khi nhân viên đăng ký công việc và đã được trưởng phòng phê duyệt thì không được phép chỉnh sửa hạn chót
        $isEmployee = in_array($currentUser['role'], ['NHAN_VIEN', 'HO_TRO']);
        $isApproved = !in_array($existingTask['status'], ['CHO_DUYET', 'YEU_CAU_CHINH_SUA']);
        if ($isEmployee && $isApproved) {
            $dueDate = $existingTask['due_date'];
        }

        // On edit, keep existing status & target_status if standard user, unless super admin
        $wasRejected = false;
        if ($currentUser['role'] !== 'SUPER_ADMIN') {
            if ($existingTask['status'] === 'YEU_CAU_CHINH_SUA') {
                // Sau khi nhân viên chỉnh sửa lại công việc bị từ chối, gửi lại cho Trưởng phòng phê duyệt
                $status = 'CHO_DUYET';
                $targetStatus = null;
                $wasRejected = true;
            } elseif (in_array($existingTask['status'], ['DANG_THUC_HIEN', 'HOAN_THANH']) && !empty($_POST['status']) && in_array($_POST['status'], ['DANG_THUC_HIEN', 'HOAN_THANH'])) {
                // Cho phép người dùng chỉnh sửa trạng thái giữa Đang thực hiện và Hoàn thành
                $status = $_POST['status'];
                $targetStatus = null;
                if ($status === 'HOAN_THANH') {
                    $pdo->prepare("UPDATE tasks SET progress = 100 WHERE id = ?")->execute([$taskId]);
                } elseif ($status === 'DANG_THUC_HIEN' && $existingTask['status'] === 'HOAN_THANH') {
                    $pdo->prepare("UPDATE tasks SET progress = 90 WHERE id = ? AND progress >= 100")->execute([$taskId]);
                }
            } else {
                $status = $existingTask['status'];
                $targetStatus = $existingTask['target_status'];
            }
        }

        // Edit existing task
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET title = ?, description = ?, department_id = ?, priority = ?, status = ?, target_status = ?, start_date = ?, due_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $departmentId, $priority, $status, $targetStatus, $startDate, $dueDate, $taskId]);

        // Fetch existing assignees to preserve their acceptance status when editing
        $existingAssigneesMap = [];
        $stmtExistingAsg = $pdo->prepare("SELECT user_id, is_accepted, accepted_at FROM task_assignees WHERE task_id = ?");
        $stmtExistingAsg->execute([$taskId]);
        foreach ($stmtExistingAsg->fetchAll() as $row) {
            $existingAssigneesMap[(int)$row['user_id']] = [
                'is_accepted' => (int)$row['is_accepted'],
                'accepted_at' => $row['accepted_at']
            ];
        }

        // Reassign assignees
        $pdo->prepare("DELETE FROM task_assignees WHERE task_id = ?")->execute([$taskId]);

        if ($wasRejected) {
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'RESUBMIT_TASK', "Đã chỉnh sửa lại công việc sau khi bị từ chối và gửi lại cho Trưởng phòng phê duyệt");
            // Gửi thông báo đến Lãnh đạo đơn vị
            $stmtLeaders = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND role IN ('TRUONG_PHONG', 'PHO_PHONG')");
            $stmtLeaders->execute([$departmentId]);
            $leaders = $stmtLeaders->fetchAll();
            foreach ($leaders as $ld) {
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, task_id, title, content, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                $stmtNotif->execute([$ld['id'], $taskId, "Nhân viên đã gửi lại công việc sau chỉnh sửa", "Nhân viên {$currentUser['fullname']} vừa cập nhật lại công việc '{$title}' sau khi bị từ chối phê duyệt. Vui lòng xem xét phê duyệt lại."]);
            }
            $_SESSION['flash_success'] = "Đã chỉnh sửa công việc thành công và gửi lại cho Trưởng phòng phê duyệt!";
        } else {
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'UPDATE_TASK', "Chỉnh sửa thông tin công việc");
        }
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
        if (!empty($existingAssigneesMap[$mainAssignee]) && $existingAssigneesMap[$mainAssignee]['is_accepted'] == 1) {
            $isAcceptedMain = 1;
            $acceptedAtMain = $existingAssigneesMap[$mainAssignee]['accepted_at'] ?: date('Y-m-d H:i:s');
        } else {
            $isAcceptedMain = ($mainAssignee === $currentUser['id']) ? 1 : 0;
            $acceptedAtMain = $isAcceptedMain ? date('Y-m-d H:i:s') : null;
        }

        $stmtMain = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role_type, is_accepted, accepted_at) VALUES (?, ?, 'MAIN', ?, ?) ON DUPLICATE KEY UPDATE role_type = 'MAIN', is_accepted = VALUES(is_accepted), accepted_at = VALUES(accepted_at)");
        $stmtMain->execute([$taskId, $mainAssignee, $isAcceptedMain, $acceptedAtMain]);
    }

    // Insert COOP assignees
    if (is_array($coopAssignees)) {
        $stmtCoop = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role_type, is_accepted, accepted_at) VALUES (?, ?, 'COOP', ?, ?) ON DUPLICATE KEY UPDATE role_type = 'COOP', is_accepted = VALUES(is_accepted), accepted_at = VALUES(accepted_at)");
        foreach ($coopAssignees as $coopId) {
            $coopId = (int)$coopId;
            if ($coopId > 0 && $coopId !== $mainAssignee) {
                if (!empty($existingAssigneesMap[$coopId]) && $existingAssigneesMap[$coopId]['is_accepted'] == 1) {
                    $isAcceptedCoop = 1;
                    $acceptedAtCoop = $existingAssigneesMap[$coopId]['accepted_at'] ?: date('Y-m-d H:i:s');
                } else {
                    $isAcceptedCoop = ($coopId === $currentUser['id']) ? 1 : 0;
                    $acceptedAtCoop = $isAcceptedCoop ? date('Y-m-d H:i:s') : null;
                }
                $stmtCoop->execute([$taskId, $coopId, $isAcceptedCoop, $acceptedAtCoop]);
            }
        }
    }

    // Auto-update task status if all assignees have accepted or if newly unaccepted assignees exist
    if (!empty($taskId)) {
        $stmtCountUnaccepted = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
        $stmtCountUnaccepted->execute([$taskId]);
        $unacceptedRemaining = (int)$stmtCountUnaccepted->fetchColumn();

        if ($unacceptedRemaining === 0 && in_array($status, ['CHO_TIEP_NHAN', 'CHO_XAC_NHAN'])) {
            $nextStatus = !empty($targetStatus) ? $targetStatus : 'DANG_THUC_HIEN';
            $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$nextStatus, $taskId]);
        } elseif ($unacceptedRemaining > 0 && $status === 'DANG_THUC_HIEN') {
            $pdo->prepare("UPDATE tasks SET status = 'CHO_TIEP_NHAN', target_status = 'DANG_THUC_HIEN' WHERE id = ?")->execute([$taskId]);
        }
    }

    // Gửi thông báo tự động (Web Notification & Email) khi BGĐ hoặc Trưởng/Phó phòng giao việc trực tiếp
    if (in_array($currentUser['role'], ['BGD', 'TRUONG_PHONG', 'PHO_PHONG', 'SUPER_ADMIN'])) {
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
            sendDirectTaskAssignmentNotifications($pdo, $taskId, $currentUser, $title, $description, $dueDate, $allAssigneeIds);
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

// BATCH APPROVAL / REJECTION OF TASKS BY LEADERS (TRƯỞNG PHÒNG & BGD)
if ($action === 'batch_approve_tasks') {
    $taskIds = $_POST['task_ids'] ?? [];
    $generalComment = trim($_POST['leader_comments_general'] ?? '');
    $batchActionType = $_POST['batch_action_type'] ?? 'approve';

    if (!in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG', 'BGD', 'SUPER_ADMIN'])) {
        $_SESSION['flash_error'] = "Bạn không có quyền thực hiện chức năng phê duyệt hàng loạt!";
        header("Location: index.php");
        exit;
    }

    if (empty($taskIds) || !is_array($taskIds)) {
        $_SESSION['flash_error'] = "Vui lòng chọn ít nhất một công việc để phê duyệt!";
        header("Location: index.php");
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT id, title, department_id, status, leader_comments, created_by FROM tasks WHERE id = ?");

    if ($batchActionType === 'reject') {
        $rejectedCount = 0;
        $stmtReject = $pdo->prepare("
            UPDATE tasks
            SET status = 'YEU_CAU_CHINH_SUA', target_status = NULL, leader_comments = ?, leader_comment_user_id = ?
            WHERE id = ?
        ");
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, task_id, title, content, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");

        foreach ($taskIds as $taskId) {
            $taskId = (int)$taskId;
            $stmtCheck->execute([$taskId]);
            $task = $stmtCheck->fetch();

            if (!$task) continue;

            $canReject = false;
            if (in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
                if ($task['status'] === 'CHO_BGD_DUYET' || $task['status'] === 'CHO_DUYET') {
                    $canReject = true;
                }
            } elseif (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG'])) {
                if ($task['status'] === 'CHO_DUYET' && $task['department_id'] == $currentUser['department_id']) {
                    $canReject = true;
                }
            }

            if ($canReject) {
                $reasonText = $generalComment ?: 'Trưởng phòng chưa chấp nhận công việc này.';
                $stmtReject->execute([$reasonText, $currentUser['id'], $taskId]);
                $rejectedCount++;

                if (!empty($task['created_by'])) {
                    $notifTitle = "Công việc bị từ chối phê duyệt";
                    $notifContent = "Công việc '{$task['title']}' đã bị Trưởng phòng từ chối phê duyệt. Ý kiến: {$reasonText}. Bạn có thể chỉnh sửa lại nội dung hoặc xóa công việc này.";
                    $stmtNotif->execute([$task['created_by'], $taskId, $notifTitle, $notifContent]);
                }

                logTaskTimeline($pdo, $taskId, $currentUser['id'], 'REJECT_TASK', "Trưởng phòng từ chối phê duyệt hàng loạt" . ($generalComment ? ". Ý kiến: $generalComment" : ""));
                logActivity($pdo, $currentUser['id'], 'BATCH_REJECT_TASK', "Đã từ chối phê duyệt công việc ID #$taskId: {$task['title']}");
            }
        }

        if ($rejectedCount > 0) {
            $_SESSION['flash_success'] = "Đã từ chối phê duyệt $rejectedCount công việc thành công! Các nhân viên liên quan đã nhận được thông báo để chỉnh sửa hoặc xóa công việc.";
        } else {
            $_SESSION['flash_error'] = "Không có công việc nào bị từ chối (kiểm tra lại quyền hoặc trạng thái công việc).";
        }

        header("Location: index.php");
        exit;
    }

    $approvedCount = 0;

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
        } elseif (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG'])) {
            if ($task['status'] === 'CHO_DUYET' && $task['department_id'] == $currentUser['department_id']) {
                $canApprove = true;
            }
        }

        if ($canApprove) {
            $stmtUnaccepted = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND (is_accepted = 0 OR is_accepted IS NULL)");
            $stmtUnaccepted->execute([$taskId]);
            $unacceptedCount = (int)$stmtUnaccepted->fetchColumn();

            $batchActualStatus = ($unacceptedCount > 0) ? 'CHO_TIEP_NHAN' : 'DANG_THUC_HIEN';
            $batchTargetStatus = ($unacceptedCount > 0) ? 'DANG_THUC_HIEN' : null;

            if (!empty($generalComment)) {
                $pdo->prepare("UPDATE tasks SET status = ?, target_status = ?, leader_comments = ?, leader_comment_user_id = ? WHERE id = ?")
                    ->execute([$batchActualStatus, $batchTargetStatus, $generalComment, $currentUser['id'], $taskId]);
            } else {
                $pdo->prepare("UPDATE tasks SET status = ?, target_status = ?, leader_comment_user_id = ? WHERE id = ?")
                    ->execute([$batchActualStatus, $batchTargetStatus, $currentUser['id'], $taskId]);
            }
            $approvedCount++;
            $statusLogText = $batchActualStatus === 'CHO_TIEP_NHAN' ? 'Chờ tiếp nhận' : 'Đang thực hiện';
            logTaskTimeline($pdo, $taskId, $currentUser['id'], 'BATCH_APPROVE', "Phê duyệt hàng loạt: $statusLogText" . ($generalComment ? ". Ý kiến: $generalComment" : ""));
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

// 8. MARK ALL NOTIFICATIONS AS READ
if ($action === 'mark_notifications_read') {
    if ($currentUser) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$currentUser['id']]);
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

header("Location: index.php");
exit;
