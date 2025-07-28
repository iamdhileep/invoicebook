<?php
include 'db.php';

echo "Testing database connection...\n";

// Check if users table exists
$users_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($users_check && $users_check->num_rows > 0) {
    echo "Users table exists.\n";
    
    // Get users
    $result = $conn->query("SELECT username, role FROM users LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "Available users:\n";
        while($row = $result->fetch_assoc()) {
            echo "- " . $row['username'] . " (Role: " . $row['role'] . ")\n";
        }
    } else {
        echo "No users found.\n";
    }
} else {
    echo "Users table does not exist.\n";
}

// Check if employees table exists
$emp_check = $conn->query("SHOW TABLES LIKE 'employees'");
if ($emp_check && $emp_check->num_rows > 0) {
    echo "\nEmployees table exists.\n";
    
    $result = $conn->query("SELECT name FROM employees LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "Sample employees:\n";
        while($row = $result->fetch_assoc()) {
            echo "- " . $row['name'] . "\n";
        }
    }
} else {
    echo "Employees table does not exist.\n";
}

$conn->close();
?>
