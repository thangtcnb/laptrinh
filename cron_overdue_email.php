<?php
// CLI Cron script for Overdue Task Email Notifications
// Run via CLI: php cron_overdue_email.php
// Or setup Cron / Task Scheduler to run daily

if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    // Basic protection if called via Web
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. CLI only.";
    exit;
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/config.php.example')) {
    require_once __DIR__ . '/config.php.example';
}

if (!isset($pdo)) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $db   = defined('DB_NAME') ? DB_NAME : 'laptrinh_db';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
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
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
}
require_once __DIR__ . '/includes/overdue_email_service.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Overdue Task Email Scan...\n";

$result = checkAndSendOverdueTaskEmails($pdo);

echo "[" . date('Y-m-d H:i:s') . "] Overdue Task Scan Complete.\n";
echo "  - Overdue Tasks Found: " . ($result['overdue_tasks_found'] ?? 0) . "\n";
echo "  - Emails Sent: " . ($result['emails_sent'] ?? 0) . "\n";
echo "  - Emails Skipped (already notified today): " . ($result['emails_skipped'] ?? 0) . "\n";

if (!empty($result['error'])) {
    echo "  - Error: " . $result['error'] . "\n";
}
