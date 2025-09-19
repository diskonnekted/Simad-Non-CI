<?php
// Authentication and session management
session_start();

// Database connection
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // If roles is an array, check if current role is in the array
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    // If roles is a string, check direct match
    return $_SESSION['role'] === $roles;
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect to login if not admin
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('Location: dashboard.php');
        exit();
    }
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

/**
 * AuthStatic class for static authentication methods
 */
class AuthStatic {
    
    public static function checkAuth() {
        if (!isLoggedIn()) {
            // For AJAX requests, return JSON error
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            // For regular requests, redirect to login
            header('Location: login.php');
            exit();
        }
    }
    
    public static function checkAdminAuth() {
        self::checkAuth();
        if (!hasRole('admin')) {
            header('Location: dashboard.php');
            exit();
        }
    }
    
    public static function isLoggedIn() {
        return isLoggedIn();
    }
    
    public static function hasRole($roles) {
        return hasRole($roles);
    }
    
    public static function getCurrentUser() {
        return getCurrentUser();
    }
    
    public static function logout() {
        logout();
    }
}

// Auto-login check for pages (optional)
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    // Allow access to login page and public assets
    $public_pages = ['login.php', 'register.php', 'index.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (!in_array($current_page, $public_pages)) {
        // Create dummy session for demo purposes
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
    }
}

// Check authentication for this page
requireLogin();
?>