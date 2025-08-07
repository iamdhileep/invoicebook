<?php
session_start();

// Create a test session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'test_user';

echo "<h3>Test Session Created</h3>";
echo "<p>Session variables set:</p>";
echo "<ul>";
echo "<li>user_id: " . $_SESSION['user_id'] . "</li>";
echo "<li>role: " . $_SESSION['role'] . "</li>";
echo "<li>username: " . $_SESSION['username'] . "</li>";
echo "</ul>";

echo "<p><a href='employee_directory.php'>→ Now test Employee Directory</a></p>";

require_once '../layouts/footer.php';
?>