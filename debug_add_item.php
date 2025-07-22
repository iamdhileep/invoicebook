<?php
session_start();
include 'db.php';

echo "<h2>Debug: Add Item Database Schema</h2>";

// Test database connection
if ($conn) {
    echo "<p>✅ Database connected successfully</p>";
} else {
    echo "<p>❌ Database connection failed</p>";
    exit;
}

// Check if items table exists and show structure
echo "<h3>Items Table Structure:</h3>";
$result = $conn->query("DESCRIBE items");
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
    echo "<p>❌ Could not describe items table: " . $conn->error . "</p>";
}

// Test different INSERT queries to see which one works
echo "<h3>Testing INSERT Queries:</h3>";

$test_queries = [
    "INSERT INTO items (item_name, item_price, category, stock, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
    "INSERT INTO items (item_name, item_price, category, stock, description) VALUES (?, ?, ?, ?, ?)",
    "INSERT INTO items (item_name, item_price, category, stock) VALUES (?, ?, ?, ?)",
    "INSERT INTO items (item_name, item_price) VALUES (?, ?)"
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

// Show some existing items to understand the data structure
echo "<h3>Sample Items in Database:</h3>";
$items = $conn->query("SELECT * FROM items LIMIT 3");
if ($items && $items->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    $first = true;
    while ($item = $items->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($item) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($item as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No items found or error: " . $conn->error . "</p>";
}

// Test a simple insert
echo "<h3>Test Simple Insert:</h3>";
if ($_POST['test_insert'] ?? false) {
    $test_name = "Test Item " . date('Y-m-d H:i:s');
    $test_price = 99.99;
    
    // Try the simplest insert first
    $stmt = $conn->prepare("INSERT INTO items (item_name, item_price) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("sd", $test_name, $test_price);
        if ($stmt->execute()) {
            echo "<p>✅ Test insert successful! Item ID: " . $conn->insert_id . "</p>";
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
echo "<p><a href='add_item.php'>Go back to add_item.php</a></p>";
?>