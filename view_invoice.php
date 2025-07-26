<?php
function convertNumberToWords($number) {
    $hyphen = '-';
    $conjunction = ' and ';
    $separator = ', ';
    $negative = 'negative ';
    $decimal = ' point ';
    $dictionary = [
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
        20 => 'twenty',
        30 => 'thirty',
        40 => 'forty',
        50 => 'fifty',
        60 => 'sixty',
        70 => 'seventy',
        80 => 'eighty',
        90 => 'ninety',
        100 => 'hundred',
        1000 => 'thousand',
        100000 => 'lakh',
        10000000 => 'crore'
    ];

    if (!is_numeric($number)) return false;

    if ($number < 0) return $negative . convertNumberToWords(abs($number));

    $string = $fraction = null;

    if (strpos((string)$number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            $string = $dictionary[$tens];
            if ($units) $string .= $hyphen . $dictionary[$units];
            break;
        case $number < 1000:
            $hundreds = (int) ($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) $string .= $conjunction . convertNumberToWords($remainder);
            break;
        case $number < 100000:
            $thousands = (int) ($number / 1000);
            $remainder = $number % 1000;
            $string = convertNumberToWords($thousands) . ' ' . $dictionary[1000];
            if ($remainder) $string .= $separator . convertNumberToWords($remainder);
            break;
        case $number < 10000000:
            $lakhs = (int) ($number / 100000);
            $remainder = $number % 100000;
            $string = convertNumberToWords($lakhs) . ' ' . $dictionary[100000];
            if ($remainder) $string .= $separator . convertNumberToWords($remainder);
            break;
        default:
            $crores = (int) ($number / 10000000);
            $remainder = $number % 10000000;
            $string = convertNumberToWords($crores) . ' ' . $dictionary[10000000];
            if ($remainder) $string .= $separator . convertNumberToWords($remainder);
            break;
    }

    if ($fraction !== null) {
        $string .= $decimal;
        $words = [];
        foreach (str_split((string) $fraction) as $digit) {
            $words[] = $dictionary[$digit];
        }
        $string .= implode(' ', $words);
    }

    return ucwords($string);
}
?>

<?php
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice ID.");
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM invoices WHERE id = $id");

if (!$result || $result->num_rows == 0) {
    die("Invoice not found.");
}

$invoice = $result->fetch_assoc();
$items = json_decode($invoice['items'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>📄 Invoice #<?= $id ?> - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .invoice-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .invoice-header {
            background: #2c3e50;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .invoice-header h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 600;
        }
        .invoice-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .action-bar {
            background: #ecf0f1;
            padding: 15px 25px;
            text-align: center;
            border-bottom: 1px solid #bdc3c7;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        .invoice-content {
            padding: 30px;
        }
        .company-customer-row {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        .company-info, .customer-info {
            flex: 1;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3498db;
        }
        .info-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        .info-line {
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-line strong {
            color: #2c3e50;
            display: inline-block;
            min-width: 80px;
        }
        .address-block {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
        }
        .items-section {
            margin: 30px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .invoice-table thead {
            background: #34495e;
            color: white;
        }
        .invoice-table th,
        .invoice-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .invoice-table th {
            font-weight: 600;
            font-size: 14px;
        }
        .invoice-table tbody tr {
            transition: background-color 0.3s ease;
        }
        .invoice-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .qty-badge {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .total-section {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            margin: 25px 0;
        }
        .total-amount {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .amount-words {
            font-size: 14px;
            opacity: 0.9;
            font-style: italic;
        }
        .footer-section {
            display: flex;
            gap: 30px;
            margin-top: 40px;
        }
        .signature-block, .thank-you-block {
            flex: 1;
        }
        .signature-area {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .signature-line {
            border-bottom: 2px solid #bdc3c7;
            height: 60px;
            margin: 20px 0;
        }
        .thank-you-card {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
        }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .invoice-wrapper { box-shadow: none; }
        }
        @media (max-width: 768px) {
            .company-customer-row { flex-direction: column; gap: 20px; }
            .footer-section { flex-direction: column; gap: 20px; }
        }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        <!-- Header -->
        <div class="invoice-header">
            <h2>INVOICE</h2>
            <p>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></p>
        </div>

        <!-- Action Buttons -->
        <div class="action-bar no-print">
            <a href="invoice_history.php" class="btn btn-secondary">← Back to History</a>
            <button onclick="window.print()" class="btn btn-primary">🖨 Print Invoice</button>
        </div>

        <!-- Invoice Content -->
        <div class="invoice-content">
            <!-- Company and Customer Info -->
            <div class="company-customer-row">
                <div class="company-info">
                    <div class="section-title">Company Information</div>
                    <div class="info-group">
                        <img src="img/teaboy.png" width="200" style="margin-bottom: 15px;" alt="Company Logo">
                        <div class="info-line"><strong>Address:</strong></div>
                        <div class="address-block">
                            No : 3, Ground Floor,<br>
                            Shivalaya Apartments, Ethiraj Salai,<br>
                            Egmore, Chennai - 600 008
                        </div>
                        <div class="info-line"><strong>GST No:</strong> 33ABCDE1234F1Z9</div>
                    </div>
                </div>
                
                <div class="customer-info">
                    <div class="section-title">Customer Details</div>
                    <div class="info-group">
                        <div class="info-line"><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></div>
                        <div class="info-line"><strong>Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></div>
                        <div class="info-line"><strong>Customer:</strong> <?= htmlspecialchars($invoice['customer_name']) ?></div>
                        <div class="info-line"><strong>Contact:</strong> <?= htmlspecialchars($invoice['customer_contact']) ?></div>
                        <div class="info-line"><strong>Bill To:</strong></div>
                        <div class="address-block">
                            <?= nl2br(htmlspecialchars($invoice['bill_address'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-section">
                <div class="section-title">Invoice Items</div>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th style="text-align: center;">Quantity</th>
                            <th style="text-align: right;">Unit Price</th>
                            <th style="text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (is_array($items)): foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td style="text-align: center;">
                                <span class="qty-badge"><?= htmlspecialchars($item['qty']) ?></span>
                            </td>
                            <td style="text-align: right;">₹<?= number_format($item['price'], 2) ?></td>
                            <td style="text-align: right;"><strong>₹<?= number_format($item['total'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Amount -->
            <div class="total-section">
                <div class="total-amount">GRAND TOTAL: ₹<?= number_format($invoice['total_amount'], 2) ?></div>
                <?php $amountInWords = convertNumberToWords($invoice['total_amount']); ?>
                <div class="amount-words">Amount in Words: <?= $amountInWords ?> Rupees Only</div>
            </div>

            <!-- Footer Section -->
            <div class="footer-section">
                <div class="signature-block">
                    <div class="section-title">Authorization</div>
                    <div class="signature-area">
                        <div class="signature-line"></div>
                        <strong>Authorized Signature</strong><br>
                        <small class="text-muted">Owner</small>
                    </div>
                </div>
                
                <div class="thank-you-block">
                    <div class="section-title">Thank You</div>
                    <div class="thank-you-card">
                        <h4 style="margin: 0 0 10px 0;">Thank You for Your Business!</h4>
                        <p style="margin: 0; font-size: 14px;">We appreciate your prompt payment and trust in our services.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
