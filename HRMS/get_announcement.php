<?php
session_start();

// Include database connection with absolute path handling
$db_path = __DIR__ . '/../db.php';
if (!file_exists($db_path)) {
    $db_path = '../db.php';
}
require_once $db_path;

// Check authentication - flexible approach
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
    exit();
}

$id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("SELECT 
        a.*, 
        CONCAT(e.first_name, ' ', e.last_name) as author_name,
        d.name as department_name
        FROM hr_announcements a 
        LEFT JOIN hr_employees e ON a.author_id = e.id
        LEFT JOIN hr_departments d ON a.department_id = d.id
        WHERE a.id = ?");
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $announcement = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'announcement' => $announcement
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Announcement not found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
