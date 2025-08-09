<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$employee_filter = $_GET['employee'] ?? '';
$project_filter = $_GET['project'] ?? '';

// Build WHERE clause
$where = "WHERE tt.date = '$date_filter'";
if ($employee_filter) {
    $where .= " AND (e.first_name LIKE '%$employee_filter%' OR e.last_name LIKE '%$employee_filter%')";
}
if ($project_filter) {
    $where .= " AND tt.project_name LIKE '%$project_filter%'";
}

// Get time entries for export
$time_entries = mysqli_query($conn, "
    SELECT 
        tt.*,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id,
        d.department_name
    FROM hr_time_tracking tt
    JOIN hr_employees e ON tt.employee_id = e.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    $where
    ORDER BY tt.date DESC, tt.start_time DESC
");

// Set headers for CSV download
$filename = 'time_tracking_' . $date_filter . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output CSV headers
$output = fopen('php://output', 'w');
fputcsv($output, [
    'Employee Name',
    'Employee ID',
    'Department',
    'Project',
    'Task Description',
    'Date',
    'Start Time',
    'End Time',
    'Total Hours',
    'Status',
    'Notes'
]);

// Output data
if ($time_entries && mysqli_num_rows($time_entries) > 0) {
    while ($entry = mysqli_fetch_assoc($time_entries)) {
        fputcsv($output, [
            $entry['employee_name'],
            $entry['emp_id'],
            $entry['department_name'] ?? 'N/A',
            $entry['project_name'],
            $entry['task_description'],
            $entry['date'],
            $entry['start_time'],
            $entry['end_time'] ?? 'In Progress',
            $entry['total_hours'] > 0 ? $entry['total_hours'] : 'In Progress',
            ucfirst(str_replace('_', ' ', $entry['status'])),
            $entry['notes'] ?? ''
        ]);
    }
}

fclose($output);
exit;
?>
