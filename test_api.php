<?php
session_start();

// Simulate logged in session
$_SESSION['user_id'] = 1;
$_SESSION['admin'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

// Include the API file
$_POST['action'] = 'refresh_stats';

include 'api/time_tracking_api.php';
?>
