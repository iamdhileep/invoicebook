<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Manage Categories';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Category name is required']);
                    exit;
                }
                
                // Check if category already exists
                $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                if ($checkStmt) {
                    $checkStmt->bind_param("s", $name);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => "Category '$name' already exists"]);
                        exit;
                    }
                }
                
                // Insert new category (simple structure)
                $insertStmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                
                if ($insertStmt && $insertStmt->bind_param("s", $name) && $insertStmt->execute()) {
                    $categoryId = $conn->insert_id;
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Category '$name' added successfully",
                        'category' => [
                            'id' => $categoryId,
                            'name' => $name
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add category: ' . $conn->error]);
                }
                exit;
                
            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                
                if ($id <= 0 || empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid category ID or name']);
                    exit;
                }
                
                // Check if another category with the same name exists
                $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                if ($checkStmt) {
                    $checkStmt->bind_param("si", $name, $id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => "Another category with name '$name' already exists"]);
                        exit;
                    }
                }
                
                // Update category (simple structure)
                $updateStmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                
                if ($updateStmt && $updateStmt->bind_param("si", $name, $id) && $updateStmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Category '$name' updated successfully",
                        'category' => [
                            'id' => $id,
                            'name' => $name
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update category: ' . $conn->error]);
                }
                exit;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                    exit;
                }
                
                // Check if category has items
                $itemCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE category = (SELECT name FROM categories WHERE id = ?)");
                if ($itemCheckStmt) {
                    $itemCheckStmt->bind_param("i", $id);
                    $itemCheckStmt->execute();
                    $itemCount = $itemCheckStmt->get_result()->fetch_assoc()['count'];
                    
                    if ($itemCount > 0) {
                        echo json_encode(['success' => false, 'message' => "Cannot delete category - it has $itemCount items"]);
                        exit;
                    }
                }
                
                // Delete category
                $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                if ($deleteStmt && $deleteStmt->bind_param("i", $id) && $deleteStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete category: ' . $conn->error]);
                }
                exit;
                
            case 'get':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                    exit;
                }
                
                $getStmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
                if ($getStmt && $getStmt->bind_param("i", $id) && $getStmt->execute()) {
                    $category = $getStmt->get_result()->fetch_assoc();
                    if ($category) {
                        echo json_encode(['success' => true, 'data' => $category]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Category not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch category']);
                }
                exit;
                
            case 'bulk_delete':
                $ids = $_POST['ids'] ?? [];
                
                if (empty($ids) || !is_array($ids)) {
                    echo json_encode(['success' => false, 'message' => 'No categories selected']);
                    exit;
                }
                
                $deleted = 0;
                $errors = [];
                
                foreach ($ids as $id) {
                    $id = intval($id);
                    if ($id <= 0) continue;
                    
                    // Check if category has items
                    $itemCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE category = (SELECT name FROM categories WHERE id = ?)");
                    if ($itemCheckStmt) {
                        $itemCheckStmt->bind_param("i", $id);
                        $itemCheckStmt->execute();
                        $itemCount = $itemCheckStmt->get_result()->fetch_assoc()['count'];
                        
                        if ($itemCount > 0) {
                            // Get category name for error message
                            $nameStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                            if ($nameStmt) {
                                $nameStmt->bind_param("i", $id);
                                $nameStmt->execute();
                                $catName = $nameStmt->get_result()->fetch_assoc()['name'] ?? "ID $id";
                                $errors[] = "Cannot delete '$catName' - has $itemCount items";
                            }
                            continue;
                        }
                    }
                    
                    // Delete category
                    $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    if ($deleteStmt && $deleteStmt->bind_param("i", $id) && $deleteStmt->execute()) {
                        $deleted++;
                    }
                }
                
                $message = "$deleted categories deleted successfully";
                if (!empty($errors)) {
                    $message .= ". Errors: " . implode(", ", $errors);
                }
                
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
                
            case 'get_categories_for_dropdown':
                $categoriesStmt = $conn->prepare("SELECT id, name, color, icon FROM categories ORDER BY name ASC");
                if ($categoriesStmt && $categoriesStmt->execute()) {
                    $categories = [];
                    $result = $categoriesStmt->get_result();
                    while ($cat = $result->fetch_assoc()) {
                        $categories[] = $cat;
                    }
                    echo json_encode(['success' => true, 'categories' => $categories]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch categories']);
                }
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch categories with item counts for display
$categoriesQuery = "
    SELECT 
        c.id,
        c.name,
        COALESCE(COUNT(i.id), 0) as item_count
    FROM categories c
    LEFT JOIN items i ON c.name = i.category
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";

$categories = $conn->query($categoriesQuery);

// Calculate statistics
$totalCategories = 0;
$usedCategories = 0;
$unusedCategories = 0;
$totalItems = 0;

if ($categories && $categories->num_rows > 0) {
    $categories->data_seek(0); // Reset pointer
    while ($cat = $categories->fetch_assoc()) {
        $totalCategories++;
        if ($cat['item_count'] > 0) {
            $usedCategories++;
            $totalItems += $cat['item_count'];
        } else {
            $unusedCategories++;
        }
    }
    $categories->data_seek(0); // Reset pointer again
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<style>
/* Modern Category Management Styles - Optimized for performance */
.main-content {
    /* Removed slow animation causing loading delay */
    opacity: 1;
    transform: translateY(0);
}

/* Enhanced Statistics Cards - Reduced animation complexity */
.stats-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease; /* Faster transitions */
    border: none;
    overflow: hidden;
    position: relative;
    will-change: transform; /* GPU acceleration */
}

.stats-card:hover {
    transform: translateY(-4px); /* Reduced movement */
    box-shadow: 0 12px 20px rgba(0,0,0,0.08) !important; /* Lighter shadow */
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

/* Enhanced Form Controls - Optimized transitions */
.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control-lg {
    border-radius: 12px;
    border: 2px solid #e9ecef;
    transition: border-color 0.15s ease-in-out;
}

.form-control-lg:focus {
    border-color: #667eea;
    /* Removed transform to prevent reflows */
}

/* Enhanced Buttons - Optimized performance */
.btn {
    border-radius: 10px;
    font-weight: 600;
    transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
}

.btn:hover {
    filter: brightness(1.05); /* GPU-accelerated effect */
}

.btn-lg {
    border-radius: 12px;
    padding: 12px 24px;
}

/* Enhanced Tables */
.table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    font-weight: 600;
    padding: 15px;
}

.table tbody tr {
    transition: background-color 0.15s ease; /* Only background transition */
}

.table tbody tr:hover {
    background-color: #f8f9ff !important;
    /* Removed scale transform to prevent reflows */
}

/* Enhanced Modals */
.modal-content {
    border-radius: 20px;
    overflow: hidden;
}

.modal-header {
    border-radius: 20px 20px 0 0;
    padding: 20px 24px;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    border-radius: 0 0 20px 20px;
    padding: 20px 24px;
}

/* Enhanced Badge Styles */
.badge {
    border-radius: 8px;
    font-weight: 500;
    padding: 6px 12px;
}

/* Enhanced Quick Form - Stable interactions */
.quick-form {
    background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
    border-radius: 16px;
    padding: 24px;
    border: 2px solid rgba(102, 126, 234, 0.1);
    transition: border-color 0.2s ease;
}

.quick-form:hover {
    border-color: rgba(102, 126, 234, 0.3);
    /* Removed transform to prevent reflows */
}

/* Enhanced Category Preview - Minimal animations */
.category-icon {
    transition: transform 0.2s ease;
}

.category-icon:hover {
    transform: scale(1.05); /* Reduced animation */
}

/* Loading States */
.btn-loading {
    position: relative;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced Alerts */
.alert {
    border-radius: 12px;
    border: none;
    padding: 16px 20px;
    font-weight: 500;
}

/* Responsive Enhancements */
@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 20px;
    }
    
    .modal-dialog {
        margin: 10px;
    }
    
    .btn-lg {
        padding: 10px 20px;
        font-size: 1rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .stats-card {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    .table tbody tr:hover {
        background-color: #2d3748;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Modern Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h1 class="h4 mb-0 fw-bold">
                    <i class="bi bi-tags me-2 text-primary"></i>Manage Categories
                </h1>
                <p class="text-muted small">Organize your products efficiently with smart category management</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Add Category
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshDropdowns()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="mb-2"></div>

    <!-- Enhanced Statistics Cards -->
    <div class="row g-2 mb-2">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Total Categories</h6>
                            <h2 class="mb-0 fw-bold" id="totalCategoriesCount"><?= $totalCategories ?></h2>
                            <small class="opacity-75">All created categories</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Active Categories</h6>
                            <h2 class="mb-0 fw-bold" id="usedCategoriesCount"><?= $usedCategories ?></h2>
                            <small class="opacity-75">Categories with items</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Total Items</h6>
                            <h2 class="mb-0 fw-bold" id="totalItemsCount"><?= $totalItems ?></h2>
                            <small class="opacity-75">Items across categories</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Empty Categories</h6>
                            <h2 class="mb-0 fw-bold" id="unusedCategoriesCount"><?= $unusedCategories ?></h2>
                            <small class="opacity-75">Categories without items</small>
                        </div>
                        <div class="display-6 opacity-75">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Category Creation Card -->
    <div class="card border-0 mb-2">
        <div class="card-header bg-gradient text-white py-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-lightning-charge-fill me-2"></i>Quick Category Creation
            </h6>
        </div>
        <div class="card-body p-2">
            <form id="quickCategoryForm" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="quickCategoryName" class="form-label fw-semibold">
                        <i class="bi bi-pencil-square me-1 text-primary"></i>Category Name
                    </label>
                    <input type="text" class="form-control" id="quickCategoryName" 
                           placeholder="Enter category name..." required maxlength="50">
                </div>
                <div class="col-md-3">
                    <label for="quickCategoryColor" class="form-label fw-semibold">
                        <i class="bi bi-palette-fill me-1 text-warning"></i>Color Theme
                    </label>
                    <input type="color" class="form-control form-control-color" 
                           id="quickCategoryColor" value="#667eea">
                </div>
                <div class="col-md-3">
                    <label for="quickCategoryIcon" class="form-label fw-semibold">
                        <i class="bi bi-emoji-smile me-1 text-info"></i>Icon
                    </label>
                    <select class="form-select" id="quickCategoryIcon">
                        <option value="bi-tag">🏷️ Tag</option>
                        <option value="bi-cup-hot">☕ Food & Drinks</option>
                        <option value="bi-laptop">💻 Electronics</option>
                        <option value="bi-house">🏠 Home</option>
                        <option value="bi-car-front">🚗 Automotive</option>
                        <option value="bi-book">📚 Books</option>
                        <option value="bi-bag">👜 Fashion</option>
                        <option value="bi-heart-pulse">� Health</option>
                        <option value="bi-controller">🎮 Games</option>
                        <option value="bi-tools">🔧 Tools</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="bi bi-plus-circle-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories Management Card -->
    <div class="card border-0">
        <div class="card-header bg-white border-bottom py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Categories Management
                </h6>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-outline-primary" onclick="exportCategories()">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-danger btn-sm d-none" id="bulkDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-semibold" for="selectAll">
                            Select All
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($categories && $categories->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="categoriesTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50" class="ps-4">
                                    <input type="checkbox" class="form-check-input" id="selectAllTable">
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-tag me-2 text-primary"></i>Category
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-box-seam me-2 text-success"></i>Items Count
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-activity me-2 text-info"></i>Status
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-gear me-2 text-warning"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <tr data-id="<?= $category['id'] ?>" class="border-bottom">
                                    <td class="ps-4">
                                        <input type="checkbox" class="form-check-input category-checkbox" 
                                               value="<?= $category['id'] ?>">
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="category-icon me-3 d-flex align-items-center justify-content-center" 
                                                 style="width: 45px; height: 45px; border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                                <i class="bi-tag fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($category['name']) ?></h6>
                                                <small class="text-muted">Category ID: #<?= $category['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge rounded-pill <?= $category['item_count'] > 0 ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                                            <i class="bi bi-box-seam me-1"></i><?= $category['item_count'] ?> items
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($category['item_count'] > 0): ?>
                                            <span class="badge rounded-pill bg-success px-3 py-2">
                                                <i class="bi bi-check-circle-fill me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-warning px-3 py-2">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Empty
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-info view-items" 
                                                    data-id="<?= $category['id'] ?>"
                                                    data-name="<?= htmlspecialchars($category['name']) ?>"
                                                    data-bs-toggle="tooltip" title="View Items">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary edit-category" 
                                                    data-id="<?= $category['id'] ?>"
                                                    data-bs-toggle="tooltip" title="Edit Category">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if ($category['item_count'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-danger delete-category" 
                                                        data-id="<?= $category['id'] ?>"
                                                        data-name="<?= htmlspecialchars($category['name']) ?>"
                                                        data-bs-toggle="tooltip" title="Delete Category">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        disabled
                                                        data-bs-toggle="tooltip" title="Cannot delete - has <?= $category['item_count'] ?> items">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-tags display-1 text-muted"></i>
                    </div>
                    <h4 class="text-muted mb-3">No Categories Found</h4>
                    <p class="text-muted mb-4">Create your first category to start organizing your products efficiently.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Create Your First Category
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Enhanced Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header border-0 py-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h6 class="modal-title text-white fw-bold">
                    <i class="bi bi-plus-circle-fill me-2"></i>Create New Category
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body p-2">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <div class="mb-2">
                                <label for="categoryName" class="form-label fw-semibold">
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>Category Name 
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="categoryName" 
                                       name="name" placeholder="Enter category name..." required maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label for="categoryColor" class="form-label fw-semibold">
                                    <i class="bi bi-palette-fill me-2 text-warning"></i>Color Theme
                                </label>
                                <input type="color" class="form-control form-control-color" 
                                       id="categoryColor" name="color" value="#667eea">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label for="categoryDescription" class="form-label fw-semibold">
                            <i class="bi bi-text-paragraph me-2 text-info"></i>Description
                        </label>
                        <textarea class="form-control" id="categoryDescription" name="description" 
                                  rows="2" placeholder="Optional description for this category..."></textarea>
                    </div>
                    
                    <div class="mb-2">
                        <label for="categoryIcon" class="form-label fw-semibold">
                            <i class="bi bi-emoji-smile me-2 text-success"></i>Category Icon
                        </label>
                        <select class="form-select form-select-lg" id="categoryIcon" name="icon">
                            <option value="bi-tag">🏷️ Tag (Default)</option>
                            <option value="bi-cup-hot">☕ Food & Beverages</option>
                            <option value="bi-laptop">💻 Electronics</option>
                            <option value="bi-house">🏠 Home & Garden</option>
                            <option value="bi-car-front">🚗 Automotive</option>
                            <option value="bi-book">📚 Books & Education</option>
                            <option value="bi-bag">👜 Fashion & Clothing</option>
                            <option value="bi-heart-pulse">💊 Health & Beauty</option>
                            <option value="bi-controller">🎮 Games & Toys</option>
                            <option value="bi-tools">🔧 Tools & Hardware</option>
                            <option value="bi-music-note">🎵 Music & Entertainment</option>
                            <option value="bi-camera">📷 Photography</option>
                            <option value="bi-gift">🎁 Gifts & Accessories</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-eye me-2 text-secondary"></i>Live Preview
                        </label>
                        <div class="d-flex align-items-center p-3 border rounded-3 bg-light" id="categoryPreview">
                            <div class="category-icon me-3 d-flex align-items-center justify-content-center" 
                                 id="previewIcon" 
                                 style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <i class="bi-tag fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-semibold" id="previewName">Category Name</h6>
                                <small class="text-muted" id="previewDescription">Category description will appear here</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="bi bi-plus-circle-fill me-2"></i>Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header border-0 py-2" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h6 class="modal-title text-white fw-bold">
                    <i class="bi bi-pencil-square-fill me-2"></i>Edit Category
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-body p-2">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <div class="mb-2">
                                <label for="editCategoryName" class="form-label fw-semibold">
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>Category Name 
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editCategoryName" 
                                       name="name" required maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editCategoryColor" class="form-label fw-semibold">
                                    <i class="bi bi-palette-fill me-2 text-warning"></i>Color Theme
                                </label>
                                <input type="color" class="form-control form-control-lg form-control-color" 
                                       id="editCategoryColor" name="color">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="editCategoryDescription" class="form-label fw-semibold">
                            <i class="bi bi-text-paragraph me-2 text-info"></i>Description
                        </label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="editCategoryIcon" class="form-label fw-semibold">
                            <i class="bi bi-emoji-smile me-2 text-success"></i>Category Icon
                        </label>
                        <select class="form-select form-select-lg" id="editCategoryIcon" name="icon">
                            <option value="bi-tag">🏷️ Tag (Default)</option>
                            <option value="bi-laptop">💻 Electronics</option>
                            <option value="bi-house">🏠 Home & Garden</option>
                            <option value="bi-briefcase">💼 Office Supplies</option>
                            <option value="bi-car">🚗 Automotive</option>
                            <option value="bi-book">📚 Books & Education</option>
                            <option value="bi-cup">☕ Food & Beverages</option>
                            <option value="bi-bag">👜 Fashion & Clothing</option>
                            <option value="bi-heart">💊 Health & Beauty</option>
                            <option value="bi-controller">🎮 Games & Toys</option>
                            <option value="bi-tools">🔧 Tools & Hardware</option>
                            <option value="bi-music-note">🎵 Music & Entertainment</option>
                            <option value="bi-camera">📷 Photography</option>
                            <option value="bi-gift">🎁 Gifts</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-eye me-2 text-secondary"></i>Live Preview
                        </label>
                        <div class="d-flex align-items-center p-3 border rounded-3 bg-light" id="editCategoryPreview">
                            <div class="category-icon me-3 d-flex align-items-center justify-content-center" 
                                 id="editPreviewIcon" 
                                 style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                <i class="bi-tag fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-semibold" id="editPreviewName">Category Name</h6>
                                <small class="text-muted" id="editPreviewDescription">Category description</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 border-start border-warning border-4">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                        <strong>Note:</strong> Changes will affect all items in this category.
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <i class="bi bi-check-circle-fill me-2"></i>Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced View Items Modal -->
<div class="modal fade" id="viewItemsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="bi bi-box-seam-fill me-2"></i>Items in Category: 
                    <span id="viewItemsCategoryName" class="badge bg-white bg-opacity-20 ms-2 px-3 py-2 rounded-pill"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="viewItemsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h6 class="text-muted">Loading items...</h6>
                        <p class="text-muted small">Please wait while we fetch the category items</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Close
                </button>
                <a href="#" class="btn btn-primary btn-sm" id="manageItemsLink" 
                   style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <i class="bi bi-box-seam-fill me-2"></i>Manage Items
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    // Add loading overlay to prevent FOUC (Flash of Unstyled Content)
    $('body').prepend('<div id="pageLoader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center;"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div></div>');
    
    // Initialize DataTable with performance optimizations
    window.categoriesTable = $('#categoriesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        processing: false, // Disable processing overlay to prevent flicker
        deferRender: true, // Improve performance for large datasets
        order: [[1, "asc"]], // Sort by category name
        columnDefs: [
            { orderable: false, targets: [0, 4] }, // Disable sorting on checkbox and actions columns
            { searchable: false, targets: [0] }, // Disable search on checkbox column
            { className: "text-center", targets: [0] }
        ],
        initComplete: function() {
            // Remove page loader when table is ready
            $('#pageLoader').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Initialize tooltips after table load
            $('[data-bs-toggle="tooltip"]').tooltip();
        },
        drawCallback: function() {
            // Throttle tooltip initialization to improve performance
            clearTimeout(window.tooltipTimeout);
            window.tooltipTimeout = setTimeout(function() {
                $('[data-bs-toggle="tooltip"]').tooltip('dispose').tooltip();
            }, 100);
        },
        language: {
            search: "Search categories:",
            lengthMenu: "Show _MENU_ categories per page",
            info: "Showing _START_ to _END_ of _TOTAL_ categories",
            infoEmpty: "No categories found",
            infoFiltered: "(filtered from _MAX_ total categories)",
            emptyTable: "No categories available",
            zeroRecords: "No matching categories found"
        }
    });

    // Quick category form
    $('#quickCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const name = $('#quickCategoryName').val().trim();
        const color = $('#quickCategoryColor').val();
        const icon = $('#quickCategoryIcon').val();
        
        if (!name) {
            showAlert('Category name is required', 'danger');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Adding...').prop('disabled', true);
        
        addCategoryDynamic(name, '', color, icon, function(success) {
            // Reset form and button state
            if (success) {
                $('#quickCategoryForm')[0].reset();
            }
            submitBtn.html(originalBtnText).prop('disabled', false);
        });
    });

    // Add category form
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const name = $('#categoryName').val().trim();
        const description = $('#categoryDescription').val().trim();
        const color = $('#categoryColor').val();
        const icon = $('#categoryIcon').val();
        
        if (!name) {
            showAlert('Category name is required', 'danger');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Adding...').prop('disabled', true);
        
        addCategoryDynamic(name, description, color, icon, function(success) {
            if (success) {
                $('#addCategoryModal').modal('hide');
                $('#addCategoryForm')[0].reset();
                updatePreview(); // Reset preview
            }
            submitBtn.html(originalBtnText).prop('disabled', false);
        });
    });

    // Edit category form
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const id = $('#editCategoryId').val();
        const name = $('#editCategoryName').val().trim();
        const description = $('#editCategoryDescription').val().trim();
        const color = $('#editCategoryColor').val();
        const icon = $('#editCategoryIcon').val();
        
        if (!name) {
            showAlert('Category name is required', 'danger');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Updating...').prop('disabled', true);
        
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: {
                action: 'edit',
                id: id,
                name: name,
                description: description,
                color: color,
                icon: icon
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#editCategoryModal').modal('hide');
                    
                    // Update the table row dynamically
                    updateCategoryRow(id, {
                        name: name,
                        description: description,
                        color: color,
                        icon: icon
                    });
                    
                    // Update dropdowns
                    updateCategoryDropdowns();
                } else {
                    showAlert(response.message, 'danger');
                }
                submitBtn.html(originalBtnText).prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('Edit category error:', xhr.responseText);
                showAlert('Error occurred while updating category: ' + error, 'danger');
                submitBtn.html(originalBtnText).prop('disabled', false);
            }
        });
    });

    // Edit category button click - Optimized with loading state
    $(document).on('click', '.edit-category', function() {
        const categoryId = $(this).data('id');
        const button = $(this);
        
        // Show loading state
        const originalContent = button.html();
        button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);

        // Get category data with timeout
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: {
                action: 'get',
                id: categoryId
            },
            dataType: 'json',
            timeout: 8000,
            success: function(response) {
                if (response.success) {
                    const category = response.data;
                    $('#editCategoryId').val(category.id);
                    $('#editCategoryName').val(category.name);
                    $('#editCategoryDescription').val(category.description || '');
                    $('#editCategoryColor').val(category.color || '#007bff');
                    $('#editCategoryIcon').val(category.icon || 'bi-tag');

                    updateEditPreview();
                    $('#editCategoryModal').modal('show');
                } else {
                    showAlert(response.message, 'danger');
                }
                
                // Restore button state
                button.html(originalContent).prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('Get request failed:', xhr.responseText);
                
                let errorMessage = 'Failed to load category data';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                }
                
                showAlert('Error: ' + errorMessage, 'danger');
                button.html(originalContent).prop('disabled', false);
            }
        });
    });

    // Delete category button click
    $(document).on('click', '.delete-category', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        const button = $(this);
        const row = button.closest('tr');

        if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);

            $.post('manage_categories.php', {
                action: 'delete',
                id: categoryId
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    
                    // Remove row from DataTable
                    window.categoriesTable.row(row).remove().draw();
                    
                    // Update statistics
                    updateStatistics();
                    
                    // Update dropdowns
                    updateCategoryDropdowns();
                } else {
                    showAlert(response.message, 'danger');
                    button.html(originalContent).prop('disabled', false);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Delete request failed:', xhr.responseText);
                showAlert('Error occurred while deleting category: ' + error, 'danger');
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // View items button click
    $(document).on('click', '.view-items', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        $('#viewItemsCategoryName').text(categoryName);
        $('#manageItemsLink').attr('href', `item-stock.php?category=${encodeURIComponent(categoryName)}`);
        
        // Reset modal content
        $('#viewItemsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading items...</p>
            </div>
        `);
        
        $('#viewItemsModal').modal('show');
        
        // Load items for this category
        $.get('get_category_items.php', {category_name: categoryName}, function(response) {
            if (response.success && response.items.length > 0) {
                let itemsHtml = '<div class="row">';
                response.items.forEach(function(item) {
                    itemsHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">${item.item_name}</h6>
                                    <p class="card-text">
                                        <strong>Price:</strong> ₹${parseFloat(item.item_price).toFixed(2)}<br>
                                        <strong>Stock:</strong> ${item.stock || 0} units
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });
                itemsHtml += '</div>';
                $('#viewItemsContent').html(itemsHtml);
            } else {
                $('#viewItemsContent').html(`
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-box fs-1"></i>
                        <h5 class="mt-3">No Items Found</h5>
                        <p>This category doesn't have any items yet.</p>
                        <a href="add_item.php?category=${encodeURIComponent(categoryName)}" class="btn btn-primary">
                            <i class="bi bi-plus"></i> Add First Item
                        </a>
                    </div>
                `);
            }
        }, 'json').fail(function() {
            $('#viewItemsContent').html(`
                <div class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle fs-1"></i>
                    <h5 class="mt-3">Error Loading Items</h5>
                    <p>Failed to load items for this category.</p>
                </div>
            `);
        });
    });

    // Select all functionality
    $('#selectAll, #selectAllTable').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.category-checkbox').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    // Individual checkbox change
    $(document).on('change', '.category-checkbox', function() {
        updateBulkDeleteButton();
    });

    // Bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        const selectedIds = $('.category-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            showAlert('Please select categories to delete', 'warning');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selectedIds.length} selected categories? This action cannot be undone.`)) {
            const button = $(this);
            const originalText = button.html();
            button.html('<i class="bi bi-hourglass-split"></i> Deleting...').prop('disabled', true);
            
            $.post('manage_categories.php', {
                action: 'bulk_delete',
                ids: selectedIds
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    
                    // Remove selected rows from DataTable
                    $('.category-checkbox:checked').each(function() {
                        const row = $(this).closest('tr');
                        window.categoriesTable.row(row).remove();
                    });
                    window.categoriesTable.draw();
                    
                    // Update statistics
                    updateStatistics();
                    
                    // Update dropdowns
                    updateCategoryDropdowns();
                    
                    // Reset bulk delete button
                    $('#bulkDeleteBtn').addClass('d-none');
                    $('#selectAll, #selectAllTable').prop('checked', false);
                } else {
                    showAlert(response.message, 'danger');
                }
                button.html(originalText).prop('disabled', false);
            }, 'json').fail(function(xhr, status, error) {
                console.error('Bulk delete error:', xhr.responseText);
                showAlert('Error occurred during bulk delete: ' + error, 'danger');
                button.html(originalText).prop('disabled', false);
            });
        }
    });

    // Live preview for add modal - Debounced for performance
    let previewTimeout;
    $('#categoryName, #categoryDescription, #categoryColor, #categoryIcon').on('input change', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(updatePreview, 150); // Debounce updates
    });

    // Live preview for edit modal - Debounced for performance
    let editPreviewTimeout;
    $('#editCategoryName, #editCategoryDescription, #editCategoryColor, #editCategoryIcon').on('input change', function() {
        clearTimeout(editPreviewTimeout);
        editPreviewTimeout = setTimeout(updateEditPreview, 150); // Debounce updates
    });

    function updatePreview() {
        // Use DocumentFragment for better performance
        const name = $('#categoryName').val() || 'Category Name';
        const description = $('#categoryDescription').val() || 'Category description will appear here';
        const color = $('#categoryColor').val();
        const icon = $('#categoryIcon').val();

        // Batch DOM updates
        requestAnimationFrame(function() {
            $('#previewName').text(name);
            $('#previewDescription').text(description);
            
            // Update the icon background with gradient using the selected color
            const iconElement = $('#previewIcon');
            iconElement.find('i').attr('class', icon + ' fs-4');
            
            // Create a dynamic gradient using the selected color
            const lightColor = adjustBrightness(color, 20);
            iconElement.css('background', `linear-gradient(135deg, ${color} 0%, ${lightColor} 100%)`);
        });
    }

    function updateEditPreview() {
        // Use DocumentFragment for better performance
        const name = $('#editCategoryName').val() || 'Category Name';
        const description = $('#editCategoryDescription').val() || 'Category description';
        const color = $('#editCategoryColor').val();
        const icon = $('#editCategoryIcon').val();

        // Batch DOM updates
        requestAnimationFrame(function() {
            $('#editPreviewName').text(name);
            $('#editPreviewDescription').text(description);
            
            // Update the icon background with gradient using the selected color
            const iconElement = $('#editPreviewIcon');
            iconElement.find('i').attr('class', icon + ' fs-4');
            
            // Create a dynamic gradient using the selected color
            const lightColor = adjustBrightness(color, 20);
            iconElement.css('background', `linear-gradient(135deg, ${color} 0%, ${lightColor} 100%)`);
        });
    }

    // Helper function to adjust color brightness for gradient effect
    function adjustBrightness(color, percent) {
        const num = parseInt(color.replace("#",""), 16);
        const amt = Math.round(2.55 * percent);
        const R = (num >> 16) + amt;
        const G = (num >> 8 & 0x00FF) + amt;
        const B = (num & 0x0000FF) + amt;
        return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
            (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
            (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
    }

    function updateBulkDeleteButton() {
        const selectedCount = $('.category-checkbox:checked').length;
        if (selectedCount > 0) {
            $('#bulkDeleteBtn').removeClass('d-none').text(`Delete Selected (${selectedCount})`);
        } else {
            $('#bulkDeleteBtn').addClass('d-none');
        }
    }

    function addCategory(name, description, color, icon) {
        // Legacy function - redirected to dynamic version
        addCategoryDynamic(name, description, color, icon);
    }
    
    function addCategoryDynamic(name, description, color, icon, callback) {
        // Add request timeout and improved error handling
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: {
                action: 'add',
                name: name,
                description: description || '',
                color: color || '#007bff',
                icon: icon || 'bi-tag'
            },
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    
                    // Add new row to DataTable efficiently
                    addCategoryRow(response.category);
                    
                    // Update statistics efficiently
                    updateStatistics();
                    
                    // Update dropdowns asynchronously
                    setTimeout(updateCategoryDropdowns, 100);
                    
                    if (callback) callback(true);
                } else {
                    showAlert(response.message, 'danger');
                    if (callback) callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Add category error:', xhr.responseText);
                let errorMessage = 'Network error occurred';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please contact support.';
                } else if (xhr.status === 0) {
                    errorMessage = 'No internet connection. Please check your network.';
                }
                
                showAlert('Error adding category: ' + errorMessage, 'danger');
                if (callback) callback(false);
            }
        });
    }
    
    function addCategoryRow(category) {
        const newRow = `
            <tr data-id="${category.id}">
                <td>
                    <input type="checkbox" class="form-check-input category-checkbox" value="${category.id}">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="category-icon me-3" style="color: ${category.color || '#007bff'};">
                            <i class="${category.icon || 'bi-tag'} fs-4"></i>
                        </div>
                        <div>
                            <strong>${category.name}</strong>
                            ${category.description ? `<br><small class="text-muted">${category.description}</small>` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary">0 items</span>
                </td>
                <td>
                    <span class="badge bg-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>Unused
                    </span>
                </td>
                <td>
                    <span data-bs-toggle="tooltip" title="${new Date().toLocaleString()}">
                        ${new Date().toLocaleDateString()}
                    </span>
                </td>
                <td>
                    <span class="text-muted">-</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info view-items" 
                                data-id="${category.id}"
                                data-name="${category.name}"
                                data-bs-toggle="tooltip" title="View Items">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary edit-category" 
                                data-id="${category.id}"
                                data-bs-toggle="tooltip" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger delete-category" 
                                data-id="${category.id}"
                                data-name="${category.name}"
                                data-bs-toggle="tooltip" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        window.categoriesTable.row.add($(newRow)).draw();
        
        // Initialize tooltips for new row
        $('[data-bs-toggle="tooltip"]').tooltip();
    }
    
    function updateCategoryRow(categoryId, categoryData) {
        const row = $(`tr[data-id="${categoryId}"]`);
        if (row.length) {
            // Update the category display
            const categoryCell = row.find('td').eq(1);
            categoryCell.html(`
                <div class="d-flex align-items-center">
                    <div class="category-icon me-3" style="color: ${categoryData.color || '#007bff'};">
                        <i class="${categoryData.icon || 'bi-tag'} fs-4"></i>
                    </div>
                    <div>
                        <strong>${categoryData.name}</strong>
                        ${categoryData.description ? `<br><small class="text-muted">${categoryData.description}</small>` : ''}
                    </div>
                </div>
            `);
            
            // Update data attributes for buttons
            row.find('.delete-category, .view-items').attr('data-name', categoryData.name);
            
            // Redraw the table to reflect changes
            window.categoriesTable.draw(false);
        }
    }
    
    function updateStatistics() {
        // Recalculate statistics from current table data
        let totalCategories = 0;
        let usedCategories = 0;
        let unusedCategories = 0;
        let totalItems = 0;
        
        $('#categoriesTable tbody tr').each(function() {
            if ($(this).find('.category-checkbox').length > 0) {
                totalCategories++;
                const itemBadge = $(this).find('td').eq(2).find('.badge');
                const itemCount = parseInt(itemBadge.text().replace(' items', '')) || 0;
                
                if (itemCount > 0) {
                    usedCategories++;
                    totalItems += itemCount;
                } else {
                    unusedCategories++;
                }
            }
        });
        
        // Update the statistics cards
        $('#totalCategoriesCount').text(totalCategories);
        $('#usedCategoriesCount').text(usedCategories);
        $('#unusedCategoriesCount').text(unusedCategories);
        $('#totalItemsCount').text(totalItems);
    }
});

// Export categories function
function exportCategories() {
    window.open('export_categories.php', '_blank');
}

// Refresh dropdowns function
function refreshDropdowns() {
    updateCategoryDropdowns();
    showAlert('Category dropdowns refreshed successfully', 'success');
}

// Update category dropdowns in other pages - Optimized with caching
function updateCategoryDropdowns() {
    // Check if we have cached data that's less than 5 minutes old
    const cachedData = localStorage.getItem('categories');
    const cacheTime = localStorage.getItem('categoriesCacheTime');
    const fiveMinutes = 5 * 60 * 1000;
    
    if (cachedData && cacheTime && (Date.now() - parseInt(cacheTime)) < fiveMinutes) {
        // Use cached data
        const categories = JSON.parse(cachedData);
        window.dispatchEvent(new CustomEvent('categoriesUpdated', {
            detail: categories
        }));
        return;
    }
    
    // Fetch fresh data with timeout and retry logic
    let retryCount = 0;
    const maxRetries = 3;
    
    function fetchCategories() {
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: { action: 'get_categories_for_dropdown' },
            dataType: 'json',
            timeout: 8000,
            success: function(response) {
                if (response.success) {
                    // Cache the data with timestamp
                    localStorage.setItem('categories', JSON.stringify(response.categories));
                    localStorage.setItem('categoriesCacheTime', Date.now().toString());
                    
                    // Dispatch event for other components
                    window.dispatchEvent(new CustomEvent('categoriesUpdated', {
                        detail: response.categories
                    }));
                }
            },
            error: function(xhr, status, error) {
                retryCount++;
                console.error(`Category fetch attempt ${retryCount} failed:`, status, error);
                
                if (retryCount < maxRetries && status !== 'abort') {
                    // Exponential backoff retry
                    setTimeout(fetchCategories, Math.pow(2, retryCount) * 1000);
                } else {
                    // Use cached data if available as fallback
                    if (cachedData) {
                        console.warn('Using stale cached category data due to network issues');
                        const categories = JSON.parse(cachedData);
                        window.dispatchEvent(new CustomEvent('categoriesUpdated', {
                            detail: categories
                        }));
                    }
                }
            }
        });
    }
    
    fetchCategories();
}

// Initialize category updates on page load with performance monitoring
$(document).ready(function() {
    const startTime = performance.now();
    
    // Log initial load time
    console.log('Categories page starting to load...');
    
    updateCategoryDropdowns();
    
    // Log performance metrics
    window.addEventListener('load', function() {
        const loadTime = performance.now() - startTime;
        console.log(`Categories page loaded in ${loadTime.toFixed(2)}ms`);
        
        // Optional: Send performance data to server for monitoring
        if (loadTime > 3000) { // If load takes more than 3 seconds
            console.warn('Slow page load detected:', loadTime + 'ms');
        }
    });
    
    // Monitor memory usage (optional debugging)
    if (window.performance && window.performance.memory) {
        setInterval(function() {
            const memory = window.performance.memory;
            if (memory.usedJSHeapSize > 50000000) { // 50MB threshold
                console.warn('High memory usage detected:', memory.usedJSHeapSize / 1024 / 1024 + 'MB');
            }
        }, 30000); // Check every 30 seconds
    }
});

// Optimized Alert function with better performance
function showAlert(message, type) {
    // Remove existing alerts to prevent stacking and memory leaks
    $('#alertContainer .alert').remove();
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-relative" role="alert" style="animation: slideInDown 0.3s ease;">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Use efficient DOM insertion
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = alertHtml;
    
    // Auto dismiss after 4 seconds with smooth animation
    setTimeout(function() {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.style.animation = 'slideOutUp 0.3s ease';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }, 4000);
}

// Add CSS animations for alerts
const alertStyles = `
<style>
@keyframes slideInDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideOutUp {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(-20px);
        opacity: 0;
    }
}

/* Performance optimizations */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* Smooth scrolling on mobile */
}

/* Reduce repaints */
.stats-card, .btn, .form-control {
    backface-visibility: hidden;
    perspective: 1000px;
}

/* Optimize images */
.category-icon img {
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}
</style>
`;

// Append styles to head
if (!document.getElementById('performance-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'performance-styles';
    styleElement.innerHTML = alertStyles;
    document.head.appendChild(styleElement);
}
</script>

<style>
.category-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: rgba(0,0,0,0.05);
}

.form-control-color {
    width: 100%;
    height: 38px;
}

#categoryPreview, #editCategoryPreview {
    background: rgba(0,0,0,0.02);
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.table th {
    font-weight: 600;
    color: #495057;
    border-top: none;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75em;
}

.text-muted {
    color: #6c757d !important;
}

.spinner-border {
    width: 2rem;
    height: 2rem;
}
</style>

    </div>
</div>
