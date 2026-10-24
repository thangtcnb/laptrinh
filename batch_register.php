<?php
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
    session_start();
}

define('PAGE_TITLE', 'Đăng Ký Công Việc');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Calculate default Monday to Sunday of current week
$defaultStartDate = date('Y-m-d', strtotime('monday this week'));
$defaultEndDate = date('Y-m-d', strtotime('sunday this week'));

$departmentsList = getDepartments($pdo);
$assignableUsers = getUsers($pdo, null, true); // Exclude Super Admin
$deptEmployeesForMain = ($currentUser['role'] === 'TRUONG_PHONG')
    ? getUsers($pdo, $currentUser['department_id'], true)
    : $assignableUsers;
$coopUsersList = array_filter($assignableUsers, fn($u) => $u['role'] !== 'BGD');

$errorMsg = '';

// Handle POST batch registration BEFORE header.php output to avoid 'Headers already sent'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchStartDate = $_POST['batch_start_date'] ?? $defaultStartDate;
    $batchEndDate = $_POST['batch_end_date'] ?? $defaultEndDate;
    $tasksData = $_POST['tasks'] ?? [];

    $insertedCount = 0;

    // Default status depends on role
    $defaultStatus = ($currentUser['role'] === 'NHAN_VIEN' || $currentUser['role'] === 'HO_TRO') ? 'CHO_DUYET' : 'CHO_BGD_DUYET';

    $pdo->beginTransaction();
    try {
        $stmtTask = $pdo->prepare("
            INSERT INTO tasks (title, description, department_id, status, priority, progress, start_date, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");

        $stmtAssignee = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role_type) VALUES (?, ?, ?)");

        foreach ($tasksData as $tData) {
            $title = trim($tData['title'] ?? '');
            if (empty($title)) continue; // skip blank rows

            $description = trim($tData['description'] ?? '');
            $priority = $tData['priority'] ?? 'TRUNG_BINH';
            $dueDate = !empty($tData['due_date']) ? $tData['due_date'] : $batchEndDate;
            $mainAssignee = (int)($tData['main_assignee'] ?? $currentUser['id']);
            $coopAssignees = $tData['coop_assignees'] ?? [];

            // Insert Task
            $stmtTask->execute([
                $title,
                $description,
                $currentUser['department_id'],
                $defaultStatus,
                $priority,
                $batchStartDate,
                $dueDate,
                $currentUser['id']
            ]);
            $newTaskId = $pdo->lastInsertId();

            // Insert MAIN Assignee
            if ($mainAssignee > 0) {
                $stmtAssignee->execute([$newTaskId, $mainAssignee, 'MAIN']);
            }

            // Insert COOP Assignees
            if (is_array($coopAssignees)) {
                foreach ($coopAssignees as $coopId) {
                    $coopId = (int)$coopId;
                    if ($coopId > 0 && $coopId !== $mainAssignee) {
                        $stmtAssignee->execute([$newTaskId, $coopId, 'COOP']);
                    }
                }
            }

            $insertedCount++;
        }

        $pdo->commit();

        if ($insertedCount > 0) {
            $_SESSION['flash_success'] = "Đã đăng ký thành công " . $insertedCount . " công việc!";
            header("Location: index.php");
            exit;
        } else {
            $errorMsg = "Vui lòng nhập ít nhất 1 công việc có tiêu đề.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Lỗi khi lưu dữ liệu: " . $e->getMessage();
    }
}

// Now include header.php after all redirect checks
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full">
    <!-- Header banner -->
    <div class="bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl p-6 text-white shadow-lg mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-blue-200 text-xs font-semibold mb-2">
                <i class="fa-solid fa-calendar-week"></i>
                <span>Phân hệ Đăng Ký Công Việc Theo Tuần</span>
            </div>
            <h2 class="text-xl md:text-2xl font-bold tracking-tight">ĐĂNG KÝ HÀNG LOẠT DANH MỤC CÔNG VIỆC</h2>
            <p class="text-xs text-blue-200 mt-1">Đăng ký danh sách công việc cá nhân/đơn vị thực hiện trong tuần, gửi cấp quản lý phê duyệt.</p>
        </div>
        <div>
            <a href="index.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Trở về Bảng công việc</span>
            </a>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg text-rose-800 text-xs font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-base"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="batch_register.php" id="batchForm">
        <!-- Date Range & Department Selection Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
            <h3 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
                <i class="fa-solid fa-clock text-indigo-600"></i>
                <span>1. Chọn Khoảng Thời Gian Tuần & Đơn Vị Thực Hiện</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Từ ngày (Thứ Hai)</label>
                    <input type="date" name="batch_start_date" value="<?= htmlspecialchars($defaultStartDate) ?>" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Đến ngày (Chủ Nhật)</label>
                    <input type="date" name="batch_end_date" value="<?= htmlspecialchars($defaultEndDate) ?>" required class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Đơn vị chủ trì</label>
                    <?php
                    $deptArr = array_filter($departmentsList, fn($d) => $d['id'] == $currentUser['department_id']);
                    $deptObj = reset($deptArr);
                    ?>
                    <input type="text" readonly value="[<?= htmlspecialchars($deptObj['code'] ?? '') ?>] <?= htmlspecialchars($deptObj['name'] ?? '') ?>" class="w-full p-2.5 border border-slate-200 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold cursor-not-allowed outline-none">
                </div>
            </div>
        </div>

        <!-- Dynamic Task Rows Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-list-ol text-indigo-600"></i>
                    <span>2. Danh Sách Công Việc Đăng Ký</span>
                </h3>
                <button type="button" onclick="addRow()" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-plus text-indigo-600"></i>
                    <span>Thêm dòng công việc</span>
                </button>
            </div>

            <!-- Table or Cards for dynamic rows -->
            <div id="rowsContainer" class="space-y-4">
                <!-- Initial 3 rows injected via JavaScript -->
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                <button type="button" onclick="addRow()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>+ Thêm dòng công việc mới (Không giới hạn)</span>
                </button>
            </div>
        </div>

        <!-- Submit Button Footer -->
        <div class="flex items-center justify-end gap-3">
            <a href="index.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Hủy bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-100 transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Lưu đăng ký</span>
            </button>
        </div>
    </form>
</div>

<!-- Template Script for Multi-row Generation -->
<script>
let rowCount = 0;

const mainUsersOptionsHtml = `
    <?php foreach ($deptEmployeesForMain as $u): ?>
        <option value="<?= $u['id'] ?>" data-dept-id="<?= $u['department_id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
    <?php endforeach; ?>
`;

const coopUsersOptionsHtml = `
    <?php foreach ($coopUsersList as $u): ?>
        <option value="<?= $u['id'] ?>" data-dept-id="<?= $u['department_id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
    <?php endforeach; ?>
`;

function filterBatchCoopDept(rowIndex, deptId) {
    const coopSelect = document.getElementById(`coop_select_${rowIndex}`);
    if (!coopSelect) return;
    Array.from(coopSelect.options).forEach(opt => {
        const optDept = opt.getAttribute('data-dept-id');
        if (deptId === 'ALL' || optDept === deptId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

function createRowHtml(index) {
    return `
        <div id="row_${index}" class="p-4 bg-slate-50/70 rounded-2xl border border-slate-200 hover:border-indigo-200 transition relative group">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200/60">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                        ${index + 1}
                    </span>
                    <span class="font-bold text-xs text-slate-800">Dòng công việc #${index + 1}</span>
                </div>

                <button type="button" onclick="removeRow(${index})" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1 rounded-xl text-xs font-semibold transition flex items-center gap-1 border border-rose-200" title="Xóa dòng này">
                    <i class="fa-solid fa-trash"></i>
                    <span>Xóa dòng</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <!-- Title (5 cols) -->
                <div class="md:col-span-5">
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tên công việc / Nội dung <span class="text-rose-500">*</span></label>
                    <input type="text" name="tasks[${index}][title]" required placeholder="Nhập tên công việc..." class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium">
                </div>

                <!-- Description (5 cols) -->
                <div class="md:col-span-5">
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Ghi chú / Mô tả</label>
                    <input type="text" name="tasks[${index}][description]" placeholder="Ghi chú chi tiết..." class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                </div>

                <!-- Priority (hidden input TRUNG_BINH for DB compatibility) -->
                <input type="hidden" name="tasks[${index}][priority]" value="TRUNG_BINH">

                <!-- Due date (2 cols) -->
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Hạn chót</label>
                    <input type="date" name="tasks[${index}][due_date]" value="<?= htmlspecialchars($defaultEndDate) ?>" class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-2 border-t border-slate-200/50">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Người phụ trách chính (MAIN) <?php if ($currentUser['role'] === 'TRUONG_PHONG'): ?><span class="text-indigo-600 font-normal">(Thuộc phòng)</span><?php endif; ?></label>
                    <select name="tasks[${index}][main_assignee]" class="w-full p-2 border border-slate-300 rounded-xl text-xs bg-white font-medium">
                        ${mainUsersOptionsHtml}
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Bộ chọn Người phối hợp (Không chọn Ban Giám đốc)</label>
                    <select onchange="filterBatchCoopDept(${index}, this.value)" class="w-full p-1.5 border border-slate-300 rounded-xl text-[11px] bg-white mb-1">
                        <option value="ALL">-- Cấp 1: Tất cả Phòng ban --</option>
                        <?php foreach ($departmentsList as $d): ?>
                            <?php if ($d['id'] != 1): ?>
                                <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <select name="tasks[${index}][coop_assignees][]" id="coop_select_${index}" multiple class="w-full p-1.5 border border-slate-300 rounded-xl text-xs bg-white h-16">
                        ${coopUsersOptionsHtml}
                    </select>
                </div>
            </div>
        </div>
    `;
}

function addRow() {
    const container = document.getElementById('rowsContainer');
    const div = document.createElement('div');
    div.innerHTML = createRowHtml(rowCount);
    container.appendChild(div.firstElementChild);

    // Default select current user as MAIN assignee
    const newMainSelect = document.querySelector(`#row_${rowCount} select[name="tasks[${rowCount}][main_assignee]"]`);
    if (newMainSelect) {
        newMainSelect.value = '<?= $currentUser['id'] ?>';
    }

    rowCount++;
}

function removeRow(index) {
    const rowEl = document.getElementById(`row_${index}`);
    if (rowEl) {
        const allRows = document.querySelectorAll('#rowsContainer > div');
        if (allRows.length <= 1) {
            alert('Cần giữ lại ít nhất 1 dòng công việc.');
            return;
        }
        rowEl.remove();
    }
}

// Initialize 3 default rows
document.addEventListener('DOMContentLoaded', () => {
    for (let i = 0; i < 3; i++) {
        addRow();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
