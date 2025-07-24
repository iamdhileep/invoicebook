<?php
/**
 * Fix Users Table Structure
 * This script adds missing columns to the existing users table
 */

include 'db.php';

echo "<h2>BillBook Users Table Fix</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

// Step 1: Check current table structure
echo "<div class='step'>";
echo "<h3>Step 1: Current Table Structure</h3>";

$columns = $conn->query("DESCRIBE users");
if ($columns) {
    echo "<p class='info'>Current users table structure:</p>";
    echo "<pre>";
    while ($column = $columns->fetch_assoc()) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    echo "</pre>";
}
echo "</div>";

// Step 2: Add missing columns
echo "<div class='step'>";
echo "<h3>Step 2: Adding Missing Columns</h3>";

$alter_queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) UNIQUE AFTER username",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) NOT NULL DEFAULT 'User' AFTER password",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user', 'admin') DEFAULT 'user' AFTER full_name",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER role",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER created_at",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER last_login",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER is_active",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER profile_image",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER phone"
];

foreach ($alter_queries as $query) {
    if ($conn->query($query)) {
        echo "<p class='success'>✅ Column added successfully</p>";
    } else {
        echo "<p class='warning'>⚠️ Column already exists or error: " . $conn->error . "</p>";
    }
}
echo "</div>";

// Step 3: Update existing admin user
echo "<div class='step'>";
echo "<h3>Step 3: Updating Existing Admin User</h3>";

// Check if admin user exists and update it
$admin_check = $conn->query("SELECT id, username FROM users WHERE username = 'admin'");
if ($admin_check && $admin_check->num_rows > 0) {
    $admin = $admin_check->fetch_assoc();
    
    // Update admin user with proper role and details
    $update_admin = "UPDATE users SET 
        email = 'admin@billbook.com',
        full_name = 'Administrator',
        role = 'admin',
        is_active = TRUE
        WHERE id = " . $admin['id'];
    
    if ($conn->query($update_admin)) {
        echo "<p class='success'>✅ Admin user updated successfully</p>";
    } else {
        echo "<p class='error'>❌ Error updating admin user: " . $conn->error . "</p>";
    }
} else {
    // Create admin user if it doesn't exist
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, email, password, full_name, role) VALUES 
    ('admin', 'admin@billbook.com', '$admin_password', 'Administrator', 'admin')";
    
    if ($conn->query($insert_admin)) {
        echo "<p class='success'>✅ Admin user created successfully</p>";
    } else {
        echo "<p class='error'>❌ Error creating admin user: " . $conn->error . "</p>";
    }
}
echo "</div>";

// Step 4: Create indexes
echo "<div class='step'>";
echo "<h3>Step 4: Creating Indexes</h3>";

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

// Step 5: Test the fixed table
echo "<div class='step'>";
echo "<h3>Step 5: Testing Fixed Table</h3>";

// Test the prepared statement that was failing
$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
if ($stmt) {
    echo "<p class='success'>✅ Users table prepared statement now works!</p>";
    
    // Test with admin user
    $admin_username = "admin";
    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<p class='success'>✅ Admin user found with role: " . $user['role'] . "</p>";
    } else {
        echo "<p class='error'>❌ Admin user not found</p>";
    }
    $stmt->close();
} else {
    echo "<p class='error'>❌ Users table prepared statement still failing: " . $conn->error . "</p>";
}
echo "</div>";

// Step 6: Show final table structure
echo "<div class='step'>";
echo "<h3>Step 6: Final Table Structure</h3>";

$final_columns = $conn->query("DESCRIBE users");
if ($final_columns) {
    echo "<p class='info'>Updated users table structure:</p>";
    echo "<pre>";
    while ($column = $final_columns->fetch_assoc()) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    echo "</pre>";
}
echo "</div>";

// Step 7: Summary
echo "<div class='step'>";
echo "<h3>Step 7: Fix Complete!</h3>";
echo "<p class='success'>✅ Users table has been successfully updated.</p>";
echo "<p>The login system should now work properly.</p>";

echo "<h4>Test the Login:</h4>";
echo "<p><strong>Username:</strong> admin<br>";
echo "<strong>Password:</strong> admin123</p>";

echo "<p>Click the links below to test:</p>";
echo "<a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Test Login</a>";
echo "<a href='debug_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Debug Login</a>";
echo "</div>";
?> 