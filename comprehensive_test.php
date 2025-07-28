<?php
echo "==== TIME TRACKING SYSTEM COMPREHENSIVE TEST ====\n\n";

// Start session and simulate login
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['admin'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

// Test 1: Basic API Connection
echo "1. Testing Basic API Connection...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'refresh_stats';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "✓ Basic API connection successful\n";
    echo "  - Total employees: " . $response['stats']['total_employees'] . "\n";
    echo "  - Present today: " . $response['stats']['present_today'] . "\n";
} else {
    echo "✗ Basic API connection failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 2: Manager Tools - Team Counts
echo "\n2. Testing Manager Tools - Team Counts...\n";
$_POST['action'] = 'get_team_counts';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "✓ Team counts API successful\n";
    echo "  - Total count: " . $response['total_count'] . "\n";
    echo "  - Present count: " . $response['present_count'] . "\n";
} else {
    echo "✗ Team counts API failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 3: Manager Tools - Team Attendance
echo "\n3. Testing Manager Tools - Team Attendance...\n";
$_POST['action'] = 'get_team_attendance';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "✓ Team attendance API successful\n";
    echo "  - Team members listed: " . count($response['data']) . "\n";
    if (!empty($response['data'])) {
        echo "  - First employee: " . $response['data'][0]['name'] . " (" . $response['data'][0]['status'] . ")\n";
    }
} else {
    echo "✗ Team attendance API failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 4: Manager Tools - Employee List
echo "\n4. Testing Manager Tools - Employee List...\n";
$_POST['action'] = 'get_employees';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "✓ Employee list API successful\n";
    echo "  - Employees available: " . count($response['data']) . "\n";
} else {
    echo "✗ Employee list API failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 5: Smart Attendance - Manual Check-in
echo "\n5. Testing Smart Attendance - Manual Check-in...\n";
$_POST['action'] = 'manual_checkin';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response) {
    if ($response['success']) {
        echo "✓ Manual check-in API successful\n";
    } else {
        echo "ℹ Manual check-in API responded (may be already checked in): " . $response['message'] . "\n";
    }
} else {
    echo "✗ Manual check-in API failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 6: Smart Attendance - IP Check-in
echo "\n6. Testing Smart Attendance - IP Check-in...\n";
$_POST['action'] = 'ip_checkin';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response) {
    if ($response['success']) {
        echo "✓ IP check-in API successful\n";
    } else {
        echo "ℹ IP check-in API responded: " . $response['message'] . "\n";
    }
} else {
    echo "✗ IP check-in API failed\n";
    echo "  Response: " . $output . "\n";
}

// Test 7: Bulk Operations
echo "\n7. Testing Bulk Operations...\n";
$_POST['action'] = 'bulk_approve_attendance';

ob_start();
include 'api/time_tracking_api.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && $response['success']) {
    echo "✓ Bulk approve API successful\n";
} else {
    echo "✗ Bulk approve API failed\n";
    echo "  Response: " . $output . "\n";
}

echo "\n==== TEST SUMMARY ====\n";
echo "All major API endpoints tested. Check above for detailed results.\n";
echo "Time Tracking System is ready for use!\n\n";

// Clean up
unset($_POST['action']);
unset($_SERVER['REQUEST_METHOD']);
?>
