<?php
include 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Expense ID missing.");
}

// Fetch current data
$query = $conn->query("SELECT * FROM expenses WHERE id = $id");
$expense = $query->fetch_assoc();
if (!$expense) {
    die("Expense not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['expense_date'];
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $note = $_POST['note'];

    $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, category=?, amount=?, note=? WHERE id=?");
    $stmt->bind_param("ssdsi", $date, $category, $amount, $note, $id);
    $stmt->execute();
    
    echo "<script>alert('Expense updated successfully'); window.location.href='expense_history.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Expense</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h4 class="mb-4">✏️ Edit Expense</h4>
  <form method="POST">
    <div class="mb-3">
      <label>Date</label>
      <input type="date" name="expense_date" class="form-control" value="<?= $expense['expense_date'] ?>" required>
    </div>
    <div class="mb-3">
      <label>Category</label>
      <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($expense['category']) ?>" required>
    </div>
    <div class="mb-3">
      <label>Amount</label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= $expense['amount'] ?>" required>
    </div>
    <div class="mb-3">
      <label>Note</label>
      <textarea name="note" class="form-control"><?= htmlspecialchars($expense['note']) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
    <a href="expense_history.php" class="btn btn-secondary">← Cancel</a>
  </form>
</div>
</body>
</html>
