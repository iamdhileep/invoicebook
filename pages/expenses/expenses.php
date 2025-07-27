<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Daily Expenses';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get recent expenses
$today = date('Y-m-d');
$recentExpenses = $conn->query("SELECT * FROM expenses WHERE DATE(created_at) = '$today' ORDER BY created_at DESC");

// Check for success message
$success = isset($_GET['success']) && $_GET['success'] == '1';
?>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                Expense added successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h5 mb-0">💰 Daily Expenses</h1>
                <p class="text-muted small">Record and track your daily business expenses</p>
            </div>
            <div>
                <a href="../../expense_history.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history"></i> Expense History
                </a>
            </div>
        </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark"><i class="bi bi-cash-stack me-2"></i>Add New Expense</h6>
                </div>
                <div class="card-body">
                    <form action="../../save_expense.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date *</label>
                                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="amount" class="form-control" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <optgroup label="Office Expenses">
                                        <option value="Office Rent">Office Rent</option>
                                        <option value="Office Supplies">Office Supplies</option>
                                        <option value="Utilities">Utilities (Electricity, Water, Internet)</option>
                                        <option value="Furniture & Equipment">Furniture & Equipment</option>
                                    </optgroup>
                                    <optgroup label="Transportation">
                                        <option value="Fuel">Fuel</option>
                                        <option value="Vehicle Maintenance">Vehicle Maintenance</option>
                                        <option value="Public Transport">Public Transport</option>
                                        <option value="Taxi/Uber">Taxi/Uber</option>
                                    </optgroup>
                                    <optgroup label="Marketing & Advertising">
                                        <option value="Online Advertising">Online Advertising</option>
                                        <option value="Print Advertising">Print Advertising</option>
                                        <option value="Marketing Materials">Marketing Materials</option>
                                    </optgroup>
                                    <optgroup label="Staff & Payroll">
                                        <option value="Salaries">Salaries</option>
                                        <option value="Bonuses">Bonuses</option>
                                        <option value="Staff Training">Staff Training</option>
                                        <option value="Staff Welfare">Staff Welfare</option>
                                    </optgroup>
                                    <optgroup label="Professional Services">
                                        <option value="Legal Fees">Legal Fees</option>
                                        <option value="Accounting Fees">Accounting Fees</option>
                                        <option value="Consulting">Consulting</option>
                                    </optgroup>
                                    <optgroup label="Technology">
                                        <option value="Software Subscriptions">Software Subscriptions</option>
                                        <option value="Hardware">Hardware</option>
                                        <option value="IT Support">IT Support</option>
                                    </optgroup>
                                    <optgroup label="Miscellaneous">
                                        <option value="Meals & Entertainment">Meals & Entertainment</option>
                                        <option value="Travel">Travel</option>
                                        <option value="Insurance">Insurance</option>
                                        <option value="Bank Charges">Bank Charges</option>
                                        <option value="Other">Other</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description *</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Enter expense description..." required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Receipt (Optional)</label>
                                <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
                                <div class="form-text">Upload receipt image or PDF (Max 5MB)</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Expense
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-2">
                    <h6 class="mb-0 text-dark">Today's Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $todayTotal = 0;
                    $todayCount = 0;
                    $result = $conn->query("SELECT SUM(amount) as total, COUNT(*) as count FROM expenses WHERE DATE(created_at) = '$today'");
                    if ($result && $row = $result->fetch_assoc()) {
                        $todayTotal = $row['total'] ?? 0;
                        $todayCount = $row['count'] ?? 0;
                    }
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Total Expenses:</span>
                        <strong class="text-danger">₹<?= number_format($todayTotal, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Number of Entries:</span>
                        <strong><?= $todayCount ?></strong>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../expense_history.php" class="btn btn-outline-primary">View All Expenses</a>
                        <a href="../../reports.php" class="btn btn-outline-success">Generate Report</a>
                        <a href="../../manage_categories.php" class="btn btn-outline-warning">Manage Categories</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($recentExpenses && mysqli_num_rows($recentExpenses) > 0): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Today's Expenses</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($expense = $recentExpenses->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($expense['created_at'])) ?></td>
                                <td><?= htmlspecialchars($expense['category']) ?></td>
                                <td><?= htmlspecialchars($expense['description'] ?? $expense['note'] ?? '') ?></td>
                                <td class="text-danger fw-bold">₹<?= number_format($expense['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($expense['payment_method'] ?? 'Cash') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>