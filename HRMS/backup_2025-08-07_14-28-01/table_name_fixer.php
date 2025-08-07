<?php
/**
 * HRMS Table Name Fixer
 * Updates table names in HRMS files to use proper hr_ prefix
 */

require_once '../db.php';

echo "<h1>HRMS Table Name Fix</h1>";

// Common table name mappings
$tableNameMappings = [
    'attendance' => 'hr_attendance',
    'employees' => 'hr_employees', 
    'departments' => 'hr_departments',
    'leave_applications' => 'hr_leave_applications',
    'leave_types' => 'hr_leave_types',
    'leave_balances' => 'hr_leave_balances',
    'payroll' => 'hr_payroll',
    'performance_reviews' => 'hr_performance_reviews'
];

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

$skipFiles = [
    'complete_database_setup.php',
    'hrms_mass_fix.php',
    'table_name_fixer.php',
    'employee_directory.php' // This one is already fixed
];

$fixedCount = 0;

echo "<h2>Fixing table names in HRMS files...</h2>";

foreach ($phpFiles as $filePath) {
    $fileName = basename($filePath);
    
    if (in_array($fileName, $skipFiles)) {
        echo "<p>⏭️ Skipped: $fileName</p>";
        continue;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) continue;
    
    $originalContent = $content;
    
    // Fix table names in queries
    foreach ($tableNameMappings as $oldTable => $newTable) {
        // Common SQL patterns
        $patterns = [
            "FROM $oldTable" => "FROM $newTable",
            "INTO $oldTable" => "INTO $newTable", 
            "UPDATE $oldTable" => "UPDATE $newTable",
            "JOIN $oldTable" => "JOIN $newTable",
            "EXISTS $oldTable" => "EXISTS $newTable",
            "LIKE '$oldTable'" => "LIKE '$newTable'",
        ];
        
        foreach ($patterns as $oldPattern => $newPattern) {
            $content = str_replace($oldPattern, $newPattern, $content);
        }
    }
    
    // Fix column names that might need updating
    $columnMappings = [
        'check_in,' => 'clock_in_time,',
        'check_out,' => 'clock_out_time,',
        'work_duration' => 'total_hours',
        'attendance_date,' => 'attendance_date,', // This stays the same
    ];
    
    foreach ($columnMappings as $oldCol => $newCol) {
        $content = str_replace($oldCol, $newCol, $content);
    }
    
    // Write back if changes were made
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content) !== false) {
            echo "<p style='color: green;'>✅ Fixed table names in: $fileName</p>";
            $fixedCount++;
        } else {
            echo "<p style='color: red;'>❌ Could not write: $fileName</p>";
        }
    } else {
        echo "<p>⚪ No table name changes needed: $fileName</p>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Files Updated:</strong> $fixedCount</p>";

// Test database connection to new table names
echo "<h2>Testing Table Connections</h2>";
foreach ($tableNameMappings as $oldTable => $newTable) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $newTable");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p style='color: green;'>✅ $newTable: $count records</p>";
    } else {
        echo "<p style='color: red;'>❌ $newTable: " . $conn->error . "</p>";
    }
}

echo "<p><a href='attendance_management.php'>Test Attendance</a> | <a href='leave_management.php'>Test Leave Management</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
a { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px; display: inline-block; }
a:hover { background: #0056b3; }
</style>

<?php require_once '../layouts/footer.php'; ?>