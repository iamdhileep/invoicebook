<?php
$page_title = "Employee Directory - HRMS";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Get employees with search functionality
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = ["e.status = 'active'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($department)) {
    $whereConditions[] = "d.department_name = ?";
    $params[] = $department;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM hr_employees e 
    LEFT JOIN hr_departments d ON e.department_id = d.id 
    WHERE $whereClause
";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$totalEmployees = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalEmployees / $limit);

// Get employees
$query = "
    SELECT 
        e.employee_id,
        e.first_name,
        e.last_name,
        e.email,
        e.phone,
        e.date_of_joining,
        e.employment_type,
        d.department_name,
        p.position_name
    FROM hr_employees e
    LEFT JOIN hr_departments d ON e.department_id = d.id
    LEFT JOIN hr_positions p ON e.position_id = p.id
    WHERE $whereClause
    ORDER BY e.first_name, e.last_name
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$allParams = array_merge($params, [$limit, $offset]);
$types = str_repeat('s', count($params)) . 'ii';
$stmt->bind_param($types, ...$allParams);
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get departments for filter
$deptResult = $conn->query("SELECT DISTINCT department_name FROM hr_departments WHERE status = 'active' ORDER BY department_name");
$departments = $deptResult ? $deptResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg bg-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-6">
                            <h2 class="mb-2 fw-bold">
                                <i class="bi bi-people-fill me-3"></i>
                                Employee Directory
                            </h2>
                            <p class="mb-0 opacity-90">Manage and view all employees in the organization</p>
                        </div>
                        <div class="col-lg-6 text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-light" onclick="exportEmployees()">
                                    <i class="bi bi-download me-2"></i>Export
                                </button>
                                <button class="btn btn-warning" onclick="addEmployee()">
                                    <i class="bi bi-person-plus me-2"></i>Add Employee
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-lg-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search employees...">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                            <?= $department === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-lg-2">
                            <a href="employee_directory.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle me-2"></i>Clear
                            </a>
                        </div>
                        <div class="col-lg-1 text-end">
                            <span class="badge bg-info"><?= number_format($totalEmployees) ?> found</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Cards -->
    <div class="row">
        <?php if (empty($employees)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-people" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">Try adjusting your search criteria or add new employees.</p>
                    <button class="btn btn-primary" onclick="addEmployee()">
                        <i class="bi bi-person-plus me-2"></i>Add First Employee
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($employees as $employee): ?>
        <div class="col-lg-6 col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100 employee-card">
                <div class="card-body">
                    <!-- Employee Header -->
                    <div class="d-flex align-items-start mb-3">
                        <div class="flex-shrink-0">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-person-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1">
                                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                            </h5>
                            <p class="text-muted mb-1">
                                <small>ID: <?= htmlspecialchars($employee['employee_id']) ?></small>
                            </p>
                            <span class="badge bg-<?= $employee['employment_type'] === 'full_time' ? 'success' : 'info' ?>">
                                <?= ucwords(str_replace('_', ' ', $employee['employment_type'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Employee Details -->
                    <div class="employee-details">
                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-envelope me-2"></i>
                                    <span><?= htmlspecialchars($employee['email']) ?></span>
                                </div>
                            </div>
                            <?php if ($employee['phone']): ?>
                            <div class="col-12">
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-telephone me-2"></i>
                                    <span><?= htmlspecialchars($employee['phone']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($employee['department_name']): ?>
                            <div class="col-12">
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-building me-2"></i>
                                    <span><?= htmlspecialchars($employee['department_name']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($employee['position_name']): ?>
                            <div class="col-12">
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-briefcase me-2"></i>
                                    <span><?= htmlspecialchars($employee['position_name']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-calendar me-2"></i>
                                    <span>Joined: <?= date('M j, Y', strtotime($employee['date_of_joining'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="viewEmployee('<?= $employee['employee_id'] ?>')">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                        <button class="btn btn-sm btn-outline-success" 
                                onclick="editEmployee('<?= $employee['employee_id'] ?>')">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
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
                            <i class="bi bi-chevron-left"></i>
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
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.employee-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.employee-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.employee-details {
    font-size: 0.9rem;
}
</style>

<script>
function viewEmployee(employeeId) {
    // Implement view employee functionality
    Swal.fire({
        title: 'View Employee',
        text: `Opening profile for employee: ${employeeId}`,
        icon: 'info',
        confirmButtonText: 'OK'
    });
}

function editEmployee(employeeId) {
    // Implement edit employee functionality
    Swal.fire({
        title: 'Edit Employee',
        text: `Opening editor for employee: ${employeeId}`,
        icon: 'info',
        confirmButtonText: 'OK'
    });
}

function addEmployee() {
    // Implement add employee functionality
    Swal.fire({
        title: 'Add New Employee',
        text: 'Employee registration form will open here',
        icon: 'info',
        confirmButtonText: 'OK'
    });
}

function exportEmployees() {
    // Implement export functionality
    Swal.fire({
        title: 'Export Employees',
        text: 'Employee data will be exported to CSV/Excel',
        icon: 'success',
        confirmButtonText: 'OK'
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Employee Directory loaded successfully');
});
</script>

<?php require_once 'hrms_footer_simple.php'; ?>