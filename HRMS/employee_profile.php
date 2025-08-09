<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Employee Profile - HRMS';

// Get employee ID from URL parameter or redirect if missing
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$employee_id) {
    header("Location: employee_directory.php");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_profile':
            $id = intval($_POST['id']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $department_id = intval($_POST['department_id']);
            $salary = floatval($_POST['salary']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $date_of_birth = $_POST['date_of_birth'] ? mysqli_real_escape_string($conn, $_POST['date_of_birth']) : null;
            $gender = mysqli_real_escape_string($conn, $_POST['gender']);
            $marital_status = mysqli_real_escape_string($conn, $_POST['marital_status']);
            
            $query = "UPDATE hr_employees SET 
                      first_name = '$first_name',
                      last_name = '$last_name',
                      email = '$email',
                      phone = '$phone',
                      address = '$address',
                      position = '$position',
                      department_id = $department_id,
                      salary = $salary,
                      status = '$status',
                      date_of_birth = " . ($date_of_birth ? "'$date_of_birth'" : "NULL") . ",
                      gender = '$gender',
                      marital_status = '$marital_status'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'update_emergency_contact':
            $id = intval($_POST['id']);
            $emergency_contact_name = mysqli_real_escape_string($conn, $_POST['emergency_contact_name']);
            $emergency_contact_phone = mysqli_real_escape_string($conn, $_POST['emergency_contact_phone']);
            $emergency_contact_relation = mysqli_real_escape_string($conn, $_POST['emergency_contact_relation']);
            
            $query = "UPDATE hr_employees SET 
                      emergency_contact_name = '$emergency_contact_name',
                      emergency_contact_phone = '$emergency_contact_phone',
                      emergency_contact_relation = '$emergency_contact_relation'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Emergency contact updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'add_note':
            $employee_id = intval($_POST['employee_id']);
            $note = mysqli_real_escape_string($conn, $_POST['note']);
            $note_type = mysqli_real_escape_string($conn, $_POST['note_type']);
            $created_by = $_SESSION['user_id'] ?? 1;
            
            $query = "INSERT INTO employee_notes (employee_id, note, note_type, created_by, created_at) 
                      VALUES ($employee_id, '$note', '$note_type', $created_by, NOW())";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Note added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_note':
            $note_id = intval($_POST['note_id']);
            $query = "DELETE FROM employee_notes WHERE id = $note_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Note deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_attendance_data':
            $emp_id = intval($_POST['employee_id']);
            $month = $_POST['month'] ?? date('Y-m');
            
            $attendance_query = "
                SELECT 
                    attendance_date,
                    check_in_time,
                    check_out_time,
                    status,
                    hours_worked,
                    overtime_hours
                FROM hr_attendance 
                WHERE employee_id = $emp_id 
                AND DATE_FORMAT(attendance_date, '%Y-%m') = '$month'
                ORDER BY attendance_date DESC
            ";
            
            $result = mysqli_query($conn, $attendance_query);
            $attendance_records = [];
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $attendance_records[] = $row;
                }
            }
            
            // Get summary statistics
            $summary_query = "
                SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                    COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
                    SUM(hours_worked) as total_hours,
                    SUM(overtime_hours) as total_overtime
                FROM hr_attendance 
                WHERE employee_id = $emp_id 
                AND DATE_FORMAT(attendance_date, '%Y-%m') = '$month'
            ";
            
            $summary_result = mysqli_query($conn, $summary_query);
            $summary = mysqli_fetch_assoc($summary_result);
            
            echo json_encode([
                'success' => true, 
                'records' => $attendance_records,
                'summary' => $summary
            ]);
            exit;

        case 'get_performance_reviews':
            $emp_id = intval($_POST['employee_id']);
            
            $reviews_query = "
                SELECT * FROM hr_performance_reviews 
                WHERE employee_id = $emp_id 
                ORDER BY review_date DESC 
                LIMIT 10
            ";
            
            $result = mysqli_query($conn, $reviews_query);
            $reviews = [];
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $reviews[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'reviews' => $reviews]);
            exit;
            
            $result = mysqli_query($conn, $performance_query);
            $performance_data = mysqli_fetch_assoc($result);
            
            echo json_encode(['success' => true, 'data' => $performance_data]);
            exit;

        case 'update_emergency_contact':
            $emp_id = intval($_POST['employee_id']);
            $contact_name = mysqli_real_escape_string($conn, $_POST['contact_name']);
            $contact_relationship = mysqli_real_escape_string($conn, $_POST['contact_relationship']);
            $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);
            
            $query = "UPDATE hr_employees SET 
                      emergency_contact_name = '$contact_name',
                      emergency_contact_relationship = '$contact_relationship',
                      emergency_contact_phone = '$contact_phone'
                      WHERE id = $emp_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Emergency contact updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Fetch employee data
$employee_query = "
    SELECT e.*, d.department_name 
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    WHERE e.id = $employee_id
";
$employee_result = mysqli_query($conn, $employee_query);
$employee = mysqli_fetch_assoc($employee_result);

if (!$employee) {
    header("Location: employee_directory.php");
    exit;
}

// Get attendance statistics for current month
$current_month = date('Y-m');
$attendance_query = "
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
        COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
        COUNT(*) as total_days
    FROM hr_attendance 
    WHERE employee_id = $employee_id 
    AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'
";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance_stats = mysqli_fetch_assoc($attendance_result) ?: [
    'present_days' => 0, 'absent_days' => 0, 'late_days' => 0, 
    'half_days' => 0, 'total_days' => 0
];

// Calculate attendance percentage
$attendance_percentage = $attendance_stats['total_days'] > 0 ? 
    round(($attendance_stats['present_days'] + ($attendance_stats['half_days'] * 0.5)) / $attendance_stats['total_days'] * 100, 1) : 0;

// Get recent attendance records
$recent_attendance_query = "
    SELECT * FROM hr_attendance 
    WHERE employee_id = $employee_id 
    ORDER BY attendance_date DESC 
    LIMIT 7
";
$recent_attendance_result = mysqli_query($conn, $recent_attendance_query);
$recent_attendance = [];
if ($recent_attendance_result) {
    while ($row = mysqli_fetch_assoc($recent_attendance_result)) {
        $recent_attendance[] = $row;
    }
}

// Get performance data
$performance_query = "
    SELECT * FROM hr_performance_reviews 
    WHERE employee_id = $employee_id 
    ORDER BY review_date DESC 
    LIMIT 1
";
$performance_result = mysqli_query($conn, $performance_query);
$performance_data = mysqli_fetch_assoc($performance_result);

// Get leave statistics
$leave_query = "
    SELECT 
        COUNT(*) as total_applications,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_leaves,
        SUM(CASE WHEN status = 'approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as days_used
    FROM hr_leave_applications 
    WHERE employee_id = $employee_id 
    AND YEAR(start_date) = YEAR(CURDATE())
";
$leave_result = mysqli_query($conn, $leave_query);
$leave_stats = mysqli_fetch_assoc($leave_result) ?: [
    'total_applications' => 0, 'approved_leaves' => 0, 'days_used' => 0
];

// Get departments for dropdown
$departments_query = "SELECT id, department_name FROM hr_departments WHERE status = 'active' ORDER BY department_name";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
if ($departments_result) {
    while ($dept = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $dept;
    }
}

// Get employee notes
$notes_query = "SELECT * FROM employee_notes WHERE employee_id = $employee_id ORDER BY created_at DESC LIMIT 10";
$notes_result = mysqli_query($conn, $notes_query);
$notes = [];
if ($notes_result) {
    while ($note = mysqli_fetch_assoc($notes_result)) {
        $notes[] = $note;
    }
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">👤 Employee Profile</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-person-badge"></i> 
                        Complete employee information and management
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="printProfile()" title="Print Profile">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportProfile()" title="Export Profile">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal" title="Edit Profile">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </button>
                    <a href="employee_directory.php" class="btn btn-outline-secondary btn-sm" title="Back to Directory">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Profile Header Card -->
            <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="position-relative d-inline-block">
                                <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center text-white" 
                                     style="width: 120px; height: 120px; border: 4px solid rgba(255,255,255,0.3);">
                                    <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                                </div>
                                <span class="position-absolute bottom-0 end-0 bg-<?= $employee['status'] === 'active' ? 'success' : ($employee['status'] === 'on_leave' ? 'warning' : 'secondary') ?> border border-white rounded-circle" 
                                      style="width: 30px; height: 30px; border-width: 3px !important;"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="text-white mb-2"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                            <p class="text-white-50 mb-1 h5"><?= htmlspecialchars($employee['position'] ?? 'No Position Assigned') ?></p>
                            <p class="text-white-50 mb-3">
                                <i class="bi bi-building me-2"></i><?= htmlspecialchars($employee['department_name'] ?? 'No Department') ?>
                            </p>
                            <div class="d-flex gap-3 flex-wrap">
                                <span class="badge bg-white bg-opacity-20 text-white px-3 py-2">
                                    <i class="bi bi-card-text me-1"></i>ID: <?= htmlspecialchars($employee['employee_id']) ?>
                                </span>
                                <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : ($employee['status'] === 'on_leave' ? 'warning' : 'secondary') ?> px-3 py-2">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i><?= ucfirst($employee['status']) ?>
                                </span>
                                <span class="badge bg-white bg-opacity-20 text-white px-3 py-2">
                                    <i class="bi bi-calendar-event me-1"></i>Joined <?= date('M Y', strtotime($employee['date_of_joining'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3 text-md-end text-center mt-3 mt-md-0">
                            <div class="d-flex flex-column gap-2">
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="bi bi-pencil me-2"></i>Edit Profile
                                </button>
                                <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                    <i class="bi bi-plus-circle me-2"></i>Add Note
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-calendar-check-fill fs-2" style="color: #388e3c;"></i>
                            </div>
                            <h4 class="mb-1 fw-bold" style="color: #388e3c;"><?= $attendance_percentage ?>%</h4>
                            <small class="text-muted">Attendance Rate</small>
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: <?= $attendance_percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-star-fill fs-2" style="color: #1976d2;"></i>
                            </div>
                            <h4 class="mb-1 fw-bold" style="color: #1976d2;">
                                <?= $performance_data ? number_format($performance_data['overall_rating'], 1) . '/5' : 'N/A' ?>
                            </h4>
                            <small class="text-muted">Performance Score</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-calendar-x-fill fs-2" style="color: #f57c00;"></i>
                            </div>
                            <h4 class="mb-1 fw-bold" style="color: #f57c00;"><?= $leave_stats['days_used'] ?></h4>
                            <small class="text-muted">Leave Days Used</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-currency-rupee fs-2" style="color: #7b1fa2;"></i>
                            </div>
                            <h4 class="mb-1 fw-bold" style="color: #7b1fa2;">
                                ₹<?= number_format($employee['salary'] ?? 0) ?>
                            </h4>
                            <small class="text-muted">Monthly Salary</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Tabs -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                <i class="bi bi-person me-2"></i>Personal Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                                <i class="bi bi-briefcase me-2"></i>Employment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                                <i class="bi bi-calendar-check me-2"></i>Attendance
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button" role="tab">
                                <i class="bi bi-graph-up me-2"></i>Performance
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
                                <i class="bi bi-sticky me-2"></i>Notes
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabContent">
                        <!-- Personal Information Tab -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-primary">
                                        <i class="bi bi-person-lines-fill me-2"></i>Contact Information
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted fw-medium" style="width: 40%;">Full Name:</td>
                                                <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Email:</td>
                                                <td>
                                                    <?php if ($employee['email']): ?>
                                                        <a href="mailto:<?= htmlspecialchars($employee['email']) ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($employee['email']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Phone:</td>
                                                <td>
                                                    <?php if ($employee['phone']): ?>
                                                        <a href="tel:<?= htmlspecialchars($employee['phone']) ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($employee['phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Address:</td>
                                                <td><?= htmlspecialchars($employee['address'] ?? 'Not provided') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Emergency Contact
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted fw-medium" style="width: 40%;">Name:</td>
                                                <td><?= htmlspecialchars($employee['emergency_contact_name'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Relationship:</td>
                                                <td><?= htmlspecialchars($employee['emergency_contact_relationship'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Phone:</td>
                                                <td>
                                                    <?php if ($employee['emergency_contact_phone']): ?>
                                                        <a href="tel:<?= htmlspecialchars($employee['emergency_contact_phone']) ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($employee['emergency_contact_phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not provided</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyContactModal">
                                        <i class="bi bi-plus-circle me-1"></i>Update Emergency Contact
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information Tab -->
                        <div class="tab-pane fade" id="employment" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-primary">
                                        <i class="bi bi-briefcase-fill me-2"></i>Job Details
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted fw-medium" style="width: 45%;">Employee ID:</td>
                                                <td><code><?= htmlspecialchars($employee['employee_id']) ?></code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Position:</td>
                                                <td><?= htmlspecialchars($employee['position'] ?? 'Not specified') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Department:</td>
                                                <td><?= htmlspecialchars($employee['department_name'] ?? 'Not specified') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Date of Joining:</td>
                                                <td>
                                                    <?= $employee['date_of_joining'] ? date('F j, Y', strtotime($employee['date_of_joining'])) : 'Not specified' ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Employment Type:</td>
                                                <td>
                                                    <span class="badge bg-info bg-opacity-75">
                                                        <?= ucwords(str_replace('_', ' ', $employee['employment_type'] ?? 'full_time')) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-success">
                                        <i class="bi bi-currency-rupee me-2"></i>Compensation
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted fw-medium" style="width: 45%;">Monthly Salary:</td>
                                                <td class="fw-bold text-success">₹<?= number_format($employee['salary'] ?? 0, 2) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Annual Package:</td>
                                                <td class="fw-bold text-success">₹<?= number_format(($employee['salary'] ?? 0) * 12, 2) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Status:</td>
                                                <td>
                                                    <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : ($employee['status'] === 'on_leave' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($employee['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted fw-medium">Work Location:</td>
                                                <td>Office</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Tab -->
                        <div class="tab-pane fade" id="attendance" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-primary">This Month's Summary</h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="bg-success bg-opacity-10 p-3 rounded text-center">
                                                <i class="bi bi-check-circle-fill text-success fs-3 mb-2"></i>
                                                <h4 class="text-success mb-1"><?= $attendance_stats['present_days'] ?></h4>
                                                <small class="text-muted">Present Days</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-danger bg-opacity-10 p-3 rounded text-center">
                                                <i class="bi bi-x-circle-fill text-danger fs-3 mb-2"></i>
                                                <h4 class="text-danger mb-1"><?= $attendance_stats['absent_days'] ?></h4>
                                                <small class="text-muted">Absent Days</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-warning bg-opacity-10 p-3 rounded text-center">
                                                <i class="bi bi-clock-fill text-warning fs-3 mb-2"></i>
                                                <h4 class="text-warning mb-1"><?= $attendance_stats['late_days'] ?></h4>
                                                <small class="text-muted">Late Days</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-info bg-opacity-10 p-3 rounded text-center">
                                                <i class="bi bi-clock-history text-info fs-3 mb-2"></i>
                                                <h4 class="text-info mb-1"><?= $attendance_stats['half_days'] ?></h4>
                                                <small class="text-muted">Half Days</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 text-primary">Recent Activity</h6>
                                    <div class="list-group list-group-flush">
                                        <?php if (!empty($recent_attendance)): ?>
                                            <?php foreach (array_slice($recent_attendance, 0, 7) as $record): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <div>
                                                        <span class="fw-medium"><?= date('M j, Y', strtotime($record['attendance_date'])) ?></span>
                                                        <small class="text-muted d-block"><?= date('l', strtotime($record['attendance_date'])) ?></small>
                                                    </div>
                                                    <span class="badge bg-<?= $record['status'] === 'present' ? 'success' : ($record['status'] === 'late' ? 'warning' : ($record['status'] === 'half_day' ? 'info' : 'danger')) ?>">
                                                        <?= ucfirst($record['status']) ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-calendar-x text-muted fs-1"></i>
                                                <p class="text-muted mt-2">No attendance records found</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3">
                                        <a href="attendance.php?employee_id=<?= $employee_id ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-calendar-check me-1"></i>View Full Attendance
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Tab -->
                        <div class="tab-pane fade" id="performance" role="tabpanel">
                            <div class="row">
                                <?php if ($performance_data): ?>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3 text-primary">Performance Metrics</h6>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="fw-medium">Overall Performance</span>
                                                <span class="fw-bold text-success"><?= number_format($performance_data['overall_rating'], 1) ?>/5</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: <?= ($performance_data['overall_rating'] / 5) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if (isset($performance_data['productivity_rating'])): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="fw-medium">Productivity</span>
                                                <span class="fw-bold text-primary"><?= number_format($performance_data['productivity_rating'], 1) ?>/5</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?= ($performance_data['productivity_rating'] / 5) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (isset($performance_data['communication_rating'])): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="fw-medium">Communication</span>
                                                <span class="fw-bold text-info"><?= number_format($performance_data['communication_rating'], 1) ?>/5</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: <?= ($performance_data['communication_rating'] / 5) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3 text-primary">Latest Review</h6>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h6 class="card-title mb-0">
                                                        <?= date('M Y', strtotime($performance_data['review_date'])) ?> Review
                                                    </h6>
                                                    <span class="badge bg-success"><?= number_format($performance_data['overall_rating'], 1) ?>/5</span>
                                                </div>
                                                <p class="card-text"><?= htmlspecialchars($performance_data['comments'] ?? 'No comments provided') ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Reviewed on <?= date('M j, Y', strtotime($performance_data['review_date'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-12 text-center py-5">
                                        <i class="bi bi-graph-up text-muted" style="font-size: 4rem;"></i>
                                        <h5 class="text-muted mt-3">No Performance Reviews</h5>
                                        <p class="text-muted">Performance reviews will appear here once conducted.</p>
                                        <button class="btn btn-primary" onclick="alert('Performance review feature coming soon!')">
                                            <i class="bi bi-plus-circle me-2"></i>Add Performance Review
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notes Tab -->
                        <div class="tab-pane fade" id="notes" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold mb-0 text-primary">Employee Notes</h6>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                            <i class="bi bi-plus-circle me-1"></i>Add Note
                                        </button>
                                    </div>
                                    <?php if (!empty($notes)): ?>
                                        <div class="timeline">
                                            <?php foreach ($notes as $note): ?>
                                                <div class="card mb-3">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <small class="text-muted">
                                                                <i class="bi bi-calendar3 me-1"></i>
                                                                <?= date('M j, Y \a\t g:i A', strtotime($note['created_at'])) ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="bi bi-person me-1"></i>Admin
                                                            </small>
                                                        </div>
                                                        <p class="mb-0"><?= htmlspecialchars($note['note']) ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="bi bi-sticky text-muted" style="font-size: 4rem;"></i>
                                            <h5 class="text-muted mt-3">No Notes Added</h5>
                                            <p class="text-muted">Employee notes and observations will appear here.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                                <i class="bi bi-plus-circle me-2"></i>Add First Note
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">
                                                <i class="bi bi-info-circle me-2"></i>Note Guidelines
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-unstyled small">
                                                <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Keep notes professional</li>
                                                <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Include dates for important events</li>
                                                <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Document achievements</li>
                                                <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Note areas for improvement</li>
                                                <li><i class="bi bi-check-circle text-success me-1"></i> Track training completions</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-primary me-2"></i>Edit Employee Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProfileForm">
                <input type="hidden" name="id" value="<?= $employee_id ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($employee['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($employee['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($employee['phone']) ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($employee['address']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($employee['position']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $employee['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Salary (₹)</label>
                            <input type="number" name="salary" class="form-control" value="<?= $employee['salary'] ?>" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $employee['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $employee['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="on_leave" <?= $employee['status'] === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-sticky text-success me-2"></i>Add Employee Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addNoteForm">
                <input type="hidden" name="employee_id" value="<?= $employee_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note Content *</label>
                        <textarea name="note" class="form-control" rows="4" placeholder="Enter your note about this employee..." required></textarea>
                    </div>
                    <div class="bg-light p-3 rounded">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This note will be associated with <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> 
                            and will be visible to authorized personnel.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Add Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Emergency Contact Modal -->
<div class="modal fade" id="emergencyContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Update Emergency Contact
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="emergencyContactForm">
                <input type="hidden" name="employee_id" value="<?= $employee_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Contact Name *</label>
                        <input type="text" name="contact_name" class="form-control" 
                               value="<?= htmlspecialchars($employee['emergency_contact_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Relationship *</label>
                        <select name="contact_relationship" class="form-select" required>
                            <option value="">Select Relationship</option>
                            <option value="spouse" <?= $employee['emergency_contact_relationship'] === 'spouse' ? 'selected' : '' ?>>Spouse</option>
                            <option value="parent" <?= $employee['emergency_contact_relationship'] === 'parent' ? 'selected' : '' ?>>Parent</option>
                            <option value="sibling" <?= $employee['emergency_contact_relationship'] === 'sibling' ? 'selected' : '' ?>>Sibling</option>
                            <option value="child" <?= $employee['emergency_contact_relationship'] === 'child' ? 'selected' : '' ?>>Child</option>
                            <option value="friend" <?= $employee['emergency_contact_relationship'] === 'friend' ? 'selected' : '' ?>>Friend</option>
                            <option value="other" <?= $employee['emergency_contact_relationship'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="contact_phone" class="form-control" 
                               value="<?= htmlspecialchars($employee['emergency_contact_phone']) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form submission handlers
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_profile');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating profile');
    });
});

document.getElementById('addNoteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_note');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding note');
    });
});

document.getElementById('emergencyContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_emergency_contact');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating emergency contact');
    });
});

// Utility functions
function printProfile() {
    window.print();
}

function exportProfile() {
    alert('Export functionality will be implemented with PDF generation');
}

// Auto-refresh attendance data when tab is clicked
document.getElementById('attendance-tab').addEventListener('click', function() {
    // Could implement real-time attendance data refresh here
});

// Initialize tooltips if Bootstrap is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips for all elements with title attribute
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
</script>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    color: white;
}

.stat-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background-color: transparent;
    border-bottom: 3px solid #0d6efd;
    color: #0d6efd;
}

.timeline {
    position: relative;
}

.table-borderless td {
    padding: 0.5rem 0.75rem;
    border: none;
}

@media print {
    .btn, .modal, .nav-tabs {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}

.progress {
    background-color: rgba(0,0,0,0.1);
    border-radius: 4px;
}

.badge {
    font-size: 0.85em;
}
</style>

<?php include '../layouts/footer.php'; ?>
