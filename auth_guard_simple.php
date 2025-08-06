<?php
/**
 * Simple Auth Guard - Basic login check only
 * Include this file at the top of pages that need login protection
 */

if (!isset($_SESSION)) {
    session_start();
}

/**
 * Check if user is logged in
 */
function checkLogin() {
    if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
        // Determine the correct path to login.php based on current location
        $login_path = 'login.php';
        
        // Check if we're in a subdirectory
        $current_path = $_SERVER['PHP_SELF'];
        if (strpos($current_path, '/HRMS/') !== false) {
            $login_path = '../login.php';
        } elseif (strpos($current_path, '/pages/') !== false) {
            $login_path = '../../login.php';
        }
        
        header("Location: $login_path");
        exit;
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? $_SESSION['admin'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? $_SESSION['admin'] ?? 'User';
}

/**
 * Check if user is admin (simplified)
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Simple permission check - just checks if user is logged in
 * This replaces all the complex permission functions
 */
function checkHRMSPermission($page_file = '', $permission_type = 'VIEW') {
    checkLogin();
    return true; // If logged in, allow access
}

/**
 * Simple group permission check - just checks if user is logged in
 */
function checkGroupPermission($group_name = '', $exit_on_fail = true) {
    checkLogin();
    return true; // If logged in, allow access
}

/**
 * Redirect to login with message
 */
function redirectToLogin($message = '') {
    checkLogin();
}

/**
 * Check specific permission type (simplified)
 */
function checkPermission($permission_type = 'VIEW') {
    checkLogin();
    return true;
}

// Compatibility with old permission system
class HRMSPermissions {
    const VIEW = 'VIEW';
    const CREATE = 'CREATE';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';
}

// Auto-check login for any page that includes this file
checkLogin();
?>
