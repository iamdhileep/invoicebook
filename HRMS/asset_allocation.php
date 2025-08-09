<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Asset Allocation';

// Ensure database tables exist (from asset management system)
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

// Create employees table if it doesn't exist (for HRMS integration)
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'allocate_asset':
            try {
                $conn->begin_transaction();
                
                // Check if asset is available
                $checkStmt = $conn->prepare("SELECT status FROM company_assets WHERE id = ?");
                $checkStmt->bind_param("i", $_POST['asset_id']);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $asset = $result->fetch_assoc();
                
                if ($asset['status'] !== 'Available') {
                    throw new Exception('Asset is not available for allocation');
                }
                
                // Add allocation record
                $stmt = $conn->prepare("INSERT INTO asset_allocations (asset_id, employee_id, allocated_date, allocated_by, allocation_reason, condition_when_allocated, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisisss", 
                    $_POST['asset_id'],
                    $_POST['employee_id'],
                    $_POST['allocated_date'],
                    $_SESSION['admin'],
                    $_POST['allocation_reason'],
                    $_POST['condition_when_allocated'],
                    $_POST['notes']
                );
                $stmt->execute();
                
                // Update asset status
                $updateStmt = $conn->prepare("UPDATE company_assets SET status = 'Allocated' WHERE id = ?");
                $updateStmt->bind_param("i", $_POST['asset_id']);
                $updateStmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset allocated successfully!']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'return_asset':
            try {
                $conn->begin_transaction();
                
                // Update allocation record
                $stmt = $conn->prepare("UPDATE asset_allocations SET return_date = ?, returned_by = ?, return_reason = ?, condition_when_returned = ?, status = 'Returned' WHERE id = ?");
                $stmt->bind_param("sissi", 
                    $_POST['return_date'],
                    $_SESSION['admin'],
                    $_POST['return_reason'],
                    $_POST['condition_when_returned'],
                    $_POST['allocation_id']
                );
                $stmt->execute();
                
                // Get asset_id from allocation
                $getAssetStmt = $conn->prepare("SELECT asset_id FROM asset_allocations WHERE id = ?");
                $getAssetStmt->bind_param("i", $_POST['allocation_id']);
                $getAssetStmt->execute();
                $result = $getAssetStmt->get_result();
                $allocation = $result->fetch_assoc();
                
                // Update asset status based on condition
                $newStatus = ($_POST['condition_when_returned'] === 'Poor') ? 'Under Maintenance' : 'Available';
                $updateAssetStmt = $conn->prepare("UPDATE company_assets SET status = ?, condition_status = ? WHERE id = ?");
                $updateAssetStmt->bind_param("ssi", $newStatus, $_POST['condition_when_returned'], $allocation['asset_id']);
                $updateAssetStmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset returned successfully!']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'report_lost_damaged':
            try {
                $conn->begin_transaction();
                
                // Update allocation record
                $stmt = $conn->prepare("UPDATE asset_allocations SET status = ?, return_reason = ?, return_date = CURDATE(), returned_by = ? WHERE id = ?");
                $stmt->bind_param("ssii", 
                    $_POST['incident_type'],
                    $_POST['incident_description'],
                    $_SESSION['admin'],
                    $_POST['allocation_id']
                );
                $stmt->execute();
                
                // Get asset_id from allocation
                $getAssetStmt = $conn->prepare("SELECT asset_id FROM asset_allocations WHERE id = ?");
                $getAssetStmt->bind_param("i", $_POST['allocation_id']);
                $getAssetStmt->execute();
                $result = $getAssetStmt->get_result();
                $allocation = $result->fetch_assoc();
                
                // Update asset status
                $newStatus = ($_POST['incident_type'] === 'Lost') ? 'Lost/Stolen' : 'Under Maintenance';
                $updateAssetStmt = $conn->prepare("UPDATE company_assets SET status = ? WHERE id = ?");
                $updateAssetStmt->bind_param("si", $newStatus, $allocation['asset_id']);
                $updateAssetStmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Incident reported successfully!']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'extend_allocation':
            try {
                $stmt = $conn->prepare("UPDATE asset_allocations SET notes = CONCAT(IFNULL(notes, ''), '\n[Extension]: ', ?) WHERE id = ?");
                $stmt->bind_param("si", $_POST['extension_reason'], $_POST['allocation_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Allocation extended successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error extending allocation']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'transfer_asset':
            try {
                $conn->begin_transaction();
                
                // Return current allocation
                $returnStmt = $conn->prepare("UPDATE asset_allocations SET return_date = CURDATE(), returned_by = ?, return_reason = 'Transfer to another employee', status = 'Returned' WHERE id = ?");
                $returnStmt->bind_param("ii", $_SESSION['admin'], $_POST['current_allocation_id']);
                $returnStmt->execute();
                
                // Create new allocation
                $allocateStmt = $conn->prepare("INSERT INTO asset_allocations (asset_id, employee_id, allocated_date, allocated_by, allocation_reason, condition_when_allocated, notes) VALUES (?, ?, CURDATE(), ?, 'Transfer from another employee', 'Good', ?)");
                $allocateStmt->bind_param("iiss", 
                    $_POST['asset_id'],
                    $_POST['new_employee_id'],
                    $_SESSION['admin'],
                    $_POST['transfer_notes']
                );
                $allocateStmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset transferred successfully!']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_allocation_history':
            $asset_id = intval($_POST['asset_id']);
            $stmt = $conn->prepare("
                SELECT aa.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                       e.employee_id as emp_id,
                       e.department,
                       ca.name as asset_name,
                       ca.asset_tag
                FROM asset_allocations aa
                LEFT JOIN hr_employees e ON aa.employee_id = e.id
                LEFT JOIN company_assets ca ON aa.asset_id = ca.id
                WHERE aa.asset_id = ?
                ORDER BY aa.allocated_date DESC
            ");
            $stmt->bind_param("i", $asset_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            
            echo json_encode(['success' => true, 'history' => $history]);
            exit;
            
        case 'get_employee_assets':
            $employee_id = intval($_POST['employee_id']);
            $stmt = $conn->prepare("
                SELECT aa.*, ca.name as asset_name, ca.asset_tag, ca.asset_type, ac.name as category_name
                FROM asset_allocations aa
                JOIN company_assets ca ON aa.asset_id = ca.id
                LEFT JOIN asset_categories ac ON ca.category_id = ac.id
                WHERE aa.employee_id = ? AND aa.status = 'Active'
                ORDER BY aa.allocated_date DESC
            ");
            $stmt->bind_param("i", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $assets = [];
            while ($row = $result->fetch_assoc()) {
                $assets[] = $row;
            }
            
            echo json_encode(['success' => true, 'assets' => $assets]);
            exit;
            
        case 'delete_allocation':
            try {
                $conn->begin_transaction();
                
                // Get asset_id before deleting
                $getAssetStmt = $conn->prepare("SELECT asset_id FROM asset_allocations WHERE id = ?");
                $getAssetStmt->bind_param("i", $_POST['allocation_id']);
                $getAssetStmt->execute();
                $result = $getAssetStmt->get_result();
                $allocation = $result->fetch_assoc();
                
                // Delete allocation record
                $deleteStmt = $conn->prepare("DELETE FROM asset_allocations WHERE id = ?");
                $deleteStmt->bind_param("i", $_POST['allocation_id']);
                $deleteStmt->execute();
                
                // Update asset status to Available
                $updateAssetStmt = $conn->prepare("UPDATE company_assets SET status = 'Available' WHERE id = ?");
                $updateAssetStmt->bind_param("i", $allocation['asset_id']);
                $updateAssetStmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Allocation deleted successfully!']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$asset_filter = $_GET['asset'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause for filtering
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($status_filter) {
    $where_conditions[] = "aa.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($employee_filter) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $search = "%" . $employee_filter . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

if ($asset_filter) {
    $where_conditions[] = "(ca.name LIKE ? OR ca.asset_tag LIKE ?)";
    $search = "%" . $asset_filter . "%";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if ($date_from) {
    $where_conditions[] = "aa.allocated_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $where_conditions[] = "aa.allocated_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Insert sample data if tables are empty
$checkCategories = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_categories");
if ($checkCategories && mysqli_fetch_assoc($checkCategories)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO asset_categories (name, description) VALUES 
        ('IT Equipment', 'Computers, laptops, phones, etc.'),
        ('Office Furniture', 'Desks, chairs, cabinets'),
        ('Vehicles', 'Company cars and trucks'),
        ('Tools & Equipment', 'Work tools and machinery')");
}

$checkEmployees = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employees");
if ($checkEmployees && mysqli_fetch_assoc($checkEmployees)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department, date_of_joining) VALUES 
        ('EMP001', 'John', 'Doe', 'john.doe@company.com', 'Software Developer', 'IT', '2023-01-15'),
        ('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', 'Marketing Manager', 'Marketing', '2022-11-20'),
        ('EMP003', 'Mike', 'Johnson', 'mike.johnson@company.com', 'HR Specialist', 'Human Resources', '2023-03-10')");
}

$checkAssets = mysqli_query($conn, "SELECT COUNT(*) as count FROM company_assets");
if ($checkAssets && mysqli_fetch_assoc($checkAssets)['count'] == 0) {
    mysqli_query($conn, "INSERT INTO company_assets (asset_tag, name, category_id, asset_type, brand, model, purchase_date, purchase_cost, location, condition_status, status) VALUES 
        ('LAPTOP001', 'Dell Laptop', 1, 'Laptop', 'Dell', 'Inspiron 15', '2023-01-01', 899.99, 'Office Floor 1', 'Good', 'Available'),
        ('PHONE001', 'iPhone 13', 1, 'Mobile Phone', 'Apple', 'iPhone 13', '2023-02-15', 699.99, 'Office Floor 2', 'Excellent', 'Available'),
        ('DESK001', 'Office Desk', 2, 'Desk', 'IKEA', 'BEKANT', '2022-12-01', 149.99, 'Office Floor 1', 'Good', 'Available')");
}

// Get statistics with error checking
$stats = [];

$result1 = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocations WHERE status = 'Active'");
$stats['active_allocations'] = $result1 ? mysqli_fetch_assoc($result1)['count'] : 0;

$result2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocations WHERE return_date = CURDATE()");
$stats['returned_today'] = $result2 ? mysqli_fetch_assoc($result2)['count'] : 0;

$result3 = mysqli_query($conn, "SELECT COUNT(*) as count FROM asset_allocations WHERE status = 'Active' AND allocated_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
$stats['overdue_returns'] = $result3 ? mysqli_fetch_assoc($result3)['count'] : 0;

$result4 = mysqli_query($conn, "SELECT COUNT(*) as count FROM company_assets WHERE status IN ('Available', 'Allocated')");
$stats['total_assets'] = $result4 ? mysqli_fetch_assoc($result4)['count'] : 0;

// Get allocations with employee and asset details
$query = "
    SELECT aa.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id,
           e.department,
           e.position,
           ca.name as asset_name,
           ca.asset_tag,
           ca.asset_type,
           ca.brand,
           ca.model,
           ac.name as category_name,
           DATEDIFF(CURDATE(), aa.allocated_date) as days_allocated
    FROM asset_allocations aa
    LEFT JOIN hr_employees e ON aa.employee_id = e.id
    LEFT JOIN company_assets ca ON aa.asset_id = ca.id
    LEFT JOIN asset_categories ac ON ca.category_id = ac.id
    WHERE $where_clause
    ORDER BY aa.allocated_date DESC
    LIMIT 50
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $allocations = $stmt->get_result();
    } else {
        // Fallback query without parameters if prepare fails
        $query_fallback = "
            SELECT aa.*, 
                   CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name,
                   COALESCE(e.employee_id, 'N/A') as emp_id,
                   COALESCE(e.department, 'N/A') as department,
                   COALESCE(e.position, 'N/A') as position,
                   COALESCE(ca.name, 'Unknown Asset') as asset_name,
                   COALESCE(ca.asset_tag, 'N/A') as asset_tag,
                   COALESCE(ca.asset_type, 'N/A') as asset_type,
                   COALESCE(ca.brand, 'N/A') as brand,
                   COALESCE(ca.model, 'N/A') as model,
                   COALESCE(ac.name, 'N/A') as category_name,
                   IFNULL(DATEDIFF(CURDATE(), aa.allocated_date), 0) as days_allocated
            FROM asset_allocations aa
            LEFT JOIN hr_employees e ON aa.employee_id = e.id
            LEFT JOIN company_assets ca ON aa.asset_id = ca.id
            LEFT JOIN asset_categories ac ON ca.category_id = ac.id
            ORDER BY aa.allocated_date DESC
            LIMIT 50
        ";
        $allocations = mysqli_query($conn, $query_fallback);
    }
} else {
    $allocations = mysqli_query($conn, $query);
}

// If query still fails, create empty result
if (!$allocations) {
    echo "Query error: " . mysqli_error($conn);
    $allocations = [];
}

// Get available assets for allocation
$available_assets = mysqli_query($conn, "
    SELECT ca.*, COALESCE(ac.name, 'Uncategorized') as category_name
    FROM company_assets ca
    LEFT JOIN asset_categories ac ON ca.category_id = ac.id
    WHERE ca.status = 'Available'
    ORDER BY ca.name
");

if (!$available_assets) {
    $available_assets = [];
}

// Get active employees
$employees = mysqli_query($conn, "
    SELECT id, employee_id, first_name, last_name, department, position
    FROM hr_employees 
    WHERE status = 'active'
    ORDER BY first_name, last_name
");

if (!$employees) {
    $employees = [];
}

// Get recent activity
$recent_activity = mysqli_query($conn, "
    SELECT aa.*, 
           CONCAT(COALESCE(e.first_name, 'Unknown'), ' ', COALESCE(e.last_name, 'Employee')) as employee_name,
           COALESCE(ca.name, 'Unknown Asset') as asset_name,
           COALESCE(ca.asset_tag, 'N/A') as asset_tag
    FROM asset_allocations aa
    LEFT JOIN hr_employees e ON aa.employee_id = e.id
    LEFT JOIN company_assets ca ON aa.asset_id = ca.id
    ORDER BY aa.updated_at DESC
    LIMIT 10
");

if (!$recent_activity) {
    $recent_activity = [];
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📋 Asset Allocation</h1>
                <p class="text-muted">Track and manage asset assignments to employees</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to HRMS
                </a>
                <a href="asset_management.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-gear"></i> Asset Management
                </a>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#allocateAssetModal">
                    <i class="bi bi-plus-circle"></i> Allocate Asset
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Active Allocations</h6>
                                <h3 class="mb-0"><?php echo $stats['active_allocations']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-check fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Returned Today</h6>
                                <h3 class="mb-0"><?php echo $stats['returned_today']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-box-arrow-in-left fs-2 text-white-50"></i>
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
                                <h6 class="card-title text-white-50 mb-2">Long-term Allocations</h6>
                                <h3 class="mb-0"><?php echo $stats['overdue_returns']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock-history fs-2 text-white-50"></i>
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
        </div>

        <!-- Filters and Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Returned" <?php echo ($status_filter === 'Returned') ? 'selected' : ''; ?>>Returned</option>
                            <option value="Lost" <?php echo ($status_filter === 'Lost') ? 'selected' : ''; ?>>Lost</option>
                            <option value="Damaged" <?php echo ($status_filter === 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee</label>
                        <input type="text" name="employee" class="form-control" placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_filter); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#allocations-tab">
                            <i class="bi bi-list-check me-2"></i>All Allocations
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#active-tab">
                            <i class="bi bi-person-check me-2"></i>Active Allocations
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history-tab">
                            <i class="bi bi-clock-history me-2"></i>Return History
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity-tab">
                            <i class="bi bi-activity me-2"></i>Recent Activity
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- All Allocations Tab -->
                    <div class="tab-pane fade show active" id="allocations-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Employee</th>
                                        <th>Allocated Date</th>
                                        <th>Days</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($allocations && mysqli_num_rows($allocations) > 0) {
                                        while ($allocation = mysqli_fetch_assoc($allocations)): 
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($allocation['asset_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($allocation['asset_tag']); ?> • 
                                                <?php echo htmlspecialchars($allocation['category_name']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($allocation['employee_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($allocation['emp_id']); ?> • 
                                                <?php echo htmlspecialchars($allocation['department']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($allocation['allocated_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($allocation['days_allocated'] > 90) ? 'warning' : 'info'; ?>">
                                                <?php echo $allocation['days_allocated']; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($allocation['condition_when_allocated'] === 'Excellent') ? 'success' : 
                                                     (($allocation['condition_when_allocated'] === 'Good') ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php echo $allocation['condition_when_allocated']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($allocation['status'] === 'Active') ? 'success' : 
                                                     (($allocation['status'] === 'Returned') ? 'secondary' : 'danger'); 
                                            ?>">
                                                <?php echo $allocation['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($allocation['status'] === 'Active'): ?>
                                                    <button class="btn btn-outline-success" onclick="returnAsset(<?php echo $allocation['id']; ?>)" title="Return Asset">
                                                        <i class="bi bi-box-arrow-in-left"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="reportIncident(<?php echo $allocation['id']; ?>)" title="Report Issue">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="transferAsset(<?php echo $allocation['id']; ?>, <?php echo $allocation['asset_id']; ?>)" title="Transfer Asset">
                                                        <i class="bi bi-arrow-right-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-primary" onclick="viewAllocationDetails(<?php echo $allocation['id']; ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteAllocation(<?php echo $allocation['id']; ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile; 
                                    } else { 
                                        echo '<tr><td colspan="7" class="text-center text-muted">No allocation records found</td></tr>'; 
                                    } 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Active Allocations Tab -->
                    <div class="tab-pane fade" id="active-tab">
                        <?php
                        mysqli_data_seek($allocations, 0);
                        ?>
                        <div class="row">
                            <?php 
                            if ($allocations && mysqli_num_rows($allocations) > 0) {
                                mysqli_data_seek($allocations, 0);
                                while ($allocation = mysqli_fetch_assoc($allocations)): 
                                    if ($allocation['status'] === 'Active'): 
                            ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="card-title mb-0">
                                                <i class="bi bi-laptop me-2"></i>
                                                <?php echo htmlspecialchars($allocation['asset_name']); ?>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <strong>Asset Tag:</strong> <?php echo htmlspecialchars($allocation['asset_tag']); ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Employee:</strong> <?php echo htmlspecialchars($allocation['employee_name']); ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Department:</strong> <?php echo htmlspecialchars($allocation['department']); ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Allocated:</strong> <?php echo date('M d, Y', strtotime($allocation['allocated_date'])); ?>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Duration:</strong> 
                                                <span class="badge bg-<?php echo ($allocation['days_allocated'] > 90) ? 'warning' : 'info'; ?>">
                                                    <?php echo $allocation['days_allocated']; ?> days
                                                </span>
                                            </div>
                                            <?php if ($allocation['allocation_reason']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($allocation['allocation_reason']); ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <button class="btn btn-sm btn-success" onclick="returnAsset(<?php echo $allocation['id']; ?>)">
                                                <i class="bi bi-box-arrow-in-left"></i> Return
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="reportIncident(<?php echo $allocation['id']; ?>)">
                                                <i class="bi bi-exclamation-triangle"></i> Report Issue
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endif; 
                                endwhile; 
                            } else {
                                echo '<div class="col-12"><div class="alert alert-info">No active allocations found</div></div>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Return History Tab -->
                    <div class="tab-pane fade" id="history-tab">
                        <?php
                        mysqli_data_seek($allocations, 0);
                        ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Employee</th>
                                        <th>Allocated</th>
                                        <th>Returned</th>
                                        <th>Duration</th>
                                        <th>Condition</th>
                                        <th>Return Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($allocations && mysqli_num_rows($allocations) > 0) {
                                        mysqli_data_seek($allocations, 0);
                                        while ($allocation = mysqli_fetch_assoc($allocations)): 
                                            if ($allocation['status'] === 'Returned'): 
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($allocation['asset_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($allocation['asset_tag']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($allocation['employee_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($allocation['allocated_date'])); ?></td>
                                            <td><?php echo $allocation['return_date'] ? date('M d, Y', strtotime($allocation['return_date'])) : '-'; ?></td>
                                            <td>
                                                <?php 
                                                if ($allocation['return_date']) {
                                                    $days = (strtotime($allocation['return_date']) - strtotime($allocation['allocated_date'])) / (60*60*24);
                                                    echo intval($days) . ' days';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($allocation['condition_when_returned']): ?>
                                                <span class="badge bg-<?php 
                                                    echo ($allocation['condition_when_returned'] === 'Excellent') ? 'success' : 
                                                         (($allocation['condition_when_returned'] === 'Good') ? 'primary' : 'warning'); 
                                                ?>">
                                                    <?php echo $allocation['condition_when_returned']; ?>
                                                </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($allocation['return_reason']) ?: '-'; ?></small>
                                            </td>
                                        </tr>
                                        <?php 
                                            endif; 
                                        endwhile; 
                                    } else {
                                        echo '<tr><td colspan="7" class="text-center text-muted">No returned assets found</td></tr>';
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
                                            } elseif ($activity['status'] === 'Lost') {
                                                echo '<i class="bi bi-exclamation-triangle text-danger"></i> Asset Lost';
                                            } else {
                                                echo '<i class="bi bi-gear text-warning"></i> Asset Issue';
                                            }
                                            ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($activity['updated_at'])); ?></small>
                                    </div>
                                    <div class="timeline-body">
                                        <strong><?php echo htmlspecialchars($activity['asset_name']); ?></strong> (<?php echo htmlspecialchars($activity['asset_tag']); ?>)
                                        <?php if ($activity['status'] === 'Active'): ?>
                                            allocated to <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                        <?php elseif ($activity['status'] === 'Returned'): ?>
                                            returned by <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                        <?php else: ?>
                                            - <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Allocate Asset Modal -->
<div class="modal fade" id="allocateAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allocate Asset to Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="allocateAssetForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Asset *</label>
                                <select class="form-select" name="asset_id" required>
                                    <option value="">Choose Available Asset</option>
                                    <?php 
                                    if ($available_assets && mysqli_num_rows($available_assets) > 0) {
                                        while ($asset = mysqli_fetch_assoc($available_assets)): 
                                    ?>
                                    <option value="<?php echo $asset['id']; ?>" data-category="<?php echo htmlspecialchars($asset['category_name']); ?>" data-type="<?php echo htmlspecialchars($asset['asset_type']); ?>">
                                        <?php echo htmlspecialchars($asset['name']); ?> - <?php echo htmlspecialchars($asset['asset_tag']); ?>
                                    </option>
                                    <?php 
                                        endwhile; 
                                    } else {
                                        echo '<option value="" disabled>No assets available</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Employee *</label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">Choose Employee</option>
                                    <?php 
                                    if ($employees && mysqli_num_rows($employees) > 0) {
                                        while ($employee = mysqli_fetch_assoc($employees)): 
                                    ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        (<?php echo htmlspecialchars($employee['employee_id']); ?> - <?php echo htmlspecialchars($employee['department']); ?>)
                                    </option>
                                    <?php 
                                        endwhile; 
                                    } else {
                                        echo '<option value="" disabled>No employees found</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Allocation Date *</label>
                                <input type="date" class="form-control" name="allocated_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Condition When Allocated</label>
                                <select class="form-select" name="condition_when_allocated">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allocation Reason</label>
                        <input type="text" class="form-control" name="allocation_reason" placeholder="e.g., Work requirement, replacement">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about the allocation"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Allocate Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Asset Modal -->
<div class="modal fade" id="returnAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="returnAssetForm">
                <input type="hidden" name="allocation_id" id="return_allocation_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Return Date *</label>
                        <input type="date" class="form-control" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condition When Returned *</label>
                        <select class="form-select" name="condition_when_returned" required>
                            <option value="">Select Condition</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor (Needs Maintenance)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Return Reason</label>
                        <input type="text" class="form-control" name="return_reason" placeholder="Reason for return">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Return Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Incident Modal -->
<div class="modal fade" id="reportIncidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Asset Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reportIncidentForm">
                <input type="hidden" name="allocation_id" id="incident_allocation_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Incident Type *</label>
                        <select class="form-select" name="incident_type" required>
                            <option value="">Select Type</option>
                            <option value="Lost">Asset Lost</option>
                            <option value="Damaged">Asset Damaged</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" name="incident_description" rows="4" placeholder="Provide details about what happened..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transfer Asset Modal -->
<div class="modal fade" id="transferAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="transferAssetForm">
                <input type="hidden" name="current_allocation_id" id="transfer_allocation_id">
                <input type="hidden" name="asset_id" id="transfer_asset_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Transfer to Employee *</label>
                        <select class="form-select" name="new_employee_id" required>
                            <option value="">Select New Employee</option>
                            <?php 
                            if ($employees && mysqli_num_rows($employees) > 0) {
                                mysqli_data_seek($employees, 0);
                                while ($employee = mysqli_fetch_assoc($employees)): 
                            ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                (<?php echo htmlspecialchars($employee['department']); ?>)
                            </option>
                            <?php 
                                endwhile; 
                            } else {
                                echo '<option value="" disabled>No employees found</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transfer Notes</label>
                        <textarea class="form-control" name="transfer_notes" rows="3" placeholder="Reason for transfer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Transfer Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Asset Details Modal -->
<div class="modal fade" id="assetDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asset Allocation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="assetDetailsContent">
                    <!-- Content will be loaded here -->
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

<script>
// Form submission handlers
document.getElementById('allocateAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'allocate_asset');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Asset allocated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while allocating the asset.');
    });
});

document.getElementById('returnAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'return_asset');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Asset returned successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while returning the asset.');
    });
});

document.getElementById('reportIncidentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'report_lost_damaged');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Incident reported successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while reporting the incident.');
    });
});

document.getElementById('transferAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'transfer_asset');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Asset transferred successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while transferring the asset.');
    });
});

// Action functions
function returnAsset(allocationId) {
    document.getElementById('return_allocation_id').value = allocationId;
    new bootstrap.Modal(document.getElementById('returnAssetModal')).show();
}

function reportIncident(allocationId) {
    document.getElementById('incident_allocation_id').value = allocationId;
    new bootstrap.Modal(document.getElementById('reportIncidentModal')).show();
}

function transferAsset(allocationId, assetId) {
    document.getElementById('transfer_allocation_id').value = allocationId;
    document.getElementById('transfer_asset_id').value = assetId;
    new bootstrap.Modal(document.getElementById('transferAssetModal')).show();
}

function viewAllocationDetails(allocationId) {
    // This would load detailed allocation information
    document.getElementById('assetDetailsContent').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    new bootstrap.Modal(document.getElementById('assetDetailsModal')).show();
    
    // Simulate loading details (in real implementation, fetch via AJAX)
    setTimeout(() => {
        document.getElementById('assetDetailsContent').innerHTML = `
            <div class="alert alert-info">
                <h6>Allocation Details</h6>
                <p>Detailed information about allocation ID: ${allocationId} would be displayed here.</p>
                <p>This could include timeline, documents, maintenance history, etc.</p>
            </div>
        `;
    }, 500);
}

function deleteAllocation(allocationId) {
    if (confirm('Are you sure you want to delete this allocation record? This will mark the asset as available.')) {
        const formData = new FormData();
        formData.append('action', 'delete_allocation');
        formData.append('allocation_id', allocationId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the allocation.');
        });
    }
}

// Set maximum date to today for return dates
document.querySelector('[name="return_date"]').max = new Date().toISOString().split('T')[0];
document.querySelector('[name="allocated_date"]').max = new Date().toISOString().split('T')[0];
</script>

<?php include '../layouts/footer.php'; ?>
