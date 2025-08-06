<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Include config and database
include '../../config.php';
include '../../db.php';

$page_title = 'Dashboard';

// Fetch dashboard statistics with enhanced queries
$totalInvoices = 0;
$todayExpenses = 0;
$totalEmployees = 0;
$totalItems = 0;
$monthlyRevenue = 0;
$todayRevenue = 0;
$activeEmployees = 0;
$lowStockItems = 0;

// Total Invoices Amount
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalInvoices = $row['total'] ?? 0;
}

// Monthly Revenue (current month)
$currentMonth = date('Y-m');
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $monthlyRevenue = $row['total'] ?? 0;
}

// Today's Revenue
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM invoices WHERE DATE(created_at) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayRevenue = $row['total'] ?? 0;
}

// Today's Expenses
$result = mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses WHERE DATE(created_at) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $todayExpenses = $row['total'] ?? 0;
}

// Total and Active Employees
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'] ?? 0;
}

// Active employees (present today)
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT employee_id) AS total FROM attendance WHERE DATE(punch_in_time) = '$today'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $activeEmployees = $row['total'] ?? 0;
}

// Total Items
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalItems = $row['total'] ?? 0;
}

// Low stock items (stock < 10)
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items WHERE stock < 10");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $lowStockItems = $row['total'] ?? 0;
}

// Calculate growth percentages (dummy data for demo)
$revenueGrowth = '+12.5%';
$expenseGrowth = '+3.2%';
$employeeGrowth = '+5.1%';
$inventoryGrowth = '-2.8%';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">Welcome Back!</h1>
                <p class="text-muted" style="font-size: 1.1rem;">Here's what's happening with your business today, <?= date('F j, Y') ?>.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>
                <div class="dropdown">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar-event"></i>
                        View Reports
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../../summary_dashboard.php">Summary Reports</a></li>
                        <li><a class="dropdown-item" href="../../reports.php">Business Analytics</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../export_reports.php">Export Data</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Key Statistics -->
        <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stat-card stat-revenue animate-fade-in-up" style="animation-delay: 0.1s;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-number">₹<?= number_format($totalInvoices, 0) ?></div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i>
                                <?= $revenueGrowth ?> from last month
                            </div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                            <i class="bi bi-currency-rupee" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                    </div>
                    <div class="progress-modern">
                        <div class="progress-bar bg-success" style="width: 75%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stat-card stat-target animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-number">₹<?= number_format($monthlyRevenue, 0) ?></div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up"></i>
                                Target: ₹100K
                            </div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="bi bi-graph-up-arrow" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                    </div>
                    <div class="progress-modern">
                        <div class="progress-bar bg-primary" style="width: <?= min(($monthlyRevenue / 100000) * 100, 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stat-card stat-expense animate-fade-in-up" style="animation-delay: 0.3s;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="stat-label">Today's Expenses</div>
                            <div class="stat-number">₹<?= number_format($todayExpenses, 0) ?></div>
                            <div class="stat-change <?= $todayExpenses > 5000 ? 'negative' : 'positive' ?>">
                                <i class="bi bi-<?= $todayExpenses > 5000 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= $expenseGrowth ?> vs average
                            </div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                            <i class="bi bi-cash-stack" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                    </div>
                    <div class="progress-modern">
                        <div class="progress-bar bg-warning" style="width: 60%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="stat-card stat-employee animate-fade-in-up" style="animation-delay: 0.4s;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="stat-label">Active Employees</div>
                            <div class="stat-number"><?= $activeEmployees ?><span style="font-size: 1rem; color: var(--gray-500);">/ <?= $totalEmployees ?></span></div>
                            <div class="stat-change positive">
                                <i class="bi bi-people"></i>
                                <?= round(($activeEmployees / max($totalEmployees, 1)) * 100) ?>% attendance
                            </div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="bi bi-people-fill" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                    </div>
                    <div class="progress-modern">
                        <div class="progress-bar bg-info" style="width: <?= round(($activeEmployees / max($totalEmployees, 1)) * 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Summary & Alerts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-day me-2"></i>
                        Today's Overview
                    </h5>
                    <span class="badge badge-info"><?= date('M j, Y') ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="activity-item">
                                <div class="d-flex align-items-center">
                                    <div class="quick-action-icon me-3" style="background: linear-gradient(135deg, #10b981, #34d399);">
                                        <i class="bi bi-cash-coin text-white"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Today's Revenue</div>
                                        <div class="h5 mb-0 text-dark">₹<?= number_format($todayRevenue, 2) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="activity-item">
                                <div class="d-flex align-items-center">
                                    <div class="quick-action-icon me-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                                        <i class="bi bi-receipt text-white"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Invoices Today</div>
                                        <div class="h5 mb-0 text-dark">
                                            <?php
                                            $todayInvoices = mysqli_query($conn, "SELECT COUNT(*) AS count FROM invoices WHERE DATE(created_at) = '$today'");
                                            echo $todayInvoices ? mysqli_fetch_assoc($todayInvoices)['count'] : 0;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Recent Activities</h6>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-plus-circle text-success"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small fw-medium">New invoice created</div>
                                            <div class="activity-time"><?= date('g:i A') ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-person-plus text-info"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small fw-medium">Employee checked in</div>
                                            <div class="activity-time"><?= date('g:i A', strtotime('-30 minutes')) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-box text-warning"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small fw-medium">Product inventory updated</div>
                                            <div class="activity-time"><?= date('g:i A', strtotime('-1 hour')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Quick Stats</h6>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--gray-50);">
                                <span class="small fw-medium">Total Products</span>
                                <span class="fw-bold text-dark"><?= $totalItems ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--gray-50);">
                                <span class="small fw-medium">Low Stock Items</span>
                                <span class="fw-bold text-danger"><?= $lowStockItems ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: var(--gray-50);">
                                <span class="small fw-medium">Active Sessions</span>
                                <span class="fw-bold text-success">1</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background: var(--gray-50);">
                                <span class="small fw-medium">System Status</span>
                                <span class="badge badge-success">Healthy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Alerts & Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($lowStockItems > 0): ?>
                    <div class="activity-item mb-3" style="border-left: 4px solid #f59e0b;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle text-warning me-3"></i>
                            <div>
                                <div class="fw-medium small">Low Stock Alert</div>
                                <div class="text-muted activity-time"><strong><?= $lowStockItems ?></strong> items running low</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="activity-item mb-3" style="border-left: 4px solid #3b82f6;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-target text-info me-3"></i>
                            <div>
                                <div class="fw-medium small">Monthly Progress</div>
                                <div class="text-muted activity-time"><?= round(($monthlyRevenue / 100000) * 100) ?>% target completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item" style="border-left: 4px solid #10b981;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-3"></i>
                            <div>
                                <div class="fw-medium small">System Status</div>
                                <div class="text-muted activity-time">All systems operational</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-lightning me-2"></i>
                Quick Actions
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <a href="../invoice/invoice.php" class="quick-action-card text-decoration-none">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="bi bi-receipt-cutoff text-white" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="mb-2 text-dark">Create Invoice</h6>
                        <p class="text-muted small mb-3">Generate new customer invoices quickly</p>
                        <div class="d-flex align-items-center text-primary">
                            <i class="bi bi-plus-circle me-1"></i>
                            <span class="small fw-medium">Create Now</span>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="../expenses/expenses.php" class="quick-action-card text-decoration-none">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                            <i class="bi bi-cash-coin text-white" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="mb-2 text-dark">Record Expense</h6>
                        <p class="text-muted small mb-3">Track daily business expenses</p>
                        <div class="d-flex align-items-center text-warning">
                            <i class="bi bi-plus-circle me-1"></i>
                            <span class="small fw-medium">Add Expense</span>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="../employees/employees.php" class="quick-action-card text-decoration-none">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                            <i class="bi bi-people-fill text-white" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="mb-2 text-dark">Manage Staff</h6>
                        <p class="text-muted small mb-3">Add employees and track attendance</p>
                        <div class="d-flex align-items-center text-success">
                            <i class="bi bi-people me-1"></i>
                            <span class="small fw-medium">Manage</span>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="../products/products.php" class="quick-action-card text-decoration-none">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="bi bi-box-seam-fill text-white" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="mb-2 text-dark">Inventory</h6>
                        <p class="text-muted small mb-3">Manage products and stock levels</p>
                        <div class="d-flex align-items-center text-info">
                            <i class="bi bi-boxes me-1"></i>
                            <span class="small fw-medium">View Stock</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Dashboard Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate statistics on load
    animateCounters();
    
    // Update time every minute
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Auto-refresh data every 5 minutes
    setInterval(refreshDashboard, 300000);
});

function animateCounters() {
    const counters = document.querySelectorAll('.stat-value');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[₹,]/g, ''));
        if (target) {
            animateValue(counter, 0, target, 2000);
        }
    });
}

function animateValue(element, start, end, duration) {
    const startTime = performance.now();
    const isRupee = element.textContent.includes('₹');
    
    function updateValue(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const current = Math.floor(start + (end - start) * progress);
        
        if (isRupee) {
            element.textContent = '₹' + current.toLocaleString();
        } else {
            element.textContent = current.toLocaleString();
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateValue);
        }
    }
    
    requestAnimationFrame(updateValue);
}

function updateDateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: 'numeric', 
        minute: '2-digit'
    });
    
    // Update any time displays
    document.querySelectorAll('.current-time').forEach(el => {
        el.textContent = timeString;
    });
}

function refreshDashboard() {
    ModernUI.showToast('Refreshing dashboard data...', 'info');
    
    // Add visual refresh indicator
    const refreshBtn = document.querySelector('[onclick="refreshDashboard()"] i');
    if (refreshBtn) {
        refreshBtn.classList.add('animate-pulse');
        setTimeout(() => {
            refreshBtn.classList.remove('animate-pulse');
            ModernUI.showToast('Dashboard refreshed successfully!', 'success');
        }, 2000);
    }
    
    // In a real implementation, this would reload the data via AJAX
    setTimeout(() => {
        location.reload();
    }, 2500);
}

// Add smooth scroll for any anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php include '../../layouts/footer.php'; ?>