<?php
include 'db.php';

echo "=== Category Debug Information ===\n\n";

// Test categories table
echo "1. Testing categories table:\n";
$categoryQuery = "SELECT name as category FROM categories ORDER BY name ASC";
$categories = $conn->query($categoryQuery);

if ($categories) {
    echo "Categories found: " . $categories->num_rows . "\n";
    if ($categories->num_rows > 0) {
        while ($cat = $categories->fetch_assoc()) {
            echo "- Name: " . ($cat['category'] ?? 'NULL') . "\n";
        }
    }
} else {
    echo "Error querying categories: " . $conn->error . "\n";
}

// Test fallback from items
echo "\n2. Testing items table fallback:\n";
$itemCategoryQuery = "SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
$itemCategories = $conn->query($itemCategoryQuery);

if ($itemCategories) {
    echo "Item categories found: " . $itemCategories->num_rows . "\n";
    if ($itemCategories->num_rows > 0) {
        while ($item = $itemCategories->fetch_assoc()) {
            echo "- Category: " . ($item['category'] ?? 'NULL') . "\n";
        }
    }
} else {
    echo "Error querying item categories: " . $conn->error . "\n";
}

// Test get_categories.php AJAX endpoint
echo "\n3. Testing get_categories.php AJAX endpoint:\n";
session_start();
$_SESSION['admin'] = true;
ob_start();
include 'get_categories.php';
$output = ob_get_clean();  
echo "AJAX Response: " . $output . "\n";

echo "\n=== Debug Complete ===\n";
?>
