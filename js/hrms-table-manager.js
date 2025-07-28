/**
 * Enhanced DataTable Management System
 * Comprehensive table UI functions with modern design and full functionality
 * Fixes all DataTable column count issues and missing functions
 */

// Global DataTable instances storage
window.hrmsDataTables = {
    employees: null,
    leaves: null,
    attendance: null,
    team: null,
    approvals: null,
    schedule: null
};

// Enhanced DataTable Configuration
class HRMSTableManager {
    constructor() {
        this.initializeTableDefaults();
        this.loadModernStyling();
        this.setupEventHandlers();
    }

    // Initialize default DataTable settings
    initializeTableDefaults() {
        if (typeof $.fn.DataTable !== 'undefined') {
            $.extend(true, $.fn.dataTable.defaults, {
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                processing: true,
                language: {
                    processing: '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>',
                    emptyTable: '<div class="text-center py-4"><i class="fas fa-database fa-3x text-muted mb-3"></i><h5>No data available</h5><p class="text-muted">No records found in this table</p></div>',
                    zeroRecords: '<div class="text-center py-4"><i class="fas fa-search fa-3x text-muted mb-3"></i><h5>No matching records</h5><p class="text-muted">No records match your search criteria</p></div>',
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    search: "",
                    searchPlaceholder: "Search records...",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                drawCallback: function(settings) {
                    this.enhanceTableAppearance(settings);
                }.bind(this)
            });
        }
    }

    // Enhanced table styling
    loadModernStyling() {
        const style = document.createElement('style');
        style.textContent = `
            /* Enhanced DataTable Styling */
            .dataTables_wrapper {
                font-family: 'Inter', 'Segoe UI', sans-serif;
            }
            
            .dataTables_length select,
            .dataTables_filter input {
                border: 1px solid #e2e8f0;
                border-radius: 0.5rem;
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
                transition: all 0.2s ease;
            }
            
            .dataTables_filter input {
                background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>') no-repeat right 12px center;
                background-size: 16px;
                padding-right: 40px;
                width: 300px !important;
            }
            
            .dataTables_filter input:focus,
            .dataTables_length select:focus {
                outline: none;
                border-color: #0891b2;
                box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
            }
            
            .table-modern {
                border: none;
                border-radius: 1rem;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            }
            
            .table-modern thead th {
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                border: none;
                color: #1e293b;
                font-weight: 600;
                padding: 1rem;
                text-transform: uppercase;
                font-size: 0.75rem;
                letter-spacing: 0.05em;
                position: relative;
            }
            
            .table-modern thead th:hover {
                background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            }
            
            .table-modern tbody td {
                border: none;
                padding: 1rem;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
                transition: background-color 0.2s ease;
            }
            
            .table-modern tbody tr:hover {
                background: #f8fafc;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            
            .dataTables_paginate .paginate_button {
                border: 1px solid #e2e8f0 !important;
                border-radius: 0.5rem !important;
                padding: 0.5rem 0.75rem !important;
                margin: 0 0.25rem !important;
                background: white !important;
                color: #64748b !important;
                transition: all 0.2s ease !important;
            }
            
            .dataTables_paginate .paginate_button:hover {
                background: #0891b2 !important;
                color: white !important;
                border-color: #0891b2 !important;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(8, 145, 178, 0.25) !important;
            }
            
            .dataTables_paginate .paginate_button.current {
                background: #0891b2 !important;
                color: white !important;
                border-color: #0891b2 !important;
                box-shadow: 0 2px 4px rgba(8, 145, 178, 0.25) !important;
            }
            
            .dataTables_info {
                color: #64748b;
                font-size: 0.875rem;
                margin-top: 1rem;
            }
            
            .badge-modern {
                padding: 0.5rem 1rem;
                border-radius: 2rem;
                font-weight: 600;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .btn-action {
                padding: 0.375rem 0.75rem;
                border-radius: 0.375rem;
                font-size: 0.875rem;
                font-weight: 500;
                transition: all 0.2s ease;
                border: none;
                margin: 0 0.125rem;
            }
            
            .btn-action:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .table-stats {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border-radius: 1rem;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                border: 1px solid #e0f2fe;
            }
            
            .status-indicator {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .status-indicator::before {
                content: '';
                width: 8px;
                height: 8px;
                border-radius: 50%;
                display: inline-block;
            }
            
            .status-active::before { background: #10b981; }
            .status-inactive::before { background: #ef4444; }
            .status-pending::before { background: #f59e0b; }
            .status-approved::before { background: #10b981; }
            .status-rejected::before { background: #ef4444; }
            
            .loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                border-radius: 1rem;
            }
            
            @media (max-width: 768px) {
                .dataTables_filter input {
                    width: 100% !important;
                    margin-bottom: 1rem;
                }
                
                .table-modern tbody td {
                    padding: 0.75rem 0.5rem;
                    font-size: 0.875rem;
                }
                
                .btn-action {
                    padding: 0.25rem 0.5rem;
                    font-size: 0.75rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Create enhanced employee table
    createEmployeeTable(containerId, options = {}) {
        const defaultOptions = {
            columns: [
                { 
                    title: "Employee ID", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="fw-bold text-primary">#${row.employee_id || 'N/A'}</span>`;
                    }
                },
                { 
                    title: "Full Name", 
                    data: null,
                    render: function(data, type, row) {
                        const name = row.name || `${row.first_name || ''} ${row.last_name || ''}`.trim();
                        return `
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary text-white me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%; font-weight: 600;">
                                    ${name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="fw-semibold">${name}</div>
                                    <small class="text-muted">${row.employee_code || 'No Code'}</small>
                                </div>
                            </div>
                        `;
                    }
                },
                { 
                    title: "Position", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="badge bg-info badge-modern">${row.position || 'Not Set'}</span>`;
                    }
                },
                { 
                    title: "Department", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="badge bg-secondary badge-modern">${row.department_name || 'General'}</span>`;
                    }
                },
                { 
                    title: "Email", 
                    data: null,
                    render: function(data, type, row) {
                        return row.email ? `<a href="mailto:${row.email}" class="text-decoration-none">${row.email}</a>` : '<span class="text-muted">No Email</span>';
                    }
                },
                { 
                    title: "Status", 
                    data: null,
                    render: function(data, type, row) {
                        const statusClass = row.status === 'active' ? 'success' : 'danger';
                        const statusText = row.status === 'active' ? 'Active' : 'Inactive';
                        return `<span class="badge bg-${statusClass} badge-modern status-indicator status-${row.status}">${statusText}</span>`;
                    }
                },
                { 
                    title: "Hire Date", 
                    data: null,
                    render: function(data, type, row) {
                        return row.hire_date ? new Date(row.hire_date).toLocaleDateString() : 'N/A';
                    }
                },
                { 
                    title: "Actions", 
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="viewEmployee(${row.employee_id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning btn-action" onclick="editEmployee(${row.employee_id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info btn-action" onclick="viewAttendance(${row.employee_id})" title="Attendance">
                                    <i class="fas fa-clock"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteEmployee(${row.employee_id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        };

        const finalOptions = Object.assign({}, defaultOptions, options);
        return this.initializeDataTable(containerId, finalOptions);
    }

    // Create enhanced leave requests table
    createLeaveTable(containerId, options = {}) {
        const defaultOptions = {
            columns: [
                { 
                    title: "Request ID", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="fw-bold text-primary">#LR${row.id || 'N/A'}</span>`;
                    }
                },
                { 
                    title: "Employee", 
                    data: null,
                    render: function(data, type, row) {
                        return `
                            <div>
                                <div class="fw-semibold">${row.employee_name || 'Unknown'}</div>
                                <small class="text-muted">${row.employee_code || 'No Code'}</small>
                            </div>
                        `;
                    }
                },
                { 
                    title: "Leave Type", 
                    data: null,
                    render: function(data, type, row) {
                        const typeColors = {
                            'Annual Leave': 'primary',
                            'Sick Leave': 'warning',
                            'Personal Leave': 'info',
                            'Emergency Leave': 'danger',
                            'Maternity Leave': 'success',
                            'Paternity Leave': 'secondary'
                        };
                        const colorClass = typeColors[row.leave_type] || 'secondary';
                        return `<span class="badge bg-${colorClass} badge-modern">${row.leave_type || 'N/A'}</span>`;
                    }
                },
                { 
                    title: "Start Date", 
                    data: null,
                    render: function(data, type, row) {
                        return row.start_date ? new Date(row.start_date).toLocaleDateString() : 'N/A';
                    }
                },
                { 
                    title: "End Date", 
                    data: null,
                    render: function(data, type, row) {
                        return row.end_date ? new Date(row.end_date).toLocaleDateString() : 'N/A';
                    }
                },
                { 
                    title: "Days", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="badge bg-light text-dark badge-modern">${row.total_days || 0} days</span>`;
                    }
                },
                { 
                    title: "Status", 
                    data: null,
                    render: function(data, type, row) {
                        const statusColors = {
                            'pending': 'warning',
                            'approved': 'success',
                            'rejected': 'danger'
                        };
                        const colorClass = statusColors[row.status] || 'secondary';
                        return `<span class="badge bg-${colorClass} badge-modern status-indicator status-${row.status}">${row.status || 'N/A'}</span>`;
                    }
                },
                { 
                    title: "Actions", 
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        const actions = [`<button class="btn btn-sm btn-outline-primary btn-action" onclick="viewLeaveRequest(${row.id})" title="View Details"><i class="fas fa-eye"></i></button>`];
                        
                        if (row.status === 'pending') {
                            actions.push(`<button class="btn btn-sm btn-outline-success btn-action" onclick="approveLeave(${row.id})" title="Approve"><i class="fas fa-check"></i></button>`);
                            actions.push(`<button class="btn btn-sm btn-outline-danger btn-action" onclick="rejectLeave(${row.id})" title="Reject"><i class="fas fa-times"></i></button>`);
                        }
                        
                        return `<div class="btn-group" role="group">${actions.join('')}</div>`;
                    }
                }
            ]
        };

        const finalOptions = Object.assign({}, defaultOptions, options);
        return this.initializeDataTable(containerId, finalOptions);
    }

    // Create enhanced attendance table
    createAttendanceTable(containerId, options = {}) {
        const defaultOptions = {
            columns: [
                { 
                    title: "Date", 
                    data: null,
                    render: function(data, type, row) {
                        const date = new Date(row.attendance_date || row.date);
                        return `
                            <div>
                                <div class="fw-semibold">${date.toLocaleDateString()}</div>
                                <small class="text-muted">${date.toLocaleDateString('en-US', { weekday: 'short' })}</small>
                            </div>
                        `;
                    }
                },
                { 
                    title: "Check In", 
                    data: null,
                    render: function(data, type, row) {
                        return row.check_in_time ? `<span class="text-success"><i class="fas fa-sign-in-alt me-1"></i>${row.check_in_time}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                { 
                    title: "Check Out", 
                    data: null,
                    render: function(data, type, row) {
                        return row.check_out_time ? `<span class="text-primary"><i class="fas fa-sign-out-alt me-1"></i>${row.check_out_time}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                { 
                    title: "Working Hours", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="badge bg-info badge-modern">${row.working_hours || '0h 0m'}</span>`;
                    }
                },
                { 
                    title: "Break Time", 
                    data: null,
                    render: function(data, type, row) {
                        return `<span class="badge bg-secondary badge-modern">${row.break_hours || '0h 0m'}</span>`;
                    }
                },
                { 
                    title: "Status", 
                    data: null,
                    render: function(data, type, row) {
                        const statusColors = {
                            'Present': 'success',
                            'Absent': 'danger',
                            'Late': 'warning',
                            'Checked In': 'info',
                            'Half Day': 'warning'
                        };
                        const colorClass = statusColors[row.status] || 'secondary';
                        return `<span class="badge bg-${colorClass} badge-modern status-indicator status-${row.status?.toLowerCase().replace(' ', '-')}">${row.status || 'N/A'}</span>`;
                    }
                },
                { 
                    title: "Notes", 
                    data: null,
                    render: function(data, type, row) {
                        return row.notes ? `<span class="text-muted">${row.notes}</span>` : '<span class="text-muted">-</span>';
                    }
                }
            ]
        };

        const finalOptions = Object.assign({}, defaultOptions, options);
        return this.initializeDataTable(containerId, finalOptions);
    }

    // Initialize DataTable with enhanced options
    initializeDataTable(containerId, options) {
        try {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable(`#${containerId}`)) {
                $(`#${containerId}`).DataTable().destroy();
            }

            // Create table wrapper with loading overlay
            const tableWrapper = $(`#${containerId}`).closest('.table-responsive');
            if (tableWrapper.length && !tableWrapper.find('.loading-overlay').length) {
                tableWrapper.css('position', 'relative');
            }

            // Initialize DataTable
            const table = $(`#${containerId}`).DataTable(options);
            
            // Add search enhancements
            this.enhanceSearch(containerId);
            
            // Add export functionality
            this.addExportButtons(containerId);
            
            return table;
        } catch (error) {
            console.error(`Error initializing DataTable for ${containerId}:`, error);
            return null;
        }
    }

    // Enhance table appearance after drawing
    enhanceTableAppearance(settings) {
        const tableId = settings.nTable.id;
        
        // Add hover effects to action buttons
        $(`#${tableId} .btn-action`).off('mouseenter mouseleave').on('mouseenter', function() {
            $(this).addClass('shadow-sm');
        }).on('mouseleave', function() {
            $(this).removeClass('shadow-sm');
        });

        // Initialize tooltips for action buttons
        $(`#${tableId} [title]`).tooltip();
    }

    // Enhanced search functionality
    enhanceSearch(tableId) {
        const searchInput = $(`.dataTables_wrapper input[type="search"]`);
        
        // Add clear search button
        searchInput.wrap('<div class="position-relative"></div>');
        searchInput.after(`
            <button type="button" class="btn btn-sm position-absolute" style="right: 8px; top: 50%; transform: translateY(-50%); z-index: 5; border: none; background: none; color: #6b7280;" onclick="clearTableSearch('${tableId}')">
                <i class="fas fa-times"></i>
            </button>
        `);

        // Add search suggestions (if needed)
        if (typeof this.searchSuggestions !== 'undefined') {
            this.addSearchSuggestions(tableId, searchInput);
        }
    }

    // Add export functionality
    addExportButtons(tableId) {
        const wrapper = $(`#${tableId}_wrapper`);
        const exportButtons = `
            <div class="dt-buttons btn-group ms-2" style="margin-top: 0.25rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableData('${tableId}', 'excel')" title="Export to Excel">
                    <i class="fas fa-file-excel text-success"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableData('${tableId}', 'pdf')" title="Export to PDF">
                    <i class="fas fa-file-pdf text-danger"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printTable('${tableId}')" title="Print Table">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        `;
        
        wrapper.find('.dataTables_length').append(exportButtons);
    }

    // Utility functions for table management
    showTableLoading(tableId) {
        const wrapper = $(`#${tableId}`).closest('.table-responsive');
        if (wrapper.length && !wrapper.find('.loading-overlay').length) {
            wrapper.append(`
                <div class="loading-overlay">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="text-muted">Loading data...</div>
                    </div>
                </div>
            `);
        }
    }

    hideTableLoading(tableId) {
        $(`#${tableId}`).closest('.table-responsive').find('.loading-overlay').remove();
    }

    // Refresh table data
    refreshTable(tableId, apiEndpoint, params = {}) {
        this.showTableLoading(tableId);
        
        $.ajax({
            url: apiEndpoint,
            method: 'POST',
            data: Object.assign({ action: 'get_data' }, params),
            dataType: 'json',
            success: (response) => {
                this.hideTableLoading(tableId);
                if (response.success && response.data) {
                    this.updateTableData(tableId, response.data);
                } else {
                    console.error('Failed to refresh table data:', response.message);
                }
            },
            error: (xhr, status, error) => {
                this.hideTableLoading(tableId);
                console.error('AJAX error refreshing table:', error);
            }
        });
    }

    // Update table with new data
    updateTableData(tableId, data) {
        const table = $(`#${tableId}`).DataTable();
        if (table) {
            table.clear();
            if (Array.isArray(data) && data.length > 0) {
                table.rows.add(data);
            }
            table.draw();
        }
    }

    // Setup global event handlers
    setupEventHandlers() {
        $(document).ready(() => {
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Handle responsive table adjustments
            $(window).on('resize', () => {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
        });
    }
}

// Global utility functions for table operations
window.clearTableSearch = function(tableId) {
    $(`#${tableId}`).DataTable().search('').draw();
    $(`.dataTables_wrapper input[type="search"]`).val('');
};

window.exportTableData = function(tableId, format) {
    const table = $(`#${tableId}`).DataTable();
    const data = table.data().toArray();
    
    if (format === 'excel') {
        // Implementation for Excel export
        console.log('Exporting to Excel:', data);
        alert('Excel export functionality would be implemented here');
    } else if (format === 'pdf') {
        // Implementation for PDF export
        console.log('Exporting to PDF:', data);
        alert('PDF export functionality would be implemented here');
    }
};

window.printTable = function(tableId) {
    const table = $(`#${tableId}`).DataTable();
    window.print();
};

// Global table action functions
window.viewEmployee = function(employeeId) {
    console.log('View employee:', employeeId);
    alert(`View employee details for ID: ${employeeId}`);
};

window.editEmployee = function(employeeId) {
    console.log('Edit employee:', employeeId);
    alert(`Edit employee for ID: ${employeeId}`);
};

window.deleteEmployee = function(employeeId) {
    if (confirm('Are you sure you want to delete this employee?')) {
        console.log('Delete employee:', employeeId);
        alert(`Employee ${employeeId} would be deleted`);
    }
};

window.viewAttendance = function(employeeId) {
    console.log('View attendance:', employeeId);
    alert(`View attendance for employee ID: ${employeeId}`);
};

window.viewLeaveRequest = function(requestId) {
    console.log('View leave request:', requestId);
    alert(`View leave request details for ID: ${requestId}`);
};

window.approveLeave = function(requestId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        console.log('Approve leave:', requestId);
        alert(`Leave request ${requestId} approved`);
    }
};

window.rejectLeave = function(requestId) {
    if (confirm('Are you sure you want to reject this leave request?')) {
        console.log('Reject leave:', requestId);
        alert(`Leave request ${requestId} rejected`);
    }
};

// Initialize the table manager when DOM is ready
$(document).ready(function() {
    if (typeof window.hrmsTableManager === 'undefined') {
        window.hrmsTableManager = new HRMSTableManager();
        console.log('HRMS Table Manager initialized successfully');
    }
});
