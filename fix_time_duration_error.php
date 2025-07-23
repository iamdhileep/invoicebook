<?php
// Fix Time Duration Errors - One-time cleanup script
// This script fixes negative time durations that cause DateTime parsing errors

include 'db.php';

echo "<h2>Fix Time Duration Errors</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 20px;'>";

try {
    // Check for problematic attendance records
    echo "<h3>🔍 Checking for problematic attendance records...</h3>";
    
    // Find records where time_out is earlier than time_in (causing negative durations)
    $problem_query = "
        SELECT 
            attendance_id,
            employee_id,
            attendance_date,
            time_in,
            time_out,
            TIMEDIFF(time_out, time_in) as duration_diff
        FROM attendance 
        WHERE time_in IS NOT NULL 
        AND time_out IS NOT NULL 
        AND time_out <= time_in
        ORDER BY attendance_date DESC
        LIMIT 50
    ";
    
    $result = $conn->query($problem_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange; background: #fff8dc; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>⚠️ Found " . $result->num_rows . " problematic records:</strong>";
        echo "</div>";
        
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Employee</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Date</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Time In</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Time Out</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Issue</th>";
        echo "</tr>";
        
        $problematic_records = [];
        while ($row = $result->fetch_assoc()) {
            $problematic_records[] = $row;
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['attendance_id'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['employee_id'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['attendance_date'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['time_in'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $row['time_out'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; color: red;'>Time Out ≤ Time In</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Fix the problematic records
        echo "<h3>🔧 Fixing problematic records...</h3>";
        
        $fixed_count = 0;
        foreach ($problematic_records as $record) {
            // Option 1: Set time_out to NULL (incomplete punch)
            // Option 2: Set time_out to time_in + 8 hours (assume 8-hour workday)
            // Option 3: Swap time_in and time_out if they seem reversed
            
            $time_in = new DateTime($record['time_in']);
            $time_out = new DateTime($record['time_out']);
            
            // If the difference is small (< 12 hours), assume they were swapped
            $diff_hours = ($time_in->getTimestamp() - $time_out->getTimestamp()) / 3600;
            
            if ($diff_hours < 12) {
                // Swap the times
                $fix_query = "UPDATE attendance SET time_in = ?, time_out = ? WHERE attendance_id = ?";
                $stmt = $conn->prepare($fix_query);
                $stmt->bind_param('ssi', $record['time_out'], $record['time_in'], $record['attendance_id']);
                
                if ($stmt->execute()) {
                    echo "<p style='color: green;'>✅ Fixed record {$record['attendance_id']}: Swapped time_in and time_out</p>";
                    $fixed_count++;
                } else {
                    echo "<p style='color: red;'>❌ Failed to fix record {$record['attendance_id']}: " . $stmt->error . "</p>";
                }
            } else {
                // Set time_out to NULL (mark as incomplete)
                $fix_query = "UPDATE attendance SET time_out = NULL WHERE attendance_id = ?";
                $stmt = $conn->prepare($fix_query);
                $stmt->bind_param('i', $record['attendance_id']);
                
                if ($stmt->execute()) {
                    echo "<p style='color: blue;'>🔄 Fixed record {$record['attendance_id']}: Set time_out to NULL (incomplete punch)</p>";
                    $fixed_count++;
                } else {
                    echo "<p style='color: red;'>❌ Failed to fix record {$record['attendance_id']}: " . $stmt->error . "</p>";
                }
            }
        }
        
        echo "<div style='color: green; padding: 15px; border: 1px solid green; background: #f0fff0; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>🎉 Fixed {$fixed_count} out of " . count($problematic_records) . " problematic records!</strong>";
        echo "</div>";
        
    } else {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0; border-radius: 5px;'>";
        echo "<strong>✅ No problematic attendance records found!</strong>";
        echo "</div>";
    }
    
    // Additional checks and improvements
    echo "<h3>🛠️ Additional Database Improvements</h3>";
    
    // Add database constraints to prevent future issues
    echo "<p><strong>Adding database constraints to prevent future issues...</strong></p>";
    
    // Check if we can add a check constraint (MySQL 8.0+)
    $mysql_version = $conn->get_server_info();
    echo "<p>MySQL Version: " . $mysql_version . "</p>";
    
    if (version_compare($mysql_version, '8.0.16', '>=')) {
        // Add check constraint for MySQL 8.0+
        $constraint_query = "
            ALTER TABLE attendance 
            ADD CONSTRAINT chk_time_order 
            CHECK (time_out IS NULL OR time_in IS NULL OR time_out > time_in)
        ";
        
        try {
            $conn->query($constraint_query);
            echo "<p style='color: green;'>✅ Added check constraint to prevent future time order issues</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Could not add check constraint (might already exist): " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>💡 MySQL version doesn't support check constraints. Consider upgrading to MySQL 8.0+ for better data validation.</p>";
    }
    
    // Summary and recommendations
    echo "<h3>📋 Summary & Recommendations</h3>";
    echo "<ul>";
    echo "<li><strong>Issue Fixed:</strong> Negative time durations that caused DateTime parsing errors</li>";
    echo "<li><strong>Prevention:</strong> SQL query updated to handle invalid time ranges</li>";
    echo "<li><strong>UI Handling:</strong> Added error handling in display code</li>";
    echo "<li><strong>Future Prevention:</strong> Validate time inputs on the frontend</li>";
    echo "</ul>";
    
    echo "<div style='margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go back to your <a href='advanced_attendance.php'>Advanced Attendance Page</a><br>";
    echo "2. The DateTime errors should now be fixed<br>";
    echo "3. Monitor for any remaining time-related issues<br>";
    echo "4. You can delete this file (fix_time_duration_error.php) after running it once";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 15px; border: 1px solid red; background: #fff0f0; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

$conn->close();
?> 