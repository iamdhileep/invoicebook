<?php
/**
 * Revert Authentication System in HRMS Files
 */

echo "<h1>Reverting Authentication System in HRMS Files</h1>";

$hrms_files = glob('HRMS/*.php');
$reverted_count = 0;

foreach ($hrms_files as $file) {
    if (!file_exists($file)) continue;
    
    echo "Processing: $file<br>";
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Revert authentication system back to original simple approach
    $replacements = [
        // Remove root_path for auth_guard includes
        'require_once $root_path . \'/auth_guard.php\';' => 'include \'../auth_guard.php\';',
        'include $root_path . \'/auth_guard.php\';' => 'include \'../auth_guard.php\';',
        
        // Remove checkLogin() function calls (since auth_guard.php now auto-checks)
        'checkLogin();' => '',
        
        // Remove any remaining root_path assignments
        '$root_path = dirname(__DIR__);' => '',
        'if (!isset($root_path)) $root_path = dirname(__DIR__);' => '',
    ];
    
    // Apply replacements
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    // Clean up extra blank lines
    $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
    
    // Write back if changed
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "✅ Reverted auth: $file<br>";
        $reverted_count++;
    } else {
        echo "⏭️ No auth changes needed: $file<br>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "Files processed: " . count($hrms_files) . "<br>";
echo "Files reverted: $reverted_count<br>";
echo "<p>✅ Authentication system has been reverted to original simple approach!</p>";
?>
