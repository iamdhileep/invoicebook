<?php
// System Restoration Verification
echo "<h2>🔧 System Restoration Verification</h2>\n";
echo "<h3>Checking file status...</h3>\n";

// Check auth_guard.php
if (file_exists('auth_guard.php')) {
    $content = file_get_contents('auth_guard.php');
    if (strpos($content, 'checkGroupPermission') === false && strpos($content, 'checkHRMSPermission') === false) {
        echo "✅ auth_guard.php: Clean (no permission functions)\n<br>";
    } else {
        echo "❌ auth_guard.php: Still contains permission functions\n<br>";
    }
} else {
    echo "❌ auth_guard.php: File missing!\n<br>";
}

// Check sample production files
$production_files = [
    'edit_item.php',
    'add_item.php', 
    'edit_invoice.php',
    'invoice_history.php',
    'advanced_attendance.php',
    'Employee_attendance.php',
    'portal_dashboard.php',
    'summary_dashboard.php'
];

$clean_count = 0;
foreach ($production_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'checkGroupPermission') === false && strpos($content, 'checkHRMSPermission') === false) {
            echo "✅ $file: Clean\n<br>";
            $clean_count++;
        } else {
            echo "❌ $file: Still contains permission functions\n<br>";
        }
    } else {
        echo "⚠️ $file: File not found\n<br>";
    }
}

echo "<h3>Summary:</h3>\n";
echo "📊 Production files checked: " . count($production_files) . "\n<br>";
echo "✅ Clean files: $clean_count\n<br>";

if ($clean_count === count($production_files)) {
    echo "<h3 style='color: green;'>🎉 SYSTEM RESTORATION COMPLETE!</h3>\n";
    echo "All permission-related changes have been successfully removed.\n<br>";
    echo "System is restored to original simple authentication.\n<br>";
} else {
    echo "<h3 style='color: red;'>⚠️ RESTORATION INCOMPLETE</h3>\n";
    echo "Some files still contain permission functions.\n<br>";
}

echo "<hr><small>Verification completed at " . date('Y-m-d H:i:s') . "</small>";
?>
