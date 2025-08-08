<?php
require_once '../../db.php';

echo "Adding sample transactions...\n";

// Get account IDs
$sales_revenue_id = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM chart_of_accounts WHERE account_code = '4001'"))[0];
$service_revenue_id = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM chart_of_accounts WHERE account_code = '4002'"))[0];
$rent_expense_id = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM chart_of_accounts WHERE account_code = '6002'"))[0];
$salary_expense_id = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM chart_of_accounts WHERE account_code = '6001'"))[0];
$utilities_id = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM chart_of_accounts WHERE account_code = '6003'"))[0];

$sample_transactions = [
    [date('Y-m-d'), 'income', $sales_revenue_id, 1, 'Sales payment from customer', 'SALE001', 15000.00, 'bank_transfer', 'Sales'],
    [date('Y-m-d', strtotime('-1 day')), 'expense', $rent_expense_id, 1, 'Monthly office rent payment', 'RENT001', 8000.00, 'bank_transfer', 'Rent'],
    [date('Y-m-d', strtotime('-2 days')), 'expense', $salary_expense_id, 1, 'Salary payment to employees', 'SAL001', 25000.00, 'bank_transfer', 'Payroll'],
    [date('Y-m-d', strtotime('-3 days')), 'income', $service_revenue_id, 1, 'Service fee from client', 'SRV001', 12000.00, 'upi', 'Services'],
    [date('Y-m-d', strtotime('-4 days')), 'expense', $utilities_id, 1, 'Electricity bill payment', 'UTIL001', 2500.00, 'cash', 'Utilities'],
];

foreach ($sample_transactions as $trans) {
    $date = $trans[0];
    $type = $trans[1];
    $account_id = $trans[2];
    $bank_id = $trans[3];
    $desc = mysqli_real_escape_string($conn, $trans[4]);
    $ref = $trans[5];
    $amount = $trans[6];
    $method = $trans[7];
    $category = $trans[8];
    
    $debit = ($type == 'expense') ? $amount : 0;
    $credit = ($type == 'income') ? $amount : 0;
    
    $query = "INSERT INTO account_transactions (transaction_date, transaction_type, account_id, bank_account_id, amount, debit_amount, credit_amount, description, reference_number, payment_method, category) 
              VALUES ('$date', '$type', $account_id, $bank_id, $amount, $debit, $credit, '$desc', '$ref', '$method', '$category')";
    
    if (mysqli_query($conn, $query)) {
        echo "✓ Added transaction: $desc - ₹$amount\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
}

echo "Sample transactions setup complete.\n";
?>
