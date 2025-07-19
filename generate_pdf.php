<?php
require_once __DIR__ . '/vendor/autoload.php';
include 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $conn->query("SELECT * FROM invoices WHERE id = $id");
    $invoice = $result->fetch_assoc();

    $items = json_decode($invoice['items'], true);

    // ✅ TIGHT INLINE STYLE TO REMOVE SPACING
    $html = '<style>
    body { font-family: sans-serif; font-size: 10pt; margin: 0; padding: 0; }
    h2, p, strong { margin: 0; padding: 0; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    th, td { border: 1px solid #000; padding: 2px; font-size: 9pt; word-wrap: break-word; }
</style>';

    $html .= '<h2>Customer Invoice</h2>
    <strong>Customer Name:</strong> ' . htmlspecialchars($invoice['customer_name']) . '<br>
    <strong>Contact:</strong> ' . htmlspecialchars($invoice['customer_contact']) . '<br>
    <strong>Date:</strong> ' . htmlspecialchars($invoice['invoice_date']) . '<br>

    <table>
        <thead>
            <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        </thead>
        <tbody>';
    foreach ($items as $item) {
        $html .= '<tr>
            <td>' . htmlspecialchars($item['name']) . '</td>
            <td>' . htmlspecialchars($item['qty']) . '</td>
            <td>₹' . htmlspecialchars($item['price']) . '</td>
            <td>₹' . htmlspecialchars($item['total']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>
    <strong>Grand Total:</strong> ₹' . htmlspecialchars($invoice['total_amount']);

    // ✅ Set ALL PDF margins to 0mm
    $mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => [210, 297],  // mm for A4 manually
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0
]);


    $mpdf->WriteHTML($html);
    $mpdf->Output("invoice_" . $id . ".pdf", "D");
}
?>
