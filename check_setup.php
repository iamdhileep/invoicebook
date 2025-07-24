<?php
/**
 * Setup Check Script
 * This script checks if the user system is properly set up
 */

include 'db.php';

echo "<h2>BillBook Setup Check</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
    .btn:hover { background: #0056b3; }
</style>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
if ($conn->connect_error) {
    echo "<p class='error'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p class='success'>✅ Database connection successful</p>";
}

// Check if users table exists
echo "<h3>2. Users Table</h3>";
$users_table = $conn->query("SHOW TABLES LIKE 'users'");
if ($users_table->num_rows > 0) {
    echo "<p class='success'>✅ Users table exists</p>";
    
    // Check table structure
    $columns = $conn->query("DESCRIBE users");
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($column = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if admin user exists
    $admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $admin_count = $admin_check->fetch_assoc()['count'];
    if ($admin_count > 0) {
        echo "<p class='success'>✅ Admin user exists</p>";
    } else {
        echo "<p class='warning'>⚠️ Admin user not found</p>";
    }
} else {
    echo "<p class='error'>❌ Users table does not exist</p>";
    echo "<p class='info'>You need to run the setup script to create the users table.</p>";
    echo "<a href='setup_user_system.php' class='btn'>Run Setup Script</a>";
}

// Check if admin table exists (legacy)
echo "<h3>3. Legacy Admin Table</h3>";
$admin_table = $conn->query("SHOW TABLES LIKE 'admin'");
if ($admin_table->num_rows > 0) {
    echo "<p class='info'>ℹ️ Legacy admin table exists</p>";
    
    // Show admin users
    $admins = $conn->query("SELECT * FROM admin");
    if ($admins->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Password</th></tr>";
        while ($admin = $admins->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $admin['id'] . "</td>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . str_repeat('*', strlen($admin['password'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='info'>ℹ️ No legacy admin table found</p>";
}

// Check activity_log table
echo "<h3>4. Activity Log Table</h3>";
$activity_table = $conn->query("SHOW TABLES LIKE 'activity_log'");
if ($activity_table->num_rows > 0) {
    echo "<p class='success'>✅ Activity log table exists</p>";
} else {
    echo "<p class='warning'>⚠️ Activity log table does not exist</p>";
}

// Summary and recommendations
echo "<h3>5. Summary & Recommendations</h3>";

$users_exists = $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0;
$admin_exists = $conn->query("SHOW TABLES LIKE 'admin'")->num_rows > 0;

if (!$users_exists && !$admin_exists) {
    echo "<p class='error'>❌ No user tables found. System needs complete setup.</p>";
    echo "<a href='setup_user_system.php' class='btn'>Run Complete Setup</a>";
} elseif (!$users_exists && $admin_exists) {
    echo "<p class='warning'>⚠️ Legacy system detected. Migrate to new user system.</p>";
    echo "<a href='setup_user_system.php' class='btn'>Migrate to New System</a>";
} elseif ($users_exists) {
    echo "<p class='success'>✅ New user system is properly configured.</p>";
    echo "<a href='login.php' class='btn'>Go to Login</a>";
    echo "<a href='manage_users.php' class='btn'>Manage Users</a>";
}

echo "<h3>6. Quick Actions</h3>";
echo "<a href='setup_user_system.php' class='btn'>Setup User System</a>";
echo "<a href='login.php' class='btn'>Test Login</a>";
echo "<a href='register.php' class='btn'>Test Registration</a>";

echo "<h3>7. Default Credentials</h3>";
echo "<p><strong>Username:</strong> admin<br>";
echo "<strong>Password:</strong> admin123</p>";

echo "<p><strong>Note:</strong> If you're still having issues, please:</p>";
echo "<ol>";
echo "<li>Run the setup script first</li>";
echo "<li>Check your database connection settings in db.php</li>";
echo "<li>Ensure your web server has write permissions</li>";
echo "<li>Check PHP error logs for additional details</li>";
echo "</ol>";
?> 