<?php
// HRMS Authentication Guard
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

// Set user variables for compatibility
if (isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user'];
}

// Set role if not exists
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'employee'; // Default role
}

// Set employee_id for benefits and other modules
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = $_SESSION['user_id'] ?? 1;
}

// Define current user info for HRMS modules
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['user'] ?? 1;
$current_user_role = $_SESSION['role'] ?? 'employee';
$current_employee_id = $_SESSION['employee_id'] ?? $current_user_id;
?>
