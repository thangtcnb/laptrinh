<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/libs/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

requireLogin();
$currentUser = getCurrentUser($pdo);

$timeFrame = $_GET['time_frame'] ?? '';
$timeDetail = $_GET['time_detail'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if (!empty($timeDetail) && strpos($timeDetail, '|') !== false) {
    list($df, $dt) = explode('|', $timeDetail, 2);
    if (empty($dateFrom)) $dateFrom = $df;
    if (empty($dateTo)) $dateTo = $dt;
}

if (empty($dateFrom) && empty($timeFrame)) $dateFrom = date('Y-m-01');
if (empty($dateTo) && empty($timeFrame)) $dateTo = date('Y-m-t');
$requestedScope = $_GET['scope'] ?? 'ALL';
$status = $_GET['status'] ?? 'ALL';
$exportType = $_GET['export_type'] ?? '';

// Enforce Role-Based Scope Permissions
$departmentsList = getDepartments($pdo);
$usersList = getUsers($pdo);

if ($currentUser['role'] === 'NHAN_VIEN' || $currentUser['role'] === 'HO_TRO') {
    $scope = 'USER_' . $currentUser['id'];
} elseif ($currentUser['role'] === 'TRUONG_PHONG') {
    $deptId = $currentUser['department_id'];
    $allowedUserIds = array_column(getUsers($pdo, $deptId), 'id');

    if (strpos($requestedScope, 'USER_') === 0) {
        $uId = (int)str_replace('USER_', '', $requestedScope);
        if (in_array($uId, $allowedUserIds)) {
            $scope = 'USER_' . $uId;
        } else {
            $scope = 'DEPT_' . $deptId;
        }
    } else {
        $scope = 'DEPT_' . $deptId;
    }
} else {
    // BGD: full access
    $scope = $requestedScope;
}

// Build Query
$where = ["1=1"];
$params = [];

// Task Visibility Logic for Export:
// 1. Direct BGD Tasks (department_id = 1 OR created_by in BGD role):
//    Only assigned employees, task creator, managing department leaders of assigned employees, BGD, and Super Admin can view/export.
// 2. General / Campus Public Tasks:
//    All campus members can view/export in-progress/completed tasks within their allowed scope.
if (!$currentUser || !in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
    $userId = $currentUser['id'] ?? 0;
    $deptId = $currentUser['department_id'] ?? 0;
    $isDeptHead = ($currentUser['role'] ?? '') === 'TRUONG_PHONG' ? 1 : 0;

    $where[] = "(
        (
            t.department_id != 1
            AND t.created_by NOT IN (SELECT id FROM users WHERE role = 'BGD')
            AND (
                t.status != 'CHO_DUYET'
                OR t.department_id = ?
                OR t.created_by = ?
                OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)
            )
        )
        OR
        (
            (t.department_id = 1 OR t.created_by IN (SELECT id FROM users WHERE role = 'BGD'))
            AND (
                t.created_by = ?
                OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)
                OR (? = 1 AND t.id IN (SELECT ta.task_id FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE u.department_id = ?))
            )
        )
    )";
    $params[] = $deptId;
    $params[] = $userId;
    $params[] = $userId;
    $params[] = $userId;
    $params[] = $userId;
    $params[] = $isDeptHead;
    $params[] = $deptId;
}

if (!empty($dateFrom)) {
    $dateFromVal = $dateFrom;
    if (strlen($dateFromVal) === 10) {
        $dateFromVal .= ' 00:00:00';
    }
    $where[] = "t.due_date >= ?";
    $params[] = $dateFromVal;
}
if (!empty($dateTo)) {
    $dateToVal = $dateTo;
    if (strlen($dateToVal) === 10) {
        $dateToVal .= ' 23:59:59';
    }
    $where[] = "t.due_date <= ?";
    $params[] = $dateToVal;
}

if (!empty($status) && $status !== 'ALL') {
    $where[] = "t.status = ?";
    $params[] = $status;
}

if (strpos($scope, 'DEPT_') === 0) {
    $deptId = (int)str_replace('DEPT_', '', $scope);
    $where[] = "(t.department_id = ? OR t.id IN (SELECT task_id FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE u.department_id = ?))";
    $params[] = $deptId;
    $params[] = $deptId;
} elseif (strpos($scope, 'USER_') === 0) {
    $userId = (int)str_replace('USER_', '', $scope);
    $where[] = "t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)";
    $params[] = $userId;
}

$whereSql = implode(" AND ", $where);

$sql = "
    SELECT t.*,
           d.code AS dept_code, d.name AS dept_name, d.color_theme AS dept_color, d.icon AS dept_icon,
           u.fullname AS creator_name
    FROM tasks t
    JOIN departments d ON t.department_id = d.id
    JOIN users u ON t.created_by = u.id
    WHERE $whereSql
    ORDER BY t.department_id ASC, t.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allTasksForExcel = $stmt->fetchAll();

foreach ($allTasksForExcel as &$t) {
    $t['assignees'] = getTaskAssignees($pdo, $t['id']);
}

// ---------------- Handle Standard OpenXML .xlsx Excel Download ----------------
if ($exportType === 'excel') {
    // Split tasks into Part 1 (MAIN) and Part 2 (COOP)
    $part1Tasks = [];
    $part2Tasks = [];

    if (strpos($scope, 'USER_') === 0) {
        $targetUserId = (int)str_replace('USER_', '', $scope);
        foreach ($allTasksForExcel as $t) {
            $mains = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'MAIN');
            $coops = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'COOP');

            $isMain = in_array($targetUserId, array_column($mains, 'user_id'));
            $isCoop = in_array($targetUserId, array_column($coops, 'user_id'));

            if ($isMain) {
                $part1Tasks[] = $t;
            }
            if ($isCoop) {
                $part2Tasks[] = $t;
            }
        }
    } elseif (strpos($scope, 'DEPT_') === 0) {
        $targetDeptId = (int)str_replace('DEPT_', '', $scope);
        $deptUserIds = array_column(getUsers($pdo, $targetDeptId), 'id');
        foreach ($allTasksForExcel as $t) {
            $mains = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'MAIN');
            $coops = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'COOP');

            $mainUserIds = array_column($mains, 'user_id');
            $coopUserIds = array_column($coops, 'user_id');

            $hasDeptMain = !empty(array_intersect($deptUserIds, $mainUserIds)) || ($t['department_id'] == $targetDeptId);
            $hasDeptCoop = !empty(array_intersect($deptUserIds, $coopUserIds));

            if ($hasDeptMain) {
                $part1Tasks[] = $t;
            }
            if ($hasDeptCoop) {
                $part2Tasks[] = $t;
            }
        }
    } else {
        foreach ($allTasksForExcel as $t) {
            $coops = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'COOP');

            $part1Tasks[] = $t;
            if (!empty($coops)) {
                $part2Tasks[] = $t;
            }
        }
    }

    $statusMap = [
        'CHO_DUYET' => 'Chờ Trưởng phòng duyệt',
        'CHO_BGD_DUYET' => 'Chờ BGD duyệt',
        'YEU_CAU_CHINH_SUA' => 'Yêu cầu chỉnh sửa',
        'CHO_XAC_NHAN' => 'Chờ NV xác nhận',
        'TU_CHOI_CHO_TP_XAC_NHAN' => 'Từ chối - Chờ TP xác nhận',
        'DANG_THUC_HIEN' => 'Đang thực hiện',
        'HOAN_THANH' => 'Hoàn thành'
    ];

    $rows = [];
    $mergeRanges = [];

    // Title Section
    $rows[] = ['<center><b><style color="#1E3A8A" font-size="12">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING</style></b></center>', '', '', '', ''];
    $mergeRanges[] = 'A1:E1';

    $rows[] = ['<center><b><style font-size="11">PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</style></b></center>', '', '', '', ''];
    $mergeRanges[] = 'A2:E2';

    $rows[] = ['<center><b><style color="#1E3A8A" font-size="14">BÁO CÁO KẾT QUẢ THỰC HIỆN CÔNG VIỆC</style></b></center>', '', '', '', ''];
    $mergeRanges[] = 'A3:E3';

    $dateFromStr = date('d/m/Y', strtotime($dateFrom));
    $dateToStr = date('d/m/Y', strtotime($dateTo));
    $rows[] = ['<center><i><style font-size="10">Từ ngày ' . $dateFromStr . ' đến ngày ' . $dateToStr . '</style></i></center>', '', '', '', ''];
    $mergeRanges[] = 'A4:E4';

    $rows[] = ['', '', '', '', '']; // Empty row

    // Helper to append table
    $appendTable = function($bannerTitle, $tasks) use (&$rows, &$mergeRanges, $statusMap) {
        $startRow = count($rows) + 1;
        // Banner Row
        $rows[] = ['<style bgcolor="#0F172A" color="#FFFFFF" height="24"><b> ' . $bannerTitle . '</b></style>', '', '', '', ''];
        $mergeRanges[] = 'A' . $startRow . ':E' . $startRow;

        // Header Row (5 exact columns)
        $rows[] = [
            '<style bgcolor="#1E3A8A" color="#FFFFFF"><center><b>Người phụ trách</b></center></style>',
            '<style bgcolor="#1E3A8A" color="#FFFFFF"><center><b>Tên công việc</b></center></style>',
            '<style bgcolor="#1E3A8A" color="#FFFFFF"><center><b>Thời gian thực hiện</b></center></style>',
            '<style bgcolor="#1E3A8A" color="#FFFFFF"><center><b>Trạng thái</b></center></style>',
            '<style bgcolor="#1E3A8A" color="#FFFFFF"><center><b>Minh chứng</b></center></style>'
        ];

        if (!empty($tasks)) {
            foreach ($tasks as $t) {
                $mains = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'MAIN');
                $coops = array_filter($t['assignees'], fn($a) => $a['role_type'] === 'COOP');
                $mainNames = implode(', ', array_column($mains, 'fullname'));
                $coopNames = implode(', ', array_column($coops, 'fullname'));

                $assigneeText = $mainNames;
                if (!empty($coopNames)) {
                    $assigneeText .= " (Phối hợp: " . $coopNames . ")";
                }
                if (empty($assigneeText)) {
                    $assigneeText = "Chưa phân công";
                }

                $taskContent = '<b>' . htmlspecialchars($t['title'], ENT_QUOTES) . '</b>';
                if (!empty($t['description'])) {
                    $taskContent .= "\n" . htmlspecialchars($t['description'], ENT_QUOTES);
                }
                $taskContentFormatted = '<wraptext>' . $taskContent . '</wraptext>';

                $startDateFmt = !empty($t['start_date']) ? date('d/m/Y', strtotime($t['start_date'])) : '';
                $dueDateFmt = !empty($t['due_date']) ? date('d/m/Y', strtotime($t['due_date'])) : '';
                if (!empty($startDateFmt) && !empty($dueDateFmt)) {
                    $timeStr = $startDateFmt . ' - ' . $dueDateFmt;
                } elseif (!empty($dueDateFmt)) {
                    $timeStr = $dueDateFmt;
                } else {
                    $timeStr = '---';
                }
                $timeFormatted = '<center>' . $timeStr . '</center>';

                $statusText = $statusMap[$t['status']] ?? $t['status'];
                $statusFormatted = '<center>' . htmlspecialchars($statusText, ENT_QUOTES) . '</center>';

                $proofLink = trim($t['proof_link'] ?? '');
                if (!empty($proofLink)) {
                    $proofFormatted = '<a href="' . htmlspecialchars($proofLink, ENT_QUOTES) . '">' . htmlspecialchars($proofLink, ENT_QUOTES) . '</a>';
                } else {
                    $proofFormatted = '';
                }

                $rows[] = [
                    $assigneeText,
                    $taskContentFormatted,
                    $timeFormatted,
                    $statusFormatted,
                    $proofFormatted
                ];
            }
        } else {
            $emptyRowIndex = count($rows) + 1;
            $rows[] = ['<i><center><style color="#888888">Không có công việc nào trong danh mục này.</style></center></i>', '', '', '', ''];
            $mergeRanges[] = 'A' . $emptyRowIndex . ':E' . $emptyRowIndex;
        }

        $rows[] = ['', '', '', '', '']; // Empty spacing row
    };

    // Build Table 1 & Table 2
    $appendTable("BẢNG 1: CÔNG VIỆC CHÍNH (Do cá nhân phụ trách chính)", $part1Tasks);
    $appendTable("BẢNG 2: CÔNG VIỆC PHỐI HỢP (Cá nhân tham gia phối hợp)", $part2Tasks);

    $xlsx = SimpleXLSXGen::fromArray($rows, "Bao Cao Cong Viec");

    foreach ($mergeRanges as $range) {
        $xlsx->mergeCells($range);
    }

    // Set custom column widths (5 columns)
    $xlsx->setColWidth(1, 30);
    $xlsx->setColWidth(2, 50);
    $xlsx->setColWidth(3, 24);
    $xlsx->setColWidth(4, 26);
    $xlsx->setColWidth(5, 38);

    // Save to temp file and serve with binary download headers
    $filename = 'Bao_Cao_Cong_Viec_UFM_Quang_Ngai_' . date('Ymd_His') . '.xlsx';
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $xlsx->saveAs($tmpFile);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tmpFile));

    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

// Pagination logic for Preview Data (15 tasks per page)
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total matching tasks
$countSql = "SELECT COUNT(DISTINCT t.id) FROM tasks t JOIN departments d ON t.department_id = d.id JOIN users u ON t.created_by = u.id WHERE $whereSql";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalTasks = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalTasks / $limit));

// Fetch paginated preview tasks
$paginatedSql = $sql . " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$stmtPaginated = $pdo->prepare($paginatedSql);
$stmtPaginated->execute($params);
$previewTasks = $stmtPaginated->fetchAll();

foreach ($previewTasks as &$t) {
    $t['assignees'] = getTaskAssignees($pdo, $t['id']);
}

define('PAGE_TITLE', 'Xuất Báo Cáo');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Export Controls -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
            <i class="fa-solid fa-file-export text-sm"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base">Tùy Chọn Xuất Báo Cáo Dữ Liệu</h3>
    </div>

    <form method="GET" action="export.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Hidden inputs for date range -->
        <input type="hidden" name="date_from" id="filter_date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        <input type="hidden" name="date_to" id="filter_date_to" value="<?= htmlspecialchars($dateTo) ?>">

        <!-- COMBOBOX 1: Khung thời gian -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Khung thời gian</label>
            <select name="time_frame" id="filter_time_frame" onchange="onTimeFrameChange()" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                <option value="">-- Tất cả thời gian --</option>
                <option value="week" <?= $timeFrame === 'week' ? 'selected' : '' ?>>Tuần</option>
                <option value="month" <?= $timeFrame === 'month' ? 'selected' : '' ?>>Tháng</option>
                <option value="quarter" <?= $timeFrame === 'quarter' ? 'selected' : '' ?>>Quý</option>
                <option value="year" <?= $timeFrame === 'year' ? 'selected' : '' ?>>Năm</option>
            </select>
        </div>

        <!-- COMBOBOX 2: Chi tiết thời gian -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Chi tiết thời gian</label>
            <select name="time_detail" id="filter_time_detail" onchange="onTimeDetailChange()" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                <option value="">-- Tất cả --</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Đối tượng xuất báo cáo</label>
            <select name="scope" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                <?php if ($currentUser['role'] === 'NHAN_VIEN' || $currentUser['role'] === 'HO_TRO'): ?>
                    <option value="USER_<?= $currentUser['id'] ?>" selected>
                        📌 Cá nhân tôi: <?= htmlspecialchars($currentUser['fullname']) ?>
                    </option>
                <?php elseif ($currentUser['role'] === 'TRUONG_PHONG'): ?>
                    <?php
                    $myDeptId = $currentUser['department_id'];
                    $deptObjArr = array_filter($departmentsList, fn($d) => $d['id'] == $myDeptId);
                    $myDept = reset($deptObjArr);
                    $deptUsers = array_filter($usersList, fn($u) => $u['department_id'] == $myDeptId);
                    ?>
                    <option value="DEPT_<?= $myDeptId ?>" <?= $scope === 'DEPT_' . $myDeptId ? 'selected' : '' ?>>
                        🏢 Toàn phòng: <?= htmlspecialchars($myDept['name'] ?? '') ?>
                    </option>
                    <optgroup label="Cá nhân thuộc phòng">
                        <?php foreach ($deptUsers as $u): ?>
                            <option value="USER_<?= $u['id'] ?>" <?= $scope === 'USER_' . $u['id'] ? 'selected' : '' ?>>
                                Cá nhân: <?= htmlspecialchars($u['fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php else: ?>
                    <option value="ALL" <?= $scope === 'ALL' ? 'selected' : '' ?>>-- Toàn Phân hiệu Quảng Ngãi --</option>
                    <optgroup label="Theo Phòng ban">
                        <?php foreach ($departmentsList as $d): ?>
                            <option value="DEPT_<?= $d['id'] ?>" <?= $scope === 'DEPT_' . $d['id'] ? 'selected' : '' ?>>
                                Phòng/Ban: <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Theo Cá nhân">
                        <?php foreach ($usersList as $u): ?>
                            <option value="USER_<?= $u['id'] ?>" <?= $scope === 'USER_' . $u['id'] ? 'selected' : '' ?>>
                                Cá nhân: <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Trạng thái công việc</label>
            <select name="status" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                <option value="DANG_THUC_HIEN" <?= $status === 'DANG_THUC_HIEN' ? 'selected' : '' ?>>Đang thực hiện</option>
                <option value="HOAN_THANH" <?= $status === 'HOAN_THANH' ? 'selected' : '' ?>>Hoàn thành</option>
                <option value="CHO_DUYET" <?= $status === 'CHO_DUYET' ? 'selected' : '' ?>>Chờ Trưởng phòng duyệt</option>
                <option value="CHO_BGD_DUYET" <?= $status === 'CHO_BGD_DUYET' ? 'selected' : '' ?>>Chờ BGD duyệt</option>
                <option value="YEU_CAU_CHINH_SUA" <?= $status === 'YEU_CAU_CHINH_SUA' ? 'selected' : '' ?>>Yêu cầu chỉnh sửa</option>
            </select>
        </div>

        <div class="lg:col-span-4 flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Xem trước dữ liệu</span>
            </button>

            <div class="flex items-center gap-2">
                <!-- Export Excel Button -->
                <a href="export.php?<?= http_build_query(array_merge($_GET, ['export_type' => 'excel'])) ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-100">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Xuất file Excel</span>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- PREVIEW DATA TABLE VIEW (PAGINATED 15 TASKS / PAGE) -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50">
        <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-table text-indigo-600"></i>
                <span>XEM TRƯỚC DỮ LIỆU BÁO CÁO</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Hiển thị <span class="font-bold text-indigo-600"><?= count($previewTasks) ?></span> / <span class="font-bold text-indigo-600"><?= $totalTasks ?></span> công việc (Trang <?= $page ?>/<?= $totalPages ?>)</p>
        </div>
        <div class="text-xs text-slate-500 font-medium">
            Phân trang 15 công việc / trang
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                    <th scope="col" class="py-3.5 px-3 text-center border-r border-slate-800 w-12">STT</th>
                    <th scope="col" class="py-3.5 px-4 border-r border-slate-800 min-w-[240px]">TÊN CÔNG VIỆC / NỘI DUNG</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-32 text-center">ĐƠN VỊ CHỦ TRÌ</th>
                    <th scope="col" class="py-3.5 px-4 border-r border-slate-800 min-w-[180px]">NGƯỜI THỰC HIỆN</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-32 text-center">TRẠNG THÁI</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-24 text-center">TIẾN ĐỘ</th>
                    <th scope="col" class="py-3.5 px-3 text-center w-28">HẠN CHÓT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (count($previewTasks) > 0): ?>
                    <?php
                    $stt = $offset + 1;
                    foreach ($previewTasks as $t):
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-3 text-center font-bold text-slate-500 border-r border-slate-100">
                                #<?= $stt++ ?>
                            </td>
                            <td class="py-3.5 px-4 border-r border-slate-100">
                                <?php
                                $titleColorClass = 'text-slate-900';
                                if ($t['priority'] === 'URGENT' || $t['priority'] === 'KHAN_CAP') {
                                    $titleColorClass = 'text-red-600 font-bold';
                                }
                                ?>
                                <div class="font-bold text-sm leading-snug <?= $titleColorClass ?>">
                                    <?= htmlspecialchars($t['title']) ?>
                                </div>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="text-slate-500 text-xs mt-1 line-clamp-2">
                                        <?= htmlspecialchars($t['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-100 font-semibold">
                                <?= renderDepartmentBadge($t['dept_code'], $t['dept_name'], $t['dept_color'], $t['dept_icon']) ?>
                            </td>
                            <td class="py-3.5 px-4 border-r border-slate-100">
                                <?= formatAssigneesHtml($t['assignees']) ?>
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-100">
                                <?= renderStatusBadge($t['status'], $t['due_date']) ?>
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-100 font-bold text-indigo-700">
                                <?= (int)$t['progress'] ?>%
                            </td>
                            <td class="py-3.5 px-3 text-center font-medium">
                                <?= $t['due_date'] ? date('d/m/Y', strtotime($t['due_date'])) : '---' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-inbox text-4xl mb-2 text-slate-300"></i>
                            <p class="font-medium">Không tìm thấy dữ liệu công việc nào cho điều kiện lọc đã chọn.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <div class="text-xs text-slate-500">
                Hiển thị <?= count($previewTasks) ?> / <?= $totalTasks ?> công việc
            </div>
            <div class="flex items-center space-x-1">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php
                    $q = $_GET;
                    $q['page'] = $p;
                    $url = 'export.php?' . http_build_query($q);
                    ?>
                    <a href="<?= $url ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $p === $page ? 'bg-indigo-600 text-white shadow' : 'bg-white hover:bg-slate-200 text-slate-700 border border-slate-200' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// SMART TIME FILTER REDESIGN (Combobox 1 & Combobox 2)
function generateTimeOptions(timeFrame) {
    const options = [];
    const now = new Date();

    if (timeFrame === 'week') {
        for (let i = 0; i < 10; i++) {
            const d = new Date();
            const day = d.getDay();
            const diffToMonday = d.getDate() - day + (day === 0 ? -6 : 1) - (i * 7);
            const monday = new Date(d.setDate(diffToMonday));
            monday.setHours(0, 0, 0, 0);

            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            sunday.setHours(23, 59, 59, 999);

            const targetDate = new Date(monday.valueOf());
            const dayNr = (monday.getDay() + 6) % 7;
            targetDate.setDate(targetDate.getDate() - dayNr + 3);
            const firstThursday = targetDate.valueOf();
            targetDate.setMonth(0, 1);
            if (targetDate.getDay() !== 4) {
                targetDate.setMonth(0, 1 + ((4 - targetDate.getDay() + 7) % 7));
            }
            const weekNum = 1 + Math.round((firstThursday - targetDate.valueOf()) / 604800000);

            const formatDdMm = (dt) => {
                const dd = String(dt.getDate()).padStart(2, '0');
                const mm = String(dt.getMonth() + 1).padStart(2, '0');
                return `${dd}/${mm}`;
            };

            const formatYmd = (dt) => {
                const yyyy = dt.getFullYear();
                const mm = String(dt.getMonth() + 1).padStart(2, '0');
                const dd = String(dt.getDate()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd}`;
            };

            const label = `Tuần ${weekNum} (${formatDdMm(monday)} - ${formatDdMm(sunday)})`;
            const val = `${formatYmd(monday)}|${formatYmd(sunday)}`;
            options.push({ label, val });
        }
    } else if (timeFrame === 'month') {
        for (let i = 0; i < 12; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const year = d.getFullYear();
            const month = d.getMonth() + 1;
            const lastDay = new Date(year, month, 0);

            const mm = String(month).padStart(2, '0');
            const lastDd = String(lastDay.getDate()).padStart(2, '0');

            const label = `Tháng ${month}/${year}`;
            const val = `${year}-${mm}-01|${year}-${mm}-${lastDd}`;
            options.push({ label, val });
        }
    } else if (timeFrame === 'quarter') {
        const curYear = now.getFullYear();
        const curQuarter = Math.floor(now.getMonth() / 3) + 1;

        for (let i = 0; i < 8; i++) {
            let q = curQuarter - i;
            let y = curYear;
            while (q <= 0) {
                q += 4;
                y -= 1;
            }

            let startMm, endMm, endDd;
            if (q === 1) { startMm = '01'; endMm = '03'; endDd = '31'; }
            else if (q === 2) { startMm = '04'; endMm = '06'; endDd = '30'; }
            else if (q === 3) { startMm = '07'; endMm = '09'; endDd = '30'; }
            else { startMm = '10'; endMm = '12'; endDd = '31'; }

            const label = `Quý ${q}/${y}`;
            const val = `${y}-${startMm}-01|${y}-${endMm}-${endDd}`;
            options.push({ label, val });
        }
    } else if (timeFrame === 'year') {
        const curYear = now.getFullYear();
        for (let i = 0; i < 2; i++) {
            const y = curYear - i;
            const label = `Năm ${y}`;
            const val = `${y}-01-01|${y}-12-31`;
            options.push({ label, val });
        }
    }

    return options;
}

function onTimeFrameChange(isInit = false) {
    const frameSelect = document.getElementById('filter_time_frame');
    const detailSelect = document.getElementById('filter_time_detail');
    if (!frameSelect || !detailSelect) return;

    const frameVal = frameSelect.value;
    detailSelect.innerHTML = '';

    if (!frameVal) {
        detailSelect.innerHTML = '<option value="">-- Tất cả --</option>';
        onTimeDetailChange();
        return;
    }

    const options = generateTimeOptions(frameVal);
    options.forEach((opt) => {
        const optionEl = document.getElementById('filter_time_detail');
        const optionElItem = document.createElement('option');
        optionElItem.value = opt.val;
        optionElItem.textContent = opt.label;
        optionEl.appendChild(optionElItem);
    });

    if (!isInit) {
        if (options.length > 0) {
            detailSelect.selectedIndex = 0;
        }
        onTimeDetailChange();
    }
}

function onTimeDetailChange() {
    const detailSelect = document.getElementById('filter_time_detail');
    const dateFromInput = document.getElementById('filter_date_from');
    const dateToInput = document.getElementById('filter_date_to');

    if (!detailSelect || !dateFromInput || !dateToInput) return;

    const val = detailSelect.value;
    if (val && val.includes('|')) {
        const parts = val.split('|');
        dateFromInput.value = parts[0] || '';
        dateToInput.value = parts[1] || '';
    } else {
        dateFromInput.value = '';
        dateToInput.value = '';
    }
}

function initSmartTimeFilter(savedTimeDetail, savedDateFrom, savedDateTo) {
    const frameSelect = document.getElementById('filter_time_frame');
    const detailSelect = document.getElementById('filter_time_detail');

    if (!frameSelect || !detailSelect) return;

    if (frameSelect.value) {
        onTimeFrameChange(true);

        const targetVal = savedTimeDetail || (savedDateFrom && savedDateTo ? `${savedDateFrom}|${savedDateTo}` : '');
        if (targetVal) {
            detailSelect.value = targetVal;
        }

        if (!detailSelect.value && detailSelect.options.length > 0) {
            detailSelect.selectedIndex = 0;
        }

        onTimeDetailChange();
    } else {
        detailSelect.innerHTML = '<option value="">-- Tất cả --</option>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initSmartTimeFilter(
        <?= json_encode($timeDetail) ?>,
        <?= json_encode($dateFrom) ?>,
        <?= json_encode($dateTo) ?>
    );
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
