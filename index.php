<?php
define('PAGE_TITLE', 'Bảng Danh Sách Công Việc');
require_once __DIR__ . '/includes/header.php';

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Filters parameters - Default homepage view based on user role:
// For TRUONG_PHONG: Default to their department ID ("DANH SÁCH CÔNG VIỆC PHÒNG [TÊN PHÒNG]")
// For BGD and SUPER_ADMIN (thangtcnb@gmail.com): Default to '' ("TẤT CẢ CÔNG VIỆC PHÂN HIỆU QUẢNG NGÃI")
// For others: Default to 'MY_TASKS'
$defaultDeptFilter = 'MY_TASKS';
if (isset($currentUser['role'])) {
    if ($currentUser['role'] === 'TRUONG_PHONG') {
        $defaultDeptFilter = $currentUser['department_id'];
    } elseif (in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN']) || ($currentUser['email'] ?? '') === 'thangtcnb@gmail.com') {
        $defaultDeptFilter = '';
    }
}

$timeFrame = $_GET['time_frame'] ?? '';
$timeDetail = $_GET['time_detail'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if (!empty($timeDetail) && strpos($timeDetail, '|') !== false) {
    list($df, $dt) = explode('|', $timeDetail, 2);
    if (empty($dateFrom)) $dateFrom = $df;
    if (empty($dateTo)) $dateTo = $dt;
}

$filters = [
    'time_frame' => $timeFrame,
    'time_detail' => $timeDetail,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'dept_filter' => $_GET['dept_filter'] ?? $defaultDeptFilter,
    'status' => $_GET['status'] ?? ''
];

// Backend Date Filter Validation: "Từ ngày" must be <= "Đến ngày"
if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
    if (strtotime($filters['date_from']) > strtotime($filters['date_to'])) {
        $flashError = 'Lỗi lọc dữ liệu: Ngày bắt đầu (Từ ngày) phải nhỏ hơn hoặc bằng Ngày kết thúc (Đến ngày)!';
        // Reset dates so invalid query is not executed
        $filters['date_from'] = '';
        $filters['date_to'] = '';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch tasks
$result = getTasksFiltered($pdo, $filters, $limit, $offset, $currentUser);
$tasks = $result['data'];
$totalTasks = $result['total'];
$totalPages = $result['total_pages'];

// Data for dropdowns
$departmentsList = getDepartments($pdo);
$usersList = getUsers($pdo); // All users for filters & admin views
$assignableUsers = getUsers($pdo, null, true); // Exclude Super Admin from assignments
$deptEmployeesForMain = ($currentUser['role'] === 'TRUONG_PHONG')
    ? getUsers($pdo, $currentUser['department_id'], true)
    : $assignableUsers;
$coopUsersList = array_filter($assignableUsers, fn($u) => $u['role'] !== 'BGD'); // Exclude BGD from COOP

// Check if current logged-in user has pending assigned tasks (CHO_TIEP_NHAN / CHO_XAC_NHAN where user has not accepted yet)
$pendingAssignedTasks = [];
if ($currentUser) {
    $stmtPending = $pdo->prepare("
        SELECT DISTINCT t.*, d.name AS dept_name, u.fullname AS assigner_name
        FROM tasks t
        JOIN departments d ON t.department_id = d.id
        JOIN users u ON t.created_by = u.id
        JOIN task_assignees ta ON t.id = ta.task_id
        WHERE ta.user_id = ?
          AND (ta.is_accepted = 0 OR ta.is_accepted IS NULL)
          AND t.status IN ('CHO_XAC_NHAN', 'CHO_TIEP_NHAN')
        ORDER BY t.id DESC
    ");
    $stmtPending->execute([$currentUser['id']]);
    $pendingAssignedTasks = $stmtPending->fetchAll();

    foreach ($pendingAssignedTasks as &$pt) {
        $pt['attachments'] = getTaskAttachments($pdo, $pt['id']);
    }
}

// Check if current logged-in user (Trưởng/Phó phòng) has pending approval tasks (CHO_DUYET) from their department
$pendingApprovalTasksForDept = [];
if ($currentUser['role'] === 'TRUONG_PHONG') {
    $stmtDeptPending = $pdo->prepare("
        SELECT t.*, u.fullname AS creator_name, d.name AS dept_name, d.code AS dept_code
        FROM tasks t
        JOIN users u ON t.created_by = u.id
        JOIN departments d ON t.department_id = d.id
        WHERE t.department_id = ? AND t.status = 'CHO_DUYET'
        ORDER BY t.id DESC
    ");
    $stmtDeptPending->execute([$currentUser['department_id']]);
    $pendingApprovalTasksForDept = $stmtDeptPending->fetchAll();
}

// Check if current logged-in user (Ban Giám đốc) has pending approval tasks (CHO_BGD_DUYET)
$pendingApprovalTasksForBgd = [];
if ($currentUser['role'] === 'BGD') {
    $stmtBgdPending = $pdo->prepare("
        SELECT t.*, d.name AS dept_name, d.code AS dept_code, u.fullname AS creator_name
        FROM tasks t
        JOIN departments d ON t.department_id = d.id
        JOIN users u ON t.created_by = u.id
        WHERE t.status = 'CHO_BGD_DUYET'
        ORDER BY t.id DESC
    ");
    $stmtBgdPending->execute();
    $pendingApprovalTasksForBgd = $stmtBgdPending->fetchAll();
}

// Unified list of pending tasks waiting for batch approval depending on user role
$pendingApprovalTasks = [];
if ($currentUser['role'] === 'BGD') {
    $pendingApprovalTasks = $pendingApprovalTasksForBgd;
} elseif ($currentUser['role'] === 'TRUONG_PHONG') {
    $pendingApprovalTasks = $pendingApprovalTasksForDept;
} elseif ($currentUser['role'] === 'SUPER_ADMIN') {
    $stmtAdminPending = $pdo->prepare("
        SELECT t.*, d.name AS dept_name, d.code AS dept_code, u.fullname AS creator_name
        FROM tasks t
        JOIN departments d ON t.department_id = d.id
        JOIN users u ON t.created_by = u.id
        WHERE t.status IN ('CHO_DUYET', 'CHO_BGD_DUYET')
        ORDER BY t.id DESC
    ");
    $stmtAdminPending->execute();
    $pendingApprovalTasks = $stmtAdminPending->fetchAll();
}

// Fetch KPI Stats for Stats Bar
$kpiStats = getKpiStats($pdo, $currentUser);
?>

<!-- WORKFLOW NOTIFICATION BANNER FOR BAN GIÁM ĐỐC: PENDING APPROVAL TASKS -->
<?php if (count($pendingApprovalTasksForBgd) > 0): ?>
    <div class="mb-6 bg-gradient-to-r from-amber-600 via-rose-700 to-purple-900 rounded-2xl p-5 text-white shadow-xl relative overflow-hidden border border-amber-300">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 text-amber-300">
                    <i class="fa-solid fa-crown text-2xl animate-pulse"></i>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-400/30 text-amber-200 text-xs font-bold uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-bell"></i> THÔNG BÁO BAN GIÁM ĐỐC PHÊ DUYỆT
                    </span>
                    <h3 class="text-base md:text-lg font-bold text-white">
                        Kính trình Ban Giám đốc: Có <span class="underline underline-offset-4 decoration-2 decoration-amber-300 font-extrabold text-amber-200"><?= count($pendingApprovalTasksForBgd) ?> công việc</span> đang chờ Ban Giám đốc phê duyệt!
                    </h3>
                    <p class="text-xs text-amber-100 mt-0.5">Vui lòng xem chi tiết nội dung và thực hiện Phê duyệt hàng loạt hoặc Cho ý kiến chỉ đạo.</p>
                </div>
            </div>

            <div>
                <button onclick="openBatchApprovalModal()" class="bg-white text-purple-950 hover:bg-amber-50 font-bold px-5 py-2.5 rounded-xl text-xs shadow-lg transition flex items-center gap-2 transform hover:scale-105">
                    <i class="fa-solid fa-clipboard-check text-purple-700 text-sm"></i>
                    <span>Xem danh sách trình BGD duyệt</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- WORKFLOW NOTIFICATION BANNER FOR TRƯỞNG/PHÓ PHÒNG: PENDING APPROVAL TASKS REGISTERED BY EMPLOYEES -->
<?php if (count($pendingApprovalTasksForDept) > 0): ?>
    <div class="mb-6 bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-900 rounded-2xl p-5 text-white shadow-xl relative overflow-hidden border border-indigo-400">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 text-amber-300">
                    <i class="fa-solid fa-clipboard-question text-2xl animate-pulse"></i>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-400/30 text-amber-200 text-xs font-bold uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-bell"></i> THÔNG BÁO CÔNG VIỆC CẦN DUYỆT
                    </span>
                    <h3 class="text-base md:text-lg font-bold text-white">
                        Phòng bạn có <span class="underline underline-offset-4 decoration-2 decoration-amber-300 font-extrabold text-amber-200"><?= count($pendingApprovalTasksForDept) ?> công việc mới</span> do nhân viên đăng ký đang chờ phê duyệt!
                    </h3>
                    <p class="text-xs text-indigo-100 mt-0.5">Vui lòng xem chi tiết nội dung và thực hiện Phê duyệt hàng loạt hoặc Cho ý kiến chỉ đạo.</p>
                </div>
            </div>

            <div>
                <button onclick="openBatchApprovalModal()" class="bg-white text-indigo-900 hover:bg-indigo-50 font-bold px-5 py-2.5 rounded-xl text-xs shadow-lg transition flex items-center gap-2 transform hover:scale-105">
                    <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
                    <span>Xem danh sách cần duyệt</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- WORKFLOW NOTIFICATION BANNER / MODAL FOR NEWLY ASSIGNED TASKS (NHÂN VIÊN) -->
<?php if (count($pendingAssignedTasks) > 0): ?>
    <div class="mb-6 bg-gradient-to-r from-amber-500 via-orange-500 to-indigo-600 rounded-2xl p-5 text-white shadow-xl relative overflow-hidden border border-amber-300 animate-pulse-subtle">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 text-amber-100">
                    <i class="fa-solid fa-bell text-2xl animate-bounce"></i>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider text-amber-100 mb-1">
                        <i class="fa-solid fa-envelope-open-text"></i> THÔNG BÁO CÔNG VIỆC MỚI ĐƯỢC GIAO
                    </span>
                    <h3 class="text-base md:text-lg font-bold text-white">
                        Bạn có <span class="underline underline-offset-4 decoration-2 decoration-amber-300 font-extrabold text-amber-200"><?= count($pendingAssignedTasks) ?> công việc mới</span> đang chờ phản hồi tiếp nhận!
                    </h3>
                    <p class="text-xs text-amber-100 mt-0.5">Vui lòng xem chi tiết nội dung và chọn Đồng ý tiếp nhận hoặc gửi Ý kiến khác / vướng mắc.</p>
                </div>
            </div>

            <div>
                <button onclick="openPendingTaskModal()" class="bg-white text-indigo-900 hover:bg-amber-50 font-bold px-5 py-2.5 rounded-xl text-xs shadow-lg transition flex items-center gap-2 transform hover:scale-105">
                    <i class="fa-solid fa-clipboard-check text-indigo-600 text-sm"></i>
                    <span>Xem & Tiếp nhận ngay</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Flash Alerts -->
<?php if ($flashSuccess): ?>
    <div class="mb-5 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm text-emerald-800 text-sm font-medium flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="mb-5 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-sm text-rose-800 text-sm font-medium flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-lg"></i>
            <span><?= $flashError ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<!-- Smart Filter Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6 no-print">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                <i class="fa-solid fa-filter text-sm"></i>
            </div>
            <h3 class="font-bold text-slate-800 text-base">Bộ Lọc Thông Minh & Tìm Kiếm</h3>
        </div>
        <div class="flex items-center gap-2">
            <?php if (in_array($currentUser['role'], ['TRUONG_PHONG', 'BGD', 'SUPER_ADMIN'])): ?>
                <button onclick="openBatchApprovalModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-md shadow-emerald-100 relative">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <span>Xem danh sách cần duyệt</span>
                    <?php if (count($pendingApprovalTasks) > 0): ?>
                        <span class="bg-rose-500 text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-full ml-0.5 animate-pulse"><?= count($pendingApprovalTasks) ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            <button onclick="openTaskModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-md shadow-indigo-100">
                <i class="fa-solid fa-plus"></i>
                <span>Thêm công việc</span>
            </button>
        </div>
    </div>

    <form method="GET" action="index.php" onsubmit="return validateDateFilter(this)" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Hidden inputs for date range -->
        <input type="hidden" name="date_from" id="filter_date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
        <input type="hidden" name="date_to" id="filter_date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">

        <!-- COMBOBOX 1: Khung thời gian -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Khung thời gian</label>
            <select name="time_frame" id="filter_time_frame" onchange="onTimeFrameChange()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium text-slate-700">
                <option value="">-- Tất cả thời gian --</option>
                <option value="week" <?= $filters['time_frame'] === 'week' ? 'selected' : '' ?>>Tuần</option>
                <option value="month" <?= $filters['time_frame'] === 'month' ? 'selected' : '' ?>>Tháng</option>
                <option value="quarter" <?= $filters['time_frame'] === 'quarter' ? 'selected' : '' ?>>Quý</option>
                <option value="year" <?= $filters['time_frame'] === 'year' ? 'selected' : '' ?>>Năm</option>
            </select>
        </div>

        <!-- COMBOBOX 2: Chi tiết thời gian -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Chi tiết thời gian</label>
            <select name="time_detail" id="filter_time_detail" onchange="onTimeDetailChange()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium text-slate-700">
                <option value="">-- Tất cả --</option>
            </select>
        </div>

        <!-- Department / My Tasks Filter -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phòng ban / Cá nhân</label>
            <select name="dept_filter" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium text-slate-700">
                <option value="MY_TASKS" <?= $filters['dept_filter'] === 'MY_TASKS' ? 'selected' : '' ?> class="font-bold text-indigo-600">
                    📌 CÔNG VIỆC CỦA TÔI
                </option>
                <option value="" <?= $filters['dept_filter'] === '' ? 'selected' : '' ?>>-- Tất cả đơn vị Phân hiệu --</option>
                <?php foreach ($departmentsList as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filters['dept_filter'] == $d['id'] ? 'selected' : '' ?>>
                        [<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Filter -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Trạng thái</label>
            <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium text-slate-700">
                <option value="ALL">-- Tất cả trạng thái --</option>
                <option value="HOAN_THANH" <?= $filters['status'] === 'HOAN_THANH' ? 'selected' : '' ?>>CV đã hoàn thành</option>
                <option value="DANG_THUC_HIEN" <?= $filters['status'] === 'DANG_THUC_HIEN' ? 'selected' : '' ?>>CV đang thực hiện</option>
                <option value="OVERDUE" <?= $filters['status'] === 'OVERDUE' ? 'selected' : '' ?>>CV quá hạn</option>
                <option value="CHO_DUYET_ALL" <?= $filters['status'] === 'CHO_DUYET_ALL' ? 'selected' : '' ?>>CV đang chờ duyệt</option>
                <option value="CHO_BGD_DUYET" <?= $filters['status'] === 'CHO_BGD_DUYET' ? 'selected' : '' ?>>Chờ BGD duyệt</option>
                <option value="YEU_CAU_CHINH_SUA" <?= $filters['status'] === 'YEU_CAU_CHINH_SUA' ? 'selected' : '' ?>>Yêu cầu chỉnh sửa</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-900 text-white py-2 px-3 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Lọc</span>
            </button>
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 px-3 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1 border border-slate-200" title="Đặt lại bộ lọc">
                <i class="fa-solid fa-arrow-rotate-left"></i>
                <span>Reset</span>
            </a>
        </div>
    </form>

    <!-- THANH THỐNG KÊ NHANH KPI (Stats Bar Phân Quyền) -->
    <?php
    // Scope label based on role
    $scopeLabel = 'Cá nhân';
    if ($currentUser['role'] === 'TRUONG_PHONG') {
        $scopeLabel = 'Toàn Phòng ' . htmlspecialchars($currentUser['department_code']);
    } elseif (in_array($currentUser['role'], ['BGD', 'SUPER_ADMIN'])) {
        $scopeLabel = 'Toàn Phân hiệu';
    }

    // Preserve existing filter params except status for quick filtering
    $baseQuery = $_GET;
    unset($baseQuery['status'], $baseQuery['page']);

    $urlCompleted = 'index.php?' . http_build_query(array_merge($baseQuery, ['status' => 'HOAN_THANH']));
    $urlInProgress = 'index.php?' . http_build_query(array_merge($baseQuery, ['status' => 'DANG_THUC_HIEN']));
    $urlOverdue = 'index.php?' . http_build_query(array_merge($baseQuery, ['status' => 'OVERDUE']));
    $urlPending = 'index.php?' . http_build_query(array_merge($baseQuery, ['status' => 'CHO_DUYET_ALL']));
    ?>
    <div class="mt-4 pt-4 border-t border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-chart-simple text-indigo-600"></i>
                <span>Thống Kê Nhanh KPI (Phạm vi: <strong class="text-indigo-900"><?= $scopeLabel ?></strong>)</span>
            </span>
            <span class="text-[10px] text-slate-400 italic">Nhấp vào từng ô để lọc nhanh danh sách</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <!-- 1. CV đã hoàn thành -->
            <a href="<?= $urlCompleted ?>" class="p-3 rounded-xl border transition shadow-sm flex items-center gap-3 group <?= $filters['status'] === 'HOAN_THANH' ? 'bg-emerald-100 border-emerald-400 ring-2 ring-emerald-400/50' : 'bg-emerald-50/60 hover:bg-emerald-100/80 border-emerald-200' ?>">
                <div class="w-10 h-10 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-lg shrink-0 group-hover:scale-110 transition">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-emerald-800">CV đã hoàn thành</div>
                    <div class="text-lg font-black text-emerald-950 leading-tight"><?= $kpiStats['completed'] ?> <span class="text-xs font-normal text-emerald-700">công việc</span></div>
                </div>
            </a>

            <!-- 2. CV đang thực hiện -->
            <a href="<?= $urlInProgress ?>" class="p-3 rounded-xl border transition shadow-sm flex items-center gap-3 group <?= $filters['status'] === 'DANG_THUC_HIEN' ? 'bg-sky-100 border-sky-400 ring-2 ring-sky-400/50' : 'bg-sky-50/60 hover:bg-sky-100/80 border-sky-200' ?>">
                <div class="w-10 h-10 rounded-lg bg-sky-500 text-white flex items-center justify-center text-lg shrink-0 group-hover:scale-110 transition">
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-sky-800">CV đang thực hiện</div>
                    <div class="text-lg font-black text-sky-950 leading-tight"><?= $kpiStats['in_progress'] ?> <span class="text-xs font-normal text-sky-700">công việc</span></div>
                </div>
            </a>

            <!-- 3. CV quá hạn (Nổi bật / Nhấp nháy màu đỏ) -->
            <a href="<?= $urlOverdue ?>" class="p-3 rounded-xl border transition shadow-sm flex items-center gap-3 group <?= $filters['status'] === 'OVERDUE' ? 'bg-rose-200 border-rose-500 ring-2 ring-rose-500/50' : 'bg-rose-50 hover:bg-rose-100 border-rose-300' ?>">
                <div class="w-10 h-10 rounded-lg bg-rose-600 text-white flex items-center justify-center text-lg shrink-0 group-hover:scale-110 transition <?= $kpiStats['overdue'] > 0 ? 'animate-pulse' : '' ?>">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-rose-800 flex items-center gap-1">
                        <span>CV QUÁ HẠN</span>
                        <?php if ($kpiStats['overdue'] > 0): ?>
                            <span class="inline-block w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-lg font-black text-rose-700 leading-tight"><?= $kpiStats['overdue'] ?> <span class="text-xs font-bold text-rose-800">công việc</span></div>
                </div>
            </a>

            <!-- 4. CV đang chờ duyệt -->
            <a href="<?= $urlPending ?>" class="p-3 rounded-xl border transition shadow-sm flex items-center gap-3 group <?= $filters['status'] === 'CHO_DUYET_ALL' ? 'bg-amber-100 border-amber-400 ring-2 ring-amber-400/50' : 'bg-amber-50/60 hover:bg-amber-100/80 border-amber-200' ?>">
                <div class="w-10 h-10 rounded-lg bg-amber-500 text-white flex items-center justify-center text-lg shrink-0 group-hover:scale-110 transition">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-amber-800">CV đang chờ duyệt</div>
                    <div class="text-lg font-black text-amber-950 leading-tight"><?= $kpiStats['pending'] ?> <span class="text-xs font-normal text-amber-700">công việc</span></div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Task Table Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50">
        <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600"></i>
                <span>
                    <?php
                    if ($filters['dept_filter'] === 'MY_TASKS') {
                        echo 'CÔNG VIỆC CỦA TÔI';
                    } elseif (!empty($filters['dept_filter']) && is_numeric($filters['dept_filter'])) {
                        $matchedDept = array_filter($departmentsList, fn($d) => $d['id'] == $filters['dept_filter']);
                        $deptObj = reset($matchedDept);
                        echo 'DANH SÁCH CÔNG VIỆC - ' . mb_strtoupper($deptObj['name'] ?? '', 'UTF-8');
                    } else {
                        echo 'DANH SÁCH CÔNG VIỆC PHÂN HIỆU QUẢNG NGÃI';
                    }
                    ?>
                </span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Tổng số: <span class="font-bold text-indigo-600"><?= $totalTasks ?></span> công việc</p>
        </div>
        <div class="text-xs text-slate-500 font-medium">
            Trang <span class="font-bold text-slate-800"><?= $page ?></span> / <?= $totalPages ?>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                    <th scope="col" class="py-3.5 px-3 text-center border-r border-slate-800 w-12">STT</th>
                    <th scope="col" class="py-3.5 px-4 border-r border-slate-800 min-w-[240px]">TÊN CÔNG VIỆC / NỘI DUNG</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-36 text-center">ĐƠN VỊ CHỦ TRÌ</th>
                    <th scope="col" class="py-3.5 px-4 border-r border-slate-800 min-w-[180px]">NGƯỜI THỰC HIỆN</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-32 text-center">TRẠNG THÁI</th>
                    <th scope="col" class="py-3.5 px-4 border-r border-slate-800 w-44">TIẾN ĐỘ & MINH CHỨNG</th>
                    <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-28 text-center">HẠN CHÓT</th>
                    <th scope="col" class="py-3.5 px-3 text-center w-28 no-print">THAO TÁC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (count($tasks) > 0): ?>
                    <?php
                    $stt = $offset + 1;
                    foreach ($tasks as $t):
                    ?>
                        <tr class="hover:bg-slate-50/80 transition group">
                            <!-- 1. STT -->
                            <td class="py-3.5 px-3 text-center font-bold text-slate-500 border-r border-slate-100">
                                #<?= $stt++ ?>
                            </td>

                            <!-- 2. TÊN CÔNG VIỆC / NỘI DUNG -->
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

                                <!-- BADGE VĂN BẢN CHỈ ĐẠO ĐÍNH KÈM -->
                                <?php if (!empty($t['attachments']) && count($t['attachments']) > 0): ?>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <?php foreach ($t['attachments'] as $att): ?>
                                            <?php $iconMeta = getFileIconClass($att['file_name']); ?>
                                            <a href="<?= htmlspecialchars($att['file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium <?= $iconMeta['bg'] ?> <?= $iconMeta['color'] ?> border <?= $iconMeta['border'] ?> hover:opacity-80 transition" title="Xem/Tải file: <?= htmlspecialchars($att['file_name']) ?> (<?= formatFileSize($att['file_size']) ?>)">
                                                <i class="fa-solid <?= $iconMeta['icon'] ?>"></i>
                                                <span class="truncate max-w-[150px]"><?= htmlspecialchars($att['file_name']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- BADGE NGƯỜI GIAO VIỆC DÀNH CHO CÔNG VIỆC TRỰC TIẾP -->
                                <?php
                                $isDirectAssignment = in_array($t['creator_role'], ['TRUONG_PHONG', 'BGD', 'SUPER_ADMIN']);
                                ?>
                                <?php if ($isDirectAssignment): ?>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200" title="Công việc do Lãnh đạo giao trực tiếp">
                                            <i class="fa-solid fa-user-tie text-indigo-600"></i>
                                            <span>Giao bởi: <strong><?= htmlspecialchars($t['creator_name']) ?></strong></span>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($t['description'])): ?>
                                    <div class="text-slate-500 text-xs mt-1 line-clamp-2">
                                        <?= htmlspecialchars($t['description']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($t['comment_feedback']) || !empty($t['rejection_reason'])): ?>
                                    <div class="mt-2 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl p-2 text-xs text-amber-950">
                                        <div class="font-bold text-amber-800 text-[11px] mb-0.5">
                                            <i class="fa-solid fa-comments text-amber-600 mr-1"></i>Ý KIẾN / ĐỀ XUẤT NGUỜI THỰC HIỆN:
                                        </div>
                                        <div class="text-slate-800 font-medium">
                                            <?= htmlspecialchars($t['comment_feedback'] ?: $t['rejection_reason']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($t['formatted_leader_comment']) || !empty($t['leader_comments'])): ?>
                                    <div class="mt-2.5 bg-amber-50/90 border-l-4 border-amber-500 rounded-r-xl p-2.5 text-xs text-amber-950 shadow-sm">
                                        <div class="flex items-center gap-1.5 font-bold text-amber-800 text-[11px] mb-1">
                                            <span class="inline-flex items-center gap-1 bg-amber-200/80 text-amber-900 px-2 py-0.5 rounded-full">
                                                <i class="fa-solid fa-user-tie text-amber-700"></i>
                                                <span>Ý KIẾN CHỈ ĐẠO</span>
                                            </span>
                                        </div>
                                        <div class="pl-1 text-slate-800 leading-relaxed font-medium">
                                            <?= htmlspecialchars($t['formatted_leader_comment'] ?: $t['leader_comments']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 3. ĐƠN VỊ CHỦ TRÌ -->
                            <td class="py-3.5 px-3 text-center border-r border-slate-100">
                                <?= renderDepartmentBadge($t['dept_code'], $t['dept_name'], $t['dept_color'], $t['dept_icon']) ?>
                            </td>

                            <!-- 4. NGƯỜI THỰC HIỆN -->
                            <td class="py-3.5 px-4 border-r border-slate-100">
                                <?= formatAssigneesHtml($t['assignees']) ?>
                            </td>

                            <!-- 5. TRẠNG THÁI -->
                            <td class="py-3.5 px-3 text-center border-r border-slate-100">
                                <?= renderStatusBadge($t['status'], $t['due_date']) ?>
                            </td>

                            <!-- 7. TIẾN ĐỘ & MINH CHỨNG -->
                            <td class="py-3.5 px-4 border-r border-slate-100">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-700">
                                        <span>Tiến độ:</span>
                                        <span class="font-bold text-indigo-700"><?= (int)$t['progress'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: <?= (int)$t['progress'] ?>%"></div>
                                    </div>

                                    <!-- Proof Link / Drive Icon -->
                                    <div class="pt-0.5">
                                        <?php if (!empty($t['proof_link'])): ?>
                                            <a href="<?= htmlspecialchars($t['proof_link']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-emerald-700 font-semibold hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded border border-emerald-200 transition" title="Mở link Google Drive minh chứng">
                                                <i class="fa-brands fa-google-drive text-emerald-600 text-sm"></i>
                                                <span>Minh chứng Drive</span>
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 italic">Chưa có link minh chứng</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- 8. HẠN CHÓT -->
                            <td class="py-3.5 px-3 text-center border-r border-slate-100 font-medium">
                                <?php if (!empty($t['due_date'])): ?>
                                    <span class="inline-block px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold">
                                        <i class="fa-regular fa-calendar-check mr-1 text-slate-500"></i>
                                        <?= date('d/m/Y', strtotime($t['due_date'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">---</span>
                                <?php endif; ?>
                            </td>

                            <!-- THAO TÁC / ACTIONS -->
                            <td class="py-3.5 px-3 text-center no-print">
                                <div class="flex items-center justify-center gap-1">
                                    <?php
                                    $isAssignee = false;
                                    if (isset($t['assignees']) && is_array($t['assignees'])) {
                                        foreach ($t['assignees'] as $a) {
                                            if ($a['user_id'] == $currentUser['id']) {
                                                $isAssignee = true;
                                                break;
                                            }
                                        }
                                    }
                                    $isCreator = ($t['created_by'] == $currentUser['id']);
                                    $isLeaderOfDept = ($currentUser['role'] === 'TRUONG_PHONG' && $t['department_id'] == $currentUser['department_id']);
                                    $isBgd = ($currentUser['role'] === 'BGD');
                                    ?>

                                    <!-- View Detail & Timeline Log Modal Button -->
                                    <button onclick='openTaskDetailModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)' class="p-1.5 bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition" title="Xem chi tiết & Lịch sử thao tác (Timeline)">
                                        <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                    </button>

                                    <!-- Progress & Proof Update Button (Trưởng phòng không hiển thị nút này theo yêu cầu) -->
                                    <?php
                                    $canUpdateProgress = ($isAssignee || $isCreator || $isBgd) && ($currentUser['role'] !== 'TRUONG_PHONG');
                                    ?>
                                    <?php if ($canUpdateProgress): ?>
                                        <button onclick='openProgressModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)' class="p-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white rounded-lg transition" title="Cập nhật tiến độ & minh chứng">
                                            <i class="fa-solid fa-sliders text-xs"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Approval & Directive Comment Button for Leaders (Trưởng phòng & BGD) -->
                                    <?php if ($isLeaderOfDept || $isBgd): ?>
                                        <button onclick='openLeaderModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)' class="px-2 py-1 bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1 shadow-sm" title="Duyệt / Cho ý kiến chỉ đạo">
                                            <i class="fa-solid fa-clipboard-check text-xs"></i>
                                            <span>Duyệt / Ý kiến</span>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Edit Button (Cá nhân được quyền sửa công việc do chính mình đã tạo/đăng ký) -->
                                    <?php if ($isCreator || $currentUser['role'] === 'SUPER_ADMIN'): ?>
                                        <button onclick='openTaskModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)' class="p-1.5 bg-amber-50 hover:bg-amber-600 text-amber-600 hover:text-white rounded-lg transition" title="Chỉnh sửa nội dung">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Delete Button (Cá nhân được quyền xóa công việc do chính mình đã tạo/đăng ký) -->
                                    <?php if ($isCreator || $currentUser['role'] === 'SUPER_ADMIN'): ?>
                                        <a href="task_actions.php?action=delete&task_id=<?= $t['id'] ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa công việc này không?');" class="p-1.5 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-lg transition" title="Xóa">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-inbox text-4xl mb-2 text-slate-300"></i>
                            <p class="font-medium">Không tìm thấy công việc nào phù hợp với bộ lọc.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between no-print">
            <div class="text-xs text-slate-500">
                Hiển thị <?= count($tasks) ?> / <?= $totalTasks ?> công việc
            </div>
            <div class="flex items-center space-x-1">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php
                    $query = $_GET;
                    $query['page'] = $p;
                    $url = 'index.php?' . http_build_query($query);
                    ?>
                    <a href="<?= $url ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $p === $page ? 'bg-indigo-600 text-white shadow' : 'bg-white hover:bg-slate-200 text-slate-700 border border-slate-200' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== MODAL: WORKFLOW CONFIRMATION FOR ASSIGNED TASKS ==================== -->
<?php if (count($pendingAssignedTasks) > 0): ?>
<div id="pendingTaskModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 border border-slate-200 transform transition-all max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-bell text-amber-500 text-lg"></i>
                <span>Xác Nhận Tiếp Nhận Công Việc Được Giao</span>
            </h3>
            <button onclick="closePendingTaskModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

            <!-- TASK ACCEPTANCE UI WITH TOP "TIẾP NHẬN TẤT CẢ" & INDIVIDUAL ROW "TIẾP NHẬN" BUTTONS -->
            <form method="POST" action="task_actions.php" id="batchAcceptForm">
                <input type="hidden" name="action" value="respond_all_assigned_tasks">

                <div class="mb-4 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                    <div>
                        <div class="font-bold text-emerald-900 text-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-list-check text-emerald-600"></i>
                            <span>Tiếp nhận toàn bộ (<?= count($pendingAssignedTasks) ?> công việc)</span>
                        </div>
                        <p class="text-xs text-emerald-700 mt-0.5">Bấm 01 lần để tiếp nhận tất cả các công việc đang chờ hoặc bấm nút "Tiếp nhận" riêng ở từng dòng.</p>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2 shrink-0">
                        <i class="fa-solid fa-check-double text-sm"></i>
                        <span>Tiếp nhận tất cả</span>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Ý kiến khác chung (Áp dụng cho toàn bộ danh sách khi bấm "Tiếp nhận tất cả"):</label>
                    <textarea name="general_comment" rows="2" placeholder="Nhập thêm ý kiến, đề xuất chung khi tiếp nhận tất cả..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none font-medium bg-white"></textarea>
                </div>

                <div class="space-y-4">
                    <?php foreach ($pendingAssignedTasks as $pt): ?>
                        <input type="hidden" name="task_ids[]" value="<?= $pt['id'] ?>">
                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-[11px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-full">
                                    Mã công việc #<?= $pt['id'] ?>
                                </span>
                                <span class="text-xs text-slate-500 font-medium">
                                    <i class="fa-solid fa-user-tie text-slate-400 mr-1"></i>Người giao: <strong class="text-slate-700"><?= htmlspecialchars($pt['assigner_name']) ?></strong>
                                </span>
                            </div>

                            <h4 class="font-bold text-slate-900 text-sm mb-1"><?= htmlspecialchars($pt['title']) ?></h4>
                            <?php if (!empty($pt['description'])): ?>
                                <p class="text-xs text-slate-600 mb-3 bg-white p-2.5 rounded-xl border border-slate-200 leading-relaxed"><?= htmlspecialchars($pt['description']) ?></p>
                            <?php endif; ?>

                            <!-- Hiển thị Văn bản / Tài liệu chỉ đạo đính kèm -->
                            <?php if (!empty($pt['attachments']) && count($pt['attachments']) > 0): ?>
                                <div class="mb-3 p-3 bg-blue-50/70 rounded-xl border border-blue-200">
                                    <div class="text-[11px] font-bold text-blue-900 mb-1.5 flex items-center gap-1.5">
                                        <i class="fa-solid fa-paperclip text-blue-600"></i>
                                        <span>Văn bản / Tài liệu chỉ đạo đính kèm (<?= count($pt['attachments']) ?> file):</span>
                                    </div>
                                    <div class="space-y-1.5">
                                        <?php foreach ($pt['attachments'] as $att): ?>
                                            <?php $iconMeta = getFileIconClass($att['file_name']); ?>
                                            <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200 text-xs">
                                                <div class="flex items-center gap-2 overflow-hidden mr-2">
                                                    <i class="fa-solid <?= $iconMeta['icon'] ?> <?= $iconMeta['color'] ?> text-base shrink-0"></i>
                                                    <span class="font-medium text-slate-800 truncate" title="<?= htmlspecialchars($att['file_name']) ?>"><?= htmlspecialchars($att['file_name']) ?></span>
                                                    <span class="text-[10px] text-slate-400 shrink-0">(<?= formatFileSize($att['file_size']) ?>)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <a href="<?= htmlspecialchars($att['file_path']) ?>" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded text-[11px] transition inline-flex items-center gap-1">
                                                        <i class="fa-solid fa-eye text-xs"></i> Xem file
                                                    </a>
                                                    <a href="<?= htmlspecialchars($att['file_path']) ?>" download="<?= htmlspecialchars($att['file_name']) ?>" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded text-[11px] transition inline-flex items-center gap-1">
                                                        <i class="fa-solid fa-download text-xs"></i> Tải về
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-4 text-xs text-slate-500 font-medium mb-3">
                                <div><i class="fa-regular fa-calendar-check text-slate-400 mr-1"></i>Hạn chót: <strong class="text-slate-800"><?= $pt['due_date'] ? date('d/m/Y', strtotime($pt['due_date'])) : 'Không quy định' ?></strong></div>
                            </div>

                            <div class="mb-3">
                                <label class="block text-xs font-bold text-slate-700 mb-1">Ý kiến riêng cho việc này (Không bắt buộc):</label>
                                <textarea name="comments[<?= $pt['id'] ?>]" id="single_comment_<?= $pt['id'] ?>" rows="2" placeholder="Nhập ý kiến / phản hồi cá nhân riêng cho công việc này..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none font-medium bg-white"></textarea>
                            </div>

                            <!-- Single Row Acceptance Button -->
                            <div class="flex justify-end pt-2 border-t border-slate-200">
                                <button type="button" onclick="acceptSingleTaskDirect(<?= $pt['id'] ?>)" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-xs shadow transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-check text-sm"></i>
                                    <span>Tiếp nhận dòng này</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-check-double text-sm"></i>
                        <span>Tiếp nhận tất cả</span>
                    </button>
                </div>
            </form>

            <!-- Hidden Form for Individual Row Acceptance -->
            <form method="POST" action="task_actions.php" id="singleAcceptForm" class="hidden">
                <input type="hidden" name="action" value="respond_assigned_task">
                <input type="hidden" name="task_id" id="single_accept_task_id" value="0">
                <input type="hidden" name="comment_feedback" id="single_accept_feedback" value="">
                <input type="hidden" name="response" value="ACCEPT">
            </form>
    </div>
</div>
<?php endif; ?>

<!-- ==================== MODAL: LEADER DIRECTIVE COMMENT & APPROVAL ==================== -->
<div id="leaderCommentModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check text-emerald-600"></i>
                <span>Phê Duyệt & Cho Ý Kiến Chỉ Đạo</span>
            </h3>
            <button onclick="closeLeaderModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="task_actions.php">
            <input type="hidden" name="action" value="save_leader_comment">
            <input type="hidden" name="task_id" id="leader_task_id">

            <div class="mb-3">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Công việc:</label>
                <div id="leader_task_title" class="p-2.5 bg-slate-50 rounded-xl text-xs font-bold text-slate-800 border border-slate-200"></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Cập nhật Trạng thái Phê duyệt</label>
                <select name="status" id="leader_status" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="DANG_THUC_HIEN">Đã duyệt - Đang thực hiện</option>
                    <option value="CHO_BGD_DUYET">Trình BGD phê duyệt</option>
                    <option value="YEU_CAU_CHINH_SUA">Yêu cầu chỉnh sửa</option>
                </select>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-comment-dots text-amber-600"></i>
                    <span>Ý kiến chỉ đạo của Lãnh đạo</span>
                </label>
                <textarea name="leader_comments" id="leader_comments" rows="4" placeholder="Nhập ý kiến chỉ đạo, hướng dẫn thực hiện cho nhân viên..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeLeaderModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow flex items-center gap-1">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu ý kiến & Phê duyệt</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: PROGRESS & PROOF UPDATE ==================== -->
<div id="progressModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-indigo-600"></i>
                <span>Cập Nhật Tiến Độ & Minh Chứng</span>
            </h3>
            <button onclick="closeProgressModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="task_actions.php">
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="task_id" id="prog_task_id">

            <div class="mb-3">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Công việc:</label>
                <div id="prog_task_title" class="p-2.5 bg-slate-50 rounded-xl text-xs font-bold text-slate-800 border border-slate-200"></div>
            </div>

            <div id="prog_leader_comments_container" class="mb-3 hidden">
                <label class="block text-xs font-bold text-amber-800 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-user-tie text-amber-600"></i>
                    <span>Ý kiến chỉ đạo của Lãnh đạo:</span>
                </label>
                <div id="prog_leader_comments" class="p-2.5 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl text-xs text-slate-800 font-medium leading-relaxed"></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Phần trăm tiến độ (%)</label>
                <div class="flex items-center gap-3">
                    <input type="range" min="0" max="100" step="5" id="prog_range" name="progress" class="w-full accent-indigo-600" oninput="document.getElementById('prog_val').value = this.value">
                    <div class="flex items-center gap-1">
                        <input type="number" min="0" max="100" id="prog_val" class="w-16 p-1.5 border border-slate-300 rounded-lg text-center font-bold text-xs" oninput="document.getElementById('prog_range').value = this.value">
                        <span class="font-bold text-xs text-slate-600">%</span>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1.5">
                    <i class="fa-brands fa-google-drive text-emerald-600"></i>
                    <span>Link Minh chứng (Google Drive / Docs / Sheets)</span>
                </label>
                <input type="url" name="proof_link" id="prog_proof_link" placeholder="https://drive.google.com/..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Dán liên kết Google Drive mở quyền xem cho hội đồng/cấp quản lý.</p>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeProgressModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: CREATE / EDIT SINGLE TASK ==================== -->
<div id="taskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 id="modalTitle" class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-indigo-600"></i>
                <span>Thêm Công Việc Mới</span>
            </h3>
            <button onclick="closeTaskModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="task_actions.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="save_task">
            <input type="hidden" name="task_id" id="form_task_id" value="0">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tên công việc / Nội dung <span class="text-rose-500">*</span></label>
                <input type="text" name="title" id="form_title" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Nhập tên công việc...">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Mô tả chi tiết</label>
                <textarea name="description" id="form_description" rows="3" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Nội dung chi tiết công việc..."></textarea>
            </div>

            <!-- Upload File Văn bản chỉ đạo đính kèm -->
            <div class="p-3.5 bg-blue-50/60 rounded-xl border border-blue-200/80 space-y-2">
                <label class="block text-xs font-bold text-blue-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-paperclip text-blue-600"></i>
                    <span>Văn bản chỉ đạo đính kèm (Công văn, Tờ trình, Quyết định...)</span>
                </label>

                <!-- Existing Attachments List (For edit mode) -->
                <div id="form_existing_attachments" class="space-y-1.5 mb-2 hidden"></div>

                <div class="relative border-2 border-dashed border-blue-300 rounded-xl p-4 text-center hover:bg-blue-50/80 transition bg-white cursor-pointer" id="drop_zone">
                    <input type="file" name="attachments[]" id="form_attachments" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewSelectedFiles(this)">
                    <div class="pointer-events-none">
                        <i class="fa-solid fa-cloud-arrow-up text-blue-600 text-2xl mb-1"></i>
                        <p class="text-xs font-semibold text-slate-700">Kéo thả file vào đây hoặc <span class="text-blue-600 underline">chọn file</span></p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Định dạng: pdf, doc, docx, xls, xlsx, png, jpg, zip, rar (Tối đa 25MB/file)</p>
                    </div>
                </div>

                <div id="selected_files_list" class="space-y-1 text-xs"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Đơn vị chủ trì</label>
                    <?php if (in_array($currentUser['role'], ['TRUONG_PHONG', 'NHAN_VIEN', 'HO_TRO'])): ?>
                        <?php
                        $userDept = array_filter($departmentsList, fn($d) => $d['id'] == $currentUser['department_id']);
                        $userDeptObj = reset($userDept);
                        ?>
                        <input type="hidden" name="department_id" id="form_department_id" value="<?= $currentUser['department_id'] ?>">
                        <input type="text" readonly value="[<?= htmlspecialchars($userDeptObj['code'] ?? '') ?>] <?= htmlspecialchars($userDeptObj['name'] ?? '') ?>" class="w-full p-2.5 border border-slate-200 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold cursor-not-allowed outline-none">
                    <?php else: ?>
                        <select name="department_id" id="form_department_id" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <?php foreach ($departmentsList as $d): ?>
                                <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mức độ ưu tiên</label>
                    <select name="priority" id="form_priority" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        <option value="NORMAL" selected>Bình thường</option>
                        <option value="URGENT">Khẩn cấp</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php if ($currentUser['role'] === 'SUPER_ADMIN'): ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Trạng thái</label>
                        <select name="status" id="form_status" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="CHO_DUYET">Chờ Trưởng phòng duyệt</option>
                            <option value="CHO_BGD_DUYET">Chờ BGD duyệt</option>
                            <option value="CHO_TIEP_NHAN">Chờ tiếp nhận</option>
                            <option value="YEU_CAU_CHINH_SUA">Yêu cầu chỉnh sửa</option>
                            <option value="DANG_THUC_HIEN">Đang thực hiện</option>
                            <option value="HOAN_THANH">Hoàn thành</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="<?= $currentUser['role'] === 'SUPER_ADMIN' ? '' : 'sm:col-span-2' ?>">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hạn chót hoàn thành (dd/mm/yyyy)</label>
                    <input type="date" name="due_date" id="form_due_date" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <!-- Assignees Section: 1 MAIN + Multi COOP (2-Level Selector) -->
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                <div class="font-bold text-xs text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-users text-indigo-600"></i>
                    <span>Phân công Người thực hiện</span>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">1. Người phụ trách chính (MAIN) <?php if ($currentUser['role'] === 'TRUONG_PHONG'): ?><span class="text-indigo-600 font-normal">(Chỉ gồm nhân viên thuộc phòng)</span><?php endif; ?></label>
                    <select name="main_assignee" id="form_main_assignee" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-medium bg-white">
                        <option value="">-- Chọn Người phụ trách chính --</option>
                        <?php foreach ($deptEmployeesForMain as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">2. Bộ chọn Người phối hợp (2 cấp: Chọn Phòng -> Chọn Nhân viên)</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                        <div>
                            <select id="form_coop_dept_filter" onchange="filterCoopUsersByDeptModal()" class="w-full p-2 border border-slate-300 rounded-lg text-xs bg-white font-medium text-slate-700">
                                <option value="">-- Chọn Phòng ban --</option>
                                <?php foreach ($departmentsList as $d): ?>
                                    <?php if ($d['id'] != 1): // skip BGD department from coop filter ?>
                                        <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="text-[11px] text-slate-500 flex items-center">
                            <span>Giữ Ctrl/Cmd để chọn nhiều Nhân viên</span>
                        </div>
                    </div>
                    <select name="coop_assignees[]" id="form_coop_assignees" multiple class="w-full p-2 border border-slate-300 rounded-lg text-xs bg-white h-24">
                        <?php foreach ($coopUsersList as $u): ?>
                            <option value="<?= $u['id'] ?>" data-dept-id="<?= $u['department_id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeTaskModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow">Lưu công việc</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: TASK DETAIL & TIMELINE LOG HISTORY ==================== -->
<div id="taskDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-blue-600"></i>
                <span id="detailModalTitle">Chi Tiết Công Việc & Lịch Sử Xử Lý</span>
            </h3>
            <button onclick="closeTaskDetailModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div class="space-y-4 text-xs">
            <!-- Task Info Card -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between">
                    <span id="detailDeptBadge"></span>
                    <span id="detailStatusBadge"></span>
                </div>
                <h4 id="detailTitle" class="text-sm font-bold text-slate-900"></h4>
                <p id="detailDesc" class="text-slate-600 leading-relaxed"></p>

                <!-- Attached Files Section -->
                <div id="detailAttachmentsContainer" class="pt-2 border-t border-slate-200/60 hidden">
                    <h5 class="font-bold text-blue-900 text-xs mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-paperclip text-blue-600"></i>
                        <span>Văn bản / Tài liệu chỉ đạo đính kèm:</span>
                    </h5>
                    <div id="detailAttachmentsList" class="space-y-1.5"></div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-slate-500 pt-2 border-t border-slate-200/60">
                    <div><i class="fa-solid fa-user text-slate-400 mr-1"></i>Người đăng ký: <strong id="detailCreator" class="text-slate-800"></strong></div>
                    <div><i class="fa-regular fa-calendar-check text-slate-400 mr-1"></i>Hạn chót: <strong id="detailDueDate" class="text-slate-800"></strong></div>
                </div>
            </div>

            <!-- Timeline Log Section -->
            <div>
                <h4 class="font-bold text-slate-800 text-xs mb-3 flex items-center gap-1.5 uppercase tracking-wider">
                    <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i>
                    <span>Lịch Sử Thao Tác Công Việc (Timeline Log)</span>
                </h4>

                <div id="timelineContainer" class="space-y-3 relative pl-4 border-l-2 border-indigo-200 ml-2">
                    <!-- Dynamic timeline logs injected here -->
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-100 mt-5">
            <button type="button" onclick="closeTaskDetailModal()" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl shadow">
                Đóng
            </button>
        </div>
    </div>
</div>

<!-- ==================== MODAL: BATCH APPROVAL FOR LEADERS (TRƯỞNG PHÒNG & BGD) ==================== -->
<?php if (in_array($currentUser['role'], ['TRUONG_PHONG', 'BGD', 'SUPER_ADMIN'])): ?>
<div id="batchApprovalModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 border border-slate-200 transform transition-all max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 shrink-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check text-emerald-600 text-xl"></i>
                <span>
                    <?php if ($currentUser['role'] === 'BGD'): ?>
                        Danh Sách Công Việc Kính Trình Ban Giám Đốc Phê Duyệt
                    <?php elseif ($currentUser['role'] === 'TRUONG_PHONG'): ?>
                        Danh Sách Công Việc Chờ Trưởng Phòng Phê Duyệt
                    <?php else: ?>
                        Danh Sách Công Việc Chờ Phê Duyệt Hàng Loạt
                    <?php endif; ?>
                </span>
                <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-2.5 py-0.5 rounded-full border border-amber-300 ml-1">
                    <?= count($pendingApprovalTasks) ?> công việc
                </span>
            </h3>
            <button onclick="closeBatchApprovalModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <!-- Modal Body / Form -->
        <form method="POST" action="task_actions.php" id="batchApprovalForm" onsubmit="return validateBatchApprovalForm()" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="batch_approve_tasks">

            <!-- Directive Comment Box (Optional) -->
            <div class="mb-4 shrink-0">
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-comment-dots text-amber-600"></i>
                    <span>Ý kiến chỉ đạo chung (Áp dụng cho các công việc được chọn phê duyệt - Không bắt buộc):</span>
                </label>
                <textarea name="leader_comments_general" rows="2" placeholder="Nhập ý kiến chỉ đạo, hướng dẫn chung cho các công việc được duyệt..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none font-medium bg-slate-50/50"></textarea>
            </div>

            <!-- Task List Table -->
            <div class="flex-1 overflow-y-auto border border-slate-200 rounded-xl mb-4">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="sticky top-0 bg-slate-900 text-white font-bold uppercase text-[11px] z-10">
                        <tr>
                            <th scope="col" class="py-3 px-3 text-center border-r border-slate-800 w-12">
                                <input type="checkbox" id="selectAllApprovalTasks" onclick="toggleSelectAllApproval(this)" class="w-4 h-4 accent-emerald-600 rounded cursor-pointer" title="Chọn tất cả">
                            </th>
                            <th scope="col" class="py-3 px-3 text-center border-r border-slate-800 w-12">STT</th>
                            <th scope="col" class="py-3 px-4 border-r border-slate-800">TÊN CÔNG VIỆC / NỘI DUNG</th>
                            <th scope="col" class="py-3 px-3 border-r border-slate-800 w-36 text-center">ĐƠN VỊ / NGƯỜI TRÌNH</th>
                            <th scope="col" class="py-3 px-3 border-r border-slate-800 w-28 text-center">HẠN CHÓT</th>
                            <th scope="col" class="py-3 px-3 text-center w-32">TRẠNG THÁI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (count($pendingApprovalTasks) > 0): ?>
                            <?php $bStt = 1; foreach ($pendingApprovalTasks as $pt): ?>
                                <tr class="hover:bg-amber-50/50 transition cursor-pointer" onclick="toggleRowCheckbox(event, 'approval_check_<?= $pt['id'] ?>')">
                                    <td class="py-3 px-3 text-center border-r border-slate-100" onclick="event.stopPropagation()">
                                        <input type="checkbox" name="task_ids[]" value="<?= $pt['id'] ?>" id="approval_check_<?= $pt['id'] ?>" onchange="updateBatchApproveCount()" class="batch-approval-checkbox w-4 h-4 accent-emerald-600 rounded cursor-pointer">
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold text-slate-500 border-r border-slate-100">
                                        #<?= $bStt++ ?>
                                    </td>
                                    <td class="py-3 px-4 border-r border-slate-100">
                                        <div class="font-bold text-slate-900 text-xs leading-snug">
                                            <?= htmlspecialchars($pt['title']) ?>
                                        </div>
                                        <?php if (!empty($pt['description'])): ?>
                                            <div class="text-slate-500 text-[11px] mt-0.5 line-clamp-2">
                                                <?= htmlspecialchars($pt['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center border-r border-slate-100">
                                        <div class="font-semibold text-slate-800 text-[11px]"><?= htmlspecialchars($pt['dept_name'] ?? '') ?></div>
                                        <div class="text-[10px] text-slate-500"><i class="fa-solid fa-user text-slate-400 mr-0.5"></i><?= htmlspecialchars($pt['creator_name'] ?? '') ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-center border-r border-slate-100 font-medium">
                                        <?= !empty($pt['due_date']) ? date('d/m/Y', strtotime($pt['due_date'])) : '<span class="text-slate-400">---</span>' ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?= renderStatusBadge($pt['status'], $pt['due_date']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-circle-check text-4xl mb-2 text-emerald-400"></i>
                                    <p class="font-medium text-slate-600">Hiện tại không có công việc nào đang chờ phê duyệt.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal Footer / Action Bar -->
            <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                <div class="text-xs text-slate-600 font-medium">
                    <span id="batchApproveSelectedInfo">Đã chọn: <strong class="text-emerald-700 font-bold" id="batchApproveCount">0</strong> / <?= count($pendingApprovalTasks) ?> công việc</span>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" onclick="closeBatchApprovalModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">Đóng</button>
                    <?php if (count($pendingApprovalTasks) > 0): ?>
                        <button type="submit" id="batchApproveSubmitBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-md shadow-emerald-200 transition flex items-center gap-2 transform hover:scale-102">
                            <i class="fa-solid fa-check-double text-sm"></i>
                            <span id="batchApproveBtnText">Phê duyệt các mục đã chọn (0)</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;")
              .replace(/"/g, "&quot;")
              .replace(/'/g, "&#039;");
}

function getFileIconMeta(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    switch (ext) {
        case 'pdf': return { icon: 'fa-file-pdf', color: 'text-rose-600', bg: 'bg-rose-50', border: 'border-rose-200' };
        case 'doc':
        case 'docx': return { icon: 'fa-file-word', color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200' };
        case 'xls':
        case 'xlsx': return { icon: 'fa-file-excel', color: 'text-emerald-600', bg: 'bg-emerald-50', border: 'border-emerald-200' };
        case 'png':
        case 'jpg':
        case 'jpeg': return { icon: 'fa-file-image', color: 'text-purple-600', bg: 'bg-purple-50', border: 'border-purple-200' };
        case 'zip':
        case 'rar': return { icon: 'fa-file-zipper', color: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-200' };
        default: return { icon: 'fa-file-lines', color: 'text-slate-600', bg: 'bg-slate-50', border: 'border-slate-200' };
    }
}

function formatBytes(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

function previewSelectedFiles(input) {
    const listEl = document.getElementById('selected_files_list');
    listEl.innerHTML = '';

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach((file, index) => {
            const meta = getFileIconMeta(file.name);
            const sizeStr = formatBytes(file.size);
            const safeName = escapeHtml(file.name);

            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 rounded-lg border bg-slate-50 border-slate-200';
            div.innerHTML = `
                <div class="flex items-center gap-2 overflow-hidden mr-2">
                    <i class="fa-solid ${meta.icon} ${meta.color} text-sm shrink-0"></i>
                    <span class="font-medium text-slate-800 truncate">${safeName}</span>
                    <span class="text-[10px] text-slate-400 shrink-0">(${sizeStr})</span>
                </div>
                <span class="text-[10px] font-bold text-emerald-600 shrink-0">File mới chọn</span>
            `;
            listEl.appendChild(div);
        });
    }
}

function openTaskDetailModal(task) {
    document.getElementById('detailModalTitle').innerText = 'Chi Tiết Công Việc #' + task.id;
    document.getElementById('detailTitle').innerText = task.title;
    document.getElementById('detailDesc').innerText = task.description || 'Không có mô tả chi tiết.';
    document.getElementById('detailCreator').innerText = task.creator_name || 'Hệ thống';
    document.getElementById('detailDueDate').innerText = task.due_date ? new Date(task.due_date).toLocaleDateString('vi-VN') : 'Không quy định';
    document.getElementById('detailDeptBadge').innerText = '[' + (task.dept_code || '') + '] ' + (task.dept_name || '');

    // Attachments section
    const attContainer = document.getElementById('detailAttachmentsContainer');
    const attList = document.getElementById('detailAttachmentsList');
    attList.innerHTML = '';

    if (task.attachments && task.attachments.length > 0) {
        task.attachments.forEach(att => {
            const meta = getFileIconMeta(att.file_name);
            const sizeStr = formatBytes(att.file_size);
            const safeName = escapeHtml(att.file_name);
            const safePath = escapeHtml(att.file_path);

            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200 text-xs';
            item.innerHTML = `
                <div class="flex items-center gap-2 overflow-hidden mr-2">
                    <i class="fa-solid ${meta.icon} ${meta.color} text-base shrink-0"></i>
                    <span class="font-medium text-slate-800 truncate" title="${safeName}">${safeName}</span>
                    <span class="text-[10px] text-slate-400 shrink-0">(${sizeStr})</span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <a href="${safePath}" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-md text-[11px] transition inline-flex items-center gap-1">
                        <i class="fa-solid fa-eye text-xs"></i> Xem file
                    </a>
                    <a href="${safePath}" download="${safeName}" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md text-[11px] transition inline-flex items-center gap-1">
                        <i class="fa-solid fa-download text-xs"></i> Tải về
                    </a>
                </div>
            `;
            attList.appendChild(item);
        });
        attContainer.classList.remove('hidden');
    } else {
        attContainer.classList.add('hidden');
    }

    const container = document.getElementById('timelineContainer');
    container.innerHTML = '';

    if (task.timeline_logs && task.timeline_logs.length > 0) {
        task.timeline_logs.forEach(log => {
            const timeStr = new Date(log.created_at).toLocaleString('vi-VN');
            const userStr = log.fullname ? (log.fullname + (log.department_code ? ' (' + log.department_code + ')' : '')) : 'Hệ thống';

            let dotColor = 'bg-blue-500';
            if (log.action.includes('APPROVE') || log.action.includes('ACCEPT')) dotColor = 'bg-emerald-500';
            else if (log.action.includes('REJECT') || log.action.includes('DELETE')) dotColor = 'bg-rose-500';

            const item = document.createElement('div');
            item.className = 'relative group';
            item.innerHTML = `
                <div class="absolute -left-[21px] top-1 w-3 h-3 rounded-full ${dotColor} border-2 border-white shadow-sm"></div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium mb-1">
                        <span><i class="fa-solid fa-user text-slate-400 mr-1"></i><strong>${escapeHtml(userStr)}</strong></span>
                        <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i>${escapeHtml(timeStr)}</span>
                    </div>
                    <div class="text-xs text-slate-800 font-semibold mb-0.5">${escapeHtml(log.action)}</div>
                    <div class="text-[11px] text-slate-600 leading-snug">${escapeHtml(log.description || '')}</div>
                </div>
            `;
            container.appendChild(item);
        });
    } else {
        container.innerHTML = '<div class="text-xs text-slate-400 italic p-2">Chưa có lịch sử thao tác ghi nhận.</div>';
    }

    document.getElementById('taskDetailModal').classList.remove('hidden');
    document.getElementById('taskDetailModal').classList.add('flex');
}

function closeTaskDetailModal() {
    document.getElementById('taskDetailModal').classList.add('hidden');
    document.getElementById('taskDetailModal').classList.remove('flex');
}

function openBatchApprovalModal() {
    const modal = document.getElementById('batchApprovalModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        updateBatchApproveCount();
    }
}

function closeBatchApprovalModal() {
    const modal = document.getElementById('batchApprovalModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function acceptSingleTaskDirect(taskId) {
    const commentInput = document.getElementById(`single_comment_${taskId}`);
    const feedback = commentInput ? commentInput.value.trim() : '';

    document.getElementById('single_accept_task_id').value = taskId;
    document.getElementById('single_accept_feedback').value = feedback;
    document.getElementById('singleAcceptForm').submit();
}

function toggleSelectAllApproval(master) {
    const checkboxes = document.querySelectorAll('.batch-approval-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
    });
    updateBatchApproveCount();
}

function updateBatchApproveCount() {
    const checkboxes = document.querySelectorAll('.batch-approval-checkbox');
    const checkedCount = document.querySelectorAll('.batch-approval-checkbox:checked').length;
    const countEl = document.getElementById('batchApproveCount');
    if (countEl) {
        countEl.innerText = checkedCount;
    }
    const btnText = document.getElementById('batchApproveBtnText');
    if (btnText) {
        btnText.innerText = `Phê duyệt các mục đã chọn (${checkedCount})`;
    }

    const selectAll = document.getElementById('selectAllApprovalTasks');
    if (selectAll && checkboxes.length > 0) {
        selectAll.checked = (checkedCount === checkboxes.length);
    }
}

function toggleRowCheckbox(event, checkboxId) {
    if (event.target.tagName.toLowerCase() === 'input') return;
    const cb = document.getElementById(checkboxId);
    if (cb) {
        cb.checked = !cb.checked;
        updateBatchApproveCount();
    }
}

function validateBatchApprovalForm() {
    const checkedCount = document.querySelectorAll('.batch-approval-checkbox:checked').length;
    if (checkedCount === 0) {
        alert('Vui lòng tích chọn ít nhất một công việc để phê duyệt!');
        return false;
    }
    return confirm(`Bạn có chắc chắn muốn phê duyệt ${checkedCount} công việc đã chọn không?`);
}

function openPendingTaskModal() {
    const modal = document.getElementById('pendingTaskModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closePendingTaskModal() {
    const modal = document.getElementById('pendingTaskModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function showRejectReason(taskId) {
    document.getElementById(`reject_reason_box_${taskId}`).classList.remove('hidden');
    document.getElementById(`action_buttons_${taskId}`).classList.add('hidden');
    document.getElementById(`reject_submit_buttons_${taskId}`).classList.remove('hidden');
}

function hideRejectReason(taskId) {
    document.getElementById(`reject_reason_box_${taskId}`).classList.add('hidden');
    document.getElementById(`action_buttons_${taskId}`).classList.remove('hidden');
    document.getElementById(`reject_submit_buttons_${taskId}`).classList.add('hidden');
}

function validateRejection(taskId) {
    const val = document.getElementById(`reject_input_${taskId}`).value.trim();
    if (!val) {
        alert('Vui lòng nhập nội dung ý kiến, đề xuất hoặc vướng mắc!');
        return false;
    }
    return true;
}

function openProgressModal(task) {
    document.getElementById('prog_task_id').value = task.id;
    document.getElementById('prog_task_title').innerText = task.title;
    document.getElementById('prog_range').value = task.progress;
    document.getElementById('prog_val').value = task.progress;
    document.getElementById('prog_proof_link').value = task.proof_link || '';

    const leaderContainer = document.getElementById('prog_leader_comments_container');
    const leaderCommentEl = document.getElementById('prog_leader_comments');
    const displayComment = task.formatted_leader_comment || task.leader_comments;
    if (displayComment && displayComment.trim() !== '') {
        leaderCommentEl.innerText = displayComment;
        leaderContainer.classList.remove('hidden');
    } else {
        leaderContainer.classList.add('hidden');
    }

    document.getElementById('progressModal').classList.remove('hidden');
    document.getElementById('progressModal').classList.add('flex');
}

function closeProgressModal() {
    document.getElementById('progressModal').classList.add('hidden');
    document.getElementById('progressModal').classList.remove('flex');
}

function openLeaderModal(task) {
    document.getElementById('leader_task_id').value = task.id;
    document.getElementById('leader_task_title').innerText = task.title;
    document.getElementById('leader_comments').value = task.leader_comments || '';

    const userRole = '<?= $currentUser['role'] ?>';
    const statusSelect = document.getElementById('leader_status');
    statusSelect.innerHTML = '';

    if (userRole === 'BGD') {
        statusSelect.innerHTML = `
            <option value="DANG_THUC_HIEN">Đã duyệt - Đang thực hiện</option>
            <option value="YEU_CAU_CHINH_SUA">Yêu cầu chỉnh sửa</option>
        `;
    } else {
        // TRUONG_PHONG / SUPER_ADMIN
        statusSelect.innerHTML = `
            <option value="DANG_THUC_HIEN">Đã duyệt - Đang thực hiện</option>
            <option value="CHO_BGD_DUYET">Trình BGD phê duyệt</option>
            <option value="YEU_CAU_CHINH_SUA">Yêu cầu chỉnh sửa</option>
        `;
    }

    statusSelect.value = task.status;
    if (!statusSelect.value) {
        statusSelect.selectedIndex = 0;
    }

    document.getElementById('leaderCommentModal').classList.remove('hidden');
    document.getElementById('leaderCommentModal').classList.add('flex');
}

function closeLeaderModal() {
    document.getElementById('leaderCommentModal').classList.add('hidden');
    document.getElementById('leaderCommentModal').classList.remove('flex');
}

function openTaskModal(task = null) {
    // Reset file input & preview
    const fileInput = document.getElementById('form_attachments');
    if (fileInput) fileInput.value = '';
    const fileListEl = document.getElementById('selected_files_list');
    if (fileListEl) fileListEl.innerHTML = '';

    const existingAttContainer = document.getElementById('form_existing_attachments');
    existingAttContainer.innerHTML = '';

    if (task) {
        document.getElementById('modalTitle').innerText = 'Chỉnh Sửa Công Việc #' + task.id;
        document.getElementById('form_task_id').value = task.id;
        document.getElementById('form_title').value = task.title;
        document.getElementById('form_description').value = task.description || '';
        document.getElementById('form_department_id').value = task.department_id;
        document.getElementById('form_priority').value = (task && (task.priority === 'URGENT' || task.priority === 'KHAN_CAP')) ? 'URGENT' : 'NORMAL';
        if (document.getElementById('form_status')) {
            document.getElementById('form_status').value = task.status;
        }
        document.getElementById('form_due_date').value = task.due_date || '';

        // Existing attachments in edit mode
        if (task.attachments && task.attachments.length > 0) {
            existingAttContainer.classList.remove('hidden');
            task.attachments.forEach(att => {
                const meta = getFileIconMeta(att.file_name);
                const sizeStr = formatBytes(att.file_size);
                const safeName = escapeHtml(att.file_name);

                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 rounded-lg border bg-white border-blue-200 text-xs';
                div.innerHTML = `
                    <div class="flex items-center gap-2 overflow-hidden mr-2">
                        <i class="fa-solid ${meta.icon} ${meta.color} text-sm shrink-0"></i>
                        <span class="font-medium text-slate-800 truncate" title="${safeName}">${safeName}</span>
                        <span class="text-[10px] text-slate-400 shrink-0">(${sizeStr})</span>
                    </div>
                    <a href="task_actions.php?action=delete_attachment&attachment_id=${att.id}" onclick="return confirm('Bạn có chắc muốn xóa văn bản đính kèm này không?');" class="text-rose-600 hover:text-rose-800 p-1 font-bold text-xs" title="Xóa file đính kèm">
                        <i class="fa-solid fa-trash"></i> Xóa
                    </a>
                `;
                existingAttContainer.appendChild(div);
            });
        } else {
            existingAttContainer.classList.add('hidden');
        }

        // Assignees
        let mainUser = '';
        let coopUsers = [];
        if (task.assignees) {
            task.assignees.forEach(a => {
                if (a.role_type === 'MAIN') mainUser = a.user_id;
                else if (a.role_type === 'COOP') coopUsers.push(a.user_id.toString());
            });
        }
        document.getElementById('form_main_assignee').value = mainUser;

        let coopSelect = document.getElementById('form_coop_assignees');
        Array.from(coopSelect.options).forEach(opt => {
            opt.selected = coopUsers.includes(opt.value);
        });

        let deptFilterVal = '';
        if (coopUsers.length > 0) {
            const firstCoopOpt = Array.from(coopSelect.options).find(opt => coopUsers.includes(opt.value));
            if (firstCoopOpt) {
                deptFilterVal = firstCoopOpt.getAttribute('data-dept-id') || '';
            }
        }
        document.getElementById('form_coop_dept_filter').value = deptFilterVal;
        filterCoopUsersByDeptModal();

    } else {
        document.getElementById('modalTitle').innerText = 'Thêm Công Việc Mới';
        document.getElementById('form_task_id').value = 0;
        document.getElementById('form_title').value = '';
        document.getElementById('form_description').value = '';
        document.getElementById('form_due_date').value = '';
        document.getElementById('form_main_assignee').value = '<?= $currentUser['id'] ?>';

        existingAttContainer.classList.add('hidden');

        let coopSelect = document.getElementById('form_coop_assignees');
        Array.from(coopSelect.options).forEach(opt => opt.selected = false);

        document.getElementById('form_priority').value = 'NORMAL';
        document.getElementById('form_coop_dept_filter').value = '';
        filterCoopUsersByDeptModal();
    }

    document.getElementById('taskModal').classList.remove('hidden');
    document.getElementById('taskModal').classList.add('flex');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.add('hidden');
    document.getElementById('taskModal').classList.remove('flex');
}

function filterCoopUsersByDeptModal() {
    const deptId = document.getElementById('form_coop_dept_filter').value;
    const coopSelect = document.getElementById('form_coop_assignees');
    Array.from(coopSelect.options).forEach(opt => {
        const optDept = opt.getAttribute('data-dept-id');
        if (deptId !== '' && optDept === deptId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

function validateDateFilter(form) {
    const dateFrom = form.date_from ? form.date_from.value : '';
    const dateTo = form.date_to ? form.date_to.value : '';

    if (dateFrom && dateTo && dateFrom > dateTo) {
        alert('Cảnh báo: Ngày bắt đầu phải nhỏ hơn hoặc bằng Ngày kết thúc!\nVui lòng chọn lại khoảng thời gian hợp lệ.');
        return false;
    }
    return true;
}

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
        const optionEl = document.createElement('option');
        optionEl.value = opt.val;
        optionEl.textContent = opt.label;
        detailSelect.appendChild(optionEl);
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
        <?= json_encode($filters['time_detail']) ?>,
        <?= json_encode($filters['date_from']) ?>,
        <?= json_encode($filters['date_to']) ?>
    );
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
