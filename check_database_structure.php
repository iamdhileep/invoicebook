<?php
// Database structure checker
include 'db.php';

try {
    echo "<h2>Database Structure Check</h2>\n";
    
    // Check if attendance table exists
    $result = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($result->num_rows > 0) {
        echo "<h3>✅ Attendance table exists</h3>\n";
        
        // Get attendance table structure
        $result = $conn->query("DESCRIBE attendance");
        echo "<h4>Attendance table structure:</h4>\n";
        echo "<table border='1'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<h3>❌ Attendance table does NOT exist</h3>\n";
    }
    
    // Check if employees table exists
    $result = $conn->query("SHOW TABLES LIKE 'employees'");
    if ($result->num_rows > 0) {
        echo "<h3>✅ Employees table exists</h3>\n";
        
        // Get employees table structure
        $result = $conn->query("DESCRIBE employees");
        echo "<h4>Employees table structure:</h4>\n";
        echo "<table border='1'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<h3>❌ Employees table does NOT exist</h3>\n";
    }
    
    // Check all tables in database
    $result = $conn->query("SHOW TABLES");
    echo "<h3>All tables in database:</h3>\n";
    echo "<ul>\n";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>\n";
    }
    echo "</ul>\n";
    
    // Check if leave-related tables exist
    $leaveRelatedTables = ['leave_applications', 'leave_types', 'leave_history', 'permissions'];
    echo "<h3>Leave Management Tables:</h3>\n";
    foreach ($leaveRelatedTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p>✅ $table exists</p>\n";
        } else {
            echo "<p>❌ $table does NOT exist</p>\n";
        }
    }
    
    // Check if biometric-related tables exist
    $biometricTables = ['biometric_devices', 'biometric_sync_status', 'device_settings'];
    echo "<h3>Biometric System Tables:</h3>\n";
    foreach ($biometricTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p>✅ $table exists</p>\n";
        } else {
            echo "<p>❌ $table does NOT exist</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
