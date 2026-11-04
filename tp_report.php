<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// 1. Phân quyền: Tính năng này chỉ dành cho phân hệ Trưởng phòng (và Phó phòng / Super Admin)
if (!isset($currentUser['role']) || !in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG', 'SUPER_ADMIN'])) {
    $_SESSION['flash_error'] = "Tính năng Báo cáo công việc chỉ dành cho phân hệ Trưởng phòng!";
    header("Location: index.php");
    exit;
}

// 2. Xác định phòng ban
$departmentsList = getDepartments($pdo);
$deptId = $currentUser['department_id'];

// Cho phép Super Admin chọn phòng ban nếu muốn xem
if ($currentUser['role'] === 'SUPER_ADMIN' && isset($_GET['dept_id'])) {
    $deptId = (int)$_GET['dept_id'];
}
if (!$deptId) {
    $deptId = 3; // Mặc định Phòng Hành chính - Tài vụ
}

// Lấy thông tin phòng ban
$currentDept = null;
foreach ($departmentsList as $d) {
    if ((int)$d['id'] === (int)$deptId) {
        $currentDept = $d;
        break;
    }
}
$deptName = $currentDept['name'] ?? 'Phòng Hành chính – Tài vụ';
$deptCode = $currentDept['code'] ?? 'HCTV';

// 3. Xử lý Tháng/Năm được chọn (mặc định tháng hiện tại, ví dụ 2026-10)
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

list($yearStr, $monthStr) = explode('-', $selectedMonth);
$yearInt = (int)$yearStr;
$monthInt = (int)$monthStr;
$monthDisplay = sprintf('%02d/%04d', $monthInt, $yearInt);

// Tính ngày bắt đầu và kết thúc của tháng
$startDate = date('Y-m-01', strtotime("{$selectedMonth}-01"));
$endDate = date('Y-m-t', strtotime("{$selectedMonth}-01"));

// Tính tháng trước / tháng sau để điều hướng nhanh
$prevMonth = date('Y-m', strtotime("{$selectedMonth}-01 -1 month"));
$nextMonth = date('Y-m', strtotime("{$selectedMonth}-01 +1 month"));

// 4. Truy vấn danh sách công việc của phòng trong tháng
$sqlTasks = "
    SELECT t.*, u.fullname AS creator_name
    FROM tasks t
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.department_id = ?
      AND (
          (t.due_date >= ? AND t.due_date <= ?)
          OR (t.start_date >= ? AND t.start_date <= ?)
          OR (t.created_at >= ? AND t.created_at <= ?)
      )
    ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id ASC
";
$stmtTasks = $pdo->prepare($sqlTasks);
$stmtTasks->execute([
    $deptId,
    $startDate,
    $endDate,
    $startDate,
    $endDate,
    $startDate . ' 00:00:00',
    $endDate . ' 23:59:59'
]);
$rawTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

// Nạp danh sách người phụ trách (assignees) và đính kèm (attachments) cho từng task
$reportTasks = [];
foreach ($rawTasks as $t) {
    $tId = (int)$t['id'];
    $assignees = getTaskAssignees($pdo, $tId);
    $attachments = getTaskAttachments($pdo, $tId);

    // Xử lý Người phụ trách chính & hỗ trợ
    $mainNames = [];
    $coopNames = [];
    foreach ($assignees as $a) {
        if ($a['role_type'] === 'MAIN') {
            $mainNames[] = $a['fullname'];
        } else {
            $coopNames[] = $a['fullname'];
        }
    }

    $t['main_names'] = $mainNames;
    $t['coop_names'] = $coopNames;
    $t['attachments'] = $attachments;
    $reportTasks[] = $t;
}

// 5. XUẤT FILE WORD NẾU CÓ THAM SỐ export=word
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $filename = 'Nhat_ky_cong_viec_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deptCode) . '_Thang_' . str_replace('-', '_', $selectedMonth) . '.doc';

    header('Content-Type: application/vnd.ms-word; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');

    ?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset='utf-8'>
        <title>Nhật Ký Công Việc - <?= htmlspecialchars($deptName) ?> - Tháng <?= htmlspecialchars($monthDisplay) ?></title>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
                <w:DoNotOptimizeForBrowser/>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page Section1 {
                size: 841.95pt 595.35pt; /* A4 Landscape */
                mso-page-orientation: landscape;
                margin: 2cm 1.5cm 2cm 1.5cm;
                mso-header-margin: 36pt;
                mso-footer-margin: 36pt;
                mso-paper-source: 0;
            }
            div.Section1 { page: Section1; }
            body {
                font-family: 'Times New Roman', Times, serif;
                font-size: 13pt;
                line-height: 1.3;
                color: #000;
            }
            .doc-title {
                text-align: center;
                font-weight: bold;
                font-size: 14pt;
                text-transform: uppercase;
                margin-bottom: 4pt;
            }
            .doc-subtitle {
                text-align: center;
                font-weight: bold;
                font-size: 13pt;
                margin-bottom: 16pt;
            }
            table.report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1pt solid #000000;
                margin-top: 10pt;
            }
            table.report-table th, table.report-table td {
                border: 1pt solid #000000;
                padding: 6pt 6pt;
                vertical-align: top;
                font-size: 11pt;
            }
            table.report-table th {
                font-weight: bold;
                text-align: center;
                background-color: #f2f2f2;
            }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .font-bold { font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="Section1">
            <div class="doc-title">NHẬT KÝ CÔNG VIỆC CỦA <?= htmlspecialchars(mb_strtoupper($deptName, 'UTF-8')) ?></div>
            <div class="doc-subtitle">THÁNG <?= htmlspecialchars($monthDisplay) ?></div>

            <table class="report-table" border="1" cellspacing="0" cellpadding="5">
                <thead>
                    <tr>
                        <th style="width: 5%;">TT</th>
                        <th style="width: 32%;">Công việc</th>
                        <th style="width: 11%;">Thời gian</th>
                        <th style="width: 16%;">Người phụ trách/hỗ trợ</th>
                        <th style="width: 13%;">Tiến độ công việc</th>
                        <th style="width: 11%;">Kết quả</th>
                        <th style="width: 12%;">Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportTasks)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 15pt; font-style: italic;">
                                Không có dữ liệu công việc trong tháng <?= htmlspecialchars($monthDisplay) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $index = 1; foreach ($reportTasks as $task): ?>
                            <?php
                            // Cột Thời gian: hạn chót hoàn thành
                            $timeStr = $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '';

                            // Cột Người phụ trách / hỗ trợ
                            $assigneeParts = [];
                            if (!empty($task['main_names'])) {
                                $assigneeParts[] = implode(', ', $task['main_names']);
                            }
                            if (!empty($task['coop_names'])) {
                                $assigneeParts[] = 'Hỗ trợ: ' . implode(', ', $task['coop_names']);
                            }
                            $assigneeDisplay = implode("<br>", $assigneeParts);

                            // Cột Tiến độ: Lấy theo tiến độ (100% / hoàn thành => "Đúng thời hạn")
                            $progressVal = (int)$task['progress'];
                            if ($progressVal >= 100 || $task['status'] === 'HOAN_THANH') {
                                $progressDisplay = 'Đúng thời hạn';
                            } elseif ($progressVal > 0) {
                                $progressDisplay = 'Đang thực hiện (' . $progressVal . '%)';
                            } else {
                                $progressDisplay = 'Chưa thực hiện (0%)';
                            }

                            // Cột Kết quả: Lấy nội dung minh chứng
                            $proofParts = [];
                            if (!empty($task['proof_link'])) {
                                $proofParts[] = htmlspecialchars($task['proof_link']);
                            }
                            if (!empty($task['attachments'])) {
                                foreach ($task['attachments'] as $att) {
                                    $proofParts[] = htmlspecialchars($att['file_name']);
                                }
                            }
                            $proofDisplay = implode("<br>", $proofParts);

                            // Cột Ghi chú: Nội dung ý kiến của Trưởng phòng/BGĐ và Ghi chú của nhân viên
                            $noteParts = [];
                            if (!empty($task['leader_comments'])) {
                                $noteParts[] = 'Ý kiến CĐ: ' . htmlspecialchars($task['leader_comments']);
                            }
                            if (!empty($task['notes'])) {
                                $noteParts[] = 'Ghi chú NV: ' . htmlspecialchars($task['notes']);
                            }
                            $noteDisplay = implode('<br>', $noteParts);
                            ?>
                            <tr>
                                <td class="text-center"><?= $index++ ?></td>
                                <td class="text-left"><?= nl2br(htmlspecialchars($task['title'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($timeStr) ?></td>
                                <td class="text-left"><?= $assigneeDisplay ?></td>
                                <td class="text-center"><?= htmlspecialchars($progressDisplay) ?></td>
                                <td class="text-left"><?= $proofDisplay ?></td>
                                <td class="text-left"><?= nl2br($noteDisplay) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 6. GIAO DIỆN WEB (PREVIEW TRỰC QUAN TRÊN TRÌNH DUYỆT)
define('PAGE_TITLE', 'Báo Cáo Nhật Ký Công Việc - ' . $deptName);
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Breadcrumb & Page Header -->
    <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-blue-800 rounded-2xl p-6 mb-6 text-white shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-full text-xs font-semibold text-blue-200 backdrop-blur-md mb-2">
                <i class="fa-solid fa-user-tie text-amber-400"></i>
                <span>Phân hệ Trưởng Phòng / Lãnh Đạo Đơn Vị</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight">
                Nhật Ký Công Việc - <?= htmlspecialchars($deptName) ?>
            </h1>
            <p class="text-xs text-blue-200 mt-1">
                Báo cáo tổng hợp tiến độ và kết quả công việc tháng, xuất file Microsoft Word (.doc) để chỉnh sửa.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-3.5 py-2 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Trở về Trang chủ</span>
            </a>
            <a href="tp_report.php?month=<?= urlencode($selectedMonth) ?>&dept_id=<?= (int)$deptId ?>&export=word" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-2 shadow-lg shadow-blue-950/50">
                <i class="fa-solid fa-file-word text-sm text-blue-100"></i>
                <span>Xuất File Word (.doc)</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
        <form method="GET" action="tp_report.php" class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Chọn tháng -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-calendar-days text-indigo-600 mr-1"></i>Chọn Tháng Báo Cáo
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="p-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <i class="fa-solid fa-filter"></i>
                            <span>Xem Báo Cáo</span>
                        </button>
                    </div>
                </div>

                <!-- Điều hướng tháng nhanh -->
                <div class="hidden sm:flex items-center gap-1 pt-5">
                    <a href="tp_report.php?month=<?= $prevMonth ?>&dept_id=<?= (int)$deptId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition" title="Tháng trước">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Tháng trước
                    </a>
                    <a href="tp_report.php?month=<?= date('Y-m') ?>&dept_id=<?= (int)$deptId ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition" title="Tháng hiện tại">
                        Tháng này
                    </a>
                    <a href="tp_report.php?month=<?= $nextMonth ?>&dept_id=<?= (int)$deptId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition" title="Tháng kế tiếp">
                        Tháng sau<i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                </div>

                <!-- Bộ lọc phòng ban cho Super Admin -->
                <?php if ($currentUser['role'] === 'SUPER_ADMIN'): ?>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            <i class="fa-solid fa-building text-indigo-600 mr-1"></i>Đơn Vị Phòng Ban
                        </label>
                        <select name="dept_id" onchange="this.form.submit()" class="p-2 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <?php foreach ($departmentsList as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= (int)$d['id'] === (int)$deptId ? 'selected' : '' ?>>
                                    [<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action buttons: Export & Print -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-2 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 border border-slate-200">
                    <i class="fa-solid fa-print"></i>
                    <span>In Nhật Ký</span>
                </button>
                <a href="tp_report.php?month=<?= urlencode($selectedMonth) ?>&dept_id=<?= (int)$deptId ?>&export=word" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-file-word text-sm"></i>
                    <span>Xuất File Word (.doc)</span>
                </a>
            </div>
        </form>
    </div>

    <!-- PREVIEW BẢNG NHẬT KÝ (MÔ PHỎNG VĂN BẢN WORD) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 md:p-8">
        <div class="text-center mb-6">
            <h2 class="text-lg md:text-xl font-extrabold text-slate-900 uppercase tracking-wide">
                NHẬT KÝ CÔNG VIỆC CỦA <?= htmlspecialchars(mb_strtoupper($deptName, 'UTF-8')) ?>
            </h2>
            <div class="text-sm md:text-base font-bold text-slate-800 mt-1 uppercase">
                THÁNG <?= htmlspecialchars($monthDisplay) ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-slate-900 text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-100 text-slate-900 font-bold border-b border-slate-900">
                        <th class="border border-slate-900 py-3 px-2 w-12 text-center">TT</th>
                        <th class="border border-slate-900 py-3 px-4 text-center min-w-[280px]">Công việc</th>
                        <th class="border border-slate-900 py-3 px-3 w-28 text-center">Thời gian</th>
                        <th class="border border-slate-900 py-3 px-4 w-44 text-center">Người phụ trách/hỗ trợ</th>
                        <th class="border border-slate-900 py-3 px-3 w-36 text-center">Tiến độ công việc</th>
                        <th class="border border-slate-900 py-3 px-3 w-40 text-center">Kết quả</th>
                        <th class="border border-slate-900 py-3 px-4 w-44 text-center">Ghi chú</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-900 text-slate-800">
                    <?php if (empty($reportTasks)): ?>
                        <tr>
                            <td colspan="7" class="border border-slate-900 py-10 text-center text-slate-500 italic">
                                <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                                Không tìm thấy công việc nào của đơn vị trong tháng <?= htmlspecialchars($monthDisplay) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $index = 1; foreach ($reportTasks as $task): ?>
                            <?php
                            $timeStr = $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '';

                            $assigneeParts = [];
                            if (!empty($task['main_names'])) {
                                $assigneeParts[] = '<span class="font-semibold text-slate-900">' . htmlspecialchars(implode(', ', $task['main_names'])) . '</span>';
                            }
                            if (!empty($task['coop_names'])) {
                                $assigneeParts[] = '<span class="text-slate-500 text-xs">(Hỗ trợ: ' . htmlspecialchars(implode(', ', $task['coop_names'])) . ')</span>';
                            }
                            $assigneeHtml = implode("<br>", $assigneeParts);

                            $progressVal = (int)$task['progress'];
                            if ($progressVal >= 100 || $task['status'] === 'HOAN_THANH') {
                                $progressBadge = '<span class="text-emerald-700 font-bold">Đúng thời hạn</span>';
                            } elseif ($progressVal > 0) {
                                $progressBadge = '<span class="text-indigo-700 font-semibold">Đang thực hiện (' . $progressVal . '%)</span>';
                            } else {
                                $progressBadge = '<span class="text-slate-500">Chưa thực hiện (0%)</span>';
                            }

                            $proofParts = [];
                            if (!empty($task['proof_link'])) {
                                $proofParts[] = '<a href="' . htmlspecialchars($task['proof_link']) . '" target="_blank" class="text-blue-600 hover:underline break-all inline-flex items-center gap-1"><i class="fa-solid fa-link text-xs"></i><span>Minh chứng</span></a>';
                            }
                            if (!empty($task['attachments'])) {
                                foreach ($task['attachments'] as $att) {
                                    $proofParts[] = '<span class="inline-flex items-center gap-1 text-slate-600 text-xs"><i class="fa-solid fa-paperclip text-slate-400"></i>' . htmlspecialchars($att['file_name']) . '</span>';
                                }
                            }
                            $proofHtml = implode("<br>", $proofParts);

                            $noteParts = [];
                            if (!empty($task['leader_comments'])) {
                                $noteParts[] = '<b>Ý kiến CĐ:</b> ' . htmlspecialchars($task['leader_comments']);
                            }
                            if (!empty($task['notes'])) {
                                $noteParts[] = '<b>Ghi chú NV:</b> ' . htmlspecialchars($task['notes']);
                            }
                            $noteHtml = implode('<br>', $noteParts);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="border border-slate-900 py-3 px-2 text-center font-bold text-slate-700"><?= $index++ ?></td>
                                <td class="border border-slate-900 py-3 px-4 font-medium text-slate-900 leading-relaxed">
                                    <?= nl2br(htmlspecialchars($task['title'])) ?>
                                    <?php if (!empty($task['description'])): ?>
                                        <div class="text-xs text-slate-500 mt-1 italic"><?= nl2br(htmlspecialchars($task['description'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="border border-slate-900 py-3 px-3 text-center whitespace-nowrap font-medium text-slate-700">
                                    <?= htmlspecialchars($timeStr) ?>
                                </td>
                                <td class="border border-slate-900 py-3 px-4 text-left leading-normal">
                                    <?= $assigneeHtml ?>
                                </td>
                                <td class="border border-slate-900 py-3 px-3 text-center leading-normal">
                                    <?= $progressBadge ?>
                                </td>
                                <td class="border border-slate-900 py-3 px-3 text-left leading-normal">
                                    <?= $proofHtml ?>
                                </td>
                                <td class="border border-slate-900 py-3 px-4 text-left text-xs leading-relaxed text-slate-700">
                                    <?= $noteHtml ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-3 pt-4 border-t border-slate-100">
            <div>
                Tổng số công việc ghi nhận trong tháng: <strong class="text-slate-800"><?= count($reportTasks) ?></strong>
            </div>
            <div>
                <a href="tp_report.php?month=<?= urlencode($selectedMonth) ?>&dept_id=<?= (int)$deptId ?>&export=word" class="text-blue-600 hover:text-blue-800 font-bold inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-file-arrow-down"></i>
                    <span>Tải về file Word (.doc) để chỉnh sửa và in ấn</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
