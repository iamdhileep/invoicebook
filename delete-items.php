<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    $selectedItems = array_map('intval', $_POST['selected_items']);
    $deletedCount = 0;
    $errors = [];
    
    if (!empty($selectedItems)) {
        foreach ($selectedItems as $itemId) {
            try {
                // Get item details for cleanup
                $stmt = $conn->prepare("SELECT item_name, image_path FROM items WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $item = $result->fetch_assoc();
                    $imagePath = $item['image_path'];
                    
                    // Delete the image file if exists
                    if (!empty($imagePath)) {
                        $filePath = 'uploads/' . $imagePath;
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    
                    // Delete the item from database
                    $deleteStmt = $conn->prepare("DELETE FROM items WHERE id = ?");
                    $deleteStmt->bind_param("i", $itemId);
                    
                    if ($deleteStmt->execute()) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete item: " . $item['item_name'];
                    }
                } else {
                    $errors[] = "Item with ID $itemId not found";
                }
            } catch (Exception $e) {
                $errors[] = "Error deleting item ID $itemId: " . $e->getMessage();
            }
        }
        
        // Prepare success/error messages
        $message = "";
        if ($deletedCount > 0) {
            $message .= "Successfully deleted $deletedCount item(s). ";
        }
        if (!empty($errors)) {
            $message .= "Errors: " . implode(", ", $errors);
        }
        
        // Redirect with message
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'item-list.php';
        if ($deletedCount > 0 && empty($errors)) {
            header("Location: $redirectUrl?success=" . urlencode($message));
        } else {
            header("Location: $redirectUrl?error=" . urlencode($message));
        }
    } else {
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-list.php') . "?error=" . urlencode('No items selected for deletion'));
    }
} else {
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'item-list.php') . "?error=" . urlencode('Invalid request'));
}

exit;
?>