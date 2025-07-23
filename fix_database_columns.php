<?php
// Database Column Fix Script
// This script adds missing columns to fix the attendance-calendar.php errors

include 'db.php';

echo "<h2>Database Column Fix Script</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 20px;'>";

try {
    $fixesApplied = 0;
    
    echo "<h3>🔧 Applying Database Fixes...</h3>";
    
    // Check and add employee_code column
    $checkEmployeeCode = $conn->query("SHOW COLUMNS FROM employees LIKE 'employee_code'");
    if ($checkEmployeeCode->num_rows == 0) {
        echo "<p>Adding employee_code column to employees table...</p>";
        $addEmployeeCode = "ALTER TABLE employees ADD COLUMN employee_code VARCHAR(50) DEFAULT NULL";
        if ($conn->query($addEmployeeCode)) {
            echo "<p style='color: green;'>✅ Added employee_code column successfully</p>";
            $fixesApplied++;
        } else {
            echo "<p style='color: red;'>❌ Error adding employee_code: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ employee_code column already exists</p>";
    }
    
    // Check and add position column
    $checkPosition = $conn->query("SHOW COLUMNS FROM employees LIKE 'position'");
    if ($checkPosition->num_rows == 0) {
        echo "<p>Adding position column to employees table...</p>";
        $addPosition = "ALTER TABLE employees ADD COLUMN position VARCHAR(100) DEFAULT NULL";
        if ($conn->query($addPosition)) {
            echo "<p style='color: green;'>✅ Added position column successfully</p>";
            $fixesApplied++;
        } else {
            echo "<p style='color: red;'>❌ Error adding position: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ position column already exists</p>";
    }
    
    // Check and add photo column
    $checkPhoto = $conn->query("SHOW COLUMNS FROM employees LIKE 'photo'");
    if ($checkPhoto->num_rows == 0) {
        echo "<p>Adding photo column to employees table...</p>";
        $addPhoto = "ALTER TABLE employees ADD COLUMN photo VARCHAR(255) DEFAULT NULL";
        if ($conn->query($addPhoto)) {
            echo "<p style='color: green;'>✅ Added photo column successfully</p>";
            $fixesApplied++;
        } else {
            echo "<p style='color: red;'>❌ Error adding photo: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ photo column already exists</p>";
    }
    
    // Check for attendance primary key
    $checkAttendanceId = $conn->query("SHOW COLUMNS FROM attendance WHERE Field IN ('id', 'attendance_id') AND Key = 'PRI'");
    if ($checkAttendanceId->num_rows == 0) {
        echo "<p>Adding primary key to attendance table...</p>";
        
        // Check if id column exists
        $checkId = $conn->query("SHOW COLUMNS FROM attendance LIKE 'id'");
        if ($checkId->num_rows == 0) {
            $addId = "ALTER TABLE attendance ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
        } else {
            $addId = "ALTER TABLE attendance MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY";
        }
        
        if ($conn->query($addId)) {
            echo "<p style='color: green;'>✅ Added attendance primary key successfully</p>";
            $fixesApplied++;
        } else {
            echo "<p style='color: red;'>❌ Error adding attendance primary key: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ attendance table already has a primary key</p>";
    }
    
    // Update some sample data to demonstrate the fix
    echo "<h3>📝 Updating Sample Data...</h3>";
    
    // Update employee codes with generated values
    $updateEmployeeCodes = "
        UPDATE employees 
        SET employee_code = CONCAT('EMP', LPAD(employee_id, 3, '0')) 
        WHERE employee_code IS NULL OR employee_code = ''
    ";
    
    if ($conn->query($updateEmployeeCodes)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "<p style='color: green;'>✅ Updated $affected employee codes</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ No employee codes needed updating</p>";
        }
    }
    
    // Update sample positions
    $updatePositions = "
        UPDATE employees 
        SET position = 'Employee' 
        WHERE position IS NULL OR position = ''
    ";
    
    if ($conn->query($updatePositions)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "<p style='color: green;'>✅ Updated $affected employee positions</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ No employee positions needed updating</p>";
        }
    }
    
    // Test the fixed query
    echo "<h3>🧪 Testing Fixed Query...</h3>";
    
    $testQuery = "
        SELECT a.*, 
               e.name as employee_name, 
               COALESCE(e.employee_code, '') as employee_code, 
               COALESCE(e.position, '') as position, 
               COALESCE(e.photo, '') as photo,
               COALESCE(a.id, a.attendance_id, 0) as attendance_id
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.employee_id 
        WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = '" . date('Y-m') . "'
        LIMIT 3
    ";
    
    $testResult = $conn->query($testQuery);
    
    if ($testResult) {
        echo "<p style='color: green;'>✅ Query executed successfully!</p>";
        if ($testResult->num_rows > 0) {
            echo "<p><strong>Sample Results:</strong></p>";
            echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f5f5f5;'>";
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>Employee Name</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>Employee Code</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>Position</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>Date</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px;'>Status</th>";
            echo "</tr>";
            
            while ($row = $testResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['employee_name'] ?? 'N/A') . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['employee_code'] ?? 'N/A') . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['position'] ?? 'N/A') . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['attendance_date'] ?? 'N/A') . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No attendance data found for current month</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Query still failed: " . $conn->error . "</p>";
    }
    
    // Summary
    echo "<h3>📋 Summary:</h3>";
    
    if ($fixesApplied > 0) {
        echo "<div style='color: green; padding: 15px; border: 1px solid green; background: #f0fff0; border-radius: 5px;'>";
        echo "<strong>🎉 Database Fixed Successfully!</strong><br>";
        echo "Applied $fixesApplied fixes to your database.<br>";
        echo "The attendance calendar should now work without errors.";
        echo "</div>";
    } else {
        echo "<div style='color: blue; padding: 15px; border: 1px solid #007bff; background: #e7f3ff; border-radius: 5px;'>";
        echo "<strong>ℹ️ Database Already Up to Date</strong><br>";
        echo "No fixes were needed. Your database structure is correct.";
        echo "</div>";
    }
    
    echo "<div style='margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go back to your <a href='attendance-calendar.php' target='_blank'>Attendance Calendar</a><br>";
    echo "2. The errors should now be resolved<br>";
    echo "3. Test all three views: Calendar, List, and Analytics<br>";
    echo "4. You can delete this file (fix_database_columns.php) after running it";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 15px; border: 1px solid red; background: #fff0f0; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

$conn->close();
?> 