<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

header('Content-Type: application/json');

try {
    $categoryName = $_GET['category_name'] ?? '';
    
    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
    
    // Fetch items for the specified category
    $itemsQuery = $conn->prepare("
        SELECT 
            id,
            item_name,
            item_price,
            stock,
            description,
            image_path,
            status
        FROM items 
        WHERE category = ? 
        ORDER BY item_name ASC
    ");
    
    if ($itemsQuery) {
        $itemsQuery->bind_param("s", $categoryName);
        $itemsQuery->execute();
        $result = $itemsQuery->get_result();
        
        $items = [];
        while ($item = $result->fetch_assoc()) {
            $items[] = $item;
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'category' => $categoryName,
            'count' => count($items)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare query: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>