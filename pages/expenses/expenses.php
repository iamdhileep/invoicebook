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
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Daily Expenses</h1>
            <p class="text-muted">Track and manage your daily business expenses</p>
        </div>
        <div>
            <a href="../../expense_history.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-clock-history"></i> Expense History
            </a>
            <a href="../../expense_summary.php" class="btn btn-outline-info">
                <i class="bi bi-graph-up"></i> Summary Report
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Add Expense Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add New Expense</h5>
                </div>
                <div class="card-body">
                    <form action="../../save_expense.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Date *</label>
                                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select" required>
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
                                    <optgroup label="Inventory & Stock">
                                        <option value="Sugar">Sugar</option>
                                        <option value="Stock">Stock</option>
                                        <option value="Biscuit">Biscuit</option>
                                        <option value="Groundnut">Groundnut</option>
                                        <option value="Sweet Corn">Sweet Corn</option>
                                        <option value="Stationery">Stationery</option>
                                        <option value="Goli Soda">Goli Soda</option>
                                        <option value="Rice">Rice</option>
                                        <option value="Lemon">Lemon</option>
                                        <option value="Banana">Banana</option>
                                        <option value="Orange">Orange</option>
                                        <option value="Apple">Apple</option>
                                        <option value="Grapes">Grapes</option>
                                        <option value="Mango">Mango</option>
                                        <option value="Watermelon">Watermelon</option>
                                        <option value="Pineapple">Pineapple</option>
                                        <option value="Pomegranate">Pomegranate</option>
                                        <option value="Guava">Guava</option>
                                        <option value="Papaya">Papaya</option>
                                    </optgroup>
                                    
                                    <!-- Vegetables -->
                                    <optgroup label="Vegetables">
                                        <option value="Onion">Onion</option>
                                        <option value="Potato">Potato</option>
                                        <option value="Tomato">Tomato</option>
                                        <option value="Carrot">Carrot</option>
                                        <option value="Cabbage">Cabbage</option>
                                        <option value="Cauliflower">Cauliflower</option>
                                        <option value="Green Peas">Green Peas</option>
                                        <option value="Spinach">Spinach</option>
                                        <option value="Brinjal">Brinjal</option>
                                        <option value="Okra">Okra</option>
                                        <option value="Cucumber">Cucumber</option>
                                        <option value="Capsicum">Capsicum</option>
                                    </optgroup>
                                    
                                    <!-- Utilities & Maintenance -->
                                    <optgroup label="Utilities & Maintenance">
                                        <option value="Electricity Bill">Electricity Bill</option>
                                        <option value="Water Bill">Water Bill</option>
                                        <option value="Internet Bill">Internet Bill</option>
                                        <option value="Phone Bill">Phone Bill</option>
                                        <option value="Gas Bill">Gas Bill</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Cleaning">Cleaning</option>
                                        <option value="Repair">Repair</option>
                                    </optgroup>
                                    
                                    <!-- Transportation -->
                                    <optgroup label="Transportation">
                                        <option value="Fuel">Fuel</option>
                                        <option value="Transportation">Transportation</option>
                                        <option value="Delivery Charges">Delivery Charges</option>
                                        <option value="Auto/Taxi">Auto/Taxi</option>
                                    </optgroup>
                                    
                                    <!-- Miscellaneous -->
                                    <optgroup label="Miscellaneous">
                                        <option value="Office Supplies">Office Supplies</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Advertising">Advertising</option>
                                        <option value="Professional Services">Professional Services</option>
                                        <option value="Insurance">Insurance</option>
                                        <option value="Tax">Tax</option>
                                        <option value="Bank Charges">Bank Charges</option>
                                        <option value="Miscellaneous">Miscellaneous</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Amount (₹) *</label>
                                <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Optional: Add details about this expense"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Receipt/Invoice (Optional)</label>
                                <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
                                <div class="form-text">Upload receipt image or PDF (Max 5MB)</div>
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
                                    <option value="Online Payment">Online Payment</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-secondary">Clear Form</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Expenses -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Today's Expenses</h6>
                    <a href="../../expense_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php
                    $today = date('Y-m-d');
                    $todayExpenses = [];
                    $result = mysqli_query($conn, "SELECT * FROM expenses WHERE DATE(created_at) = '$today' ORDER BY created_at DESC LIMIT 10");
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $todayExpenses[] = $row;
                        }
                    }
                    ?>
                    
                    <?php if (empty($todayExpenses)): ?>
                        <p class="text-muted text-center py-4">No expenses recorded today</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayExpenses as $expense): ?>
                                        <tr>
                                            <td><?= date('H:i', strtotime($expense['created_at'])) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($expense['category']) ?></span></td>
                                            <td><strong class="text-danger">₹ <?= number_format($expense['amount'], 2) ?></strong></td>
                                            <td><?= htmlspecialchars($expense['description'] ?? 'No description') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../../edit_expense.php?id=<?= $expense['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger btn-sm delete-expense" data-id="<?= $expense['id'] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Today's Summary -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Today's Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $todayTotal = 0;
                    $todayCount = 0;
                    $result = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(amount) as total FROM expenses WHERE DATE(created_at) = '$today'");
                    if ($result) {
                        $row = mysqli_fetch_assoc($result);
                        $todayTotal = $row['total'] ?? 0;
                        $todayCount = $row['count'] ?? 0;
                    }
                    ?>
                    
                    <div class="text-center">
                        <h3 class="text-danger">₹ <?= number_format($todayTotal, 2) ?></h3>
                        <p class="text-muted mb-0"><?= $todayCount ?> expense<?= $todayCount != 1 ? 's' : '' ?> recorded today</p>
                    </div>
                    
                    <hr>
                    
                    <!-- Category Breakdown -->
                    <h6 class="mb-3">Category Breakdown</h6>
                    <?php
                    $categoryBreakdown = [];
                    $result = mysqli_query($conn, "SELECT category, SUM(amount) as total FROM expenses WHERE DATE(created_at) = '$today' GROUP BY category ORDER BY total DESC LIMIT 5");
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $categoryBreakdown[] = $row;
                        }
                    }
                    ?>
                    
                    <?php if (empty($categoryBreakdown)): ?>
                        <p class="text-muted small">No expenses to show</p>
                    <?php else: ?>
                        <?php foreach ($categoryBreakdown as $cat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small><?= htmlspecialchars($cat['category']) ?></small>
                                <small class="fw-bold text-danger">₹ <?= number_format($cat['total'], 2) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../expense_history.php" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-text"></i> View All Expenses
                        </a>
                        <a href="../../expense_summary.php" class="btn btn-outline-info">
                            <i class="bi bi-graph-up"></i> Expense Summary
                        </a>
                        <a href="../../export_expense_excel.php" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-excel"></i> Export to Excel
                        </a>
                        <a href="../../export_expense_pdf.php" class="btn btn-outline-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Record expenses immediately to avoid forgetting</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Upload receipts for better record keeping</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use proper categories for better reporting</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Review your daily expenses regularly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    $(document).ready(function() {
        // Delete expense functionality
        $(".delete-expense").click(function() {
            const expenseId = $(this).data("id");
            const row = $(this).closest("tr");
            
            if (confirm("Are you sure you want to delete this expense? This action cannot be undone.")) {
                $.post("../../delete_expense.php", {id: expenseId}, function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                            location.reload(); // Reload to update summary
                        });
                        showAlert("Expense deleted successfully", "success");
                    } else {
                        showAlert("Failed to delete expense: " + (response.message || "Unknown error"), "danger");
                    }
                }, "json").fail(function() {
                    showAlert("Error occurred while deleting expense", "danger");
                });
            }
        });

        // Auto-save form data to localStorage
        const form = $("form");
        const formInputs = form.find("input, select, textarea");
        
        // Load saved data
        formInputs.each(function() {
            const savedValue = localStorage.getItem("expense_" + this.name);
            if (savedValue && this.type !== "file") {
                $(this).val(savedValue);
            }
        });

        // Save data on change
        formInputs.on("change input", function() {
            if (this.type !== "file") {
                localStorage.setItem("expense_" + this.name, this.value);
            }
        });

        // Clear saved data on successful submit
        form.on("submit", function() {
            setTimeout(function() {
                formInputs.each(function() {
                    localStorage.removeItem("expense_" + this.name);
                });
            }, 1000);
        });

        // Format amount input
        $("input[name=amount]").on("input", function() {
            let value = this.value;
            if (value && !isNaN(value)) {
                this.value = parseFloat(value).toFixed(2);
            }
        });
    });
</script>
';

include '../../layouts/footer.php';
?>