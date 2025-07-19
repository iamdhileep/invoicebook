<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Item List & Stock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script>
    function toggleAll(source) {
      checkboxes = document.getElementsByName('delete_ids[]');
      for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
      }
    }
  </script>
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .container {
      max-width: 1000px;
      background-color: white;
      margin-top: 40px;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    h4 {
      font-weight: 600;
      margin-bottom: 25px;
    }
    .btn {
      border-radius: 8px;
    }
    .table thead th {
      vertical-align: middle;
      text-align: center;
    }
    .table td, .table th {
      vertical-align: middle;
    }
    .low-stock {
      background-color: #fff3cd !important;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h4>📦 Item List & Stock</h4>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">← Back to Dashboard</a>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <form method="GET" class="d-flex">
        <input type="text" name="search" class="form-control me-2" placeholder="Search by item/category" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button class="btn btn-outline-primary">Search</button>
      </form>
    </div>
    <div class="col-md-6 text-end">
      <a href="add_item.php" class="btn btn-success">+ Add New Item</a>
      <a href="export-items.php" class="btn btn-outline-primary">⬇ Export to Excel</a>
    </div>
  </div>

  <!-- 🔽 Bulk Delete Form -->
  <form method="POST" action="bulk_delete_items.php">
    <div class="mb-2">
      <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected items?')">🗑️ Delete Selected</button>
    </div>

    <table class="table table-bordered table-hover table-striped">
      <thead class="table-dark text-center">
        <tr>
          <th><input type="checkbox" onclick="toggleAll(this)"></th>
          <th>ID</th>
          <th>Item Name</th>
          <th>Category</th>
          <th>Price (₹)</th>
          <th>Stock</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $search = $_GET['search'] ?? '';
        $query = "SELECT * FROM items";
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $query .= " WHERE item_name LIKE '%$search%' OR category LIKE '%$search%'";
        }
        $query .= " ORDER BY item_name ASC";
        $result = mysqli_query($conn, $query);

        while ($row = mysqli_fetch_assoc($result)) {
            $lowStock = ($row['stock'] < 5) ? 'low-stock' : '';
            echo "<tr class='$lowStock text-center'>
                <td><input type='checkbox' name='delete_ids[]' value='{$row['id']}'></td>
                <td>{$row['id']}</td>
                <td>" . htmlspecialchars($row['item_name']) . "</td>
                <td>" . htmlspecialchars($row['category']) . "</td>
                <td>₹ " . number_format($row['item_price'], 2) . "</td>
                <td>" . (isset($row['stock']) ? $row['stock'] : '0') . "</td>
                <td>
                  <a href='edit-item.php?id={$row['id']}' class='btn btn-sm btn-primary'>Edit</a>
                  <a href='delete-item.php?id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                </td>
              </tr>";
        }
        ?>
      </tbody>
    </table>
  </form>
</div>

</body>
</html>
