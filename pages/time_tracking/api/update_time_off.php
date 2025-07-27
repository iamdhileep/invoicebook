<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)$input['id'];
$status = $input['status'];
$admin_id = $_SESSION['admin']['id'] ?? 1; // Assuming admin ID is stored in session

if (!in_array($status, ['Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $query = $conn->prepare("
        UPDATE time_off_requests 
        SET status = ?, approved_by = ?, approved_date = NOW() 
        WHERE id = ?
    ");
    
    $query->bind_param("sii", $status, $admin_id, $id);
    
    if ($query->execute()) {
        if ($conn->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => "Request $status successfully"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
