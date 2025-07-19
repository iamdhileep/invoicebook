<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: item-stock.php");
    exit;
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['item_name'];
    $price = $_POST['item_price'];
    $stock = $_POST['stock'];
    $category = $_POST['category'];

    $update = $conn->prepare("UPDATE items SET item_name = ?, item_price = ?, stock = ?, category = ? WHERE id = ?");
    $update->bind_param("sdisi", $name, $price, $stock, $category, $id);
    $update->execute();

    header("Location: item-stock.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Item</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
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
  <h4 class="mb-4">✏️ Edit Item</h4>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Item Name</label>
      <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Category</label>
      <div class="input-group">
        <select name="category" class="form-select" required>
          <option value="">-- Select Category --</option>
          <?php
          $catResult = mysqli_query($conn, "SELECT name FROM categories ORDER BY name ASC");
          while ($cat = mysqli_fetch_assoc($catResult)) {
              $selected = ($cat['name'] == $item['category']) ? 'selected' : '';
              echo "<option value=\"" . htmlspecialchars($cat['name']) . "\" $selected>" . htmlspecialchars($cat['name']) . "</option>";
          }
          ?>
        </select>
        <a href="add-category.php" class="btn btn-outline-secondary">+ Add</a>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Price (₹)</label>
      <input type="number" step="0.01" name="item_price" class="form-control" value="<?= htmlspecialchars($item['item_price']) ?>" required>
    </div>

    <div class="mb-4">
      <label class="form-label">Stock Quantity</label>
      <input type="number" name="stock" class="form-control" value="<?= htmlspecialchars($item['stock']) ?>" required>
    </div>

    <div class="d-flex justify-content-between">
      <a href="item-stock.php" class="btn btn-secondary">← Back</a>
      <button type="submit" class="btn btn-primary">💾 Update Item</button>
    </div>
  </form>
</div>

</body>
</html>
