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
            background-color: #ffffff;
            margin: 0;
            padding: 15px;
            font-size: 13px;
            line-height: 1.3;
        }
        .invoice-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .invoice-header {
            background: #f8f9fa;
            border-bottom: 2px solid #007bff;
            padding: 15px 20px;
            text-align: center;
        }
        .invoice-header h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        .invoice-header p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .action-bar {
            background: #f1f3f4;
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .invoice-content {
            padding: 20px;
        }
        .company-customer-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .company-info, .customer-info {
            flex: 1;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #007bff;
        }
        .info-group {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .info-line {
            margin-bottom: 4px;
            font-size: 12px;
            line-height: 1.4;
        }
        .info-line strong {
            color: #333;
            display: inline-block;
            min-width: 70px;
            font-size: 12px;
        }
        .address-block {
            background: white;
            padding: 8px;
            border-radius: 3px;
            margin: 5px 0;
            border: 1px solid #e0e0e0;
            font-size: 12px;
            line-height: 1.3;
        }
        .items-section {
            margin: 15px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .invoice-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #007bff;
        }
        .invoice-table th,
        .invoice-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        .invoice-table th {
            font-weight: 600;
            color: #333;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .qty-badge {
            background: #007bff;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
        }
        .total-section {
            background: #e8f5e9;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            margin: 15px 0;
        }
        .total-amount {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #28a745;
        }
        .amount-words {
            font-size: 11px;
            color: #666;
            font-style: italic;
        }
        .footer-section {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .signature-block, .thank-you-block {
            flex: 1;
        }
        .signature-area {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .signature-line {
            border-bottom: 1px solid #999;
            height: 40px;
            margin: 15px 0 10px 0;
        }
        .thank-you-card {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .thank-you-card h4 {
            color: #856404;
        }
        
        /* Print Styles */
        @media print {
            body { 
                background: white; 
                padding: 0; 
                margin: 0;
                font-size: 11px;
                line-height: 1.2;
            }
            .no-print { display: none !important; }
            .invoice-wrapper { 
                box-shadow: none; 
                border: none;
                max-width: none;
                margin: 0;
            }
            .invoice-content {
                padding: 10px;
            }
            .company-customer-row {
                margin-bottom: 10px;
            }
            .info-group {
                padding: 8px;
            }
            .invoice-table th,
            .invoice-table td {
                padding: 4px 6px;
                font-size: 10px;
            }
            .total-section {
                padding: 10px;
                margin: 10px 0;
            }
            .footer-section {
                margin-top: 10px;
            }
            .signature-line {
                height: 30px;
                margin: 10px 0 5px 0;
            }
            .section-title {
                font-size: 12px;
                margin-bottom: 5px;
            }
            .info-line {
                font-size: 10px;
                margin-bottom: 2px;
            }
            .address-block {
                padding: 5px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .company-customer-row { flex-direction: column; gap: 15px; }
            .footer-section { flex-direction: column; gap: 15px; }
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
                        <img src="img/teaboy.png" width="150" style="margin-bottom: 8px;" alt="Company Logo">
                        <div class="info-line"><strong>Address:</strong></div>
                        <div class="address-block">
                            No : 3, Ground Floor, Shivalaya Apartments,<br>
                            Ethiraj Salai, Egmore, Chennai - 600 008
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
                            <th style="text-align: center; width: 80px;">Qty</th>
                            <th style="text-align: right; width: 100px;">Unit Price</th>
                            <th style="text-align: right; width: 100px;">Total</th>
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
                        <strong style="font-size: 12px;">Authorized Signature</strong><br>
                        <small style="font-size: 11px; color: #666;">Owner</small>
                    </div>
                </div>
                
                <div class="thank-you-block">
                    <div class="section-title">Thank You</div>
                    <div class="thank-you-card">
                        <h4 style="margin: 0 0 5px 0; font-size: 14px;">Thank You for Your Business!</h4>
                        <p style="margin: 0; font-size: 11px;">We appreciate your prompt payment and trust.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
