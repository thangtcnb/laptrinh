<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Get logged-in user info (with Super Admin "View As" support)
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        // Identify if the real account is Super Admin
        if (empty($_SESSION['is_real_admin'])) {
            $stmtReal = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtReal->execute([$_SESSION['user_id']]);
            $realRole = $stmtReal->fetchColumn();
            if ($realRole === 'SUPER_ADMIN') {
                $_SESSION['is_real_admin'] = true;
                $_SESSION['real_admin_user_id'] = $_SESSION['user_id'];
            }
        }

        $effectiveUserId = $_SESSION['user_id'];
        $isViewAs = false;

        // If Super Admin is viewing as another user
        if (!empty($_SESSION['is_real_admin']) && !empty($_SESSION['view_as_user_id'])) {
            $effectiveUserId = (int)$_SESSION['view_as_user_id'];
            $isViewAs = true;
        }

        $stmt = $pdo->prepare("SELECT u.*, COALESCE(d.name, 'Chưa phân công') AS department_name, COALESCE(d.code, 'N/A') AS department_code, d.color_theme, d.icon AS department_icon FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
        $stmt->execute([$effectiveUserId]);
        $user = $stmt->fetch() ?: null;

        if ($user) {
            $user['is_view_as'] = $isViewAs;
            $user['real_admin_id'] = $_SESSION['real_admin_user_id'] ?? $_SESSION['user_id'];
        }

        return $user;
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
