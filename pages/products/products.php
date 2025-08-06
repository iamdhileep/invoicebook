<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Product Management';

// Get product statistics
$totalProducts = 0;
$totalValue = 0;
$lowStockCount = 0;
$totalStock = 0;

$statsQuery = $conn->query("SELECT COUNT(*) as total_products, SUM(item_price * COALESCE(item_stock, 0)) as total_value, SUM(COALESCE(item_stock, 0)) as total_stock FROM items");
if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
    $totalProducts = $row['total_products'] ?? 0;
    $totalValue = $row['total_value'] ?? 0;
    $totalStock = $row['total_stock'] ?? 0;
}

$lowStockQuery = $conn->query("SELECT COUNT(*) as low_stock FROM items WHERE COALESCE(item_stock, 0) < 10");
if ($lowStockQuery && $row = $lowStockQuery->fetch_assoc()) {
    $lowStockCount = $row['low_stock'] ?? 0;
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get all items
$items = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">📦 Product Management</h1>
                <p class="text-muted small">Manage your product inventory and stock levels</p>
            </div>
            <div>
                <a href="../../add_item.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Product
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
                    <h5 class="mb-1 fw-bold" style="color: #0d6efd;"><?= $totalProducts ?></h5>
                    <small class="text-muted">Total Products</small>
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
                        <i class="bi bi-boxes fs-3" style="color: #ff9800;"></i>
                    </div>
                    <h5 class="mb-1 fw-bold" style="color: #ff9800;"><?= $totalStock ?></h5>
                    <small class="text-muted">Total Stock</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card statistics-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                <div class="card-body text-center p-3">
                    <div class="mb-2">
                        <i class="bi bi-exclamation-triangle fs-3" style="color: #dc3545;"></i>
                    </div>
                    <h5 class="mb-1 fw-bold" style="color: #dc3545;"><?= $lowStockCount ?></h5>
                    <small class="text-muted">Low Stock</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-0 py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-dark"><i class="bi bi-table me-2"></i>Product Inventory</h6>
            <div>
                <a href="../../item-stock.php" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-boxes"></i> Manage Stock
                </a>
                <a href="../../item-full-list.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-list-ul"></i> Full List
                </a>
            </div>
        </div>
        <div class="card-body p-3">
            <?php if ($items && mysqli_num_rows($items) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                            <br><small class="text-muted">ID: #<?= str_pad($item['id'], 4, '0', STR_PAD_LEFT) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-primary">₹<?= number_format($item['item_price'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($item['item_stock'] ?? 0) < 10 ? 'bg-danger' : 'bg-success' ?>">
                                            <?= $item['item_stock'] ?? 0 ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $stock = $item['item_stock'] ?? 0;
                                        if ($stock == 0) {
                                            echo '<span class="status-badge inactive">Out of Stock</span>';
                                        } elseif ($stock < 10) {
                                            echo '<span class="status-badge pending">Low Stock</span>';
                                        } else {
                                            echo '<span class="status-badge active">In Stock</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="dt-action-buttons">
                                            <a href="../../edit_item.php?id=<?= $item['id'] ?>" 
                                               class="dt-btn dt-btn-edit" 
                                               title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="dt-btn dt-btn-delete" 
                                                    onclick="confirmDelete('../../delete_item.php', <?= $item['id'] ?>)"
                                                    title="Delete">
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
                    <i class="bi bi-box-seam fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No products found</h5>
                    <p class="text-muted">Start by adding your first product to the inventory.</p>
                    <a href="../../add_item.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add First Product
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Enhanced DataTable
    const productsTable = initDataTable('#productsTable', {
        pageLength: 25,
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [4] },
            { searchable: false, targets: [4] }
        ]
    });
    
    // Add export buttons
    addExportButtons(productsTable, 'products');
});
</script>

<?php include '../../layouts/footer.php'; ?>

<?php include '../../layouts/footer.php'; ?>