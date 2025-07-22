<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get item ID
$item_id = $_GET['id'] ?? null;
if (!$item_id) {
    header('Location: item-stock.php?error=' . urlencode('Item ID is required'));
    exit;
}

// Fetch item details
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: item-stock.php?error=' . urlencode('Item not found'));
    exit;
}

$item = $result->fetch_assoc();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $item_price = floatval($_POST['item_price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($item_name) || $item_price <= 0) {
        $error = 'Item name and price are required, and price must be greater than 0.';
    } else {
        // Handle image upload
        $imagePath = $item['image_path']; // Keep existing image by default
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/items/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = 'item_' . $item_id . '_' . time() . '.' . $fileExtension;
                $newImagePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $newImagePath)) {
                    // Delete old image if it exists
                    if (!empty($imagePath) && file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                    $imagePath = $newImagePath;
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid image format. Please upload JPG, JPEG, PNG, or GIF files only.';
            }
        }
        
        if (empty($error)) {
            // Update item - use basic columns that definitely exist
            $updateQuery = $conn->prepare("UPDATE items SET item_name = ?, item_price = ?, category = ?, stock = ? WHERE id = ?");
            
            if (!$updateQuery) {
                $error = 'Failed to prepare update statement: ' . $conn->error;
            } else {
                $updateQuery->bind_param("sdsii", $item_name, $item_price, $category, $stock, $item_id);
                
                if ($updateQuery->execute()) {
                    // Try to update description if the column exists
                    if (!empty($description)) {
                        $descQuery = $conn->prepare("UPDATE items SET description = ? WHERE id = ?");
                        if ($descQuery) {
                            $descQuery->bind_param("si", $description, $item_id);
                            $descQuery->execute(); // Don't fail if this doesn't work
                        }
                    }
                    
                    // Try to update image path if it was changed
                    if ($imagePath !== $item['image_path']) {
                        $imgQuery = $conn->prepare("UPDATE items SET image_path = ? WHERE id = ?");
                        if ($imgQuery) {
                            $imgQuery->bind_param("si", $imagePath, $item_id);
                            $imgQuery->execute(); // Don't fail if this doesn't work
                        }
                    }
                    
                    $success = true;
                    // Refresh item data
                    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $item_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $item = $result->fetch_assoc();
                    }
                } else {
                    $error = 'Failed to update item: ' . $conn->error;
                }
            }
        }
    }
}

// Get categories for dropdown
$categories = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category");

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-header d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="page-title mb-0">Edit Item</h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="pages/dashboard/dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="item-stock.php">Stock Management</a></li>
                                    <li class="breadcrumb-item active">Edit Item</li>
                                </ol>
                            </nav>
                        </div>
                        <div>
                            <a href="item-stock.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Stock
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            Item updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-box me-2"></i>
                                Item Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="editItemForm">
                                <div class="row">
                                    <!-- Current Image Display -->
                                    <div class="col-md-3 text-center mb-4">
                                        <div class="mb-3">
                                            <label class="form-label">Current Image</label>
                                            <div class="image-preview">
                                                <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" 
                                                         class="img-fluid rounded" 
                                                         style="max-width: 200px; max-height: 200px;" 
                                                         id="currentImage">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 200px; height: 200px; margin: 0 auto;" id="currentImage">
                                                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="image" class="form-label">Update Image</label>
                                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                            <div class="form-text">JPG, JPEG, PNG, GIF (Max: 5MB)</div>
                                        </div>
                                    </div>

                                    <!-- Item Details -->
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="item_name" class="form-label">
                                                    Item Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="item_name" name="item_name" 
                                                       value="<?= htmlspecialchars($item['item_name']) ?>" 
                                                       required placeholder="Enter item name">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="category" class="form-label">Category</label>
                                                <div class="input-group">
                                                    <select class="form-select" id="category" name="category">
                                                        <option value="">Select or enter category</option>
                                                        <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                                                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                                                <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                                                        <?= $item['category'] === $cat['category'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($cat['category']) ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleCustomCategory()">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                                <input type="text" class="form-control mt-2" id="custom_category" 
                                                       placeholder="Enter new category" style="display: none;">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="item_price" class="form-label">
                                                    Item Price (₹) <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="item_price" name="item_price" 
                                                           value="<?= htmlspecialchars($item['item_price']) ?>" 
                                                           required min="0.01" step="0.01" placeholder="0.00">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="stock" class="form-label">Stock Quantity</label>
                                                <input type="number" class="form-control" id="stock" name="stock" 
                                                       value="<?= htmlspecialchars($item['stock']) ?>" 
                                                       min="0" placeholder="Enter stock quantity">
                                            </div>

                                            <div class="col-12">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="4" 
                                                          placeholder="Enter item description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="item-stock.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                            <button type="reset" class="btn btn-outline-warning">
                                                <i class="bi bi-arrow-clockwise"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Update Item
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Item Statistics Card -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-graph-up me-2"></i>
                                        Item Statistics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="border-end">
                                                <h4 class="text-primary mb-0"><?= $item['stock'] ?></h4>
                                                <small class="text-muted">Current Stock</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="border-end">
                                                <h4 class="text-success mb-0">₹<?= number_format($item['item_price'], 2) ?></h4>
                                                <small class="text-muted">Unit Price</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-info mb-0">₹<?= number_format($item['stock'] * $item['item_price'], 2) ?></h4>
                                            <small class="text-muted">Total Value</small>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    
                                    <div class="text-center">
                                        <h6 class="mb-1">Stock Status</h6>
                                        <?php if ($item['stock'] == 0): ?>
                                            <span class="badge bg-danger fs-6">Out of Stock</span>
                                        <?php elseif ($item['stock'] <= 10): ?>
                                            <span class="badge bg-warning fs-6">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success fs-6">In Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-tools me-2"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="updateStockQuick()">
                                            <i class="bi bi-pencil-square"></i> Quick Stock Update
                                        </button>
                                        <a href="item-stock.php" class="btn btn-outline-info">
                                            <i class="bi bi-list-ul"></i> View All Items
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                            <i class="bi bi-trash"></i> Delete Item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
// Image preview functionality
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentImage = document.getElementById('currentImage');
            currentImage.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-width: 200px; max-height: 200px;">`;
        };
        reader.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('editItemForm').addEventListener('submit', function(e) {
    const itemName = document.getElementById('item_name').value.trim();
    const itemPrice = parseFloat(document.getElementById('item_price').value);
    
    if (!itemName) {
        e.preventDefault();
        showAlert('Please enter item name.', 'danger');
        document.getElementById('item_name').focus();
        return false;
    }
    
    if (itemPrice <= 0) {
        e.preventDefault();
        showAlert('Item price must be greater than 0.', 'danger');
        document.getElementById('item_price').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
    submitBtn.disabled = true;
    
    // Re-enable after 3 seconds (in case of form errors)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

// Toggle custom category input
function toggleCustomCategory() {
    const customInput = document.getElementById('custom_category');
    const categorySelect = document.getElementById('category');
    
    if (customInput.style.display === 'none') {
        customInput.style.display = 'block';
        customInput.focus();
        categorySelect.value = '';
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
    }
}

// Use custom category value when typing
document.getElementById('custom_category').addEventListener('input', function() {
    document.getElementById('category').value = this.value;
});

// Quick stock update
function updateStockQuick() {
    const currentStock = <?= $item['stock'] ?>;
    const newStock = prompt(`Current stock: ${currentStock}\nEnter new stock quantity:`, currentStock);
    
    if (newStock !== null && !isNaN(newStock) && newStock >= 0) {
        document.getElementById('stock').value = parseInt(newStock);
        showAlert('Stock quantity updated in form. Click "Update Item" to save.', 'info');
    }
}

// Delete confirmation
function confirmDelete() {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        window.location.href = `delete_item.php?id=<?= $item['id'] ?>`;
    }
}

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);

// Show alert function
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="bi bi-info-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>