<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Handle both GET and POST requests
$id = $_POST['id'] ?? $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit;
}

$id = intval($id);

try {
    // Get item details first
    $stmt = $conn->prepare("SELECT item_name, image_path FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    $imagePath = $item['image_path'];
    $itemName = $item['item_name'];

    // Delete associated image if exists
    if (!empty($imagePath)) {
        $file = 'uploads/' . $imagePath;
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Delete item from database
    $deleteStmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    
    if ($deleteStmt->execute()) {
        // For AJAX requests (POST), return JSON
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo json_encode([
                'success' => true, 
                'message' => "Item '{$itemName}' deleted successfully"
            ]);
        } else {
            // For direct access (GET), redirect
            header("Location: item-stock.php?deleted=1");
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete item: ' . $conn->error
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
?>
