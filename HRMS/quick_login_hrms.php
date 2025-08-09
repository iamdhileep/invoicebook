<?php
session_start();
include '../db.php';

echo "<h3>Quick Login for HRMS Department Management</h3>";

if ($conn) {
    // Try to login as admin first
    $admin_result = mysqli_query($conn, "SELECT * FROM admins LIMIT 1");
    if ($admin_result && $admin = mysqli_fetch_assoc($admin_result)) {
        $_SESSION['admin'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        echo "<p style='color: green;'>✅ Logged in as admin: " . $admin['username'] . "</p>";
    } else {
        // Fallback to regular user
        $user_result = mysqli_query($conn, "SELECT * FROM users LIMIT 1");
        if ($user_result && $user = mysqli_fetch_assoc($user_result)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo "<p style='color: green;'>✅ Logged in as user: " . $user['username'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ No users found in database</p>";
        }
    }
    
    echo "<p><a href='department_management.php'>Go to Department Management</a></p>";
    
    // Auto redirect after 2 seconds
    echo "<script>setTimeout(() => { window.location.href = 'department_management.php'; }, 2000);</script>";
    
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
}
?>
