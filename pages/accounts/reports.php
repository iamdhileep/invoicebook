<?php
/**
 * Financial Reports
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Get date range from parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'profit_loss';

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $start_date) || !DateTime::createFromFormat('Y-m-d', $end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Generate reports based on type
function generateProfitLossReport($conn, $start_date, $end_date) {
    // Income accounts (Revenue)
    $income_query = "
        SELECT ca.account_name, SUM(at.amount) as total_amount
        FROM account_transactions at
        JOIN chart_of_accounts ca ON at.chart_account_id = ca.id
        WHERE ca.account_type = 'Revenue' 
        AND at.transaction_type = 'income'
        AND DATE(at.transaction_date) BETWEEN '$start_date' AND '$end_date'
        GROUP BY ca.id, ca.account_name
        ORDER BY total_amount DESC
    ";
    $income_result = mysqli_query($conn, $income_query);
    
    // Expense accounts
    $expense_query = "
        SELECT ca.account_name, SUM(at.amount) as total_amount
        FROM account_transactions at
        JOIN chart_of_accounts ca ON at.chart_account_id = ca.id
        WHERE ca.account_type = 'Expense' 
        AND at.transaction_type = 'expense'
        AND DATE(at.transaction_date) BETWEEN '$start_date' AND '$end_date'
        GROUP BY ca.id, ca.account_name
        ORDER BY total_amount DESC
    ";
    $expense_result = mysqli_query($conn, $expense_query);
    
    // Calculate totals
    $total_income = 0;
    $total_expenses = 0;
    
    $income_data = [];
    while ($row = mysqli_fetch_assoc($income_result)) {
        $income_data[] = $row;
        $total_income += $row['total_amount'];
    }
    
    $expense_data = [];
    while ($row = mysqli_fetch_assoc($expense_result)) {
        $expense_data[] = $row;
        $total_expenses += $row['total_amount'];
    }
    
    return [
        'income' => $income_data,
        'expenses' => $expense_data,
        'total_income' => $total_income,
        'total_expenses' => $total_expenses,
        'net_profit' => $total_income - $total_expenses
    ];
}

function generateBalanceSheetReport($conn, $end_date) {
    // Assets
    $assets_query = "
        SELECT ca.account_name, ca.current_balance
        FROM chart_of_accounts ca
        WHERE ca.account_type IN ('Asset', 'Current Asset', 'Fixed Asset')
        AND ca.is_active = TRUE
        ORDER BY ca.account_type, ca.account_name
    ";
    $assets_result = mysqli_query($conn, $assets_query);
    
    // Liabilities
    $liabilities_query = "
        SELECT ca.account_name, ca.current_balance
        FROM chart_of_accounts ca
        WHERE ca.account_type IN ('Liability', 'Current Liability', 'Long-term Liability')
        AND ca.is_active = TRUE
        ORDER BY ca.account_type, ca.account_name
    ";
    $liabilities_result = mysqli_query($conn, $liabilities_query);
    
    // Equity
    $equity_query = "
        SELECT ca.account_name, ca.current_balance
        FROM chart_of_accounts ca
        WHERE ca.account_type = 'Equity'
        AND ca.is_active = TRUE
        ORDER BY ca.account_name
    ";
    $equity_result = mysqli_query($conn, $equity_query);
    
    $assets = [];
    $total_assets = 0;
    while ($row = mysqli_fetch_assoc($assets_result)) {
        $assets[] = $row;
        $total_assets += $row['current_balance'];
    }
    
    $liabilities = [];
    $total_liabilities = 0;
    while ($row = mysqli_fetch_assoc($liabilities_result)) {
        $liabilities[] = $row;
        $total_liabilities += $row['current_balance'];
    }
    
    $equity = [];
    $total_equity = 0;
    while ($row = mysqli_fetch_assoc($equity_result)) {
        $equity[] = $row;
        $total_equity += $row['current_balance'];
    }
    
    return [
        'assets' => $assets,
        'liabilities' => $liabilities,
        'equity' => $equity,
        'total_assets' => $total_assets,
        'total_liabilities' => $total_liabilities,
        'total_equity' => $total_equity
    ];
}

function generateCashFlowReport($conn, $start_date, $end_date) {
    // Operating Activities
    $operating_query = "
        SELECT 
            SUM(CASE WHEN at.transaction_type = 'income' THEN at.amount ELSE -at.amount END) as net_cash_flow,
            COUNT(*) as transaction_count
        FROM account_transactions at
        JOIN chart_of_accounts ca ON at.chart_account_id = ca.id
        WHERE ca.account_type IN ('Revenue', 'Expense')
        AND DATE(at.transaction_date) BETWEEN '$start_date' AND '$end_date'
    ";
    $operating_result = mysqli_query($conn, $operating_query);
    $operating_data = mysqli_fetch_assoc($operating_result);
    
    // Bank Account Changes
    $bank_changes_query = "
        SELECT 
            ba.account_name,
            ba.account_type,
            SUM(CASE WHEN at.transaction_type = 'income' THEN at.amount ELSE -at.amount END) as net_change
        FROM account_transactions at
        JOIN bank_accounts ba ON at.bank_account_id = ba.id
        WHERE DATE(at.transaction_date) BETWEEN '$start_date' AND '$end_date'
        GROUP BY ba.id, ba.account_name, ba.account_type
        ORDER BY ABS(net_change) DESC
    ";
    $bank_changes_result = mysqli_query($conn, $bank_changes_query);
    
    $bank_changes = [];
    while ($row = mysqli_fetch_assoc($bank_changes_result)) {
        $bank_changes[] = $row;
    }
    
    return [
        'operating_cash_flow' => $operating_data['net_cash_flow'] ?? 0,
        'operating_transactions' => $operating_data['transaction_count'] ?? 0,
        'bank_changes' => $bank_changes
    ];
}

// Generate the selected report
$report_data = [];
switch ($report_type) {
    case 'profit_loss':
        $report_data = generateProfitLossReport($conn, $start_date, $end_date);
        break;
    case 'balance_sheet':
        $report_data = generateBalanceSheetReport($conn, $end_date);
        break;
    case 'cash_flow':
        $report_data = generateCashFlowReport($conn, $start_date, $end_date);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .report-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .financial-table {
            border: none;
            margin-bottom: 0;
        }
        .financial-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        .financial-table td, .financial-table th {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
        }
        .amount-positive { color: #28a745; font-weight: 600; }
        .amount-negative { color: #dc3545; font-weight: 600; }
        .amount-neutral { color: #6c757d; font-weight: 500; }
        .total-row {
            background-color: #f8f9fa;
            font-weight: 700;
        }
        .net-profit-positive {
            background-color: #d4edda;
            color: #155724;
        }
        .net-profit-negative {
            background-color: #f8d7da;
            color: #721c24;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .card { box-shadow: none !important; border: 1px solid #000 !important; }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
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
                <a class="nav-link" href="bank_accounts.php">
                    <i class="fas fa-university me-1"></i>Bank Accounts
                </a>
                <a class="nav-link active" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4 no-print">
            <div class="col-md-6">
                <h2><i class="fas fa-chart-bar me-2"></i>Financial Reports</h2>
                <p class="text-muted">Comprehensive financial analysis and reporting</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                </button>
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="card report-card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" onchange="this.form.submit()">
                            <option value="profit_loss" <?php echo $report_type == 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss</option>
                            <option value="balance_sheet" <?php echo $report_type == 'balance_sheet' ? 'selected' : ''; ?>>Balance Sheet</option>
                            <option value="cash_flow" <?php echo $report_type == 'cash_flow' ? 'selected' : ''; ?>>Cash Flow</option>
                        </select>
                    </div>
                    <?php if ($report_type != 'balance_sheet'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo $report_type == 'balance_sheet' ? 'As of Date' : 'End Date'; ?></label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-bar me-1"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Content -->
        <?php if ($report_type == 'profit_loss'): ?>
        <!-- Profit & Loss Report -->
        <div class="card report-card">
            <div class="report-header card-header text-center">
                <h4 class="mb-1">Profit & Loss Statement</h4>
                <p class="mb-0">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            </div>
            <div class="card-body p-0">
                <table class="table financial-table">
                    <!-- Income Section -->
                    <tr class="table-primary">
                        <th colspan="2"><strong>INCOME</strong></th>
                    </tr>
                    <?php if (!empty($report_data['income'])): ?>
                        <?php foreach ($report_data['income'] as $income): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($income['account_name']); ?></td>
                            <td class="text-end amount-positive">₹<?php echo number_format($income['total_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">No income transactions found</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>Total Income</strong></td>
                        <td class="text-end"><strong>₹<?php echo number_format($report_data['total_income'], 2); ?></strong></td>
                    </tr>

                    <!-- Expenses Section -->
                    <tr class="table-warning">
                        <th colspan="2"><strong>EXPENSES</strong></th>
                    </tr>
                    <?php if (!empty($report_data['expenses'])): ?>
                        <?php foreach ($report_data['expenses'] as $expense): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expense['account_name']); ?></td>
                            <td class="text-end amount-negative">₹<?php echo number_format($expense['total_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">No expense transactions found</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>Total Expenses</strong></td>
                        <td class="text-end"><strong>₹<?php echo number_format($report_data['total_expenses'], 2); ?></strong></td>
                    </tr>

                    <!-- Net Profit -->
                    <tr class="<?php echo $report_data['net_profit'] >= 0 ? 'net-profit-positive' : 'net-profit-negative'; ?>">
                        <td><strong>NET <?php echo $report_data['net_profit'] >= 0 ? 'PROFIT' : 'LOSS'; ?></strong></td>
                        <td class="text-end"><strong>₹<?php echo number_format(abs($report_data['net_profit']), 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php elseif ($report_type == 'balance_sheet'): ?>
        <!-- Balance Sheet Report -->
        <div class="card report-card">
            <div class="report-header card-header text-center">
                <h4 class="mb-1">Balance Sheet</h4>
                <p class="mb-0">As of <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            </div>
            <div class="card-body p-0">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table financial-table">
                            <tr class="table-success">
                                <th colspan="2"><strong>ASSETS</strong></th>
                            </tr>
                            <?php if (!empty($report_data['assets'])): ?>
                                <?php foreach ($report_data['assets'] as $asset): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['account_name']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($asset['current_balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">No assets found</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td><strong>Total Assets</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($report_data['total_assets'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <table class="table financial-table">
                            <tr class="table-danger">
                                <th colspan="2"><strong>LIABILITIES</strong></th>
                            </tr>
                            <?php if (!empty($report_data['liabilities'])): ?>
                                <?php foreach ($report_data['liabilities'] as $liability): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($liability['account_name']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($liability['current_balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">No liabilities found</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td><strong>Total Liabilities</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($report_data['total_liabilities'], 2); ?></strong></td>
                            </tr>
                            
                            <tr class="table-info">
                                <th colspan="2"><strong>EQUITY</strong></th>
                            </tr>
                            <?php if (!empty($report_data['equity'])): ?>
                                <?php foreach ($report_data['equity'] as $equity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($equity['account_name']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($equity['current_balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">No equity accounts found</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td><strong>Total Equity</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($report_data['total_equity'], 2); ?></strong></td>
                            </tr>
                            
                            <tr class="net-profit-positive">
                                <td><strong>Total Liab. + Equity</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($report_data['total_liabilities'] + $report_data['total_equity'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($report_type == 'cash_flow'): ?>
        <!-- Cash Flow Report -->
        <div class="card report-card">
            <div class="report-header card-header text-center">
                <h4 class="mb-1">Cash Flow Statement</h4>
                <p class="mb-0">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            </div>
            <div class="card-body p-0">
                <table class="table financial-table">
                    <tr class="table-primary">
                        <th colspan="2"><strong>OPERATING ACTIVITIES</strong></th>
                    </tr>
                    <tr>
                        <td>Net Cash from Operating Activities</td>
                        <td class="text-end <?php echo $report_data['operating_cash_flow'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                            ₹<?php echo number_format($report_data['operating_cash_flow'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Number of Transactions</td>
                        <td class="text-end"><?php echo $report_data['operating_transactions']; ?></td>
                    </tr>
                    
                    <tr class="table-info">
                        <th colspan="2"><strong>BANK ACCOUNT CHANGES</strong></th>
                    </tr>
                    <?php if (!empty($report_data['bank_changes'])): ?>
                        <?php foreach ($report_data['bank_changes'] as $change): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($change['account_name']); ?> (<?php echo ucwords(str_replace('_', ' ', $change['account_type'])); ?>)</td>
                            <td class="text-end <?php echo $change['net_change'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                ₹<?php echo number_format($change['net_change'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">No bank account changes found</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Summary -->
        <div class="row mt-4 no-print">
            <div class="col-12">
                <div class="card report-card">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle me-2"></i>Report Information</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Generated on:</small><br>
                                <strong><?php echo date('M d, Y \a\t h:i A'); ?></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Report Period:</small><br>
                                <strong>
                                    <?php if ($report_type == 'balance_sheet'): ?>
                                        As of <?php echo date('M d, Y', strtotime($end_date)); ?>
                                    <?php else: ?>
                                        <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Report Type:</small><br>
                                <strong><?php echo ucwords(str_replace('_', ' ', $report_type)); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToPDF() {
            // This would typically integrate with a PDF generation library
            alert('PDF export functionality would be implemented here using libraries like jsPDF or server-side PDF generation');
        }

        // Auto-submit form when date changes
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>
