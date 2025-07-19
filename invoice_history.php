<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleCheckboxes(source) {
            checkboxes = document.getElementsByName('selected_ids[]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>
<div class="container mt-5">

    <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">← Back to Dashboard</a>
    <h2>Invoice History</h2>

    <!-- 🔍 Filter Form -->
    <form method="GET" class="row g-3 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by customer name" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <!-- 🗑️ Bulk Delete Form -->
    <form method="POST" action="bulk_delete_invoice.php">
        <div class="mb-3">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete selected invoices?')">🗑️ Delete Selected</button>
        </div>

        <table class="table table-bordered table-hover">
            <thead class="table-dark text-center">
                <tr>
                    <th><input type="checkbox" onclick="toggleCheckboxes(this)"></th>
                    <th>#ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $search = $_GET['search'] ?? '';
                $sql = "SELECT * FROM invoices";
                if (!empty($search)) {
                    $sql .= " WHERE customer_name LIKE '%" . $conn->real_escape_string($search) . "%'";
                }
                $sql .= " ORDER BY id DESC";
                $result = $conn->query($sql);

                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td class='text-center'><input type='checkbox' name='selected_ids[]' value='{$row['id']}'></td>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['customer_name']) . "</td>
                        <td>{$row['invoice_date']}</td>
                        <td>₹" . number_format($row['total_amount'], 2) . "</td>
                        <td class='text-center'>
                            <a href='view_invoice.php?id={$row['id']}' class='btn btn-sm btn-success'>View / Print</a>
                            <a href='delete_invoice.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Are you sure you want to delete this invoice?');\">Delete</a>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </form>
</div>
</body>
</html>
