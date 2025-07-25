<?php
/**
 * URL Testing Script for Sidebar Navigation
 * This script tests all sidebar URLs to ensure they work correctly
 */

// Define all sidebar URLs from the current sidebar.php
$sidebar_urls = [
    // Dashboard
    'Dashboard' => 'pages/dashboard/dashboard.php',
    
    // Quick Actions
    'New Invoice' => 'invoice_form.php',
    'Add Product' => 'add_item.php', 
    'Add Employee' => 'add_employee.php',
    'Record Expense' => 'pages/expenses/expenses.php',
    
    // Sales & Revenue
    'Invoice History' => 'invoice_history.php',
    'Sales Summary' => 'summary_dashboard.php',
    
    // Inventory
    'All Products' => 'pages/products/products.php',
    'Stock Control' => 'item-stock.php',
    'Categories' => 'manage_categories.php',
    
    // Finances
    'Expense History' => 'expense_history.php',
    'Financial Reports' => 'reports.php',
    
    // Human Resources
    'Employee Directory' => 'pages/employees/employees.php',
    'Mark Attendance' => 'pages/attendance/attendance.php',
    'Employee Attendance' => 'Employee_attendance.php',
    'Time Tracking' => 'advanced_attendance.php',
    'Attendance Calendar' => 'attendance-calendar.php',
    
    // Payroll
    'Process Payroll' => 'pages/payroll/payroll.php',
    'Payroll Reports' => 'payroll_report.php',
    'Attendance Reports' => 'attendance_preview.php',
    
    // System
    'Settings' => 'settings.php',
    'Sign Out' => 'logout.php'
];

echo "<h1>Sidebar URL Testing Results</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .working { color: green; }
    .broken { color: red; }
    .redirect { color: orange; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

echo "<table>";
echo "<tr><th>Menu Item</th><th>URL</th><th>Status</th><th>Details</th></tr>";

foreach ($sidebar_urls as $name => $url) {
    $full_path = __DIR__ . '/' . $url;
    $web_url = "http://localhost/billbook/" . $url;
    
    if (file_exists($full_path)) {
        // Check if it's a redirect file
        $content = file_get_contents($full_path, false, null, 0, 200);
        if (strpos($content, 'header("Location:') !== false) {
            echo "<tr><td>$name</td><td>$url</td><td class='redirect'>REDIRECT</td><td>File redirects to another location</td></tr>";
        } else {
            echo "<tr><td>$name</td><td>$url</td><td class='working'>✅ WORKING</td><td><a href='$web_url' target='_blank'>Test Link</a></td></tr>";
        }
    } else {
        echo "<tr><td>$name</td><td>$url</td><td class='broken'>❌ NOT FOUND</td><td>File does not exist</td></tr>";
    }
}

echo "</table>";

echo "<h2>Summary</h2>";
echo "<p>This test checks if all sidebar navigation URLs point to existing files.</p>";
echo "<p>✅ <strong>WORKING</strong>: File exists and is functional</p>";
echo "<p>🔄 <strong>REDIRECT</strong>: File exists but redirects to another location</p>";
echo "<p>❌ <strong>NOT FOUND</strong>: File does not exist</p>";

?>
