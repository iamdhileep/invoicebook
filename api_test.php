<?php
/**
 * API Test Endpoint
 * Tests all API functionality and database connections
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db.php';
require_once 'notification_system.php';

// Test API endpoints
$endpoint = $_GET['test'] ?? 'all';

function testDatabaseConnection($conn) {
    try {
        if ($conn->ping()) {
            return [
                'status' => 'success',
                'message' => 'Database connection successful',
                'mysql_version' => $conn->server_info,
                'database_name' => $conn->query("SELECT DATABASE() as db")->fetch_assoc()['db']
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
}

function testNotificationSystem($conn) {
    try {
        $notificationSystem = new NotificationSystem($conn);
        
        // Get an existing employee ID
        $result = $conn->query("SELECT employee_id FROM employees WHERE status = 'active' LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'No active employees found for testing',
                'test_notification_sent' => false
            ];
        }
        
        $employee = $result->fetch_assoc();
        $employee_id = $employee['employee_id'];
        
        // Test creating a notification
        $testResult = $notificationSystem->sendNotification(
            $employee_id, 
            'API Test Notification', 
            'This is a test notification from the API system', 
            'info', 
            ['web']
        );
        
        return [
            'status' => $testResult ? 'success' : 'error',
            'message' => $testResult ? 'Notification system working' : 'Notification system failed',
            'test_notification_sent' => $testResult,
            'tested_employee_id' => $employee_id
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Notification system error: ' . $e->getMessage()
        ];
    }
}

function testEmployeeQueries($conn) {
    try {
        // Test employee count
        $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
        $employeeCount = $result->fetch_assoc()['count'];
        
        // Test attendance count
        $today = date('Y-m-d');
        $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = '$today'");
        $attendanceCount = $result->fetch_assoc()['count'];
        
        return [
            'status' => 'success',
            'message' => 'Employee queries working',
            'active_employees' => $employeeCount,
            'todays_attendance_records' => $attendanceCount
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Employee query error: ' . $e->getMessage()
        ];
    }
}

function testMobileAPISimulation($conn) {
    try {
        // Simulate mobile API functionality
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        
        // Test dashboard data query
        $dashboardQuery = "SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
            FROM employees e
            LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = '$today'
            WHERE e.status = 'active'";
        
        $result = $conn->query($dashboardQuery);
        $dashboardData = $result->fetch_assoc();
        
        return [
            'status' => 'success',
            'message' => 'Mobile API simulation successful',
            'dashboard_data' => $dashboardData,
            'current_time' => $currentTime
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Mobile API simulation failed: ' . $e->getMessage()
        ];
    }
}

function testAllSystems($conn) {
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => [
            'database' => testDatabaseConnection($conn),
            'notification_system' => testNotificationSystem($conn),
            'employee_queries' => testEmployeeQueries($conn),
            'mobile_api_simulation' => testMobileAPISimulation($conn)
        ]
    ];
    
    // Calculate overall status
    $allSuccess = true;
    foreach ($results['tests'] as $test) {
        if ($test['status'] !== 'success') {
            $allSuccess = false;
            break;
        }
    }
    
    $results['overall_status'] = $allSuccess ? 'success' : 'partial_failure';
    $results['message'] = $allSuccess ? 'All systems operational' : 'Some systems have issues';
    
    return $results;
}

// Handle the API request
try {
    switch ($endpoint) {
        case 'database':
            $response = testDatabaseConnection($conn);
            break;
        case 'notifications':
            $response = testNotificationSystem($conn);
            break;
        case 'employees':
            $response = testEmployeeQueries($conn);
            break;
        case 'mobile':
            $response = testMobileAPISimulation($conn);
            break;
        case 'all':
        default:
            $response = testAllSystems($conn);
            break;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'API test failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
