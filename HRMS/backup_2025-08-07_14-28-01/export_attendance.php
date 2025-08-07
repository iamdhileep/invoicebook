<?php
/**
 * Attendance Export CSV functionality
 */

require_once '../auth_check.php';
require_once '../db.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../hrms_portal.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Get filters
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$employeeFilter = $_GET['employee'] ?? '';
$startDate = $_GET['start_date'] ?? $dateFilter;
$endDate = $_GET['end_date'] ?? $dateFilter;

// Build where clause
$whereClause = "WHERE a.date BETWEEN '$startDate' AND '$endDate'";

if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
    $whereClause .= " AND e.user_id = $currentUserId";
} elseif ($employeeFilter) {
    $whereClause .= " AND a.employee_id = $employeeFilter";
}

try {
    $result = $conn->query("
        SELECT 
            e.employee_id as emp_id,
            e.first_name,
            e.last_name,
            d.name as department_name,
            a.date,
            a.clock_in_time,
            a.clock_out_time,
            a.hours_worked,
            a.status,
            a.clock_in_location,
            a.clock_out_location,
            a.notes
        FROM hr_attendance a
        LEFT JOIN hr_employees e ON a.employee_id = e.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        $whereClause
        ORDER BY a.date DESC, e.first_name ASC
    ");
    
    // Set headers for CSV download
    $filename = "attendance_report_" . $startDate . "_to_" . $endDate . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Employee ID',
        'First Name',
        'Last Name',
        'Department',
        'Date',
        'Clock In Time',
        'Clock Out Time',
        'Hours Worked',
        'Status',
        'Clock In Location',
        'Clock Out Location',
        'Notes'
    ]);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        $clockInTime = $row['clock_in_time'] ? date('g:i A', strtotime($row['clock_in_time'])) : '';
        $clockOutTime = $row['clock_out_time'] ? date('g:i A', strtotime($row['clock_out_time'])) : '';
        $hoursWorked = $row['hours_worked'] ? number_format($row['hours_worked'], 2) : '';
        
        fputcsv($output, [
            $row['emp_id'],
            $row['first_name'],
            $row['last_name'],
            $row['department_name'],
            date('M j, Y', strtotime($row['date'])),
            $clockInTime,
            $clockOutTime,
            $hoursWorked,
            ucfirst(str_replace('_', ' ', $row['status'])),
            $row['clock_in_location'],
            $row['clock_out_location'],
            $row['notes']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Attendance export error: " . $e->getMessage());
    header('Content-Type: text/html');
    echo "<script>alert('Export failed: " . $e->getMessage() . "'); window.close();</script>";
}

require_once '../layouts/footer.php';
?>