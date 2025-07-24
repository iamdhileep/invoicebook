<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Stock Management';

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_items'])) {
    if (!empty($_POST['delete_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['delete_ids']));
        $conn->query("DELETE FROM items WHERE id IN ($ids)");
        $success = "Selected items deleted successfully!";
    }
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$stock_status = $_GET['stock_status'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (item_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($category) {
    $where .= " AND category = '" . mysqli_real_escape_string($conn, $category) . "'";
}
if ($stock_status === 'low') {
    $where .= " AND stock <= 10";
} elseif ($stock_status === 'out') {
    $where .= " AND stock = 0";
}

// Get items
$items = $conn->query("SELECT * FROM items $where ORDER BY item_name ASC");

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Get stock statistics
$totalItems = 0;
$lowStockItems = 0;
$outOfStockItems = 0;
$totalValue = 0;

$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN stock <= 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(stock * item_price) as total_value
    FROM items $where
");

if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
    $totalItems = $row['total_items'] ?? 0;
    $lowStockItems = $row['low_stock'] ?? 0;
    $outOfStockItems = $row['out_of_stock'] ?? 0;
    $totalValue = $row['total_value'] ?? 0;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📦 Stock Management</h1>
                <p class="text-muted small">Monitor and manage your inventory stock levels</p>
            </div>
            <div>
                <a href="add_item.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add New Item
                </a>
            </div>
        </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

        <!-- Stock Summary Cards -->
        <div class="row g-2 mb-3">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-box-seam fs-3" style="color: #1976d2;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #1976d2;"><?= $totalItems ?></h5>
                        <small class="text-muted">Total Items</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-exclamation-triangle-fill fs-3" style="color: #f57c00;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #f57c00;"><?= $lowStockItems ?></h5>
                        <small class="text-muted">Low Stock</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-x-circle-fill fs-3" style="color: #d32f2f;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #d32f2f;"><?= $outOfStockItems ?></h5>
                        <small class="text-muted">Out of Stock</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                        </div>
                        <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($totalValue, 0) ?></h5>
                        <small class="text-muted">Total Value</small>
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
                    <label class="form-label">Search Items</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by item name" 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                        <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stock Status</label>
                    <select name="stock_status" class="form-select">
                        <option value="">All Items</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock (≤10)</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="item-stock.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

        <!-- Stock Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark">Stock Inventory</h6>
                <div>
                    <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" style="display: none;">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportStock()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
            <?php if ($items && mysqli_num_rows($items) > 0): ?>
                <form id="bulkDeleteForm" method="POST">
                    <input type="hidden" name="delete_items" value="1">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="stockTable">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="delete_ids[]" value="<?= $item['id'] ?>" class="form-check-input item-checkbox">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                            <?php if (!empty($item['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['category'])): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No category</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-success">₹<?= number_format($item['item_price'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?= $item['stock'] == 0 ? 'bg-danger' : ($item['stock'] <= 10 ? 'bg-warning' : 'bg-success') ?>">
                                                <?= $item['stock'] ?> units
                                            </span>
                                        </td>
                                        <td>
                                            <strong>₹<?= number_format($item['stock'] * $item['item_price'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($item['stock'] == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($item['stock'] <= 10): ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary update-stock" 
                                                        data-id="<?= $item['id'] ?>"
                                                        data-name="<?= htmlspecialchars($item['item_name']) ?>"
                                                        data-stock="<?= $item['stock'] ?>"
                                                        data-bs-toggle="tooltip" title="Update Stock">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <a href="edit_item.php?id=<?= $item['id'] ?>" 
                                                   class="btn btn-outline-info"
                                                   data-bs-toggle="tooltip" title="Edit Item">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-outline-danger delete-item" 
                                                        data-id="<?= $item['id'] ?>"
                                                        data-name="<?= htmlspecialchars($item['item_name']) ?>"
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
                    <i class="bi bi-box fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No items found</h5>
                    <p class="text-muted">No items match your current filters.</p>
                    <a href="add_item.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add First Item
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateStockForm">
                <div class="modal-body">
                    <input type="hidden" id="itemId">
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" id="itemName" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="number" id="currentStock" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Stock Quantity</label>
                        <input type="number" id="newStock" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#stockTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        order: [[1, "asc"]],
        columnDefs: [
            { orderable: false, targets: [0, 7] }
        ],
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });

    // Select all functionality
    $('#selectAll').change(function() {
        $('.item-checkbox').prop('checked', this.checked);
        toggleBulkDelete();
    });

    // Individual checkbox change
    $('.item-checkbox').change(function() {
        const totalCheckboxes = $('.item-checkbox').length;
        const checkedCheckboxes = $('.item-checkbox:checked').length;
        
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        toggleBulkDelete();
    });

    // Toggle bulk delete button
    function toggleBulkDelete() {
        const checkedCount = $('.item-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteBtn').show().text(`Delete Selected (${checkedCount})`);
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }

    // Bulk delete
    $('#bulkDeleteBtn').click(function() {
        const checkedCount = $('.item-checkbox:checked').length;
        if (confirm(`Are you sure you want to delete ${checkedCount} selected item(s)? This action cannot be undone.`)) {
            $('#bulkDeleteForm').submit();
        }
    });

    // Update stock
    $('.update-stock').click(function() {
        const itemId = $(this).data('id');
        const itemName = $(this).data('name');
        const currentStock = $(this).data('stock');

        $('#itemId').val(itemId);
        $('#itemName').val(itemName);
        $('#currentStock').val(currentStock);
        $('#newStock').val(currentStock);

        new bootstrap.Modal(document.getElementById('updateStockModal')).show();
    });

    // Handle stock update form
    $('#updateStockForm').submit(function(e) {
        e.preventDefault();
        
        const itemId = $('#itemId').val();
        const newStock = $('#newStock').val();

        $.post('update_stock.php', {
            item_id: itemId,
            new_stock: newStock
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert('Failed to update stock: ' + (response.message || 'Unknown error'), 'danger');
            }
        }, 'json').fail(function() {
            showAlert('Error occurred while updating stock', 'danger');
        });
    });

    // Delete individual item - using event delegation for DataTables compatibility
    $(document).on('click', '.delete-item', function() {
        const itemId = $(this).data('id');
        const itemName = $(this).data('name');
        const row = $(this).closest('tr');
        const button = $(this);

        if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
            // Show loading state
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);

            $.post('delete_item.php', {id: itemId}, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert(response.message || 'Item deleted successfully', 'success');
                } else {
                    showAlert('Failed to delete item: ' + (response.message || 'Unknown error'), 'danger');
                    button.html(originalContent).prop('disabled', false);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Delete request failed:', xhr.responseText);
                showAlert('Error occurred while deleting item: ' + error, 'danger');
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

function exportStock() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_stock.php?' + params.toString());
}

function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('.main-content').prepend(alertDiv);
    
    setTimeout(() => {
        alertDiv.fadeOut();
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
