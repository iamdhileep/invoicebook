<?php
session_start();
include 'db.php';

echo "<h2>Settings Page Diagnostic Test</h2>";

echo "<h3>1. Session Check:</h3>";
if (isset($_SESSION['admin'])) {
    echo "<div style='color: green;'>✅ Admin session exists: " . $_SESSION['admin'] . "</div>";
} else {
    echo "<div style='color: red;'>❌ No admin session found</div>";
}

echo "<h3>2. Database Connection:</h3>";
if ($conn) {
    echo "<div style='color: green;'>✅ Database connected successfully</div>";
} else {
    echo "<div style='color: red;'>❌ Database connection failed</div>";
}

echo "<h3>3. Users Table Check:</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<div style='color: green;'>✅ Users table exists</div>";
    
    // Check permissions column
    $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
    if ($columns_check && $columns_check->num_rows > 0) {
        echo "<div style='color: green;'>✅ Permissions column exists</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ Permissions column missing</div>";
    }
    
    // Show all columns
    echo "<h4>Table Columns:</h4>";
    $columns_result = $conn->query("DESCRIBE users");
    if ($columns_result) {
        echo "<ul>";
        while ($row = $columns_result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['Field']) . " (" . htmlspecialchars($row['Type']) . ")</li>";
        }
        echo "</ul>";
    }
    
} else {
    echo "<div style='color: red;'>❌ Users table does not exist</div>";
}

echo "<h3>4. Permission Check Test:</h3>";
if (isset($_SESSION['admin'])) {
    $user_id = $_SESSION['admin'];
    
    // Test the permission query that was causing the error
    $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
    $has_permissions_column = $columns_check->num_rows > 0;
    
    if ($has_permissions_column) {
        echo "<div style='color: blue;'>Testing with permissions column...</div>";
        $permission_check = $conn->prepare("SELECT * FROM users WHERE id = ? AND (role = 'admin' OR permissions LIKE '%settings%')");
    } else {
        echo "<div style='color: blue;'>Testing without permissions column...</div>";
        $permission_check = $conn->prepare("SELECT * FROM users WHERE id = ?");
    }
    
    if ($permission_check === false) {
        echo "<div style='color: red;'>❌ SQL Prepare Error: " . $conn->error . "</div>";
    } else {
        $permission_check->bind_param('i', $user_id);
        if ($permission_check->execute()) {
            $user = $permission_check->get_result()->fetch_assoc();
            if ($user) {
                echo "<div style='color: green;'>✅ Permission check successful!</div>";
                echo "<div>User found: " . htmlspecialchars($user['username'] ?? 'Unknown') . "</div>";
                echo "<div>Role: " . htmlspecialchars($user['role'] ?? 'Not set') . "</div>";
                echo "<div>Permissions: " . htmlspecialchars($user['permissions'] ?? 'Not set') . "</div>";
            } else {
                echo "<div style='color: orange;'>⚠️ No user found with ID: $user_id</div>";
            }
        } else {
            echo "<div style='color: red;'>❌ SQL Execute Error: " . $permission_check->error . "</div>";
        }
    }
} else {
    echo "<div style='color: red;'>❌ Cannot test - no admin session</div>";
}

echo "<h3>5. All Users List:</h3>";
$users_result = $conn->query("SELECT * FROM users ORDER BY id");
if ($users_result && $users_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Permissions</th><th>Status</th></tr>";
    
    while ($user = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($user['role'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($user['permissions'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($user['status'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='color: orange;'>⚠️ No users found</div>";
}

echo "<br><hr><br>";
echo "<h3>Test Results:</h3>";
echo "<p><a href='settings.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Settings Page</a></p>";
echo "<p><a href='migrate_users_table.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Migration Again</a></p>";
echo "<p><a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";

?>
