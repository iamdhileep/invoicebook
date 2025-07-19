<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// Total Invoices
$totalInvoices = 0;
$result = mysqli_query($conn, "SELECT SUM(grand_total) AS total FROM invoices");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalInvoices = $row['total'] ?? 0;
}

// Today's Expenses
$todayExpenses = 0;
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayExpenses = $row['total'] ?? 0;
}

// Total Employees
$totalEmployees = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'] ?? 0;
}

// Total Items in Menu
$totalItems = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalItems = $row['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Invoice & Daily Expense</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body>

    <!-- Loader -->
    <div id="ajax-loader" style="
  position: fixed;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.8);
  z-index: 9999;
  top: 0;
  left: 0;
  display: flex;
  justify-content: center;
  align-items: center;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- 3. DASHBOARD CARDS (already working now) -->
    <div class="container mt-4">
        <div class="row g-4">
            <!-- Cards: Total Invoices, Expenses, Employees, Items -->
        </div>
    </div>

    <!-- 4. 🔽 PLACE THIS: DATE FILTER FORM -->
    <div class="container mt-5">
        <h4>View Summary by Date Range</h4>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="from_date" class="form-label">From Date</label>
                <input type="date" class="form-control" name="from_date" required>
            </div>
            <div class="col-md-3">
                <label for="to_date" class="form-label">To Date</label>
                <input type="date" class="form-control" name="to_date" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <!-- 5. 🔽 PLACE THIS: PHP Output of Summary -->
    <?php
    if (isset($_GET['from_date']) && isset($_GET['to_date'])) {
        $from = $_GET['from_date'];
        $to = $_GET['to_date'];

        // Invoices
        $invoiceTotal = 0;
        $invoiceQuery = "SELECT SUM(total_amount) AS total FROM invoices WHERE invoice_date BETWEEN '$from' AND '$to'";
        $invoiceResult = mysqli_query($conn, $invoiceQuery);
        if ($invoiceResult) {
            $row = mysqli_fetch_assoc($invoiceResult);
            $invoiceTotal = $row['total'] !== null ? $row['total'] : 0;
        } else {
            echo "<div class='alert alert-danger'>Invoice Query Error: " . mysqli_error($conn) . "</div>";
        }

        // Expenses
        $expenseTotal = 0;
        $expenseQuery = "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) BETWEEN '$from' AND '$to'";
        $expenseResult = mysqli_query($conn, $expenseQuery);
        if ($expenseResult) {
            $row = mysqli_fetch_assoc($expenseResult);
            $expenseTotal = $row['total'] !== null ? $row['total'] : 0;
        }

        // Output
        echo "<div class='container mt-4'>";
        echo "<h5>Summary from <strong>$from</strong> to <strong>$to</strong>:</h5>";
        echo "<ul class='list-group'>";
        echo "<li class='list-group-item'>Total Invoices: ₹ " . number_format($invoiceTotal, 2) . "</li>";
        echo "<li class='list-group-item'>Total Expenses: ₹ " . number_format($expenseTotal, 2) . "</li>";
        echo "<li class='list-group-item'>Net Profit: ₹ " . number_format(($invoiceTotal - $expenseTotal), 2) . "</li>";
        echo "</ul></div>";
    }
    ?>
    <div class="container mt-5">
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">Dashboard</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab">Invoice</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">Product List</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense" type="button" role="tab">Daily Expense</button>
            </li>
            <!-- ✅ New Employee Manager Tab -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="emp-manager-tab" data-bs-toggle="tab" data-bs-target="#emp-manager" type="button" role="tab">Employee Details</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">Attendance</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">Payroll</button>
            </li>
        </ul>


        <!-- Tab Contents -->
        <div class="tab-content p-3 border border-top-0" id="myTabContent">

            <!-- Invoice Tab -->
            <?php
            // Add this line at the top of your file if not already done
            include 'db.php';
            // Total Invoices Amount
            $totalInvoices = 0;

            // 🔁 Replace 'total_amount' with your actual column name
            $result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $totalInvoices = $row['total'] ?? 0;
            }
            $itemOptions = $conn->query("SELECT * FROM items ORDER BY id DESC");
            ?>

            <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                <div class="container py-4">
                    <?php include 'dashboard.php'; ?>
                </div>
            </div>

            <!-- 🧾 Invoice -->
            <div class="tab-pane fade show" id="invoice" role="tabpanel" aria-labelledby="invoice-tab">
                <div class="container py-4">
                    <?php include 'invoice.php'; ?>
                </div>
            </div>

            <!-- 🧾 Item List Tab -->
            <div class="tab-pane fade" id="items" role="tabpanel" aria-labelledby="items-tab">
                <div class="container py-4">
                    <?php include 'product_list.php'; ?>
                </div>
            </div>

            <!-- Daily Expense Tab -->
            <div class="tab-pane fade" id="expense" role="tabpanel" aria-labelledby="expense-tab">
                <div class="container py-4">
                    <?php include 'daily_expense_form.php'; ?>
                </div>
            </div>


            <!-- Employee Manager Tab -->
            <div class="tab-pane fade" id="emp-manager" role="tabpanel" aria-labelledby="emp-manager-tab">
                <div class="container py-4">
                    <?php include 'add_employee.php'; ?>
                </div>
            </div>

            <!-- Emp attendance -->
            <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                <div class="container py-4">
                    <?php include 'mark_attendance.php'; ?>
                </div>
            </div>

            <!-- Payroll Tab -->
            <div class="tab-pane fade" id="payroll" role="tabpanel" aria-labelledby="payroll-tab">
                <div class="container py-4">
                    <?php include 'payroll.php'; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#itemTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                responsive: true
            });
            $('#empTable').DataTable({
                pageLength: 5,
                lengthMenu: [5, 10, 25],
                responsive: true
            });

            // Activate "Employee Manager" tab if redirected
            if (window.location.hash === "#emp-manager") {
                const trigger = new bootstrap.Tab(document.querySelector('#emp-manager-tab'));
                trigger.show();
            }
        });
    </script>

    <script>
        function clearTable() {
            const container = document.getElementById('itemRows');
            container.innerHTML = `
        <label>Items</label>
        <div class="row mb-2 item-row">
            <div class="col"><input type="text" name="item_name[]" placeholder="Item Name" class="form-control" required></div>
            <div class="col"><input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required></div>
            <div class="col"><input type="number" name="item_price[]" placeholder="Price" class="form-control price" required></div>
            <div class="col"><input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly></div>
            <div class="col-auto">
                <button type="button" class="btn btn-danger remove-item">X</button>
            </div>
        </div>
    `;
            updateListeners();
            calculateTotals();
        }

        function calculateTotals() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty')?.value) || 0;
                const price = parseFloat(row.querySelector('.price')?.value) || 0;
                const itemTotal = qty * price;
                if (row.querySelector('.total')) {
                    row.querySelector('.total').value = itemTotal.toFixed(2);
                }
                total += itemTotal;
            });

            const grandTotalInput = document.getElementById('grandTotal');
            if (grandTotalInput) {
                grandTotalInput.value = total.toFixed(2);
            }
        }


        function updateListeners() {
            // Update price when item is selected
            document.querySelectorAll('.item-select').forEach(select => {
                select.addEventListener('change', function() {
                    const price = this.selectedOptions[0].getAttribute('data-price');
                    const row = this.closest('.item-row');
                    row.querySelector('.price').value = price || 0;
                    row.querySelector('.qty').value = 1;
                    row.querySelector('.total').value = price || 0;
                    calculateTotals();
                });
            });

            // Qty/Price changes
            document.querySelectorAll('.qty, .price').forEach(input => {
                input.removeEventListener('input', calculateTotals);
                input.addEventListener('input', calculateTotals);
            });

            // Remove row
            document.querySelectorAll('.remove-item').forEach(button => {
                button.onclick = function() {
                    this.closest('.item-row').remove();
                    calculateTotals();
                };
            });
        }

        function addRow() {
            const row = document.createElement('div');
            row.className = 'row mb-2 item-row';
            row.innerHTML = `
        <div class="col">
            <select name="item_name[]" class="form-select item-select" required>
                <option value="">-- Select Item --</option>
                <?php
                $itemsAgain = $conn->query("SELECT * FROM items ORDER BY id DESC");
                while ($item = $itemsAgain->fetch_assoc()) {
                    echo "<option value='{$item['item_name']}' data-price='{$item['item_price']}'>{$item['item_name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col"><input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required></div>
        <div class="col"><input type="number" name="item_price[]" placeholder="Price" class="form-control price" required></div>
        <div class="col"><input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly></div>
        <div class="col-auto">
            <button type="button" class="btn btn-danger remove-item">X</button>
        </div>`;

            document.getElementById('itemRows').appendChild(row);
            updateListeners();
            calculateTotals();
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateListeners();
            calculateTotals();
        });
    </script>
    <script>
        // 🔍 Live Search & Category Filter
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('itemSearch');
            const categoryFilter = document.getElementById('categoryFilter');
            const selectAll = document.getElementById('selectAll');
            const deleteSelected = document.getElementById('deleteSelected');

            function filterItems() {
                const searchTerm = searchInput?.value.toLowerCase() || '';
                const selectedCategory = categoryFilter?.value || '';

                document.querySelectorAll('#itemTable tbody tr').forEach(row => {
                    const name = row.getAttribute('data-name')?.toLowerCase() || '';
                    const category = row.getAttribute('data-category') || '';
                    const matchesName = name.includes(searchTerm);
                    const matchesCategory = !selectedCategory || category === selectedCategory;

                    row.style.display = matchesName && matchesCategory ? '' : 'none';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterItems);
            }

            if (categoryFilter) {
                categoryFilter.addEventListener('change', filterItems);
            }

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    document.querySelectorAll("#itemTable tbody input[type='checkbox']").forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }

            if (deleteSelected) {
                deleteSelected.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm("Are you sure you want to delete selected items?")) {
                        document.getElementById('bulkDeleteForm')?.submit();
                    }
                });
            }
        });
        // const searchInput = document.getElementById('itemSearch');
        // const categoryFilter = document.getElementById('categoryFilter');
        // const selectAll = document.getElementById('selectAll');
        // const deleteSelected = document.getElementById('deleteSelected');

        // function filterItems() {
        //     const searchTerm = searchInput.value.toLowerCase();
        //     const selectedCategory = categoryFilter.value;

        //     document.querySelectorAll('#itemTable tbody tr').forEach(row => {
        //         const name = row.getAttribute('data-name').toLowerCase();
        //         const category = row.getAttribute('data-category');
        //         const matchesName = name.includes(searchTerm);
        //         const matchesCategory = !selectedCategory || category === selectedCategory;

        //         row.style.display = matchesName && matchesCategory ? '' : 'none';
        //     });
        // }

        // searchInput.addEventListener('input', filterItems);
        // categoryFilter.addEventListener('change', filterItems);

        // selectAll.addEventListener('change', () => {
        //     document.querySelectorAll("#itemTable tbody input[type='checkbox']").forEach(checkbox => {
        //         checkbox.checked = selectAll.checked;
        //     });
        // });

        // deleteSelected.addEventListener('click', function(e) {
        //     e.preventDefault();
        //     if (confirm("Are you sure you want to delete selected items?")) {
        //         document.getElementById('bulkDeleteForm').submit();
        //     }
        // });
    </script>
    <script>
        // Hide loader after full page is loaded
        window.addEventListener('load', function() {
            document.getElementById('ajax-loader').style.display = 'none';
        });
    </script>

    <script>
        $(document).ready(function() {
            $('.delete-btn').click(function() {
                const btn = $(this);
                const id = btn.data('id');

                if (confirm("Are you sure you want to delete this employee?")) {
                    $.ajax({
                        url: 'employee-tabs.php',
                        method: 'POST',
                        data: {
                            ajax_delete: 1,
                            id: id
                        },
                        success: function(res) {
                            if (res.trim() === 'success') {
                                btn.closest('tr').fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                alert("Failed to delete employee.");
                            }
                        },
                        error: function() {
                            alert("AJAX error occurred.");
                        }
                    });
                }
            });
        });
    </script>
    <script>
        // Store active tab in localStorage
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(button => {
            button.addEventListener('shown.bs.tab', function(e) {
                const activeTab = e.target.getAttribute('data-bs-target'); // Example: #emp-manager
                localStorage.setItem('activeTab', activeTab);
            });
        });

        // On page load, open last active tab
        document.addEventListener('DOMContentLoaded', function() {
            const lastTab = localStorage.getItem('activeTab');
            if (lastTab) {
                const tabTrigger = document.querySelector(`button[data-bs-target="${lastTab}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
        });
    </script>
</body>

</html>