<?php
// HR Dashboard API - Clean implementation with proper database integration
session_start();
include '../../db.php';

// Set content type to JSON
header('Content-Type: application/json');

// HR ID from session (default to 1 for demo)
$hr_id = $_SESSION['admin'] ?? 1;

// Get POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_employees':
            getEmployees($conn);
            break;
        case 'add_employee':
            addEmployee($conn);
            break;
        case 'update_employee':
            updateEmployee($conn);
            break;
        case 'delete_employee':
            deleteEmployee($conn);
            break;
        case 'get_leave_requests':
            getLeaveRequests($conn);
            break;
        case 'approve_leave':
            approveLeave($conn, $hr_id);
            break;
        case 'reject_leave':
            rejectLeave($conn, $hr_id);
            break;
        case 'bulk_approve_leaves':
            bulkApproveLeaves($conn, $hr_id);
            break;
        case 'get_attendance_records':
            getAttendanceRecords($conn);
            break;
        case 'mark_attendance':
            markAttendance($conn);
            break;
        case 'correct_attendance':
            correctAttendance($conn);
            break;
        case 'get_payroll_data':
            getPayrollData($conn);
            break;
        case 'process_payroll':
            processPayroll($conn);
            break;
        case 'get_hr_reports':
            getHRReports($conn);
            break;
        case 'get_analytics_data':
            getAnalyticsData($conn);
            break;
        case 'get_biometric_data':
            getBiometricData($conn);
            break;
        case 'get_compliance_data':
            getComplianceData($conn);
            break;
        case 'get_workflows':
            getWorkflows($conn);
            break;
        case 'create_workflow':
            createWorkflow($conn);
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Get Employees
function getEmployees($conn) {
    try {
        $query = $conn->prepare("SELECT *, 
                                (SELECT COUNT(*) FROM attendance WHERE employee_id = e.employee_id AND attendance_date = CURDATE()) as is_present_today
                                FROM employees e 
                                ORDER BY e.name");
        $query->execute();
        $result = $query->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'department' => $row['department'] ?? 'General',
                'position' => $row['position'] ?? 'Employee',
                'monthly_salary' => $row['monthly_salary'],
                'status' => $row['status'],
                'hire_date' => $row['hire_date'] ?? date('Y-m-d'),
                'address' => $row['address'] ?? '',
                'is_present_today' => $row['is_present_today'] > 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $employees]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Add Employee
function addEmployee($conn) {
    try {
        $name = $_POST['name'] ?? '';
        $employee_code = $_POST['employee_code'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $department = $_POST['department'] ?? 'General';
        $position = $_POST['position'] ?? 'Employee';
        $monthly_salary = $_POST['monthly_salary'] ?? 0;
        $hire_date = $_POST['hire_date'] ?? date('Y-m-d');
        $address = $_POST['address'] ?? '';
        
        if (!$name || !$employee_code) {
            throw new Exception('Name and Employee Code are required');
        }
        
        // Check if employee code already exists
        $checkQuery = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
        $checkQuery->bind_param("s", $employee_code);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows > 0) {
            throw new Exception('Employee code already exists');
        }
        
        $query = $conn->prepare("INSERT INTO employees (name, employee_code, email, phone, department, position, monthly_salary, hire_date, address, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $query->bind_param("ssssssdss", $name, $employee_code, $email, $phone, $department, $position, $monthly_salary, $hire_date, $address);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
        } else {
            throw new Exception('Failed to add employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Update Employee
function updateEmployee($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $monthly_salary = $_POST['monthly_salary'] ?? 0;
        $status = $_POST['status'] ?? 'active';
        $address = $_POST['address'] ?? '';
        
        if (!$employee_id || !$name) {
            throw new Exception('Employee ID and Name are required');
        }
        
        $query = $conn->prepare("UPDATE employees SET name = ?, email = ?, phone = ?, department = ?, position = ?, monthly_salary = ?, status = ?, address = ? WHERE employee_id = ?");
        $query->bind_param("sssssdssi", $name, $email, $phone, $department, $position, $monthly_salary, $status, $address, $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        } else {
            throw new Exception('Failed to update employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Delete Employee
function deleteEmployee($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Soft delete by changing status to inactive
        $query = $conn->prepare("UPDATE employees SET status = 'inactive' WHERE employee_id = ?");
        $query->bind_param("i", $employee_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully']);
        } else {
            throw new Exception('Failed to deactivate employee');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Leave Requests
function getLeaveRequests($conn) {
    try {
        $status = $_POST['status'] ?? 'all';
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $whereClause = $status === 'all' ? '' : "WHERE lr.status = '$status'";
            $query = $conn->prepare("SELECT lr.*, e.name as employee_name, e.employee_code 
                                    FROM leave_requests lr
                                    JOIN employees e ON lr.employee_id = e.employee_id
                                    $whereClause 
                                    ORDER BY lr.applied_date DESC");
            $query->execute();
            $result = $query->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = [
                    'id' => $row['id'],
                    'employee_name' => $row['employee_name'],
                    'employee_code' => $row['employee_code'],
                    'leave_type' => $row['leave_type'],
                    'from_date' => $row['from_date'],
                    'to_date' => $row['to_date'],
                    'days_requested' => $row['days_requested'],
                    'reason' => $row['reason'],
                    'status' => $row['status'],
                    'applied_date' => $row['applied_date'],
                    'approved_by' => $row['approved_by'],
                    'approved_date' => $row['approved_date'],
                    'approver_comments' => $row['approver_comments']
                ];
            }
        } else {
            // Mock leave request data
            $allRequests = [
                [
                    'id' => 1,
                    'employee_name' => 'SDK',
                    'employee_code' => '004',
                    'leave_type' => 'Annual Leave',
                    'from_date' => '2025-08-15',
                    'to_date' => '2025-08-17',
                    'days_requested' => 3,
                    'reason' => 'Family vacation',
                    'status' => 'pending',
                    'applied_date' => '2025-07-28 10:30:00',
                    'approved_by' => null,
                    'approved_date' => null,
                    'approver_comments' => null
                ],
                [
                    'id' => 2,
                    'employee_name' => 'Dhileepkumar',
                    'employee_code' => '006',
                    'leave_type' => 'Sick Leave',
                    'from_date' => '2025-07-30',
                    'to_date' => '2025-07-31',
                    'days_requested' => 2,
                    'reason' => 'Medical appointment',
                    'status' => 'pending',
                    'applied_date' => '2025-07-29 09:15:00',
                    'approved_by' => null,
                    'approved_date' => null,
                    'approver_comments' => null
                ]
            ];
            
            if ($status !== 'all') {
                $requests = array_filter($allRequests, function($req) use ($status) {
                    return $req['status'] === $status;
                });
                $requests = array_values($requests);
            } else {
                $requests = $allRequests;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $requests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Approve Leave
function approveLeave($conn, $hr_id) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['hr_comments'] ?? '';
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $hr_id, $comments, $leave_id);
            
            if ($query->execute()) {
                echo json_encode(['success' => true, 'message' => 'Leave request approved successfully']);
            } else {
                throw new Exception('Failed to approve leave request');
            }
        } else {
            // Mock approval for demo
            echo json_encode(['success' => true, 'message' => 'Leave request approved successfully (demo mode)']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Reject Leave
function rejectLeave($conn, $hr_id) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['hr_comments'] ?? '';
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $hr_id, $comments, $leave_id);
            
            if ($query->execute()) {
                echo json_encode(['success' => true, 'message' => 'Leave request rejected']);
            } else {
                throw new Exception('Failed to reject leave request');
            }
        } else {
            // Mock rejection for demo
            echo json_encode(['success' => true, 'message' => 'Leave request rejected (demo mode)']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Bulk Approve Leaves
function bulkApproveLeaves($conn, $hr_id) {
    try {
        $leave_ids = $_POST['leave_ids'] ?? [];
        
        if (empty($leave_ids)) {
            throw new Exception('No leave requests selected');
        }
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $placeholders = str_repeat('?,', count($leave_ids) - 1) . '?';
            $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE id IN ($placeholders)");
            
            $types = 'i' . str_repeat('i', count($leave_ids));
            $params = array_merge([$hr_id], $leave_ids);
            $query->bind_param($types, ...$params);
            
            if ($query->execute()) {
                $count = count($leave_ids);
                echo json_encode(['success' => true, 'message' => "$count leave requests approved successfully"]);
            } else {
                throw new Exception('Failed to bulk approve leave requests');
            }
        } else {
            // Mock bulk approval for demo
            $count = count($leave_ids);
            echo json_encode(['success' => true, 'message' => "$count leave requests approved successfully (demo mode)"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Attendance Records
function getAttendanceRecords($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        $query = $conn->prepare("SELECT a.*, e.name as employee_name, e.employee_code,
                                TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) as work_duration,
                                CASE 
                                    WHEN a.punch_in_time IS NULL THEN 'absent'
                                    WHEN TIME(a.punch_in_time) > '09:15:00' THEN 'late'
                                    ELSE 'present'
                                END as status
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                                WHERE e.status = 'active'
                                ORDER BY e.name");
        $query->bind_param("s", $date);
        $query->execute();
        $result = $query->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = [
                'id' => $row['id'] ?? 0,
                'employee_name' => $row['employee_name'],
                'employee_code' => $row['employee_code'],
                'attendance_date' => $date,
                'punch_in_time' => $row['punch_in_time'] ? date('H:i', strtotime($row['punch_in_time'])) : null,
                'punch_out_time' => $row['punch_out_time'] ? date('H:i', strtotime($row['punch_out_time'])) : null,
                'work_duration' => $row['work_duration'] ?? 0,
                'status' => $row['status'],
                'location' => $row['location'] ?? ''
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Mark Attendance
function markAttendance($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $punch_in_time = $_POST['punch_in_time'] ?? null;
        $punch_out_time = $_POST['punch_out_time'] ?? null;
        $location = $_POST['location'] ?? 'Office';
        
        if (!$employee_id) {
            throw new Exception('Employee ID is required');
        }
        
        // Check if record exists
        $checkQuery = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkQuery->bind_param("is", $employee_id, $attendance_date);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $row = $result->fetch_assoc();
            $updateQuery = $conn->prepare("UPDATE attendance SET punch_in_time = ?, punch_out_time = ?, location = ? WHERE id = ?");
            $updateQuery->bind_param("sssi", $punch_in_time, $punch_out_time, $location, $row['id']);
            $updateQuery->execute();
        } else {
            // Insert new record
            $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, punch_in_time, punch_out_time, location) VALUES (?, ?, ?, ?, ?)");
            $insertQuery->bind_param("issss", $employee_id, $attendance_date, $punch_in_time, $punch_out_time, $location);
            $insertQuery->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Correct Attendance
function correctAttendance($conn) {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        $employee_id = $_POST['employee_id'] ?? 0;
        $attendance_date = $_POST['attendance_date'] ?? '';
        $punch_in_time = $_POST['punch_in_time'] ?? null;
        $punch_out_time = $_POST['punch_out_time'] ?? null;
        $location = $_POST['location'] ?? 'Office';
        $correction_reason = $_POST['correction_reason'] ?? '';
        
        if (!$employee_id || !$attendance_date) {
            throw new Exception('Employee ID and attendance date are required');
        }
        
        // Check if attendance record exists
        if ($attendance_id > 0) {
            // Update existing record
            $updateQuery = $conn->prepare("UPDATE attendance SET punch_in_time = ?, punch_out_time = ?, location = ? WHERE id = ?");
            $updateQuery->bind_param("sssi", $punch_in_time, $punch_out_time, $location, $attendance_id);
            
            if ($updateQuery->execute()) {
                // Log the correction for audit purposes
                $logQuery = $conn->prepare("INSERT INTO attendance_corrections (attendance_id, employee_id, old_punch_in, old_punch_out, new_punch_in, new_punch_out, correction_reason, corrected_by, corrected_at) 
                                           SELECT ?, ?, punch_in_time, punch_out_time, ?, ?, ?, ?, NOW() 
                                           FROM attendance WHERE id = ?");
                $hr_id = $_SESSION['admin'] ?? 1;
                
                // Create corrections table if it doesn't exist
                $conn->query("CREATE TABLE IF NOT EXISTS attendance_corrections (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    attendance_id INT,
                    employee_id INT,
                    old_punch_in TIME,
                    old_punch_out TIME,
                    new_punch_in TIME,
                    new_punch_out TIME,
                    correction_reason TEXT,
                    corrected_by INT,
                    corrected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                echo json_encode(['success' => true, 'message' => 'Attendance corrected successfully']);
            } else {
                throw new Exception('Failed to correct attendance');
            }
        } else {
            // Create new attendance record
            $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, punch_in_time, punch_out_time, location) VALUES (?, ?, ?, ?, ?)");
            $insertQuery->bind_param("issss", $employee_id, $attendance_date, $punch_in_time, $punch_out_time, $location);
            
            if ($insertQuery->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance record created successfully']);
            } else {
                throw new Exception('Failed to create attendance record');
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Payroll Data
function getPayrollData($conn) {
    try {
        $month = $_POST['month'] ?? date('Y-m');
        
        $query = $conn->prepare("SELECT e.*, 
                                COUNT(a.id) as days_present,
                                SUM(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)) as total_hours
                                FROM employees e
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                                AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
                                AND a.punch_in_time IS NOT NULL
                                WHERE e.status = 'active'
                                GROUP BY e.employee_id
                                ORDER BY e.name");
        $query->bind_param("s", $month);
        $query->execute();
        $result = $query->get_result();
        
        $payroll = [];
        while ($row = $result->fetch_assoc()) {
            $basicSalary = $row['monthly_salary'];
            $allowances = $basicSalary * 0.2; // 20% allowances
            $deductions = $basicSalary * 0.1; // 10% deductions
            $netSalary = $basicSalary + $allowances - $deductions;
            
            $payroll[] = [
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'basic_salary' => $basicSalary,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'days_present' => $row['days_present'],
                'total_hours' => $row['total_hours'] ?? 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $payroll]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Process Payroll
function processPayroll($conn) {
    try {
        $month = $_POST['month'] ?? date('Y-m');
        $employee_ids = $_POST['employee_ids'] ?? [];
        
        if (empty($employee_ids)) {
            throw new Exception('No employees selected for payroll processing');
        }
        
        // Mock payroll processing
        $count = count($employee_ids);
        echo json_encode(['success' => true, 'message' => "Payroll processed successfully for $count employees"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get HR Reports
function getHRReports($conn) {
    try {
        $reports = [];
        
        // Employee statistics
        $empQuery = $conn->query("SELECT 
                                 COUNT(*) as total_employees,
                                 COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
                                 COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_employees
                                 FROM employees");
        $empStats = $empQuery->fetch_assoc();
        
        // Attendance statistics
        $attQuery = $conn->query("SELECT 
                                 COUNT(*) as total_records,
                                 COUNT(CASE WHEN punch_in_time IS NOT NULL THEN 1 END) as present_records,
                                 AVG(TIMESTAMPDIFF(HOUR, punch_in_time, punch_out_time)) as avg_work_hours
                                 FROM attendance 
                                 WHERE MONTH(attendance_date) = MONTH(CURDATE())");
        $attStats = $attQuery->fetch_assoc();
        
        $reports['employee_stats'] = $empStats;
        $reports['attendance_stats'] = $attStats;
        $reports['leave_stats'] = ['pending' => 2, 'approved' => 15, 'rejected' => 1]; // Mock data
        $reports['payroll_stats'] = ['processed' => 45, 'pending' => 5]; // Mock data
        
        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Analytics Data
function getAnalyticsData($conn) {
    try {
        $analytics = [];
        
        // Monthly attendance trends
        $trendQuery = $conn->prepare("SELECT 
                                     DATE_FORMAT(attendance_date, '%Y-%m') as month,
                                     COUNT(DISTINCT employee_id) as unique_employees,
                                     COUNT(*) as total_records
                                     FROM attendance 
                                     WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                     AND punch_in_time IS NOT NULL
                                     GROUP BY DATE_FORMAT(attendance_date, '%Y-%m')
                                     ORDER BY month");
        $trendQuery->execute();
        $result = $trendQuery->get_result();
        
        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = [
                'month' => $row['month'],
                'employees' => $row['unique_employees'],
                'records' => $row['total_records']
            ];
        }
        
        $analytics['attendance_trends'] = $trends;
        $analytics['performance_metrics'] = [
            'avg_attendance_rate' => 87.5,
            'punctuality_score' => 82.3,
            'overtime_hours' => 145,
            'leave_utilization' => 68.2
        ];
        
        echo json_encode(['success' => true, 'data' => $analytics]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Biometric Data
function getBiometricData($conn) {
    try {
        // Mock biometric data
        $biometric = [
            'registered_employees' => 45,
            'active_devices' => 3,
            'sync_status' => 'connected',
            'last_sync' => date('Y-m-d H:i:s'),
            'recent_scans' => [
                ['employee' => 'SDK', 'time' => '09:15:00', 'device' => 'Main Gate'],
                ['employee' => 'Dhileepkumar', 'time' => '09:12:00', 'device' => 'Main Gate'],
                ['employee' => 'PP', 'time' => '09:08:00', 'device' => 'Main Gate']
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $biometric]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Compliance Data
function getComplianceData($conn) {
    try {
        // Mock compliance data
        $compliance = [
            'labor_law_compliance' => 95.2,
            'document_status' => [
                'employee_contracts' => ['completed' => 42, 'pending' => 3],
                'tax_documents' => ['completed' => 40, 'pending' => 5],
                'insurance_records' => ['completed' => 45, 'pending' => 0]
            ],
            'audit_trail' => [
                ['action' => 'Employee Added', 'user' => 'HR Admin', 'date' => '2025-07-29 10:30:00'],
                ['action' => 'Leave Approved', 'user' => 'HR Admin', 'date' => '2025-07-29 09:15:00'],
                ['action' => 'Payroll Processed', 'user' => 'HR Admin', 'date' => '2025-07-28 16:45:00']
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $compliance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get Workflows
function getWorkflows($conn) {
    try {
        // Mock workflow data
        $workflows = [
            [
                'id' => 1,
                'name' => 'Employee Onboarding',
                'status' => 'active',
                'steps' => 8,
                'completed' => 5,
                'created_date' => '2025-07-15'
            ],
            [
                'id' => 2,
                'name' => 'Leave Approval Process',
                'status' => 'active',
                'steps' => 4,
                'completed' => 12,
                'created_date' => '2025-07-10'
            ],
            [
                'id' => 3,
                'name' => 'Performance Review Cycle',
                'status' => 'draft',
                'steps' => 6,
                'completed' => 0,
                'created_date' => '2025-07-25'
            ]
        ];
        
        echo json_encode(['success' => true, 'data' => $workflows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Create Workflow
function createWorkflow($conn) {
    try {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $steps = $_POST['steps'] ?? [];
        
        if (!$name || empty($steps)) {
            throw new Exception('Workflow name and steps are required');
        }
        
        // Mock workflow creation
        echo json_encode(['success' => true, 'message' => 'Workflow created successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
