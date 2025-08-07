<?php
require_once '../db.php';

// Add sample time tracking data
$sql = "INSERT INTO time_tracking (employee_id, project_name, task_description, date, start_time, end_time, total_hours, status) VALUES 
(1, 'Website Development', 'Frontend Development', '2025-08-05', '09:00:00', '17:00:00', 8.0, 'completed'),
(2, 'Mobile App', 'UI Design', '2025-08-05', '10:00:00', '18:00:00', 8.0, 'in-progress'),
(1, 'API Development', 'Backend API', '2025-08-04', '09:30:00', '16:30:00', 7.0, 'completed'),
(3, 'HR System', 'Employee Onboarding', '2025-08-05', '08:00:00', '16:00:00', 8.0, 'completed'),
(4, 'Marketing Campaign', 'Content Creation', '2025-08-05', '09:00:00', '17:30:00', 8.5, 'completed'),
(2, 'Website Development', 'Backend Development', '2025-08-04', '10:00:00', '18:00:00', 8.0, 'completed')";

if (mysqli_query($conn, $sql)) {
    echo "✓ Sample data inserted successfully\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check count
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM time_tracking");
$row = mysqli_fetch_assoc($result);
echo "Total entries: " . $row['total'] . "\n";

require_once '../layouts/footer.php';
?>