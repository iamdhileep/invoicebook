<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get filters from URL parameters
$current_date = $_GET['date'] ?? date('Y-m-d');
$department_filter = $_GET['department'] ?? '';
$position_filter = $_GET['position'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($department_filter)) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
}

if (!empty($position_filter)) {
    $where_conditions[] = "e.position = ?";
    $params[] = $position_filter;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search_filter)) {
    $where_conditions[] = "(e.employee_name LIKE ? OR e.name LIKE ? OR e.employee_code LIKE ?)";
    $search_param = '%' . $search_filter . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'AND ' . implode(' AND ', $where_conditions);
}

// Get attendance data
$query = "
    SELECT 
        COALESCE(e.employee_name, e.name) as employee_name,
        e.employee_code,
        e.position,
        e.department,
        e.phone,
        COALESCE(a.status, 'Not Marked') as status,
        COALESCE(a.time_in, '-') as time_in,
        COALESCE(a.time_out, '-') as time_out,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
            THEN TIMEDIFF(a.time_out, a.time_in)
            ELSE '-'
        END as work_duration,
        ? as attendance_date
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
    WHERE 1=1 $where_clause
    ORDER BY e.employee_name ASC, e.name ASC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $all_params = array_merge([$current_date, $current_date], $params);
    $all_param_types = 'ss' . $param_types;
    $stmt->bind_param($all_param_types, ...$all_params);
} else {
    $stmt->bind_param('ss', $current_date, $current_date);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
$filename = 'attendance_' . $current_date . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Employee Name',
    'Employee Code', 
    'Position',
    'Department',
    'Phone',
    'Status',
    'Punch In',
    'Punch Out',
    'Work Duration',
    'Date'
]);

// Write data rows
while ($row = $result->fetch_assoc()) {
    // Format time for CSV
    $time_in = $row['time_in'] !== '-' ? date('h:i A', strtotime($row['time_in'])) : '-';
    $time_out = $row['time_out'] !== '-' ? date('h:i A', strtotime($row['time_out'])) : '-';
    
    // Format duration
    $duration = $row['work_duration'];
    if ($duration !== '-') {
        try {
            $duration_obj = new DateTime($duration);
            $duration = $duration_obj->format('H:i') . ' hrs';
        } catch (Exception $e) {
            $duration = '-';
        }
    }
    
    fputcsv($output, [
        $row['employee_name'],
        $row['employee_code'],
        $row['position'],
        $row['department'] ?: '-',
        $row['phone'],
        $row['status'],
        $time_in,
        $time_out,
        $duration,
        date('F j, Y', strtotime($row['attendance_date']))
    ]);
}

fclose($output);
exit;
?>