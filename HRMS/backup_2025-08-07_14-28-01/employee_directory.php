<?php
$page_title = "Employee Directory";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';
?>

<!-- Main Content Area -->
<!-- Page Content Starts Here -->
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">
                    <i class="fas fa-address-book text-primary me-2"></i>
                    Employee Directory
                </h1>
                <p class="text-muted mb-0">Search and manage employee information</p>
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

        <!-- Search Section -->
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
                                    <input type="text" class="form-control" id="searchInput" placeholder="Name, email...">
                                </div>
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-3">
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
                                        <i class="fas fa-search"></i> Search
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
            <!-- Employees will be loaded here -->
        </div>

        <!-- Loading -->
        <div class="row" id="loadingSpinner" style="display: none;">
            <div class="col-12 text-center py-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading employees...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Results -->
        <div class="row" id="noResults" style="display: none;">
            <div class="col-12 text-center py-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-search text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <h5 class="mt-3">No employees found</h5>
                        <p class="text-muted">Try adjusting your search criteria</p>
                    </div>
                </div>
        </div>
    </div>
</div>

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-labelledby="employeeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeDetailsModalLabel">
                    <i class="fas fa-user me-2"></i>Employee Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="employeeDetailsContent">
                <!-- Content loaded dynamically -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editEmployee()">
                    <i class="fas fa-edit me-1"></i>Edit Employee
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="employeeDetailsContent">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    </div>
</div>

<style>
.employee-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    border: none;
    cursor: pointer;
}

.employee-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
</style>

<script>
let currentEmployees = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    searchEmployees();
    
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(searchEmployees, 300);
    });
    document.getElementById('departmentFilter').addEventListener('change', searchEmployees);
    document.getElementById('statusFilter').addEventListener('change', searchEmployees);
});

function searchEmployees() {
    const formData = new FormData();
    formData.append('action', 'search_employees');
    formData.append('search', document.getElementById('searchInput').value);
    formData.append('department', document.getElementById('departmentFilter').value);
    formData.append('status', document.getElementById('statusFilter').value);
    
    showLoading();
    
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
            console.error('Error:', data.message);
            showError('Failed to load employees: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showError('Network error occurred');
    });
}

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
        const fullName = employee.first_name + ' ' + employee.last_name;
        
        html += `
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card employee-card h-100" onclick="showEmployeeDetails(${employee.id})">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start mb-3">
                            <div class="employee-avatar me-3">${initials}</div>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">${escapeHtml(fullName)}</h6>
                                <p class="text-muted small mb-1">${escapeHtml(employee.position || 'N/A')}</p>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-building me-1"></i>
                                    ${escapeHtml(employee.department_name || 'No Department')}
                                </p>
                            </div>
                            <span class="badge ${employee.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                                ${employee.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
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
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                ${employee.hire_date ? 'Since ' + new Date(employee.hire_date).getFullYear() : 'New Employee'}
                            </small>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

function showEmployeeDetails(employeeId) {
    // Store current employee ID globally for edit functionality
    window.currentEmployeeId = employeeId;
    
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
            displayEmployeeDetailsModal(data);
            new bootstrap.Modal(document.getElementById('employeeDetailsModal')).show();
        } else {
            showError('Failed to load employee details');
        }
    })
    .catch(error => {
        showError('Network error occurred');
    });
}

function displayEmployeeDetailsModal(data) {
    const employee = data.employee;
    const fullName = employee.first_name + ' ' + employee.last_name;
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
                    <span class="badge ${employee.status === 'active' ? 'bg-success' : 'bg-secondary'} fs-6 px-3 py-2">
                        ${employee.status === 'active' ? 'Active Employee' : 'Inactive'}
                    </span>
                </div>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-address-card me-2"></i>Contact Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Employee ID</small>
                            <div class="fw-semibold">${escapeHtml(employee.employee_id)}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Email Address</small>
                            <div class="fw-semibold">
                                <a href="mailto:${escapeHtml(employee.email)}" class="text-decoration-none">
                                    ${escapeHtml(employee.email)}
                                </a>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Phone Number</small>
                            <div class="fw-semibold">${escapeHtml(employee.phone || 'Not provided')}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Department</small>
                            <div class="fw-semibold">${escapeHtml(employee.department_name || 'Unassigned')}</div>
                        </div>
                        ${employee.hire_date ? `
                            <div class="mb-3">
                                <small class="text-muted">Hire Date</small>
                                <div class="fw-semibold">${new Date(employee.hire_date).toLocaleDateString()}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-primary">
                            <h6 class="alert-heading">
                                <i class="fas fa-user-circle me-2"></i>Employee Profile
                            </h6>
                            <p class="mb-2">Complete employee management features including attendance tracking, performance reviews, and leave management are available through the HRMS system.</p>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Current Status:</strong> ${employee.status === 'active' ? 'Active' : 'Inactive'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Employee Type:</strong> ${employee.position || 'General'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="text-primary mb-2">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                                <h6>Attendance</h6>
                                <p class="text-muted small mb-0">Track daily attendance and hours</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="text-success mb-2">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <h6>Leave History</h6>
                                <p class="text-muted small mb-0">View leave applications and approvals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="text-warning mb-2">
                                    <i class="fas fa-star fa-2x"></i>
                                </div>
                                <h6>Performance</h6>
                                <p class="text-muted small mb-0">Performance reviews and ratings</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('employeeDetailsContent').innerHTML = html;
}

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
    // Create a toast notification or alert
    if (typeof window.showToast === 'function') {
        window.showToast(message, 'error');
    } else {
        alert('Error: ' + message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function exportDirectory() {
    if (currentEmployees.length === 0) {
        showError('No employees to export');
        return;
    }
    
    // Create CSV content
    const headers = ['Employee ID', 'Name', 'Email', 'Department', 'Position', 'Phone', 'Status', 'Hire Date'];
    const csvContent = [
        headers.join(','),
        ...currentEmployees.map(emp => [
            emp.employee_id,
            `"${emp.first_name} ${emp.last_name}"`,
            emp.email,
            `"${emp.department_name || ''}"`,
            `"${emp.position || ''}"`,
            emp.phone || '',
            emp.status === 'active' ? 'Active' : 'Inactive',
            emp.hire_date || ''
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
    window.location.href = '../add_employee.php';
}

function editEmployee() {
    // Get current employee ID from modal (could be stored globally when modal opens)
    if (window.currentEmployeeId) {
        window.location.href = `../edit_employee.php?id=${window.currentEmployeeId}`;
    } else {
        showError('Employee ID not found');
    }
}
</script>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>