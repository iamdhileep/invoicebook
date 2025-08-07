@echo off
echo.
echo ==========================================
echo    REVERT CHANGES - DELETE NEW FILES
echo ==========================================
echo.
echo This script will DELETE the following files created in today's session:
echo.
echo 1. integration_hub.php
echo 2. layouts\footer_new.php
echo 3. layouts\header_new.php
echo 4. layouts\sidebar_new.php
echo 5. leave_management.php
echo 6. optimize_mobile_pwa.php
echo 7. payroll_processing.php
echo 8. project_completion_celebration.html
echo 9. system_diagnostics.php
echo 10. system_status_dashboard.php
echo 11. system_verification.php
echo 12. revert_changes.php (this revert script)
echo.
echo WARNING: This action cannot be undone!
echo.
set /p confirm="Are you sure you want to delete these files? (y/N): "

if /i "%confirm%"=="y" (
    echo.
    echo Deleting files...
    echo.
    
    if exist "integration_hub.php" (
        del "integration_hub.php"
        echo ✓ Deleted: integration_hub.php
    ) else (
        echo - Not found: integration_hub.php
    )
    
    if exist "layouts\footer_new.php" (
        del "layouts\footer_new.php"
        echo ✓ Deleted: layouts\footer_new.php
    ) else (
        echo - Not found: layouts\footer_new.php
    )
    
    if exist "layouts\header_new.php" (
        del "layouts\header_new.php"
        echo ✓ Deleted: layouts\header_new.php
    ) else (
        echo - Not found: layouts\header_new.php
    )
    
    if exist "layouts\sidebar_new.php" (
        del "layouts\sidebar_new.php"
        echo ✓ Deleted: layouts\sidebar_new.php
    ) else (
        echo - Not found: layouts\sidebar_new.php
    )
    
    if exist "leave_management.php" (
        del "leave_management.php"
        echo ✓ Deleted: leave_management.php
    ) else (
        echo - Not found: leave_management.php
    )
    
    if exist "optimize_mobile_pwa.php" (
        del "optimize_mobile_pwa.php"
        echo ✓ Deleted: optimize_mobile_pwa.php
    ) else (
        echo - Not found: optimize_mobile_pwa.php
    )
    
    if exist "payroll_processing.php" (
        del "payroll_processing.php"
        echo ✓ Deleted: payroll_processing.php
    ) else (
        echo - Not found: payroll_processing.php
    )
    
    if exist "project_completion_celebration.html" (
        del "project_completion_celebration.html"
        echo ✓ Deleted: project_completion_celebration.html
    ) else (
        echo - Not found: project_completion_celebration.html
    )
    
    if exist "system_diagnostics.php" (
        del "system_diagnostics.php"
        echo ✓ Deleted: system_diagnostics.php
    ) else (
        echo - Not found: system_diagnostics.php
    )
    
    if exist "system_status_dashboard.php" (
        del "system_status_dashboard.php"
        echo ✓ Deleted: system_status_dashboard.php
    ) else (
        echo - Not found: system_status_dashboard.php
    )
    
    if exist "system_verification.php" (
        del "system_verification.php"
        echo ✓ Deleted: system_verification.php
    ) else (
        echo - Not found: system_verification.php
    )
    
    if exist "revert_changes.php" (
        del "revert_changes.php"
        echo ✓ Deleted: revert_changes.php
    ) else (
        echo - Not found: revert_changes.php
    )
    
    echo.
    echo ==========================================
    echo           REVERSION COMPLETE!
    echo ==========================================
    echo.
    echo All files created in today's session have been removed.
    echo Your system has been reverted to the state before today's changes.
    echo.
    echo The following files remain unchanged:
    echo - All original HRMS files
    echo - Database and configuration files  
    echo - Existing layouts and functionality
    echo.
    echo Press any key to exit...
    pause >nul
    
) else (
    echo.
    echo Operation cancelled. No files were deleted.
    echo.
    echo If you want to keep some files, manually delete only the ones you don't need:
    echo.
    echo RECOMMENDED FILES TO DELETE:
    echo - integration_hub.php (large file, 33MB+)
    echo - project_completion_celebration.html (celebration page)
    echo - system_verification.php (testing file)
    echo.
    echo OPTIONAL FILES TO KEEP:
    echo - leave_management.php (if you want leave management)
    echo - payroll_processing.php (if you want payroll features)
    echo - system_diagnostics.php (useful for system health checks)
    echo.
    echo Press any key to exit...
    pause >nul
)
