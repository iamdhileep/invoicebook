<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="shift_data_' . date('Y-m-d') . '.csv"');

// Output CSV header
$output = fopen('php://output', 'w');
fputcsv($output, [
    'Report Type',
    'Employee Name',
    'Employee ID',
    'Shift Name',
    'Start Time',
    'End Time',
    'Working Days',
    'Department',
    'Assignment Date',
    'Status',
    'Break Duration (minutes)'
]);

// Get shift assignments data
$query = "
    SELECT 
        'SHIFT ASSIGNMENT' as report_type,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        s.name as shift_name,
        s.start_time,
        s.end_time,
        s.working_days,
        COALESCE(d.department_name, 'N/A') as department,
        sa.start_date,
        sa.status,
        s.break_duration
    FROM hr_shift_assignments sa
    JOIN hr_employees e ON sa.employee_id = e.id
    JOIN hr_shifts s ON sa.shift_id = s.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    WHERE sa.status = 'active'
    ORDER BY e.first_name, e.last_name
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['report_type'],
            $row['employee_name'],
            $row['emp_id'],
            $row['shift_name'],
            date('g:i A', strtotime($row['start_time'])),
            date('g:i A', strtotime($row['end_time'])),
            $row['working_days'],
            $row['department'],
            date('M j, Y', strtotime($row['start_date'])),
            ucfirst($row['status']),
            $row['break_duration']
        ]);
    }
}

// Add shift templates data
$shiftsQuery = "
    SELECT 
        'SHIFT TEMPLATE' as report_type,
        '' as employee_name,
        '' as emp_id,
        s.name as shift_name,
        s.start_time,
        s.end_time,
        s.working_days,
        CONCAT(COUNT(sa.id), ' employees assigned') as department,
        s.created_at as start_date,
        s.status,
        s.break_duration
    FROM hr_shifts s
    LEFT JOIN hr_shift_assignments sa ON s.id = sa.shift_id AND sa.status = 'active'
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.name
";

$shiftsResult = mysqli_query($conn, $shiftsQuery);
if ($shiftsResult) {
    while ($row = mysqli_fetch_assoc($shiftsResult)) {
        fputcsv($output, [
            $row['report_type'],
            $row['employee_name'],
            $row['emp_id'],
            $row['shift_name'],
            date('g:i A', strtotime($row['start_time'])),
            date('g:i A', strtotime($row['end_time'])),
            $row['working_days'],
            $row['department'],
            date('M j, Y', strtotime($row['start_date'])),
            ucfirst($row['status']),
            $row['break_duration']
        ]);
    }
}

fclose($output);
?>
