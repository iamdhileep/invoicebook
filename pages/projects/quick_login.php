<?php
session_start();
include '../../db.php';

$admin_id = intval($_GET['admin_id'] ?? 0);

if ($admin_id > 0 && $conn) {
    $result = mysqli_query($conn, "SELECT * FROM admins WHERE id = $admin_id");
    if ($result && $admin = mysqli_fetch_assoc($result)) {
        $_SESSION['admin'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'] ?? '';
        
        echo "<h3>✅ Login Successful</h3>";
        echo "<p>Logged in as: " . $admin['username'] . "</p>";
        echo "<p><a href='project_dashboard.php'>Go to Project Dashboard</a></p>";
        echo "<p><a href='debug_dashboard.php'>Check Debug Info</a></p>";
        
        // Auto redirect after 3 seconds
        echo "<script>setTimeout(() => { window.location.href = 'project_dashboard.php'; }, 3000);</script>";
    } else {
        echo "<p style='color: red;'>Admin not found</p>";
    }
} else {
    echo "<p style='color: red;'>Invalid admin ID</p>";
}
?>
