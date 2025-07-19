<?php
include 'db.php';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get total items count
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items");
$totalRow = mysqli_fetch_assoc($totalResult);
$totalItems = $totalRow['total'];
$totalPages = ceil($totalItems / $limit);

// Fetch items for current page
$result = mysqli_query($conn, "SELECT * FROM items ORDER BY item_name ASC LIMIT $offset, $limit");
?>

<!DOCTYPE html>
<html>
<head>
  <title>📦 Item List (with Bulk Delete)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .container { margin-top: 40px; }
    th, td { text-align: center; }
  </style>
</head>
<body>
<div class="container">
  <h4>📦 Item List</h4>

  <form method="POST" action="delete-items.php" onsubmit="return confirm('Are you sure to delete selected items?');">
    <div class="mb-3 d-flex justify-content-between">
      <a href="add_item.php" class="btn btn-success">+ Add Item</a>
      <button type="submit" class="btn btn-danger">🗑 Delete Selected</button>
    </div>

    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th><input type="checkbox" id="select-all"></th>
          <th>ID</th>
          <th>Item</th>
          <th>Category</th>
          <th>Price (₹)</th>
          <th>Stock</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><input type="checkbox" name="item_ids[]" value="<?= $row['id'] ?>"></td>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= htmlspecialchars($row['category']) ?></td>
            <td>₹ <?= number_format($row['item_price'], 2) ?></td>
            <td><?= $row['stock'] ?></td>
            <td><a href="edit-item.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </form>

  <!-- Pagination -->
  <nav>
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<!-- Select all checkbox -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $('#select-all').click(function() {
    $('input[name="item_ids[]"]').prop('checked', this.checked);
  });
</script>
</body>
</html>

