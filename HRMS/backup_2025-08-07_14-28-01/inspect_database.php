<?php
require_once '../db.php';

echo "<h3>Database Tables Inspection</h3>";

// Show all tables
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<h4>Available Tables:</h4><ul>";
    while ($row = $result->fetch_array()) {
        $tableName = $row[0];
        echo "<li><strong>$tableName</strong>";
        
        // Get table structure for HRMS-related tables
        if (strpos($tableName, 'employee') !== false || strpos($tableName, 'hr_') !== false || strpos($tableName, 'department') !== false) {
            echo " (HR-related)";
            $desc_result = $conn->query("DESCRIBE $tableName");
            if ($desc_result) {
                echo "<ul style='font-size: 0.9em;'>";
                while ($desc_row = $desc_result->fetch_assoc()) {
                    echo "<li>{$desc_row['Field']} ({$desc_row['Type']})</li>";
                }
                echo "</ul>";
            }
        }
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Could not retrieve table list</p>";
}

echo "<hr>";
echo "<p><a href='employee_directory.php'>→ Test Employee Directory</a></p>";

require_once '../layouts/footer.php';
?>