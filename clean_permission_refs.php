<?php
/**
 * Clean Permission References from HRMS Files
 */

echo "<h1>Cleaning Permission References from All Files</h1>";

// Get all HRMS files that contain permission references
$hrms_files = glob('HRMS/*.php');
$main_files = ['add_employee.php', 'edit_employee.php', 'delete_employee.php'];
$all_files = array_merge($hrms_files, $main_files);

$cleaned_count = 0;

foreach ($all_files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Remove permission-related lines
    $lines = explode("\n", $content);
    $cleaned_lines = [];
    
    foreach ($lines as $line) {
        // Skip lines with permission comments or function calls
        if (strpos($line, '// Check') !== false && 
            (strpos($line, 'permission') !== false || 
             strpos($line, 'panel permission') !== false ||
             strpos($line, 'management permission') !== false ||
             strpos($line, 'directory permission') !== false ||
             strpos($line, 'service permission') !== false)) {
            continue; // Skip this line
        }
        
        // Skip permission function calls
        if (strpos($line, 'checkHRMSPermission') !== false ||
            strpos($line, 'checkGroupPermission') !== false ||
            strpos($line, 'PermissionManager') !== false ||
            strpos($line, 'hasPermission') !== false ||
            strpos($line, 'restrictAccess') !== false) {
            continue; // Skip this line
        }
        
        // Skip permission includes
        if (strpos($line, 'PermissionManager.php') !== false ||
            strpos($line, 'rbac_system.php') !== false) {
            continue; // Skip this line
        }
        
        $cleaned_lines[] = $line;
    }
    
    $new_content = implode("\n", $cleaned_lines);
    
    // Clean up extra blank lines
    $new_content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $new_content);
    
    if ($new_content !== $original_content) {
        file_put_contents($file, $new_content);
        echo "✅ Cleaned: $file<br>";
        $cleaned_count++;
    } else {
        echo "⏭️ No changes needed: $file<br>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "Files processed: " . count($all_files) . "<br>";
echo "Files cleaned: $cleaned_count<br>";
echo "<p>✅ All permission references have been cleaned!</p>";
?>
