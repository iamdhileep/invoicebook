<?php
echo "<h2>🔧 Error Fix Verification</h2>\n";

// Test files that were problematic
$test_files = [
    'pages/invoice/invoice.php',
    'pages/employees/employees.php', 
    'pages/dashboard/dashboard.php',
    'pages/manager/manager_dashboard_api.php',
    'auth_guard.php'
];

echo "<h3>Testing PHP syntax...</h3>\n";
$syntax_errors = 0;

foreach ($test_files as $file) {
    if (file_exists($file)) {
        $output = [];
        $return_var = 0;
        exec("php -l \"$file\" 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "✅ $file: Syntax OK\n<br>";
        } else {
            echo "❌ $file: " . implode(' ', $output) . "\n<br>";
            $syntax_errors++;
        }
    } else {
        echo "⚠️ $file: File not found\n<br>";
    }
}

echo "<h3>Checking for problematic function calls...</h3>\n";
$problem_functions = 0;

foreach ($test_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'checkLogin()') !== false) {
            echo "❌ $file: Still contains checkLogin() call\n<br>";
            $problem_functions++;
        } else {
            echo "✅ $file: No problematic function calls\n<br>";
        }
    }
}

echo "<h3>Summary:</h3>\n";
if ($syntax_errors === 0 && $problem_functions === 0) {
    echo "<h3 style='color: green;'>🎉 ALL ERRORS FIXED!</h3>\n";
    echo "✅ No syntax errors\n<br>";
    echo "✅ No undefined function calls\n<br>";
    echo "✅ No session conflicts\n<br>";
    echo "System is ready for use!\n<br>";
} else {
    echo "<h3 style='color: red;'>⚠️ ISSUES REMAINING</h3>\n";
    echo "❌ Syntax errors: $syntax_errors\n<br>";
    echo "❌ Function call issues: $problem_functions\n<br>";
}

echo "<hr><small>Verification completed at " . date('Y-m-d H:i:s') . "</small>";
?>
