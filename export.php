<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

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

if ($currentUser['role'] === 'NHAN_VIEN') {
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
    $where[] = "t.department_id = ?";
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
$tasks = $stmt->fetchAll();

foreach ($tasks as &$t) {
    $t['assignees'] = getTaskAssignees($pdo, $t['id']);
}

// ---------------- Handle Excel (.xls) Download ----------------
if ($exportType === 'excel') {
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "Bao_Cao_Cong_Viec_UFM_Quang_Ngai_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style>
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
            th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; padding: 10px; border: 1px solid #000; text-align: center; }
            td { padding: 8px; border: 1px solid #ccc; vertical-align: top; }
            .header-title { font-size: 16px; font-weight: bold; text-align: center; color: #1e3a8a; }
            .sub-title { font-size: 13px; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header-title">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING</div>
        <div class="sub-title">PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</div>
        <div style="text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0;">
            BÁO CÁO KẾT QUẢ THỰC HIỆN CÔNG VIỆC
        </div>
        <div style="text-align: center; font-style: italic; margin-bottom: 15px;">
            Từ ngày <?= date('d/m/Y', strtotime($dateFrom)) ?> đến ngày <?= date('d/m/Y', strtotime($dateTo)) ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">1. Người phụ trách (Chính & Phối hợp)</th>
                    <th style="width: 30%;">2. Tên công việc (Nội dung)</th>
                    <th style="width: 15%;">3. Thời gian thực hiện</th>
                    <th style="width: 15%;">4. Trạng thái</th>
                    <th style="width: 15%;">5. Minh chứng (Link Google Drive)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $statusMap = [
                    'CHO_DUYET' => 'Chờ Trưởng phòng duyệt',
                    'CHO_BGD_DUYET' => 'Chờ BGD duyệt',
                    'DANG_THUC_HIEN' => 'Đang thực hiện',
                    'HOAN_THANH' => 'Hoàn thành'
                ];

                foreach ($tasks as $t):
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
                ?>
                    <tr>
                        <td><?= htmlspecialchars($assigneeText) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($t['title']) ?></strong>
                            <?php if (!empty($t['description'])): ?>
                                <br/><span style="color: #555; font-size: 11px;"><?= htmlspecialchars($t['description']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?= htmlspecialchars($timeStr) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($statusText) ?></td>
                        <td><?= htmlspecialchars($t['proof_link'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

define('PAGE_TITLE', 'Xuất Báo Cáo & In Dữ Liệu');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Export Controls -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6 no-print">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
            <i class="fa-solid fa-file-export text-sm"></i>
        </div>
        <h3 class="font-bold text-slate-800 text-base">Tùy Chọn Xuất Báo Cáo & In Ấn</h3>
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
                <?php if ($currentUser['role'] === 'NHAN_VIEN'): ?>
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
                <i class="fa-solid fa-filter"></i>
                <span>Xem trước dữ liệu</span>
            </button>

            <div class="flex items-center gap-2">
                <!-- Export Excel Button -->
                <a href="export.php?<?= http_build_query(array_merge($_GET, ['export_type' => 'excel'])) ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-100">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Xuất file Excel (.xlsx)</span>
                </a>

                <!-- Print PDF Button -->
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-indigo-100">
                    <i class="fa-solid fa-print"></i>
                    <span>In / Xuất PDF</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- OFFICIAL REPORT PRINT PREVIEW VIEW -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">

    <!-- Official Header for Print -->
    <div class="grid grid-cols-2 text-center text-xs uppercase font-bold text-slate-800 mb-6 pb-4 border-b-2 border-slate-900">
        <div>
            <div>TRƯỜNG ĐẠI HỌC TÀI CHÍNH - MARKETING</div>
            <div class="font-extrabold text-indigo-900 text-sm mt-0.5">PHÂN HIỆU TẠI TỈNH QUẢNG NGÃI</div>
            <div class="text-[10px] font-normal tracking-normal text-slate-500 mt-1">***</div>
        </div>
        <div>
            <div>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div class="font-bold text-slate-900 mt-0.5">Độc lập - Tự do - Hạnh phúc</div>
            <div class="text-[10px] font-normal tracking-normal text-slate-500 mt-1">Quảng Ngãi, ngày <?= date('d') ?> tháng <?= date('m') ?> năm <?= date('Y') ?></div>
        </div>
    </div>

    <!-- Title -->
    <div class="text-center my-6">
        <h1 class="text-xl font-extrabold text-slate-900 uppercase tracking-wide">
            BÁO CÁO KẾT QUẢ VÀ TIẾN ĐỘ THỰC HIỆN CÔNG VIỆC
        </h1>
        <p class="text-xs text-slate-600 font-medium italic mt-1">
            Giai đoạn: Từ ngày <?= date('d/m/Y', strtotime($dateFrom)) ?> đến ngày <?= date('d/m/Y', strtotime($dateTo)) ?>
        </p>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto mb-8">
        <table class="w-full text-left border-collapse border border-slate-900 text-xs">
            <thead>
                <tr class="bg-slate-100 font-bold uppercase text-slate-900 text-center">
                    <th class="p-2 border border-slate-900 w-10">STT</th>
                    <th class="p-2 border border-slate-900">TÊN CÔNG VIỆC / NỘI DUNG</th>
                    <th class="p-2 border border-slate-900 w-28">ĐƠN VỊ CHỦ TRÌ</th>
                    <th class="p-2 border border-slate-900 w-44">NGƯỜI THỰC HIỆN</th>
                    <th class="p-2 border border-slate-900 w-24">TRẠNG THÁI</th>
                    <th class="p-2 border border-slate-900 w-24">TIẾN ĐỘ</th>
                    <th class="p-2 border border-slate-900 w-24">HẠN CHÓT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tasks) > 0): ?>
                    <?php
                    $idx = 1;
                    foreach ($tasks as $t):
                    ?>
                        <tr>
                            <td class="p-2 border border-slate-900 text-center font-bold"><?= $idx++ ?></td>
                            <td class="p-2 border border-slate-900">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></div>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="text-[11px] text-slate-600 italic mt-0.5"><?= htmlspecialchars($t['description']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($t['proof_link'])): ?>
                                    <div class="text-[10px] text-emerald-800 mt-1 font-mono">
                                        Drive: <?= htmlspecialchars($t['proof_link']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 border border-slate-900 text-center font-semibold">
                                <?= htmlspecialchars($t['dept_code']) ?>
                            </td>
                            <td class="p-2 border border-slate-900">
                                <?= formatAssigneesHtml($t['assignees']) ?>
                            </td>
                            <td class="p-2 border border-slate-900 text-center font-semibold">
                                <?= htmlspecialchars($t['status']) ?>
                            </td>
                            <td class="p-2 border border-slate-900 text-center font-bold">
                                <?= (int)$t['progress'] ?>%
                            </td>
                            <td class="p-2 border border-slate-900 text-center">
                                <?= $t['due_date'] ? date('d/m/Y', strtotime($t['due_date'])) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500 italic">Không có dữ liệu công việc trong giai đoạn báo cáo này.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Official Signatures Block for Print -->
    <div class="grid grid-cols-3 text-center text-xs font-bold text-slate-900 pt-6">
        <div>
            <div>NGƯỜI LẬP BÁO CÁO</div>
            <div class="text-[11px] font-normal italic text-slate-500 mt-0.5">(Ký và ghi rõ họ tên)</div>
            <div class="mt-16 font-bold"><?= htmlspecialchars($currentUser['fullname']) ?></div>
        </div>
        <div>
            <div>TRƯỞNG PHÒNG / ĐƠN VỊ</div>
            <div class="text-[11px] font-normal italic text-slate-500 mt-0.5">(Ký và ghi rõ họ tên)</div>
            <div class="mt-16 font-bold">Phạm Hoài Nam</div>
        </div>
        <div>
            <div>BAN GIÁM ĐỐC PHÂN HIỆU</div>
            <div class="text-[11px] font-normal italic text-slate-500 mt-0.5">(Ký duyệt)</div>
            <div class="mt-16 font-bold">Bùi Thị Yến Linh</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
