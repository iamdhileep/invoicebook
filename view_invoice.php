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
    <title>Invoice #<?= $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
        }
        @page {
            margin: 10mm 15mm 10mm 0mm; /* top, right, bottom, left */
            padding: 0px;
        }
        body {
            margin: 10px 10px 10px 80px !important;
            padding: 0 !important;
        }
        p{
            margin-bottom:1px;
            font-size: 13px;
        }
        h6{
            font-size: 13px;
        }
        h5{
            font-size: 15px;
        }
        td {
            font-size: 13px;
        }

    </style>
</head>
<body class="mt-5 me-3">
    <div class="no-print mb-5">
        <a href="invoice_history.php" class="btn btn-secondary">Back</a>
        <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
    </div>
    <div class="row">
        <div class="col-4">
            <img src="img/teaboy.png" width="300">
        </div>
        <div class="col-8 text-end">
            <h6 class="text-left">No : 3, Ground Floor,</h6>
            <h6 class="text-left">Shivalaya Apartments, Ethiraj Salai,</h6>
            <h6 class="text-Left">Egmore, Chennnai - 600 008</h6>
            <!-- <h3>GourmetHub Restaurant</h3> -->
            <p><strong>GST No:</strong> 33ABCDE1234F1Z9</p>
<!-- <hr> -->

        </div>
    </div>
    
    <hr>
    <div class="row">
        <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($invoice['customer_name']) ?></p>
        <p><strong>Bill To: </strong><?= nl2br(htmlspecialchars($invoice['bill_address'])) ?></p>
        <p><strong>Contact:</strong> <?= htmlspecialchars($invoice['customer_contact']) ?></p>
    </div>
    <hr>
    <table class="table table-bordered">
        <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
        <tbody>
        <?php if (is_array($items)): foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= htmlspecialchars($item['qty']) ?></td>
                <td>₹<?= htmlspecialchars($item['price']) ?></td>
                <td>₹<?= htmlspecialchars($item['total']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <h5 class="text-end">Grand Total: ₹<?= $invoice['total_amount'] ?></h5>
    <?php
$amountInWords = convertNumberToWords($invoice['total_amount']);
?>

<p><strong>Amount in Words:</strong> <?= $amountInWords ?> Rupees Only</p>

    <hr class="my-4">

        <div class="row mt-3">
            <div class="col-md-6 text-start">
                <p><strong>Authorized Signature</strong></p>
                <div style="height: 60px;"></div>
                <p><strong>Owner</strong></p>
            </div>
            <div class="col-md-6 text-end">
                <div class="text-left mt-5">
                    <h5>Thank You for Your Business!</h5>
                    <p>We appreciate your prompt payment.</p>
                </div>
            </div>
        </div> 
</body>
</html>
