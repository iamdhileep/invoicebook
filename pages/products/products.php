<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Product Management';

// Get categories for filter
$categories = [];
$categoryQuery = "SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categoryResult = mysqli_query($conn, $categoryQuery);
if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row['category'];
    }
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Product Management</h1>
            <p class="text-muted">Manage your inventory and product listings</p>
        </div>
        <div>
            <a href="../../add_item.php" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Add Product
            </a>
            <a href="../../item-stock.php" class="btn btn-outline-warning">
                <i class="bi bi-boxes"></i> Stock Management
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <?php
    // Get product statistics
    $totalProducts = 0;
    $lowStockCount = 0;
    $totalValue = 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM items");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalProducts = $row['total'];
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM items WHERE stock <= 5");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $lowStockCount = $row['total'];
    }

    $result = mysqli_query($conn, "SELECT SUM(item_price * COALESCE(stock, 0)) as total FROM items");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalValue = $row['total'] ?? 0;
    }
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Products</h6>
                            <h2 class="mb-0"><?= $totalProducts ?></h2>
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
                            <h6 class="card-title mb-0">Low Stock Items</h6>
                            <h2 class="mb-0"><?= $lowStockCount ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-exclamation-triangle"></i>
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
                            <h6 class="card-title mb-0">Inventory Value</h6>
                            <h2 class="mb-0">₹ <?= number_format($totalValue, 0) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Categories</h6>
                            <h2 class="mb-0"><?= count($categories) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search Products</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="itemSearch" class="form-control" placeholder="Search by name, category, or price...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Category</label>
                    <select id="categoryFilter" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock Status</label>
                    <select id="stockFilter" class="form-select">
                        <option value="">All Items</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock (≤5)</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportProducts()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Product List</h5>
            <div>
                <button class="btn btn-sm btn-outline-danger" id="deleteSelected" style="display: none;">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
            </div>
        </div>
        <div class="card-body">
            <form id="bulkDeleteForm" method="POST" action="../../bulk_delete_items.php">
                <div class="table-responsive">
                    <table id="itemTable" class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = mysqli_query($conn, "SELECT * FROM items ORDER BY id DESC");
                            while ($row = mysqli_fetch_assoc($result)) {
                                $stock = $row['stock'] ?? 0;
                                $stockStatus = $stock <= 0 ? 'out-of-stock' : ($stock <= 5 ? 'low-stock' : 'in-stock');
                                $stockBadge = $stock <= 0 ? 'bg-danger' : ($stock <= 5 ? 'bg-warning' : 'bg-success');
                                $stockText = $stock <= 0 ? 'Out of Stock' : ($stock <= 5 ? 'Low Stock' : 'In Stock');
                                
                                echo "<tr data-name='" . strtolower(htmlspecialchars($row['item_name'])) . "' 
                                         data-category='" . htmlspecialchars($row['category'] ?? '') . "' 
                                         data-stock-status='" . $stockStatus . "'>";
                                echo "<td><input type='checkbox' name='selected_items[]' value='{$row['id']}' class='form-check-input item-checkbox'></td>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td><strong>" . htmlspecialchars($row['item_name']) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($row['category'] ?? 'Uncategorized') . "</td>";
                                echo "<td><span class='fw-bold text-primary'>₹ " . number_format($row['item_price'], 2) . "</span></td>";
                                echo "<td><span class='fw-bold'>" . $stock . "</span></td>";
                                echo "<td><span class='badge {$stockBadge}'>{$stockText}</span></td>";
                                echo "<td>
                                    <div class='btn-group btn-group-sm'>
                                        <a href='../../edit-item.php?id={$row['id']}' class='btn btn-outline-primary' data-bs-toggle='tooltip' title='Edit'>
                                            <i class='bi bi-pencil'></i>
                                        </a>
                                        <button type='button' class='btn btn-outline-danger delete-single-item' data-id='{$row['id']}' data-bs-toggle='tooltip' title='Delete'>
                                            <i class='bi bi-trash'></i>
                                        </button>
                                    </div>
                                </td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    $(document).ready(function() {
        // Initialize DataTable
        const table = $("#itemTable").DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            order: [[1, "desc"]],
            columnDefs: [
                { orderable: false, targets: [0, 7] }
            ]
        });

        // Search functionality
        $("#itemSearch").on("input", function() {
            table.search(this.value).draw();
        });

        // Category filter
        $("#categoryFilter").on("change", function() {
            const category = this.value;
            if (category) {
                table.column(3).search("^" + category + "$", true, false).draw();
            } else {
                table.column(3).search("").draw();
            }
        });

        // Stock filter
        $("#stockFilter").on("change", function() {
            const status = this.value;
            if (status) {
                table.rows().every(function() {
                    const row = this.node();
                    const stockStatus = row.getAttribute("data-stock-status");
                    if (status === stockStatus) {
                        $(row).show();
                    } else {
                        $(row).hide();
                    }
                });
            } else {
                table.rows().every(function() {
                    $(this.node()).show();
                });
            }
            table.draw();
        });

        // Select all functionality
        $("#selectAll").change(function() {
            $(".item-checkbox").prop("checked", this.checked);
            toggleDeleteButton();
        });

        // Individual checkbox change
        $(document).on("change", ".item-checkbox", function() {
            const totalCheckboxes = $(".item-checkbox").length;
            const checkedCheckboxes = $(".item-checkbox:checked").length;
            
            $("#selectAll").prop("checked", totalCheckboxes === checkedCheckboxes);
            toggleDeleteButton();
        });

        // Toggle delete button
        function toggleDeleteButton() {
            const checkedCount = $(".item-checkbox:checked").length;
            if (checkedCount > 0) {
                $("#deleteSelected").show().text(`Delete Selected (${checkedCount})`);
            } else {
                $("#deleteSelected").hide();
            }
        }

        // Bulk delete
        $("#deleteSelected").click(function() {
            const checkedCount = $(".item-checkbox:checked").length;
            if (confirm(`Are you sure you want to delete ${checkedCount} selected item(s)? This action cannot be undone.`)) {
                $("#bulkDeleteForm").submit();
            }
        });

        // Single item delete
        $(document).on("click", ".delete-single-item", function() {
            const itemId = $(this).data("id");
            const row = $(this).closest("tr");
            const itemName = row.find("td:nth-child(3)").text();
            
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                $.post("../../delete_item.php", {id: itemId}, function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            table.row(this).remove().draw();
                        });
                        showAlert("Product deleted successfully", "success");
                    } else {
                        showAlert("Failed to delete product: " + response.message, "danger");
                    }
                }, "json").fail(function() {
                    showAlert("Error occurred while deleting product", "danger");
                });
            }
        });
    });

    // Clear all filters
    function clearFilters() {
        $("#itemSearch").val("");
        $("#categoryFilter").val("");
        $("#stockFilter").val("");
        $("#itemTable").DataTable().search("").columns().search("").draw();
        $("tbody tr").show();
    }

    // Export products (placeholder function)
    function exportProducts() {
        showAlert("Export functionality will be implemented soon", "info");
    }
</script>
';

include '../../layouts/footer.php';
?>