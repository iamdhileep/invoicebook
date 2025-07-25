<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$name = trim($_POST['name'] ?? '');

if (empty($name)) {
    exit(json_encode(['success' => false, 'message' => 'Category name is required']));
}

try {
    // Check if category already exists
    $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("s", $name);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            exit(json_encode(['success' => false, 'message' => "Category '$name' already exists"]));
        }
    }
    
    // Insert new category
    $insertStmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    if ($insertStmt) {
        $insertStmt->bind_param("s", $name);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Category added successfully',
                'category' => [
                    'id' => $conn->insert_id,
                    'name' => $name
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add category: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
