<?php
echo "HRMS UI Fix Application Script\n";
echo "=============================\n\n";

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

$filesToFix = [];
$filesAlreadyFixed = [];

foreach ($phpFiles as $file) {
    $filename = basename($file);
    
    // Skip the fix files themselves
    if (in_array($filename, ['hrms_ui_fix.php', 'apply_hrms_ui_fix.php', 'test_sidebar_ui.php'])) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if file already has the UI fix
    if (strpos($content, 'hrms_ui_fix.php') !== false) {
        $filesAlreadyFixed[] = $filename;
        continue;
    }
    
    // Check if file has header include
    if (strpos($content, 'layouts/header.php') !== false) {
        $filesToFix[] = $file;
    }
}

echo "Files that need UI fix: " . count($filesToFix) . "\n";
echo "Files already fixed: " . count($filesAlreadyFixed) . "\n\n";

if (!empty($filesAlreadyFixed)) {
    echo "Already Fixed Files:\n";
    foreach ($filesAlreadyFixed as $filename) {
        echo "  ✅ $filename\n";
    }
    echo "\n";
}

if (empty($filesToFix)) {
    echo "All files are already fixed or don't need fixing!\n";
    exit;
}

echo "Files to fix:\n";
foreach ($filesToFix as $file) {
    echo "  📄 " . basename($file) . "\n";
}

echo "\nApplying fixes...\n\n";

$fixedCount = 0;
$errorCount = 0;

foreach ($filesToFix as $file) {
    $filename = basename($file);
    echo "Processing $filename... ";
    
    $content = file_get_contents($file);
    
    // Find the pattern where sidebar is included
    $pattern = '/require_once\s+[\'"]\.\.\/layouts\/sidebar\.php[\'"];/';
    
    if (preg_match($pattern, $content)) {
        // Replace with the sidebar include + UI fix
        $replacement = "require_once '../layouts/sidebar.php';\n\n// Include HRMS UI fix\nrequire_once 'hrms_ui_fix.php';";
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            if (file_put_contents($file, $newContent)) {
                echo "✅ FIXED\n";
                $fixedCount++;
            } else {
                echo "❌ ERROR: Could not write file\n";
                $errorCount++;
            }
        } else {
            echo "⚠️ NO CHANGE\n";
        }
    } else {
        // Try to find after header include instead
        $headerPattern = '/require_once\s+[\'"]\.\.\/layouts\/header\.php[\'"];/';
        
        if (preg_match($headerPattern, $content)) {
            $replacement = "require_once '../layouts/header.php';\nrequire_once '../layouts/sidebar.php';\n\n// Include HRMS UI fix\nrequire_once 'hrms_ui_fix.php';";
            $newContent = preg_replace($headerPattern, $replacement, $content);
            
            // Remove any subsequent sidebar includes to avoid duplicates
            $newContent = preg_replace('/require_once\s+[\'"]\.\.\/layouts\/sidebar\.php[\'"];/', '', $newContent, 1);
            
            if (file_put_contents($file, $newContent)) {
                echo "✅ FIXED (added sidebar include)\n";
                $fixedCount++;
            } else {
                echo "❌ ERROR: Could not write file\n";
                $errorCount++;
            }
        } else {
            echo "⚠️ SKIP: No header include found\n";
        }
    }
}

echo "\n=============================\n";
echo "Summary:\n";
echo "Fixed: $fixedCount files\n";
echo "Errors: $errorCount files\n";
echo "Already fixed: " . count($filesAlreadyFixed) . " files\n";
echo "Total HRMS files processed: " . (count($filesToFix) + count($filesAlreadyFixed)) . " files\n";

if ($fixedCount > 0) {
    echo "\n✅ HRMS UI fixes have been applied successfully!\n";
    echo "The sidebar should now display properly with:\n";
    echo "  • Professional styling and colors\n";
    echo "  • Working hover effects\n";
    echo "  • Proper icon alignment\n";
    echo "  • Functional dropdown menus\n";
    echo "  • Responsive behavior\n";
    echo "\nPlease refresh your browser and test the HRMS pages.\n";
}

if ($errorCount > 0) {
    echo "\n⚠️ Some files had errors. Please check file permissions.\n";
}
?>
