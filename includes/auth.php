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
