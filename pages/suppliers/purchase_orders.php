<?php
/**
 * Purchase Orders Management System
 */
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Purchase Orders';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_purchase_order':
            $supplier_id = intval($_POST['supplier_id']);
            $po_number = mysqli_real_escape_string($conn, $_POST['po_number']);
            $order_date = mysqli_real_escape_string($conn, $_POST['order_date']);
            $expected_delivery_date = mysqli_real_escape_string($conn, $_POST['expected_delivery_date']);
            $total_amount = floatval($_POST['total_amount']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $query = "INSERT INTO purchase_orders (supplier_id, po_number, order_date, expected_delivery_date, total_amount, notes, status) 
                      VALUES ($supplier_id, '$po_number', '$order_date', '$expected_delivery_date', $total_amount, '$notes', 'pending')";
            
            if (mysqli_query($conn, $query)) {
                $po_id = mysqli_insert_id($conn);
                
                // Add items to purchase order
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $item) {
                        $item_id = intval($item['item_id']);
                        $quantity = intval($item['quantity']);
                        $unit_price = floatval($item['unit_price']);
                        $total_price = $quantity * $unit_price;
                        
                        $item_query = "INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, unit_price, total_price) 
                                       VALUES ($po_id, $item_id, $quantity, $unit_price, $total_price)";
                        mysqli_query($conn, $item_query);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Purchase order created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating purchase order']);
            }
            exit;
            
        case 'update_purchase_order':
            $po_id = intval($_POST['po_id']);
            $supplier_id = intval($_POST['supplier_id']);
            $order_date = mysqli_real_escape_string($conn, $_POST['order_date']);
            $expected_delivery_date = mysqli_real_escape_string($conn, $_POST['expected_delivery_date']);
            $total_amount = floatval($_POST['total_amount']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE purchase_orders SET 
                        supplier_id = $supplier_id,
                        order_date = '$order_date',
                        expected_delivery_date = '$expected_delivery_date',
                        total_amount = $total_amount,
                        notes = '$notes',
                        status = '$status',
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = $po_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Purchase order updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating purchase order']);
            }
            exit;
            
        case 'delete_purchase_order':
            $po_id = intval($_POST['po_id']);
            
            // Delete items first
            mysqli_query($conn, "DELETE FROM purchase_order_items WHERE purchase_order_id = $po_id");
            
            // Delete purchase order
            $query = "DELETE FROM purchase_orders WHERE id = $po_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Purchase order deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting purchase order']);
            }
            exit;
            
        case 'update_status':
            $po_id = intval($_POST['po_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $received_date = '';
            if ($status == 'received') {
                $received_date = ", received_date = CURRENT_TIMESTAMP";
            }
            
            $query = "UPDATE purchase_orders SET status = '$status', updated_at = CURRENT_TIMESTAMP $received_date WHERE id = $po_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating status']);
            }
            exit;
    }
}

// Get purchase orders with supplier information
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';

$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(po.po_number LIKE '%$search%' OR s.supplier_name LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $where_conditions[] = "po.status = '$status_filter'";
}
if (!empty($supplier_filter)) {
    $supplier_filter = intval($supplier_filter);
    $where_conditions[] = "po.supplier_id = $supplier_filter";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT po.*, s.supplier_name, s.contact_person, s.phone
          FROM purchase_orders po
          LEFT JOIN suppliers s ON po.supplier_id = s.id
          $where_clause
          ORDER BY po.created_at DESC";

$purchase_orders = mysqli_query($conn, $query);

// Get suppliers for dropdown
$suppliers_query = "SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC";
$suppliers = mysqli_query($conn, $suppliers_query);

// Get statistics
$stats_query = "SELECT 
                  COUNT(*) as total_orders,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                  SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_orders,
                  SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                  SUM(total_amount) as total_value
                FROM purchase_orders";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<style>
.stats-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.status-badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
}

.po-card {
    transition: transform 0.2s;
    border: 1px solid #e3e6f0;
}

.po-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.amount-text {
    font-weight: 600;
    color: #198754;
}

.overdue-alert {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

.bg-pending { background-color: #ffc107; }
.bg-confirmed { background-color: #0dcaf0; }
.bg-received { background-color: #198754; }
.bg-cancelled { background-color: #dc3545; }

.text-pending { color: #ffc107; }
.text-confirmed { color: #0dcaf0; }
.text-received { color: #198754; }
.text-cancelled { color: #dc3545; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📋 Purchase Orders Management</h1>
                <p class="text-muted">Create and track purchase orders with suppliers</p>
            </div>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPurchaseOrderModal">
                    <i class="bi bi-plus-square"></i> Create Purchase Order
                </button>
                <button class="btn btn-outline-secondary" onclick="exportPurchaseOrders()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-cart-plus fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($stats['total_orders'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Total Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-hourglass-split fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($stats['pending_orders'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Pending Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($stats['received_orders'] ?? 0) ?></h3>
                        <p class="mb-0 opacity-75">Received Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($stats['total_value'] ?? 0, 2) ?></h3>
                        <p class="mb-0 opacity-75">Total Value</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="card-title mb-0">
                    <i class="bi bi-funnel me-2 text-primary"></i>Filter & Search Purchase Orders
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search PO number or supplier..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= ($_GET['status'] ?? '') == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="received" <?= ($_GET['status'] ?? '') == 'received' ? 'selected' : '' ?>>Received</option>
                            <option value="cancelled" <?= ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier" class="form-select">
                            <option value="">All Suppliers</option>
                            <?php
                            mysqli_data_seek($suppliers, 0);
                            while ($supplier = mysqli_fetch_assoc($suppliers)) {
                                $selected = $supplier_filter == $supplier['id'] ? 'selected' : '';
                                echo "<option value='{$supplier['id']}' $selected>{$supplier['supplier_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filter
                            </button>
                            <a href="purchase_orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="row">
            <?php if (mysqli_num_rows($purchase_orders) > 0): ?>
                <?php while ($po = mysqli_fetch_assoc($purchase_orders)): ?>
                    <?php
                    $status_class = [
                        'pending' => 'bg-warning text-dark',
                        'confirmed' => 'bg-info text-white',
                        'received' => 'bg-success text-white',
                        'cancelled' => 'bg-danger text-white'
                    ][$po['status']] ?? 'bg-secondary text-white';
                    
                    $is_overdue = $po['status'] != 'received' && $po['status'] != 'cancelled' && 
                                  strtotime($po['expected_delivery_date']) < strtotime('today');
                    ?>
                    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                        <div class="card po-card h-100 <?php echo $is_overdue ? 'overdue-alert' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <strong><?php echo htmlspecialchars($po['po_number']); ?></strong>
                                </h6>
                                <span class="badge status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($po['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <i class="bi bi-building me-2 text-muted"></i>
                                    <strong><?php echo htmlspecialchars($po['supplier_name']); ?></strong>
                                </p>
                                
                                <div class="row text-sm mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Order Date:</small><br>
                                        <small><?php echo date('M d, Y', strtotime($po['order_date'])); ?></small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Expected:</small><br>
                                        <small <?php echo $is_overdue ? 'class="text-danger fw-bold"' : ''; ?>>
                                            <?php echo date('M d, Y', strtotime($po['expected_delivery_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="amount-text h5">₹<?php echo number_format($po['total_amount'], 2); ?></span>
                                </div>
                                
                                <?php if (!empty($po['notes'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-sticky me-1"></i>
                                        <?php echo htmlspecialchars(substr($po['notes'], 0, 50)) . (strlen($po['notes']) > 50 ? '...' : ''); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewPurchaseOrder(<?php echo $po['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editPurchaseOrder(<?php echo $po['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-info btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bi bi-gear"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $po['id']; ?>, 'confirmed')">
                                                <i class="bi bi-check-circle text-info me-2"></i>Confirm
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $po['id']; ?>, 'received')">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>Mark Received
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $po['id']; ?>, 'cancelled')">
                                                <i class="bi bi-x-circle text-danger me-2"></i>Cancel
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deletePurchaseOrder(<?php echo $po['id']; ?>)">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-cart-plus display-1 text-muted"></i>
                            </div>
                            <h4 class="mb-3">No Purchase Orders Found</h4>
                            <p class="text-muted mb-4">Create your first purchase order to get started with supplier management.</p>
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addPurchaseOrderModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Purchase Order
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Purchase Order Modal -->
    <div class="modal fade" id="addPurchaseOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>New Purchase Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addPurchaseOrderForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_purchase_order">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier *</label>
                                <select name="supplier_id" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    mysqli_data_seek($suppliers, 0);
                                    while ($supplier = mysqli_fetch_assoc($suppliers)) {
                                        echo "<option value='{$supplier['id']}'>{$supplier['supplier_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PO Number *</label>
                                <input type="text" name="po_number" class="form-control" value="PO<?php echo date('Ymd') . sprintf('%04d', rand(1, 9999)); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Order Date *</label>
                                <input type="date" name="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expected Delivery *</label>
                                <input type="date" name="expected_delivery_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Total Amount *</label>
                            <input type="number" name="total_amount" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or requirements..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Purchase Order Modal -->
    <div class="modal fade" id="viewPurchaseOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Purchase Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="purchaseOrderDetails">
                    <!-- Purchase order details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printPurchaseOrder()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Purchase Order
        document.getElementById('addPurchaseOrderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('purchase_orders.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Purchase order created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the purchase order');
            });
        });

        // View Purchase Order
        function viewPurchaseOrder(poId) {
            fetch(`purchase_order_details.php?id=${poId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('purchaseOrderDetails').innerHTML = html;
                new bootstrap.Modal(document.getElementById('viewPurchaseOrderModal')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading purchase order details');
            });
        }

        // Update Status
        function updateStatus(poId, status) {
            if (confirm(`Are you sure you want to mark this purchase order as ${status}?`)) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('po_id', poId);
                formData.append('status', status);
                
                fetch('purchase_orders.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the status');
                });
            }
        }

        // Delete Purchase Order
        function deletePurchaseOrder(poId) {
            if (confirm('Are you sure you want to delete this purchase order? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_purchase_order');
                formData.append('po_id', poId);
                
                fetch('purchase_orders.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Purchase order deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the purchase order');
                });
            }
        }

        // Edit Purchase Order
        function editPurchaseOrder(poId) {
            // Load purchase order data and populate edit modal
            // Implementation similar to supplier editing
            showAlert('Edit functionality - to be implemented with dedicated edit modal', 'info');
        }

        // Print Purchase Order
        function printPurchaseOrder() {
            window.print();
        }
        
        // Export Purchase Orders
        function exportPurchaseOrders() {
            showAlert('Export functionality - to be implemented', 'info');
        }
        
        // Utility function to show alerts
        function showAlert(message, type = 'info') {
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
            
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message.substring(0, 20))) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
    </script>
</div>

<?php include '../../layouts/footer.php'; ?>
