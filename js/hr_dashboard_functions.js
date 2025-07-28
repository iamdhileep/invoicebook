// HR Dashboard JavaScript Functions
// This file contains all the interactive functions for the HR system dashboards

// Debug mode for logging
const DEBUG_MODE = true;

function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        console.log('[HR Dashboard]', message, data);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    debugLog('HR Dashboard functions loaded');
    
    // Test basic functionality
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
    } else {
        debugLog('Bootstrap loaded successfully');
    }
});

// ============= UTILITY FUNCTIONS =============

function showModal(title, content, onSave = null, size = 'modal-lg') {
    debugLog('Opening modal:', title);
    
    try {
        const modalHtml = `
            <div class="modal fade" id="dynamicModal" tabindex="-1">
                <div class="modal-dialog ${size}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            ${onSave ? `<button type="button" class="btn btn-primary" onclick="${onSave}">Save</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('dynamicModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('dynamicModal'));
        modal.show();
        
        debugLog('Modal opened successfully');
        
    } catch (error) {
        console.error('Error opening modal:', error);
        alert('Error opening modal: ' + error.message);
    }
}

function closeModal() {
    try {
        const modal = bootstrap.Modal.getInstance(document.getElementById('dynamicModal'));
        if (modal) {
            modal.hide();
        }
    } catch (error) {
        console.error('Error closing modal:', error);
    }
}

function showAlert(message, type = 'info') {
    debugLog('Showing alert:', { message, type });
    
    try {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.textContent.includes(message)) {
                    alert.remove();
                }
            });
        }, 5000);
        
    } catch (error) {
        console.error('Error showing alert:', error);
        // Fallback to basic alert
        alert(message);
    }
}

// ============= API HELPER FUNCTIONS =============

async function makeApiCall(url, options = {}) {
    debugLog('Making API call to:', url);
    
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        debugLog('API response:', data);
        
        return data;
    } catch (error) {
        console.error('API call failed:', error);
        showAlert('API call failed: ' + error.message, 'danger');
        throw error;
    }
}

// Get the correct API base path based on current location
function getApiPath(endpoint) {
    // Determine if we're in a subdirectory
    const currentPath = window.location.pathname;
    let basePath = '';
    
    if (currentPath.includes('/pages/')) {
        basePath = '../../api/';
    } else if (currentPath.includes('/timesheet/')) {
        basePath = '../api/';
    } else {
        basePath = 'api/';
    }
    
    return basePath + endpoint;
}

// ============= HR DASHBOARD FUNCTIONS =============

function addNewEmployee() {
    debugLog('Opening Add New Employee modal');
    
    // Show modal for adding new employee
    showModal('Add New Employee', `
        <form id="addEmployeeForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Department *</label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department</option>
                            <option value="1">Human Resources</option>
                            <option value="2">Engineering</option>
                            <option value="3">Marketing</option>
                            <option value="4">Sales</option>
                            <option value="5">Finance</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Salary</label>
                        <input type="number" class="form-control" name="salary" step="0.01">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="hire_date">
                    </div>
                </div>
            </div>
        </form>
    `, 'saveEmployee()');
}

async function saveEmployee() {
    debugLog('Saving employee');
    
    try {
        const form = document.getElementById('addEmployeeForm');
        const formData = new FormData(form);
        
        const response = await fetch(getApiPath('save_employee.php'), {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal();
            showAlert('Employee added successfully!', 'success');
            refreshDashboard();
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
        
    } catch (error) {
        console.error('Error saving employee:', error);
        showAlert('Error adding employee: ' + error.message, 'danger');
    }
}

async function viewPendingLeaves() {
    debugLog('Loading pending leaves');
    
    try {
        const data = await makeApiCall(getApiPath('get_pending_leaves.php'));
        
        let content = `
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (data.success && data.leaves.length > 0) {
            data.leaves.forEach(leave => {
                content += `
                    <tr>
                        <td>${leave.employee_name}</td>
                        <td><span class="badge bg-info">${leave.leave_type}</span></td>
                        <td>${leave.start_date}</td>
                        <td>${leave.end_date}</td>
                        <td>${leave.days}</td>
                        <td>${leave.reason}</td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="approveLeave(${leave.id})">Approve</button>
                            <button class="btn btn-danger btn-sm" onclick="rejectLeave(${leave.id})">Reject</button>
                        </td>
                    </tr>
                `;
            });
        } else {
            content += `
                <tr>
                    <td colspan="7" class="text-center text-muted">No pending leave requests found</td>
                </tr>
            `;
        }
        
        content += `
                    </tbody>
                </table>
            </div>
        `;
        
        showModal('Pending Leave Requests', content);
        
    } catch (error) {
        console.error('Error loading pending leaves:', error);
        showAlert('Error loading pending leaves: ' + error.message, 'danger');
    }
}

function manageEmployees() {
    fetch('../api/get_all_employees.php')
    .then(response => response.json())
    .then(data => {
        let content = `
            <div class="mb-3">
                <button class="btn btn-primary" onclick="addNewEmployee()">
                    <i class="fas fa-plus"></i> Add New Employee
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.employees.forEach(employee => {
            content += `
                <tr>
                    <td>${employee.id}</td>
                    <td>${employee.first_name} ${employee.last_name}</td>
                    <td>${employee.email}</td>
                    <td>${employee.department}</td>
                    <td>${employee.position || 'N/A'}</td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editEmployee(${employee.id})">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteEmployee(${employee.id})">Delete</button>
                    </td>
                </tr>
            `;
        });
        
        content += `
                    </tbody>
                </table>
            </div>
        `;
        
        showModal('Manage Employees', content, null, 'modal-xl');
    });
}

function showHRSettings() {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h5>System Settings</h5>
                <div class="mb-3">
                    <label class="form-label">Working Hours per Day</label>
                    <input type="number" class="form-control" value="8" min="1" max="24">
                </div>
                <div class="mb-3">
                    <label class="form-label">Working Days per Week</label>
                    <input type="number" class="form-control" value="5" min="1" max="7">
                </div>
                <div class="mb-3">
                    <label class="form-label">Leave Policy</label>
                    <select class="form-control">
                        <option>Annual Leave: 20 days</option>
                        <option>Sick Leave: 10 days</option>
                        <option>Personal Leave: 5 days</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <h5>Notification Settings</h5>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" checked>
                    <label class="form-check-label">Email notifications for leave requests</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" checked>
                    <label class="form-check-label">Attendance alerts</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox">
                    <label class="form-check-label">Weekly reports</label>
                </div>
            </div>
        </div>
    `;
    
    showModal('HR Settings', content, 'saveHRSettings()');
}

function manageHolidays() {
    const content = `
        <div class="mb-3">
            <button class="btn btn-primary" onclick="addHoliday()">
                <i class="fas fa-plus"></i> Add Holiday
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Holiday Name</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2024-01-01</td>
                        <td>New Year's Day</td>
                        <td><span class="badge bg-primary">Public Holiday</span></td>
                        <td>
                            <button class="btn btn-warning btn-sm">Edit</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                    <tr>
                        <td>2024-12-25</td>
                        <td>Christmas Day</td>
                        <td><span class="badge bg-primary">Public Holiday</span></td>
                        <td>
                            <button class="btn btn-warning btn-sm">Edit</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    showModal('Manage Holidays', content, null, 'modal-lg');
}

function viewAttendance() {
    window.location.href = '../attendance/attendance_report.php';
}

function generateReports() {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h5>Available Reports</h5>
                <div class="list-group">
                    <button class="list-group-item list-group-item-action" onclick="generateAttendanceReport()">
                        <i class="fas fa-calendar-check"></i> Attendance Report
                    </button>
                    <button class="list-group-item list-group-item-action" onclick="generateLeaveReport()">
                        <i class="fas fa-calendar-times"></i> Leave Report
                    </button>
                    <button class="list-group-item list-group-item-action" onclick="generatePayrollReport()">
                        <i class="fas fa-dollar-sign"></i> Payroll Report
                    </button>
                    <button class="list-group-item list-group-item-action" onclick="generateEmployeeReport()">
                        <i class="fas fa-users"></i> Employee Report
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <h5>Report Filters</h5>
                <div class="mb-3">
                    <label class="form-label">Date Range</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="date" class="form-control" id="reportStartDate">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" id="reportEndDate">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select class="form-control" id="reportDepartment">
                        <option value="">All Departments</option>
                        <option value="1">Human Resources</option>
                        <option value="2">Engineering</option>
                        <option value="3">Marketing</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    showModal('Generate Reports', content);
}

// ============= MANAGER DASHBOARD FUNCTIONS =============

function performTeamSearch() {
    const searchTerm = document.querySelector('input[placeholder="Search team members..."]').value;
    showAlert('Searching for: ' + searchTerm, 'info');
}

function openTeamMeetingModal() {
    const content = `
        <form id="teamMeetingForm">
            <div class="mb-3">
                <label class="form-label">Meeting Title *</label>
                <input type="text" class="form-control" name="title" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Time *</label>
                        <input type="time" class="form-control" name="time" required>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" class="form-control" name="duration" value="60">
            </div>
            <div class="mb-3">
                <label class="form-label">Attendees</label>
                <select class="form-control" name="attendees" multiple>
                    <option value="1">John Doe</option>
                    <option value="2">Jane Smith</option>
                    <option value="3">Mike Johnson</option>
                </select>
                <small class="form-text text-muted">Hold Ctrl to select multiple attendees</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Agenda</label>
                <textarea class="form-control" name="agenda" rows="3"></textarea>
            </div>
        </form>
    `;
    
    showModal('Schedule Team Meeting', content, 'scheduleTeamMeeting()');
}

function refreshManagerDashboard() {
    showAlert('Dashboard refreshed!', 'success');
    location.reload();
}

function viewPendingApprovals() {
    viewPendingLeaves(); // Reuse the HR function
}

function viewTeamMembers() {
    fetch('../api/get_team_members.php')
    .then(response => response.json())
    .then(data => {
        let content = `
            <div class="row">
        `;
        
        data.members.forEach(member => {
            content += `
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">${member.name}</h6>
                            <p class="card-text">
                                <small class="text-muted">${member.position}</small><br>
                                Email: ${member.email}<br>
                                Status: <span class="badge bg-success">Active</span>
                            </p>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewEmployeeDetails(${member.id})">View</button>
                                <button class="btn btn-outline-warning" onclick="editEmployee(${member.id})">Edit</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        content += `
            </div>
        `;
        
        showModal('Team Members', content, null, 'modal-lg');
    })
    .catch(error => {
        showAlert('Error loading team members', 'danger');
    });
}

function viewAttendanceDetails() {
    window.location.href = '../attendance/team_attendance.php';
}

function viewActiveProjects() {
    const content = `
        <div class="mb-3">
            <button class="btn btn-primary" onclick="addNewProject()">
                <i class="fas fa-plus"></i> New Project
            </button>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="card-title">Website Redesign</h6>
                        <p class="card-text">Redesigning company website with modern UI/UX</p>
                        <div class="progress mb-2">
                            <div class="progress-bar" style="width: 75%">75%</div>
                        </div>
                        <small class="text-muted">Due: 2024-02-15</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-primary">View Details</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-warning">
                    <div class="card-body">
                        <h6 class="card-title">Mobile App Development</h6>
                        <p class="card-text">Developing new mobile application for customers</p>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-warning" style="width: 45%">45%</div>
                        </div>
                        <small class="text-muted">Due: 2024-03-01</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-warning">View Details</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    showModal('Active Projects', content, null, 'modal-lg');
}

// ============= EMPLOYEE PORTAL FUNCTIONS =============

function markAttendance() {
    const now = new Date();
    const content = `
        <div class="text-center">
            <h4>Mark Attendance</h4>
            <p class="text-muted">Current Time: ${now.toLocaleString()}</p>
            <div class="my-4">
                <button class="btn btn-success btn-lg me-3" onclick="clockIn()">
                    <i class="fas fa-sign-in-alt"></i> Clock In
                </button>
                <button class="btn btn-danger btn-lg" onclick="clockOut()">
                    <i class="fas fa-sign-out-alt"></i> Clock Out
                </button>
            </div>
            <div class="alert alert-info">
                <small>Note: Your location will be recorded for security purposes.</small>
            </div>
        </div>
    `;
    
    showModal('Attendance', content);
}

function clockIn() {
    fetch('../api/clock_in.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            timestamp: new Date().toISOString()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showAlert('Successfully clocked in!', 'success');
            refreshEmployeeData();
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    });
}

function clockOut() {
    // Add loading state
    const originalButton = event.target;
    const originalText = originalButton.innerHTML;
    originalButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clocking out...';
    originalButton.disabled = true;
    
    // Set timeout to prevent hanging
    const timeoutId = setTimeout(() => {
        showAlert('Request timeout. Please try again.', 'danger');
        originalButton.innerHTML = originalText;
        originalButton.disabled = false;
    }, 10000); // 10 second timeout
    
    fetch('../api/clock_out.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            timestamp: new Date().toISOString()
        })
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        originalButton.innerHTML = originalText;
        originalButton.disabled = false;
        
        if (data.success) {
            closeModal();
            showAlert('Successfully clocked out!', 'success');
            // Don't reload immediately, just update UI
            setTimeout(() => refreshEmployeeData(), 1000);
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        originalButton.innerHTML = originalText;
        originalButton.disabled = false;
        console.error('Clock out error:', error);
        showAlert('Error clocking out: ' + error.message, 'danger');
    });
}

function refreshEmployeeData() {
    showAlert('Dashboard refreshed!', 'success');
    // Instead of full page reload, try to refresh specific components
    if (typeof loadAttendanceData === 'function') {
        loadAttendanceData();
    }
    if (typeof updateAttendanceStatus === 'function') {
        updateAttendanceStatus();
    }
    // Only reload as last resort after a delay
    setTimeout(() => {
        location.reload();
    }, 2000);
}

function openProfileModal() {
    const content = `
        <form id="profileForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="John">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="Doe">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="john.doe@company.com" readonly>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" value="+1234567890">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="dob">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2"></textarea>
            </div>
        </form>
    `;
    
    showModal('Edit Profile', content, 'updateProfile()');
}

function viewTimesheet() {
    window.location.href = '../timesheet/timesheet.php';
}

function requestOvertime() {
    const content = `
        <form id="overtimeForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Hours *</label>
                        <input type="number" class="form-control" name="hours" min="1" max="12" required>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Reason *</label>
                <textarea class="form-control" name="reason" rows="3" required placeholder="Please explain the reason for overtime..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Project/Task</label>
                <input type="text" class="form-control" name="project" placeholder="Related project or task">
            </div>
        </form>
    `;
    
    showModal('Request Overtime', content, 'submitOvertimeRequest()');
}

// ============= UTILITY FUNCTIONS =============

function showModal(title, content, onSave = null, size = 'modal-lg') {
    const modalHtml = `
        <div class="modal fade" id="dynamicModal" tabindex="-1">
            <div class="modal-dialog ${size}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        ${onSave ? `<button type="button" class="btn btn-primary" onclick="${onSave}">Save</button>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('dynamicModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('dynamicModal'));
    modal.show();
}

function closeModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('dynamicModal'));
    if (modal) {
        modal.hide();
    }
}

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message)) {
                alert.remove();
            }
        });
    }, 5000);
}

function refreshDashboard() {
    location.reload();
}

// Additional specific functions
function approveLeave(leaveId) {
    fetch('../api/approve_leave.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ leave_id: leaveId })
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            viewPendingLeaves(); // Refresh the list
        }
    });
}

function rejectLeave(leaveId) {
    fetch('../api/reject_leave.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ leave_id: leaveId })
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            viewPendingLeaves(); // Refresh the list
        }
    });
}

function editEmployee(employeeId) {
    fetch(`../api/get_employee.php?id=${employeeId}`)
    .then(response => response.json())
    .then(data => {
        const employee = data.employee;
        const content = `
            <form id="editEmployeeForm">
                <input type="hidden" name="employee_id" value="${employee.id}">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="${employee.first_name}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="${employee.last_name}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="${employee.email}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" class="form-control" name="position" value="${employee.position || ''}">
                </div>
            </form>
        `;
        
        showModal('Edit Employee', content, 'updateEmployee()');
    });
}

function updateEmployee() {
    const form = document.getElementById('editEmployeeForm');
    const formData = new FormData(form);
    
    fetch('../api/update_employee.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showAlert('Employee updated successfully!', 'success');
            manageEmployees(); // Refresh the list
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Any initialization code can go here
    console.log('HR Dashboard functions loaded');
});
