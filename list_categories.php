<?php
include 'db.php';

echo "=== Current Categories in Database ===\n";
$result = $conn->query('SELECT name FROM categories ORDER BY name');

if ($result && $result->num_rows > 0) {
    $count = 1;
    while($row = $result->fetch_assoc()) {
        echo $count . ". " . $row['name'] . "\n";
        $count++;
    }
} else {
    echo "No categories found.\n";
}

$conn->close();
?>
