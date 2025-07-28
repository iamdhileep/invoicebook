<?php
require_once 'db.php';

try {
    echo "=== Database Tables ===\n";
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        echo "Table: " . $row[0] . "\n";
    }
    
    echo "\n=== Employee Table Structure ===\n";
    $result = $conn->query("DESCRIBE employees");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    
    echo "\n=== Attendance Table Structure ===\n";
    $result = $conn->query("DESCRIBE attendance");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    
    echo "\n=== Leave Requests Table (if exists) ===\n";
    try {
        $result = $conn->query("DESCRIBE leave_requests");
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } catch (Exception $e) {
        echo "Table doesn't exist or error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
