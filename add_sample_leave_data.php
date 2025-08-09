<?php
include 'db.php';

echo "=== Available Leave Types ===\n";
$types = mysqli_query($conn, "SELECT * FROM hr_leave_types WHERE status = 'active'");
if($types) {
    while($type = mysqli_fetch_assoc($types)) {
        echo "- " . $type['leave_type_name'] . " (" . $type['leave_code'] . ") - " . $type['days_allowed_per_year'] . " days/year\n";
    }
}

echo "\n=== Adding Sample Leave Requests ===\n";

// Get employee IDs
$employees = mysqli_query($conn, "SELECT id, first_name, last_name FROM hr_employees LIMIT 3");
$emp_ids = [];
if($employees) {
    while($emp = mysqli_fetch_assoc($employees)) {
        $emp_ids[] = $emp['id'];
        echo "Employee: " . $emp['first_name'] . " " . $emp['last_name'] . " (ID: " . $emp['id'] . ")\n";
    }
}

if(!empty($emp_ids)) {
    // Sample leave requests
    $sample_requests = [
        [
            'employee_id' => $emp_ids[0],
            'leave_type' => 'AL',
            'start_date' => '2025-08-15',
            'end_date' => '2025-08-17',
            'reason' => 'Family vacation',
            'status' => 'pending'
        ],
        [
            'employee_id' => $emp_ids[1] ?? $emp_ids[0],
            'leave_type' => 'SL',
            'start_date' => '2025-08-20',
            'end_date' => '2025-08-21',
            'reason' => 'Medical checkup',
            'status' => 'approved'
        ],
        [
            'employee_id' => $emp_ids[2] ?? $emp_ids[0],
            'leave_type' => 'CL',
            'start_date' => '2025-08-25',
            'end_date' => '2025-08-25',
            'reason' => 'Personal work',
            'status' => 'pending'
        ]
    ];

    foreach($sample_requests as $req) {
        $start = new DateTime($req['start_date']);
        $end = new DateTime($req['end_date']);
        $days = $start->diff($end)->days + 1;
        
        $query = "INSERT INTO hr_leave_requests (employee_id, leave_type, start_date, end_date, days_requested, reason, status, applied_at) 
                  VALUES ({$req['employee_id']}, '{$req['leave_type']}', '{$req['start_date']}', '{$req['end_date']}', $days, '{$req['reason']}', '{$req['status']}', NOW())";
        
        if(mysqli_query($conn, $query)) {
            echo "✓ Added leave request for employee {$req['employee_id']}\n";
        } else {
            echo "✗ Failed to add request: " . mysqli_error($conn) . "\n";
        }
    }
}
?>
