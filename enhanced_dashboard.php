<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';
include 'auth_guard.php';

// Check permissions
checkPermission(PagePermissions::DASHBOARD);

// Get dashboard statistics
$today = date('Y-m-d');
$this_month = date('Y-m');
$this_year = date('Y');

// Today's stats
$today_stats = $conn->query("
    SELECT 
        COUNT(DISTINCT e.employee_id) as total_employees,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_today,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_today,
        COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_today
    FROM employees e
    LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = '$today'
")->fetch_assoc();

// This month's stats  
$month_stats = $conn->query("
    SELECT 
        COUNT(*) as total_attendance_records,
        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_month,
        COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_month,
        ROUND(AVG(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL 
            THEN TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600 END), 2) as avg_hours
    FROM attendance 
    WHERE attendance_date LIKE '$this_month%'
")->fetch_assoc();

// Recent invoices
$recent_invoices = $conn->query("
    SELECT invoice_number, customer_name, total_amount, created_at 
    FROM invoices 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent expenses
$recent_expenses = $conn->query("
    SELECT description, amount, expense_date 
    FROM expenses 
    ORDER BY expense_date DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <!-- Welcome Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="page-header">
                        <h4 class="page-title">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard Overview
                        </h4>
                        <p class="text-muted">Welcome back! Here's what's happening today.</p>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $today_stats['total_employees'] ?></h4>
                                    <p class="mb-0">Total Employees</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $today_stats['present_today'] ?></h4>
                                    <p class="mb-0">Present Today</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $today_stats['late_today'] ?></h4>
                                    <p class="mb-0">Late Today</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= number_format($month_stats['avg_hours'], 1) ?>h</h4>
                                    <p class="mb-0">Avg Hours/Day</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-bar-chart"></i>
                                Attendance Trends (Last 7 Days)
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="attendanceChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-pie-chart"></i>
                                Today's Attendance
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="attendancePieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-receipt"></i>
                                Recent Invoices
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_invoices as $invoice): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                            <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                            <td>₹<?= number_format($invoice['total_amount'], 2) ?></td>
                                            <td><?= date('M j', strtotime($invoice['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-wallet2"></i>
                                Recent Expenses
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_expenses as $expense): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($expense['description']) ?></td>
                                            <td>₹<?= number_format($expense['amount'], 2) ?></td>
                                            <td><?= date('M j', strtotime($expense['expense_date'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-lightning"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <a href="Employee_attendance.php" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-clock"></i><br>
                                        <small>Attendance</small>
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="add_employee.php" class="btn btn-outline-success w-100">
                                        <i class="bi bi-person-plus"></i><br>
                                        <small>Add Employee</small>
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="invoice_form.php" class="btn btn-outline-info w-100">
                                        <i class="bi bi-receipt"></i><br>
                                        <small>New Invoice</small>
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="daily_expense_form.php" class="btn btn-outline-warning w-100">
                                        <i class="bi bi-wallet2"></i><br>
                                        <small>Add Expense</small>
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="reports.php" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-graph-up"></i><br>
                                        <small>Reports</small>
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="settings.php" class="btn btn-outline-dark w-100">
                                        <i class="bi bi-gear"></i><br>
                                        <small>Settings</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Trend Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Present',
            data: [12, 15, 13, 14, 16, 8, 5],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Late',
            data: [2, 3, 1, 2, 1, 0, 0],
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Attendance Pie Chart
const pieCtx = document.getElementById('attendancePieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Late', 'Absent'],
        datasets: [{
            data: [<?= $today_stats['present_today'] ?>, <?= $today_stats['late_today'] ?>, <?= $today_stats['absent_today'] ?>],
            backgroundColor: ['#28a745', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<style>
.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.btn i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}
</style>

<?php include 'layouts/footer.php'; ?>
