<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Expense History';

// Filter functionality
$filterDate = $_GET['filter_date'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';
$filterMonth = $_GET['filter_month'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($filterDate) {
    $where .= " AND expense_date = '" . mysqli_real_escape_string($conn, $filterDate) . "'";
}
if ($filterCategory) {
    $where .= " AND category = '" . mysqli_real_escape_string($conn, $filterCategory) . "'";
}
if ($filterMonth) {
    $where .= " AND DATE_FORMAT(expense_date, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterMonth) . "'";
}

// Get expenses
$result = $conn->query("SELECT * FROM expenses $where ORDER BY expense_date DESC, created_at DESC");

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL ORDER BY category");

// Get summary statistics
$totalAmount = 0;
$totalCount = 0;
$summaryQuery = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total FROM expenses $where");
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
            <h1 class="h3 mb-0">Expense History</h1>
            <p class="text-muted">View and manage all expense records</p>
        </div>
        <div>
            <a href="pages/expenses/expenses.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Expense
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Expenses</h6>
                            <h3 class="mb-0">₹<?= number_format($totalAmount, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Records</h6>
                            <h3 class="mb-0"><?= $totalCount ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-list-ul"></i>
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
                <div class="col-md-3">
                    <label class="form-label">Filter by Date</label>
                    <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Month</label>
                    <input type="month" name="filter_month" class="form-control" value="<?= htmlspecialchars($filterMonth) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Category</label>
                    <select name="filter_category" class="form-select">
                        <option value="">All Categories</option>
                        <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                        <?= $filterCategory === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="expense_history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Expense Records</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="exportToPDF()">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="expensesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Receipt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= date('M d, Y', strtotime($row['expense_date'])) ?></strong>
                                            <br><small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($row['category']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['description'] ?? $row['note'] ?? '') ?></td>
                                    <td>
                                        <strong class="text-danger">₹<?= number_format($row['amount'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($row['payment_method'] ?? 'Cash') ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['receipt']) && file_exists('uploads/receipts/' . $row['receipt'])): ?>
                                            <a href="uploads/receipts/<?= htmlspecialchars($row['receipt']) ?>" 
                                               class="btn btn-outline-primary btn-sm" target="_blank">
                                                <i class="bi bi-file-earmark"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No receipt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_expense.php?id=<?= $row['id'] ?>" 
                                               class="btn btn-outline-primary" 
                                               data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger delete-expense" 
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
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No expenses found</h5>
                    <p class="text-muted">No expenses match your current filters.</p>
                    <a href="pages/expenses/expenses.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add First Expense
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    console.log('Expense History: Document ready, jQuery loaded');
    console.log('Found delete buttons:', $('.delete-expense').length);
    
    // Initialize DataTable
    $('#expensesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        order: [[0, "desc"]],
        columnDefs: [
            { orderable: false, targets: [5, 6] }
        ]
    });

    // Delete expense functionality - using event delegation for DataTables compatibility
    $(document).on('click', '.delete-expense', function() {
        console.log('Delete button clicked');
        const expenseId = $(this).data('id');
        const row = $(this).closest('tr');
        const button = $(this);
        
        console.log('Expense ID:', expenseId);
        
        if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
            console.log('User confirmed deletion');
            // Show loading state
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);
            
            $.post('delete_expense.php', {id: expenseId}, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        // Reload page to update totals
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert(response.message || 'Expense deleted successfully', 'success');
                } else {
                    showAlert('Failed to delete expense: ' + (response.message || 'Unknown error'), 'danger');
                    // Restore button
                    button.html(originalContent).prop('disabled', false);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Delete request failed:', xhr.responseText);
                showAlert('Error occurred while deleting expense: ' + error, 'danger');
                // Restore button
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Export functions
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_expense_excel.php?' + params.toString());
}

function exportToPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_expense_pdf.php?' + params.toString());
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
