<?php
session_start();
require_once '../db.php';
require_once '../auth_check.php';

// Page title for global header
$page_title = "Benefits Management";

// Set compatibility variables for HRMS modules
if (isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user'];
}
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'employee';
}
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = $_SESSION['user_id'] ?? 1;
}

// Create benefits tables if not exist
$createBenefitsTable = "CREATE TABLE IF NOT EXISTS hr_benefits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    benefit_name VARCHAR(100) NOT NULL,
    benefit_type ENUM('health', 'dental', 'vision', 'retirement', 'life_insurance', 'disability', 'wellness', 'transportation', 'other') DEFAULT 'other',
    description TEXT,
    provider VARCHAR(100),
    cost_employee DECIMAL(10,2) DEFAULT 0,
    cost_employer DECIMAL(10,2) DEFAULT 0,
    eligibility_criteria TEXT,
    coverage_details TEXT,
    enrollment_period_start DATE,
    enrollment_period_end DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB";
mysqli_query($conn, $createBenefitsTable);

$createEmployeeBenefitsTable = "CREATE TABLE IF NOT EXISTS hr_employee_benefits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    benefit_id INT(11) NOT NULL,
    enrollment_date DATE NOT NULL,
    coverage_start_date DATE NOT NULL,
    coverage_end_date DATE,
    premium_amount DECIMAL(10,2) DEFAULT 0,
    deduction_amount DECIMAL(10,2) DEFAULT 0,
    beneficiary_name VARCHAR(100),
    beneficiary_relationship VARCHAR(50),
    beneficiary_contact VARCHAR(100),
    status ENUM('enrolled', 'pending', 'declined', 'terminated', 'suspended') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (benefit_id) REFERENCES hr_benefits(id) ON DELETE CASCADE
) ENGINE=InnoDB";
mysqli_query($conn, $createEmployeeBenefitsTable);

// Insert sample benefits if table is empty
$checkBenefits = mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_benefits");
$benefitCount = mysqli_fetch_assoc($checkBenefits)['count'];

if ($benefitCount == 0) {
    $sampleBenefits = [
        ['Health Insurance - Family', 'health', 'Comprehensive family health coverage with major medical', 'HealthCorp Insurance', 250.00, 750.00, 'All full-time employees after 90 days', 'Family coverage including spouse and children', '2025-01-01', '2025-01-31'],
        ['Health Insurance - Individual', 'health', 'Individual health coverage with major medical', 'HealthCorp Insurance', 150.00, 450.00, 'All full-time employees after 90 days', 'Individual coverage only', '2025-01-01', '2025-01-31'],
        ['Dental Insurance', 'dental', 'Comprehensive dental care including preventive and restorative', 'SmileCare Dental', 25.00, 75.00, 'All employees', 'Covers cleanings, fillings, major dental work', '2025-01-01', '2025-01-31'],
        ['Vision Insurance', 'vision', 'Eye care coverage including exams and glasses', 'VisionPlus', 15.00, 35.00, 'All employees', 'Annual eye exams, glasses, contact lenses', '2025-01-01', '2025-01-31'],
        ['Life Insurance', 'life_insurance', 'Term life insurance coverage', 'SecureLife Insurance', 0.00, 50.00, 'All full-time employees', '2x annual salary coverage', '2025-01-01', '2025-12-31'],
        ['Retirement Plan (401k)', 'retirement', 'Company-sponsored retirement savings plan', 'FutureSecure Investments', 0.00, 0.00, 'All employees after 1 year', 'Company matches up to 6% of salary', '2025-01-01', '2025-12-31'],
        ['Transportation Allowance', 'transportation', 'Monthly transportation reimbursement', 'Company Direct', 0.00, 100.00, 'All employees', 'Public transport or parking reimbursement', '2025-01-01', '2025-12-31'],
        ['Wellness Program', 'wellness', 'Gym membership and wellness activities', 'FitLife Wellness', 20.00, 80.00, 'All employees', 'Gym access, fitness classes, health screenings', '2025-01-01', '2025-12-31']
    ];
    
    foreach ($sampleBenefits as $benefit) {
        $stmt = $conn->prepare("INSERT INTO hr_benefits (benefit_name, benefit_type, description, provider, cost_employee, cost_employer, eligibility_criteria, coverage_details, enrollment_period_start, enrollment_period_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssddssss", ...$benefit);
        $stmt->execute();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_benefits':
            $result = mysqli_query($conn, "SELECT * FROM hr_benefits WHERE status = 'active' ORDER BY benefit_type, benefit_name");
            $benefits = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $benefits[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $benefits]);
            exit;
            
        case 'get_employee_benefits':
            $employee_id = $_SESSION['employee_id'] ?? 1; // Fallback for testing
            $result = mysqli_query($conn, "
                SELECT eb.*, b.benefit_name, b.benefit_type, b.provider, b.description 
                FROM hr_employee_benefits eb 
                JOIN hr_benefits b ON eb.benefit_id = b.id 
                WHERE eb.employee_id = $employee_id 
                ORDER BY eb.created_at DESC
            ");
            $enrollments = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $enrollments[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $enrollments]);
            exit;
            
        case 'enroll_benefit':
            $employee_id = $_SESSION['employee_id'] ?? 1;
            $benefit_id = intval($_POST['benefit_id']);
            $coverage_start_date = mysqli_real_escape_string($conn, $_POST['coverage_start_date']);
            $beneficiary_name = mysqli_real_escape_string($conn, $_POST['beneficiary_name'] ?? '');
            $beneficiary_relationship = mysqli_real_escape_string($conn, $_POST['beneficiary_relationship'] ?? '');
            $beneficiary_contact = mysqli_real_escape_string($conn, $_POST['beneficiary_contact'] ?? '');
            
            // Get benefit cost
            $benefit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM hr_benefits WHERE id = $benefit_id"));
            
            if ($benefit) {
                $query = "INSERT INTO hr_employee_benefits 
                         (employee_id, benefit_id, enrollment_date, coverage_start_date, premium_amount, deduction_amount, beneficiary_name, beneficiary_relationship, beneficiary_contact, status) 
                         VALUES ($employee_id, $benefit_id, CURDATE(), '$coverage_start_date', {$benefit['cost_employee']}, {$benefit['cost_employee']}, '$beneficiary_name', '$beneficiary_relationship', '$beneficiary_contact', 'enrolled')";
                
                if (mysqli_query($conn, $query)) {
                    echo json_encode(['success' => true, 'message' => 'Successfully enrolled in benefit']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Enrollment failed: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Benefit not found']);
            }
            exit;
            
        case 'cancel_benefit':
            $enrollment_id = intval($_POST['enrollment_id']);
            $employee_id = $_SESSION['employee_id'] ?? 1;
            
            $query = "UPDATE hr_employee_benefits 
                     SET status = 'terminated', coverage_end_date = CURDATE(), updated_at = NOW() 
                     WHERE id = $enrollment_id AND employee_id = $employee_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Benefit enrollment cancelled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cancellation failed']);
            }
            exit;
            
        case 'update_beneficiary':
            $enrollment_id = intval($_POST['enrollment_id']);
            $employee_id = $_SESSION['employee_id'] ?? 1;
            $beneficiary_name = mysqli_real_escape_string($conn, $_POST['beneficiary_name']);
            $beneficiary_relationship = mysqli_real_escape_string($conn, $_POST['beneficiary_relationship']);
            $beneficiary_contact = mysqli_real_escape_string($conn, $_POST['beneficiary_contact']);
            
            $query = "UPDATE hr_employee_benefits 
                     SET beneficiary_name = '$beneficiary_name', beneficiary_relationship = '$beneficiary_relationship', 
                         beneficiary_contact = '$beneficiary_contact', updated_at = NOW()
                     WHERE id = $enrollment_id AND employee_id = $employee_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Beneficiary information updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed']);
            }
            exit;
            
        case 'save_benefit':
            $id = $_POST['id'] ?? null;
            $benefit_name = mysqli_real_escape_string($conn, $_POST['benefit_name']);
            $benefit_type = $_POST['benefit_type'];
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $provider = mysqli_real_escape_string($conn, $_POST['provider']);
            $cost_employee = floatval($_POST['cost_employee']);
            $cost_employer = floatval($_POST['cost_employer']);
            $eligibility_criteria = mysqli_real_escape_string($conn, $_POST['eligibility_criteria']);
            $coverage_details = mysqli_real_escape_string($conn, $_POST['coverage_details']);
            $enrollment_period_start = $_POST['enrollment_period_start'];
            $enrollment_period_end = $_POST['enrollment_period_end'];
            $status = $_POST['status'];
            
            if ($id) {
                $query = "UPDATE hr_benefits SET 
                         benefit_name = '$benefit_name',
                         benefit_type = '$benefit_type',
                         description = '$description',
                         provider = '$provider',
                         cost_employee = $cost_employee,
                         cost_employer = $cost_employer,
                         eligibility_criteria = '$eligibility_criteria',
                         coverage_details = '$coverage_details',
                         enrollment_period_start = '$enrollment_period_start',
                         enrollment_period_end = '$enrollment_period_end',
                         status = '$status'
                         WHERE id = $id";
            } else {
                $query = "INSERT INTO hr_benefits (benefit_name, benefit_type, description, provider, cost_employee, cost_employer, eligibility_criteria, coverage_details, enrollment_period_start, enrollment_period_end, status) 
                         VALUES ('$benefit_name', '$benefit_type', '$description', '$provider', $cost_employee, $cost_employer, '$eligibility_criteria', '$coverage_details', '$enrollment_period_start', '$enrollment_period_end', '$status')";
            }
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Benefit saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving benefit']);
            }
            exit;
            
        case 'delete_benefit':
            $id = intval($_POST['id']);
            if (mysqli_query($conn, "DELETE FROM hr_benefits WHERE id = $id")) {
                echo json_encode(['success' => true, 'message' => 'Benefit deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting benefit']);
            }
            exit;
    }
}

//Get statistics
$benefits_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_benefits WHERE status = 'active'"))['count'];
$enrolled_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employee_benefits WHERE status = 'enrolled'"))['count'];
$employee_id = $_SESSION['employee_id'] ?? 1;
$enrollments_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM hr_employee_benefits WHERE employee_id = $employee_id AND status = 'enrolled'"))['count'];
$monthly_premium = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(premium_amount) as total FROM hr_employee_benefits WHERE employee_id = $employee_id AND status = 'enrolled'"))['total'] ?? 0;
$enrollment_period = "Open"; // Simplified

function getBenefitIcon($type) {
    $icons = [
        'health' => 'bi-heart-pulse',
        'dental' => 'bi-emoji-smile',
        'vision' => 'bi-eye',
        'retirement' => 'bi-piggy-bank',
        'life_insurance' => 'bi-shield-check',
        'disability' => 'bi-person-check',
        'wellness' => 'bi-activity',
        'transportation' => 'bi-bus-front',
        'other' => 'bi-gift'
    ];
    return $icons[$type] ?? 'bi-gift';
}

function getBenefitColor($type) {
    $colors = [
        'health' => 'danger',
        'dental' => 'info',
        'vision' => 'success',
        'retirement' => 'primary',
        'life_insurance' => 'secondary',
        'disability' => 'warning',
        'wellness' => 'success',
        'transportation' => 'info',
        'other' => 'dark'
    ];
    return $colors[$type] ?? 'dark';
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold text-dark">🎁 Benefits Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active">HRMS</li>
                        <li class="breadcrumb-item active">Benefits Management</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="generateBenefitsReport()">
                    <i class="bi bi-file-earmark-text me-2"></i>Generate Reports
                </button>
                <?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'hr'): ?>
                <button class="btn btn-primary" onclick="showAddBenefitModal()">
                    <i class="bi bi-plus-circle me-2"></i>Add New Benefit
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-gift-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $benefits_count ?></h3>
                        <small class="opacity-75">Available Benefits</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $enrolled_count ?></h3>
                        <small class="opacity-75">Total Enrollments</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-check-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $enrollments_count ?></h3>
                        <small class="opacity-75">My Enrollments</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">₹<?= number_format($monthly_premium) ?></h3>
                        <small class="opacity-75">Monthly Premium</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="card shadow-sm">
        <div class="card-header bg-white border-0">
            <ul class="nav nav-tabs card-header-tabs" id="benefitsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="available-benefits-tab" data-bs-toggle="tab" data-bs-target="#available-benefits" type="button" role="tab">
                        <i class="bi bi-gift me-2"></i>Available Benefits
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-benefits-tab" data-bs-toggle="tab" data-bs-target="#my-benefits" type="button" role="tab">
                        <i class="bi bi-person-check me-2"></i>My Benefits
                    </button>
                </li>
                <?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'hr'): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-panel-tab" data-bs-toggle="tab" data-bs-target="#admin-panel" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>Admin Panel
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="benefitsTabsContent">
                <!-- Available Benefits Tab -->
                <div class="tab-pane fade show active" id="available-benefits" role="tabpanel">
                    <div class="row" id="availableBenefitsContainer">
                        <!-- Available benefits will be loaded here -->
                    </div>
                </div>

                <!-- My Benefits Tab -->
                <div class="tab-pane fade" id="my-benefits" role="tabpanel">
                    <div class="row" id="myBenefitsContainer">
                        <!-- My enrolled benefits will be loaded here -->
                    </div>
                </div>

                <!-- Admin Panel Tab -->
                <?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'hr'): ?>
                <div class="tab-pane fade" id="admin-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Manage Benefits</h5>
                        <button class="btn btn-success" onclick="showAddBenefitModal()">
                            <i class="bi bi-plus-circle me-2"></i>Add New Benefit
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="benefitsAdminTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Benefit Name</th>
                                    <th>Type</th>
                                    <th>Provider</th>
                                    <th>Employee Cost</th>
                                    <th>Employer Cost</th>
                                    <th>Enrollment Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Admin benefits data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Benefit Details Modal -->
<div class="modal fade" id="benefitDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Benefit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="benefitDetailsContent">
                    <!-- Benefit details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="enrollBenefitBtn" onclick="showEnrollmentForm()">Enroll Now</button>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Form Modal -->
<div class="modal fade" id="enrollmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enroll in Benefit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="enrollmentForm">
                    <input type="hidden" id="enrollBenefitId">
                    <div class="mb-3">
                        <label class="form-label">Coverage Start Date *</label>
                        <input type="date" class="form-control" id="coverageStartDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beneficiary Name</label>
                        <input type="text" class="form-control" id="beneficiaryName" placeholder="Primary beneficiary name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Relationship</label>
                        <select class="form-select" id="beneficiaryRelationship">
                            <option value="">Select relationship</option>
                            <option value="spouse">Spouse</option>
                            <option value="child">Child</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beneficiary Contact</label>
                        <input type="text" class="form-control" id="beneficiaryContact" placeholder="Phone or email">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="enrollInBenefit()">Confirm Enrollment</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Benefit Modal (Admin) -->
<div class="modal fade" id="benefitFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Benefit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="benefitForm">
                    <input type="hidden" id="benefitId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Benefit Name *</label>
                            <input type="text" class="form-control" id="benefitName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Benefit Type *</label>
                            <select class="form-select" id="benefitType" required>
                                <option value="">Select Type</option>
                                <option value="health">Health Insurance</option>
                                <option value="dental">Dental Insurance</option>
                                <option value="vision">Vision Insurance</option>
                                <option value="retirement">Retirement Plan</option>
                                <option value="life_insurance">Life Insurance</option>
                                <option value="disability">Disability Insurance</option>
                                <option value="wellness">Wellness Program</option>
                                <option value="transportation">Transportation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" id="benefitDescription" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provider</label>
                            <input type="text" class="form-control" id="benefitProvider">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Employee Cost (₹)</label>
                            <input type="number" class="form-control" id="costEmployee" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Employer Cost (₹)</label>
                            <input type="number" class="form-control" id="costEmployer" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Eligibility Criteria</label>
                            <textarea class="form-control" id="eligibilityCriteria" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Coverage Details</label>
                            <textarea class="form-control" id="coverageDetails" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enrollment Start</label>
                            <input type="date" class="form-control" id="enrollmentStart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enrollment End</label>
                            <input type="date" class="form-control" id="enrollmentEnd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="benefitStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveBenefit()">Save Benefit</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Beneficiary Modal -->
<div class="modal fade" id="updateBeneficiaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Beneficiary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateBeneficiaryForm">
                    <input type="hidden" id="updateEnrollmentId">
                    <div class="mb-3">
                        <label class="form-label">Beneficiary Name</label>
                        <input type="text" class="form-control" id="updateBeneficiaryName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Relationship</label>
                        <select class="form-select" id="updateBeneficiaryRelationship">
                            <option value="">Select relationship</option>
                            <option value="spouse">Spouse</option>
                            <option value="child">Child</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Information</label>
                        <input type="text" class="form-control" id="updateBeneficiaryContact">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateBeneficiary()">Update</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableBenefits();
    loadMyBenefits();
    <?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'hr'): ?>
    loadAdminBenefits();
    <?php endif; ?>
    
    // Set minimum date to today for coverage start
    document.getElementById('coverageStartDate').min = new Date().toISOString().split('T')[0];
});

let currentBenefitId = null;

// Load available benefits
function loadAvailableBenefits() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_benefits'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAvailableBenefits(data.data);
            displayAdminBenefits(data.data);
        }
    });
}

function displayAvailableBenefits(benefits) {
    const container = document.getElementById('availableBenefitsContainer');
    container.innerHTML = '';
    
    benefits.forEach(benefit => {
        const icon = getBenefitIcon(benefit.benefit_type);
        const color = getBenefitColor(benefit.benefit_type);
        
        const benefitCard = `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="rounded-circle p-2 me-3 bg-${color} bg-opacity-10">
                                <i class="bi ${icon} text-${color} fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">${benefit.benefit_name}</h6>
                                <span class="badge bg-${color} bg-opacity-20 text-${color} mb-2">${benefit.benefit_type.replace('_', ' ').toUpperCase()}</span>
                            </div>
                        </div>
                        
                        <p class="text-muted small mb-3">${benefit.description}</p>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <div class="small text-muted">Employee Cost</div>
                                    <div class="fw-bold">₹${parseFloat(benefit.cost_employee).toLocaleString()}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <div class="small text-muted">Company Pays</div>
                                    <div class="fw-bold text-success">₹${parseFloat(benefit.cost_employer).toLocaleString()}</div>
                                </div>
                            </div>
                        </div>
                        
                        ${benefit.provider ? `<div class="small mb-2"><i class="bi bi-building me-1"></i> Provider: ${benefit.provider}</div>` : ''}
                        
                        <div class="d-grid">
                            <button class="btn btn-outline-primary" onclick="showBenefitDetails(${benefit.id})">
                                <i class="bi bi-info-circle me-2"></i>View Details & Enroll
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', benefitCard);
    });
}

// Load my enrolled benefits
function loadMyBenefits() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_employee_benefits'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMyBenefits(data.data);
        }
    });
}

function displayMyBenefits(enrollments) {
    const container = document.getElementById('myBenefitsContainer');
    container.innerHTML = '';
    
    if (enrollments.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-gift text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3">No Enrollments Yet</h4>
                <p class="text-muted">Browse available benefits to get started</p>
                <button class="btn btn-primary" onclick="document.getElementById('available-benefits-tab').click()">
                    View Available Benefits
                </button>
            </div>
        `;
        return;
    }
    
    enrollments.forEach(enrollment => {
        const icon = getBenefitIcon(enrollment.benefit_type);
        const color = getBenefitColor(enrollment.benefit_type);
        const statusColor = enrollment.status === 'enrolled' ? 'success' : 
                           enrollment.status === 'pending' ? 'warning' : 
                           enrollment.status === 'terminated' ? 'danger' : 'secondary';
        
        const enrollmentCard = `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle p-2 me-3 bg-${color} bg-opacity-10">
                                    <i class="bi ${icon} text-${color} fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-1">${enrollment.benefit_name}</h6>
                                    <span class="badge bg-${statusColor}">${enrollment.status.toUpperCase()}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="small mb-2">
                            <i class="bi bi-calendar-start me-1"></i> Coverage: ${new Date(enrollment.coverage_start_date).toLocaleDateString()}
                            ${enrollment.coverage_end_date ? ` - ${new Date(enrollment.coverage_end_date).toLocaleDateString()}` : ' - Ongoing'}
                        </div>
                        
                        <div class="small mb-2">
                            <i class="bi bi-currency-rupee me-1"></i> Monthly Premium: ₹${parseFloat(enrollment.premium_amount).toLocaleString()}
                        </div>
                        
                        ${enrollment.beneficiary_name ? `
                        <div class="small mb-3">
                            <i class="bi bi-person me-1"></i> Beneficiary: ${enrollment.beneficiary_name}
                            ${enrollment.beneficiary_relationship ? ` (${enrollment.beneficiary_relationship})` : ''}
                        </div>
                        ` : ''}
                        
                        <div class="d-grid gap-2">
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-info btn-sm" onclick="updateBeneficiaryInfo(${enrollment.id}, '${enrollment.beneficiary_name || ''}', '${enrollment.beneficiary_relationship || ''}', '${enrollment.beneficiary_contact || ''}')">
                                    <i class="bi bi-pencil"></i> Update
                                </button>
                                ${enrollment.status === 'enrolled' ? `
                                <button class="btn btn-outline-danger btn-sm" onclick="cancelBenefit(${enrollment.id})">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', enrollmentCard);
    });
}

// Show benefit details
function showBenefitDetails(benefitId) {
    currentBenefitId = benefitId;
    
    // Get benefit data
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_benefits'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const benefit = data.data.find(b => b.id == benefitId);
            if (benefit) {
                const icon = getBenefitIcon(benefit.benefit_type);
                const color = getBenefitColor(benefit.benefit_type);
                
                document.getElementById('benefitDetailsContent').innerHTML = `
                    <div class="text-center mb-4">
                        <div class="rounded-circle p-3 mx-auto mb-3 bg-${color} bg-opacity-10" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi ${icon} text-${color}" style="font-size: 2rem;"></i>
                        </div>
                        <h4>${benefit.benefit_name}</h4>
                        <span class="badge bg-${color}">${benefit.benefit_type.replace('_', ' ').toUpperCase()}</span>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <h6>Cost Breakdown</h6>
                            <div class="bg-light p-3 rounded">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Your Cost:</span>
                                    <span class="fw-bold">₹${parseFloat(benefit.cost_employee).toLocaleString()}/month</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Company Pays:</span>
                                    <span class="fw-bold text-success">₹${parseFloat(benefit.cost_employer).toLocaleString()}/month</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Provider Information</h6>
                            <div class="bg-light p-3 rounded">
                                <div class="mb-2"><strong>Provider:</strong> ${benefit.provider || 'N/A'}</div>
                                <div><strong>Enrollment Period:</strong><br>
                                ${benefit.enrollment_period_start ? new Date(benefit.enrollment_period_start).toLocaleDateString() : 'N/A'} - 
                                ${benefit.enrollment_period_end ? new Date(benefit.enrollment_period_end).toLocaleDateString() : 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <h6>Description</h6>
                    <p class="mb-3">${benefit.description}</p>
                    
                    ${benefit.coverage_details ? `
                    <h6>Coverage Details</h6>
                    <p class="mb-3">${benefit.coverage_details}</p>
                    ` : ''}
                    
                    ${benefit.eligibility_criteria ? `
                    <h6>Eligibility</h6>
                    <p class="mb-0">${benefit.eligibility_criteria}</p>
                    ` : ''}
                `;
                
                document.getElementById('enrollBenefitBtn').onclick = () => showEnrollmentForm(benefitId);
                new bootstrap.Modal(document.getElementById('benefitDetailsModal')).show();
            }
        }
    });
}

function showEnrollmentForm(benefitId) {
    document.getElementById('enrollBenefitId').value = benefitId || currentBenefitId;
    bootstrap.Modal.getInstance(document.getElementById('benefitDetailsModal')).hide();
    new bootstrap.Modal(document.getElementById('enrollmentModal')).show();
}

function enrollInBenefit() {
    const formData = new FormData();
    formData.append('action', 'enroll_benefit');
    formData.append('benefit_id', document.getElementById('enrollBenefitId').value);
    formData.append('coverage_start_date', document.getElementById('coverageStartDate').value);
    formData.append('beneficiary_name', document.getElementById('beneficiaryName').value);
    formData.append('beneficiary_relationship', document.getElementById('beneficiaryRelationship').value);
    formData.append('beneficiary_contact', document.getElementById('beneficiaryContact').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('enrollmentModal')).hide();
            loadMyBenefits();
            // Refresh statistics
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

function cancelBenefit(enrollmentId) {
    if (confirm('Are you sure you want to cancel this benefit enrollment?')) {
        const formData = new FormData();
        formData.append('action', 'cancel_benefit');
        formData.append('enrollment_id', enrollmentId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadMyBenefits();
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.message, 'danger');
            }
        });
    }
}

function updateBeneficiaryInfo(enrollmentId, name, relationship, contact) {
    document.getElementById('updateEnrollmentId').value = enrollmentId;
    document.getElementById('updateBeneficiaryName').value = name;
    document.getElementById('updateBeneficiaryRelationship').value = relationship;
    document.getElementById('updateBeneficiaryContact').value = contact;
    
    new bootstrap.Modal(document.getElementById('updateBeneficiaryModal')).show();
}

function updateBeneficiary() {
    const formData = new FormData();
    formData.append('action', 'update_beneficiary');
    formData.append('enrollment_id', document.getElementById('updateEnrollmentId').value);
    formData.append('beneficiary_name', document.getElementById('updateBeneficiaryName').value);
    formData.append('beneficiary_relationship', document.getElementById('updateBeneficiaryRelationship').value);
    formData.append('beneficiary_contact', document.getElementById('updateBeneficiaryContact').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('updateBeneficiaryModal')).hide();
            loadMyBenefits();
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

// Admin functions
<?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'hr'): ?>
function loadAdminBenefits() {
    // This will be called from loadAvailableBenefits
}

function displayAdminBenefits(benefits) {
    const tbody = document.querySelector('#benefitsAdminTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    benefits.forEach(benefit => {
        const row = `
            <tr>
                <td>${benefit.benefit_name}</td>
                <td><span class="badge bg-${getBenefitColor(benefit.benefit_type)}">${benefit.benefit_type.replace('_', ' ').toUpperCase()}</span></td>
                <td>${benefit.provider || 'N/A'}</td>
                <td>₹${parseFloat(benefit.cost_employee).toLocaleString()}</td>
                <td>₹${parseFloat(benefit.cost_employer).toLocaleString()}</td>
                <td>${benefit.enrollment_period_start ? new Date(benefit.enrollment_period_start).toLocaleDateString() : 'N/A'} - 
                    ${benefit.enrollment_period_end ? new Date(benefit.enrollment_period_end).toLocaleDateString() : 'N/A'}</td>
                <td><span class="badge bg-${benefit.status === 'active' ? 'success' : 'secondary'}">${benefit.status.toUpperCase()}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editBenefit(${benefit.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteBenefit(${benefit.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
    });
}

function showAddBenefitModal() {
    document.getElementById('benefitForm').reset();
    document.getElementById('benefitId').value = '';
    document.querySelector('#benefitFormModal .modal-title').textContent = 'Add New Benefit';
    new bootstrap.Modal(document.getElementById('benefitFormModal')).show();
}

function editBenefit(id) {
    // Get benefit data and populate form
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_benefits'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const benefit = data.data.find(b => b.id == id);
            if (benefit) {
                document.getElementById('benefitId').value = benefit.id;
                document.getElementById('benefitName').value = benefit.benefit_name;
                document.getElementById('benefitType').value = benefit.benefit_type;
                document.getElementById('benefitDescription').value = benefit.description;
                document.getElementById('benefitProvider').value = benefit.provider || '';
                document.getElementById('costEmployee').value = benefit.cost_employee;
                document.getElementById('costEmployer').value = benefit.cost_employer;
                document.getElementById('eligibilityCriteria').value = benefit.eligibility_criteria || '';
                document.getElementById('coverageDetails').value = benefit.coverage_details || '';
                document.getElementById('enrollmentStart').value = benefit.enrollment_period_start || '';
                document.getElementById('enrollmentEnd').value = benefit.enrollment_period_end || '';
                document.getElementById('benefitStatus').value = benefit.status;
                
                document.querySelector('#benefitFormModal .modal-title').textContent = 'Edit Benefit';
                new bootstrap.Modal(document.getElementById('benefitFormModal')).show();
            }
        }
    });
}

function saveBenefit() {
    const formData = new FormData();
    formData.append('action', 'save_benefit');
    formData.append('id', document.getElementById('benefitId').value);
    formData.append('benefit_name', document.getElementById('benefitName').value);
    formData.append('benefit_type', document.getElementById('benefitType').value);
    formData.append('description', document.getElementById('benefitDescription').value);
    formData.append('provider', document.getElementById('benefitProvider').value);
    formData.append('cost_employee', document.getElementById('costEmployee').value);
    formData.append('cost_employer', document.getElementById('costEmployer').value);
    formData.append('eligibility_criteria', document.getElementById('eligibilityCriteria').value);
    formData.append('coverage_details', document.getElementById('coverageDetails').value);
    formData.append('enrollment_period_start', document.getElementById('enrollmentStart').value);
    formData.append('enrollment_period_end', document.getElementById('enrollmentEnd').value);
    formData.append('status', document.getElementById('benefitStatus').value);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('benefitFormModal')).hide();
            loadAvailableBenefits();
        } else {
            showAlert(data.message, 'danger');
        }
    });
}

function deleteBenefit(id) {
    if (confirm('Are you sure you want to delete this benefit? This will also remove all related enrollments.')) {
        const formData = new FormData();
        formData.append('action', 'delete_benefit');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadAvailableBenefits();
            } else {
                showAlert(data.message, 'danger');
            }
        });
    }
}
<?php endif; ?>

// Generate Benefits Report
function generateBenefitsReport() {
    showAlert('Generating benefits report...', 'info');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=generate_report'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Benefits report generated successfully!', 'success');
        } else {
            showAlert('Error generating report: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        showAlert('Error generating report: ' + error.message, 'danger');
    });
}

// Utility functions
function getBenefitIcon(type) {
    const icons = {
        'health': 'bi-heart-pulse',
        'dental': 'bi-emoji-smile',
        'vision': 'bi-eye',
        'retirement': 'bi-piggy-bank',
        'life_insurance': 'bi-shield-check',
        'disability': 'bi-person-check',
        'wellness': 'bi-activity',
        'transportation': 'bi-bus-front',
        'other': 'bi-gift'
    };
    return icons[type] || 'bi-gift';
}

function getBenefitColor(type) {
    const colors = {
        'health': 'danger',
        'dental': 'info',
        'vision': 'success',
        'retirement': 'primary',
        'life_insurance': 'secondary',
        'disability': 'warning',
        'wellness': 'success',
        'transportation': 'info',
        'other': 'dark'
    };
    return colors[type] || 'dark';
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
</div>

<?php include '../layouts/footer.php'; ?>
