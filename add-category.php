<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCat = trim($_POST['category']);

    if ($newCat !== '') {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $newCat);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect back to the same form so user stays on 'add_item.php'
    header("Location: add_item.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Category</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f1f3f5;
      font-family: 'Segoe UI', sans-serif;
    }
    .form-container {
      max-width: 500px;
      margin: 80px auto;
      padding: 30px;
      background-color: #fff;
      border-radius: 15px;
      box-shadow: 0 0 12px rgba(0,0,0,0.1);
    }
    .form-container h4 {
      font-weight: 600;
      margin-bottom: 25px;
    }
    .btn {
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div class="form-container">
  <h4>🗂️ Add New Category</h4>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Category Name</label>
      <input type="text" name="category" class="form-control" placeholder="e.g. Tea, Snacks" required>
    </div>

    <div class="d-flex justify-content-between">
      <a href="add_item.php" class="btn btn-secondary">← Back</a>
      <button type="submit" class="btn btn-success">➕ Add Category</button>
    </div>
  </form>
</div>

</body>
</html>
