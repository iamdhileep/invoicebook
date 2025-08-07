<?php
/**
 * HRMS Database Integration Completeness Script
 * Ensures all HRMS files have proper database connectivity
 */

echo "Starting HRMS Database Integration Update...\n";

// Files that need database integration checks
$files_to_check = [
    'salary_structure.php',
    'performance_management.php', 
    'training_management.php',
    'system_optimizer.php',
    'employee_self_service.php',
    'time_tracking.php',
    'shift_management.php',
    'announcements.php'
];

// Standard database include block
$db_include_block = "<?php
session_start();
\$page_title = \"[PAGE_TITLE]\";

// Include header and navigation
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
include '../db.php';";

// Check each file and add database integration if missing
foreach ($files_to_check as $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Check if file already has database inclusion
        if (strpos($content, "include '../db.php'") === false && 
            strpos($content, 'include "../db.php"') === false) {
            
            echo "Updating $file with database integration...\n";
            
            // Extract page title from existing content
            preg_match('/\$page_title\s*=\s*["\']([^"\']+)["\']/', $content, $matches);
            $page_title = isset($matches[1]) ? $matches[1] : ucfirst(str_replace('.php', '', $file));
            
            // Replace the beginning of the file
            $updated_include = str_replace('[PAGE_TITLE]', $page_title, $db_include_block);
            
            // Replace the opening PHP tag and any existing includes
            $content = preg_replace('/^<\?php.*?(?=<div|<html|<!DOCTYPE)/s', $updated_include . "\n?>\n\n", $content);
            
            file_put_contents($file_path, $content);
            echo "✅ Updated $file\n";
        } else {
            echo "✅ $file already has database integration\n";
        }
    } else {
        echo "⚠️  $file not found, creating basic template...\n";
        
        // Create basic template for missing files
        $template = str_replace('[PAGE_TITLE]', ucfirst(str_replace(['.php', '_'], ['', ' '], $file)), $db_include_block);
        $template .= "\n?>\n\n<div class=\"main-content\">\n    <div class=\"container-fluid p-4\">\n        <h1>" . ucfirst(str_replace(['.php', '_'], ['', ' '], $file)) . "</h1>\n        <p>This page is under development.</p>\n    </div>\n</div>\n\n<?php require_once 'hrms_footer_simple.php'; ?>";
        
        file_put_contents($file_path, $template);
        echo "✅ Created template for $file\n";
    }
}

echo "\n🎉 HRMS Database Integration Update Complete!\n";
echo "All HRMS files now have proper database connectivity.\n";

require_once 'hrms_footer_simple.php';
?>