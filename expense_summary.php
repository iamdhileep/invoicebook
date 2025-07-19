<?php
include 'db.php';

// Get filter inputs
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Query for grouped expenses by date
$sql = "SELECT expense_date, category, SUM(amount) as total 
        FROM expenses 
        WHERE expense_date BETWEEN '$from' AND '$to'
        GROUP BY expense_date, category
        ORDER BY expense_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3 class="mb-4">Expense Summary (Daily / Monthly)</h3>
<div class="mb-4">
        <a href="export_expense_pdf.php?from=<?= $from ?>&to=<?= $to ?>" class="btn btn-danger me-2">Export to PDF</a>
        <a href="export_expense_excel.php?from=<?= $from ?>&to=<?= $to ?>" class="btn btn-success">Export to Excel</a>
    </div>
    <!-- 🔍 Filter Form -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="from" class="form-label">From Date</label>
            <input type="date" name="from" value="<?= $from ?>" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label for="to" class="form-label">To Date</label>
            <input type="date" name="to" value="<?= $to ?>" class="form-control" required>
        </div>
        <div class="col-md-4 align-self-end">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Back</a>
        </div>
    </form>

    <!-- 🧾 Summary Table -->
<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Date</th>
            <th>Category</th>
            <th>Total Amount (₹)</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $grandTotal = 0;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['expense_date']}</td>
                <td>{$row['category']}</td>
                <td>₹" . number_format($row['total'], 2) . "</td>
                <td>
                    <form action='delete_expense_group.php' method='POST' onsubmit=\"return confirm('Delete all expenses for this date & category?');\">
                        <input type='hidden' name='expense_date' value='{$row['expense_date']}'>
                        <input type='hidden' name='category' value='{$row['category']}'>
                        <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                    </form>
                </td>
            </tr>";
            $grandTotal += $row['total'];
        }
        ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2" class="text-end">Grand Total:</th>
            <th>₹<?= number_format($grandTotal, 2) ?></th>
            <th></th>
        </tr>
    </tfoot>
</table>

<h5 class="mt-4">📊 Daily Expense Chart</h5>
<canvas id="dailyExpenseChart" height="100"></canvas>

    

</div>
</body>
<?php
// Group expenses by date (last 15 days or adjust as needed)
$expenseData = [];
$dateLabels = [];
$result = $conn->query("SELECT expense_date, SUM(amount) as total 
                        FROM expenses 
                        GROUP BY expense_date 
                        ORDER BY expense_date DESC 
                        LIMIT 15");

while ($row = $result->fetch_assoc()) {
    $dateLabels[] = $row['expense_date'];
    $expenseData[] = $row['total'];
}

// Reverse to show oldest to newest
$dateLabels = array_reverse($dateLabels);
$expenseData = array_reverse($expenseData);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('dailyExpenseChart').getContext('2d');
    const expenseChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($dateLabels) ?>,
            datasets: [{
                label: 'Daily Expenses (₹)',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: '#dc3545',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (₹)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
});
</script>


</html>
