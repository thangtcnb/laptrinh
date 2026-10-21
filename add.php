<?php
require_once 'config.php';

$errors = [];
$name = '';
$price = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');

    // Validate tên sản phẩm
    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập tên sản phẩm.';
    } elseif (mb_strlen($name) > 255) {
        $errors['name'] = 'Tên sản phẩm không được vượt quá 255 ký tự.';
    }

    // Validate giá
    if (empty($price) && $price !== '0') {
        $errors['price'] = 'Vui lòng nhập giá sản phẩm.';
    } elseif (!is_numeric($price) || floatval($price) < 0) {
        $errors['price'] = 'Giá sản phẩm phải là số nguyên hoặc số thực không âm.';
    }

    // Nếu không có lỗi, thêm vào cơ sở dữ liệu
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
            $stmt->execute([
                ':name'  => $name,
                ':price' => $price
            ]);
            header('Location: index.php?msg=added');
            exit;
        } catch (PDOException $e) {
            $errors['system'] = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm mới</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h4 class="card-title mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Thêm Sản Phẩm Mới</h4>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['system'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($errors['system']) ?>
                            </div>
                        <?php endif; ?>

                        <form action="add.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="Nhập tên sản phẩm" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errors['name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="price" class="form-label fw-bold">Giá sản phẩm (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" id="price" name="price" value="<?= htmlspecialchars($price) ?>" placeholder="Nhập giá sản phẩm" required>
                                <?php if (isset($errors['price'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($errors['price']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu sản phẩm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
