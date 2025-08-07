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

$page_title = 'Asset Allocation - HRMS';

// Fetch asset allocations from database
$asset_allocations = [];
$result = mysqli_query($conn, "
    SELECT aa.id, aa.asset_code as asset_id, aa.asset_name, 
           aa.employee_id, aa.employee_name, 
           COALESCE(e.department_name, 'Unassigned') as department,
           aa.allocated_date, aa.return_date, aa.status, 
           aa.condition_status as condition, aa.location
    FROM asset_allocations aa
    LEFT JOIN hr_employees e ON aa.employee_id = e.employee_id
    ORDER BY aa.allocated_date DESC
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $asset_allocations[] = $row;
    }
}

// Available assets (you can create a separate table for this)
$available_assets = [
    ['id' => 'LAP005', 'name' => 'MacBook Pro 14"', 'category' => 'Laptop', 'status' => 'available'],
    ['id' => 'PHN006', 'name' => 'Samsung Galaxy S24', 'category' => 'Phone', 'status' => 'available'],
    ['id' => 'TAB007', 'name' => 'iPad Pro 12.9"', 'category' => 'Tablet', 'status' => 'available'],
    ['id' => 'KEY008', 'name' => 'Mechanical Keyboard', 'category' => 'Accessory', 'status' => 'available']
];

$current_page = 'asset_allocation';

require_once 'hrms_header_simple.php';
if (!isset($root_path)) 
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-diagram-3 text-primary me-3"></i>Asset Allocation
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage and track company assets assigned to employees</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportAllocationReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <div class="btn-group">
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-circle"></i> New
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#allocateAssetModal">
                            <i class="bi bi-diagram-3"></i> Allocate Asset
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#returnAssetModal">
                            <i class="bi bi-arrow-return-left"></i> Return Asset
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-diagram-3-fill fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #1976d2;"><?= count($asset_allocations) ?></h3>
                        <p class="text-muted mb-0">Total Allocations</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #388e3c;"><?= count(array_filter($asset_allocations, fn($a) => $a['status'] === 'allocated')) ?></h3>
                        <p class="text-muted mb-0">Currently Allocated</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-archive-fill fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #f57c00;"><?= count($available_assets) ?></h3>
                        <p class="text-muted mb-0">Available Assets</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-arrow-return-left fs-1" style="color: #d32f2f;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #d32f2f;"><?= count(array_filter($asset_allocations, fn($a) => $a['status'] === 'returned')) ?></h3>
                        <p class="text-muted mb-0">Returned Assets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Allocations Table -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Asset Allocations</h5>
                    <div class="btn-group" role="group" aria-label="Status filter">
                        <button type="button" class="btn btn-outline-primary btn-sm status-filter active" data-status="all">
                            All Allocations
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm status-filter" data-status="allocated">
                            Currently Allocated
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm status-filter" data-status="returned">
                            Returned
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="allocationsTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Asset</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Allocated Date</th>
                                <th>Return Date</th>
                                <th>Condition</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asset_allocations as $allocation): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($allocation['asset_name']) ?></div>
                                        <small class="text-muted"><?= $allocation['asset_id'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($allocation['employee_name']) ?></div>
                                        <small class="text-muted"><?= $allocation['employee_id'] ?></small>
                                    </td>
                                    <td><?= $allocation['department'] ?></td>
                                    <td><?= date('M j, Y', strtotime($allocation['allocated_date'])) ?></td>
                                    <td>
                                        <?php if ($allocation['return_date']): ?>
                                            <?= date('M j, Y', strtotime($allocation['return_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $condition_colors = [
                                            'excellent' => 'success',
                                            'good' => 'info',
                                            'fair' => 'warning',
                                            'poor' => 'danger'
                                        ];
                                        $color = $condition_colors[$allocation['condition']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($allocation['condition']) ?></span>
                                    </td>
                                    <td><?= $allocation['location'] ?></td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'allocated' => 'success',
                                            'returned' => 'secondary',
                                            'overdue' => 'danger'
                                        ];
                                        $color = $status_colors[$allocation['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($allocation['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($allocation['status'] === 'allocated'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Return Asset">
                                                    <i class="bi bi-arrow-return-left"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Available Assets -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-archive"></i> Available Assets</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="availableAssetsTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Asset ID</th>
                                <th>Asset Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_assets as $asset): ?>
                                <tr>
                                    <td><span class="fw-semibold"><?= $asset['id'] ?></span></td>
                                    <td><?= htmlspecialchars($asset['name']) ?></td>
                                    <td><?= $asset['category'] ?></td>
                                    <td>
                                        <span class="badge bg-success">Available</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" title="Quick Allocate" data-bs-toggle="modal" data-bs-target="#allocateAssetModal">
                                            <i class="bi bi-plus-circle"></i> Allocate
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Allocate Asset Modal -->
<div class="modal fade" id="allocateAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3"></i> Allocate Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="asset_select" class="form-label">Select Asset *</label>
                                <select class="form-select" id="asset_select" required>
                                    <option value="">Choose an asset</option>
                                    <?php foreach ($available_assets as $asset): ?>
                                        <option value="<?= $asset['id'] ?>"><?= $asset['id'] ?> - <?= htmlspecialchars($asset['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employee_select" class="form-label">Select Employee *</label>
                                <select class="form-select" id="employee_select" required>
                                    <option value="">Choose an employee</option>
                                    <option value="EMP001">EMP001 - John Doe</option>
                                    <option value="EMP002">EMP002 - Sarah Wilson</option>
                                    <option value="EMP003">EMP003 - Mike Johnson</option>
                                    <option value="EMP004">EMP004 - Emily Davis</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="allocation_date" class="form-label">Allocation Date *</label>
                                <input type="date" class="form-control" id="allocation_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expected_return" class="form-label">Expected Return Date</label>
                                <input type="date" class="form-control" id="expected_return">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="condition" class="form-label">Asset Condition</label>
                                <select class="form-select" id="condition">
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <select class="form-select" id="location">
                                    <option value="head_office">Head Office</option>
                                    <option value="regional_office">Regional Office</option>
                                    <option value="remote_work">Remote Work</option>
                                    <option value="warehouse">Warehouse</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="3" placeholder="Any additional notes about the allocation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Allocate Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Asset Modal -->
<div class="modal fade" id="returnAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-return-left"></i> Return Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="allocation_select" class="form-label">Select Allocation *</label>
                        <select class="form-select" id="allocation_select" required>
                            <option value="">Choose an allocation</option>
                            <?php foreach ($asset_allocations as $allocation): ?>
                                <?php if ($allocation['status'] === 'allocated'): ?>
                                    <option value="<?= $allocation['id'] ?>"><?= $allocation['asset_id'] ?> - <?= htmlspecialchars($allocation['employee_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="return_date" class="form-label">Return Date *</label>
                        <input type="date" class="form-control" id="return_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="return_condition" class="form-label">Return Condition</label>
                        <select class="form-select" id="return_condition">
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="return_notes" class="form-label">Return Notes</label>
                        <textarea class="form-control" id="return_notes" rows="3" placeholder="Any notes about the asset condition or return..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Process Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables for allocations table
    $('#allocationsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [
            { 
                targets: -1, 
                orderable: false,
                searchable: false
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search allocations...",
            lengthMenu: "Show _MENU_ allocations per page",
            info: "Showing _START_ to _END_ of _TOTAL_ allocations",
            infoEmpty: "No allocations found",
            infoFiltered: "(filtered from _MAX_ total allocations)",
            zeroRecords: "No matching allocations found"
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Initialize DataTables for available assets table
    $('#availableAssetsTable').DataTable({
        responsive: true,
        pageLength: 15,
        order: [[1, 'asc']],
        columnDefs: [
            { 
                targets: -1, 
                orderable: false,
                searchable: false
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search available assets...",
            lengthMenu: "Show _MENU_ assets per page",
            info: "Showing _START_ to _END_ of _TOTAL_ assets",
            infoEmpty: "No assets found",
            infoFiltered: "(filtered from _MAX_ total assets)",
            zeroRecords: "No matching assets found"
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Status filter functionality
    $('.status-filter').on('click', function() {
        const status = $(this).data('status');
        const table = $('#allocationsTable').DataTable();
        
        if (status === 'all') {
            table.column(7).search('').draw();
        } else {
            table.column(7).search(status).draw();
        }
        
        // Update active button
        $('.status-filter').removeClass('active');
        $(this).addClass('active');
    });

    // Form validation
    $('form').on('submit', function(e) {
        const form = $(this);
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

function exportAllocationReport() {
    alert('Exporting allocation report...');
}
</script>

<?php if (!isset($root_path)) 
require_once 'hrms_footer_simple.php'; ?>
                    </div>
                </div>
            </div>
        </div>
                    </div>
                </div>

                <!-- Allocate Asset Tab -->
                <div class="tab-pane fade" id="allocate" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="allocation-form">
                                <h5 class="mb-4">Allocate Asset to Employee</h5>
                                <form>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Asset</label>
                                                <select class="form-select">
                                                    <option value="">Choose an asset...</option>
                                                    <?php foreach ($available_assets as $asset): ?>
                                                    <option value="<?= $asset['id'] ?>"><?= $asset['name'] ?> (<?= $asset['id'] ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Employee</label>
                                                <select class="form-select">
                                                    <option value="">Choose an employee...</option>
                                                    <option value="EMP001">John Doe (EMP001)</option>
                                                    <option value="EMP002">Sarah Wilson (EMP002)</option>
                                                    <option value="EMP003">Mike Johnson (EMP003)</option>
                                                    <option value="EMP004">Emily Davis (EMP004)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Allocation Date</label>
                                                <input type="date" class="form-control" value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Expected Return Date</label>
                                                <input type="date" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Asset Condition</label>
                                                <select class="form-select">
                                                    <option value="excellent">Excellent</option>
                                                    <option value="good">Good</option>
                                                    <option value="fair">Fair</option>
                                                    <option value="poor">Poor</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Location</label>
                                                <select class="form-select">
                                                    <option value="head_office">Head Office</option>
                                                    <option value="regional_office">Regional Office</option>
                                                    <option value="remote_work">Remote Work</option>
                                                    <option value="warehouse">Warehouse</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" rows="3" placeholder="Any additional notes about the allocation..."></textarea>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary btn-modern" onclick="processAllocation()">
                                        <i class="bi bi-check-lg me-2"></i>Allocate Asset
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="content-card">
                                <div class="card-header">
                                    <h6 class="mb-0">Allocation Guidelines</h6>
                                </div>
                                <div class="p-3">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Verify employee eligibility</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Check asset condition</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Document allocation properly</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Set return expectations</li>
                                        <li class="mb-0"><i class="bi bi-check-circle text-success me-2"></i>Notify relevant departments</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Assets Tab -->
                <div class="tab-pane fade" id="available" role="tabpanel">
                    <div class="row">
                        <?php foreach ($available_assets as $asset): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="asset-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-success">Available</span>
                                    <small class="text-muted"><?= $asset['id'] ?></small>
                                </div>
                                
                                <h6 class="mb-2"><?= htmlspecialchars($asset['name']) ?></h6>
                                <p class="text-muted small mb-3">Category: <?= $asset['category'] ?></p>
                                
                                <button class="btn btn-primary btn-sm w-100" onclick="quickAllocate('<?= $asset['id'] ?>')">
                                    <i class="bi bi-arrow-right me-2"></i>Quick Allocate
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- History Tab -->
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Employee</th>
                                    <th>Allocated Date</th>
                                    <th>Returned Date</th>
                                    <th>Duration</th>
                                    <th>Condition</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asset_allocations as $allocation): ?>
                                <?php if ($allocation['status'] == 'returned'): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($allocation['asset_name']) ?></div>
                                        <div class="text-muted small"><?= $allocation['asset_id'] ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($allocation['employee_name']) ?></div>
                                        <div class="text-muted small"><?= $allocation['employee_id'] ?></div>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($allocation['allocated_date'])) ?></td>
                                    <td><?= $allocation['return_date'] ? date('M j, Y', strtotime($allocation['return_date'])) : '-' ?></td>
                                    <td>
                                        <?php
                                        if ($allocation['return_date']) {
                                            $start = new DateTime($allocation['allocated_date']);
                                            $end = new DateTime($allocation['return_date']);
                                            $diff = $start->diff($end);
                                            echo $diff->days . ' days';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="condition-indicator condition-<?= $allocation['condition'] ?>">
                                            <?= ucfirst($allocation['condition']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewAllocationHistory(<?= $allocation['id'] ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewAllocationDetails(allocationId) {
            showAlert(`Viewing allocation details for ID ${allocationId}...`, 'info');
        }

        function editAllocation(allocationId) {
            showAlert(`Editing allocation ${allocationId}...`, 'warning');
        }

        function returnAsset(allocationId) {
            showAlert(`Processing asset return for allocation ${allocationId}...`, 'warning');
        }

        function allocateNewAsset() {
            // Switch to allocate tab
            const allocateTab = new bootstrap.Tab(document.querySelector('[data-bs-target="#allocate"]'));
            allocateTab.show();
        }

        function processAllocation() {
            showAlert('Asset allocation processed successfully!', 'success');
        }

        function quickAllocate(assetId) {
            showAlert(`Quick allocation for asset ${assetId} will be implemented!`, 'info');
        }

        function viewAllocationHistory(allocationId) {
            showAlert(`Viewing allocation history for ID ${allocationId}...`, 'info');
        }

        function exportAllocationReport() {
            showAlert('Exporting allocation report...', 'success');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertDiv);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.textContent.includes(message)) {
                        alert.remove();
                    }
                });
            }, 5000);
        }
    </script>
    </div>
</div>

<?php if (!isset($root_path)) 
require_once 'hrms_footer_simple.php'; 
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>

require_once 'hrms_footer_simple.php';
?>