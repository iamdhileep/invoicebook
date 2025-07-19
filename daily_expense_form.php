<div class="accordion accordion-flush" id="accordionFlushExample">
    <div class="accordion-item">
        <h2 class="accordion-header" id="flush-headingOne">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                Daily Expense add & Preview Report
            </button>
        </h2>
        <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
            <div class="accordion-body">
                <div class="container py-4">
                    <h4 class="mb-4">📅 Daily Expense Entry</h4>
                    <!-- ✅ Expense Form -->
                    <form action="save_expense.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label>Date</label>
                                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label>Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <!-- Salaries & Advances -->
    <option value="Salary Advance / Salary">Salary Advance / Salary</option>
    <option value="House Advance / Rent">House Advance / Rent</option>
    <option value="Shop Rent & Maintenance">Shop Rent & Maintenance</option>
    <option value="Weekly Advance">Weekly Advance</option>

    <!-- Food Items -->
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

    <!-- Inventory / Stock -->
    <option value="Sugar">Sugar</option>
    <option value="Stock">Stock</option>
    <option value="Biscuit">Biscuit</option>
    <option value="Groundnut">Groundnut</option>
    <option value="Sweet Corn">Sweet Corn</option>
    <option value="Stationery">Stationery</option>
    <option value="Goli Soda">Goli Soda</option>
    <option value="Rice">Rice</option>
    <option value="Lemon">Lemon</option>
    <option value="Ginger">Ginger</option>

    <!-- Utilities & Services -->
    <option value="Water Can 20 ltrs">Water Can 20 ltrs</option>
    <option value="Water Bottle 1 ltr">Water Bottle 1 ltr</option>
    <option value="Water Bottle 500 ml">Water Bottle 500 ml</option>
    <option value="Porter Charges">Porter Charges</option>
    <option value="Cylinder">Cylinder</option>
    <option value="Breakfast/Lunch/Dinner">Breakfast/Lunch/Dinner</option>
    <option value="Electricity Bill">Electricity Bill</option>
    <option value="Internet">Internet</option>
    <option value="Parking Charges">Parking Charges</option>
    <option value="Auto Fare">Auto Fare</option>
    <option value="Plumber and Electrical Charges">Plumber and Electrical Charges</option>
    <option value="Miscellaneous Charges">Miscellaneous Charges</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Amount (₹)</label>
                                <input type="number" name="amount" class="form-control" step="0.01" required>
                            </div>
                            <div class="col-md-3">
                                <label>Attach Bill (Optional)</label>
                                <input type="file" name="bill_file" class="form-control" accept="image/*,application/pdf">
                            </div>
                            <div class="col-12">
                                <label>Note</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Optional note or purpose..."></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-success">💾 Save Expense</button>
                            <div>
                                <a href="expense_history.php" class="btn btn-info">📋 View History</a>
                                <a href="expense_summary.php" class="btn btn-outline-success">📊 Summary Report</a>
                            </div>
                        </div>
                    </form>

                    <hr class="my-4">

                    <!-- ✅ Quick Today Summary -->
                    <h5 class="mb-3">Today's Expenses (<?= date('d M Y') ?>)</h5>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Amount (₹)</th>
                                <th>Note</th>
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
                                <td>{$row['category']}</td>
                                <td>₹" . number_format($row['amount'], 2) . "</td>
                                <td>" . htmlspecialchars($row['note']) . "</td>
                            </tr>";
                                $total += $row['amount'];
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info fw-bold">
                                <td colspan="2">Total</td>
                                <td colspan="2">₹ <?= number_format($total, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header" id="flush-headingTwo">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                Monthly Report view
            </button>
        </h2>
        <div id="flush-collapseTwo" class="accordion-collapse collapse" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
            <div class="accordion-body">
                <div class="container py-4">
                    <div class="row mb-4">
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="report_month">Select Month</label>
                                <input type="month" name="report_month" id="report_month" class="form-control" value="<?= $_GET['report_month'] ?? date('Y-m') ?>" required>
                            </div>
                            <div class="col-md-2 align-self-end">
                                <button type="submit" class="btn btn-primary">📊 Show Report</button>
                            </div>
                        </form>

                        <?php
                        if (isset($_GET['report_month'])):
                            $month = $_GET['report_month']; // Format: YYYY-MM
                            $startDate = $month . '-01';
                            $endDate = date("Y-m-t", strtotime($startDate));

                            echo "<div class='mb-3'><strong>Showing Expenses for:</strong> " . date("F Y", strtotime($startDate)) . "</div>";

                            // Fetch expenses
                            $query = "SELECT * FROM expenses WHERE expense_date BETWEEN '$startDate' AND '$endDate' ORDER BY expense_date ASC";
                            $result = mysqli_query($conn, $query);

                            $total = 0;
                            $categoryTotals = [];

                            if (mysqli_num_rows($result) > 0):
                        ?>
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Amount (₹)</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        while ($row = mysqli_fetch_assoc($result)):
                                            $amount = $row['amount'];
                                            $total += $amount;
                                            $cat = $row['category'];
                                            $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + $amount;
                                        ?>
                                            <tr>
                                                <td><?= $row['expense_date'] ?></td>
                                                <td><?= htmlspecialchars($cat) ?></td>
                                                <td>₹<?= number_format($amount, 2) ?></td>
                                                <td><?= htmlspecialchars($row['note']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>

                                <!-- Category Totals -->
                                <h6 class="mt-4">🗂️ Category-wise Total</h6>
                                <ul class="list-group mb-3">
                                    <?php foreach ($categoryTotals as $cat => $amt): ?>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($cat) ?></strong>
                                            <span>₹<?= number_format($amt, 2) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <div class="alert alert-success fw-bold">
                                    💰 Total Expense for <?= date("F Y", strtotime($startDate)) ?>: ₹ <?= number_format($total, 2) ?>
                                </div>

                        <?php
                            else:
                                echo "<div class='alert alert-warning'>No expenses found for the selected month.</div>";
                            endif;
                        endif;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>