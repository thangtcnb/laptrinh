<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Get logged-in user info
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT u.*, COALESCE(d.name, 'Chưa phân công') AS department_name, COALESCE(d.code, 'N/A') AS department_code, d.color_theme, d.icon AS department_icon FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (\PDOException $e) {
        return null;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in & Check Session Timeout (1 hour = 3600 seconds)
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    $timeout = 3600; // 1 hour idle session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Check user role authorization
function hasRole($roles) {
    global $pdo;
    $user = getCurrentUser($pdo);
    if (!$user) return false;

    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }
    return $user['role'] === $roles;
}
