<?php
// maintenance_schedule.php - Asset Maintenance Scheduling System
// Created: 2025 - Complete HRMS Asset Maintenance Management
// Integrated with asset_allocation.php and asset_management systems

session_start();

// Authentication check
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Maintenance Schedule';

// Process AJAX requests
if ($_POST['action'] ?? '' !== '') {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_schedules':
                $query = "
                    SELECT 
                        ms.id,
                        ms.asset_id,
                        am.asset_name,
                        am.asset_tag,
                        am.category,
                        ms.maintenance_type,
                        ms.scheduled_date,
                        ms.due_date,
                        ms.status,
                        ms.priority,
                        ms.description,
                        ms.assigned_technician,
                        ms.estimated_duration,
                        ms.estimated_cost,
                        ms.last_service_date,
                        ms.created_at,
                        CASE 
                            WHEN ms.due_date < CURDATE() THEN 'overdue'
                            WHEN ms.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'due_soon'
                            ELSE 'scheduled'
                        END as urgency_status,
                        DATEDIFF(ms.due_date, CURDATE()) as days_until_due
                    FROM maintenance_schedules ms
                    LEFT JOIN asset_management am ON ms.asset_id = am.id
                    ORDER BY 
                        CASE ms.priority 
                            WHEN 'Critical' THEN 1 
                            WHEN 'High' THEN 2 
                            WHEN 'Medium' THEN 3 
                            ELSE 4 
                        END,
                        ms.due_date ASC
                ";
                
                $result = mysqli_query($conn, $query);
                $schedules = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $schedules[] = $row;
                }
                
                echo json_encode(['success' => true, 'schedules' => $schedules]);
                exit;
                
            case 'create_schedule':
                $asset_id = (int)$_POST['asset_id'];
                $maintenance_type = mysqli_real_escape_string($conn, $_POST['maintenance_type']);
                $scheduled_date = mysqli_real_escape_string($conn, $_POST['scheduled_date']);
                $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
                $priority = mysqli_real_escape_string($conn, $_POST['priority']);
                $description = mysqli_real_escape_string($conn, $_POST['description']);
                $assigned_technician = mysqli_real_escape_string($conn, $_POST['assigned_technician']);
                $estimated_duration = mysqli_real_escape_string($conn, $_POST['estimated_duration']);
                $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
                
                $insert_query = "
                    INSERT INTO maintenance_schedules (
                        asset_id, maintenance_type, scheduled_date, due_date, 
                        priority, description, assigned_technician, 
                        estimated_duration, estimated_cost, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, NOW())
                ";
                
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, 'isssssssdi', 
                    $asset_id, $maintenance_type, $scheduled_date, $due_date,
                    $priority, $description, $assigned_technician,
                    $estimated_duration, $estimated_cost, $_SESSION['admin']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $schedule_id = mysqli_insert_id($conn);
                    
                    // Log activity
                    $log_query = "INSERT INTO maintenance_logs (schedule_id, action, details, created_by, created_at) 
                                 VALUES (?, 'created', 'Maintenance schedule created', ?, NOW())";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    mysqli_stmt_bind_param($log_stmt, 'ii', $schedule_id, $_SESSION['admin']);
                    mysqli_stmt_execute($log_stmt);
                    
                    echo json_encode(['success' => true, 'message' => 'Maintenance schedule created successfully', 'id' => $schedule_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error creating schedule: ' . mysqli_error($conn)]);
                }
                exit;
                
            case 'update_schedule':
                $id = (int)$_POST['id'];
                $maintenance_type = mysqli_real_escape_string($conn, $_POST['maintenance_type']);
                $scheduled_date = mysqli_real_escape_string($conn, $_POST['scheduled_date']);
                $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
                $priority = mysqli_real_escape_string($conn, $_POST['priority']);
                $description = mysqli_real_escape_string($conn, $_POST['description']);
                $assigned_technician = mysqli_real_escape_string($conn, $_POST['assigned_technician']);
                $estimated_duration = mysqli_real_escape_string($conn, $_POST['estimated_duration']);
                $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                
                $update_query = "
                    UPDATE maintenance_schedules SET
                        maintenance_type = ?, scheduled_date = ?, due_date = ?,
                        priority = ?, description = ?, assigned_technician = ?,
                        estimated_duration = ?, estimated_cost = ?, status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'sssssssdsi',
                    $maintenance_type, $scheduled_date, $due_date,
                    $priority, $description, $assigned_technician,
                    $estimated_duration, $estimated_cost, $status, $id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log activity
                    $log_query = "INSERT INTO maintenance_logs (schedule_id, action, details, created_by, created_at) 
                                 VALUES (?, 'updated', 'Schedule updated', ?, NOW())";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    mysqli_stmt_bind_param($log_stmt, 'ii', $id, $_SESSION['admin']);
                    mysqli_stmt_execute($log_stmt);
                    
                    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating schedule: ' . mysqli_error($conn)]);
                }
                exit;
                
            case 'delete_schedule':
                $id = (int)$_POST['id'];
                
                // Delete related logs first
                $delete_logs = "DELETE FROM maintenance_logs WHERE schedule_id = ?";
                $stmt = mysqli_prepare($conn, $delete_logs);
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                
                // Delete schedule
                $delete_query = "DELETE FROM maintenance_schedules WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, 'i', $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting schedule: ' . mysqli_error($conn)]);
                }
                exit;
                
            case 'complete_maintenance':
                $id = (int)$_POST['id'];
                $completion_notes = mysqli_real_escape_string($conn, $_POST['completion_notes']);
                $actual_cost = (float)($_POST['actual_cost'] ?? 0);
                $parts_used = mysqli_real_escape_string($conn, $_POST['parts_used']);
                
                mysqli_autocommit($conn, false);
                
                try {
                    // Update schedule status
                    $update_query = "
                        UPDATE maintenance_schedules SET
                            status = 'Completed',
                            completed_date = NOW(),
                            completion_notes = ?,
                            actual_cost = ?,
                            parts_used = ?,
                            last_service_date = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ";
                    
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, 'sdsi', $completion_notes, $actual_cost, $parts_used, $id);
                    mysqli_stmt_execute($stmt);
                    
                    // Get asset_id for updating asset status
                    $asset_query = "SELECT asset_id FROM maintenance_schedules WHERE id = ?";
                    $asset_stmt = mysqli_prepare($conn, $asset_query);
                    mysqli_stmt_bind_param($asset_stmt, 'i', $id);
                    mysqli_stmt_execute($asset_stmt);
                    $asset_result = mysqli_stmt_get_result($asset_stmt);
                    $asset_row = mysqli_fetch_assoc($asset_result);
                    
                    // Update asset last maintenance date
                    $asset_update = "UPDATE asset_management SET last_maintenance_date = NOW() WHERE id = ?";
                    $asset_stmt = mysqli_prepare($conn, $asset_update);
                    mysqli_stmt_bind_param($asset_stmt, 'i', $asset_row['asset_id']);
                    mysqli_stmt_execute($asset_stmt);
                    
                    // Log completion
                    $log_query = "INSERT INTO maintenance_logs (schedule_id, action, details, created_by, created_at) 
                                 VALUES (?, 'completed', ?, ?, NOW())";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    $log_details = 'Maintenance completed. Cost: $' . number_format($actual_cost, 2);
                    mysqli_stmt_bind_param($log_stmt, 'isi', $id, $log_details, $_SESSION['admin']);
                    mysqli_stmt_execute($log_stmt);
                    
                    mysqli_commit($conn);
                    echo json_encode(['success' => true, 'message' => 'Maintenance completed successfully']);
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    echo json_encode(['success' => false, 'message' => 'Error completing maintenance: ' . $e->getMessage()]);
                }
                
                mysqli_autocommit($conn, true);
                exit;
                
            case 'get_assets':
                $query = "SELECT id, asset_name, asset_tag, category, location, status FROM asset_management WHERE status != 'Disposed' ORDER BY asset_name";
                $result = mysqli_query($conn, $query);
                $assets = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $assets[] = $row;
                }
                echo json_encode(['success' => true, 'assets' => $assets]);
                exit;
                
            case 'get_technicians':
                $query = "SELECT DISTINCT assigned_technician FROM maintenance_schedules WHERE assigned_technician IS NOT NULL AND assigned_technician != ''";
                $result = mysqli_query($conn, $query);
                $technicians = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $technicians[] = $row['assigned_technician'];
                }
                echo json_encode(['success' => true, 'technicians' => $technicians]);
                exit;
                
            case 'get_dashboard_stats':
                $stats = [];
                
                // Total schedules
                $total_query = "SELECT COUNT(*) as total FROM maintenance_schedules";
                $result = mysqli_query($conn, $total_query);
                $stats['total_schedules'] = mysqli_fetch_assoc($result)['total'];
                
                // Overdue
                $overdue_query = "SELECT COUNT(*) as overdue FROM maintenance_schedules WHERE due_date < CURDATE() AND status != 'Completed'";
                $result = mysqli_query($conn, $overdue_query);
                $stats['overdue'] = mysqli_fetch_assoc($result)['overdue'];
                
                // Due this week
                $due_week_query = "SELECT COUNT(*) as due_week FROM maintenance_schedules WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status != 'Completed'";
                $result = mysqli_query($conn, $due_week_query);
                $stats['due_week'] = mysqli_fetch_assoc($result)['due_week'];
                
                // Completed this month
                $completed_month_query = "SELECT COUNT(*) as completed_month FROM maintenance_schedules WHERE status = 'Completed' AND MONTH(completed_date) = MONTH(CURDATE()) AND YEAR(completed_date) = YEAR(CURDATE())";
                $result = mysqli_query($conn, $completed_month_query);
                $stats['completed_month'] = mysqli_fetch_assoc($result)['completed_month'];
                
                // Total cost this month
                $cost_month_query = "SELECT SUM(actual_cost) as total_cost FROM maintenance_schedules WHERE status = 'Completed' AND MONTH(completed_date) = MONTH(CURDATE()) AND YEAR(completed_date) = YEAR(CURDATE())";
                $result = mysqli_query($conn, $cost_month_query);
                $stats['total_cost_month'] = mysqli_fetch_assoc($result)['total_cost'] ?? 0;
                
                echo json_encode(['success' => true, 'stats' => $stats]);
                exit;
                
            case 'get_maintenance_history':
                $asset_id = (int)$_POST['asset_id'];
                
                $query = "
                    SELECT 
                        ms.id,
                        ms.maintenance_type,
                        ms.scheduled_date,
                        ms.completed_date,
                        ms.status,
                        ms.priority,
                        ms.assigned_technician,
                        ms.estimated_cost,
                        ms.actual_cost,
                        ms.completion_notes,
                        ms.parts_used
                    FROM maintenance_schedules ms
                    WHERE ms.asset_id = ?
                    ORDER BY ms.scheduled_date DESC
                ";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'i', $asset_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                $history = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $history[] = $row;
                }
                
                echo json_encode(['success' => true, 'history' => $history]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Create tables if they don't exist
$create_maintenance_schedules = "
CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_type VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Overdue') DEFAULT 'Scheduled',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    description TEXT,
    assigned_technician VARCHAR(100),
    estimated_duration VARCHAR(50),
    estimated_cost DECIMAL(10,2) DEFAULT 0.00,
    actual_cost DECIMAL(10,2) DEFAULT 0.00,
    completion_notes TEXT,
    parts_used TEXT,
    completed_date DATETIME NULL,
    last_service_date DATE NULL,
    next_service_date DATE NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asset_id (asset_id),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    FOREIGN KEY (asset_id) REFERENCES asset_management(id) ON DELETE CASCADE
)
";

$create_maintenance_logs = "
CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_schedule_id (schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES maintenance_schedules(id) ON DELETE CASCADE
)
";

mysqli_query($conn, $create_maintenance_schedules);
mysqli_query($conn, $create_maintenance_logs);

// Add maintenance date column to asset_management if it doesn't exist
$add_maintenance_column = "
ALTER TABLE asset_management 
ADD COLUMN IF NOT EXISTS last_maintenance_date DATE NULL,
ADD COLUMN IF NOT EXISTS next_maintenance_due DATE NULL
";
mysqli_query($conn, $add_maintenance_column);

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<style>
.badge-overdue { 
    background: #dc3545 !important; 
    color: white !important;
}
.badge-due-soon { 
    background: #ffc107 !important; 
    color: #212529 !important;
}
.badge-scheduled { 
    background: #007bff !important; 
    color: white !important;
}
.badge-completed { 
    background: #28a745 !important; 
    color: white !important;
}
.badge-in-progress { 
    background: #fd7e14 !important; 
    color: white !important;
}
.badge-cancelled { 
    background: #6c757d !important; 
    color: white !important;
}

.priority-critical { color: #dc3545; font-weight: bold; }
.priority-high { color: #fd7e14; font-weight: bold; }
.priority-medium { color: #ffc107; font-weight: bold; }
.priority-low { color: #28a745; font-weight: bold; }

.action-buttons .btn {
    margin: 0 2px;
    border-radius: 4px;
}

.maintenance-history-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border-left: 4px solid #007bff;
}

.card {
    box-shadow: 0 0 10px rgba(0,0,0,.1);
    border: 0;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.badge {
    font-size: 0.75em;
}
</style>
<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🔧 Maintenance Schedule</h1>
                <p class="text-muted">Asset Maintenance Planning & Tracking System</p>
            </div>
            <button class="btn btn-primary" onclick="showAddScheduleModal()">
                <i class="fas fa-plus me-2"></i>Schedule Maintenance
            </button>
        </div>
        <!-- Dashboard Stats -->
        <div class="row mb-4" id="statsCards">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalSchedules">0</h3>
                        <p class="mb-0">Total Schedules</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="overdueCount">0</h3>
                        <p class="mb-0">Overdue</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h3 class="mb-1" id="dueWeekCount">0</h3>
                        <p class="mb-0">Due This Week</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="completedMonth">0</h3>
                        <p class="mb-0">Completed This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filter Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Overdue">Overdue</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority Filter</label>
                                <select class="form-select" id="priorityFilter">
                                    <option value="">All Priorities</option>
                                    <option value="Critical">Critical</option>
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <select class="form-select" id="dateFilter">
                                    <option value="">All Dates</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="next_month">Next Month</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" onclick="applyFilters()">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Schedule Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Maintenance Schedules</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="maintenanceTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Asset</th>
                                <th>Type</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Technician</th>
                                <th>Est. Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTableBody">
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Loading Spinner -->
                <div class="loading-spinner text-center" id="loadingSpinner" style="display: none;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading maintenance schedules...</p>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Add/Edit Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalTitle">Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm">
                        <input type="hidden" id="scheduleId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Asset <span class="text-danger">*</span></label>
                                <select class="form-select" id="assetId" required>
                                    <option value="">Select Asset</option>
                                    <!-- Options loaded via AJAX -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="maintenanceType" required>
                                    <option value="">Select Type</option>
                                    <option value="Preventive">Preventive Maintenance</option>
                                    <option value="Corrective">Corrective Maintenance</option>
                                    <option value="Predictive">Predictive Maintenance</option>
                                    <option value="Emergency">Emergency Repair</option>
                                    <option value="Inspection">Safety Inspection</option>
                                    <option value="Calibration">Equipment Calibration</option>
                                    <option value="Cleaning">Deep Cleaning</option>
                                    <option value="Software Update">Software Update</option>
                                    <option value="Hardware Upgrade">Hardware Upgrade</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="scheduledDate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dueDate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority <span class="text-danger">*</span></label>
                                <select class="form-select" id="priority" required>
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Assigned Technician</label>
                                <input type="text" class="form-control" id="assignedTechnician" list="techniciansList">
                                <datalist id="techniciansList">
                                    <!-- Options loaded via AJAX -->
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Duration</label>
                                <input type="text" class="form-control" id="estimatedDuration" placeholder="e.g., 2 hours, 1 day">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Cost</label>
                                <input type="number" class="form-control" id="estimatedCost" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-12" id="statusDiv" style="display: none;">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="status">
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="description" rows="3" placeholder="Describe the maintenance work to be performed..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSchedule()">
                        <i class="fas fa-save me-2"></i>Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Maintenance Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="completeForm">
                        <input type="hidden" id="completeScheduleId">
                        <div class="mb-3">
                            <label class="form-label">Actual Cost</label>
                            <input type="number" class="form-control" id="actualCost" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parts Used</label>
                            <textarea class="form-control" id="partsUsed" rows="2" placeholder="List any parts or materials used..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Completion Notes <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="completionNotes" rows="3" placeholder="Describe the work completed..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="completeMaintenance()">
                        <i class="fas fa-check me-2"></i>Mark Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent">
                        <!-- History will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let dataTable;
        
        $(document).ready(function() {
            // Initialize DataTable
            dataTable = $('#maintenanceTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[2, 'asc']], // Order by due date
                language: {
                    search: "Search schedules:",
                    lengthMenu: "Show _MENU_ schedules per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ schedules",
                    emptyTable: "No maintenance schedules found"
                }
            });
            
            // Load initial data
            loadDashboardStats();
            loadMaintenanceSchedules();
            loadAssets();
            loadTechnicians();
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            $('#scheduledDate').val(today);
            $('#dueDate').val(nextWeek);
        });

        function loadDashboardStats() {
            $.post('', {action: 'get_dashboard_stats'}, function(response) {
                if (response.success) {
                    const stats = response.stats;
                    $('#totalSchedules').text(stats.total_schedules);
                    $('#overdueCount').text(stats.overdue);
                    $('#dueWeekCount').text(stats.due_week);
                    $('#completedMonth').text(stats.completed_month);
                    
                    // Animate counters
                    animateCounter('#totalSchedules', stats.total_schedules);
                    animateCounter('#overdueCount', stats.overdue);
                    animateCounter('#dueWeekCount', stats.due_week);
                    animateCounter('#completedMonth', stats.completed_month);
                }
            }, 'json');
        }

        function animateCounter(selector, endValue) {
            let startValue = 0;
            const duration = 1000;
            const stepTime = Math.abs(Math.floor(duration / endValue));
            
            const timer = setInterval(function() {
                startValue++;
                $(selector).text(startValue);
                if (startValue >= endValue) {
                    clearInterval(timer);
                }
            }, stepTime);
        }

        function loadMaintenanceSchedules() {
            $('#loadingSpinner').show();
            
            $.post('', {action: 'get_schedules'}, function(response) {
                $('#loadingSpinner').hide();
                
                if (response.success) {
                    dataTable.clear();
                    
                    response.schedules.forEach(function(schedule) {
                        const urgencyClass = schedule.urgency_status;
                        const priorityClass = 'priority-' + schedule.priority.toLowerCase();
                        
                        let statusBadge = `<span class="badge badge-${schedule.status.toLowerCase().replace(' ', '-')}">${schedule.status}</span>`;
                        
                        if (schedule.urgency_status === 'overdue' && schedule.status !== 'Completed') {
                            statusBadge = '<span class="badge badge-overdue">Overdue</span>';
                        } else if (schedule.urgency_status === 'due_soon' && schedule.status !== 'Completed') {
                            statusBadge += ' <small class="text-warning">Due Soon</small>';
                        }
                        
                        let dueDateText = schedule.due_date;
                        if (schedule.days_until_due !== null) {
                            if (schedule.days_until_due < 0) {
                                dueDateText += ` <small class="text-danger">(${Math.abs(schedule.days_until_due)} days overdue)</small>`;
                            } else if (schedule.days_until_due <= 7) {
                                dueDateText += ` <small class="text-warning">(${schedule.days_until_due} days left)</small>`;
                            }
                        }
                        
                        const actions = `
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editSchedule(${schedule.id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                ${schedule.status !== 'Completed' ? `
                                    <button class="btn btn-sm btn-success" onclick="showCompleteModal(${schedule.id})" title="Complete">
                                        <i class="fas fa-check"></i>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-info" onclick="viewHistory(${schedule.asset_id})" title="History">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(${schedule.id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        
                        dataTable.row.add([
                            `<strong>${schedule.asset_name}</strong><br><small class="text-muted">${schedule.asset_tag} - ${schedule.category}</small>`,
                            schedule.maintenance_type,
                            dueDateText,
                            statusBadge,
                            `<span class="${priorityClass}">${schedule.priority}</span>`,
                            schedule.assigned_technician || '<em class="text-muted">Unassigned</em>',
                            schedule.estimated_cost > 0 ? '$' + parseFloat(schedule.estimated_cost).toFixed(2) : '-',
                            actions
                        ]).draw();
                    });
                } else {
                    showAlert('Error loading schedules: ' + response.message, 'error');
                }
            }, 'json');
        }

        function loadAssets() {
            $.post('', {action: 'get_assets'}, function(response) {
                if (response.success) {
                    const assetSelect = $('#assetId');
                    assetSelect.empty().append('<option value="">Select Asset</option>');
                    
                    response.assets.forEach(function(asset) {
                        assetSelect.append(`<option value="${asset.id}">${asset.asset_name} (${asset.asset_tag}) - ${asset.category}</option>`);
                    });
                }
            }, 'json');
        }

        function loadTechnicians() {
            $.post('', {action: 'get_technicians'}, function(response) {
                if (response.success) {
                    const techniciansList = $('#techniciansList');
                    techniciansList.empty();
                    
                    response.technicians.forEach(function(technician) {
                        techniciansList.append(`<option value="${technician}">`);
                    });
                }
            }, 'json');
        }

        function showAddScheduleModal() {
            $('#scheduleModalTitle').text('Schedule New Maintenance');
            $('#scheduleForm')[0].reset();
            $('#scheduleId').val('');
            $('#statusDiv').hide();
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            $('#scheduledDate').val(today);
            $('#dueDate').val(nextWeek);
            
            $('#scheduleModal').modal('show');
        }

        function editSchedule(id) {
            // Get schedule data from table
            const schedules = dataTable.data().toArray();
            let scheduleData = null;
            
            // Find the schedule data (this is a simplified approach - in production you'd fetch from server)
            $.post('', {action: 'get_schedules'}, function(response) {
                if (response.success) {
                    const schedule = response.schedules.find(s => s.id == id);
                    if (schedule) {
                        $('#scheduleModalTitle').text('Edit Maintenance Schedule');
                        $('#scheduleId').val(schedule.id);
                        $('#assetId').val(schedule.asset_id);
                        $('#maintenanceType').val(schedule.maintenance_type);
                        $('#scheduledDate').val(schedule.scheduled_date);
                        $('#dueDate').val(schedule.due_date);
                        $('#priority').val(schedule.priority);
                        $('#description').val(schedule.description);
                        $('#assignedTechnician').val(schedule.assigned_technician);
                        $('#estimatedDuration').val(schedule.estimated_duration);
                        $('#estimatedCost').val(schedule.estimated_cost);
                        $('#status').val(schedule.status);
                        $('#statusDiv').show();
                        
                        $('#scheduleModal').modal('show');
                    }
                }
            }, 'json');
        }

        function saveSchedule() {
            const formData = {
                action: $('#scheduleId').val() ? 'update_schedule' : 'create_schedule',
                id: $('#scheduleId').val(),
                asset_id: $('#assetId').val(),
                maintenance_type: $('#maintenanceType').val(),
                scheduled_date: $('#scheduledDate').val(),
                due_date: $('#dueDate').val(),
                priority: $('#priority').val(),
                description: $('#description').val(),
                assigned_technician: $('#assignedTechnician').val(),
                estimated_duration: $('#estimatedDuration').val(),
                estimated_cost: $('#estimatedCost').val(),
                status: $('#status').val() || 'Scheduled'
            };
            
            // Validation
            if (!formData.asset_id || !formData.maintenance_type || !formData.scheduled_date || !formData.due_date) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }
            
            if (new Date(formData.due_date) < new Date(formData.scheduled_date)) {
                showAlert('Due date cannot be earlier than scheduled date', 'error');
                return;
            }
            
            $.post('', formData, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#scheduleModal').modal('hide');
                    loadMaintenanceSchedules();
                    loadDashboardStats();
                } else {
                    showAlert('Error: ' + response.message, 'error');
                }
            }, 'json');
        }

        function deleteSchedule(id) {
            Swal.fire({
                title: 'Delete Maintenance Schedule?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        action: 'delete_schedule',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            showAlert(response.message, 'success');
                            loadMaintenanceSchedules();
                            loadDashboardStats();
                        } else {
                            showAlert('Error: ' + response.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        function showCompleteModal(id) {
            $('#completeScheduleId').val(id);
            $('#completeForm')[0].reset();
            $('#completeModal').modal('show');
        }

        function completeMaintenance() {
            const formData = {
                action: 'complete_maintenance',
                id: $('#completeScheduleId').val(),
                completion_notes: $('#completionNotes').val(),
                actual_cost: $('#actualCost').val(),
                parts_used: $('#partsUsed').val()
            };
            
            if (!formData.completion_notes.trim()) {
                showAlert('Please provide completion notes', 'error');
                return;
            }
            
            $.post('', formData, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#completeModal').modal('hide');
                    loadMaintenanceSchedules();
                    loadDashboardStats();
                } else {
                    showAlert('Error: ' + response.message, 'error');
                }
            }, 'json');
        }

        function viewHistory(assetId) {
            $.post('', {
                action: 'get_maintenance_history',
                asset_id: assetId
            }, function(response) {
                if (response.success) {
                    let historyHtml = '';
                    
                    if (response.history.length === 0) {
                        historyHtml = '<p class="text-muted">No maintenance history found for this asset.</p>';
                    } else {
                        response.history.forEach(function(record) {
                            const completedDate = record.completed_date ? new Date(record.completed_date).toLocaleDateString() : 'Not completed';
                            const costInfo = record.actual_cost > 0 ? `$${parseFloat(record.actual_cost).toFixed(2)}` : 
                                           (record.estimated_cost > 0 ? `Est. $${parseFloat(record.estimated_cost).toFixed(2)}` : 'N/A');
                            
                            historyHtml += `
                                <div class="maintenance-history-card">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">${record.maintenance_type}</h6>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-calendar me-1"></i> Scheduled: ${new Date(record.scheduled_date).toLocaleDateString()}
                                                ${record.completed_date ? ` | Completed: ${completedDate}` : ''}
                                            </p>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-user me-1"></i> Technician: ${record.assigned_technician || 'Unassigned'}
                                            </p>
                                            ${record.completion_notes ? `<p class="mb-1"><strong>Notes:</strong> ${record.completion_notes}</p>` : ''}
                                            ${record.parts_used ? `<p class="mb-1"><strong>Parts Used:</strong> ${record.parts_used}</p>` : ''}
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge badge-${record.status.toLowerCase().replace(' ', '-')} mb-2">${record.status}</span><br>
                                            <span class="badge bg-secondary">${record.priority}</span><br>
                                            <strong class="text-success">${costInfo}</strong>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    $('#historyContent').html(historyHtml);
                    $('#historyModal').modal('show');
                } else {
                    showAlert('Error loading history: ' + response.message, 'error');
                }
            }, 'json');
        }

        function applyFilters() {
            const statusFilter = $('#statusFilter').val();
            const priorityFilter = $('#priorityFilter').val();
            const dateFilter = $('#dateFilter').val();
            
            // Reset search
            dataTable.search('').columns().search('');
            
            // Apply status filter
            if (statusFilter) {
                dataTable.column(3).search(statusFilter);
            }
            
            // Apply priority filter
            if (priorityFilter) {
                dataTable.column(4).search(priorityFilter);
            }
            
            // Apply date filter (this would need server-side implementation for complex date filtering)
            if (dateFilter) {
                switch (dateFilter) {
                    case 'overdue':
                        dataTable.column(3).search('Overdue');
                        break;
                    case 'today':
                        const today = new Date().toISOString().split('T')[0];
                        dataTable.column(2).search(today);
                        break;
                    // Add more date filter cases as needed
                }
            }
            
            dataTable.draw();
        }

        function showAlert(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            loadDashboardStats();
            loadMaintenanceSchedules();
        }, 300000);
    </script>

<?php include '../layouts/footer.php'; ?>
