<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Manage Categories';

$success = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
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
                // Fallback: try without optional columns
                $insertStmt = $conn->prepare("INSERT INTO categories (name, created_at) VALUES (?, NOW())");
                if (!$insertStmt) {
                    // Final fallback: basic insert
                    $insertStmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                    if ($insertStmt) {
                        $insertStmt->bind_param("s", $name);
                    }
                } else {
                    $insertStmt->bind_param("s", $name);
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
                
                echo json_encode(['success' => true, 'message' => "Category '$name' added successfully"]);
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
            
            if (empty($name) || $id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                exit;
            }
            
            // Check if category name already exists (excluding current category)
            $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            if ($checkStmt) {
                $checkStmt->bind_param("si", $name, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => "Category '$name' already exists"]);
                    exit;
                }
            }
            
            // Update category with fallback
            $updateStmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, color = ?, icon = ? WHERE id = ?");
            if (!$updateStmt) {
                // Fallback: update only name
                $updateStmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $name, $id);
                }
            } else {
                $updateStmt->bind_param("ssssi", $name, $description, $color, $icon, $id);
            }
            
            if ($updateStmt && $updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => "Category '$name' updated successfully"]);
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
            
            // Get category name for response
            $nameStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $categoryName = 'Category';
            if ($nameStmt) {
                $nameStmt->bind_param("i", $id);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $categoryName = $nameRow['name'];
                }
            }
            
            // Check if category is being used by items
            $usageStmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE category = ?");
            if ($usageStmt) {
                $usageStmt->bind_param("s", $categoryName);
                $usageStmt->execute();
                $usageResult = $usageStmt->get_result();
                $usageCount = $usageResult->fetch_assoc()['count'] ?? 0;
                
                if ($usageCount > 0) {
                    echo json_encode(['success' => false, 'message' => "Cannot delete '$categoryName'. It is being used by $usageCount item(s)"]);
                    exit;
                }
            }
            
            // Delete category
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $id);
                if ($deleteStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => "Category '$categoryName' deleted successfully"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete category: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
            
        case 'get':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($category = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $category]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Category not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
            
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No categories selected']);
                exit;
            }
            
            $deletedCount = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id <= 0) continue;
                
                // Check usage
                $nameStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                $categoryName = "Category $id";
                if ($nameStmt) {
                    $nameStmt->bind_param("i", $id);
                    $nameStmt->execute();
                    $nameResult = $nameStmt->get_result();
                    if ($nameRow = $nameResult->fetch_assoc()) {
                        $categoryName = $nameRow['name'];
                    }
                }
                
                $usageStmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE category = ?");
                if ($usageStmt) {
                    $usageStmt->bind_param("s", $categoryName);
                    $usageStmt->execute();
                    $usageResult = $usageStmt->get_result();
                    $usageCount = $usageResult->fetch_assoc()['count'] ?? 0;
                    
                    if ($usageCount > 0) {
                        $errors[] = "$categoryName (used by $usageCount item(s))";
                        continue;
                    }
                }
                
                // Delete if not in use
                $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param("i", $id);
                    if ($deleteStmt->execute()) {
                        $deletedCount++;
                    }
                }
            }
            
            $message = "$deletedCount category(ies) deleted successfully";
            if (!empty($errors)) {
                $message .= ". Could not delete: " . implode(', ', $errors);
            }
            
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
    }
}

// Get categories with item counts
$categoriesQuery = "
    SELECT 
        c.*,
        COUNT(i.id) as item_count,
        COALESCE(c.color, '#007bff') as color,
        COALESCE(c.icon, 'bi-tag') as icon,
        COALESCE(c.description, '') as description
    FROM categories c
    LEFT JOIN items i ON c.name = i.category
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";

$categories = $conn->query($categoriesQuery);

// Get statistics
$totalCategories = 0;
$usedCategories = 0;
$unusedCategories = 0;
$totalItems = 0;

if ($categories) {
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
                    <p class="text-muted">Organize your products with categories</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                    <button class="btn btn-danger d-none" id="bulkDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Categories</h6>
                                    <h3 class="mb-0"><?= $totalCategories ?></h3>
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
                                    <h3 class="mb-0"><?= $usedCategories ?></h3>
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
                                    <h3 class="mb-0"><?= $unusedCategories ?></h3>
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
                                    <h3 class="mb-0"><?= $totalItems ?></h3>
                                </div>
                                <div class="fs-1 opacity-75">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Categories List</h5>
                        <div class="d-flex gap-2">
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
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($category = $categories->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input category-checkbox" 
                                                       value="<?= $category['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="category-icon me-3" 
                                                         style="color: <?= htmlspecialchars($category['color']) ?>;">
                                                        <i class="<?= htmlspecialchars($category['icon']) ?> fs-4"></i>
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
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Unused</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($category['created_at'])): ?>
                                                    <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
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
                                                                data-bs-toggle="tooltip" title="Cannot delete - has items">
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
                            <i class="bi bi-tags fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">No categories found</h5>
                            <p class="text-muted">Create your first category to organize your products.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-circle"></i> Add First Category
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
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3" 
                                  placeholder="Optional description for this category"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoryColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" 
                                       id="categoryColor" name="color" value="#007bff">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoryIcon" class="form-label">Icon</label>
                                <select class="form-select" id="categoryIcon" name="icon">
                                    <option value="bi-tag">Tag</option>
                                    <option value="bi-tags">Tags</option>
                                    <option value="bi-box-seam">Box</option>
                                    <option value="bi-bag">Bag</option>
                                    <option value="bi-cart">Cart</option>
                                    <option value="bi-shop">Shop</option>
                                    <option value="bi-grid">Grid</option>
                                    <option value="bi-collection">Collection</option>
                                    <option value="bi-folder">Folder</option>
                                    <option value="bi-bookmark">Bookmark</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div class="d-flex align-items-center p-3 border rounded">
                            <i id="iconPreview" class="bi-tag fs-4 me-3" style="color: #007bff;"></i>
                            <div>
                                <strong id="namePreview">Category Name</strong>
                                <br><small class="text-muted" id="descPreview">Category description will appear here</small>
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
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editCategoryColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" 
                                       id="editCategoryColor" name="color">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editCategoryIcon" class="form-label">Icon</label>
                                <select class="form-select" id="editCategoryIcon" name="icon">
                                    <option value="bi-tag">Tag</option>
                                    <option value="bi-tags">Tags</option>
                                    <option value="bi-box-seam">Box</option>
                                    <option value="bi-bag">Bag</option>
                                    <option value="bi-cart">Cart</option>
                                    <option value="bi-shop">Shop</option>
                                    <option value="bi-grid">Grid</option>
                                    <option value="bi-collection">Collection</option>
                                    <option value="bi-folder">Folder</option>
                                    <option value="bi-bookmark">Bookmark</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div class="d-flex align-items-center p-3 border rounded">
                            <i id="editIconPreview" class="bi-tag fs-4 me-3"></i>
                            <div>
                                <strong id="editNamePreview">Category Name</strong>
                                <br><small class="text-muted" id="editDescPreview">Category description</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    console.log('Categories page loaded');
    
    // Initialize DataTable
    $('#categoriesTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[1, "asc"]],
        columnDefs: [
            { orderable: false, targets: [0, 5] }
        ],
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-bs-toggle="tooltip"]').tooltip();
            console.log('Categories DataTable redrawn, tooltips reinitialized');
        }
    });

    // Select all functionality
    $('#selectAll, #selectAllTable').change(function() {
        const isChecked = $(this).is(':checked');
        $('.category-checkbox').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    // Individual checkbox change
    $(document).on('change', '.category-checkbox', function() {
        const totalCheckboxes = $('.category-checkbox').length;
        const checkedCheckboxes = $('.category-checkbox:checked').length;
        
        $('#selectAll, #selectAllTable').prop('checked', totalCheckboxes === checkedCheckboxes);
        updateBulkDeleteButton();
    });

    // Update bulk delete button visibility
    function updateBulkDeleteButton() {
        const checkedCount = $('.category-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteBtn').removeClass('d-none');
        } else {
            $('#bulkDeleteBtn').addClass('d-none');
        }
    }

    // Add category form
    $('#addCategoryForm').submit(function(e) {
        e.preventDefault();
        console.log('Adding category');
        
        const formData = new FormData(this);
        formData.append('action', 'add');
        
        $.ajax({
            url: 'manage_categories.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Add response:', response);
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
                console.error('Add request failed:', xhr.responseText);
                showAlert('Error occurred while adding category: ' + error, 'danger');
            }
        });
    });

    // Edit category - using event delegation for DataTables compatibility
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

    // Edit category form
    $('#editCategoryForm').submit(function(e) {
        e.preventDefault();
        console.log('Updating category');
        
        const formData = new FormData(this);
        formData.append('action', 'edit');
        
        $.ajax({
            url: 'manage_categories.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Edit response:', response);
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#editCategoryModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit request failed:', xhr.responseText);
                showAlert('Error occurred while updating category: ' + error, 'danger');
            }
        });
    });

    // Delete category - using event delegation for DataTables compatibility
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

    // Bulk delete
    $('#bulkDeleteBtn').click(function() {
        const selectedIds = $('.category-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedIds.length === 0) {
            showAlert('Please select categories to delete', 'warning');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${selectedIds.length} selected category(ies)? This action cannot be undone.`)) {
            const button = $(this);
            const originalContent = button.html();
            button.html('<i class="bi bi-hourglass-split"></i> Deleting...').prop('disabled', true);
            
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
                button.html(originalContent).prop('disabled', false);
            }, 'json').fail(function(xhr, status, error) {
                console.error('Bulk delete request failed:', xhr.responseText);
                showAlert('Error occurred while deleting categories: ' + error, 'danger');
                button.html(originalContent).prop('disabled', false);
            });
        }
    });

    // Live preview for add form
    $('#categoryName, #categoryDescription, #categoryColor, #categoryIcon').on('input change', function() {
        updateAddPreview();
    });
    
    function updateAddPreview() {
        const name = $('#categoryName').val() || 'Category Name';
        const description = $('#categoryDescription').val() || 'Category description will appear here';
        const color = $('#categoryColor').val();
        const icon = $('#categoryIcon').val();
        
        $('#namePreview').text(name);
        $('#descPreview').text(description);
        $('#iconPreview').attr('class', icon + ' fs-4 me-3').css('color', color);
    }

    // Live preview for edit form
    $('#editCategoryName, #editCategoryDescription, #editCategoryColor, #editCategoryIcon').on('input change', function() {
        updateEditPreview();
    });
    
    function updateEditPreview() {
        const name = $('#editCategoryName').val() || 'Category Name';
        const description = $('#editCategoryDescription').val() || 'Category description';
        const color = $('#editCategoryColor').val();
        const icon = $('#editCategoryIcon').val();
        
        $('#editNamePreview').text(name);
        $('#editDescPreview').text(description);
        $('#editIconPreview').attr('class', icon + ' fs-4 me-3').css('color', color);
    }

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
});

// Helper function for alerts
function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('.main-content .container-fluid').prepend(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => alertDiv.fadeOut(), 5000);
}
</script>

<?php include 'layouts/footer.php'; ?>
