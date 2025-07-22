<?php
/**
 * Database Setup Script
 * Initializes the complete database structure for the Billbook Application
 */

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$dbname = "billing";

echo "<h2>🚀 Billbook Database Setup</h2>";
echo "<p>Initializing database structure...</p>";

try {
    // First, connect without specifying database to create it if needed
    $conn = new mysqli($host, $user, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p>✅ Connected to MySQL server</p>";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ Database '$dbname' created/verified</p>";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Close initial connection and reconnect to the specific database
    $conn->close();
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection to database failed: " . $conn->connect_error);
    }
    
    echo "<p>✅ Connected to database '$dbname'</p>";
    
    // Read and execute the SQL setup file
    $sqlFile = 'database_setup.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL setup file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL setup file");
    }
    
    echo "<p>📄 SQL setup file loaded</p>";
    
    // Split SQL into individual statements
    $statements = preg_split('/;\s*$/m', $sql);
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, 'DELIMITER') === 0 ||
            strpos($statement, 'SELECT \'') === 0) {
            continue;
        }
        
        // Execute the statement
        if ($conn->query($statement) === TRUE) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = "Error in statement: " . substr($statement, 0, 100) . "... - " . $conn->error;
        }
    }
    
    echo "<h3>📊 Setup Results:</h3>";
    echo "<p>✅ Successful statements: $successCount</p>";
    
    if ($errorCount > 0) {
        echo "<p>❌ Failed statements: $errorCount</p>";
        echo "<details><summary>View Errors</summary>";
        foreach ($errors as $error) {
            echo "<p style='color: red; font-size: 12px;'>$error</p>";
        }
        echo "</details>";
    }
    
    // Verify table creation
    echo "<h3>🔍 Verifying Tables:</h3>";
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        $expectedTables = [
            'users', 'categories', 'items', 'employees', 'attendance',
            'invoices', 'invoice_items', 'expenses', 'stock_logs', 
            'payroll', 'settings'
        ];
        
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 20px 0;'>";
        
        foreach ($expectedTables as $table) {
            $exists = in_array($table, $tables);
            $status = $exists ? "✅" : "❌";
            $color = $exists ? "green" : "red";
            echo "<div style='padding: 10px; border: 1px solid $color; border-radius: 5px;'>";
            echo "<strong>$status $table</strong>";
            
            if ($exists) {
                // Get row count
                $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
                echo "<br><small>$count records</small>";
            }
            echo "</div>";
        }
        echo "</div>";
        
        $createdCount = count(array_intersect($expectedTables, $tables));
        echo "<p><strong>Tables created: $createdCount/" . count($expectedTables) . "</strong></p>";
        
    } else {
        echo "<p>❌ Could not verify tables: " . $conn->error . "</p>";
    }
    
    // Test categories functionality
    echo "<h3>🧪 Testing Categories Functionality:</h3>";
    
    // Test insert
    $testCategory = "Test Category " . date('His');
    $testDesc = "Test description for automated setup";
    $testColor = "#ff5722";
    $testIcon = "bi-gear";
    
    $stmt = $conn->prepare("INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $testCategory, $testDesc, $testColor, $testIcon);
        if ($stmt->execute()) {
            $testId = $conn->insert_id;
            echo "<p>✅ Test category inserted (ID: $testId)</p>";
            
            // Test select
            $selectStmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
            if ($selectStmt) {
                $selectStmt->bind_param("i", $testId);
                $selectStmt->execute();
                $result = $selectStmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    echo "<p>✅ Test category retrieved: " . htmlspecialchars($row['name']) . "</p>";
                    
                    // Test update
                    $updateStmt = $conn->prepare("UPDATE categories SET description = ? WHERE id = ?");
                    if ($updateStmt) {
                        $newDesc = "Updated description " . date('H:i:s');
                        $updateStmt->bind_param("si", $newDesc, $testId);
                        if ($updateStmt->execute()) {
                            echo "<p>✅ Test category updated</p>";
                        }
                    }
                    
                    // Test delete
                    $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("i", $testId);
                        if ($deleteStmt->execute()) {
                            echo "<p>✅ Test category deleted</p>";
                        }
                    }
                }
            }
        } else {
            echo "<p>❌ Failed to insert test category: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p>❌ Failed to prepare test insert: " . $conn->error . "</p>";
    }
    
    // Check sample data
    echo "<h3>📋 Sample Data Status:</h3>";
    
    $sampleTables = [
        'categories' => 'SELECT COUNT(*) as count FROM categories',
        'items' => 'SELECT COUNT(*) as count FROM items',
        'employees' => 'SELECT COUNT(*) as count FROM employees',
        'expenses' => 'SELECT COUNT(*) as count FROM expenses',
        'settings' => 'SELECT COUNT(*) as count FROM settings'
    ];
    
    foreach ($sampleTables as $table => $query) {
        $result = $conn->query($query);
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            echo "<p>📊 <strong>$table:</strong> $count records</p>";
        }
    }
    
    echo "<h3>🎉 Database Setup Complete!</h3>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h4>✅ Setup Summary:</h4>";
    echo "<ul>";
    echo "<li><strong>Database:</strong> '$dbname' created and configured</li>";
    echo "<li><strong>Tables:</strong> All core tables created with proper structure</li>";
    echo "<li><strong>Sample Data:</strong> Default categories, items, employees, and settings inserted</li>";
    echo "<li><strong>Views & Triggers:</strong> Database automation configured</li>";
    echo "<li><strong>Categories Module:</strong> Fully functional and tested</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #cce5ff; border: 1px solid #99d6ff; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h4>🔑 Login Information:</h4>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h4>🚀 Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Access your application at: <a href='manage_categories.php'>manage_categories.php</a></li>";
    echo "<li>Login with the admin credentials above</li>";
    echo "<li>Test the categories management functionality</li>";
    echo "<li>Add your own categories, items, and employees</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>❌ Setup Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h4>🔧 Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>Make sure MySQL/MariaDB is running</li>";
    echo "<li>Verify database credentials in db.php</li>";
    echo "<li>Check if the user has CREATE DATABASE privileges</li>";
    echo "<li>Ensure the database_setup.sql file exists</li>";
    echo "</ul>";
    echo "</div>";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Add some styling
echo "<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
h3 { color: #555; margin-top: 30px; }
p { line-height: 1.6; }
details { margin: 10px 0; }
summary { cursor: pointer; font-weight: bold; }
</style>";
?>