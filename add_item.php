<?php
include 'db.php';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['item_name'];
    $price = $_POST['item_price'];
    $stock = $_POST['stock'];
    $category = $_POST['category'];
    $imagePath = '';

    // ✅ Step 1: Handle image upload
    if (!empty($_FILES['item_image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['item_image']['name']);
        $targetPath = 'uploads/' . $imageName;
        move_uploaded_file($_FILES['item_image']['tmp_name'], $targetPath);
        $imagePath = $imageName;
    }

    // ✅ Step 2: Insert into database
    $stmt = $conn->prepare("INSERT INTO items (item_name, item_price, stock, category, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiss", $name, $price, $stock, $category, $imagePath);
    $stmt->execute();

    // ✅ Step 3: Redirect
    header("Location: item-full-list.php?success=1");
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Item</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .container {
      max-width: 600px;
      background-color: #fff;
      margin-top: 60px;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    .btn {
      border-radius: 10px;
    }
  </style>
</head>
<body>

<div class="container">
  <h4 class="mb-4">➕ Add New Item</h4>

  <form method="POST" onsubmit="return validateForm();">
    <div class="mb-3">
  <label class="form-label">Item Image</label>
  <input type="file" name="item_image" class="form-control" accept="image/*">
</div>

    <div class="mb-3">
      <label class="form-label">Item Name</label>
      <input type="text" name="item_name" id="item_name" class="form-control" required>
    </div>

    <div class="mb-3">
  <label class="form-label">Category</label>
  <div class="input-group">
    <select name="category" class="form-select" required>
      <option value="">-- Select Category --</option>
      <?php
      $catResult = mysqli_query($conn, "SELECT name FROM categories ORDER BY name ASC");
      while ($cat = mysqli_fetch_assoc($catResult)) {
          echo "<option value=\"" . htmlspecialchars($cat['name']) . "\">" . htmlspecialchars($cat['name']) . "</option>";
      }
      ?>
    </select>
    <a href="manage_categories.php" class="btn btn-outline-secondary">⚙ Manage</a>
  </div>
</div>
    <div class="mb-3">
      <label class="form-label">Item Price (₹)</label>
      <input type="number" step="0.01" name="item_price" id="item_price" class="form-control" required>
    </div>

    <div class="mb-4">
      <label class="form-label">Stock Quantity</label>
      <input type="number" name="stock" id="stock" class="form-control" required>
    </div>

    <div class="d-flex justify-content-between">
      <a href="item-stock.php" class="btn btn-secondary">← Back</a>
      <button type="submit" class="btn btn-success">💾 Save</button>
    </div>
  </form>
</div>

<!-- Toast Message -->
<?php if ($success): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div class="toast show align-items-center text-bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        ✅ Item added successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function validateForm() {
  const name = document.getElementById('item_name').value.trim();
  const price = document.getElementById('item_price').value;
  const stock = document.getElementById('stock').value;

  if (!name || price <= 0 || stock < 0) {
    alert('⚠ Please enter valid item name, price, and stock.');
    return false;
  }
  return true;
}
</script>

</body>
</html>
