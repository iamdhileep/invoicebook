<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_stats':
        getDashboardStats($conn);
        break;
    case 'get_team_members':
        getTeamMembers($conn);
        break;
    case 'get_leave_requests':
        getLeaveRequests($conn);
        break;
    case 'update_leave_status':
        updateLeaveStatus($conn);
        break;
    case 'get_team_attendance':
        getTeamAttendance($conn);
        break;
    case 'submit_review':
        submitReview($conn);
        break;
    case 'export_report':
        exportReport($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getDashboardStats($conn) {
    try {
        $manager_id = $_SESSION['admin'];
        
        // Get team members count
        $teamQuery = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
        $teamResult = $conn->query($teamQuery);
        $totalTeam = $teamResult->fetch_assoc()['total'];
        
        // Get pending leaves for team
        $pendingQuery = "SELECT COUNT(*) as total FROM leave_requests lr 
                        JOIN employees e ON lr.employee_id = e.employee_id 
                        WHERE lr.status = 'pending' AND e.status = 'active'";
        $pendingResult = $conn->query($pendingQuery);
        $pendingLeaves = $pendingResult->fetch_assoc()['total'];
        
        // Get present today count
        $presentQuery = "SELECT COUNT(*) as total FROM attendance a 
                        JOIN employees e ON a.employee_id = e.employee_id 
                        WHERE a.attendance_date = CURDATE() AND a.status = 'present' AND e.status = 'active'";
        $presentResult = $conn->query($presentQuery);
        $presentToday = $presentResult->fetch_assoc()['total'];
        
        // Get reviews due (assuming monthly reviews)
        $reviewsQuery = "SELECT COUNT(*) as total FROM employees e 
                        LEFT JOIN performance_reviews pr ON e.employee_id = pr.employee_id 
                        AND MONTH(pr.review_period) = MONTH(CURDATE()) 
                        AND YEAR(pr.review_period) = YEAR(CURDATE())
                        WHERE e.status = 'active' AND pr.id IS NULL";
        $reviewsResult = $conn->query($reviewsQuery);
        $reviewsDue = $reviewsResult->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_team' => $totalTeam,
                'pending_leaves' => $pendingLeaves,
                'present_today' => $presentToday,
                'reviews_due' => $reviewsDue
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTeamMembers($conn) {
    try {
        $query = "SELECT employee_id, name, email, phone, department, position, join_date, status 
                  FROM employees 
                  WHERE status = 'active' 
                  ORDER BY name ASC";
        $result = $conn->query($query);
        
        $teamMembers = [];
        while ($row = $result->fetch_assoc()) {
            $teamMembers[] = $row;
        }
        
        echo json_encode(['success' => true, 'team_members' => $teamMembers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLeaveRequests($conn) {
    try {
        $query = "SELECT lr.*, e.name as employee_name, lt.name as leave_type 
                  FROM leave_requests lr 
                  JOIN employees e ON lr.employee_id = e.employee_id 
                  LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
                  WHERE e.status = 'active'
                  ORDER BY lr.created_at DESC";
        $result = $conn->query($query);
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate days
            $from = new DateTime($row['from_date']);
            $to = new DateTime($row['to_date']);
            $row['days'] = $from->diff($to)->days + 1;
            $leaves[] = $row;
        }
        
        echo json_encode(['success' => true, 'leaves' => $leaves]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLeaveStatus($conn) {
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $comments = $_POST['comments'] ?? '';
        
        if (!$leave_id || !in_array($status, ['approved', 'rejected'])) {
            throw new Exception('Invalid parameters');
        }
        
        $query = $conn->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW(), comments = ? WHERE id = ?");
        $query->bind_param("sisi", $status, $_SESSION['admin'], $comments, $leave_id);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Leave status updated']);
        } else {
            throw new Exception('Failed to update leave status');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTeamAttendance($conn) {
    try {
        $date = $_POST['date'] ?? date('Y-m-d');
        
        $query = "SELECT a.*, e.name as employee_name, e.employee_id,
                         TIME_FORMAT(a.check_in_time, '%H:%i') as check_in,
                         TIME_FORMAT(a.check_out_time, '%H:%i') as check_out,
                         CASE 
                             WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                             THEN TIMEDIFF(a.check_out_time, a.check_in_time)
                             ELSE NULL 
                         END as working_hours
                  FROM employees e 
                  LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                  WHERE e.status = 'active'
                  ORDER BY e.name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            // Set default status if no attendance record
            if (!$row['status']) {
                $row['status'] = 'absent';
            }
            $attendance[] = $row;
        }
        
        echo json_encode(['success' => true, 'attendance' => $attendance]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function submitReview($conn) {
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $review_period = $_POST['review_period'] ?? '';
        $overall_rating = $_POST['overall_rating'] ?? 0;
        $goals_rating = $_POST['goals_rating'] ?? 0;
        $strengths = $_POST['strengths'] ?? '';
        $improvements = $_POST['improvements'] ?? '';
        $comments = $_POST['comments'] ?? '';
        
        if (!$employee_id || !$review_period || !$overall_rating) {
            throw new Exception('Required fields are missing');
        }
        
        // Check if review already exists for this period
        $checkQuery = $conn->prepare("SELECT id FROM performance_reviews WHERE employee_id = ? AND review_period = ?");
        $checkQuery->bind_param("is", $employee_id, $review_period);
        $checkQuery->execute();
        
        if ($checkQuery->get_result()->num_rows > 0) {
            throw new Exception('Review already exists for this period');
        }
        
        $query = $conn->prepare("INSERT INTO performance_reviews 
                                (employee_id, review_period, overall_rating, goals_rating, strengths, improvements, comments, reviewer_id, review_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $query->bind_param("isiiissi", $employee_id, $review_period, $overall_rating, $goals_rating, $strengths, $improvements, $comments, $_SESSION['admin']);
        
        if ($query->execute()) {
            echo json_encode(['success' => true, 'message' => 'Performance review submitted successfully']);
        } else {
            throw new Exception('Failed to submit review');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function exportReport($conn) {
    try {
        $type = $_GET['type'] ?? 'attendance';
        
        switch ($type) {
            case 'attendance':
                exportAttendanceReport($conn);
                break;
            case 'leave':
                exportLeaveReport($conn);
                break;
            case 'performance':
                exportPerformanceReport($conn);
                break;
            default:
                throw new Exception('Invalid report type');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function exportAttendanceReport($conn) {
    $query = "SELECT e.name, e.employee_code, a.attendance_date, a.check_in_time, a.check_out_time, a.status, a.location
              FROM employees e 
              LEFT JOIN attendance a ON e.employee_id = a.employee_id 
              WHERE e.status = 'active' AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              ORDER BY e.name, a.attendance_date DESC";
    
    $result = $conn->query($query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="team_attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Employee Code', 'Date', 'Check In', 'Check Out', 'Status', 'Location']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportLeaveReport($conn) {
    $query = "SELECT e.name, e.employee_code, lr.from_date, lr.to_date, lr.reason, lr.status, lt.name as leave_type
              FROM employees e 
              JOIN leave_requests lr ON e.employee_id = lr.employee_id 
              LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
              WHERE e.status = 'active' AND lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
              ORDER BY e.name, lr.created_at DESC";
    
    $result = $conn->query($query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="team_leave_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Employee Code', 'From Date', 'To Date', 'Reason', 'Status', 'Leave Type']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportPerformanceReport($conn) {
    $query = "SELECT e.name, e.employee_code, pr.review_period, pr.overall_rating, pr.goals_rating, pr.strengths, pr.improvements
              FROM employees e 
              JOIN performance_reviews pr ON e.employee_id = pr.employee_id 
              WHERE e.status = 'active' AND pr.review_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
              ORDER BY e.name, pr.review_period DESC";
    
    $result = $conn->query($query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="team_performance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Employee Code', 'Review Period', 'Overall Rating', 'Goals Rating', 'Strengths', 'Improvements']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}
?>
