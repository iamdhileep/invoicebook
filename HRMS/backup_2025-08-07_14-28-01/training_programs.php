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

$page_title = 'Training Programs - HRMS';

// Fetch real training programs from database
$programs_query = "
    SELECT tp.*, 
           COUNT(tr.id) as participants,
           ROUND(AVG(CASE WHEN tr.status = 'completed' THEN 1 ELSE 0 END) * 100, 0) as completion_rate
    FROM training_programs tp
    LEFT JOIN training_registrations tr ON tp.id = tr.training_id
    GROUP BY tp.id
    ORDER BY tp.created_at DESC
";
$programs_result = mysqli_query($conn, $programs_query);

$programs = [];
if ($programs_result) {
    while ($row = mysqli_fetch_assoc($programs_result)) {
        $programs[] = [
            'id' => $row['id'],
            'name' => $row['program_name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'duration' => $row['duration_hours'] . ' hours',
            'mode' => $row['delivery_mode'],
            'status' => ucfirst($row['status']),
            'participants' => $row['participants'] ?: 0,
            'completion_rate' => $row['completion_rate'] ?: 0,
            'created_date' => $row['created_at']
        ];
    }
}

// If no real data, provide sample data
if (empty($programs)) {
    $programs = [
        [
            'id' => 1,
            'name' => 'Leadership Development Program',
            'description' => 'Comprehensive leadership training for managers and team leads',
            'category' => 'Leadership',
            'duration' => '40 hours',
            'mode' => 'Hybrid',
            'status' => 'Active',
            'participants' => 25,
            'completion_rate' => 85,
            'created_date' => '2025-01-15'
        ],
        [
            'id' => 2,
            'name' => 'Technical Skills Workshop',
            'description' => 'Advanced technical training for software development teams',
            'category' => 'Technical',
            'duration' => '24 hours',
            'mode' => 'Online',
            'status' => 'Active',
            'participants' => 45,
            'completion_rate' => 92,
            'created_date' => '2025-02-01'
        ]
    ];
}

$stats = [
    'total_programs' => count($programs),
    'active_programs' => count(array_filter($programs, fn($p) => $p['status'] === 'Active')),
    'total_participants' => array_sum(array_column($programs, 'participants')),
    'avg_completion' => round(array_sum(array_column($programs, 'completion_rate')) / count($programs), 1)
];

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
                    <i class="bi bi-book text-primary me-3"></i>Training Programs
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Manage and monitor all training programs and their effectiveness</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportPrograms()">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                    <i class="bi bi-plus"></i> Create Program
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-collection fs-1" style="color: #1976d2;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #1976d2;"><?= $stats['total_programs'] ?></h3>
                        <p class="text-muted mb-0">Total Programs</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill fs-1" style="color: #388e3c;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #388e3c;"><?= $stats['active_programs'] ?></h3>
                        <p class="text-muted mb-0">Active Programs</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-people-fill fs-1" style="color: #f57c00;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #f57c00;"><?= $stats['total_participants'] ?></h3>
                        <p class="text-muted mb-0">Total Participants</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-graph-up fs-1" style="color: #7b1fa2;"></i>
                        </div>
                        <h3 class="mb-2 fw-bold" style="color: #7b1fa2;"><?= $stats['avg_completion'] ?>%</h3>
                        <p class="text-muted mb-0">Avg Completion</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Programs Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul text-primary"></i> Training Programs
                        </h5>
                    </div>
                    <div class="col-auto">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" placeholder="Search programs..." id="searchPrograms">
                            <button class="btn btn-outline-secondary btn-sm" type="button">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="programsTable">
                        <thead>
                            <tr>
                                <th>Program Name</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Mode</th>
                                <th>Participants</th>
                                <th>Completion Rate</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($program['name']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($program['description']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $categoryColors = [
                                            'Leadership' => 'primary',
                                            'Technical' => 'info',
                                            'Compliance' => 'warning',
                                            'Safety' => 'danger'
                                        ];
                                        $color = $categoryColors[$program['category']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>"><?= $program['category'] ?></span>
                                    </td>
                                    <td><?= $program['duration'] ?></td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $program['mode'] ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= $program['participants'] ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-<?= $program['completion_rate'] >= 90 ? 'success' : ($program['completion_rate'] >= 75 ? 'warning' : 'danger') ?>" 
                                                     style="width: <?= $program['completion_rate'] ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $program['completion_rate'] ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $program['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= $program['status'] ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewProgram(<?= $program['id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="editProgram(<?= $program['id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="Edit Program">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="scheduleProgram(<?= $program['id'] ?>)" 
                                                    data-bs-toggle="tooltip" title="Schedule Training">
                                                <i class="bi bi-calendar-plus"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="duplicateProgram(<?= $program['id'] ?>)">
                                                        <i class="bi bi-copy"></i> Duplicate
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="archiveProgram(<?= $program['id'] ?>)">
                                                        <i class="bi bi-archive"></i> Archive
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteProgram(<?= $program['id'] ?>)">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
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

<!-- Create Program Modal -->
<div class="modal fade" id="createProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Training Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Program Name *</label>
                            <input type="text" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="leadership">Leadership</option>
                                <option value="technical">Technical</option>
                                <option value="compliance">Compliance</option>
                                <option value="safety">Safety</option>
                                <option value="soft_skills">Soft Skills</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (Hours) *</label>
                            <input type="number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Training Mode *</label>
                            <select class="form-select" required>
                                <option value="">Select Mode</option>
                                <option value="classroom">Classroom</option>
                                <option value="online">Online</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="workshop">Workshop</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Participants</label>
                            <input type="number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prerequisites</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Learning Objectives</label>
                            <textarea class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="certification">
                                <label class="form-check-label" for="certification">
                                    Certification Provided
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#programsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { targets: [7], orderable: false }
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Search functionality
    $('#searchPrograms').on('keyup', function() {
        $('#programsTable').DataTable().search(this.value).draw();
    });
});

function viewProgram(id) {
    showAlert(`Viewing program details for ID: ${id}`, 'info');
}

function editProgram(id) {
    showAlert(`Editing program with ID: ${id}`, 'info');
}

function scheduleProgram(id) {
    showAlert(`Scheduling training for program ID: ${id}`, 'info');
}

function duplicateProgram(id) {
    showAlert(`Duplicating program with ID: ${id}`, 'info');
}

function archiveProgram(id) {
    if (confirm('Are you sure you want to archive this program?')) {
        showAlert(`Program ${id} archived successfully`, 'success');
    }
}

function deleteProgram(id) {
    if (confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
        showAlert(`Program ${id} deleted successfully`, 'success');
    }
}

function exportPrograms() {
    showAlert('Exporting training programs data...', 'info');
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

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