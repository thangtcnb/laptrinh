<?php
session_start();
define('PAGE_TITLE', 'Lịch Họp BGD');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// 04 Authorized emails for Add/Edit/Delete permissions
$allowedScheduleAdmins = [
    'yenlinhbt@ufm.edu.vn',
    'lexuanlam@ufm.edu.vn',
    'tranthibichlien@ufm.edu.vn',
    'tranthitam@ufm.edu.vn'
];
$canManageSchedule = in_array(strtolower($currentUser['email']), array_map('strtolower', $allowedScheduleAdmins));

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch 2 BGD Leaders for selection: Bùi Thị Yến Linh & Lê Xuân Lãm
$stmtLeaders = $pdo->query("SELECT id, fullname, email, role FROM users WHERE LOWER(email) IN ('yenlinhbt@ufm.edu.vn', 'lexuanlam@ufm.edu.vn') ORDER BY id ASC");
$bgdLeaders = $stmtLeaders->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$canManageSchedule) {
        $_SESSION['flash_error'] = "Bạn không có quyền thực hiện thao tác quản lý lịch họp BGD!";
        header("Location: bgd_schedule.php");
        exit;
    }

    if ($action === 'save_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $bgdUserId = (int)($_POST['bgd_user_id'] ?? 0);
        $eventTime = $_POST['event_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $content = trim($_POST['content'] ?? '');

        // Verify bgdUserId is one of the 2 authorized BGD members
        $allowedBgdUserIds = array_column($bgdLeaders, 'id');
        if (!in_array($bgdUserId, $allowedBgdUserIds)) {
            $_SESSION['flash_error'] = "Lịch họp BGD chỉ được phép chọn Giám đốc (Bùi Thị Yến Linh) hoặc Phó Giám đốc (Lê Xuân Lãm)!";
            header("Location: bgd_schedule.php");
            exit;
        }

        if (empty($eventTime) || empty($content)) {
            $_SESSION['flash_error'] = "Vui lòng nhập đầy đủ Thời gian họp và Nội dung cuộc họp!";
            header("Location: bgd_schedule.php");
            exit;
        }

        if ($scheduleId > 0) {
            // Update
            $stmtUpdate = $pdo->prepare("
                UPDATE bgd_schedules
                SET bgd_user_id = ?, content = ?, event_time = ?, location = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$bgdUserId, $content, $eventTime, $location, $scheduleId]);
            logActivity($pdo, $currentUser['id'], 'UPDATE_BGD_SCHEDULE', "Đã cập nhật lịch họp BGD ID #$scheduleId");
            $_SESSION['flash_success'] = "Đã cập nhật lịch họp BGD thành công!";
        } else {
            // Insert
            $stmtInsert = $pdo->prepare("
                INSERT INTO bgd_schedules (bgd_user_id, content, event_time, location, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$bgdUserId, $content, $eventTime, $location, $currentUser['id']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $currentUser['id'], 'CREATE_BGD_SCHEDULE', "Đã tạo mới lịch họp BGD ID #$newId");
            $_SESSION['flash_success'] = "Đã thêm lịch họp BGD mới thành công!";
        }

        header("Location: bgd_schedule.php");
        exit;
    }

    if ($action === 'delete_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        if ($scheduleId > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM bgd_schedules WHERE id = ?");
            $stmtDel->execute([$scheduleId]);
            logActivity($pdo, $currentUser['id'], 'DELETE_BGD_SCHEDULE', "Đã xóa lịch họp BGD ID #$scheduleId");
            $_SESSION['flash_success'] = "Đã xóa lịch họp BGD thành công!";
        }
        header("Location: bgd_schedule.php");
        exit;
    }
}

// Filters
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterBgdUser = (int)($_GET['bgd_user_id'] ?? 0);

$where = ["1=1"];
$params = [];

if (!empty($filterDateFrom)) {
    $where[] = "DATE(s.event_time) >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $where[] = "DATE(s.event_time) <= ?";
    $params[] = $filterDateTo;
}
if ($filterBgdUser > 0) {
    $where[] = "s.bgd_user_id = ?";
    $params[] = $filterBgdUser;
}

$whereSql = implode(" AND ", $where);

// Query Schedules
$sqlSchedules = "
    SELECT s.*, u_bgd.fullname AS bgd_name, u_bgd.role AS bgd_role, u_cr.fullname AS creator_name
    FROM bgd_schedules s
    JOIN users u_bgd ON s.bgd_user_id = u_bgd.id
    JOIN users u_cr ON s.created_by = u_cr.id
    WHERE $whereSql
    ORDER BY s.event_time DESC
";
$stmtSched = $pdo->prepare($sqlSchedules);
$stmtSched->execute($params);
$schedules = $stmtSched->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 rounded-2xl p-6 text-white shadow-lg mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 border border-purple-700/50">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/20 text-purple-200 border border-purple-400/30 text-xs font-semibold mb-2">
                <i class="fa-solid fa-calendar-days text-purple-300"></i>
                <span>Phân Hệ Quản Lý & Theo Dõi Lịch Họp BGD</span>
            </div>
            <h2 class="text-xl md:text-2xl font-bold tracking-tight">LỊCH HỌP BAN GIÁM ĐỐC PHÂN HIỆU</h2>
            <p class="text-xs text-purple-200 mt-1">Theo dõi công tác, lịch làm việc và cuộc họp của Giám đốc & Phó Giám đốc Phân hiệu Quảng Ngãi.</p>
        </div>

        <?php if ($canManageSchedule): ?>
            <div>
                <button onclick="openScheduleModal()" class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs shadow-lg shadow-purple-900/40 transition flex items-center gap-2">
                    <i class="fa-solid fa-plus text-sm"></i>
                    <span>Thêm lịch họp BGD</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

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
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
        <form method="GET" action="bgd_schedule.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Từ ngày</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Đến ngày</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Thành viên Ban Giám đốc</label>
                <select name="bgd_user_id" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none bg-white font-medium text-slate-700">
                    <option value="0">-- Tất cả Ban Giám đốc --</option>
                    <?php foreach ($bgdLeaders as $leader): ?>
                        <option value="<?= $leader['id'] ?>" <?= $filterBgdUser == $leader['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($leader['fullname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-purple-800 hover:bg-purple-900 text-white py-2 px-3 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Lọc</span>
                </button>
                <a href="bgd_schedule.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 px-3 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1 border border-slate-200" title="Đặt lại bộ lọc">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                    <span>Reset</span>
                </a>
            </div>
        </form>
    </div>

    <!-- Schedules Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-purple-600"></i>
                    <span>DANH SÁCH LỊCH HỌP BAN GIÁM ĐỐC</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Hiển thị <span class="font-bold text-purple-600"><?= count($schedules) ?></span> lịch họp</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                        <th scope="col" class="py-3.5 px-3 text-center border-r border-slate-800 w-12">STT</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-slate-800 w-44 text-center">THỜI GIAN HỌP</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-slate-800 w-52">LÃNH ĐẠO THAM DỰ</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-slate-800 min-w-[250px]">NỘI DUNG CUỘC HỌP</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-slate-800 w-48">ĐỊA ĐIỂM</th>
                        <th scope="col" class="py-3.5 px-3 border-r border-slate-800 w-36 text-center">NGƯỜI TẠO</th>
                        <?php if ($canManageSchedule): ?>
                            <th scope="col" class="py-3.5 px-3 text-center w-28">THAO TÁC</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (count($schedules) > 0): ?>
                        <?php $stt = 1; foreach ($schedules as $s): ?>
                            <tr class="hover:bg-purple-50/40 transition">
                                <td class="py-3.5 px-3 text-center font-bold text-slate-500 border-r border-slate-100">
                                    #<?= $stt++ ?>
                                </td>
                                <td class="py-3.5 px-4 text-center border-r border-slate-100 font-semibold">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-50 text-purple-800 border border-purple-200 text-xs font-bold">
                                        <i class="fa-regular fa-clock text-purple-600"></i>
                                        <span><?= date('H:i - d/m/Y', strtotime($s['event_time'])) ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 border-r border-slate-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs shrink-0">
                                            <i class="fa-solid fa-user-tie"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-xs">
                                                <?= htmlspecialchars($s['bgd_name']) ?>
                                            </div>
                                            <div class="text-[10px] text-purple-700 font-medium">
                                                Ban Giám đốc
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 border-r border-slate-100">
                                    <div class="font-bold text-slate-800 text-xs leading-relaxed">
                                        <?= nl2br(htmlspecialchars($s['content'])) ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 border-r border-slate-100 font-medium text-slate-700">
                                    <?php if (!empty($s['location'])): ?>
                                        <span class="inline-flex items-center gap-1 text-slate-800">
                                            <i class="fa-solid fa-location-dot text-rose-500"></i>
                                            <?= htmlspecialchars($s['location']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Chưa xác định</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 text-center border-r border-slate-100 text-slate-600 font-medium text-[11px]">
                                    <?= htmlspecialchars($s['creator_name']) ?>
                                </td>
                                <?php if ($canManageSchedule): ?>
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick='openScheduleModal(<?= json_encode($s) ?>)' class="p-1.5 bg-amber-50 hover:bg-amber-600 text-amber-600 hover:text-white rounded-lg transition" title="Sửa lịch họp">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>
                                            <form method="POST" action="bgd_schedule.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lịch họp này?');" class="inline">
                                                <input type="hidden" name="action" value="delete_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="p-1.5 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-lg transition" title="Xóa lịch họp">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $canManageSchedule ? '7' : '6' ?>" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-calendar-xmark text-4xl mb-2 text-slate-300"></i>
                                <p class="font-medium">Chưa có lịch họp BGD nào trong khoảng thời gian đã chọn.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODAL: ADD / EDIT BGD SCHEDULE ==================== -->
<?php if ($canManageSchedule): ?>
<div id="scheduleModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 id="scheduleModalTitle" class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-calendar-plus text-purple-600"></i>
                <span>Thêm Lịch Họp BGD Mới</span>
            </h3>
            <button onclick="closeScheduleModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="bgd_schedule.php" class="space-y-4">
            <input type="hidden" name="action" value="save_schedule">
            <input type="hidden" name="schedule_id" id="sched_id" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Thành viên Ban Giám đốc <span class="text-rose-500">*</span></label>
                <select name="bgd_user_id" id="sched_bgd_user_id" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none bg-white">
                    <?php foreach ($bgdLeaders as $leader): ?>
                        <option value="<?= $leader['id'] ?>"><?= htmlspecialchars($leader['fullname']) ?> (<?= $leader['email'] === 'yenlinhbt@ufm.edu.vn' ? 'Giám đốc' : 'Phó Giám đốc' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Thời gian họp <span class="text-rose-500">*</span></label>
                <input type="datetime-local" name="event_time" id="sched_event_time" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-purple-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Địa điểm họp</label>
                <input type="text" name="location" id="sched_location" placeholder="Nhập địa điểm (Ví dụ: Phòng họp A, Cơ sở Quảng Ngãi)..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nội dung cuộc họp <span class="text-rose-500">*</span></label>
                <textarea name="content" id="sched_content" rows="4" required placeholder="Nhập chi tiết nội dung cuộc họp, thành phần tham dự..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu lịch họp</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal(sched = null) {
    if (sched) {
        document.getElementById('scheduleModalTitle').innerText = 'Chỉnh Sửa Lịch Họp BGD';
        document.getElementById('sched_id').value = sched.id;
        document.getElementById('sched_bgd_user_id').value = sched.bgd_user_id;
        // Format datetime-local value (YYYY-MM-DDTHH:MM)
        if (sched.event_time) {
            let dt = sched.event_time.replace(' ', 'T').substring(0, 16);
            document.getElementById('sched_event_time').value = dt;
        }
        document.getElementById('sched_location').value = sched.location || '';
        document.getElementById('sched_content').value = sched.content || '';
    } else {
        document.getElementById('scheduleModalTitle').innerText = 'Thêm Lịch Họp BGD Mới';
        document.getElementById('sched_id').value = 0;
        document.getElementById('sched_event_time').value = '';
        document.getElementById('sched_location').value = '';
        document.getElementById('sched_content').value = '';
    }

    document.getElementById('scheduleModal').classList.remove('hidden');
    document.getElementById('scheduleModal').classList.add('flex');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.getElementById('scheduleModal').classList.remove('flex');
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
