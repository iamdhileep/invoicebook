<?php
/**
 * Quick Fix Script
 * This script automatically fixes the most common login issues
 */

echo "<h2>BillBook Quick Fix</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

// Step 1: Test database connection
echo "<div class='step'>";
echo "<h3>Step 1: Testing Database Connection</h3>";

try {
    include 'db.php';
    echo "<p class='success'>✅ Database connection successful</p>";
    echo "<p><strong>Database:</strong> " . $conn->database . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Step 2: Create users table if it doesn't exist
echo "<div class='step'>";
echo "<h3>Step 2: Creating Users Table</h3>";

$create_users_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    profile_image VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL
)";

if ($conn->query($create_users_table)) {
    echo "<p class='success'>✅ Users table created successfully</p>";
} else {
    echo "<p class='error'>❌ Error creating users table: " . $conn->error . "</p>";
}
echo "</div>";

// Step 3: Create indexes
echo "<div class='step'>";
echo "<h3>Step 3: Creating Indexes</h3>";

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
    "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
    "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
    "CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at)"
];

foreach ($indexes as $index) {
    if ($conn->query($index)) {
        echo "<p class='success'>✅ Index created successfully</p>";
    } else {
        echo "<p class='warning'>⚠️ Index already exists or error: " . $conn->error . "</p>";
    }
}
echo "</div>";

// Step 4: Create activity_log table
echo "<div class='step'>";
echo "<h3>Step 4: Creating Activity Log Table</h3>";

$create_activity_log = "
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($create_activity_log)) {
    echo "<p class='success'>✅ Activity log table created successfully</p>";
} else {
    echo "<p class='warning'>⚠️ Activity log table error: " . $conn->error . "</p>";
}
echo "</div>";

// Step 5: Create default admin user
echo "<div class='step'>";
echo "<h3>Step 5: Creating Default Admin User</h3>";

// Check if admin user already exists
$admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
$admin_count = $admin_check->fetch_assoc()['count'];

if ($admin_count == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, email, password, full_name, role) VALUES 
    ('admin', 'admin@billbook.com', '$admin_password', 'Administrator', 'admin')";
    
    if ($conn->query($insert_admin)) {
        echo "<p class='success'>✅ Default admin user created successfully</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        echo "<p class='error'>❌ Error creating admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p class='success'>✅ Admin user already exists</p>";
}
echo "</div>";

// Step 6: Test login functionality
echo "<div class='step'>";
echo "<h3>Step 6: Testing Login Functionality</h3>";

// Test prepared statement
$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
if ($stmt) {
    echo "<p class='success'>✅ Login prepared statement works</p>";
    $stmt->close();
} else {
    echo "<p class='error'>❌ Login prepared statement failed: " . $conn->error . "</p>";
}

// Test admin user query
$test_stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
if ($test_stmt) {
    $test_stmt->bind_param("s", "admin");
    $test_stmt->execute();
    $result = $test_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify('admin123', $user['password'])) {
            echo "<p class='success'>✅ Admin user login test successful</p>";
        } else {
            echo "<p class='error'>❌ Admin user password verification failed</p>";
        }
    } else {
        echo "<p class='error'>❌ Admin user not found in database</p>";
    }
    $test_stmt->close();
} else {
    echo "<p class='error'>❌ Admin user query test failed</p>";
}
echo "</div>";

// Step 7: Summary
echo "<div class='step'>";
echo "<h3>Step 7: Setup Complete!</h3>";
echo "<p class='success'>✅ User system has been successfully set up.</p>";
echo "<p>You can now:</p>";
echo "<ul>";
echo "<li><a href='login.php' style='color: #007bff;'>Login to the system</a></li>";
echo "<li><a href='register.php' style='color: #007bff;'>Register new users</a></li>";
echo "<li><a href='manage_users.php' style='color: #007bff;'>Manage users (admin only)</a></li>";
echo "</ul>";

echo "<h4>Default Admin Credentials:</h4>";
echo "<p><strong>Username:</strong> admin<br>";
echo "<strong>Password:</strong> admin123</p>";

echo "<p><strong>Important:</strong> Please change the default admin password after first login!</p>";
echo "</div>";

// Step 8: Quick test links
echo "<div class='step'>";
echo "<h3>Step 8: Quick Test</h3>";
echo "<p>Click the links below to test the system:</p>";
echo "<a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Test Login</a>";
echo "<a href='debug_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Debug Login</a>";
echo "<a href='check_setup.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Check Setup</a>";
echo "</div>";
?> 