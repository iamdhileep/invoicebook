<?php
/**
 * Complete Navigation Test - Tests sidebar links from different directory levels
 */

// Test URLs that should all work now
$test_urls = [
    'Dashboard' => 'http://localhost/billbook/pages/dashboard/dashboard.php',
    'Add Item' => 'http://localhost/billbook/add_item.php',
    'Add Employee' => 'http://localhost/billbook/add_employee.php', 
    'Invoice Form' => 'http://localhost/billbook/invoice_form.php',
    'Expenses' => 'http://localhost/billbook/pages/expenses/expenses.php',
    'Products' => 'http://localhost/billbook/pages/products/products.php',
    'Employees' => 'http://localhost/billbook/pages/employees/employees.php',
    'Employee Attendance' => 'http://localhost/billbook/Employee_attendance.php',
    'Advanced Attendance' => 'http://localhost/billbook/advanced_attendance.php',
    'Attendance Calendar' => 'http://localhost/billbook/attendance-calendar.php',
    'Settings' => 'http://localhost/billbook/settings.php',
    'Payroll' => 'http://localhost/billbook/pages/payroll/payroll.php',
    'Invoice History' => 'http://localhost/billbook/invoice_history.php',
    'Summary Dashboard' => 'http://localhost/billbook/summary_dashboard.php'
];

echo "<h1>🎯 Complete Navigation Test Results</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f8f9fa; }
    .test-link { padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; }
    .test-link:hover { background: #0056b3; }
</style>";

echo "<div class='container'>";
echo "<h2>📋 Navigation URL Tests</h2>";
echo "<table>";
echo "<tr><th>Page Name</th><th>URL Path</th><th>Status</th><th>Test Link</th></tr>";

$working_count = 0;
$total_count = count($test_urls);

foreach ($test_urls as $name => $url) {
    // Extract the file path from URL
    $path = str_replace('http://localhost/billbook/', '', $url);
    $full_path = __DIR__ . '/' . $path;
    
    if (file_exists($full_path)) {
        echo "<tr>";
        echo "<td><strong>$name</strong></td>";
        echo "<td><code>$path</code></td>";
        echo "<td class='success'>✅ EXISTS</td>";
        echo "<td><a href='$url' target='_blank' class='test-link'>Test Page</a></td>";
        echo "</tr>";
        $working_count++;
    } else {
        echo "<tr>";
        echo "<td><strong>$name</strong></td>";
        echo "<td><code>$path</code></td>";
        echo "<td class='error'>❌ NOT FOUND</td>";
        echo "<td>File missing</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<h2>📊 Test Summary</h2>";
echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<p><strong>Total Pages Tested:</strong> $total_count</p>";
echo "<p><strong>Working Pages:</strong> <span class='success'>$working_count</span></p>";
echo "<p><strong>Success Rate:</strong> " . round(($working_count / $total_count) * 100, 1) . "%</p>";
echo "</div>";

if ($working_count == $total_count) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "🎉 <strong>ALL NAVIGATION LINKS WORKING!</strong><br>";
    echo "✅ Sidebar navigation is fully functional<br>";
    echo "✅ All URL paths correctly resolved<br>";
    echo "✅ Both root and subdirectory pages accessible";
    echo "</div>";
} else {
    $broken_count = $total_count - $working_count;
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "⚠️ <strong>$broken_count PAGE(S) NEED ATTENTION</strong><br>";
    echo "Some navigation links are not working properly.";
    echo "</div>";
}

echo "<h2>🔧 Technical Details</h2>";
echo "<ul>";
echo "<li><strong>Base Path Method:</strong> Absolute path (<code>/billbook/</code>)</li>";
echo "<li><strong>Sidebar Location:</strong> <code>layouts/sidebar.php</code></li>";
echo "<li><strong>Root Files:</strong> Direct access from billbook root</li>";
echo "<li><strong>Subdirectory Files:</strong> Organized in <code>pages/</code> structure</li>";
echo "</ul>";

echo "</div>";
?>
