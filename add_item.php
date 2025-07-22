<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Add Product';

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name']);
    $item_price = floatval($_POST['item_price']);
    $category = trim($_POST['category']);
    $stock = intval($_POST['stock'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($item_name) || $item_price <= 0) {
        $error = 'Please provide valid item name and price.';
    } else {
        // Check if item already exists with fallback for different column names
        $checkQuery = $conn->prepare("SELECT id FROM items WHERE item_name = ?");
        
        if (!$checkQuery) {
            // Fallback: try with different column names
            $checkQuery = $conn->prepare("SELECT id FROM items WHERE name = ?");
            
            if (!$checkQuery) {
                $error = 'Database error: Could not check item name - ' . $conn->error;
            }
        }
        
        if (!empty($error)) {
            // Skip the check if there was an error
        } elseif ($checkQuery) {
            $checkQuery->bind_param("s", $item_name);
            $checkQuery->execute();
            $result = $checkQuery->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'An item with this name already exists.';
            }
        }
        
        if (empty($error)) {
            // Try modern schema first, then fallback to basic schema
            $insertQuery = $conn->prepare("INSERT INTO items (item_name, item_price, category, stock, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            if (!$insertQuery) {
                // Fallback 1: Try without created_at column
                $insertQuery = $conn->prepare("INSERT INTO items (item_name, item_price, category, stock, description) VALUES (?, ?, ?, ?, ?)");
                
                if (!$insertQuery) {
                    // Fallback 2: Try minimal schema (core columns only)
                    $insertQuery = $conn->prepare("INSERT INTO items (item_name, item_price, category, stock) VALUES (?, ?, ?, ?)");
                    
                    if (!$insertQuery) {
                        // Fallback 3: Try most basic schema
                        $insertQuery = $conn->prepare("INSERT INTO items (item_name, item_price) VALUES (?, ?)");
                        if (!$insertQuery) {
                            $error = 'Database error: Failed to prepare insert statement - ' . $conn->error;
                        } else {
                            $insertQuery->bind_param("sd", $item_name, $item_price);
                        }
                    } else {
                        $insertQuery->bind_param("sdsi", $item_name, $item_price, $category, $stock);
                    }
                } else {
                    $insertQuery->bind_param("sdsis", $item_name, $item_price, $category, $stock, $description);
                }
            } else {
                $insertQuery->bind_param("sdsis", $item_name, $item_price, $category, $stock, $description);
            }
            
            // Execute the insert if we have a valid statement
            if (empty($error) && $insertQuery && $insertQuery->execute()) {
                $success = true;
                
                // Try to update additional columns if the basic insert succeeded
                $itemId = $conn->insert_id;
                
                // Try to add description if it wasn't included in the main insert
                if (!empty($description) && $itemId) {
                    $descQuery = $conn->prepare("UPDATE items SET description = ? WHERE id = ?");
                    if ($descQuery) {
                        $descQuery->bind_param("si", $description, $itemId);
                        $descQuery->execute();
                    }
                }
                
                // Try to add category if it wasn't included in the main insert
                if (!empty($category) && $itemId) {
                    $catQuery = $conn->prepare("UPDATE items SET category = ? WHERE id = ?");
                    if ($catQuery) {
                        $catQuery->bind_param("si", $category, $itemId);
                        $catQuery->execute();
                    }
                }
                
            } elseif (empty($error)) {
                $error = 'Failed to add item: ' . ($insertQuery ? $conn->error : 'Could not prepare statement');
            }
        }
    }
}

// Get categories for dropdown
// Get existing categories for dropdown - try categories table first, then fallback
$categories = null;
try {
    // First try to get from categories table
    $categoryQuery = "SELECT id, name as category, color, icon FROM categories ORDER BY name ASC";
    $categories = $conn->query($categoryQuery);
    
    // If categories table doesn't exist or is empty, fallback to distinct categories from items
    if (!$categories || $categories->num_rows == 0) {
        $categoryQuery = "SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
        $categories = $conn->query($categoryQuery);
    }
} catch (Exception $e) {
    // Categories query failed - fallback to items
    $categories = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Add New Product</h1>
            <p class="text-muted">Add a new product to your inventory</p>
        </div>
        <div>
            <a href="pages/products/products.php" class="btn btn-outline-primary">
                <i class="bi bi-list"></i> View All Products
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Product added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
            // Clear form after successful submission
            setTimeout(function() {
                document.querySelector('form').reset();
            }, 1000);
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Product Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="item_name" class="form-control" 
                                       placeholder="Enter product name" 
                                       value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Price (₹) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="item_price" class="form-control" 
                                           placeholder="0.00" step="0.01" min="0.01"
                                           value="<?= htmlspecialchars($_POST['item_price'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <div class="input-group">
                                    <select name="category" class="form-select" id="categorySelect">
                                        <option value="">-- Select or Add Category --</option>
                                        <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($cat['category']) ?>"
                                                        data-color="<?= htmlspecialchars($cat['color'] ?? '#007bff') ?>"
                                                        data-icon="<?= htmlspecialchars($cat['icon'] ?? 'bi-tag') ?>"
                                                        <?= (($_POST['category'] ?? '') === $cat['category']) ? 'selected' : '' ?>>
                                                    <?php if (isset($cat['icon'])): ?>
                                                        <?= htmlspecialchars($cat['category']) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($cat['category']) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                        <option value="__new__">+ Add New Category</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="manageCategoriesBtn" title="Manage Categories">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                    <input type="text" name="new_category" class="form-control" 
                                           placeholder="Enter new category" style="display: none;" id="newCategoryInput">
                                </div>
                                <div class="form-text">
                                    <small id="categoryPreview" style="display: none;">
                                        <i class="bi-tag me-1"></i>
                                        <span class="category-name"></span>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Initial Stock</label>
                                <input type="number" name="stock" class="form-control" 
                                       placeholder="0" min="0"
                                       value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>">
                                <div class="form-text">Leave blank or 0 if not tracking stock</div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Description (Optional)</label>
                                <textarea name="description" class="form-control" rows="3" 
                                          placeholder="Enter product description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Add Product
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </button>
                            <a href="pages/products/products.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Products
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="pages/products/products.php" class="btn btn-outline-primary">
                            <i class="bi bi-list"></i> View All Products
                        </a>
                        <a href="item-stock.php" class="btn btn-outline-warning">
                            <i class="bi bi-boxes"></i> Manage Stock
                        </a>
                        <a href="item-full-list.php" class="btn btn-outline-info">
                            <i class="bi bi-table"></i> Full Product List
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use clear, descriptive product names</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Set competitive prices</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Categorize products for better organization</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Add descriptions to help customers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize category dropdown with dynamic updates
    loadCategoriesFromStorage();
    
    // Listen for category updates from manage_categories.php
    window.addEventListener('categoriesUpdated', function(e) {
        updateCategoryDropdown(e.detail);
    });

    // Handle category selection
    $('#categorySelect').change(function() {
        if (this.value === '__new__') {
            $('#newCategoryInput').show().attr('name', 'category').focus();
            $(this).hide().attr('name', '');
            $('#categoryPreview').hide();
        } else if (this.value) {
            updateCategoryPreview();
        } else {
            $('#categoryPreview').hide();
        }
    });

    // Allow going back to select from dropdown
    $('#newCategoryInput').blur(function() {
        if ($(this).val() === '') {
            $(this).hide().attr('name', '');
            $('#categorySelect').show().attr('name', 'category').val('');
            $('#categoryPreview').hide();
        }
    });

    // Manage categories button
    $('#manageCategoriesBtn').click(function() {
        window.open('manage_categories.php', '_blank');
    });

    // Form validation
    $('form').on('submit', function(e) {
        const itemName = $('input[name="item_name"]').val().trim();
        const itemPrice = parseFloat($('input[name="item_price"]').val());

        if (!itemName) {
            e.preventDefault();
            showAlert('Please enter a product name', 'warning');
            $('input[name="item_name"]').focus();
            return false;
        }

        if (!itemPrice || itemPrice <= 0) {
            e.preventDefault();
            showAlert('Please enter a valid price', 'warning');
            $('input[name="item_price"]').focus();
            return false;
        }
    });

    // Auto-dismiss success alerts
    setTimeout(function() {
        $('.alert-success').fadeOut();
    }, 3000);

    // Initialize category preview if there's a selected value
    if ($('#categorySelect').val()) {
        updateCategoryPreview();
    }
});

function updateCategoryPreview() {
    const selectedOption = $('#categorySelect option:selected');
    const categoryName = selectedOption.val();
    const categoryColor = selectedOption.data('color') || '#007bff';
    const categoryIcon = selectedOption.data('icon') || 'bi-tag';
    
    if (categoryName && categoryName !== '__new__') {
        $('#categoryPreview').show();
        $('#categoryPreview i').attr('class', categoryIcon + ' me-1').css('color', categoryColor);
        $('#categoryPreview .category-name').text(categoryName);
    } else {
        $('#categoryPreview').hide();
    }
}

function loadCategoriesFromStorage() {
    const storedCategories = localStorage.getItem('categories');
    if (storedCategories) {
        try {
            const categories = JSON.parse(storedCategories);
            updateCategoryDropdown(categories);
        } catch (e) {
            console.log('Error parsing stored categories:', e);
        }
    }
}

function updateCategoryDropdown(categories) {
    const select = $('#categorySelect');
    const currentValue = select.val();
    
    // Clear existing options except default and "add new"
    select.find('option').not(':first').not(':last').remove();
    
    // Add updated categories
    categories.forEach(function(category) {
        const option = $('<option></option>')
            .val(category.name)
            .text(category.name)
            .attr('data-color', category.color || '#007bff')
            .attr('data-icon', category.icon || 'bi-tag');
            
        select.find('option:last').before(option);
    });
    
    // Restore selection if it still exists
    if (currentValue && select.find(`option[value="${currentValue}"]`).length > 0) {
        select.val(currentValue);
        updateCategoryPreview();
    }
    
    console.log('Category dropdown updated with', categories.length, 'categories');
}

function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
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
