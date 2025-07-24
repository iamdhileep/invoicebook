<?php
/**
 * Debug Login Issues
 * This script helps identify the exact cause of login problems
 */

echo "<h2>BillBook Login Debug</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

// Step 1: Check if db.php exists
echo "<h3>Step 1: Database Configuration</h3>";
if (file_exists('db.php')) {
    echo "<p class='success'>✅ db.php file exists</p>";
} else {
    echo "<p class='error'>❌ db.php file not found</p>";
    exit;
}

// Step 2: Try to include db.php
echo "<h3>Step 2: Database Connection</h3>";
try {
    include 'db.php';
    echo "<p class='success'>✅ db.php included successfully</p>";
    
    // Test connection
    if (isset($conn)) {
        if ($conn->connect_error) {
            echo "<p class='error'>❌ Database connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='success'>✅ Database connection successful</p>";
        }
    } else {
        echo "<p class='error'>❌ \$conn variable not set</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error including db.php: " . $e->getMessage() . "</p>";
}

// Step 3: Check database tables
echo "<h3>Step 3: Database Tables</h3>";
if (isset($conn) && !$conn->connect_error) {
    // Check users table
    $users_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($users_check && $users_check->num_rows > 0) {
        echo "<p class='success'>✅ Users table exists</p>";
        
        // Check users table structure
        $columns = $conn->query("DESCRIBE users");
        if ($columns) {
            echo "<p class='info'>Users table structure:</p>";
            echo "<pre>";
            while ($column = $columns->fetch_assoc()) {
                echo $column['Field'] . " - " . $column['Type'] . "\n";
            }
            echo "</pre>";
        }
        
        // Check if admin user exists
        $admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        if ($admin_check) {
            $admin_count = $admin_check->fetch_assoc()['count'];
            if ($admin_count > 0) {
                echo "<p class='success'>✅ Admin user exists</p>";
            } else {
                echo "<p class='warning'>⚠️ Admin user not found</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ Users table does not exist</p>";
    }
    
    // Check admin table (legacy)
    $admin_table_check = $conn->query("SHOW TABLES LIKE 'admin'");
    if ($admin_table_check && $admin_table_check->num_rows > 0) {
        echo "<p class='info'>ℹ️ Legacy admin table exists</p>";
        
        // Show admin users
        $admins = $conn->query("SELECT * FROM admin");
        if ($admins && $admins->num_rows > 0) {
            echo "<p class='info'>Admin users in legacy table:</p>";
            echo "<pre>";
            while ($admin = $admins->fetch_assoc()) {
                echo "ID: " . $admin['id'] . ", Username: " . $admin['username'] . "\n";
            }
            echo "</pre>";
        }
    } else {
        echo "<p class='info'>ℹ️ No legacy admin table found</p>";
    }
} else {
    echo "<p class='error'>❌ Cannot check tables - database connection failed</p>";
}

// Step 4: Test prepared statements
echo "<h3>Step 4: Prepared Statement Test</h3>";
if (isset($conn) && !$conn->connect_error) {
    // Test users table query
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    if ($stmt) {
        echo "<p class='success'>✅ Users table prepared statement works</p>";
        $stmt->close();
    } else {
        echo "<p class='error'>❌ Users table prepared statement failed: " . $conn->error . "</p>";
    }
    
    // Test admin table query
    $admin_stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
    if ($admin_stmt) {
        echo "<p class='success'>✅ Admin table prepared statement works</p>";
        $admin_stmt->close();
    } else {
        echo "<p class='warning'>⚠️ Admin table prepared statement failed: " . $conn->error . "</p>";
    }
}

// Step 5: Recommendations
echo "<h3>Step 5: Recommendations</h3>";

$users_exists = false;
$admin_exists = false;

if (isset($conn) && !$conn->connect_error) {
    $users_check = $conn->query("SHOW TABLES LIKE 'users'");
    $users_exists = $users_check && $users_check->num_rows > 0;
    
    $admin_check = $conn->query("SHOW TABLES LIKE 'admin'");
    $admin_exists = $admin_check && $admin_check->num_rows > 0;
}

if (!$users_exists && !$admin_exists) {
    echo "<p class='error'>❌ No user tables found!</p>";
    echo "<p>Solution: Run the setup script to create the users table.</p>";
    echo "<a href='setup_user_system.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Setup Script</a>";
} elseif (!$users_exists && $admin_exists) {
    echo "<p class='warning'>⚠️ Only legacy admin table exists</p>";
    echo "<p>Solution: Migrate to the new user system.</p>";
    echo "<a href='setup_user_system.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Migrate to New System</a>";
} elseif ($users_exists) {
    echo "<p class='success'>✅ New user system is properly configured</p>";
    echo "<a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
}

echo "<h3>Step 6: Quick Fix</h3>";
echo "<p>If you're still having issues, try these steps:</p>";
echo "<ol>";
echo "<li><a href='setup_user_system.php'>Run Setup Script</a></li>";
echo "<li><a href='check_setup.php'>Run Setup Check</a></li>";
echo "<li><a href='login.php'>Test Login</a></li>";
echo "</ol>";

echo "<h3>Step 7: Database Info</h3>";
if (isset($conn) && !$conn->connect_error) {
    echo "<p><strong>Database:</strong> " . $conn->database . "</p>";
    echo "<p><strong>Server:</strong> " . $conn->server_info . "</p>";
    echo "<p><strong>Client:</strong> " . $conn->client_info . "</p>";
}
?> 