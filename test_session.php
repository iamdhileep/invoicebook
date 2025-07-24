<?php
session_start();
echo "<h2>Session Test</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "All Session Variables:\n";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User ID is set: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ User ID is not set</p>";
}

if (isset($_SESSION['username'])) {
    echo "<p style='color: green;'>✅ Username is set: " . $_SESSION['username'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Username is not set</p>";
}

if (isset($_SESSION['role'])) {
    echo "<p style='color: green;'>✅ Role is set: " . $_SESSION['role'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Role is not set</p>";
}

echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
?> 