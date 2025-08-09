<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Asset Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_asset':
            try {
                $stmt = $conn->prepare("INSERT INTO company_assets (asset_tag, name, category_id, asset_type, brand, model, serial_number, purchase_date, purchase_cost, warranty_expiry, current_value, location, supplier, notes, condition_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisssssdddssss", 
                    $_POST['asset_tag'],
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['asset_type'],
                    $_POST['brand'],
                    $_POST['model'],
                    $_POST['serial_number'],
                    $_POST['purchase_date'],
                    $_POST['purchase_cost'],
                    $_POST['warranty_expiry'],
                    $_POST['purchase_cost'], // current_value = purchase_cost initially
                    $_POST['location'],
                    $_POST['supplier'],
                    $_POST['notes'],
                    $_POST['condition_status']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Asset added successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding asset: ' . $conn->error]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_asset':
            try {
                $stmt = $conn->prepare("UPDATE company_assets SET name = ?, category_id = ?, asset_type = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, purchase_cost = ?, warranty_expiry = ?, current_value = ?, location = ?, supplier = ?, notes = ?, condition_status = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sisssssdddsssssi", 
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['asset_type'],
                    $_POST['brand'],
                    $_POST['model'],
                    $_POST['serial_number'],
                    $_POST['purchase_date'],
                    $_POST['purchase_cost'],
                    $_POST['warranty_expiry'],
                    $_POST['current_value'],
                    $_POST['location'],
                    $_POST['supplier'],
                    $_POST['notes'],
                    $_POST['condition_status'],
                    $_POST['status'],
                    $_POST['asset_id']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Asset updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating asset: ' . $conn->error]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'allocate_asset':
            try {
                $conn->begin_transaction();
                
                // Add allocation record
                $stmt = $conn->prepare("INSERT INTO asset_allocations (asset_id, employee_id, allocated_date, allocated_by, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisis", 
                    $_POST['asset_id'],
                    $_POST['employee_id'],
                    $_POST['allocated_date'],
                    $_SESSION['admin'], // Using admin session
                    $_POST['allocation_notes']
                );
                $stmt->execute();
                
                // Update asset status
                $stmt = $conn->prepare("UPDATE company_assets SET status = 'Allocated' WHERE id = ?");
                $stmt->bind_param("i", $_POST['asset_id']);
                $stmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset allocated successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error allocating asset: ' . $e->getMessage()]);
            }
            exit;
            
        case 'return_asset':
            try {
                $conn->begin_transaction();
                
                // Update allocation record
                $stmt = $conn->prepare("UPDATE asset_allocations SET return_date = ?, allocation_status = 'Returned', return_condition = ? WHERE asset_id = ? AND allocation_status = 'Active'");
                $stmt->bind_param("ssi", 
                    $_POST['return_date'],
                    $_POST['return_condition'],
                    $_POST['asset_id']
                );
                $stmt->execute();
                
                // Update asset status and condition
                $stmt = $conn->prepare("UPDATE company_assets SET status = 'Available', condition_status = ? WHERE id = ?");
                $stmt->bind_param("si", $_POST['return_condition'], $_POST['asset_id']);
                $stmt->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset returned successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error returning asset: ' . $e->getMessage()]);
            }
            exit;
            
        case 'schedule_maintenance':
            try {
                $stmt = $conn->prepare("INSERT INTO asset_maintenance (asset_id, maintenance_type, scheduled_date, description, vendor, cost, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssdi", 
                    $_POST['maintenance_asset_id'],
                    $_POST['maintenance_type'],
                    $_POST['scheduled_date'],
                    $_POST['maintenance_description'],
                    $_POST['vendor'],
                    $_POST['estimated_cost'],
                    $_SESSION['admin']
                );
                
                if ($stmt->execute()) {
                    // Update asset status if needed
                    if ($_POST['maintenance_type'] === 'Emergency' || $_POST['maintenance_type'] === 'Corrective') {
                        $stmt = $conn->prepare("UPDATE company_assets SET status = 'Under Maintenance' WHERE id = ?");
                        $stmt->bind_param("i", $_POST['maintenance_asset_id']);
                        $stmt->execute();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Maintenance scheduled successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error scheduling maintenance: ' . $conn->error]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_asset':
            try {
                // Check if asset is allocated
                $check = $conn->prepare("SELECT COUNT(*) as count FROM asset_allocations WHERE asset_id = ? AND allocation_status = 'Active'");
                $check->bind_param("i", $_POST['asset_id']);
                $check->execute();
                $result = $check->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete asset: Asset is currently allocated']);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM company_assets WHERE id = ?");
                $stmt->bind_param("i", $_POST['asset_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Asset deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting asset: ' . $conn->error]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_asset_details':
            try {
                $stmt = $conn->prepare("SELECT a.*, c.name as category_name FROM company_assets a LEFT JOIN asset_categories c ON a.category_id = c.id WHERE a.id = ?");
                $stmt->bind_param("i", $_POST['asset_id']);
                $stmt->execute();
                $asset = $stmt->get_result()->fetch_assoc();
                
                if ($asset) {
                    echo json_encode(['success' => true, 'asset' => $asset]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Asset not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

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
        asset_type ENUM('IT Equipment', 'Office Furniture', 'Vehicle', 'Machinery', 'Electronics', 'Other') DEFAULT 'IT Equipment',
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
        FOREIGN KEY (asset_id) REFERENCES company_assets(id) ON DELETE CASCADE
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
        FOREIGN KEY (asset_id) REFERENCES company_assets(id) ON DELETE CASCADE
    )"
];

foreach ($asset_tables_sql as $sql) {
    $conn->query($sql);
}

// Insert default asset categories if none exist
$check_categories = $conn->query("SELECT COUNT(*) as count FROM asset_categories")->fetch_assoc();
if ($check_categories['count'] == 0) {
    $default_categories = [
        ['IT Equipment', 'Computers, laptops, printers, networking equipment'],
        ['Office Furniture', 'Desks, chairs, cabinets, meeting room furniture'],
        ['Vehicles', 'Company cars, trucks, motorcycles'],
        ['Machinery', 'Industrial equipment, tools, production machinery'],
        ['Electronics', 'Mobile phones, tablets, cameras, audio equipment']
    ];
    
    foreach ($default_categories as $category) {
        $stmt = $conn->prepare("INSERT INTO asset_categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category[0], $category[1]);
        $stmt->execute();
    }
}

// Fetch data for display
try {
    // Asset statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_assets,
        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_assets,
        COUNT(CASE WHEN status = 'Allocated' THEN 1 END) as allocated_assets,
        COUNT(CASE WHEN status = 'Under Maintenance' THEN 1 END) as maintenance_assets,
        SUM(CASE WHEN current_value IS NOT NULL THEN current_value ELSE purchase_cost END) as total_value
    FROM company_assets";
    
    $stats = $conn->query($stats_sql)->fetch_assoc();
    
    // Maintenance due (warranty expiring in 30 days)
    $maintenance_due_sql = "SELECT COUNT(*) as count FROM company_assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $maintenance_due = $conn->query($maintenance_due_sql)->fetch_assoc();
    
    // Assets with details
    $assets_sql = "SELECT a.*, c.name as category_name,
                   CASE WHEN al.id IS NOT NULL THEN e.name ELSE 'Not Allocated' END as allocated_to,
                   al.allocated_date
                   FROM company_assets a 
                   LEFT JOIN asset_categories c ON a.category_id = c.id
                   LEFT JOIN asset_allocations al ON a.id = al.asset_id AND al.allocation_status = 'Active'
                   LEFT JOIN employees e ON al.employee_id = e.employee_id
                   ORDER BY a.created_at DESC";
    
    $assets_result = $conn->query($assets_sql);
    $assets = [];
    if ($assets_result) {
        while ($row = $assets_result->fetch_assoc()) {
            $assets[] = $row;
        }
    }
    
    // Asset categories
    $categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY name");
    $categories = [];
    if ($categories_result) {
        while ($row = $categories_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    // Employees for allocation
    $employees_result = $conn->query("SELECT employee_id, name, department_name FROM employees WHERE status = 'active' ORDER BY name");
    $employees = [];
    if ($employees_result) {
        while ($row = $employees_result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    
    // Recent maintenance
    $maintenance_sql = "SELECT m.*, a.name as asset_name, a.asset_tag 
                       FROM asset_maintenance m 
                       LEFT JOIN company_assets a ON m.asset_id = a.id 
                       ORDER BY m.created_at DESC 
                       LIMIT 10";
    $maintenance_result = $conn->query($maintenance_sql);
    $recent_maintenance = [];
    if ($maintenance_result) {
        while ($row = $maintenance_result->fetch_assoc()) {
            $recent_maintenance[] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("Asset Management Error: " . $e->getMessage());
    $stats = ['total_assets' => 0, 'available_assets' => 0, 'allocated_assets' => 0, 'maintenance_assets' => 0, 'total_value' => 0];
    $maintenance_due = ['count' => 0];
    $assets = [];
    $categories = [];
    $employees = [];
    $recent_maintenance = [];
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🏢 Asset Management</h1>
                <p class="text-muted">Manage company assets, allocations, and maintenance</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Asset
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#allocateAssetModal">
                    <i class="bi bi-person-check me-1"></i>Allocate Asset
                </button>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                    <i class="bi bi-tools me-1"></i>Schedule Maintenance
                </button>
                <button type="button" class="btn btn-info" onclick="generateReport()">
                    <i class="bi bi-file-earmark-text me-1"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Asset Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-box-seam fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['total_assets'] ?></h3>
                        <small class="opacity-75">Total Assets</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['available_assets'] ?></h3>
                        <small class="opacity-75">Available</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['allocated_assets'] ?></h3>
                        <small class="opacity-75">Allocated</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-tools fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $maintenance_due['count'] ?></h3>
                        <small class="opacity-75">Maintenance Due</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-dollar fs-2" style="color: #28a745;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #333;">$<?= number_format($stats['total_value'], 0) ?></h3>
                        <small style="color: #666;">Total Value</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets Table -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list me-2 text-primary"></i>Asset Inventory</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active status-filter" data-status="all">All</button>
                            <button type="button" class="btn btn-outline-success status-filter" data-status="Available">Available</button>
                            <button type="button" class="btn btn-outline-info status-filter" data-status="Allocated">Allocated</button>
                            <button type="button" class="btn btn-outline-warning status-filter" data-status="Under Maintenance">Maintenance</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="assetsTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Allocated To</th>
                                        <th>Value</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets as $asset): ?>
                                    <tr data-status="<?= $asset['status'] ?>">
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($asset['asset_tag']) ?></span></td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($asset['brand'] ?? '') ?> <?= htmlspecialchars($asset['model'] ?? '') ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($asset['asset_type']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $asset['status'] == 'Available' ? 'success' : 
                                                ($asset['status'] == 'Allocated' ? 'info' : 
                                                ($asset['status'] == 'Under Maintenance' ? 'warning' : 'danger')) 
                                            ?>">
                                                <?= $asset['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($asset['allocated_to']) ?></td>
                                        <td>$<?= number_format($asset['current_value'] ?? $asset['purchase_cost'] ?? 0, 0) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary" onclick="viewAsset(<?= $asset['id'] ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="editAsset(<?= $asset['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($asset['status'] == 'Allocated'): ?>
                                                    <button class="btn btn-outline-warning" onclick="returnAsset(<?= $asset['id'] ?>)" title="Return Asset">
                                                        <i class="bi bi-arrow-left"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-danger" onclick="deleteAsset(<?= $asset['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="bi bi-tools me-2 text-warning"></i>Recent Maintenance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_maintenance)): ?>
                            <?php foreach (array_slice($recent_maintenance, 0, 5) as $maintenance): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="bi bi-<?= $maintenance['maintenance_type'] == 'Emergency' ? 'exclamation-triangle text-danger' : 'gear text-warning' ?> fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($maintenance['asset_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($maintenance['maintenance_type']) ?> • <?= htmlspecialchars($maintenance['asset_tag']) ?></small>
                                    <div class="text-muted small"><?= date('M d, Y', strtotime($maintenance['scheduled_date'])) ?></div>
                                </div>
                                <div>
                                    <span class="badge bg-<?= $maintenance['status'] == 'Completed' ? 'success' : ($maintenance['status'] == 'In Progress' ? 'warning' : 'secondary') ?>">
                                        <?= $maintenance['status'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No maintenance activities</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addAssetForm">
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
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="asset_type" class="form-label">Asset Type *</label>
                                <select class="form-select" id="asset_type" name="asset_type" required>
                                    <option value="IT Equipment">IT Equipment</option>
                                    <option value="Office Furniture">Office Furniture</option>
                                    <option value="Vehicle">Vehicle</option>
                                    <option value="Machinery">Machinery</option>
                                    <option value="Electronics">Electronics</option>
                                    <option value="Other">Other</option>
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
                                <input type="number" class="form-control" id="purchase_cost" name="purchase_cost" step="0.01">
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
                                <label for="condition_status" class="form-label">Condition</label>
                                <select class="form-select" id="condition_status" name="condition_status">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="supplier" class="form-label">Supplier</label>
                        <input type="text" class="form-control" id="supplier" name="supplier">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addAsset()">Add Asset</button>
            </div>
        </div>
    </div>
</div>

<!-- Allocate Asset Modal -->
<div class="modal fade" id="allocateAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allocate Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="allocateAssetForm">
                    <div class="mb-3">
                        <label for="allocate_asset_id" class="form-label">Select Asset *</label>
                        <select class="form-select" id="allocate_asset_id" name="asset_id" required>
                            <option value="">Choose Asset...</option>
                            <?php foreach ($assets as $asset): ?>
                                <?php if ($asset['status'] == 'Available'): ?>
                                    <option value="<?= $asset['id'] ?>"><?= htmlspecialchars($asset['asset_tag']) ?> - <?= htmlspecialchars($asset['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="allocate_employee_id" class="form-label">Allocate to Employee *</label>
                        <select class="form-select" id="allocate_employee_id" name="employee_id" required>
                            <option value="">Choose Employee...</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?></option>
                            <?php endforeach; ?>
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="allocateAsset()">Allocate Asset</button>
            </div>
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
            <div class="modal-body">
                <form id="returnAssetForm">
                    <input type="hidden" id="return_asset_id" name="asset_id">
                    <div class="mb-3">
                        <label for="return_date" class="form-label">Return Date *</label>
                        <input type="date" class="form-control" id="return_date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="return_condition" class="form-label">Return Condition *</label>
                        <select class="form-select" id="return_condition" name="return_condition" required>
                            <option value="">Select Condition...</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="returnAsset()">Return Asset</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Maintenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <div class="mb-3">
                        <label for="maintenance_asset_id" class="form-label">Select Asset *</label>
                        <select class="form-select" id="maintenance_asset_id" name="maintenance_asset_id" required>
                            <option value="">Choose Asset...</option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset['id'] ?>"><?= htmlspecialchars($asset['asset_tag']) ?> - <?= htmlspecialchars($asset['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="maintenance_type" class="form-label">Maintenance Type *</label>
                        <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                            <option value="">Select Type...</option>
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
                        <input type="number" class="form-control" id="estimated_cost" name="estimated_cost" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="maintenance_description" class="form-label">Description *</label>
                        <textarea class="form-control" id="maintenance_description" name="maintenance_description" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="scheduleMaintenance()">Schedule Maintenance</button>
            </div>
        </div>
    </div>
</div>

<!-- View Asset Details Modal -->
<div class="modal fade" id="viewAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asset Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assetDetailsContent">
                <!-- Asset details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status filter functionality
    const statusFilters = document.querySelectorAll('.status-filter');
    statusFilters.forEach(filter => {
        filter.addEventListener('click', function() {
            const status = this.dataset.status;
            
            // Update active button
            statusFilters.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter table rows
            const tableRows = document.querySelectorAll('#assetsTable tbody tr');
            tableRows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});

function addAsset() {
    const formData = new FormData(document.getElementById('addAssetForm'));
    formData.append('action', 'add_asset');
    
    fetch('asset_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Asset added successfully!', 'success');
            document.getElementById('addAssetForm').reset();
            $('#addAssetModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error adding asset', 'error');
    });
}

function allocateAsset() {
    const formData = new FormData(document.getElementById('allocateAssetForm'));
    formData.append('action', 'allocate_asset');
    
    fetch('asset_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Asset allocated successfully!', 'success');
            document.getElementById('allocateAssetForm').reset();
            $('#allocateAssetModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error allocating asset', 'error');
    });
}

function returnAsset(assetId = null) {
    if (assetId) {
        document.getElementById('return_asset_id').value = assetId;
        $('#returnAssetModal').modal('show');
    } else {
        const formData = new FormData(document.getElementById('returnAssetForm'));
        formData.append('action', 'return_asset');
        
        fetch('asset_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Asset returned successfully!', 'success');
                document.getElementById('returnAssetForm').reset();
                $('#returnAssetModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error returning asset', 'error');
        });
    }
}

function scheduleMaintenance() {
    const formData = new FormData(document.getElementById('maintenanceForm'));
    formData.append('action', 'schedule_maintenance');
    
    fetch('asset_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Maintenance scheduled successfully!', 'success');
            document.getElementById('maintenanceForm').reset();
            $('#maintenanceModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error scheduling maintenance', 'error');
    });
}

function viewAsset(assetId) {
    const formData = new FormData();
    formData.append('action', 'get_asset_details');
    formData.append('asset_id', assetId);
    
    fetch('asset_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAssetDetails(data.asset);
            $('#viewAssetModal').modal('show');
        } else {
            showAlert('Error loading asset details', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading asset details', 'error');
    });
}

function displayAssetDetails(asset) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Asset Tag:</strong></td><td>${asset.asset_tag}</td></tr>
                    <tr><td><strong>Name:</strong></td><td>${asset.name}</td></tr>
                    <tr><td><strong>Type:</strong></td><td>${asset.asset_type}</td></tr>
                    <tr><td><strong>Category:</strong></td><td>${asset.category_name || 'N/A'}</td></tr>
                    <tr><td><strong>Brand:</strong></td><td>${asset.brand || 'N/A'}</td></tr>
                    <tr><td><strong>Model:</strong></td><td>${asset.model || 'N/A'}</td></tr>
                    <tr><td><strong>Serial Number:</strong></td><td>${asset.serial_number || 'N/A'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Financial & Status</h6>
                <table class="table table-sm">
                    <tr><td><strong>Purchase Date:</strong></td><td>${asset.purchase_date || 'N/A'}</td></tr>
                    <tr><td><strong>Purchase Cost:</strong></td><td>$${asset.purchase_cost || '0'}</td></tr>
                    <tr><td><strong>Current Value:</strong></td><td>$${asset.current_value || asset.purchase_cost || '0'}</td></tr>
                    <tr><td><strong>Warranty Expiry:</strong></td><td>${asset.warranty_expiry || 'N/A'}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-info">${asset.status}</span></td></tr>
                    <tr><td><strong>Condition:</strong></td><td><span class="badge bg-success">${asset.condition_status}</span></td></tr>
                    <tr><td><strong>Location:</strong></td><td>${asset.location || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <h6>Additional Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Supplier:</strong></td><td>${asset.supplier || 'N/A'}</td></tr>
                    <tr><td><strong>Notes:</strong></td><td>${asset.notes || 'No notes'}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('assetDetailsContent').innerHTML = content;
}

function editAsset(assetId) {
    // For now, redirect to view details - can be enhanced to edit modal
    viewAsset(assetId);
}

function deleteAsset(assetId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_asset');
            formData.append('asset_id', assetId);
            
            fetch('asset_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Asset deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error deleting asset', 'error');
            });
        }
    });
}

function generateReport() {
    // Simple CSV export
    window.location.href = 'asset_reports.php?format=csv';
}

function showAlert(message, type = 'info') {
    Swal.fire({
        title: type === 'error' ? 'Error!' : 'Success!',
        text: message,
        icon: type,
        timer: 3000,
        showConfirmButton: false
    });
}
</script>

<style>
.col-xl-2-4 {
    flex: 0 0 20%;
    max-width: 20%;
}

@media (max-width: 1200px) {
    .col-xl-2-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (max-width: 768px) {
    .col-xl-2-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

.stats-card:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.card {
    border: none;
}

.card-header {
    font-weight: 600;
}

.asset-tag {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.badge {
    font-size: 0.8em;
}

.btn-group-sm .btn {
    font-size: 0.875rem;
}
</style>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>
