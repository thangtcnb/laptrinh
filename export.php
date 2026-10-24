<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

requireLogin();
$currentUser = getCurrentUser($pdo);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
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

// Internal BGD Security Restriction for Export
if (!$currentUser || !in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
    $where[] = "(t.department_id != 1 AND t.created_by NOT IN (SELECT id FROM users WHERE role = 'BGD'))";
}

if (!empty($dateFrom)) {
    $where[] = "(t.start_date >= ? OR t.due_date >= ?)";
    $params[] = $dateFrom;
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where[] = "(t.start_date <= ? OR t.due_date <= ?)";
    $params[] = $dateTo;
    $params[] = $dateTo;
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

// ---------------- Handle Standard Excel (.xlsx) Download ----------------
if ($exportType === 'excel') {
    while (ob_get_level()) {
        ob_end_clean();
    }

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
        'CHO_XAC_NHAN' => 'Chờ NV xác nhận',
        'TU_CHOI_CHO_TP_XAC_NHAN' => 'Từ chối - Chờ TP xác nhận',
        'DANG_THUC_HIEN' => 'Đang thực hiện',
        'HOAN_THANH' => 'Hoàn thành'
    ];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Bao Cao Cong Viec');

    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

    // Header Title
    $sheet->setCellValue('A1', 'TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI');
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'BÁO CÁO KẾT QUẢ THỰC HIỆN CÔNG VIỆC');
    $sheet->mergeCells('A3:F3');
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $dateStr = "Từ ngày " . date('d/m/Y', strtotime($dateFrom)) . " đến ngày " . date('d/m/Y', strtotime($dateTo));
    $sheet->setCellValue('A4', $dateStr);
    $sheet->mergeCells('A4:F4');
    $sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(10);
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $currentRow = 6;

    // Closure to generate table
    $buildTable = function($sheet, &$row, $bannerText, $tasks) use ($statusMap) {
        // Section Banner
        $sheet->setCellValue("A{$row}", $bannerText);
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
        $sheet->getStyle("A{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        // Table Header
        $headers = [
            'A' => 'STT',
            'B' => '1. Người phụ trách (Chính & Phối hợp)',
            'C' => '2. Tên công việc (Nội dung)',
            'D' => '3. Thời gian thực hiện',
            'E' => '4. Trạng thái',
            'F' => '5. Minh chứng (Link Google Drive)'
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$row}", $text);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        if (!empty($tasks)) {
            $stt = 1;
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

                $content = $t['title'];
                if (!empty($t['description'])) {
                    $content .= "\n" . $t['description'];
                }

                $startDateFmt = !empty($t['start_date']) ? date('d/m/Y', strtotime($t['start_date'])) : '';
                $dueDateFmt = !empty($t['due_date']) ? date('d/m/Y', strtotime($t['due_date'])) : '';
                if (!empty($startDateFmt) && !empty($dueDateFmt)) {
                    $timeStr = $startDateFmt . ' - ' . $dueDateFmt;
                } elseif (!empty($dueDateFmt)) {
                    $timeStr = $dueDateFmt;
                } else {
                    $timeStr = '---';
                }

                $statusText = $statusMap[$t['status']] ?? $t['status'];
                $proofLink = $t['proof_link'] ?? '';

                $sheet->setCellValue("A{$row}", $stt++);
                $sheet->setCellValue("B{$row}", $assigneeText);
                $sheet->setCellValue("C{$row}", $content);
                $sheet->setCellValue("D{$row}", $timeStr);
                $sheet->setCellValue("E{$row}", $statusText);
                $sheet->setCellValue("F{$row}", $proofLink);

                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

                $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CCCCCC'));

                $row++;
            }
        } else {
            $sheet->setCellValue("A{$row}", "Không có công việc nào trong danh mục này.");
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('888888'));
            $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CCCCCC'));
            $row++;
        }

        $row += 2;
    };

    $buildTable($sheet, $currentRow, "Bảng 1: Công việc chính phụ trách", $part1Tasks);
    $buildTable($sheet, $currentRow, "Bảng 2: Công việc phối hợp", $part2Tasks);

    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(32);
    $sheet->getColumnDimension('C')->setWidth(45);
    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(22);
    $sheet->getColumnDimension('F')->setWidth(35);

    $filename = "Bao_Cao_Cong_Viec_UFM_Quang_Ngai.xlsx";

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0");
    header("Pragma: public");
    header("Expires: 0");

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
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
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Từ ngày</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Đến ngày</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500">
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
                                <div class="font-bold text-slate-900 text-sm leading-snug">
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
                                <?= renderStatusBadge($t['status']) ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
