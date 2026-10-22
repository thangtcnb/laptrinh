<?php
define('PAGE_TITLE', 'Bảng Danh Sách Công Việc');
require_once __DIR__ . '/includes/header.php';

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Filters parameters
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'dept_filter' => $_GET['dept_filter'] ?? '',
    'status' => $_GET['status'] ?? ''
];

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
$usersList = getUsers($pdo);
?>

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
            <a href="batch_register.php" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3.5 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-calendar-plus"></i>
                <span>Đăng kí công việc</span>
            </a>
            <button onclick="openTaskModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-md shadow-indigo-100">
                <i class="fa-solid fa-plus"></i>
                <span>Thêm công việc</span>
            </button>
        </div>
    </div>

    <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Date From -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Từ ngày</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>

        <!-- Date To -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Đến ngày</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>

        <!-- Department / My Tasks Filter -->
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phòng ban / Cá nhân</label>
            <select name="dept_filter" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium text-slate-700">
                <option value="">-- Tất cả đơn vị --</option>
                <option value="MY_TASKS" <?= $filters['dept_filter'] === 'MY_TASKS' ? 'selected' : '' ?> class="font-bold text-indigo-600">
                    📌 Công việc của tôi
                </option>
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
                <option value="CHO_BGD_DUYET" <?= $filters['status'] === 'CHO_BGD_DUYET' ? 'selected' : '' ?>>Chờ BGD duyệt</option>
                <option value="CHO_DUYET" <?= $filters['status'] === 'CHO_DUYET' ? 'selected' : '' ?>>Chờ duyệt</option>
                <option value="DANG_THUC_HIEN" <?= $filters['status'] === 'DANG_THUC_HIEN' ? 'selected' : '' ?>>Đang thực hiện</option>
                <option value="HOAN_THANH" <?= $filters['status'] === 'HOAN_THANH' ? 'selected' : '' ?>>Hoàn thành</option>
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
</div>

<!-- Task Table Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50">
        <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600"></i>
                <span>DANH SÁCH CÔNG VIỆC PHÂN HIỆU QUẢNG NGÃI</span>
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
                                <div class="font-bold text-slate-900 text-sm leading-snug">
                                    <?= htmlspecialchars($t['title']) ?>
                                </div>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="text-slate-500 text-xs mt-1 line-clamp-2">
                                        <?= htmlspecialchars($t['description']) ?>
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
                                <?= renderStatusBadge($t['status']) ?>
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
                                    <!-- Progress Update Button -->
                                    <button onclick='openProgressModal(<?= json_encode($t) ?>)' class="p-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white rounded-lg transition" title="Cập nhật tiến độ & minh chứng">
                                        <i class="fa-solid fa-sliders text-xs"></i>
                                    </button>

                                    <!-- Approval Button (BGD / TRUONG_PHONG) -->
                                    <?php if ($currentUser['role'] === 'BGD' && $t['status'] === 'CHO_BGD_DUYET'): ?>
                                        <a href="task_actions.php?action=approve&task_id=<?= $t['id'] ?>&status=DANG_THUC_HIEN" class="p-1.5 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white rounded-lg transition" title="Duyệt công việc (BGD)">
                                            <i class="fa-solid fa-check text-xs"></i>
                                        </a>
                                    <?php elseif ($currentUser['role'] === 'TRUONG_PHONG' && $t['status'] === 'CHO_DUYET' && $t['department_id'] == $currentUser['department_id']): ?>
                                        <a href="task_actions.php?action=approve&task_id=<?= $t['id'] ?>&status=DANG_THUC_HIEN" class="p-1.5 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white rounded-lg transition" title="Trưởng phòng duyệt công việc">
                                            <i class="fa-solid fa-check text-xs"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Edit Button -->
                                    <button onclick='openTaskModal(<?= json_encode($t) ?>)' class="p-1.5 bg-amber-50 hover:bg-amber-600 text-amber-600 hover:text-white rounded-lg transition" title="Chỉnh sửa">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>

                                    <!-- Delete Button (BGD & TRUONG_PHONG) -->
                                    <?php if ($currentUser['role'] === 'BGD' || ($currentUser['role'] === 'TRUONG_PHONG' && $t['department_id'] == $currentUser['department_id'])): ?>
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

        <form method="POST" action="task_actions.php" class="space-y-4">
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Đơn vị chủ trì</label>
                    <select name="department_id" id="form_department_id" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        <?php foreach ($departmentsList as $d): ?>
                            <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mức độ ưu tiên</label>
                    <select name="priority" id="form_priority" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        <option value="THAP">Thấp</option>
                        <option value="TRUNG_BINH" selected>Trung bình</option>
                        <option value="CAO">Cao</option>
                        <option value="KHAN_CAP">Khẩn cấp</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Trạng thái</label>
                    <select name="status" id="form_status" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        <option value="CHO_DUYET">Chờ duyệt</option>
                        <option value="CHO_BGD_DUYET">Chờ BGD duyệt</option>
                        <option value="DANG_THUC_HIEN">Đang thực hiện</option>
                        <option value="HOAN_THANH">Hoàn thành</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hạn chót hoàn thành</label>
                    <input type="date" name="due_date" id="form_due_date" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <!-- Assignees Section: 1 MAIN + Multi COOP -->
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                <div class="font-bold text-xs text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-users text-indigo-600"></i>
                    <span>Phân công Người thực hiện</span>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">1. Người phụ trách chính (MAIN)</label>
                    <select name="main_assignee" id="form_main_assignee" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-medium bg-white">
                        <option value="">-- Chọn Người phụ trách chính --</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">2. Người phối hợp (COOP - Giữ Ctrl/Cmd để chọn nhiều người)</label>
                    <select name="coop_assignees[]" id="form_coop_assignees" multiple class="w-full p-2 border border-slate-300 rounded-lg text-xs bg-white h-24">
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['department_name']) ?>)</option>
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

<script>
function openProgressModal(task) {
    document.getElementById('prog_task_id').value = task.id;
    document.getElementById('prog_task_title').innerText = task.title;
    document.getElementById('prog_range').value = task.progress;
    document.getElementById('prog_val').value = task.progress;
    document.getElementById('prog_proof_link').value = task.proof_link || '';

    document.getElementById('progressModal').classList.remove('hidden');
    document.getElementById('progressModal').classList.add('flex');
}

function closeProgressModal() {
    document.getElementById('progressModal').classList.add('hidden');
    document.getElementById('progressModal').classList.remove('flex');
}

function openTaskModal(task = null) {
    if (task) {
        document.getElementById('modalTitle').innerText = 'Chỉnh Sửa Công Việc #' + task.id;
        document.getElementById('form_task_id').value = task.id;
        document.getElementById('form_title').value = task.title;
        document.getElementById('form_description').value = task.description || '';
        document.getElementById('form_department_id').value = task.department_id;
        document.getElementById('form_priority').value = task.priority;
        document.getElementById('form_status').value = task.status;
        document.getElementById('form_due_date').value = task.due_date || '';

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

    } else {
        document.getElementById('modalTitle').innerText = 'Thêm Công Việc Mới';
        document.getElementById('form_task_id').value = 0;
        document.getElementById('form_title').value = '';
        document.getElementById('form_description').value = '';
        document.getElementById('form_due_date').value = '';
        document.getElementById('form_main_assignee').value = '<?= $currentUser['id'] ?>';

        let coopSelect = document.getElementById('form_coop_assignees');
        Array.from(coopSelect.options).forEach(opt => opt.selected = false);
    }

    document.getElementById('taskModal').classList.remove('hidden');
    document.getElementById('taskModal').classList.add('flex');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.add('hidden');
    document.getElementById('taskModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
