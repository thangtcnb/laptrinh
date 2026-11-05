<?php
/**
 * Script tao / dong bo tai khoan Truong phong:
 * Username: tp
 * Password: tp
 * Role: TRUONG_PHONG
 * Don vi: Phong Hanh chinh - Tai vu (id: 3)
 *
 * Tu dong load thong so ket noi CSDL tu file config.php
 */

$configFile = __DIR__ . '/config.php';
$exampleConfigFile = __DIR__ . '/config.php.example';

if (file_exists($configFile)) {
    require_once $configFile;
    echo "[Config] Da load thong tin ket noi tu config.php\n";
} elseif (file_exists($exampleConfigFile)) {
    require_once $exampleConfigFile;
    echo "[Config] Da load thong tin ket noi tu config.php.example\n";
} else {
    echo "[ERROR] Khong tim thay config.php!\n";
    exit(1);
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname = defined('DB_NAME') ? DB_NAME : 'laptrinh_db';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$port = 3306;

if (strpos($host, ':') !== false) {
    list($host, $port) = explode(':', $host, 2);
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];
    $pdo = new PDO($dsn, $username, $password, $options);

    // Xoa bo tai khoan phu tp theo yeu cau
    $pdo->exec("DELETE FROM task_assignees WHERE user_id IN (SELECT id FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn') OR user_id = 24");
    $pdo->exec("DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn') OR user_id = 24");
    $pdo->exec("DELETE FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn' OR id = 24");
    echo "[SUCCESS] Da xoa bo tai khoan tp khoi he thong.\n";
    exit(0);
} catch (\PDOException $e) {
    echo "[ERROR] Loi ket noi/cap nhat CSDL: " . $e->getMessage() . "\n";
    exit(1);
}
