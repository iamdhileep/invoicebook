<?php
/**
 * Supplier Payments Management System
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_payment':
            $supplier_id = intval($_POST['supplier_id']);
            $purchase_order_id = !empty($_POST['purchase_order_id']) ? intval($_POST['purchase_order_id']) : null;
            $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
            $amount = floatval($_POST['amount']);
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $po_clause = $purchase_order_id ? "$purchase_order_id" : "NULL";
            
            $query = "INSERT INTO supplier_payments (supplier_id, purchase_order_id, payment_date, amount, payment_method, reference_number, notes) 
                      VALUES ($supplier_id, $po_clause, '$payment_date', $amount, '$payment_method', '$reference_number', '$notes')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error recording payment: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'update_payment':
            $payment_id = intval($_POST['payment_id']);
            $supplier_id = intval($_POST['supplier_id']);
            $purchase_order_id = !empty($_POST['purchase_order_id']) ? intval($_POST['purchase_order_id']) : null;
            $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
            $amount = floatval($_POST['amount']);
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number'] ?? '');
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $po_clause = $purchase_order_id ? "$purchase_order_id" : "NULL";
            
            $query = "UPDATE supplier_payments SET 
                        supplier_id = $supplier_id,
                        purchase_order_id = $po_clause,
                        payment_date = '$payment_date',
                        amount = $amount,
                        payment_method = '$payment_method',
                        reference_number = '$reference_number',
                        notes = '$notes',
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = $payment_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating payment: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'delete_payment':
            $payment_id = intval($_POST['payment_id']);
            
            $query = "DELETE FROM supplier_payments WHERE id = $payment_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting payment: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get payments with supplier and purchase order information
$search = $_GET['search'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(s.supplier_name LIKE '%$search%' OR sp.reference_number LIKE '%$search%' OR po.po_number LIKE '%$search%')";
}
if (!empty($supplier_filter)) {
    $supplier_filter = intval($supplier_filter);
    $where_conditions[] = "sp.supplier_id = $supplier_filter";
}
if (!empty($method_filter)) {
    $method_filter = mysqli_real_escape_string($conn, $method_filter);
    $where_conditions[] = "sp.payment_method = '$method_filter'";
}
if (!empty($date_from)) {
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $where_conditions[] = "sp.payment_date >= '$date_from'";
}
if (!empty($date_to)) {
    $date_to = mysqli_real_escape_string($conn, $date_to);
    $where_conditions[] = "sp.payment_date <= '$date_to'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT sp.*, s.supplier_name, s.contact_person, po.po_number, po.total_amount as po_amount
          FROM supplier_payments sp
          LEFT JOIN suppliers s ON sp.supplier_id = s.id
          LEFT JOIN purchase_orders po ON sp.purchase_order_id = po.id
          $where_clause
          ORDER BY sp.payment_date DESC, sp.created_at DESC";

$payments = mysqli_query($conn, $query);

// Get suppliers for dropdown
$suppliers_query = "SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC";
$suppliers = mysqli_query($conn, $suppliers_query);

// Get statistics
$stats_query = "SELECT 
                  COUNT(*) as total_payments,
                  SUM(amount) as total_paid,
                  COUNT(DISTINCT supplier_id) as suppliers_paid,
                  AVG(amount) as avg_payment
                FROM supplier_payments";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get monthly payment trends
$monthly_query = "SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount
                  FROM supplier_payments
                  WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                  ORDER BY month ASC";
$monthly_data = mysqli_query($conn, $monthly_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payments - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .payment-method-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .amount-display {
            font-size: 1.25rem;
            font-weight: 600;
            color: #198754;
        }
        .stats-card {
            border: none;
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../dashboard.php">
                <i class="fas fa-book me-2"></i>BillBook
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="suppliers.php">
                    <i class="fas fa-truck me-1"></i>Suppliers
                </a>
                <a class="nav-link" href="purchase_orders.php">
                    <i class="fas fa-shopping-cart me-1"></i>Purchase Orders
                </a>
                <a class="nav-link active" href="supplier_payments.php">
                    <i class="fas fa-money-bill-wave me-1"></i>Payments
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Total Payments</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_payments']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-receipt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Total Paid</h6>
                                <h3 class="mb-0">₹<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-rupee-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Suppliers Paid</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['suppliers_paid']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-light">Avg Payment</h6>
                                <h3 class="mb-0">₹<?php echo number_format($stats['avg_payment'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header and Controls -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-money-bill-wave me-2"></i>Supplier Payments</h2>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                    <i class="fas fa-plus me-2"></i>Record Payment
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search supplier, reference..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
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
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo $method_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="check" <?php echo $method_filter == 'check' ? 'selected' : ''; ?>>Check</option>
                            <option value="bank_transfer" <?php echo $method_filter == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="online" <?php echo $method_filter == 'online' ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments List -->
        <div class="row">
            <?php if (mysqli_num_rows($payments) > 0): ?>
                <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
                    <?php
                    $method_badges = [
                        'cash' => 'bg-success',
                        'check' => 'bg-primary',
                        'bank_transfer' => 'bg-info',
                        'online' => 'bg-warning text-dark'
                    ];
                    $method_badge = $method_badges[$payment['payment_method']] ?? 'bg-secondary';
                    ?>
                    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                        <div class="card payment-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <strong><?php echo htmlspecialchars($payment['supplier_name']); ?></strong>
                                </h6>
                                <span class="badge payment-method-badge <?php echo $method_badge; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="amount-display mb-3">
                                    ₹<?php echo number_format($payment['amount'], 2); ?>
                                </div>
                                
                                <div class="row text-sm mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Payment Date:</small><br>
                                        <small><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Reference:</small><br>
                                        <small><?php echo htmlspecialchars($payment['reference_number'] ?: 'N/A'); ?></small>
                                    </div>
                                </div>
                                
                                <?php if (!empty($payment['po_number'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-shopping-cart me-1"></i>
                                        PO: <?php echo htmlspecialchars($payment['po_number']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($payment['notes'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-sticky-note me-1"></i>
                                        <?php echo htmlspecialchars(substr($payment['notes'], 0, 50)) . (strlen($payment['notes']) > 50 ? '...' : ''); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-muted small">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($payment['created_at'])); ?>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewPayment(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editPayment(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="printPayment(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deletePayment(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                            <h4>No Payments Found</h4>
                            <p class="text-muted">Record your first supplier payment to get started.</p>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                                <i class="fas fa-plus me-2"></i>Record Payment
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Record New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addPaymentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_payment">
                        
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
                                <label class="form-label">Purchase Order (Optional)</label>
                                <select name="purchase_order_id" class="form-select" id="purchaseOrderSelect">
                                    <option value="">Select PO (Optional)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount *</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online">Online Payment</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="Check no., Transaction ID, etc.">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Payment notes or description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Payment
        document.getElementById('addPaymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('supplier_payments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment recorded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while recording the payment');
            });
        });

        // Load Purchase Orders when supplier is selected
        document.querySelector('select[name="supplier_id"]').addEventListener('change', function() {
            const supplierId = this.value;
            const poSelect = document.getElementById('purchaseOrderSelect');
            
            poSelect.innerHTML = '<option value="">Select PO (Optional)</option>';
            
            if (supplierId) {
                fetch(`purchase_order_api.php?action=get_supplier_pos&supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        data.data.forEach(po => {
                            const option = document.createElement('option');
                            option.value = po.id;
                            option.textContent = `${po.po_number} - ₹${parseFloat(po.total_amount).toFixed(2)}`;
                            poSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading purchase orders:', error));
            }
        });

        // View Payment
        function viewPayment(paymentId) {
            alert('View payment details - to be implemented');
        }

        // Edit Payment
        function editPayment(paymentId) {
            alert('Edit payment - to be implemented');
        }

        // Print Payment
        function printPayment(paymentId) {
            window.open(`payment_receipt.php?id=${paymentId}`, '_blank');
        }

        // Delete Payment
        function deletePayment(paymentId) {
            if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_payment');
                formData.append('payment_id', paymentId);
                
                fetch('supplier_payments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the payment');
                });
            }
        }
    </script>
</body>
</html>
