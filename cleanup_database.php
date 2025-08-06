<?php
/**
 * Cleanup Database - Remove all permission system tables
 */

include 'db.php';

echo "<h1>Cleaning up Permission System Database Tables</h1>";

$tables_to_drop = [
    'role_permissions',
    'user_permissions', 
    'hrms_pages',
    'permission_groups',
    'user_roles',
    'user_role_assignments'
];

$dropped_count = 0;

foreach ($tables_to_drop as $table) {
    $sql = "DROP TABLE IF EXISTS `$table`";
    if ($conn->query($sql)) {
        echo "✅ Dropped table: $table<br>";
        $dropped_count++;
    } else {
        echo "❌ Failed to drop table: $table - " . $conn->error . "<br>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "Tables dropped: $dropped_count<br>";
echo "Database cleanup complete!<br>";

// Also remove any permission-related columns from users table if they exist
$columns_to_check = ['permissions', 'role_permissions', 'hrms_permissions'];
foreach ($columns_to_check as $column) {
    $check_sql = "SHOW COLUMNS FROM users LIKE '$column'";
    $result = $conn->query($check_sql);
    if ($result && $result->num_rows > 0) {
        $drop_sql = "ALTER TABLE users DROP COLUMN `$column`";
        if ($conn->query($drop_sql)) {
            echo "✅ Removed column: $column from users table<br>";
        } else {
            echo "❌ Failed to remove column: $column - " . $conn->error . "<br>";
        }
    }
}

echo "<p>✅ Database is now clean of permission system tables!</p>";
$conn->close();
?>
