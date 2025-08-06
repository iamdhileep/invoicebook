<?php
// Simple error checking script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simple Error Check</h2>";

// Check if we can run any PHP at all
echo "✅ PHP is working<br>";

// Test database connection
echo "<h3>Database Test:</h3>";
try {
    include 'db.php';
    if (isset($conn) && $conn) {
        echo "✅ Database connection successful<br>";
        
        // Test a simple query
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Database query works - Found " . $row['count'] . " users<br>";
        } else {
            echo "❌ Database query failed: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database exception: " . $e->getMessage() . "<br>";
}

// Test what happens when we try to access dashboard
echo "<h3>Dashboard Access Test:</h3>";
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['admin'] = 'admin';

echo "Session set. Trying to include dashboard...<br>";

try {
    // Capture any output from dashboard
    ob_start();
    include 'dashboard.php';
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "✅ Dashboard included successfully<br>";
    echo "Output: " . htmlspecialchars($output) . "<br>";
} catch (Exception $e) {
    echo "❌ Dashboard error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ Dashboard fatal error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
