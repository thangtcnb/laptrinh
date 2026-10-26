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

// Super Admin, BGD, or authorized emails can manage the schedule
$canManageSchedule = (
    $currentUser['role'] === 'SUPER_ADMIN' ||
    $currentUser['role'] === 'BGD' ||
    in_array(strtolower($currentUser['email']), array_map('strtolower', $allowedScheduleAdmins))
);

// Week and Year calculations
$week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('o');

// Guard bounds
if ($week < 1) $week = 1;
if ($week > 53) $week = 53;
if ($year < 2000) $year = (int)date('o');

// Calculate Monday to Sunday dates of the selected ISO week
$dto = new DateTime();
$dto->setISODate($year, $week);
$mondayStr = $dto->format('Y-m-d');
$mondayDisplay = $dto->format('d/m/Y');

$dtoSunday = clone $dto;
$dtoSunday->modify('+6 days');
$sundayStr = $dtoSunday->format('Y-m-d');
$sundayDisplay = $dtoSunday->format('d/m/Y');

// Previous and Next week navigation
$prevDto = (clone $dto)->modify('-1 week');
$prevWeek = (int)$prevDto->format('W');
$prevYear = (int)$prevDto->format('o');

$nextDto = (clone $dto)->modify('+1 week');
$nextWeek = (int)$nextDto->format('W');
$nextYear = (int)$nextDto->format('o');

// Current week / today values
$currentISOWeek = (int)date('W');
$currentISOYear = (int)date('o');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$canManageSchedule) {
        $_SESSION['flash_error'] = "Bạn không có quyền thực hiện thao tác quản lý lịch họp BGD!";
        header("Location: bgd_schedule.php?week=$week&year=$year");
        exit;
    }

    if ($action === 'save_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $scheduleDate = trim($_POST['schedule_date'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $participants = trim($_POST['participants'] ?? '');
        $chairperson = trim($_POST['chairperson'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($scheduleDate)) {
            $_SESSION['flash_error'] = "Vui lòng chọn ngày công tác!";
            header("Location: bgd_schedule.php?week=$week&year=$year");
            exit;
        }

        if (empty($content)) {
            $_SESSION['flash_error'] = "Vui lòng nhập nội dung công việc!";
            header("Location: bgd_schedule.php?week=$week&year=$year");
            exit;
        }

        // Check if an entry already exists for this date
        $stmtCheck = $pdo->prepare("SELECT id FROM bgd_schedules WHERE schedule_date = ?");
        $stmtCheck->execute([$scheduleDate]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            // Update
            $stmtUpdate = $pdo->prepare("
                UPDATE bgd_schedules
                SET content = ?, participants = ?, chairperson = ?, location = ?, created_by = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$content, $participants, $chairperson, $location, $currentUser['id'], $existing['id']]);
            logActivity($pdo, $currentUser['id'], 'UPDATE_BGD_SCHEDULE', "Đã cập nhật lịch công tác ngày $scheduleDate (ID #{$existing['id']})");
            $_SESSION['flash_success'] = "Đã cập nhật lịch công tác thành công!";
        } else {
            // Insert
            $stmtInsert = $pdo->prepare("
                INSERT INTO bgd_schedules (schedule_date, content, participants, chairperson, location, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$scheduleDate, $content, $participants, $chairperson, $location, $currentUser['id']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $currentUser['id'], 'CREATE_BGD_SCHEDULE', "Đã tạo mới lịch công tác ngày $scheduleDate (ID #$newId)");
            $_SESSION['flash_success'] = "Đã thêm lịch công tác mới thành công!";
        }

        // Calculate target week and year for the saved date
        $saveDateDto = new DateTime($scheduleDate);
        $targetWeek = (int)$saveDateDto->format('W');
        $targetYear = (int)$saveDateDto->format('o');

        header("Location: bgd_schedule.php?week=$targetWeek&year=$targetYear");
        exit;
    }

    if ($action === 'delete_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        if ($scheduleId > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM bgd_schedules WHERE id = ?");
            $stmtDel->execute([$scheduleId]);
            logActivity($pdo, $currentUser['id'], 'DELETE_BGD_SCHEDULE', "Đã xóa lịch công tác BGD ID #$scheduleId");
            $_SESSION['flash_success'] = "Đã xóa lịch công tác thành công!";
        }
        header("Location: bgd_schedule.php?week=$week&year=$year");
        exit;
    }

    if ($action === 'save_weekly_note') {
        $noteContent = trim($_POST['note_content'] ?? '');

        $stmtCheckNote = $pdo->prepare("SELECT id FROM bgd_weekly_notes WHERE week_number = ? AND year = ?");
        $stmtCheckNote->execute([$week, $year]);
        $existingNote = $stmtCheckNote->fetch();

        if ($existingNote) {
            $stmtUpdateNote = $pdo->prepare("UPDATE bgd_weekly_notes SET note_content = ?, created_by = ? WHERE id = ?");
            $stmtUpdateNote->execute([$noteContent, $currentUser['id'], $existingNote['id']]);
            logActivity($pdo, $currentUser['id'], 'UPDATE_WEEKLY_NOTE', "Đã cập nhật ghi chú tuần $week/$year");
        } else {
            $stmtInsertNote = $pdo->prepare("INSERT INTO bgd_weekly_notes (week_number, year, note_content, created_by) VALUES (?, ?, ?, ?)");
            $stmtInsertNote->execute([$week, $year, $noteContent, $currentUser['id']]);
            logActivity($pdo, $currentUser['id'], 'CREATE_WEEKLY_NOTE', "Đã tạo mới ghi chú tuần $week/$year");
        }

        $_SESSION['flash_success'] = "Đã lưu Ghi chú trong tuần $week/$year thành công!";
        header("Location: bgd_schedule.php?week=$week&year=$year");
        exit;
    }
}

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Query Schedules for the selected week
$sqlSchedules = "
    SELECT s.*, u_cr.fullname AS creator_name
    FROM bgd_schedules s
    LEFT JOIN users u_cr ON s.created_by = u_cr.id
    WHERE s.schedule_date >= ? AND s.schedule_date <= ?
    ORDER BY s.schedule_date ASC
";
$stmtSched = $pdo->prepare($sqlSchedules);
$stmtSched->execute([$mondayStr, $sundayStr]);
$schedulesList = $stmtSched->fetchAll();

// Map schedule entries by schedule_date (YYYY-MM-DD)
$schedulesByDate = [];
foreach ($schedulesList as $s) {
    $schedulesByDate[$s['schedule_date']] = $s;
}

// Fetch Weekly Note for this week and year
$weeklyNoteContent = '';
$stmtNote = $pdo->prepare("SELECT note_content FROM bgd_weekly_notes WHERE week_number = ? AND year = ?");
$stmtNote->execute([$week, $year]);
$weeklyNote = $stmtNote->fetch();
if ($weeklyNote && !empty($weeklyNote['note_content'])) {
    $weeklyNoteContent = $weeklyNote['note_content'];
}

$daysOfWeekNames = [
    1 => 'Thứ Hai',
    2 => 'Thứ Ba',
    3 => 'Thứ Tư',
    4 => 'Thứ Năm',
    5 => 'Thứ Sáu',
    6 => 'Thứ Bảy',
    7 => 'Chủ Nhật'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full">
    <!-- Header Banner Tiêu Đề Theo Tuần -->
    <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 rounded-2xl p-6 text-white shadow-xl mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 border border-purple-700/50">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/20 text-purple-200 border border-purple-400/30 text-xs font-semibold mb-2">
                <i class="fa-solid fa-calendar-days text-purple-300"></i>
                <span>Phân Hệ Lịch Công Tác Tuần Ban Giám Đốc</span>
            </div>
            <!-- Title mẫu chuẩn: BẢNG LỊCH CÔNG TÁC TUẦN [Số tuần]/[Năm] ([dd/mm/yyyy] – [dd/mm/yyyy]) -->
            <h2 class="text-xl md:text-2xl font-black tracking-tight text-amber-300">
                BẢNG LỊCH CÔNG TÁC TUẦN <?= $week ?>/<?= $year ?> (<?= $mondayDisplay ?> – <?= $sundayDisplay ?>)
            </h2>
            <p class="text-xs text-purple-200 mt-1">
                Lịch làm việc, họp giao ban và công tác tuần của Ban Giám đốc Phân hiệu Quảng Ngãi.
            </p>
        </div>

        <!-- Action Controls & Navigation -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Navigation buttons: Prev / Current / Next Week -->
            <div class="inline-flex rounded-xl bg-purple-950/60 p-1 border border-purple-700/50 shadow-inner">
                <a href="bgd_schedule.php?week=<?= $prevWeek ?>&year=<?= $prevYear ?>" class="px-3 py-2 rounded-lg text-xs font-bold text-purple-200 hover:text-white hover:bg-purple-800/60 transition flex items-center gap-1" title="Tuần trước">
                    <i class="fa-solid fa-chevron-left"></i>
                    <span class="hidden sm:inline">Tuần trước</span>
                </a>
                <a href="bgd_schedule.php?week=<?= $currentISOWeek ?>&year=<?= $currentISOYear ?>" class="px-3 py-2 rounded-lg text-xs font-bold text-purple-200 hover:text-white hover:bg-purple-800/60 transition border-x border-purple-700/40" title="Tuần hiện tại">
                    <span>Tuần hiện tại</span>
                </a>
                <a href="bgd_schedule.php?week=<?= $nextWeek ?>&year=<?= $nextYear ?>" class="px-3 py-2 rounded-lg text-xs font-bold text-purple-200 hover:text-white hover:bg-purple-800/60 transition flex items-center gap-1" title="Tuần sau">
                    <span class="hidden sm:inline">Tuần sau</span>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <!-- Nút Thêm Lịch Họp dành cho cấp có quyền -->
            <?php if ($canManageSchedule): ?>
                <button onclick="openScheduleModal()" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold px-4 py-2.5 rounded-xl text-xs shadow-lg shadow-amber-900/30 transition flex items-center gap-2 border border-amber-400">
                    <i class="fa-solid fa-plus text-sm"></i>
                    <span>Thêm lịch họp</span>
                </button>
            <?php endif; ?>
        </div>
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

    <!-- Table Displays 5 Columns (Read-only vs Edit Permission) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-purple-700 text-lg"></i>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    BẢNG LỊCH CÔNG TÁC TUẦN <?= $week ?>/<?= $year ?> (<?= $mondayDisplay ?> – <?= $sundayDisplay ?>)
                </h3>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-purple-950 text-white font-bold uppercase tracking-wider text-[11px] border-b border-purple-900">
                        <th scope="col" class="py-3.5 px-4 border-r border-purple-900 w-[14%] min-w-[130px]">1. THỨ & NGÀY</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-purple-900 w-[32%] min-w-[260px]">2. NỘI DUNG CÔNG VIỆC</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-purple-900 w-[32%] min-w-[260px]">3. THÀNH PHẦN THAM DỰ</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-purple-900 w-[11%] min-w-[120px]">4. CHỦ TRÌ</th>
                        <th scope="col" class="py-3.5 px-4 border-r border-purple-900 w-[11%] min-w-[120px]">5. ĐỊA ĐIỂM</th>
                        <?php if ($canManageSchedule): ?>
                            <th scope="col" class="py-3.5 px-3 text-center w-[100px]">THAO TÁC</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <?php
                            $dayDate = (clone $dto)->modify('+' . ($i - 1) . ' days');
                            $dateStr = $dayDate->format('Y-m-d');
                            $dayName = $daysOfWeekNames[$i];
                            $fullDayLabel = $dayName . ', ngày ' . $dayDate->format('d/m/Y');
                            $sData = $schedulesByDate[$dateStr] ?? null;
                            $isToday = ($dateStr === date('Y-m-d'));

                            // Parse multi-line content and participants for item row alignment
                            $cRaw = $sData['content'] ?? '';
                            $pRaw = $sData['participants'] ?? '';

                            $cLines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $cRaw))), 'strlen'));
                            $pLines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $pRaw))), 'strlen'));

                            $maxItems = max(count($cLines), count($pLines));
                        ?>
                        <tr class="<?= $isToday ? 'bg-amber-50/60' : 'hover:bg-slate-50' ?> transition">
                            <!-- Cột 1: Thứ & Ngày -->
                            <td class="py-3 px-4 font-bold border-r border-slate-100 text-slate-900 align-top">
                                <div class="flex items-start gap-2 pt-1">
                                    <div class="mt-0.5 text-purple-700">
                                        <i class="fa-regular fa-calendar text-sm"></i>
                                    </div>
                                    <div>
                                        <span class="text-xs font-extrabold text-slate-900 block"><?= $dayName ?></span>
                                        <span class="text-[11px] font-semibold text-purple-700 block mt-0.5"><?= $dayDate->format('d/m/Y') ?></span>
                                        <?php if ($isToday): ?>
                                            <span class="inline-block px-2 py-0.5 text-[10px] font-bold bg-amber-500 text-white rounded mt-1 shadow-sm">Hôm nay</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Cột 2: Nội dung công việc (Căn hàng ngang với Cột 3) -->
                            <td class="py-3 px-4 border-r border-slate-100 text-slate-800 font-medium align-top">
                                <?php if ($maxItems > 0): ?>
                                    <div class="grid grid-cols-1 h-full divide-y divide-slate-100" style="grid-template-rows: repeat(<?= $maxItems ?>, minmax(max-content, 1fr));">
                                        <?php for ($k = 0; $k < $maxItems; $k++): ?>
                                            <div class="py-1.5 flex items-start gap-2">
                                                <?php if (isset($cLines[$k])): ?>
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-600 mt-1.5 shrink-0"></span>
                                                    <span class="text-xs leading-relaxed text-slate-800 font-semibold"><?= htmlspecialchars($cLines[$k]) ?></span>
                                                <?php else: ?>
                                                    <span class="text-slate-300 italic text-xs">-</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="pt-1">
                                        <span class="text-slate-400 italic text-xs">Chưa có lịch công tác</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Cột 3: Thành phần tham dự (Căn hàng ngang tương ứng Cột 2) -->
                            <td class="py-3 px-4 border-r border-slate-100 text-slate-700 font-medium align-top">
                                <?php if ($maxItems > 0): ?>
                                    <div class="grid grid-cols-1 h-full divide-y divide-slate-100" style="grid-template-rows: repeat(<?= $maxItems ?>, minmax(max-content, 1fr));">
                                        <?php for ($k = 0; $k < $maxItems; $k++): ?>
                                            <div class="py-1.5 flex items-start gap-2">
                                                <?php if (isset($pLines[$k])): ?>
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span>
                                                    <span class="text-xs leading-relaxed text-slate-700 font-medium"><?= htmlspecialchars($pLines[$k]) ?></span>
                                                <?php else: ?>
                                                    <span class="text-slate-300 italic text-xs">-</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="pt-1">
                                        <span class="text-slate-400 italic text-xs">-</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Cột 4: Chủ trì -->
                            <td class="py-3 px-4 border-r border-slate-100 font-bold text-slate-900 align-top">
                                <div class="pt-1">
                                    <?php if ($sData && !empty(trim($sData['chairperson']))): ?>
                                        <span class="inline-flex items-start gap-1 text-purple-900 font-bold leading-relaxed">
                                            <i class="fa-solid fa-user-tie text-purple-600 mt-0.5 shrink-0"></i>
                                            <span><?= htmlspecialchars($sData['chairperson']) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic font-normal text-xs">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Cột 5: Địa điểm -->
                            <td class="py-3 px-4 border-r border-slate-100 font-medium text-slate-700 align-top">
                                <div class="pt-1">
                                    <?php if ($sData && !empty(trim($sData['location']))): ?>
                                        <span class="inline-flex items-start gap-1.5 text-rose-700 font-semibold leading-relaxed">
                                            <i class="fa-solid fa-location-dot text-rose-500 mt-0.5 shrink-0"></i>
                                            <span><?= htmlspecialchars($sData['location']) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-xs">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Thao tác: Chỉ dành cho cấp có quyền -->
                            <?php if ($canManageSchedule): ?>
                                <td class="py-3 px-3 text-center align-top">
                                    <div class="pt-1">
                                    <div class="flex items-center justify-center gap-1">
                                        <?php
                                            $modalPayload = [
                                                'schedule_date' => $dateStr,
                                                'date_display' => $fullDayLabel,
                                                'content' => $sData['content'] ?? '',
                                                'participants' => $sData['participants'] ?? '',
                                                'chairperson' => $sData['chairperson'] ?? '',
                                                'location' => $sData['location'] ?? '',
                                                'id' => $sData['id'] ?? 0
                                            ];
                                        ?>
                                        <button onclick='openScheduleModal(<?= json_encode($modalPayload, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-1.5 bg-purple-50 hover:bg-purple-600 text-purple-700 hover:text-white rounded-lg transition" title="Nhập / Sửa lịch công tác ngày này">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <?php if ($sData): ?>
                                            <form method="POST" action="bgd_schedule.php?week=<?= $week ?>&year=<?= $year ?>" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lịch công tác ngày <?= $dayDate->format('d/m/Y') ?>?');" class="inline">
                                                <input type="hidden" name="action" value="delete_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $sData['id'] ?>">
                                                <button type="submit" class="p-1.5 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-lg transition" title="Xóa lịch công tác ngày này">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- KHUNG GHI CHÚ TRONG TUẦN -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between gap-3 bg-amber-50/50">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-note-sticky text-amber-600 text-lg"></i>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    GHI CHÚ TRONG TUẦN <?= $week ?>/<?= $year ?>
                </h3>
            </div>
            <?php if ($canManageSchedule): ?>
                <button onclick="openNoteModal()" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-sm flex items-center gap-1.5 border border-amber-400">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Cập nhật Ghi chú</span>
                </button>
            <?php endif; ?>
        </div>
        <div class="p-5 text-xs leading-relaxed text-slate-800">
            <?php if (!empty(trim($weeklyNoteContent))): ?>
                <div class="bg-slate-50/80 rounded-xl p-4 border border-slate-200/80 font-medium text-slate-800 leading-relaxed">
                    <?= nl2br(htmlspecialchars($weeklyNoteContent)) ?>
                </div>
            <?php else: ?>
                <div class="text-slate-400 italic flex items-center gap-2 py-2">
                    <i class="fa-regular fa-circle-info text-slate-400"></i>
                    <span>Chưa có ghi chú nào trong tuần này.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== MODAL: THÊM / CHỈNH SỬA GHI CHÚ TRONG TUẦN ==================== -->
<?php if ($canManageSchedule): ?>
<div id="noteModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-note-sticky text-amber-600"></i>
                <span>Cập Nhật Ghi Chú Tuần <?= $week ?>/<?= $year ?></span>
            </h3>
            <button onclick="closeNoteModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="bgd_schedule.php?week=<?= $week ?>&year=<?= $year ?>" class="space-y-4">
            <input type="hidden" name="action" value="save_weekly_note">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nội dung Ghi chú trong tuần</label>
                <textarea name="note_content" rows="6" placeholder="Nhập nội dung ghi chú trong tuần (cho phép xuống dòng nhiều mục)..." class="w-full p-3 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 outline-none font-medium"><?= htmlspecialchars($weeklyNoteContent) ?></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeNoteModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-extrabold rounded-xl shadow-md transition flex items-center gap-1.5 border border-amber-400">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu ghi chú tuần</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openNoteModal() {
    document.getElementById('noteModal').classList.remove('hidden');
    document.getElementById('noteModal').classList.add('flex');
}
function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
    document.getElementById('noteModal').classList.remove('flex');
}
</script>
<?php endif; ?>

<!-- ==================== MODAL: THÊM / CHỈNH SỬA LỊCH CÔNG TÁC BGD ==================== -->
<?php if ($canManageSchedule): ?>
<div id="scheduleModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 id="scheduleModalTitle" class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-calendar-plus text-purple-600"></i>
                <span>Cập Nhật Lịch Công Tác BGD</span>
            </h3>
            <button onclick="closeScheduleModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" action="bgd_schedule.php?week=<?= $week ?>&year=<?= $year ?>" class="space-y-4">
            <input type="hidden" name="action" value="save_schedule">
            <input type="hidden" name="schedule_id" id="sched_id" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Ngày công tác <span class="text-rose-500">*</span></label>
                <input type="date" name="schedule_date" id="sched_date" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none bg-white">
                <span id="sched_date_display" class="text-[11px] text-purple-700 font-bold block mt-1"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nội dung công việc <span class="text-rose-500">*</span></label>
                <textarea name="content" id="sched_content" rows="4" required placeholder="Nhập chi tiết nội dung công việc (cho phép xuống dòng nhiều mục)..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Thành phần tham dự</label>
                <textarea name="participants" id="sched_participants" rows="2" placeholder="Ví dụ: Ban Giám đốc, Trưởng các phòng/khoa..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Chủ trì</label>
                <input type="text" name="chairperson" id="sched_chairperson" placeholder="Ví dụ: Đ/c Bùi Thị Yến Linh - Giám đốc..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Địa điểm</label>
                <input type="text" name="location" id="sched_location" placeholder="Ví dụ: Phòng họp A, Cơ sở Quảng Ngãi..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 outline-none font-medium">
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                <button type="submit" class="px-5 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Lưu lịch công tác</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal(payload = null) {
    if (payload) {
        document.getElementById('scheduleModalTitle').innerText = 'Cập Nhật Lịch Công Tác BGD';
        document.getElementById('sched_id').value = payload.id || 0;
        document.getElementById('sched_date').value = payload.schedule_date || '';
        document.getElementById('sched_date_display').innerText = payload.date_display ? ('(' + payload.date_display + ')') : '';
        document.getElementById('sched_content').value = payload.content || '';
        document.getElementById('sched_participants').value = payload.participants || '';
        document.getElementById('sched_chairperson').value = payload.chairperson || '';
        document.getElementById('sched_location').value = payload.location || '';
    } else {
        document.getElementById('scheduleModalTitle').innerText = 'Thêm Lịch Công Tác BGD Mới';
        document.getElementById('sched_id').value = 0;
        document.getElementById('sched_date').value = '<?= $mondayStr ?>';
        document.getElementById('sched_date_display').innerText = '';
        document.getElementById('sched_content').value = '';
        document.getElementById('sched_participants').value = '';
        document.getElementById('sched_chairperson').value = '';
        document.getElementById('sched_location').value = '';
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
