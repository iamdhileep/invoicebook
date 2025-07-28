<?php
// Initialize session for HR dashboard access
session_start();

// Set admin session
$_SESSION['admin'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "Session initialized for HR dashboard access.<br>";
echo "You can now access the HR dashboard at: <a href='pages/hr/hr_dashboard.php'>HR Dashboard</a><br><br>";
echo "Other links:<br>";
echo "<a href='test_hr_api.php'>Test HR API</a><br>";
echo "<a href='timesheet_access.php'>Timesheet Access</a><br>";
echo "<a href='analytics_dashboard.php'>Analytics Dashboard</a><br>";
?>
