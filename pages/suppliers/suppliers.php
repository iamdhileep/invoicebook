<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Supplier Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_supplier':
            $name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
            $company = mysqli_real_escape_string($conn, $_POST['company_name']);
            $contact = mysqli_real_escape_string($conn, $_POST['contact_person']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $alt_phone = mysqli_real_escape_string($conn, $_POST['alternate_phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $website = mysqli_real_escape_string($conn, $_POST['website']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);
            $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
            $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
            $credit_limit = floatval($_POST['credit_limit']);
            $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
            $category = mysqli_real_escape_string($conn, $_POST['category']);
            $rating = intval($_POST['rating']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            $query = "INSERT INTO suppliers (supplier_name, company_name, contact_person, email, phone, alternate_phone, 
                      address, city, state, postal_code, country, tax_number, website, bank_name, bank_account, 
                      ifsc_code, payment_terms, credit_limit, supplier_type, category, rating, notes) 
                      VALUES ('$name', '$company', '$contact', " . ($email ? "'$email'" : "NULL") . ", '$phone', 
                      '$alt_phone', '$address', '$city', '$state', '$postal_code', '$country', '$tax_number', 
                      '$website', '$bank_name', '$bank_account', '$ifsc_code', '$payment_terms', $credit_limit, 
                      '$supplier_type', '$category', $rating, '$notes')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Supplier added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_supplier':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "SELECT * FROM suppliers WHERE id = $id");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Supplier not found']);
            }
            exit;

        case 'update_supplier':
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
            $company = mysqli_real_escape_string($conn, $_POST['company_name']);
            $contact = mysqli_real_escape_string($conn, $_POST['contact_person']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $alt_phone = mysqli_real_escape_string($conn, $_POST['alternate_phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $website = mysqli_real_escape_string($conn, $_POST['website']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);
            $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
            $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
            $credit_limit = floatval($_POST['credit_limit']);
            $supplier_type = mysqli_real_escape_string($conn, $_POST['supplier_type']);
            $category = mysqli_real_escape_string($conn, $_POST['category']);
            $rating = intval($_POST['rating']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE suppliers SET 
                      supplier_name = '$name',
                      company_name = '$company',
                      contact_person = '$contact',
                      email = " . ($email ? "'$email'" : "NULL") . ",
                      phone = '$phone',
                      alternate_phone = '$alt_phone',
                      address = '$address',
                      city = '$city',
                      state = '$state',
                      postal_code = '$postal_code',
                      country = '$country',
                      tax_number = '$tax_number',
                      website = '$website',
                      bank_name = '$bank_name',
                      bank_account = '$bank_account',
                      ifsc_code = '$ifsc_code',
                      payment_terms = '$payment_terms',
                      credit_limit = $credit_limit,
                      supplier_type = '$supplier_type',
                      category = '$category',
                      rating = $rating,
                      notes = '$notes',
                      status = '$status',
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Supplier updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_supplier':
            $id = intval($_POST['id']);
            
            // Check if supplier has any purchase orders
            $poCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = $id");
            $poCount = mysqli_fetch_assoc($poCheck)['count'];
            
            // Check if supplier has any items
            $itemCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM items WHERE supplier_id = $id");
            $itemCount = mysqli_fetch_assoc($itemCheck)['count'];
            
            if ($poCount > 0 || $itemCount > 0) {
                // Don't delete, just mark as inactive
                $query = "UPDATE suppliers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = $id";
                $message = "Supplier marked as inactive (has $poCount purchase orders and $itemCount items)";
            } else {
                // Safe to delete
                $query = "DELETE FROM suppliers WHERE id = $id";
                $message = "Supplier deleted successfully!";
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_supplier_stats':
            $id = intval($_POST['id']);
            
            // Get supplier details
            $supplierQuery = mysqli_query($conn, "SELECT * FROM suppliers WHERE id = $id");
            $supplier = mysqli_fetch_assoc($supplierQuery);
            
            // Get purchase order statistics
            $statsQuery = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent,
                    SUM(CASE WHEN status = 'received' THEN total_amount ELSE 0 END) as completed_orders_value,
                    SUM(CASE WHEN payment_status = 'paid' THEN paid_amount ELSE 0 END) as total_paid,
                    MAX(order_date) as last_order_date,
                    MIN(order_date) as first_order_date
                FROM purchase_orders 
                WHERE supplier_id = $id
            ");
            $stats = mysqli_fetch_assoc($statsQuery);
            
            // Get recent orders
            $recentQuery = mysqli_query($conn, "
                SELECT po_number, order_date, total_amount, status 
                FROM purchase_orders 
                WHERE supplier_id = $id 
                ORDER BY order_date DESC 
                LIMIT 5
            ");
            $recentOrders = [];
            while ($row = mysqli_fetch_assoc($recentQuery)) {
                $recentOrders[] = $row;
            }
            
            // Get supplied items
            $itemsQuery = mysqli_query($conn, "
                SELECT item_name, item_price, supplier_cost
                FROM items 
                WHERE supplier_id = $id 
                LIMIT 10
            ");
            $items = [];
            while ($row = mysqli_fetch_assoc($itemsQuery)) {
                $items[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'supplier' => $supplier,
                'stats' => $stats,
                'recent_orders' => $recentOrders,
                'items' => $items
            ]);
            exit;
    }
}

// Get supplier statistics for dashboard
$totalSuppliers = 0;
$activeSuppliers = 0;
$totalSpent = 0;
$pendingOrders = 0;

$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_suppliers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_suppliers
    FROM suppliers
");
if ($statsQuery && $row = mysqli_fetch_assoc($statsQuery)) {
    $totalSuppliers = $row['total_suppliers'];
    $activeSuppliers = $row['active_suppliers'];
}

$spentQuery = mysqli_query($conn, "
    SELECT 
        SUM(total_amount) as total_spent,
        SUM(CASE WHEN status IN ('draft', 'sent', 'acknowledged') THEN 1 ELSE 0 END) as pending_orders
    FROM purchase_orders
");
if ($spentQuery && $row = mysqli_fetch_assoc($spentQuery)) {
    $totalSpent = $row['total_spent'] ?? 0;
    $pendingOrders = $row['pending_orders'] ?? 0;
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$supplier_type = $_GET['supplier_type'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (supplier_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR company_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR contact_person LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR email LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR phone LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($status) {
    $where .= " AND status = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($category) {
    $where .= " AND category = '" . mysqli_real_escape_string($conn, $category) . "'";
}
if ($supplier_type) {
    $where .= " AND supplier_type = '" . mysqli_real_escape_string($conn, $supplier_type) . "'";
}

// Get suppliers
$suppliers = mysqli_query($conn, "SELECT * FROM suppliers $where ORDER BY supplier_name ASC");

// Get distinct categories and types for filters
$categories = mysqli_query($conn, "SELECT DISTINCT category FROM suppliers WHERE category IS NOT NULL AND category != '' ORDER BY category");
$types = mysqli_query($conn, "SELECT DISTINCT supplier_type FROM suppliers ORDER BY supplier_type");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🏢 Supplier Management</h1>
                <p class="text-muted">Manage your suppliers and vendor relationships</p>
            </div>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#createPOModal">
                    <i class="bi bi-plus-square"></i> Create Purchase Order
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="bi bi-building-plus"></i> Add Supplier
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-building fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $totalSuppliers ?></h3>
                        <small class="opacity-75">Total Suppliers</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-building-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $activeSuppliers ?></h3>
                        <small class="opacity-75">Active Suppliers</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($totalSpent, 0) ?></h3>
                        <small class="opacity-75">Total Spent</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-hourglass-split fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $pendingOrders ?></h3>
                        <small class="opacity-75">Pending Orders</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="purchase_orders.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-cart-plus me-2"></i>Purchase Orders
                </a>
            </div>
            <div class="col-md-3">
                <a href="supplier_payments.php" class="btn btn-outline-success w-100">
                    <i class="bi bi-credit-card me-2"></i>Payment History
                </a>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-info w-100" onclick="exportSuppliers()">
                    <i class="bi bi-download me-2"></i>Export Data
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100" onclick="showSupplierReports()">
                    <i class="bi bi-graph-up me-2"></i>Reports
                </button>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search Suppliers</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, company, contact..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="blacklisted" <?= $status === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php if ($categories): while ($catRow = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= htmlspecialchars($catRow['category']) ?>" <?= $category === $catRow['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($catRow['category']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="supplier_type" class="form-select">
                            <option value="">All Types</option>
                            <?php if ($types): while ($typeRow = mysqli_fetch_assoc($types)): ?>
                                <option value="<?= htmlspecialchars($typeRow['supplier_type']) ?>" <?= $supplier_type === $typeRow['supplier_type'] ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $typeRow['supplier_type'])) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-circle"></i>
                        </a>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importSuppliersModal">
                            <i class="bi bi-upload"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Supplier Database
                    <span class="badge bg-primary ms-2"><?= $suppliers ? mysqli_num_rows($suppliers) : 0 ?> suppliers</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="suppliersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Supplier Info</th>
                                    <th>Contact Details</th>
                                    <th>Location</th>
                                    <th>Type & Category</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($supplier = mysqli_fetch_assoc($suppliers)): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($supplier['supplier_name']) ?></strong>
                                                <?php if ($supplier['company_name']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($supplier['company_name']) ?></small>
                                                <?php endif; ?>
                                                <?php if ($supplier['contact_person']): ?>
                                                    <br><small class="text-info">Contact: <?= htmlspecialchars($supplier['contact_person']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($supplier['phone']): ?>
                                                <i class="bi bi-telephone text-success me-1"></i><?= htmlspecialchars($supplier['phone']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($supplier['email']): ?>
                                                <i class="bi bi-envelope text-info me-1"></i><?= htmlspecialchars($supplier['email']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($supplier['website']): ?>
                                                <i class="bi bi-globe text-warning me-1"></i>
                                                <a href="<?= htmlspecialchars($supplier['website']) ?>" target="_blank" class="text-decoration-none">
                                                    Website
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($supplier['city'] || $supplier['state']): ?>
                                                <i class="bi bi-geo-alt text-warning me-1"></i>
                                                <?= htmlspecialchars($supplier['city']) ?>
                                                <?php if ($supplier['city'] && $supplier['state']): ?>, <?php endif; ?>
                                                <?= htmlspecialchars($supplier['state']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $supplier['supplier_type'])) ?></span>
                                            <?php if ($supplier['category']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($supplier['category']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $supplier['rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= $supplier['rating'] ?>/5</small>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match($supplier['status']) {
                                                'active' => 'bg-success',
                                                'inactive' => 'bg-secondary',
                                                'blacklisted' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= ucfirst($supplier['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-info" onclick="viewSupplierStats(<?= $supplier['id'] ?>)" title="View Stats">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="createPOForSupplier(<?= $supplier['id'] ?>)" title="Create PO">
                                                    <i class="bi bi-cart-plus"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editSupplier(<?= $supplier['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteSupplier(<?= $supplier['id'] ?>)" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-building fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No suppliers found</h5>
                        <p class="text-muted">Start by adding your first supplier to the database.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="bi bi-building-plus"></i> Add First Supplier
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-building-plus text-primary me-2"></i>Add New Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSupplierForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Basic Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="supplier_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone" class="form-control">
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Contact Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control" placeholder="https://">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="India">
                        </div>
                        
                        <!-- Business Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Business Information</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier Type</label>
                            <select name="supplier_type" class="form-select">
                                <option value="distributor">Distributor</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="wholesaler">Wholesaler</option>
                                <option value="retailer">Retailer</option>
                                <option value="service_provider">Service Provider</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g., Food & Beverages">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select">
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Very Poor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tax Number (GST)</label>
                            <input type="text" name="tax_number" class="form-control" placeholder="GSTIN">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="Net 30">Net 30 Days</option>
                                <option value="Net 15">Net 15 Days</option>
                                <option value="Net 7">Net 7 Days</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="Advance">Advance Payment</option>
                            </select>
                        </div>
                        
                        <!-- Banking Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Banking Information</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" name="ifsc_code" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Limit (₹)</label>
                            <input type="number" name="credit_limit" class="form-control" value="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes about the supplier..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSupplierForm">
                <input type="hidden" name="id" id="editSupplierId">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">Basic Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="supplier_name" id="editSupplierName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" id="editCompanyName" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" id="editContactPerson" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" id="editPhone" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone" id="editAltPhone" class="form-control">
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Contact Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" id="editWebsite" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="editAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="editCity" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="editState" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" id="editPostalCode" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="editCountry" class="form-control">
                        </div>
                        
                        <!-- Business Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Business Information</h6>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Supplier Type</label>
                            <select name="supplier_type" id="editSupplierType" class="form-select">
                                <option value="distributor">Distributor</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="wholesaler">Wholesaler</option>
                                <option value="retailer">Retailer</option>
                                <option value="service_provider">Service Provider</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="editCategory" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" id="editRating" class="form-select">
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Very Poor</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blacklisted">Blacklisted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tax Number (GST)</label>
                            <input type="text" name="tax_number" id="editTaxNumber" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" id="editPaymentTerms" class="form-select">
                                <option value="Net 30">Net 30 Days</option>
                                <option value="Net 15">Net 15 Days</option>
                                <option value="Net 7">Net 7 Days</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="Advance">Advance Payment</option>
                            </select>
                        </div>
                        
                        <!-- Banking Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2">Banking Information</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" id="editBankName" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account" id="editBankAccount" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" name="ifsc_code" id="editIFSC" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Limit (₹)</label>
                            <input type="number" name="credit_limit" id="editCreditLimit" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Supplier Stats Modal -->
<div class="modal fade" id="supplierStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up text-info me-2"></i>Supplier Statistics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierStatsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Purchase Order Modal -->
<div class="modal fade" id="createPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cart-plus text-success me-2"></i>Create Purchase Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4">
                    <i class="bi bi-arrow-right-circle fs-2 text-info mb-3"></i>
                    <h5>Purchase Order System</h5>
                    <p class="text-muted">This will redirect to the Purchase Order management system</p>
                    <a href="purchase_orders.php" class="btn btn-success btn-lg">
                        <i class="bi bi-cart-plus me-2"></i>Go to Purchase Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Suppliers Modal -->
<div class="modal fade" id="importSuppliersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upload text-success me-2"></i>Import Suppliers
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4">
                    <i class="bi bi-file-earmark-excel fs-2 text-success mb-3"></i>
                    <h5>Bulk Import</h5>
                    <p class="text-muted">Upload CSV/Excel file to import multiple suppliers</p>
                    <input type="file" class="form-control mb-3" accept=".csv,.xlsx,.xls">
                    <button class="btn btn-success">
                        <i class="bi bi-upload me-2"></i>Upload & Import
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    transition: transform 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
}
.table-responsive {
    border-radius: 0.5rem;
}
.btn-group-sm .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.rating .bi-star-fill {
    color: #ffc107;
}
</style>

<script>
// Add Supplier Form
document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_supplier');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addSupplierModal')).hide();
            showAlert(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
});

// Edit Supplier
function editSupplier(id) {
    const formData = new FormData();
    formData.append('action', 'get_supplier');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const supplier = data.data;
            document.getElementById('editSupplierId').value = supplier.id;
            document.getElementById('editSupplierName').value = supplier.supplier_name;
            document.getElementById('editCompanyName').value = supplier.company_name || '';
            document.getElementById('editContactPerson').value = supplier.contact_person || '';
            document.getElementById('editPhone').value = supplier.phone || '';
            document.getElementById('editAltPhone').value = supplier.alternate_phone || '';
            document.getElementById('editEmail').value = supplier.email || '';
            document.getElementById('editWebsite').value = supplier.website || '';
            document.getElementById('editAddress').value = supplier.address || '';
            document.getElementById('editCity').value = supplier.city || '';
            document.getElementById('editState').value = supplier.state || '';
            document.getElementById('editPostalCode').value = supplier.postal_code || '';
            document.getElementById('editCountry').value = supplier.country || '';
            document.getElementById('editSupplierType').value = supplier.supplier_type;
            document.getElementById('editCategory').value = supplier.category || '';
            document.getElementById('editRating').value = supplier.rating;
            document.getElementById('editStatus').value = supplier.status;
            document.getElementById('editTaxNumber').value = supplier.tax_number || '';
            document.getElementById('editPaymentTerms').value = supplier.payment_terms || '';
            document.getElementById('editBankName').value = supplier.bank_name || '';
            document.getElementById('editBankAccount').value = supplier.bank_account || '';
            document.getElementById('editIFSC').value = supplier.ifsc_code || '';
            document.getElementById('editCreditLimit').value = supplier.credit_limit || '';
            document.getElementById('editNotes').value = supplier.notes || '';
            
            new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading supplier data', 'error');
    });
}

// Update Supplier Form
document.getElementById('editSupplierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_supplier');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editSupplierModal')).hide();
            showAlert(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
});

// Delete Supplier
function deleteSupplier(id) {
    if (confirm('Are you sure you want to delete this supplier? If they have purchase orders or items, they will be marked as inactive instead.')) {
        const formData = new FormData();
        formData.append('action', 'delete_supplier');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred. Please try again.', 'error');
        });
    }
}

// View Supplier Stats
function viewSupplierStats(id) {
    const formData = new FormData();
    formData.append('action', 'get_supplier_stats');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const supplier = data.supplier;
            const stats = data.stats;
            const recentOrders = data.recent_orders;
            const items = data.items;
            
            let content = `
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="text-primary">${supplier.supplier_name}</h6>
                        <p class="text-muted mb-0">${supplier.company_name || 'No company'} • ${supplier.contact_person || 'No contact'}</p>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-primary">${stats.total_orders || 0}</div>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-success">₹${Number(stats.total_spent || 0).toLocaleString()}</div>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-info">₹${Number(stats.total_paid || 0).toLocaleString()}</div>
                            <small class="text-muted">Total Paid</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-warning">${items.length}</div>
                            <small class="text-muted">Items Supplied</small>
                        </div>
                    </div>
                </div>
                
                ${recentOrders.length > 0 ? `
                <h6 class="mb-3">Recent Purchase Orders</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>PO #</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            ${recentOrders.map(order => `
                                <tr>
                                    <td>${order.po_number}</td>
                                    <td>${new Date(order.order_date).toLocaleDateString()}</td>
                                    <td>₹${Number(order.total_amount).toLocaleString()}</td>
                                    <td><span class="badge ${order.status === 'received' ? 'bg-success' : order.status === 'sent' ? 'bg-primary' : 'bg-warning'}">${order.status}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ` : '<div class="text-center text-muted">No purchase orders found</div>'}
                
                ${items.length > 0 ? `
                <h6 class="mb-3 mt-4">Supplied Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Item</th><th>Selling Price</th><th>Cost Price</th></tr>
                        </thead>
                        <tbody>
                            ${items.map(item => `
                                <tr>
                                    <td>${item.item_name}</td>
                                    <td>₹${Number(item.item_price).toLocaleString()}</td>
                                    <td>₹${Number(item.supplier_cost || 0).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ` : ''}
            `;
            
            document.getElementById('supplierStatsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('supplierStatsModal')).show();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading supplier statistics', 'error');
    });
}

// Create PO for Supplier
function createPOForSupplier(id) {
    window.open('purchase_orders.php?supplier_id=' + id, '_blank');
}

// Export Suppliers
function exportSuppliers() {
    window.open('supplier_api.php?action=export_suppliers', '_blank');
}

// Show Reports
function showSupplierReports() {
    window.open('supplier_reports.php', '_blank');
}

// Alert function
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
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    }, 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
