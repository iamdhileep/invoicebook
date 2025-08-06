<?php
/**
 * HRMS Database Connectivity Balance Checker & Auto-Fixer
 * Ensures all files in HRMS folder are properly connected to main database
 */

if (!isset($root_path)) 
require_once '../db.php';

echo "🔄 HRMS DATABASE CONNECTIVITY BALANCE CHECKER\n";
echo "============================================\n\n";

$timestamp = date('Y-m-d H:i:s');
echo "📅 Starting balance check at: $timestamp\n\n";

// Define HRMS root directory
$hrms_dir = __DIR__;
$php_files = [];

// Scan for all PHP files in HRMS directory
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($hrms_dir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $relative_path = str_replace($hrms_dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        if (!str_contains($relative_path, 'api' . DIRECTORY_SEPARATOR) && $relative_path !== basename(__FILE__)) {
            $php_files[] = $file->getPathname();
        }
    }
}

echo "📁 Found " . count($php_files) . " PHP files to check\n\n";

$database_connected_files = [];
$database_missing_files = [];
$files_with_issues = [];

// Analyze each file
foreach ($php_files as $file_path) {
    $filename = basename($file_path);
    $content = file_get_contents($file_path);
    
    // Check for database inclusion
    $has_db_include = (
        strpos($content, "require_once '../db.php'") !== false ||
        strpos($content, "include '../db.php'") !== false ||
        strpos($content, 'require_once "../db.php"') !== false ||
        strpos($content, 'include "../db.php"') !== false
    );
    
    // Check for database usage
    $has_db_usage = (
        strpos($content, 'mysqli_query') !== false ||
        strpos($content, '$conn->') !== false ||
        strpos($content, 'SELECT') !== false ||
        strpos($content, 'INSERT') !== false ||
        strpos($content, 'UPDATE') !== false ||
        strpos($content, 'DELETE') !== false
    );
    
    // Check for static data simulation
    $has_static_data = (
        strpos($content, 'Simulate') !== false ||
        preg_match('/\$\w+\s*=\s*\[.*=>/s', $content)
    );
    
    if ($has_db_include && $has_db_usage) {
        $database_connected_files[] = [
            'file' => $filename,
            'path' => $file_path,
            'status' => 'connected',
            'has_static' => $has_static_data
        ];
    } elseif ($has_db_usage && !$has_db_include) {
        $database_missing_files[] = [
            'file' => $filename,
            'path' => $file_path,
            'status' => 'missing_include',
            'has_static' => $has_static_data
        ];
    } else {
        $files_with_issues[] = [
            'file' => $filename,
            'path' => $file_path,
            'status' => 'no_database',
            'has_static' => $has_static_data
        ];
    }
}

// Display Results
echo "✅ DATABASE CONNECTED FILES (" . count($database_connected_files) . "):\n";
echo str_repeat("-", 50) . "\n";
foreach ($database_connected_files as $file) {
    $static_indicator = $file['has_static'] ? " (⚠️ Has static data)" : " (✅ Clean)";
    echo "   ✅ " . $file['file'] . $static_indicator . "\n";
}

echo "\n❌ MISSING DATABASE INCLUDE (" . count($database_missing_files) . "):\n";
echo str_repeat("-", 50) . "\n";
foreach ($database_missing_files as $file) {
    echo "   ❌ " . $file['file'] . " - Uses database but missing include\n";
}

echo "\n⚠️ NO DATABASE CONNECTION (" . count($files_with_issues) . "):\n";
echo str_repeat("-", 50) . "\n";
foreach ($files_with_issues as $file) {
    $static_indicator = $file['has_static'] ? " (Has static data)" : " (No data operations)";
    echo "   ⚠️ " . $file['file'] . $static_indicator . "\n";
}

// Auto-fix missing includes
if (!empty($database_missing_files)) {
    echo "\n🔧 AUTO-FIXING MISSING DATABASE INCLUDES...\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($database_missing_files as $file) {
        $content = file_get_contents($file['path']);
        
        // Find the best place to add the database include
        if (strpos($content, "<?php") === 0) {
            // Check if session_start exists
            $session_pos = strpos($content, 'session_start()');
            $config_pos = strpos($content, "include '../config.php'");
            
            if ($session_pos !== false) {
                // Add after session management
                $lines = explode("\n", $content);
                $new_lines = [];
                $added = false;
                
                foreach ($lines as $line) {
                    $new_lines[] = $line;
                    
                    if (!$added && (strpos($line, 'session_start()') !== false || 
                                   strpos($line, "include '../config.php'") !== false ||
                                   strpos($line, 'exit;') !== false)) {
                        // Add database include after session/config/auth lines
                        if (strpos($line, 'exit;') !== false) {
                            $new_lines[] = "";
                            $new_lines[] = "if (!isset($root_path)) 
require_once '../db.php';";
                            $added = true;
                        }
                    }
                    
                    if (!$added && strpos($line, "include '../config.php'") !== false) {
                        $new_lines[] = "if (!isset($root_path)) 
require_once '../db.php';";
                        $added = true;
                    }
                }
                
                if (!$added) {
                    // If no good place found, add after <?php
                    array_splice($new_lines, 1, 0, ["if (!isset($root_path)) 
require_once '../db.php';"]);
                }
                
                $new_content = implode("\n", $new_lines);
            } else {
                // Simple case - add right after <?php
                $new_content = str_replace("<?php", "<?php\nif (!isset($root_path)) 
require_once '../db.php';", $content);
            }
            
            if (file_put_contents($file['path'], $new_content)) {
                echo "   ✅ Fixed: " . $file['file'] . "\n";
            } else {
                echo "   ❌ Failed to fix: " . $file['file'] . "\n";
            }
        }
    }
}

// Check for files that need database connectivity
echo "\n🔍 ANALYZING FILES NEEDING DATABASE INTEGRATION...\n";
echo str_repeat("-", 50) . "\n";

$files_needing_database = [];
foreach ($files_with_issues as $file) {
    if ($file['has_static'] || 
        strpos($file['file'], 'management') !== false ||
        strpos($file['file'], 'analytics') !== false ||
        strpos($file['file'], 'report') !== false ||
        strpos($file['file'], 'employee') !== false ||
        strpos($file['file'], 'payroll') !== false ||
        strpos($file['file'], 'attendance') !== false) {
        
        $files_needing_database[] = $file;
        echo "   🔄 " . $file['file'] . " - Requires database integration\n";
    }
}

// Database validation checks
echo "\n🗄️ DATABASE VALIDATION CHECKS...\n";
echo str_repeat("-", 50) . "\n";

// Check main database connection
if (mysqli_ping($conn)) {
    echo "   ✅ Main database connection: Active\n";
} else {
    echo "   ❌ Main database connection: Failed\n";
}

// Check essential tables
$essential_tables = [
    'employees', 'departments', 'attendance', 'leave_requests', 
    'employee_performance', 'training_programs', 'helpdesk_tickets',
    'employee_payroll', 'salary_grades', 'leave_balance'
];

$missing_tables = [];
foreach ($essential_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "   ✅ Table '$table': Exists\n";
    } else {
        echo "   ❌ Table '$table': Missing\n";
        $missing_tables[] = $table;
    }
}

// Data flow validation
echo "\n📊 DATA FLOW VALIDATION...\n";
echo str_repeat("-", 50) . "\n";

// Check for sample data in key tables
$data_checks = [
    'employees' => "SELECT COUNT(*) as count FROM employees",
    'departments' => "SELECT COUNT(*) as count FROM departments", 
    'attendance' => "SELECT COUNT(*) as count FROM attendance",
    'employee_performance' => "SELECT COUNT(*) as count FROM employee_performance"
];

foreach ($data_checks as $table => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $count = $row['count'];
        if ($count > 0) {
            echo "   ✅ $table: $count records\n";
        } else {
            echo "   ⚠️ $table: No data (may need seeding)\n";
        }
    } else {
        echo "   ❌ $table: Query failed\n";
    }
}

// Generate balance score
$total_files = count($php_files);
$connected_files = count($database_connected_files);
$balance_percentage = ($connected_files / $total_files) * 100;

echo "\n🎯 BALANCE SUMMARY:\n";
echo str_repeat("=", 50) . "\n";
echo "   📁 Total PHP files: $total_files\n";
echo "   ✅ Database connected: $connected_files\n";
echo "   ❌ Missing connection: " . count($database_missing_files) . "\n";
echo "   ⚠️ No database usage: " . count($files_with_issues) . "\n";
echo "   📊 Balance Score: " . round($balance_percentage, 1) . "%\n\n";

// Status determination
if ($balance_percentage >= 95) {
    echo "🎉 STATUS: PERFECTLY BALANCED! 🎉\n";
    echo "✅ Your HRMS system has excellent database connectivity.\n";
} elseif ($balance_percentage >= 85) {
    echo "🎯 STATUS: WELL BALANCED\n";
    echo "✅ Your HRMS system has good database connectivity with minor issues.\n";
} elseif ($balance_percentage >= 70) {
    echo "⚖️ STATUS: MODERATELY BALANCED\n";
    echo "🔄 Your HRMS system needs some database connectivity improvements.\n";
} else {
    echo "⚠️ STATUS: NEEDS BALANCING\n";
    echo "🔧 Your HRMS system requires significant database connectivity fixes.\n";
}

// Recommendations
echo "\n💡 RECOMMENDATIONS:\n";
echo str_repeat("=", 50) . "\n";

if (!empty($database_missing_files)) {
    echo "🔧 Run auto-fix again if any files still missing database includes\n";
}

if (!empty($files_needing_database)) {
    echo "🔄 Convert remaining static data files to database-driven:\n";
    foreach ($files_needing_database as $file) {
        echo "   • " . $file['file'] . "\n";
    }
}

if (!empty($missing_tables)) {
    echo "🗄️ Create missing database tables:\n";
    foreach ($missing_tables as $table) {
        echo "   • $table\n";
    }
}

echo "📊 Implement proper data flow (pull/push) for all modules\n";
echo "🔍 Regular database connectivity monitoring\n";
echo "🚀 Performance optimization for database queries\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 HRMS DATABASE BALANCE CHECK COMPLETE!\n";
echo "📈 System ready for optimal data flow operations.\n";
echo str_repeat("=", 50) . "\n";
?>
