<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Warehouse Management System';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_warehouse':
            $name = mysqli_real_escape_string($conn, $_POST['warehouse_name']);
            $location = mysqli_real_escape_string($conn, $_POST['location']);
            $capacity = intval($_POST['capacity']);
            $type = mysqli_real_escape_string($conn, $_POST['warehouse_type']);
            $manager = mysqli_real_escape_string($conn, $_POST['manager_name']);
            $contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $address = mysqli_real_escape_string($conn, $_POST['full_address']);
            $zones = intval($_POST['storage_zones']);
            $temperature_controlled = isset($_POST['temperature_controlled']) ? 1 : 0;
            $security_level = mysqli_real_escape_string($conn, $_POST['security_level']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            $query = "INSERT INTO warehouses (name, location, capacity, warehouse_type, manager_name, contact_number, 
                      email, full_address, storage_zones, temperature_controlled, security_level, notes, status) 
                      VALUES ('$name', '$location', $capacity, '$type', '$manager', '$contact', 
                      '$email', '$address', $zones, $temperature_controlled, '$security_level', '$notes', 'active')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Warehouse added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding warehouse: ' . $conn->error]);
            }
            exit;
            
        case 'receive_goods':
            $warehouse_id = intval($_POST['warehouse_id']);
            $supplier_id = intval($_POST['supplier_id']);
            $po_number = mysqli_real_escape_string($conn, $_POST['po_number']);
            $received_by = mysqli_real_escape_string($conn, $_POST['received_by']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            // Create goods receipt
            $receipt_number = 'GR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO goods_receipts (receipt_number, warehouse_id, supplier_id, po_number, 
                      received_date, received_by, status, notes) 
                      VALUES ('$receipt_number', $warehouse_id, $supplier_id, '$po_number', NOW(), 
                      '$received_by', 'pending', '$notes')";
            
            if (mysqli_query($conn, $query)) {
                $receipt_id = mysqli_insert_id($conn);
                
                // Process items
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $item) {
                        $item_id = intval($item['item_id']);
                        $expected_qty = intval($item['expected_qty']);
                        $received_qty = intval($item['received_qty']);
                        $condition_status = mysqli_real_escape_string($conn, $item['condition']);
                        $location = mysqli_real_escape_string($conn, $item['location']);
                        
                        // Insert receipt item
                        $item_query = "INSERT INTO goods_receipt_items (receipt_id, item_id, expected_quantity, 
                                       received_quantity, condition_status, storage_location) 
                                       VALUES ($receipt_id, $item_id, $expected_qty, $received_qty, 
                                       '$condition_status', '$location')";
                        mysqli_query($conn, $item_query);
                        
                        // Update item stock if condition is good
                        if ($condition_status === 'good' && $received_qty > 0) {
                            $stock_update = "UPDATE items SET stock = COALESCE(stock, 0) + $received_qty WHERE id = $item_id";
                            mysqli_query($conn, $stock_update);
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Goods received successfully!', 'receipt_number' => $receipt_number]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error receiving goods: ' . $conn->error]);
            }
            exit;
            
        case 'create_picking_list':
            $warehouse_id = intval($_POST['warehouse_id']);
            $order_type = mysqli_real_escape_string($conn, $_POST['order_type']);
            $priority = mysqli_real_escape_string($conn, $_POST['priority']);
            $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            // Create picking list
            $pick_number = 'PL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO picking_lists (pick_number, warehouse_id, order_type, priority, 
                      assigned_to, status, notes, created_date) 
                      VALUES ('$pick_number', $warehouse_id, '$order_type', '$priority', 
                      '$assigned_to', 'pending', '$notes', NOW())";
            
            if (mysqli_query($conn, $query)) {
                $pick_id = mysqli_insert_id($conn);
                
                // Process items
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $item) {
                        $item_id = intval($item['item_id']);
                        $quantity = intval($item['quantity']);
                        $location = mysqli_real_escape_string($conn, $item['location']);
                        
                        $item_query = "INSERT INTO picking_list_items (pick_id, item_id, quantity_requested, 
                                       storage_location, status) 
                                       VALUES ($pick_id, $item_id, $quantity, '$location', 'pending')";
                        mysqli_query($conn, $item_query);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Picking list created successfully!', 'pick_number' => $pick_number]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating picking list: ' . $conn->error]);
            }
            exit;
            
        case 'update_warehouse':
            $id = intval($_POST['warehouse_id']);
            $name = mysqli_real_escape_string($conn, $_POST['warehouse_name']);
            $location = mysqli_real_escape_string($conn, $_POST['location']);
            $capacity = intval($_POST['capacity']);
            $manager = mysqli_real_escape_string($conn, $_POST['manager_name']);
            $contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE warehouses SET name = '$name', location = '$location', capacity = $capacity, 
                      manager_name = '$manager', contact_number = '$contact', status = '$status' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Warehouse updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating warehouse: ' . $conn->error]);
            }
            exit;
            
        case 'delete_warehouse':
            $id = intval($_POST['warehouse_id']);
            $query = "UPDATE warehouses SET status = 'inactive' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Warehouse deactivated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deactivating warehouse: ' . $conn->error]);
            }
            exit;
    }
}

// Create warehouse management tables
$tables = [
    "CREATE TABLE IF NOT EXISTS warehouses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        capacity INT DEFAULT 0,
        warehouse_type ENUM('main', 'distribution', 'cold_storage', 'overflow') DEFAULT 'main',
        manager_name VARCHAR(255),
        contact_number VARCHAR(50),
        email VARCHAR(255),
        full_address TEXT,
        storage_zones INT DEFAULT 1,
        temperature_controlled BOOLEAN DEFAULT FALSE,
        security_level ENUM('basic', 'medium', 'high') DEFAULT 'medium',
        notes TEXT,
        status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS goods_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receipt_number VARCHAR(100) UNIQUE NOT NULL,
        warehouse_id INT,
        supplier_id INT,
        po_number VARCHAR(100),
        received_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        received_by VARCHAR(255),
        status ENUM('pending', 'completed', 'discrepancy') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS goods_receipt_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receipt_id INT,
        item_id INT,
        expected_quantity INT,
        received_quantity INT,
        condition_status ENUM('good', 'damaged', 'expired') DEFAULT 'good',
        storage_location VARCHAR(100),
        notes TEXT,
        FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS picking_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pick_number VARCHAR(100) UNIQUE NOT NULL,
        warehouse_id INT,
        order_type ENUM('sales', 'transfer', 'return') DEFAULT 'sales',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        assigned_to VARCHAR(255),
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_date TIMESTAMP NULL,
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS picking_list_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pick_id INT,
        item_id INT,
        quantity_requested INT,
        quantity_picked INT DEFAULT 0,
        storage_location VARCHAR(100),
        status ENUM('pending', 'picked', 'unavailable') DEFAULT 'pending',
        notes TEXT,
        FOREIGN KEY (pick_id) REFERENCES picking_lists(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        warehouse_id INT,
        item_id INT,
        movement_type ENUM('in', 'out', 'transfer', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        reference_number VARCHAR(100),
        reference_type ENUM('receipt', 'picking', 'transfer', 'adjustment'),
        notes TEXT,
        created_by VARCHAR(255),
        movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )"
];

foreach ($tables as $table) {
    mysqli_query($conn, $table);
}

// Get warehouse statistics
$stats = [
    'total_warehouses' => 0,
    'active_warehouses' => 0,
    'total_capacity' => 0,
    'pending_receipts' => 0,
    'pending_picks' => 0,
    'stock_movements_today' => 0
];

$warehouse_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_warehouses,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_warehouses,
        SUM(capacity) as total_capacity
    FROM warehouses
");

if ($warehouse_stats && $row = $warehouse_stats->fetch_assoc()) {
    $stats['total_warehouses'] = $row['total_warehouses'];
    $stats['active_warehouses'] = $row['active_warehouses'];
    $stats['total_capacity'] = $row['total_capacity'];
}

// Get operational statistics
$operations_stats = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM goods_receipts WHERE status = 'pending') as pending_receipts,
        (SELECT COUNT(*) FROM picking_lists WHERE status IN ('pending', 'in_progress')) as pending_picks,
        (SELECT COUNT(*) FROM stock_movements WHERE DATE(movement_date) = CURDATE()) as movements_today
");

if ($operations_stats && $row = $operations_stats->fetch_assoc()) {
    $stats['pending_receipts'] = $row['pending_receipts'];
    $stats['pending_picks'] = $row['pending_picks'];
    $stats['stock_movements_today'] = $row['movements_today'];
}

// Get warehouses
$warehouses = mysqli_query($conn, "SELECT * FROM warehouses ORDER BY name ASC");

// Get recent goods receipts
$recent_receipts = mysqli_query($conn, "
    SELECT gr.*, w.name as warehouse_name, s.supplier_name 
    FROM goods_receipts gr
    LEFT JOIN warehouses w ON gr.warehouse_id = w.id
    LEFT JOIN suppliers s ON gr.supplier_id = s.id
    ORDER BY gr.received_date DESC
    LIMIT 10
");

// Get active picking lists
$active_picks = mysqli_query($conn, "
    SELECT pl.*, w.name as warehouse_name 
    FROM picking_lists pl
    LEFT JOIN warehouses w ON pl.warehouse_id = w.id
    WHERE pl.status IN ('pending', 'in_progress')
    ORDER BY pl.priority DESC, pl.created_date ASC
    LIMIT 10
");

// Get suppliers for dropdown
$suppliers = mysqli_query($conn, "SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name");

// Get items for dropdown  
$items = mysqli_query($conn, "SELECT id, item_name, stock FROM items ORDER BY item_name ASC");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🏭 Warehouse Management System</h1>
                <p class="text-muted">Complete warehouse operations, goods receipt, and inventory control</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                    <i class="bi bi-building-add me-1"></i>Add Warehouse
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiveGoodsModal">
                    <i class="bi bi-box-arrow-in-down me-1"></i>Receive Goods
                </button>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pickingListModal">
                    <i class="bi bi-clipboard-check me-1"></i>Create Pick List
                </button>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-building fs-2" style="color: #1976d2;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #1976d2;"><?= $stats['total_warehouses'] ?></h4>
                        <small class="text-muted">Total Warehouses</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2" style="color: #388e3c;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #388e3c;"><?= $stats['active_warehouses'] ?></h4>
                        <small class="text-muted">Active Warehouses</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-boxes fs-2" style="color: #f57c00;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #f57c00;"><?= number_format($stats['total_capacity']) ?></h4>
                        <small class="text-muted">Total Capacity</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-arrow-down-circle fs-2" style="color: #c2185b;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #c2185b;"><?= $stats['pending_receipts'] ?></h4>
                        <small class="text-muted">Pending Receipts</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-check fs-2" style="color: #7b1fa2;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= $stats['pending_picks'] ?></h4>
                        <small class="text-muted">Pending Picks</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-arrow-left-right fs-2" style="color: #00695c;"></i>
                        </div>
                        <h4 class="mb-1 fw-bold" style="color: #00695c;"><?= $stats['stock_movements_today'] ?></h4>
                        <small class="text-muted">Movements Today</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="warehouseTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#warehouses" type="button">
                                    <i class="bi bi-building me-2"></i>Warehouses
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#goods-receipts" type="button">
                                    <i class="bi bi-box-arrow-in-down me-2"></i>Goods Receipts
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#picking-lists" type="button">
                                    <i class="bi bi-clipboard-check me-2"></i>Picking Lists
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stock-movements" type="button">
                                    <i class="bi bi-arrow-left-right me-2"></i>Stock Movements
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Warehouses Tab -->
                            <div class="tab-pane fade show active" id="warehouses" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="warehousesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Warehouse Name</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Capacity</th>
                                                <th>Manager</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($warehouse = $warehouses->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <i class="bi bi-building-fill text-primary fs-4"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?= htmlspecialchars($warehouse['name']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($warehouse['warehouse_type']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($warehouse['location']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $warehouse['warehouse_type'] === 'main' ? 'primary' : ($warehouse['warehouse_type'] === 'cold_storage' ? 'info' : 'secondary') ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $warehouse['warehouse_type'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($warehouse['capacity']) ?> sq ft</td>
                                                <td>
                                                    <div><?= htmlspecialchars($warehouse['manager_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($warehouse['contact_number']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $warehouse['status'] === 'active' ? 'success' : ($warehouse['status'] === 'maintenance' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($warehouse['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary edit-warehouse" 
                                                                data-warehouse='<?= json_encode($warehouse) ?>'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info view-warehouse" 
                                                                data-id="<?= $warehouse['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-warehouse" 
                                                                data-id="<?= $warehouse['id'] ?>"
                                                                data-name="<?= htmlspecialchars($warehouse['name']) ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Goods Receipts Tab -->
                            <div class="tab-pane fade" id="goods-receipts" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="receiptsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Receipt #</th>
                                                <th>Warehouse</th>
                                                <th>Supplier</th>
                                                <th>PO Number</th>
                                                <th>Received Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($receipt = $recent_receipts->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= $receipt['receipt_number'] ?></div>
                                                    <small class="text-muted">by <?= htmlspecialchars($receipt['received_by']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($receipt['warehouse_name']) ?></td>
                                                <td><?= htmlspecialchars($receipt['supplier_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($receipt['po_number']) ?></td>
                                                <td><?= date('M j, Y g:i A', strtotime($receipt['received_date'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $receipt['status'] === 'completed' ? 'success' : ($receipt['status'] === 'discrepancy' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($receipt['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-receipt" 
                                                            data-id="<?= $receipt['id'] ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Picking Lists Tab -->
                            <div class="tab-pane fade" id="picking-lists" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="pickingTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Pick #</th>
                                                <th>Warehouse</th>
                                                <th>Type</th>
                                                <th>Priority</th>
                                                <th>Assigned To</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($pick = $active_picks->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-bold"><?= $pick['pick_number'] ?></td>
                                                <td><?= htmlspecialchars($pick['warehouse_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $pick['order_type'] === 'sales' ? 'primary' : ($pick['order_type'] === 'transfer' ? 'info' : 'warning') ?>">
                                                        <?= ucfirst($pick['order_type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $pick['priority'] === 'urgent' ? 'danger' : ($pick['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($pick['priority']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($pick['assigned_to']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $pick['status'] === 'completed' ? 'success' : ($pick['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                        <?= str_replace('_', ' ', ucfirst($pick['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, g:i A', strtotime($pick['created_date'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-picking" 
                                                            data-id="<?= $pick['id'] ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Stock Movements Tab -->
                            <div class="tab-pane fade" id="stock-movements" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-arrow-left-right fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">Stock Movement Tracking</h5>
                                    <p class="text-muted">Real-time inventory movement tracking will be displayed here</p>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Stock movements are automatically tracked when goods are received or picked
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

<!-- Add Warehouse Modal -->
<div class="modal fade" id="addWarehouseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addWarehouseForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacity (sq ft)</label>
                            <input type="number" name="capacity" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warehouse Type</label>
                            <select name="warehouse_type" class="form-select">
                                <option value="main">Main Warehouse</option>
                                <option value="distribution">Distribution Center</option>
                                <option value="cold_storage">Cold Storage</option>
                                <option value="overflow">Overflow Storage</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Manager Name</label>
                            <input type="text" name="manager_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Storage Zones</label>
                            <input type="number" name="storage_zones" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full Address</label>
                            <textarea name="full_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Security Level</label>
                            <select name="security_level" class="form-select">
                                <option value="basic">Basic</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="temperature_controlled" id="tempControlled">
                                <label class="form-check-label" for="tempControlled">
                                    Temperature Controlled
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receive Goods Modal -->
<div class="modal fade" id="receiveGoodsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receive Goods</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="receiveGoodsForm">
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">Select Warehouse</option>
                                <?php 
                                $warehouses->data_seek(0);
                                while ($wh = $warehouses->fetch_assoc()): 
                                ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Number</label>
                            <input type="text" name="po_number" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Received By</label>
                            <input type="text" name="received_by" class="form-control" value="<?= $_SESSION['admin'] ?>">
                        </div>
                    </div>
                    
                    <h6>Items to Receive</h6>
                    <div id="receiveItemsContainer">
                        <div class="row g-2 mb-2 receive-item-row">
                            <div class="col-md-3">
                                <select name="items[0][item_id]" class="form-select item-select" required>
                                    <option value="">Select Item</option>
                                    <?php 
                                    $items->data_seek(0);
                                    while ($item = $items->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (Stock: <?= $item['stock'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="items[0][expected_qty]" placeholder="Expected Qty" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="items[0][received_qty]" placeholder="Received Qty" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-2">
                                <select name="items[0][condition]" class="form-select">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="items[0][location]" placeholder="Location" class="form-control">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-success add-receive-item">+</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Process Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Picking List Modal -->
<div class="modal fade" id="pickingListModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Picking List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pickingListForm">
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">Select Warehouse</option>
                                <?php 
                                $warehouses->data_seek(0);
                                while ($wh = $warehouses->fetch_assoc()): 
                                ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order Type</label>
                            <select name="order_type" class="form-select">
                                <option value="sales">Sales Order</option>
                                <option value="transfer">Transfer</option>
                                <option value="return">Return</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Assign To</label>
                            <input type="text" name="assigned_to" class="form-control">
                        </div>
                    </div>
                    
                    <h6>Items to Pick</h6>
                    <div id="pickItemsContainer">
                        <div class="row g-2 mb-2 pick-item-row">
                            <div class="col-md-4">
                                <select name="items[0][item_id]" class="form-select item-select" required>
                                    <option value="">Select Item</option>
                                    <?php 
                                    $items->data_seek(0);
                                    while ($item = $items->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (Stock: <?= $item['stock'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="items[0][quantity]" placeholder="Quantity" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="items[0][location]" placeholder="Storage Location" class="form-control">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-success add-pick-item">+</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Create Picking List</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Warehouse Modal -->
<div class="modal fade" id="editWarehouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editWarehouseForm">
                <div class="modal-body">
                    <input type="hidden" name="warehouse_id" id="edit_warehouse_id">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Warehouse Name</label>
                            <input type="text" name="warehouse_name" id="edit_warehouse_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Manager Name</label>
                            <input type="text" name="manager_name" id="edit_manager_name" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact_number" id="edit_contact_number" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#warehousesTable, #receiptsTable, #pickingTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, "asc"]]
    });

    // Add Warehouse Form
    $('#addWarehouseForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_warehouse');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#addWarehouseModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Receive Goods Form
    $('#receiveGoodsForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'receive_goods');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`${response.message} Receipt Number: ${response.receipt_number}`, 'success');
                    $('#receiveGoodsModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Picking List Form
    $('#pickingListForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_picking_list');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`${response.message} Pick Number: ${response.pick_number}`, 'success');
                    $('#pickingListModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Add more receive items
    $(document).on('click', '.add-receive-item', function() {
        const container = $('#receiveItemsContainer');
        const index = container.children().length;
        const newRow = $(this).closest('.receive-item-row').clone();
        
        // Update field names with new index
        newRow.find('select, input').each(function() {
            const name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, `[${index}]`));
                $(this).val('');
            }
        });
        
        // Change + to - for removal
        newRow.find('.add-receive-item').removeClass('add-receive-item btn-outline-success')
              .addClass('remove-receive-item btn-outline-danger').text('×');
        
        container.append(newRow);
    });

    // Add more pick items
    $(document).on('click', '.add-pick-item', function() {
        const container = $('#pickItemsContainer');
        const index = container.children().length;
        const newRow = $(this).closest('.pick-item-row').clone();
        
        // Update field names with new index
        newRow.find('select, input').each(function() {
            const name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, `[${index}]`));
                $(this).val('');
            }
        });
        
        // Change + to - for removal
        newRow.find('.add-pick-item').removeClass('add-pick-item btn-outline-success')
              .addClass('remove-pick-item btn-outline-danger').text('×');
        
        container.append(newRow);
    });

    // Remove item rows
    $(document).on('click', '.remove-receive-item, .remove-pick-item', function() {
        $(this).closest('.row').remove();
    });

    // Edit Warehouse
    $(document).on('click', '.edit-warehouse', function() {
        const warehouse = $(this).data('warehouse');
        $('#edit_warehouse_id').val(warehouse.id);
        $('#edit_warehouse_name').val(warehouse.name);
        $('#edit_location').val(warehouse.location);
        $('#edit_capacity').val(warehouse.capacity);
        $('#edit_status').val(warehouse.status);
        $('#edit_manager_name').val(warehouse.manager_name);
        $('#edit_contact_number').val(warehouse.contact_number);
        $('#editWarehouseModal').modal('show');
    });

    // Update Warehouse Form
    $('#editWarehouseForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_warehouse');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#editWarehouseModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Delete Warehouse
    $(document).on('click', '.delete-warehouse', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        if (confirm(`Are you sure you want to deactivate warehouse "${name}"?`)) {
            const formData = new FormData();
            formData.append('action', 'delete_warehouse');
            formData.append('warehouse_id', id);
            
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error');
                    }
                }
            });
        }
    });

    // View functions (placeholder)
    $(document).on('click', '.view-warehouse, .view-receipt, .view-picking', function() {
        const id = $(this).data('id');
        showAlert('Detailed view functionality will be implemented next!', 'info');
    });
});

function showAlert(message, type) {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    setTimeout(() => $('.alert').alert('close'), 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
