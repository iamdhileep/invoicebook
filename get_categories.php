<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

include 'db.php';

header('Content-Type: application/json');

try {
    $categories = [];
    
    // Try to get from categories table first (using correct column names)
    $categoryQuery = "SELECT name FROM categories ORDER BY name ASC";
    $result = $conn->query($categoryQuery);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'name' => $row['name'],
                'color' => '#007bff',  // Default color since table doesn't have this column
                'icon' => 'bi-tag'     // Default icon since table doesn't have this column
            ];
        }
    } else {
        // Fallback to distinct categories from items table
        $itemCategoryQuery = "SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
        $result = $conn->query($itemCategoryQuery);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = [
                    'name' => $row['category'],
                    'color' => '#007bff',
                    'icon' => 'bi-tag'
                ];
            }
        }
    }
    
    echo json_encode($categories);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch categories: ' . $e->getMessage()]);
}
?>
