<?php
include 'db.php';

echo "<h2>Checking Users Table Structure</h2>";

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows > 0) {
    echo "<div style='color: green;'>✅ Users table exists!</div><br>";
    
    // Check current table structure
    $columns_result = $conn->query("DESCRIBE users");
    if ($columns_result) {
        echo "<h3>Current Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $existing_columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Check which columns are missing
        $required_columns = [
            'permissions' => 'TEXT',
            'role' => "ENUM('admin', 'manager', 'employee', 'viewer') DEFAULT 'employee'",
            'status' => "ENUM('active', 'inactive') DEFAULT 'active'",
            'last_login' => 'TIMESTAMP NULL',
            'failed_login_attempts' => 'INT DEFAULT 0',
            'locked_until' => 'TIMESTAMP NULL'
        ];
        
        $missing_columns = [];
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                $missing_columns[$column] = $definition;
            }
        }
        
        if (!empty($missing_columns)) {
            echo "<h3 style='color: orange;'>⚠️ Missing Columns Found:</h3>";
            echo "<ul>";
            foreach ($missing_columns as $column => $definition) {
                echo "<li><strong>$column</strong> - $definition</li>";
            }
            echo "</ul>";
            
            echo "<h3>Adding Missing Columns...</h3>";
            
            // Add missing columns
            foreach ($missing_columns as $column => $definition) {
                $alter_sql = "ALTER TABLE users ADD COLUMN $column $definition";
                if ($conn->query($alter_sql)) {
                    echo "<div style='color: green;'>✅ Added column: $column</div>";
                } else {
                    echo "<div style='color: red;'>❌ Error adding column $column: " . $conn->error . "</div>";
                }
            }
            
            echo "<br><h3>Updated Table Structure:</h3>";
            $updated_columns_result = $conn->query("DESCRIBE users");
            if ($updated_columns_result) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                while ($row = $updated_columns_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
        } else {
            echo "<div style='color: green;'>✅ All required columns are present!</div>";
        }
        
    } else {
        echo "<div style='color: red;'>❌ Error checking table structure: " . $conn->error . "</div>";
    }
    
} else {
    echo "<div style='color: red;'>❌ Users table does not exist!</div>";
    echo "<p><a href='setup_user_permissions.php'>Run Full Setup</a></p>";
}

// Check existing users and update their permissions if needed
echo "<br><h3>Checking Existing Users:</h3>";
$users_result = $conn->query("SELECT id, username, email, role, permissions FROM users");
if ($users_result && $users_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Permissions</th></tr>";
    
    while ($user = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role'] ?? 'Not set') . "</td>";
        echo "<td>" . htmlspecialchars($user['permissions'] ?? 'Not set') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Update permissions for existing users if they don't have any
    echo "<br><h3>Updating User Permissions:</h3>";
    $update_result = $conn->query("SELECT id, username, permissions FROM users WHERE permissions IS NULL OR permissions = ''");
    if ($update_result && $update_result->num_rows > 0) {
        while ($user = $update_result->fetch_assoc()) {
            $permissions = 'all'; // Give all permissions by default
            if ($user['username'] === 'admin') {
                $permissions = 'all';
            } else {
                $permissions = 'attendance,reports';
            }
            
            $update_sql = "UPDATE users SET permissions = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('si', $permissions, $user['id']);
            
            if ($stmt->execute()) {
                echo "<div style='color: green;'>✅ Updated permissions for user: " . htmlspecialchars($user['username']) . "</div>";
            } else {
                echo "<div style='color: red;'>❌ Error updating permissions for user: " . htmlspecialchars($user['username']) . "</div>";
            }
        }
    } else {
        echo "<div style='color: green;'>✅ All users already have permissions set!</div>";
    }
    
} else {
    echo "<div style='color: orange;'>⚠️ No users found in the table.</div>";
}

echo "<br><hr><br>";
echo "<h3>Migration Complete!</h3>";
echo "<p><a href='settings.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Settings Page</a></p>";
echo "<p><a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";

?>
