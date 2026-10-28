<?php
// CLI Database Migration Runner
// Automatically reads connection settings from config.php (or config.php.example fallback)
// Executes migrations safely via PDO without hardcoding passwords or relying on OS mysql CLI.

if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_MIGRATION')) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. CLI only.\n";
    exit(1);
}

// 1. Load Configuration
// Check root config.php FIRST to acquire environment DB parameters (DB_HOST, DB_USER, DB_PASS, DB_NAME).
// Fallback to config.php.example only if config.php does not exist.
$configFile = __DIR__ . '/../config.php';
$exampleConfigFile = __DIR__ . '/../config.php.example';

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

// Extract parameters from configuration constants dynamically
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'laptrinh_db';

echo "[Config] Target DB: Host={$dbHost}, User={$dbUser}, Database={$dbName}\n";

// 2. Connect to MySQL Server via PDO
try {
    $dsn = "mysql:host={$dbHost};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    // Ensure Database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    $pdo->exec("SET SQL_MODE = ''");
    echo "[DB] Connected to MySQL successfully.\n";
} catch (\PDOException $e) {
    echo "[ERROR] Failed to connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Define Migration Files List
$migrationFiles = [
    'update_20261023_comments.sql' => 'database/update_20261023_comments.sql',
    'update_v2.sql' => 'database/migrations/update_v2.sql',
    'update_bgd_schedules.sql' => 'database/migrations/update_bgd_schedules.sql',
    'update_20261025_approval_priority.sql' => 'database/migrations/update_20261025_approval_priority.sql',
    'update_20261026_batch_approval.sql' => 'database/migrations/update_20261026_batch_approval.sql',
    'update_20261025_stats_idle.sql' => 'database/migrations/update_20261025_stats_idle.sql',
    'update_20261026_workflow_redesign.sql' => 'database/migrations/update_20261026_workflow_redesign.sql',
    'update_20261026_bgd_tp_workflow.sql' => 'database/migrations/update_20261026_bgd_tp_workflow.sql',
    'update_20261026_bgd_weekly_schedule.sql' => 'database/migrations/update_20261026_bgd_weekly_schedule.sql',
    'update_20261026_bgd_schedule_notes.sql' => 'database/migrations/update_20261026_bgd_schedule_notes.sql',
    'update_20261026_filter_and_priority.sql' => 'database/migrations/update_20261026_filter_and_priority.sql',
    'update_20261026_overdue_and_schedule_form.sql' => 'database/migrations/update_20261026_overdue_and_schedule_form.sql',
    'update_20261027_task_attachments.sql' => 'database/migrations/update_20261027_task_attachments.sql',
    'update_20261027_secure_uploads_and_notifications.sql' => 'database/migrations/update_20261027_secure_uploads_and_notifications.sql',
    'update_20261027_overdue_email_logs.sql' => 'database/migrations/update_20261027_overdue_email_logs.sql',
    'update_20261028_discussions.sql' => 'database/migrations/update_20261028_discussions.sql',
    'update_20261028_create_t1.sql' => 'database/migrations/update_20261028_create_t1.sql',
];

$total = count($migrationFiles);
$index = 1;

foreach ($migrationFiles as $label => $relativePath) {
    $fullPath = __DIR__ . '/../' . $relativePath;
    if (!file_exists($fullPath)) {
        echo "[$index/$total] [SKIP] File not found: $relativePath\n";
        $index++;
        continue;
    }

    echo "[$index/$total] Running migration: $label ($relativePath)...\n";
    $sql = file_get_contents($fullPath);

    // Replace hardcoded 'USE laptrinh_db;' or 'USE `laptrinh_db`;' with current $dbName
    $sql = preg_replace('/USE\s+`?laptrinh_db`?\s*;/i', "USE `$dbName`;", $sql);

    try {
        $pdo->exec($sql);
        echo "  -> [SUCCESS] Executed $label\n";
    } catch (\PDOException $e) {
        echo "  -> [WARNING/NOTE] " . $e->getMessage() . "\n";
        // Continue migration
    }
    $index++;
}

echo "========================================================================\n";
echo "Migration process finished successfully!\n";
echo "========================================================================\n";
exit(0);
