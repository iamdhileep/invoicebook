<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Products';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get all items
$items = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Product List</h1>
            <p class="text-muted">Manage your product inventory</p>
        </div>
        <div>
            <a href="../../add_item.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Product
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="productsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items && mysqli_num_rows($items) > 0): ?>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td>₹<?= number_format($item['item_price'], 2) ?></td>
                                    <td><?= $item['item_stock'] ?? 'N/A' ?></td>
                                    <td>
                                        <a href="../../edit_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="../../delete_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No products found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-plus-circle fs-1 text-primary mb-3"></i>
                    <h5>Add New Product</h5>
                    <p class="text-muted">Add products to your inventory</p>
                    <a href="../../add_item.php" class="btn btn-primary">Add Product</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-boxes fs-1 text-warning mb-3"></i>
                    <h5>Stock Management</h5>
                    <p class="text-muted">Manage product stock levels</p>
                    <a href="../../item-stock.php" class="btn btn-warning">Manage Stock</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-list-ul fs-1 text-success mb-3"></i>
                    <h5>Full Product List</h5>
                    <p class="text-muted">View detailed product information</p>
                    <a href="../../item-full-list.php" class="btn btn-success">View Full List</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>