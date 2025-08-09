<?php
echo "<h2>🔍 Tax Management File Diagnostic</h2>";
echo "<p><strong>Checking for any auth-related issues...</strong></p>";

$file_path = 'HRMS/tax_management.php';

echo "<h3>File Information:</h3>";
echo "<p>File exists: " . (file_exists($file_path) ? "✅ Yes" : "❌ No") . "</p>";
echo "<p>File size: " . filesize($file_path) . " bytes</p>";
echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($file_path)) . "</p>";

echo "<h3>Checking for problematic includes:</h3>";
$content = file_get_contents($file_path);

if (strpos($content, 'hrms_auth.php') !== false) {
    echo "<p>❌ Found reference to 'hrms_auth.php' - THIS IS THE PROBLEM!</p>";
    // Find the line number
    $lines = explode("\n", $content);
    foreach ($lines as $num => $line) {
        if (strpos($line, 'hrms_auth.php') !== false) {
            echo "<p>Found on line " . ($num + 1) . ": <code>" . htmlspecialchars(trim($line)) . "</code></p>";
        }
    }
} else {
    echo "<p>✅ No references to 'hrms_auth.php' found</p>";
}

if (strpos($content, '../auth_check.php') !== false) {
    echo "<p>✅ Correct '../auth_check.php' reference found</p>";
} else {
    echo "<p>❌ Missing '../auth_check.php' reference</p>";
}

if (strpos($content, '$base_dir') !== false) {
    echo "<p>✅ Using absolute paths with \$base_dir</p>";
} else {
    echo "<p>⚠️ Not using absolute paths</p>";
}

echo "<h3>First 10 lines of the file:</h3>";
$lines = explode("\n", $content);
echo "<pre>";
for ($i = 0; $i < min(10, count($lines)); $i++) {
    echo ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
}
echo "</pre>";

echo "<h3>Include/Require statements found:</h3>";
foreach ($lines as $num => $line) {
    if (preg_match('/^\s*(include|require|include_once|require_once)/i', $line)) {
        echo "<p>Line " . ($num + 1) . ": <code>" . htmlspecialchars(trim($line)) . "</code></p>";
    }
}

echo "<h3>Recommendations:</h3>";
echo "<ul>";
echo "<li>Clear your browser cache (Ctrl+F5 or Ctrl+Shift+R)</li>";
echo "<li>If using XAMPP, restart Apache server</li>";
echo "<li>Check if you're accessing the correct file URL</li>";
echo "<li>If error persists, check error logs in xampp/apache/logs/error.log</li>";
echo "</ul>";

echo "<h3>PHP Configuration:</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Error Reporting: " . (error_reporting() ? "Enabled" : "Disabled") . "</p>";
echo "<p>Display Errors: " . (ini_get('display_errors') ? "On" : "Off") . "</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #007bff; }
h3 { color: #666; }
pre { background: #f8f9fa; padding: 10px; border: 1px solid #e9ecef; border-radius: 4px; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>
