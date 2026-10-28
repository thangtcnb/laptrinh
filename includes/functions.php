<?php
require_once __DIR__ . '/../config.php';

// Get list of all departments
function getDepartments($pdo) {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY id ASC");
    return $stmt->fetchAll();
}

// Get list of users (optionally filtered by department) - Excludes Super Admin from task assignments
function getUsers($pdo, $departmentId = null, $excludeSuperAdmin = false) {
    $where = [];
    $params = [];

    if ($departmentId) {
        $where[] = "u.department_id = ?";
        $params[] = $departmentId;
    }

    if ($excludeSuperAdmin) {
        $where[] = "u.role != 'SUPER_ADMIN' AND u.email != 'thangtcnb@gmail.com'";
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT u.*, d.name AS department_name FROM users u JOIN departments d ON u.department_id = d.id $whereSql ORDER BY d.id ASC, u.fullname ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get task assignees for a given task
function getTaskAssignees($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT ta.role_type, u.id AS user_id, u.fullname, u.email, u.department_id, d.name AS department_name
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.id
        JOIN departments d ON u.department_id = d.id
        WHERE ta.task_id = ?
        ORDER BY FIELD(ta.role_type, 'MAIN', 'COOP'), u.fullname ASC
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

// Get task attachments for a given task
function getTaskAttachments($pdo, $taskId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ta.*, u.fullname AS uploader_name
            FROM task_attachments ta
            LEFT JOIN users u ON ta.uploaded_by = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.id ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}

// Format file size in human-readable string
function formatFileSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' B';
    }
    return '0 B';
}

// Get FontAwesome icon class and color for file extension
function getFileIconClass($fileName) {
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return ['icon' => 'fa-file-pdf', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50', 'border' => 'border-rose-200'];
        case 'doc':
        case 'docx':
            return ['icon' => 'fa-file-word', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50', 'border' => 'border-blue-200'];
        case 'xls':
        case 'xlsx':
            return ['icon' => 'fa-file-excel', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-200'];
        case 'png':
        case 'jpg':
        case 'jpeg':
            return ['icon' => 'fa-file-image', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50', 'border' => 'border-purple-200'];
        case 'zip':
        case 'rar':
            return ['icon' => 'fa-file-zipper', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200'];
        default:
            return ['icon' => 'fa-file-lines', 'color' => 'text-slate-600', 'bg' => 'bg-slate-50', 'border' => 'border-slate-200'];
    }
}

// Format assignees according to system requirements
function formatAssigneesHtml($assignees) {
    if (empty($assignees)) {
        return '<span class="text-gray-400 italic">Chưa phân công</span>';
    }

    $mainAssignees = array_filter($assignees, function($a) { return $a['role_type'] === 'MAIN'; });
    $coopAssignees = array_filter($assignees, function($a) { return $a['role_type'] === 'COOP'; });

    if (count($assignees) === 1) {
        $single = reset($assignees);
        return htmlspecialchars($single['fullname']);
    }

    // >= 2 people
    $mainNames = implode(', ', array_map(function($a) { return htmlspecialchars($a['fullname']); }, $mainAssignees));
    $coopNames = implode(', ', array_map(function($a) { return htmlspecialchars($a['fullname']); }, $coopAssignees));

    $html = '<div class="text-xs leading-relaxed">';
    if ($mainNames !== '') {
        $html .= '<div><span class="font-semibold text-slate-800"><i class="fa-solid fa-user-check text-indigo-600 mr-1"></i>Chính:</span> <span class="font-bold text-indigo-900">' . $mainNames . '</span></div>';
    }
    if ($coopNames !== '') {
        $html .= '<div class="text-slate-600 mt-0.5"><span class="font-semibold text-slate-700"><i class="fa-solid fa-users text-sky-600 mr-1"></i>Phối hợp:</span> ' . $coopNames . '</div>';
    }
    $html .= '</div>';

    return $html;
}

// Format department badge UI with matching color themes
function renderDepartmentBadge($code, $name, $colorTheme, $icon) {
    $codeEsc = htmlspecialchars($code);
    $nameEsc = htmlspecialchars($name);
    $iconEsc = htmlspecialchars($icon ?: 'fa-building');

    switch (strtolower($colorTheme)) {
        case 'purple':
            $badgeClass = 'bg-purple-100 text-purple-800 border-purple-200';
            break;
        case 'emerald':
        case 'green':
            $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            break;
        case 'blue':
        default:
            $badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
            break;
    }

    return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border ' . $badgeClass . '" title="' . $nameEsc . '">
                <i class="fa-solid ' . $iconEsc . ' text-xs"></i>
                <span>' . $codeEsc . '</span>
            </span>';
}

// Render status badge (including prominent "Quá hạn" warning badge when overdue)
function renderStatusBadge($status, $dueDate = null) {
    $html = '';
    switch ($status) {
        case 'CHO_BGD_DUYET':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="fa-solid fa-clock-rotate-left"></i> Chờ BGD duyệt
                    </span>';
            break;
        case 'CHO_DUYET':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <i class="fa-solid fa-hourglass-half"></i> Chờ Trưởng phòng duyệt
                    </span>';
            break;
        case 'CHO_TIEP_NHAN':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800 border border-cyan-200">
                        <i class="fa-solid fa-clock"></i> Chờ tiếp nhận
                    </span>';
            break;
        case 'YEU_CAU_CHINH_SUA':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-300">
                        <i class="fa-solid fa-pen-to-square"></i> Yêu cầu chỉnh sửa
                    </span>';
            break;
        case 'CHO_XAC_NHAN':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                        <i class="fa-solid fa-envelope-open-text"></i> Chờ NV xác nhận
                    </span>';
            break;
        case 'TU_CHOI_CHO_TP_XAC_NHAN':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">
                        <i class="fa-solid fa-circle-xmark"></i> Từ chối - Chờ TP xác nhận
                    </span>';
            break;
        case 'DANG_THUC_HIEN':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-sky-100 text-sky-800 border border-sky-200">
                        <i class="fa-solid fa-spinner fa-spin text-xs"></i> Đang thực hiện
                    </span>';
            break;
        case 'HOAN_THANH':
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <i class="fa-solid fa-circle-check"></i> Hoàn thành
                    </span>';
            break;
        default:
            $html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . htmlspecialchars($status) . '</span>';
            break;
    }

    if (!empty($dueDate) && $status !== 'HOAN_THANH') {
        $today = date('Y-m-d');
        $due = date('Y-m-d', strtotime($dueDate));
        if ($due < $today) {
            $html .= ' <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-600 text-white shadow-sm border border-rose-700 animate-pulse" title="Công việc đã quá hạn">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i> Quá hạn
                    </span>';
        }
    }

    return $html;
}

// Render priority badge
function renderPriorityBadge($priority) {
    switch ($priority) {
        case 'URGENT':
        case 'KHAN_CAP':
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 animate-pulse">
                        <i class="fa-solid fa-triangle-exclamation mr-1 text-rose-600"></i> Khẩn cấp
                    </span>';
        case 'NORMAL':
        case 'TRUNG_BINH':
        case 'THAP':
        case 'CAO':
        default:
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">Bình thường</span>';
    }
}

// Format Leader Comment with prefix depending on role
function formatLeaderComment($t) {
    if (empty($t['leader_comments'])) {
        return '';
    }
    $comment = trim($t['leader_comments']);

    if (!empty($t['lc_role'])) {
        if ($t['lc_role'] === 'BGD') {
            $prefix = 'BGĐ: ';
        } elseif ($t['lc_role'] === 'TRUONG_PHONG') {
            $prefix = ($t['lc_fullname'] ?? 'Trưởng/Phó phòng') . ': ';
        } else {
            $prefix = ($t['lc_fullname'] ?? 'Lãnh đạo') . ': ';
        }
    } else {
        if (strpos($comment, 'BGĐ:') === 0 || preg_match('/^\[.+\]:/u', $comment)) {
            return $comment;
        }
        $prefix = '';
    }

    if (!empty($prefix)) {
        if (strpos($comment, $prefix) === 0) {
            return $comment;
        }
        return $prefix . $comment;
    }

    return $comment;
}

/**
 * Direct Task Assignment Notification Helper (Web Popup Notification)
 * Triggered when Ban Giám Đốc (BGĐ) or Department Head (Trưởng/Phó phòng) directly assigns tasks to staff.
 */
function sendDirectTaskAssignmentNotifications($pdo, $taskId, $assignerUser, $title, $description, $dueDate, $assigneeIds) {
    if (empty($assigneeIds)) return;

    try {
        if (!is_array($assigneeIds)) {
            $assigneeIds = [$assigneeIds];
        }
        $assigneeIds = array_unique(array_filter(array_map('intval', $assigneeIds)));
        if (empty($assigneeIds)) return;

        // Fetch Assigner Position / Role display
        $assignerName = $assignerUser['fullname'] ?? 'Lãnh đạo';
        $assignerRole = $assignerUser['role'] ?? '';
        $assignerDept = $assignerUser['department_name'] ?? '';

        $assignerTitle = 'Cán bộ quản lý';
        if ($assignerRole === 'BGD') {
            $assignerTitle = 'Ban Giám đốc';
        } elseif ($assignerRole === 'TRUONG_PHONG') {
            $assignerTitle = 'Trưởng phòng' . (!empty($assignerDept) ? " $assignerDept" : '');
        } elseif ($assignerRole === 'PHO_PHONG') {
            $assignerTitle = 'Phó phòng' . (!empty($assignerDept) ? " $assignerDept" : '');
        } elseif ($assignerRole === 'SUPER_ADMIN') {
            $assignerTitle = 'Ban Giám đốc / Quản trị hệ thống';
        }

        $assignerDisplay = $assignerName . ' (' . $assignerTitle . ')';

        // Fetch assigned users details
        $inClause = implode(',', array_fill(0, count($assigneeIds), '?'));
        $stmtAssignees = $pdo->prepare("
            SELECT u.id, u.fullname, u.email, u.role, u.department_id, d.name AS department_name
            FROM users u
            JOIN departments d ON u.department_id = d.id
            WHERE u.id IN ($inClause)
        ");
        $stmtAssignees->execute($assigneeIds);
        $assignedUsers = $stmtAssignees->fetchAll();

        $stmtLeaders = $pdo->prepare("
            SELECT id, fullname, email, role, department_id
            FROM users
            WHERE department_id = ? AND role IN ('TRUONG_PHONG', 'PHO_PHONG')
        ");

        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, task_id, title, content, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");

        $notifiedWebUserIds = [];

        $dueDateFormatted = !empty($dueDate) ? date('d/m/Y', strtotime($dueDate)) : 'Không quy định';

        foreach ($assignedUsers as $empUser) {
            // 1. Send Web Notification to Assigned Individual
            if (!in_array($empUser['id'], $notifiedWebUserIds)) {
                $notifTitleEmp = 'Thông báo giao công việc mới';
                $notifContentEmp = "$assignerDisplay vừa giao công việc: '$title' cho bạn. Hạn chót: $dueDateFormatted.";
                $stmtNotif->execute([$empUser['id'], $taskId, $notifTitleEmp, $notifContentEmp]);
                $notifiedWebUserIds[] = $empUser['id'];
            }

            // 2. If Assigner is BGD/Super Admin assigning directly to Employee, send Web Notification to Employee's Managing Department Leader
            if (in_array($assignerRole, ['BGD', 'SUPER_ADMIN']) && in_array($empUser['role'], ['NHAN_VIEN', 'HO_TRO', 'PHO_PHONG'])) {
                $stmtLeaders->execute([$empUser['department_id']]);
                $leaders = $stmtLeaders->fetchAll();

                foreach ($leaders as $leader) {
                    if (!in_array($leader['id'], $notifiedWebUserIds)) {
                        $notifTitleLeader = 'Thông báo BGD giao việc cho nhân viên đơn vị';
                        $notifContentLeader = "BGĐ vừa giao công việc '$title' cho nhân viên " . $empUser['fullname'] . " thuộc đơn vị của bạn.";
                        $stmtNotif->execute([$leader['id'], $taskId, $notifTitleLeader, $notifContentLeader]);
                        $notifiedWebUserIds[] = $leader['id'];
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("sendDirectTaskAssignmentNotifications Error: " . $e->getMessage());
    }
}

/**
 * Legacy wrapper for BGD Task Notifications
 */
function sendBgdTaskNotifications($pdo, $taskId, $title, $description, $dueDate, $assigneeIds) {
    $bgdUser = ['fullname' => 'Ban Giám đốc', 'role' => 'BGD'];
    sendDirectTaskAssignmentNotifications($pdo, $taskId, $bgdUser, $title, $description, $dueDate, $assigneeIds);
}

// Get KPI stats based on selected filter and user permissions
function getKpiStats($pdo, $currentUser, $filters = []) {
    if (!$currentUser) {
        return ['completed' => 0, 'in_progress' => 0, 'overdue' => 0, 'pending' => 0];
    }

    $where = ["1=1"];
    $params = [];

    // Filter by due date range if provided in filters
    if (!empty($filters['date_from'])) {
        $dateFromVal = $filters['date_from'];
        if (strlen($dateFromVal) === 10) {
            $dateFromVal .= ' 00:00:00';
        }
        $where[] = "t.due_date >= ?";
        $params[] = $dateFromVal;
    }
    if (!empty($filters['date_to'])) {
        $dateToVal = $filters['date_to'];
        if (strlen($dateToVal) === 10) {
            $dateToVal .= ' 23:59:59';
        }
        $where[] = "t.due_date <= ?";
        $params[] = $dateToVal;
    }

    // Dynamic scoping based on dept_filter parameter
    if (isset($filters['dept_filter'])) {
        if ($filters['dept_filter'] === 'MY_TASKS') {
            $where[] = "(t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
            $params[] = $currentUser['id'];
            $params[] = $currentUser['id'];
        } elseif (is_numeric($filters['dept_filter'])) {
            $where[] = "(t.department_id = ? OR t.id IN (SELECT ta.task_id FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE u.department_id = ?))";
            $params[] = (int)$filters['dept_filter'];
            $params[] = (int)$filters['dept_filter'];
        }
    } else {
        // Fallback default scoping if dept_filter not passed
        if (in_array($currentUser['role'], ['TRUONG_PHONG', 'NHAN_VIEN', 'HO_TRO'])) {
            if (!empty($currentUser['department_id'])) {
                $where[] = "(t.department_id = ? OR t.id IN (SELECT ta.task_id FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE u.department_id = ?))";
                $params[] = $currentUser['department_id'];
                $params[] = $currentUser['department_id'];
            }
        }
    }

    // Task Visibility Logic:
    // In-progress / completed tasks are public to all campus users.
    // Unapproved pending tasks are visible to creator, assigned employees, department head, BGD, or Super Admin.
    if (!in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
        $userId = $currentUser['id'] ?? 0;
        $deptId = $currentUser['department_id'] ?? 0;
        $isDeptHead = ($currentUser['role'] ?? '') === 'TRUONG_PHONG' ? 1 : 0;

        $where[] = "(
            t.status NOT IN ('CHO_DUYET', 'CHO_BGD_DUYET')
            OR t.created_by = ?
            OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)
            OR (? = 1 AND t.department_id = ?)
        )";
        $params[] = $userId;
        $params[] = $userId;
        $params[] = $isDeptHead;
        $params[] = $deptId;
    }

    $whereSql = implode(" AND ", $where);

    $sql = "
        SELECT
            SUM(CASE WHEN t.status = 'HOAN_THANH' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN t.status = 'DANG_THUC_HIEN' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN t.status IN ('CHO_DUYET', 'CHO_BGD_DUYET', 'CHO_TIEP_NHAN') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN (t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status != 'HOAN_THANH') THEN 1 ELSE 0 END) AS overdue_count
        FROM tasks t
        WHERE $whereSql
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'completed' => (int)($row['completed_count'] ?? 0),
        'in_progress' => (int)($row['in_progress_count'] ?? 0),
        'pending' => (int)($row['pending_count'] ?? 0),
        'overdue' => (int)($row['overdue_count'] ?? 0),
    ];
}

// Helper function to record audit logs
function logActivity($pdo, $userId, $action, $description = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $description]);
    } catch (\PDOException $e) {
        // ignore log error
    }
}

// Helper function to record task timeline log (Lịch sử thao tác xử lý công việc)
function logTaskTimeline($pdo, $taskId, $userId, $action, $description = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, action, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$taskId, $userId, $action, $description]);
    } catch (\PDOException $e) {
        // ignore log error
    }
}

// Get task timeline logs for a specific task
function getTaskTimelineLogs($pdo, $taskId) {
    try {
        $stmt = $pdo->prepare("
            SELECT tl.*, u.fullname, u.role, d.code AS department_code
            FROM task_logs tl
            LEFT JOIN users u ON tl.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE tl.task_id = ?
            ORDER BY tl.id ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}

// Get tasks query with smart filters and pagination
function getTasksFiltered($pdo, $filters = [], $limit = 15, $offset = 0, $currentUser = null) {
    $where = ["1=1"];
    $params = [];

    // Filter by due date range
    if (!empty($filters['date_from'])) {
        $dateFromVal = $filters['date_from'];
        if (strlen($dateFromVal) === 10) {
            $dateFromVal .= ' 00:00:00';
        }
        $where[] = "t.due_date >= ?";
        $params[] = $dateFromVal;
    }
    if (!empty($filters['date_to'])) {
        $dateToVal = $filters['date_to'];
        if (strlen($dateToVal) === 10) {
            $dateToVal .= ' 23:59:59';
        }
        $where[] = "t.due_date <= ?";
        $params[] = $dateToVal;
    }

    // Filter by department or "📌 Công việc của tôi"
    if (!empty($filters['dept_filter'])) {
        if ($filters['dept_filter'] === 'MY_TASKS' && $currentUser) {
            $where[] = "(t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
            $params[] = $currentUser['id'];
            $params[] = $currentUser['id'];
        } elseif (is_numeric($filters['dept_filter'])) {
            $where[] = "(t.department_id = ? OR t.id IN (SELECT ta.task_id FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE u.department_id = ?))";
            $params[] = (int)$filters['dept_filter'];
            $params[] = (int)$filters['dept_filter'];
        }
    }

    // Task Visibility Logic:
    // All campus users can view in-progress / completed tasks across all departments.
    // Unapproved pending tasks (CHO_DUYET, CHO_BGD_DUYET) are restricted to creator, assignees, managing department head, BGD, or Super Admin.
    if (!$currentUser || !in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
        $userId = $currentUser['id'] ?? 0;
        $deptId = $currentUser['department_id'] ?? 0;
        $isDeptHead = ($currentUser['role'] ?? '') === 'TRUONG_PHONG' ? 1 : 0;

        $where[] = "(
            t.status NOT IN ('CHO_DUYET', 'CHO_BGD_DUYET')
            OR t.created_by = ?
            OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)
            OR (? = 1 AND t.department_id = ?)
        )";
        $params[] = $userId;
        $params[] = $userId;
        $params[] = $isDeptHead;
        $params[] = $deptId;
    }

    // Filter by status
    if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
        if ($filters['status'] === 'OVERDUE') {
            $where[] = "(t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status != 'HOAN_THANH')";
        } elseif ($filters['status'] === 'CHO_DUYET_ALL') {
            $where[] = "t.status IN ('CHO_DUYET', 'CHO_BGD_DUYET')";
        } else {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }
    }

    $whereSql = implode(" AND ", $where);

    // Count query
    $countSql = "SELECT COUNT(DISTINCT t.id) FROM tasks t WHERE $whereSql";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Data query
    $dataSql = "
        SELECT t.*,
               d.code AS dept_code, d.name AS dept_name, d.color_theme AS dept_color, d.icon AS dept_icon,
               u.fullname AS creator_name, u.role AS creator_role,
               lc.fullname AS lc_fullname, lc.role AS lc_role
        FROM tasks t
        JOIN departments d ON t.department_id = d.id
        JOIN users u ON t.created_by = u.id
        LEFT JOIN users lc ON t.leader_comment_user_id = lc.id
        WHERE $whereSql
        ORDER BY t.id DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $stmtData = $pdo->prepare($dataSql);
    $stmtData->execute($params);
    $tasks = $stmtData->fetchAll();

    // Populate assignees, attachments, formatted leader comment & timeline logs for each task
    foreach ($tasks as &$task) {
        $task['assignees'] = getTaskAssignees($pdo, $task['id']);
        $task['attachments'] = getTaskAttachments($pdo, $task['id']);
        $task['formatted_leader_comment'] = formatLeaderComment($task);
        $task['timeline_logs'] = getTaskTimelineLogs($pdo, $task['id']);
    }

    return [
        'data' => $tasks,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'page' => floor($offset / $limit) + 1,
        'total_pages' => max(1, ceil($total / $limit))
    ];
}
