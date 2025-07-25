<?php
session_start();

include 'db.php';

echo "<h2>Setting up User Permission System</h2>";

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows > 0) {
    echo "<div style='color: green;'>✅ Users table already exists!</div>";
} else {
    echo "<div style='color: orange;'>⚠️ Users table does not exist. Creating...</div>";
    
    // Create the users table and related tables
    $setup_sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'employee', 'viewer') DEFAULT 'employee',
        permissions TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL
    );
    ";
    
    if ($conn->query($setup_sql)) {
        echo "<div style='color: green;'>✅ Users table created successfully!</div>";
    } else {
        echo "<div style='color: red;'>❌ Error creating users table: " . $conn->error . "</div>";
        exit;
    }
}

// Create activity log table
$activity_table_check = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
if ($activity_table_check->num_rows > 0) {
    echo "<div style='color: green;'>✅ User activity log table already exists!</div>";
} else {
    echo "<div style='color: orange;'>⚠️ User activity log table does not exist. Creating...</div>";
    
    $activity_sql = "
    CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    if ($conn->query($activity_sql)) {
        echo "<div style='color: green;'>✅ User activity log table created successfully!</div>";
    } else {
        echo "<div style='color: red;'>❌ Error creating activity log table: " . $conn->error . "</div>";
    }
}

// Check if default users exist
$default_users_check = $conn->query("SELECT COUNT(*) as count FROM users");
$user_count = $default_users_check->fetch_assoc()['count'];

if ($user_count == 0) {
    echo "<div style='color: orange;'>⚠️ No users found. Creating default users...</div>";
    
    // Insert default users
    $default_users_sql = "
    INSERT INTO users (username, email, password, role, permissions) VALUES 
    ('admin', 'admin@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'all'),
    ('manager', 'manager@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'attendance,reports,employees,settings'),
    ('user', 'user@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'attendance');
    ";
    
    if ($conn->query($default_users_sql)) {
        echo "<div style='color: green;'>✅ Default users created successfully!</div>";
        echo "<div style='margin: 10px 0; padding: 10px; background: #e7f3ff; border: 1px solid #b3d9ff;'>";
        echo "<strong>Default Login Credentials:</strong><br>";
        echo "• Admin: username=<strong>admin</strong>, password=<strong>password</strong><br>";
        echo "• Manager: username=<strong>manager</strong>, password=<strong>password</strong><br>";
        echo "• User: username=<strong>user</strong>, password=<strong>password</strong>";
        echo "</div>";
    } else {
        echo "<div style='color: red;'>❌ Error creating default users: " . $conn->error . "</div>";
    }
} else {
    echo "<div style='color: green;'>✅ Found $user_count existing users in the system.</div>";
}

echo "<br><hr><br>";
echo "<h3>Setup Complete!</h3>";
echo "<p><a href='settings.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Settings Page</a></p>";
echo "<p><a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";

?>
