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
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
    $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    
    $records = [];
    
    // Get leave records - adapt to existing table structure
    if ($type === '' || $type === 'leave') {
        // Check table structure first
        $checkColumns = $conn->query("SHOW COLUMNS FROM leaves");
        $columns = [];
        if ($checkColumns) {
            while ($row = $checkColumns->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        
        $idColumn = in_array('id', $columns) ? 'l.id' : 'l.leave_id';
        $daysColumn = in_array('days_count', $columns) ? 'l.days_count' : 'l.total_days';
        
        $leaveQuery = "
            SELECT 
                $idColumn as id,
                l.employee_id,
                e.name as employee_name,
                e.employee_code,
                'leave' as type,
                l.leave_type as leave_type,
                NULL as permission_type,
                l.start_date,
                l.end_date,
                NULL as permission_date,
                NULL as from_time,
                NULL as to_time,
                $daysColumn as total_days,
                l.reason,
                l.status,
                l.applied_date,
                l.rejection_reason
            FROM leaves l
            INNER JOIN employees e ON l.employee_id = e.employee_id
            WHERE 1=1
        ";
        
        if ($employee_id > 0) {
            $leaveQuery .= " AND l.employee_id = $employee_id";
        }
        
        if (!empty($status)) {
            $leaveQuery .= " AND l.status = '$status'";
        }
        
        $leaveResult = $conn->query($leaveQuery);
        if ($leaveResult) {
            while ($row = $leaveResult->fetch_assoc()) {
                $records[] = $row;
            }
        }
    }
    
    // Get permission records
    if ($type === '' || $type === 'permission') {
        $permissionQuery = "
            SELECT 
                p.id,
                p.employee_id,
                e.name as employee_name,
                e.employee_code,
                'permission' as type,
                NULL as leave_type,
                p.permission_type,
                NULL as start_date,
                NULL as end_date,
                p.permission_date,
                p.from_time,
                p.to_time,
                NULL as total_days,
                p.reason,
                p.status,
                p.applied_date,
                p.rejection_reason
            FROM permissions p
            INNER JOIN employees e ON p.employee_id = e.employee_id
            WHERE 1=1
        ";
        
        if ($employee_id > 0) {
            $permissionQuery .= " AND p.employee_id = $employee_id";
        }
        
        if (!empty($status)) {
            $permissionQuery .= " AND p.status = '$status'";
        }
        
        $permissionResult = $conn->query($permissionQuery);
        if ($permissionResult) {
            while ($row = $permissionResult->fetch_assoc()) {
                $records[] = $row;
            }
        }
    }
    
    // Sort records by applied_date (newest first)
    usort($records, function($a, $b) {
        return strtotime($b['applied_date']) - strtotime($a['applied_date']);
    });
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'count' => count($records)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
