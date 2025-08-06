<?php
session_start();

// Set up a test session to access the Employee Portal
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'employee';
$_SESSION['user_name'] = 'John Doe';
$_SESSION['employee_id'] = 'EMP001';

echo "Test session created:\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "User Role: " . $_SESSION['user_role'] . "\n";
echo "User Name: " . $_SESSION['user_name'] . "\n";
echo "Employee ID: " . $_SESSION['employee_id'] . "\n";
echo "\nYou can now access the HRMS portals!\n";
?>
