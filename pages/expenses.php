<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../includes/header.php';
include '../config.php';
?>

<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-cash-coin me-2"></i>
    Daily Expenses
  </h1>
  <p class="text-muted mb-0">Track and manage your daily business expenses</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <!-- Expense Entry Form -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-plus-circle me-2"></i>
          Add New Expense
        </h5>
      </div>
      <div class="card-body">
        <form action="../save_expense.php" method="POST" enctype="multipart/form-data">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="expense_date" class="form-label">Date</label>
              <input type="date" name="expense_date" id="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label for="category" class="form-label">Category</label>
              <select name="category" id="category" class="form-select" required>
                <option value="">-- Select Category --</option>
                <!-- Salaries & Advances -->
                <optgroup label="Salaries & Advances">
                  <option value="Salary Advance / Salary">Salary Advance / Salary</option>
                  <option value="House Advance / Rent">House Advance / Rent</option>
                  <option value="Shop Rent & Maintenance">Shop Rent & Maintenance</option>
                  <option value="Weekly Advance">Weekly Advance</option>
                </optgroup>

                <!-- Food Items -->
                <optgroup label="Food Items">
                  <option value="Milk Order">Milk Order</option>
                  <option value="Mini Samosa">Mini Samosa</option>
                  <option value="Samosa">Samosa</option>
                  <option value="Veg Roll">Veg Roll</option>
                  <option value="Veg Puffs">Veg Puffs</option>
                  <option value="Bun Butter Jam">Bun Butter Jam</option>
                  <option value="Coconut Ball">Coconut Ball</option>
                  <option value="Paneer Puff">Paneer Puff</option>
                  <option value="Kova Bun">Kova Bun</option>
                  <option value="Shop Items">Shop Items</option>
                </optgroup>

                <!-- Inventory / Stock -->
                <optgroup label="Inventory / Stock">
                  <option value="Sugar">Sugar</option>
                  <option value="Stock">Stock</option>
                  <option value="Biscuit">Biscuit</option>
                  <option value="Groundnut">Groundnut</option>
                  <option value="Sweet Corn">Sweet Corn</option>
                  <option value="Stationery">Stationery</option>
                  <option value="Goli Soda">Goli Soda</option>
                  <option value="Rice">Rice</option>
                  <option value="Lemon">Lemon</option>
                </optgroup>

                <!-- Utilities -->
                <optgroup label="Utilities">
                  <option value="Electricity">Electricity</option>
                  <option value="Water">Water</option>
                  <option value="Internet">Internet</option>
                  <option value="Phone">Phone</option>
                </optgroup>

                <!-- Others -->
                <optgroup label="Others">
                  <option value="Transport">Transport</option>
                  <option value="Miscellaneous">Miscellaneous</option>
                  <option value="Repairs">Repairs</option>
                  <option value="Marketing">Marketing</option>
                </optgroup>
              </select>
            </div>
            <div class="col-md-4">
              <label for="amount" class="form-label">Amount (₹)</label>
              <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="note" class="form-label">Description/Note</label>
            <textarea name="note" id="note" class="form-control" rows="3" placeholder="Enter expense description or note"></textarea>
          </div>

          <div class="mb-3">
            <label for="receipt" class="form-label">Receipt/Bill (Optional)</label>
            <input type="file" name="receipt" id="receipt" class="form-control" accept="image/*,application/pdf">
          </div>

          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-save me-2"></i>
              Save Expense
            </button>
            <div>
              <a href="../expense_history.php" class="btn btn-info">
                <i class="bi bi-clock-history me-2"></i>
                View History
              </a>
              <a href="../expense_summary.php" class="btn btn-outline-success">
                <i class="bi bi-graph-up me-2"></i>
                Summary Report
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Today's Expenses -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-calendar-day me-2"></i>
          Today's Expenses (<?= date('d M Y') ?>)
        </h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead class="table-dark">
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount (₹)</th>
                <th>Note</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $today = date('Y-m-d');
              $expenses = mysqli_query($conn, "SELECT * FROM expenses WHERE expense_date = '$today' ORDER BY id DESC");
              $total = 0;
              while ($row = mysqli_fetch_assoc($expenses)) {
                  echo "<tr>
                      <td>{$row['expense_date']}</td>
                      <td><span class='badge bg-primary'>{$row['category']}</span></td>
                      <td class='fw-bold'>₹" . number_format($row['amount'], 2) . "</td>
                      <td>" . htmlspecialchars($row['note']) . "</td>
                      <td>
                          <div class='btn-group' role='group'>
                              <a href='../edit_expense.php?id={$row['id']}' class='btn btn-sm btn-outline-primary'>
                                  <i class='bi bi-pencil'></i>
                              </a>
                              <a href='../delete_expense.php?id={$row['id']}' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Are you sure?\")'>
                                  <i class='bi bi-trash'></i>
                              </a>
                          </div>
                      </td>
                  </tr>";
                  $total += $row['amount'];
              }
              ?>
            </tbody>
            <tfoot>
              <tr class="table-info fw-bold">
                <td colspan="2">Total</td>
                <td colspan="3">₹ <?= number_format($total, 2) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <?php if (mysqli_num_rows($expenses) == 0): ?>
          <div class="text-center py-4">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="text-muted mt-2">No expenses recorded for today</h5>
            <p class="text-muted">Add your first expense using the form above</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Monthly Report -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-bar-chart me-2"></i>
          Monthly Report
        </h5>
      </div>
      <div class="card-body">
        <form method="GET" class="mb-3">
          <div class="mb-3">
            <label for="report_month" class="form-label">Select Month</label>
            <input type="month" name="report_month" id="report_month" class="form-control" value="<?= $_GET['report_month'] ?? date('Y-m') ?>" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search me-2"></i>
            Show Report
          </button>
        </form>

        <?php
        if (isset($_GET['report_month'])):
            $month = $_GET['report_month']; // Format: YYYY-MM
            $startDate = $month . '-01';
            $endDate = date("Y-m-t", strtotime($startDate));

            echo "<div class='mb-3'><strong>Showing:</strong> " . date("F Y", strtotime($startDate)) . "</div>";

            // Fetch expenses
            $query = "SELECT * FROM expenses WHERE expense_date BETWEEN '$startDate' AND '$endDate' ORDER BY expense_date ASC";
            $result = mysqli_query($conn, $query);

            $total = 0;
            $categoryTotals = [];

            if (mysqli_num_rows($result) > 0):
                while ($row = mysqli_fetch_assoc($result)):
                    $amount = $row['amount'];
                    $total += $amount;
                    $cat = $row['category'];
                    $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + $amount;
                endwhile;
        ?>
                <!-- Category Totals -->
                <h6 class="mt-3 mb-3">Category-wise Total</h6>
                <div class="category-totals">
                    <?php foreach ($categoryTotals as $cat => $amt): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><?= htmlspecialchars($cat) ?></small>
                            <span class="badge bg-secondary">₹<?= number_format($amt, 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-success mt-3">
                    <strong>Total: ₹ <?= number_format($total, 2) ?></strong>
                </div>

        <?php
            else:
                echo "<div class='alert alert-warning'>No expenses found for the selected month.</div>";
            endif;
        endif;
        ?>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>
          Quick Stats
        </h5>
      </div>
      <div class="card-body">
        <?php
        $todayTotal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE expense_date = CURDATE()"))['total'] ?? 0;
        $weekTotal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"))['total'] ?? 0;
        $monthTotal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())"))['total'] ?? 0;
        ?>

        <div class="row g-3 text-center">
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">Today</h6>
              <h4 class="text-danger">₹ <?= number_format($todayTotal, 2) ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">This Week</h6>
              <h4 class="text-warning">₹ <?= number_format($weekTotal, 2) ?></h4>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <h6 class="text-muted">This Month</h6>
              <h4 class="text-info">₹ <?= number_format($monthTotal, 2) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>