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

    $sql = "SELECT u.*, COALESCE(d.name, 'Chưa phân công') AS department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id $whereSql ORDER BY d.id ASC, u.fullname ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get task assignees for a given task
function getTaskAssignees($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT ta.role_type, ta.is_accepted, ta.accepted_at, u.id AS user_id, u.fullname, u.email, u.department_id, COALESCE(d.name, 'Chưa phân công') AS department_name
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
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

// Render status badge (including prominent "Quá hạn" warning badge when overdue and unaccepted assignees)
function renderStatusBadge($status, $dueDate = null, $unacceptedAssignees = []) {
    $unacceptedNames = [];
    if (is_array($unacceptedAssignees)) {
        foreach ($unacceptedAssignees as $item) {
            if (is_array($item) && !empty($item['fullname'])) {
                $unacceptedNames[] = trim($item['fullname']);
            } elseif (is_string($item) && trim($item) !== '') {
                $unacceptedNames[] = trim($item);
            }
        }
    }
    $unacceptedNames = array_unique(array_filter($unacceptedNames));

    $unacceptedPill = '';
    $badge = '';

    switch ($status) {
        case 'CHO_BGD_DUYET':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="fa-solid fa-clock-rotate-left"></i> Chờ BGD duyệt
                    </span>';
            break;
        case 'CHO_DUYET':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <i class="fa-solid fa-hourglass-half"></i> Chờ Trưởng phòng duyệt
                    </span>';
            break;
        case '':
        case null:
        case 'CHO_TIEP_NHAN':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800 border border-cyan-200 shadow-2xs">
                        <i class="fa-solid fa-clock"></i> Chờ tiếp nhận
                    </span>';
            if (!empty($unacceptedNames)) {
                $allNamesStr = implode(', ', $unacceptedNames);
                $allNamesEsc = htmlspecialchars($allNamesStr, ENT_QUOTES, 'UTF-8');
                if (count($unacceptedNames) > 2) {
                    $firstTwo = array_slice($unacceptedNames, 0, 2);
                    $displayNamesStr = implode(', ', $firstTwo) . ' (+' . (count($unacceptedNames) - 2) . ')';
                } else {
                    $displayNamesStr = $allNamesStr;
                }
                $displayNamesEsc = htmlspecialchars($displayNamesStr, ENT_QUOTES, 'UTF-8');
                $unacceptedPill = '<div class="mt-1 text-[11px] text-amber-800 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200 font-semibold inline-flex items-center gap-1 shadow-2xs max-w-[210px] cursor-help" title="Nhân viên chưa tiếp nhận: ' . $allNamesEsc . '">
                                        <i class="fa-solid fa-user-clock text-amber-600 text-[10px] shrink-0"></i>
                                        <span class="truncate">Chưa nhận: <strong class="font-bold text-amber-900">' . $displayNamesEsc . '</strong></span>
                                   </div>';
            }
            break;
        case 'YEU_CAU_CHINH_SUA':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 shadow-xs">
                        <i class="fa-solid fa-circle-xmark text-rose-600"></i> Từ chối - Yêu cầu chỉnh sửa
                    </span>';
            break;
        case 'CHO_XAC_NHAN':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                        <i class="fa-solid fa-envelope-open-text"></i> Chờ NV xác nhận
                    </span>';
            break;
        case 'TU_CHOI_CHO_TP_XAC_NHAN':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">
                        <i class="fa-solid fa-circle-xmark"></i> Từ chối - Chờ TP xác nhận
                    </span>';
            break;
        case 'DANG_THUC_HIEN':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-sky-100 text-sky-800 border border-sky-200">
                        <i class="fa-solid fa-spinner fa-spin text-xs"></i> Đang thực hiện
                    </span>';
            break;
        case 'HOAN_THANH':
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <i class="fa-solid fa-circle-check"></i> Hoàn thành
                    </span>';
            break;
        default:
            $badge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . htmlspecialchars($status) . '</span>';
            break;
    }

    $overdueHtml = '';
    if (!empty($dueDate) && $status !== 'HOAN_THANH') {
        $today = date('Y-m-d');
        $due = date('Y-m-d', strtotime($dueDate));
        if ($due < $today) {
            $overdueHtml = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-600 text-white shadow-sm border border-rose-700 animate-pulse" title="Công việc đã quá hạn">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i> Quá hạn
                    </span>';
        }
    }

    if (!empty($unacceptedPill)) {
        return '<div class="inline-flex flex-col items-center">
                    <div class="inline-flex items-center gap-1.5 flex-wrap justify-center">
                        ' . $badge . '
                        ' . $overdueHtml . '
                    </div>
                    ' . $unacceptedPill . '
                </div>';
    }

    if (!empty($overdueHtml)) {
        return '<div class="inline-flex items-center gap-1.5 flex-wrap justify-center">
                    ' . $badge . '
                    ' . $overdueHtml . '
                </div>';
    }

    return $badge;
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
        } elseif (in_array($t['lc_role'], ['TRUONG_PHONG', 'PHO_PHONG'])) {
            $prefix = ($t['lc_fullname'] ?? 'Lãnh đạo phòng') . ': ';
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
        if (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG', 'NHAN_VIEN', 'HO_TRO'])) {
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
        $isDeptHead = in_array($currentUser['role'] ?? '', ['TRUONG_PHONG', 'PHO_PHONG']) ? 1 : 0;

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
    // Auto-reconcile database inconsistencies
    try {
        // 1. Update user fullname if 'Trưởng phòng HCTV'
        $pdo->exec("UPDATE users SET fullname = 'Trần Thị Bích Liên' WHERE fullname = 'Trưởng phòng HCTV'");

        // 2. Restore is_accepted = 1 for assignees who accepted in task_logs (reversing old edit bug)
        $pdo->exec("
            UPDATE task_assignees ta
            JOIN task_logs tl ON ta.task_id = tl.task_id AND ta.user_id = tl.user_id AND tl.action = 'ACCEPT_TASK'
            SET ta.is_accepted = 1,
                ta.accepted_at = COALESCE(ta.accepted_at, tl.created_at)
            WHERE ta.is_accepted = 0 OR ta.is_accepted IS NULL
        ");

        // 3. Auto-transition tasks stuck in CHO_TIEP_NHAN where all assignees have accepted
        $pdo->exec("
            UPDATE tasks t
            SET status = CASE WHEN t.target_status = 'CHO_BGD_DUYET' THEN 'CHO_BGD_DUYET' ELSE 'DANG_THUC_HIEN' END
            WHERE t.status = 'CHO_TIEP_NHAN'
              AND EXISTS (SELECT 1 FROM task_assignees WHERE task_id = t.id)
              AND NOT EXISTS (
                  SELECT 1 FROM task_assignees ta
                  WHERE ta.task_id = t.id AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
              )
        ");
    } catch (\Exception $e) {
        // Silently continue
    }

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
        $isDeptHead = in_array($currentUser['role'] ?? '', ['TRUONG_PHONG', 'PHO_PHONG']) ? 1 : 0;

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
            $where[] = "t.status IN ('CHO_DUYET', 'CHO_BGD_DUYET', 'CHO_TIEP_NHAN')";
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
    foreach ($tasks as $k => $task) {
        $tasks[$k]['assignees'] = getTaskAssignees($pdo, $task['id']);
        $tasks[$k]['attachments'] = getTaskAttachments($pdo, $task['id']);
        $tasks[$k]['formatted_leader_comment'] = formatLeaderComment($task);
        $tasks[$k]['timeline_logs'] = getTaskTimelineLogs($pdo, $task['id']);
    }
    unset($task);

    return [
        'data' => $tasks,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'page' => floor($offset / $limit) + 1,
        'total_pages' => max(1, ceil($total / $limit))
    ];
}

/**
 * Resolve login background path and URL with fallback handling
 */
function resolveLoginBackground($dbValue) {
    $rootDir = dirname(__DIR__);
    $rawPath = trim((string)($dbValue ?? ''));

    $path = '';
    $isCustom = false;

    if ($rawPath !== '') {
        $cleanPath = ltrim($rawPath, '/\\');
        if (strpos($cleanPath, 'uploads/bg/') !== 0) {
            if (strpos($cleanPath, 'bg/') === 0) {
                $cleanPath = 'uploads/' . $cleanPath;
            } else {
                $cleanPath = 'uploads/bg/' . $cleanPath;
            }
        }
        if (file_exists($rootDir . '/' . $cleanPath) && is_file($rootDir . '/' . $cleanPath)) {
            $path = $cleanPath;
            $isCustom = true;
        }
    }

    // Fallback if custom bg missing or empty
    if ($path === '') {
        $defaultPath = 'uploads/bg/default_bg.jpg';
        if (file_exists($rootDir . '/' . $defaultPath) && is_file($rootDir . '/' . $defaultPath)) {
            $path = $defaultPath;
        } elseif (file_exists($rootDir . '/assets/images/default_bg.jpg') && is_file($rootDir . '/assets/images/default_bg.jpg')) {
            $path = 'assets/images/default_bg.jpg';
        }
    }

    if ($path !== '') {
        $mtime = filemtime($rootDir . '/' . $path) ?: time();
        $url = $path . '?v=' . $mtime;
    } else {
        $url = '';
    }

    return [
        'path' => $path,
        'url' => $url,
        'is_custom' => $isCustom
    ];
}
if (!defined('GOOGLE_APPS_SCRIPT_UPLOAD_URL')) {
    define('GOOGLE_APPS_SCRIPT_UPLOAD_URL', 'https://script.google.com/macros/s/AKfycbyPLd8DzOsfXP4808SXZXCaPvHEOWJhtFPrqvuuW8eR5vA-Bb6rbF0-tI_Jjh-TLOC-/exec');
}
if (!defined('PROOF_UPLOAD_DIR')) {
    define('PROOF_UPLOAD_DIR', 'D:/web/minhchung');
}

/**
 * Lấy mã phiên bản dữ liệu hệ thống để hỗ trợ tự động làm mới (Auto-refresh)
 */
function getSystemDataVersion($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT CONCAT(
                COALESCE((SELECT MAX(id) FROM audit_logs), 0), ':',
                COALESCE((SELECT COUNT(*) FROM tasks), 0), ':',
                COALESCE((SELECT MAX(id) FROM task_timeline), 0), ':',
                COALESCE((SELECT MAX(progress) FROM tasks), 0), ':',
                COALESCE((SELECT MAX(id) FROM notifications), 0)
            ) AS v
        ");
        return $stmt->fetchColumn() ?: '0';
    } catch (\Exception $e) {
        return '0';
    }
}

/**
 * Tải file Hồ sơ Minh chứng lên ổ D:\web\minhchung và đồng bộ lên Google Drive qua Webhook
 */
function uploadProofFileToDrive($file) {
    // Tăng thời gian thực thi và bộ nhớ để xử lý file dung lượng lớn
    @set_time_limit(180);
    @ini_set('memory_limit', '256M');

    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Vui lòng chọn file hợp lệ để tải lên.'];
    }

    // Giới hạn dung lượng tối đa 30MB
    if ($file['size'] > 30 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Dung lượng file vượt quá giới hạn 30MB.'];
    }

    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip', 'rar', '7z', 'txt'];
    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'error' => 'Định dạng file không được hỗ trợ (chỉ chấp nhận: PDF, Word, Excel, PowerPoint, Ảnh, Zip, Rar).'];
    }

    // 1. Tạo thư mục D:\web\minhchung nếu chưa tồn tại
    $targetDir = PROOF_UPLOAD_DIR;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    // Fallback nếu ổ D không ghi được (trường hợp chạy trên môi trường Linux khác)
    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        $targetDir = dirname(__DIR__) . '/uploads/minhchung';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
    }

    // Tên file lưu trên ổ đĩa để tránh trùng lặp
    $safeName = preg_replace('/[^\w\.\-\p{L}]/u', '_', $origName);
    $diskFileName = date('Ymd_His') . '_' . $safeName;
    $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $diskFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Không thể lưu file vào thư mục máy chủ (' . $targetDir . ').'];
    }

    // 2. Đọc nội dung file chuyển sang base64 để gửi sang Google Apps Script
    $fileContent = @file_get_contents($targetPath);
    if ($fileContent === false) {
        return ['success' => false, 'error' => 'Không thể đọc nội dung file từ máy chủ.'];
    }

    $mimeType = $file['type'] ?: 'application/octet-stream';
    $postPayload = json_encode([
        'base64' => base64_encode($fileContent),
        'fileName' => $origName,
        'mimeType' => $mimeType
    ]);

    $webhookUrl = GOOGLE_APPS_SCRIPT_UPLOAD_URL;
    $response = false;
    $curlErr = '';

    if (function_exists('curl_init')) {
        // BƯỚC 1: POST payload sang Google Apps Script (tắt follow location để tránh lỗi 411 Length Required)
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postPayload)
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $rawPost = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // BƯỚC 2: Google Apps Script luôn trả về mã 302 redirect sang googleusercontent echo
        if (($httpCode === 301 || $httpCode === 302 || $httpCode === 303 || $httpCode === 307) && !empty($redirectUrl)) {
            $chGet = curl_init($redirectUrl);
            curl_setopt_array($chGet, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $response = curl_exec($chGet);
            $getErr = curl_error($chGet);
            curl_close($chGet);
            if (!empty($getErr)) {
                $curlErr = $getErr;
            }
        } else {
            $response = $rawPost;
        }
    } else {
        // Fallback dùng stream_context nếu máy chủ không có curl extension
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($postPayload) . "\r\n",
                'content' => $postPayload,
                'timeout' => 120,
                'follow_location' => 0
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $raw = @file_get_contents($webhookUrl, false, $context);
        $redirectUrl = null;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (stripos($hdr, 'Location:') === 0) {
                    $redirectUrl = trim(substr($hdr, 9));
                    break;
                }
            }
        }
        if (!empty($redirectUrl)) {
            $response = @file_get_contents($redirectUrl, false, stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]));
        } else {
            $response = $raw;
        }
    }

    if (!$response) {
        return [
            'success' => false,
            'error' => 'Đã lưu file nội bộ nhưng chưa thể kết nối đến Google Drive. ' . ($curlErr ? "($curlErr)" : ''),
            'localPath' => $targetPath
        ];
    }

    $result = json_decode($response, true);
    if (!empty($result) && ($result['status'] ?? '') === 'success') {
        return [
            'success' => true,
            'fileUrl' => $result['fileUrl'],
            'fileId' => $result['fileId'] ?? '',
            'fileName' => $origName,
            'localPath' => $targetPath
        ];
    }

    return [
        'success' => false,
        'error' => $result['message'] ?? 'Lỗi không xác định khi đồng bộ file lên Google Drive.',
        'localPath' => $targetPath
    ];
}
