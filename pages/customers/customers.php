<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Customer Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_customer':
            $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
            $website = mysqli_real_escape_string($conn, $_POST['website']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            $query = "INSERT INTO customers (customer_name, email, phone, address, city, state, postal_code, country, tax_number, company_name, website, notes) 
                      VALUES ('$name', " . ($email ? "'$email'" : "NULL") . ", '$phone', '$address', '$city', '$state', '$postal_code', '$country', '$tax_number', '$company_name', '$website', '$notes')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Customer added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_customer':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "SELECT * FROM customers WHERE id = $id");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
            }
            exit;

        case 'update_customer':
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
            $website = mysqli_real_escape_string($conn, $_POST['website']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE customers SET 
                      customer_name = '$name',
                      email = " . ($email ? "'$email'" : "NULL") . ",
                      phone = '$phone',
                      address = '$address',
                      city = '$city',
                      state = '$state',
                      postal_code = '$postal_code',
                      country = '$country',
                      tax_number = '$tax_number',
                      company_name = '$company_name',
                      website = '$website',
                      notes = '$notes',
                      status = '$status',
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Customer updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_customer':
            $id = intval($_POST['id']);
            
            // Check if customer has any invoices
            $invoiceCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM invoices WHERE customer_name = (SELECT customer_name FROM customers WHERE id = $id)");
            $invoiceCount = mysqli_fetch_assoc($invoiceCheck)['count'];
            
            if ($invoiceCount > 0) {
                // Don't delete, just mark as inactive
                $query = "UPDATE customers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = $id";
                $message = "Customer marked as inactive (has $invoiceCount invoices)";
            } else {
                // Safe to delete
                $query = "DELETE FROM customers WHERE id = $id";
                $message = "Customer deleted successfully!";
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_customer_stats':
            $id = intval($_POST['id']);
            
            // Get customer details
            $customerQuery = mysqli_query($conn, "SELECT * FROM customers WHERE id = $id");
            $customer = mysqli_fetch_assoc($customerQuery);
            
            // Get invoice statistics
            $statsQuery = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_invoices,
                    SUM(total_amount) as total_revenue,
                    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_amount,
                    MAX(invoice_date) as last_invoice_date,
                    MIN(invoice_date) as first_invoice_date
                FROM invoices 
                WHERE customer_name = '{$customer['customer_name']}'
            ");
            $stats = mysqli_fetch_assoc($statsQuery);
            
            // Get recent invoices
            $recentQuery = mysqli_query($conn, "
                SELECT invoice_number, invoice_date, total_amount, status 
                FROM invoices 
                WHERE customer_name = '{$customer['customer_name']}' 
                ORDER BY invoice_date DESC 
                LIMIT 5
            ");
            $recentInvoices = [];
            while ($row = mysqli_fetch_assoc($recentQuery)) {
                $recentInvoices[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'customer' => $customer,
                'stats' => $stats,
                'recent_invoices' => $recentInvoices
            ]);
            exit;
    }
}

// Get customer statistics for dashboard
$totalCustomers = 0;
$activeCustomers = 0;
$totalRevenue = 0;
$avgOrderValue = 0;

$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers
    FROM customers
");
if ($statsQuery && $row = mysqli_fetch_assoc($statsQuery)) {
    $totalCustomers = $row['total_customers'];
    $activeCustomers = $row['active_customers'];
}

$revenueQuery = mysqli_query($conn, "
    SELECT 
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value
    FROM invoices
");
if ($revenueQuery && $row = mysqli_fetch_assoc($revenueQuery)) {
    $totalRevenue = $row['total_revenue'] ?? 0;
    $avgOrderValue = $row['avg_order_value'] ?? 0;
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$city = $_GET['city'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (customer_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR email LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR phone LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR company_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($status) {
    $where .= " AND status = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($city) {
    $where .= " AND city = '" . mysqli_real_escape_string($conn, $city) . "'";
}

// Get customers
$customers = mysqli_query($conn, "SELECT * FROM customers $where ORDER BY customer_name ASC");

// Get distinct cities for filter
$cities = mysqli_query($conn, "SELECT DISTINCT city FROM customers WHERE city IS NOT NULL AND city != '' ORDER BY city");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">👥 Customer Management</h1>
                <p class="text-muted">Manage your customer database and relationships</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-person-plus"></i> Add Customer
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $totalCustomers ?></h3>
                        <small class="opacity-75">Total Customers</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $activeCustomers ?></h3>
                        <small class="opacity-75">Active Customers</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($totalRevenue, 0) ?></h3>
                        <small class="opacity-75">Total Revenue</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-graph-up fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($avgOrderValue, 0) ?></h3>
                        <small class="opacity-75">Avg. Order Value</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Customers</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, or company..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <select name="city" class="form-select">
                            <option value="">All Cities</option>
                            <?php if ($cities): while ($cityRow = mysqli_fetch_assoc($cities)): ?>
                                <option value="<?= htmlspecialchars($cityRow['city']) ?>" <?= $city === $cityRow['city'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cityRow['city']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 text-dark">
                    <i class="bi bi-table me-2"></i>Customer Database
                    <span class="badge bg-primary ms-2"><?= $customers ? mysqli_num_rows($customers) : 0 ?> customers</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if ($customers && mysqli_num_rows($customers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="customersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact Info</th>
                                    <th>Location</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = mysqli_fetch_assoc($customers)): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($customer['customer_name']) ?></strong>
                                                <?php if ($customer['email']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($customer['phone']): ?>
                                                <i class="bi bi-telephone text-success me-1"></i><?= htmlspecialchars($customer['phone']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($customer['website']): ?>
                                                <i class="bi bi-globe text-info me-1"></i>
                                                <a href="<?= htmlspecialchars($customer['website']) ?>" target="_blank" class="text-decoration-none">
                                                    Website
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['city'] || $customer['state']): ?>
                                                <i class="bi bi-geo-alt text-warning me-1"></i>
                                                <?= htmlspecialchars($customer['city']) ?>
                                                <?php if ($customer['city'] && $customer['state']): ?>, <?php endif; ?>
                                                <?= htmlspecialchars($customer['state']) ?>
                                            <?php endif; ?>
                                            <?php if ($customer['address']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($customer['address'], 0, 50)) . (strlen($customer['address']) > 50 ? '...' : '') ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['company_name']): ?>
                                                <strong><?= htmlspecialchars($customer['company_name']) ?></strong><br>
                                            <?php endif; ?>
                                            <?php if ($customer['tax_number']): ?>
                                                <small class="text-muted">Tax: <?= htmlspecialchars($customer['tax_number']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $customer['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= ucfirst($customer['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M d, Y', strtotime($customer['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-info" onclick="viewCustomerStats(<?= $customer['id'] ?>)" title="View Stats">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editCustomer(<?= $customer['id'] ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Delete">
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
                        <i class="bi bi-people fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No customers found</h5>
                        <p class="text-muted">Start by adding your first customer to the database.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="bi bi-person-plus"></i> Add First Customer
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>Add New Customer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCustomerForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="India">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="tax_number" class="form-control" placeholder="GST/Tax ID">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control" placeholder="https://">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional information about the customer..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Customer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCustomerForm">
                <input type="hidden" name="id" id="editCustomerId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="customer_name" id="editCustomerName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editCustomerEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" id="editCustomerPhone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" id="editCustomerCompany" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="editCustomerAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="editCustomerCity" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="editCustomerState" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" id="editCustomerPostal" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="editCustomerCountry" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="tax_number" id="editCustomerTax" class="form-control" placeholder="GST/Tax ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCustomerStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" id="editCustomerWebsite" class="form-control" placeholder="https://">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editCustomerNotes" class="form-control" rows="2" placeholder="Additional information about the customer..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Stats Modal -->
<div class="modal fade" id="customerStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up text-info me-2"></i>Customer Statistics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerStatsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
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
</style>

<script>
// Add Customer Form
document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_customer');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
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

// Edit Customer
function editCustomer(id) {
    const formData = new FormData();
    formData.append('action', 'get_customer');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.data;
            document.getElementById('editCustomerId').value = customer.id;
            document.getElementById('editCustomerName').value = customer.customer_name;
            document.getElementById('editCustomerEmail').value = customer.email || '';
            document.getElementById('editCustomerPhone').value = customer.phone || '';
            document.getElementById('editCustomerCompany').value = customer.company_name || '';
            document.getElementById('editCustomerAddress').value = customer.address || '';
            document.getElementById('editCustomerCity').value = customer.city || '';
            document.getElementById('editCustomerState').value = customer.state || '';
            document.getElementById('editCustomerPostal').value = customer.postal_code || '';
            document.getElementById('editCustomerCountry').value = customer.country || '';
            document.getElementById('editCustomerTax').value = customer.tax_number || '';
            document.getElementById('editCustomerWebsite').value = customer.website || '';
            document.getElementById('editCustomerNotes').value = customer.notes || '';
            document.getElementById('editCustomerStatus').value = customer.status;
            
            new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading customer data', 'error');
    });
}

// Update Customer Form
document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_customer');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editCustomerModal')).hide();
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

// Delete Customer
function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer? If they have invoices, they will be marked as inactive instead.')) {
        const formData = new FormData();
        formData.append('action', 'delete_customer');
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

// View Customer Stats
function viewCustomerStats(id) {
    const formData = new FormData();
    formData.append('action', 'get_customer_stats');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.customer;
            const stats = data.stats;
            const recentInvoices = data.recent_invoices;
            
            let content = `
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="text-primary">${customer.customer_name}</h6>
                        <p class="text-muted mb-0">${customer.email || 'No email'} • ${customer.phone || 'No phone'}</p>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-primary">${stats.total_invoices || 0}</div>
                            <small class="text-muted">Total Invoices</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-success">₹${Number(stats.total_revenue || 0).toLocaleString()}</div>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-info">₹${Number(stats.paid_amount || 0).toLocaleString()}</div>
                            <small class="text-muted">Paid Amount</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-warning">₹${Number(stats.overdue_amount || 0).toLocaleString()}</div>
                            <small class="text-muted">Overdue Amount</small>
                        </div>
                    </div>
                </div>
                
                ${recentInvoices.length > 0 ? `
                <h6 class="mb-3">Recent Invoices</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Invoice #</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            ${recentInvoices.map(invoice => `
                                <tr>
                                    <td>${invoice.invoice_number}</td>
                                    <td>${new Date(invoice.invoice_date).toLocaleDateString()}</td>
                                    <td>₹${Number(invoice.total_amount).toLocaleString()}</td>
                                    <td><span class="badge ${invoice.status === 'paid' ? 'bg-success' : invoice.status === 'overdue' ? 'bg-danger' : 'bg-warning'}">${invoice.status}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ` : '<div class="text-center text-muted">No invoices found</div>'}
            `;
            
            document.getElementById('customerStatsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('customerStatsModal')).show();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading customer statistics', 'error');
    });
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

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('customersTable')) {
        // You can add DataTable initialization here if you have it loaded
        // $('#customersTable').DataTable({
        //     pageLength: 25,
        //     order: [[0, 'asc']]
        // });
    }
});
</script>

<?php include '../../layouts/footer.php'; ?>
