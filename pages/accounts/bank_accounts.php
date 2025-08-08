<?php
/**
 * Bank Accounts Management
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
        case 'add_bank_account':
            $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
            $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
            $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
            $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
            $opening_balance = floatval($_POST['opening_balance']);
            
            $query = "INSERT INTO bank_accounts 
                      (account_name, bank_name, account_number, account_type, ifsc_code, branch_name, opening_balance, current_balance) 
                      VALUES 
                      ('$account_name', '$bank_name', '$account_number', '$account_type', '$ifsc_code', '$branch_name', $opening_balance, $opening_balance)";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding bank account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_bank_account':
            $account_id = intval($_POST['account_id']);
            $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
            $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
            $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
            $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
            $opening_balance = floatval($_POST['opening_balance']);
            
            $query = "UPDATE bank_accounts SET 
                        account_name = '$account_name',
                        bank_name = '$bank_name',
                        account_number = '$account_number',
                        account_type = '$account_type',
                        ifsc_code = '$ifsc_code',
                        branch_name = '$branch_name',
                        opening_balance = $opening_balance,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating bank account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_bank_account':
            $account_id = intval($_POST['account_id']);
            
            // Check if bank account has transactions
            $check_transactions = mysqli_query($conn, "SELECT COUNT(*) as count FROM account_transactions WHERE bank_account_id = $account_id");
            $transaction_count = mysqli_fetch_assoc($check_transactions)['count'];
            
            if ($transaction_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete bank account with existing transactions. Deactivate it instead.']);
                exit;
            }
            
            $query = "DELETE FROM bank_accounts WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting bank account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'toggle_status':
            $account_id = intval($_POST['account_id']);
            $is_active = intval($_POST['is_active']);
            
            $query = "UPDATE bank_accounts SET is_active = $is_active WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating bank account status: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get bank accounts with transaction statistics
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(ba.account_name LIKE '%$search%' OR ba.bank_name LIKE '%$search%' OR ba.account_number LIKE '%$search%')";
}
if (!empty($type_filter)) {
    $type_filter = mysqli_real_escape_string($conn, $type_filter);
    $where_conditions[] = "ba.account_type = '$type_filter'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$bank_accounts_query = "
    SELECT ba.*,
           (SELECT COUNT(*) FROM account_transactions at WHERE at.bank_account_id = ba.id) as transaction_count,
           (SELECT SUM(amount) FROM account_transactions at WHERE at.bank_account_id = ba.id AND at.transaction_type = 'income') as total_deposits,
           (SELECT SUM(amount) FROM account_transactions at WHERE at.bank_account_id = ba.id AND at.transaction_type = 'expense') as total_withdrawals
    FROM bank_accounts ba
    $where_clause
    ORDER BY ba.created_at DESC
";

$bank_accounts = mysqli_query($conn, $bank_accounts_query);

// Get account type statistics
$type_stats = mysqli_query($conn, "
    SELECT account_type, 
           COUNT(*) as count,
           SUM(current_balance) as total_balance
    FROM bank_accounts 
    WHERE is_active = TRUE
    GROUP BY account_type
    ORDER BY account_type
");

// Get total balances
$total_balance_query = "
    SELECT 
        SUM(current_balance) as total_balance,
        COUNT(*) as total_accounts,
        SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END) as positive_balance,
        SUM(CASE WHEN current_balance < 0 THEN ABS(current_balance) ELSE 0 END) as negative_balance
    FROM bank_accounts 
    WHERE is_active = TRUE
";

$total_balance_result = mysqli_query($conn, $total_balance_query);
$balance_stats = mysqli_fetch_assoc($total_balance_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Accounts - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .bank-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .bank-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .account-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .balance-positive { color: #28a745; font-weight: 600; }
        .balance-negative { color: #dc3545; font-weight: 600; }
        .balance-zero { color: #6c757d; font-weight: 500; }
        
        .account-type-savings { border-left: 5px solid #28a745; }
        .account-type-current { border-left: 5px solid #007bff; }
        .account-type-fixed_deposit { border-left: 5px solid #ffc107; }
        .account-type-credit_card { border-left: 5px solid #dc3545; }
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
                <a class="nav-link" href="accounts.php">
                    <i class="fas fa-chart-line me-1"></i>Accounts
                </a>
                <a class="nav-link" href="chart_of_accounts.php">
                    <i class="fas fa-list me-1"></i>Chart of Accounts
                </a>
                <a class="nav-link active" href="bank_accounts.php">
                    <i class="fas fa-university me-1"></i>Bank Accounts
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-university me-2"></i>Bank Accounts</h2>
                <p class="text-muted">Manage your bank accounts and financial institutions</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">
                    <i class="fas fa-plus me-2"></i>Add Bank Account
                </button>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card account-card text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-university fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo $balance_stats['total_accounts']; ?></h3>
                        <h6 class="text-light">Total Accounts</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card account-card text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-coins fa-2x mb-2"></i>
                        <h3 class="mb-1">₹<?php echo number_format($balance_stats['total_balance'], 2); ?></h3>
                        <h6 class="text-light">Net Balance</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card account-card text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-up fa-2x mb-2"></i>
                        <h3 class="mb-1">₹<?php echo number_format($balance_stats['positive_balance'], 2); ?></h3>
                        <h6 class="text-light">Positive Balances</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card account-card text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-down fa-2x mb-2"></i>
                        <h3 class="mb-1">₹<?php echo number_format($balance_stats['negative_balance'], 2); ?></h3>
                        <h6 class="text-light">Negative Balances</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Type Statistics -->
        <div class="row mb-4">
            <?php while ($stat = mysqli_fetch_assoc($type_stats)): ?>
                <?php
                $type_colors = [
                    'savings' => 'bg-success',
                    'current' => 'bg-primary', 
                    'fixed_deposit' => 'bg-warning',
                    'credit_card' => 'bg-danger'
                ];
                $bg_class = $type_colors[$stat['account_type']] ?? 'bg-secondary';
                $text_class = $stat['account_type'] == 'fixed_deposit' ? 'text-dark' : 'text-white';
                ?>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card <?php echo $bg_class; ?> <?php echo $text_class; ?>">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?php echo $stat['count']; ?></h4>
                            <h6><?php echo ucwords(str_replace('_', ' ', $stat['account_type'])); ?></h6>
                            <small>₹<?php echo number_format($stat['total_balance'], 2); ?></small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Search and Filters -->
        <div class="card bank-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search Bank Accounts</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by account name, bank, or account number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Account Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="savings" <?php echo $type_filter == 'savings' ? 'selected' : ''; ?>>Savings</option>
                            <option value="current" <?php echo $type_filter == 'current' ? 'selected' : ''; ?>>Current</option>
                            <option value="fixed_deposit" <?php echo $type_filter == 'fixed_deposit' ? 'selected' : ''; ?>>Fixed Deposit</option>
                            <option value="credit_card" <?php echo $type_filter == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bank Accounts List -->
        <div class="row">
            <?php if (mysqli_num_rows($bank_accounts) > 0): ?>
                <?php while ($bank = mysqli_fetch_assoc($bank_accounts)): ?>
                    <?php
                    $balance_class = 'balance-zero';
                    if ($bank['current_balance'] > 0) $balance_class = 'balance-positive';
                    if ($bank['current_balance'] < 0) $balance_class = 'balance-negative';
                    
                    $type_icon = [
                        'savings' => 'fa-piggy-bank',
                        'current' => 'fa-building-columns',
                        'fixed_deposit' => 'fa-certificate',
                        'credit_card' => 'fa-credit-card'
                    ][$bank['account_type']] ?? 'fa-university';
                    ?>
                    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                        <div class="card bank-card h-100 account-type-<?php echo $bank['account_type']; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas <?php echo $type_icon; ?> me-2"></i>
                                    <strong><?php echo htmlspecialchars($bank['account_name']); ?></strong>
                                </h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           <?php echo $bank['is_active'] ? 'checked' : ''; ?>
                                           onchange="toggleBankAccountStatus(<?php echo $bank['id']; ?>, this.checked)">
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h4 class="mb-0 <?php echo $balance_class; ?>">₹<?php echo number_format($bank['current_balance'], 2); ?></h4>
                                        <small class="text-muted">Current Balance</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?php echo ucwords(str_replace('_', ' ', $bank['account_type'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row text-sm">
                                        <div class="col-6">
                                            <small class="text-muted">Bank:</small><br>
                                            <strong><?php echo htmlspecialchars($bank['bank_name']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Account No:</small><br>
                                            <strong><?php echo substr($bank['account_number'], 0, 4) . '****' . substr($bank['account_number'], -4); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($bank['ifsc_code'])): ?>
                                <div class="mb-3">
                                    <div class="row text-sm">
                                        <div class="col-6">
                                            <small class="text-muted">IFSC:</small><br>
                                            <strong><?php echo htmlspecialchars($bank['ifsc_code']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Branch:</small><br>
                                            <small><?php echo htmlspecialchars($bank['branch_name']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row text-center border-top pt-2">
                                    <div class="col-4">
                                        <small class="text-muted">Transactions</small><br>
                                        <strong><?php echo $bank['transaction_count']; ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Deposits</small><br>
                                        <strong class="text-success">₹<?php echo number_format($bank['total_deposits'] ?? 0, 0); ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Withdrawals</small><br>
                                        <strong class="text-danger">₹<?php echo number_format($bank['total_withdrawals'] ?? 0, 0); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewBankAccountDetails(<?php echo $bank['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editBankAccount(<?php echo $bank['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewTransactions(<?php echo $bank['id']; ?>)">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <?php if ($bank['transaction_count'] == 0): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteBankAccount(<?php echo $bank['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card bank-card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-university fa-3x text-muted mb-3"></i>
                            <h4>No Bank Accounts Found</h4>
                            <p class="text-muted">Add your first bank account to get started with financial management.</p>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">
                                <i class="fas fa-plus me-2"></i>Add Bank Account
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div class="modal fade" id="addBankAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addBankAccountForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_bank_account">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" name="account_name" class="form-control" required placeholder="e.g., Main Business Account">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name *</label>
                                <input type="text" name="bank_name" class="form-control" required placeholder="e.g., State Bank of India">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number *</label>
                                <input type="text" name="account_number" class="form-control" required placeholder="Account Number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type *</label>
                                <select name="account_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="savings">Savings Account</option>
                                    <option value="current">Current Account</option>
                                    <option value="fixed_deposit">Fixed Deposit</option>
                                    <option value="credit_card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control" placeholder="e.g., SBIN0001234">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch Name</label>
                                <input type="text" name="branch_name" class="form-control" placeholder="e.g., Mumbai Main Branch">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" name="opening_balance" class="form-control" step="0.01" value="0.00" placeholder="0.00">
                            <small class="form-text text-muted">Enter the current balance in this account</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Add Bank Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Bank Account
        document.getElementById('addBankAccountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('bank_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bank account added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the bank account');
            });
        });

        // Toggle Bank Account Status
        function toggleBankAccountStatus(accountId, isActive) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('account_id', accountId);
            formData.append('is_active', isActive ? 1 : 0);
            
            fetch('bank_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Status updated successfully
                } else {
                    alert('Error: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the account status');
                location.reload();
            });
        }

        // Delete Bank Account
        function deleteBankAccount(accountId) {
            if (confirm('Are you sure you want to delete this bank account? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_bank_account');
                formData.append('account_id', accountId);
                
                fetch('bank_accounts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Bank account deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the bank account');
                });
            }
        }

        // View Bank Account Details
        function viewBankAccountDetails(accountId) {
            alert('View bank account details - to be implemented');
        }

        // Edit Bank Account
        function editBankAccount(accountId) {
            alert('Edit bank account functionality - to be implemented with dedicated edit modal');
        }

        // View Transactions
        function viewTransactions(accountId) {
            window.open(`accounts.php?bank_account=${accountId}`, '_blank');
        }
    </script>
</body>
</html>
