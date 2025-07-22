<?php
// Simple syntax test for add_employee.php
echo "<h2>PHP Syntax Test</h2>";

$file = 'add_employee.php';
$output = [];
$return_var = 0;

// Use php -l to check syntax
exec("php -l $file 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "<p style='color: green;'>✅ Syntax is valid!</p>";
    echo "<p>Output: " . implode('<br>', $output) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Syntax errors found:</p>";
    echo "<p>" . implode('<br>', $output) . "</p>";
}

// Alternative method - include the file to check for fatal errors
echo "<h3>Testing File Inclusion:</h3>";
try {
    // Capture any output/errors
    ob_start();
    $error_reporting = error_reporting(E_ALL);
    
    // We can't actually include it because it has session_start and redirects
    // But we can check if the file is readable
    if (is_readable($file)) {
        echo "<p style='color: green;'>✅ File is readable</p>";
        
        // Check file size to ensure it's not empty
        $filesize = filesize($file);
        echo "<p>File size: $filesize bytes</p>";
        
        if ($filesize > 0) {
            echo "<p style='color: green;'>✅ File has content</p>";
        } else {
            echo "<p style='color: red;'>❌ File is empty</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ File is not readable</p>";
    }
    
    error_reporting($error_reporting);
    $output = ob_get_clean();
    echo $output;
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='add_employee.php'>Test add_employee.php</a></p>";
echo "<p><a href='debug_add_employee.php'>Debug add_employee.php</a></p>";
?>