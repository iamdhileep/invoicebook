
<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get employee ID
$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: pages/employees/employees.php?error=' . urlencode('Employee ID is required'));
    exit;
}

// Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: pages/employees/employees.php?error=' . urlencode('Employee not found'));
    exit;
}

$employee = $result->fetch_assoc();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['employee_name'] ?? '');
    $code = trim($_POST['employee_code'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $monthly_salary = floatval($_POST['monthly_salary'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($name) || empty($code) || empty($position) || $monthly_salary <= 0 || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if employee code is unique (excluding current employee)
        $checkQuery = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? AND employee_id != ?");
        $checkQuery->bind_param("si", $code, $employeeId);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = 'Employee code already exists. Please use a different code.';
        } else {
            // Handle photo upload
            $photoPath = $employee['photo']; // Keep existing photo by default
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/employees/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = 'employee_' . $employeeId . '_' . time() . '.' . $fileExtension;
                    $photoPath = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                        $error = 'Failed to upload photo.';
                    } else {
                        // Delete old photo if it exists
                        if (!empty($employee['photo']) && file_exists($employee['photo']) && $employee['photo'] !== $photoPath) {
                            unlink($employee['photo']);
                        }
                    }
                } else {
                    $error = 'Invalid photo format. Please upload JPG, JPEG, PNG, or GIF files only.';
                }
            }
            
            if (empty($error)) {
                // Update employee
                $updateQuery = $conn->prepare("UPDATE employees SET employee_name = ?, employee_code = ?, position = ?, monthly_salary = ?, phone = ?, address = ?, email = ?, photo = ? WHERE employee_id = ?");
                $updateQuery->bind_param("sssdsssi", $name, $code, $position, $monthly_salary, $phone, $address, $email, $photoPath, $employeeId);
                
                if ($updateQuery->execute()) {
                    $success = true;
                    // Refresh employee data
                    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
                    $stmt->bind_param("i", $employeeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $employee = $result->fetch_assoc();
                } else {
                    $error = 'Failed to update employee: ' . $conn->error;
                }
            }
        }
    }
}

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-header d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="page-title mb-0">Edit Employee</h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="pages/dashboard/dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="pages/employees/employees.php">Employees</a></li>
                                    <li class="breadcrumb-item active">Edit Employee</li>
                                </ol>
                            </nav>
                        </div>
                        <div>
                            <a href="pages/employees/employees.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Employees
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            Employee updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-gear me-2"></i>
                                Employee Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="editEmployeeForm">
                                <div class="row">
                                    <!-- Current Photo Display -->
                                    <div class="col-md-3 text-center mb-4">
                                        <div class="mb-3">
                                            <label class="form-label">Current Photo</label>
                                            <div class="photo-preview">
                                                <?php if (!empty($employee['photo']) && file_exists($employee['photo'])): ?>
                                                    <img src="<?= htmlspecialchars($employee['photo']) ?>" 
                                                         class="img-fluid rounded-circle" 
                                                         style="width: 150px; height: 150px; object-fit: cover;" 
                                                         id="currentPhoto">
                                                <?php else: ?>
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                                         style="width: 150px; height: 150px;" id="currentPhoto">
                                                        <i class="bi bi-person text-muted" style="font-size: 4rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="photo" class="form-label">Update Photo</label>
                                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                            <div class="form-text">JPG, JPEG, PNG, GIF (Max: 2MB)</div>
                                        </div>
                                    </div>

                                    <!-- Employee Details -->
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="employee_name" class="form-label">
                                                    Full Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="employee_name" name="employee_name" 
                                                       value="<?= htmlspecialchars($employee['employee_name'] ?? $employee['name'] ?? '') ?>" 
                                                       required placeholder="Enter full name">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="employee_code" class="form-label">
                                                    Employee Code <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="employee_code" name="employee_code" 
                                                       value="<?= htmlspecialchars($employee['employee_code']) ?>" 
                                                       required placeholder="e.g., EMP001">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="position" class="form-label">
                                                    Position/Designation <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="position" name="position" 
                                                       value="<?= htmlspecialchars($employee['position']) ?>" 
                                                       required placeholder="e.g., Software Developer">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="monthly_salary" class="form-label">
                                                    Monthly Salary (₹) <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" 
                                                       value="<?= htmlspecialchars($employee['monthly_salary']) ?>" 
                                                       required min="1" step="0.01" placeholder="Enter monthly salary">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">
                                                    Phone Number <span class="text-danger">*</span>
                                                </label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?= htmlspecialchars($employee['phone']) ?>" 
                                                       required placeholder="e.g., +91 9876543210">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= htmlspecialchars($employee['email'] ?? '') ?>" 
                                                       placeholder="employee@company.com">
                                            </div>

                                            <div class="col-12">
                                                <label for="address" class="form-label">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="3" 
                                                          placeholder="Enter complete address"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="pages/employees/employees.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                            <button type="reset" class="btn btn-outline-warning">
                                                <i class="bi bi-arrow-clockwise"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Update Employee
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Employee Statistics Card -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-graph-up me-2"></i>
                                        Employee Statistics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Get employee statistics
                                    $currentMonth = date('m');
                                    $currentYear = date('Y');
                                    
                                    $statsQuery = $conn->prepare("
                                        SELECT 
                                            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
                                            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days,
                                            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_days
                                        FROM attendance 
                                        WHERE employee_id = ? 
                                        AND MONTH(attendance_date) = ? 
                                        AND YEAR(attendance_date) = ?
                                    ");
                                    $statsQuery->bind_param("iii", $employeeId, $currentMonth, $currentYear);
                                    $statsQuery->execute();
                                    $stats = $statsQuery->get_result()->fetch_assoc();
                                    
                                    $totalDays = ($stats['present_days'] ?? 0) + ($stats['absent_days'] ?? 0) + ($stats['late_days'] ?? 0);
                                    $presentDays = ($stats['present_days'] ?? 0) + ($stats['late_days'] ?? 0);
                                    $attendanceRate = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;
                                    ?>
                                    
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="border-end">
                                                <h4 class="text-success mb-0"><?= $stats['present_days'] ?? 0 ?></h4>
                                                <small class="text-muted">Present</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="border-end">
                                                <h4 class="text-danger mb-0"><?= $stats['absent_days'] ?? 0 ?></h4>
                                                <small class="text-muted">Absent</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-warning mb-0"><?= $stats['late_days'] ?? 0 ?></h4>
                                            <small class="text-muted">Late</small>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    
                                    <div class="text-center">
                                        <h5 class="mb-1">
                                            <span class="badge bg-<?= $attendanceRate >= 90 ? 'success' : ($attendanceRate >= 75 ? 'warning' : 'danger') ?> fs-6">
                                                <?= number_format($attendanceRate, 1) ?>%
                                            </span>
                                        </h5>
                                        <small class="text-muted">Attendance Rate (<?= date('F Y') ?>)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-clock-history me-2"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="attendance_preview.php?employee_id=<?= $employeeId ?>" 
                                           class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-calendar-check"></i> View Attendance History
                                        </a>
                                        <a href="payroll_report.php?employee_id=<?= $employeeId ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-currency-rupee"></i> View Payroll Details
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                onclick="confirmDelete(<?= $employeeId ?>)">
                                            <i class="bi bi-trash"></i> Delete Employee
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>

<script>
// Photo preview functionality
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentPhoto = document.getElementById('currentPhoto');
            currentPhoto.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">`;
        };
        reader.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
    const name = document.getElementById('employee_name').value.trim();
    const code = document.getElementById('employee_code').value.trim();
    const position = document.getElementById('position').value.trim();
    const salary = parseFloat(document.getElementById('monthly_salary').value);
    const phone = document.getElementById('phone').value.trim();
    
    if (!name || !code || !position || salary <= 0 || !phone) {
        e.preventDefault();
        showAlert('Please fill in all required fields.', 'danger');
        return false;
    }
    
    if (salary < 1) {
        e.preventDefault();
        showAlert('Monthly salary must be greater than 0.', 'danger');
        document.getElementById('monthly_salary').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
    submitBtn.disabled = true;
    
    // Re-enable after 3 seconds (in case of form errors)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

// Delete confirmation
function confirmDelete(employeeId) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        window.location.href = `delete_employee.php?id=${employeeId}`;
    }
}

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);
</script>
