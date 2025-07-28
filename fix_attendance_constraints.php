<?php
require 'db.php';

echo "Checking and fixing attendance table constraints...\n";

try {
    // Check existing indexes
    $result = $conn->query("SHOW INDEX FROM attendance");
    echo "\nCurrent indexes:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Key_name'] . " on " . $row['Column_name'] . "\n";
    }
    
    // Check if we need to add unique constraint for employee_id + date
    $indexExists = false;
    $result = $conn->query("SHOW INDEX FROM attendance WHERE Key_name = 'unique_employee_date'");
    if ($result->num_rows > 0) {
        echo "\n✅ Unique constraint already exists\n";
        $indexExists = true;
    }
    
    if (!$indexExists) {
        echo "\n🔧 Adding unique constraint for employee_id + date...\n";
        $conn->query("ALTER TABLE attendance ADD UNIQUE KEY unique_employee_date (employee_id, date)");
        echo "✅ Unique constraint added\n";
    }
    
    // Ensure attendance_date column is also synced with date column
    echo "\n🔧 Syncing attendance_date with date column...\n";
    $conn->query("UPDATE attendance SET attendance_date = date WHERE attendance_date IS NULL OR attendance_date != date");
    echo "✅ Date columns synchronized\n";
    
    // Test a sample insert
    echo "\n🧪 Testing attendance insert...\n";
    $testEmpId = 1; // Assuming employee ID 1 exists
    $testDate = date('Y-m-d');
    
    $stmt = $conn->prepare("
        INSERT INTO attendance (employee_id, date, attendance_date, status, punch_in_time, remarks, marked_by, created_at, updated_at)
        VALUES (?, ?, ?, 'Present', '09:00:00', 'Test entry', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            punch_in_time = VALUES(punch_in_time),
            updated_at = NOW()
    ");
    $stmt->bind_param("iss", $testEmpId, $testDate, $testDate);
    
    if ($stmt->execute()) {
        echo "✅ Test insert successful\n";
        
        // Clean up test entry
        $conn->query("DELETE FROM attendance WHERE employee_id = $testEmpId AND date = '$testDate' AND remarks = 'Test entry'");
        echo "✅ Test entry cleaned up\n";
    } else {
        echo "❌ Test insert failed: " . $stmt->error . "\n";
    }
    
    echo "\n✅ Attendance table constraints check complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
