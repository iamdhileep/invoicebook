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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .invoice-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }
        .invoice-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .invoice-header h2 {
            margin: 0;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .invoice-body {
            padding: 30px;
        }
        .info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2563eb;
        }
        .success { color: #16a34a; }
        .error { color: #dc2626; }
        .info { color: #2563eb; }
        .amount-highlight {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        .table-modern {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table-modern thead {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: white;
        }
        .table-modern tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .btn-modern {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s ease;
        }
        .btn-primary-modern {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
        }
        .btn-secondary-modern {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print { display: none; }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
            .invoice-header {
                background: #2563eb !important;
                -webkit-print-color-adjust: exact;
            }
        }
        @page {
            margin: 10mm 15mm 10mm 0mm;
            padding: 0px;
        }
        p { margin-bottom: 8px; font-size: 14px; }
        h6 { font-size: 14px; }
        h5 { font-size: 16px; }
        td { font-size: 14px; }
    </style>
</head>
<body class="mt-5 me-3">
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <h2><i class="fas fa-file-invoice"></i> Invoice Management System</h2>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Professional Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></p>
        </div>

        <!-- Action Buttons -->
        <div class="no-print" style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e5e7eb;">
            <div class="text-center">
                <a href="invoice_history.php" class="btn-modern btn-secondary-modern">
                    <i class="fas fa-arrow-left"></i> Back to History
                </a>
                <button onclick="window.print()" class="btn-modern btn-primary-modern">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
            </div>
        </div>

        <!-- Invoice Content -->
        <div class="invoice-body">
            <!-- Company Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-building text-primary"></i> Company Information</h5>
                        <img src="img/teaboy.png" width="250" class="mb-3" alt="Company Logo">
                        <div>
                            <p><strong>Address:</strong></p>
                            <p>No : 3, Ground Floor,</p>
                            <p>Shivalaya Apartments, Ethiraj Salai,</p>
                            <p>Egmore, Chennai - 600 008</p>
                            <p><strong>GST No:</strong> 33ABCDE1234F1Z9</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-user text-success"></i> Customer Details</h5>
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            <p><strong><i class="fas fa-hashtag"></i> Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
                            <p><strong><i class="fas fa-calendar"></i> Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></p>
                            <p><strong><i class="fas fa-user-circle"></i> Name:</strong> <?= htmlspecialchars($invoice['customer_name']) ?></p>
                            <p><strong><i class="fas fa-map-marker-alt"></i> Bill To:</strong></p>
                            <div style="padding-left: 20px; border-left: 3px solid #2563eb; margin-left: 10px;">
                                <?= nl2br(htmlspecialchars($invoice['bill_address'])) ?>
                            </div>
                            <p><strong><i class="fas fa-phone"></i> Contact:</strong> <?= htmlspecialchars($invoice['customer_contact']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="info-card">
                <h5><i class="fas fa-shopping-cart text-info"></i> Invoice Items</h5>
                <table class="table table-modern mt-3">
                    <thead>
                        <tr>
                            <th><i class="fas fa-box"></i> Item</th>
                            <th><i class="fas fa-sort-numeric-up"></i> Qty</th>
                            <th><i class="fas fa-tag"></i> Price</th>
                            <th><i class="fas fa-calculator"></i> Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (is_array($items)): foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($item['qty']) ?></span></td>
                            <td>₹<?= number_format($item['price'], 2) ?></td>
                            <td><strong>₹<?= number_format($item['total'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Amount -->
            <div class="amount-highlight">
                <h4><i class="fas fa-rupee-sign"></i> Grand Total: ₹<?= number_format($invoice['total_amount'], 2) ?></h4>
                <?php $amountInWords = convertNumberToWords($invoice['total_amount']); ?>
                <p style="margin: 10px 0 0 0; opacity: 0.9;"><strong>Amount in Words:</strong> <?= $amountInWords ?> Rupees Only</p>
            </div>

            <!-- Footer Section -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="info-card">
                        <h6><i class="fas fa-signature text-warning"></i> Authorization</h6>
                        <div style="height: 80px; border-bottom: 2px solid #e5e7eb; margin: 20px 0;"></div>
                        <p><strong>Authorized Signature</strong></p>
                        <p class="text-muted">Owner</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card text-center">
                        <div style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 20px; border-radius: 10px; margin-top: 20px;">
                            <h5><i class="fas fa-heart"></i> Thank You for Your Business!</h5>
                            <p style="margin: 5px 0 0 0; opacity: 0.9;">We appreciate your prompt payment and trust.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> 
</body>
</html>
