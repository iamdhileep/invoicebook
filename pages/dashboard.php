<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../includes/header.php';
include '../config.php';

// Get dashboard statistics
$totalInvoices = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(grand_total) AS total FROM invoices"))['total'] ?? 0;
$todayExpenses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) = CURDATE()"))['total'] ?? 0;
$totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees"))['total'] ?? 0;
$totalItems = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM items"))['total'] ?? 0;

// Get monthly data for charts
$monthlyInvoices = mysqli_query($conn, "SELECT MONTH(invoice_date) as month, SUM(grand_total) as total FROM invoices WHERE YEAR(invoice_date) = YEAR(CURDATE()) GROUP BY MONTH(invoice_date)");
$monthlyExpenses = mysqli_query($conn, "SELECT MONTH(created_at) as month, SUM(amount) as total FROM expenses WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)");
?>

<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-speedometer2 me-2"></i>
    Dashboard
  </h1>
  <p class="text-muted mb-0">Welcome back! Here's what's happening with your business today.</p>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
  <div class="col-xl-3 col-lg-6">
    <div class="card bg-primary text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-title mb-1">Total Invoices</h6>
            <h3 class="mb-0">₹ <?= number_format($totalInvoices, 2) ?></h3>
          </div>
          <div class="text-primary-emphasis">
            <i class="bi bi-receipt-cutoff" style="font-size: 2rem; opacity: 0.7;"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-lg-6">
    <div class="card bg-danger text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-title mb-1">Today's Expenses</h6>
            <h3 class="mb-0">₹ <?= number_format($todayExpenses, 2) ?></h3>
          </div>
          <div class="text-danger-emphasis">
            <i class="bi bi-cash-stack" style="font-size: 2rem; opacity: 0.7;"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-lg-6">
    <div class="card bg-success text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-title mb-1">Total Employees</h6>
            <h3 class="mb-0"><?= $totalEmployees ?></h3>
          </div>
          <div class="text-success-emphasis">
            <i class="bi bi-people-fill" style="font-size: 2rem; opacity: 0.7;"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-lg-6">
    <div class="card bg-warning text-dark">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-title mb-1">Total Items</h6>
            <h3 class="mb-0"><?= $totalItems ?></h3>
          </div>
          <div class="text-warning-emphasis">
            <i class="bi bi-box-seam" style="font-size: 2rem; opacity: 0.7;"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="card-title mb-0">
      <i class="bi bi-calendar-range me-2"></i>
      Summary by Date Range
    </h5>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label for="from_date" class="form-label">From Date</label>
        <input type="date" class="form-control" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label for="to_date" class="form-label">To Date</label>
        <input type="date" class="form-control" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-search me-2"></i>
          Filter
        </button>
        <?php if (isset($_GET['from_date']) && isset($_GET['to_date'])): ?>
          <a href="dashboard.php" class="btn btn-secondary ms-2">
            <i class="bi bi-x-circle me-2"></i>
            Clear
          </a>
        <?php endif; ?>
      </div>
    </form>

    <?php
    if (isset($_GET['from_date']) && isset($_GET['to_date'])) {
        $from = $_GET['from_date'];
        $to = $_GET['to_date'];

        // Invoices
        $invoiceTotal = 0;
        $invoiceQuery = "SELECT SUM(grand_total) AS total FROM invoices WHERE invoice_date BETWEEN '$from' AND '$to'";
        $invoiceResult = mysqli_query($conn, $invoiceQuery);
        if ($invoiceResult) {
            $row = mysqli_fetch_assoc($invoiceResult);
            $invoiceTotal = $row['total'] !== null ? $row['total'] : 0;
        } else {
            echo "<div class='alert alert-danger mt-3'>Invoice Query Error: " . mysqli_error($conn) . "</div>";
        }

        // Expenses
        $expenseTotal = 0;
        $expenseQuery = "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) BETWEEN '$from' AND '$to'";
        $expenseResult = mysqli_query($conn, $expenseQuery);
        if ($expenseResult) {
            $row = mysqli_fetch_assoc($expenseResult);
            $expenseTotal = $row['total'] !== null ? $row['total'] : 0;
        }

        // Calculate net profit
        $netProfit = $invoiceTotal - $expenseTotal;

        echo "<div class='mt-4'>";
        echo "<h6>Summary from <strong>$from</strong> to <strong>$to</strong>:</h6>";
        echo "<div class='row g-3 mt-2'>";
        echo "<div class='col-md-4'>";
        echo "<div class='card border-primary'>";
        echo "<div class='card-body text-center'>";
        echo "<h5 class='text-primary'>Total Invoices</h5>";
        echo "<h3 class='text-primary'>₹ " . number_format($invoiceTotal, 2) . "</h3>";
        echo "</div></div></div>";

        echo "<div class='col-md-4'>";
        echo "<div class='card border-danger'>";
        echo "<div class='card-body text-center'>";
        echo "<h5 class='text-danger'>Total Expenses</h5>";
        echo "<h3 class='text-danger'>₹ " . number_format($expenseTotal, 2) . "</h3>";
        echo "</div></div></div>";

        echo "<div class='col-md-4'>";
        echo "<div class='card border-" . ($netProfit >= 0 ? 'success' : 'warning') . "'>";
        echo "<div class='card-body text-center'>";
        echo "<h5 class='text-" . ($netProfit >= 0 ? 'success' : 'warning') . "'>Net " . ($netProfit >= 0 ? 'Profit' : 'Loss') . "</h5>";
        echo "<h3 class='text-" . ($netProfit >= 0 ? 'success' : 'warning') . "'>₹ " . number_format(abs($netProfit), 2) . "</h3>";
        echo "</div></div></div>";
        echo "</div></div>";
    }
    ?>
  </div>
</div>

<!-- Quick Actions -->
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">
      <i class="bi bi-lightning me-2"></i>
      Quick Actions
    </h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <a href="invoices.php" class="btn btn-outline-primary w-100">
          <i class="bi bi-receipt me-2"></i>
          Create Invoice
        </a>
      </div>
      <div class="col-md-4">
        <a href="expenses.php" class="btn btn-outline-danger w-100">
          <i class="bi bi-cash-coin me-2"></i>
          Add Expense
        </a>
      </div>
      <div class="col-md-4">
        <a href="employees.php" class="btn btn-outline-success w-100">
          <i class="bi bi-person-plus me-2"></i>
          Add Employee
        </a>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>