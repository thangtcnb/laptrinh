<?php
/**
 * Database Migration & Table Update Script
 * Automatically loads connection parameters from config.php (or config.php.example fallback)
 */

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
    echo "[SUCCESS] Khoi tao / Cap nhat bang 'discussions' thanh cong!\n";

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
    exit(0);
} catch (\PDOException $e) {
    echo "[ERROR] Ket noi hoac cap nhat CSDL that bai: " . $e->getMessage() . "\n";
    exit(1);
}
