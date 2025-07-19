<?php
include 'db.php';

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    header("Location: manage_categories.php");
    exit;
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCategory = trim($_POST['category']);
    if (!empty($newCategory)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $newCategory);
        $stmt->execute();
    }
    header("Location: manage_categories.php");
    exit;
}

// Fetch categories
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Categories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <a href="add_item.php" class="btn btn-outline-secondary mb-3">← Back to Add Item</a>
  <h4 class="mb-4">🗂 Category Manager</h4>

  <!-- Add Category Form -->
  <form method="POST" class="mb-4">
    <div class="input-group">
      <input type="text" name="category" class="form-control" placeholder="New Category" required>
      <button type="submit" class="btn btn-success">➕ Add</button>
    </div>
  </form>

  <!-- Category List -->
  <table class="table table-bordered">
    <thead class="table-dark text-center">
      <tr><th>#</th><th>Category Name</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php $i = 1; while ($cat = $result->fetch_assoc()): ?>
        <tr>
          <td class="text-center"><?= $i++ ?></td>
          <td><?= htmlspecialchars($cat['name']) ?></td>
          <td class="text-center">
            <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')">🗑️</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
