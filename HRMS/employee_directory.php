<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Employee Directory - HRMS';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_employee':
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $department_id = intval($_POST['department_id']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $date_of_joining = mysqli_real_escape_string($conn, $_POST['date_of_joining']);
            $salary = floatval($_POST['salary']);
            
            $query = "INSERT INTO hr_employees (employee_id, first_name, last_name, email, phone, department_id, 
                      position, employment_type, date_of_joining, salary, status) 
                      VALUES ('$employee_id', '$first_name', '$last_name', '$email', '$phone', $department_id, 
                      '$position', '$employment_type', '$date_of_joining', $salary, 'active')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'get_employee':
            $id = intval($_POST['id']);
            $query = mysqli_query($conn, "
                SELECT e.*, d.department_name 
                FROM hr_employees e 
                LEFT JOIN hr_departments d ON e.department_id = d.id 
                WHERE e.id = $id
            ");
            if ($query && $row = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
            }
            exit;

        case 'update_employee':
            $id = intval($_POST['id']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $department_id = intval($_POST['department_id']);
            $position = mysqli_real_escape_string($conn, $_POST['position']);
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $salary = floatval($_POST['salary']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            
            $query = "UPDATE hr_employees SET 
                      first_name = '$first_name',
                      last_name = '$last_name',
                      email = '$email',
                      phone = '$phone',
                      department_id = $department_id,
                      position = '$position',
                      employment_type = '$employment_type',
                      salary = $salary,
                      status = '$status'
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'delete_employee':
            $id = intval($_POST['id']);
            $query = "UPDATE hr_employees SET status = 'inactive' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
            }
            exit;

        case 'export_employees':
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="employees_directory_' . date('Y-m-d') . '.csv"');
            
            // Open output stream
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, [
                'Employee ID', 'First Name', 'Last Name', 'Email', 'Phone', 
                'Department', 'Position', 'Employment Type', 'Date of Joining', 'Salary', 'Status'
            ]);
            
            // Get all employees
            $exportQuery = mysqli_query($conn, "
                SELECT e.*, d.department_name 
                FROM hr_employees e 
                LEFT JOIN hr_departments d ON e.department_id = d.id 
                ORDER BY e.first_name, e.last_name
            ");
            
            if ($exportQuery) {
                while ($row = mysqli_fetch_assoc($exportQuery)) {
                    fputcsv($output, [
                        $row['employee_id'],
                        $row['first_name'],
                        $row['last_name'],
                        $row['email'],
                        $row['phone'],
                        $row['department_name'] ?? 'N/A',
                        $row['position'],
                        ucfirst(str_replace('_', ' ', $row['employment_type'])),
                        $row['date_of_joining'],
                        $row['salary'] ? '₹' . number_format($row['salary']) : 'N/A',
                        ucfirst($row['status'])
                    ]);
                }
            }
            
            fclose($output);
            exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$employment_type = $_GET['employment_type'] ?? '';
$status = $_GET['status'] ?? 'active';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12; // Show 12 cards per page
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($department)) {
    $where .= " AND d.department_name = ?";
    $params[] = $department;
}

if (!empty($employment_type)) {
    $where .= " AND e.employment_type = ?";
    $params[] = $employment_type;
}

if (!empty($status)) {
    $where .= " AND e.status = ?";
    $params[] = $status;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM hr_employees e LEFT JOIN hr_departments d ON e.department_id = d.id $where";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$totalEmployees = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalEmployees / $limit);

// Get employees with pagination
$query = "
    SELECT e.*, d.department_name 
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    $where
    ORDER BY e.first_name, e.last_name
    LIMIT ? OFFSET ?
";

$allParams = array_merge($params, [$limit, $offset]);
$types = str_repeat('s', count($params)) . 'ii';
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$allParams);
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get departments for filter
$departments = mysqli_query($conn, "SELECT DISTINCT department_name FROM hr_departments WHERE status = 'active' ORDER BY department_name");
$deptList = [];
if ($departments) {
    while ($dept = mysqli_fetch_assoc($departments)) {
        $deptList[] = $dept;
    }
}

// Get employee statistics
$activeEmployees = 0;
$totalSalaryBudget = 0;
$departmentCounts = [];

$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as active_count,
        SUM(salary) as total_salary,
        d.department_name,
        COUNT(CASE WHEN e.status = 'active' THEN 1 END) as dept_count
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    WHERE e.status = 'active'
    GROUP BY d.department_name
");

if ($statsQuery) {
    while ($stat = mysqli_fetch_assoc($statsQuery)) {
        $activeEmployees += $stat['dept_count'];
        $totalSalaryBudget += $stat['total_salary'];
        $departmentCounts[$stat['department_name']] = $stat['dept_count'];
    }
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">👥 Employee Directory</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-people"></i> 
                        Manage and view all employees in the organization
                        <span class="badge bg-light text-dark ms-2"><?= $totalEmployees ?> Total Employees</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm" onclick="exportEmployees()" title="Export Employee Data">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="printDirectory()" title="Print Directory">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal" title="Add New Employee">
                        <i class="bi bi-person-plus"></i> Add Employee
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Back to HRMS">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-2 mb-3">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f4fd 0%, #cce7ff 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-people-fill fs-3" style="color: #0d6efd;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #0d6efd;"><?= number_format($activeEmployees) ?></h5>
                            <small class="text-muted">Active Employees</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-building fs-3" style="color: #7b1fa2;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #7b1fa2;"><?= count($deptList) ?></h5>
                            <small class="text-muted">Departments</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-currency-rupee fs-3" style="color: #388e3c;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #388e3c;">₹<?= number_format($totalSalaryBudget) ?></h5>
                            <small class="text-muted">Total Salary Budget</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                        <div class="card-body text-center p-3">
                            <div class="mb-2">
                                <i class="bi bi-graph-up fs-3" style="color: #ff9800;"></i>
                            </div>
                            <h5 class="mb-1 fw-bold" style="color: #ff9800;"><?= $totalSalaryBudget > 0 ? number_format($totalSalaryBudget / max($activeEmployees, 1)) : 0 ?></h5>
                            <small class="text-muted">Avg Salary</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Card -->
            <div class="card mb-3 border-0">
                <div class="card-header bg-gradient text-white py-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h6 class="mb-0 text-white">
                        <i class="bi bi-funnel me-2"></i>Search & Filter Employees
                        <span class="float-end">
                            <i class="bi bi-search"></i>
                        </span>
                    </h6>
                </div>
                <div class="card-body p-3">
                    <form method="GET" class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-search me-1"></i>Search Employees
                            </label>
                            <input type="text" name="search" class="form-control" placeholder="Name, ID, email..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-building me-1"></i>Department
                            </label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($deptList as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" <?= $department === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-briefcase me-1"></i>Type
                            </label>
                            <select name="employment_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="full_time" <?= $employment_type === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                                <option value="part_time" <?= $employment_type === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                                <option value="contract" <?= $employment_type === 'contract' ? 'selected' : '' ?>>Contract</option>
                                <option value="intern" <?= $employment_type === 'intern' ? 'selected' : '' ?>>Intern</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                <i class="bi bi-toggle-on me-1"></i>Status
                            </label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="on_leave" <?= $status === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Employee Cards -->
            <?php if (empty($employees)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="text-muted mb-3">
                            <i class="bi bi-people" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="text-muted">No employees found</h5>
                        <p class="text-muted">Try adjusting your search criteria or add new employees.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="bi bi-person-plus me-2"></i>Add First Employee
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($employees as $employee): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="card border-0 shadow-sm h-100 employee-card" style="transition: all 0.3s ease;">
                                <div class="card-body p-4">
                                    <!-- Employee Header -->
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 60px;">
                                                <i class="bi bi-person-fill fs-3"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-1 fw-bold">
                                                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                            </h5>
                                            <p class="text-muted mb-1 small">
                                                ID: <?= htmlspecialchars($employee['employee_id']) ?>
                                            </p>
                                            <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : ($employee['status'] === 'on_leave' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst($employee['status']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Employee Details -->
                                    <div class="employee-details mb-3">
                                        <?php if ($employee['email']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-envelope text-primary me-2"></i>
                                            <small class="text-muted"><?= htmlspecialchars($employee['email']) ?></small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($employee['phone']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-telephone text-success me-2"></i>
                                            <small class="text-muted"><?= htmlspecialchars($employee['phone']) ?></small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($employee['department_name']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-building text-info me-2"></i>
                                            <small class="text-muted"><?= htmlspecialchars($employee['department_name']) ?></small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($employee['position']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-briefcase text-warning me-2"></i>
                                            <small class="text-muted"><?= htmlspecialchars($employee['position']) ?></small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-calendar text-secondary me-2"></i>
                                            <small class="text-muted">Joined: <?= date('M j, Y', strtotime($employee['date_of_joining'])) ?></small>
                                        </div>
                                        
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-badge text-dark me-2"></i>
                                            <small class="badge bg-info bg-opacity-75"><?= ucwords(str_replace('_', ' ', $employee['employment_type'])) ?></small>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="employee_profile.php?id=<?= $employee['id'] ?>" class="btn btn-sm btn-outline-info" title="View Full Profile">
                                            <i class="bi bi-person-vcard me-1"></i>Profile
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewEmployee(<?= $employee['id'] ?>)" title="View Details">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="editEmployee(<?= $employee['id'] ?>)" title="Edit Employee">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteEmployee(<?= $employee['id'] ?>)" title="Deactivate">
                                            <i class="bi bi-person-x me-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Employee directory pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center text-muted small">
                            Showing <?= (($page - 1) * $limit) + 1 ?> to <?= min($page * $limit, $totalEmployees) ?> of <?= $totalEmployees ?> employees
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>Add New Employee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEmployeeForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" name="employee_id" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php 
                                $deptQuery = mysqli_query($conn, "SELECT id, department_name FROM hr_departments WHERE status = 'active'");
                                while ($dept = mysqli_fetch_assoc($deptQuery)): 
                                ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Type *</label>
                            <select name="employment_type" class="form-select" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Joining *</label>
                            <input type="date" name="date_of_joining" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary (₹)</label>
                            <input type="number" name="salary" class="form-control" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning me-2"></i>Edit Employee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEmployeeForm">
                <input type="hidden" name="id" id="editEmployeeId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="editLastName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" id="editPhone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" id="editDepartmentId" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php 
                                mysqli_data_seek($deptQuery, 0);
                                while ($dept = mysqli_fetch_assoc($deptQuery)): 
                                ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" id="editPosition" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Type *</label>
                            <select name="employment_type" id="editEmploymentType" class="form-select" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Salary (₹)</label>
                            <input type="number" name="salary" id="editSalary" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Employee Modal -->
<div class="modal fade" id="viewEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person text-info me-2"></i>Employee Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Employee management functions
document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_employee');
    
    fetch('', {
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
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding employee');
    });
});

document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_employee');
    
    fetch('', {
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
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating employee');
    });
});

function viewEmployee(id) {
    const formData = new FormData();
    formData.append('action', 'get_employee');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const emp = data.data;
            document.getElementById('employeeDetailsContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-primary">Personal Information</h6>
                        <p><strong>Name:</strong> ${emp.first_name} ${emp.last_name}</p>
                        <p><strong>Employee ID:</strong> ${emp.employee_id}</p>
                        <p><strong>Email:</strong> ${emp.email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${emp.phone || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Professional Information</h6>
                        <p><strong>Department:</strong> ${emp.department_name || 'N/A'}</p>
                        <p><strong>Position:</strong> ${emp.position || 'N/A'}</p>
                        <p><strong>Employment Type:</strong> ${emp.employment_type ? emp.employment_type.replace('_', ' ').toUpperCase() : 'N/A'}</p>
                        <p><strong>Date of Joining:</strong> ${emp.date_of_joining || 'N/A'}</p>
                        <p><strong>Salary:</strong> ${emp.salary ? '₹' + parseFloat(emp.salary).toLocaleString() : 'Not set'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${emp.status === 'active' ? 'success' : emp.status === 'inactive' ? 'secondary' : 'warning'}">${emp.status ? emp.status.toUpperCase() : 'N/A'}</span></p>
                    </div>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('viewEmployeeModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching employee details');
    });
}

function editEmployee(id) {
    const formData = new FormData();
    formData.append('action', 'get_employee');
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const emp = data.data;
            document.getElementById('editEmployeeId').value = emp.id;
            document.getElementById('editFirstName').value = emp.first_name || '';
            document.getElementById('editLastName').value = emp.last_name || '';
            document.getElementById('editEmail').value = emp.email || '';
            document.getElementById('editPhone').value = emp.phone || '';
            document.getElementById('editDepartmentId').value = emp.department_id || '';
            document.getElementById('editPosition').value = emp.position || '';
            document.getElementById('editEmploymentType').value = emp.employment_type || '';
            document.getElementById('editSalary').value = emp.salary || '';
            document.getElementById('editStatus').value = emp.status || '';
            
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching employee details');
    });
}

function deleteEmployee(id) {
    if (confirm('Are you sure you want to deactivate this employee?')) {
        const formData = new FormData();
        formData.append('action', 'delete_employee');
        formData.append('id', id);
        
        fetch('', {
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
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deactivating employee');
        });
    }
}

function exportEmployees() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_employees';
    
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function printDirectory() {
    window.print();
}

// Hover effects for cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.employee-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});
</script>

<style>
.statistics-card {
    transition: transform 0.2s ease;
}

.statistics-card:hover {
    transform: translateY(-2px);
}

.employee-card {
    transition: all 0.3s ease;
}

.employee-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-details {
    font-size: 0.9rem;
}

@media print {
    .btn, .pagination, .card-header {
        display: none !important;
    }
}
</style>

<?php include '../layouts/footer.php'; ?>