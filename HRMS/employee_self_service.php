<?php
session_start();
$page_title = "Employee Self Service";

// Include header and navigation
include '../layouts/header.php';
include '../layouts/sidebar.php';
include '../db.php';

// Get current employee data (for demo, using a default employee)
$employee_id = $_SESSION['employee_id'] ?? 1;

// Get employee details
$employee_query = "SELECT * FROM employees WHERE employee_id = $employee_id";
$employee_result = $conn->query($employee_query);
$user_info = $employee_result ? $employee_result->fetch_assoc() : ['first_name' => 'Employee'];

// Get notification counts (mock data)
$notification_counts = [
    'unread_count' => 0,
    'action_required_count' => 0
];

// Get unread notifications if tables exist
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE employee_id = $employee_id AND is_read = 0";
$unread_result = $conn->query($unread_query);
if ($unread_result) {
    $notification_counts['unread_count'] = $unread_result->fetch_assoc()['count'];
}

// Get pending leave requests count
$pending_query = "SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = $employee_id AND status = 'pending'";
$pending_result = $conn->query($pending_query);
if ($pending_result) {
    $notification_counts['action_required_count'] = $pending_result->fetch_assoc()['count'];
}
?>

<style>
        .notification-card.unread {
            background: #f8f9ff;
            border-left-color: #007bff;
        }
        
        .notification-card.urgent {
            border-left-color: #dc3545;
        }
        
        .notification-card.high {
            border-left-color: #fd7e14;
        }
        
        .quick-action-card {
            transition: transform 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
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
        
        .self-service-header {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
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
        <!-- Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-person-workspace me-3"></i>Welcome, <?= htmlspecialchars($user_info['first_name']) ?>!
                        </h1>
                        <p class="text-muted mb-0">Your comprehensive self-service portal for all HR needs</p>
                    </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end">
                                <div class="text-center me-3">
                                    <div class="h4 mb-0"><?= $notification_counts['unread_count'] ?></div>
                                    <small>Unread Notifications</small>
                                </div>
                                <div class="text-center">
                                    <div class="h4 mb-0"><?= $notification_counts['action_required_count'] ?></div>
                                    <small>Action Required</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="mb-3"><i class="bi bi-lightning-fill text-warning"></i> Quick Actions</h5>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#requestModal">
                            <div class="card-body">
                                <i class="bi bi-file-text display-4 text-primary"></i>
                                <h6 class="mt-2">Submit Request</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                            <div class="card-body">
                                <i class="bi bi-chat-heart display-4 text-success"></i>
                                <h6 class="mt-2">Give Feedback</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" onclick="location.href='leave_management.php'">
                            <div class="card-body">
                                <i class="bi bi-calendar-x display-4 text-info"></i>
                                <h6 class="mt-2">Apply Leave</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" onclick="location.href='attendance_management.php'">
                            <div class="card-body">
                                <i class="bi bi-clock display-4 text-warning"></i>
                                <h6 class="mt-2">View Attendance</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" onclick="location.href='payroll_processing.php'">
                            <div class="card-body">
                                <i class="bi bi-calculator display-4 text-danger"></i>
                                <h6 class="mt-2">View Payslip</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card quick-action-card text-center" onclick="location.href='training_management.php'">
                            <div class="card-body">
                                <i class="bi bi-book display-4 text-purple"></i>
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
                                    <h3><?= $leave_balance['annual_leave_balance'] ?? '0' ?> days</h3>
                                </div>
                                <i class="bi bi-calendar-check display-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Performance Rating</h6>
                                    <h3><?= number_format($performance_summary['avg_rating'] ?? 0, 1) ?>/5.0</h3>
                                </div>
                                <i class="bi bi-star display-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Pending Requests</h6>
                                    <h3><?= $conn->query("SELECT COUNT(*) as count FROM employee_requests WHERE employee_id = " . $_SESSION['user_id'] . " AND status = 'Pending'")->fetch_assoc()['count'] ?></h3>
                                </div>
                                <i class="bi bi-hourglass display-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Training Hours</h6>
                                    <h3><?= $conn->query("SELECT SUM(TIMESTAMPDIFF(HOUR, ts.start_time, ts.end_time)) as hours FROM training_enrollments te JOIN training_schedules ts ON te.schedule_id = ts.id WHERE te.employee_id = " . $_SESSION['user_id'] . " AND te.completion_status = 'Completed'")->fetch_assoc()['hours'] ?? '0' ?></h3>
                                </div>
                                <i class="bi bi-mortarboard display-4 opacity-50"></i>
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
                                <h5><i class="bi bi-bell"></i> Recent Notifications</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">Mark All Read</button>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php while ($notification = $notifications->fetch_assoc()): ?>
                                    <div class="notification-card card mb-2 <?= $notification['status'] === 'Unread' ? 'unread' : '' ?> <?= strtolower($notification['priority']) ?>">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                                    <p class="mb-1 small"><?= htmlspecialchars($notification['message']) ?></p>
                                                    <small class="text-muted">
                                                        <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                                        <?php if ($notification['sender_name']): ?>
                                                            • From: <?= htmlspecialchars($notification['sender_name']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="ms-2">
                                                    <span class="badge bg-<?= $notification['priority'] === 'High' ? 'warning' : ($notification['priority'] === 'Urgent' ? 'danger' : 'secondary') ?> priority-badge">
                                                        <?= $notification['priority'] ?>
                                                    </span>
                                                    <?php if ($notification['status'] === 'Unread'): ?>
                                                        <button class="btn btn-sm btn-outline-primary ms-1" onclick="markAsRead(<?= $notification['id'] ?>)">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Requests & Activity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-list-task"></i> My Recent Requests</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php while ($request = $user_requests->fetch_assoc()): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($request['title']) ?></h6>
                                                <p class="mb-1 small text-muted"><?= htmlspecialchars($request['description']) ?></p>
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Training -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-calendar-event"></i> Upcoming Training Sessions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
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
                    <h5 class="modal-title"><i class="bi bi-file-text"></i> Submit New Request</h5>
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
                    <h5 class="modal-title"><i class="bi bi-chat-heart"></i> Submit Feedback</h5>
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
        function markAsRead(notificationId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_notification_read&notification_id=' + notificationId
            }).then(() => {
                location.reload();
            });
        }

        function markAllAsRead() {
            // Implementation for marking all notifications as read
            location.reload();
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            // Implementation for auto-refresh
        }, 30000);
    </script>
</div>
</div>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
