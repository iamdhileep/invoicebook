<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

// Create sample shifts if they don't exist
$shifts_data = [
    [
        'name' => 'Morning Shift',
        'description' => 'Regular morning working hours for general operations',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'working_days' => 'Monday-Friday',
        'break_duration' => 60
    ],
    [
        'name' => 'Evening Shift',
        'description' => 'Evening shift for customer support and operations',
        'start_time' => '14:00:00',
        'end_time' => '22:00:00',
        'working_days' => 'Monday-Friday',
        'break_duration' => 60
    ],
    [
        'name' => 'Night Shift',
        'description' => 'Overnight operations and security coverage',
        'start_time' => '22:00:00',
        'end_time' => '06:00:00',
        'working_days' => 'Monday-Sunday',
        'break_duration' => 90
    ],
    [
        'name' => 'Weekend Shift',
        'description' => 'Weekend operations and maintenance',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'working_days' => 'Saturday-Sunday',
        'break_duration' => 45
    ],
    [
        'name' => 'Flexible Shift',
        'description' => 'Flexible working hours for senior staff',
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
        'working_days' => 'Monday-Friday',
        'break_duration' => 60
    ]
];

echo "Adding sample shifts...<br>";

foreach ($shifts_data as $shift) {
    // Check if shift already exists
    $check = mysqli_query($conn, "SELECT id FROM hr_shifts WHERE name = '{$shift['name']}'");
    
    if (!$check || mysqli_num_rows($check) == 0) {
        $query = "INSERT INTO hr_shifts (name, description, start_time, end_time, working_days, break_duration) 
                  VALUES ('{$shift['name']}', '{$shift['description']}', '{$shift['start_time']}', '{$shift['end_time']}', '{$shift['working_days']}', {$shift['break_duration']})";
        
        if (mysqli_query($conn, $query)) {
            echo "✓ Added shift: {$shift['name']}<br>";
        } else {
            echo "✗ Error adding shift {$shift['name']}: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "- Shift already exists: {$shift['name']}<br>";
    }
}

// Create sample shift assignments (if employees exist)
$employees_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees WHERE status = 'active'");
$emp_count = 0;
if ($employees_check) {
    $result = mysqli_fetch_assoc($employees_check);
    $emp_count = $result['count'];
}

if ($emp_count > 0) {
    echo "<br>Adding sample shift assignments...<br>";
    
    // Get some employees and shifts
    $employees = mysqli_query($conn, "SELECT id, first_name, last_name FROM hr_employees WHERE status = 'active' LIMIT 5");
    $shifts = mysqli_query($conn, "SELECT id, name FROM hr_shifts WHERE status = 'active'");
    
    $shift_list = [];
    while ($shift = mysqli_fetch_assoc($shifts)) {
        $shift_list[] = $shift;
    }
    
    $assignment_count = 0;
    while ($employee = mysqli_fetch_assoc($employees)) {
        if (count($shift_list) > 0) {
            // Check if employee already has assignment
            $existing = mysqli_query($conn, "SELECT id FROM hr_shift_assignments WHERE employee_id = {$employee['id']} AND status = 'active'");
            
            if (!$existing || mysqli_num_rows($existing) == 0) {
                // Assign random shift
                $random_shift = $shift_list[array_rand($shift_list)];
                $start_date = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
                
                $query = "INSERT INTO hr_shift_assignments (employee_id, shift_id, start_date, assigned_by, notes) 
                          VALUES ({$employee['id']}, {$random_shift['id']}, '$start_date', 1, 'Sample assignment for demo')";
                
                if (mysqli_query($conn, $query)) {
                    echo "✓ Assigned {$employee['first_name']} {$employee['last_name']} to {$random_shift['name']}<br>";
                    $assignment_count++;
                } else {
                    echo "✗ Error assigning {$employee['first_name']} {$employee['last_name']}: " . mysqli_error($conn) . "<br>";
                }
            } else {
                echo "- {$employee['first_name']} {$employee['last_name']} already has shift assignment<br>";
            }
        }
    }
    
    if ($assignment_count > 0) {
        echo "<br>✓ Created $assignment_count shift assignments successfully!<br>";
    }
} else {
    echo "<br>⚠️ No active employees found. Please add employees first to create shift assignments.<br>";
}

// Create some sample shift requests
echo "<br>Adding sample shift requests...<br>";
$requests_created = 0;

// Get employees with assignments
$assigned_employees = mysqli_query($conn, "
    SELECT DISTINCT sa.employee_id, sa.shift_id, e.first_name, e.last_name 
    FROM hr_shift_assignments sa 
    JOIN hr_employees e ON sa.employee_id = e.id 
    WHERE sa.status = 'active' 
    LIMIT 3
");

if ($assigned_employees && mysqli_num_rows($assigned_employees) > 0) {
    while ($emp = mysqli_fetch_assoc($assigned_employees)) {
        // Get a different shift for request
        $other_shifts = mysqli_query($conn, "SELECT id, name FROM hr_shifts WHERE id != {$emp['shift_id']} AND status = 'active' LIMIT 1");
        
        if ($other_shifts && $other_shift = mysqli_fetch_assoc($other_shifts)) {
            // Check if request already exists
            $existing_request = mysqli_query($conn, "SELECT id FROM hr_shift_requests WHERE employee_id = {$emp['employee_id']} AND status = 'pending'");
            
            if (!$existing_request || mysqli_num_rows($existing_request) == 0) {
                $reasons = [
                    'Personal schedule change required',
                    'Better work-life balance',
                    'Transportation issues with current shift',
                    'Family commitments',
                    'Health reasons - doctor recommended'
                ];
                
                $random_reason = $reasons[array_rand($reasons)];
                $request_date = date('Y-m-d', strtotime('+' . rand(1, 7) . ' days'));
                
                $query = "INSERT INTO hr_shift_requests (employee_id, current_shift_id, requested_shift_id, request_date, reason) 
                          VALUES ({$emp['employee_id']}, {$emp['shift_id']}, {$other_shift['id']}, '$request_date', '$random_reason')";
                
                if (mysqli_query($conn, $query)) {
                    echo "✓ Created shift change request for {$emp['first_name']} {$emp['last_name']}<br>";
                    $requests_created++;
                } else {
                    echo "✗ Error creating request for {$emp['first_name']} {$emp['last_name']}: " . mysqli_error($conn) . "<br>";
                }
            } else {
                echo "- {$emp['first_name']} {$emp['last_name']} already has pending request<br>";
            }
        }
    }
}

if ($requests_created > 0) {
    echo "<br>✓ Created $requests_created shift change requests successfully!<br>";
}

echo "<br><strong>Sample data creation completed!</strong><br>";
echo "<a href='shift_management.php' class='btn btn-primary mt-3'>Go to Shift Management</a>";
?>
