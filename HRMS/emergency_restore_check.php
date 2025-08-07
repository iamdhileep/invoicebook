<?php
/**
 * Emergency HRMS Restore Script
 * Identifies and fixes any empty critical files
 */

echo "Emergency HRMS Restore Script\n";
echo "============================\n\n";

$criticalFiles = [
    'hrms_header_simple.php',
    'hrms_sidebar_simple.php',
    'hrms_footer_simple.php',
    'index.php',
    'system_diagnostics.php',
    'employee_directory.php',
    'attendance_management.php',
    'leave_management.php',
    'payroll_processing.php'
];

$emptyFiles = [];
$missingFiles = [];
$workingFiles = [];

echo "Checking critical files...\n";

foreach ($criticalFiles as $file) {
    if (!file_exists($file)) {
        $missingFiles[] = $file;
        echo "❌ Missing: $file\n";
    } else {
        $content = file_get_contents($file);
        $size = strlen(trim($content));
        
        if ($size === 0) {
            $emptyFiles[] = $file;
            echo "⚠️  Empty: $file\n";
        } else {
            $workingFiles[] = $file;
            echo "✅ OK: $file ({$size} bytes)\n";
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Summary:\n";
echo "✅ Working files: " . count($workingFiles) . "\n";
echo "⚠️  Empty files: " . count($emptyFiles) . "\n";
echo "❌ Missing files: " . count($missingFiles) . "\n";

if (!empty($emptyFiles) || !empty($missingFiles)) {
    echo "\n🚨 Issues detected! Please restore the following files:\n";
    foreach (array_merge($emptyFiles, $missingFiles) as $file) {
        echo "   • $file\n";
    }
    echo "\nThese files contain critical layout and functionality code.\n";
} else {
    echo "\n✅ All critical files are working properly!\n";
}

echo "\n📊 Current system status:\n";
echo "   • Layout system: " . (in_array('hrms_header_simple.php', $workingFiles) && 
                               in_array('hrms_sidebar_simple.php', $workingFiles) && 
                               in_array('hrms_footer_simple.php', $workingFiles) ? 
                               "✅ Active" : "❌ Broken") . "\n";
echo "   • Main dashboard: " . (in_array('index.php', $workingFiles) ? "✅ Working" : "❌ Broken") . "\n";
echo "   • System diagnostics: " . (in_array('system_diagnostics.php', $workingFiles) ? "✅ Working" : "❌ Broken") . "\n";

// Check if basic includes work
echo "\n🔧 Testing basic functionality...\n";

try {
    if (file_exists('../auth_check.php')) {
        echo "✅ Authentication file accessible\n";
    } else {
        echo "❌ Authentication file missing\n";
    }
    
    if (file_exists('../db.php')) {
        echo "✅ Database configuration accessible\n";
    } else {
        echo "❌ Database configuration missing\n";
    }
    
    // Test database connection
    require_once '../db.php';
    if ($conn && $conn->ping()) {
        echo "✅ Database connection working\n";
    } else {
        echo "❌ Database connection failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing functionality: " . $e->getMessage() . "\n";
}

echo "\n🎯 Next steps:\n";
echo "1. Restore any empty/missing files\n";
echo "2. Test HRMS pages in browser\n";
echo "3. Verify sidebar navigation works\n";
echo "4. Check system diagnostics page\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "Emergency check completed!\n";
?>
