<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to HRMS portal with logout message
header("Location: hrms_portal.php?logout=1");
exit;
?>
