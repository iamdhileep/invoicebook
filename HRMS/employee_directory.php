<?php
/**
 * Advanced Employee Directory
 * Comprehensive employee search, filtering, and management
 */

// Start output buffering to prevent header issues
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Employee Directory";
require_once 'includes/hrms_config.php';

// Debug: Check authentication status
error_log("Employee Directory - Session data: " . print_r($_SESSION, true));
error_log("Employee Directory - isLoggedIn: " . (HRMSHelper::isLoggedIn() ? 'true' : 'false'));

// Simple authentication check - ensure we have user_id in session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Clear any output buffer
    ob_end_clean();
    
    // Check if this is an AJAX request
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required', 'redirect' => true]);
        exit;
    }
    
    // For regular page requests, redirect to login
    header('Location: ../hrms_portal.php?redirect=HRMS/employee_directory.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'search_employees':
            $searchTerm = $_POST['search'] ?? '';
            $department = $_POST['department'] ?? '';
            $position = $_POST['position'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            $sql = "
                SELECT 
                    e.*,
                    d.name as department_name,
                    m.first_name as manager_first_name,
                    m.last_name as manager_last_name,
                    (SELECT AVG(overall_rating) FROM hr_performance_reviews WHERE employee_id = e.id) as avg_rating,
                    (SELECT COUNT(*) FROM hr_attendance WHERE employee_id = e.id AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count
                FROM hr_employees e
                LEFT JOIN hr_departments d ON e.department_id = d.id
                LEFT JOIN hr_employees m ON e.manager_id = m.id
                WHERE 1=1
            ";
            
            $params = [];
            $types = '';
            
            if (!empty($searchTerm)) {
                $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ?)";
                $searchParam = "%$searchTerm%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
                $types .= 'ssss';
            }
            
            if (!empty($department)) {
                $sql .= " AND e.department_id = ?";
                $params[] = $department;
                $types .= 'i';
            }
            
            if (!empty($position)) {
                $sql .= " AND e.position LIKE ?";
                $params[] = "%$position%";
                $types .= 's';
            }
            
            if ($status === 'active') {
                $sql .= " AND e.is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND e.is_active = 0";
            }
            
            $sql .= " ORDER BY e.first_name, e.last_name";
            
            try {
                if (!empty($params)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }
                
                $employees = [];
                while ($row = $result->fetch_assoc()) {
                    $employees[] = $row;
                }
                
                echo json_encode(['success' => true, 'employees' => $employees]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_employee_details':
            $employeeId = $_POST['employee_id'] ?? 0;
            
            try {
                // Get employee details
                $stmt = $conn->prepare("
                    SELECT 
                        e.*,
                        d.name as department_name,
                        m.first_name as manager_first_name,
                        m.last_name as manager_last_name
                    FROM hr_employees e
                    LEFT JOIN hr_departments d ON e.department_id = d.id
                    LEFT JOIN hr_employees m ON e.manager_id = m.id
                    WHERE e.id = ?
                ");
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $employee = $stmt->get_result()->fetch_assoc();
                
                if (!$employee) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
                // Get recent attendance
                $stmt = $conn->prepare("
                    SELECT * FROM hr_attendance 
                    WHERE employee_id = ? 
                    ORDER BY date DESC 
                    LIMIT 10
                ");
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get recent leave applications
                $stmt = $conn->prepare("
                    SELECT lr.*, lt.name as leave_type_name
                    FROM hr_leave_requests lr
                    LEFT JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
                    WHERE lr.employee_id = ? 
                    ORDER BY lr.created_at DESC 
                    LIMIT 5
                ");
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get performance reviews
                $stmt = $conn->prepare("
                    SELECT * FROM hr_performance_reviews 
                    WHERE employee_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 3
                ");
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'employee' => $employee,
                    'attendance' => $attendance,
                    'leaves' => $leaves,
                    'performance' => $performance
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get departments for filter
$departments = [];
try {
    $result = HRMSHelper::safeQuery("SELECT id, name FROM hr_departments WHERE status = 'active' ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
} catch (Exception $e) {
    error_log("Department fetch error: " . $e->getMessage());
}

// Get unique positions for filter
$positions = [];
try {
    $result = HRMSHelper::safeQuery("SELECT DISTINCT position FROM hr_employees WHERE position IS NOT NULL AND position != '' ORDER BY position");
    while ($row = $result->fetch_assoc()) {
        $positions[] = $row['position'];
    }
} catch (Exception $e) {
    error_log("Position fetch error: " . $e->getMessage());
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-address-book text-primary me-2"></i>
                            Employee Directory
                        </h1>
                        <p class="text-muted mb-0">Search, filter, and manage employee information</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-success" onclick="exportDirectory()">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                        <button class="btn btn-primary" onclick="openAddEmployeeModal()">
                            <i class="fas fa-plus me-1"></i>Add Employee
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Search Employees</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchInput" 
                                           placeholder="Name, email, or employee ID...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">Department</label>
                                <select class="form-select" id="departmentFilter">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>">
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">Position</label>
                                <select class="form-select" id="positionFilter">
                                    <option value="">All Positions</option>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?= htmlspecialchars($position) ?>">
                                            <?= htmlspecialchars($position) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">&nbsp;</label>
                                <div class="d-grid">
                                    <button class="btn btn-primary" onclick="searchEmployees()">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Grid -->
        <div class="row" id="employeeGrid">
            <!-- Employee cards will be loaded here -->
        </div>

        <!-- Loading Spinner -->
        <div class="row" id="loadingSpinner" style="display: none;">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading employees...</p>
            </div>
        </div>

        <!-- No Results -->
        <div class="row" id="noResults" style="display: none;">
            <div class="col-12 text-center py-5">
                <i class="fas fa-search text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                <h5 class="text-muted mt-3">No employees found</h5>
                <p class="text-muted">Try adjusting your search criteria</p>
            </div>
        </div>
    </div>
</div>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Employee Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.employee-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
    border: none;
    cursor: pointer;
}

.employee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #6610f2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
}

.rating-stars {
    color: #ffc107;
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}
</style>

<script>
let currentEmployees = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    searchEmployees();
    
    // Add event listeners for real-time search
    document.getElementById('searchInput').addEventListener('input', debounce(searchEmployees, 300));
    document.getElementById('departmentFilter').addEventListener('change', searchEmployees);
    document.getElementById('positionFilter').addEventListener('change', searchEmployees);
    document.getElementById('statusFilter').addEventListener('change', searchEmployees);
});

// Debounce function for search input
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search employees
function searchEmployees() {
    const searchTerm = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const position = document.getElementById('positionFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    showLoading();
    
    const formData = new FormData();
    formData.append('action', 'search_employees');
    formData.append('search', searchTerm);
    formData.append('department', department);
    formData.append('position', position);
    formData.append('status', status);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentEmployees = data.employees;
            displayEmployees(data.employees);
        } else {
            showError('Error searching employees: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showError('Network error: ' + error.message);
    });
}

// Display employees in grid
function displayEmployees(employees) {
    const grid = document.getElementById('employeeGrid');
    const noResults = document.getElementById('noResults');
    
    if (employees.length === 0) {
        grid.innerHTML = '';
        noResults.style.display = 'block';
        return;
    }
    
    noResults.style.display = 'none';
    
    let html = '';
    employees.forEach(employee => {
        const initials = (employee.first_name.charAt(0) + employee.last_name.charAt(0)).toUpperCase();
        const fullName = `${employee.first_name} ${employee.last_name}`;
        const rating = employee.avg_rating ? parseFloat(employee.avg_rating) : 0;
        const attendanceRate = employee.attendance_count ? (employee.attendance_count / 30) * 100 : 0;
        
        html += `
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card employee-card h-100" onclick="showEmployeeDetails(${employee.id})">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start mb-3">
                            <div class="employee-avatar me-3">
                                ${initials}
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">${escapeHtml(fullName)}</h6>
                                <p class="text-muted small mb-1">${escapeHtml(employee.position || 'N/A')}</p>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-building me-1"></i>
                                    ${escapeHtml(employee.department_name || 'N/A')}
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge ${employee.is_active ? 'bg-success' : 'bg-secondary'}">
                                    ${employee.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">
                                <i class="fas fa-id-card me-1"></i>
                                ID: ${escapeHtml(employee.employee_id)}
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-envelope me-1"></i>
                                ${escapeHtml(employee.email)}
                            </small>
                            ${employee.phone ? `
                                <small class="text-muted d-block">
                                    <i class="fas fa-phone me-1"></i>
                                    ${escapeHtml(employee.phone)}
                                </small>
                            ` : ''}
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <div class="rating-stars">
                                        ${generateStars(rating)}
                                    </div>
                                    <small class="text-muted d-block">Performance</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-${attendanceRate >= 90 ? 'success' : attendanceRate >= 75 ? 'warning' : 'danger'}">
                                    <strong>${Math.round(attendanceRate)}%</strong>
                                </div>
                                <small class="text-muted d-block">Attendance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

// Generate star rating HTML
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star"></i>';
        } else if (i - 0.5 <= rating) {
            stars += '<i class="fas fa-star-half-alt"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    return stars;
}

// Show employee details modal
function showEmployeeDetails(employeeId) {
    const formData = new FormData();
    formData.append('action', 'get_employee_details');
    formData.append('employee_id', employeeId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayEmployeeDetails(data);
            const modal = new bootstrap.Modal(document.getElementById('employeeDetailsModal'));
            modal.show();
        } else {
            showError('Error loading employee details: ' + data.message);
        }
    })
    .catch(error => {
        showError('Network error: ' + error.message);
    });
}

// Display employee details in modal
function displayEmployeeDetails(data) {
    const employee = data.employee;
    const fullName = `${employee.first_name} ${employee.last_name}`;
    const initials = (employee.first_name.charAt(0) + employee.last_name.charAt(0)).toUpperCase();
    
    let html = `
        <div class="row">
            <div class="col-md-4">
                <div class="text-center mb-4">
                    <div class="employee-avatar mx-auto mb-3" style="width: 120px; height: 120px; font-size: 2.5rem;">
                        ${initials}
                    </div>
                    <h4>${escapeHtml(fullName)}</h4>
                    <p class="text-muted">${escapeHtml(employee.position || 'N/A')}</p>
                    <span class="badge ${employee.is_active ? 'bg-success' : 'bg-secondary'} fs-6 px-3 py-2">
                        ${employee.is_active ? 'Active Employee' : 'Inactive'}
                    </span>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Contact Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">Employee ID</small>
                            <div>${escapeHtml(employee.employee_id)}</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Email</small>
                            <div>${escapeHtml(employee.email)}</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Phone</small>
                            <div>${escapeHtml(employee.phone || 'N/A')}</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Department</small>
                            <div>${escapeHtml(employee.department_name || 'N/A')}</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Manager</small>
                            <div>${escapeHtml(employee.manager_first_name && employee.manager_last_name ? 
                                employee.manager_first_name + ' ' + employee.manager_last_name : 'N/A')}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Tabs for different sections -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#attendance-tab">
                            <i class="fas fa-calendar-check me-1"></i>Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#leaves-tab">
                            <i class="fas fa-clock me-1"></i>Leave History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#performance-tab">
                            <i class="fas fa-star me-1"></i>Performance
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content mt-3">
                    <!-- Attendance Tab -->
                    <div class="tab-pane fade show active" id="attendance-tab">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    data.attendance.forEach(record => {
        const statusClass = {
            'present': 'success',
            'late': 'warning',
            'absent': 'danger',
            'half_day': 'info'
        }[record.status] || 'secondary';
        
        html += `
            <tr>
                <td>${record.attendance_date}</td>
                <td>${record.clock_in_time || '-'}</td>
                <td>${record.clock_out_time || '-'}</td>
                <td>${record.total_hours || '-'}</td>
                <td><span class="badge bg-${statusClass}">${record.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Leave History Tab -->
                    <div class="tab-pane fade" id="leaves-tab">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    data.leaves.forEach(leave => {
        const statusClass = {
            'approved': 'success',
            'pending': 'warning',
            'rejected': 'danger',
            'cancelled': 'secondary'
        }[leave.status] || 'secondary';
        
        html += `
            <tr>
                <td>${escapeHtml(leave.leave_type_name || leave.leave_type_id)}</td>
                <td>${leave.start_date}</td>
                <td>${leave.end_date}</td>
                <td>${leave.days_requested}</td>
                <td><span class="badge bg-${statusClass}">${leave.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Performance Tab -->
                    <div class="tab-pane fade" id="performance-tab">
    `;
    
    if (data.performance.length > 0) {
        data.performance.forEach(review => {
            html += `
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Review Period</small>
                                <div>${review.review_period_start} to ${review.review_period_end}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Overall Rating</small>
                                <div class="rating-stars fs-5">
                                    ${generateStars(review.overall_rating || 0)}
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <small class="text-muted">Goals Achievement</small>
                                <div>${review.goals_achievement || 'N/A'}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Technical Skills</small>
                                <div>${review.technical_skills || 'N/A'}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Teamwork</small>
                                <div>${review.teamwork || 'N/A'}</div>
                            </div>
                        </div>
                        ${review.comments ? `
                            <div class="mt-3">
                                <small class="text-muted">Comments</small>
                                <div>${escapeHtml(review.comments)}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
    } else {
        html += '<p class="text-muted">No performance reviews available.</p>';
    }
    
    html += `
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('employeeDetailsContent').innerHTML = html;
}

// Utility functions
function showLoading() {
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('employeeGrid').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
}

function hideLoading() {
    document.getElementById('loadingSpinner').style.display = 'none';
    document.getElementById('employeeGrid').style.display = 'flex';
}

function showError(message) {
    alert(message); // Replace with better error handling
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function exportDirectory() {
    if (currentEmployees.length === 0) {
        alert('No employees to export');
        return;
    }
    
    // Create CSV content
    const headers = ['Employee ID', 'Name', 'Email', 'Department', 'Position', 'Phone', 'Status'];
    const csvContent = [
        headers.join(','),
        ...currentEmployees.map(emp => [
            emp.employee_id,
            `"${emp.first_name} ${emp.last_name}"`,
            emp.email,
            `"${emp.department_name || ''}"`,
            `"${emp.position || ''}"`,
            emp.phone || '',
            emp.is_active ? 'Active' : 'Inactive'
        ].join(','))
    ].join('\n');
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'employee_directory_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function openAddEmployeeModal() {
    alert('Add employee functionality will be implemented next!');
}
</script>

<?php require_once '../layouts/footer.php'; ?>
                            <div class="row mb-2">
                                <div class="col-4 text-muted">Phone:</div>
                                <div class="col-8">
                                    <a href="tel:+1234567892" class="text-decoration-none">
                                        +1 (234) 567-892
                                    </a>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 text-muted">Join Date:</div>
                                <div class="col-8">Jul 20, 2022</div>
                            </div>
                        </div>

                        <div class="employee-actions mt-3">
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewEmployee(3)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="editEmployee(3)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="contactEmployee(3)">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="viewAttendance(3)">
                                    <i class="fas fa-calendar"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add more employee cards as needed -->
        </div>

        <!-- Pagination -->
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Employee pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">3</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<style>
.employee-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.employee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-avatar img {
    width: 60px;
    height: 60px;
    object-fit: cover;
}

.employee-details {
    font-size: 0.9rem;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-group .btn {
    flex: 1;
}
</style>

<script>
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterEmployees();
}

function filterEmployees() {
    // Implement filtering logic here
    console.log('Filtering employees...');
}

function exportEmployees() {
    alert('Employee export functionality will be implemented here.');
}

function viewEmployee(id) {
    window.location.href = `employee_profile.php?id=${id}`;
}

function editEmployee(id) {
    window.location.href = `../edit_employee.php?id=${id}`;
}

function contactEmployee(id) {
    alert(`Contact employee ${id} functionality will be implemented here.`);
}

function viewAttendance(id) {
    window.location.href = `../attendance.php?employee_id=${id}`;
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    filterEmployees();
});

document.getElementById('departmentFilter').addEventListener('change', function() {
    filterEmployees();
});

document.getElementById('statusFilter').addEventListener('change', function() {
    filterEmployees();
});
</script>

<?php include '../layouts/footer.php'; ?>
