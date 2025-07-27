<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Complete Item Inventory';

// Simple statistics query
$statsQuery = "
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN stock <= 10 AND stock > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        COUNT(DISTINCT category) as total_categories
    FROM items
";

$statsResult = $conn->query($statsQuery);

if (!$statsResult) {
    die("Database query error: " . $conn->error);
}

$stats = $statsResult->fetch_assoc();

// Extract statistics
$total_items = $stats['total_items'] ?? 0;
$in_stock_count = $stats['in_stock'] ?? 0;
$low_stock_count = $stats['low_stock'] ?? 0;
$out_of_stock_count = $stats['out_of_stock'] ?? 0;
$total_categories = $stats['total_categories'] ?? 0;

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<style>
body { 
    visibility: hidden;
}

body.loaded {
    visibility: visible;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#pageLoader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.95);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.main-content {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.4s ease-out, transform 0.4s ease-out;
}

.main-content.loaded {
    opacity: 1;
    transform: translateY(0);
}
</style>

<!-- Page Loader -->
<div id="pageLoader">
    <div class="text-center">
        <div class="spinner-border text-primary mb-3" style="width: 4rem; height: 4rem;"></div>
        <h5 class="text-primary">Loading Inventory...</h5>
        <p class="text-muted">Please wait while we load your items</p>
    </div>
</div>

<div class="main-content">
    <div class="container-fluid">
        <!-- Modern Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 fw-bold">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Complete Item Inventory
                </h1>
                <p class="text-muted">Manage and analyze your complete product catalog with advanced filtering and insights</p>
            </div>
            <div class="d-flex gap-2">
                <a href="add_item.php" class="btn btn-success btn-lg">
                    <i class="bi bi-plus-circle-fill me-2"></i>Add New Item
                </a>
                <button class="btn btn-outline-primary btn-lg" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="mb-4"></div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Total Items</h6>
                            <h2 class="mb-0 fw-bold"><?= $total_items ?></h2>
                            <small class="opacity-75">All inventory items</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">In Stock</h6>
                            <h2 class="mb-0 fw-bold"><?= $in_stock_count ?></h2>
                            <small class="opacity-75">Items with >10 stock</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Low Stock</h6>
                            <h2 class="mb-0 fw-bold"><?= $low_stock_count ?></h2>
                            <small class="opacity-75">Items with 1-10 stock</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Out of Stock</h6>
                            <h2 class="mb-0 fw-bold"><?= $out_of_stock_count ?></h2>
                            <small class="opacity-75">Items with 0 stock</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Basic Items Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-list-ul me-2 text-primary"></i>Items Inventory
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Item Name</th>
                            <th class="fw-semibold">Category</th>
                            <th class="fw-semibold">Price</th>
                            <th class="fw-semibold">Stock</th>
                            <th class="fw-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $itemsQuery = "SELECT * FROM items ORDER BY id DESC";
                        $itemsResult = $conn->query($itemsQuery);
                        
                        if ($itemsResult && $itemsResult->num_rows > 0):
                            while ($item = $itemsResult->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                            <td>$<?= number_format($item['item_price'], 2) ?></td>
                            <td><?= $item['stock'] ?></td>
                            <td>
                                <?php if ($item['stock'] > 10): ?>
                                    <span class="badge bg-success">In Stock</span>
                                <?php elseif ($item['stock'] > 0): ?>
                                    <span class="badge bg-warning">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="bi bi-box-seam display-4 text-muted"></i>
                                <p class="text-muted mt-2">No items found</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.getElementById('pageLoader').style.display = 'none';
        document.querySelector('.main-content').classList.add('loaded');
        document.body.classList.add('loaded');
    }, 300);
});

function refreshData() {
    location.reload();
}
</script>

    </div>
</div>
