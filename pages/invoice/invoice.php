<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Create Invoice';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create Invoice</h1>
                <p class="text-muted">Generate a new customer invoice</p>
            </div>
            <div>
                <a href="../../invoice_history.php" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history"></i> Invoice History
                </a>
            </div>
        </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Invoice Details</h5>
                </div>
                <div class="card-body">
                    <form action="../../save_invoice.php" method="POST">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Customer *</label>
                                <div class="input-group">
                                    <input type="text" name="customer_name" id="customerSearch" class="form-control" placeholder="Search or enter customer name" required autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" onclick="showNewCustomerForm()" title="Add New Customer">
                                        <i class="bi bi-person-plus"></i>
                                    </button>
                                    <a href="../customers/customers.php" class="btn btn-outline-info" title="Manage Customers" target="_blank">
                                        <i class="bi bi-people"></i>
                                    </a>
                                </div>
                                <div id="customerDropdown" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto;"></div>
                                <input type="hidden" name="customer_id" id="selectedCustomerId">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Contact *</label>
                                <input type="text" name="customer_contact" id="customerContact" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Bill To Address *</label>
                                <textarea name="bill_address" id="billAddress" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Invoice Date *</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <!-- New Customer Form (initially hidden) -->
                        <div id="newCustomerForm" class="card mb-4" style="display: none;">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-plus text-primary me-2"></i>Add New Customer
                                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" onclick="hideNewCustomerForm()">
                                        <i class="bi bi-x"></i> Cancel
                                    </button>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" id="newCustomerName" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" id="newCustomerEmail" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone *</label>
                                        <input type="text" id="newCustomerPhone" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Company</label>
                                        <input type="text" id="newCustomerCompany" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address *</label>
                                        <textarea id="newCustomerAddress" class="form-control" rows="2" required></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" id="newCustomerCity" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">State</label>
                                        <input type="text" id="newCustomerState" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid">
                                            <button type="button" onclick="saveNewCustomer()" class="btn btn-success">
                                                <i class="bi bi-check-lg"></i> Save Customer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6>Invoice Items</h6>
                            <div id="itemRows">
                                <div class="row g-2 mb-2 item-row">
                                    <div class="col-md-4">
                                        <select name="item_name[]" class="form-select" required>
                                            <option value="">-- Select Item --</option>
                                            <?php 
                                            if ($itemOptions && mysqli_num_rows($itemOptions) > 0):
                                                while ($item = $itemOptions->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                                                    <?= htmlspecialchars($item['item_name']) ?> (₹<?= $item['item_price'] ?>)
                                                </option>
                                            <?php endwhile;
                                            endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" min="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="item_price[]" placeholder="Price" class="form-control price" step="0.01" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm remove-item">×</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addRow()" class="btn btn-sm btn-success">Add Item</button>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <strong>Grand Total: ₹ <span id="grandTotal">0.00</span></strong>
                                        <input type="hidden" name="grand_total" id="grandTotalInput">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Invoice</button>
                            <a href="../dashboard/dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../invoice_history.php" class="btn btn-outline-primary">View All Invoices</a>
                        <a href="../../add_item.php" class="btn btn-outline-success">Add New Product</a>
                        <a href="../products/products.php" class="btn btn-outline-warning">Manage Products</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Customer Search Functionality
let customerSearchTimeout;
let customers = [];

document.addEventListener("DOMContentLoaded", () => {
    updateListeners();
    calculateTotals();
    initCustomerSearch();
});

function initCustomerSearch() {
    const customerSearch = document.getElementById('customerSearch');
    const customerDropdown = document.getElementById('customerDropdown');
    
    // Load all customers initially
    loadCustomers();
    
    customerSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear existing timeout
        clearTimeout(customerSearchTimeout);
        
        if (query.length >= 1) {
            customerSearchTimeout = setTimeout(() => {
                searchCustomers(query);
            }, 300);
        } else {
            customerDropdown.style.display = 'none';
        }
    });
    
    customerSearch.addEventListener('focus', function() {
        if (this.value.trim().length >= 1) {
            customerDropdown.style.display = 'block';
        }
    });
    
    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!customerSearch.contains(e.target) && !customerDropdown.contains(e.target)) {
            customerDropdown.style.display = 'none';
        }
    });
}

function loadCustomers() {
    fetch('../customers/customer_api.php?action=get_all_customers')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            customers = data.data;
        }
    })
    .catch(error => console.error('Error loading customers:', error));
}

function searchCustomers(query) {
    const filtered = customers.filter(customer => 
        customer.customer_name.toLowerCase().includes(query.toLowerCase()) ||
        (customer.email && customer.email.toLowerCase().includes(query.toLowerCase())) ||
        (customer.phone && customer.phone.includes(query))
    );
    
    displayCustomerDropdown(filtered);
}

function displayCustomerDropdown(customerList) {
    const dropdown = document.getElementById('customerDropdown');
    
    if (customerList.length === 0) {
        dropdown.innerHTML = '<div class="dropdown-item text-muted">No customers found</div>';
    } else {
        dropdown.innerHTML = customerList.map(customer => `
            <div class="dropdown-item customer-item" onclick="selectCustomer(${customer.id})" style="cursor: pointer;">
                <div>
                    <strong>${customer.customer_name}</strong>
                    ${customer.email ? `<br><small class="text-muted">${customer.email}</small>` : ''}
                    ${customer.phone ? `<br><small class="text-muted">${customer.phone}</small>` : ''}
                    ${customer.city ? `<br><small class="text-muted">${customer.city}</small>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    dropdown.style.display = 'block';
}

function selectCustomer(customerId) {
    fetch(`../customers/customer_api.php?action=get_customer_info&id=${customerId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.data;
            
            // Fill form fields
            document.getElementById('customerSearch').value = customer.customer_name;
            document.getElementById('selectedCustomerId').value = customer.id;
            document.getElementById('customerContact').value = customer.phone || '';
            document.getElementById('billAddress').value = customer.address || '';
            
            // Hide dropdown
            document.getElementById('customerDropdown').style.display = 'none';
        }
    })
    .catch(error => console.error('Error loading customer:', error));
}

// New Customer Functions
function showNewCustomerForm() {
    document.getElementById('newCustomerForm').style.display = 'block';
    document.getElementById('newCustomerName').focus();
}

function hideNewCustomerForm() {
    document.getElementById('newCustomerForm').style.display = 'none';
    // Clear form
    document.getElementById('newCustomerName').value = '';
    document.getElementById('newCustomerEmail').value = '';
    document.getElementById('newCustomerPhone').value = '';
    document.getElementById('newCustomerCompany').value = '';
    document.getElementById('newCustomerAddress').value = '';
    document.getElementById('newCustomerCity').value = '';
    document.getElementById('newCustomerState').value = '';
}

function saveNewCustomer() {
    const formData = new FormData();
    formData.append('action', 'add_customer');
    formData.append('customer_name', document.getElementById('newCustomerName').value);
    formData.append('email', document.getElementById('newCustomerEmail').value);
    formData.append('phone', document.getElementById('newCustomerPhone').value);
    formData.append('company_name', document.getElementById('newCustomerCompany').value);
    formData.append('address', document.getElementById('newCustomerAddress').value);
    formData.append('city', document.getElementById('newCustomerCity').value);
    formData.append('state', document.getElementById('newCustomerState').value);
    formData.append('country', 'India');
    formData.append('tax_number', '');
    formData.append('website', '');
    formData.append('notes', '');
    
    fetch('../customers/customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fill the main form with new customer data
            document.getElementById('customerSearch').value = document.getElementById('newCustomerName').value;
            document.getElementById('customerContact').value = document.getElementById('newCustomerPhone').value;
            document.getElementById('billAddress').value = document.getElementById('newCustomerAddress').value;
            
            hideNewCustomerForm();
            loadCustomers(); // Refresh customer list
            
            // Show success message
            showAlert('Customer added successfully!', 'success');
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error saving customer', 'error');
    });
}

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

function calculateTotals() {
    let total = 0;
    document.querySelectorAll(".item-row").forEach(row => {
        const qty = parseFloat(row.querySelector(".qty")?.value) || 0;
        const price = parseFloat(row.querySelector(".price")?.value) || 0;
        const itemTotal = qty * price;
        if (row.querySelector(".total")) {
            row.querySelector(".total").value = itemTotal.toFixed(2);
        }
        total += itemTotal;
    });
    
    document.getElementById("grandTotal").textContent = total.toFixed(2);
    document.getElementById("grandTotalInput").value = total.toFixed(2);
}

function addRow() {
    const container = document.getElementById("itemRows");
    const newRow = container.querySelector(".item-row").cloneNode(true);
    
    // Clear the values
    newRow.querySelectorAll("input, select").forEach(input => {
        if (input.type !== "button") input.value = "";
    });
    
    container.appendChild(newRow);
    updateListeners();
}

function updateListeners() {
    document.querySelectorAll(".qty, .price").forEach(input => {
        input.removeEventListener("input", calculateTotals);
        input.addEventListener("input", calculateTotals);
    });
    
    document.querySelectorAll(".remove-item").forEach(button => {
        button.onclick = function() {
            if (document.querySelectorAll(".item-row").length > 1) {
                this.closest(".item-row").remove();
                calculateTotals();
            }
        };
    });
    
    document.querySelectorAll("select[name='item_name[]']").forEach(select => {
        select.addEventListener("change", function() {
            const price = this.selectedOptions[0].getAttribute("data-price");
            const row = this.closest(".item-row");
            if (price) {
                row.querySelector(".price").value = price;
                row.querySelector(".qty").value = 1;
                calculateTotals();
            }
        });
    });
}
</script>

<style>
.customer-item:hover {
    background-color: #f8f9fa;
}
.dropdown-menu {
    position: relative !important;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 1000;
}
</style>

    </div>
</div>

<?php include '../../layouts/footer.php'; ?>