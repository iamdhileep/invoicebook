<?php
include 'db.php';

echo "<h2>HR Management Database Setup</h2>";

// Read the SQL file
$sql_file = 'complete_hr_database_setup.sql';
if (!file_exists($sql_file)) {
    die("❌ SQL setup file not found: $sql_file");
}

$sql_content = file_get_contents($sql_file);
if (!$sql_content) {
    die("❌ Could not read SQL file: $sql_file");
}

// Split the SQL content into individual statements
$statements = explode(';', $sql_content);

$success_count = 0;
$error_count = 0;
$errors = [];

echo "<h3>Executing Database Setup...</h3>";

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || substr($statement, 0, 2) === '--') {
        continue; // Skip empty lines and comments
    }
    
    if ($conn->query($statement)) {
        $success_count++;
        // Only show table creation success messages
        if (stripos($statement, 'CREATE TABLE') !== false) {
            preg_match('/CREATE TABLE.*?`([^`]+)`/', $statement, $matches);
            if ($matches) {
                echo "✅ Created table: " . $matches[1] . "<br>";
            }
        } else if (stripos($statement, 'INSERT') !== false) {
            echo "✅ Inserted sample data<br>";
        }
    } else {
        $error_count++;
        $error_msg = $conn->error;
        if (stripos($error_msg, 'already exists') === false && stripos($error_msg, 'Duplicate entry') === false) {
            $errors[] = "❌ Error executing statement: " . substr($statement, 0, 50) . "... - " . $error_msg;
        }
    }
}

echo "<br><h3>Setup Summary:</h3>";
echo "✅ Successful operations: $success_count<br>";
echo "❌ Errors (excluding duplicates): " . count($errors) . "<br>";

if (!empty($errors)) {
    echo "<h4>Errors:</h4>";
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
}

// Verify the setup
echo "<br><h3>Verification:</h3>";

$tables_to_check = ['departments', 'employees', 'attendance', 'leave_requests', 'leave_balance'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ $table: " . $row['count'] . " records<br>";
    } else {
        echo "❌ $table: Error - " . $conn->error . "<br>";
    }
}

echo "<br><h3>✅ Database setup complete!</h3>";
echo "<p><a href='pages/hr/hrms_admin_panel.php'>Go to HRMS Admin Panel</a></p>";
echo "<p><a href='pages/hr/team_manager_console.php'>Go to Team Manager Console</a></p>";
echo "<p><a href='pages/employee/staff_self_service.php'>Go to Staff Self Service Portal</a></p>";

$conn->close();
?>
