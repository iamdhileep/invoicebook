<?php
/**
 * Revert HRMS Files to Original Include Paths
 */

echo "<h1>Reverting HRMS Files to Original Include Paths</h1>";

$hrms_files = glob('HRMS/*.php');
$reverted_count = 0;

foreach ($hrms_files as $file) {
    if (!file_exists($file)) continue;
    
    echo "Processing: $file<br>";
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Revert includes back to relative paths
    $replacements = [
        // Revert config and database includes
        '$root_path = dirname(__DIR__);' => '',
        'include $root_path . \'/config.php\';' => 'include \'../config.php\';',
        'include $root_path . \'/db.php\';' => 'include \'../db.php\';',
        'require_once $root_path . \'/config.php\';' => 'require_once \'../config.php\';',
        'require_once $root_path . \'/db.php\';' => 'require_once \'../db.php\';',
        
        // Revert layout includes
        'include $root_path . \'/layouts/header.php\';' => 'include \'../layouts/header.php\';',
        'include $root_path . \'/layouts/sidebar.php\';' => 'include \'../layouts/sidebar.php\';',
        'include $root_path . \'/layouts/footer.php\';' => 'include \'../layouts/footer.php\';',
        
        // Remove duplicate root_path definitions
        'if (!isset($root_path)) $root_path = dirname(__DIR__);' => '',
        
        // Clean up any remaining root_path assignments at the end
        '$root_path = dirname(__DIR__);' => '',
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
        echo "✅ Reverted: $file<br>";
        $reverted_count++;
    } else {
        echo "⏭️ No changes needed: $file<br>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "Files processed: " . count($hrms_files) . "<br>";
echo "Files reverted: $reverted_count<br>";
echo "<p>✅ HRMS files have been reverted to original include paths!</p>";
?>
