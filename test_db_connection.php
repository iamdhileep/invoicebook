<?php
include 'db.php';

echo "=== Database Connection Test ===\n";

// Test connection
if ($conn->connect_error) {
    echo "❌ Database connection: FAILED - " . $conn->connect_error . "\n";
    exit;
} else {
    echo "✅ Database connection: SUCCESS\n";
}

// Check if categories table exists
$result = $conn->query("SHOW TABLES LIKE 'categories'");
if ($result && $result->num_rows > 0) {
    echo "✅ Categories table: EXISTS\n";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE categories");
    echo "\nCategories table structure:\n";
    while ($row = $structure->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    // Check if there are any categories
    $count = $conn->query("SELECT COUNT(*) as total FROM categories");
    if ($count) {
        $total = $count->fetch_assoc()['total'];
        echo "\nTotal categories: " . $total . "\n";
        
        // Show first few categories
        if ($total > 0) {
            $categories = $conn->query("SELECT * FROM categories LIMIT 5");
            echo "\nSample categories:\n";
            while ($cat = $categories->fetch_assoc()) {
                echo "  - ID: " . $cat['id'] . ", Name: " . $cat['name'] . "\n";
            }
        }
    }
} else {
    echo "❌ Categories table: NOT FOUND\n";
}

// Check if items table exists
$result = $conn->query("SHOW TABLES LIKE 'items'");
if ($result && $result->num_rows > 0) {
    echo "\n✅ Items table: EXISTS\n";
    
    // Check if there are any items
    $count = $conn->query("SELECT COUNT(*) as total FROM items");
    if ($count) {
        $total = $count->fetch_assoc()['total'];
        echo "Total items: " . $total . "\n";
    }
} else {
    echo "\n❌ Items table: NOT FOUND\n";
}

// Test the get_categories.php endpoint
echo "\n=== Testing get_categories.php ===\n";
if (file_exists('get_categories.php')) {
    echo "✅ get_categories.php file exists\n";
    
    // Test by including it
    ob_start();
    try {
        include 'get_categories.php';
        $output = ob_get_clean();
        echo "Response: " . $output . "\n";
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Error in get_categories.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ get_categories.php file not found\n";
}

$conn->close();
echo "\n=== Test Complete ===\n";
?>
