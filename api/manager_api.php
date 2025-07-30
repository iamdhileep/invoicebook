<?php
// Manager Dashboard API Functions
// This file contains all manager-specific functions for the dashboard

include '../db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get POST data
$action = $_POST['action'] ?? '';

// Manager ID from session (default to 2 for demo)
$manager_id = $_SESSION['employee_id'] ?? 2;

try {
    switch ($action) {
        case 'get_dashboard_stats':
            getDashboardStats($conn);
            break;
        case 'get_team_members':
            getTeamMembers($conn);
            break;
        case 'get_team_attendance':
            getTeamAttendance($conn);
            break;
        case 'get_team_leave_requests':
            getTeamLeaveRequests($conn);
            break;
        case 'get_team_reports':
            getTeamReports($conn);
            break;
        case 'approve_leave':
            approveLeave($conn);
            break;
        case 'reject_leave':
            rejectLeave($conn);
            break;
        case 'get_performance_reviews':
            getPerformanceReviews($conn);
            break;
        case 'add_performance_review':
            addPerformanceReview($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Dashboard Statistics
function getDashboardStats($conn) {
    global $manager_id;
    
    try {
        $stats = [];
        
        // Team size - count active employees
        $teamQuery = $conn->query("SELECT COUNT(*) as team_size FROM employees WHERE status = 'active'");
        $stats['team_members'] = $teamQuery ? $teamQuery->fetch_assoc()['team_size'] : 0;
        
        // Pending leave requests - check if table exists
        $checkLeaveTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($checkLeaveTable && $checkLeaveTable->num_rows > 0) {
            $pendingQuery = $conn->query("SELECT COUNT(*) as pending_requests FROM leave_requests WHERE status = 'pending'");
            $stats['pending_approvals'] = $pendingQuery ? $pendingQuery->fetch_assoc()['pending_requests'] : 0;
        } else {
            $stats['pending_approvals'] = 3; // Mock data
        }
        
        // Today's attendance
        $todayQuery = $conn->prepare("SELECT COUNT(*) as present_today FROM attendance a
                                     JOIN employees e ON a.employee_id = e.employee_id
                                     WHERE e.status = 'active' AND a.attendance_date = CURDATE()
                                     AND a.punch_in_time IS NOT NULL");
        $todayQuery->execute();
        $result = $todayQuery->get_result();
        $stats['team_present'] = $result ? $result->fetch_assoc()['present_today'] : 0;
        
        // Performance score - calculate based on attendance
        $totalEmployees = $stats['team_members'];
        $presentToday = $stats['team_present'];
        $stats['performance_score'] = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 85;
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Team Members
function getTeamMembers($conn) {
    try {
        // Get team members with today's attendance status
        $query = $conn->prepare("SELECT e.*, 
                                COALESCE(a.punch_in_time, null) as today_punch_in,
                                COALESCE(a.punch_out_time, null) as today_punch_out,
                                CASE 
                                    WHEN a.punch_in_time IS NULL THEN 'absent'
                                    WHEN TIME(a.punch_in_time) > '09:15:00' THEN 'late'
                                    ELSE 'present'
                                END as today_status,
                                COALESCE(pr.avg_rating, 3.5) as performance_rating
                                FROM employees e 
                                LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = CURDATE()
                                LEFT JOIN (
                                    SELECT employee_id, 
                                           AVG((technical_rating + communication_rating + teamwork_rating) / 3) as avg_rating
                                    FROM performance_reviews 
                                    GROUP BY employee_id
                                ) pr ON e.employee_id = pr.employee_id
                                WHERE e.status = 'active'
                                ORDER BY e.name");
        $query->execute();
        $result = $query->get_result();
        
        $team = [];
        while ($row = $result->fetch_assoc()) {
            $team[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'position' => $row['position'] ?? 'Employee',
                'status' => $row['status'],
                'today_status' => $row['today_status'],
                'performance_rating' => round($row['performance_rating'], 1),
                'phone' => $row['phone'] ?? '',
                'monthly_salary' => $row['monthly_salary'],
                'today_punch_in' => $row['today_punch_in'] ? date('H:i', strtotime($row['today_punch_in'])) : null,
                'today_punch_out' => $row['today_punch_out'] ? date('H:i', strtotime($row['today_punch_out'])) : null
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $team]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Team Attendance
function getTeamAttendance($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Get attendance data for the specified date
        $query = $conn->prepare("SELECT a.*, e.name as employee_name,
                                TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time) as work_duration,
                                CASE 
                                    WHEN a.punch_in_time IS NULL THEN 'absent'
                                    WHEN TIME(a.punch_in_time) > '09:15:00' THEN 'late'
                                    ELSE 'present'
                                END as status,
                                CASE 
                                    WHEN TIME(a.punch_in_time) > '09:00:00' THEN TIMESTAMPDIFF(MINUTE, TIME('09:00:00'), TIME(a.punch_in_time))
                                    ELSE 0
                                END as late_minutes
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
                'attendance_date' => $date,
                'punch_in_time' => $row['punch_in_time'] ? date('H:i', strtotime($row['punch_in_time'])) : null,
                'punch_out_time' => $row['punch_out_time'] ? date('H:i', strtotime($row['punch_out_time'])) : null,
                'work_duration' => $row['work_duration'] ?? 0,
                'status' => $row['status'],
                'late_minutes' => $row['late_minutes'] ?? 0
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Team Leave Requests
function getTeamLeaveRequests($conn) {
    try {
        $status = $_POST['status'] ?? 'pending';
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $whereClause = $status === 'all' ? '' : "WHERE lr.status = '$status'";
            $query = $conn->prepare("SELECT lr.*, e.name as employee_name 
                                    FROM leave_requests lr
                                    JOIN employees e ON lr.employee_id = e.employee_id
                                    $whereClause 
                                    ORDER BY lr.applied_date DESC");
            $query->execute();
            $result = $query->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
        } else {
            // Create mock leave request data
            $allRequests = [
                [
                    'id' => 1,
                    'employee_name' => 'John Doe',
                    'leave_type' => 'Annual Leave',
                    'from_date' => '2025-08-01',
                    'to_date' => '2025-08-03',
                    'days_requested' => 3,
                    'reason' => 'Family vacation',
                    'applied_date' => '2025-07-20 10:30:00',
                    'status' => 'pending'
                ],
                [
                    'id' => 2,
                    'employee_name' => 'Jane Smith',
                    'leave_type' => 'Sick Leave',
                    'from_date' => '2025-07-28',
                    'to_date' => '2025-07-29',
                    'days_requested' => 2,
                    'reason' => 'Medical appointment',
                    'applied_date' => '2025-07-27 14:20:00',
                    'status' => 'approved'
                ],
                [
                    'id' => 3,
                    'employee_name' => 'Mike Johnson',
                    'leave_type' => 'Personal Leave',
                    'from_date' => '2025-08-05',
                    'to_date' => '2025-08-05',
                    'days_requested' => 1,
                    'reason' => 'Personal work',
                    'applied_date' => '2025-07-25 09:15:00',
                    'status' => 'pending'
                ]
            ];
            
            // Filter by status if not 'all'
            if ($status !== 'all') {
                $requests = array_filter($allRequests, function($req) use ($status) {
                    return $req['status'] === $status;
                });
                $requests = array_values($requests); // Re-index array
            } else {
                $requests = $allRequests;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $requests]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Team Reports
function getTeamReports($conn) {
    try {
        $reports = [];
        
        // Team attendance summary
        $totalQuery = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
        $totalEmployees = $totalQuery ? $totalQuery->fetch_assoc()['total'] : 0;
        
        $attendanceQuery = $conn->prepare("SELECT 
                                          COUNT(DISTINCT a.employee_id) as present_employees,
                                          COUNT(a.id) as total_records,
                                          COUNT(CASE WHEN TIME(a.punch_in_time) <= '09:15:00' THEN 1 END) as on_time_records
                                          FROM attendance a 
                                          WHERE MONTH(a.attendance_date) = MONTH(CURDATE()) 
                                          AND YEAR(a.attendance_date) = YEAR(CURDATE())
                                          AND a.punch_in_time IS NOT NULL");
        $attendanceQuery->execute();
        $attendanceResult = $attendanceQuery->get_result()->fetch_assoc();
        
        $reports['attendance'] = [
            'total_members' => $totalEmployees,
            'avg_attendance' => $totalEmployees > 0 ? round(($attendanceResult['present_employees'] / $totalEmployees) * 100) : 0,
            'on_time_rate' => $attendanceResult['total_records'] > 0 ? round(($attendanceResult['on_time_records'] / $attendanceResult['total_records']) * 100) : 0
        ];
        
        // Leave utilization (mock data)
        $reports['leaves'] = [
            'pending' => 3,
            'approved' => 18,
            'avg_days' => 3.2
        ];
        
        // Performance metrics (mock data)
        $reports['performance'] = [
            'total_reviews' => 15,
            'avg_rating' => 4.1,
            'top_performers' => 9
        ];
        
        // Productivity metrics
        $productivityQuery = $conn->prepare("SELECT 
                                            SUM(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)) as total_hours,
                                            AVG(TIMESTAMPDIFF(HOUR, a.punch_in_time, a.punch_out_time)) as avg_daily
                                            FROM attendance a 
                                            WHERE MONTH(a.attendance_date) = MONTH(NOW())
                                            AND YEAR(a.attendance_date) = YEAR(NOW())
                                            AND a.punch_in_time IS NOT NULL 
                                            AND a.punch_out_time IS NOT NULL");
        $productivityQuery->execute();
        $productivityResult = $productivityQuery->get_result()->fetch_assoc();
        
        $reports['productivity'] = [
            'score' => 89,
            'total_hours' => $productivityResult['total_hours'] ?? 1450,
            'avg_daily' => round($productivityResult['avg_daily'] ?? 8.2, 1)
        ];
        
        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Approve Leave
function approveLeave($conn) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['manager_comments'] ?? '';
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $GLOBALS['manager_id'], $comments, $leave_id);
            
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
function rejectLeave($conn) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $comments = $_POST['manager_comments'] ?? '';
        
        if (!$leave_id) {
            throw new Exception('Leave ID is required');
        }
        
        // Check if leave_requests table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $query = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), approver_comments = ? WHERE id = ?");
            $query->bind_param("isi", $GLOBALS['manager_id'], $comments, $leave_id);
            
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

// Performance Reviews
function getPerformanceReviews($conn) {
    try {
        // Check if performance_reviews table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'performance_reviews'");
        
        if ($checkTable && $checkTable->num_rows > 0) {
            $query = $conn->prepare("SELECT pr.*, e.name as employee_name FROM performance_reviews pr
                                    JOIN employees e ON pr.employee_id = e.employee_id
                                    ORDER BY pr.review_date DESC");
            $query->execute();
            $result = $query->get_result();
            
            $reviews = [];
            while ($row = $result->fetch_assoc()) {
                $reviews[] = $row;
            }
        } else {
            // Mock performance review data
            $reviews = [
                [
                    'id' => 1,
                    'employee_name' => 'John Doe',
                    'review_period' => 'quarterly',
                    'technical_rating' => 4,
                    'communication_rating' => 4,
                    'teamwork_rating' => 5,
                    'achievements' => 'Completed major project ahead of schedule',
                    'improvement_areas' => 'Could improve in client communication',
                    'review_date' => '2025-07-15'
                ],
                [
                    'id' => 2,
                    'employee_name' => 'Jane Smith',
                    'review_period' => 'monthly',
                    'technical_rating' => 5,
                    'communication_rating' => 5,
                    'teamwork_rating' => 4,
                    'achievements' => 'Excellent team leadership and mentoring',
                    'improvement_areas' => 'Focus on technical skill development',
                    'review_date' => '2025-07-10'
                ]
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $reviews]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Add Performance Review
function addPerformanceReview($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $review_period = $_POST['review_period'] ?? '';
        $technical_rating = $_POST['technical_rating'] ?? 3;
        $communication_rating = $_POST['communication_rating'] ?? 3;
        $teamwork_rating = $_POST['teamwork_rating'] ?? 3;
        $achievements = $_POST['achievements'] ?? '';
        $improvement_areas = $_POST['improvement_areas'] ?? '';
        $next_goals = $_POST['next_goals'] ?? '';
        
        if (!$employee_id || !$review_period) {
            throw new Exception('Employee ID and Review Period are required');
        }
        
        // Check if performance_reviews table exists, if not create it
        $createTable = "CREATE TABLE IF NOT EXISTS performance_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            manager_id INT NOT NULL,
            review_period VARCHAR(20) NOT NULL,
            technical_rating INT DEFAULT 3,
            communication_rating INT DEFAULT 3,
            teamwork_rating INT DEFAULT 3,
            achievements TEXT,
            improvement_areas TEXT,
            next_goals TEXT,
            review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);
        
        $query = $conn->prepare("INSERT INTO performance_reviews (employee_id, manager_id, review_period, technical_rating, communication_rating, teamwork_rating, achievements, improvement_areas, next_goals, review_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $query->bind_param("iisiissss", $employee_id, $GLOBALS['manager_id'], $review_period, $technical_rating, $communication_rating, $teamwork_rating, $achievements, $improvement_areas, $next_goals);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Performance review added successfully']);
        } else {
            throw new Exception('Failed to add performance review');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
