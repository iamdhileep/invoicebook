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
    // First try to get from categories table (simplified query for actual table structure)
    $categoryQuery = "SELECT name as category FROM categories ORDER BY name ASC";
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
    <div class="container-fluid">
        <!-- Compact Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1" style="font-weight: 600;">
                    <i class="bi bi-plus-circle me-2"></i>Add New Product
                </h1>
                <p class="text-muted mb-0" style="font-size: 0.875rem;">Create a new product entry for your inventory system</p>
            </div>
            <div class="d-flex gap-2">
                <a href="pages/products/products.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-list"></i>
                    View All Products
                </a>
                <a href="item-stock.php" class="btn btn-outline-secondary btn-sm">
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

    <div class="row g-3">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Product Information
                    </h6>
                    <span class="badge badge-info">Required Fields *</span>
                </div>
                <div class="card-body">
                    <form method="POST" data-autosave="add_product" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Product Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-tag me-1 text-primary"></i>
                                    Product Name *
                                </label>
                                <input type="text" 
                                       name="item_name" 
                                       class="form-control" 
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
                                    <i class="bi bi-currency-rupee me-1 text-success"></i>
                                    Price (₹) *
                                </label>
                                <div class="input-group">
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
                                    <i class="bi bi-collection me-1 text-info"></i>
                                    Category
                                </label>
                                <div class="input-group">
                                    <select name="category" class="form-select" id="categorySelect">
                                        <option value="">-- Loading Categories... --</option>
                                    </select>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-success btn-sm" id="quickAddBtn" title="Quick Add Category">
                                            <i class="bi bi-plus-circle"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="quickEditBtn" title="Edit Selected Category">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="quickDeleteBtn" title="Delete Selected Category">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-sm" id="manageCategoriesBtn" title="Manage All Categories">
                                            <i class="bi bi-gear-fill"></i>
                                        </button>
                                    </div>
                                    <input type="text" 
                                           name="new_category" 
                                           class="form-control" 
                                           placeholder="Enter new category name and press Enter" 
                                           style="display: none;" 
                                           id="newCategoryInput"
                                           maxlength="50">
                                </div>
                                <div class="form-text" id="categoryHelp">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Select an existing category or create a new one
                                </div>
                                <div class="form-text" id="newCategoryHelp" style="display: none;">
                                    <i class="bi bi-lightbulb me-1 text-warning"></i>
                                    Press <kbd>Enter</kbd> to add category, <kbd>Esc</kbd> to cancel
                                    <br><small class="text-muted">Note: Duplicate categories will be rejected</small>
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
                                    <i class="bi bi-boxes me-1 text-warning"></i>
                                    Initial Stock
                                </label>
                                <div class="input-group">
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
                                    <i class="bi bi-card-text me-1 text-secondary"></i>
                                    Product Description
                                </label>
                                <textarea name="description" 
                                          class="form-control" 
                                          rows="3" 
                                          placeholder="Enter detailed product description, specifications, or notes..."
                                          style="resize: vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Add helpful details like specifications, dimensions, or usage instructions
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-save me-1"></i>
                                    Add Product
                                </button>
                                <button type="reset" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    Reset Form
                                </button>
                                <a href="pages/products/products.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Back to Products
                                </a>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="previewProduct()">
                                    <i class="bi bi-eye me-1"></i>
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
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lightning-fill me-1 text-warning"></i>
                        Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="pages/products/products.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-list-ul me-1"></i>
                            View All Products
                        </a>
                        <a href="item-stock.php" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-boxes me-1"></i>
                            Manage Stock
                        </a>
                        <a href="manage_categories.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-tags-fill me-1"></i>
                            Manage Categories
                        </a>
                        <a href="item-full-list.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-table me-1"></i>
                            Full Product List
                        </a>
                    </div>
                </div>
            </div>

            <!-- Product Guidelines -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lightbulb-fill me-1 text-info"></i>
                        Product Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <div class="guidelines-list">
                        <div class="guideline-item d-flex align-items-start mb-2">
                            <div class="guideline-icon me-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Clear Naming</div>
                                <div class="small text-muted">Use descriptive, easy-to-understand product names</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start mb-2">
                            <div class="guideline-icon me-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Competitive Pricing</div>
                                <div class="small text-muted">Research market rates before setting prices</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start mb-2">
                            <div class="guideline-icon me-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Proper Categorization</div>
                                <div class="small text-muted">Group similar products for better organization</div>
                            </div>
                        </div>
                        <div class="guideline-item d-flex align-items-start">
                            <div class="guideline-icon me-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Detailed Descriptions</div>
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
                        <i class="bi bi-clock-history me-1 text-secondary"></i>
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
                                <div class="recent-item d-flex justify-content-between align-items-center mb-2">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold small"><?= htmlspecialchars($recent['item_name']) ?></div>
                                        <div class="small text-muted">₹<?= number_format($recent['item_price'], 2) ?></div>
                                    </div>
                                    <div class="text-muted small">
                                        <?= date('M j', strtotime($recent['created_at'] ?? 'now')) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-2">
                            <i class="bi bi-inbox h4 mb-2"></i>
                            <div class="small">No recent products found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced category input styling */
#newCategoryInput {
    border-color: #0891b2;
    box-shadow: 0 0 0 0.2rem rgba(8, 145, 178, 0.25);
}

#newCategoryInput:focus {
    border-color: #0891b2;
    box-shadow: 0 0 0 0.2rem rgba(8, 145, 178, 0.25);
}

/* Category preview badge styling */
.badge.badge-info {
    background-color: var(--info-color) !important;
    color: white;
}

/* Enhanced help text */
#newCategoryHelp {
    color: #d97706;
}

/* Compact form spacing for add_item page */
.main-content .form-label {
    margin-bottom: 0.375rem;
    font-size: 0.875rem;
}

.main-content .form-text {
    margin-top: 0.25rem;
    font-size: 0.75rem;
}

.main-content .input-group-text {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

.main-content .btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.main-content .card-header h6 {
    font-size: 0.875rem;
    font-weight: 600;
}

.main-content .guidelines-list .guideline-item {
    margin-bottom: 0.5rem;
}

.main-content .guidelines-list .guideline-item:last-child {
    margin-bottom: 0;
}

.main-content .recent-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--gray-100);
}

.main-content .recent-item:last-child {
    border-bottom: none;
}

/* Compact alert styling */
.main-content .alert {
    padding: 0.5rem 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

/* Compact badge styling */
.main-content .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Form validation feedback compact */
.main-content .invalid-feedback,
.main-content .valid-feedback {
    font-size: 0.75rem;
}
</style>

<?php include 'layouts/footer.php'; ?>

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
    console.log('Initializing category system...');
    
    // Load categories dynamically on page load
    refreshCategories();
    
    // Handle category selection
    $('#categorySelect').change(function() {
        console.log('Category select changed to:', this.value);
        
        if (this.value === '__new__') {
            $('#newCategoryInput').show().focus();
            $('#categorySelect').hide();
            $('#categoryPreview').hide();
            $('#categoryHelp').hide();
            $('#newCategoryHelp').show();
        } else if (this.value) {
            $('#newCategoryInput').hide();
            $('#categorySelect').show();
            $('#categoryHelp').show();
            $('#newCategoryHelp').hide();
            updateCategoryPreview();
        } else {
            $('#newCategoryInput').hide();
            $('#categorySelect').show();
            $('#categoryPreview').hide();
            $('#categoryHelp').show();
            $('#newCategoryHelp').hide();
        }
    });

    // Handle new category input
    $('#newCategoryInput').on('keypress', function(e) {
        console.log('Key pressed in new category input:', e.which);
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const categoryName = $(this).val().trim();
            console.log('Attempting to add category:', categoryName);
            
            if (categoryName) {
                console.log('Sending AJAX request to save_category.php');
                // First, try to add category to database via AJAX
                $.ajax({
                    url: 'save_category.php',
                    method: 'POST',
                    data: { name: categoryName },
                    dataType: 'json',
                    beforeSend: function() {
                        console.log('AJAX request started');
                    },
                    success: function(response) {
                        console.log('AJAX success response:', response);
                        if (response.success) {
                            console.log('Category saved successfully, refreshing dropdown');
                            // Refresh the entire dropdown to get the new category
                            refreshCategories();
                            
                            // After refresh completes, select the new category
                            setTimeout(function() {
                                $('#categorySelect').val(categoryName);
                                $('#newCategoryInput').val('').hide();
                                $('#categorySelect').show();
                                $('#categoryHelp').show();
                                $('#newCategoryHelp').hide();
                                updateCategoryPreview();
                                console.log('Category added and selected:', categoryName);
                            }, 100);
                            
                            // Show success feedback
                            showSuccessToast('Category "' + categoryName + '" added successfully!');
                        } else {
                            console.error('Server error:', response.message);
                            
                            // Clear the input and show the dropdown again
                            $('#newCategoryInput').val('').hide();
                            $('#categorySelect').show();
                            $('#categoryHelp').show();
                            $('#newCategoryHelp').hide();
                            
                            if (response.message && response.message.includes('already exists')) {
                                alert('This category already exists! Please choose a different name or select it from the dropdown.');
                            } else {
                                alert('Error adding category: ' + (response.message || 'Unknown error'));
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error, xhr.responseText);
                        
                        // Clear the input and show the dropdown again
                        $('#newCategoryInput').val('').hide();
                        $('#categorySelect').show();
                        $('#categoryHelp').show();
                        $('#newCategoryHelp').hide();
                        
                        // Fallback: add to dropdown only (legacy behavior)
                        console.log('Using fallback method to add category');
                        const newOption = new Option(categoryName, categoryName, true, true);
                        $('#categorySelect').append(newOption);
                        $('#categorySelect').val(categoryName);
                        updateCategoryPreview();
                        
                        showSuccessToast('Category "' + categoryName + '" added to form (will be saved with product)');
                    }
                });
            } else {
                console.log('Empty category name provided');
            }
        }
    });

    // Handle escape key to cancel new category
    $('#newCategoryInput').on('keyup', function(e) {
        if (e.which === 27) { // Escape key
            $(this).val('').hide();
            $('#categorySelect').show().val('');
            $('#categoryPreview').hide();
            $('#categoryHelp').show();
            $('#newCategoryHelp').hide();
        }
    });

    // Allow going back to select from dropdown
    $('#newCategoryInput').blur(function() {
        // Delay to allow for any click events to process
        setTimeout(() => {
            if ($(this).val() === '') {
                $(this).hide();
                $('#categorySelect').show().val('');
                $('#categoryPreview').hide();
                $('#categoryHelp').show();
                $('#newCategoryHelp').hide();
            }
        }, 150);
    });

    // Quick Add Category button
    $('#quickAddBtn').click(function(e) {
        e.preventDefault();
        console.log('Quick Add button clicked');
        $('#categorySelect').val('__new__').trigger('change');
    });
    
    // Quick Edit Category button
    $('#quickEditBtn').click(function(e) {
        e.preventDefault();
        console.log('Quick Edit button clicked');
        
        const selectedValue = $('#categorySelect').val();
        console.log('Selected category for edit:', selectedValue);
        
        if (!selectedValue || selectedValue === '__new__') {
            alert('Please select a category to edit first.');
            return;
        }
        
        const newName = prompt('Enter new name for category "' + selectedValue + '":', selectedValue);
        if (newName && newName.trim() !== '' && newName !== selectedValue) {
            editCategory(selectedValue, newName.trim());
        }
    });
    
    // Quick Delete Category button
    $('#quickDeleteBtn').click(function(e) {
        e.preventDefault();
        console.log('Quick Delete button clicked');
        
        const selectedValue = $('#categorySelect').val();
        console.log('Selected category for delete:', selectedValue);
        
        if (!selectedValue || selectedValue === '__new__') {
            alert('Please select a category to delete first.');
            return;
        }
        
        if (confirm('Are you sure you want to delete the category "' + selectedValue + '"?\n\nThis action cannot be undone.')) {
            deleteCategory(selectedValue);
        }
    });

    // Manage categories button
    $('#manageCategoriesBtn').click(function(e) {
        e.preventDefault();
        console.log('Manage Categories button clicked');
        
        const popup = window.open('manage_categories.php', 'managecategories', 'width=900,height=700,scrollbars=yes,resizable=yes');
        
        // Listen for category updates from the popup
        const checkClosed = setInterval(function() {
            if (popup.closed) {
                clearInterval(checkClosed);
                // Refresh categories dropdown
                refreshCategories();
            }
        }, 1000);
    });

    // Initialize category preview if there's a selected value
    if ($('#categorySelect').val()) {
        updateCategoryPreview();
    }
}

// Function to edit a category
function editCategory(oldName, newName) {
    // First get the category ID from the database
    $.ajax({
        url: 'get_category_id.php',
        method: 'POST',
        data: { name: oldName },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.id) {
                // Now update the category
                $.ajax({
                    url: 'manage_categories.php',
                    method: 'POST',
                    data: { 
                        action: 'edit',
                        id: response.id,
                        name: newName
                    },
                    dataType: 'json',
                    success: function(editResponse) {
                        if (editResponse.success) {
                            // Refresh the dropdown to get updated categories
                            refreshCategories();
                            
                            // After refresh, select the updated category
                            setTimeout(function() {
                                $('#categorySelect').val(newName);
                                updateCategoryPreview();
                            }, 100);
                            
                            showSuccessToast('Category updated successfully!');
                        } else {
                            alert('Error updating category: ' + (editResponse.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error communicating with server while updating category.');
                    }
                });
            } else {
                alert('Error finding category: ' + (response.message || 'Category not found'));
            }
        },
        error: function() {
            alert('Error communicating with server while finding category.');
        }
    });
}

// Function to delete a category
function deleteCategory(categoryName) {
    // First get the category ID from the database
    $.ajax({
        url: 'get_category_id.php',
        method: 'POST',
        data: { name: categoryName },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.id) {
                // Now delete the category
                $.ajax({
                    url: 'manage_categories.php',
                    method: 'POST',
                    data: { 
                        action: 'delete',
                        id: response.id
                    },
                    dataType: 'json',
                    success: function(deleteResponse) {
                        if (deleteResponse.success) {
                            // Refresh the dropdown to remove deleted category
                            refreshCategories();
                            
                            // Clear selection and preview
                            setTimeout(function() {
                                $('#categorySelect').val('');
                                $('#categoryPreview').hide();
                            }, 100);
                            
                            showSuccessToast('Category deleted successfully!');
                        } else {
                            alert('Error deleting category: ' + (deleteResponse.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error communicating with server while deleting category.');
                    }
                });
            } else {
                alert('Error finding category: ' + (response.message || 'Category not found'));
            }
        },
        error: function() {
            alert('Error communicating with server while finding category.');
        }
    });
}

// Function to refresh categories from server
function refreshCategories() {
    console.log('Refreshing categories from server...');
    
    $.ajax({
        url: 'get_categories.php',
        method: 'GET',
        dataType: 'json',
        beforeSend: function() {
            console.log('Loading categories...');
            $('#categorySelect').html('<option value="">-- Loading Categories... --</option>');
        },
        success: function(data) {
            console.log('Categories loaded successfully:', data);
            
            const currentValue = $('#categorySelect').val();
            const postValue = '<?= htmlspecialchars($_POST['category'] ?? '') ?>';
            const $select = $('#categorySelect');
            
            // Clear existing options
            $select.empty();
            
            // Add default option
            $select.append('<option value="">-- Select or Add Category --</option>');
            
            // Add categories from server
            if (data && data.length > 0) {
                console.log('Adding', data.length, 'categories to dropdown');
                data.forEach(function(category) {
                    const option = $('<option></option>')
                        .val(category.name)
                        .text(category.name)
                        .attr('data-color', category.color || '#007bff')
                        .attr('data-icon', category.icon || 'bi-tag');
                    
                    // Check if this should be selected
                    if (postValue && postValue === category.name) {
                        option.prop('selected', true);
                    }
                        
                    $select.append(option);
                });
                
                // Update help text to show count
                $('#categoryHelp').html('<i class="bi bi-info-circle me-1"></i>Select from ' + data.length + ' existing categories or create a new one');
            } else {
                console.log('No categories found');
                $('#categoryHelp').html('<i class="bi bi-info-circle me-1"></i>No categories found - create your first category');
            }
            
            // Add "Add New Category" option
            $select.append('<option value="__new__" class="text-primary fw-bold">+ Add New Category</option>');
            
            // Restore selection if it still exists and no POST value
            if (!postValue && currentValue && currentValue !== '-- Loading Categories... --') {
                $select.val(currentValue);
            }
            
            // Update preview if there's a selected value
            if ($select.val() && $select.val() !== '' && $select.val() !== '__new__') {
                updateCategoryPreview();
            }
            
            console.log('Categories refresh completed successfully');
        },
        error: function(xhr, status, error) {
            console.error('Failed to load categories:', error, xhr.responseText);
            $('#categorySelect').html('<option value="">-- Error Loading Categories --</option>');
            
            // Show fallback message
            showErrorToast('Failed to load categories. Please refresh the page.');
        }
    });
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

// Helper function to show success toast
function showSuccessToast(message) {
    const toast = $('<div class="toast align-items-center text-white bg-success border-0" style="position: fixed; top: 20px; right: 20px; z-index: 1055;" role="alert">')
        .append('<div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>' + message + '</div></div>');
    $('body').append(toast);
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
    toast.on('hidden.bs.toast', function() { $(this).remove(); });
}

// Helper function to show error toast
function showErrorToast(message) {
    const toast = $('<div class="toast align-items-center text-white bg-danger border-0" style="position: fixed; top: 20px; right: 20px; z-index: 1055;" role="alert">')
        .append('<div class="d-flex"><div class="toast-body"><i class="bi bi-exclamation-triangle me-2"></i>' + message + '</div></div>');
    $('body').append(toast);
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
    toast.on('hidden.bs.toast', function() { $(this).remove(); });
}

// Global helper functions
function showAlert(message, type) {
    if (typeof ModernUI !== 'undefined' && ModernUI.showToast) {
        ModernUI.showToast(message, type);
    } else {
        // Fallback
        if (type === 'success') {
            showSuccessToast(message);
        } else {
            showErrorToast(message);
        }
    }
}
</script>

    </div>
</div>
