<?php
/**
 * Transaction Export Handler
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'csv';

// Get transactions for export
$export_query = "
    SELECT 
        at.transaction_date,
        at.transaction_type,
        coa.account_code,
        coa.account_name,
        ba.account_name as bank_account,
        at.amount,
        at.debit_amount,
        at.credit_amount,
        at.description,
        at.reference_number,
        at.payment_method,
        at.category,
        at.approval_status,
        at.created_at,
        creator.username as created_by
    FROM account_transactions at
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    LEFT JOIN admin creator ON at.created_by = creator.id
    WHERE at.transaction_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY at.transaction_date DESC, at.created_at DESC
";

$result = mysqli_query($conn, $export_query);

if (!$result) {
    die("Error executing query: " . mysqli_error($conn));
}

switch ($format) {
    case 'csv':
        exportCSV($result, $date_from, $date_to);
        break;
    case 'excel':
        exportExcel($result, $date_from, $date_to);
        break;
    case 'pdf':
        exportPDF($result, $date_from, $date_to);
        break;
    default:
        exportCSV($result, $date_from, $date_to);
}

function exportCSV($result, $date_from, $date_to) {
    $filename = "transactions_" . $date_from . "_to_" . $date_to . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'Date',
        'Type',
        'Account Code',
        'Account Name',
        'Bank Account',
        'Amount',
        'Debit Amount',
        'Credit Amount',
        'Description',
        'Reference Number',
        'Payment Method',
        'Category',
        'Status',
        'Created Date',
        'Created By'
    ]);
    
    // Data
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['transaction_date'],
            ucfirst($row['transaction_type']),
            $row['account_code'],
            $row['account_name'],
            $row['bank_account'] ?? '',
            number_format($row['amount'], 2),
            number_format($row['debit_amount'], 2),
            number_format($row['credit_amount'], 2),
            $row['description'],
            $row['reference_number'] ?? '',
            ucwords(str_replace('_', ' ', $row['payment_method'])),
            $row['category'] ?? '',
            ucfirst($row['approval_status']),
            $row['created_at'],
            $row['created_by'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

function exportExcel($result, $date_from, $date_to) {
    // For simplicity, we'll export as CSV with Excel headers
    $filename = "transactions_" . $date_from . "_to_" . $date_to . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>Date</th>";
    echo "<th>Type</th>";
    echo "<th>Account Code</th>";
    echo "<th>Account Name</th>";
    echo "<th>Bank Account</th>";
    echo "<th>Amount</th>";
    echo "<th>Debit Amount</th>";
    echo "<th>Credit Amount</th>";
    echo "<th>Description</th>";
    echo "<th>Reference Number</th>";
    echo "<th>Payment Method</th>";
    echo "<th>Category</th>";
    echo "<th>Status</th>";
    echo "<th>Created Date</th>";
    echo "<th>Created By</th>";
    echo "</tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['transaction_date']) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($row['transaction_type'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['account_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['account_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bank_account'] ?? '') . "</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . number_format($row['debit_amount'], 2) . "</td>";
        echo "<td>" . number_format($row['credit_amount'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>" . htmlspecialchars($row['reference_number'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars(ucwords(str_replace('_', ' ', $row['payment_method']))) . "</td>";
        echo "<td>" . htmlspecialchars($row['category'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($row['approval_status'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_by'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportPDF($result, $date_from, $date_to) {
    // For a basic PDF export, we'll create an HTML page that can be printed as PDF
    $filename = "transactions_" . $date_from . "_to_" . $date_to . ".pdf";
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Transaction Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .summary { margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Transaction Report</h1>
            <p>Period: <?= date('d/m/Y', strtotime($date_from)) ?> to <?= date('d/m/Y', strtotime($date_to)) ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Account</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_income = 0;
                $total_expense = 0;
                while ($row = mysqli_fetch_assoc($result)):
                    if ($row['transaction_type'] == 'income') $total_income += $row['amount'];
                    if ($row['transaction_type'] == 'expense') $total_expense += $row['amount'];
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['transaction_date'])) ?></td>
                    <td><?= ucfirst($row['transaction_type']) ?></td>
                    <td><?= htmlspecialchars($row['account_code'] . ' - ' . $row['account_name']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td style="text-align: right; <?= $row['transaction_type'] == 'income' ? 'color: green;' : 'color: red;' ?>">
                        <?= $row['transaction_type'] == 'income' ? '+' : '-' ?>₹<?= number_format($row['amount'], 2) ?>
                    </td>
                    <td><?= ucwords(str_replace('_', ' ', $row['payment_method'])) ?></td>
                    <td><?= ucfirst($row['approval_status']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background-color: #f8f9fa;">
                    <td colspan="4">Summary</td>
                    <td style="text-align: right;">
                        Income: +₹<?= number_format($total_income, 2) ?><br>
                        Expense: -₹<?= number_format($total_expense, 2) ?><br>
                        Net: ₹<?= number_format($total_income - $total_expense, 2) ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        
        <script>
            window.print();
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
