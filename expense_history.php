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
$avgAmount = 0;
$thisMonthAmount = 0;

$summaryQuery = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total, AVG(amount) as average FROM expenses $where");
if ($summaryQuery && $row = $summaryQuery->fetch_assoc()) {
    $totalCount = $row['count'] ?? 0;
    $totalAmount = $row['total'] ?? 0;
    $avgAmount = $row['average'] ?? 0;
}

// Get this month's expenses
$thisMonth = date('Y-m');
$thisMonthQuery = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$thisMonth'");
if ($thisMonthQuery && $row = $thisMonthQuery->fetch_assoc()) {
    $thisMonthAmount = $row['total'] ?? 0;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">💰 Expense History</h1>
                <p class="text-muted small">View and manage all expense records</p>
            </div>
            <div>
                <a href="pages/expenses/expenses.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add New Expense
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-cash-stack fs-3" style="color: #d32f2f;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #d32f2f;">₹<?= number_format($totalAmount, 2) ?></h5>
                        <small class="text-muted">Total Expenses</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-list-ul fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?= $totalCount ?></h5>
                        <small class="text-muted">Total Records</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calculator-fill fs-3" style="color: #7b1fa2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #7b1fa2;">₹<?= number_format($avgAmount, 2) ?></h5>
                        <small class="text-muted">Average Amount</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-calendar-month-fill fs-3" style="color: #ff9800;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #ff9800;">₹<?= number_format($thisMonthAmount, 2) ?></h5>
                        <small class="text-muted">This Month</small>
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
                <form method="GET" class="row g-2">
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
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark">Expense Records</h6>
                <div>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="exportToPDF()">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
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
</div>

<script>
$(document).ready(function() {
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
        const expenseId = $(this).data('id');
        const row = $(this).closest('tr');
        const button = $(this);
        
        if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
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

<style>
/* Statistics Cards Styling */
.statistics-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.statistics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.statistics-card .card-body {
    position: relative;
    overflow: hidden;
}

.statistics-card .card-body::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
    opacity: 0;
}

.statistics-card:hover .card-body::before {
    opacity: 1;
    transform: scale(1.2);
}

.statistics-card i {
    transition: all 0.3s ease;
}

.statistics-card:hover i {
    transform: scale(1.1);
}

/* Custom Card Styling */
.card {
    border-radius: 10px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

/* Page Content Spacing */
.main-content {
    padding: 1rem 0;
}

.main-content .container-fluid {
    padding: 0 15px;
}

/* Compact spacing for better space utilization */
.mb-4 {
    margin-bottom: 1rem !important;
}

.mb-3 {
    margin-bottom: 0.75rem !important;
}

.p-3 {
    padding: 0.75rem !important;
}

.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.g-2 > * {
    padding: 0.25rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-card .card-body {
        padding: 0.75rem;
    }
    
    .statistics-card h5 {
        font-size: 1.1rem;
    }
    
    .statistics-card i {
        font-size: 1.5rem !important;
    }
}

@media (max-width: 992px) {
    .main-content .container-fluid {
        padding: 0 10px;
    }
    
    .statistics-card .card-body {
        padding: 0.65rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 0.5rem 0;
    }
    
    .main-content .container-fluid {
        padding: 0 5px;
    }
    
    .statistics-card .card-body {
        padding: 0.5rem;
        text-align: center;
    }
    
    .statistics-card h5 {
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }
    
    .statistics-card small {
        font-size: 0.7rem;
    }
    
    .statistics-card i {
        font-size: 1.3rem !important;
        margin-bottom: 0.25rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between .d-flex {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .form-control, .form-select {
        font-size: 0.8rem;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
}

@media (max-width: 576px) {
    .statistics-card {
        margin-bottom: 0.5rem;
    }
    
    .statistics-card .card-body {
        padding: 0.4rem;
    }
    
    .statistics-card h5 {
        font-size: 0.9rem;
    }
    
    .statistics-card small {
        font-size: 0.65rem;
    }
    
    .col-xl-3 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .card-header h6 {
        font-size: 0.9rem;
    }
    
    .card-body {
        padding: 0.5rem !important;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.15rem 0.3rem;
        font-size: 0.7rem;
    }
}

/* Smooth Transitions */
* {
    transition: all 0.2s ease;
}

/* Table Improvements */
.table-responsive {
    border-radius: 8px;
}

.table th {
    font-weight: 600;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .table th, .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.7rem;
    }
}
</style>

<?php include 'layouts/footer.php'; ?>
