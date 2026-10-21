<?php
$host = 'localhost';
$db   = 'laptrinh_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Neu chay moi truong dev/demo ma chua tao DB or loi ket noi, bat exception or hien thi thong bao
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}
