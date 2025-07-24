<?php
/**
 * User System Setup Script
 * This script initializes the user management system
 */

include 'db.php';

echo "<h2>BillBook User System Setup</h2>";

// Create users table
echo "<h3>1. Creating users table...</h3>";
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
    echo "✅ Users table created successfully<br>";
} else {
    echo "❌ Error creating users table: " . $conn->error . "<br>";
}

// Create indexes
echo "<h3>2. Creating indexes...</h3>";
$indexes = [
    "CREATE INDEX idx_users_username ON users(username)",
    "CREATE INDEX idx_users_email ON users(email)",
    "CREATE INDEX idx_users_role ON users(role)",
    "CREATE INDEX idx_users_created_at ON users(created_at)"
];

foreach ($indexes as $index) {
    if ($conn->query($index)) {
        echo "✅ Index created successfully<br>";
    } else {
        echo "⚠️ Index already exists or error: " . $conn->error . "<br>";
    }
}

// Create activity_log table
echo "<h3>3. Creating activity_log table...</h3>";
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
    echo "✅ Activity log table created successfully<br>";
} else {
    echo "❌ Error creating activity log table: " . $conn->error . "<br>";
}

// Insert default admin user
echo "<h3>4. Creating default admin user...</h3>";
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$insert_admin = "INSERT INTO users (username, email, password, full_name, role) VALUES 
('admin', 'admin@billbook.com', '$admin_password', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE username=username";

if ($conn->query($insert_admin)) {
    echo "✅ Default admin user created successfully<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
} else {
    echo "❌ Error creating admin user: " . $conn->error . "<br>";
}

// Check if admin table exists and migrate data
echo "<h3>5. Checking for existing admin table...</h3>";
$check_admin_table = "SHOW TABLES LIKE 'admin'";
$result = $conn->query($check_admin_table);

if ($result->num_rows > 0) {
    echo "✅ Admin table found. Migrating data...<br>";
    
    // Get admin users from old table
    $old_admins = $conn->query("SELECT * FROM admin");
    
    while ($admin = $old_admins->fetch_assoc()) {
        // Check if admin already exists in users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $admin['username']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows == 0) {
            // Create new user from old admin
            $hashed_password = password_hash($admin['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'admin')");
            $email = $admin['username'] . '@billbook.com';
            $full_name = ucfirst($admin['username']);
            $stmt->bind_param("ssss", $admin['username'], $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                echo "✅ Migrated admin user: " . $admin['username'] . "<br>";
            } else {
                echo "❌ Error migrating admin user: " . $admin['username'] . "<br>";
            }
        } else {
            echo "⚠️ Admin user already exists: " . $admin['username'] . "<br>";
        }
    }
} else {
    echo "ℹ️ No existing admin table found<br>";
}

// Display current users
echo "<h3>6. Current users in system:</h3>";
$users = $conn->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY id");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Created</th></tr>";

while ($user = $users->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
    echo "<td>" . ucfirst($user['role']) . "</td>";
    echo "<td>" . $user['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>7. Setup Complete!</h3>";
echo "<p>✅ User system has been successfully set up.</p>";
echo "<p>You can now:</p>";
echo "<ul>";
echo "<li><a href='login.php'>Login to the system</a></li>";
echo "<li><a href='register.php'>Register new users</a></li>";
echo "<li><a href='manage_users.php'>Manage users (admin only)</a></li>";
echo "</ul>";

echo "<h4>Default Admin Credentials:</h4>";
echo "<p><strong>Username:</strong> admin<br>";
echo "<strong>Password:</strong> admin123</p>";

echo "<p><strong>Important:</strong> Please change the default admin password after first login!</p>";
?> 