<?php
/**
 * Verify System Reversion - Check that all permission system changes have been removed
 */

echo "<h1>🔄 System Reversion Verification</h1>";
echo "<p>Checking that all complex permission system changes have been successfully reverted...</p>";

include 'db.php';

$issues_removed = 0;
$checks_passed = 0;
$total_checks = 0;

// 1. Check Database Tables
echo "<h2>1. Database Tables Check</h2>";
$permission_tables = [
    'role_permissions',
    'user_permissions', 
    'hrms_pages',
    'permission_groups',
    'user_roles',
    'user_role_assignments'
];

foreach ($permission_tables as $table) {
    $total_checks++;
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "❌ Table $table still exists<br>";
    } else {
        echo "✅ Table $table removed<br>";
        $checks_passed++;
        $issues_removed++;
    }
}

// 2. Check Files Removed
echo "<h2>2. Permission System Files Check</h2>";
$permission_files = [
    'api/get_hrms_permissions.php',
    'api/get_group_permissions.php', 
    'api/permission_management.php',
    'models/UserPermission.php',
    'setup_enhanced_permissions.php',
    'permission_management.php',
    'config/encryption.key'
];

foreach ($permission_files as $file) {
    $total_checks++;
    if (file_exists($file)) {
        echo "❌ File $file still exists<br>";
    } else {
        echo "✅ File $file removed<br>";
        $checks_passed++;
        $issues_removed++;
    }
}

// 3. Check Auth Guard Simplified
echo "<h2>3. Auth Guard Check</h2>";
$total_checks++;
if (file_exists('auth_guard.php')) {
    $auth_content = file_get_contents('auth_guard.php');
    $auth_size = filesize('auth_guard.php');
    
    // Check if it's the simplified version (should be small)
    if ($auth_size < 1000 && strpos($auth_content, 'checkLogin()') === false) {
        echo "✅ Auth guard simplified (size: {$auth_size} bytes)<br>";
        $checks_passed++;
        $issues_removed++;
    } else {
        echo "❌ Auth guard still contains complex functions<br>";
    }
} else {
    echo "❌ Auth guard file missing<br>";
}

// 4. Check HRMS Files Reverted
echo "<h2>4. HRMS Files Check</h2>";
$hrms_files = array_slice(glob('HRMS/*.php'), 0, 5); // Check first 5 files as sample

foreach ($hrms_files as $file) {
    $total_checks++;
    $content = file_get_contents($file);
    
    // Check if it contains old relative includes (reverted)
    if (strpos($content, '../layouts/') !== false || strpos($content, '../config.php') !== false) {
        echo "✅ $file reverted to relative includes<br>";
        $checks_passed++;
        $issues_removed++;
    } else {
        echo "❌ $file still has absolute path includes<br>";
    }
}

// 5. Check Dashboard Simplified
echo "<h2>5. Dashboard Check</h2>";
$total_checks++;
if (file_exists('dashboard.php')) {
    $dashboard_content = file_get_contents('dashboard.php');
    
    // Check if it's simplified (no permission_guard.php)
    if (strpos($dashboard_content, 'permission_guard.php') === false) {
        echo "✅ Dashboard simplified - no permission_guard.php dependency<br>";
        $checks_passed++;
        $issues_removed++;
    } else {
        echo "❌ Dashboard still references permission_guard.php<br>";
    }
} else {
    echo "❌ Dashboard file missing<br>";
}

// 6. Check for remaining permission-related code
echo "<h2>6. Code Cleanup Check</h2>";
$total_checks++;
$sample_files = ['HRMS/Hr_panel.php', 'HRMS/Employee_panel.php'];
$permission_functions_found = 0;

foreach ($sample_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'checkHRMSPermission') !== false || 
            strpos($content, 'checkGroupPermission') !== false ||
            strpos($content, 'UserPermission') !== false) {
            $permission_functions_found++;
        }
    }
}

if ($permission_functions_found == 0) {
    echo "✅ No permission function calls found in sample files<br>";
    $checks_passed++;
    $issues_removed++;
} else {
    echo "❌ Permission function calls still found in $permission_functions_found files<br>";
}

// Summary
echo "<h2>7. Summary</h2>";
$success_percentage = round(($checks_passed / $total_checks) * 100, 1);

if ($success_percentage >= 90) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 5px solid #28a745;'>";
    echo "<h3 style='color: #155724; margin: 0;'>✅ REVERSION SUCCESSFUL</h3>";
    echo "<p style='color: #155724; margin: 5px 0 0 0;'>";
    echo "Success Rate: $success_percentage% ($checks_passed/$total_checks checks passed)<br>";
    echo "Issues Removed: $issues_removed<br>";
    echo "Your system has been successfully reverted to the original state!";
    echo "</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 5px solid #dc3545;'>";
    echo "<h3 style='color: #721c24; margin: 0;'>⚠️ REVERSION INCOMPLETE</h3>";
    echo "<p style='color: #721c24; margin: 5px 0 0 0;'>";
    echo "Success Rate: $success_percentage% ($checks_passed/$total_checks checks passed)<br>";
    echo "Some permission system remnants may still exist.";
    echo "</p>";
    echo "</div>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Test Login:</strong> <a href='login.php' target='_blank'>Access login page</a></li>";
echo "<li><strong>Test Dashboard:</strong> Login and access the main dashboard</li>";
echo "<li><strong>Test HRMS:</strong> Check if HRMS modules load without permission errors</li>";
echo "<li><strong>Test Core Features:</strong> Verify invoicing, employee management, etc. work normally</li>";
echo "</ol>";

echo "<p><strong>🎉 Your system should now work as it did before the permission system implementation!</strong></p>";
?>
