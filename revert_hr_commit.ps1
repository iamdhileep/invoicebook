#!/usr/bin/env powershell
# Script to remove files added in commit 5183f048172dd6ed27bf169c71319d7426364f21

Write-Host "Removing files added in the HR Management System commit..."

# Files to remove (only the ones that were added, not modified)
$filesToRemove = @(
    "add_notes_column.php",
    "api/advanced_features.php",
    "api/analytics_reports.php",
    "api/apply_leave.php", 
    "api/apply_permission.php",
    "api/biometric_api_test.php",
    "api/export_leave_history.php",
    "api/get_leave_details.php",
    "api/get_leave_history.php",
    "api/leave_management.php",
    "api/manager_tools.php",
    "api/notification_system.php",
    "api/payslip_api.php",
    "api/reports_api.php",
    "api/salary_api.php",
    "api/smart_attendance.php",
    "api/time_tracking_api.php",
    "api/update_leave_status.php",
    "api_test.html",
    "api_test.php",
    "attendance_backup.php",
    "js/advanced_attendance.js",
    "login_new.php",
    "migrate_users_table.php",
    "mobile_attendance_api.php",
    "notification_system.php",
    "pages/employee/employee_portal.php",
    "pages/hr/hr_api.php",
    "pages/hr/hr_dashboard.php",
    "pages/manager/manager_dashboard.php",
    "quick_setup.php",
    "realtime_attendance_api.php",
    "setup_advanced_features.php",
    "setup_attendance_api.php",
    "setup_biometric_db.php",
    "setup_hr_database.php",
    "setup_password_reset.php",
    "setup_time_tracking.php",
    "setup_user_permissions.php",
    "setup_wizard.php",
    "smart_attendance_fixed.php",
    "smart_leave_api.php",
    "system_health_check.php"
)

$removedCount = 0
$notFoundCount = 0

foreach ($file in $filesToRemove) {
    if (Test-Path $file) {
        try {
            Remove-Item -Path $file -Force
            Write-Host "Removed: $file" -ForegroundColor Green
            $removedCount++
        } catch {
            Write-Host "Failed to remove: $file - $($_.Exception.Message)" -ForegroundColor Red
        }
    } else {
        Write-Host "Not found: $file" -ForegroundColor Yellow
        $notFoundCount++
    }
}

Write-Host "`nSummary:" -ForegroundColor Cyan
Write-Host "Files removed: $removedCount" -ForegroundColor Green
Write-Host "Files not found: $notFoundCount" -ForegroundColor Yellow

Write-Host "`nNote: Some files may have already been removed in subsequent commits." -ForegroundColor Gray
Write-Host "Core modified files (like layouts/sidebar.php, assets/css files) were preserved." -ForegroundColor Gray
