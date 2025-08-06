<?php
/**
 * Employee Profile - HRMS Module
 * View and manage employee profiles
 */
session_start();
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Employee Profiles - HRMS';

// Get employee ID from URL parameter
$employee_id = $_GET['id'] ?? 0;
$employee = null;

if ($employee_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error fetching employee: " . $e->getMessage());
    }
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
            border-radius: 15px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.2);
            border: 4px solid rgba(255,255,255,0.3);
        <?php if ($employee): ?>
            <!-- Employee Profile Header -->
                <div class="profile-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="profile-avatar rounded-circle mx-auto d-flex align-items-center justify-content-center">
                                <h1 class="mb-0"><?= strtoupper(substr($employee['name'] ?? 'N', 0, 1)) ?></h1>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="mb-1"><?= htmlspecialchars($employee['name'] ?? 'Unknown') ?></h2>
                            <p class="mb-1 h5"><?= htmlspecialchars($employee['position'] ?? 'No Position') ?></p>
                            <p class="mb-2"><i class="bi bi-building me-2"></i><?= htmlspecialchars($employee['department_name'] ?? 'General') ?></p>
                            <div class="d-flex gap-3">
                                <span class="badge bg-light text-dark px-3 py-2">ID: <?= $employee['employee_code'] ?? $employee['employee_id'] ?></span>
                                <span class="badge bg-success px-3 py-2"><?= ucfirst($employee['status'] ?? 'active') ?></span>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-light btn-lg me-2" onclick="editEmployee(<?= $employee['employee_id'] ?>)">
                                <i class="bi bi-pencil me-2"></i>Edit Profile
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <i class="bi bi-calendar-check display-4 text-success mb-3"></i>
                                <h4 class="text-success">92%</h4>
                                <p class="text-muted mb-0">Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <i class="bi bi-graph-up-arrow display-4 text-primary mb-3"></i>
                                <h4 class="text-primary">4.2/5</h4>
                                <p class="text-muted mb-0">Performance Score</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <i class="bi bi-calendar-x display-4 text-warning mb-3"></i>
                                <h4 class="text-warning">8</h4>
                                <p class="text-muted mb-0">Leave Days Used</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <i class="bi bi-award display-4 text-info mb-3"></i>
                                <h4 class="text-info">3</h4>
                                <p class="text-muted mb-0">Certifications</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Details Tabs -->
                <div class="card">
                    <div class="card-header">
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
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="profileTabContent">
                            <!-- Personal Information -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Contact Information</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted">Email:</td>
                                                <td><?= htmlspecialchars($employee['email'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Phone:</td>
                                                <td><?= htmlspecialchars($employee['phone'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Address:</td>
                                                <td><?= htmlspecialchars($employee['address'] ?? 'Not provided') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Emergency Contact</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted">Name:</td>
                                                <td>Not provided</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Relationship:</td>
                                                <td>Not provided</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Phone:</td>
                                                <td>Not provided</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment Information -->
                            <div class="tab-pane fade" id="employment" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Job Details</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted">Employee ID:</td>
                                                <td><?= htmlspecialchars($employee['employee_code'] ?? $employee['employee_id']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Position:</td>
                                                <td><?= htmlspecialchars($employee['position'] ?? 'Not specified') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Department:</td>
                                                <td><?= htmlspecialchars($employee['department_name'] ?? 'General') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Hire Date:</td>
                                                <td><?= $employee['hire_date'] ? date('F j, Y', strtotime($employee['hire_date'])) : 'Not specified' ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Compensation</h6>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="text-muted">Monthly Salary:</td>
                                                <td>₹<?= number_format($employee['monthly_salary'] ?? 0, 2) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Employment Type:</td>
                                                <td>Full-time</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Work Location:</td>
                                                <td>Office</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Information -->
                            <div class="tab-pane fade" id="attendance" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">This Month</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="bg-success-subtle p-3 rounded text-center">
                                                    <h4 class="text-success mb-1">22</h4>
                                                    <small>Present Days</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="bg-danger-subtle p-3 rounded text-center">
                                                    <h4 class="text-danger mb-1">2</h4>
                                                    <small>Absent Days</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Recent Activity</h6>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Today</span>
                                                <span class="badge bg-success">Present</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span>Yesterday</span>
                                                <span class="badge bg-success">Present</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><?= date('M j', strtotime('-2 days')) ?></span>
                                                <span class="badge bg-warning">Late</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Performance Information -->
                            <div class="tab-pane fade" id="performance" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Performance Metrics</h6>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Overall Performance</span>
                                                <span>84%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: 84%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Project Completion</span>
                                                <span>92%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" style="width: 92%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Team Collaboration</span>
                                                <span>78%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-info" style="width: 78%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Recent Reviews</h6>
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">Q3 2024 Review</h6>
                                                <p class="card-text">Excellent performance with consistent delivery quality. Shows great initiative and leadership potential.</p>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">Reviewed by: Manager</small>
                                                    <span class="badge bg-success">4.2/5</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Employee Selected -->
                <div class="text-center">
                    <div class="card">
                        <div class="card-body py-5">
                            <i class="bi bi-person-x display-1 text-muted mb-3"></i>
                            <h3>No Employee Selected</h3>
                            <p class="text-muted mb-4">Please select an employee to view their profile</p>
                            <a href="employee_directory.php" class="btn btn-primary">
                                <i class="bi bi-people me-2"></i>Go to Employee Directory
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function editEmployee(employeeId) {
            // Redirect to edit page or open modal
            window.location.href = `edit_employee.php?id=${employeeId}`;
        }
    </script>
</div>
</div>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
