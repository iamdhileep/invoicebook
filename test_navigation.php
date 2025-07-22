<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$page_title = 'Navigation Test';
include 'layouts/header.php';
include 'layouts/sidebar.php';

// Test all navigation URLs
$testUrls = [
    'Dashboard' => 'pages/dashboard/dashboard.php',
    'Create Invoice' => 'pages/invoice/invoice.php',
    'Invoice History' => 'invoice_history.php',
    'Product List' => 'pages/products/products.php',
    'Add Product' => 'add_item.php',
    'Stock Management' => 'item-stock.php',
    'Categories' => 'manage_categories.php',
    'Daily Expenses' => 'pages/expenses/expenses.php',
    'Expense History' => 'expense_history.php',
    'All Employees' => 'pages/employees/employees.php',
    'Add Employee' => 'add_employee.php',
    'Mark Attendance' => 'pages/attendance/attendance.php',
    'Attendance Calendar' => 'attendance-calendar.php',
    'Payroll Management' => 'pages/payroll/payroll.php',
    'Payroll Report' => 'payroll_report.php',
    'Business Reports' => 'reports.php',
    'Attendance Report' => 'attendance_preview.php'
];

// Get current base path
$basePath = '';
if (strpos(dirname($_SERVER['SCRIPT_NAME']), '/pages/') !== false) {
    $basePath = '../../';
} else {
    $basePath = './';
}
?>

<div class="main-content">
    <div class="mb-4">
        <h1 class="h3 mb-0">Navigation Test</h1>
        <p class="text-muted">Testing all sidebar navigation URLs</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">URL Test Results</h5>
                </div>
                <div class="card-body">
                    <p><strong>Current Script:</strong> <?= $_SERVER['SCRIPT_NAME'] ?></p>
                    <p><strong>Base Path:</strong> <code><?= htmlspecialchars($basePath) ?></code></p>
                    <p><strong>Request URI:</strong> <?= $_SERVER['REQUEST_URI'] ?></p>
                    <p><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="testAllUrls()">Test All URLs</button>
                        <button class="btn btn-success" onclick="checkFileExists()">Check File Existence</button>
                        <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="btn btn-info">Go to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Navigation URLs Test</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Page Name</th>
                            <th>URL</th>
                            <th>Full Path</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testUrls as $name => $url): ?>
                            <?php
                            $fullPath = $basePath . $url;
                            $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fullPath, './');
                            $fileExists = file_exists($absolutePath);
                            ?>
                            <tr>
                                <td><strong><?= $name ?></strong></td>
                                <td><code><?= htmlspecialchars($url) ?></code></td>
                                <td><code><?= htmlspecialchars($fullPath) ?></code></td>
                                <td>
                                    <?php if ($fileExists): ?>
                                        <span class="badge bg-success">✅ Exists</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">❌ Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= $fullPath ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        Test Link
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Debug Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Server Variables:</h6>
                    <ul class="list-unstyled small">
                        <li><strong>HTTP_HOST:</strong> <?= $_SERVER['HTTP_HOST'] ?></li>
                        <li><strong>SERVER_NAME:</strong> <?= $_SERVER['SERVER_NAME'] ?></li>
                        <li><strong>REQUEST_SCHEME:</strong> <?= $_SERVER['REQUEST_SCHEME'] ?? 'http' ?></li>
                        <li><strong>HTTPS:</strong> <?= $_SERVER['HTTPS'] ?? 'off' ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Path Information:</h6>
                    <ul class="list-unstyled small">
                        <li><strong>SCRIPT_FILENAME:</strong> <?= $_SERVER['SCRIPT_FILENAME'] ?></li>
                        <li><strong>PHP_SELF:</strong> <?= $_SERVER['PHP_SELF'] ?></li>
                        <li><strong>dirname(SCRIPT_NAME):</strong> <?= dirname($_SERVER['SCRIPT_NAME']) ?></li>
                        <li><strong>Pages check:</strong> <?= strpos(dirname($_SERVER['SCRIPT_NAME']), '/pages/') !== false ? 'TRUE' : 'FALSE' ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testAllUrls() {
    const rows = document.querySelectorAll('tbody tr');
    let workingCount = 0;
    let totalCount = rows.length;
    
    rows.forEach(row => {
        const testLink = row.querySelector('a[target="_blank"]');
        const statusCell = row.querySelector('.badge');
        
        if (statusCell.classList.contains('bg-success')) {
            workingCount++;
        }
    });
    
    alert(`URL Test Results:\n✅ Working: ${workingCount}\n❌ Broken: ${totalCount - workingCount}\n📊 Success Rate: ${Math.round((workingCount/totalCount)*100)}%`);
}

function checkFileExists() {
    // This would need to be implemented with AJAX to check server-side
    alert('File existence check completed. See the table above for results.');
}
</script>

<?php include 'layouts/footer.php'; ?>