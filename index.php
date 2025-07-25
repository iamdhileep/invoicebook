<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Redirect to the new dashboard structure
header("Location: pages/dashboard/dashboard.php");
exit;
?>