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
    // Fix: Use new_category if category is __new__ or empty
    $category = '';
    if (isset($_POST['category']) && $_POST['category'] !== '__new__' && $_POST['category'] !== '') {
        $category = trim($_POST['category']);
    } elseif (isset($_POST['new_category']) && trim($_POST['new_category']) !== '') {
        $category = trim($_POST['new_category']);
    }
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
$categoriesFromItems = false;
try {
    // First try to get from categories table
    $categoryQuery = "SELECT id, name as category, color, icon FROM categories ORDER BY name ASC";
    $categories = $conn->query($categoryQuery);
    
    // If categories table doesn't exist or is empty, fallback to distinct categories from items
    if (!$categories || $categories->num_rows == 0) {
        $categoryQuery = "SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
        $categories = $conn->query($categoryQuery);
        $categoriesFromItems = true;
    }
} catch (Exception $e) {
    // Categories query failed - fallback to items
    $categories = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categoriesFromItems = true;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <!-- Modern Header with Gradient -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="gradient-text mb-2" style="font-size: 2.25rem; font-weight: 700;">
                <i class="bi bi-plus-circle me-3"></i>Add New Product
            </h1>
            <p class="text-muted" style="font-size: 1.1rem;">Create a new product entry for your inventory system</p>
        </div>
        <div class="d-flex gap-2">
            <a href="pages/products/products.php" class="btn btn-outline-primary">
                <i class="bi bi-list"></i>
                View All Products
            </a>
            <a href="item-stock.php" class="btn btn-outline-secondary">
                <i class="bi bi-boxes"></i>
                Manage Stock
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in-down" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Success!</strong> Product has been added successfully to your inventory.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in-down" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error!</strong> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2 text-primary"></i>
                        Product Information
                    </h5>
                    <span class="badge badge-info">Required Fields *</span>
                </div>
                <div class="card-body">
                    <form method="POST" data-autosave="add_product" class="needs-validation" novalidate>
                        <div class="row g-4">
                            <!-- Product Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-tag me-2 text-primary"></i>
                                    Product Name *
                                </label>
                                <input type="text" 
                                       name="item_name" 
                                       class="form-control form-control-lg" 
                                       placeholder="Enter product name" 
                                       value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a valid product name.
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Use a clear, descriptive name for easy identification
                                </div>
                            </div>
                            
                            <!-- Price -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-currency-rupee me-2 text-success"></i>
                                    Price (₹) *
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-success text-white">
                                        <i class="bi bi-currency-rupee"></i>
                                    </span>
                                    <input type="number" 
                                           name="item_price" 
                                           class="form-control" 
                                           placeholder="0.00" 
                                           step="0.01" 
                                           min="0.01"
                                           value="<?= htmlspecialchars($_POST['item_price'] ?? '') ?>" 
                                           required>
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a valid price greater than 0.
                                </div>
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-collection me-2 text-info"></i>
                                    Category
                                </label>
                                <div class="input-group">
                                    <select name="category" class="form-select form-select-lg" id="categorySelect">
                                        <option value="">-- Select or Add Category --</option>
                                        <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                                <?php if ($categoriesFromItems): ?>
                                                    <!-- Categories from items table (fallback) -->
                                                    <option value="<?= htmlspecialchars($cat['category']) ?>"
                                                            data-color="#007bff"
                                                            data-icon="bi-tag"
                                                            <?= (($_POST['category'] ?? '') === $cat['category']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat['category']) ?>
                                                    </option>
                                                <?php else: ?>
                                                    <!-- Categories from categories table -->
                                                    <option value="<?= htmlspecialchars($cat['category']) ?>"
                                                            data-color="<?= htmlspecialchars($cat['color'] ?? '#007bff') ?>"
                                                            data-icon="<?= htmlspecialchars($cat['icon'] ?? 'bi-tag') ?>"
                                                            <?= (($_POST['category'] ?? '') === $cat['category']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat['category']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                        <option value="__new__" class="text-primary fw-bold">
                                            <i class="bi bi-plus-circle"></i> Add New Category
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-outline-info" id="manageCategoriesBtn" title="Manage Categories">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                    <input type="text" 
                                           name="new_category" 
                                           class="form-control form-control-lg" 
                                           placeholder="Enter new category name" 
                                           style="display: none;" 
                                           id="newCategoryInput">
                                </div>
                                <div class="form-text" id="categoryPreview" style="display: none;">
                                    <span class="badge badge-info">
                                        <i class="bi-tag me-1"></i>
                                        <span class="category-name"></span>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Initial Stock -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-boxes me-2 text-warning"></i>
                                    Initial Stock
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-warning text-dark">
                                        <i class="bi bi-boxes"></i>
                                    </span>
                                    <input type="number" 
                                           name="stock" 
                                           class="form-control" 
                                           placeholder="0" 
                                           min="0"
                                           value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>">
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Leave as 0 if you don't want to track inventory
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-card-text me-2 text-secondary"></i>
                                    Product Description
                                </label>
                                <textarea name="description" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Enter detailed product description, specifications, or notes..."
                                          style="resize: vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Add helpful details like specifications, dimensions, or usage instructions
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="mt-5 pt-4 border-top">
                            <div class="d-flex gap-3 flex-wrap">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save me-2"></i>
                                    Add Product
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>
                                    Reset Form
                                </button>
                                <a href="pages/products/products.php" class="btn btn-outline-primary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Back to Products
                                </a>
                                <button type="button" class="btn btn-outline-info btn-lg" onclick="previewProduct()">
                                    <i class="bi bi-eye me-2"></i>
                                    Preview
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lightning-fill me-2 text-warning"></i>
                        Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="pages/products/products.php" class="btn btn-outline-primary">
                            <i class="bi bi-list-ul me-2"></i>
                            View All Products
                        </a>
                        <a href="item-stock.php" class="btn btn-outline-warning">
                            <i class="bi bi-boxes me-2"></i>
                            Manage Stock
                        </a>
                        <a href="manage_categories.php" class="btn btn-outline-info">
                            <i class="bi bi-tags-fill me-2"></i>
                            Manage Categories
                        </a>
                        <a href="item-full-list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-table me-2"></i>
                            Full Product List
                        </a>
                    </div>
                </div>
            </div>

            <!-- Product Guidelines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lightbulb-fill me-2 text-info"></i>
                        Product Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <div class="guidelines-list">
                        <div class="guideline-item d-flex align-items-start mb-3">
                            <div class="guideline-icon me-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Clear Naming</div>
                                <div class="small text-muted">Use descriptive, easy-to-understand product names</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start mb-3">
                            <div class="guideline-icon me-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Competitive Pricing</div>
                                <div class="small text-muted">Research market rates before setting prices</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start mb-3">
                            <div class="guideline-icon me-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Proper Categorization</div>
                                <div class="small text-muted">Group similar products for better organization</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start">
                            <div class="guideline-icon me-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Detailed Descriptions</div>
                                <div class="small text-muted">Help customers with comprehensive details</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-clock-history me-2 text-secondary"></i>
                        Recent Products
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent products
                    $recentQuery = "SELECT item_name, item_price, created_at FROM items ORDER BY id DESC LIMIT 5";
                    $recentResult = $conn->query($recentQuery);
                    
                    if ($recentResult && $recentResult->num_rows > 0):
                    ?>
                        <div class="recent-products">
                            <?php while ($recent = $recentResult->fetch_assoc()): ?>
                                <div class="recent-item d-flex justify-content-between align-items-center mb-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($recent['item_name']) ?></div>
                                        <div class="small text-muted">₹<?= number_format($recent['item_price'], 2) ?></div>
                                    </div>
                                    <div class="text-muted small">
                                        <?= date('M j', strtotime($recent['created_at'] ?? 'now')) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-inbox display-6 mb-2"></i>
                            <div>No recent products found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
$(document).ready(function() {
    // Initialize form enhancements
    initializeFormValidation();
    initializeCategorySystem();
    initializeFormInteractions();
    
    // Auto-dismiss success alerts
    setTimeout(function() {
        $('.alert-success').fadeOut(400);
    }, 5000);
});

function initializeFormValidation() {
    // Bootstrap form validation
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            
            // Focus on first invalid field
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        form.classList.add('was-validated');
    });
    
    // Real-time validation
    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('blur', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    });
}

function initializeCategorySystem() {
    // Handle category selection
    $('#categorySelect').change(function() {
        if (this.value === '__new__') {
            $('#newCategoryInput').show().focus();
            $(this).hide();
            $('#categoryPreview').hide();
        } else if (this.value) {
            $('#newCategoryInput').hide();
            $(this).show();
            updateCategoryPreview();
        } else {
            $('#newCategoryInput').hide();
            $(this).show();
            $('#categoryPreview').hide();
        }
    });

    // Allow going back to select from dropdown
    $('#newCategoryInput').blur(function() {
        if ($(this).val() === '') {
            $(this).hide();
            $('#categorySelect').show().val('');
            $('#categoryPreview').hide();
        }
    });

    // Manage categories button
    $('#manageCategoriesBtn').click(function() {
        window.open('manage_categories.php', '_blank', 'width=800,height=600,scrollbars=yes');
    });

    // Initialize category preview if there's a selected value
    if ($('#categorySelect').val()) {
        updateCategoryPreview();
    }
}

function initializeFormInteractions() {
    // Price formatting
    $('input[name="item_price"]').on('blur', function() {
        const value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
    
    // Product name suggestions (placeholder for future enhancement)
    $('input[name="item_name"]').on('input', function() {
        // Could add autocomplete suggestions here
    });
    
    // Character counter for description
    const descTextarea = $('textarea[name="description"]');
    const maxLength = 500;
    
    descTextarea.attr('maxlength', maxLength);
    descTextarea.after(`<div class="form-text text-end"><span id="charCount">0</span>/${maxLength} characters</div>`);
    
    descTextarea.on('input', function() {
        $('#charCount').text(this.value.length);
    });
}

function updateCategoryPreview() {
    const selectedOption = $('#categorySelect option:selected');
    const categoryName = selectedOption.val();
    const categoryColor = selectedOption.data('color') || '#007bff';
    const categoryIcon = selectedOption.data('icon') || 'bi-tag';
    
    if (categoryName && categoryName !== '__new__') {
        $('#categoryPreview').show();
        $('#categoryPreview .badge')
            .css('background-color', categoryColor)
            .find('i')
            .attr('class', categoryIcon + ' me-1');
        $('#categoryPreview .category-name').text(categoryName);
    } else {
        $('#categoryPreview').hide();
    }
}

function previewProduct() {
    const formData = new FormData(document.querySelector('form'));
    const productData = Object.fromEntries(formData.entries());
    
    if (!productData.item_name || !productData.item_price) {
        ModernUI.showToast('Please fill in the required fields first', 'warning');
        return;
    }
    
    // Create preview modal
    const previewContent = `
        <div class="product-preview">
            <h5 class="mb-3">Product Preview</h5>
            <div class="row g-3">
                <div class="col-sm-6">
                    <strong>Name:</strong><br>
                    <span class="text-muted">${productData.item_name}</span>
                </div>
                <div class="col-sm-6">
                    <strong>Price:</strong><br>
                    <span class="text-success fw-bold">₹${parseFloat(productData.item_price).toLocaleString()}</span>
                </div>
                <div class="col-sm-6">
                    <strong>Category:</strong><br>
                    <span class="text-muted">${productData.category || productData.new_category || 'Uncategorized'}</span>
                </div>
                <div class="col-sm-6">
                    <strong>Initial Stock:</strong><br>
                    <span class="text-muted">${productData.stock || 0} units</span>
                </div>
                ${productData.description ? `
                <div class="col-12">
                    <strong>Description:</strong><br>
                    <span class="text-muted">${productData.description}</span>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    // Show in a modal (simplified - in a real app you'd use Bootstrap modal)
    const modal = $(`
        <div class="modal fade" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Product Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${previewContent}</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    modal.modal('show');
    
    modal.on('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Global helper functions
function showAlert(message, type) {
    ModernUI.showToast(message, type);
}
</script>

<?php include 'layouts/footer.php'; ?>
