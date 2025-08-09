<?php
/**
 * Transactions Management System
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

$page_title = 'Transaction Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_transaction':
            $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
            $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
            $account_id = intval($_POST['account_id']);
            $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : 'NULL';
            $reference_type = mysqli_real_escape_string($conn, $_POST['reference_type'] ?? 'manual');
            $reference_id = !empty($_POST['reference_id']) ? intval($_POST['reference_id']) : 'NULL';
            $amount = floatval($_POST['amount']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
            $tags = mysqli_real_escape_string($conn, $_POST['tags'] ?? '');
            $approval_status = mysqli_real_escape_string($conn, $_POST['approval_status'] ?? 'approved');

            $debit_amount = ($transaction_type == 'expense') ? $amount : 0;
            $credit_amount = ($transaction_type == 'income') ? $amount : 0;

            $query = "INSERT INTO account_transactions
                      (transaction_date, transaction_type, account_id, bank_account_id, reference_type, reference_id,
                       amount, debit_amount, credit_amount, description, reference_number, payment_method, 
                       category, tags, approval_status, created_by, created_at)
                      VALUES 
                      ('$transaction_date', '$transaction_type', $account_id, $bank_account_id, '$reference_type', $reference_id,
                       $amount, $debit_amount, $credit_amount, '$description', '$reference_number', '$payment_method', 
                       '$category', '$tags', '$approval_status', " . ($_SESSION['admin_id'] ?? 1) . ", NOW())";

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
            $reference_type = mysqli_real_escape_string($conn, $_POST['reference_type'] ?? 'manual');
            $reference_id = !empty($_POST['reference_id']) ? intval($_POST['reference_id']) : 'NULL';
            $amount = floatval($_POST['amount']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
            $tags = mysqli_real_escape_string($conn, $_POST['tags'] ?? '');

            $debit_amount = ($transaction_type == 'expense') ? $amount : 0;
            $credit_amount = ($transaction_type == 'income') ? $amount : 0;

            $query = "UPDATE account_transactions SET
                      transaction_date = '$transaction_date',
                      transaction_type = '$transaction_type',
                      account_id = $account_id,
                      bank_account_id = $bank_account_id,
                      reference_type = '$reference_type',
                      reference_id = $reference_id,
                      amount = $amount,
                      debit_amount = $debit_amount,
                      credit_amount = $credit_amount,
                      description = '$description',
                      reference_number = '$reference_number',
                      payment_method = '$payment_method',
                      category = '$category',
                      tags = '$tags',
                      updated_at = NOW()
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

        case 'approve_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $query = "UPDATE account_transactions SET 
                      approval_status = 'approved', 
                      approved_by = " . ($_SESSION['admin_id'] ?? 1) . ",
                      updated_at = NOW()
                      WHERE id = $transaction_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Transaction approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error approving transaction: ' . mysqli_error($conn)]);
            }
            exit;

        case 'reject_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $query = "UPDATE account_transactions SET 
                      approval_status = 'rejected', 
                      approved_by = " . ($_SESSION['admin_id'] ?? 1) . ",
                      updated_at = NOW()
                      WHERE id = $transaction_id";

            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error rejecting transaction: ' . mysqli_error($conn)]);
            }
            exit;

        case 'bulk_approve':
            $transaction_ids = $_POST['transaction_ids'] ?? [];
            if (!empty($transaction_ids)) {
                $ids = implode(',', array_map('intval', $transaction_ids));
                $query = "UPDATE account_transactions SET 
                          approval_status = 'approved', 
                          approved_by = " . ($_SESSION['admin_id'] ?? 1) . ",
                          updated_at = NOW()
                          WHERE id IN ($ids)";

                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Transactions approved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error approving transactions: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No transactions selected']);
            }
            exit;

        case 'bulk_delete':
            $transaction_ids = $_POST['transaction_ids'] ?? [];
            if (!empty($transaction_ids)) {
                $ids = implode(',', array_map('intval', $transaction_ids));
                $query = "DELETE FROM account_transactions WHERE id IN ($ids)";

                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Transactions deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting transactions: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No transactions selected']);
            }
            exit;

        case 'export_transactions':
            $date_from = $_POST['date_from'] ?? date('Y-m-01');
            $date_to = $_POST['date_to'] ?? date('Y-m-t');
            $format = $_POST['format'] ?? 'csv';
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Export initiated', 'redirect' => "export.php?from=$date_from&to=$date_to&format=$format"]);
            exit;
    }
}

// Handle filters and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$search = $_GET['search'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$approval_status = $_GET['approval_status'] ?? '';
$account_id = $_GET['account_id'] ?? '';
$bank_account_id = $_GET['bank_account_id'] ?? '';

$where_conditions = ["at.transaction_date BETWEEN '$date_from' AND '$date_to'"];

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(at.description LIKE '%$search_escaped%' OR at.reference_number LIKE '%$search_escaped%' OR at.category LIKE '%$search_escaped%' OR coa.account_name LIKE '%$search_escaped%')";
}

if (!empty($transaction_type)) {
    $where_conditions[] = "at.transaction_type = '$transaction_type'";
}

if (!empty($payment_method)) {
    $where_conditions[] = "at.payment_method = '$payment_method'";
}

if (!empty($approval_status)) {
    $where_conditions[] = "at.approval_status = '$approval_status'";
}

if (!empty($account_id)) {
    $where_conditions[] = "at.account_id = $account_id";
}

if (!empty($bank_account_id)) {
    $where_conditions[] = "at.bank_account_id = $bank_account_id";
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions
$transactions_query = "
    SELECT at.*, 
           coa.account_name, coa.account_code, 
           ba.account_name as bank_account_name,
           creator.username as created_by_name,
           approver.username as approved_by_name
    FROM account_transactions at
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    LEFT JOIN admin creator ON at.created_by = creator.id
    LEFT JOIN admin approver ON at.approved_by = approver.id
    WHERE $where_clause
    ORDER BY at.transaction_date DESC, at.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$transactions = mysqli_query($conn, $transactions_query);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM account_transactions at 
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    WHERE $where_clause
";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM account_transactions at
    LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
    LEFT JOIN bank_accounts ba ON at.bank_account_id = ba.id
    WHERE $where_clause
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get dropdown options
$accounts = mysqli_query($conn, "SELECT id, account_name, account_code FROM chart_of_accounts WHERE is_active = TRUE ORDER BY account_code");
$bank_accounts = mysqli_query($conn, "SELECT id, account_name, bank_name FROM bank_accounts WHERE is_active = TRUE ORDER BY account_name");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">💳 Transaction Management</h1>
                <p class="text-muted">Manage and monitor all financial transactions</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="showReportsModal()">
                    <i class="bi bi-graph-up me-1"></i>Reports
                </button>
                <button class="btn btn-outline-secondary" onclick="showExportModal()">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                    <i class="bi bi-plus-circle me-1"></i>New Transaction
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
                                <i class="bi bi-list-check fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Transactions</div>
                                <div class="h4 mb-0"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
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
                                <i class="bi bi-arrow-up-circle fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Total Income</div>
                                <div class="h4 mb-0">₹<?= number_format($stats['total_income'] ?? 0, 2) ?></div>
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
                                <div class="h4 mb-0">₹<?= number_format($stats['total_expenses'] ?? 0, 2) ?></div>
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
                                <i class="bi bi-clock fs-2"></i>
                            </div>
                            <div class="ms-3">
                                <div class="text-white-50 small">Pending Approvals</div>
                                <div class="h4 mb-0"><?= number_format($stats['pending_approvals'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Description, ref no..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="transaction_type">
                            <option value="">All Types</option>
                            <option value="income" <?= $transaction_type == 'income' ? 'selected' : '' ?>>Income</option>
                            <option value="expense" <?= $transaction_type == 'expense' ? 'selected' : '' ?>>Expense</option>
                            <option value="transfer" <?= $transaction_type == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            <option value="adjustment" <?= $transaction_type == 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="approval_status">
                            <option value="">All Status</option>
                            <option value="pending" <?= $approval_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $approval_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $approval_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="transactions.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">
                            Select All
                        </label>
                    </div>
                    <div class="d-none" id="bulkActions">
                        <button class="btn btn-sm btn-success" onclick="bulkApprove()">
                            <i class="bi bi-check-circle me-1"></i>Approve Selected
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                            <i class="bi bi-trash me-1"></i>Delete Selected
                        </button>
                        <span class="text-muted ms-2">
                            <span id="selectedCount">0</span> selected
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>Transactions
                    <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($transactions) ?> of <?= number_format($total_records) ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAllHeader">
                                </th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($transactions) > 0): ?>
                                <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="transaction-checkbox" value="<?= $transaction['id'] ?>">
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></div>
                                            <small class="text-muted"><?= date('h:i A', strtotime($transaction['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $type_colors = [
                                                'income' => 'success',
                                                'expense' => 'danger', 
                                                'transfer' => 'info',
                                                'adjustment' => 'warning'
                                            ];
                                            $color = $type_colors[$transaction['transaction_type']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>">
                                                <i class="bi bi-<?= $transaction['transaction_type'] == 'income' ? 'arrow-up' : 'arrow-down' ?> me-1"></i>
                                                <?= ucfirst($transaction['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($transaction['account_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($transaction['account_code']) ?></small>
                                            <?php if (!empty($transaction['bank_account_name'])): ?>
                                                <div><small class="text-info">Bank: <?= htmlspecialchars($transaction['bank_account_name']) ?></small></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($transaction['description']) ?></div>
                                            <?php if (!empty($transaction['reference_number'])): ?>
                                                <small class="text-muted">Ref: <?= htmlspecialchars($transaction['reference_number']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($transaction['category'])): ?>
                                                <div><small class="badge bg-light text-dark"><?= htmlspecialchars($transaction['category']) ?></small></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-medium <?= $transaction['transaction_type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                                <?= $transaction['transaction_type'] == 'income' ? '+' : '-' ?>₹<?= number_format($transaction['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= ucwords(str_replace('_', ' ', $transaction['payment_method'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger'
                                            ];
                                            $status_color = $status_colors[$transaction['approval_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $status_color ?>">
                                                <?= ucfirst($transaction['approval_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewTransaction(<?= $transaction['id'] ?>)" 
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="editTransaction(<?= $transaction['id'] ?>)" 
                                                        title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($transaction['approval_status'] == 'pending'): ?>
                                                    <button class="btn btn-outline-success btn-sm" 
                                                            onclick="approveTransaction(<?= $transaction['id'] ?>)" 
                                                            title="Approve">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning btn-sm" 
                                                            onclick="rejectTransaction(<?= $transaction['id'] ?>)" 
                                                            title="Reject">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                <?php endif; ?>
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
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No transactions found for the selected criteria
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
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                </li>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
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
                            <label class="form-label">Transaction Date *</label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type *</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="transfer">Transfer</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account *</label>
                            <select class="form-select" name="account_id" required>
                                <option value="">Select Account</option>
                                <?php mysqli_data_seek($accounts, 0); ?>
                                <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                    <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account</label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">Select Bank Account</option>
                                <?php mysqli_data_seek($bank_accounts, 0); ?>
                                <?php while ($bank_account = mysqli_fetch_assoc($bank_accounts)): ?>
                                    <option value="<?= $bank_account['id'] ?>"><?= htmlspecialchars($bank_account['account_name'] . ' - ' . $bank_account['bank_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
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
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tags (comma separated)</label>
                            <input type="text" class="form-control" name="tags" placeholder="tag1, tag2, tag3">
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
                            <label class="form-label">Transaction Date *</label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type *</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="transfer">Transfer</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account *</label>
                            <select class="form-select" name="account_id" required>
                                <option value="">Select Account</option>
                                <?php mysqli_data_seek($accounts, 0); ?>
                                <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                                    <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account</label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">Select Bank Account</option>
                                <?php mysqli_data_seek($bank_accounts, 0); ?>
                                <?php while ($bank_account = mysqli_fetch_assoc($bank_accounts)): ?>
                                    <option value="<?= $bank_account['id'] ?>"><?= htmlspecialchars($bank_account['account_name'] . ' - ' . $bank_account['bank_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
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
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tags (comma separated)</label>
                            <input type="text" class="form-control" name="tags" placeholder="tag1, tag2, tag3">
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

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <!-- Transaction details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Transactions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="exportForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                            </div>
                            <div class="col">
                                <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reports Modal -->
<div class="modal fade" id="reportsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Reports</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar3 fs-1 text-primary mb-3"></i>
                                <h6>Monthly Report</h6>
                                <p class="text-muted">Transaction summary by month</p>
                                <button class="btn btn-outline-primary" onclick="generateReport('monthly')">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-pie-chart fs-1 text-success mb-3"></i>
                                <h6>Category Report</h6>
                                <p class="text-muted">Breakdown by categories</p>
                                <button class="btn btn-outline-success" onclick="generateReport('category')">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-bank fs-1 text-info mb-3"></i>
                                <h6>Account Report</h6>
                                <p class="text-muted">Transaction by accounts</p>
                                <button class="btn btn-outline-info" onclick="generateReport('account')">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-credit-card fs-1 text-warning mb-3"></i>
                                <h6>Payment Method Report</h6>
                                <p class="text-muted">Breakdown by payment methods</p>
                                <button class="btn btn-outline-warning" onclick="generateReport('payment_method')">Generate</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Set current date as default for transaction forms
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('#addTransactionModal input[name="transaction_date"]').value = today;
    
    // Initialize bulk action handlers
    initializeBulkActions();
});

// Bulk Actions
function initializeBulkActions() {
    const selectAll = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const checkboxes = document.querySelectorAll('.transaction-checkbox');
    
    [selectAll, selectAllHeader].forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            [selectAll, selectAllHeader].forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });
    });
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
}

function updateBulkActions() {
    const selectedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedBoxes.length > 0) {
        bulkActions.classList.remove('d-none');
        selectedCount.textContent = selectedBoxes.length;
    } else {
        bulkActions.classList.add('d-none');
    }
}

function getSelectedTransactions() {
    return Array.from(document.querySelectorAll('.transaction-checkbox:checked')).map(cb => cb.value);
}

function bulkApprove() {
    const selectedIds = getSelectedTransactions();
    if (selectedIds.length === 0) {
        alert('Please select transactions to approve');
        return;
    }
    
    if (confirm(`Are you sure you want to approve ${selectedIds.length} transactions?`)) {
        const formData = new FormData();
        formData.append('action', 'bulk_approve');
        formData.append('transaction_ids', JSON.stringify(selectedIds));
        
        fetch('transactions.php', {
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
            alert('An error occurred while approving transactions.');
        });
    }
}

function bulkDelete() {
    const selectedIds = getSelectedTransactions();
    if (selectedIds.length === 0) {
        alert('Please select transactions to delete');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedIds.length} transactions? This action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'bulk_delete');
        formData.append('transaction_ids', JSON.stringify(selectedIds));
        
        fetch('transactions.php', {
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
            alert('An error occurred while deleting transactions.');
        });
    }
}

// Add Transaction Form Handler
document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_transaction');
    
    fetch('transactions.php', {
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
    
    fetch('transactions.php', {
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

// View Transaction Function
function viewTransaction(transactionId) {
    const formData = new FormData();
    formData.append('action', 'get_transaction');
    formData.append('transaction_id', transactionId);
    
    fetch('transactions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const transaction = data.data;
            const detailsHtml = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Transaction Date:</strong><br>
                        ${new Date(transaction.transaction_date).toLocaleDateString()}
                    </div>
                    <div class="col-md-6">
                        <strong>Type:</strong><br>
                        <span class="badge bg-${transaction.transaction_type === 'income' ? 'success' : 'danger'}">
                            ${transaction.transaction_type.charAt(0).toUpperCase() + transaction.transaction_type.slice(1)}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Amount:</strong><br>
                        <span class="fs-5 fw-bold ${transaction.transaction_type === 'income' ? 'text-success' : 'text-danger'}">
                            ${transaction.transaction_type === 'income' ? '+' : '-'}₹${parseFloat(transaction.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Method:</strong><br>
                        ${transaction.payment_method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </div>
                    <div class="col-12">
                        <strong>Description:</strong><br>
                        ${transaction.description}
                    </div>
                    ${transaction.reference_number ? `
                    <div class="col-md-6">
                        <strong>Reference Number:</strong><br>
                        ${transaction.reference_number}
                    </div>
                    ` : ''}
                    ${transaction.category ? `
                    <div class="col-md-6">
                        <strong>Category:</strong><br>
                        <span class="badge bg-info">${transaction.category}</span>
                    </div>
                    ` : ''}
                    ${transaction.tags ? `
                    <div class="col-12">
                        <strong>Tags:</strong><br>
                        ${transaction.tags.split(',').map(tag => `<span class="badge bg-secondary me-1">${tag.trim()}</span>`).join('')}
                    </div>
                    ` : ''}
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="badge bg-${transaction.approval_status === 'approved' ? 'success' : transaction.approval_status === 'pending' ? 'warning' : 'danger'}">
                            ${transaction.approval_status.charAt(0).toUpperCase() + transaction.approval_status.slice(1)}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Created:</strong><br>
                        ${new Date(transaction.created_at).toLocaleString()}
                    </div>
                </div>
            `;
            
            document.getElementById('transactionDetails').innerHTML = detailsHtml;
            const modal = new bootstrap.Modal(document.getElementById('viewTransactionModal'));
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

// Edit Transaction Function
function editTransaction(transactionId) {
    const formData = new FormData();
    formData.append('action', 'get_transaction');
    formData.append('transaction_id', transactionId);
    
    fetch('transactions.php', {
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
            form.querySelector('input[name="tags"]').value = transaction.tags || '';
            
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

// Approve Transaction Function
function approveTransaction(transactionId) {
    if (confirm('Are you sure you want to approve this transaction?')) {
        const formData = new FormData();
        formData.append('action', 'approve_transaction');
        formData.append('transaction_id', transactionId);
        
        fetch('transactions.php', {
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
            alert('An error occurred while approving the transaction.');
        });
    }
}

// Reject Transaction Function
function rejectTransaction(transactionId) {
    if (confirm('Are you sure you want to reject this transaction?')) {
        const formData = new FormData();
        formData.append('action', 'reject_transaction');
        formData.append('transaction_id', transactionId);
        
        fetch('transactions.php', {
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
            alert('An error occurred while rejecting the transaction.');
        });
    }
}

// Delete Transaction Function
function deleteTransaction(transactionId) {
    if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_transaction');
        formData.append('transaction_id', transactionId);
        
        fetch('transactions.php', {
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

// Show Export Modal
function showExportModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

// Show Reports Modal
function showReportsModal() {
    const modal = new bootstrap.Modal(document.getElementById('reportsModal'));
    modal.show();
}

// Export Form Handler
document.getElementById('exportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'export_transactions');
    
    fetch('transactions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.redirect) {
                window.open(data.redirect, '_blank');
            }
            document.querySelector('#exportModal .btn-close').click();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while exporting transactions.');
    });
});

// Generate Report Function
function generateReport(type) {
    // This would typically open a new window or redirect to a report page
    const url = `reports.php?type=${type}&date_from=${document.querySelector('input[name="date_from"]').value}&date_to=${document.querySelector('input[name="date_to"]').value}`;
    window.open(url, '_blank');
}
</script>
