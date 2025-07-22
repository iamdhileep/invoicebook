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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Stock Management</h1>
            <p class="text-muted">Monitor and manage your inventory stock levels</p>
        </div>
        <div>
            <a href="add_item.php" class="btn btn-primary">
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
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Items</h6>
                            <h3 class="mb-0"><?= $totalItems ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-box"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Low Stock</h6>
                            <h3 class="mb-0"><?= $lowStockItems ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Out of Stock</h6>
                            <h3 class="mb-0"><?= $outOfStockItems ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Value</h6>
                            <h3 class="mb-0">₹<?= number_format($totalValue, 0) ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
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
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Stock Inventory</h5>
            <div>
                <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" style="display: none;">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportStock()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body">
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
        ]
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

    // Delete individual item
    $('.delete-item').click(function() {
        const itemId = $(this).data('id');
        const itemName = $(this).data('name');
        const row = $(this).closest('tr');

        if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
            $.post('delete_item.php', {id: itemId}, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        setTimeout(() => location.reload(), 500);
                    });
                    showAlert('Item deleted successfully', 'success');
                } else {
                    showAlert('Failed to delete item', 'danger');
                }
            }, 'json').fail(function() {
                showAlert('Error occurred while deleting item', 'danger');
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

<?php include 'layouts/footer.php'; ?>
