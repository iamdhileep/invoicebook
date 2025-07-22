<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Employee Management';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Employee Management</h1>
            <p class="text-muted">Manage your team members and their information</p>
        </div>
        <div>
            <a href="../../attendance.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-calendar-check"></i> Mark Attendance
            </a>
            <a href="../../payroll.php" class="btn btn-outline-success">
                <i class="bi bi-currency-rupee"></i> Payroll
            </a>
        </div>
    </div>

    <!-- Employee Statistics -->
    <?php
    $totalEmployees = 0;
    $totalSalary = 0;
    $activeEmployees = 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(monthly_salary) as total_salary FROM employees");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalEmployees = $row['total'] ?? 0;
        $totalSalary = $row['total_salary'] ?? 0;
    }
    $activeEmployees = $totalEmployees; // Assuming all are active for now
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Employees</h6>
                            <h2 class="mb-0"><?= $totalEmployees ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Monthly Payroll</h6>
                            <h2 class="mb-0">₹ <?= number_format($totalSalary, 0) ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Active Employees</h6>
                            <h2 class="mb-0"><?= $activeEmployees ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <!-- Add Employee Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add New Employee</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="../../employee-tabs.php" enctype="multipart/form-data">
                        <input type="hidden" name="add" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Employee Code *</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g., EMP001" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., Manager, Cashier" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monthly Salary (₹) *</label>
                            <input type="number" step="0.01" name="monthly_salary" class="form-control" placeholder="0.00" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="text" name="phone" class="form-control" placeholder="Enter phone number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address *</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter full address" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <div class="form-text">Upload employee photo (Optional)</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-person-plus"></i> Add Employee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Employee List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Employee List</h5>
                    <div>
                        <input type="text" id="employeeSearch" class="form-control form-control-sm" placeholder="Search employees..." style="width: 200px;">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="empTable" class="table table-striped data-table">
                            <thead>
                                <tr>
                                    <th width="60">Photo</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Position</th>
                                    <th>Phone</th>
                                    <th>Salary</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $result = mysqli_query($conn, "SELECT * FROM employees ORDER BY employee_id DESC");
                                if (!$result) {
                                    echo "<tr><td colspan='7' class='text-center text-muted'>Error loading employees: " . mysqli_error($conn) . "</td></tr>";
                                } else {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $photo = !empty($row['photo']) ? 'uploads/' . $row['photo'] : 'img/default-avatar.png';
                                        echo "<tr>";
                                        echo "<td>";
                                        echo "<img src='../../{$photo}' alt='Photo' class='rounded-circle' width='50' height='50' style='object-fit: cover;' onerror=\"this.src='../../img/default-avatar.png'\">";
                                        echo "</td>";
                                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                                        echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['code']) . "</span></td>";
                                        echo "<td>" . htmlspecialchars($row['position']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                                        echo "<td><strong class='text-success'>₹ " . number_format($row['monthly_salary'], 2) . "</strong></td>";
                                        echo "<td>";
                                        echo "<div class='btn-group btn-group-sm'>";
                                        echo "<a href='../../edit_employee.php?id={$row['employee_id']}' class='btn btn-outline-primary' data-bs-toggle='tooltip' title='Edit'>";
                                        echo "<i class='bi bi-pencil'></i>";
                                        echo "</a>";
                                        echo "<button type='button' class='btn btn-outline-danger delete-btn' data-id='{$row['employee_id']}' data-bs-toggle='tooltip' title='Delete'>";
                                        echo "<i class='bi bi-trash'></i>";
                                        echo "</button>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Employee Details Modal -->
            <div class="modal fade" id="employeeModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Employee Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="employeeDetails">
                                <!-- Employee details will be loaded here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    $(document).ready(function() {
        // Initialize DataTable
        const table = $("#empTable").DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            responsive: true,
            order: [[1, "asc"]], // Sort by name
            columnDefs: [
                { orderable: false, targets: [0, 6] } // Disable sorting for photo and actions columns
            ]
        });

        // Search functionality
        $("#employeeSearch").on("input", function() {
            table.search(this.value).draw();
        });

        // Delete employee functionality
        $(".delete-btn").click(function() {
            const btn = $(this);
            const id = btn.data("id");
            const row = btn.closest("tr");
            const employeeName = row.find("td:nth-child(2)").text();

            if (confirm(`Are you sure you want to delete employee "${employeeName}"? This action cannot be undone.`)) {
                $.ajax({
                    url: "../../employee-tabs.php",
                    method: "POST",
                    data: {
                        ajax_delete: 1,
                        id: id
                    },
                    success: function(res) {
                        if (res.trim() === "success") {
                            row.fadeOut(300, function() {
                                table.row(this).remove().draw();
                            });
                            showAlert("Employee deleted successfully", "success");
                            
                            // Update statistics
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert("Failed to delete employee: " + res, "danger");
                        }
                    },
                    error: function() {
                        showAlert("AJAX error occurred while deleting employee", "danger");
                    }
                });
            }
        });

        // View employee details (placeholder for future implementation)
        $(document).on("click", ".view-employee", function() {
            const employeeId = $(this).data("id");
            // Load employee details via AJAX and show in modal
            $("#employeeModal").modal("show");
        });

        // Form validation and enhancement
        $("form").on("submit", function(e) {
            const form = this;
            const salary = parseFloat($("input[name=monthly_salary]").val());
            
            if (salary <= 0) {
                e.preventDefault();
                showAlert("Please enter a valid salary amount", "warning");
                return false;
            }
            
            // Show loading state
            $(form).find("button[type=submit]").prop("disabled", true).html("<i class=\"bi bi-hourglass-split\"></i> Adding...");
        });

        // Format salary input
        $("input[name=monthly_salary]").on("blur", function() {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });

        // Phone number formatting
        $("input[name=phone]").on("input", function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9+\-\s]/g, "");
        });

        // Employee code formatting
        $("input[name=code]").on("input", function() {
            // Convert to uppercase
            this.value = this.value.toUpperCase();
        });
    });
</script>
';

include '../../layouts/footer.php';
?>