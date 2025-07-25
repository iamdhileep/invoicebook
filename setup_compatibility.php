<?php
include 'db.php';

echo "<h2>🔧 System Compatibility Setup</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Ensure admin table exists
$create_admin = "CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($create_admin)) {
    echo "<div class='success'>✅ Admin table ready</div>";
} else {
    echo "<div class='error'>❌ Admin table error: " . $conn->error . "</div>";
}

// Ensure users table exists for compatibility
$create_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
)";

if ($conn->query($create_users)) {
    echo "<div class='success'>✅ Users table ready</div>";
} else {
    echo "<div class='error'>❌ Users table error: " . $conn->error . "</div>";
}

// Check admin user
$check_admin = $conn->query("SELECT COUNT(*) as count FROM admin WHERE username = 'admin'");
if ($check_admin) {
    $admin_exists = $check_admin->fetch_assoc()['count'] > 0;
    if (!$admin_exists) {
        $stmt = $conn->prepare("INSERT INTO admin (username, password, full_name) VALUES (?, ?, ?)");
        $username = 'admin';
        $password = 'admin123';
        $full_name = 'System Administrator';
        $stmt->bind_param('sss', $username, $password, $full_name);
        if ($stmt->execute()) {
            echo "<div class='success'>✅ Admin user created in admin table</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ Admin user exists in admin table</div>";
    }
}

// Check users table admin
$check_users_admin = $conn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
if ($check_users_admin) {
    $users_admin_exists = $check_users_admin->fetch_assoc()['count'] > 0;
    if (!$users_admin_exists) {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $username = 'admin';
        $email = 'admin@billbook.local';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $full_name = 'System Administrator';
        $role = 'admin';
        $stmt->bind_param('sssss', $username, $email, $password, $full_name, $role);
        if ($stmt->execute()) {
            echo "<div class='success'>✅ Admin user created in users table</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ Admin user exists in users table</div>";
    }
}

echo "<br><h3>📋 Login Credentials (Both Systems):</h3>";
echo "<strong>Username:</strong> admin<br>";
echo "<strong>Password:</strong> admin123<br>";

echo "<br><h3>🔄 Compatibility Features:</h3>";
echo "<div style='background:#e8f5e8;padding:15px;border-radius:8px;border-left:4px solid #28a745;'>";
echo "✅ <strong>Dual Authentication:</strong> Supports both \$_SESSION['admin'] and \$_SESSION['user_id']<br>";
echo "✅ <strong>Modern System:</strong> Users table with hashed passwords<br>";
echo "✅ <strong>Legacy Support:</strong> Admin table with plain passwords<br>";
echo "✅ <strong>Cross-Compatible:</strong> Works with both old and new code<br>";
echo "</div>";

echo "<br><h3>🧪 Test Login:</h3>";
echo "<a href='login.php' style='background:#2563eb;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>🔑 Test Login</a>";

echo "<br><br><div style='background:#f0f9ff;padding:15px;border-radius:8px;border-left:4px solid #2563eb;'>";
echo "<strong>🎯 System Status:</strong><br>";
echo "• Your application now supports both authentication systems<br>";
echo "• Login will work whether you have old or new code<br>";
echo "• All pages check for both session variables<br>";
echo "• Compatible with remote repository changes<br>";
echo "</div>";
?>
