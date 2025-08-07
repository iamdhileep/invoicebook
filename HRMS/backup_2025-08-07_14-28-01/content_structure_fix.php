<?php
echo "HRMS Content Structure Fix Script\n";
echo "=================================\n\n";

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

$filesToFix = [];
$filesSkipped = [];

foreach ($phpFiles as $file) {
    $filename = basename($file);
    
    // Skip utility files
    $skipFiles = [
        'hrms_header_simple.php', 'hrms_sidebar_simple.php', 'hrms_footer_simple.php',
        'debug_check.php', 'emergency_diagnostic.php', 'simple_fix_test.php',
        'replace_with_simple_layout.php', 'content_structure_fix.php'
    ];
    
    if (in_array($filename, $skipFiles)) {
        $filesSkipped[] = $filename;
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if file has conflicting structures
    if (strpos($content, '<div class="main-content') !== false || 
        strpos($content, '.main-content {') !== false ||
        strpos($content, 'main-content animate-fade-in-up') !== false ||
        strpos($content, '<!-- Content area handled by simple layout -->') !== false) {
        $filesToFix[] = $file;
    }
}

echo "Files to fix: " . count($filesToFix) . "\n";
echo "Files skipped: " . count($filesSkipped) . "\n\n";

if (empty($filesToFix)) {
    echo "No files need structure fixes!\n";
    exit;
}

echo "Files to fix:\n";
foreach ($filesToFix as $file) {
    echo "  📄 " . basename($file) . "\n";
}

echo "\nApplying structure fixes...\n\n";

$fixedCount = 0;
$errorCount = 0;

foreach ($filesToFix as $file) {
    $filename = basename($file);
    echo "Processing $filename... ";
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Remove conflicting main-content div wrappers
    $content = preg_replace(
        '/<div class=["\']main-content[^>]*>/',
        '<!-- Page Content Starts Here -->',
        $content
    );
    
    // Remove conflicting CSS for main-content
    $content = preg_replace(
        '/\.main-content\s*{[^}]*}/s',
        '',
        $content
    );
    
    // Remove old layout comments
    $content = str_replace(
        '<!-- Content area handled by simple layout -->',
        '<!-- Page Content Starts Here -->',
        $content
    );
    
    // Fix indentation issues from removed divs
    $content = preg_replace(
        '/^\s*<div class="container-fluid">/m',
        '<div class="container-fluid">',
        $content
    );
    
    // Remove duplicate closing divs at the end that were for main-content
    $content = preg_replace(
        '/<\/div>\s*<\/div>\s*(<\?php require_once [\'"]hrms_footer_simple\.php[\'"];)/m',
        '</div> $1',
        $content
    );
    
    // Remove extra closing main-content divs before footer
    $content = preg_replace(
        '/<\/div>\s*<!-- End main-content -->\s*(<\?php require_once)/m',
        '$1',
        $content
    );
    
    if ($content !== $originalContent) {
        if (file_put_contents($file, $content)) {
            echo "✅ FIXED\n";
            $fixedCount++;
        } else {
            echo "❌ ERROR: Could not write file\n";
            $errorCount++;
        }
    } else {
        echo "⚠️ NO CHANGES NEEDED\n";
    }
}

echo "\n=================================\n";
echo "Summary:\n";
echo "Fixed: $fixedCount files\n";
echo "Errors: $errorCount files\n";
echo "Skipped: " . count($filesSkipped) . " files\n";

if ($fixedCount > 0) {
    echo "\n✅ HRMS content structure has been cleaned up!\n";
    echo "\nChanges made:\n";
    echo "  • Removed conflicting main-content div wrappers\n";
    echo "  • Removed conflicting CSS styles\n";
    echo "  • Fixed indentation and structure\n";
    echo "  • Cleaned up old layout comments\n";
    echo "\nThe simple layout now handles all content wrapper functionality.\n";
    echo "Please test HRMS pages to verify they load correctly!\n";
}
?>
