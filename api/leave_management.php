<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../auth_check.php';

try {
    // Database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? $_GET;
    $action = $input['action'] ?? $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_leave_applications':
            $employeeId = $input['employee_id'] ?? null;
            $status = $input['status'] ?? null;
            $page = intval($input['page'] ?? 1);
            $limit = intval($input['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            $whereClause = [];
            $params = [];
            $types = '';

            if ($employeeId) {
                $whereClause[] = "la.employee_id = ?";
                $params[] = $employeeId;
                $types .= 'i';
            }

            if ($status) {
                $whereClause[] = "la.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            $whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

            $sql = "
                SELECT la.*, e.first_name, e.last_name, e.employee_id as emp_code,
                       DATEDIFF(la.end_date, la.start_date) + 1 as total_days
                FROM leave_applications la
                JOIN employees e ON la.employee_id = e.id
                {$whereSQL}
                ORDER BY la.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($sql);
            $types .= 'ii';
            $params[] = $limit;
            $params[] = $offset;

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $applications = [];
            while ($row = $result->fetch_assoc()) {
                $applications[] = [
                    'id' => $row['id'],
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'employee_code' => $row['emp_code'],
                    'leave_type' => $row['leave_type'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'total_days' => $row['total_days'],
                    'reason' => $row['reason'],
                    'status' => $row['status'],
                    'applied_date' => $row['created_at'],
                    'manager_remarks' => $row['manager_remarks'] ?? '',
                    'approved_by' => $row['approved_by'],
                    'approved_date' => $row['approved_date']
                ];
            }

            echo json_encode([
                'success' => true,
                'applications' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($applications)
                ]
            ]);
            break;

        case 'apply_leave':
            $employeeId = $input['employee_id'] ?? 0;
            $leaveType = $input['leave_type'] ?? '';
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $reason = $input['reason'] ?? '';

            // Validation
            if (!$employeeId || !$leaveType || !$startDate || !$endDate || !$reason) {
                throw new Exception('All fields are required');
            }

            // Check if employee exists
            $empCheck = $conn->prepare("SELECT id FROM employees WHERE id = ?");
            $empCheck->bind_param("i", $employeeId);
            $empCheck->execute();
            if ($empCheck->get_result()->num_rows === 0) {
                throw new Exception('Employee not found');
            }

            // Insert leave application
            $stmt = $conn->prepare("
                INSERT INTO leave_applications (employee_id, leave_type, start_date, end_date, reason, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("issss", $employeeId, $leaveType, $startDate, $endDate, $reason);

            if ($stmt->execute()) {
                $applicationId = $conn->insert_id;

                // Add to leave history
                $historyStmt = $conn->prepare("
                    INSERT INTO leave_history (application_id, employee_id, action, status, remarks, created_by, created_at)
                    VALUES (?, ?, 'applied', 'pending', 'Leave application submitted', ?, NOW())
                ");
                $historyStmt->bind_param("iii", $applicationId, $employeeId, $employeeId);
                $historyStmt->execute();

                echo json_encode([
                    'success' => true,
                    'message' => 'Leave application submitted successfully',
                    'application_id' => $applicationId
                ]);
            } else {
                throw new Exception('Failed to submit leave application');
            }
            break;

        case 'update_leave_status':
            $applicationId = $input['application_id'] ?? 0;
            $status = $input['status'] ?? '';
            $managerRemarks = $input['manager_remarks'] ?? '';
            $managerId = $input['manager_id'] ?? 0;

            // Validation
            if (!$applicationId || !$status || !in_array($status, ['approved', 'rejected'])) {
                throw new Exception('Invalid parameters');
            }

            // Update leave application
            $stmt = $conn->prepare("
                UPDATE leave_applications 
                SET status = ?, manager_remarks = ?, approved_by = ?, approved_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ssii", $status, $managerRemarks, $managerId, $applicationId);

            if ($stmt->execute()) {
                // Get application details for history
                $appDetails = $conn->query("SELECT employee_id FROM leave_applications WHERE id = {$applicationId}")->fetch_assoc();

                // Add to leave history
                $historyStmt = $conn->prepare("
                    INSERT INTO leave_history (application_id, employee_id, action, status, remarks, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $action = $status === 'approved' ? 'approved' : 'rejected';
                $historyStmt->bind_param("iisssi", $applicationId, $appDetails['employee_id'], $action, $status, $managerRemarks, $managerId);
                $historyStmt->execute();

                echo json_encode([
                    'success' => true,
                    'message' => "Leave application {$status} successfully"
                ]);
            } else {
                throw new Exception('Failed to update leave status');
            }
            break;

        case 'get_leave_balance':
            $employeeId = $input['employee_id'] ?? 0;
            $year = $input['year'] ?? date('Y');

            if (!$employeeId) {
                throw new Exception('Employee ID is required');
            }

            // Calculate leave balance (assuming 30 days annual leave)
            $totalLeave = 30;
            $usedLeaveQuery = $conn->prepare("
                SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as used_days
                FROM leave_applications 
                WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = ?
            ");
            $usedLeaveQuery->bind_param("ii", $employeeId, $year);
            $usedLeaveQuery->execute();
            $usedDays = $usedLeaveQuery->get_result()->fetch_assoc()['used_days'];

            $balance = $totalLeave - $usedDays;

            echo json_encode([
                'success' => true,
                'leave_balance' => [
                    'employee_id' => $employeeId,
                    'year' => $year,
                    'total_leave' => $totalLeave,
                    'used_leave' => $usedDays,
                    'remaining_leave' => max(0, $balance)
                ]
            ]);
            break;

        case 'get_leave_statistics':
            $year = $input['year'] ?? date('Y');

            // Get overall statistics
            $stats = $conn->query("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    SUM(CASE WHEN status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as total_approved_days
                FROM leave_applications 
                WHERE YEAR(created_at) = {$year}
            ")->fetch_assoc();

            // Get leave type breakdown
            $typeStats = [];
            $typeResult = $conn->query("
                SELECT leave_type, COUNT(*) as count, 
                       SUM(CASE WHEN status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as approved_days
                FROM leave_applications 
                WHERE YEAR(created_at) = {$year}
                GROUP BY leave_type
            ");
            while ($row = $typeResult->fetch_assoc()) {
                $typeStats[] = $row;
            }

            echo json_encode([
                'success' => true,
                'statistics' => [
                    'year' => $year,
                    'overall' => $stats,
                    'by_type' => $typeStats
                ]
            ]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>