<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get ID from POST (AJAX) or GET (direct link)
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Get item details first
        $stmt = $conn->prepare("SELECT item_name, image_path FROM items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            $itemName = $item['item_name'];
            $imagePath = $item['image_path'];
            
            // Delete the image file if exists
            if (!empty($imagePath)) {
                $filePath = 'uploads/' . $imagePath;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete the item from the database
            $deleteStmt = $conn->prepare("DELETE FROM items WHERE id = ?");
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => "Item '{$itemName}' deleted successfully"
                    ]);
                } else {
                    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-stock.php') . "?deleted=1");
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to delete item: ' . $conn->error
                    ]);
                } else {
                    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-stock.php') . "?error=delete_failed");
                }
            }
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Item not found']);
            } else {
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-stock.php') . "?error=item_not_found");
            }
        }
    } catch (Exception $e) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } else {
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-stock.php') . "?error=exception");
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid item ID is required']);
    } else {
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-stock.php') . "?error=invalid_id");
    }
}

exit;
?>
