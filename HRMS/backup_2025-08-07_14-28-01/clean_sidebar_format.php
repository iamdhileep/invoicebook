<?php
// Clean up sidebar formatting

$sidebarFile = '../layouts/sidebar.php';
$content = file_get_contents($sidebarFile);

// Clean up extra line breaks around dropdown toggles
$content = preg_replace('/(\s*)<\/span>\s*\n\s*<\/a>/', '$1</span>$1</a>', $content);

// Ensure consistent formatting for dropdown toggles
$content = preg_replace('/(<span>[^<]+<\/span>)\s*\n\s*(<\/a>)/', '$1\n                        $2', $content);

file_put_contents($sidebarFile, $content);

echo "✅ Cleaned up sidebar formatting\n";
echo "✅ Fixed dropdown toggle layout\n";
?>
