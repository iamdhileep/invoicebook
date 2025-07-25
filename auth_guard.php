<?php
// Permission Guard Helper
// Include this file at the top of pages that need permission checks

if (!isset($_SESSION)) {
    session_start();
}

// Include the user permission model if not already included
if (!class_exists('UserPermission')) {
    include_once __DIR__ . '/models/UserPermission.php';
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

// Check if user has specific permission
function checkPermission($required_permission) {
    global $conn;
    
    checkLogin();
    
    $userPermission = new UserPermission($conn);
    if (!$userPermission->validateAccess($required_permission)) {
        // Redirect to dashboard with error message
        $_SESSION['error'] = "You don't have permission to access this page.";
        header('Location: dashboard.php');
        exit;
    }
}

// Check multiple permissions (user needs at least one)
function checkAnyPermission($permissions) {
    global $conn;
    
    checkLogin();
    
    $userPermission = new UserPermission($conn);
    $user_id = $_SESSION['admin'];
    
    foreach ($permissions as $permission) {
        if ($userPermission->hasPermission($user_id, $permission)) {
            return true;
        }
    }
    
    // No permissions matched
    $_SESSION['error'] = "You don't have permission to access this page.";
    header('Location: dashboard.php');
    exit;
}

// Check all permissions (user needs all of them)
function checkAllPermissions($permissions) {
    global $conn;
    
    checkLogin();
    
    $userPermission = new UserPermission($conn);
    $user_id = $_SESSION['admin'];
    
    foreach ($permissions as $permission) {
        if (!$userPermission->hasPermission($user_id, $permission)) {
            $_SESSION['error'] = "You don't have sufficient permissions to access this page.";
            header('Location: dashboard.php');
            exit;
        }
    }
    
    return true;
}

// Get current user info
function getCurrentUserInfo() {
    global $conn;
    
    if (!isset($_SESSION['admin'])) {
        return null;
    }
    
    $userPermission = new UserPermission($conn);
    return $userPermission->getUserInfo($_SESSION['admin']);
}

// Check if current user is admin
function isCurrentUserAdmin() {
    global $conn;
    
    if (!isset($_SESSION['admin'])) {
        return false;
    }
    
    $userPermission = new UserPermission($conn);
    return $userPermission->isAdmin($_SESSION['admin']);
}

// Show permission-based navigation items
function canShowNavItem($required_permission) {
    global $conn;
    
    if (!isset($_SESSION['admin'])) {
        return false;
    }
    
    $userPermission = new UserPermission($conn);
    return $userPermission->hasPermission($_SESSION['admin'], $required_permission);
}

// Display error messages if any
function displayMessages() {
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-exclamation-triangle me-2"></i>' . $_SESSION['error'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle me-2"></i>' . $_SESSION['success'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['warning'])) {
        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-exclamation-triangle me-2"></i>' . $_SESSION['warning'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['warning']);
    }
}

// Permission constants for easy reference
class PagePermissions {
    const DASHBOARD = 'dashboard';
    const EMPLOYEES = 'employees';
    const ATTENDANCE = 'attendance';
    const INVOICES = 'invoices';
    const ITEMS = 'items';
    const REPORTS = 'reports';
    const SETTINGS = 'settings';
    const EXPORT = 'export';
    const BULK_ACTIONS = 'bulk_actions';
}

// Usage examples:
// checkLogin();                                    // Just check if logged in
// checkPermission(PagePermissions::SETTINGS);     // Check specific permission
// checkAnyPermission(['employees', 'attendance']); // Check any of these permissions
// checkAllPermissions(['export', 'reports']);     // Check all these permissions
?>
