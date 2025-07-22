<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Check if required parameters are provided
$item_id = $_POST['item_id'] ?? null;
$new_stock = $_POST['new_stock'] ?? null;

if (!$item_id || $new_stock === null) {
    echo json_encode(['success' => false, 'message' => 'Item ID and new stock quantity are required']);
    exit;
}

$item_id = intval($item_id);
$new_stock = intval($new_stock);

if ($new_stock < 0) {
    echo json_encode(['success' => false, 'message' => 'Stock quantity cannot be negative']);
    exit;
}

try {
    // First, verify the item exists and get current details
    $checkStmt = $conn->prepare("SELECT item_name, stock FROM items WHERE id = ?");
    $checkStmt->bind_param("i", $item_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    $item_name = $item['item_name'];
    $old_stock = $item['stock'];
    
    // Update the stock
    $updateStmt = $conn->prepare("UPDATE items SET stock = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $new_stock, $item_id);
    
    if ($updateStmt->execute()) {
        // Log the stock change for audit trail
        $logStmt = $conn->prepare("
            INSERT INTO stock_logs (item_id, item_name, old_stock, new_stock, change_amount, updated_by, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if ($logStmt) {
            $change_amount = $new_stock - $old_stock;
            $updated_by = $_SESSION['admin'];
            $logStmt->bind_param("isiiss", $item_id, $item_name, $old_stock, $new_stock, $change_amount, $updated_by);
            $logStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Stock updated successfully for '{$item_name}'",
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'change' => $new_stock - $old_stock
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update stock: ' . $conn->error
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