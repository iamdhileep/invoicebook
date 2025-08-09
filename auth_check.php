<?php
/**
 * Authentication Check for Billbook HRMS
 * This file handles user authentication verification
 */

// Start session if not already started
if (!isset($_SESSION)) {
    session_start();
}

/**
 * Check if user is logged in and redirect to login if not
 */
function checkAuth() {
    // Check for admin session (primary auth method)
    if (isset($_SESSION['admin'])) {
        return true;
    }
    
    // Check for user_id session (alternative auth method)
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Check for employee login session
    if (isset($_SESSION['employee_id'])) {
        return true;
    }
    
    // No valid session found, redirect to login
    redirectToLogin();
    return false;
}

/**
 * Redirect to appropriate login page based on current location
 */
function redirectToLogin() {
    $current_path = $_SERVER['PHP_SELF'];
    $login_url = 'login.php';
    
    // If we're in a subdirectory, adjust the path
    if (strpos($current_path, '/pages/') !== false) {
        $login_url = '../../login.php';
    } elseif (strpos($current_path, '/HRMS/') !== false) {
        $login_url = '../login.php';
    } elseif (strpos($current_path, '/api/') !== false) {
        $login_url = '../login.php';
    }
    
    header("Location: $login_url");
    exit();
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (isset($_SESSION['admin'])) {
        return [
            'type' => 'admin',
            'id' => $_SESSION['admin'],
            'username' => $_SESSION['username'] ?? 'Admin'
        ];
    }
    
    if (isset($_SESSION['user_id'])) {
        return [
            'type' => 'user',
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'User'
        ];
    }
    
    if (isset($_SESSION['employee_id'])) {
        return [
            'type' => 'employee',
            'id' => $_SESSION['employee_id'],
            'username' => $_SESSION['employee_name'] ?? 'Employee'
        ];
    }
    
    return null;
}

/**
 * Check if user has admin privileges
 */
function isAdmin() {
    return isset($_SESSION['admin']);
}

/**
 * Check if user is an employee
 */
function isEmployee() {
    return isset($_SESSION['employee_id']);
}

/**
 * Get user role
 */
function getUserRole() {
    if (isAdmin()) {
        return 'admin';
    } elseif (isEmployee()) {
        return 'employee';
    } elseif (isset($_SESSION['user_id'])) {
        return 'user';
    }
    return 'guest';
}

// Automatically check authentication when this file is included
checkAuth();

?>
