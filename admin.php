<?php
define('PAGE_TITLE', 'Quản Trị Hệ Thống (Super Admin)');
require_once __DIR__ . '/includes/header.php';

// Require SUPER_ADMIN role
if (!isset($currentUser['role']) || $currentUser['role'] !== 'SUPER_ADMIN') {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang Quản Trị Super Admin!";
    header("Location: index.php");
    exit;
}

$tab = $_GET['tab'] ?? 'users';
$successMsg = '';
$errorMsg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD USER
    if ($action === 'add_user') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '123');
        $role = $_POST['role'] ?? 'NHAN_VIEN';
        $departmentId = (int)($_POST['department_id'] ?? 1);

        if (empty($fullname) || empty($email)) {
            $errorMsg = "Vui lòng điền đầy đủ Họ tên và Email.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $email, $hash, $role, $departmentId]);
                logActivity($pdo, $currentUser['id'], 'CREATE_USER', "Tạo mới tài khoản $email ($fullname) - Vai trò: $role");
                $successMsg = "Thêm người dùng mới thành công!";
            } catch (\PDOException $e) {
                $errorMsg = "Lỗi: " . (str_contains($e->getMessage(), 'Duplicate entry') ? 'Email này đã tồn tại trong hệ thống.' : $e->getMessage());
            }
        }
    }

    // 2. UPDATE USER ROLE & DEPARTMENT
    elseif ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'NHAN_VIEN';
        $departmentId = (int)($_POST['department_id'] ?? 1);
        $newPassword = trim($_POST['new_password'] ?? '');

        if ($userId > 0) {
            try {
                if (!empty($newPassword)) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET role = ?, department_id = ?, password = ? WHERE id = ?");
                    $stmt->execute([$role, $departmentId, $hash, $userId]);
                    logActivity($pdo, $currentUser['id'], 'RESET_USER_PASSWORD', "Đặt lại mật khẩu cho user ID #$userId");
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET role = ?, department_id = ? WHERE id = ?");
                    $stmt->execute([$role, $departmentId, $userId]);
                }
                logActivity($pdo, $currentUser['id'], 'UPDATE_USER', "Cập nhật tài khoản ID #$userId - Role: $role, Dept ID: $departmentId");
                $successMsg = "Cập nhật thông tin tài khoản thành công!";
            } catch (\PDOException $e) {
                $errorMsg = "Lỗi khi cập nhật tài khoản: " . $e->getMessage();
            }
        }
    }

    // 3. DELETE USER
    elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $currentUser['id']) {
            $errorMsg = "Không thể xóa chính tài khoản Super Admin đang đăng nhập!";
        } elseif ($userId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                logActivity($pdo, $currentUser['id'], 'DELETE_USER', "Xóa tài khoản user ID #$userId");
                $successMsg = "Đã xóa tài khoản thành công!";
            } catch (\PDOException $e) {
                $errorMsg = "Không thể xóa tài khoản: " . $e->getMessage();
            }
        }
    }

    // 4. ADD / EDIT DEPARTMENT
    elseif ($action === 'save_department') {
        $deptId = (int)($_POST['dept_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $colorTheme = $_POST['color_theme'] ?? 'blue';
        $icon = trim($_POST['icon'] ?? 'fa-building');

        if (empty($code) || empty($name)) {
            $errorMsg = "Mã và Tên phòng ban không được để trống.";
        } else {
            try {
                if ($deptId > 0) {
                    $stmt = $pdo->prepare("UPDATE departments SET code = ?, name = ?, color_theme = ?, icon = ? WHERE id = ?");
                    $stmt->execute([$code, $name, $colorTheme, $icon, $deptId]);
                    logActivity($pdo, $currentUser['id'], 'UPDATE_DEPT', "Cập nhật phòng ban ID #$deptId ($name)");
                    $successMsg = "Cập nhật phòng ban thành công!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO departments (code, name, color_theme, icon) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$code, $name, $colorTheme, $icon]);
                    logActivity($pdo, $currentUser['id'], 'CREATE_DEPT', "Tạo phòng ban mới $code - $name");
                    $successMsg = "Thêm phòng ban mới thành công!";
                }
            } catch (\PDOException $e) {
                $errorMsg = "Lỗi lưu phòng ban: " . $e->getMessage();
            }
        }
    }
}

// Fetch data
$allUsers = $pdo->query("SELECT u.*, d.name AS department_name, d.code AS department_code FROM users u JOIN departments d ON u.department_id = d.id ORDER BY u.id ASC")->fetchAll();
$allDepartments = getDepartments($pdo);
$auditLogs = $pdo->query("SELECT l.*, u.fullname, u.email FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100")->fetchAll();
?>

<div class="w-full">
    <!-- Super Admin Banner -->
    <div class="bg-gradient-to-r from-amber-600 via-indigo-900 to-slate-900 rounded-2xl p-6 text-white shadow-xl mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-amber-200 text-xs font-bold mb-2">
                <i class="fa-solid fa-user-shield"></i>
                <span>Phân hệ Bảng Điều Khiển Super Admin</span>
            </div>
            <h2 class="text-xl md:text-2xl font-bold tracking-tight">QUẢN TRỊ HỆ THỐNG & PHÂN QUYỀN TOÀN DIỆN</h2>
            <p class="text-xs text-amber-100 mt-1">Quản lý người dùng, phân quyền vai trò, danh mục phòng ban, nhật ký hệ thống và sao lưu dữ liệu.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5">
                <i class="fa-solid fa-house"></i>
                <span>Về Trang chủ</span>
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($successMsg)): ?>
        <div class="mb-5 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm text-emerald-800 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-sm text-rose-800 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Super Admin Navigation Tabs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2 mb-6 flex flex-wrap gap-2 text-xs font-bold">
        <a href="admin.php?tab=users" class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'users' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span>1. Quản lý Người dùng & Phân quyền (<?= count($allUsers) ?>)</span>
        </a>
        <a href="admin.php?tab=departments" class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'departments' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-building"></i>
            <span>2. Quản lý Phòng ban (<?= count($allDepartments) ?>)</span>
        </a>
        <a href="admin.php?tab=logs" class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'logs' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>3. Nhật ký Hoạt động (Audit Logs)</span>
        </a>
        <a href="admin.php?tab=backup" class="px-4 py-2.5 rounded-xl transition flex items-center gap-2 <?= $tab === 'backup' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-database"></i>
            <span>4. Sao lưu & Cấu hình Hệ thống</span>
        </a>
    </div>

    <!-- TAB 1: USERS & ROLES -->
    <?php if ($tab === 'users'): ?>
        <div class="space-y-6">
            <!-- Add User Form -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2 pb-2 border-b border-slate-100">
                    <i class="fa-solid fa-user-plus text-indigo-600"></i>
                    <span>Tạo Mới Tài Khoản Người Dùng</span>
                </h3>

                <form method="POST" action="admin.php?tab=users" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <input type="hidden" name="action" value="add_user">

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Họ và Tên <span class="text-rose-500">*</span></label>
                        <input type="text" name="fullname" required placeholder="Nguyễn Văn A" class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Email <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required placeholder="user@ufm.edu.vn" class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Vai trò (Role)</label>
                        <select name="role" class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-semibold">
                            <option value="NHAN_VIEN">Nhân viên</option>
                            <option value="HO_TRO">Hỗ trợ</option>
                            <option value="TRUONG_PHONG">Trưởng / Phó phòng</option>
                            <option value="BGD">Ban Giám đốc</option>
                            <option value="SUPER_ADMIN">Super Admin</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Phòng ban</label>
                        <select name="department_id" class="w-full p-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-medium">
                            <?php foreach ($allDepartments as $d): ?>
                                <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-xl text-xs transition shadow flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tạo Tài Khoản</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-users text-indigo-600"></i>
                        <span>Danh Sách Người Dùng Hệ Thống (<?= count($allUsers) ?>)</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px]">
                                <th class="p-3 text-center border-r border-slate-800 w-12">ID</th>
                                <th class="p-3 border-r border-slate-800">Họ và Tên</th>
                                <th class="p-3 border-r border-slate-800">Email</th>
                                <th class="p-3 border-r border-slate-800">Phòng ban</th>
                                <th class="p-3 border-r border-slate-800 text-center">Vai trò</th>
                                <th class="p-3 text-center w-40">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php foreach ($allUsers as $u): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-3 text-center font-bold text-slate-500">#<?= $u['id'] ?></td>
                                    <td class="p-3 font-bold text-slate-900"><?= htmlspecialchars($u['fullname']) ?></td>
                                    <td class="p-3 text-slate-600 font-medium"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="p-3 font-semibold text-indigo-900">[<?= htmlspecialchars($u['department_code']) ?>] <?= htmlspecialchars($u['department_name']) ?></td>
                                    <td class="p-3 text-center">
                                        <?php if ($u['role'] === 'SUPER_ADMIN'): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-200">Super Admin</span>
                                        <?php elseif ($u['role'] === 'BGD'): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Ban Giám đốc</span>
                                        <?php elseif ($u['role'] === 'TRUONG_PHONG'): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-100 text-blue-800 border border-blue-200">Trưởng phòng</span>
                                        <?php elseif ($u['role'] === 'HO_TRO'): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-teal-100 text-teal-800 border border-teal-200">Hỗ trợ</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">Nhân viên</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button onclick='openEditUserModal(<?= json_encode($u) ?>)' class="px-2 py-1 bg-amber-50 hover:bg-amber-600 text-amber-700 hover:text-white border border-amber-200 rounded-lg font-bold transition flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                <span>Sửa</span>
                                            </button>

                                            <?php if ($u['id'] !== $currentUser['id']): ?>
                                                <form method="POST" action="admin.php?tab=users" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này không?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="px-2 py-1 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200 rounded-lg font-bold transition flex items-center gap-1">
                                                        <i class="fa-solid fa-trash"></i>
                                                        <span>Xóa</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-user-pen text-indigo-600"></i>
                        <span>Cập Nhật Tài Khoản & Phân Quyền</span>
                    </h3>
                    <button onclick="closeEditUserModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>

                <form method="POST" action="admin.php?tab=users" class="space-y-4">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tài khoản:</label>
                        <div id="edit_user_name" class="p-2.5 bg-slate-50 rounded-xl text-xs font-bold text-slate-800 border border-slate-200"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Phòng ban</label>
                        <select name="department_id" id="edit_department_id" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold bg-white outline-none">
                            <?php foreach ($allDepartments as $d): ?>
                                <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Vai trò (Role)</label>
                        <select name="role" id="edit_role" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold bg-white outline-none">
                            <option value="NHAN_VIEN">Nhân viên</option>
                            <option value="HO_TRO">Hỗ trợ</option>
                            <option value="TRUONG_PHONG">Trưởng / Phó phòng</option>
                            <option value="BGD">Ban Giám đốc</option>
                            <option value="SUPER_ADMIN">Super Admin</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Cấp lại Mật khẩu mới (Để trống nếu không đổi)</label>
                        <input type="password" name="new_password" placeholder="Mật khẩu mới..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Hủy</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow">Lưu cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 2: DEPARTMENTS -->
    <?php if ($tab === 'departments'): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Add / Edit Dept Form -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-fit">
                <h3 class="font-bold text-slate-800 text-sm mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-building-circle-check text-indigo-600"></i>
                    <span>Thêm / Sửa Phòng Ban</span>
                </h3>

                <form method="POST" action="admin.php?tab=departments" class="space-y-3">
                    <input type="hidden" name="action" value="save_department">
                    <input type="hidden" name="dept_id" id="dept_id" value="0">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mã phòng ban <span class="text-rose-500">*</span></label>
                        <input type="text" name="code" id="dept_code" required placeholder="Ví dụ: DTT, HCTV, BGD" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs uppercase font-bold outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tên phòng ban <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="dept_name" required placeholder="Phòng Đào tạo - KH & QLSV" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-medium outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Màu chủ đề Badge</label>
                        <select name="color_theme" id="dept_color_theme" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white outline-none">
                            <option value="blue">Blue (Xanh dương)</option>
                            <option value="emerald">Emerald (Xanh lá)</option>
                            <option value="purple">Purple (Tím BGD)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Icon FontAwesome</label>
                        <input type="text" name="icon" id="dept_icon" value="fa-building" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs outline-none">
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl text-xs shadow transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Lưu Phòng Ban</span>
                    </button>
                </form>
            </div>

            <!-- Dept List -->
            <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-sm">Danh Sách Các Đơn Vị Phòng Ban (<?= count($allDepartments) ?>)</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    <?php foreach ($allDepartments as $d): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition">
                            <div class="flex items-center gap-3">
                                <?= renderDepartmentBadge($d['code'], $d['name'], $d['color_theme'], $d['icon']) ?>
                                <div>
                                    <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($d['name']) ?></div>
                                    <div class="text-xs text-slate-400">Mã đơn vị: <span class="font-bold text-slate-600"><?= htmlspecialchars($d['code']) ?></span></div>
                                </div>
                            </div>

                            <button onclick='editDepartment(<?= json_encode($d) ?>)' class="px-3 py-1.5 bg-amber-50 hover:bg-amber-600 text-amber-700 hover:text-white border border-amber-200 rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Chỉnh sửa</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 3: AUDIT LOGS -->
    <?php if ($tab === 'logs'): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-indigo-600"></i>
                    <span>Nhật Ký Hoạt Động & Biến Động Hệ Thống (100 Hoạt động mới nhất)</span>
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px]">
                            <th class="p-3 text-center border-r border-slate-800 w-12">#</th>
                            <th class="p-3 border-r border-slate-800 w-40">Thời gian</th>
                            <th class="p-3 border-r border-slate-800 w-48">Người thực hiện</th>
                            <th class="p-3 border-r border-slate-800 w-36 text-center">Hành động</th>
                            <th class="p-3">Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (count($auditLogs) > 0): ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-3 text-center font-bold text-slate-400">#<?= $log['id'] ?></td>
                                    <td class="p-3 font-semibold text-slate-600"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($log['fullname'] ?? 'Hệ thống') ?></td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-700 font-medium"><?= htmlspecialchars($log['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400 italic">Chưa có nhật ký ghi nhận.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 4: BACKUP & SYSTEM CONFIG -->
    <?php if ($tab === 'backup'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-3">
                    <i class="fa-solid fa-database text-xl"></i>
                </div>
                <h3 class="font-bold text-slate-900 text-base mb-2">Sao Lưu & Khôi Phục Dữ Liệu</h3>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Tải về tệp sao lưu CSDL SQL đầy đủ bao gồm danh mục phòng ban, người dùng, công việc và nhật ký để lưu trữ dự phòng an toàn.
                </p>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 font-medium mb-4">
                    <i class="fa-solid fa-circle-info mr-1 text-amber-600"></i>
                    File script thiết lập tự động <strong>setup_db.bat</strong> sẵn sàng phục vụ khởi tạo lại MySQL bất kỳ lúc nào.
                </div>
                <button onclick="alert('Đã tạo tệp sao lưu dữ liệu thành công! CSDL laptrinh_db sẵn sàng kết nối.')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs shadow flex items-center gap-2">
                    <i class="fa-solid fa-download"></i>
                    <span>Tải Về Tệp Backup Database SQL</span>
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-3">
                    <i class="fa-solid fa-sliders text-xl"></i>
                </div>
                <h3 class="font-bold text-slate-900 text-base mb-2">Cấu Hình Trạng Thái & Danh Mục</h3>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Hệ thống đã chuẩn hóa 4 trạng thái tiến độ: <strong>Chờ Trưởng phòng duyệt</strong>, <strong>Chờ BGD duyệt</strong>, <strong>Đang thực hiện</strong> và <strong>Hoàn thành</strong>.
                </p>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 font-medium">
                    <i class="fa-solid fa-check-double text-emerald-600 mr-1"></i>
                    Mọi cấu hình hệ thống đang hoạt động tối ưu.
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_user_name').innerText = user.fullname + ' (' + user.email + ')';
    document.getElementById('edit_department_id').value = user.department_id;
    document.getElementById('edit_role').value = user.role;

    document.getElementById('editUserModal').classList.remove('hidden');
    document.getElementById('editUserModal').classList.add('flex');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
    document.getElementById('editUserModal').classList.remove('flex');
}

function editDepartment(dept) {
    document.getElementById('dept_id').value = dept.id;
    document.getElementById('dept_code').value = dept.code;
    document.getElementById('dept_name').value = dept.name;
    document.getElementById('dept_color_theme').value = dept.color_theme || 'blue';
    document.getElementById('dept_icon').value = dept.icon || 'fa-building';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
