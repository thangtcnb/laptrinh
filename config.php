<?php
// Configuration for Database Connection and App Settings
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
    // Return friendly error if connection fails
    die("Lỗi kết nối CSDL MySQL: " . $e->getMessage());
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Hệ thống Quản lý Công việc - Phân hiệu UFM Quảng Ngãi');
}
