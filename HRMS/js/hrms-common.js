// Universal HRMS JavaScript Functions
// Common functionality for all HRMS modules

// Global HRMS configuration
window.HRMS = {
    ajaxUrl: 'ajax_handler.php',
    currentUser: null,
    currentRole: null
};

// Initialize HRMS JavaScript when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips and popovers
    initializeBootstrapComponents();
    
    // Set up global event handlers
    setupGlobalEventHandlers();
    
    // Auto-refresh functionality for dashboard
    if (window.location.pathname.includes('index.php') || 
        window.location.pathname.endsWith('/HRMS/')) {
        startDashboardAutoRefresh();
    }
});

// Initialize Bootstrap components
function initializeBootstrapComponents() {
    // Initialize tooltips
    const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(el => new bootstrap.Tooltip(el));
    
    // Initialize popovers
    const popoverElements = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverElements.forEach(el => new bootstrap.Popover(el));
    
    // Initialize dropdowns
    const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    dropdownElements.forEach(el => new bootstrap.Dropdown(el));
}

// Setup global event handlers
function setupGlobalEventHandlers() {
    // Handle all delete buttons with confirmation
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-delete') || 
            e.target.closest('.btn-delete')) {
            e.preventDefault();
            const btn = e.target.classList.contains('btn-delete') ? e.target : e.target.closest('.btn-delete');
            confirmDelete(btn);
        }
    });
    
    // Handle all edit buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-edit') || 
            e.target.closest('.btn-edit')) {
            e.preventDefault();
            const btn = e.target.classList.contains('btn-edit') ? e.target : e.target.closest('.btn-edit');
            handleEdit(btn);
        }
    });
    
    // Handle form submissions with AJAX
    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('ajax-form')) {
            e.preventDefault();
            submitForm(e.target);
        }
    });
}

// Modal Management Functions
function showModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        return modal;
    }
    console.error('Modal not found:', modalId);
    return null;
}

function hideModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }
}

function hideAllModals() {
    document.querySelectorAll('.modal.show').forEach(modal => {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    });
}

// AJAX Functions
function ajaxRequest(action, data = {}, callback = null) {
    const formData = new FormData();
    formData.append('action', action);
    
    // Add data to form
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    fetch(window.HRMS.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (callback) {
            callback(data);
        } else {
            handleAjaxResponse(data);
        }
    })
    .catch(error => {
        console.error('AJAX Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

function handleAjaxResponse(response) {
    if (response.success) {
        showNotification(response.message || 'Operation completed successfully', 'success');
        
        // Auto-reload if specified
        if (response.reload) {
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    } else {
        showNotification(response.message || 'An error occurred', 'error');
    }
}

// Form submission with AJAX
function submitForm(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    
    // Disable submit button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    }
    
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        handleAjaxResponse(data);
        
        if (data.success) {
            // Reset form on success
            form.reset();
            
            // Close modal if form is in a modal
            const modal = form.closest('.modal');
            if (modal) {
                bootstrap.Modal.getInstance(modal)?.hide();
            }
        }
    })
    .catch(error => {
        console.error('Form submission error:', error);
        showNotification('Form submission failed. Please try again.', 'error');
    })
    .finally(() => {
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Record Management Functions
function loadRecord(id, table, modalId = null) {
    ajaxRequest('get_record', {
        id: id,
        table: table
    }, (response) => {
        if (response.success) {
            populateForm(response.data, modalId);
            if (modalId) {
                showModal(modalId);
            }
        } else {
            showNotification(response.message, 'error');
        }
    });
}

function populateForm(data, modalId = null) {
    const container = modalId ? document.getElementById(modalId) : document;
    
    Object.keys(data).forEach(key => {
        const field = container.querySelector(`[name="${key}"]`) || 
                     container.querySelector(`#${key}`);
        
        if (field) {
            if (field.type === 'checkbox') {
                field.checked = data[key] == 1 || data[key] === 'true';
            } else if (field.type === 'radio') {
                const radio = container.querySelector(`[name="${key}"][value="${data[key]}"]`);
                if (radio) radio.checked = true;
            } else {
                field.value = data[key] || '';
            }
        }
    });
}

function confirmDelete(element) {
    const id = element.dataset.id;
    const table = element.dataset.table;
    const itemName = element.dataset.name || 'this item';
    
    if (!id || !table) {
        showNotification('Missing required data for deletion', 'error');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
        deleteRecord(id, table);
    }
}

function deleteRecord(id, table) {
    ajaxRequest('delete_record', {
        id: id,
        table: table
    }, (response) => {
        if (response.success) {
            showNotification(response.message, 'success');
            // Remove the row from table if it exists
            const row = document.querySelector(`[data-id="${id}"]`)?.closest('tr');
            if (row) {
                row.remove();
            } else {
                // Reload page if we can't find the specific row
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            showNotification(response.message, 'error');
        }
    });
}

function handleEdit(element) {
    const id = element.dataset.id;
    const table = element.dataset.table;
    const modalId = element.dataset.modal;
    
    if (id && table) {
        loadRecord(id, table, modalId);
    }
}

// Notification System
function showNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.hrms-notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `hrms-notification alert alert-${getBootstrapAlertClass(type)} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
            <div>${message}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }
}

function getBootstrapAlertClass(type) {
    const map = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    return map[type] || 'info';
}

function getNotificationIcon(type) {
    const map = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle'
    };
    return map[type] || 'info-circle';
}

// Dashboard Auto-refresh
function startDashboardAutoRefresh() {
    setInterval(() => {
        refreshDashboardStats();
    }, 30000); // Refresh every 30 seconds
}

function refreshDashboardStats() {
    ajaxRequest('get_dashboard_stats', {}, (response) => {
        if (response.success) {
            updateDashboardDisplay(response.data);
        }
    });
}

function updateDashboardDisplay(stats) {
    // Update stat cards if they exist
    Object.keys(stats).forEach(key => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            element.textContent = stats[key];
        }
    });
}

// Export Functions
function exportData(table, format = 'csv') {
    ajaxRequest('export_data', {
        table: table,
        format: format
    }, (response) => {
        if (response.success && response.download_url) {
            window.open(response.download_url, '_blank');
        } else {
            showNotification(response.message || 'Export failed', 'error');
        }
    });
}

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
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

// Search and Filter Functions
function setupDataTableFilters() {
    // Live search functionality
    const searchInputs = document.querySelectorAll('.hrms-search');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            filterTable(this.value, this.dataset.target);
        }, 300));
    });
    
    // Status filters
    const statusFilters = document.querySelectorAll('.hrms-status-filter');
    statusFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            filterByStatus(this.value, this.dataset.target);
        });
    });
}

function filterTable(searchTerm, targetTable) {
    const table = document.getElementById(targetTable);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
    
    // Update row count
    const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])').length;
    updateTableInfo(targetTable, visibleRows, rows.length);
}

function filterByStatus(status, targetTable) {
    const table = document.getElementById(targetTable);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        if (!status || status === 'all') {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('[data-status]');
            const rowStatus = statusCell ? statusCell.dataset.status : '';
            row.style.display = rowStatus === status ? '' : 'none';
        }
    });
}

function updateTableInfo(tableId, visible, total) {
    const infoElement = document.querySelector(`#${tableId}-info`);
    if (infoElement) {
        infoElement.textContent = `Showing ${visible} of ${total} entries`;
    }
}

// Initialize data table features when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupDataTableFilters();
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error caught:', e.error);
    // You could send error reports to server here
});

// Expose main functions to global scope for inline event handlers
window.HRMS_Functions = {
    showModal,
    hideModal,
    loadRecord,
    deleteRecord,
    exportData,
    showNotification,
    ajaxRequest
};
