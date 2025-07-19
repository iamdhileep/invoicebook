<?php

include 'db.php';
include 'includes/header.php';

// Fetch totals
$totalInvoices = 0;
$todayExpenses = 0;
$totalEmployees = 0;
$totalItems = 0;

$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalInvoices = $row['total'] ?? 0;
}

$result = mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) = CURDATE()");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayExpenses = $row['total'] ?? 0;
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'] ?? 0;
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalItems = $row['total'] ?? 0;
}
?>

<div class="container mt-4">
  <h2 class="mb-4">Dashboard</h2>
  <div class="row g-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          Total Invoices<br>
          <strong>₹ <?= number_format($totalInvoices, 2) ?></strong>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-danger text-white">
        <div class="card-body">
          Today's Expenses<br>
          <strong>₹ <?= number_format($todayExpenses, 2) ?></strong>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          Employees<br>
          <strong><?= $totalEmployees ?></strong>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body">
          Items<br>
          <strong><?= $totalItems ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
