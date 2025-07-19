<?php
include 'db.php';

$filterDate = $_GET['filter_date'] ?? '';
$where = $filterDate ? "WHERE expense_date = '$filterDate'" : '';

$result = $conn->query("SELECT * FROM expenses $where ORDER BY expense_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Expense History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>📋 Daily Expense History</h3>
    <form method="GET" class="d-flex gap-2">
      <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="expense_history.php" class="btn btn-outline-secondary">Reset</a>
    </form>
  </div>

  <table class="table table-bordered table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>Date</th>
        <th>Category</th>
        <th>Amount</th>
        <th>Note</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
          <td><?= $row['expense_date'] ?></td>
          <td><?= htmlspecialchars($row['category']) ?></td>
          <td>₹<?= number_format($row['amount'], 2) ?></td>
          <td><?= htmlspecialchars($row['note']) ?></td>
          <td class="text-center">
            <a href="edit_expense.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">✏️ Edit</a>
            <a href="delete_expense.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?');">🗑️ Delete</a>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>

  <a href="index.php" class="btn btn-secondary mt-3">← Back to Dashboard</a>
</div>
</body>
</html>
