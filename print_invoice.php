<?php
include 'db.php';

// Function to convert numbers to words (Indian format)
function convertNumberToWords($number) {
    $hyphen = '-';
    $conjunction = ' and ';
    $separator = ', ';
    $negative = 'negative ';
    $decimal = ' point ';
    $dictionary = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
        5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
        15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
        20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty',
        60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety',
        100 => 'hundred', 1000 => 'thousand', 100000 => 'lakh', 10000000 => 'crore'
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

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice ID.");
}

$id = intval($_GET['id']);

// Fetch invoice data
$result = $conn->query("SELECT * FROM invoices WHERE id = $id");

if (!$result || $result->num_rows == 0) {
    die("Invoice not found.");
}

$invoice = $result->fetch_assoc();
$items = json_decode($invoice['items'], true);

// If items is not an array or null, set it as empty array
if (!is_array($items)) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($invoice['invoice_number'] ?? $invoice['id']) ?> - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { 
                display: none !important; 
            }
            .print-container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        
        @page {
            margin: 15mm;
            size: A4;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 3px solid #007bff;
        }
        
        .company-logo {
            max-width: 200px;
            height: auto;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .invoice-details {
            padding: 20px;
        }
        
        .customer-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .items-table {
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #007bff;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table td {
            text-align: center;
            vertical-align: middle;
        }
        
        .items-table .item-name {
            text-align: left;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .amount-words {
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }
        
        .footer-section {
            border-top: 2px solid #007bff;
            padding-top: 20px;
            margin-top: 30px;
        }
        
        .signature-section {
            margin-top: 40px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .company-details {
            font-size: 11px;
            line-height: 1.3;
        }
        
        @media screen {
            body {
                background: #e9ecef;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button (visible only on screen) -->
    <div class="no-print print-button">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            <i class="bi bi-printer me-2"></i>Print Invoice
        </button>
        <a href="invoice_history.php" class="btn btn-secondary btn-lg ms-2">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="invoice-container print-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <?php if (file_exists('img/teaboy.png')): ?>
                        <img src="img/teaboy.png" alt="Company Logo" class="company-logo">
                    <?php else: ?>
                        <div class="invoice-title">BillBook</div>
                        <div class="company-details">Management System</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-8 text-end">
                    <div class="invoice-title">INVOICE</div>
                    <div class="company-details">
                        <strong>No: 3, Ground Floor,</strong><br>
                        Shivalaya Apartments, Ethiraj Salai,<br>
                        Egmore, Chennai - 600 008<br>
                        <strong>GST No:</strong> 33ABCDE1234F1Z9<br>
                        <strong>Phone:</strong> +91 44 1234 5678<br>
                        <strong>Email:</strong> info@billbook.com
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <!-- Invoice Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="text-primary mb-3">Invoice Details</h5>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Invoice #:</strong></td>
                            <td><?= htmlspecialchars($invoice['invoice_number'] ?? 'INV-' . str_pad($invoice['id'], 6, '0', STR_PAD_LEFT)) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Due Date:</strong></td>
                            <td><?= isset($invoice['due_date']) && $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : 'N/A' ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="text-primary mb-3">Bill To</h5>
                    <div class="customer-details">
                        <strong><?= htmlspecialchars($invoice['customer_name']) ?></strong><br>
                        <?= nl2br(htmlspecialchars($invoice['bill_address'] ?? 'N/A')) ?><br>
                        <strong>Contact:</strong> <?= htmlspecialchars($invoice['customer_contact']) ?>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-table">
                <h5 class="text-primary mb-3">Invoice Items</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="45%" class="item-name">Item Description</th>
                            <th width="15%">Quantity</th>
                            <th width="15%">Unit Price</th>
                            <th width="20%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="item-name"><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['qty']) ?></td>
                                    <td>₹<?= number_format($item['price'], 2) ?></td>
                                    <td>₹<?= number_format($item['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Section -->
            <div class="row">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <div class="total-section">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td class="text-end">₹<?= number_format($invoice['total_amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tax (GST):</strong></td>
                                <td class="text-end">₹0.00</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong class="text-primary">Grand Total:</strong></td>
                                <td class="text-end"><strong class="text-primary fs-5">₹<?= number_format($invoice['total_amount'], 2) ?></strong></td>
                            </tr>
                        </table>
                        
                        <div class="amount-words">
                            <strong>Amount in Words:</strong><br>
                            <?= convertNumberToWords($invoice['total_amount']) ?> Rupees Only
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="footer-section">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Terms & Conditions</h6>
                        <ul class="small">
                            <li>Payment is due within 30 days</li>
                            <li>Please include invoice number with payment</li>
                            <li>Late payments may incur additional charges</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="signature-section">
                            <div class="text-center">
                                <div style="border-bottom: 1px solid #333; width: 200px; margin: 0 auto;"></div>
                                <div class="mt-2">
                                    <strong>Authorized Signature</strong><br>
                                    <small>For BillBook Management</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 pt-3 border-top">
                    <h5 class="text-success">Thank You for Your Business!</h5>
                    <p class="text-muted">We appreciate your prompt payment.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Print function
        function printInvoice() {
            window.print();
        }
        
        // Add keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printInvoice();
            }
        });
        
        // Optional auto-print (uncomment if needed)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html> 