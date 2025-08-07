<?php
$page_title = "Benefits Management";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Create benefits tables if not exist
$createBenefitsTable = "
CREATE TABLE IF NOT EXISTS hr_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    benefit_name VARCHAR(100) NOT NULL,
    benefit_type ENUM('health', 'dental', 'vision', 'retirement', 'life_insurance', 'disability', 'wellness', 'other') DEFAULT 'other',
    description TEXT,
    provider VARCHAR(100),
    cost_employee DECIMAL(10,2) DEFAULT 0,
    cost_employer DECIMAL(10,2) DEFAULT 0,
    eligibility_criteria TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createBenefitsTable);

$createEmployeeBenefitsTable = "
CREATE TABLE IF NOT EXISTS hr_employee_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    benefit_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    coverage_start_date DATE NOT NULL,
    coverage_end_date DATE,
    premium_amount DECIMAL(10,2) DEFAULT 0,
    deduction_amount DECIMAL(10,2) DEFAULT 0,
    beneficiary_name VARCHAR(100),
    beneficiary_relationship VARCHAR(50),
    status ENUM('enrolled', 'pending', 'declined', 'terminated') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(employee_id),
    FOREIGN KEY (benefit_id) REFERENCES hr_benefits(id)
)";
$conn->query($createEmployeeBenefitsTable);

// Insert sample benefits if table is empty
$checkBenefits = $conn->query("SELECT COUNT(*) as count FROM hr_benefits");
if ($checkBenefits && $checkBenefits->fetch_assoc()['count'] == 0) {
    $sampleBenefits = [
        ['Health Insurance Premium', 'health', 'Comprehensive health insurance coverage', 'BlueCross BlueShield', 150.00, 450.00, 'Full-time employees after 90 days'],
        ['Dental Insurance', 'dental', 'Dental care coverage including cleanings and procedures', 'Delta Dental', 25.00, 75.00, 'Full-time employees'],
        ['Vision Insurance', 'vision', 'Eye care coverage including exams and glasses', 'VSP', 15.00, 35.00, 'Full-time employees'],
        ['401(k) Retirement Plan', 'retirement', '401(k) with company matching up to 6%', 'Fidelity', 0.00, 0.00, 'All employees after 6 months'],
        ['Life Insurance', 'life_insurance', 'Basic life insurance coverage', 'MetLife', 0.00, 25.00, 'Full-time employees'],
        ['Disability Insurance', 'disability', 'Short and long-term disability coverage', 'UNUM', 12.00, 38.00, 'Full-time employees'],
        ['Wellness Program', 'wellness', 'Gym membership and wellness activities', 'Corporate Wellness', 0.00, 50.00, 'All employees'],
        ['Flexible Spending Account', 'other', 'Pre-tax spending for healthcare expenses', 'WageWorks', 0.00, 10.00, 'Full-time employees']
    ];
    
    foreach ($sampleBenefits as $benefit) {
        $stmt = $conn->prepare("INSERT INTO hr_benefits (benefit_name, benefit_type, description, provider, cost_employee, cost_employer, eligibility_criteria) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdds", $benefit[0], $benefit[1], $benefit[2], $benefit[3], $benefit[4], $benefit[5], $benefit[6]);
        $stmt->execute();
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'enroll_benefit':
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $benefit_id = intval($_POST['benefit_id'] ?? 0);
            $coverage_start = $_POST['coverage_start'] ?? '';
            $beneficiary_name = $conn->real_escape_string($_POST['beneficiary_name'] ?? '');
            $beneficiary_relationship = $conn->real_escape_string($_POST['beneficiary_relationship'] ?? '');
            
            if ($employee_id && $benefit_id && $coverage_start) {
                // Check if already enrolled
                $checkStmt = $conn->prepare("SELECT id FROM hr_employee_benefits WHERE employee_id = ? AND benefit_id = ? AND status = 'enrolled'");
                $checkStmt->bind_param("ii", $employee_id, $benefit_id);
                $checkStmt->execute();
                $existing = $checkStmt->get_result();
                
                if ($existing->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already enrolled in this benefit']);
                } else {
                    $stmt = $conn->prepare("INSERT INTO hr_employee_benefits (employee_id, benefit_id, enrollment_date, coverage_start_date, beneficiary_name, beneficiary_relationship, status) VALUES (?, ?, NOW(), ?, ?, ?, 'enrolled')");
                    $stmt->bind_param("iisss", $employee_id, $benefit_id, $coverage_start, $beneficiary_name, $beneficiary_relationship);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Successfully enrolled in benefit']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Enrollment failed: ' . $conn->error]);
                    }
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing required information']);
            }
            exit;
            
        case 'cancel_benefit':
            $enrollment_id = intval($_POST['enrollment_id'] ?? 0);
            if ($enrollment_id) {
                $stmt = $conn->prepare("UPDATE hr_employee_benefits SET status = 'terminated', coverage_end_date = NOW() WHERE id = ?");
                $stmt->bind_param("i", $enrollment_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Benefit enrollment cancelled']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cancellation failed']);
                }
            }
            exit;
            
        case 'get_benefit_details':
            $benefit_id = intval($_POST['benefit_id'] ?? 0);
            if ($benefit_id) {
                $stmt = $conn->prepare("SELECT * FROM hr_benefits WHERE id = ?");
                $stmt->bind_param("i", $benefit_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Benefit not found']);
                }
            }
            exit;
    }
}

// Get all benefits
$benefits = [];
$benefitsResult = $conn->query("SELECT * FROM hr_benefits WHERE status = 'active' ORDER BY benefit_type, benefit_name");
if ($benefitsResult) {
    while ($row = $benefitsResult->fetch_assoc()) {
        $benefits[] = $row;
    }
}

// Get employee enrollments
$enrollments = [];
$enrollmentResult = $conn->query("
    SELECT eb.*, b.benefit_name, b.benefit_type, b.provider 
    FROM hr_employee_benefits eb 
    JOIN hr_benefits b ON eb.benefit_id = b.id 
    WHERE eb.employee_id = $currentUserId AND eb.status = 'enrolled'
    ORDER BY eb.enrollment_date DESC
");
if ($enrollmentResult) {
    while ($row = $enrollmentResult->fetch_assoc()) {
        $enrollments[] = $row;
    }
}

// Get employees for admin view
$employees = [];
if ($currentUserRole === 'admin' || $currentUserRole === 'hr') {
    $empResult = $conn->query("SELECT employee_id, first_name, last_name, department FROM hr_employees WHERE status = 'active'");
    if ($empResult) {
        while ($row = $empResult->fetch_assoc()) {
            $employees[] = $row;
        }
    }
}

// Group benefits by type
$benefitsByType = [];
foreach ($benefits as $benefit) {
    $benefitsByType[$benefit['benefit_type']][] = $benefit;
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-heart mr-2"></i>Benefits Management
            </h1>
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'hr'): ?>
            <button class="btn btn-primary" onclick="showAddBenefitModal()">
                <i class="fas fa-plus mr-1"></i>Add Benefit
            </button>
            <?php endif; ?>
        </div>

        <!-- Benefits Overview -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Available Benefits</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($benefits); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-gift fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">My Enrollments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($enrollments); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Monthly Premium</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format(array_sum(array_column($enrollments, 'premium_amount')), 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Enrollment Period</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Open</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Current Benefits -->
        <?php if (!empty($enrollments)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">My Current Benefits</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Benefit</th>
                                        <th>Type</th>
                                        <th>Provider</th>
                                        <th>Coverage Start</th>
                                        <th>Premium</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enrollment['benefit_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getBenefitTypeColor($enrollment['benefit_type']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $enrollment['benefit_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($enrollment['provider']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($enrollment['coverage_start_date'])); ?></td>
                                        <td>$<?php echo number_format($enrollment['premium_amount'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewBenefitDetails(<?php echo $enrollment['benefit_id']; ?>)">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="cancelBenefit(<?php echo $enrollment['id']; ?>)">
                                                <i class="fas fa-times"></i> Cancel
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
        <?php endif; ?>

        <!-- Available Benefits -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Available Benefits</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($benefitsByType as $type => $typeBenefits): ?>
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas <?php echo getBenefitTypeIcon($type); ?> mr-2"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </h5>
                            <div class="row">
                                <?php foreach ($typeBenefits as $benefit): ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="card border-left-<?php echo getBenefitTypeColor($benefit['benefit_type']); ?> h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($benefit['benefit_name']); ?></h6>
                                            <p class="card-text text-muted small"><?php echo htmlspecialchars($benefit['description']); ?></p>
                                            <div class="mt-3">
                                                <small class="text-muted d-block">Provider: <?php echo htmlspecialchars($benefit['provider']); ?></small>
                                                <small class="text-muted d-block">Employee Cost: $<?php echo number_format($benefit['cost_employee'], 2); ?>/month</small>
                                            </div>
                                            <div class="mt-3">
                                                <?php
                                                $isEnrolled = false;
                                                foreach ($enrollments as $enrollment) {
                                                    if ($enrollment['benefit_id'] == $benefit['id']) {
                                                        $isEnrolled = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <?php if ($isEnrolled): ?>
                                                    <span class="badge badge-success">Enrolled</span>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-primary" onclick="enrollInBenefit(<?php echo $benefit['id']; ?>)">
                                                        <i class="fas fa-plus mr-1"></i>Enroll
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-info ml-1" onclick="viewBenefitDetails(<?php echo $benefit['id']; ?>)">
                                                    <i class="fas fa-info"></i> Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Benefit Enrollment Modal -->
<div class="modal fade" id="enrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enroll in Benefit</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="enrollmentForm">
                    <input type="hidden" id="enrollBenefitId" name="benefit_id">
                    <input type="hidden" name="employee_id" value="<?php echo $currentUserId; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="coverageStart" class="form-label">Coverage Start Date</label>
                            <input type="date" class="form-control" id="coverageStart" name="coverage_start" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="beneficiaryName" class="form-label">Beneficiary Name</label>
                            <input type="text" class="form-control" id="beneficiaryName" name="beneficiary_name">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="beneficiaryRelationship" class="form-label">Beneficiary Relationship</label>
                        <select class="form-control" id="beneficiaryRelationship" name="beneficiary_relationship">
                            <option value="">Select Relationship</option>
                            <option value="spouse">Spouse</option>
                            <option value="child">Child</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEnrollment()">Enroll</button>
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
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="benefitDetailsContent">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script>
function enrollInBenefit(benefitId) {
    document.getElementById('enrollBenefitId').value = benefitId;
    
    // Set default coverage start date to next month
    const nextMonth = new Date();
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    nextMonth.setDate(1);
    document.getElementById('coverageStart').value = nextMonth.toISOString().split('T')[0];
    
    new bootstrap.Modal(document.getElementById('enrollmentModal')).show();
}

function submitEnrollment() {
    const formData = new FormData(document.getElementById('enrollmentForm'));
    formData.append('action', 'enroll_benefit');
    
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
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function viewBenefitDetails(benefitId) {
    const formData = new FormData();
    formData.append('action', 'get_benefit_details');
    formData.append('benefit_id', benefitId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const benefit = data.data;
            const content = `
                <h5>${benefit.benefit_name}</h5>
                <p><strong>Type:</strong> ${benefit.benefit_type.replace('_', ' ')}</p>
                <p><strong>Provider:</strong> ${benefit.provider}</p>
                <p><strong>Description:</strong> ${benefit.description}</p>
                <p><strong>Employee Cost:</strong> $${parseFloat(benefit.cost_employee).toFixed(2)} per month</p>
                <p><strong>Employer Cost:</strong> $${parseFloat(benefit.cost_employer).toFixed(2)} per month</p>
                <p><strong>Eligibility:</strong> ${benefit.eligibility_criteria}</p>
            `;
            
            document.getElementById('benefitDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('benefitDetailsModal')).show();
        }
    });
}
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }

.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }

.card {
    border: none;
    border-radius: 0.35rem;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
}
</style>

<?php 
require_once 'hrms_footer_simple.php';

function getBenefitTypeColor($type) {
    $colors = [
        'health' => 'primary',
        'dental' => 'success', 
        'vision' => 'info',
        'retirement' => 'warning',
        'life_insurance' => 'danger',
        'disability' => 'secondary',
        'wellness' => 'success',
        'other' => 'dark'
    ];
    return $colors[$type] ?? 'secondary';
}

function getBenefitTypeIcon($type) {
    $icons = [
        'health' => 'fa-heartbeat',
        'dental' => 'fa-tooth',
        'vision' => 'fa-eye',
        'retirement' => 'fa-piggy-bank',
        'life_insurance' => 'fa-shield-alt',
        'disability' => 'fa-wheelchair',
        'wellness' => 'fa-dumbbell',
        'other' => 'fa-gift'
    ];
    return $icons[$type] ?? 'fa-gift';
}

require_once 'hrms_footer_simple.php';
?>