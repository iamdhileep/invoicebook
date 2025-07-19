<?php
require_once __DIR__ . '/vendor/autoload.php';
include 'db.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$sql = "SELECT expense_date, category, SUM(amount) as total 
        FROM expenses 
        WHERE expense_date BETWEEN '$from' AND '$to'
        GROUP BY expense_date, category
        ORDER BY expense_date DESC";

$result = $conn->query($sql);

$html = "<h2>Expense Summary Report</h2>";
$html .= "<p>From: $from &nbsp;&nbsp; To: $to</p>";
$html .= "<table border='1' cellpadding='8' width='100%'>
<tr><th>Date</th><th>Category</th><th>Total Amount</th></tr>";

$grandTotal = 0;
while ($row = $result->fetch_assoc()) {
    $html .= "<tr>
        <td>{$row['expense_date']}</td>
        <td>{$row['category']}</td>
        <td>₹" . number_format($row['total'], 2) . "</td>
    </tr>";
    $grandTotal += $row['total'];
}
$html .= "<tr><td colspan='2'><strong>Grand Total</strong></td><td><strong>₹" . number_format($grandTotal, 2) . "</strong></td></tr>";
$html .= "</table>";

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output("expense_summary.pdf", "D");
