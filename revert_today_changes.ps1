# Revert Changes - PowerShell Version
# This script removes files created in today's chat session

Write-Host ""
Write-Host "==========================================" -ForegroundColor Yellow
Write-Host "   REVERT CHANGES - DELETE NEW FILES" -ForegroundColor Yellow  
Write-Host "==========================================" -ForegroundColor Yellow
Write-Host ""

$filesToDelete = @(
    "integration_hub.php",
    "layouts/footer_new.php", 
    "layouts/header_new.php",
    "layouts/sidebar_new.php",
    "leave_management.php",
    "optimize_mobile_pwa.php", 
    "payroll_processing.php",
    "project_completion_celebration.html",
    "system_diagnostics.php",
    "system_status_dashboard.php",
    "system_verification.php",
    "revert_changes.php"
)

Write-Host "This script will DELETE the following files created in today's session:" -ForegroundColor Cyan
Write-Host ""

for ($i = 0; $i -lt $filesToDelete.Length; $i++) {
    $fileStatus = if (Test-Path $filesToDelete[$i]) { "EXISTS" } else { "NOT FOUND" }
    $color = if (Test-Path $filesToDelete[$i]) { "White" } else { "DarkGray" }
    Write-Host "$($i + 1). $($filesToDelete[$i]) - $fileStatus" -ForegroundColor $color
}

Write-Host ""
Write-Host "WARNING: This action cannot be undone!" -ForegroundColor Red
Write-Host ""

$confirm = Read-Host "Are you sure you want to delete these files? (y/N)"

if ($confirm.ToLower() -eq "y") {
    Write-Host ""
    Write-Host "Deleting files..." -ForegroundColor Yellow
    Write-Host ""
    
    $deletedCount = 0
    $notFoundCount = 0
    
    foreach ($file in $filesToDelete) {
        if (Test-Path $file) {
            try {
                Remove-Item $file -Force
                Write-Host "✓ Deleted: $file" -ForegroundColor Green
                $deletedCount++
            } catch {
                Write-Host "✗ Failed to delete: $file - $($_.Exception.Message)" -ForegroundColor Red
            }
        } else {
            Write-Host "- Not found: $file" -ForegroundColor DarkGray
            $notFoundCount++
        }
    }
    
    Write-Host ""
    Write-Host "==========================================" -ForegroundColor Green
    Write-Host "           REVERSION COMPLETE!" -ForegroundColor Green
    Write-Host "==========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Summary:" -ForegroundColor Cyan
    Write-Host "- Files deleted: $deletedCount" -ForegroundColor Green
    Write-Host "- Files not found: $notFoundCount" -ForegroundColor DarkGray
    Write-Host ""
    Write-Host "Your system has been reverted to the state before today's changes." -ForegroundColor Green
    Write-Host ""
    Write-Host "The following remain unchanged:" -ForegroundColor Cyan
    Write-Host "- All original HRMS files"
    Write-Host "- Database and configuration files"  
    Write-Host "- Existing layouts and functionality"
    Write-Host ""
    
} else {
    Write-Host ""
    Write-Host "Operation cancelled. No files were deleted." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "If you want to keep some files, manually delete only the ones you don't need:" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "RECOMMENDED FILES TO DELETE:" -ForegroundColor Red
    Write-Host "- integration_hub.php (large file, 33MB+)"
    Write-Host "- project_completion_celebration.html (celebration page)"  
    Write-Host "- system_verification.php (testing file)"
    Write-Host ""
    Write-Host "OPTIONAL FILES TO KEEP:" -ForegroundColor Green
    Write-Host "- leave_management.php (if you want leave management)"
    Write-Host "- payroll_processing.php (if you want payroll features)"
    Write-Host "- system_diagnostics.php (useful for system health checks)"
    Write-Host ""
}

Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
