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

$page_title = 'Bank Accounts Management';

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
                        opening_balance = $opening_balance
                      WHERE id = $account_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating bank account: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_bank_account':
            $account_id = intval($_POST['account_id']);
            $query = "UPDATE bank_accounts SET is_active = FALSE WHERE id = $account_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting bank account: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_bank_account':
            $account_id = intval($_POST['account_id']);
            $query = "SELECT * FROM bank_accounts WHERE id = $account_id";
            $result = mysqli_query($conn, $query);

            if ($result && mysqli_num_rows($result) > 0) {
                $account = mysqli_fetch_assoc($result);
                echo json_encode(['success' => true, 'data' => $account]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bank account not found']);
            }
            exit;

        case 'toggle_status':
            $account_id = intval($_POST['account_id']);
            $query = "UPDATE bank_accounts SET is_active = NOT is_active WHERE id = $account_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Account status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating account status: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get bank accounts with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$account_type_filter = $_GET['account_type'] ?? '';

$where_conditions = ['is_active = TRUE'];
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(account_name LIKE '%$search_escaped%' OR bank_name LIKE '%$search_escaped%' OR account_number LIKE '%$search_escaped%' OR ifsc_code LIKE '%$search_escaped%')";
}

if (!empty($account_type_filter)) {
    $account_type_escaped = mysqli_real_escape_string($conn, $account_type_filter);
    $where_conditions[] = "account_type = '$account_type_escaped'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get bank accounts
$accounts_query = "
    SELECT *
    FROM bank_accounts
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";

$accounts = mysqli_query($conn, $accounts_query);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM bank_accounts WHERE $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get account type summary
$type_summary = mysqli_query($conn, "
    SELECT 
        account_type,
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

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🏦 Bank Accounts Management</h1>
                <p class="text-muted">Manage your banking accounts and monitor balances</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="window.location.href='accounts.php'">
                    <i class="bi bi-arrow-left me-1"></i>Back to Accounts
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Bank Account
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-bank fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Accounts</div>
                                <div class="h4 mb-0"><?= number_format($balance_stats['total_accounts'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-wallet2 fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Balance</div>
                                <div class="h4 mb-0">₹<?= number_format($balance_stats['total_balance'] ?? 0, 2) ?></div>
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
                                <i class="bi bi-arrow-up-circle fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Positive Balance</div>
                                <div class="h4 mb-0">₹<?= number_format($balance_stats['positive_balance'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-arrow-down-circle fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Negative Balance</div>
                                <div class="h4 mb-0">₹<?= number_format($balance_stats['negative_balance'] ?? 0, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content - Bank Accounts List -->
            <div class="col-lg-9">
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Account name, bank, number, IFSC..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Account Type</label>
                                <select class="form-select" name="account_type">
                                    <option value="">All Types</option>
                                    <option value="savings" <?= $account_type_filter == 'savings' ? 'selected' : '' ?>>Savings</option>
                                    <option value="current" <?= $account_type_filter == 'current' ? 'selected' : '' ?>>Current</option>
                                    <option value="overdraft" <?= $account_type_filter == 'overdraft' ? 'selected' : '' ?>>Overdraft</option>
                                    <option value="fixed_deposit" <?= $account_type_filter == 'fixed_deposit' ? 'selected' : '' ?>>Fixed Deposit</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <a href="bank_accounts.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bank Accounts Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Bank Accounts
                            <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($accounts) ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Account Details</th>
                                        <th>Bank Info</th>
                                        <th>Type</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($accounts) > 0): ?>
                                        <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($account['account_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($account['account_number']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($account['bank_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($account['branch_name']) ?></small>
                                                    <div><small class="text-muted">IFSC: <?= htmlspecialchars($account['ifsc_code']) ?></small></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= ucwords(str_replace('_', ' ', $account['account_type'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-medium <?= $account['current_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        ₹<?= number_format($account['current_balance'], 2) ?>
                                                    </div>
                                                    <small class="text-muted">Opening: ₹<?= number_format($account['opening_balance'], 2) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($account['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary btn-sm" 
                                                                onclick="editBankAccount(<?= $account['id'] ?>)" 
                                                                title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-outline-<?= $account['is_active'] ? 'warning' : 'success' ?> btn-sm" 
                                                                onclick="toggleStatus(<?= $account['id'] ?>)" 
                                                                title="<?= $account['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                            <i class="bi bi-<?= $account['is_active'] ? 'pause' : 'play' ?>"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                onclick="deleteBankAccount(<?= $account['id'] ?>)" 
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
                                                No bank accounts found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&account_type=<?= urlencode($account_type_filter) ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&account_type=<?= urlencode($account_type_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&account_type=<?= urlencode($account_type_filter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

            <!-- Sidebar - Account Type Summary -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-pie-chart me-2"></i>Account Type Summary
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($type_summary) > 0): ?>
                            <?php while ($type = mysqli_fetch_assoc($type_summary)): ?>
                                <div class="d-flex align-items-center p-3 border-bottom">
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= ucwords(str_replace('_', ' ', $type['account_type'])) ?></div>
                                        <small class="text-muted"><?= $type['count'] ?> accounts</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-medium">₹<?= number_format($type['total_balance'], 2) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-pie-chart"></i> No data available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Bank Account Modal -->
<div class="modal fade" id="addBankAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBankAccountForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control" name="account_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="account_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Type</label>
                            <select class="form-select" name="account_type" required>
                                <option value="">Select Type</option>
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                                <option value="overdraft">Overdraft</option>
                                <option value="fixed_deposit">Fixed Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" name="ifsc_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="branch_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bank Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bank Account Modal -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBankAccountForm">
                <input type="hidden" name="account_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control" name="account_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="account_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Type</label>
                            <select class="form-select" name="account_type" required>
                                <option value="">Select Type</option>
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                                <option value="overdraft">Overdraft</option>
                                <option value="fixed_deposit">Fixed Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" name="ifsc_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="branch_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Bank Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Add Bank Account Form Handler
document.getElementById('addBankAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_bank_account');
    
    fetch('bank_accounts.php', {
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
        alert('An error occurred while adding the bank account.');
    });
});

// Edit Bank Account Form Handler
document.getElementById('editBankAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_bank_account');
    
    fetch('bank_accounts.php', {
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
        alert('An error occurred while updating the bank account.');
    });
});

// Edit Bank Account Function
function editBankAccount(accountId) {
    const formData = new FormData();
    formData.append('action', 'get_bank_account');
    formData.append('account_id', accountId);
    
    fetch('bank_accounts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const account = data.data;
            const form = document.getElementById('editBankAccountForm');
            
            form.querySelector('input[name="account_id"]').value = account.id;
            form.querySelector('input[name="account_name"]').value = account.account_name;
            form.querySelector('input[name="bank_name"]').value = account.bank_name;
            form.querySelector('input[name="account_number"]').value = account.account_number;
            form.querySelector('select[name="account_type"]').value = account.account_type;
            form.querySelector('input[name="ifsc_code"]').value = account.ifsc_code;
            form.querySelector('input[name="branch_name"]').value = account.branch_name;
            form.querySelector('input[name="opening_balance"]').value = account.opening_balance;
            
            const modal = new bootstrap.Modal(document.getElementById('editBankAccountModal'));
            modal.show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading the bank account data.');
    });
}

// Toggle Status Function
function toggleStatus(accountId) {
    if (confirm('Are you sure you want to change the status of this account?')) {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('account_id', accountId);
        
        fetch('bank_accounts.php', {
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
            alert('An error occurred while updating the account status.');
        });
    }
}

// Delete Bank Account Function
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
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the bank account.');
        });
    }
}
</script>
