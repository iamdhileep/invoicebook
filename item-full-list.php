<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>📦 Item List</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- DataTables + Export CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
</head>
<body>

<div class="container mt-5">
  
  <h4 class="mb-4">📦 All Items with Export, Search, Filter</h4>

  <!-- Category Filter -->
  <?php
  $catResult = mysqli_query($conn, "SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
  ?>
  <select id="categoryFilter" class="form-select mb-3" style="max-width: 300px;">
    <option value="">🔍 Filter by Category</option>
    <?php while ($cat = mysqli_fetch_assoc($catResult)): ?>
      <option value="<?= htmlspecialchars($cat['category']) ?>">
        <?= htmlspecialchars($cat['category']) ?>
      </option>
    <?php endwhile; ?>
  </select>

  <!-- Item Table -->
  <table id="itemTable" class="table table-bordered table-striped">
    <thead class="table-dark">
  <tr>
    <th>ID</th>
    <th>Image</th>
    <th>Item Name</th>
    <th>Category</th>
    <th>Price (₹)</th>
    <th>Stock</th>
    <th>Action</th>
  </tr>
</thead>
<tbody>
  <?php
  $result = mysqli_query($conn, "SELECT * FROM items ORDER BY id DESC");
  while ($row = mysqli_fetch_assoc($result)) {
      $image = !empty($row['image_path']) ? $row['image_path'] : 'no-image.png';
      echo "<tr>
        <td>{$row['id']}</td>
        <td><img src='uploads/{$image}' width='50' height='50' style='object-fit:cover; border-radius:5px;'></td>
        <td>" . htmlspecialchars($row['item_name']) . "</td>
        <td>" . htmlspecialchars($row['category']) . "</td>
        <td>₹ " . number_format($row['item_price'], 2) . "</td>
        <td>" . htmlspecialchars($row['stock']) . "</td>
        <td>
          <a href='edit-item.php?id={$row['id']}' class='btn btn-sm btn-primary'>✏️ Edit</a>
          <a href='delete-item.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>🗑️ Delete</a>
        </td>
      </tr>";
  }
  ?>
</tbody>


  </table>

  <!-- Total Summary -->
  <?php
  $totalRes = mysqli_query($conn, "SELECT SUM(stock) as total_stock, SUM(item_price * stock) as total_value FROM items");
  $totals = mysqli_fetch_assoc($totalRes);
  ?>
  <div class="alert alert-info mt-3">
    <strong>📦 Total Stock:</strong> <?= $totals['total_stock'] ?? 0 ?> |
    <strong>💰 Total Value:</strong> ₹ <?= number_format($totals['total_value'] ?? 0, 2) ?>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">← Back to Dashboard</a>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>

<script>
  $(document).ready(function () {
    var table = $('#itemTable').DataTable({
      pageLength: 10,
      lengthMenu: [5, 10, 25, 50, 100],
      responsive: true,
      dom: 'Bfrtip',
      buttons: ['copy', 'excel', 'pdf', 'print']
    });

    // Category filter
    $('#categoryFilter').on('change', function () {
      table.column(2).search(this.value).draw();
    });
  });
</script>

</body>
</html>
