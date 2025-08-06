<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database

include '../config.php';
if (!isset($root_path)) 
include '../db.php';
if (!isset($root_path)) 
include '../auth_guard.php';

$page_title = 'Asset Management - HRMS';

// Create asset management tables if they don't exist
$asset_tables_sql = [
    "CREATE TABLE IF NOT EXISTS asset_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS company_assets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        asset_tag VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        category_id INT,
        asset_type ENUM('IT Equipment', 'Office Furniture', 'Vehicle', 'Machinery', 'Other') DEFAULT 'IT Equipment',
        brand VARCHAR(100),
        model VARCHAR(100),
        serial_number VARCHAR(100),
        purchase_date DATE,
        purchase_cost DECIMAL(12,2),
        warranty_expiry DATE,
        depreciation_rate DECIMAL(5,2) DEFAULT 0.00,
        current_value DECIMAL(12,2),
        location VARCHAR(200),
        status ENUM('Available', 'Allocated', 'Under Maintenance', 'Retired', 'Lost/Stolen') DEFAULT 'Available',
        condition_status ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged') DEFAULT 'Good',
        supplier VARCHAR(200),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS asset_allocations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        asset_id INT NOT NULL,
        employee_id INT NOT NULL,
        allocated_date DATE NOT NULL,
        return_date DATE NULL,
        allocation_status ENUM('Active', 'Returned', 'Lost', 'Damaged') DEFAULT 'Active',
        allocated_by INT,
        return_condition ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged') NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES company_assets(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (allocated_by) REFERENCES employees(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS asset_maintenance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        asset_id INT NOT NULL,
        maintenance_type ENUM('Preventive', 'Corrective', 'Emergency', 'Upgrade') NOT NULL,
        scheduled_date DATE NOT NULL,
        completed_date DATE NULL,
        cost DECIMAL(10,2) DEFAULT 0.00,
        vendor VARCHAR(200),
        description TEXT NOT NULL,
        status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
        performed_by VARCHAR(200),
        next_maintenance_date DATE NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES company_assets(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
    )"
];

foreach ($asset_tables_sql as $sql) {
    $conn->query($sql);
}

// Insert default asset categories
$default_categories = [
    ['IT Equipment', 'Computers, laptops, printers, networking equipment'],
    ['Office Furniture', 'Desks, chairs, cabinets, meeting room furniture'],
    ['Vehicles', 'Company cars, trucks, motorcycles'],
    ['Machinery', 'Industrial equipment, tools, production machinery'],
    ['Electronics', 'Mobile phones, tablets, cameras, audio equipment']
];

$check_categories = $conn->query("SELECT COUNT(*) as count FROM asset_categories")->fetch_assoc();
if ($check_categories['count'] == 0) {
    foreach ($default_categories as $category) {
        $stmt = $conn->prepare("INSERT INTO asset_categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category[0], $category[1]);
        $stmt->execute();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_asset':
                $asset_tag = $_POST['asset_tag'];
                $name = $_POST['name'];
                $category_id = $_POST['category_id'] ?: null;
                $asset_type = $_POST['asset_type'];
                $brand = $_POST['brand'];
                $model = $_POST['model'];
                $serial_number = $_POST['serial_number'];
                $purchase_date = $_POST['purchase_date'] ?: null;
                $purchase_cost = $_POST['purchase_cost'] ?: null;
                $warranty_expiry = $_POST['warranty_expiry'] ?: null;
                $location = $_POST['location'];
                $supplier = $_POST['supplier'];
                $notes = $_POST['notes'];
                
                $stmt = $conn->prepare("INSERT INTO company_assets (asset_tag, name, category_id, asset_type, brand, model, serial_number, purchase_date, purchase_cost, warranty_expiry, current_value, location, supplier, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisssssddssss", $asset_tag, $name, $category_id, $asset_type, $brand, $model, $serial_number, $purchase_date, $purchase_cost, $warranty_expiry, $purchase_cost, $location, $supplier, $notes);
                
                if ($stmt->execute()) {
                    $success_message = "Asset added successfully!";
                } else {
                    $error_message = "Error adding asset: " . $conn->error;
                }
                break;
                
            case 'allocate_asset':
                $asset_id = $_POST['asset_id'];
                $employee_id = $_POST['employee_id'];
                $allocated_date = $_POST['allocated_date'];
                $notes = $_POST['allocation_notes'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Add allocation record
                    $stmt = $conn->prepare("INSERT INTO asset_allocations (asset_id, employee_id, allocated_date, allocated_by, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisis", $asset_id, $employee_id, $allocated_date, $_SESSION['user_id'], $notes);
                    $stmt->execute();
                    
                    // Update asset status
                    $stmt = $conn->prepare("UPDATE company_assets SET status = 'Allocated' WHERE id = ?");
                    $stmt->bind_param("i", $asset_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $success_message = "Asset allocated successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error allocating asset: " . $e->getMessage();
                }
                break;
                
            case 'schedule_maintenance':
                $asset_id = $_POST['maintenance_asset_id'];
                $maintenance_type = $_POST['maintenance_type'];
                $scheduled_date = $_POST['scheduled_date'];
                $description = $_POST['maintenance_description'];
                $vendor = $_POST['vendor'];
                $cost = $_POST['estimated_cost'] ?: 0;
                
                $stmt = $conn->prepare("INSERT INTO asset_maintenance (asset_id, maintenance_type, scheduled_date, description, vendor, cost, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssdi", $asset_id, $maintenance_type, $scheduled_date, $description, $vendor, $cost, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = "Maintenance scheduled successfully!";
                } else {
                    $error_message = "Error scheduling maintenance: " . $conn->error;
                }
                break;
        }
    }
}

// Fetch data for display
$assets_query = "
    SELECT a.*, c.name as category_name,
           CASE WHEN al.id IS NOT NULL THEN CONCAT(e.first_name, ' ', e.last_name) ELSE 'Not Allocated' END as allocated_to
    FROM company_assets a 
    LEFT JOIN asset_categories c ON a.category_id = c.id
    LEFT JOIN asset_allocations al ON a.id = al.asset_id AND al.allocation_status = 'Active'
    LEFT JOIN employees e ON al.employee_id = e.id
    ORDER BY a.created_at DESC
";
$assets = $conn->query($assets_query);
if (!$assets) {
    $assets = $conn->query("SELECT * FROM company_assets ORDER BY created_at DESC");
}

$categories = $conn->query("SELECT * FROM asset_categories ORDER BY name");
if (!$categories) {
    $categories = $conn->query("SELECT 1 WHERE FALSE"); // Empty result set
}

$employees = $conn->query("SELECT id, first_name, last_name, employee_id FROM employees WHERE status = 'active' ORDER BY first_name, last_name");
if (!$employees) {
    $employees = $conn->query("SELECT id, first_name, last_name, employee_id FROM employees ORDER BY first_name, last_name");
}

$available_assets = $conn->query("SELECT id, asset_tag, name FROM company_assets WHERE status = 'Available' ORDER BY name");
if (!$available_assets) {
    $available_assets = $conn->query("SELECT 1 WHERE FALSE"); // Empty result set
}

// Asset statistics with error handling
$stats_query = "
    SELECT 
        COUNT(*) as total_assets,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available_assets,
        SUM(CASE WHEN status = 'Allocated' THEN 1 ELSE 0 END) as allocated_assets,
        SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_assets,
        SUM(purchase_cost) as total_value,
        SUM(current_value) as current_total_value
    FROM company_assets
";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
} else {
    // Default values if query fails
    $stats = [
        'total_assets' => 0,
        'available_assets' => 0,
        'allocated_assets' => 0,
        'maintenance_assets' => 0,
        'total_value' => 0,
        'current_total_value' => 0
    ];
}

$maintenance_query = "
    SELECT COUNT(*) as count 
    FROM asset_maintenance 
    WHERE status = 'Scheduled' AND scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAYS)
";
$maintenance_result = $conn->query($maintenance_query);
if ($maintenance_result) {
    $maintenance_due = $maintenance_result->fetch_assoc();
} else {
    // Default value if query fails
    $maintenance_due = ['count' => 0];
}

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-laptop text-primary me-3"></i>Asset Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage company assets, tracking, and maintenance schedules</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportAssets()">
                    <i class="bi bi-download"></i> Export Assets
                </button>
                <div class="btn-group">
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-circle"></i> New
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                            <i class="bi bi-laptop"></i> Add Asset
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#allocateAssetModal">
                            <i class="bi bi-person-gear"></i> Allocate Asset
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                            <i class="bi bi-tools"></i> Schedule Maintenance
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Asset Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-box-seam fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #1976d2;"><?= $stats['total_assets'] ?></h3>
                        <p class="text-muted mb-0">Total Assets</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #388e3c;"><?= $stats['available_assets'] ?></h3>
                        <p class="text-muted mb-0">Available</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-person-check-fill fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #f57c00;"><?= $stats['allocated_assets'] ?></h3>
                        <p class="text-muted mb-0">Allocated</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-tools fs-1" style="color: #d32f2f;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #d32f2f;"><?= $maintenance_due['count'] ?></h3>
                        <p class="text-muted mb-0">Maintenance Due</p>
                    </div>
                </div>
            </div>
        </div>

                <!-- Assets Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list"></i> Asset Inventory</h5>
                            <div class="btn-group" role="group" aria-label="Status filter">
                                <button type="button" class="btn btn-outline-primary btn-sm status-filter active" data-status="all">
                                    All Assets
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm status-filter" data-status="Available">
                                    Available
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm status-filter" data-status="Allocated">
                                    Allocated
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm status-filter" data-status="Under Maintenance">
                                    Maintenance
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="assetsTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Allocated To</th>
                                        <th>Value</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assets && $assets->num_rows > 0): ?>
                                        <?php while ($asset = $assets->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="asset-tag"><?= htmlspecialchars($asset['asset_tag']) ?></span></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                                    <?php if ($asset['brand'] && $asset['model']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($asset['brand']) ?> <?= htmlspecialchars($asset['model']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($asset['asset_type']) ?></td>
                                                <td><?= htmlspecialchars($asset['category_name'] ?? 'Uncategorized') ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'Available' => 'success',
                                                    'Allocated' => 'info',
                                                    'Under Maintenance' => 'warning',
                                                    'Retired' => 'secondary',
                                                    'Lost/Stolen' => 'danger'
                                                ];
                                                $color = $status_colors[$asset['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $color ?> status-badge"><?= $asset['status'] ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($asset['allocated_to']) ?></td>
                                            <td>
                                                <?php if ($asset['current_value']): ?>
                                                    ₹<?= number_format($asset['current_value'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($asset['status'] === 'Allocated'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Return Asset">
                                                            <i class="bi bi-arrow-return-left"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                                    <h5>No Assets Found</h5>
                                                    <p>Start by adding your first asset to the inventory.</p>
                                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                                                        <i class="bi bi-plus-circle"></i> Add First Asset
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Asset Modal -->
    <div class="modal fade" id="addAssetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_asset">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="asset_tag" class="form-label">Asset Tag *</label>
                                    <input type="text" class="form-control" id="asset_tag" name="asset_tag" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Asset Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="asset_type" class="form-label">Asset Type</label>
                                    <select class="form-select" id="asset_type" name="asset_type">
                                        <option value="IT Equipment">IT Equipment</option>
                                        <option value="Office Furniture">Office Furniture</option>
                                        <option value="Vehicle">Vehicle</option>
                                        <option value="Machinery">Machinery</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php 
                                        if ($categories && $categories->num_rows > 0) {
                                            $categories->data_seek(0);
                                            while ($category = $categories->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                        <?php 
                                            endwhile; 
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_cost" class="form-label">Purchase Cost</label>
                                    <input type="number" step="0.01" class="form-control" id="purchase_cost" name="purchase_cost">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                                    <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier" class="form-label">Supplier</label>
                                    <input type="text" class="form-control" id="supplier" name="supplier">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Allocate Asset Modal -->
    <div class="modal fade" id="allocateAssetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-gear"></i> Allocate Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="allocate_asset">
                        <div class="mb-3">
                            <label for="asset_id" class="form-label">Select Asset *</label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">Choose an available asset</option>
                                <?php if ($available_assets && $available_assets->num_rows > 0): ?>
                                    <?php while ($asset = $available_assets->fetch_assoc()): ?>
                                        <option value="<?= $asset['id'] ?>"><?= htmlspecialchars($asset['asset_tag']) ?> - <?= htmlspecialchars($asset['name']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Allocate To *</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php if ($employees && $employees->num_rows > 0): ?>
                                    <?php while ($employee = $employees->fetch_assoc()): ?>
                                        <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= htmlspecialchars($employee['employee_id']) ?>)</option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="allocated_date" class="form-label">Allocation Date *</label>
                            <input type="date" class="form-control" id="allocated_date" name="allocated_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="allocation_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="allocation_notes" name="allocation_notes" rows="3"></textarea>
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

    <!-- Schedule Maintenance Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tools"></i> Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="schedule_maintenance">
                        <div class="mb-3">
                            <label for="maintenance_asset_id" class="form-label">Select Asset *</label>
                            <select class="form-select" id="maintenance_asset_id" name="maintenance_asset_id" required>
                                <option value="">Choose an asset</option>
                                <?php 
                                $all_assets = $conn->query("SELECT id, asset_tag, name FROM company_assets ORDER BY name");
                                if ($all_assets && $all_assets->num_rows > 0):
                                    while ($asset = $all_assets->fetch_assoc()): 
                                ?>
                                    <option value="<?= $asset['id'] ?>"><?= htmlspecialchars($asset['asset_tag']) ?> - <?= htmlspecialchars($asset['name']) ?></option>
                                <?php 
                                    endwhile; 
                                endif;
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="maintenance_type" class="form-label">Maintenance Type *</label>
                            <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                                <option value="Preventive">Preventive</option>
                                <option value="Corrective">Corrective</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Upgrade">Upgrade</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="scheduled_date" class="form-label">Scheduled Date *</label>
                            <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="vendor" class="form-label">Vendor/Service Provider</label>
                            <input type="text" class="form-control" id="vendor" name="vendor">
                        </div>
                        <div class="mb-3">
                            <label for="estimated_cost" class="form-label">Estimated Cost</label>
                            <input type="number" step="0.01" class="form-control" id="estimated_cost" name="estimated_cost">
                        </div>
                        <div class="mb-3">
                            <label for="maintenance_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="maintenance_description" name="maintenance_description" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Schedule Maintenance</button>
                    </div>
                </form>
            </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables for assets table
    $('#assetsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { 
                targets: -1, 
                orderable: false,
                searchable: false
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search assets...",
            lengthMenu: "Show _MENU_ assets per page",
            info: "Showing _START_ to _END_ of _TOTAL_ assets",
            infoEmpty: "No assets found",
            infoFiltered: "(filtered from _MAX_ total assets)",
            zeroRecords: "No matching assets found"
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Asset status filter functionality
    $('.status-filter').on('click', function() {
        const status = $(this).data('status');
        const table = $('#assetsTable').DataTable();
        
        if (status === 'all') {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(status).draw();
        }
        
        // Update active button
        $('.status-filter').removeClass('active');
        $(this).addClass('active');
    });

    // Auto-generate asset tag
    $('#asset_type, #name').on('change keyup', function() {
        const type = $('#asset_type').val();
        const name = $('#name').val();
        
        if (type && name) {
            const typePrefix = type.substring(0, 2).toUpperCase();
            const namePrefix = name.substring(0, 3).toUpperCase();
            const timestamp = Date.now().toString().substr(-4);
            const assetTag = typePrefix + namePrefix + timestamp;
            $('#asset_tag').val(assetTag);
        }
    });

    // Form validation and submission
    $('form[method="POST"]').on('submit', function(e) {
        const form = $(this);
        const action = form.find('input[name="action"]').val();
        
        // Basic validation
        const requiredFields = form.find('[required]');
        let isValid = true;
        
        requiredFields.each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return false;
        }
    });

    // Real-time validation
    $('[required]').on('blur', function() {
        if ($(this).val().trim()) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
});
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
