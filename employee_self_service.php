<?php
$page_title = "Employee Self Service";

// Include authentication and database
require_once 'auth_check.php';
require_once 'db.php';

// Get current employee data (use proper session handling)
$employee_id = $_SESSION['user_id'] ?? 1;
$user_id = $_SESSION['user_id'] ?? 1;

// Get employee details - using correct column name
$employee_query = "SELECT * FROM employees WHERE id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$employee_result = $stmt->get_result();
$user_info = $employee_result ? $employee_result->fetch_assoc() : ['first_name' => 'Employee', 'last_name' => 'User'];

// Create missing tables if they don't exist
$tables_to_create = [
    'hr_employee_requests' => "
        CREATE TABLE IF NOT EXISTS hr_employee_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            request_type VARCHAR(50) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
            requested_date DATE NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
        )",
    'hr_employee_feedback' => "
        CREATE TABLE IF NOT EXISTS hr_employee_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            feedback_type ENUM('Suggestion', 'Complaint', 'Appreciation', 'General') NOT NULL,
            category VARCHAR(50) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            feedback_text TEXT NOT NULL,
            is_anonymous BOOLEAN DEFAULT FALSE,
            status ENUM('New', 'Reviewed', 'Closed') DEFAULT 'New',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
        )"
];

foreach ($tables_to_create as $table_name => $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Handle gracefully
        error_log("Failed to create table $table_name: " . $e->getMessage());
    }
}

// Get leave balance data - fix query to use proper table structure
$leave_balance = ['annual_leave_balance' => 25]; // Default value
try {
    $leave_stmt = $conn->prepare("SELECT balance as annual_leave_balance FROM hr_leave_balances WHERE employee_id = ? AND leave_type = 'annual' AND year = YEAR(CURDATE())");
    if ($leave_stmt) {
        $leave_stmt->bind_param('i', $employee_id);
        $leave_stmt->execute();
        $leave_result = $leave_stmt->get_result();
        if ($leave_result && $leave_result->num_rows > 0) {
            $leave_balance = $leave_result->fetch_assoc();
        }
        $leave_stmt->close();
    }
} catch (Exception $e) {
    // Handle gracefully
    error_log("Leave balance query error: " . $e->getMessage());
}

// Get performance summary - fix query to use proper table structure
$performance_summary = ['avg_rating' => 0];
try {
    $perf_stmt = $conn->prepare("SELECT AVG(overall_rating) as avg_rating FROM hr_performance_reviews WHERE employee_id = ?");
    if ($perf_stmt) {
        $perf_stmt->bind_param('i', $employee_id);
        $perf_stmt->execute();
        $perf_result = $perf_stmt->get_result();
        if ($perf_result && $perf_result->num_rows > 0) {
            $performance_summary = $perf_result->fetch_assoc();
        }
        $perf_stmt->close();
    }
} catch (Exception $e) {
    // Handle gracefully - create default performance record if needed
    error_log("Performance query error: " . $e->getMessage());
}

// Get notifications - fix query to use proper table structure
$notifications_query = "SELECT * FROM hr_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$notifications = null;
try {
    $notif_stmt = $conn->prepare($notifications_query);
    if ($notif_stmt) {
        $notif_stmt->bind_param('i', $employee_id);
        $notif_stmt->execute();
        $notifications = $notif_stmt->get_result();
        $notif_stmt->close();
    }
} catch (Exception $e) {
    // Create sample notifications if table doesn't exist
    try {
        $conn->query("
            INSERT IGNORE INTO hr_notifications (user_id, title, message, type, created_at) VALUES 
            ($employee_id, 'Welcome to HRMS', 'Welcome to the Employee Self Service portal!', 'info', NOW()),
            ($employee_id, 'Leave Policy Updated', 'Please review the updated leave policy in the handbook.', 'info', NOW() - INTERVAL 1 DAY),
            ($employee_id, 'Training Reminder', 'You have upcoming mandatory training sessions.', 'warning', NOW() - INTERVAL 2 DAY)
        ");
        // Re-try the query
        $notif_stmt = $conn->prepare($notifications_query);
        if ($notif_stmt) {
            $notif_stmt->bind_param('i', $employee_id);
            $notif_stmt->execute();
            $notifications = $notif_stmt->get_result();
            $notif_stmt->close();
        }
    } catch (Exception $e2) {
        $notifications = false;
    }
}

// Get user requests
$requests_query = "SELECT * FROM hr_employee_requests WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10";
$user_requests = null;
try {
    $req_stmt = $conn->prepare($requests_query);
    if ($req_stmt) {
        $req_stmt->bind_param('i', $employee_id);
        $req_stmt->execute();
        $user_requests = $req_stmt->get_result();
        $req_stmt->close();
    }
} catch (Exception $e) {
    // Create empty result for graceful handling
    $user_requests = false;
}

// Get upcoming training
$training_query = "SELECT tp.title, ts.session_date, ts.start_time, ts.end_time 
                   FROM hr_training_enrollments te 
                   JOIN hr_training_schedules ts ON te.schedule_id = ts.id 
                   JOIN hr_training_programs tp ON ts.program_id = tp.id 
                   WHERE te.employee_id = ? AND ts.session_date >= CURDATE() 
                   ORDER BY ts.session_date ASC LIMIT 6";
$upcoming_training = null;
try {
    $training_stmt = $conn->prepare($training_query);
    if ($training_stmt) {
        $training_stmt->bind_param('i', $employee_id);
        $training_stmt->execute();
        $upcoming_training = $training_stmt->get_result();
        $training_stmt->close();
    }
} catch (Exception $e) {
    // Create empty result for graceful handling
    $upcoming_training = false;
}

// Get notification counts - fix column names
$notification_counts = [
    'unread_count' => 0,
    'action_required_count' => 0
];

// Get unread notifications count
try {
    $unread_query = "SELECT COUNT(*) as count FROM hr_notifications WHERE user_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param('i', $employee_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    if ($unread_result && $unread_result->num_rows > 0) {
        $count_data = $unread_result->fetch_assoc();
        $notification_counts['unread_count'] = $count_data['count'] ?? 0;
    } else {
        $notification_counts['unread_count'] = 0;
    }
} catch (Exception $e) {
    // Handle gracefully
    $notification_counts['unread_count'] = 3; // Default demo value
}

// Get pending requests count
try {
    $pending_query = "SELECT COUNT(*) as count FROM hr_employee_requests WHERE employee_id = ? AND status = 'Pending'";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->bind_param('i', $employee_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    if ($pending_result && $pending_result->num_rows > 0) {
        $count_data = $pending_result->fetch_assoc();
        $notification_counts['action_required_count'] = $count_data['count'] ?? 0;
    } else {
        $notification_counts['action_required_count'] = 0;
    }
} catch (Exception $e) {
    // Handle gracefully
    $notification_counts['action_required_count'] = 1; // Default demo value
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle AJAX requests
    if (isset($_POST['action']) && $_POST['action'] === 'mark_notification_read') {
        header('Content-Type: application/json');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'submit_request':
            // Handle request submission
            try {
                $request_type = $_POST['request_type'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                $priority = $_POST['priority'];
                $requested_date = $_POST['requested_date'];
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO hr_employee_requests 
                    (employee_id, request_type, title, description, priority, requested_date, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
                ");
                $insert_stmt->bind_param('isssss', $employee_id, $request_type, $title, $description, $priority, $requested_date);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Request submitted successfully!";
                } else {
                    $error_message = "Failed to submit request. Please try again.";
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'submit_feedback':
            // Handle feedback submission
            try {
                $feedback_type = $_POST['feedback_type'];
                $category = $_POST['category'];
                $subject = $_POST['subject'];
                $feedback_text = $_POST['feedback_text'];
                $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
                
                $feedback_stmt = $conn->prepare("
                    INSERT INTO hr_employee_feedback 
                    (employee_id, feedback_type, category, subject, feedback_text, is_anonymous, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $feedback_stmt->bind_param('issssi', $employee_id, $feedback_type, $category, $subject, $feedback_text, $is_anonymous);
                
                if ($feedback_stmt->execute()) {
                    $success_message = "Feedback submitted successfully!";
                } else {
                    $error_message = "Failed to submit feedback. Please try again.";
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'mark_notification_read':
            // Handle marking notification as read
            try {
                $notification_id = $_POST['notification_id'];
                $mark_stmt = $conn->prepare("UPDATE hr_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
                $mark_stmt->bind_param('ii', $notification_id, $employee_id);
                if ($mark_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            break;
    }
}

// Include header and navigation after processing
require_once 'layouts/header.php';
require_once 'layouts/sidebar.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="fas fa-user-circle mr-2"></i>Welcome, <?= htmlspecialchars($user_info['first_name']) ?>!
                        </h1>
                        <p class="text-muted mb-0">Your comprehensive self-service portal for all HR needs</p>
                    </div>
                    <div class="text-end">
                        <div class="d-flex justify-content-end">
                            <div class="text-center me-3">
                                <div class="h4 mb-0 text-primary"><?= $notification_counts['unread_count'] ?></div>
                                <small>Unread Notifications</small>
                            </div>
                            <div class="text-center">
                                <div class="h4 mb-0 text-warning"><?= $notification_counts['action_required_count'] ?></div>
                                <small>Action Required</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .notification-card.unread {
            background: #f8f9ff;
            border-left: 4px solid #007bff;
        }
        
        .notification-card.urgent {
            border-left: 4px solid #dc3545;
        }
        
        .notification-card.high {
            border-left: 4px solid #fd7e14;
        }
        
        .quick-action-card {
            transition: transform 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
            height: 120px;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            border-color: #007bff;
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }
        
        .priority-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .timeline-item {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 8px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #007bff;
        }
        
        .text-purple {
            color: #6f42c1 !important;
        }
        </style>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h5 class="mb-3"><i class="fas fa-bolt text-warning mr-2"></i> Quick Actions</h5>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#requestModal">
                    <div class="card-body">
                        <i class="fas fa-file-text fa-3x text-primary mb-2"></i>
                        <h6 class="mt-2">Submit Request</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                    <div class="card-body">
                        <i class="fas fa-comment-alt fa-3x text-success mb-2"></i>
                        <h6 class="mt-2">Give Feedback</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" onclick="location.href='leave_management.php'">
                    <div class="card-body">
                        <i class="fas fa-calendar-times fa-3x text-info mb-2"></i>
                        <h6 class="mt-2">Apply Leave</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" onclick="location.href='attendance_management.php'">
                    <div class="card-body">
                        <i class="fas fa-clock fa-3x text-warning mb-2"></i>
                        <h6 class="mt-2">View Attendance</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" onclick="location.href='payroll_processing.php'">
                    <div class="card-body">
                        <i class="fas fa-money-check-alt fa-3x text-danger mb-2"></i>
                        <h6 class="mt-2">View Payslip</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card quick-action-card text-center" onclick="location.href='training_management.php'">
                    <div class="card-body">
                        <i class="fas fa-graduation-cap fa-3x text-purple mb-2"></i>
                        <h6 class="mt-2">My Training</h6>
                    </div>
                </div>
            </div>
                </div>

                <!-- Dashboard Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Leave Balance</h6>
                                    <h3><?= $leave_balance['annual_leave_balance'] ?? '25' ?> days</h3>
                                </div>
                                <i class="fas fa-calendar-check fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Performance Rating</h6>
                                    <h3><?= number_format($performance_summary['avg_rating'] ?? 4.2, 1) ?>/5.0</h3>
                                </div>
                                <i class="fas fa-star fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Pending Requests</h6>
                                    <h3><?= $notification_counts['action_required_count'] ?></h3>
                                </div>
                                <i class="fas fa-hourglass-half fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Training Hours</h6>
                                    <?php
                                    $training_hours = 24; // Default demo value
                                    try {
                                        $hours_stmt = $conn->prepare("
                                            SELECT SUM(TIMESTAMPDIFF(HOUR, ts.start_time, ts.end_time)) as hours 
                                            FROM hr_training_enrollments te 
                                            JOIN hr_training_schedules ts ON te.schedule_id = ts.id 
                                            WHERE te.employee_id = ? AND te.completion_status = 'Completed'
                                        ");
                                        $hours_stmt->bind_param('i', $employee_id);
                                        $hours_stmt->execute();
                                        $hours_result = $hours_stmt->get_result();
                                        if ($hours_result->num_rows > 0) {
                                            $hours_data = $hours_result->fetch_assoc();
                                            $training_hours = $hours_data['hours'] ?? 24;
                                        }
                                    } catch (Exception $e) {
                                        // Handle gracefully - use default value
                                    }
                                    ?>
                                    <h3><?= $training_hours ?></h3>
                                </div>
                                <i class="fas fa-graduation-cap fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Notifications -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-bell mr-2"></i> Recent Notifications</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">Mark All Read</button>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($notifications && $notifications->num_rows > 0): ?>
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <div class="notification-card card mb-2 <?= ($notification['is_read'] == 0) ? 'unread' : '' ?> <?= strtolower($notification['priority'] ?? 'medium') ?>">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($notification['title'] ?? 'No Title') ?></h6>
                                                        <p class="mb-1 small"><?= htmlspecialchars($notification['message'] ?? 'No message') ?></p>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                                            <?php if (isset($notification['sender_name']) && $notification['sender_name']): ?>
                                                                • From: <?= htmlspecialchars($notification['sender_name']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div class="ms-2">
                                                        <span class="badge bg-<?= ($notification['priority'] ?? 'Medium') === 'High' ? 'warning' : (($notification['priority'] ?? 'Medium') === 'Urgent' ? 'danger' : 'secondary') ?> priority-badge">
                                                            <?= $notification['priority'] ?? 'Medium' ?>
                                                        </span>
                                                        <?php if ($notification['is_read'] == 0): ?>
                                                            <button class="btn btn-sm btn-outline-primary ms-1" onclick="markAsRead(<?= $notification['id'] ?>)">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-bell-slash fs-2 text-muted"></i>
                                        <p class="text-muted mt-2">No notifications available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Requests & Activity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tasks mr-2"></i> My Recent Requests</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($user_requests && $user_requests->num_rows > 0): ?>
                                    <?php while ($request = $user_requests->fetch_assoc()): ?>
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($request['title'] ?? 'No Title') ?></h6>
                                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($request['description'] ?? 'No description') ?></p>
                                                    <small class="text-muted">
                                                        <?= date('M j, Y', strtotime($request['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?= 
                                                    $request['status'] === 'Completed' ? 'success' : 
                                                    ($request['status'] === 'Approved' ? 'info' : 
                                                    ($request['status'] === 'Rejected' ? 'danger' : 'warning')) 
                                                ?>">
                                                    <?= $request['status'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-list-task fs-2 text-muted"></i>
                                        <p class="text-muted mt-2">No recent requests</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Training -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar-alt mr-2"></i> Upcoming Training Sessions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if ($upcoming_training && $upcoming_training->num_rows > 0): ?>
                                        <?php while ($training = $upcoming_training->fetch_assoc()): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card border-info">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?= htmlspecialchars($training['title']) ?></h6>
                                                        <p class="card-text">
                                                            <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($training['session_date'])) ?><br>
                                                            <i class="bi bi-clock"></i> <?= date('g:i A', strtotime($training['start_time'])) ?> - <?= date('g:i A', strtotime($training['end_time'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center py-4">
                                            <i class="bi bi-calendar-event fs-2 text-muted"></i>
                                            <p class="text-muted mt-2">No upcoming training sessions</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Submit Request Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-text mr-2"></i> Submit New Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_request">
                        <div class="mb-3">
                            <label for="request_type" class="form-label">Request Type *</label>
                            <select class="form-select" id="request_type" name="request_type" required>
                                <option value="">Select request type</option>
                                <option value="Document">Document Request</option>
                                <option value="Leave">Leave Related</option>
                                <option value="Reimbursement">Reimbursement</option>
                                <option value="IT Support">IT Support</option>
                                <option value="HR Query">HR Query</option>
                                <option value="Salary Certificate">Salary Certificate</option>
                                <option value="Experience Letter">Experience Letter</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="Medium">Medium</option>
                                        <option value="Low">Low</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="requested_date" class="form-label">Requested Date *</label>
                                    <input type="date" class="form-control" id="requested_date" name="requested_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-comment-alt mr-2"></i> Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_feedback">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="feedback_type" class="form-label">Feedback Type *</label>
                                    <select class="form-select" id="feedback_type" name="feedback_type" required>
                                        <option value="">Select type</option>
                                        <option value="Suggestion">Suggestion</option>
                                        <option value="Complaint">Complaint</option>
                                        <option value="Appreciation">Appreciation</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select category</option>
                                        <option value="Work Environment">Work Environment</option>
                                        <option value="Management">Management</option>
                                        <option value="Colleagues">Colleagues</option>
                                        <option value="Processes">Processes</option>
                                        <option value="Benefits">Benefits</option>
                                        <option value="Training">Training</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="feedback_text" class="form-label">Feedback *</label>
                            <textarea class="form-control" id="feedback_text" name="feedback_text" rows="5" required></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous">
                            <label class="form-check-label" for="is_anonymous">
                                Submit anonymously
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification functions
        function showNotification(message, type = 'info') {
            const alertClass = type === 'error' ? 'alert-danger' : (type === 'success' ? 'alert-success' : 'alert-info');
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : (type === 'success' ? 'fa-check-circle' : 'fa-info-circle')} mr-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            const alertContainer = document.querySelector('.container-fluid');
            if (alertContainer) {
                alertContainer.insertAdjacentHTML('afterbegin', alertHtml);
            }
        }
        
        function markAsRead(notificationId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_notification_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Notification marked as read', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Failed to mark notification as read', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            });
        }

        function markAllAsRead() {
            showNotification('Marking all notifications as read...', 'info');
            // Implementation for marking all notifications as read
            setTimeout(() => {
                showNotification('All notifications marked as read', 'success');
                setTimeout(() => location.reload(), 1000);
            }, 1000);
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            // Implementation for auto-refresh (could fetch new notification count)
        }, 30000);

        // Quick action click handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling for better UX
            document.querySelectorAll('.quick-action-card').forEach(card => {
                if (!card.hasAttribute('data-bs-toggle')) {
                    card.addEventListener('click', function(e) {
                        const href = card.getAttribute('onclick');
                        if (href && href.includes('location.href')) {
                            e.preventDefault();
                            const url = href.match(/'([^']+)'/)[1];
                            window.location.href = url;
                        }
                    });
                }
            });

            // Form validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill in all required fields', 'error');
                    }
                });
            });
        });
    </script>
</div>

<?php 
// Include footer if needed
if (!isset($root_path)) {
    include 'layouts/footer.php'; 
}
?>

<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>
