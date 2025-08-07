@echo off
echo.
echo ==============================================
echo    REVERT LAST CHAT REQUEST ONLY (After 6PM)
echo ==============================================
echo.
echo This script will DELETE ONLY the files created after 6 PM today.
echo.
echo ✅ FILES TO BE PRESERVED (created before 6 PM):
echo    • integration_hub.php (33MB enterprise features)
echo    • project_completion_celebration.html
echo    • system_status_dashboard.php  
echo    • system_verification.php
echo    • layouts\footer_new.php
echo    • layouts\header_new.php
echo    • layouts\sidebar_new.php
echo    • optimize_mobile_pwa.php
echo.
echo ❌ FILES TO BE REMOVED (created after 6 PM):
echo    • revert_today_changes.ps1
echo    • revert_today_changes.bat
echo    • revert_changes.php
echo    • employee_directory.php
echo    • system_diagnostics.php
echo    • hrms_footer_simple.php
echo    • hrms_sidebar_simple.php
echo    • hrms_header_simple.php
echo    • emergency_restore_check.php
echo    • payroll_processing.php
echo    • leave_management.php
echo    • attendance_management.php
echo.
echo WARNING: This action cannot be undone!
echo.
set /p confirm="Are you sure you want to delete ONLY the recent files? (y/N): "

if /i "%confirm%"=="y" (
    echo.
    echo Deleting recent files (after 6 PM)...
    echo.
    
    if exist "revert_today_changes.ps1" (
        del "revert_today_changes.ps1"
        echo ✓ Deleted: revert_today_changes.ps1
    ) else (
        echo - Not found: revert_today_changes.ps1
    )
    
    if exist "revert_changes.php" (
        del "revert_changes.php"
        echo ✓ Deleted: revert_changes.php
    ) else (
        echo - Not found: revert_changes.php
    )
    
    if exist "employee_directory.php" (
        del "employee_directory.php"
        echo ✓ Deleted: employee_directory.php
    ) else (
        echo - Not found: employee_directory.php
    )
    
    if exist "system_diagnostics.php" (
        del "system_diagnostics.php"
        echo ✓ Deleted: system_diagnostics.php
    ) else (
        echo - Not found: system_diagnostics.php
    )
    
    if exist "hrms_footer_simple.php" (
        del "hrms_footer_simple.php"
        echo ✓ Deleted: hrms_footer_simple.php
    ) else (
        echo - Not found: hrms_footer_simple.php
    )
    
    if exist "hrms_sidebar_simple.php" (
        del "hrms_sidebar_simple.php"
        echo ✓ Deleted: hrms_sidebar_simple.php
    ) else (
        echo - Not found: hrms_sidebar_simple.php
    )
    
    if exist "hrms_header_simple.php" (
        del "hrms_header_simple.php"
        echo ✓ Deleted: hrms_header_simple.php
    ) else (
        echo - Not found: hrms_header_simple.php
    )
    
    if exist "emergency_restore_check.php" (
        del "emergency_restore_check.php"
        echo ✓ Deleted: emergency_restore_check.php
    ) else (
        echo - Not found: emergency_restore_check.php
    )
    
    if exist "payroll_processing.php" (
        del "payroll_processing.php"
        echo ✓ Deleted: payroll_processing.php
    ) else (
        echo - Not found: payroll_processing.php
    )
    
    if exist "leave_management.php" (
        del "leave_management.php"
        echo ✓ Deleted: leave_management.php
    ) else (
        echo - Not found: leave_management.php
    )
    
    if exist "attendance_management.php" (
        del "attendance_management.php"
        echo ✓ Deleted: attendance_management.php
    ) else (
        echo - Not found: attendance_management.php
    )
    
    echo.
    echo ==============================================
    echo         SELECTIVE REVERSION COMPLETE!
    echo ==============================================
    echo.
    echo ✅ PRESERVED - All major work from earlier today:
    echo   - Integration Hub (33MB enterprise system)
    echo   - Project Completion Celebration
    echo   - System Status Dashboard
    echo   - System Verification Tools
    echo   - New Layout Files
    echo.
    echo ❌ REMOVED - Only recent files after 6 PM:
    echo   - HRMS layout files and recent additions
    echo   - Duplicate management files
    echo   - Revert scripts
    echo.
    echo Your system now contains all the valuable work from
    echo earlier today while removing only the recent additions.
    echo.
    echo Press any key to exit...
    pause >nul
    
    REM Delete this script last
    del "%~f0"
    
) else (
    echo.
    echo Operation cancelled. No files were deleted.
    echo.
    echo All files remain as they were, including:
    echo ✅ Major enterprise features from earlier today
    echo ✅ Recent files from after 6 PM
    echo.
    echo Press any key to exit...
    pause >nul
)
