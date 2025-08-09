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
                        category = '$category',
                        updated_at = CURRENT_TIMESTAMP
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
    }
}

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$account_filter = $_GET['account'] ?? '';
$type_filter = $_GET['type'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build where clause
$where_conditions = ["at.transaction_date BETWEEN '$date_from' AND '$date_to'"];
if (!empty($account_filter)) {
    $where_conditions[] = "at.account_id = " . intval($account_filter);
}
if (!empty($type_filter)) {
    $where_conditions[] = "at.transaction_type = '" . mysqli_real_escape_string($conn, $type_filter) . "'";
}
if (!empty($category_filter)) {
    $where_conditions[] = "at.category = '" . mysqli_real_escape_string($conn, $category_filter) . "'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions with account and bank information
$transactions_query = "
    SELECT at.*, 
           coa.account_name, coa.account_code, coa.account_type,
           ba.account_name as bank_account_name, ba.bank_name
    FROM account_transactions at
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    WHERE $where_clause
    ORDER BY at.transaction_date DESC, at.created_at DESC
    LIMIT 50
";

$transactions = mysqli_query($conn, $transactions_query);

// Get accounts for dropdowns
$accounts = mysqli_query($conn, "SELECT id, account_code, account_name, account_type FROM chart_of_accounts WHERE is_active = TRUE ORDER BY account_code ASC");
$bank_accounts = mysqli_query($conn, "SELECT id, account_name, bank_name FROM bank_accounts WHERE is_active = TRUE ORDER BY account_name ASC");

// Get dashboard statistics
$stats_query = "
    SELECT 
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
        COUNT(CASE WHEN transaction_type = 'income' THEN 1 END) as income_count,
        COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_count,
        COUNT(*) as total_transactions
    FROM account_transactions 
    WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Management - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .account-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .account-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .transaction-card {
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .income-card {
            border-left-color: #28a745;
        }
        .expense-card {
            border-left-color: #dc3545;
        }
        .balance-positive {
            color: #28a745;
            font-weight: 600;
        }
        .balance-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .quick-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../dashboard.php">
                <i class="fas fa-book me-2"></i>BillBook
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="accounts.php">
                    <i class="fas fa-chart-line me-1"></i>Accounts
                </a>
                <a class="nav-link" href="chart_of_accounts.php">
                    <i class="fas fa-list me-1"></i>Chart of Accounts
                </a>
                <a class="nav-link" href="bank_accounts.php">
                    <i class="fas fa-university me-1"></i>Bank Accounts
                </a>
                <a class="nav-link" href="journal_entries.php">
                    <i class="fas fa-book-open me-1"></i>Journal Entries
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Dashboard Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-chart-line me-2"></i>Accounts Dashboard</h2>
                <p class="text-muted">Manage your business finances and accounting</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                    <i class="fas fa-plus me-2"></i>New Transaction
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Total Income</h6>
                                <h3 class="mb-0">₹<?php echo number_format($stats['total_income'] ?? 0, 2); ?></h3>
                                <small>+<?php echo $stats['income_count']; ?> transactions</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-arrow-up fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Total Expenses</h6>
                                <h3 class="mb-0">₹<?php echo number_format($stats['total_expenses'] ?? 0, 2); ?></h3>
                                <small>-<?php echo $stats['expense_count']; ?> transactions</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-arrow-down fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Net Profit/Loss</h6>
                                <?php 
                                $net_amount = ($stats['total_income'] ?? 0) - ($stats['total_expenses'] ?? 0);
                                $net_class = $net_amount >= 0 ? 'text-success' : 'text-danger';
                                ?>
                                <h3 class="mb-0 <?php echo $net_class; ?>">₹<?php echo number_format($net_amount, 2); ?></h3>
                                <small><?php echo $net_amount >= 0 ? 'Profit' : 'Loss'; ?></small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Total Transactions</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_transactions'] ?? 0); ?></h3>
                                <small>This period</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exchange-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-lg-8">
                <!-- Filters -->
                <div class="card account-card mb-4">
                    <div class="card-body">
                        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Account</label>
                                <select name="account" class="form-select">
                                    <option value="">All Accounts</option>
                                    <?php
                                    mysqli_data_seek($accounts, 0);
                                    while ($account = mysqli_fetch_assoc($accounts)) {
                                        $selected = $account_filter == $account['id'] ? 'selected' : '';
                                        echo "<option value='{$account['id']}' $selected>{$account['account_code']} - {$account['account_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="income" <?php echo $type_filter == 'income' ? 'selected' : ''; ?>>Income</option>
                                    <option value="expense" <?php echo $type_filter == 'expense' ? 'selected' : ''; ?>>Expense</option>
                                    <option value="transfer" <?php echo $type_filter == 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Apply Filters
                                </button>
                                <a href="accounts.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions List -->
                <div class="card account-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Transactions</h5>
                        <small class="text-muted"><?php echo mysqli_num_rows($transactions); ?> transactions found</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Account</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($transactions) > 0): ?>
                                        <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                            <?php
                                            $amount_class = $transaction['transaction_type'] == 'income' ? 'text-success' : 'text-danger';
                                            $amount_sign = $transaction['transaction_type'] == 'income' ? '+' : '-';
                                            $type_badge = $transaction['transaction_type'] == 'income' ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($transaction['description']); ?></strong>
                                                    <?php if (!empty($transaction['reference_number'])): ?>
                                                        <br><small class="text-muted">Ref: <?php echo htmlspecialchars($transaction['reference_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo $transaction['account_code']; ?></small><br>
                                                    <?php echo htmlspecialchars($transaction['account_name']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($transaction['category'] ?: 'General'); ?></span>
                                                </td>
                                                <td class="<?php echo $amount_class; ?>">
                                                    <strong><?php echo $amount_sign; ?>₹<?php echo number_format($transaction['amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $type_badge; ?>"><?php echo ucfirst($transaction['transaction_type']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-primary" onclick="editTransaction(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No transactions found for the selected period.</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                                    <i class="fas fa-plus me-2"></i>Add First Transaction
                                                </button>
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
                <div class="card account-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-university me-2"></i>Bank Accounts</h5>
                    </div>
                    <div class="card-body">
                        <?php while ($bank = mysqli_fetch_assoc($bank_balances)): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($bank['account_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($bank['bank_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="<?php echo $bank['current_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                        ₹<?php echo number_format($bank['current_balance'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endwhile; ?>
                        
                        <div class="text-center mt-3">
                            <a href="bank_accounts.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-cog me-1"></i>Manage Bank Accounts
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Account Balances -->
                <div class="card account-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Account Balances</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php while ($account = mysqli_fetch_assoc($account_balances)): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <small class="text-muted"><?php echo $account['account_code']; ?></small><br>
                                        <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($account['account_name']); ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <span class="<?php echo $account['current_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>" style="font-size: 0.9rem;">
                                            ₹<?php echo number_format($account['current_balance'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <hr class="my-2">
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="chart_of_accounts.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list me-1"></i>View Chart of Accounts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Floating Button -->
    <div class="quick-actions">
        <div class="btn-group-vertical" role="group">
            <button class="btn btn-success rounded-pill mb-2" data-bs-toggle="modal" data-bs-target="#addTransactionModal" title="Add Transaction">
                <i class="fas fa-plus"></i>
            </button>
            <button class="btn btn-info rounded-pill mb-2" onclick="window.location.href='reports.php'" title="View Reports">
                <i class="fas fa-chart-bar"></i>
            </button>
            <button class="btn btn-primary rounded-pill" onclick="exportTransactions()" title="Export Data">
                <i class="fas fa-download"></i>
            </button>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTransactionForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_transaction">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Transaction Type *</label>
                                <select name="transaction_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                    <option value="transfer">Transfer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account *</label>
                                <select name="account_id" class="form-select" required>
                                    <option value="">Select Account</option>
                                    <?php
                                    mysqli_data_seek($accounts, 0);
                                    while ($account = mysqli_fetch_assoc($accounts)) {
                                        echo "<option value='{$account['id']}'>{$account['account_code']} - {$account['account_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Account</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">Select Bank Account</option>
                                    <?php
                                    mysqli_data_seek($bank_accounts, 0);
                                    while ($bank = mysqli_fetch_assoc($bank_accounts)) {
                                        echo "<option value='{$bank['id']}'>{$bank['account_name']} - {$bank['bank_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount *</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="upi">UPI</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g., Office Rent, Sales, etc.">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="Invoice #, Receipt #, etc.">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="3" required placeholder="Transaction description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Transaction
        document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Transaction added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the transaction');
            });
        });

        // Delete Transaction
        function deleteTransaction(transactionId) {
            if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
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
                        alert('Transaction deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the transaction');
                });
            }
        }

        // Edit Transaction (placeholder)
        function editTransaction(transactionId) {
            alert('Edit transaction functionality - to be implemented with dedicated edit modal');
        }

        // Export Transactions
        function exportTransactions() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.open(`export_transactions.php?${params.toString()}`, '_blank');
        }
    </script>
</body>
</html>
