<?php
/**
 * Remove Complex Permission System from All Files
 * This script simplifies all HRMS files to use basic login-only auth
 */

echo "<h1>Simplifying Permission System</h1>";

// Files to update
$directories = ['HRMS', 'pages/dashboard', 'pages/employees', 'pages/expenses', 'pages/invoice', 'pages/manager', 'pages/payroll', 'pages/products', 'pages/attendance'];

$updated_files = 0;
$total_files = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    echo "<h2>Processing: $dir</h2>";
    
    $files = glob($dir . '/*.php');
    
    foreach ($files as $file) {
        $total_files++;
        echo "Processing: $file<br>";
        
        $content = file_get_contents($file);
        $original_content = $content;
        
        // Remove complex permission checks and replace with simple ones
        $replacements = [
            // Replace complex HRMS permission calls
            "checkHRMSPermission('attendance_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('employee_directory.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('leave_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('payroll_processing.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('onboarding_process.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('offboarding_process.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('performance_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('training_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('asset_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('department_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('employee_self_service.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('employee_profile.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('time_tracking.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('shift_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('salary_structure.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('goal_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('document_verification.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('payroll_reports.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('tax_management.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('training_schedule.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('performance_analytics.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('fnf_settlement.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('exit_interview.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('hr_insights.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('kpi_tracking.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('asset_allocation.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('employee_surveys.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('employee_helpdesk.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('workforce_analytics.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('custom_reports.php', HRMSPermissions::VIEW);" => "checkLogin();",
            "checkHRMSPermission('predictive_analytics.php', HRMSPermissions::VIEW);" => "checkLogin();",
            
            // Replace any other HRMS permission patterns
            "/checkHRMSPermission\([^)]+\);/" => "checkLogin();",
            
            // Replace group permission calls
            "checkGroupPermission('Dashboard');" => "checkLogin();",
            "checkGroupPermission('Employee Management');" => "checkLogin();",
            "checkGroupPermission('Invoice Management');" => "checkLogin();",
            "checkGroupPermission('Expense Management');" => "checkLogin();",
            "checkGroupPermission('Report Generation');" => "checkLogin();",
            
            // Replace any other group permission patterns
            "/checkGroupPermission\([^)]+\);/" => "checkLogin();",
        ];
        
        // Apply replacements
        foreach ($replacements as $old => $new) {
            if (strpos($old, '/') === 0) {
                // This is a regex pattern
                $content = preg_replace($old, $new, $content);
            } else {
                // This is a simple string replacement
                $content = str_replace($old, $new, $content);
            }
        }
        
        // Check if content changed
        if ($content !== $original_content) {
            file_put_contents($file, $content);
            echo "✅ Simplified: $file<br>";
            $updated_files++;
        } else {
            echo "⏭️ No changes needed: $file<br>";
        }
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "Total files processed: $total_files<br>";
echo "Files simplified: $updated_files<br>";
echo "<p>Permission system has been simplified to basic login-only checks!</p>";

echo "<h3>Test the simplified system:</h3>";
echo "<a href='final_test.php' target='_blank'>Test Dashboard Access</a><br>";
echo "<a href='HRMS/Hr_panel.php' target='_blank'>Test HRMS Panel</a><br>";
?>
