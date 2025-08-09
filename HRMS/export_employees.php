<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Employee ID',
    'First Name',
    'Last Name',
    'Email',
    'Phone',
    'Department',
    'Position',
    'Employment Type',
    'Date of Joining',
    'Salary',
    'Status'
]);

// Get employees with department info
$query = "
    SELECT 
        e.employee_id,
        e.first_name,
        e.last_name,
        e.email,
        e.phone,
        d.department_name,
        e.position,
        e.employment_type,
        e.date_of_joining,
        e.salary,
        e.status
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    ORDER BY e.first_name, e.last_name
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['employee_id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['department_name'] ?? 'N/A',
            $row['position'],
            ucfirst(str_replace('_', ' ', $row['employment_type'])),
            $row['date_of_joining'],
            $row['salary'] ? '₹' . number_format($row['salary']) : 'N/A',
            ucfirst($row['status'])
        ]);
    }
}

fclose($output);
exit;
?>
