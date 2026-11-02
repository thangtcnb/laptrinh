<?php
/**
 * Database Migration & Table Update Script
 * Automatically loads connection parameters from config.php (or config.php.example fallback)
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>Cập Nhật Cơ Sở Dữ Liệu</title>';
    echo '<style>body{background:#0f172a;color:#f8fafc;font-family:Consolas,monospace;padding:24px;line-height:1.6;} pre{background:#1e293b;padding:16px;border-radius:10px;overflow-x:auto;} .btn{display:inline-block;padding:10px 20px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:16px;}</style></head><body>';
    echo '<h2 style="color:#60a5fa;margin-top:0;">TIẾN TRÌNH CẬP NHẬT CƠ SỞ DỮ LIỆU UFM</h2><pre>';
}

$configFile = __DIR__ . '/config.php';
$exampleConfigFile = __DIR__ . '/config.php.example';

if (file_exists($configFile)) {
    require_once $configFile;
    echo "[Config] Loaded connection parameters from config.php\n";
} elseif (file_exists($exampleConfigFile)) {
    require_once $exampleConfigFile;
    echo "[Config] Loaded connection parameters from config.php.example (fallback)\n";
} else {
    echo "[ERROR] Neither config.php nor config.php.example was found!\n";
    if (php_sapi_name() !== 'cli') {
        echo '</pre><a href="index.php" class="btn" style="background:#ef4444;">Trở về Trang Chủ</a></body></html>';
    }
    exit(1);
}

// Extract connection parameters from defined constants
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname = defined('DB_NAME') ? DB_NAME : 'laptrinh_db';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$port = 3306;

if (strpos($host, ':') !== false) {
    list($host, $port) = explode(':', $host, 2);
}

echo "========================================================================\n";
echo "CAP NHAT CO SO DU LIEU - DONG BO TU CONFIG.PHP\n";
echo "========================================================================\n";
echo "[DB Connection] Host={$host}, Port={$port}, Database={$dbname}, User={$username}\n";

try {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];
    $pdo = new PDO($dsn, $username, $password, $options);

    // Ensure database exists and select it
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // DDL: Create 'discussions' table if not exists
    $sqlDiscussions = "CREATE TABLE IF NOT EXISTS discussions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlDiscussions);

    // Check if 'message' column exists in 'discussions' table, if not add it
    $stmtCheckMsg = $pdo->query("SHOW COLUMNS FROM `discussions` LIKE 'message'");
    if ($stmtCheckMsg->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `discussions` ADD COLUMN `message` TEXT NOT NULL");
        echo "[UPDATE] Da bổ sung cot 'message' vao bang 'discussions'.\n";
    }

    // Check if legacy 'content' column exists and copy data to 'message' if needed
    $stmtCheckContent = $pdo->query("SHOW COLUMNS FROM `discussions` LIKE 'content'");
    if ($stmtCheckContent->rowCount() > 0) {
        $pdo->exec("UPDATE `discussions` SET `message` = `content` WHERE (`message` IS NULL OR `message` = '') AND `content` IS NOT NULL AND `content` != ''");
        echo "[UPDATE] Da dong bo du lieu tu cot legacy 'content' sang 'message'.\n";
    }

    echo "[SUCCESS] Khoi tao / Cap nhat bang 'discussions' thanh cong!\n";

    // DDL: Create 't1' table if not exists
    $sqlT1 = "CREATE TABLE IF NOT EXISTS t1 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlT1);
    echo "[SUCCESS] Khoi tao / Cap nhat bang 't1' thanh cong!\n";

    // Ensure Truong Phong (tp / tp) account exists and is synchronized
    $hashTp = '$2y$10$71WiWQKWK9jIU05VNPq23O/uacRWwQ89qt2yUjRha7IeTxaXKS.y2';
    $stmtTp = $pdo->prepare("SELECT id FROM users WHERE email = 'tp' OR email = 'tp@ufm.edu.vn'");
    $stmtTp->execute();
    $existingTp = $stmtTp->fetch();
    if ($existingTp) {
        $stmtUpdateTp = $pdo->prepare("UPDATE users SET fullname = 'Trưởng phòng HCTV', password = ?, role = 'TRUONG_PHONG', department_id = 3 WHERE id = ?");
        $stmtUpdateTp->execute([$hashTp, $existingTp['id']]);
        echo "[SUCCESS] Da dong bo tai khoan Truong phong (ID: {$existingTp['id']}): username: tp / password: tp\n";
    } else {
        $stmtInsertTp = $pdo->prepare("INSERT INTO users (fullname, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)");
        $stmtInsertTp->execute(['Trưởng phòng HCTV', 'tp', $hashTp, 'TRUONG_PHONG', 3]);
        echo "[SUCCESS] Da tao moi tai khoan Truong phong: username: tp / password: tp\n";
    }

    // Execute additional migrations runner if present
    if (file_exists(__DIR__ . '/database/run_migrations.php')) {
        if (!defined('ALLOW_WEB_MIGRATION')) {
            define('ALLOW_WEB_MIGRATION', true);
        }
        echo "------------------------------------------------------------------------\n";
        echo "Dang chay cac migration bo sung trong database/...\n";
        require_once __DIR__ . '/database/run_migrations.php';
    }

    echo "========================================================================\n";
    echo "CAP NHAT CSDL HOAN TAT THANH CONG!\n";
    echo "========================================================================\n";
    if (php_sapi_name() !== 'cli') {
        echo '</pre><a href="index.php" class="btn">Trở về Trang Chủ</a></body></html>';
    }
    exit(0);
} catch (\PDOException $e) {
    echo "[ERROR] Ket noi hoac cap nhat CSDL that bai: " . $e->getMessage() . "\n";
    if (php_sapi_name() !== 'cli') {
        echo '</pre><a href="index.php" class="btn" style="background:#ef4444;">Trở về Trang Chủ</a></body></html>';
    }
    exit(1);
}
