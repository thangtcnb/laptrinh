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
        SELECT ta.role_type, u.id AS user_id, u.fullname, u.email, d.name AS department_name
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.id
        JOIN departments d ON u.department_id = d.id
        WHERE ta.task_id = ?
        ORDER BY FIELD(ta.role_type, 'MAIN', 'COOP'), u.fullname ASC
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
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

// Render status badge
function renderStatusBadge($status) {
    switch ($status) {
        case 'CHO_BGD_DUYET':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="fa-solid fa-clock-rotate-left"></i> Chờ BGD duyệt
                    </span>';
        case 'CHO_DUYET':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <i class="fa-solid fa-hourglass-half"></i> Chờ Trưởng phòng duyệt
                    </span>';
        case 'CHO_TIEP_NHAN':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800 border border-cyan-200">
                        <i class="fa-solid fa-clock"></i> Chờ tiếp nhận
                    </span>';
        case 'YEU_CAU_CHINH_SUA':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-300">
                        <i class="fa-solid fa-pen-to-square"></i> Yêu cầu chỉnh sửa
                    </span>';
        case 'CHO_XAC_NHAN':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                        <i class="fa-solid fa-envelope-open-text"></i> Chờ NV xác nhận
                    </span>';
        case 'TU_CHOI_CHO_TP_XAC_NHAN':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">
                        <i class="fa-solid fa-circle-xmark"></i> Từ chối - Chờ TP xác nhận
                    </span>';
        case 'DANG_THUC_HIEN':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-sky-100 text-sky-800 border border-sky-200">
                        <i class="fa-solid fa-spinner fa-spin text-xs"></i> Đang thực hiện
                    </span>';
        case 'HOAN_THANH':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <i class="fa-solid fa-circle-check"></i> Hoàn thành
                    </span>';
        default:
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . htmlspecialchars($status) . '</span>';
    }
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

// Get KPI stats based on user role and scope
// Role permissions:
// - NHAN_VIEN / HO_TRO: Personal tasks (Cá nhân)
// - TRUONG_PHONG: Department tasks (Toàn Phòng)
// - BGD / SUPER_ADMIN: Campus-wide tasks (Toàn Phân hiệu)
function getKpiStats($pdo, $currentUser) {
    if (!$currentUser) {
        return ['completed' => 0, 'in_progress' => 0, 'overdue' => 0, 'pending' => 0];
    }

    $where = ["1=1"];
    $params = [];

    // Role-based scoping
    if ($currentUser['role'] === 'NHAN_VIEN' || $currentUser['role'] === 'HO_TRO') {
        $where[] = "(t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
        $params[] = $currentUser['id'];
        $params[] = $currentUser['id'];
    } elseif ($currentUser['role'] === 'TRUONG_PHONG') {
        $where[] = "t.department_id = ?";
        $params[] = $currentUser['department_id'];
    }
    // For BGD and SUPER_ADMIN, default to all campus tasks

    // Internal BGD Security Restriction (Allow assigned users to see tasks assigned to them by BGD)
    if (!in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
        $where[] = "((t.department_id != 1 AND t.created_by NOT IN (SELECT id FROM users WHERE role = 'BGD')) OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
        $params[] = $currentUser['id'];
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

    // Filter by date range
    if (!empty($filters['date_from'])) {
        $where[] = "(t.start_date >= ? OR t.due_date >= ?)";
        $params[] = $filters['date_from'];
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = "(t.start_date <= ? OR t.due_date <= ?)";
        $params[] = $filters['date_to'];
        $params[] = $filters['date_to'];
    }

    // Filter by department or "📌 Công việc của tôi"
    if (!empty($filters['dept_filter'])) {
        if ($filters['dept_filter'] === 'MY_TASKS' && $currentUser) {
            $where[] = "(t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
            $params[] = $currentUser['id'];
            $params[] = $currentUser['id'];
        } elseif (is_numeric($filters['dept_filter'])) {
            $where[] = "t.department_id = ?";
            $params[] = (int)$filters['dept_filter'];
        }
    }

    // Internal BGD Security Restriction:
    // Internal BGD tasks are restricted unless assigned to the user
    if (!$currentUser || !in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
        $where[] = "((t.department_id != 1 AND t.created_by NOT IN (SELECT id FROM users WHERE role = 'BGD')) OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
        $params[] = $currentUser['id'];
    }

    // Role-based visibility restriction for Pending Approval (CHO_DUYET) tasks:
    // If a task is CHO_DUYET (waiting for Department Head approval), it should only be visible to:
    // - The creator or assignees of the task
    // - Department Head / Phó phòng (TRUONG_PHONG) of that specific department
    // - Board of Directors (BGD)
    if ($currentUser) {
        if ($currentUser['role'] === 'TRUONG_PHONG') {
            $where[] = "(t.status != 'CHO_DUYET' OR t.department_id = ? OR t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
            $params[] = $currentUser['department_id'];
            $params[] = $currentUser['id'];
            $params[] = $currentUser['id'];
        } elseif ($currentUser['role'] === 'NHAN_VIEN' || $currentUser['role'] === 'HO_TRO') {
            $where[] = "(t.status != 'CHO_DUYET' OR t.created_by = ? OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?))";
            $params[] = $currentUser['id'];
            $params[] = $currentUser['id'];
        }
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

    // Populate assignees & formatted leader comment & timeline logs for each task
    foreach ($tasks as &$task) {
        $task['assignees'] = getTaskAssignees($pdo, $task['id']);
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
