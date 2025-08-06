<?php
session_start();
include 'db.php';

echo "<h2>Manual Login Test</h2>";

// Simulate login by setting session variables
$_SESSION['user_id'] = 1;
$_SESSION['admin'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

echo "✅ Session variables set:<br>";
echo "- user_id: " . $_SESSION['user_id'] . "<br>";
echo "- admin: " . $_SESSION['admin'] . "<br>";
echo "- username: " . $_SESSION['username'] . "<br>";
echo "- role: " . $_SESSION['role'] . "<br>";

echo "<hr>";
echo "<p><a href='dashboard.php'>Test Dashboard Access Now</a></p>";
echo "<p><a href='test_login.php'>Check Login Status</a></p>";

// Also test the permission system directly
echo "<h3>Testing Permission System:</h3>";
require_once 'models/UserPermission.php';

try {
    $userPerm = new UserPermission($conn);
    $hasPermission = $userPerm->hasGroupPermission(1, 'Dashboard');
    echo $hasPermission ? "✅ User has Dashboard permission" : "❌ User does not have Dashboard permission";
    echo "<br>";
} catch (Exception $e) {
    echo "❌ Permission system error: " . $e->getMessage() . "<br>";
}
?>
