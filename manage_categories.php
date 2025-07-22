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
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#007bff');
                $icon = trim($_POST['icon'] ?? 'bi-tag');
                
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
                
                // Insert new category with fallback for different schemas
                $insertStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon, created_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$insertStmt) {
                    // Fallback 1: try without created_at
                    $insertStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
                    if (!$insertStmt) {
                        // Fallback 2: basic columns only
                        $insertStmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                        if ($insertStmt) {
                            $insertStmt->bind_param("s", $name);
                        }
                    } else {
                        $insertStmt->bind_param("ssss", $name, $description, $color, $icon);
                    }
                } else {
                    $insertStmt->bind_param("ssss", $name, $description, $color, $icon);
                }
                
                if ($insertStmt && $insertStmt->execute()) {
                    $categoryId = $conn->insert_id;
                    
                    // Try to update optional columns if they weren't included in the main insert
                    if (!empty($description) && $categoryId) {
                        $descStmt = $conn->prepare("UPDATE categories SET description = ? WHERE id = ?");
                        if ($descStmt) {
                            $descStmt->bind_param("si", $description, $categoryId);
                            $descStmt->execute();
                        }
                    }
                    
                    if (!empty($color) && $categoryId) {
                        $colorStmt = $conn->prepare("UPDATE categories SET color = ? WHERE id = ?");
                        if ($colorStmt) {
                            $colorStmt->bind_param("si", $color, $categoryId);
                            $colorStmt->execute();
                        }
                    }
                    
                    if (!empty($icon) && $categoryId) {
                        $iconStmt = $conn->prepare("UPDATE categories SET icon = ? WHERE id = ?");
                        if ($iconStmt) {
                            $iconStmt->bind_param("si", $icon, $categoryId);
                            $iconStmt->execute();
                        }
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Category '$name' added successfully",
                        'category' => [
                            'id' => $categoryId,
                            'name' => $name,
                            'description' => $description,
                            'color' => $color,
                            'icon' => $icon
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add category: ' . $conn->error]);
                }
                exit;
                
            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#007bff');
                $icon = trim($_POST['icon'] ?? 'bi-tag');
                
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
                
                // Update category with fallback
                $updateStmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, color = ?, icon = ?, updated_at = NOW() WHERE id = ?");
                if (!$updateStmt) {
                    // Fallback: update without updated_at
                    $updateStmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, color = ?, icon = ? WHERE id = ?");
                    if (!$updateStmt) {
                        // Final fallback: update only name
                        $updateStmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param("si", $name, $id);
                        }
                    } else {
                        $updateStmt->bind_param("ssssi", $name, $description, $color, $icon, $id);
                    }
                } else {
                    $updateStmt->bind_param("ssssi", $name, $description, $color, $icon, $id);
                }
                
                if ($updateStmt && $updateStmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Category '$name' updated successfully",
                        'category' => [
                            'id' => $id,
                            'name' => $name,
                            'description' => $description,
                            'color' => $color,
                            'icon' => $icon
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
        c.*,
        COALESCE(COUNT(i.id), 0) as item_count
    FROM categories c
    LEFT JOIN items i ON c.name = i.category
    GROUP BY c.id, c.name, c.description, c.color, c.icon, c.created_at, c.updated_at
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
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Manage Categories</h1>
                    <p class="text-muted">Organize your products with categories and manage item classifications</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                    <button class="btn btn-success" onclick="refreshDropdowns()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Dropdowns
                    </button>
                    <button class="btn btn-danger d-none" id="bulkDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                </div>
            </div>

            <!-- Alert Container -->
            <div id="alertContainer"></div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Categories</h6>
                                    <h3 class="mb-0" id="totalCategoriesCount"><?= $totalCategories ?></h3>
                                </div>
                                <div class="fs-1 opacity-75">
                                    <i class="bi bi-tags"></i>
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
                                    <h6 class="card-title mb-0">Used Categories</h6>
                                    <h3 class="mb-0" id="usedCategoriesCount"><?= $usedCategories ?></h3>
                                </div>
                                <div class="fs-1 opacity-75">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Unused Categories</h6>
                                    <h3 class="mb-0" id="unusedCategoriesCount"><?= $unusedCategories ?></h3>
                                </div>
                                <div class="fs-1 opacity-75">
                                    <i class="bi bi-exclamation-triangle"></i>
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
                                    <h6 class="card-title mb-0">Total Items</h6>
                                    <h3 class="mb-0" id="totalItemsCount"><?= $totalItems ?></h3>
                                </div>
                                <div class="fs-1 opacity-75">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Category Creation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quick Category Creation</h5>
                </div>
                <div class="card-body">
                    <form id="quickCategoryForm" class="row g-3">
                        <div class="col-md-4">
                            <label for="quickCategoryName" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="quickCategoryName" placeholder="Enter category name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="quickCategoryColor" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="quickCategoryColor" value="#007bff">
                        </div>
                        <div class="col-md-3">
                            <label for="quickCategoryIcon" class="form-label">Icon</label>
                            <select class="form-select" id="quickCategoryIcon">
                                <option value="bi-tag">🏷️ Tag</option>
                                <option value="bi-laptop">💻 Electronics</option>
                                <option value="bi-house">🏠 Home</option>
                                <option value="bi-briefcase">💼 Office</option>
                                <option value="bi-car">🚗 Automotive</option>
                                <option value="bi-book">📚 Books</option>
                                <option value="bi-cup">☕ Food & Drinks</option>
                                <option value="bi-bag">👜 Fashion</option>
                                <option value="bi-heart">💊 Health</option>
                                <option value="bi-controller">🎮 Games</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categories DataTable -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Categories List</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportCategories()">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">
                                    Select All
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="categoriesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" id="selectAllTable">
                                        </th>
                                        <th>Category</th>
                                        <th>Items Count</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th>Updated Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($category = $categories->fetch_assoc()): ?>
                                        <tr data-id="<?= $category['id'] ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input category-checkbox" 
                                                       value="<?= $category['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="category-icon me-3" 
                                                         style="color: <?= htmlspecialchars($category['color'] ?? '#007bff') ?>;">
                                                        <i class="<?= htmlspecialchars($category['icon'] ?? 'bi-tag') ?> fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($category['name']) ?></strong>
                                                        <?php if (!empty($category['description'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($category['description']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $category['item_count'] > 0 ? 'primary' : 'secondary' ?>">
                                                    <?= $category['item_count'] ?> items
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($category['item_count'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>Unused
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($category['created_at'])): ?>
                                                    <span data-bs-toggle="tooltip" title="<?= date('F d, Y g:i A', strtotime($category['created_at'])) ?>">
                                                        <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($category['updated_at']) && $category['updated_at'] != $category['created_at']): ?>
                                                    <span data-bs-toggle="tooltip" title="<?= date('F d, Y g:i A', strtotime($category['updated_at'])) ?>">
                                                        <?= date('M d, Y', strtotime($category['updated_at'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info view-items" 
                                                            data-id="<?= $category['id'] ?>"
                                                            data-name="<?= htmlspecialchars($category['name']) ?>"
                                                            data-bs-toggle="tooltip" title="View Items">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary edit-category" 
                                                            data-id="<?= $category['id'] ?>"
                                                            data-bs-toggle="tooltip" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($category['item_count'] == 0): ?>
                                                        <button class="btn btn-outline-danger delete-category" 
                                                                data-id="<?= $category['id'] ?>"
                                                                data-name="<?= htmlspecialchars($category['name']) ?>"
                                                                data-bs-toggle="tooltip" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" 
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
                            <i class="bi bi-tags fs-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Categories Found</h4>
                            <p class="text-muted">Create your first category to organize your products.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-circle"></i> Add Your First Category
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="categoryName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="categoryColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="categoryColor" name="color" value="#007bff">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3" placeholder="Optional description for this category"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryIcon" class="form-label">Icon</label>
                        <select class="form-select" id="categoryIcon" name="icon">
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
                        <label class="form-label">Preview</label>
                        <div class="d-flex align-items-center p-3 border rounded" id="categoryPreview">
                            <div class="category-icon me-3" id="previewIcon" style="color: #007bff;">
                                <i class="bi-tag fs-4"></i>
                            </div>
                            <div>
                                <strong id="previewName">Category Name</strong>
                                <br><small class="text-muted" id="previewDescription">Category description will appear here</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="editCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editCategoryName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editCategoryColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="editCategoryColor" name="color">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCategoryIcon" class="form-label">Icon</label>
                        <select class="form-select" id="editCategoryIcon" name="icon">
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
                        <label class="form-label">Preview</label>
                        <div class="d-flex align-items-center p-3 border rounded" id="editCategoryPreview">
                            <div class="category-icon me-3" id="editPreviewIcon" style="color: #007bff;">
                                <i class="bi-tag fs-4"></i>
                            </div>
                            <div>
                                <strong id="editPreviewName">Category Name</strong>
                                <br><small class="text-muted" id="editPreviewDescription">Category description</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Items Modal -->
<div class="modal fade" id="viewItemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Items in Category: <span id="viewItemsCategoryName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewItemsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading items...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="manageItemsLink">
                    <i class="bi bi-box-seam"></i> Manage Items
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#categoriesTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        responsive: true,
        order: [[1, "asc"]], // Sort by category name
        columnDefs: [
            { orderable: false, targets: [0, 6] }, // Disable sorting on checkbox and actions columns
            { searchable: false, targets: [0] } // Disable search on checkbox column
        ],
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-bs-toggle="tooltip"]').tooltip();
            console.log('Categories DataTable redrawn, tooltips reinitialized');
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

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

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
        
        addCategory(name, '', color, icon);
    });

    // Add category form
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('name', $('#categoryName').val().trim());
        formData.append('description', $('#categoryDescription').val().trim());
        formData.append('color', $('#categoryColor').val());
        formData.append('icon', $('#categoryIcon').val());
        
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#addCategoryModal').modal('hide');
                    $('#addCategoryForm')[0].reset();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Add category error:', xhr.responseText);
                showAlert('Error occurred while adding category: ' + error, 'danger');
            }
        });
    });

    // Edit category form
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'edit');
        formData.append('id', $('#editCategoryId').val());
        formData.append('name', $('#editCategoryName').val().trim());
        formData.append('description', $('#editCategoryDescription').val().trim());
        formData.append('color', $('#editCategoryColor').val());
        formData.append('icon', $('#editCategoryIcon').val());
        
        $.ajax({
            url: 'manage_categories.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#editCategoryModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit category error:', xhr.responseText);
                showAlert('Error occurred while updating category: ' + error, 'danger');
            }
        });
    });

    // Edit category button click
    $(document).on('click', '.edit-category', function() {
        console.log('Edit button clicked');
        const categoryId = $(this).data('id');

        console.log('Category ID:', categoryId);

        // Get category data
        $.post('manage_categories.php', {
            action: 'get',
            id: categoryId
        }, function(response) {
            console.log('Get response:', response);
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
        }, 'json').fail(function(xhr, status, error) {
            console.error('Get request failed:', xhr.responseText);
            showAlert('Error occurred while loading category: ' + error, 'danger');
        });
    });

    // Delete category button click
    $(document).on('click', '.delete-category', function() {
        console.log('Delete button clicked');
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        const button = $(this);

        console.log('Category ID:', categoryId, 'Name:', categoryName);

        if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
            console.log('User confirmed deletion');
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i>').prop('disabled', true);

            $.post('manage_categories.php', {
                action: 'delete',
                id: categoryId
            }, function(response) {
                console.log('Delete response:', response);
                if (response.success) {
                    showAlert(response.message, 'success');
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        setTimeout(() => location.reload(), 500);
                    });
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
            $.post('manage_categories.php', {
                action: 'bulk_delete',
                ids: selectedIds
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(response.message, 'danger');
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Bulk delete error:', xhr.responseText);
                showAlert('Error occurred during bulk delete: ' + error, 'danger');
            });
        }
    });

    // Live preview for add modal
    $('#categoryName, #categoryDescription, #categoryColor, #categoryIcon').on('input change', function() {
        updatePreview();
    });

    // Live preview for edit modal
    $('#editCategoryName, #editCategoryDescription, #editCategoryColor, #editCategoryIcon').on('input change', function() {
        updateEditPreview();
    });

    function updatePreview() {
        const name = $('#categoryName').val() || 'Category Name';
        const description = $('#categoryDescription').val() || 'Category description will appear here';
        const color = $('#categoryColor').val();
        const icon = $('#categoryIcon').val();

        $('#previewName').text(name);
        $('#previewDescription').text(description);
        $('#previewIcon').css('color', color).find('i').attr('class', icon + ' fs-4');
    }

    function updateEditPreview() {
        const name = $('#editCategoryName').val() || 'Category Name';
        const description = $('#editCategoryDescription').val() || 'Category description';
        const color = $('#editCategoryColor').val();
        const icon = $('#editCategoryIcon').val();

        $('#editPreviewName').text(name);
        $('#editPreviewDescription').text(description);
        $('#editPreviewIcon').css('color', color).find('i').attr('class', icon + ' fs-4');
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
        $.post('manage_categories.php', {
            action: 'add',
            name: name,
            description: description,
            color: color,
            icon: icon
        }, function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#quickCategoryForm')[0].reset();
                // Update dropdowns in other pages
                updateCategoryDropdowns();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Quick add error:', xhr.responseText);
            showAlert('Error occurred while adding category: ' + error, 'danger');
        });
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

// Update category dropdowns in other pages
function updateCategoryDropdowns() {
    // This function can be called to refresh category dropdowns across the application
    $.post('manage_categories.php', {
        action: 'get_categories_for_dropdown'
    }, function(response) {
        if (response.success) {
            // Store categories in localStorage for other pages to use
            localStorage.setItem('categories', JSON.stringify(response.categories));
            
            // Dispatch custom event for other pages to listen
            window.dispatchEvent(new CustomEvent('categoriesUpdated', {
                detail: response.categories
            }));
            
            console.log('Categories updated:', response.categories);
        }
    }, 'json');
}

// Initialize category updates on page load
$(document).ready(function() {
    updateCategoryDropdowns();
});
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
