<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../config.php';

// Handle form submission for adding new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = $_POST['item_name'];
    $price = $_POST['item_price'];
    $stock = $_POST['stock'];
    $category = $_POST['category'];
    $imagePath = '';

    // Handle image upload
    if (!empty($_FILES['item_image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['item_image']['name']);
        $targetPath = '../uploads/' . $imageName;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $targetPath)) {
            $imagePath = $imageName;
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO items (item_name, item_price, stock, category, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiss", $name, $price, $stock, $category, $imagePath);
    if ($stmt->execute()) {
        $success_message = "Item added successfully!";
    } else {
        $error_message = "Error adding item: " . $conn->error;
    }
}

// Get categories for filter
$catResult = mysqli_query($conn, "SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");

// Get totals
$totalRes = mysqli_query($conn, "SELECT SUM(stock) as total_stock, SUM(item_price * stock) as total_value FROM items");
$totals = mysqli_fetch_assoc($totalRes);

include '../includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-box-seam me-2"></i>
    Product Management
  </h1>
  <p class="text-muted mb-0">Manage your inventory and product catalog</p>
</div>

<?php if (isset($success_message)): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $success_message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?= $error_message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">
            <i class="bi bi-list-ul me-2"></i>
            Product List
          </h5>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-circle me-2"></i>
            Add New Product
          </button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filter Controls -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="categoryFilter" class="form-label">Filter by Category</label>
            <select id="categoryFilter" class="form-select">
              <option value="">All Categories</option>
              <?php
              mysqli_data_seek($catResult, 0);
              while ($cat = mysqli_fetch_assoc($catResult)): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>">
                  <?= htmlspecialchars($cat['category']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="stockFilter" class="form-label">Filter by Stock</label>
            <select id="stockFilter" class="form-select">
              <option value="">All Stock Levels</option>
              <option value="low">Low Stock (< 10)</option>
              <option value="medium">Medium Stock (10-50)</option>
              <option value="high">High Stock (> 50)</option>
            </select>
          </div>
        </div>

        <!-- Products Table -->
        <table id="productTable" class="table table-striped dataTable">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Image</th>
              <th>Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $result = mysqli_query($conn, "SELECT * FROM items ORDER BY id DESC");
            while ($row = mysqli_fetch_assoc($result)) {
                $image = !empty($row['image_path']) ? $row['image_path'] : 'no-image.png';
                $stockClass = $row['stock'] < 10 ? 'text-danger' : ($row['stock'] < 50 ? 'text-warning' : 'text-success');
                echo "<tr data-category='" . htmlspecialchars($row['category']) . "' data-stock='" . $row['stock'] . "'>
                    <td>{$row['id']}</td>
                    <td><img src='../uploads/{$image}' width='50' height='50' style='object-fit:cover; border-radius:8px;' class='shadow-sm'></td>
                    <td>" . htmlspecialchars($row['item_name']) . "</td>
                    <td><span class='badge bg-secondary'>" . htmlspecialchars($row['category']) . "</span></td>
                    <td>₹ " . number_format($row['item_price'], 2) . "</td>
                    <td><span class='{$stockClass} fw-bold'>" . htmlspecialchars($row['stock']) . "</span></td>
                    <td>
                        <div class='btn-group' role='group'>
                            <button class='btn btn-sm btn-outline-primary' onclick='editItem({$row['id']})'>
                                <i class='bi bi-pencil'></i>
                            </button>
                            <button class='btn btn-sm btn-outline-danger' onclick='deleteItem({$row['id']})'>
                                <i class='bi bi-trash'></i>
                            </button>
                        </div>
                    </td>
                </tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Summary Cards -->
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>
          Inventory Summary
        </h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <div class="text-center">
              <h4 class="text-primary"><?= $totals['total_stock'] ?? 0 ?></h4>
              <small class="text-muted">Total Stock</small>
            </div>
          </div>
          <div class="col-6">
            <div class="text-center">
              <h4 class="text-success">₹ <?= number_format($totals['total_value'] ?? 0, 2) ?></h4>
              <small class="text-muted">Total Value</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-lightning me-2"></i>
          Quick Actions
        </h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-circle me-2"></i>
            Add New Product
          </button>
          <a href="../manage_categories.php" class="btn btn-outline-secondary">
            <i class="bi bi-tags me-2"></i>
            Manage Categories
          </a>
          <a href="../item-stock.php" class="btn btn-outline-info">
            <i class="bi bi-box-seam me-2"></i>
            Stock Report
          </a>
          <a href="dashboard.php" class="btn btn-outline-success">
            <i class="bi bi-speedometer2 me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-plus-circle me-2"></i>
          Add New Product
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="mb-3">
            <label for="item_name" class="form-label">Product Name</label>
            <input type="text" class="form-control" name="item_name" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="item_price" class="form-label">Price (₹)</label>
              <input type="number" class="form-control" name="item_price" step="0.01" min="0" required>
            </div>
            <div class="col-md-6">
              <label for="stock" class="form-label">Stock Quantity</label>
              <input type="number" class="form-control" name="stock" min="0" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select class="form-select" name="category" required>
              <option value="">Select Category</option>
              <?php
              mysqli_data_seek($catResult, 0);
              while ($cat = mysqli_fetch_assoc($catResult)): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>">
                  <?= htmlspecialchars($cat['category']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="item_image" class="form-label">Product Image</label>
            <input type="file" class="form-control" name="item_image" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_item" class="btn btn-primary">
            <i class="bi bi-save me-2"></i>
            Save Product
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Category filter
$('#categoryFilter').on('change', function() {
    const selectedCategory = this.value;
    $('#productTable tbody tr').each(function() {
        const rowCategory = $(this).data('category');
        if (selectedCategory === '' || rowCategory === selectedCategory) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Stock filter
$('#stockFilter').on('change', function() {
    const selectedStock = this.value;
    $('#productTable tbody tr').each(function() {
        const rowStock = parseInt($(this).data('stock'));
        let show = true;

        if (selectedStock === 'low' && rowStock >= 10) show = false;
        if (selectedStock === 'medium' && (rowStock < 10 || rowStock > 50)) show = false;
        if (selectedStock === 'high' && rowStock <= 50) show = false;

        if (show) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

function editItem(id) {
    window.location.href = '../edit-item.php?id=' + id;
}

function deleteItem(id) {
    if (confirm('Are you sure you want to delete this item?')) {
        window.location.href = '../delete-item.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>