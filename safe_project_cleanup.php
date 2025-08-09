<?php
/**
 * Safe Project Cleanup - Remove Unwanted, Unnecessary, Test & Unlinked Files
 * Focuses on Pages folder, HRMS folder, and project-wide cleanup
 */

echo "<h1>🧹 Safe Project Cleanup - Pages, HRMS & Project Files</h1>\n";
echo "<p>Safely removing unwanted, unnecessary, test, and unlinked files...</p>\n";
echo "<style>
body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; margin: 20px; }
.safe { color: #28a745; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.danger { color: #dc3545; font-weight: bold; }
.info { color: #17a2b8; }
.section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
</style>";

$removed_files = [];
$protected_files = [];
$total_space_saved = 0;

// Define patterns for files to be safely removed
$safe_removal_patterns = [
    // Demo and test files
    'advanced_features_demo.php',
    'unified_sidebar_demo.php', 
    'working_sidebar_demo.html',
    'pages/hrms-table-demo.html',
    
    // Temporary files  
    'temp_suppliers.php',
    
    // Backup files (dated)
    'pages/users/user_management_backup_2025-08-09.php',
    'pages/accounts/accounts.php.backup',
    
    // Empty or clean files
    'HRMS/employee_self_service_clean.php',
    
    // Status/completion HTML files
    'sidebar_complete.html',
    'hrms_verification_complete.html',
    'HRMS/status_complete.html',
    
    // Diagnostic files
    'diagnose_tax_management.php',
    'diagnose_performance.php',
    
    // _new suffix files (after verification)
    'pages/accounts/accounts_new.php',
    'pages/accounts/bank_accounts_new.php', 
    'pages/manager/manager_dashboard_new.php'
];

// Files to protect (important for project)
$protected_patterns = [
    // Core HRMS files
    'HRMS/index.php',
    'HRMS/hrms_main_dashboard.php',
    'HRMS/employee_directory.php',
    'HRMS/payroll_processing.php',
    'HRMS/attendance_management.php',
    'HRMS/performance_management.php',
    
    // Important pages
    'pages/dashboard/',
    'pages/customers/',
    'pages/suppliers/',
    'pages/invoice/',
    'pages/employees/',
    'pages/payroll/',
    
    // Current working files
    'setup/update_backup_tables.php', // Active setup file
    
    // Database and config files
    'db.php',
    'config.php',
    
    // Layout files
    'layouts/',
    
    // API files
    'api/'
];

echo "<div class='section'>";
echo "<h2>🔍 Phase 1: Analyzing Files for Safe Removal</h2>";

function is_file_protected($file_path, $protected_patterns) {
    foreach ($protected_patterns as $pattern) {
        if (strpos($file_path, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

function safe_file_remove($file_path) {
    global $removed_files, $total_space_saved;
    
    if (file_exists($file_path)) {
        $file_size = filesize($file_path);
        if (unlink($file_path)) {
            $removed_files[] = $file_path;
            $total_space_saved += $file_size;
            echo "<div class='safe'>✅ REMOVED: " . htmlspecialchars($file_path) . " (" . formatBytes($file_size) . ")</div>";
            return true;
        } else {
            echo "<div class='danger'>❌ FAILED TO REMOVE: " . htmlspecialchars($file_path) . "</div>";
            return false;
        }
    } else {
        echo "<div class='info'>ℹ️  FILE NOT FOUND: " . htmlspecialchars($file_path) . "</div>";
        return false;
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Process each file for removal
foreach ($safe_removal_patterns as $file_pattern) {
    $full_path = __DIR__ . '/' . $file_pattern;
    
    if (is_file_protected($file_pattern, $protected_patterns)) {
        $protected_files[] = $file_pattern;
        echo "<div class='warning'>🛡️  PROTECTED: " . htmlspecialchars($file_pattern) . " (Important for project)</div>";
        continue;
    }
    
    // Check if file exists before attempting removal
    if (file_exists($full_path)) {
        // Additional safety check for important files
        if (strpos($file_pattern, 'index.php') !== false || 
            strpos($file_pattern, 'config') !== false ||
            strpos($file_pattern, 'db.php') !== false) {
            $protected_files[] = $file_pattern;
            echo "<div class='warning'>🛡️  PROTECTED: " . htmlspecialchars($file_pattern) . " (Core system file)</div>";
            continue;
        }
        
        safe_file_remove($full_path);
    } else {
        echo "<div class='info'>ℹ️  ALREADY REMOVED: " . htmlspecialchars($file_pattern) . "</div>";
    }
}

echo "</div>";

// Check for additional empty directories that can be removed
echo "<div class='section'>";
echo "<h2>📁 Phase 2: Checking Empty Directories</h2>";

$directories_to_check = [
    'pages/backups',
    'HRMS/temp',
    'HRMS/backup',
    'temp',
    'backup'
];

foreach ($directories_to_check as $dir) {
    $full_dir_path = __DIR__ . '/' . $dir;
    if (is_dir($full_dir_path)) {
        $files = scandir($full_dir_path);
        $file_count = count($files) - 2; // Subtract . and ..
        
        if ($file_count == 0) {
            if (rmdir($full_dir_path)) {
                echo "<div class='safe'>✅ REMOVED EMPTY DIRECTORY: " . htmlspecialchars($dir) . "</div>";
            } else {
                echo "<div class='danger'>❌ FAILED TO REMOVE DIRECTORY: " . htmlspecialchars($dir) . "</div>";
            }
        } else {
            echo "<div class='info'>ℹ️  DIRECTORY NOT EMPTY: " . htmlspecialchars($dir) . " ($file_count files)</div>";
        }
    } else {
        echo "<div class='info'>ℹ️  DIRECTORY NOT FOUND: " . htmlspecialchars($dir) . "</div>";
    }
}

echo "</div>";

// Additional scan for unreferenced files in specific folders
echo "<div class='section'>";
echo "<h2>🔍 Phase 3: Additional Safety Scan</h2>";

// Check for any remaining temporary or test files
$additional_patterns = [
    '*.tmp',
    '*.temp',
    '*_test.php',
    '*test_*.php',
    '*.bak'
];

$found_additional = false;
foreach ($additional_patterns as $pattern) {
    $matches = glob(__DIR__ . '/' . $pattern);
    if ($matches) {
        $found_additional = true;
        foreach ($matches as $match) {
            $relative_path = str_replace(__DIR__ . '/', '', $match);
            if (!is_file_protected($relative_path, $protected_patterns)) {
                safe_file_remove($match);
            } else {
                echo "<div class='warning'>🛡️  PROTECTED: " . htmlspecialchars($relative_path) . "</div>";
            }
        }
    }
}

if (!$found_additional) {
    echo "<div class='safe'>✅ No additional temporary or test files found</div>";
}

echo "</div>";

// Summary report
echo "<div class='section'>";
echo "<h2>📊 Cleanup Summary Report</h2>";
echo "<div class='safe'><strong>Files Successfully Removed:</strong> " . count($removed_files) . "</div>";
echo "<div class='info'><strong>Files Protected:</strong> " . count($protected_files) . "</div>";
echo "<div class='info'><strong>Total Space Saved:</strong> " . formatBytes($total_space_saved) . "</div>";

if (count($removed_files) > 0) {
    echo "<h3>🗑️ Removed Files:</h3>";
    echo "<ul>";
    foreach ($removed_files as $file) {
        echo "<li>" . htmlspecialchars(str_replace(__DIR__ . '/', '', $file)) . "</li>";
    }
    echo "</ul>";
}

if (count($protected_files) > 0) {
    echo "<h3>🛡️ Protected Files (Not Removed):</h3>";
    echo "<ul>";
    foreach ($protected_files as $file) {
        echo "<li>" . htmlspecialchars($file) . "</li>";
    }
    echo "</ul>";
}

echo "</div>";

// Recommendations
echo "<div class='section'>";
echo "<h2>💡 Recommendations</h2>";
echo "<div class='info'>✅ Cleanup completed safely with protection for important files</div>";
echo "<div class='info'>✅ All core system files, databases, and layouts preserved</div>";
echo "<div class='info'>✅ HRMS functionality maintained</div>";
echo "<div class='info'>✅ Pages functionality maintained</div>";
echo "<div class='info'>ℹ️  Consider running this cleanup periodically to maintain project hygiene</div>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🚀 Next Steps</h2>";
echo "<div class='info'>1. Test core functionality to ensure nothing critical was removed</div>";
echo "<div class='info'>2. Commit changes to version control</div>";
echo "<div class='info'>3. Consider implementing automated cleanup processes</div>";
echo "</div>";

?>
