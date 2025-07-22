<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Redirect to the new dashboard structure
header("Location: pages/dashboard/dashboard.php");
exit;
?>