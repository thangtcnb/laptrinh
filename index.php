<?php
require_once 'config.php';

$message = '';
$messageType = 'success';

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = 'Thêm sản phẩm thành công!';
            break;
        case 'updated':
            $message = 'Cập nhật sản phẩm thành công!';
            break;
        case 'deleted':
            $message = 'Xóa sản phẩm thành công!';
            break;
        case 'error':
            $message = 'Đã xảy ra lỗi, vui lòng thử lại!';
            $messageType = 'danger';
            break;
        case 'not_found':
            $message = 'Không tìm thấy sản phẩm!';
            $messageType = 'warning';
            break;
    }
}

// Lấy danh sách sản phẩm
$products = [];
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMsg = "Lỗi khi truy vấn dữ liệu: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold text-primary mb-0"><i class="bi bi-box-seam me-2"></i>Quản Lý Sản Phẩm</h2>
            </div>
            <div class="col-auto">
                <a href="add.php" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Thêm mới</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col" class="text-center" style="width: 80px;">Mã</th>
                                <th scope="col">Tên sản phẩm</th>
                                <th scope="col" class="text-end" style="width: 180px;">Giá (VNĐ)</th>
                                <th scope="col" class="text-center" style="width: 200px;">Ngày tạo</th>
                                <th scope="col" class="text-center" style="width: 160px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="text-center fw-bold">#<?= htmlspecialchars($product['id']) ?></td>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td class="text-end text-success fw-bold">
                                            <?= number_format($product['price'], 0, ',', '.') ?> đ
                                        </td>
                                        <td class="text-center text-muted">
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($product['created_at']))) ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Sửa">
                                                <i class="bi bi-pencil-square"></i> Sửa
                                            </a>
                                            <a href="delete.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?');">
                                                <i class="bi bi-trash"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Chưa có sản phẩm nào.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
