<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $id = intval($_GET['id']);
    $type = $conn->real_escape_string($_GET['type']);
    
    // Validate required fields
    if (empty($id) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Required parameters missing']);
        exit;
    }
    
    // Validate type
    if (!in_array($type, ['leave', 'permission'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }
    
    $record = null;
    
    if ($type === 'leave') {
        // Check table structure first
        $checkColumns = $conn->query("SHOW COLUMNS FROM leaves");
        $columns = [];
        if ($checkColumns) {
            while ($row = $checkColumns->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        
        $idColumn = in_array('id', $columns) ? 'l.id' : 'l.leave_id';
        
        $query = "
            SELECT 
                l.*,
                e.name as employee_name,
                e.employee_code,
                'leave' as type
            FROM leaves l
            INNER JOIN employees e ON l.employee_id = e.employee_id
            WHERE $idColumn = ?
        ";
    } else {
        $query = "
            SELECT 
                p.*,
                e.name as employee_name,
                e.employee_code,
                'permission' as type
            FROM permissions p
            INNER JOIN employees e ON p.employee_id = e.employee_id
            WHERE p.id = ?
        ";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $record = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'record' => $record
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
