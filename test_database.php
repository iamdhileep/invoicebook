<?php
include 'db.php';

echo "Testing database connection...\n";

// Test basic connection
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
    exit(1);
}

echo "Database connected successfully\n";

// Check if required tables exist
$tables = ['employees', 'attendance', 'leave_applications', 'permission_requests', 'biometric_devices'];
$missingTables = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "Table $table: EXISTS\n";
    } else {
        echo "Table $table: MISSING\n";
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "\nMissing tables detected. Creating tables...\n";
    
    // Read and execute SQL schema
    if (file_exists('database_schema_fix.sql')) {
        $sql = file_get_contents('database_schema_fix.sql');
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                if ($conn->query($statement)) {
                    echo "Executed: " . substr($statement, 0, 50) . "...\n";
                } else {
                    echo "Error executing: " . $conn->error . "\n";
                }
            }
        }
        
        echo "Database schema setup completed!\n";
    } else {
        echo "database_schema_fix.sql file not found!\n";
    }
}

echo "Database check completed\n";
?>
