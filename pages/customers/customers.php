<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Customer Management';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-users me-2"></i>Customer Management</h1>
                <p class="text-muted mb-0">Manage customer information and relationships</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="openAddCustomerModal()">
                    <i class="fas fa-plus me-2"></i>Add Customer
                </button>
                <button class="btn btn-info me-2" onclick="importFromInvoices()">
                    <i class="fas fa-download me-2"></i>Import from Invoices
                </button>
                <button class="btn btn-outline-primary" onclick="exportCustomers()">
                    <i class="fas fa-file-export me-2"></i>Export
                </button>
            </div>
        </div>

        <!-- Dashboard Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="total-customers">0</h3>
                                <small>Total Customers</small>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="active-customers">0</h3>
                                <small>Active Customers</small>
                            </div>
                            <i class="fas fa-user-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="new-this-month">0</h3>
                                <small>New This Month</small>
                            </div>
                            <i class="fas fa-user-plus fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="top-customer-value">₹0</h3>
                                <small>Top Customer Value</small>
                            </div>
                            <i class="fas fa-crown fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Customers</label>
                        <div class="input-group">
                            <input type="text" id="search-input" class="form-control" 
                                   placeholder="Search by name, email, phone, or company...">
                            <button class="btn btn-outline-secondary" onclick="loadCustomers()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="status-filter" class="form-select" onchange="loadCustomers()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <select id="per-page" class="form-select" onchange="loadCustomers()">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                            <button class="btn btn-outline-info" onclick="toggleAdvancedSearch()">
                                <i class="fas fa-filter me-1"></i>Advanced
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Search (Hidden by default) -->
                <div id="advanced-search" class="row g-3 mt-3" style="display: none;">
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="text" id="city-filter" class="form-control" placeholder="Filter by city">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="text" id="state-filter" class="form-control" placeholder="Filter by state">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country</label>
                        <input type="text" id="country-filter" class="form-control" placeholder="Filter by country">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <input type="text" id="company-filter" class="form-control" placeholder="Filter by company">
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Customer Directory</h5>
            </div>
            <div class="card-body">
                <div id="loading-indicator" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading customers...</p>
                </div>
                
                <div id="customer-table-container" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all-customers" onchange="toggleAllCustomers()">
                                    </th>
                                    <th>Customer Details</th>
                                    <th>Contact Information</th>
                                    <th>Location</th>
                                    <th>Business Info</th>
                                    <th>Status</th>
                                    <th>Invoice Stats</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customers-tbody">
                                <!-- Customer rows will be populated here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Customer pagination" class="mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div id="pagination-info">
                                <!-- Pagination info -->
                            </div>
                            <ul class="pagination mb-0" id="pagination-links">
                                <!-- Pagination links -->
                            </ul>
                        </div>
                    </nav>
                </div>
                
                <div id="no-customers" class="text-center py-5" style="display: none;">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted">Try adjusting your search criteria or add a new customer.</p>
                    <button class="btn btn-primary" onclick="openAddCustomerModal()">
                        <i class="fas fa-plus me-2"></i>Add First Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Add New Customer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" id="customer-id" name="customer_id">
                    
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-user me-2"></i>Basic Information
                            </h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="customer-name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="customer-name" name="customer_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="company-name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company-name" name="company_name">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="text" class="form-control" id="phone" name="phone" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" placeholder="https://">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <!-- Address Information -->
                        <div class="col-12 mt-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>Address Information
                            </h6>
                        </div>
                        
                        <div class="col-12">
                            <label for="address" class="form-label">Street Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" 
                                      placeholder="Street address, apartment, suite, etc."></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="state" class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="postal-code" class="form-label">Postal Code</label>
                            <input type="text" class="form-control" id="postal-code" name="postal_code">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" value="India">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tax-number" class="form-label">Tax Number/GST</label>
                            <input type="text" class="form-control" id="tax-number" name="tax_number">
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="col-12 mt-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-sticky-note me-2"></i>Additional Information
                            </h6>
                        </div>
                        
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes about this customer..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomer()">
                    <i class="fas fa-save me-2"></i><span id="save-button-text">Save Customer</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerDetailsModalLabel">
                    <i class="fas fa-user me-2"></i>Customer Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="customer-details-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this customer?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    This action cannot be undone. If the customer has invoices, deletion will not be allowed.
                </div>
                <div id="delete-customer-info" class="bg-light p-3 rounded">
                    <!-- Customer info to delete -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash me-2"></i>Delete Customer
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>

<script src="../../assets/js/customer_management.js"></script>
