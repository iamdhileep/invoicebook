// Customer Management JavaScript
// Comprehensive customer management functionality

$(document).ready(function() {
    initializeCustomerManagement();
});

let currentPage = 1;
let currentCustomerId = null;

function initializeCustomerManagement() {
    loadDashboardStats();
    loadCustomers();
    
    // Setup search input event
    $('#search-input').on('input', debounce(function() {
        currentPage = 1;
        loadCustomers();
    }, 500));
    
    // Setup enter key for search
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) {
            currentPage = 1;
            loadCustomers();
        }
    });
}

// Load dashboard statistics
function loadDashboardStats() {
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: { action: 'get_dashboard_stats' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const stats = response.stats;
                $('#total-customers').text(stats.total_customers);
                $('#active-customers').text(stats.active_customers);
                $('#new-this-month').text(stats.new_this_month);
                
                // Top customer value
                if (stats.top_customers && stats.top_customers.length > 0) {
                    $('#top-customer-value').text('₹' + numberFormat(stats.top_customers[0].total_amount));
                }
            }
        },
        error: function() {
            console.error('Failed to load dashboard stats');
        }
    });
}

// Load customers with pagination and filtering
function loadCustomers(page = 1) {
    currentPage = page;
    
    const formData = {
        action: 'get_customers',
        page: page,
        limit: $('#per-page').val() || 10,
        search: $('#search-input').val(),
        status: $('#status-filter').val()
    };
    
    $('#loading-indicator').show();
    $('#customer-table-container').hide();
    $('#no-customers').hide();
    
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            $('#loading-indicator').hide();
            
            if (response.success) {
                if (response.customers.length === 0) {
                    $('#no-customers').show();
                } else {
                    displayCustomers(response.customers);
                    setupPagination(response.page, response.total_pages, response.total);
                    $('#customer-table-container').show();
                }
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            $('#loading-indicator').hide();
            showAlert('Error', 'Failed to load customers', 'error');
        }
    });
}

// Display customers in table
function displayCustomers(customers) {
    const tbody = $('#customers-tbody');
    tbody.empty();
    
    customers.forEach(function(customer) {
        const statusBadge = customer.status === 'active' 
            ? '<span class="badge bg-success">Active</span>' 
            : '<span class="badge bg-secondary">Inactive</span>';
            
        const row = `
            <tr>
                <td>
                    <input type="checkbox" class="customer-checkbox" value="${customer.id}">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle me-3">
                            ${customer.customer_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <strong>${escapeHtml(customer.customer_name)}</strong><br>
                            <small class="text-muted">${customer.company_name ? escapeHtml(customer.company_name) : 'Individual Customer'}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <i class="fas fa-envelope me-1"></i>
                        ${customer.email ? escapeHtml(customer.email) : '<span class="text-muted">No email</span>'}
                    </div>
                    <div class="mt-1">
                        <i class="fas fa-phone me-1"></i>
                        ${escapeHtml(customer.phone)}
                    </div>
                    ${customer.website ? `<div class="mt-1"><i class="fas fa-globe me-1"></i><a href="${customer.website}" target="_blank">${escapeHtml(customer.website)}</a></div>` : ''}
                </td>
                <td>
                    <div>
                        ${customer.city ? escapeHtml(customer.city) : ''} 
                        ${customer.state ? (customer.city ? ', ' : '') + escapeHtml(customer.state) : ''}
                    </div>
                    ${customer.country ? `<small class="text-muted">${escapeHtml(customer.country)}</small>` : ''}
                </td>
                <td>
                    ${customer.tax_number ? `<div><strong>Tax:</strong> ${escapeHtml(customer.tax_number)}</div>` : ''}
                    ${customer.notes ? `<small class="text-muted">${escapeHtml(customer.notes.substring(0, 50))}${customer.notes.length > 50 ? '...' : ''}</small>` : ''}
                </td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="loadCustomerInvoices(${customer.id})">
                        <i class="fas fa-file-invoice"></i> View
                    </button>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewCustomer(${customer.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="editCustomer(${customer.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(${customer.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Setup pagination
function setupPagination(currentPageNum, totalPages, totalRecords) {
    const paginationInfo = $('#pagination-info');
    const paginationLinks = $('#pagination-links');
    
    const startRecord = ((currentPageNum - 1) * $('#per-page').val()) + 1;
    const endRecord = Math.min(currentPageNum * $('#per-page').val(), totalRecords);
    
    paginationInfo.html(`Showing ${startRecord} to ${endRecord} of ${totalRecords} customers`);
    
    paginationLinks.empty();
    
    if (totalPages <= 1) return;
    
    // Previous button
    if (currentPageNum > 1) {
        paginationLinks.append(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadCustomers(${currentPageNum - 1})">Previous</a>
            </li>
        `);
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPageNum - 2);
    const endPage = Math.min(totalPages, currentPageNum + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPageNum ? 'active' : '';
        paginationLinks.append(`
            <li class="page-item ${activeClass}">
                <a class="page-link" href="#" onclick="loadCustomers(${i})">${i}</a>
            </li>
        `);
    }
    
    // Next button
    if (currentPageNum < totalPages) {
        paginationLinks.append(`
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadCustomers(${currentPageNum + 1})">Next</a>
            </li>
        `);
    }
}

// Open add customer modal
function openAddCustomerModal() {
    currentCustomerId = null;
    $('#customerForm')[0].reset();
    $('#customer-id').val('');
    $('#customerModalLabel').html('<i class="fas fa-user-plus me-2"></i>Add New Customer');
    $('#save-button-text').text('Save Customer');
    $('#customerModal').modal('show');
}

// Edit customer
function editCustomer(customerId) {
    currentCustomerId = customerId;
    
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: {
            action: 'get_customer',
            id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const customer = response.customer;
                
                // Populate form
                $('#customer-id').val(customer.id);
                $('#customer-name').val(customer.customer_name);
                $('#company-name').val(customer.company_name);
                $('#email').val(customer.email);
                $('#phone').val(customer.phone);
                $('#website').val(customer.website);
                $('#status').val(customer.status);
                $('#address').val(customer.address);
                $('#city').val(customer.city);
                $('#state').val(customer.state);
                $('#postal-code').val(customer.postal_code);
                $('#country').val(customer.country);
                $('#tax-number').val(customer.tax_number);
                $('#notes').val(customer.notes);
                
                $('#customerModalLabel').html('<i class="fas fa-user-edit me-2"></i>Edit Customer');
                $('#save-button-text').text('Update Customer');
                $('#customerModal').modal('show');
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to load customer details', 'error');
        }
    });
}

// Save customer (create or update)
function saveCustomer() {
    const form = $('#customerForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', currentCustomerId ? 'update_customer' : 'create_customer');
    if (currentCustomerId) {
        formData.append('id', currentCustomerId);
    }
    
    const submitButton = $('#save-button-text');
    const originalText = submitButton.text();
    submitButton.text('Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            submitButton.text(originalText).prop('disabled', false);
            
            if (response.success) {
                $('#customerModal').modal('hide');
                showAlert('Success', response.message, 'success');
                loadCustomers(currentPage);
                loadDashboardStats();
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            submitButton.text(originalText).prop('disabled', false);
            showAlert('Error', 'Failed to save customer', 'error');
        }
    });
}

// View customer details
function viewCustomer(customerId) {
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: {
            action: 'get_customer',
            id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayCustomerDetails(response.customer);
                $('#customerDetailsModal').modal('show');
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to load customer details', 'error');
        }
    });
}

// Display customer details in modal
function displayCustomerDetails(customer) {
    const content = `
        <div class="row">
            <!-- Customer Info -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-user me-2"></i>Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><th>Name:</th><td>${escapeHtml(customer.customer_name)}</td></tr>
                            <tr><th>Company:</th><td>${customer.company_name ? escapeHtml(customer.company_name) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Email:</th><td>${customer.email ? escapeHtml(customer.email) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Phone:</th><td>${escapeHtml(customer.phone)}</td></tr>
                            <tr><th>Website:</th><td>${customer.website ? `<a href="${customer.website}" target="_blank">${escapeHtml(customer.website)}</a>` : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Status:</th><td>${customer.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Address Info -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-map-marker-alt me-2"></i>Address Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><th>Address:</th><td>${customer.address ? escapeHtml(customer.address) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>City:</th><td>${customer.city ? escapeHtml(customer.city) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>State:</th><td>${customer.state ? escapeHtml(customer.state) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Postal Code:</th><td>${customer.postal_code ? escapeHtml(customer.postal_code) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Country:</th><td>${customer.country ? escapeHtml(customer.country) : '<span class="text-muted">Not provided</span>'}</td></tr>
                            <tr><th>Tax Number:</th><td>${customer.tax_number ? escapeHtml(customer.tax_number) : '<span class="text-muted">Not provided</span>'}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Stats -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar me-2"></i>Invoice Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary">${customer.total_invoices || 0}</h4>
                                <small class="text-muted">Total Invoices</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success">₹${numberFormat(customer.total_amount || 0)}</h4>
                                <small class="text-muted">Total Amount</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-outline-info btn-sm" onclick="loadCustomerInvoices(${customer.id})">
                                <i class="fas fa-file-invoice me-2"></i>View All Invoices
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                    </div>
                    <div class="card-body">
                        <p>${customer.notes ? escapeHtml(customer.notes) : '<span class="text-muted">No notes available</span>'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="col-12 mt-3">
                <div class="d-flex gap-2">
                    <button class="btn btn-warning" onclick="editCustomer(${customer.id})">
                        <i class="fas fa-edit me-2"></i>Edit Customer
                    </button>
                    <button class="btn btn-info" onclick="loadCustomerInvoices(${customer.id})">
                        <i class="fas fa-file-invoice me-2"></i>View Invoices
                    </button>
                    <button class="btn btn-success" onclick="createInvoiceForCustomer(${customer.id})">
                        <i class="fas fa-plus me-2"></i>Create Invoice
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#customer-details-content').html(content);
}

// Delete customer
function deleteCustomer(customerId) {
    // Get customer details for confirmation
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: {
            action: 'get_customer',
            id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const customer = response.customer;
                currentCustomerId = customerId;
                
                $('#delete-customer-info').html(`
                    <strong>Customer:</strong> ${escapeHtml(customer.customer_name)}<br>
                    <strong>Phone:</strong> ${escapeHtml(customer.phone)}<br>
                    <strong>Company:</strong> ${customer.company_name ? escapeHtml(customer.company_name) : 'Individual Customer'}
                `);
                
                $('#deleteModal').modal('show');
            }
        }
    });
}

// Confirm delete
function confirmDelete() {
    if (!currentCustomerId) return;
    
    $.ajax({
        url: 'customer_api.php',
        method: 'POST',
        data: {
            action: 'delete_customer',
            id: currentCustomerId
        },
        dataType: 'json',
        success: function(response) {
            $('#deleteModal').modal('hide');
            
            if (response.success) {
                showAlert('Success', response.message, 'success');
                loadCustomers(currentPage);
                loadDashboardStats();
            } else {
                showAlert('Error', response.message, 'error');
            }
        },
        error: function() {
            showAlert('Error', 'Failed to delete customer', 'error');
        }
    });
}

// Import customers from invoices
function importFromInvoices() {
    if (confirm('This will import unique customers from your invoices. Continue?')) {
        $.ajax({
            url: 'customer_api.php',
            method: 'POST',
            data: { action: 'import_from_invoices' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Success', response.message, 'success');
                    loadCustomers();
                    loadDashboardStats();
                } else {
                    showAlert('Error', response.message, 'error');
                }
            },
            error: function() {
                showAlert('Error', 'Failed to import customers', 'error');
            }
        });
    }
}

// Load customer invoices (placeholder for future implementation)
function loadCustomerInvoices(customerId) {
    // This would integrate with invoice system
    showAlert('Info', 'Invoice integration coming soon!', 'info');
}

// Create invoice for customer (placeholder for future implementation)
function createInvoiceForCustomer(customerId) {
    // This would redirect to invoice creation with customer pre-filled
    window.location.href = '../invoice/invoice.php?customer_id=' + customerId;
}

// Export customers
function exportCustomers() {
    showAlert('Info', 'Export functionality coming soon!', 'info');
}

// Reset filters
function resetFilters() {
    $('#search-input').val('');
    $('#status-filter').val('');
    $('#per-page').val('10');
    $('#city-filter').val('');
    $('#state-filter').val('');
    $('#country-filter').val('');
    $('#company-filter').val('');
    currentPage = 1;
    loadCustomers();
}

// Toggle advanced search
function toggleAdvancedSearch() {
    $('#advanced-search').toggle();
}

// Toggle all customers selection
function toggleAllCustomers() {
    const selectAll = $('#select-all-customers').prop('checked');
    $('.customer-checkbox').prop('checked', selectAll);
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function numberFormat(number) {
    return new Intl.NumberFormat('en-IN').format(number || 0);
}

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

function showAlert(title, message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alertHtml = `
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
