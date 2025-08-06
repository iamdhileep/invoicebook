<?php
require_once 'db.php';

echo "Creating HRMS database tables...\n";

$sqlFile = 'HRMS/includes/hrms_database_schema.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolons and execute each statement
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                if ($conn->query($statement)) {
                    echo "✓ Executed statement successfully\n";
                } else {
                    echo "✗ Error: " . $conn->error . "\n";
                }
            } catch (Exception $e) {
                echo "✗ Exception: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nVerifying tables...\n";
    $tables = ['hr_departments', 'hr_employees', 'hr_attendance', 'hr_leave_types', 'hr_leave_applications'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        echo "Table $table: " . ($result && $result->num_rows > 0 ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
    }
} else {
    echo "Schema file not found!\n";
}
?>
