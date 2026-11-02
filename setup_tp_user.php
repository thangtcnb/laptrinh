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

    $hash = password_hash('tp', PASSWORD_BCRYPT);
    $fullname = 'Trưởng phòng HCTV';
    $email = 'tp';
    $role = 'TRUONG_PHONG';
    $deptId = 3;

    // Kiem tra ton tai
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpdate = $pdo->prepare("UPDATE users SET fullname = ?, password = ?, role = ?, department_id = ? WHERE id = ?");
        $stmtUpdate->execute([$fullname, $hash, $role, $deptId, $existing['id']]);
        echo "[SUCCESS] Da cap nhat tai khoan Truong phong (ID: {$existing['id']}): username: tp / password: tp\n";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO users (fullname, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([$fullname, $email, $hash, $role, $deptId]);
        $newId = $pdo->lastInsertId();
        echo "[SUCCESS] Da tao moi tai khoan Truong phong thanh cong (ID: {$newId}): username: tp / password: tp\n";
    }
    exit(0);
} catch (\PDOException $e) {
    echo "[ERROR] Loi ket noi/cap nhat CSDL: " . $e->getMessage() . "\n";
    exit(1);
}
