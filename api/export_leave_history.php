<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="leave_history_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
    $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    
    // Start Excel output
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>Employee Name</th>";
    echo "<th>Employee Code</th>";
    echo "<th>Type</th>";
    echo "<th>Leave/Permission Type</th>";
    echo "<th>Start Date</th>";
    echo "<th>End Date</th>";
    echo "<th>Permission Date</th>";
    echo "<th>From Time</th>";
    echo "<th>To Time</th>";
    echo "<th>Total Days</th>";
    echo "<th>Reason</th>";
    echo "<th>Status</th>";
    echo "<th>Applied Date</th>";
    echo "<th>Rejection Reason</th>";
    echo "</tr>";
    
    $records = [];
    
    // Get leave records
    if ($type === '' || $type === 'leave') {
        $leaveQuery = "
            SELECT 
                l.id,
                l.employee_id,
                e.name as employee_name,
                e.employee_code,
                'Leave' as type,
                l.leave_type,
                l.start_date,
                l.end_date,
                NULL as permission_date,
                NULL as from_time,
                NULL as to_time,
                l.total_days,
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
                'Permission' as type,
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
    
    // Output records
    foreach ($records as $record) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($record['employee_name']) . "</td>";
        echo "<td>" . htmlspecialchars($record['employee_code']) . "</td>";
        echo "<td>" . htmlspecialchars($record['type']) . "</td>";
        echo "<td>" . htmlspecialchars($record['type'] === 'Leave' ? ucfirst(str_replace('_', ' ', $record['leave_type'])) : ucfirst(str_replace('_', ' ', $record['permission_type']))) . "</td>";
        echo "<td>" . ($record['start_date'] ? $record['start_date'] : '-') . "</td>";
        echo "<td>" . ($record['end_date'] ? $record['end_date'] : '-') . "</td>";
        echo "<td>" . ($record['permission_date'] ? $record['permission_date'] : '-') . "</td>";
        echo "<td>" . ($record['from_time'] ? $record['from_time'] : '-') . "</td>";
        echo "<td>" . ($record['to_time'] ? $record['to_time'] : '-') . "</td>";
        echo "<td>" . ($record['total_days'] ? $record['total_days'] : '-') . "</td>";
        echo "<td>" . htmlspecialchars($record['reason']) . "</td>";
        echo "<td>" . ucfirst($record['status']) . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', strtotime($record['applied_date'])) . "</td>";
        echo "<td>" . ($record['rejection_reason'] ? htmlspecialchars($record['rejection_reason']) : '-') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$conn->close();
?>
