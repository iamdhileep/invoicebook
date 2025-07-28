<?php
// Insert sample attendance data for timesheet testing
require_once 'db.php';

echo "Inserting sample attendance data...\n";

// Sample attendance data
$attendance_data = [
    // July 2025 data for employee ID 1
    ['employee_id' => 1, 'check_in' => '2025-07-01 09:00:00', 'check_out' => '2025-07-01 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-02 08:45:00', 'check_out' => '2025-07-02 17:15:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-03 09:15:00', 'check_out' => '2025-07-03 18:00:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-04 08:30:00', 'check_out' => '2025-07-04 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-07 09:00:00', 'check_out' => '2025-07-07 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-08 08:50:00', 'check_out' => '2025-07-08 17:20:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-09 09:10:00', 'check_out' => '2025-07-09 17:40:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-10 08:40:00', 'check_out' => '2025-07-10 17:25:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-11 09:05:00', 'check_out' => '2025-07-11 17:35:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-14 08:55:00', 'check_out' => '2025-07-14 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-15 09:00:00', 'check_out' => '2025-07-15 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-16 08:45:00', 'check_out' => '2025-07-16 17:20:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-17 09:15:00', 'check_out' => '2025-07-17 18:00:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-18 08:30:00', 'check_out' => '2025-07-18 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-21 09:00:00', 'check_out' => '2025-07-21 17:40:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-22 08:45:00', 'check_out' => '2025-07-22 17:25:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-23 09:10:00', 'check_out' => '2025-07-23 17:50:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-24 08:40:00', 'check_out' => '2025-07-24 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-25 09:05:00', 'check_out' => '2025-07-25 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-28 08:50:00', 'check_out' => '2025-07-28 17:35:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-29 09:00:00', 'check_out' => '2025-07-29 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-30 08:45:00', 'check_out' => '2025-07-30 17:40:00'],
    ['employee_id' => 1, 'check_in' => '2025-07-31 09:15:00', 'check_out' => null], // Today, still working
    
    // June 2025 data for historical comparison
    ['employee_id' => 1, 'check_in' => '2025-06-02 09:00:00', 'check_out' => '2025-06-02 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-03 08:45:00', 'check_out' => '2025-06-03 17:15:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-04 09:15:00', 'check_out' => '2025-06-04 18:00:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-05 08:30:00', 'check_out' => '2025-06-05 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-06 09:00:00', 'check_out' => '2025-06-06 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-09 08:50:00', 'check_out' => '2025-06-09 17:20:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-10 09:10:00', 'check_out' => '2025-06-10 17:40:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-11 08:40:00', 'check_out' => '2025-06-11 17:25:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-12 09:05:00', 'check_out' => '2025-06-12 17:35:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-13 08:55:00', 'check_out' => '2025-06-13 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-16 09:00:00', 'check_out' => '2025-06-16 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-17 08:45:00', 'check_out' => '2025-06-17 17:20:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-18 09:15:00', 'check_out' => '2025-06-18 18:00:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-19 08:30:00', 'check_out' => '2025-06-19 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-20 09:00:00', 'check_out' => '2025-06-20 17:40:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-23 08:45:00', 'check_out' => '2025-06-23 17:25:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-24 09:10:00', 'check_out' => '2025-06-24 17:50:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-25 08:40:00', 'check_out' => '2025-06-25 17:30:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-26 09:05:00', 'check_out' => '2025-06-26 17:45:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-27 08:50:00', 'check_out' => '2025-06-27 17:35:00'],
    ['employee_id' => 1, 'check_in' => '2025-06-30 09:00:00', 'check_out' => '2025-06-30 17:30:00'],
    
    // Some data for other employees
    ['employee_id' => 2, 'check_in' => '2025-07-01 09:30:00', 'check_out' => '2025-07-01 17:45:00'],
    ['employee_id' => 2, 'check_in' => '2025-07-02 09:00:00', 'check_out' => '2025-07-02 17:30:00'],
    ['employee_id' => 2, 'check_in' => '2025-07-03 09:15:00', 'check_out' => '2025-07-03 18:15:00'],
    ['employee_id' => 2, 'check_in' => '2025-07-04 09:10:00', 'check_out' => '2025-07-04 17:50:00'],
    
    ['employee_id' => 3, 'check_in' => '2025-07-01 08:30:00', 'check_out' => '2025-07-01 16:30:00'],
    ['employee_id' => 3, 'check_in' => '2025-07-02 08:45:00', 'check_out' => '2025-07-02 16:45:00'],
    ['employee_id' => 3, 'check_in' => '2025-07-03 08:30:00', 'check_out' => '2025-07-03 16:30:00'],
];

try {
    // Clear existing attendance data first (optional)
    $conn->query("DELETE FROM attendance WHERE employee_id IN (1, 2, 3)");
    echo "Cleared existing attendance data\n";
    
    // Insert new attendance data
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, check_in, check_out, created_at) VALUES (?, ?, ?, NOW())");
    
    $inserted = 0;
    foreach ($attendance_data as $record) {
        $stmt->bind_param("iss", $record['employee_id'], $record['check_in'], $record['check_out']);
        
        if ($stmt->execute()) {
            $inserted++;
        } else {
            echo "Error inserting record: " . $stmt->error . "\n";
        }
    }
    
    echo "Successfully inserted $inserted attendance records\n";
    
    // Verify the data
    $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = 1 AND MONTH(check_in) = 7 AND YEAR(check_in) = 2025");
    $july_count = $result->fetch_assoc()['count'];
    echo "July 2025 attendance records for employee 1: $july_count\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = 1 AND MONTH(check_in) = 6 AND YEAR(check_in) = 2025");
    $june_count = $result->fetch_assoc()['count'];
    echo "June 2025 attendance records for employee 1: $june_count\n";
    
    echo "\nSample attendance data setup complete!\n";
    echo "You can now access the timesheet at: http://localhost/billbook/timesheet/timesheet.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
