<?php
/**
 * Accounts Management System - Main Dashboard
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

$page_title = 'Accounts Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_transaction':
            $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
            $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
            $account_id = intval($_POST['account_id']);
            $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : 'NULL';
            $amount = floatval($_POST['amount']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');

            $debit_amount = ($transaction_type == 'expense') ? $amount : 0;
            $credit_amount = ($transaction_type == 'income') ? $amount : 0;

            $query = "INSERT INTO account_transactions
                      (transaction_date, transaction_type, account_id, bank_account_id, amount, debit_amount, credit_amount,
                       description, reference_number, payment_method, category)
                      VALUES 
                      ('$transaction_date', '$transaction_type', $account_id, $bank_account_id, $amount, $debit_amount, $credit_amount,
                       '$description', '$reference_number', '$payment_method', '$category')";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Transaction added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding transaction: ' . mysqli_error($conn)]);
            }
            exit;

        case 'update_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
            $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
            $account_id = intval($_POST['account_id']);
            $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : 'NULL';
            $amount = floatval($_POST['amount']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');

            $debit_amount = ($transaction_type == 'expense') ? $amount : 0;
            $credit_amount = ($transaction_type == 'income') ? $amount : 0;

            $query = "UPDATE account_transactions SET
                      transaction_date = '$transaction_date',
                      transaction_type = '$transaction_type',
                      account_id = $account_id,
                      bank_account_id = $bank_account_id,
                      amount = $amount,
                      debit_amount = $debit_amount,
                      credit_amount = $credit_amount,
                      description = '$description',
                      reference_number = '$reference_number',
                      payment_method = '$payment_method',
                      category = '$category'
                      WHERE id = $transaction_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating transaction: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $query = "DELETE FROM account_transactions WHERE id = $transaction_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting transaction: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $query = "SELECT * FROM account_transactions WHERE id = $transaction_id";
            $result = mysqli_query($conn, $query);

            if ($result && mysqli_num_rows($result) > 0) {
                $transaction = mysqli_fetch_assoc($result);
                echo json_encode(['success' => true, 'data' => $transaction]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            }
            exit;
    }
}

// Handle date filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Get recent transactions
$transactions_query = "
    SELECT at.*, coa.account_name, coa.account_code, ba.account_name as bank_account_name
    FROM account_transactions at
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    WHERE at.transaction_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY at.transaction_date DESC, at.created_at DESC
    LIMIT 50
";

$transactions = mysqli_query($conn, $transactions_query);

// Get totals for the period
$totals_query = "
    SELECT 
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
        COUNT(*) as total_transactions
    FROM account_transactions
    WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
";

$totals_result = mysqli_query($conn, $totals_query);
$totals = mysqli_fetch_assoc($totals_result);

// Get accounts for dropdowns
$accounts = mysqli_query($conn, "SELECT id, account_name, account_code FROM chart_of_accounts WHERE is_active = TRUE ORDER BY account_code");
$bank_accounts = mysqli_query($conn, "SELECT id, account_name, bank_name FROM bank_accounts WHERE is_active = TRUE ORDER BY account_name");

// Get bank account balances
$bank_balances = mysqli_query($conn, "SELECT account_name, bank_name, current_balance FROM bank_accounts WHERE is_active = TRUE ORDER BY current_balance DESC");

// Get account balances
$account_balances_query = "
    SELECT coa.account_name, coa.account_code, coa.account_type, coa.current_balance,
           COALESCE(SUM(CASE WHEN at.transaction_type = 'income' THEN at.amount ELSE -at.amount END), 0) as period_change
    FROM chart_of_accounts coa
    LEFT JOIN account_transactions at ON coa.id = at.account_id
        AND at.transaction_date BETWEEN '$date_from' AND '$date_to'
    WHERE coa.is_active = TRUE
    GROUP BY coa.id, coa.account_name, coa.account_code, coa.account_type, coa.current_balance
    ORDER BY coa.account_code ASC
";

$account_balances = mysqli_query($conn, $account_balances_query);

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💰 Accounts Management</h1>
                <p class="text-muted">Manage your financial accounts and transactions</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="window.location.href='chart_of_accounts.php'">
                    <i class="bi bi-list-ul me-1"></i>Chart of Accounts
                </button>
                <button class="btn btn-outline-secondary" onclick="window.location.href='bank_accounts.php'">
                    <i class="bi bi-bank me-1"></i>Bank Accounts
                </button>
                <button class="btn btn-outline-info" onclick="window.location.href='reports.php'">
                    <i class="bi bi-graph-up me-1"></i>Reports
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                    <i class="bi bi-plus-circle me-1"></i>New Transaction
                </button>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="accounts.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-arrow-up-circle fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Income</div>
                                <div class="h4 mb-0">₹<?= number_format($totals['total_income'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-arrow-down-circle fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Expenses</div>
                                <div class="h4 mb-0">₹<?= number_format($totals['total_expenses'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-calculator fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Net Income</div>
                                <div class="h4 mb-0">₹<?= number_format(($totals['total_income'] ?? 0) - ($totals['total_expenses'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-list-check fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Transactions</div>
                                <div class="h4 mb-0"><?= number_format($totals['total_transactions'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content - Transactions List -->
            <div class="col-lg-8">
                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Recent Transactions
                            <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($transactions) ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($transactions) > 0): ?>
                                        <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></td>
                                                <td>
                                                    <?php if ($transaction['transaction_type'] == 'income'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-arrow-up me-1"></i>Income
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-arrow-down me-1"></i>Expense
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($transaction['account_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($transaction['account_code']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($transaction['description']) ?></div>
                                                    <?php if (!empty($transaction['reference_number'])): ?>
                                                        <small class="text-muted">Ref: <?= htmlspecialchars($transaction['reference_number']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="fw-medium <?= $transaction['transaction_type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                                        ₹<?= number_format($transaction['amount'], 2) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary btn-sm" 
                                                                onclick="editTransaction(<?= $transaction['id'] ?>)" 
                                                                title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                onclick="deleteTransaction(<?= $transaction['id'] ?>)" 
                                                                title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                No transactions found for the selected period
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar - Account Balances -->
            <div class="col-lg-4">
                <!-- Bank Accounts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-bank me-2"></i>Bank Account Balances
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($bank_balances) > 0): ?>
                            <?php while ($bank = mysqli_fetch_assoc($bank_balances)): ?>
                                <div class="d-flex align-items-center p-3 border-bottom">
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= htmlspecialchars($bank['account_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($bank['bank_name']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-medium">₹<?= number_format($bank['current_balance'], 2) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-bank"></i> No bank accounts found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Account Balances -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Account Balances
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($account_balances) > 0): ?>
                            <?php while ($account = mysqli_fetch_assoc($account_balances)): ?>
                                <div class="d-flex align-items-center p-3 border-bottom">
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= htmlspecialchars($account['account_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($account['account_code']) ?> - <?= htmlspecialchars($account['account_type']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-medium">₹<?= number_format($account['current_balance'], 2) ?></div>
                                        <?php if ($account['period_change'] != 0): ?>
                                            <small class="<?= $account['period_change'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $account['period_change'] > 0 ? '+' : '' ?>₹<?= number_format($account['period_change'], 2) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-list-ul"></i> No accounts found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTransactionForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date</label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account</label>
                            <select class="form-select" name="account_id" required>
                                <option value="">Select Account</option>
                                <?php mysqli_data_seek($accounts, 0); ?>
                                <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                    <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account (Optional)</label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">Select Bank Account</option>
                                <?php mysqli_data_seek($bank_accounts, 0); ?>
                                <?php while ($bank_account = mysqli_fetch_assoc($bank_accounts)): ?>
                                    <option value="<?= $bank_account['id'] ?>"><?= htmlspecialchars($bank_account['account_name'] . ' - ' . $bank_account['bank_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTransactionForm">
                <input type="hidden" name="transaction_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date</label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account</label>
                            <select class="form-select" name="account_id" required>
                                <option value="">Select Account</option>
                                <?php mysqli_data_seek($accounts, 0); ?>
                                <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                    <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account (Optional)</label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">Select Bank Account</option>
                                <?php mysqli_data_seek($bank_accounts, 0); ?>
                                <?php while ($bank_account = mysqli_fetch_assoc($bank_accounts)): ?>
                                    <option value="<?= $bank_account['id'] ?>"><?= htmlspecialchars($bank_account['account_name'] . ' - ' . $bank_account['bank_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Set current date as default for transaction forms
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('#addTransactionModal input[name="transaction_date"]').value = today;
});

// Add Transaction Form Handler
document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_transaction');
    
    fetch('accounts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the transaction.');
    });
});

// Edit Transaction Form Handler
document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_transaction');
    
    fetch('accounts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the transaction.');
    });
});

// Edit Transaction Function
function editTransaction(transactionId) {
    const formData = new FormData();
    formData.append('action', 'get_transaction');
    formData.append('transaction_id', transactionId);
    
    fetch('accounts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const transaction = data.data;
            const form = document.getElementById('editTransactionForm');
            
            form.querySelector('input[name="transaction_id"]').value = transaction.id;
            form.querySelector('input[name="transaction_date"]').value = transaction.transaction_date;
            form.querySelector('select[name="transaction_type"]').value = transaction.transaction_type;
            form.querySelector('select[name="account_id"]').value = transaction.account_id;
            form.querySelector('select[name="bank_account_id"]').value = transaction.bank_account_id || '';
            form.querySelector('input[name="amount"]').value = transaction.amount;
            form.querySelector('select[name="payment_method"]').value = transaction.payment_method;
            form.querySelector('input[name="reference_number"]').value = transaction.reference_number || '';
            form.querySelector('input[name="category"]').value = transaction.category || '';
            form.querySelector('textarea[name="description"]').value = transaction.description;
            
            const modal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
            modal.show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading the transaction data.');
    });
}

// Delete Transaction Function
function deleteTransaction(transactionId) {
    if (confirm('Are you sure you want to delete this transaction?')) {
        const formData = new FormData();
        formData.append('action', 'delete_transaction');
        formData.append('transaction_id', transactionId);
        
        fetch('accounts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the transaction.');
        });
    }
}
</script>
