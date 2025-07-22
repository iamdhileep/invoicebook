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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Invoice History</h1>
            <p class="text-muted">View and manage all invoice records</p>
        </div>
        <div>
            <a href="pages/invoice/invoice.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create New Invoice
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Revenue</h6>
                            <h3 class="mb-0">₹<?= number_format($totalAmount, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Invoices</h6>
                            <h3 class="mb-0"><?= $totalCount ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Customer</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by customer name" 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="invoice_history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Invoice Records</h5>
            <div>
                <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" style="display: none;">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body">
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
                    <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No invoices found</h5>
                    <p class="text-muted">No invoices match your current filters.</p>
                    <a href="pages/invoice/invoice.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create First Invoice
                    </a>
                </div>
            <?php endif; ?>
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
        ]
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

    // Delete individual invoice
    $('.delete-invoice').click(function() {
        const invoiceId = $(this).data('id');
        const row = $(this).closest('tr');
        
        if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
            $.post('delete_invoice.php', {id: invoiceId}, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        // Reload page to update totals
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert('Invoice deleted successfully', 'success');
                } else {
                    showAlert('Failed to delete invoice: ' + (response.message || 'Unknown error'), 'danger');
                }
            }, 'json').fail(function() {
                showAlert('Error occurred while deleting invoice', 'danger');
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

<?php include 'layouts/footer.php'; ?>
