<?php
echo "HRMS Layout Replacement Script\n";
echo "==============================\n\n";

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

$filesToUpdate = [];
$filesAlreadySimple = [];
$filesSkipped = [];

foreach ($phpFiles as $file) {
    $filename = basename($file);
    
    // Skip the layout files themselves and utility files
    $skipFiles = [
        'hrms_header_simple.php', 'hrms_sidebar_simple.php', 'hrms_footer_simple.php',
        'hrms_ui_fix.php', 'apply_hrms_ui_fix.php', 'emergency_diagnostic.php', 
        'simple_fix_test.php', 'replace_with_simple_layout.php', 'final_ui_verification.php',
        'diagnostic_ui_check.php', 'path_debug_test.php'
    ];
    
    if (in_array($filename, $skipFiles)) {
        $filesSkipped[] = $filename;
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if file already uses simple layout
    if (strpos($content, 'hrms_header_simple.php') !== false) {
        $filesAlreadySimple[] = $filename;
        continue;
    }
    
    // Check if file has standard layout includes
    if (strpos($content, 'layouts/header.php') !== false || strpos($content, 'layouts/sidebar.php') !== false) {
        $filesToUpdate[] = $file;
    }
}

echo "Files to update: " . count($filesToUpdate) . "\n";
echo "Files already using simple layout: " . count($filesAlreadySimple) . "\n";
echo "Files skipped: " . count($filesSkipped) . "\n\n";

if (!empty($filesAlreadySimple)) {
    echo "Already using simple layout:\n";
    foreach ($filesAlreadySimple as $filename) {
        echo "  ✅ $filename\n";
    }
    echo "\n";
}

if (empty($filesToUpdate)) {
    echo "All applicable files are already updated or don't need updating!\n";
    exit;
}

echo "Files to update:\n";
foreach ($filesToUpdate as $file) {
    echo "  📄 " . basename($file) . "\n";
}

echo "\nApplying simple layout replacement...\n\n";

$updateCount = 0;
$errorCount = 0;

foreach ($filesToUpdate as $file) {
    $filename = basename($file);
    echo "Processing $filename... ";
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Replace header includes
    $content = preg_replace(
        '/require_once\s+[\'"]\.\.\/layouts\/header\.php[\'"];/',
        "require_once 'hrms_header_simple.php';",
        $content
    );
    
    // Replace sidebar includes
    $content = preg_replace(
        '/require_once\s+[\'"]\.\.\/layouts\/sidebar\.php[\'"];/',
        "require_once 'hrms_sidebar_simple.php';",
        $content
    );
    
    // Remove HRMS UI fix includes (no longer needed)
    $content = preg_replace(
        '/require_once\s+[\'"]hrms_ui_fix\.php[\'"];\s*/',
        '',
        $content
    );
    
    // Replace footer includes
    $content = preg_replace(
        '/require_once\s+[\'"]\.\.\/layouts\/footer\.php[\'"];/',
        "require_once 'hrms_footer_simple.php';",
        $content
    );
    
    // Handle include statements too
    $content = preg_replace(
        '/include\s+[\'"]\.\.\/layouts\/header\.php[\'"];/',
        "require_once 'hrms_header_simple.php';",
        $content
    );
    
    $content = preg_replace(
        '/include\s+[\'"]\.\.\/layouts\/sidebar\.php[\'"];/',
        "require_once 'hrms_sidebar_simple.php';",
        $content
    );
    
    $content = preg_replace(
        '/include\s+[\'"]\.\.\/layouts\/footer\.php[\'"];/',
        "require_once 'hrms_footer_simple.php';",
        $content
    );
    
    // Remove main-content div wrappers if they exist (the simple layout handles this)
    $content = preg_replace(
        '/<div class=["\']main-content["\']>/',
        '<!-- Content area handled by simple layout -->',
        $content
    );
    
    // Close the content divs at the end
    $content = preg_replace(
        '/<\/div>\s*<\?php\s+require_once\s+[\'"]hrms_footer_simple\.php[\'"];\s*\?>/',
        '<?php require_once "hrms_footer_simple.php"; ?>',
        $content
    );
    
    if ($content !== $originalContent) {
        if (file_put_contents($file, $content)) {
            echo "✅ UPDATED\n";
            $updateCount++;
        } else {
            echo "❌ ERROR: Could not write file\n";
            $errorCount++;
        }
    } else {
        echo "⚠️ NO CHANGES NEEDED\n";
    }
}

echo "\n==============================\n";
echo "Summary:\n";
echo "Updated: $updateCount files\n";
echo "Errors: $errorCount files\n";
echo "Already simple: " . count($filesAlreadySimple) . " files\n";
echo "Skipped utility files: " . count($filesSkipped) . " files\n";
echo "Total HRMS files processed: " . (count($filesToUpdate) + count($filesAlreadySimple)) . " files\n";

if ($updateCount > 0) {
    echo "\n🎉 SUCCESS! HRMS files have been updated to use the simplified layout!\n";
    echo "\nKey improvements:\n";
    echo "  • Removed complex CSS conflicts\n";
    echo "  • Simplified header and sidebar structure\n";
    echo "  • Clean, professional styling\n";
    echo "  • Responsive design\n";
    echo "  • Bootstrap 5 compatibility\n";
    echo "  • Consistent HRMS navigation\n";
    echo "\nThe sidebar should now work correctly on all HRMS pages!\n";
    echo "Please test any HRMS page to verify the fixes.\n";
}

if ($errorCount > 0) {
    echo "\n⚠️ Some files had errors. Please check file permissions.\n";
}
?>
