<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Invoice History';

// Filter functionality
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND customer_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
}
if ($dateFrom) {
    $where .= " AND invoice_date >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
}
if ($dateTo) {
    $where .= " AND invoice_date <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
}

// Get invoices
$result = $conn->query("SELECT * FROM invoices $where ORDER BY invoice_date DESC, created_at DESC");

// Get summary statistics
$totalAmount = 0;
$totalCount = 0;
$summaryQuery = $conn->query("SELECT COUNT(*) as count, SUM(total_amount) as total FROM invoices $where");
if ($summaryQuery && $row = $summaryQuery->fetch_assoc()) {
    $totalCount = $row['count'] ?? 0;
    $totalAmount = $row['total'] ?? 0;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📄 Invoice History</h1>
                <p class="text-muted small">View and manage all invoice records</p>
            </div>
            <div>
                <a href="pages/invoice/invoice.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Create New Invoice
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Total Revenue</h6>
                                <h4 class="mb-0 fw-bold" style="color: #ffeb3b;">₹<?= number_format($totalAmount, 2) ?></h4>
                                <small class="text-white-50">From <?= $totalCount ?> invoices</small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-currency-rupee"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 text-white small">Total Invoices</h6>
                                <h4 class="mb-0 fw-bold" style="color: #ffeb3b;"><?= $totalCount ?></h4>
                                <small class="text-white-50">Invoice records</small>
                            </div>
                            <div class="fs-2 text-white-50">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 text-dark"><i class="bi bi-funnel me-2"></i>Filters</h6>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Search Customer</label>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Search by customer name" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="invoice_history.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Invoice Records</h6>
                <div>
                    <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" style="display: none;">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <form id="bulkDeleteForm" method="POST" action="bulk_delete_invoice.php">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="invoicesTable">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Contact</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="form-check-input invoice-checkbox">
                                        </td>
                                        <td>
                                            <strong class="text-primary">#INV-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= date('M d, Y', strtotime($row['invoice_date'])) ?></strong>
                                                <?php if (isset($row['created_at'])): ?>
                                                    <br><small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($row['customer_name']) ?></strong>
                                                <?php if (!empty($row['bill_address'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($row['bill_address'], 0, 30)) ?>...</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['customer_contact']) ?></td>
                                        <td>
                                            <strong class="text-success">₹<?= number_format($row['total_amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view_invoice.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="print_invoice.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-success" 
                                                   target="_blank"
                                                   data-bs-toggle="tooltip" title="Print">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <a href="edit_invoice.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-warning" 
                                                   data-bs-toggle="tooltip" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-danger delete-invoice" 
                                                        data-id="<?= $row['id'] ?>"
                                                        data-bs-toggle="tooltip" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="text-muted mb-2">No invoices found</h6>
                    <p class="text-muted small mb-3">No invoices match your current filters.</p>
                    <a href="pages/invoice/invoice.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Create First Invoice
                    </a>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#invoicesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        order: [[2, "desc"]], // Sort by date
        columnDefs: [
            { orderable: false, targets: [0, 6] }
        ],
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });

    // Select all functionality
    $('#selectAll').change(function() {
        $('.invoice-checkbox').prop('checked', this.checked);
        toggleBulkDelete();
    });

    // Individual checkbox change
    $('.invoice-checkbox').change(function() {
        const totalCheckboxes = $('.invoice-checkbox').length;
        const checkedCheckboxes = $('.invoice-checkbox:checked').length;
        
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        toggleBulkDelete();
    });

    // Toggle bulk delete button
    function toggleBulkDelete() {
        const checkedCount = $('.invoice-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteBtn').show().text(`Delete Selected (${checkedCount})`);
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }

    // Bulk delete
    $('#bulkDeleteBtn').click(function() {
        const checkedCount = $('.invoice-checkbox:checked').length;
        if (confirm(`Are you sure you want to delete ${checkedCount} selected invoice(s)? This action cannot be undone.`)) {
            $('#bulkDeleteForm').submit();
        }
    });

    // Delete individual invoice - using event delegation for DataTables compatibility
    $(document).on('click', '.delete-invoice', function() {
        const invoiceId = $(this).data('id');
        const row = $(this).closest('tr');
        const button = $(this);
        
        if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
            // Show loading state
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);

            $.post('delete_invoice.php', {id: invoiceId}, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        // Reload page to update totals
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert(response.message || 'Invoice deleted successfully', 'success');
                } else {
                    showAlert('Failed to delete invoice: ' + (response.message || 'Unknown error'), 'danger');
                    button.html(originalContent).prop('disabled', false);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Delete request failed:', xhr.responseText);
                showAlert('Error occurred while deleting invoice: ' + error, 'danger');
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Export function
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_invoice_excel.php?' + params.toString());
}

// Helper function for alerts
function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('.main-content').prepend(alertDiv);
    
    setTimeout(() => {
        alertDiv.alert('close');
    }, 5000);
}
</script>

<style>
/* Enhanced Invoice History Styling */
.main-content {
    padding: 10px;
}

.container-fluid {
    max-width: 100%;
    padding: 0 10px;
}

/* Responsive Enhancements */
@media (max-width: 768px) {
    .main-content {
        padding: 5px;
    }
    
    .container-fluid {
        padding: 0 5px;
    }
    
    .row {
        margin: 0 -5px;
    }
    
    .row > * {
        padding: 0 5px;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 2px;
        border-radius: 0.25rem !important;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .d-flex.gap-1 {
        flex-direction: column;
        gap: 0.25rem !important;
    }
}

/* Card Hover Effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Table Enhancements */
.table th {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    border-bottom: 2px solid var(--primary-color) !important;
    background-color: var(--gray-100) !important;
    color: var(--gray-800) !important;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
}

/* Button Group Improvements */
.btn-group-sm > .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* DataTable Responsive */
@media (max-width: 992px) {
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        text-align: left;
        margin-bottom: 10px;
    }
    
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        text-align: center;
        margin-top: 10px;
    }
}

/* Empty State Styling */
.text-center.py-5 {
    padding: 3rem 0 !important;
}

.text-center.py-5 .bi {
    color: #6c757d;
}

/* Alert Improvements */
.alert {
    font-size: 0.875rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

/* Form Control Sizing */
.form-control-sm {
    font-size: 0.875rem;
}

.form-label.small {
    font-size: 0.875rem;
    font-weight: 500;
    color: #495057;
}
</style>

<?php include 'layouts/footer.php'; ?>
