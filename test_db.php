<?php
include 'db.php';

echo "Database connection: " . ($conn->ping() ? "OK" : "Failed") . "\n";

$result = $conn->query("SELECT COUNT(*) as count FROM items");
if($result) {
    $row = $result->fetch_assoc();
    echo "Items table has " . $row['count'] . " records\n";
} else {
    echo "Items table error: " . $conn->error . "\n";
}

// Test the specific query from item-full-list.php
$statsQuery = "
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN stock <= 10 AND stock > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        COUNT(DISTINCT category) as total_categories
    FROM items
";

$statsResult = $conn->query($statsQuery);
if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
    echo "Stats query successful:\n";
    print_r($stats);
} else {
    echo "Stats query failed: " . $conn->error . "\n";
}
?>
