<?php
session_start();

// Simulate logged in session
$_SESSION['user_id'] = 1;
$_SESSION['admin'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'refresh_stats';

// Capture output
ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

echo "API Response:\n";
echo $output;
?>
