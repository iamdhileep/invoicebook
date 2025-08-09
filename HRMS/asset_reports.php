<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Asset Reports';

// Ensure all required tables exist (from asset management system)
$createCategoriesTable = "CREATE TABLE IF NOT EXISTS asset_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createCategoriesTable);

$createAssetsTable = "CREATE TABLE IF NOT EXISTS company_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    asset_type VARCHAR(100),
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    purchase_date DATE,
    purchase_cost DECIMAL(12,2),
    warranty_expiry DATE,
    current_value DECIMAL(12,2),
    location VARCHAR(200),
    supplier VARCHAR(200),
    notes TEXT,
    condition_status ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Needs Repair') DEFAULT 'Good',
    status ENUM('Available', 'Allocated', 'Under Maintenance', 'Retired', 'Lost/Stolen') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id)
) ENGINE=InnoDB";
mysqli_query($conn, $createAssetsTable);

$createAllocationsTable = "CREATE TABLE IF NOT EXISTS asset_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_id INT NOT NULL,
    allocated_date DATE NOT NULL,
    return_date DATE NULL,
    allocated_by INT NOT NULL,
    returned_by INT NULL,
    allocation_reason VARCHAR(200),
    return_reason VARCHAR(200),
    condition_when_allocated ENUM('Excellent', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
    condition_when_returned ENUM('Excellent', 'Good', 'Fair', 'Poor') NULL,
    notes TEXT,
    status ENUM('Active', 'Returned', 'Lost', 'Damaged') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES company_assets(id) ON DELETE CASCADE,
    INDEX idx_employee (employee_id),
    INDEX idx_asset (asset_id),
    INDEX idx_status (status)
) ENGINE=InnoDB";
mysqli_query($conn, $createAllocationsTable);

$createEmployeesTable = "CREATE TABLE IF NOT EXISTS hr_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20),
    position VARCHAR(100),
    department VARCHAR(100),
    date_of_joining DATE,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createEmployeesTable);

// Insert sample data if tables are empty
$checkCategories = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_categories");
if ($checkCategories && mysqli_fetch_assoc($checkCategories)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO asset_categories (name, description) VALUES 
        ('IT Equipment', 'Computers, laptops, phones, tablets, etc.'),
        ('Office Furniture', 'Desks, chairs, cabinets, meeting tables'),
        ('Vehicles', 'Company cars, trucks, and other vehicles'),
        ('Tools & Equipment', 'Work tools, machinery, and specialized equipment'),
        ('Software & Licenses', 'Software licenses and digital assets')");
}

$checkEmployees = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees");
if ($checkEmployees && mysqli_fetch_assoc($checkEmployees)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department, date_of_joining) VALUES 
        ('EMP001', 'John', 'Doe', 'john.doe@company.com', 'Software Developer', 'IT', '2023-01-15'),
        ('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', 'Marketing Manager', 'Marketing', '2022-11-20'),
        ('EMP003', 'Mike', 'Johnson', 'mike.johnson@company.com', 'HR Specialist', 'Human Resources', '2023-03-10'),
        ('EMP004', 'Sarah', 'Wilson', 'sarah.wilson@company.com', 'Finance Analyst', 'Finance', '2022-08-05'),
        ('EMP005', 'David', 'Brown', 'david.brown@company.com', 'Operations Manager', 'Operations', '2021-12-01')");
}

$checkAssets = mysqli_query($conn, "SELECT COUNT(*) as count FROM company_assets");
if ($checkAssets && mysqli_fetch_assoc($checkAssets)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO company_assets (asset_tag, name, category_id, asset_type, brand, model, purchase_date, purchase_cost, current_value, location, condition_status, status) VALUES 
        ('LAPTOP001', 'Dell Inspiron Laptop', 1, 'Laptop', 'Dell', 'Inspiron 15 3000', '2023-01-01', 899.99, 720.00, 'Office Floor 1', 'Good', 'Allocated'),
        ('PHONE001', 'iPhone 13', 1, 'Mobile Phone', 'Apple', 'iPhone 13', '2023-02-15', 699.99, 580.00, 'Office Floor 2', 'Excellent', 'Available'),
        ('DESK001', 'Office Desk', 2, 'Desk', 'IKEA', 'BEKANT', '2022-12-01', 149.99, 120.00, 'Office Floor 1', 'Good', 'Available'),
        ('CHAIR001', 'Office Chair', 2, 'Chair', 'Herman Miller', 'Aeron', '2022-10-15', 1200.00, 950.00, 'Office Floor 2', 'Excellent', 'Allocated'),
        ('TABLET001', 'iPad Pro', 1, 'Tablet', 'Apple', 'iPad Pro 11-inch', '2023-03-20', 899.99, 750.00, 'Office Floor 1', 'Good', 'Available'),
        ('PRINTER001', 'LaserJet Printer', 1, 'Printer', 'HP', 'LaserJet Pro M404n', '2022-09-10', 299.99, 220.00, 'Office Floor 1', 'Good', 'Available')");
}

// Add some sample allocations
$checkAllocations = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocations");
if ($checkAllocations && mysqli_fetch_assoc($checkAllocations)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO asset_allocations (asset_id, employee_id, allocated_date, allocated_by, allocation_reason, condition_when_allocated, status) VALUES 
        (1, 1, '2023-06-01', 1, 'Work laptop assignment', 'Good', 'Active'),
        (4, 2, '2023-06-15', 1, 'Ergonomic chair for daily work', 'Excellent', 'Active')");
}

// Handle AJAX requests for report generation and exports
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'export_csv':
            $report_type = $_POST['report_type'];
            $filename = "asset_report_" . $report_type . "_" . date('Y-m-d') . ".csv";
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            switch ($report_type) {
                case 'inventory':
                    fputcsv($output, ['Asset Tag', 'Name', 'Category', 'Brand', 'Model', 'Purchase Date', 'Purchase Cost', 'Current Value', 'Status', 'Condition', 'Location']);
                    
                    $query = "SELECT ca.asset_tag, ca.name, COALESCE(ac.name, 'Uncategorized') as category_name, 
                              ca.brand, ca.model, ca.purchase_date, ca.purchase_cost, ca.current_value, 
                              ca.status, ca.condition_status, ca.location
                              FROM company_assets ca 
                              LEFT JOIN asset_categories ac ON ca.category_id = ac.id 
                              ORDER BY ca.asset_tag";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            fputcsv($output, $row);
                        }
                    }
                    break;
                    
                case 'allocations':
                    fputcsv($output, ['Asset Tag', 'Asset Name', 'Employee', 'Department', 'Allocated Date', 'Days Allocated', 'Status', 'Condition']);
                    
                    $query = "SELECT ca.asset_tag, ca.name as asset_name, 
                              CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name,
                              COALESCE(e.department, 'N/A') as department,
                              aa.allocated_date, 
                              DATEDIFF(COALESCE(aa.return_date, CURDATE()), aa.allocated_date) as days_allocated,
                              aa.status, aa.condition_when_allocated
                              FROM asset_allocations aa
                              LEFT JOIN company_assets ca ON aa.asset_id = ca.id
                              LEFT JOIN hr_employees e ON aa.employee_id = e.id
                              ORDER BY aa.allocated_date DESC";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            fputcsv($output, $row);
                        }
                    }
                    break;
                    
                case 'depreciation':
                    fputcsv($output, ['Asset Tag', 'Name', 'Purchase Date', 'Purchase Cost', 'Current Value', 'Depreciation', 'Age (Months)']);
                    
                    $query = "SELECT ca.asset_tag, ca.name, ca.purchase_date, ca.purchase_cost, ca.current_value,
                              (ca.purchase_cost - ca.current_value) as depreciation,
                              TIMESTAMPDIFF(MONTH, ca.purchase_date, CURDATE()) as age_months
                              FROM company_assets ca 
                              WHERE ca.purchase_date IS NOT NULL AND ca.purchase_cost > 0
                              ORDER BY depreciation DESC";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            fputcsv($output, $row);
                        }
                    }
                    break;
            }
            
            fclose($output);
            exit;
            
        case 'generate_report':
            $report_type = $_POST['report_type'];
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';
            $department = $_POST['department'] ?? '';
            $category = $_POST['category'] ?? '';
            
            $data = [];
            
            switch ($report_type) {
                case 'utilization':
                    $query = "SELECT 
                                COUNT(*) as total_assets,
                                SUM(CASE WHEN ca.status = 'Allocated' THEN 1 ELSE 0 END) as allocated_assets,
                                SUM(CASE WHEN ca.status = 'Available' THEN 1 ELSE 0 END) as available_assets,
                                SUM(CASE WHEN ca.status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_assets,
                                SUM(CASE WHEN ca.status = 'Retired' THEN 1 ELSE 0 END) as retired_assets,
                                SUM(CASE WHEN ca.status = 'Lost/Stolen' THEN 1 ELSE 0 END) as lost_assets,
                                ROUND((SUM(CASE WHEN ca.status = 'Allocated' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as utilization_rate
                              FROM company_assets ca";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        $data = mysqli_fetch_assoc($result);
                    }
                    break;
                    
                case 'cost_analysis':
                    $query = "SELECT 
                                ac.name as category,
                                COUNT(ca.id) as asset_count,
                                SUM(ca.purchase_cost) as total_purchase_cost,
                                SUM(ca.current_value) as total_current_value,
                                SUM(ca.purchase_cost - ca.current_value) as total_depreciation,
                                AVG(ca.purchase_cost) as avg_purchase_cost
                              FROM company_assets ca
                              LEFT JOIN asset_categories ac ON ca.category_id = ac.id
                              GROUP BY ca.category_id, ac.name
                              ORDER BY total_purchase_cost DESC";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $data[] = $row;
                        }
                    }
                    break;
                    
                case 'allocation_summary':
                    $whereClause = "WHERE 1=1";
                    
                    if ($date_from) $whereClause .= " AND aa.allocated_date >= '$date_from'";
                    if ($date_to) $whereClause .= " AND aa.allocated_date <= '$date_to'";
                    if ($department) $whereClause .= " AND e.department = '$department'";
                    
                    $query = "SELECT 
                                e.department,
                                COUNT(aa.id) as total_allocations,
                                COUNT(CASE WHEN aa.status = 'Active' THEN 1 END) as active_allocations,
                                COUNT(CASE WHEN aa.status = 'Returned' THEN 1 END) as returned_allocations,
                                AVG(DATEDIFF(COALESCE(aa.return_date, CURDATE()), aa.allocated_date)) as avg_allocation_days
                              FROM asset_allocations aa
                              LEFT JOIN hr_employees e ON aa.employee_id = e.id
                              $whereClause
                              GROUP BY e.department
                              ORDER BY total_allocations DESC";
                    
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $data[] = $row;
                        }
                    }
                    break;
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
    }
}

// Get filter options
$departments = mysqli_query($conn, "SELECT DISTINCT department FROM hr_employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
$categories = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY name");

// Get overview statistics
$stats = [];

// Asset overview
$result1 = mysqli_query($conn, "SELECT COUNT(*) as count FROM company_assets");
$stats['total_assets'] = $result1 ? mysqli_fetch_assoc($result1)['count'] : 0;

$result2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM company_assets WHERE status = 'Allocated'");
$stats['allocated_assets'] = $result2 ? mysqli_fetch_assoc($result2)['count'] : 0;

$result3 = mysqli_query($conn, "SELECT SUM(purchase_cost) as total FROM company_assets WHERE purchase_cost IS NOT NULL");
$stats['total_value'] = $result3 ? (mysqli_fetch_assoc($result3)['total'] ?: 0) : 0;

$result4 = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocations WHERE status = 'Active'");
$stats['active_allocations'] = $result4 ? mysqli_fetch_assoc($result4)['count'] : 0;

// Calculate utilization rate
$stats['utilization_rate'] = $stats['total_assets'] > 0 ? round(($stats['allocated_assets'] / $stats['total_assets']) * 100, 1) : 0;

// Get asset distribution by category
$category_distribution = mysqli_query($conn, "
    SELECT ac.name as category, COUNT(ca.id) as count, SUM(ca.purchase_cost) as total_cost
    FROM asset_categories ac
    LEFT JOIN company_assets ca ON ac.id = ca.category_id
    GROUP BY ac.id, ac.name
    ORDER BY count DESC
");

// Get recent activity
$recent_activity = mysqli_query($conn, "
    SELECT aa.*, ca.name as asset_name, ca.asset_tag,
           CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name
    FROM asset_allocations aa
    LEFT JOIN company_assets ca ON aa.asset_id = ca.id
    LEFT JOIN hr_employees e ON aa.employee_id = e.id
    ORDER BY aa.updated_at DESC
    LIMIT 10
");

// Get top allocated assets
$top_allocated = mysqli_query($conn, "
    SELECT ca.name, ca.asset_tag, COUNT(aa.id) as allocation_count,
           AVG(DATEDIFF(COALESCE(aa.return_date, CURDATE()), aa.allocated_date)) as avg_days
    FROM company_assets ca
    LEFT JOIN asset_allocations aa ON ca.id = aa.asset_id
    GROUP BY ca.id
    HAVING allocation_count > 0
    ORDER BY allocation_count DESC
    LIMIT 10
");

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Asset Reports</h1>
                <p class="text-muted">Comprehensive asset analytics and reporting</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <a href="asset_management.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-gear"></i> Asset Management
                </a>
                <a href="asset_allocation.php" class="btn btn-outline-info">
                    <i class="bi bi-person-check"></i> Allocations
                </a>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Assets</h6>
                                <h3 class="mb-0"><?php echo $stats['total_assets']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-laptop fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Allocated Assets</h6>
                                <h3 class="mb-0"><?php echo $stats['allocated_assets']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-check fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Value</h6>
                                <h3 class="mb-0">$<?php echo number_format($stats['total_value'], 0); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-currency-dollar fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Utilization Rate</h6>
                                <h3 class="mb-0"><?php echo $stats['utilization_rate']; ?>%</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-speedometer2 fs-2 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Generation Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i>Generate Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="reportForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Report Type *</label>
                                        <select class="form-select" name="report_type" required onchange="toggleFilters(this.value)">
                                            <option value="">Select Report</option>
                                            <option value="inventory">Asset Inventory</option>
                                            <option value="utilization">Asset Utilization</option>
                                            <option value="allocation_summary">Allocation Summary</option>
                                            <option value="cost_analysis">Cost Analysis</option>
                                            <option value="depreciation">Depreciation Report</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Department</label>
                                        <select class="form-select" name="department">
                                            <option value="">All Departments</option>
                                            <?php 
                                            if ($departments && mysqli_num_rows($departments) > 0) {
                                                while ($dept = mysqli_fetch_assoc($departments)): 
                                            ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                                <?php echo htmlspecialchars($dept['department']); ?>
                                            </option>
                                            <?php 
                                                endwhile; 
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category">
                                            <option value="">All Categories</option>
                                            <?php 
                                            if ($categories && mysqli_num_rows($categories) > 0) {
                                                while ($category = mysqli_fetch_assoc($categories)): 
                                            ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                            <?php 
                                                endwhile; 
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="dateFilters" style="display: none;">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">From Date</label>
                                        <input type="date" class="form-control" name="date_from">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">To Date</label>
                                        <input type="date" class="form-control" name="date_to">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="generateReport()">
                                    <i class="bi bi-play-circle"></i> Generate Report
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportReport('csv')">
                                    <i class="bi bi-download"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-info" onclick="exportReport('pdf')">
                                    <i class="bi bi-file-pdf"></i> Export PDF
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">Category Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Results -->
        <div id="reportResults" class="d-none">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Report Results</h5>
                </div>
                <div class="card-body">
                    <div id="reportContent"></div>
                </div>
            </div>
        </div>

        <!-- Dashboard Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview-tab">
                            <i class="bi bi-graph-up me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assets-tab">
                            <i class="bi bi-laptop me-2"></i>Asset Performance
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity-tab">
                            <i class="bi bi-activity me-2"></i>Recent Activity
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#analytics-tab">
                            <i class="bi bi-bar-chart me-2"></i>Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview-tab">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent">
                                        <h6 class="card-title mb-0">Asset Status Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent">
                                        <h6 class="card-title mb-0">Quick Stats</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span>Available Assets:</span>
                                            <strong><?php echo $stats['total_assets'] - $stats['allocated_assets']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span>Active Allocations:</span>
                                            <strong><?php echo $stats['active_allocations']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span>Avg. Asset Value:</span>
                                            <strong>$<?php echo $stats['total_assets'] > 0 ? number_format($stats['total_value'] / $stats['total_assets'], 0) : 0; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-2">
                                            <span>Categories:</span>
                                            <strong><?php echo $categories ? mysqli_num_rows($categories) : 0; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Asset Performance Tab -->
                    <div class="tab-pane fade" id="assets-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Total Allocations</th>
                                        <th>Avg. Days Allocated</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($top_allocated && mysqli_num_rows($top_allocated) > 0) {
                                        while ($asset = mysqli_fetch_assoc($top_allocated)): 
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($asset['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($asset['asset_tag']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $asset['allocation_count']; ?></span>
                                        </td>
                                        <td><?php echo round($asset['avg_days'], 0); ?> days</td>
                                        <td>
                                            <?php 
                                            $performance = ($asset['allocation_count'] > 3) ? 'High' : (($asset['allocation_count'] > 1) ? 'Medium' : 'Low');
                                            $badge_color = ($performance === 'High') ? 'success' : (($performance === 'Medium') ? 'warning' : 'secondary');
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>"><?php echo $performance; ?></span>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile; 
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center text-muted">No allocation data available</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity Tab -->
                    <div class="tab-pane fade" id="activity-tab">
                        <div class="timeline">
                            <?php 
                            if ($recent_activity && mysqli_num_rows($recent_activity) > 0) {
                                while ($activity = mysqli_fetch_assoc($recent_activity)): 
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <h6 class="timeline-title">
                                            <?php 
                                            if ($activity['status'] === 'Active') {
                                                echo '<i class="bi bi-plus-circle text-success"></i> Asset Allocated';
                                            } elseif ($activity['status'] === 'Returned') {
                                                echo '<i class="bi bi-arrow-return-left text-info"></i> Asset Returned';
                                            } else {
                                                echo '<i class="bi bi-exclamation-triangle text-warning"></i> Asset Issue';
                                            }
                                            ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($activity['updated_at'])); ?></small>
                                    </div>
                                    <div class="timeline-body">
                                        <strong><?php echo htmlspecialchars($activity['asset_name']); ?></strong> 
                                        (<?php echo htmlspecialchars($activity['asset_tag']); ?>)
                                        <?php if ($activity['status'] === 'Active'): ?>
                                            allocated to <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                        <?php elseif ($activity['status'] === 'Returned'): ?>
                                            returned by <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                endwhile; 
                            } else {
                                echo '<div class="alert alert-info">No recent activity found</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics-tab">
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent">
                                        <h6 class="card-title mb-0">Asset Value Trends</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="valueChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent">
                                        <h6 class="card-title mb-0">Department Allocation</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="departmentChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #dee2e6;
}

.timeline-item:last-child {
    border-left: 2px solid transparent;
}

.timeline-marker {
    position: absolute;
    left: -6px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-content {
    padding-left: 20px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 5px;
}

.timeline-title {
    margin: 0;
    font-size: 0.95rem;
}

.timeline-body {
    font-size: 0.9rem;
    color: #6c757d;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Toggle filter visibility based on report type
function toggleFilters(reportType) {
    const dateFilters = document.getElementById('dateFilters');
    if (reportType === 'allocation_summary') {
        dateFilters.style.display = 'flex';
    } else {
        dateFilters.style.display = 'none';
    }
}

// Generate report
function generateReport() {
    const form = document.getElementById('reportForm');
    const formData = new FormData(form);
    formData.append('action', 'generate_report');
    
    const reportType = formData.get('report_type');
    if (!reportType) {
        alert('Please select a report type');
        return;
    }
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayReport(reportType, data.data);
        } else {
            alert('Error generating report');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while generating the report');
    });
}

// Display report results
function displayReport(reportType, data) {
    const resultsDiv = document.getElementById('reportResults');
    const contentDiv = document.getElementById('reportContent');
    
    let html = '';
    
    switch (reportType) {
        case 'utilization':
            html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Asset Utilization Overview</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Assets:</span>
                                <strong>${data.total_assets}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Allocated:</span>
                                <strong>${data.allocated_assets}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Available:</span>
                                <strong>${data.available_assets}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Utilization Rate:</span>
                                <strong>${data.utilization_rate}%</strong>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <canvas id="utilizationChart" height="200"></canvas>
                    </div>
                </div>
            `;
            break;
            
        case 'cost_analysis':
            html = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Asset Count</th>
                                <th>Total Purchase Cost</th>
                                <th>Current Value</th>
                                <th>Depreciation</th>
                                <th>Avg. Cost</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.category || 'Uncategorized'}</td>
                        <td>${row.asset_count}</td>
                        <td>$${parseFloat(row.total_purchase_cost || 0).toLocaleString()}</td>
                        <td>$${parseFloat(row.total_current_value || 0).toLocaleString()}</td>
                        <td>$${parseFloat(row.total_depreciation || 0).toLocaleString()}</td>
                        <td>$${parseFloat(row.avg_purchase_cost || 0).toLocaleString()}</td>
                    </tr>
                `;
            });
            html += '</tbody></table></div>';
            break;
            
        case 'allocation_summary':
            html = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total Allocations</th>
                                <th>Active</th>
                                <th>Returned</th>
                                <th>Avg. Days</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            data.forEach(row => {
                html += `
                    <tr>
                        <td>${row.department || 'Unassigned'}</td>
                        <td>${row.total_allocations}</td>
                        <td><span class="badge bg-success">${row.active_allocations}</span></td>
                        <td><span class="badge bg-secondary">${row.returned_allocations}</span></td>
                        <td>${Math.round(row.avg_allocation_days || 0)} days</td>
                    </tr>
                `;
            });
            html += '</tbody></table></div>';
            break;
    }
    
    contentDiv.innerHTML = html;
    resultsDiv.classList.remove('d-none');
    
    // Initialize charts if needed
    if (reportType === 'utilization') {
        setTimeout(initUtilizationChart, 100);
    }
}

// Export report
function exportReport(format) {
    const form = document.getElementById('reportForm');
    const formData = new FormData(form);
    
    const reportType = formData.get('report_type');
    if (!reportType) {
        alert('Please select a report type first');
        return;
    }
    
    if (format === 'csv') {
        formData.append('action', 'export_csv');
        
        // Create a temporary form to submit for download
        const tempForm = document.createElement('form');
        tempForm.method = 'POST';
        tempForm.action = '';
        
        for (let pair of formData.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = pair[1];
            tempForm.appendChild(input);
        }
        
        document.body.appendChild(tempForm);
        tempForm.submit();
        document.body.removeChild(tempForm);
    } else if (format === 'pdf') {
        alert('PDF export feature coming soon!');
    }
}

// Initialize charts
function initializeCharts() {
    // Category distribution chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        <?php
        $categoryLabels = [];
        $categoryData = [];
        if ($category_distribution && mysqli_num_rows($category_distribution) > 0) {
            mysqli_data_seek($category_distribution, 0);
            while ($row = mysqli_fetch_assoc($category_distribution)) {
                $categoryLabels[] = $row['category'];
                $categoryData[] = $row['count'];
            }
        }
        ?>
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categoryLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($categoryData); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#fa709a',
                        '#43e97b'
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
    
    // Asset status chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['Available', 'Allocated', 'Maintenance', 'Retired'],
                datasets: [{
                    label: 'Assets',
                    data: [
                        <?php echo $stats['total_assets'] - $stats['allocated_assets']; ?>,
                        <?php echo $stats['allocated_assets']; ?>,
                        0, // Would need to calculate from actual data
                        0  // Would need to calculate from actual data
                    ],
                    backgroundColor: ['#4facfe', '#667eea', '#f093fb', '#fa709a']
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
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Initialize utilization chart for reports
function initUtilizationChart() {
    const ctx = document.getElementById('utilizationChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Allocated', 'Available'],
                datasets: [{
                    data: [<?php echo $stats['allocated_assets']; ?>, <?php echo $stats['total_assets'] - $stats['allocated_assets']; ?>],
                    backgroundColor: ['#667eea', '#4facfe']
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
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

// Initialize charts when tabs are shown
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function() {
        setTimeout(initializeCharts, 100);
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
