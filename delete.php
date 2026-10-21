<?php
require_once 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            header('Location: index.php?msg=deleted');
            exit;
        } else {
            header('Location: index.php?msg=not_found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: index.php?msg=error');
        exit;
    }
} else {
    header('Location: index.php?msg=not_found');
    exit;
}
