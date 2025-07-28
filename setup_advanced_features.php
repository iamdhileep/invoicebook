<?php
// Database setup for advanced features
include 'db.php';

echo "Starting advanced features database setup...\n";

// Read and execute SQL file
$sql_content = file_get_contents('database_advanced_features.sql');

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    try {
        if ($conn->query($statement)) {
            $success_count++;
            echo "✓ Executed statement successfully\n";
        } else {
            $error_count++;
            echo "✗ Error: " . $conn->error . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\nDatabase setup completed!\n";
echo "Successfully executed: $success_count statements\n";
echo "Errors encountered: $error_count statements\n";

// Verify tables were created
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "\nTables in database: " . count($tables) . "\n";
foreach ($tables as $table) {
    if (strpos($table, 'smart_') !== false || 
        strpos($table, 'leave_') !== false || 
        strpos($table, 'mobile_') !== false ||
        strpos($table, 'notification_') !== false ||
        strpos($table, 'biometric_') !== false) {
        echo "✓ $table\n";
    }
}

echo "\nAdvanced features database setup complete!\n";
?>
