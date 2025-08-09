// Comprehensive JavaScript for payroll modal operations
$(document).ready(function() {
    
    // Initialize payroll system
    initializePayrollSystem();
    
    // Event handlers for all payroll buttons
    setupPayrollEventHandlers();
    
});

// Initialize the payroll system
function initializePayrollSystem() {
    // Load any necessary data on page load
    console.log('Payroll system initialized');
    
    // Set default values
    $('#bulk-pay-period').val(getCurrentPeriod());
    $('#summary-period').val(getCurrentPeriod());
    $('#export-period').val(getCurrentPeriod());
}

// Setup all event handlers for payroll operations
function setupPayrollEventHandlers() {
    
    // Bulk Payslip Generation
    $('#generateBulkBtn').on('click', function() {
        openBulkPayslipModal();
    });
    
    $('#bulk-department').on('change', function() {
        loadEmployeesForBulk();
    });
    
    $('#select-all-employees').on('change', function() {
        toggleAllEmployees(this.checked);
    });
    
    $('#generate-bulk-payslips').on('click', function() {
        generateBulkPayslips();
    });
    
    // Payroll Summary
    $('#payrollSummaryBtn').on('click', function() {
        openPayrollSummaryModal();
    });
    
    $('#load-summary').on('click', function() {
        loadPayrollSummary();
    });
    
    // Template Selection
    $('#templateSelectionBtn').on('click', function() {
        openTemplateSelectionModal();
    });
    
    $('#apply-template').on('click', function() {
        applySelectedTemplate();
    });
    
    // Payroll Settings
    $('#payrollSettingsBtn').on('click', function() {
        openPayrollSettingsModal();
    });
    
    $('#save-settings').on('click', function() {
        savePayrollSettings();
    });
    
    // Export Operations
    $('#exportPayslipsBtn').on('click', function() {
        openExportModal();
    });
    
    $('#export-data').on('click', function() {
        exportPayslips();
    });
    
    // Individual payslip actions
    $(document).on('click', '.send-email-btn', function() {
        var payslipId = $(this).data('payslip-id');
        var email = $(this).data('email');
        sendPayslipEmail(payslipId, email);
    });
    
}

// Bulk Payslip Generation Functions
function openBulkPayslipModal() {
    $('#bulkPayslipModal').modal('show');
    loadEmployeesForBulk();
}

function loadEmployeesForBulk() {
    var department = $('#bulk-department').val();
    
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'get_employees_for_bulk',
            department: department
        },
        success: function(response) {
            if (response.success) {
                displayEmployeesForBulk(response.employees);
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to load employees', 'error');
        }
    });
}

function displayEmployeesForBulk(employees) {
    var html = '';
    employees.forEach(function(emp) {
        html += `
            <div class="form-check employee-item">
                <input class="form-check-input employee-checkbox" type="checkbox" 
                       value="${emp.employee_id}" id="emp_${emp.employee_id}">
                <label class="form-check-label" for="emp_${emp.employee_id}">
                    <strong>${emp.name}</strong> (${emp.employee_code})<br>
                    <small class="text-muted">${emp.department_name} - ${emp.position}</small><br>
                    <small class="text-success">₹${numberFormat(emp.monthly_salary)}/month</small>
                </label>
            </div>
        `;
    });
    
    $('#employees-list').html(html);
    $('#employee-count').text(employees.length + ' employees found');
}

function toggleAllEmployees(checked) {
    $('.employee-checkbox').prop('checked', checked);
}

function generateBulkPayslips() {
    var selectedEmployees = [];
    $('.employee-checkbox:checked').each(function() {
        selectedEmployees.push($(this).val());
    });
    
    if (selectedEmployees.length === 0) {
        showAlert('Warning', 'Please select at least one employee', 'warning');
        return;
    }
    
    var payPeriod = $('#bulk-pay-period').val();
    
    $('#generate-bulk-payslips').prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Generating...'
    );
    
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'generate_bulk_payslips',
            employee_ids: selectedEmployees,
            pay_period: payPeriod
        },
        success: function(response) {
            $('#generate-bulk-payslips').prop('disabled', false).html('Generate Payslips');
            
            if (response.success) {
                showAlert('Success', response.message, 'success');
                $('#bulkPayslipModal').modal('hide');
                // Refresh the page to show new payslips
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            $('#generate-bulk-payslips').prop('disabled', false).html('Generate Payslips');
            showAlert('Error', 'Failed to generate payslips', 'error');
        }
    });
}

// Payroll Summary Functions
function openPayrollSummaryModal() {
    $('#payrollSummaryModal').modal('show');
    loadPayrollSummary();
}

function loadPayrollSummary() {
    var period = $('#summary-period').val();
    var department = $('#summary-department').val();
    
    $('#summary-loading').show();
    $('#summary-content').hide();
    
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'get_payroll_summary',
            period: period,
            department: department
        },
        success: function(response) {
            $('#summary-loading').hide();
            $('#summary-content').show();
            
            if (response.success) {
                displayPayrollSummary(response);
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            $('#summary-loading').hide();
            showAlert('Error', 'Failed to load summary', 'error');
        }
    });
}

function displayPayrollSummary(data) {
    var summary = data.summary;
    
    // Display summary statistics
    var summaryHtml = `
        <div class="row text-center mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4>${summary.total_payslips || 0}</h4>
                        <small>Total Payslips</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h4>₹${numberFormat(summary.total_gross || 0)}</h4>
                        <small>Total Gross</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h4>₹${numberFormat(summary.total_deductions || 0)}</h4>
                        <small>Total Deductions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h4>₹${numberFormat(summary.total_net || 0)}</h4>
                        <small>Net Payable</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Display department breakdown
    var deptHtml = '<h5>Department-wise Breakdown</h5><div class="table-responsive">';
    deptHtml += '<table class="table table-striped"><thead><tr>';
    deptHtml += '<th>Department</th><th>Employees</th><th>Gross Amount</th><th>Net Amount</th>';
    deptHtml += '</tr></thead><tbody>';
    
    data.departments.forEach(function(dept) {
        deptHtml += `
            <tr>
                <td>${dept.department}</td>
                <td>${dept.emp_count}</td>
                <td>₹${numberFormat(dept.dept_gross)}</td>
                <td>₹${numberFormat(dept.dept_net)}</td>
            </tr>
        `;
    });
    
    deptHtml += '</tbody></table></div>';
    
    $('#summary-stats').html(summaryHtml);
    $('#summary-details').html(deptHtml);
}

// Template Selection Functions
function openTemplateSelectionModal() {
    $('#templateSelectionModal').modal('show');
}

function applySelectedTemplate() {
    var template = $('#template-select').val();
    var format = $('#format-select').val();
    
    if (!template) {
        showAlert('Warning', 'Please select a template', 'warning');
        return;
    }
    
    // Store selections in localStorage or session
    localStorage.setItem('selected_template', template);
    localStorage.setItem('selected_format', format);
    
    $('#templateSelectionModal').modal('hide');
    showAlert('Success', 'Template and format applied successfully', 'success');
}

// Payroll Settings Functions
function openPayrollSettingsModal() {
    $('#payrollSettingsModal').modal('show');
    loadPayrollSettings();
}

function loadPayrollSettings() {
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'get_payroll_settings'
        },
        success: function(response) {
            if (response.success) {
                populateSettingsForm(response.settings);
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to load settings', 'error');
        }
    });
}

function populateSettingsForm(settings) {
    // Populate form fields with current settings
    Object.keys(settings).forEach(function(key) {
        var input = $('#' + key.replace(/_/g, '-'));
        if (input.length > 0) {
            if (input.attr('type') === 'checkbox') {
                input.prop('checked', settings[key] === '1' || settings[key] === 'true');
            } else {
                input.val(settings[key]);
            }
        }
    });
}

function savePayrollSettings() {
    var settings = {};
    
    // Collect all form data
    $('#payrollSettingsForm input, #payrollSettingsForm select, #payrollSettingsForm textarea').each(function() {
        var name = $(this).attr('name');
        var value = $(this).val();
        
        if ($(this).attr('type') === 'checkbox') {
            value = $(this).is(':checked') ? '1' : '0';
        }
        
        if (name) {
            settings[name] = value;
        }
    });
    
    $('#save-settings').prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Saving...'
    );
    
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'save_payroll_settings',
            settings: settings
        },
        success: function(response) {
            $('#save-settings').prop('disabled', false).html('Save Settings');
            
            if (response.success) {
                showAlert('Success', response.message, 'success');
                $('#payrollSettingsModal').modal('hide');
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            $('#save-settings').prop('disabled', false).html('Save Settings');
            showAlert('Error', 'Failed to save settings', 'error');
        }
    });
}

// Export Functions
function openExportModal() {
    $('#exportModal').modal('show');
}

function exportPayslips() {
    var format = $('#export-format').val();
    var period = $('#export-period').val();
    var department = $('#export-department').val();
    
    $('#export-data').prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Exporting...'
    );
    
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'export_payslips',
            format: format,
            period: period,
            department: department
        },
        success: function(response) {
            $('#export-data').prop('disabled', false).html('Export Data');
            
            if (response.success) {
                if (format === 'csv' && response.data) {
                    downloadFile(response.filename, response.data, response.type);
                } else {
                    showAlert('Success', 'Export prepared successfully', 'success');
                }
                $('#exportModal').modal('hide');
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            $('#export-data').prop('disabled', false).html('Export Data');
            showAlert('Error', 'Failed to export data', 'error');
        }
    });
}

// Email Functions
function sendPayslipEmail(payslipId, email) {
    $.ajax({
        url: '../api/payroll_api.php',
        method: 'POST',
        data: {
            action: 'send_payslip_email',
            payslip_id: payslipId,
            email: email
        },
        success: function(response) {
            if (response.success) {
                showAlert('Success', response.message, 'success');
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to send email', 'error');
        }
    });
}

// Utility Functions
function getCurrentPeriod() {
    var date = new Date();
    var year = date.getFullYear();
    var month = (date.getMonth() + 1).toString().padStart(2, '0');
    return year + '-' + month;
}

function numberFormat(number) {
    return new Intl.NumberFormat('en-IN').format(number);
}

function downloadFile(filename, data, type) {
    var blob = new Blob([atob(data)], { type: type });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function showAlert(title, message, type) {
    var alertClass = 'alert-info';
    switch(type) {
        case 'success': alertClass = 'alert-success'; break;
        case 'error': alertClass = 'alert-danger'; break;
        case 'warning': alertClass = 'alert-warning'; break;
    }
    
    var alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Show alert at the top of the page
    if ($('#alert-container').length === 0) {
        $('body').prepend('<div id="alert-container" class="position-fixed top-0 start-50 translate-middle-x" style="z-index: 9999; width: 500px; margin-top: 20px;"></div>');
    }
    
    $('#alert-container').html(alertHtml);
    
    // Auto hide after 5 seconds
    setTimeout(function() {
        $('#alert-container .alert').alert('close');
    }, 5000);
}

// Individual payslip functions for existing payslips
function viewPayslip(payslipId) {
    window.open(`view_payslip.php?id=${payslipId}`, '_blank');
}

function editPayslip(payslipId) {
    window.location.href = `edit_payslip.php?id=${payslipId}`;
}

function deletePayslip(payslipId) {
    if (confirm('Are you sure you want to delete this payslip?')) {
        $.ajax({
            url: '../api/payroll_api.php',
            method: 'POST',
            data: {
                action: 'delete_payslip',
                payslip_id: payslipId
            },
            success: function(response) {
                if (response.success) {
                    showAlert('Success', 'Payslip deleted successfully', 'success');
                    location.reload();
                } else {
                    showAlert('Error', response.message, 'error');
                }
            },
            error: function() {
                showAlert('Error', 'Failed to delete payslip', 'error');
            }
        });
    }
}
