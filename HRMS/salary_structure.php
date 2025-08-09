<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Salary Structure - HRMS";
include '../db.php';

// Handle export request first (before AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'export_salary_data') {
    // Generate CSV export
    $filename = 'salary_structure_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Export Grades
    fputcsv($output, ['SALARY GRADES']);
    fputcsv($output, ['Grade Name', 'Level', 'Min Salary', 'Max Salary', 'Description']);
    
    $grades_result = mysqli_query($conn, "SELECT * FROM salary_grades WHERE is_active = 1");
    while ($row = mysqli_fetch_assoc($grades_result)) {
        fputcsv($output, [$row['grade_name'], $row['grade_level'], $row['min_salary'], $row['max_salary'], $row['grade_description']]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['ALLOWANCES']);
    fputcsv($output, ['Name', 'Type', 'Value', 'Applicable To', 'Taxable', 'Description']);
    
    $allowances_result = mysqli_query($conn, "SELECT * FROM salary_allowances WHERE is_active = 1");
    while ($row = mysqli_fetch_assoc($allowances_result)) {
        fputcsv($output, [$row['allowance_name'], $row['allowance_type'], $row['allowance_value'], $row['applicable_to'], $row['is_taxable'] ? 'Yes' : 'No', $row['description']]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['DEDUCTIONS']);
    fputcsv($output, ['Name', 'Type', 'Value', 'Applicable To', 'Mandatory', 'Description']);
    
    $deductions_result = mysqli_query($conn, "SELECT * FROM salary_deductions WHERE is_active = 1");
    while ($row = mysqli_fetch_assoc($deductions_result)) {
        fputcsv($output, [$row['deduction_name'], $row['deduction_type'], $row['deduction_value'], $row['applicable_to'], $row['is_mandatory'] ? 'Yes' : 'No', $row['description']]);
    }
    
    fclose($output);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_grade':
            // Validate required fields
            if (empty($_POST['grade_name']) || empty($_POST['grade_level']) || 
                !isset($_POST['min_salary']) || !isset($_POST['max_salary'])) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                exit;
            }
            
            $grade_name = mysqli_real_escape_string($conn, trim($_POST['grade_name']));
            $grade_level = mysqli_real_escape_string($conn, trim($_POST['grade_level']));
            $min_salary = floatval($_POST['min_salary']);
            $max_salary = floatval($_POST['max_salary']);
            $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
            
            // Validate salary range
            if ($min_salary <= 0 || $max_salary <= 0) {
                echo json_encode(['success' => false, 'message' => 'Salary amounts must be greater than zero']);
                exit;
            }
            
            if ($min_salary >= $max_salary) {
                echo json_encode(['success' => false, 'message' => 'Maximum salary must be greater than minimum salary']);
                exit;
            }
            
            // Check for duplicate grade names
            $check_query = "SELECT id FROM salary_grades WHERE grade_name = '$grade_name' AND is_active = 1";
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Grade name already exists']);
                exit;
            }
            
            $query = "INSERT INTO salary_grades (grade_name, grade_level, min_salary, max_salary, grade_description) 
                      VALUES ('$grade_name', '$grade_level', $min_salary, $max_salary, '$description')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Salary grade added successfully']);
            } else {
                $error = mysqli_error($conn);
                error_log("Database error in add_grade: " . $error);
                echo json_encode(['success' => false, 'message' => 'Database error: Unable to add grade']);
            }
            exit;

        case 'edit_grade':
            // Validate required fields
            if (empty($_POST['id']) || empty($_POST['grade_name']) || empty($_POST['grade_level']) || 
                !isset($_POST['min_salary']) || !isset($_POST['max_salary'])) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                exit;
            }
            
            $id = intval($_POST['id']);
            $grade_name = mysqli_real_escape_string($conn, trim($_POST['grade_name']));
            $grade_level = mysqli_real_escape_string($conn, trim($_POST['grade_level']));
            $min_salary = floatval($_POST['min_salary']);
            $max_salary = floatval($_POST['max_salary']);
            $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
            
            // Validate ID
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid grade ID']);
                exit;
            }
            
            // Validate salary range
            if ($min_salary <= 0 || $max_salary <= 0) {
                echo json_encode(['success' => false, 'message' => 'Salary amounts must be greater than zero']);
                exit;
            }
            
            if ($min_salary >= $max_salary) {
                echo json_encode(['success' => false, 'message' => 'Maximum salary must be greater than minimum salary']);
                exit;
            }
            
            // Check for duplicate grade names (excluding current record)
            $check_query = "SELECT id FROM salary_grades WHERE grade_name = '$grade_name' AND id != $id AND is_active = 1";
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Grade name already exists']);
                exit;
            }
            
            $query = "UPDATE salary_grades SET 
                      grade_name = '$grade_name',
                      grade_level = '$grade_level',
                      min_salary = $min_salary,
                      max_salary = $max_salary,
                      grade_description = '$description'
                      WHERE id = $id AND is_active = 1";
            
            if (mysqli_query($conn, $query)) {
                if (mysqli_affected_rows($conn) > 0) {
                    echo json_encode(['success' => true, 'message' => 'Salary grade updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No changes were made or grade not found']);
                }
            } else {
                $error = mysqli_error($conn);
                error_log("Database error in edit_grade: " . $error);
                echo json_encode(['success' => false, 'message' => 'Database error: Unable to update grade']);
            }
            exit;

        case 'delete_grade':
            $id = intval($_POST['id']);
            $query = "DELETE FROM salary_grades WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Salary grade deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting salary grade']);
            }
            exit;

        case 'get_grade':
            $id = intval($_POST['id']);
            $query = "SELECT * FROM salary_grades WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($row = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Grade not found']);
            }
            exit;

        case 'add_allowance':
            $name = mysqli_real_escape_string($conn, $_POST['allowance_name']);
            $type = $_POST['allowance_type'];
            $value = floatval($_POST['allowance_value']);
            $applicable_to = mysqli_real_escape_string($conn, $_POST['applicable_to']);
            $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            $query = "INSERT INTO salary_allowances (allowance_name, allowance_type, allowance_value, applicable_to, is_taxable, description) 
                      VALUES ('$name', '$type', $value, '$applicable_to', $is_taxable, '$description')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Allowance added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding allowance']);
            }
            exit;

        case 'edit_allowance':
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['allowance_name']);
            $type = $_POST['allowance_type'];
            $value = floatval($_POST['allowance_value']);
            $applicable_to = mysqli_real_escape_string($conn, $_POST['applicable_to']);
            $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            $query = "UPDATE salary_allowances SET 
                      allowance_name = '$name',
                      allowance_type = '$type',
                      allowance_value = $value,
                      applicable_to = '$applicable_to',
                      is_taxable = $is_taxable,
                      description = '$description'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Allowance updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating allowance']);
            }
            exit;

        case 'delete_allowance':
            $id = intval($_POST['id']);
            $query = "DELETE FROM salary_allowances WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Allowance deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting allowance']);
            }
            exit;

        case 'get_allowance':
            $id = intval($_POST['id']);
            $query = "SELECT * FROM salary_allowances WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($row = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Allowance not found']);
            }
            exit;

        case 'add_deduction':
            $name = mysqli_real_escape_string($conn, $_POST['deduction_name']);
            $type = $_POST['deduction_type'];
            $value = floatval($_POST['deduction_value']);
            $applicable_to = mysqli_real_escape_string($conn, $_POST['applicable_to']);
            $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            $query = "INSERT INTO salary_deductions (deduction_name, deduction_type, deduction_value, applicable_to, is_mandatory, description) 
                      VALUES ('$name', '$type', $value, '$applicable_to', $is_mandatory, '$description')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Deduction added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding deduction']);
            }
            exit;

        case 'edit_deduction':
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['deduction_name']);
            $type = $_POST['deduction_type'];
            $value = floatval($_POST['deduction_value']);
            $applicable_to = mysqli_real_escape_string($conn, $_POST['applicable_to']);
            $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            $query = "UPDATE salary_deductions SET 
                      deduction_name = '$name',
                      deduction_type = '$type',
                      deduction_value = $value,
                      applicable_to = '$applicable_to',
                      is_mandatory = $is_mandatory,
                      description = '$description'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Deduction updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating deduction']);
            }
            exit;

        case 'delete_deduction':
            $id = intval($_POST['id']);
            $query = "DELETE FROM salary_deductions WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Deduction deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting deduction']);
            }
            exit;

        case 'get_deduction':
            $id = intval($_POST['id']);
            $query = "SELECT * FROM salary_deductions WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($row = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Deduction not found']);
            }
            exit;
    }
}

// Get salary statistics
$total_grades_query = "SELECT COUNT(*) as total FROM salary_grades WHERE is_active = 1";
$total_grades_result = mysqli_query($conn, $total_grades_query);
$total_grades = $total_grades_result ? mysqli_fetch_assoc($total_grades_result)['total'] : 0;

$total_employees_query = "SELECT COUNT(*) as total FROM hr_employees WHERE status = 'active'";
$total_employees_result = mysqli_query($conn, $total_employees_query);
$total_employees = $total_employees_result ? mysqli_fetch_assoc($total_employees_result)['total'] : 0;

$total_allowances_query = "SELECT COUNT(*) as total FROM salary_allowances WHERE is_active = 1";
$total_allowances_result = mysqli_query($conn, $total_allowances_query);
$total_allowances = $total_allowances_result ? mysqli_fetch_assoc($total_allowances_result)['total'] : 0;

$total_deductions_query = "SELECT COUNT(*) as total FROM salary_deductions WHERE is_active = 1";
$total_deductions_result = mysqli_query($conn, $total_deductions_query);
$total_deductions = $total_deductions_result ? mysqli_fetch_assoc($total_deductions_result)['total'] : 0;

// Get salary grades
$salary_grades_query = "SELECT sg.*, COUNT(he.id) as employee_count 
                        FROM salary_grades sg 
                        LEFT JOIN hr_employees he ON he.salary BETWEEN sg.min_salary AND sg.max_salary AND he.status = 'active'
                        WHERE sg.is_active = 1 
                        GROUP BY sg.id 
                        ORDER BY sg.min_salary ASC";
$salary_grades_result = mysqli_query($conn, $salary_grades_query);
$salary_grades = [];
while ($row = mysqli_fetch_assoc($salary_grades_result)) {
    $salary_grades[] = $row;
}

// Get allowances
$allowances_query = "SELECT * FROM salary_allowances WHERE is_active = 1 ORDER BY allowance_name";
$allowances_result = mysqli_query($conn, $allowances_query);
$allowances = [];
while ($row = mysqli_fetch_assoc($allowances_result)) {
    $allowances[] = $row;
}

// Get deductions
$deductions_query = "SELECT * FROM salary_deductions WHERE is_active = 1 ORDER BY deduction_name";
$deductions_result = mysqli_query($conn, $deductions_query);
$deductions = [];
while ($row = mysqli_fetch_assoc($deductions_result)) {
    $deductions[] = $row;
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="bi bi-currency-rupee text-primary"></i> Salary Structure Management</h1>
                <p class="text-muted">Manage salary grades, allowances, and deductions for your organization</p>
            </div>
            <div>
                <button class="btn btn-outline-success" onclick="exportSalaryData()">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    <i class="bi bi-plus-lg"></i> Add Grade
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-ladder fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #1976d2;"><?= $total_grades ?></h3>
                        <p class="text-muted mb-0">Salary Grades</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-people fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #388e3c;"><?= $total_employees ?></h3>
                        <p class="text-muted mb-0">Total Employees</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-plus-circle fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #f57c00;"><?= $total_allowances ?></h3>
                        <p class="text-muted mb-0">Allowances</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-dash-circle fs-1" style="color: #d32f2f;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #d32f2f;"><?= $total_deductions ?></h3>
                        <p class="text-muted mb-0">Deductions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#grades" role="tab">
                            <i class="bi bi-ladder me-2"></i>Salary Grades
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#allowances" role="tab">
                            <i class="bi bi-plus-circle me-2"></i>Allowances
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#deductions" role="tab">
                            <i class="bi bi-dash-circle me-2"></i>Deductions
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calculator" role="tab">
                            <i class="bi bi-calculator me-2"></i>Salary Calculator
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Salary Grades Tab -->
                    <div class="tab-pane fade show active" id="grades" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Salary Grades</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="bi bi-plus-lg"></i> Add Grade
                            </button>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($salary_grades as $grade): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-primary me-2"><?= htmlspecialchars($grade['grade_name']) ?></span>
                                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($grade['grade_level']) ?></h6>
                                                </div>
                                                <div class="text-muted small mb-2">
                                                    <i class="bi bi-people me-1"></i>
                                                    <span><?= $grade['employee_count'] ?> employees</span>
                                                </div>
                                                <div class="text-success fw-bold">
                                                    ₹<?= number_format($grade['min_salary']) ?> - ₹<?= number_format($grade['max_salary']) ?>
                                                </div>
                                                <?php if ($grade['grade_description']): ?>
                                                <p class="text-muted small mt-2 mb-0"><?= htmlspecialchars($grade['grade_description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm flex-fill" onclick="editGrade(<?= $grade['id'] ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteGrade(<?= $grade['id'] ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Allowances Tab -->
                    <div class="tab-pane fade" id="allowances" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Salary Allowances</h5>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAllowanceModal">
                                <i class="bi bi-plus-lg"></i> Add Allowance
                            </button>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($allowances as $allowance): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100 border-start border-success border-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($allowance['allowance_name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($allowance['applicable_to']) ?></small>
                                                <div class="text-success fw-bold mt-2">
                                                    <?php if ($allowance['allowance_type'] === 'percentage'): ?>
                                                        <?= $allowance['allowance_value'] ?>% of Basic Salary
                                                    <?php elseif ($allowance['allowance_type'] === 'fixed'): ?>
                                                        ₹<?= number_format($allowance['allowance_value']) ?> per month
                                                    <?php else: ?>
                                                        Variable Amount
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-<?= $allowance['is_taxable'] ? 'warning' : 'info' ?> small">
                                                        <?= $allowance['is_taxable'] ? 'Taxable' : 'Non-Taxable' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAllowance(<?= $allowance['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAllowance(<?= $allowance['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Deductions Tab -->
                    <div class="tab-pane fade" id="deductions" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Salary Deductions</h5>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addDeductionModal">
                                <i class="bi bi-plus-lg"></i> Add Deduction
                            </button>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($deductions as $deduction): ?>
                            <div class="col-lg-6 col-md-6">
                                <div class="card border-0 shadow-sm h-100 border-start border-danger border-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($deduction['deduction_name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($deduction['applicable_to']) ?></small>
                                                <div class="text-danger fw-bold mt-2">
                                                    <?php if ($deduction['deduction_type'] === 'percentage'): ?>
                                                        <?= $deduction['deduction_value'] ?>% of Basic Salary
                                                    <?php elseif ($deduction['deduction_type'] === 'fixed'): ?>
                                                        ₹<?= number_format($deduction['deduction_value']) ?> per month
                                                    <?php else: ?>
                                                        Variable Amount
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge bg-<?= $deduction['is_mandatory'] ? 'danger' : 'secondary' ?> small">
                                                        <?= $deduction['is_mandatory'] ? 'Mandatory' : 'Optional' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editDeduction(<?= $deduction['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDeduction(<?= $deduction['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Salary Calculator Tab -->
                    <div class="tab-pane fade" id="calculator" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Salary Calculator</h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="salaryCalculatorForm">
                                            <div class="mb-3">
                                                <label class="form-label">Salary Grade</label>
                                                <select class="form-select" id="gradeSelect" onchange="updateSalaryRange()">
                                                    <option value="">Select Grade</option>
                                                    <?php foreach ($salary_grades as $grade): ?>
                                                    <option value="<?= $grade['id'] ?>" data-min="<?= $grade['min_salary'] ?>" data-max="<?= $grade['max_salary'] ?>">
                                                        <?= $grade['grade_name'] ?> - <?= $grade['grade_level'] ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Basic Salary (₹)</label>
                                                <input type="number" class="form-control" id="basicSalary" placeholder="Enter basic salary" min="0">
                                                <div class="form-text" id="salaryRange"></div>
                                            </div>
                                            <button type="button" class="btn btn-primary w-100" onclick="calculateSalary()">
                                                <i class="bi bi-calculator"></i> Calculate Total Salary
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Salary Breakdown</h6>
                                    </div>
                                    <div class="card-body" id="salaryBreakdown">
                                        <div class="text-center text-muted py-5">
                                            <i class="bi bi-calculator text-muted" style="font-size: 3rem;"></i>
                                            <p class="mt-3">Enter basic salary to see breakdown</p>
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
</div>

<!-- Add Grade Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Salary Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeForm" class="ajax-form">
                    <input type="hidden" name="action" value="add_grade">
                    <input type="hidden" name="id" id="grade_id">
                    <div class="mb-3">
                        <label class="form-label">Grade Name *</label>
                        <input type="text" class="form-control" name="grade_name" required maxlength="50" 
                               pattern="[A-Za-z0-9\s\-_]+" title="Only letters, numbers, spaces, hyphens, and underscores allowed">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade Level *</label>
                        <input type="text" class="form-control" name="grade_level" required maxlength="100">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Min Salary (₹) *</label>
                                <input type="number" class="form-control" name="min_salary" min="1" max="999999999" 
                                       step="0.01" required onblur="validateSalaryRange(this)">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Max Salary (₹) *</label>
                                <input type="number" class="form-control" name="max_salary" min="1" max="999999999" 
                                       step="0.01" required onblur="validateSalaryRange(this)">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="1000" 
                                  placeholder="Optional description for this grade"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="gradeForm" class="btn btn-primary">Save Grade</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Allowance Modal -->
<div class="modal fade" id="addAllowanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Allowance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="allowanceForm" class="ajax-form">
                    <input type="hidden" name="action" value="add_allowance">
                    <input type="hidden" name="id" id="allowance_id">
                    <div class="mb-3">
                        <label class="form-label">Allowance Name *</label>
                        <input type="text" class="form-control" name="allowance_name" required maxlength="100" 
                               pattern="[A-Za-z0-9\s\-_\.]+" title="Only letters, numbers, spaces, hyphens, underscores and periods allowed">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Type *</label>
                                <select class="form-select" name="allowance_type" required onchange="updateAllowanceValueLabel(this)">
                                    <option value="fixed">Fixed Amount (₹)</option>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="variable">Variable</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label" id="allowanceValueLabel">Value *</label>
                                <input type="number" class="form-control" name="allowance_value" step="0.01" min="0" 
                                       max="999999" required placeholder="Enter value">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Applicable To</label>
                        <input type="text" class="form-control" name="applicable_to" value="All Employees" 
                               maxlength="200" placeholder="e.g., All Employees, Grade A, Management">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_taxable" id="is_taxable_add" checked>
                            <label class="form-check-label" for="is_taxable_add">
                                Taxable (Subject to income tax)
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="1000" 
                                  placeholder="Optional description for this allowance"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="allowanceForm" class="btn btn-success">Save Allowance</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Deduction Modal -->
<div class="modal fade" id="addDeductionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Deduction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="deductionForm" class="ajax-form">
                    <input type="hidden" name="action" value="add_deduction">
                    <input type="hidden" name="id" id="deduction_id">
                    <div class="mb-3">
                        <label class="form-label">Deduction Name *</label>
                        <input type="text" class="form-control" name="deduction_name" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Type *</label>
                                <select class="form-select" name="deduction_type" required>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="variable">Variable</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Value *</label>
                                <input type="number" class="form-control" name="deduction_value" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Applicable To</label>
                        <input type="text" class="form-control" name="applicable_to" value="All Employees">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_mandatory" id="is_mandatory" checked>
                            <label class="form-check-label" for="is_mandatory">Mandatory</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deductionForm" class="btn btn-danger">Save Deduction</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Handle form submissions
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('input[type="submit"]');
        
        // Disable submit button to prevent double submission
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
        }
        
        $.post(window.location.href, formData)
            .done(function(response) {
                try {
                    // Handle both JSON and string responses
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response.success) {
                        showAlert(response.message, 'success');
                        // Close modal if it exists
                        const modal = $(form).closest('.modal');
                        if (modal.length) {
                            bootstrap.Modal.getInstance(modal[0])?.hide();
                        }
                        // Reload page after short delay to show success message
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'danger');
                    }
                } catch (e) {
                    console.error('Response parsing error:', e, response);
                    showAlert('Invalid server response', 'danger');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                showAlert('Network error: ' + error, 'danger');
            })
            .always(function() {
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
    });
});

function updateAllowanceValueLabel(selectElement) {
    const valueLabel = document.getElementById('allowanceValueLabel');
    const valueInput = selectElement.closest('.row').querySelector('input[name="allowance_value"]');
    
    switch (selectElement.value) {
        case 'fixed':
            valueLabel.textContent = 'Amount (₹) *';
            valueInput.placeholder = 'Enter amount in rupees';
            valueInput.max = '999999';
            break;
        case 'percentage':
            valueLabel.textContent = 'Percentage (%) *';
            valueInput.placeholder = 'Enter percentage (0-100)';
            valueInput.max = '100';
            break;
        case 'variable':
            valueLabel.textContent = 'Default Value *';
            valueInput.placeholder = 'Enter default value';
            valueInput.max = '999999';
            break;
    }
}

function validateSalaryRange(input) {
    const form = input.closest('form');
    const minSalaryInput = form.querySelector('input[name="min_salary"]');
    const maxSalaryInput = form.querySelector('input[name="max_salary"]');
    
    if (minSalaryInput.value && maxSalaryInput.value) {
        const minSalary = parseFloat(minSalaryInput.value);
        const maxSalary = parseFloat(maxSalaryInput.value);
        
        if (minSalary >= maxSalary) {
            input.setCustomValidity('Maximum salary must be greater than minimum salary');
            input.classList.add('is-invalid');
        } else {
            minSalaryInput.setCustomValidity('');
            maxSalaryInput.setCustomValidity('');
            minSalaryInput.classList.remove('is-invalid');
            maxSalaryInput.classList.remove('is-invalid');
        }
    }
}

function updateSalaryRange() {
    const gradeSelect = document.getElementById('gradeSelect');
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const salaryRange = document.getElementById('salaryRange');
    
    if (selectedOption.value) {
        const minSalary = parseFloat(selectedOption.dataset.min);
        const maxSalary = parseFloat(selectedOption.dataset.max);
        salaryRange.textContent = `Salary range: ₹${minSalary.toLocaleString()} - ₹${maxSalary.toLocaleString()}`;
        
        // Set basic salary to minimum of range
        document.getElementById('basicSalary').value = minSalary;
        document.getElementById('basicSalary').min = minSalary;
        document.getElementById('basicSalary').max = maxSalary;
    } else {
        salaryRange.textContent = '';
        document.getElementById('basicSalary').value = '';
        document.getElementById('basicSalary').removeAttribute('min');
        document.getElementById('basicSalary').removeAttribute('max');
    }
}

function calculateSalary() {
    const basicSalaryInput = document.getElementById('basicSalary');
    const basicSalary = parseFloat(basicSalaryInput.value);
    
    if (!basicSalary || basicSalary <= 0) {
        showAlert('Please enter a valid basic salary amount', 'warning');
        basicSalaryInput.focus();
        return;
    }
    
    // Check if salary is within selected grade range
    const gradeSelect = document.getElementById('gradeSelect');
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    
    if (selectedOption.value) {
        const minSalary = parseFloat(selectedOption.dataset.min);
        const maxSalary = parseFloat(selectedOption.dataset.max);
        
        if (basicSalary < minSalary || basicSalary > maxSalary) {
            showAlert(`Salary should be between ₹${minSalary.toLocaleString()} and ₹${maxSalary.toLocaleString()} for this grade`, 'warning');
            return;
        }
    }

    try {
        // Get allowances and deductions from database
        const allowances = <?= json_encode($allowances) ?>;
        const deductions = <?= json_encode($deductions) ?>;
        
        let totalAllowances = 0;
        let allowanceBreakdown = '';
        
        allowances.forEach(allowance => {
            let amount = 0;
            if (allowance.allowance_type === 'percentage') {
                amount = (basicSalary * parseFloat(allowance.allowance_value)) / 100;
            } else if (allowance.allowance_type === 'fixed') {
                amount = parseFloat(allowance.allowance_value);
            }
            totalAllowances += amount;
            allowanceBreakdown += `
                <div class="row">
                    <div class="col-8">${allowance.allowance_name}:</div>
                    <div class="col-4 text-end">₹${amount.toLocaleString()}</div>
                </div>`;
        });
        
        let totalDeductions = 0;
        let deductionBreakdown = '';
        
        deductions.forEach(deduction => {
            let amount = 0;
            if (deduction.deduction_type === 'percentage') {
                amount = (basicSalary * parseFloat(deduction.deduction_value)) / 100;
            } else if (deduction.deduction_type === 'fixed') {
                amount = parseFloat(deduction.deduction_value);
            }
            totalDeductions += amount;
            deductionBreakdown += `
                <div class="row">
                    <div class="col-8">${deduction.deduction_name}:</div>
                    <div class="col-4 text-end">₹${amount.toLocaleString()}</div>
                </div>`;
        });
        
        const grossSalary = basicSalary + totalAllowances;
        const netSalary = grossSalary - totalDeductions;

        const breakdown = `
            <div class="salary-summary">
                <div class="row mb-3">
                    <div class="col-8"><strong>Basic Salary:</strong></div>
                    <div class="col-4 text-end"><strong>₹${basicSalary.toLocaleString()}</strong></div>
                </div>
                <hr>
                <h6 class="text-success">Allowances:</h6>
                ${allowanceBreakdown || '<div class="text-muted text-center py-2">No allowances configured</div>'}
                <div class="row mb-3 border-top pt-2">
                    <div class="col-8"><strong>Total Allowances:</strong></div>
                    <div class="col-4 text-end"><strong class="text-success">₹${totalAllowances.toLocaleString()}</strong></div>
                </div>
                <hr>
                <h6 class="text-danger">Deductions:</h6>
                ${deductionBreakdown || '<div class="text-muted text-center py-2">No deductions configured</div>'}
                <div class="row mb-3 border-top pt-2">
                    <div class="col-8"><strong>Total Deductions:</strong></div>
                    <div class="col-4 text-end"><strong class="text-danger">₹${totalDeductions.toLocaleString()}</strong></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-8"><strong>Gross Salary:</strong></div>
                    <div class="col-4 text-end"><strong>₹${grossSalary.toLocaleString()}</strong></div>
                </div>
                <div class="row">
                    <div class="col-8"><strong class="fs-5">Net Salary:</strong></div>
                    <div class="col-4 text-end"><strong class="fs-5 text-primary">₹${netSalary.toLocaleString()}</strong></div>
                </div>
            </div>
        `;

        document.getElementById('salaryBreakdown').innerHTML = breakdown;
    } catch (error) {
        console.error('Calculation error:', error);
        showAlert('Error calculating salary: ' + error.message, 'danger');
    }
}

function editGrade(id) {
    if (!id || isNaN(id)) {
        showAlert('Invalid grade ID', 'danger');
        return;
    }
    
    $.post(window.location.href, {action: 'get_grade', id: id})
        .done(function(response) {
            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success && response.data) {
                    const data = response.data;
                    const modal = document.getElementById('addGradeModal');
                    
                    // Update modal title and form action
                    modal.querySelector('.modal-title').textContent = 'Edit Salary Grade';
                    modal.querySelector('input[name="action"]').value = 'edit_grade';
                    modal.querySelector('input[name="id"]').value = data.id;
                    
                    // Populate form fields
                    modal.querySelector('input[name="grade_name"]').value = data.grade_name || '';
                    modal.querySelector('input[name="grade_level"]').value = data.grade_level || '';
                    modal.querySelector('input[name="min_salary"]').value = data.min_salary || '';
                    modal.querySelector('input[name="max_salary"]').value = data.max_salary || '';
                    modal.querySelector('textarea[name="description"]').value = data.grade_description || '';
                    
                    // Show modal
                    new bootstrap.Modal(modal).show();
                } else {
                    showAlert(response.message || 'Failed to load grade data', 'danger');
                }
            } catch (e) {
                console.error('Response parsing error:', e, response);
                showAlert('Invalid server response', 'danger');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            showAlert('Network error: ' + error, 'danger');
        });
}

function deleteGrade(id) {
    if (!id || isNaN(id)) {
        showAlert('Invalid grade ID', 'danger');
        return;
    }
    
    if (confirm('Are you sure you want to delete this salary grade? This action cannot be undone.')) {
        $.post(window.location.href, {action: 'delete_grade', id: id})
            .done(function(response) {
                try {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'danger');
                    }
                } catch (e) {
                    console.error('Response parsing error:', e, response);
                    showAlert('Invalid server response', 'danger');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                showAlert('Network error: ' + error, 'danger');
            });
    }
}

function editAllowance(id) {
    $.post(window.location.href, {action: 'get_allowance', id: id})
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                document.querySelector('#addAllowanceModal .modal-title').textContent = 'Edit Allowance';
                document.querySelector('#allowanceForm input[name="action"]').value = 'edit_allowance';
                document.querySelector('#allowanceForm input[name="id"]').value = data.id;
                document.querySelector('#allowanceForm input[name="allowance_name"]').value = data.allowance_name;
                document.querySelector('#allowanceForm select[name="allowance_type"]').value = data.allowance_type;
                document.querySelector('#allowanceForm input[name="allowance_value"]').value = data.allowance_value;
                document.querySelector('#allowanceForm input[name="applicable_to"]').value = data.applicable_to;
                document.querySelector('#allowanceForm input[name="is_taxable"]').checked = data.is_taxable == 1;
                document.querySelector('#allowanceForm textarea[name="description"]').value = data.description || '';
                
                new bootstrap.Modal(document.getElementById('addAllowanceModal')).show();
            } else {
                showAlert(response.message, 'danger');
            }
        });
}

function deleteAllowance(id) {
    if (confirm('Are you sure you want to delete this allowance?')) {
        $.post(window.location.href, {action: 'delete_allowance', id: id})
            .done(function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    location.reload();
                } else {
                    showAlert(response.message, 'danger');
                }
            });
    }
}

function editDeduction(id) {
    $.post(window.location.href, {action: 'get_deduction', id: id})
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                document.querySelector('#addDeductionModal .modal-title').textContent = 'Edit Deduction';
                document.querySelector('#deductionForm input[name="action"]').value = 'edit_deduction';
                document.querySelector('#deductionForm input[name="id"]').value = data.id;
                document.querySelector('#deductionForm input[name="deduction_name"]').value = data.deduction_name;
                document.querySelector('#deductionForm select[name="deduction_type"]').value = data.deduction_type;
                document.querySelector('#deductionForm input[name="deduction_value"]').value = data.deduction_value;
                document.querySelector('#deductionForm input[name="applicable_to"]').value = data.applicable_to;
                document.querySelector('#deductionForm input[name="is_mandatory"]').checked = data.is_mandatory == 1;
                document.querySelector('#deductionForm textarea[name="description"]').value = data.description || '';
                
                new bootstrap.Modal(document.getElementById('addDeductionModal')).show();
            } else {
                showAlert(response.message, 'danger');
            }
        });
}

function deleteDeduction(id) {
    if (confirm('Are you sure you want to delete this deduction?')) {
        $.post(window.location.href, {action: 'delete_deduction', id: id})
            .done(function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    location.reload();
                } else {
                    showAlert(response.message, 'danger');
                }
            });
    }
}

function exportSalaryData() {
    try {
        // Create a temporary form for POST request
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = window.location.href;
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'export_salary_data';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        showAlert('Export started, your download will begin shortly', 'info');
    } catch (error) {
        console.error('Export error:', error);
        showAlert('Export failed: ' + error.message, 'danger');
    }
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

// Reset modal forms when hidden
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        if (form) {
            form.reset();
            // Reset action to add mode
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) {
                actionInput.value = actionInput.value.replace('edit_', 'add_');
            }
            // Reset modal title
            const modalTitle = this.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = modalTitle.textContent.replace('Edit', 'Add');
            }
        }
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
