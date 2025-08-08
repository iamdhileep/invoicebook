<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Suppliers';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_supplier':
            $name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
            $company = mysqli_real_escape_string($conn, $_POST['company_name']);
            $contact = mysqli_real_escape_string($conn, $_POST['contact_person']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            
            $query = "INSERT INTO suppliers (supplier_name, company_name, contact_person, phone, email, address, city, state, country, postal_code, tax_number) 
                      VALUES ('$name', '$company', '$contact', '$phone', " . ($email ? "'$email'" : "NULL") . ", '$address', '$city', '$state', '$country', '$postal_code', '$tax_number')";
            
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
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $email = !empty($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : null;
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $state = mysqli_real_escape_string($conn, $_POST['state']);
            $country = mysqli_real_escape_string($conn, $_POST['country']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE suppliers SET 
                      supplier_name = '$name',
                      company_name = '$company',
                      contact_person = '$contact',
                      phone = '$phone',
                      email = " . ($email ? "'$email'" : "NULL") . ",
                      address = '$address',
                      city = '$city',
                      state = '$state',
                      country = '$country',
                      postal_code = '$postal_code',
                      tax_number = '$tax_number',
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
            $query = "DELETE FROM suppliers WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;
    }
}

// Get suppliers
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (supplier_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR company_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
                OR contact_person LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($status) {
    $where .= " AND status = '" . mysqli_real_escape_string($conn, $status) . "'";
}

$suppliers = mysqli_query($conn, "SELECT * FROM suppliers $where ORDER BY supplier_name ASC");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Suppliers</h4>
                    <div class="page-title-right">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="bi bi-plus-circle"></i> Add Supplier
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search suppliers..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="?" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Supplier Name</th>
                                            <th>Company</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>City</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($supplier = mysqli_fetch_assoc($suppliers)): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                                <td><?= htmlspecialchars($supplier['company_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($supplier['contact_person'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($supplier['phone']) ?></td>
                                                <td><?= htmlspecialchars($supplier['email'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($supplier['city'] ?? '') ?></td>
                                                <td>
                                                    <span class="badge <?= $supplier['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= ucfirst($supplier['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-primary" onclick="editSupplier(<?= $supplier['id'] ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger" onclick="deleteSupplier(<?= $supplier['id'] ?>)">
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
                            <div class="text-center py-4">
                                <i class="bi bi-building display-4 text-muted"></i>
                                <h4 class="mt-3">No Suppliers Found</h4>
                                <p class="text-muted">Start by adding your first supplier.</p>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="bi bi-plus-circle"></i> Add First Supplier
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Add New Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSupplierForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="supplier_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="tax_number" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="India">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Edit Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSupplierForm">
                <input type="hidden" name="id" id="editSupplierId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" name="supplier_name" id="editSupplierName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" id="editCompanyName" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" id="editContactPerson" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" id="editPhone" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="tax_number" id="editTaxNumber" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="editAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="editCity" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="editState" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="editCountry" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" id="editPostalCode" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
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
            document.getElementById('editPhone').value = supplier.phone;
            document.getElementById('editEmail').value = supplier.email || '';
            document.getElementById('editTaxNumber').value = supplier.tax_number || '';
            document.getElementById('editAddress').value = supplier.address || '';
            document.getElementById('editCity').value = supplier.city || '';
            document.getElementById('editState').value = supplier.state || '';
            document.getElementById('editCountry').value = supplier.country || '';
            document.getElementById('editPostalCode').value = supplier.postal_code || '';
            document.getElementById('editStatus').value = supplier.status;
            
            new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
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
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Delete Supplier
function deleteSupplier(id) {
    if (confirm('Are you sure you want to delete this supplier?')) {
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
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<?php include '../../layouts/footer.php'; ?>
