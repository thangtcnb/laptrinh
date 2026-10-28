<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$attachmentId = (int)($_GET['id'] ?? $_GET['attachment_id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Yêu cầu không hợp lệ. Vui lòng cung cấp ID file đính kèm.');
}

// Lấy thông tin file đính kèm và thông tin công việc liên quan
$stmt = $pdo->prepare("
    SELECT ta.*, t.created_by AS task_creator_id, t.department_id AS task_department_id, t.title AS task_title, t.status AS task_status
    FROM task_attachments ta
    JOIN tasks t ON ta.task_id = t.id
    WHERE ta.id = ?
");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    exit('Không tìm thấy văn bản đính kèm trong hệ thống.');
}

$taskId = (int)$attachment['task_id'];

// Kiểm tra quyền hạn RBAC đối với file đính kèm / công việc:
// (1) Super Admin hoặc Ban Giám đốc (BGD)
// (2) Người tạo / giao công việc đó hoặc người tải file
// (3) Trưởng/Phó phòng quản lý đơn vị thực hiện công việc đó
// (4) Người dùng thuộc danh sách phân công (task_assignees)
// (5) Công việc ở trạng thái công khai đã duyệt/đang thực hiện/hoàn thành (tất cả cá nhân trong phân hiệu có quyền xem công việc)
$hasAccess = false;

if (in_array($currentUser['role'], ['SUPER_ADMIN', 'BGD'])) {
    $hasAccess = true;
} elseif ($attachment['task_creator_id'] == $currentUser['id'] || $attachment['uploaded_by'] == $currentUser['id']) {
    $hasAccess = true;
} elseif (in_array($currentUser['role'], ['TRUONG_PHONG', 'PHO_PHONG']) && $currentUser['department_id'] == $attachment['task_department_id']) {
    $hasAccess = true;
} elseif (!in_array($attachment['task_status'], ['CHO_DUYET', 'CHO_BGD_DUYET'])) {
    // Công việc đã duyệt / đang thực hiện / hoàn thành hiển thị công khai toàn Phân hiệu
    $hasAccess = true;
} else {
    // Kiểm tra danh sách người được phân công (assignees)
    $stmtAssignee = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE task_id = ? AND user_id = ?");
    $stmtAssignee->execute([$taskId, $currentUser['id']]);
    if ((int)$stmtAssignee->fetchColumn() > 0) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden - Truy Cập Bị Từ Chối</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="bg-white p-8 rounded-2xl shadow-xl max-w-md w-full text-center border border-slate-200">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800 mb-2">403 Forbidden - Truy Cập Bị Từ Chối</h1>
        <p class="text-sm text-slate-600 mb-6 leading-relaxed">Bạn không có quyền xem hoặc tải về văn bản đính kèm này. Chỉ người giao việc, người thực hiện hoặc lãnh đạo quản lý mới có quyền truy cập.</p>
        <a href="index.php" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5 rounded-xl text-xs transition">
            <i class="fa-solid fa-arrow-left"></i> Quay lại trang chủ
        </a>
    </div>
</body>
</html>';
    exit;
}

// File path trên máy chủ
$filePath = __DIR__ . '/' . $attachment['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Tập tin không tồn tại trên máy chủ.');
}

// Xác định Content-Type phù hợp
$fileName = $attachment['file_name'];
$fileSize = filesize($filePath);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar-compressed'
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Nếu tham số inline=1 và là PDF hoặc hình ảnh, cho phép mở trực tiếp trong trình duyệt
$disposition = 'attachment';
if (isset($_GET['inline']) && $_GET['inline'] == '1' && in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'])) {
    $disposition = 'inline';
}

// Xóa output buffer để đảm bảo không bị lỗi binary data
if (ob_get_level()) {
    ob_end_clean();
}

// Set Header để stream file an toàn
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
