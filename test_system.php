<?php
// Simple test script to check system functionality
echo "<h2>Business Management System - Status Check</h2>";

// Check if files exist
$files_to_check = [
    'db.php' => 'Database Configuration',
    'config.php' => 'System Configuration',
    'layouts/header.php' => 'Header Layout',
    'layouts/sidebar.php' => 'Sidebar Layout', 
    'layouts/footer.php' => 'Footer Layout',
    'pages/dashboard/dashboard.php' => 'Dashboard Page',
    'pages/invoice/invoice.php' => 'Invoice Page',
    'pages/products/products.php' => 'Products Page',
    'pages/expenses/expenses.php' => 'Expenses Page',
    'pages/employees/employees.php' => 'Employees Page',
    'pages/attendance/attendance.php' => 'Attendance Page',
    'pages/payroll/payroll.php' => 'Payroll Page'
];

echo "<h3>File Status:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>File</th><th>Description</th><th>Status</th></tr>";

foreach ($files_to_check as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? "<span style='color: green;'>✅ Exists</span>" : "<span style='color: red;'>❌ Missing</span>";
    echo "<tr><td>$file</td><td>$description</td><td>$status</td></tr>";
}

echo "</table>";

// Test database connection
echo "<h3>Database Connection:</h3>";
if (file_exists('db.php')) {
    include 'db.php';
    if (isset($conn) && $conn) {
        echo "<span style='color: green;'>✅ Database connection successful</span><br>";
        
        // Test some basic queries
        $tables = ['invoices', 'expenses', 'employees', 'items'];
        echo "<h4>Database Tables:</h4>";
        foreach ($tables as $table) {
            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                echo "✅ $table: {$row['count']} records<br>";
            } else {
                echo "❌ $table: Error - " . mysqli_error($conn) . "<br>";
            }
        }
    } else {
        echo "<span style='color: red;'>❌ Database connection failed</span>";
    }
} else {
    echo "<span style='color: red;'>❌ Database configuration file missing</span>";
}

echo "<h3>Quick Links (Test Navigation):</h3>";
echo "<ul>";
echo "<li><a href='pages/dashboard/dashboard.php'>Dashboard</a></li>";
echo "<li><a href='pages/invoice/invoice.php'>Create Invoice</a></li>";
echo "<li><a href='pages/products/products.php'>Products</a></li>";
echo "<li><a href='pages/expenses/expenses.php'>Expenses</a></li>";
echo "<li><a href='pages/employees/employees.php'>Employees</a></li>";
echo "<li><a href='pages/attendance/attendance.php'>Attendance</a></li>";
echo "<li><a href='pages/payroll/payroll.php'>Payroll</a></li>";
echo "</ul>";

echo "<h3>System Information:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>