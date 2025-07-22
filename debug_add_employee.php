<?php
session_start();
include 'db.php';

echo "<h2>Debug: Add Employee Database Schema</h2>";

// Test database connection
if ($conn) {
    echo "<p>✅ Database connected successfully</p>";
} else {
    echo "<p>❌ Database connection failed</p>";
    exit;
}

// Check if employees table exists and show structure
echo "<h3>Employees Table Structure:</h3>";
$result = $conn->query("DESCRIBE employees");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Could not describe employees table: " . $conn->error . "</p>";
}

// Test different SELECT queries for checking duplicates
echo "<h3>Testing SELECT Queries:</h3>";

$check_queries = [
    "SELECT employee_id FROM employees WHERE employee_code = ?",
    "SELECT id FROM employees WHERE employee_code = ?",
    "SELECT id FROM employees WHERE code = ?",
    "SELECT * FROM employees LIMIT 1"
];

foreach ($check_queries as $index => $query) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        echo "<p>✅ Query " . ($index + 1) . " prepared successfully: <code>" . htmlspecialchars($query) . "</code></p>";
    } else {
        echo "<p>❌ Query " . ($index + 1) . " failed: <code>" . htmlspecialchars($query) . "</code><br>";
        echo "Error: " . $conn->error . "</p>";
    }
}

// Test different INSERT queries to see which one works
echo "<h3>Testing INSERT Queries:</h3>";

$test_queries = [
    "INSERT INTO employees (employee_name, employee_code, position, monthly_salary, phone, address, email, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    "INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, email, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    "INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, email, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    "INSERT INTO employees (name, employee_code, position, monthly_salary) VALUES (?, ?, ?, ?)",
    "INSERT INTO employees (name, employee_code) VALUES (?, ?)"
];

foreach ($test_queries as $index => $query) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        echo "<p>✅ Query " . ($index + 1) . " prepared successfully: <code>" . htmlspecialchars($query) . "</code></p>";
    } else {
        echo "<p>❌ Query " . ($index + 1) . " failed: <code>" . htmlspecialchars($query) . "</code><br>";
        echo "Error: " . $conn->error . "</p>";
    }
}

// Show some existing employees to understand the data structure
echo "<h3>Sample Employees in Database:</h3>";
$employees = $conn->query("SELECT * FROM employees LIMIT 3");
if ($employees && $employees->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    $first = true;
    while ($employee = $employees->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($employee) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($employee as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No employees found or error: " . $conn->error . "</p>";
}

// Test a simple insert
echo "<h3>Test Simple Insert:</h3>";
if ($_POST['test_insert'] ?? false) {
    $test_name = "Test Employee " . date('Y-m-d H:i:s');
    $test_code = "TEST" . time();
    
    // Try the simplest insert first
    $stmt = $conn->prepare("INSERT INTO employees (name, employee_code) VALUES (?, ?)");
    if (!$stmt) {
        // Try alternative column names
        $stmt = $conn->prepare("INSERT INTO employees (employee_name, employee_code) VALUES (?, ?)");
    }
    
    if ($stmt) {
        $stmt->bind_param("ss", $test_name, $test_code);
        if ($stmt->execute()) {
            echo "<p>✅ Test insert successful! Employee ID: " . $conn->insert_id . "</p>";
        } else {
            echo "<p>❌ Test insert failed: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p>❌ Could not prepare test insert: " . $conn->error . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_insert' value='1'>Test Simple Insert</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='add_employee.php'>Go back to add_employee.php</a></p>";
?>