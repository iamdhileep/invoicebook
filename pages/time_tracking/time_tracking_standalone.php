<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Database connection
include '../../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Tracking Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 280px;
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #dee2e6;
            padding: 20px;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        .stats-card {
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Simple Sidebar -->
    <div class="sidebar">
        <h4 class="mb-4">Time Tracking</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#"><i class="bi bi-house"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../../dashboard.php"><i class="bi bi-arrow-left"></i> Back to Main</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Time Tracking Dashboard</h1>
                            <p class="text-muted">Manage employee time and attendance</p>
                        </div>
                        <div>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#clockModal">
                                <i class="bi bi-clock"></i> Clock In/Out
                            </button>
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#timeOffModal">
                                <i class="bi bi-calendar-plus"></i> Request Time Off
                            </button>
                            <button class="btn btn-warning">
                                <i class="bi bi-clock-fill"></i> Request Overtime
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white stats-card">
                        <div class="card-body text-center">
                            <h2 class="mb-1">
                                <?php
                                $today = date('Y-m-d');
                                $present_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM time_clock WHERE clock_date = ? AND clock_in IS NOT NULL";
                                        $stmt = $conn->prepare($query);
                                        if ($stmt) {
                                            $stmt->bind_param("s", $today);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result) {
                                                $present_count = $result->fetch_assoc()['count'];
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $present_count;
                                ?>
                            </h2>
                            <p class="mb-0">Present Today</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white stats-card">
                        <div class="card-body text-center">
                            <h2 class="mb-1">
                                <?php
                                $on_time_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM time_clock WHERE clock_date = ? AND clock_in <= '09:15:00' AND clock_in IS NOT NULL";
                                        $stmt = $conn->prepare($query);
                                        if ($stmt) {
                                            $stmt->bind_param("s", $today);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result) {
                                                $on_time_count = $result->fetch_assoc()['count'];
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $on_time_count;
                                ?>
                            </h2>
                            <p class="mb-0">On Time</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark stats-card">
                        <div class="card-body text-center">
                            <h2 class="mb-1">
                                <?php
                                $late_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM time_clock WHERE clock_date = ? AND clock_in > '09:15:00'";
                                        $stmt = $conn->prepare($query);
                                        if ($stmt) {
                                            $stmt->bind_param("s", $today);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result) {
                                                $late_count = $result->fetch_assoc()['count'];
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $late_count;
                                ?>
                            </h2>
                            <p class="mb-0">Late Arrivals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white stats-card">
                        <div class="card-body text-center">
                            <h2 class="mb-1">
                                <?php
                                $remote_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM time_clock WHERE clock_date = ? AND location = 'Remote'";
                                        $stmt = $conn->prepare($query);
                                        if ($stmt) {
                                            $stmt->bind_param("s", $today);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result) {
                                                $remote_count = $result->fetch_assoc()['count'];
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $remote_count;
                                ?>
                            </h2>
                            <p class="mb-0">Remote Work</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests Section -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Time Off Requests</h5>
                            <span class="badge bg-primary">
                                <?php
                                $timeoff_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM time_off_requests WHERE status = 'Pending'";
                                        $result = $conn->query($query);
                                        if ($result) {
                                            $timeoff_count = $result->fetch_assoc()['count'];
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $timeoff_count;
                                ?> Pending
                            </span>
                        </div>
                        <div class="card-body">
                            <?php
                            $timeoff_requests = [];
                            try {
                                if ($conn) {
                                    $query = "SELECT tor.*, e.name as employee_name 
                                             FROM time_off_requests tor 
                                             LEFT JOIN employees e ON tor.employee_id = e.id 
                                             WHERE tor.status = 'Pending' 
                                             ORDER BY tor.created_at DESC 
                                             LIMIT 5";
                                    $result = $conn->query($query);
                                    if ($result) {
                                        while ($row = $result->fetch_assoc()) {
                                            $timeoff_requests[] = $row;
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                // Silent fail
                            }
                            ?>
                            
                            <?php if (empty($timeoff_requests)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No pending time off requests</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($timeoff_requests as $request): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($request['employee_name'] ?? 'Unknown Employee') ?></strong>
                                            <small class="text-muted">
                                                <?= isset($request['start_date']) ? date('M d', strtotime($request['start_date'])) : 'N/A' ?> - 
                                                <?= isset($request['end_date']) ? date('M d', strtotime($request['end_date'])) : 'N/A' ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 small"><?= htmlspecialchars($request['reason'] ?? 'No reason provided') ?></p>
                                        <div>
                                            <button class="btn btn-sm btn-success me-1" onclick="alert('Approved!')">
                                                <i class="bi bi-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="alert('Rejected!')">
                                                <i class="bi bi-x"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Overtime Requests</h5>
                            <span class="badge bg-warning">
                                <?php
                                $overtime_count = 0;
                                try {
                                    if ($conn) {
                                        $query = "SELECT COUNT(*) as count FROM overtime_requests WHERE status = 'Pending'";
                                        $result = $conn->query($query);
                                        if ($result) {
                                            $overtime_count = $result->fetch_assoc()['count'];
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silent fail
                                }
                                echo $overtime_count;
                                ?> Pending
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-4">
                                <i class="bi bi-clock-fill text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No pending overtime requests</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= date('F Y') ?> Attendance Calendar</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-5" style="background: #f8f9fa; border-radius: 8px;">
                                <i class="bi bi-calendar3 text-muted" style="font-size: 4rem;"></i>
                                <h6 class="mt-3 text-muted">Calendar View</h6>
                                <p class="text-muted">Attendance calendar will be implemented here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Clock In/Out Modal -->
    <div class="modal fade" id="clockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Clock In/Out</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" id="employeeSelect" required>
                                <option value="">Select Employee</option>
                                <?php
                                try {
                                    if ($conn) {
                                        $empQuery = "SELECT id, name FROM employees ORDER BY name";
                                        $empResult = $conn->query($empQuery);
                                        if ($empResult) {
                                            while ($emp = $empResult->fetch_assoc()) {
                                                echo '<option value="' . $emp['id'] . '">' . htmlspecialchars($emp['name']) . '</option>';
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    echo '<option disabled>Error loading employees</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Action</label>
                            <select class="form-select" id="actionSelect" required>
                                <option value="">Select Action</option>
                                <option value="clock_in">Clock In</option>
                                <option value="clock_out">Clock Out</option>
                                <option value="break_start">Start Break</option>
                                <option value="break_end">End Break</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <select class="form-select" id="locationSelect">
                                <option value="Office">Office</option>
                                <option value="Remote">Remote/WFH</option>
                                <option value="Field">Field Work</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notesInput" rows="3" placeholder="Add any notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitClock()">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Off Modal -->
    <div class="modal fade" id="timeOffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Time Off</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php
                                try {
                                    if ($conn) {
                                        $empQuery = "SELECT id, name FROM employees ORDER BY name";
                                        $empResult = $conn->query($empQuery);
                                        if ($empResult) {
                                            while ($emp = $empResult->fetch_assoc()) {
                                                echo '<option value="' . $emp['id'] . '">' . htmlspecialchars($emp['name']) . '</option>';
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    echo '<option disabled>Error loading employees</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" rows="3" required placeholder="Reason for time off..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="alert('Time off request submitted!')">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitClock() {
            const employee = document.getElementById('employeeSelect').value;
            const action = document.getElementById('actionSelect').value;
            
            if (!employee || !action) {
                alert('Please fill in all required fields');
                return;
            }
            
            alert('Clock action submitted successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('clockModal'));
            if (modal) modal.hide();
        }
    </script>
</body>
</html>
