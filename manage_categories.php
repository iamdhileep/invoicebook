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

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    // Check if category is being used
    $checkUsage = $conn->query("SELECT COUNT(*) as count FROM items WHERE category = (SELECT name FROM categories WHERE id = $deleteId)");
    $usageCount = $checkUsage->fetch_assoc()['count'] ?? 0;
    
    if ($usageCount > 0) {
        $error = "Cannot delete category. It is being used by $usageCount item(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        if ($stmt->execute()) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Failed to delete category.";
        }
    }
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $newCategory = trim($_POST['category']);
    if (!empty($newCategory)) {
        // Check if category already exists
        $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $checkStmt->bind_param("s", $newCategory);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Category '$newCategory' already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name, created_at) VALUES (?, NOW())");
            $stmt->bind_param("s", $newCategory);
            if ($stmt->execute()) {
                $success = "Category '$newCategory' added successfully!";
            } else {
                $error = "Failed to add category.";
            }
        }
    } else {
        $error = "Please enter a category name.";
    }
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $editId = intval($_POST['category_id']);
    $editName = trim($_POST['category_name']);
    
    if (!empty($editName)) {
        // Check if new name already exists (excluding current category)
        $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $checkStmt->bind_param("si", $editName, $editId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Category '$editName' already exists.";
        } else {
            // Update category name in categories table
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $editName, $editId);
            
            if ($stmt->execute()) {
                // Also update items table to reflect the new category name
                $oldNameStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                $oldNameStmt->bind_param("i", $editId);
                $oldNameStmt->execute();
                $oldResult = $oldNameStmt->get_result();
                
                if ($oldResult->num_rows > 0) {
                    $updateItemsStmt = $conn->prepare("UPDATE items SET category = ? WHERE category = (SELECT name FROM categories WHERE id = ?)");
                    $updateItemsStmt->bind_param("si", $editName, $editId);
                    $updateItemsStmt->execute();
                }
                
                $success = "Category updated successfully!";
            } else {
                $error = "Failed to update category.";
            }
        }
    } else {
        $error = "Please enter a category name.";
    }
}

// Fetch categories with item count
$categories = $conn->query("
    SELECT c.*, 
           COUNT(i.id) as item_count 
    FROM categories c 
    LEFT JOIN items i ON i.category = c.name 
    GROUP BY c.id, c.name 
    ORDER BY c.name ASC
");

// Get statistics
$totalCategories = 0;
$totalItems = 0;
$unusedCategories = 0;

$statsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT c.id) as total_categories,
        COUNT(i.id) as total_items,
        SUM(CASE WHEN i.id IS NULL THEN 1 ELSE 0 END) as unused_categories
    FROM categories c 
    LEFT JOIN items i ON i.category = c.name
");

if ($statsQuery && $row = $statsQuery->fetch_assoc()) {
    $totalCategories = $row['total_categories'] ?? 0;
    $totalItems = $row['total_items'] ?? 0;
    $unusedCategories = $row['unused_categories'] ?? 0;
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Categories</h1>
            <p class="text-muted">Organize your products with categories</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-circle"></i> Add Category
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
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
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Items Categorized</h6>
                            <h3 class="mb-0"><?= $totalItems ?></h3>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-box"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
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
    </div>

    <!-- Categories Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Categories List</h5>
        </div>
        <div class="card-body">
            <?php if ($categories && mysqli_num_rows($categories) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Category Name</th>
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
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="bi bi-tag text-white"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($category['name']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $category['item_count'] ?> items</span>
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
                                                    data-name="<?= htmlspecialchars($category['name']) ?>"
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category" class="form-control" placeholder="Enter category name" required>
                        <div class="form-text">Choose a descriptive name for your product category</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
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
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                        <div class="form-text">This will update the category for all associated items</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    console.log('Categories page loaded, found buttons:', $('.edit-category, .delete-category').length);
    
    // Initialize DataTable
    $('#categoriesTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: [4] }
        ],
        drawCallback: function() {
            // Reinitialize tooltips after table redraw
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });

    // Edit category - using event delegation for DataTables compatibility
    $(document).on('click', '.edit-category', function() {
        console.log('Edit button clicked');
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');

        console.log('Category ID:', categoryId, 'Name:', categoryName);

        $('#editCategoryId').val(categoryId);
        $('#editCategoryName').val(categoryName);

        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    });

    // Delete category - using event delegation for DataTables compatibility
    $(document).on('click', '.delete-category', function() {
        console.log('Delete button clicked');
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');

        console.log('Category ID:', categoryId, 'Name:', categoryName);

        if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
            console.log('User confirmed deletion, redirecting...');
            window.location.href = `manage_categories.php?delete=${categoryId}`;
        }
    });

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
});
</script>

<?php include 'layouts/footer.php'; ?>
