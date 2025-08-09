<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../db.php';

// Create procurement tables if they don't exist
$procurement_tables = [
    "CREATE TABLE IF NOT EXISTS procurement_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES procurement_categories(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS purchase_requisitions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        requisition_number VARCHAR(50) UNIQUE NOT NULL,
        department VARCHAR(100),
        requested_by INT,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('draft', 'submitted', 'approved', 'rejected', 'converted_to_po') DEFAULT 'draft',
        total_amount DECIMAL(15,2) DEFAULT 0,
        required_by_date DATE,
        justification TEXT,
        approval_notes TEXT,
        approved_by INT,
        approved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS requisition_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        requisition_id INT NOT NULL,
        item_description VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2),
        total_price DECIMAL(12,2),
        category_id INT,
        specifications TEXT,
        supplier_preference VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES procurement_categories(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS vendor_evaluations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        supplier_id INT NOT NULL,
        evaluation_period VARCHAR(50),
        quality_score INT CHECK (quality_score BETWEEN 1 AND 10),
        delivery_score INT CHECK (delivery_score BETWEEN 1 AND 10),
        price_competitiveness INT CHECK (price_competitiveness BETWEEN 1 AND 10),
        communication_score INT CHECK (communication_score BETWEEN 1 AND 10),
        overall_score DECIMAL(3,2),
        comments TEXT,
        evaluated_by INT,
        evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS rfq_requests (
        id INT PRIMARY KEY AUTO_INCREMENT,
        rfq_number VARCHAR(50) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('draft', 'sent', 'responses_received', 'evaluated', 'awarded', 'closed') DEFAULT 'draft',
        deadline DATE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS rfq_suppliers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        rfq_id INT NOT NULL,
        supplier_id INT NOT NULL,
        sent_at TIMESTAMP NULL,
        response_received BOOLEAN DEFAULT FALSE,
        response_date TIMESTAMP NULL,
        quoted_amount DECIMAL(15,2) NULL,
        delivery_terms TEXT,
        payment_terms TEXT,
        notes TEXT,
        is_selected BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (rfq_id) REFERENCES rfq_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS contract_management (
        id INT PRIMARY KEY AUTO_INCREMENT,
        contract_number VARCHAR(50) UNIQUE NOT NULL,
        supplier_id INT NOT NULL,
        contract_type ENUM('purchase_agreement', 'service_agreement', 'framework_agreement', 'nda') NOT NULL,
        title VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        value DECIMAL(15,2),
        currency VARCHAR(3) DEFAULT 'INR',
        status ENUM('draft', 'active', 'expired', 'terminated', 'renewed') DEFAULT 'draft',
        auto_renewal BOOLEAN DEFAULT FALSE,
        renewal_notice_days INT DEFAULT 30,
        terms_conditions TEXT,
        attachments JSON,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS spend_analysis (
        id INT PRIMARY KEY AUTO_INCREMENT,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        department VARCHAR(100),
        category_id INT,
        supplier_id INT,
        total_spend DECIMAL(15,2) NOT NULL,
        transaction_count INT DEFAULT 0,
        average_order_value DECIMAL(10,2),
        cost_savings DECIMAL(10,2) DEFAULT 0,
        analysis_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES procurement_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS inventory_forecasting (
        id INT PRIMARY KEY AUTO_INCREMENT,
        item_id INT,
        item_name VARCHAR(255) NOT NULL,
        current_stock INT DEFAULT 0,
        forecasted_demand INT NOT NULL,
        forecast_period VARCHAR(50) NOT NULL,
        reorder_point INT,
        optimal_order_quantity INT,
        lead_time_days INT DEFAULT 7,
        safety_stock INT DEFAULT 0,
        forecast_accuracy DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS procurement_approvals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_type ENUM('requisition', 'purchase_order', 'contract', 'vendor') NOT NULL,
        request_id INT NOT NULL,
        approval_level INT NOT NULL,
        approver_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'delegated') DEFAULT 'pending',
        comments TEXT,
        approved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request_type_id (request_type, request_id),
        INDEX idx_approver_status (approver_id, status)
    )"
];

foreach ($procurement_tables as $sql) {
    mysqli_query($conn, $sql);
}

// Get dashboard statistics
$stats = [];

// Purchase requisitions
$req_query = "SELECT 
    COUNT(*) as total_requisitions,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_approvals,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requisitions,
    SUM(total_amount) as total_requisition_value
    FROM purchase_requisitions";
$req_result = mysqli_query($conn, $req_query);
$req_stats = mysqli_fetch_assoc($req_result) ?: [];

// RFQ statistics
$rfq_query = "SELECT 
    COUNT(*) as total_rfqs,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as active_rfqs,
    SUM(CASE WHEN status = 'evaluated' THEN 1 ELSE 0 END) as completed_rfqs
    FROM rfq_requests";
$rfq_result = mysqli_query($conn, $rfq_query);
$rfq_stats = mysqli_fetch_assoc($rfq_result) ?: [];

// Contract statistics
$contract_query = "SELECT 
    COUNT(*) as total_contracts,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contracts,
    SUM(CASE WHEN status = 'active' THEN value ELSE 0 END) as active_contract_value,
    SUM(CASE WHEN end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active' THEN 1 ELSE 0 END) as expiring_soon
    FROM contract_management";
$contract_result = mysqli_query($conn, $contract_query);
$contract_stats = mysqli_fetch_assoc($contract_result) ?: [];

// Spend analysis
$spend_query = "SELECT 
    SUM(total_spend) as total_spend_this_month,
    COUNT(DISTINCT supplier_id) as active_suppliers_count
    FROM spend_analysis 
    WHERE period_start >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
$spend_result = mysqli_query($conn, $spend_query);
$spend_stats = mysqli_fetch_assoc($spend_result) ?: [];

$stats = array_merge($req_stats, $rfq_stats, $contract_stats, $spend_stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Procurement & Supply Chain Management - Billbook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 0;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .procurement-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .procurement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
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
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .metric-label {
            opacity: 0.9;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 3rem;
            opacity: 0.3;
        }
        .sidebar-nav {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .nav-section {
            margin-bottom: 25px;
        }
        .nav-section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .nav-item {
            margin: 8px 0;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #374151;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .nav-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
            text-decoration: none;
        }
        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }
        .content-section {
            display: none;
            animation: fadeInUp 0.5s ease;
        }
        .content-section.active {
            display: block;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #374151;
            padding: 15px;
        }
        .table td {
            border: none;
            padding: 15px;
            vertical-align: middle;
        }
        .table tbody tr {
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-draft { background: #e5e7eb; color: #6b7280; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-expired { background: #fee2e2; color: #991b1b; }
        .priority-low { color: #10b981; }
        .priority-medium { color: #f59e0b; }
        .priority-high { color: #ef4444; }
        .priority-urgent { color: #dc2626; font-weight: bold; }
        .form-floating {
            margin-bottom: 1rem;
        }
        .btn-group-custom {
            gap: 8px;
        }
        .btn-custom {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3">
                <div class="sidebar-nav">
                    <h5 class="mb-4">
                        <i class="fas fa-shopping-cart text-primary me-2"></i>
                        Procurement Management
                    </h5>
                    
                    <div class="nav-section">
                        <div class="nav-section-title">Overview</div>
                        <div class="nav-item">
                            <a href="#" class="nav-link active" onclick="showSection('dashboard')">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('analytics')">
                                <i class="fas fa-chart-line"></i>
                                Analytics & Reports
                            </a>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Procurement Process</div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('requisitions')">
                                <i class="fas fa-clipboard-list"></i>
                                Purchase Requisitions
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('rfq')">
                                <i class="fas fa-file-invoice"></i>
                                RFQ Management
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('purchase_orders')">
                                <i class="fas fa-shopping-bag"></i>
                                Purchase Orders
                            </a>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Vendor Management</div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('vendor_evaluation')">
                                <i class="fas fa-star"></i>
                                Vendor Evaluation
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('contracts')">
                                <i class="fas fa-file-contract"></i>
                                Contract Management
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('vendor_performance')">
                                <i class="fas fa-chart-bar"></i>
                                Performance Analytics
                            </a>
                        </div>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Advanced Features</div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('spend_analysis')">
                                <i class="fas fa-dollar-sign"></i>
                                Spend Analysis
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('inventory_forecasting')">
                                <i class="fas fa-chart-area"></i>
                                Inventory Forecasting
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#" class="nav-link" onclick="showSection('approval_workflows')">
                                <i class="fas fa-route"></i>
                                Approval Workflows
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section active">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Procurement Dashboard</h2>
                            <p class="text-muted">Comprehensive procurement and supply chain overview</p>
                        </div>
                        <div class="btn-group-custom d-flex">
                            <button class="btn btn-primary-custom" onclick="refreshDashboard()">
                                <i class="fas fa-sync me-2"></i>Refresh
                            </button>
                            <button class="btn btn-success" onclick="showSection('requisitions')">
                                <i class="fas fa-plus me-2"></i>New Requisition
                            </button>
                        </div>
                    </div>

                    <!-- Key Metrics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card">
                                <i class="fas fa-clipboard-list metric-icon"></i>
                                <div class="metric-label">Total Requisitions</div>
                                <div class="metric-value"><?= $stats['total_requisitions'] ?? 0 ?></div>
                                <small><?= $stats['pending_approvals'] ?? 0 ?> Pending Approval</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card success">
                                <i class="fas fa-file-invoice metric-icon"></i>
                                <div class="metric-label">Active RFQs</div>
                                <div class="metric-value"><?= $stats['active_rfqs'] ?? 0 ?></div>
                                <small><?= $stats['completed_rfqs'] ?? 0 ?> Completed</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card warning">
                                <i class="fas fa-file-contract metric-icon"></i>
                                <div class="metric-label">Active Contracts</div>
                                <div class="metric-value"><?= $stats['active_contracts'] ?? 0 ?></div>
                                <small><?= $stats['expiring_soon'] ?? 0 ?> Expiring Soon</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="metric-card info">
                                <i class="fas fa-dollar-sign metric-icon"></i>
                                <div class="metric-label">Monthly Spend</div>
                                <div class="metric-value">₹<?= number_format($stats['total_spend_this_month'] ?? 0) ?></div>
                                <small><?= $stats['active_suppliers_count'] ?? 0 ?> Active Suppliers</small>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="procurement-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">Recent Purchase Requisitions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-container">
                                        <table class="table table-hover mb-0" id="recentRequisitionsTable">
                                            <thead>
                                                <tr>
                                                    <th>Requisition #</th>
                                                    <th>Department</th>
                                                    <th>Priority</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="procurement-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary-custom" onclick="createNewRequisition()">
                                            <i class="fas fa-plus me-2"></i>Create Requisition
                                        </button>
                                        <button class="btn btn-success" onclick="createNewRFQ()">
                                            <i class="fas fa-file-invoice me-2"></i>Create RFQ
                                        </button>
                                        <button class="btn btn-info text-white" onclick="viewContracts()">
                                            <i class="fas fa-file-contract me-2"></i>View Contracts
                                        </button>
                                        <button class="btn btn-warning" onclick="generateReports()">
                                            <i class="fas fa-chart-bar me-2"></i>Generate Reports
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="procurement-card mt-3">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">Upcoming Deadlines</h5>
                                </div>
                                <div class="card-body" id="upcomingDeadlines">
                                    <!-- Dynamic content will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Requisitions Section -->
                <div id="requisitions" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Purchase Requisitions</h2>
                            <p class="text-muted">Create and manage purchase requisitions</p>
                        </div>
                        <button class="btn btn-primary-custom" onclick="openRequisitionModal()">
                            <i class="fas fa-plus me-2"></i>New Requisition
                        </button>
                    </div>

                    <div class="procurement-card">
                        <div class="card-body">
                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select class="form-select" id="statusFilter" onchange="filterRequisitions()">
                                        <option value="">All Statuses</option>
                                        <option value="draft">Draft</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="priorityFilter" onchange="filterRequisitions()">
                                        <option value="">All Priorities</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" id="searchRequisitions" placeholder="Search requisitions..." onkeyup="searchRequisitions()">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-primary w-100" onclick="loadRequisitions()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="table-container">
                                <table class="table table-hover mb-0" id="requisitionsTable">
                                    <thead>
                                        <tr>
                                            <th>Requisition #</th>
                                            <th>Department</th>
                                            <th>Requested By</th>
                                            <th>Priority</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Required By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic content will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RFQ Management Section -->
                <div id="rfq" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">RFQ Management</h2>
                            <p class="text-muted">Request for Quotation management and supplier responses</p>
                        </div>
                        <button class="btn btn-primary-custom" onclick="openRFQModal()">
                            <i class="fas fa-plus me-2"></i>Create RFQ
                        </button>
                    </div>

                    <div class="procurement-card">
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table table-hover mb-0" id="rfqTable">
                                    <thead>
                                        <tr>
                                            <th>RFQ #</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Suppliers</th>
                                            <th>Responses</th>
                                            <th>Deadline</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic content will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract Management Section -->
                <div id="contracts" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Contract Management</h2>
                            <p class="text-muted">Manage supplier contracts and agreements</p>
                        </div>
                        <button class="btn btn-primary-custom" onclick="openContractModal()">
                            <i class="fas fa-plus me-2"></i>New Contract
                        </button>
                    </div>

                    <div class="procurement-card">
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table table-hover mb-0" id="contractsTable">
                                    <thead>
                                        <tr>
                                            <th>Contract #</th>
                                            <th>Supplier</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic content will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spend Analysis Section -->
                <div id="spend_analysis" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Spend Analysis</h2>
                            <p class="text-muted">Analyze procurement spending patterns and cost savings</p>
                        </div>
                        <button class="btn btn-primary-custom" onclick="generateSpendReport()">
                            <i class="fas fa-chart-bar me-2"></i>Generate Report
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="chart-container">
                                <h5 class="mb-3">Monthly Spend Trend</h5>
                                <canvas id="spendTrendChart" height="300"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="chart-container">
                                <h5 class="mb-3">Spend by Category</h5>
                                <canvas id="spendCategoryChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading procurement data...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals will be added here -->
    <!-- Requisition Modal -->
    <div class="modal fade" id="requisitionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Create Purchase Requisition
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="requisitionForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="reqDepartment" name="department" required>
                                    <label for="reqDepartment">Department</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="reqPriority" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <label for="reqPriority">Priority</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="date" class="form-control" id="reqRequiredBy" name="required_by_date" required>
                            <label for="reqRequiredBy">Required By Date</label>
                        </div>
                        
                        <div class="form-floating">
                            <textarea class="form-control" id="reqJustification" name="justification" style="height: 100px;" required></textarea>
                            <label for="reqJustification">Justification</label>
                        </div>

                        <h6 class="mt-4 mb-3">Items Required</h6>
                        <div id="requisitionItems">
                            <div class="row item-row mb-2">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="item_description[]" placeholder="Item Description" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control" name="unit_price[]" placeholder="Unit Price" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control total-price" readonly placeholder="Total">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-primary" onclick="addRequisitionItem()">
                                    <i class="fas fa-plus me-2"></i>Add Item
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <h5>Total: ₹<span id="requisitionTotal">0.00</span></h5>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" onclick="saveRequisitionDraft()">Save Draft</button>
                    <button type="button" class="btn btn-primary-custom" onclick="submitRequisition()">Submit for Approval</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Global variables
        let currentSection = 'dashboard';

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
        });

        // Section Navigation
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName).classList.add('active');
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
            
            currentSection = sectionName;
            
            // Load section-specific data
            loadSectionData(sectionName);
        }

        function loadSectionData(section) {
            switch(section) {
                case 'dashboard':
                    loadDashboardData();
                    break;
                case 'requisitions':
                    loadRequisitions();
                    break;
                case 'rfq':
                    loadRFQs();
                    break;
                case 'contracts':
                    loadContracts();
                    break;
                case 'spend_analysis':
                    loadSpendAnalysis();
                    break;
            }
        }

        function loadDashboardData() {
            showLoading();
            
            // Load recent requisitions
            fetch('procurement_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_recent_requisitions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRecentRequisitionsTable(data.requisitions);
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
            });

            // Load upcoming deadlines
            loadUpcomingDeadlines();
        }

        function updateRecentRequisitionsTable(requisitions) {
            const tbody = document.querySelector('#recentRequisitionsTable tbody');
            tbody.innerHTML = '';
            
            requisitions.forEach(req => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${req.requisition_number}</strong></td>
                    <td>${req.department}</td>
                    <td><span class="priority-${req.priority}">${req.priority.toUpperCase()}</span></td>
                    <td>₹${Number(req.total_amount || 0).toLocaleString()}</td>
                    <td><span class="status-badge status-${req.status}">${req.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewRequisition(${req.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function loadUpcomingDeadlines() {
            // Mock data for upcoming deadlines
            const deadlines = [
                { type: 'RFQ', title: 'Office Supplies RFQ', date: '2025-08-15', urgent: true },
                { type: 'Contract', title: 'IT Services Contract', date: '2025-08-20', urgent: false },
                { type: 'Requisition', title: 'Marketing Materials', date: '2025-08-25', urgent: false }
            ];

            const container = document.getElementById('upcomingDeadlines');
            container.innerHTML = '';

            deadlines.forEach(deadline => {
                const item = document.createElement('div');
                item.className = 'border-start border-3 border-primary ps-3 mb-3';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${deadline.title}</h6>
                            <small class="text-muted">${deadline.type}</small>
                        </div>
                        <small class="${deadline.urgent ? 'text-danger fw-bold' : 'text-muted'}">${deadline.date}</small>
                    </div>
                `;
                container.appendChild(item);
            });
        }

        function loadRequisitions() {
            showLoading();
            
            fetch('procurement_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_all_requisitions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRequisitionsTable(data.requisitions);
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
            });
        }

        function updateRequisitionsTable(requisitions) {
            const tbody = document.querySelector('#requisitionsTable tbody');
            tbody.innerHTML = '';
            
            requisitions.forEach(req => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${req.requisition_number}</strong></td>
                    <td>${req.department}</td>
                    <td>${req.requested_by || 'System'}</td>
                    <td><span class="priority-${req.priority}">${req.priority.toUpperCase()}</span></td>
                    <td>₹${Number(req.total_amount || 0).toLocaleString()}</td>
                    <td><span class="status-badge status-${req.status}">${req.status}</span></td>
                    <td>${req.required_by_date || 'N/A'}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewRequisition(${req.id})" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="approveRequisition(${req.id})" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editRequisition(${req.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Requisition Modal Functions
        function openRequisitionModal() {
            // Generate requisition number
            const reqNumber = 'REQ-' + Date.now().toString().substr(-6);
            document.getElementById('requisitionModal').querySelector('.modal-title').innerHTML = `
                <i class="fas fa-clipboard-list me-2"></i>Create Purchase Requisition (${reqNumber})
            `;
            
            new bootstrap.Modal(document.getElementById('requisitionModal')).show();
        }

        function addRequisitionItem() {
            const container = document.getElementById('requisitionItems');
            const newRow = document.createElement('div');
            newRow.className = 'row item-row mb-2';
            newRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" class="form-control" name="item_description[]" placeholder="Item Description" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="1" required onchange="calculateItemTotal(this)">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="unit_price[]" placeholder="Unit Price" step="0.01" min="0" onchange="calculateItemTotal(this)">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control total-price" readonly placeholder="Total">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }

        function removeItem(button) {
            button.closest('.item-row').remove();
            calculateRequisitionTotal();
        }

        function calculateItemTotal(input) {
            const row = input.closest('.item-row');
            const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
            const unitPrice = parseFloat(row.querySelector('input[name="unit_price[]"]').value) || 0;
            const total = quantity * unitPrice;
            
            row.querySelector('.total-price').value = total.toFixed(2);
            calculateRequisitionTotal();
        }

        function calculateRequisitionTotal() {
            let total = 0;
            document.querySelectorAll('.total-price').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('requisitionTotal').textContent = total.toFixed(2);
        }

        function saveRequisitionDraft() {
            submitRequisitionForm('draft');
        }

        function submitRequisition() {
            submitRequisitionForm('submitted');
        }

        function submitRequisitionForm(status) {
            const formData = new FormData(document.getElementById('requisitionForm'));
            formData.append('action', 'create_requisition');
            formData.append('status', status);
            
            fetch('procurement_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('requisitionModal')).hide();
                    showAlert('Requisition ' + (status === 'draft' ? 'saved as draft' : 'submitted') + ' successfully!', 'success');
                    loadRequisitions();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error submitting requisition', 'error');
            });
        }

        // Quick Action Functions
        function createNewRequisition() {
            openRequisitionModal();
        }

        function createNewRFQ() {
            showSection('rfq');
            // Future implementation
        }

        function viewContracts() {
            showSection('contracts');
        }

        function generateReports() {
            showSection('analytics');
        }

        function refreshDashboard() {
            loadDashboardData();
        }

        // Utility Functions
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        function showAlert(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
        }

        // Stub functions for future implementation
        function viewRequisition(id) {
            showAlert('View requisition functionality will be implemented', 'info');
        }

        function approveRequisition(id) {
            showAlert('Approve requisition functionality will be implemented', 'info');
        }

        function editRequisition(id) {
            showAlert('Edit requisition functionality will be implemented', 'info');
        }

        function loadRFQs() {
            showAlert('RFQ management will be implemented', 'info');
        }

        function loadContracts() {
            showAlert('Contract management will be implemented', 'info');
        }

        function loadSpendAnalysis() {
            showAlert('Spend analysis will be implemented', 'info');
        }

        // Add event listeners for item calculations
        document.addEventListener('change', function(e) {
            if (e.target.matches('input[name="quantity[]"], input[name="unit_price[]"]')) {
                calculateItemTotal(e.target);
            }
        });
    </script>
</body>
</html>
