<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get filters from URL parameters
$search = $_GET['search'] ?? '';
$position = $_GET['position'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR employee_code LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR phone LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($position) {
    $where .= " AND position = '" . mysqli_real_escape_string($conn, $position) . "'";
}

// Get employees data
$query = "SELECT name, employee_code, position, monthly_salary, phone, email, address FROM employees $where ORDER BY name ASC";
$result = $conn->query($query);

// Set headers for CSV download
$filename = 'employees_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Employee Name',
    'Employee Code',
    'Position',
    'Monthly Salary (₹)',
    'Phone',
    'Email',
    'Address'
]);

// Write data rows
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['name'],
            $row['employee_code'],
            $row['position'],
            number_format($row['monthly_salary'], 2),
            $row['phone'],
            $row['email'] ?: '',
            $row['address'] ?: ''
        ]);
    }
}

fclose($output);
exit;
?>