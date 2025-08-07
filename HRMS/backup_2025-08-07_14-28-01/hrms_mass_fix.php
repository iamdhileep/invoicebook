<?php
/**
 * HRMS Files Mass Fix Script
 * This script will fix all HRMS PHP files with database and authentication issues
 */

echo "<h1>HRMS Files Mass Fix</h1>";

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

// Files to skip (this script and test files)
$skipFiles = [
    'hrms_mass_fix.php',
    'setup_leave_table.php',
    'test_db_connection.php',
    'setup_hrms_tables.php',
    'debug_employee_directory.php'
];

$fixedCount = 0;
$errorCount = 0;

echo "<h2>Processing " . count($phpFiles) . " PHP files...</h2>";

foreach ($phpFiles as $filePath) {
    $fileName = basename($filePath);
    
    // Skip certain files
    if (in_array($fileName, $skipFiles)) {
        echo "<p>⏭️ Skipped: $fileName</p>";
        continue;
    }
    
    try {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Could not read file");
        }
        
        $originalContent = $content;
        
        // Common fixes
        $fixes = [
            // Fix includes
            "require_once 'includes/hrms_config.php';" => "require_once '../auth_check.php';\nrequire_once '../db.php';",
            "require_once '../includes/config.php';" => "require_once '../auth_check.php';\nrequire_once '../db.php';",
            "require_once '../includes/HRMSHelper.php';" => "",
            
            // Fix authentication checks
            "if (!HRMSHelper::isLoggedIn()) {" => "if (!isset(\$_SESSION['user_id'])) {",
            "header('Location: ../hrms_portal.php?redirect=HRMS/" => "header('Location: ../login.php');",
            
            // Fix user data retrieval
            "HRMSHelper::getCurrentUserId()" => "\$_SESSION['user_id']",
            "HRMSHelper::getCurrentUserRole()" => "\$_SESSION['role'] ?? 'employee'",
            
            // Fix database queries
            "HRMSHelper::safeQuery(" => "\$conn->query(",
            "HRMSHelper::hasPermission(" => "(\$_SESSION['role'] === 'admin' || \$_SESSION['role'] === 'hr'",
            
            // Fix result handling - this is trickier, we'll handle it separately
            "if (\$result && \$result->num_rows > 0)" => "if (\$result && \$result->num_rows > 0)",
        ];
        
        // Apply basic fixes
        foreach ($fixes as $search => $replace) {
            if (strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);
            }
        }
        
        // Fix page title and auth section at the beginning
        if (strpos($content, '$page_title = ') !== false) {
            // Extract page title
            preg_match('/\$page_title = ["\']([^"\']+)["\'];/', $content, $matches);
            $pageTitle = $matches[1] ?? 'HRMS Page';
            
            $authSection = "<?php\n\$page_title = \"$pageTitle\";\n\n// Include authentication and database\nrequire_once '../auth_check.php';\nrequire_once '../db.php';\n\n// Include layouts\nrequire_once 'hrms_header_simple.php';\nrequire_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
\n\n\$currentUserId = \$_SESSION['user_id'];\n\$currentUserRole = \$_SESSION['role'] ?? 'employee';\n";
            
            // Replace everything from start until the first real content
            $content = preg_replace('/^<\?php.*?require_once.*?sidebar\.php.*?\n/s', $authSection, $content);
        }
        
        // Additional specific fixes
        $content = str_replace('new HRMSHelper()', '$conn', $content);
        $content = str_replace('$hrms = $conn', '$hrms = $conn', $content); // Ensure consistency
        
        // Write back if changes were made
        if ($content !== $originalContent) {
            if (file_put_contents($filePath, $content) !== false) {
                echo "<p style='color: green;'>✅ Fixed: $fileName</p>";
                $fixedCount++;
            } else {
                echo "<p style='color: red;'>❌ Could not write: $fileName</p>";
                $errorCount++;
            }
        } else {
            echo "<p>⚪ No changes needed: $fileName</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error processing $fileName: " . $e->getMessage() . "</p>";
        $errorCount++;
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Files Fixed:</strong> $fixedCount</p>";
echo "<p><strong>Errors:</strong> $errorCount</p>";
echo "<p><strong>Total Processed:</strong> " . count($phpFiles) . "</p>";

echo "<hr>";
echo "<p><a href='employee_directory.php'>Test Employee Directory</a> | <a href='leave_management.php'>Test Leave Management</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
p { margin: 5px 0; }
</style>
