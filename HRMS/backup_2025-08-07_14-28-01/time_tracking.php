<?php
session_start();
$page_title = "Time Tracking - HRMS";

// Include header and navigation
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
include '../db.php';

// Get real-time statistics
$today = date('Y-m-d');

// Total Hours Today (from time_tracking table or attendance total_hours)
$total_hours_today_query = "SELECT SUM(total_hours) as total_hours FROM hr_attendance WHERE date = '$today'";
$total_hours_result = $conn->query($total_hours_today_query);
$total_hours_today = $total_hours_result ? round($total_hours_result->fetch_assoc()['total_hours'] ?? 0) : 0;

// Active Projects (from projects table)
$active_projects_query = "SELECT COUNT(*) as count FROM projects WHERE status = 'active'";
$active_projects_result = $conn->query($active_projects_query);
$active_projects = $active_projects_result ? $active_projects_result->fetch_assoc()['count'] : 0;

// Active Employees (working today)
$active_employees_query = "SELECT COUNT(DISTINCT employee_id) as count FROM hr_attendance WHERE date = '$today' AND status != 'absent'";
$active_employees_result = $conn->query($active_employees_query);
$active_employees = $active_employees_result ? $active_employees_result->fetch_assoc()['count'] : 0;

// Employees on leave today
$on_leave_query = "SELECT COUNT(DISTINCT employee_id) as count FROM leave_requests WHERE '$today' BETWEEN start_date AND end_date AND status = 'approved'";
$on_leave_result = $conn->query($on_leave_query);
$on_leave_today = $on_leave_result ? $on_leave_result->fetch_assoc()['count'] : 0;

// Productivity score (average work duration vs expected hours)
$expected_hours_per_day = 8;
$avg_hours_query = "SELECT AVG(total_hours) as avg_hours FROM hr_attendance WHERE date = '$today' AND total_hours > 0";
$avg_hours_result = $conn->query($avg_hours_query);
$avg_hours = $avg_hours_result ? $avg_hours_result->fetch_assoc()['avg_hours'] : 0;
$productivity_score = $avg_hours > 0 ? round(($avg_hours / $expected_hours_per_day) * 100) : 0;
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-clock-history text-primary me-3"></i>Time Tracking
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Track work hours, projects, and productivity across your organization</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportTimeData()">
                    <i class="bi bi-download me-2"></i>Export Data
                </button>
                <button class="btn btn-primary" onclick="showNewProjectModal()">
                    <i class="bi bi-plus-lg me-2"></i>New Project
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-clock text-primary fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Total Hours Today</h6>
                                <h3 class="mb-0"><?php echo $total_hours_today; ?></h3>
                                <small class="text-success">Real-time tracking</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Active Projects</h6>
                                <h3 class="mb-0"><?php echo $active_projects; ?></h3>
                                <small class="text-success">Currently running</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-people text-warning fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Active Employees</h6>
                                <h3 class="mb-0"><?php echo $active_employees; ?></h3>
                                <small class="text-warning"><?php echo $on_leave_today; ?> on leave today</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-graph-up text-info fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-muted small">Productivity Score</h6>
                                <h3 class="mb-0"><?php echo $productivity_score; ?>%</h3>
                                <small class="text-success">Today's average</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Timer Section -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
                    <div class="card-header text-white border-0">
                        <h5 class="mb-0 text-center">Current Session</h5>
                    </div>
                    <div class="card-body text-white text-center">
                        <div class="mb-3" style="font-size: 3rem; font-weight: bold; font-family: 'Courier New', monospace;" id="timerDisplay">00:00:00</div>
                        <div class="mb-3">
                            <small>Working on: <strong>Website Development</strong></small>
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-light" id="startBtn" onclick="startTimer()">
                                <i class="bi bi-play-fill me-1"></i>Start
                            </button>
                            <button class="btn btn-outline-light" id="pauseBtn" onclick="pauseTimer()" disabled>
                                <i class="bi bi-pause-fill me-1"></i>Pause
                            </button>
                            <button class="btn btn-outline-light" id="stopBtn" onclick="stopTimer()" disabled>
                                <i class="bi bi-stop-fill me-1"></i>Stop
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Start Form -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Start Timer</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Project</label>
                                <select class="form-select">
                                    <option>Website Development</option>
                                    <option>Mobile App</option>
                                    <option>API Development</option>
                                    <option>HR System</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Task Description</label>
                                <input type="text" class="form-control" placeholder="What are you working on?">
                            </div>
                            <button type="button" class="btn btn-primary w-100">
                                <i class="bi bi-play me-2"></i>Start Tracking
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Time Entries Table -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Time Entries</h5>
                        <div>
                            <select class="form-select form-select-sm">
                                <option>Today</option>
                                <option>This Week</option>
                                <option>This Month</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Date</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($time_entries as $entry): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                                    <?= strtoupper(substr($entry['employee_name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($entry['employee_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($entry['project']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($entry['task']) ?></td>
                                        <td><?= date('M j, Y', strtotime($entry['date'])) ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= $entry['hours'] ?>h</span>
                                            <small class="text-muted d-block"><?= $entry['start_time'] ?> - <?= $entry['end_time'] ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = $entry['status'] === 'completed' ? 'bg-success' : 'bg-warning';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($entry['status']) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editTimeEntry(<?= $entry['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTimeEntry(<?= $entry['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let timerInterval;
        let seconds = 0;
        let isRunning = false;

        function updateDisplay() {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            document.getElementById('timerDisplay').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function startTimer() {
            if (!isRunning) {
                isRunning = true;
                timerInterval = setInterval(() => {
                    seconds++;
                    updateDisplay();
                }, 1000);
                
                document.getElementById('startBtn').disabled = true;
                document.getElementById('pauseBtn').disabled = false;
                document.getElementById('stopBtn').disabled = false;
                
                showAlert('Timer started!', 'success');
            }
        }

        function pauseTimer() {
            if (isRunning) {
                isRunning = false;
                clearInterval(timerInterval);
                
                document.getElementById('startBtn').disabled = false;
                document.getElementById('pauseBtn').disabled = true;
                
                showAlert('Timer paused', 'warning');
            }
        }

        function stopTimer() {
            isRunning = false;
            clearInterval(timerInterval);
            
            // Save time entry here
            const timeWorked = formatTime(seconds);
            showAlert(`Time entry saved: ${timeWorked}`, 'success');
            
            // Reset timer
            seconds = 0;
            updateDisplay();
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('pauseBtn').disabled = true;
            document.getElementById('stopBtn').disabled = true;
        }

        function formatTime(totalSeconds) {
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            return `${hours}h ${minutes}m`;
        }

        function editTimeEntry(id) {
            showAlert('Edit time entry functionality will be implemented soon!', 'info');
        }

        function deleteTimeEntry(id) {
            if (confirm('Are you sure you want to delete this time entry?')) {
                showAlert('Time entry deleted successfully!', 'success');
                // Implement delete functionality here
            }
        }

        function exportTimeData() {
            showAlert('Exporting time tracking data...', 'info');
            // Implement export functionality here
        }

        function showNewProjectModal() {
            showAlert('New project modal will be implemented soon!', 'info');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }

        // Initialize
        updateDisplay();
    </script>
</div>

<?php if (!isset($root_path)) 
require_once 'hrms_footer_simple.php'; 
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

require_once 'hrms_footer_simple.php';
?>