<?php
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';

// Database-driven helpdesk ticket data
$helpdesk_tickets = [];
$query = "SELECT 
    ticket_id as id,
    employee_id,
    employee_name,
    subject,
    category,
    priority,
    status,
    created_date,
    assigned_to,
    description
FROM helpdesk_tickets 
ORDER BY created_date DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $helpdesk_tickets[] = [
            'id' => $row['id'],
            'employee_id' => 'EMP' . str_pad($row['employee_id'], 3, '0', STR_PAD_LEFT),
            'employee_name' => $row['employee_name'],
            'subject' => $row['subject'],
            'category' => $row['category'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'created_date' => $row['created_date'],
            'assigned_to' => $row['assigned_to'],
            'description' => $row['description']
        ];
    }
}

// Database-driven ticket categories
$ticket_categories = [];
$query = "SHOW COLUMNS FROM helpdesk_tickets LIKE 'category'";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $enum_str = $row['Type'];
    preg_match('/^enum\((.*)\)$/', $enum_str, $matches);
    if ($matches) {
        $ticket_categories = array_map(function($value) {
            return trim($value, "'");
        }, str_getcsv($matches[1], ',', "'"));
    }
}

// Fallback categories if database query fails
if (empty($ticket_categories)) {
    $ticket_categories = [
        'IT Support', 'HR Query', 'Payroll', 'Training', 'Benefits', 'Policies', 'Equipment', 'Other'
    ];
}

$current_page = 'employee_helpdesk';

require_once 'hrms_header_simple.php';
if (!isset($root_path)) 
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">Employee Helpdesk</h1>
                        <p class="text-muted mb-0">Manage employee tickets and support requests</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                            <i class="bi bi-plus me-2"></i>Create Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Helpdesk - HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard-modern.css" rel="stylesheet">
    <link href="../assets/css/global-styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
        }

        

        .content-header {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: #f8fafc;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .ticket-card {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
            position: relative;
        }

        .ticket-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .ticket-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 4px 0 0 4px;
        }

        .ticket-card.priority-high::before { background: var(--danger-color); }
        .ticket-card.priority-medium::before { background: var(--warning-color); }
        .ticket-card.priority-low::before { background: var(--success-color); }

        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high { background: #fecaca; color: #991b1b; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-low { background: #dcfce7; color: #166534; }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #fef3c7; color: #92400e; }
        .status-resolved { background: #dcfce7; color: #166534; }
        .status-closed { background: #f3f4f6; color: #6b7280; }

        .table-modern {
            border: none;
        }

        .table-modern thead th {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        .table-modern tbody td {
            border: none;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-modern tbody tr:hover {
            background: #f8fafc;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary.btn-modern {
            background: var(--primary-color);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }

        .btn-primary.btn-modern:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
        }

        .nav-tabs {
            border-bottom: 2px solid #f1f5f9;
            background: #f8fafc;
            padding: 0 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-pane {
            padding: 2rem;
        }

        .helpdesk-form {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .quick-action-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            
        }
    </style>
</head>
<body>
    <!-- Page Content Starts Here -->
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Employee Helpdesk</h1>
                    <p class="text-muted mb-0">Submit and track support requests for HR, IT, and other employee needs</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary btn-modern me-2" onclick="viewKnowledgeBase()">
                        <i class="bi bi-book me-2"></i>Knowledge Base
                    </button>
                    <button class="btn btn-primary btn-modern" onclick="createNewTicket()">
                        <i class="bi bi-plus-lg me-2"></i>New Ticket
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card" onclick="quickAction('password_reset')">
                    <i class="bi bi-key" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;"></i>
                    <h6 class="mb-1">Password Reset</h6>
                    <small class="text-muted">Reset your account password</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card" onclick="quickAction('leave_inquiry')">
                    <i class="bi bi-calendar-check" style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.5rem;"></i>
                    <h6 class="mb-1">Leave Inquiry</h6>
                    <small class="text-muted">Check leave balance</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card" onclick="quickAction('it_support')">
                    <i class="bi bi-laptop" style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.5rem;"></i>
                    <h6 class="mb-1">IT Support</h6>
                    <small class="text-muted">Technical assistance</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card" onclick="quickAction('payroll_query')">
                    <i class="bi bi-currency-dollar" style="font-size: 2rem; color: var(--info-color); margin-bottom: 0.5rem;"></i>
                    <h6 class="mb-1">Payroll Query</h6>
                    <small class="text-muted">Salary and benefits</small>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="content-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#my_tickets" role="tab">
                        <i class="bi bi-ticket-perforated me-2"></i>My Tickets
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_ticket" role="tab">
                        <i class="bi bi-plus-circle me-2"></i>Create Ticket
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#faq" role="tab">
                        <i class="bi bi-question-circle me-2"></i>FAQ
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#feedback" role="tab">
                        <i class="bi bi-chat-square-dots me-2"></i>Feedback
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- My Tickets Tab -->
                <div class="tab-pane fade show active" id="my_tickets" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($helpdesk_tickets as $ticket): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?= $ticket['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($ticket['subject']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars(substr($ticket['description'], 0, 50)) ?>...</div>
                                    </td>
                                    <td><?= $ticket['category'] ?></td>
                                    <td>
                                        <span class="priority-badge priority-<?= $ticket['priority'] ?>">
                                            <?= ucfirst($ticket['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $ticket['status'] ?>">
                                            <?= ucwords(str_replace('_', ' ', $ticket['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($ticket['created_date'])) ?></td>
                                    <td><?= $ticket['assigned_to'] ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTicket('<?= $ticket['id'] ?>')" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateTicket('<?= $ticket['id'] ?>')" title="Update">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Create Ticket Tab -->
                <div class="tab-pane fade" id="create_ticket" role="tabpanel">
                    <div class="helpdesk-form">
                        <h5 class="mb-4">Submit New Support Request</h5>
                        <form>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select">
                                            <option value="">Select category...</option>
                                            <?php foreach ($ticket_categories as $category): ?>
                                            <option value="<?= $category ?>"><?= $category ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Priority</label>
                                        <select class="form-select">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" placeholder="Brief description of your request...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" rows="5" placeholder="Please provide detailed information about your request..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Attachments</label>
                                <input type="file" class="form-control" multiple>
                                <div class="form-text">You can attach screenshots, documents, or other relevant files.</div>
                            </div>
                            
                            <button type="button" class="btn btn-primary btn-modern" onclick="submitTicket()">
                                <i class="bi bi-send me-2"></i>Submit Ticket
                            </button>
                        </form>
                    </div>
                </div>

                <!-- FAQ Tab -->
                <div class="tab-pane fade" id="faq" role="tabpanel">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I reset my password?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can reset your password by clicking on the "Password Reset" quick action above or by submitting a ticket under IT Support category.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How do I check my leave balance?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Your leave balance is available in the Employee Self-Service portal under the "Leave Management" section.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What is the typical response time for tickets?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Response times vary by priority: High priority - 2 hours, Medium priority - 8 hours, Low priority - 24 hours.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Tab -->
                <div class="tab-pane fade" id="feedback" role="tabpanel">
                    <div class="text-center py-5">
                        <i class="bi bi-chat-square-dots" style="font-size: 4rem; color: var(--info-color);"></i>
                        <h5 class="mt-3">Helpdesk Feedback</h5>
                        <p class="text-muted">Help us improve our support services by providing feedback.</p>
                        <button class="btn btn-info btn-modern">
                            <i class="bi bi-star me-2"></i>Rate Our Service
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewTicket(ticketId) {
            showAlert(`Opening ticket ${ticketId} details...`, 'info');
        }

        function updateTicket(ticketId) {
            showAlert(`Updating ticket ${ticketId}...`, 'warning');
        }

        function createNewTicket() {
            // Switch to create ticket tab
            const createTab = new bootstrap.Tab(document.querySelector('[data-bs-target="#create_ticket"]'));
            createTab.show();
        }

        function submitTicket() {
            showAlert('Support ticket submitted successfully! You will receive updates via email.', 'success');
        }

        function quickAction(action) {
            switch(action) {
                case 'password_reset':
                    showAlert('Password reset request initiated. Check your email for instructions.', 'info');
                    break;
                case 'leave_inquiry':
                    showAlert('Redirecting to leave management portal...', 'info');
                    break;
                case 'it_support':
                    showAlert('Creating IT support ticket...', 'info');
                    break;
                case 'payroll_query':
                    showAlert('Creating payroll inquiry ticket...', 'info');
                    break;
            }
        }

        function viewKnowledgeBase() {
            showAlert('Opening knowledge base...', 'info');
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
    </script>
    </div>
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