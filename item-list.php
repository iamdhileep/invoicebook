<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Item List - Bulk Management';

// Pagination settings
$limit = 25;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get total items count and statistics
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(item_price * COALESCE(stock, 0)) as total_value FROM items");
$totalRow = mysqli_fetch_assoc($totalResult);
$totalItems = $totalRow['total'];
$totalValue = $totalRow['total_value'] ?? 0;
$totalPages = ceil($totalItems / $limit);

// Get low stock count
$lowStockResult = mysqli_query($conn, "SELECT COUNT(*) AS low_stock FROM items WHERE COALESCE(stock, 0) < 10");
$lowStockRow = mysqli_fetch_assoc($lowStockResult);
$lowStockCount = $lowStockRow['low_stock'];

// Fetch items for current page
$result = mysqli_query($conn, "SELECT * FROM items ORDER BY item_name ASC LIMIT $offset, $limit");

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📦 Item List - Bulk Management</h1>
                <p class="text-muted small">Manage multiple items with bulk operations</p>
            </div>
            <div>
                <a href="add_item.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Item
                </a>
            </div>
        </div>

    <!-- Summary Cards -->
    <div class="row g-2 mb-3">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f4fd 0%, #cce7ff 100%);">
                <div class="card-body text-center p-3">
                    <div class="mb-2">
                        <i class="bi bi-box-seam fs-3" style="color: #0d6efd;"></i>
                    </div>
                    <h5 class="mb-1 fw-bold" style="color: #0d6efd;"><?= $totalItems ?></h5>
                    <small class="text-muted">Total Items</small>
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
                    <small class="text-muted">Inventory Value</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                <div class="card-body text-center p-3">
                    <div class="mb-2">
                        <i class="bi bi-exclamation-triangle fs-3" style="color: #ff9800;"></i>
                    </div>
                    <h5 class="mb-1 fw-bold" style="color: #ff9800;"><?= $lowStockCount ?></h5>
                    <small class="text-muted">Low Stock</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                <div class="card-body text-center p-3">
                    <div class="mb-2">
                        <i class="bi bi-file-text fs-3" style="color: #7b1fa2;"></i>
                    </div>
                    <h5 class="mb-1 fw-bold" style="color: #7b1fa2;">Page <?= $page ?></h5>
                    <small class="text-muted">of <?= $totalPages ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table with Bulk Operations -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Items Management</h6>
            <div>
                <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger btn-sm" style="display: none;" onclick="confirmBulkDelete()">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <form method="POST" action="delete_bulk_items.php" id="bulkForm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Item Details</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="item_ids[]" value="<?= $row['id'] ?>" class="form-check-input item-checkbox">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($row['item_name']) ?></strong>
                                                <br><small class="text-muted">ID: #<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-primary">₹<?= number_format($row['item_price'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $stock = $row['stock'] ?? 0;
                                            if ($stock == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($stock < 10): ?>
                                                <span class="badge bg-warning">Low Stock (<?= $stock ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock (<?= $stock ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_item.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete_item.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this item?')"
                                                   data-bs-toggle="tooltip" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-box-seam fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No items found</h5>
                    <p class="text-muted">Start by adding your first item to the inventory.</p>
                    <a href="add_item.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add First Item
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select all functionality
    $('#selectAll').change(function() {
        $('.item-checkbox').prop('checked', this.checked);
        toggleBulkDeleteButton();
    });
    
    // Individual checkbox change
    $('.item-checkbox').change(function() {
        toggleBulkDeleteButton();
        
        // Update select all checkbox
        var totalCheckboxes = $('.item-checkbox').length;
        var checkedCheckboxes = $('.item-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    
    function toggleBulkDeleteButton() {
        var checkedBoxes = $('.item-checkbox:checked').length;
        if (checkedBoxes > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function confirmBulkDelete() {
    var checkedBoxes = $('.item-checkbox:checked').length;
    if (checkedBoxes > 0) {
        if (confirm(`Are you sure you want to delete ${checkedBoxes} selected items? This action cannot be undone.`)) {
            $('#bulkForm').submit();
        }
    }
}
</script>

    </div>
</div>

<?php include 'layouts/footer.php'; ?>
<script>
  $('#select-all').click(function() {
    $('input[name="item_ids[]"]').prop('checked', this.checked);
  });
</script>
</body>
</html>

