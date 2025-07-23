<?php
// Simple script to add break columns to attendance table
// Run this file once in your browser to update the database

include 'db.php';

echo "<h2>Attendance Table Update Script</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px;'>";

try {
    // Check if columns already exist
    $check_break_start = $conn->query("SHOW COLUMNS FROM attendance LIKE 'break_start'");
    $check_break_end = $conn->query("SHOW COLUMNS FROM attendance LIKE 'break_end'");
    
    $break_start_exists = $check_break_start && $check_break_start->num_rows > 0;
    $break_end_exists = $check_break_end && $check_break_end->num_rows > 0;
    
    if ($break_start_exists && $break_end_exists) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0; border-radius: 5px;'>";
        echo "<strong>✅ Success!</strong> Break tracking columns already exist in your attendance table.";
        echo "</div>";
    } else {
        echo "<h3>Adding Break Tracking Columns...</h3>";
        
        // Add break_start column
        if (!$break_start_exists) {
            $sql1 = "ALTER TABLE attendance ADD COLUMN break_start DATETIME NULL";
            if ($conn->query($sql1)) {
                echo "<p style='color: green;'>✅ Added 'break_start' column successfully</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding 'break_start' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ 'break_start' column already exists</p>";
        }
        
        // Add break_end column
        if (!$break_end_exists) {
            $sql2 = "ALTER TABLE attendance ADD COLUMN break_end DATETIME NULL";
            if ($conn->query($sql2)) {
                echo "<p style='color: green;'>✅ Added 'break_end' column successfully</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding 'break_end' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ 'break_end' column already exists</p>";
        }
        
        // Add indexes for better performance
        echo "<h3>Adding Performance Indexes...</h3>";
        
        $index1 = "CREATE INDEX IF NOT EXISTS idx_attendance_date_employee ON attendance(attendance_date, employee_id)";
        if ($conn->query($index1)) {
            echo "<p style='color: green;'>✅ Added date-employee index</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Index might already exist: " . $conn->error . "</p>";
        }
        
        $index2 = "CREATE INDEX IF NOT EXISTS idx_attendance_punch_status ON attendance(time_in, time_out)";
        if ($conn->query($index2)) {
            echo "<p style='color: green;'>✅ Added punch status index</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Index might already exist: " . $conn->error . "</p>";
        }
        
        echo "<div style='color: green; padding: 15px; border: 1px solid green; background: #f0fff0; border-radius: 5px; margin-top: 20px;'>";
        echo "<strong>🎉 Database Update Complete!</strong><br>";
        echo "Your attendance system now supports break time tracking and other advanced features.";
        echo "</div>";
    }
    
    // Show current table structure
    echo "<h3>Current Attendance Table Structure:</h3>";
    echo "<table style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
    echo "<tr style='background: #f5f5f5;'><th style='border: 1px solid #ddd; padding: 8px;'>Column</th><th style='border: 1px solid #ddd; padding: 8px;'>Type</th><th style='border: 1px solid #ddd; padding: 8px;'>Null</th></tr>";
    
    $columns = $conn->query("SHOW COLUMNS FROM attendance");
    while ($column = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Field'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Type'] . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $column['Null'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go back to your <a href='advanced_attendance.php'>Advanced Attendance Page</a><br>";
    echo "2. The break tracking features should now be available<br>";
    echo "3. You can delete this file (setup_attendance_columns.php) after running it once";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 15px; border: 1px solid red; background: #fff0f0; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

$conn->close();
?> 