<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

define('HRMS_ACCESS', true);
require_once '../db.php';

$page_title = "Goal Management";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_goal':
            try {
                $stmt = $conn->prepare("INSERT INTO employee_goals (employee_id, goal_title, description, start_date, end_date, target_value, measurement_unit, assigned_by, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("issssisss",
                    $_POST['employee_id'],
                    $_POST['goal_title'],
                    $_POST['description'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['target_value'],
                    $_POST['measurement_unit'],
                    $_POST['assigned_by'],
                    $_POST['priority']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add goal']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_goal':
            try {
                $stmt = $conn->prepare("UPDATE employee_goals SET goal_title = ?, description = ?, start_date = ?, end_date = ?, priority = ?, target_value = ?, measurement_unit = ? WHERE id = ?");
                $stmt->bind_param("sssssissi", 
                    $_POST['goal_title'],
                    $_POST['description'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['priority'],
                    $_POST['target_value'],
                    $_POST['measurement_unit'],
                    $_POST['goal_id']
                );
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update goal']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_progress':
            try {
                $progress = min(100, max(0, floatval($_POST['progress'])));
                $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'active');
                
                $stmt = $conn->prepare("UPDATE employee_goals SET progress_percentage = ?, current_value = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("dissi", $progress, $_POST['current_value'], $status, $_POST['notes'], $_POST['goal_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Progress updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_goal':
            try {
                $stmt = $conn->prepare("DELETE FROM employee_goals WHERE id = ?");
                $stmt->bind_param("i", $_POST['goal_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Goal deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete goal']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_goal_details':
            try {
                $stmt = $conn->prepare("SELECT g.*, e1.name as employee_name, e1.department_name as department, e2.name as assigned_by_name FROM employee_goals g LEFT JOIN employees e1 ON g.employee_id = e1.employee_id LEFT JOIN employees e2 ON g.assigned_by = e2.employee_id WHERE g.id = ?");
                $stmt->bind_param("i", $_POST['goal_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($goal = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'goal' => $goal]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Goal not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Fetch employees for dropdowns
$employees = [];
$result = $conn->query("SELECT employee_id as id, name, department_name as department, position FROM employees WHERE status = 'active' ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch goals
$goals = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$employee_filter = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

$where_clause = "WHERE 1=1";
if ($filter !== 'all') {
    $where_clause .= " AND g.status = '$filter'";
}
if ($employee_filter > 0) {
    $where_clause .= " AND g.employee_id = $employee_filter";
}

$sql = "SELECT g.*, e1.name as employee_name, e1.department_name as department, e1.position, e2.name as assigned_by_name 
        FROM employee_goals g 
        LEFT JOIN employees e1 ON g.employee_id = e1.employee_id 
        LEFT JOIN employees e2 ON g.assigned_by = e2.employee_id 
        $where_clause
        ORDER BY g.created_at DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $goals[] = $row;
    }
}

// Calculate statistics
$stats = [
    'total' => count($goals),
    'active' => count(array_filter($goals, function($g) { return $g['status'] == 'active'; })),
    'in_progress' => count(array_filter($goals, function($g) { return $g['status'] == 'in_progress'; })),
    'completed' => count(array_filter($goals, function($g) { return $g['status'] == 'completed'; })),
    'overdue' => 0
];

// Check for overdue goals
foreach ($goals as $goal) {
    if ($goal['end_date'] && strtotime($goal['end_date']) < strtotime(date('Y-m-d')) && $goal['status'] != 'completed') {
        $stats['overdue']++;
    }
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🎯 Goal Management</h1>
                <p class="text-muted">Track and manage employee goals and objectives</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                <i class="bi bi-plus-circle me-1"></i>Add New Goal
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-bullseye fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['total'] ?></h3>
                        <small class="opacity-75">Total Goals</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-play-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['active'] ?></h3>
                        <small class="opacity-75">Active Goals</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-arrow-clockwise fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['in_progress'] ?></h3>
                        <small class="opacity-75">In Progress</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $stats['completed'] ?></h3>
                        <small class="opacity-75">Completed</small>
                    </div>
                </div>
            </div>
        </div>

            <?php if ($stats['overdue'] > 0): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Attention!</strong> You have <?= $stats['overdue'] ?> overdue goal(s) that need attention.
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="statusFilter" class="form-label">Filter by Status</label>
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Goals</option>
                                <option value="active" <?= $filter == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="in_progress" <?= $filter == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="employeeFilter" class="form-label">Filter by Employee</label>
                            <select class="form-select" id="employeeFilter" onchange="applyFilters()">
                                <option value="0">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $employee_filter == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="bi bi-x-circle me-1"></i>Clear Filters
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="exportGoals()">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goals Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Goals Overview</h5>
                    <span class="badge bg-info"><?= count($goals) ?> Goals</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="goalsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Goal Title</th>
                                    <th>Priority</th>
                                    <th>Progress</th>
                                    <th>Timeline</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($goals as $goal): ?>
                                <tr class="<?= ($goal['end_date'] && strtotime($goal['end_date']) < strtotime(date('Y-m-d')) && $goal['status'] != 'completed') ? 'table-danger' : '' ?>">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($goal['employee_name'] ?? 'N/A') ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($goal['department'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($goal['goal_title'] ?? '') ?></strong>
                                            <?php if (!empty($goal['description'])): ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($goal['description'], 0, 100)) ?><?= strlen($goal['description']) > 100 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $goal['priority'] == 'high' ? 'danger' : ($goal['priority'] == 'medium' ? 'warning' : 'info') ?>">
                                            <?= ucfirst($goal['priority'] ?? 'low') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar <?= ($goal['progress_percentage'] ?? 0) >= 100 ? 'bg-success' : (($goal['progress_percentage'] ?? 0) >= 75 ? 'bg-info' : (($goal['progress_percentage'] ?? 0) >= 50 ? 'bg-warning' : 'bg-danger')) ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= $goal['progress_percentage'] ?? 0 ?>%" 
                                                 aria-valuenow="<?= $goal['progress_percentage'] ?? 0 ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?= $goal['progress_percentage'] ?? 0 ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small>
                                            <strong>Start:</strong> <?= date('M d, Y', strtotime($goal['start_date'] ?? '')) ?><br>
                                            <strong>End:</strong> <?= date('M d, Y', strtotime($goal['end_date'] ?? '')) ?>
                                            <?php if ($goal['end_date'] && strtotime($goal['end_date']) < strtotime(date('Y-m-d')) && $goal['status'] != 'completed'): ?>
                                                <br><span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Overdue</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $goal['status'] == 'completed' ? 'success' : ($goal['status'] == 'in_progress' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $goal['status'] ?? 'active')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewGoal(<?= $goal['id'] ?>)" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="updateProgress(<?= $goal['id'] ?>)" title="Update Progress">
                                                <i class="bi bi-arrow-up-circle"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="editGoal(<?= $goal['id'] ?>)" title="Edit Goal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGoal(<?= $goal['id'] ?>)" title="Delete Goal">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
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
</div>

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1" aria-labelledby="addGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGoalModalLabel">Add New Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addGoalForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="goal_employee_id" class="form-label">Employee *</label>
                                <select class="form-select" id="goal_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="goal_assigned_by" class="form-label">Assigned By *</label>
                                <select class="form-select" id="goal_assigned_by" name="assigned_by" required>
                                    <option value="">Select Assigner</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['position']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="goal_title" class="form-label">Goal Title *</label>
                        <input type="text" class="form-control" id="goal_title" name="goal_title" required placeholder="Enter goal title...">
                    </div>
                    <div class="mb-3">
                        <label for="goal_description" class="form-label">Description</label>
                        <textarea class="form-control" id="goal_description" name="description" rows="3" placeholder="Detailed description of the goal..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="goal_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="goal_end_date" name="end_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="goal_priority" class="form-label">Priority *</label>
                                <select class="form-select" id="goal_priority" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_value" class="form-label">Target Value</label>
                                <input type="number" step="0.01" class="form-control" id="target_value" name="target_value" placeholder="e.g., 100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="measurement_unit" class="form-label">Measurement Unit</label>
                                <input type="text" class="form-control" id="measurement_unit" name="measurement_unit" placeholder="e.g., sales, projects, hours">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Goal Modal -->
<div class="modal fade" id="editGoalModal" tabindex="-1" aria-labelledby="editGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGoalModalLabel">Edit Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editGoalForm">
                <input type="hidden" id="edit_goal_id" name="goal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_goal_title" class="form-label">Goal Title *</label>
                        <input type="text" class="form-control" id="edit_goal_title" name="goal_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_goal_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_goal_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_goal_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_goal_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_goal_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="edit_goal_end_date" name="end_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_goal_priority" class="form-label">Priority *</label>
                                <select class="form-select" id="edit_goal_priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_target_value" class="form-label">Target Value</label>
                                <input type="number" step="0.01" class="form-control" id="edit_target_value" name="target_value">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_measurement_unit" class="form-label">Measurement Unit</label>
                                <input type="text" class="form-control" id="edit_measurement_unit" name="measurement_unit">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-labelledby="updateProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProgressModalLabel">Update Goal Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateProgressForm">
                <div class="modal-body">
                    <input type="hidden" id="progress_goal_id" name="goal_id">
                    <div class="mb-3">
                        <label for="progress_percentage" class="form-label">Progress Percentage *</label>
                        <input type="range" class="form-range" id="progress_percentage" name="progress" min="0" max="100" step="1" value="50">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <small id="progressValue">50%</small>
                            <small>100%</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="current_value" class="form-label">Current Value</label>
                        <input type="number" step="0.01" class="form-control" id="current_value" name="current_value" placeholder="Current achievement">
                    </div>
                    <div class="mb-3">
                        <label for="progress_notes" class="form-label">Progress Notes</label>
                        <textarea class="form-control" id="progress_notes" name="notes" rows="3" placeholder="Notes about the progress..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Progress</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Goal Modal -->
<div class="modal fade" id="viewGoalModal" tabindex="-1" aria-labelledby="viewGoalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewGoalModalLabel">Goal Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="goalDetails">
                <!-- Goal details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Progress range input
    const progressRange = document.getElementById('progress_percentage');
    const progressValue = document.getElementById('progressValue');
    
    if (progressRange && progressValue) {
        progressRange.addEventListener('input', function() {
            progressValue.textContent = this.value + '%';
        });
    }

    // Form submissions
    document.getElementById('addGoalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'add_goal');
    });

    document.getElementById('editGoalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update_goal');
    });

    document.getElementById('updateProgressForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update_progress');
    });
});

function submitForm(form, action) {
    const formData = new FormData(form);
    formData.append('action', action);
    
    fetch('goal_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while processing the request.'
        });
    });
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const employee = document.getElementById('employeeFilter').value;
    
    let url = 'goal_management.php?';
    if (status !== 'all') url += 'filter=' + status + '&';
    if (employee !== '0') url += 'employee_id=' + employee + '&';
    
    window.location.href = url;
}

function clearFilters() {
    window.location.href = 'goal_management.php';
}

function exportGoals() {
    window.open('goal_management.php?export=1', '_blank');
}

function viewGoal(goalId) {
    const goals = <?= json_encode($goals) ?>;
    const goal = goals.find(g => g.id == goalId);
    
    if (goal) {
        const modalBody = document.getElementById('goalDetails');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Goal Information</h6>
                    <p><strong>Title:</strong> ${goal.goal_title || 'N/A'}</p>
                    <p><strong>Employee:</strong> ${goal.employee_name || 'N/A'}</p>
                    <p><strong>Department:</strong> ${goal.department || 'N/A'}</p>
                    <p><strong>Assigned By:</strong> ${goal.assigned_by_name || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Timeline & Progress</h6>
                    <p><strong>Start Date:</strong> ${goal.start_date || 'N/A'}</p>
                    <p><strong>End Date:</strong> ${goal.end_date || 'N/A'}</p>
                    <p><strong>Priority:</strong> <span class="badge bg-${goal.priority == 'high' ? 'danger' : (goal.priority == 'medium' ? 'warning' : 'info')}">${goal.priority ? goal.priority.charAt(0).toUpperCase() + goal.priority.slice(1) : 'Low'}</span></p>
                    <p><strong>Status:</strong> <span class="badge bg-${goal.status == 'completed' ? 'success' : (goal.status == 'in_progress' ? 'warning' : 'secondary')}">${goal.status ? goal.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Active'}</span></p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Progress</h6>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar" role="progressbar" style="width: ${goal.progress_percentage || 0}%" 
                             aria-valuenow="${goal.progress_percentage || 0}" aria-valuemin="0" aria-valuemax="100">
                            ${goal.progress_percentage || 0}%
                        </div>
                    </div>
                    ${goal.target_value ? `<p><strong>Target:</strong> ${goal.target_value} ${goal.measurement_unit || ''}</p>` : ''}
                    ${goal.current_value ? `<p><strong>Current:</strong> ${goal.current_value} ${goal.measurement_unit || ''}</p>` : ''}
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6>Description</h6>
                    <p>${goal.description || 'No description provided.'}</p>
                    ${goal.notes ? `<h6>Notes</h6><p>${goal.notes}</p>` : ''}
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('viewGoalModal'));
        modal.show();
    }
}

function editGoal(goalId) {
    fetch('goal_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_goal_details&goal_id=' + goalId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const goal = data.goal;
            document.getElementById('edit_goal_id').value = goal.id;
            document.getElementById('edit_goal_title').value = goal.goal_title || '';
            document.getElementById('edit_goal_description').value = goal.description || '';
            document.getElementById('edit_goal_start_date').value = goal.start_date || '';
            document.getElementById('edit_goal_end_date').value = goal.end_date || '';
            document.getElementById('edit_goal_priority').value = goal.priority || 'low';
            document.getElementById('edit_target_value').value = goal.target_value || '';
            document.getElementById('edit_measurement_unit').value = goal.measurement_unit || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editGoalModal'));
            modal.show();
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    });
}

function updateProgress(goalId) {
    const goals = <?= json_encode($goals) ?>;
    const goal = goals.find(g => g.id == goalId);
    
    if (goal) {
        document.getElementById('progress_goal_id').value = goalId;
        document.getElementById('progress_percentage').value = goal.progress_percentage || 0;
        document.getElementById('progressValue').textContent = (goal.progress_percentage || 0) + '%';
        document.getElementById('current_value').value = goal.current_value || '';
        document.getElementById('progress_notes').value = goal.notes || '';
        
        const modal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
        modal.show();
    }
}

function deleteGoal(goalId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_goal');
            formData.append('goal_id', goalId);
            
            fetch('goal_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            });
        }
    });
}
</script>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>
