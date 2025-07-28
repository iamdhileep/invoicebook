<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $id = intval($input['id']);
    $type = $conn->real_escape_string($input['type']);
    $status = $conn->real_escape_string($input['status']);
    $rejection_reason = isset($input['rejection_reason']) ? $conn->real_escape_string($input['rejection_reason']) : '';
    $approved_by = intval($_SESSION['admin']['id'] ?? 1); // Default to admin ID 1 if not set
    
    // Validate required fields
    if (empty($id) || empty($type) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }
    
    // Validate status
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Validate type
    if (!in_array($type, ['leave', 'permission'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }
    
    $table = $type === 'leave' ? 'leaves' : 'permissions';
    
    // Check table structure for leaves table
    if ($type === 'leave') {
        $checkColumns = $conn->query("SHOW COLUMNS FROM leaves");
        $columns = [];
        if ($checkColumns) {
            while ($row = $checkColumns->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        
        $idColumn = in_array('id', $columns) ? 'id' : 'leave_id';
    } else {
        $idColumn = 'id';
    }
    
    // Update the record
    if ($status === 'approved') {
        $updateQuery = "
            UPDATE $table 
            SET status = ?, approved_by = ?, approved_date = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE $idColumn = ?
        ";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sii", $status, $approved_by, $id);
    } else {
        $updateQuery = "
            UPDATE $table 
            SET status = ?, approved_by = ?, approved_date = CURRENT_TIMESTAMP, rejection_reason = ?, updated_at = CURRENT_TIMESTAMP
            WHERE $idColumn = ?
        ";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sisi", $status, $approved_by, $rejection_reason, $id);
    }
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        exit;
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => ucfirst($type) . ' ' . $status . ' successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found or no changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
