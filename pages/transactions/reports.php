<?php
/**
 * Transaction Reports
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

$page_title = 'Transaction Reports';

$report_type = $_GET['type'] ?? 'monthly';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Generate report based on type
switch ($report_type) {
    case 'monthly':
        $report_data = getMonthlyReport($conn, $date_from, $date_to);
        $report_title = 'Monthly Transaction Report';
        break;
    case 'category':
        $report_data = getCategoryReport($conn, $date_from, $date_to);
        $report_title = 'Category-wise Transaction Report';
        break;
    case 'account':
        $report_data = getAccountReport($conn, $date_from, $date_to);
        $report_title = 'Account-wise Transaction Report';
        break;
    case 'payment_method':
        $report_data = getPaymentMethodReport($conn, $date_from, $date_to);
        $report_title = 'Payment Method Report';
        break;
    default:
        $report_data = getMonthlyReport($conn, $date_from, $date_to);
        $report_title = 'Monthly Transaction Report';
}

function getMonthlyReport($conn, $date_from, $date_to) {
    $query = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense,
            COUNT(*) as total_transactions
        FROM account_transactions
        WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month
    ";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

function getCategoryReport($conn, $date_from, $date_to) {
    $query = "
        SELECT 
            COALESCE(category, 'Uncategorized') as category,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense,
            COUNT(*) as total_transactions
        FROM account_transactions
        WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
        GROUP BY COALESCE(category, 'Uncategorized')
        ORDER BY (income + expense) DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

function getAccountReport($conn, $date_from, $date_to) {
    $query = "
        SELECT 
            coa.account_name,
            coa.account_code,
            SUM(CASE WHEN at.transaction_type = 'income' THEN at.amount ELSE 0 END) as income,
            SUM(CASE WHEN at.transaction_type = 'expense' THEN at.amount ELSE 0 END) as expense,
            COUNT(*) as total_transactions
        FROM account_transactions at
        LEFT JOIN chart_of_accounts coa ON at.account_id = coa.id
        WHERE at.transaction_date BETWEEN '$date_from' AND '$date_to'
        GROUP BY at.account_id, coa.account_name, coa.account_code
        ORDER BY (income + expense) DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

function getPaymentMethodReport($conn, $date_from, $date_to) {
    $query = "
        SELECT 
            payment_method,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense,
            COUNT(*) as total_transactions
        FROM account_transactions
        WHERE transaction_date BETWEEN '$date_from' AND '$date_to'
        GROUP BY payment_method
        ORDER BY (income + expense) DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 <?= $report_title ?></h1>
                <p class="text-muted">Period: <?= date('d/m/Y', strtotime($date_from)) ?> to <?= date('d/m/Y', strtotime($date_to)) ?></p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="window.history.back()">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Report Content -->
        <div class="card">
            <div class="card-body">
                <?php if ($report_type == 'monthly'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Income</th>
                                    <th>Expense</th>
                                    <th>Net Income</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_income = 0;
                                $total_expense = 0;
                                $total_transactions = 0;
                                foreach ($report_data as $row):
                                    $total_income += $row['income'];
                                    $total_expense += $row['expense'];
                                    $total_transactions += $row['total_transactions'];
                                    $net_income = $row['income'] - $row['expense'];
                                ?>
                                <tr>
                                    <td><?= date('F Y', strtotime($row['month'] . '-01')) ?></td>
                                    <td class="text-success">₹<?= number_format($row['income'], 2) ?></td>
                                    <td class="text-danger">₹<?= number_format($row['expense'], 2) ?></td>
                                    <td class="fw-bold <?= $net_income >= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₹<?= number_format($net_income, 2) ?>
                                    </td>
                                    <td><?= number_format($row['total_transactions']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-success">₹<?= number_format($total_income, 2) ?></th>
                                    <th class="text-danger">₹<?= number_format($total_expense, 2) ?></th>
                                    <th class="fw-bold <?= ($total_income - $total_expense) >= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₹<?= number_format($total_income - $total_expense, 2) ?>
                                    </th>
                                    <th><?= number_format($total_transactions) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                <?php elseif ($report_type == 'category'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Income</th>
                                    <th>Expense</th>
                                    <th>Total Amount</th>
                                    <th>Transactions</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_amount = array_sum(array_map(function($row) {
                                    return $row['income'] + $row['expense'];
                                }, $report_data));
                                
                                foreach ($report_data as $row):
                                    $category_total = $row['income'] + $row['expense'];
                                    $percentage = $total_amount > 0 ? ($category_total / $total_amount) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td class="text-success">₹<?= number_format($row['income'], 2) ?></td>
                                    <td class="text-danger">₹<?= number_format($row['expense'], 2) ?></td>
                                    <td class="fw-bold">₹<?= number_format($category_total, 2) ?></td>
                                    <td><?= number_format($row['total_transactions']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 100px; height: 10px;">
                                                <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type == 'account'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Income</th>
                                    <th>Expense</th>
                                    <th>Net Amount</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row):
                                    $net_amount = $row['income'] - $row['expense'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($row['account_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['account_code']) ?></small>
                                    </td>
                                    <td class="text-success">₹<?= number_format($row['income'], 2) ?></td>
                                    <td class="text-danger">₹<?= number_format($row['expense'], 2) ?></td>
                                    <td class="fw-bold <?= $net_amount >= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₹<?= number_format($net_amount, 2) ?>
                                    </td>
                                    <td><?= number_format($row['total_transactions']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type == 'payment_method'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Income</th>
                                    <th>Expense</th>
                                    <th>Total Amount</th>
                                    <th>Transactions</th>
                                    <th>Usage %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_transactions = array_sum(array_column($report_data, 'total_transactions'));
                                
                                foreach ($report_data as $row):
                                    $method_total = $row['income'] + $row['expense'];
                                    $usage_percentage = $total_transactions > 0 ? ($row['total_transactions'] / $total_transactions) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= ucwords(str_replace('_', ' ', $row['payment_method'])) ?></td>
                                    <td class="text-success">₹<?= number_format($row['income'], 2) ?></td>
                                    <td class="text-danger">₹<?= number_format($row['expense'], 2) ?></td>
                                    <td class="fw-bold">₹<?= number_format($method_total, 2) ?></td>
                                    <td><?= number_format($row['total_transactions']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 100px; height: 10px;">
                                                <div class="progress-bar bg-info" style="width: <?= $usage_percentage ?>%"></div>
                                            </div>
                                            <?= number_format($usage_percentage, 1) ?>%
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .main-content { margin: 0 !important; }
    .btn, .navbar, .sidebar { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
