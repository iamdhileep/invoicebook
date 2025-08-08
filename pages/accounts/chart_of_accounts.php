<?php
/**
 * Chart of Accounts Management
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
        case 'add_account':
            $account_code = mysqli_real_escape_string($conn, $_POST['account_code']);
            $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
            $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
            $account_subtype = mysqli_real_escape_string($conn, $_POST['account_subtype'] ?? '');
            $parent_account_id = !empty($_POST['parent_account_id']) ? intval($_POST['parent_account_id']) : 'NULL';
            $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
            $balance_type = mysqli_real_escape_string($conn, $_POST['balance_type']);
            $opening_balance = floatval($_POST['opening_balance'] ?? 0);
            
            $query = "INSERT INTO chart_of_accounts 
                      (account_code, account_name, account_type, account_subtype, parent_account_id, description, balance_type, opening_balance, current_balance) 
                      VALUES 
                      ('$account_code', '$account_name', '$account_type', '$account_subtype', $parent_account_id, '$description', '$balance_type', $opening_balance, $opening_balance)";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Account added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_account':
            $account_id = intval($_POST['account_id']);
            $account_code = mysqli_real_escape_string($conn, $_POST['account_code']);
            $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
            $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
            $account_subtype = mysqli_real_escape_string($conn, $_POST['account_subtype'] ?? '');
            $parent_account_id = !empty($_POST['parent_account_id']) ? intval($_POST['parent_account_id']) : 'NULL';
            $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
            $balance_type = mysqli_real_escape_string($conn, $_POST['balance_type']);
            $opening_balance = floatval($_POST['opening_balance'] ?? 0);
            
            $query = "UPDATE chart_of_accounts SET 
                        account_code = '$account_code',
                        account_name = '$account_name',
                        account_type = '$account_type',
                        account_subtype = '$account_subtype',
                        parent_account_id = $parent_account_id,
                        description = '$description',
                        balance_type = '$balance_type',
                        opening_balance = $opening_balance,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_account':
            $account_id = intval($_POST['account_id']);
            
            // Check if account has transactions
            $check_transactions = mysqli_query($conn, "SELECT COUNT(*) as count FROM account_transactions WHERE account_id = $account_id");
            $transaction_count = mysqli_fetch_assoc($check_transactions)['count'];
            
            if ($transaction_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete account with existing transactions. Archive it instead.']);
                exit;
            }
            
            $query = "DELETE FROM chart_of_accounts WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting account: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'toggle_status':
            $account_id = intval($_POST['account_id']);
            $is_active = intval($_POST['is_active']);
            
            $query = "UPDATE chart_of_accounts SET is_active = $is_active WHERE id = $account_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Account status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating account status: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get chart of accounts with parent relationships
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(coa.account_code LIKE '%$search%' OR coa.account_name LIKE '%$search%' OR coa.description LIKE '%$search%')";
}
if (!empty($type_filter)) {
    $type_filter = mysqli_real_escape_string($conn, $type_filter);
    $where_conditions[] = "coa.account_type = '$type_filter'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$accounts_query = "
    SELECT coa.*, parent.account_name as parent_account_name,
           (SELECT COUNT(*) FROM account_transactions at WHERE at.account_id = coa.id) as transaction_count
    FROM chart_of_accounts coa
    LEFT JOIN chart_of_accounts parent ON coa.parent_account_id = parent.id
    $where_clause
    ORDER BY coa.account_code ASC
";

$accounts = mysqli_query($conn, $accounts_query);

// Get parent accounts for dropdown
$parent_accounts = mysqli_query($conn, "SELECT id, account_code, account_name FROM chart_of_accounts WHERE parent_account_id IS NULL ORDER BY account_code ASC");

// Get account type statistics
$type_stats = mysqli_query($conn, "
    SELECT account_type, 
           COUNT(*) as count,
           SUM(current_balance) as total_balance
    FROM chart_of_accounts 
    WHERE is_active = TRUE
    GROUP BY account_type
    ORDER BY account_type
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart of Accounts - BillBook</title>
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
        .account-type-asset { border-left: 4px solid #28a745; }
        .account-type-liability { border-left: 4px solid #dc3545; }
        .account-type-equity { border-left: 4px solid #6f42c1; }
        .account-type-revenue { border-left: 4px solid #20c997; }
        .account-type-expense { border-left: 4px solid #fd7e14; }
        
        .balance-positive { color: #28a745; font-weight: 600; }
        .balance-negative { color: #dc3545; font-weight: 600; }
        .balance-zero { color: #6c757d; font-weight: 500; }
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
                <a class="nav-link active" href="chart_of_accounts.php">
                    <i class="fas fa-list me-1"></i>Chart of Accounts
                </a>
                <a class="nav-link" href="bank_accounts.php">
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
                <h2><i class="fas fa-list me-2"></i>Chart of Accounts</h2>
                <p class="text-muted">Manage your accounting structure and account hierarchy</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                    <i class="fas fa-plus me-2"></i>Add Account
                </button>
            </div>
        </div>

        <!-- Account Type Statistics -->
        <div class="row mb-4">
            <?php while ($stat = mysqli_fetch_assoc($type_stats)): ?>
                <?php
                $type_colors = [
                    'asset' => 'bg-success',
                    'liability' => 'bg-danger',
                    'equity' => 'bg-primary',
                    'revenue' => 'bg-info',
                    'expense' => 'bg-warning'
                ];
                $bg_class = $type_colors[$stat['account_type']] ?? 'bg-secondary';
                ?>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card <?php echo $bg_class; ?> text-white">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?php echo $stat['count']; ?></h4>
                            <h6 class="text-light"><?php echo ucfirst($stat['account_type']); ?> Accounts</h6>
                            <small>₹<?php echo number_format($stat['total_balance'], 2); ?></small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Search and Filters -->
        <div class="card account-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search Accounts</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by code, name, or description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Account Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="asset" <?php echo $type_filter == 'asset' ? 'selected' : ''; ?>>Assets</option>
                            <option value="liability" <?php echo $type_filter == 'liability' ? 'selected' : ''; ?>>Liabilities</option>
                            <option value="equity" <?php echo $type_filter == 'equity' ? 'selected' : ''; ?>>Equity</option>
                            <option value="revenue" <?php echo $type_filter == 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                            <option value="expense" <?php echo $type_filter == 'expense' ? 'selected' : ''; ?>>Expenses</option>
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

        <!-- Accounts Table -->
        <div class="card account-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Accounts List</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Account Name</th>
                                <th>Type</th>
                                <th>Parent Account</th>
                                <th>Balance Type</th>
                                <th>Current Balance</th>
                                <th>Transactions</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($accounts) > 0): ?>
                                <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                    <?php
                                    $balance_class = 'balance-zero';
                                    if ($account['current_balance'] > 0) $balance_class = 'balance-positive';
                                    if ($account['current_balance'] < 0) $balance_class = 'balance-negative';
                                    
                                    $type_badge_class = [
                                        'asset' => 'bg-success',
                                        'liability' => 'bg-danger',
                                        'equity' => 'bg-primary',
                                        'revenue' => 'bg-info',
                                        'expense' => 'bg-warning text-dark'
                                    ][$account['account_type']] ?? 'bg-secondary';
                                    ?>
                                    <tr class="account-type-<?php echo $account['account_type']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($account['account_code']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($account['account_name']); ?></strong>
                                            <?php if (!empty($account['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($account['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $type_badge_class; ?>">
                                                <?php echo ucfirst($account['account_type']); ?>
                                            </span>
                                            <?php if (!empty($account['account_subtype'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($account['account_subtype']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($account['parent_account_name']): ?>
                                                <small><?php echo htmlspecialchars($account['parent_account_name']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Root Account</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $account['balance_type'] == 'debit' ? 'bg-info' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($account['balance_type']); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo $balance_class; ?>">
                                            ₹<?php echo number_format($account['current_balance'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $account['transaction_count']; ?> transactions
                                            </span>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       <?php echo $account['is_active'] ? 'checked' : ''; ?>
                                                       onchange="toggleAccountStatus(<?php echo $account['id']; ?>, this.checked)">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary" onclick="editAccount(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="viewAccountDetails(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($account['transaction_count'] == 0): ?>
                                                <button class="btn btn-outline-danger" onclick="deleteAccount(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-list fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No accounts found matching your criteria.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Account Modal -->
    <div class="modal fade" id="addAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAccountForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_account">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Code *</label>
                                <input type="text" name="account_code" class="form-control" required placeholder="e.g., 1001, 2001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" name="account_name" class="form-control" required placeholder="e.g., Cash, Accounts Receivable">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type *</label>
                                <select name="account_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="asset">Asset</option>
                                    <option value="liability">Liability</option>
                                    <option value="equity">Equity</option>
                                    <option value="revenue">Revenue</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Subtype</label>
                                <input type="text" name="account_subtype" class="form-control" placeholder="e.g., current_asset, fixed_asset">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Parent Account</label>
                                <select name="parent_account_id" class="form-select">
                                    <option value="">No Parent (Root Account)</option>
                                    <?php
                                    mysqli_data_seek($parent_accounts, 0);
                                    while ($parent = mysqli_fetch_assoc($parent_accounts)) {
                                        echo "<option value='{$parent['id']}'>{$parent['account_code']} - {$parent['account_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Normal Balance Type *</label>
                                <select name="balance_type" class="form-select" required>
                                    <option value="">Select Balance Type</option>
                                    <option value="debit">Debit</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" name="opening_balance" class="form-control" step="0.01" value="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this account..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Account
        document.getElementById('addAccountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('chart_of_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Account added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the account');
            });
        });

        // Toggle Account Status
        function toggleAccountStatus(accountId, isActive) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('account_id', accountId);
            formData.append('is_active', isActive ? 1 : 0);
            
            fetch('chart_of_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Status updated successfully
                } else {
                    alert('Error: ' + data.message);
                    // Revert checkbox state
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the account status');
                location.reload();
            });
        }

        // Delete Account
        function deleteAccount(accountId) {
            if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_account');
                formData.append('account_id', accountId);
                
                fetch('chart_of_accounts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Account deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the account');
                });
            }
        }

        // Edit Account (placeholder)
        function editAccount(accountId) {
            alert('Edit account functionality - to be implemented with dedicated edit modal');
        }

        // View Account Details
        function viewAccountDetails(accountId) {
            window.open(`account_details.php?id=${accountId}`, '_blank');
        }

        // Auto-suggest balance type based on account type
        document.querySelector('select[name="account_type"]').addEventListener('change', function() {
            const balanceTypeSelect = document.querySelector('select[name="balance_type"]');
            const accountType = this.value;
            
            // Clear current selection
            balanceTypeSelect.value = '';
            
            // Suggest balance type based on account type
            const suggestions = {
                'asset': 'debit',
                'expense': 'debit',
                'liability': 'credit',
                'equity': 'credit',
                'revenue': 'credit'
            };
            
            if (suggestions[accountType]) {
                balanceTypeSelect.value = suggestions[accountType];
            }
        });
    </script>
</body>
</html>
