<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Fast role switcher handling via query parameter for convenient testing
if (isset($_GET['switch_user_id']) && is_numeric($_GET['switch_user_id'])) {
    $switchId = (int)$_GET['switch_user_id'];
    $stmt = $pdo->prepare("SELECT u.*, d.name AS department_name, d.code AS department_code FROM users u JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
    $stmt->execute([$switchId]);
    $targetUser = $stmt->fetch();
    if ($targetUser) {
        $_SESSION['user_id'] = $targetUser['id'];
        // Remove switch_user_id parameter and redirect back
        $currentUri = preg_replace('/[?&]switch_user_id=\d+/', '', $_SERVER['REQUEST_URI']);
        if ($currentUri === '') {
            $currentUri = 'index.php';
        }
        header("Location: " . $currentUri);
        exit;
    }
}

// Get logged-in user info
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT u.*, d.name AS department_name, d.code AS department_code, d.color_theme, d.icon AS department_icon FROM users u JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
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

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
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
