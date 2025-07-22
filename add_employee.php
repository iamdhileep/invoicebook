<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Add Employee';

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $position = trim($_POST['position']);
    $monthly_salary = floatval($_POST['monthly_salary']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email'] ?? '');
    
    if (empty($name) || empty($code) || empty($position) || $monthly_salary <= 0 || empty($phone) || empty($address)) {
        $error = 'Please fill all required fields with valid data.';
    } else {
        // Check if employee code already exists with fallback for different column names
        $checkQuery = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ?");
        
        if (!$checkQuery) {
            // Fallback: try with different column names
            $checkQuery = $conn->prepare("SELECT id FROM employees WHERE employee_code = ?");
            
            if (!$checkQuery) {
                // Fallback: try with 'code' column name
                $checkQuery = $conn->prepare("SELECT id FROM employees WHERE code = ?");
                
                if (!$checkQuery) {
                    $error = 'Database error: Could not check employee code - ' . $conn->error;
                }
            }
        }
        
        if (!empty($error)) {
            // Skip the check if there was an error
        } elseif ($checkQuery) {
            $checkQuery->bind_param("s", $code);
            $checkQuery->execute();
            $result = $checkQuery->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'An employee with this code already exists.';
            }
        }
        
        if (empty($error)) {
            // Handle photo upload
            $photoPath = '';
            if (!empty($_FILES['photo']['name'])) {
                $uploadDir = 'uploads/employees/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $photoName = time() . '_' . basename($_FILES['photo']['name']);
                $photoPath = $uploadDir . $photoName;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                    // Photo uploaded successfully
                } else {
                    $photoPath = '';
                }
            }
            
            // Try modern schema first, then fallback to basic schema
            $insertQuery = $conn->prepare("INSERT INTO employees (employee_name, employee_code, position, monthly_salary, phone, address, email, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if (!$insertQuery) {
                // Fallback 1: Try with 'name' instead of 'employee_name'
                $insertQuery = $conn->prepare("INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, email, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if (!$insertQuery) {
                    // Fallback 2: Try without created_at column
                    $insertQuery = $conn->prepare("INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, email, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if (!$insertQuery) {
                        // Fallback 3: Try minimal schema (core columns only)
                        $insertQuery = $conn->prepare("INSERT INTO employees (name, employee_code, position, monthly_salary) VALUES (?, ?, ?, ?)");
                        
                        if (!$insertQuery) {
                            // Fallback 4: Try most basic schema
                            $insertQuery = $conn->prepare("INSERT INTO employees (name, employee_code) VALUES (?, ?)");
                            if (!$insertQuery) {
                                $error = 'Database error: Failed to prepare insert statement - ' . $conn->error;
                            } else {
                                $insertQuery->bind_param("ss", $name, $code);
                            }
                        } else {
                            $insertQuery->bind_param("sssd", $name, $code, $position, $monthly_salary);
                        }
                    } else {
                        $insertQuery->bind_param("sssdssss", $name, $code, $position, $monthly_salary, $phone, $address, $email, $photoPath);
                    }
                } else {
                    $insertQuery->bind_param("sssdssss", $name, $code, $position, $monthly_salary, $phone, $address, $email, $photoPath);
                }
            } else {
                $insertQuery->bind_param("sssdssss", $name, $code, $position, $monthly_salary, $phone, $address, $email, $photoPath);
            }
            
            // Execute the insert if we have a valid statement
            if (empty($error) && $insertQuery && $insertQuery->execute()) {
                $success = true;
                
                // Try to update additional columns if the basic insert succeeded
                $employeeId = $conn->insert_id;
                
                // Try to add optional fields if they weren't included in the main insert
                if (!empty($phone) && $employeeId) {
                    $phoneQuery = $conn->prepare("UPDATE employees SET phone = ? WHERE id = ? OR employee_id = ?");
                    if ($phoneQuery) {
                        $phoneQuery->bind_param("sii", $phone, $employeeId, $employeeId);
                        $phoneQuery->execute();
                    }
                }
                
                if (!empty($address) && $employeeId) {
                    $addressQuery = $conn->prepare("UPDATE employees SET address = ? WHERE id = ? OR employee_id = ?");
                    if ($addressQuery) {
                        $addressQuery->bind_param("sii", $address, $employeeId, $employeeId);
                        $addressQuery->execute();
                    }
                }
                
                if (!empty($email) && $employeeId) {
                    $emailQuery = $conn->prepare("UPDATE employees SET email = ? WHERE id = ? OR employee_id = ?");
                    if ($emailQuery) {
                        $emailQuery->bind_param("sii", $email, $employeeId, $employeeId);
                        $emailQuery->execute();
                    }
                }
                
                if (!empty($photoPath) && $employeeId) {
                    $photoQuery = $conn->prepare("UPDATE employees SET photo = ? WHERE id = ? OR employee_id = ?");
                    if ($photoQuery) {
                        $photoQuery->bind_param("sii", $photoPath, $employeeId, $employeeId);
                        $photoQuery->execute();
                    }
                }
                
            } elseif (empty($error)) {
                $error = 'Failed to add employee: ' . ($insertQuery ? $conn->error : 'Could not prepare statement');
            }
        }
        }
    }
}

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Add New Employee</h1>
            <p class="text-muted">Add a new team member to your organization</p>
        </div>
        <div>
            <a href="pages/employees/employees.php" class="btn btn-outline-primary">
                <i class="bi bi-people"></i> View All Employees
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Employee added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" class="form-control" 
                                       placeholder="Enter employee's full name" 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Employee Code *</label>
                                <input type="text" name="code" class="form-control" 
                                       placeholder="e.g., EMP001" 
                                       value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required>
                                <div class="form-text">Unique identifier for the employee</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Position *</label>
                                <input type="text" name="position" class="form-control" 
                                       placeholder="e.g., Manager, Cashier, Sales Executive" 
                                       value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Monthly Salary (₹) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="monthly_salary" class="form-control" 
                                           placeholder="0.00" step="0.01" min="1"
                                           value="<?= htmlspecialchars($_POST['monthly_salary'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" 
                                       placeholder="Enter phone number" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email (Optional)</label>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="employee@example.com" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Address *</label>
                                <textarea name="address" class="form-control" rows="3" 
                                          placeholder="Enter complete address" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Profile Photo (Optional)</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                <div class="form-text">Upload employee photo (JPG, PNG, max 5MB)</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Add Employee
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </button>
                            <a href="pages/employees/employees.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Employees
                            </a>
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
                        <a href="pages/employees/employees.php" class="btn btn-outline-primary">
                            <i class="bi bi-people"></i> View All Employees
                        </a>
                        <a href="pages/attendance/attendance.php" class="btn btn-outline-success">
                            <i class="bi bi-calendar-check"></i> Mark Attendance
                        </a>
                        <a href="pages/payroll/payroll.php" class="btn btn-outline-warning">
                            <i class="bi bi-currency-rupee"></i> Manage Payroll
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use unique employee codes</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Set accurate salary amounts</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Provide complete contact information</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Upload clear profile photos</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $('form').on('submit', function(e) {
        const name = $('input[name="name"]').val().trim();
        const code = $('input[name="code"]').val().trim();
        const salary = parseFloat($('input[name="monthly_salary"]').val());

        if (!name) {
            e.preventDefault();
            showAlert('Please enter employee name', 'warning');
            $('input[name="name"]').focus();
            return false;
        }

        if (!code) {
            e.preventDefault();
            showAlert('Please enter employee code', 'warning');
            $('input[name="code"]').focus();
            return false;
        }

        if (!salary || salary <= 0) {
            e.preventDefault();
            showAlert('Please enter a valid salary amount', 'warning');
            $('input[name="monthly_salary"]').focus();
            return false;
        }
    });

    // Auto-generate employee code
    $('input[name="name"]').blur(function() {
        const name = $(this).val().trim();
        const codeInput = $('input[name="code"]');
        
        if (name && !codeInput.val()) {
            const nameParts = name.split(' ');
            const initials = nameParts.map(part => part.charAt(0).toUpperCase()).join('');
            const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            codeInput.val('EMP' + initials + randomNum);
        }
    });

    // Format phone number
    $('input[name="phone"]').on('input', function() {
        this.value = this.value.replace(/[^0-9+\-\s]/g, '');
    });

    // Auto-dismiss success alerts
    setTimeout(function() {
        $('.alert-success').fadeOut();
    }, 3000);
});

function showAlert(message, type) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('.main-content').prepend(alertDiv);
    
    setTimeout(() => {
        alertDiv.fadeOut();
    }, 5000);
}
</script>

<?php include 'layouts/footer.php'; ?>
