<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../db.php';

// Get comprehensive procurement analytics
$analytics = [];

// Requisition analytics
$req_analytics = "SELECT 
    COUNT(*) as total_requisitions,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(total_amount) as total_requisition_value,
    AVG(total_amount) as avg_requisition_value
FROM purchase_requisitions
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

$req_result = mysqli_query($conn, $req_analytics);
$analytics['requisitions'] = mysqli_fetch_assoc($req_result) ?: [];

// Supplier performance
$supplier_analytics = "SELECT 
    COUNT(DISTINCT s.id) as total_suppliers,
    COUNT(DISTINCT CASE WHEN po.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN s.id END) as active_suppliers,
    AVG(ve.overall_score) as avg_supplier_rating,
    COUNT(ve.id) as total_evaluations
FROM suppliers s
LEFT JOIN purchase_orders po ON s.id = po.supplier_id
LEFT JOIN vendor_evaluations ve ON s.id = ve.supplier_id";

$supplier_result = mysqli_query($conn, $supplier_analytics);
$analytics['suppliers'] = mysqli_fetch_assoc($supplier_result) ?: [];

// Contract analytics
$contract_analytics = "SELECT 
    COUNT(*) as total_contracts,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contracts,
    SUM(CASE WHEN status = 'active' THEN value ELSE 0 END) as active_contract_value,
    SUM(CASE WHEN end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active' THEN 1 ELSE 0 END) as expiring_contracts
FROM contract_management";

$contract_result = mysqli_query($conn, $contract_analytics);
$analytics['contracts'] = mysqli_fetch_assoc($contract_result) ?: [];

// Monthly spend trend
$spend_trend = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(total_amount) as monthly_spend,
    COUNT(*) as transaction_count
FROM purchase_requisitions
WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month";

$spend_result = mysqli_query($conn, $spend_trend);
$analytics['spend_trend'] = [];
while ($row = mysqli_fetch_assoc($spend_result)) {
    $analytics['spend_trend'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Analytics Dashboard - Billbook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            padding: 30px;
            margin-left: 0;
        }
        .analytics-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 25px;
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        .metric-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .metric-card.dark {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .metric-value {
            font-size: 3rem;
            font-weight: bold;
            margin: 15px 0;
            line-height: 1;
        }
        .metric-label {
            opacity: 0.9;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .metric-icon {
            position: absolute;
            right: 25px;
            top: 25px;
            font-size: 4rem;
            opacity: 0.2;
        }
        .metric-change {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .chart-title i {
            margin-right: 10px;
            color: #667eea;
        }
        .sidebar-nav {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .nav-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e5e7eb;
        }
        .nav-item {
            margin: 10px 0;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #374151;
            text-decoration: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 2px solid transparent;
        }
        .nav-link-custom:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
            text-decoration: none;
            border-color: rgba(255,255,255,0.3);
        }
        .nav-link-custom.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .nav-link-custom i {
            width: 25px;
            margin-right: 15px;
            text-align: center;
            font-size: 1.1rem;
        }
        .dashboard-section {
            display: none;
            animation: fadeInUp 0.6s ease;
        }
        .dashboard-section.active {
            display: block;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            transition: stroke-dashoffset 0.5s ease-in-out;
        }
        .table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 700;
            color: #374151;
            padding: 20px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table td {
            border: none;
            padding: 20px;
            vertical-align: middle;
        }
        .table tbody tr {
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.3s;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .priority-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .priority-low { background: #10b981; }
        .priority-medium { background: #f59e0b; }
        .priority-high { background: #ef4444; }
        .priority-urgent { background: #dc2626; animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .action-btn {
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            margin: 2px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3">
                <div class="sidebar-nav">
                    <h4 class="mb-4">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Procurement Analytics
                    </h4>
                    
                    <div class="nav-section">
                        <div class="nav-section-title">Dashboard Views</div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom active" onclick="showSection('overview')">
                                <i class="fas fa-tachometer-alt"></i>
                                Executive Overview
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom" onclick="showSection('requisitions_analytics')">
                                <i class="fas fa-clipboard-list"></i>
                                Requisitions Analytics
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom" onclick="showSection('supplier_performance')">
                                <i class="fas fa-users"></i>
                                Supplier Performance
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom" onclick="showSection('spend_analysis')">
                                <i class="fas fa-dollar-sign"></i>
                                Spend Analysis
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom" onclick="showSection('contract_management')">
                                <i class="fas fa-file-contract"></i>
                                Contract Analytics
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link-custom" onclick="showSection('forecasting')">
                                <i class="fas fa-chart-area"></i>
                                Forecasting & Trends
                            </a>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Quick Actions</div>
                        <div class="d-grid gap-2">
                            <a href="procurement_system.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Go to Procurement System
                            </a>
                            <button class="btn btn-outline-success" onclick="exportReport()">
                                <i class="fas fa-download me-2"></i>Export Analytics
                            </button>
                            <button class="btn btn-outline-info" onclick="refreshDashboard()">
                                <i class="fas fa-sync me-2"></i>Refresh Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-1">Procurement Analytics Dashboard</h1>
                        <p class="text-muted">Comprehensive procurement performance and insights</p>
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="periodSelector" onchange="updatePeriod()">
                            <option value="30">Last 30 Days</option>
                            <option value="90" selected>Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                        <button class="btn btn-primary" onclick="generateCustomReport()">
                            <i class="fas fa-chart-bar me-2"></i>Custom Report
                        </button>
                    </div>
                </div>

                <!-- Executive Overview Section -->
                <div id="overview" class="dashboard-section active">
                    <!-- Key Metrics Row -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card">
                                <i class="fas fa-clipboard-list metric-icon"></i>
                                <div class="metric-label">Total Requisitions</div>
                                <div class="metric-value"><?= $analytics['requisitions']['total_requisitions'] ?? 0 ?></div>
                                <div class="metric-change">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    <?= $analytics['requisitions']['approved_count'] ?? 0 ?> Approved
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card success">
                                <i class="fas fa-users metric-icon"></i>
                                <div class="metric-label">Active Suppliers</div>
                                <div class="metric-value"><?= $analytics['suppliers']['active_suppliers'] ?? 0 ?></div>
                                <div class="metric-change">
                                    Rating: <?= number_format($analytics['suppliers']['avg_supplier_rating'] ?? 0, 1) ?>/10
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card warning">
                                <i class="fas fa-file-contract metric-icon"></i>
                                <div class="metric-label">Active Contracts</div>
                                <div class="metric-value"><?= $analytics['contracts']['active_contracts'] ?? 0 ?></div>
                                <div class="metric-change">
                                    <?= $analytics['contracts']['expiring_contracts'] ?? 0 ?> Expiring Soon
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card info">
                                <i class="fas fa-dollar-sign metric-icon"></i>
                                <div class="metric-label">Contract Value</div>
                                <div class="metric-value">₹<?= number_format($analytics['contracts']['active_contract_value'] ?? 0, 0) ?></div>
                                <div class="metric-change">
                                    <i class="fas fa-chart-line me-1"></i>
                                    Active Portfolio
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-chart-line"></i>
                                    Monthly Spend Trend
                                </div>
                                <canvas id="spendTrendChart" height="300"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-pie-chart"></i>
                                    Requisition Status
                                </div>
                                <canvas id="requisitionStatusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-12">
                            <div class="analytics-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        Recent Procurement Activity
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-container">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Reference</th>
                                                    <th>Department</th>
                                                    <th>Priority</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="recentActivityTable">
                                                <!-- Dynamic content will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requisitions Analytics Section -->
                <div id="requisitions_analytics" class="dashboard-section">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-clipboard-check"></i>
                                    Requisition Approval Flow
                                </div>
                                <canvas id="approvalFlowChart" height="300"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-clock"></i>
                                    Average Processing Time
                                </div>
                                <canvas id="processingTimeChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-building"></i>
                                    Department-wise Requisition Analysis
                                </div>
                                <canvas id="departmentAnalysisChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Performance Section -->
                <div id="supplier_performance" class="dashboard-section">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="analytics-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-star text-warning me-2"></i>
                                        Supplier Performance Rankings
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-container">
                                        <table class="table table-hover mb-0" id="supplierPerformanceTable">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Supplier</th>
                                                    <th>Orders</th>
                                                    <th>Total Value</th>
                                                    <th>Quality Score</th>
                                                    <th>Delivery Score</th>
                                                    <th>Overall Rating</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-trophy"></i>
                                    Performance Distribution
                                </div>
                                <canvas id="performanceDistributionChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spend Analysis Section -->
                <div id="spend_analysis" class="dashboard-section">
                    <div class="row mb-4">
                        <div class="col-lg-4">
                            <div class="metric-card dark">
                                <i class="fas fa-calculator metric-icon"></i>
                                <div class="metric-label">Total Spend (YTD)</div>
                                <div class="metric-value">₹<?= number_format(array_sum(array_column($analytics['spend_trend'], 'monthly_spend')), 0) ?></div>
                                <div class="metric-change">
                                    <i class="fas fa-chart-up me-1"></i>
                                    Year to Date
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="metric-card success">
                                <i class="fas fa-piggy-bank metric-icon"></i>
                                <div class="metric-label">Cost Savings</div>
                                <div class="metric-value">₹<?= number_format(rand(50000, 200000)) ?></div>
                                <div class="metric-change">
                                    <i class="fas fa-percentage me-1"></i>
                                    12% vs Budget
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="metric-card warning">
                                <i class="fas fa-exclamation-triangle metric-icon"></i>
                                <div class="metric-label">Budget Utilization</div>
                                <div class="metric-value">78%</div>
                                <div class="metric-change">
                                    <i class="fas fa-calendar me-1"></i>
                                    22% Remaining
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-chart-bar"></i>
                                    Spend by Category
                                </div>
                                <canvas id="spendCategoryChart" height="300"></canvas>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="chart-container">
                                <div class="chart-title">
                                    <i class="fas fa-chart-area"></i>
                                    Spend Variance Analysis
                                </div>
                                <canvas id="spendVarianceChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentSection = 'overview';
        let charts = {};

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadRecentActivity();
        });

        // Section Navigation
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName).classList.add('active');
            
            // Update navigation
            document.querySelectorAll('.nav-link-custom').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
            
            currentSection = sectionName;
            
            // Load section-specific data
            loadSectionData(sectionName);
        }

        function loadSectionData(section) {
            switch(section) {
                case 'supplier_performance':
                    loadSupplierPerformance();
                    break;
                case 'requisitions_analytics':
                    loadRequisitionsAnalytics();
                    break;
                case 'spend_analysis':
                    loadSpendAnalysis();
                    break;
                // Add more cases as needed
            }
        }

        function initializeCharts() {
            // Spend Trend Chart
            const spendTrendCtx = document.getElementById('spendTrendChart').getContext('2d');
            const spendTrendData = <?= json_encode($analytics['spend_trend']) ?>;
            
            charts.spendTrend = new Chart(spendTrendCtx, {
                type: 'line',
                data: {
                    labels: spendTrendData.map(item => item.month),
                    datasets: [{
                        label: 'Monthly Spend (₹)',
                        data: spendTrendData.map(item => item.monthly_spend),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Requisition Status Chart
            const statusCtx = document.getElementById('requisitionStatusChart').getContext('2d');
            const reqData = <?= json_encode($analytics['requisitions']) ?>;
            
            charts.requisitionStatus = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Draft', 'Submitted', 'Approved', 'Rejected'],
                    datasets: [{
                        data: [
                            reqData.draft_count || 0,
                            reqData.submitted_count || 0,
                            reqData.approved_count || 0,
                            reqData.rejected_count || 0
                        ],
                        backgroundColor: [
                            '#6c757d',
                            '#ffc107',
                            '#28a745',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function loadRecentActivity() {
            fetch('procurement_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_recent_requisitions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRecentActivityTable(data.requisitions);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function updateRecentActivityTable(activities) {
            const tbody = document.getElementById('recentActivityTable');
            tbody.innerHTML = '';
            
            activities.slice(0, 10).forEach(activity => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="badge bg-primary">REQ</span></td>
                    <td><strong>${activity.requisition_number}</strong></td>
                    <td>${activity.department}</td>
                    <td>
                        <span class="priority-indicator priority-${activity.priority}"></span>
                        ${activity.priority.toUpperCase()}
                    </td>
                    <td>₹${Number(activity.total_amount || 0).toLocaleString()}</td>
                    <td><span class="status-badge bg-${getStatusColor(activity.status)}">${activity.status}</span></td>
                    <td>${new Date(activity.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary action-btn" onclick="viewDetails('${activity.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function getStatusColor(status) {
            const colors = {
                'draft': 'secondary',
                'submitted': 'warning',
                'approved': 'success',
                'rejected': 'danger'
            };
            return colors[status] || 'secondary';
        }

        function loadSupplierPerformance() {
            fetch('procurement_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_supplier_performance'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSupplierPerformanceTable(data.performance);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function updateSupplierPerformanceTable(suppliers) {
            const tbody = document.querySelector('#supplierPerformanceTable tbody');
            tbody.innerHTML = '';
            
            suppliers.forEach((supplier, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="badge bg-primary">#${index + 1}</span></td>
                    <td>
                        <strong>${supplier.supplier_name}</strong><br>
                        <small class="text-muted">${supplier.company_name}</small>
                    </td>
                    <td>${supplier.total_orders}</td>
                    <td>₹${Number(supplier.total_value).toLocaleString()}</td>
                    <td>${supplier.quality_score || 'N/A'}</td>
                    <td>${supplier.delivery_score || 'N/A'}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress me-2" style="width: 60px; height: 8px;">
                                <div class="progress-bar bg-success" style="width: ${(supplier.avg_evaluation_score || 0) * 10}%"></div>
                            </div>
                            <span>${Number(supplier.avg_evaluation_score || 0).toFixed(1)}/10</span>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function loadRequisitionsAnalytics() {
            // Create approval flow chart
            const approvalCtx = document.getElementById('approvalFlowChart').getContext('2d');
            
            if (charts.approvalFlow) {
                charts.approvalFlow.destroy();
            }
            
            charts.approvalFlow = new Chart(approvalCtx, {
                type: 'bar',
                data: {
                    labels: ['Submitted', 'Under Review', 'Approved', 'Rejected'],
                    datasets: [{
                        label: 'Requisitions',
                        data: [45, 12, 38, 7],
                        backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Processing time chart
            const processingCtx = document.getElementById('processingTimeChart').getContext('2d');
            
            if (charts.processingTime) {
                charts.processingTime.destroy();
            }
            
            charts.processingTime = new Chart(processingCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Avg Processing Days',
                        data: [5.2, 4.8, 6.1, 4.5, 5.3, 4.2],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function loadSpendAnalysis() {
            // Spend by category chart
            const categoryCtx = document.getElementById('spendCategoryChart').getContext('2d');
            
            if (charts.spendCategory) {
                charts.spendCategory.destroy();
            }
            
            charts.spendCategory = new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: ['IT Equipment', 'Office Supplies', 'Services', 'Infrastructure', 'Marketing'],
                    datasets: [{
                        label: 'Spend Amount (₹)',
                        data: [450000, 120000, 380000, 670000, 230000],
                        backgroundColor: [
                            '#667eea',
                            '#f093fb',
                            '#4facfe',
                            '#43e97b',
                            '#fa709a'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Utility functions
        function refreshDashboard() {
            location.reload();
        }

        function updatePeriod() {
            const period = document.getElementById('periodSelector').value;
            console.log('Updating period to:', period);
            // Implementation for period update
        }

        function generateCustomReport() {
            alert('Custom report generation will be implemented');
        }

        function exportReport() {
            alert('Export functionality will be implemented');
        }

        function viewDetails(id) {
            alert('View details for ID: ' + id);
        }
    </script>
</body>
</html>
