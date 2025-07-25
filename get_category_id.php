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
    // Find category by name
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE name = ?");
    if ($stmt) {
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $category = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'id' => $category['id'],
                'name' => $category['name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
