<?php
session_start();

// Clear employee session
unset($_SESSION['employee_id']);
unset($_SESSION['employee_name']);
unset($_SESSION['employee_email']);

// Redirect to employee login with success message
header("Location: employee_login.php?logout=1");
exit;
?>
