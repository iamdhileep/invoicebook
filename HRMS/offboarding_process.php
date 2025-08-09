<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

// Database connection
$base_dir = dirname(__DIR__);
require_once $base_dir . '/db.php';

// Set page title for header
$page_title = 'Employee Offboarding Process';

// Set user session variables
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['admin']['id'] ?? 1;
$current_user = $_SESSION['user']['name'] ?? $_SESSION['admin']['name'] ?? 'Admin';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            
            case 'initiate_offboarding':
                $employee_id = intval($_POST['employee_id']);
                $resignation_date = $_POST['resignation_date'];
                $notice_period = intval($_POST['notice_period_days'] ?? 30);
                $reason = trim($_POST['reason']);
                
                if (!$employee_id || !$reason) {
                    throw new Exception('Employee ID and reason are required');
                }
                
                // Check if employee already has active offboarding
                $check = $conn->prepare("SELECT id FROM fnf_settlements WHERE employee_id = ? AND status NOT IN ('completed', 'paid')");
                $check->bind_param('i', $employee_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    throw new Exception('Employee already has an active offboarding process');
                }
                
                // Get employee details
                $emp_stmt = $conn->prepare("SELECT name as full_name, employee_code, department_name, monthly_salary as salary FROM employees WHERE employee_id = ? AND status = 'active'");
                $emp_stmt->bind_param('i', $employee_id);
                $emp_stmt->execute();
                $employee = $emp_stmt->get_result()->fetch_assoc();
                
                if (!$employee) {
                    throw new Exception('Employee not found');
                }
                
                // Calculate last working day
                $resign_date = new DateTime($resignation_date);
                $last_working = clone $resign_date;
                $last_working->add(new DateInterval("P{$notice_period}D"));
                
                // Insert offboarding record
                $stmt = $conn->prepare("
                    INSERT INTO fnf_settlements (
                        employee_id, employee_name, employee_code, department_name,
                        resignation_date, last_working_day, notice_period_days, basic_salary, 
                        status, remarks, initiated_by, initiated_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                ");
                $stmt->bind_param('isssssidsi', 
                    $employee_id,
                    $employee['full_name'],
                    $employee['employee_code'],
                    $employee['department_name'],
                    $resignation_date,
                    $last_working->format('Y-m-d'),
                    $notice_period,
                    $employee['salary'],
                    $reason,
                    $current_user_id
                );                if ($stmt->execute()) {
                    $settlement_id = $conn->insert_id;
                    
                    // Add default clearance steps
                    $default_steps = [
                        'Return IT equipment and access cards',
                        'Clear personal belongings from workspace',
                        'Complete knowledge transfer documentation',
                        'Return company documents and files',
                        'Clear pending expenses and invoices',
                        'Complete exit interview',
                        'Final HR documentation and approval'
                    ];
                    
                    $step_stmt = $conn->prepare("INSERT INTO clearance_steps (employee_id, step_name, department, status) VALUES (?, ?, 'HR', 'pending')");
                    foreach ($default_steps as $step) {
                        $step_stmt->bind_param('is', $employee_id, $step);
                        $step_stmt->execute();
                    }
                    
                    $response = ['success' => true, 'message' => 'Offboarding process initiated successfully'];
                }
                break;
                
            case 'update_status':
                $settlement_id = intval($_POST['settlement_id']);
                $status = $_POST['status'];
                $remarks = $_POST['remarks'] ?? '';
                
                $stmt = $conn->prepare("UPDATE fnf_settlements SET status = ?, remarks = CONCAT(IFNULL(remarks, ''), '\n', ?) WHERE id = ?");
                $stmt->bind_param('ssi', $status, $remarks, $settlement_id);
                
                if ($stmt->execute()) {
                    if ($status === 'completed') {
                        $conn->query("UPDATE fnf_settlements SET completed_date = NOW() WHERE id = $settlement_id");
                    }
                    $response = ['success' => true, 'message' => 'Status updated successfully'];
                }
                break;
                
            case 'schedule_interview':
                $employee_id = intval($_POST['employee_id'] ?? $_POST['settlement_id']);
                $interview_date = $_POST['interview_date'];
                $interviewer_name = $_POST['interviewer_name'];
                
                $stmt = $conn->prepare("
                    INSERT INTO exit_interviews (employee_id, interview_date, interviewer_name, interview_status) 
                    VALUES (?, ?, ?, 'scheduled')
                    ON DUPLICATE KEY UPDATE 
                    interview_date = VALUES(interview_date),
                    interviewer_name = VALUES(interviewer_name),
                    interview_status = 'scheduled'
                ");
                $stmt->bind_param('iss', $employee_id, $interview_date, $interviewer_name);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Exit interview scheduled successfully'];
                }
                break;
                
            case 'complete_interview':
                $interview_id = intval($_POST['interview_id']);
                $feedback = $_POST['feedback'];
                $rating = intval($_POST['rating']);
                $recommendations = $_POST['recommendations'] ?? '';
                
                $stmt = $conn->prepare("
                    UPDATE exit_interviews 
                    SET feedback_comments = ?, overall_rating = ?, suggestions_improvement = ?, interview_status = 'completed'
                    WHERE id = ?
                ");
                $stmt->bind_param('sdsi', $feedback, $rating, $recommendations, $interview_id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Exit interview completed successfully'];
                }
                break;
                
            case 'update_clearance_step':
                $step_id = intval($_POST['step_id']);
                $status = $_POST['status'];
                $remarks = $_POST['remarks'] ?? '';
                
                $completed_at = ($status === 'completed') ? ', completed_at = NOW()' : '';
                
                $stmt = $conn->prepare("
                    UPDATE clearance_steps 
                    SET status = ?, notes = ?, completed_by = ? $completed_at
                    WHERE id = ?
                ");
                $stmt->bind_param('ssii', $status, $remarks, $current_user_id, $step_id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Clearance step updated successfully'];
                }
                break;
                
            case 'get_offboarding_details':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("
                    SELECT f.*, e.name as employee_name, e.employee_code, e.department_name,
                           ei.id as interview_id, ei.interview_date, ei.interviewer_name, 
                           ei.interview_status, ei.feedback_comments, ei.overall_rating, ei.suggestions_improvement
                    FROM fnf_settlements f 
                    LEFT JOIN employees e ON f.employee_id = e.employee_id 
                    LEFT JOIN exit_interviews ei ON f.employee_id = ei.employee_id
                    WHERE f.id = ?
                ");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                
                if ($data) {
                    // Get clearance steps
                    $steps_stmt = $conn->prepare("SELECT * FROM clearance_steps WHERE employee_id = ? ORDER BY id");
                    $steps_stmt->bind_param('i', $data['employee_id']);
                    $steps_stmt->execute();
                    $data['clearance_steps'] = $steps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    $response = ['success' => true, 'data' => $data];
                } else {
                    throw new Exception('Record not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Fetch data for display
$offboarding_records = [];
$employees = [];
$statistics = ['total' => 0, 'initiated' => 0, 'in_progress' => 0, 'completed' => 0];

try {
    // Get offboarding records
    $records_query = "
        SELECT f.*, e.name as employee_name, e.employee_code, e.department_name,
               ei.interview_date, ei.interview_status, ei.interviewer_name
        FROM fnf_settlements f 
        LEFT JOIN employees e ON f.employee_id = e.employee_id 
        LEFT JOIN exit_interviews ei ON f.employee_id = ei.employee_id
        ORDER BY f.initiated_date DESC
        LIMIT 100
    ";
    $records_result = $conn->query($records_query);
    if ($records_result) {
        $offboarding_records = $records_result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get active employees for dropdown
    $employees_query = "SELECT employee_id as id, name as full_name, employee_code, department_name, monthly_salary as salary FROM employees WHERE status = 'active' ORDER BY name";
    $employees_result = $conn->query($employees_query);
    if ($employees_result) {
        $employees = $employees_result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Calculate statistics
    $stats_query = "SELECT status, COUNT(*) as count FROM fnf_settlements GROUP BY status";
    $stats_result = $conn->query($stats_query);
    if ($stats_result) {
        while ($row = $stats_result->fetch_assoc()) {
            $statistics[$row['status']] = $row['count'];
            $statistics['total'] += $row['count'];
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching offboarding data: " . $e->getMessage());
}

// Include global layout files
include $base_dir . '/layouts/header.php';
include $base_dir . '/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">👋 Employee Offboarding Process</h1>
                <p class="text-muted">Manage employee exit procedures and final settlements</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="generateReport()">
                    <i class="bi bi-file-earmark-text"></i> Generate Report
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#initiateModal">
                    <i class="bi bi-person-plus"></i> Start New Process
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-data fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['total'] ?></h3>
                        <p class="mb-0 opacity-90">Total Cases</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-hourglass-split fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['initiated'] + ($statistics['in_progress'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-90">In Progress</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $statistics['completed'] ?></h3>
                        <p class="mb-0 opacity-90">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= count($employees) ?></h3>
                        <p class="mb-0 opacity-90">Active Employees</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="searchInput" 
                                   placeholder="Search by employee name, code, or department..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="initiated">Initiated</option>
                            <option value="in_progress">In Progress</option>
                            <option value="approved">Approved</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary me-2" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-success me-2" onclick="exportData()">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="bi bi-question-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Offboarding Records
                    <span class="badge bg-primary ms-2"><?= count($offboarding_records) ?> records</span>
                </h6>
            </div>
            <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Resignation Date</th>
                            <th class="py-3">Last Working Day</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Interview</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offboarding_records)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Offboarding Records Found</h5>
                                        <p class="text-muted">Click "Start New Process" to initiate an employee offboarding.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($offboarding_records as $record): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="employee-avatar">
                                                <?= strtoupper(substr($record['employee_name'] ?? 'N', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($record['employee_name'] ?? 'Unknown') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($record['employee_code'] ?? 'N/A') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-secondary"><?= htmlspecialchars($record['department_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="py-3">
                                        <?= $record['resignation_date'] ? date('M j, Y', strtotime($record['resignation_date'])) : 'N/A' ?>
                                    </td>
                                    <td class="py-3">
                                        <strong><?= date('M j, Y', strtotime($record['last_working_day'])) ?></strong>
                                    </td>
                                    <td class="py-3">
                                        <span class="status-badge status-<?= $record['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($record['interview_date']): ?>
                                            <div class="small">
                                                <i class="fas fa-calendar-check text-success"></i>
                                                <?= date('M j', strtotime($record['interview_date'])) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($record['interviewer_name'] ?? '') ?></small>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-warning">
                                                <i class="fas fa-calendar-times"></i>
                                                Not Scheduled
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <div class="action-buttons">
                                            <button class="btn btn-outline-primary btn-sm" onclick="viewDetails(<?= $record['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="updateStatus(<?= $record['id'] ?>, '<?= $record['status'] ?>')" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!$record['interview_date'] && $record['status'] !== 'completed'): ?>
                                                <button class="btn btn-outline-success btn-sm" onclick="scheduleInterview(<?= $record['employee_id'] ?>)" title="Schedule Interview">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Processing...</div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>Offboarding Process Help
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#help1">
                                How to initiate offboarding process?
                            </button>
                        </h2>
                        <div id="help1" class="accordion-collapse collapse show">
                            <div class="accordion-body">
                                <ol>
                                    <li>Click "Start New Process" button</li>
                                    <li>Select the employee from dropdown</li>
                                    <li>Enter resignation date and notice period</li>
                                    <li>Provide reason for leaving</li>
                                    <li>Click "Initiate Process"</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help2">
                                Managing clearance steps
                            </button>
                        </h2>
                        <div id="help2" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                <p>Each employee gets an automatic clearance checklist. You can:</p>
                                <ul>
                                    <li>View details by clicking the eye icon</li>
                                    <li>Update clearance step status</li>
                                    <li>Add notes for each step</li>
                                    <li>Mark steps as completed or not applicable</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initiate Offboarding Modal -->
<div class="modal fade" id="initiateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">
                    <i class="fas fa-user-times me-2"></i>Initiate Employee Offboarding
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="initiateForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Choose an employee...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" data-salary="<?= $emp['salary'] ?>">
                                        <?= htmlspecialchars($emp['full_name']) ?> 
                                        (<?= htmlspecialchars($emp['employee_code']) ?>) - 
                                        <?= htmlspecialchars($emp['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Resignation Date *</label>
                            <input type="date" class="form-control" name="resignation_date" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Notice Period (Days)</label>
                            <input type="number" class="form-control" name="notice_period_days" value="30" min="0" max="365">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Calculated Last Working Day</label>
                            <input type="date" class="form-control" name="last_working_day" readonly style="background: #f8f9fc;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Leaving *</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Enter detailed reason for leaving..." required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> A complete offboarding checklist will be automatically created for this employee.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-paper-plane me-2"></i>Initiate Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">Update Offboarding Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="statusForm">
                <input type="hidden" name="settlement_id" id="statusSettlementId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="">Select status...</option>
                            <option value="initiated">Initiated</option>
                            <option value="in_progress">In Progress</option>
                            <option value="approved">Approved</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3" placeholder="Enter any additional comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">Schedule Exit Interview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="interviewForm">
                <input type="hidden" name="employee_id" id="interviewEmployeeId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Interview Date *</label>
                        <input type="datetime-local" class="form-control" name="interview_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interviewer Name *</label>
                        <input type="text" class="form-control" name="interviewer_name" placeholder="Enter interviewer's name" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        The employee will be notified about this interview schedule.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-calendar-plus me-2"></i>Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">Offboarding Process Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Clearance Step Modal -->
<div class="modal fade" id="clearanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">Update Clearance Step</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="clearanceForm">
                <input type="hidden" name="step_id" id="clearanceStepId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Clearance Step</label>
                        <input type="text" class="form-control" id="clearanceStepDesc" readonly style="background: #f8f9fc;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="not_applicable">Not Applicable</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3" placeholder="Enter any remarks or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-check me-2"></i>Update Step
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Utility functions
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// Calculate last working day
function calculateLastWorkingDay() {
    const resignationDate = document.querySelector('[name="resignation_date"]').value;
    const noticePeriod = parseInt(document.querySelector('[name="notice_period_days"]').value) || 30;
    
    if (resignationDate) {
        const resignDate = new Date(resignationDate);
        const lastWorkingDate = new Date(resignDate.getTime() + (noticePeriod * 24 * 60 * 60 * 1000));
        document.querySelector('[name="last_working_day"]').value = lastWorkingDate.toISOString().split('T')[0];
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('[name="resignation_date"]').min = today;
    
    // Date calculation listeners
    document.querySelector('[name="resignation_date"]').addEventListener('change', calculateLastWorkingDay);
    document.querySelector('[name="notice_period_days"]').addEventListener('input', calculateLastWorkingDay);
    
    // Calculate on page load
    calculateLastWorkingDay();
});

// Form submissions
document.getElementById('initiateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm('initiate_offboarding', new FormData(this), 'Offboarding process initiated successfully!', 'initiateModal');
});

document.getElementById('statusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm('update_status', new FormData(this), 'Status updated successfully!', 'statusModal');
});

document.getElementById('interviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm('schedule_interview', new FormData(this), 'Exit interview scheduled successfully!', 'interviewModal');
});

document.getElementById('clearanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm('update_clearance_step', new FormData(this), 'Clearance step updated successfully!', 'clearanceModal');
});

// Generic form submission
function submitForm(action, formData, successMessage, modalId) {
    showLoading();
    formData.append('action', action);
    
    fetch('offboarding_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: successMessage,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error!',
            text: 'Please check your connection and try again'
        });
    });
}

// Action functions
function updateStatus(id, currentStatus) {
    document.getElementById('statusSettlementId').value = id;
    document.querySelector('#statusModal [name="status"]').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function scheduleInterview(employeeId) {
    document.getElementById('interviewEmployeeId').value = employeeId;
    // Set minimum datetime to now
    const now = new Date();
    const minDateTime = now.toISOString().slice(0, 16);
    document.querySelector('#interviewModal [name="interview_date"]').min = minDateTime;
    new bootstrap.Modal(document.getElementById('interviewModal')).show();
}

function viewDetails(id) {
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'get_offboarding_details');
    formData.append('id', id);
    
    fetch('offboarding_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayDetails(data.data);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error!',
            text: 'Failed to load details'
        });
    });
}

function displayDetails(data) {
    let clearanceHtml = '';
    if (data.clearance_steps && data.clearance_steps.length > 0) {
        clearanceHtml = `
            <div class="mt-4">
                <h6 class="mb-3"><i class="fas fa-tasks me-2 text-primary"></i>Clearance Checklist</h6>
                <div class="row">
                    ${data.clearance_steps.map(step => `
                        <div class="col-md-6 mb-3">
                            <div class="clearance-step ${step.status}" onclick="updateClearanceStep(${step.id}, '${step.step_name}', '${step.status}')">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">${step.step_name}</div>
                                        <small class="text-muted">Status: ${step.status.charAt(0).toUpperCase() + step.status.slice(1)}</small>
                                        ${step.notes ? `<br><small class="text-info"><em>${step.notes}</em></small>` : ''}
                                        ${step.completed_at ? `<br><small class="text-success">Completed: ${new Date(step.completed_at).toLocaleDateString()}</small>` : ''}
                                    </div>
                                    <div class="ms-3">
                                        ${step.status === 'completed' ? '<i class="fas fa-check-circle text-success fa-2x"></i>' : 
                                          step.status === 'pending' ? '<i class="fas fa-clock text-warning fa-2x"></i>' : 
                                          '<i class="fas fa-times-circle text-muted fa-2x"></i>'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Employee Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="fw-semibold text-muted">Name:</td><td class="fw-semibold">${data.employee_name || 'N/A'}</td></tr>
                            <tr><td class="fw-semibold text-muted">Code:</td><td>${data.employee_code || 'N/A'}</td></tr>
                            <tr><td class="fw-semibold text-muted">Department:</td><td><span class="badge bg-secondary">${data.department_name || 'N/A'}</span></td></tr>
                            <tr><td class="fw-semibold text-muted">Basic Salary:</td><td class="text-success fw-bold">₹${parseFloat(data.basic_salary || 0).toLocaleString()}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Process Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="fw-semibold text-muted">Status:</td><td><span class="status-badge status-${data.status}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span></td></tr>
                            <tr><td class="fw-semibold text-muted">Resignation Date:</td><td>${data.resignation_date ? new Date(data.resignation_date).toLocaleDateString() : 'N/A'}</td></tr>
                            <tr><td class="fw-semibold text-muted">Last Working Day:</td><td class="text-danger fw-bold">${new Date(data.last_working_day).toLocaleDateString()}</td></tr>
                            <tr><td class="fw-semibold text-muted">Notice Period:</td><td>${data.notice_period_days} days</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        ${data.interview_date ? `
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Exit Interview Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Interview Date:</strong> ${new Date(data.interview_date).toLocaleDateString()}<br>
                                    <strong>Interviewer:</strong> ${data.interviewer_name || 'N/A'}<br>
                                    <strong>Status:</strong> <span class="badge ${data.interview_status === 'completed' ? 'bg-success' : 'bg-warning'}">${data.interview_status || 'Not Scheduled'}</span>
                                </div>
                                <div class="col-md-6">
                                    ${data.overall_rating ? `<strong>Rating:</strong> ${'⭐'.repeat(data.overall_rating)} (${data.overall_rating}/5)<br>` : ''}
                                    ${data.feedback_comments ? `<strong>Feedback:</strong><br><div class="mt-2 p-3 bg-light rounded"><em>${data.feedback_comments}</em></div>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ` : ''}
        
        ${data.remarks ? `
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Process Remarks</h6>
                        </div>
                        <div class="card-body">
                            <div class="bg-light p-3 rounded">${data.remarks.replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                </div>
            </div>
        ` : ''}
        
        ${clearanceHtml}
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function updateClearanceStep(stepId, description, currentStatus) {
    document.getElementById('clearanceStepId').value = stepId;
    document.getElementById('clearanceStepDesc').value = description;
    document.querySelector('#clearanceModal [name="status"]').value = currentStatus;
    new bootstrap.Modal(document.getElementById('clearanceModal')).show();
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const isVisible = text.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
    });
});

// Refresh functionality
function refreshData() {
    location.reload();
}

// Additional functions from suppliers page for consistency
function generateReport() {
    showLoading();
    // Report generation logic here
    setTimeout(hideLoading, 2000);
}

function exportData() {
    const data = [];
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            data.push({
                employee: cells[0].textContent.trim(),
                status: cells[1].textContent.trim(),
                dates: cells[2].textContent.trim(),
                progress: cells[3].textContent.trim()
            });
        }
    });
    
    // Convert to CSV and download
    const csv = convertToCSV(data);
    downloadCSV(csv, 'offboarding_records.csv');
}

function convertToCSV(data) {
    if (data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(',');
    const csvRows = data.map(row => 
        headers.map(header => `"${row[header]}"`).join(',')
    );
    
    return [csvHeaders, ...csvRows].join('\n');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Status filter functionality
document.getElementById('statusFilter').addEventListener('change', function() {
    const filterValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        if (!filterValue) {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('.status-badge');
            const status = statusCell ? statusCell.className.toLowerCase() : '';
            row.style.display = status.includes(filterValue) ? '' : 'none';
        }
    });
});
</script>

</div>
</div>

<?php include $base_dir . '/layouts/footer.php'; ?>

<style>
/* Additional CSS to match suppliers page styling */
.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 12px;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-pending { background-color: #fef3c7; color: #92400e; }
.status-approved { background-color: #d1fae5; color: #065f46; }
.status-completed { background-color: #dbeafe; color: #1e40af; }
.status-in_progress { background-color: #fde68a; color: #92400e; }
.status-cancelled { background-color: #fee2e2; color: #991b1b; }
.status-rejected { background-color: #fecaca; color: #dc2626; }

.clearance-step {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.clearance-step:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.clearance-step.completed {
    border-color: #10b981;
    background-color: #f0fdf4;
}

.clearance-step.pending {
    border-color: #f59e0b;
    background-color: #fffbeb;
}

.modal-header-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary-custom:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    border: none;
}

.action-buttons .btn {
    margin-right: 0.25rem;
}

.nav-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}
</style>
