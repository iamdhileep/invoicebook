<?php
$page_title = "Announcements Management";
session_start();

// Include database connection with absolute path handling
$db_path = __DIR__ . '/../db.php';
if (!file_exists($db_path)) {
    $db_path = '../db.php';
}
require_once $db_path;

// Check authentication - flexible approach
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include header with absolute path handling
$header_path = __DIR__ . '/../layouts/header.php';
if (!file_exists($header_path)) {
    $header_path = '../layouts/header.php';
}
require_once $header_path;

// Check if user is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
$message = '';
$error = '';

// Add new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = $_POST['type'];
    $priority = $_POST['priority'];
    $target_audience = $_POST['target_audience'];
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $publish_date = $_POST['publish_date'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $author_id = $_SESSION['admin']; // Assuming admin ID is stored in session

    if (!empty($title) && !empty($content)) {
        // Get or create author in hr_employees table
        $check_author = $conn->prepare("SELECT id FROM hr_employees WHERE id = ? OR employee_id = ?");
        $check_author->bind_param("is", $author_id, $author_id);
        $check_author->execute();
        $author_result = $check_author->get_result();
        
        if ($author_result->num_rows === 0) {
            // Create author entry in hr_employees
            $insert_author = $conn->prepare("INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department, status, date_of_joining) VALUES (?, 'Admin', 'User', 'admin@company.com', 'Administrator', 'Management', 'active', NOW())");
            $insert_author->bind_param("s", $author_id);
            $insert_author->execute();
            $author_id = $conn->insert_id;
        } else {
            $author_row = $author_result->fetch_assoc();
            $author_id = $author_row['id'];
        }

        $stmt = $conn->prepare("INSERT INTO hr_announcements (title, content, type, priority, target_audience, department_id, author_id, publish_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiiss", $title, $content, $type, $priority, $target_audience, $department_id, $author_id, $publish_date, $expiry_date);
        
        if ($stmt->execute()) {
            $message = "Announcement added successfully!";
        } else {
            $error = "Error adding announcement: " . $conn->error;
        }
    } else {
        $error = "Title and content are required!";
    }
}

// Update announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    $id = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = $_POST['type'];
    $priority = $_POST['priority'];
    $target_audience = $_POST['target_audience'];
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $publish_date = $_POST['publish_date'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE hr_announcements SET title = ?, content = ?, type = ?, priority = ?, target_audience = ?, department_id = ?, publish_date = ?, expiry_date = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssssissii", $title, $content, $type, $priority, $target_audience, $department_id, $publish_date, $expiry_date, $is_active, $id);
    
    if ($stmt->execute()) {
        $message = "Announcement updated successfully!";
    } else {
        $error = "Error updating announcement: " . $conn->error;
    }
}

// Delete announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM hr_announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
    } else {
        $error = "Error deleting announcement: " . $conn->error;
    }
}

// Toggle active status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE hr_announcements SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Announcement status updated successfully!";
    } else {
        $error = "Error updating announcement status: " . $conn->error;
    }
}

// Get all announcements with author details
$announcements_query = "SELECT 
    a.*, 
    CONCAT(e.first_name, ' ', e.last_name) as author_name,
    d.name as department_name
    FROM hr_announcements a 
    LEFT JOIN hr_employees e ON a.author_id = e.id
    LEFT JOIN hr_departments d ON a.department_id = d.id
    ORDER BY a.created_at DESC";
$announcements_result = $conn->query($announcements_query);

// Get departments for dropdown
$departments_query = "SELECT id, name FROM hr_departments ORDER BY name";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($dept = $departments_result->fetch_assoc()) {
    $departments[] = $dept;
}

// Reset departments result for reuse
$departments_result = $conn->query($departments_query);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-bullhorn me-2"></i>Announcements
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">HRMS</a></li>
                    <li class="breadcrumb-item active">Announcements</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="fas fa-plus me-2"></i>Add Announcement
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Announcements</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $announcements_result->num_rows ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bullhorn fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Announcements</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $active_count = $conn->query("SELECT COUNT(*) as count FROM hr_announcements WHERE is_active = 1")->fetch_assoc()['count'];
                                echo $active_count;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Urgent Announcements</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $urgent_count = $conn->query("SELECT COUNT(*) as count FROM hr_announcements WHERE priority = 'urgent' AND is_active = 1")->fetch_assoc()['count'];
                                echo $urgent_count;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">This Month</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $month_count = $conn->query("SELECT COUNT(*) as count FROM hr_announcements WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['count'];
                                echo $month_count;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>All Announcements
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Target</th>
                            <th>Author</th>
                            <th>Publish Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>
                                        <?= strlen($announcement['content']) > 100 ? '...' : '' ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= ucfirst($announcement['type']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $priority_class = '';
                                    switch ($announcement['priority']) {
                                        case 'urgent': $priority_class = 'bg-danger'; break;
                                        case 'high': $priority_class = 'bg-warning'; break;
                                        case 'medium': $priority_class = 'bg-primary'; break;
                                        case 'low': $priority_class = 'bg-secondary'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $priority_class ?>"><?= ucfirst($announcement['priority']) ?></span>
                                </td>
                                <td>
                                    <?= ucfirst($announcement['target_audience']) ?>
                                    <?php if ($announcement['department_name']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($announcement['department_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($announcement['author_name'] ?? 'Unknown') ?>
                                    <br>
                                    <small class="text-muted"><?= date('M j, Y', strtotime($announcement['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($announcement['publish_date'])) ?>
                                    <?php if ($announcement['expiry_date']): ?>
                                        <br><small class="text-danger">Expires: <?= date('M j, Y', strtotime($announcement['expiry_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($announcement['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-info" onclick="viewAnnouncement(<?= $announcement['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editAnnouncement(<?= $announcement['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?toggle=<?= $announcement['id'] ?>" class="btn btn-sm btn-<?= $announcement['is_active'] ? 'secondary' : 'success' ?>" onclick="return confirm('Toggle announcement status?')">
                                            <i class="fas fa-<?= $announcement['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </a>
                                        <a href="?delete=<?= $announcement['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="general">General</option>
                                <option value="policy">Policy</option>
                                <option value="event">Event</option>
                                <option value="urgent">Urgent</option>
                                <option value="celebration">Celebration</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="target_audience" class="form-label">Target Audience</label>
                            <select class="form-select" id="target_audience" name="target_audience" onchange="toggleDepartment()">
                                <option value="all">All Employees</option>
                                <option value="management">Management</option>
                                <option value="employees">Employees Only</option>
                                <option value="department">Specific Department</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" disabled>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="publish_date" class="form-label">Publish Date</label>
                            <input type="date" class="form-control" id="publish_date" name="publish_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="content" class="form-label">Content *</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_announcement" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_announcement_id" name="announcement_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_type" class="form-label">Type</label>
                            <select class="form-select" id="edit_type" name="type">
                                <option value="general">General</option>
                                <option value="policy">Policy</option>
                                <option value="event">Event</option>
                                <option value="urgent">Urgent</option>
                                <option value="celebration">Celebration</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit_priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_target_audience" class="form-label">Target Audience</label>
                            <select class="form-select" id="edit_target_audience" name="target_audience" onchange="toggleEditDepartment()">
                                <option value="all">All Employees</option>
                                <option value="management">Management</option>
                                <option value="employees">Employees Only</option>
                                <option value="department">Specific Department</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_department_id" class="form-label">Department</label>
                            <select class="form-select" id="edit_department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_publish_date" class="form-label">Publish Date</label>
                            <input type="date" class="form-control" id="edit_publish_date" name="publish_date">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_expiry_date" class="form-label">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="edit_content" class="form-label">Content *</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_announcement" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>View Announcement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewAnnouncementContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle department dropdown based on target audience
function toggleDepartment() {
    const targetAudience = document.getElementById('target_audience').value;
    const departmentSelect = document.getElementById('department_id');
    
    if (targetAudience === 'department') {
        departmentSelect.disabled = false;
        departmentSelect.required = true;
    } else {
        departmentSelect.disabled = true;
        departmentSelect.required = false;
        departmentSelect.value = '';
    }
}

function toggleEditDepartment() {
    const targetAudience = document.getElementById('edit_target_audience').value;
    const departmentSelect = document.getElementById('edit_department_id');
    
    if (targetAudience === 'department') {
        departmentSelect.disabled = false;
        departmentSelect.required = true;
    } else {
        departmentSelect.disabled = true;
        departmentSelect.required = false;
        departmentSelect.value = '';
    }
}

// View announcement function
function viewAnnouncement(id) {
    fetch(`get_announcement.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const announcement = data.announcement;
                const content = `
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">${announcement.title}</h4>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    <span class="badge bg-info me-2">${announcement.type}</span>
                                    <span class="badge bg-${getPriorityClass(announcement.priority)}">${announcement.priority}</span>
                                </div>
                                <small class="text-muted">By ${announcement.author_name || 'Unknown'}</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="card-text">${announcement.content}</p>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Target Audience:</strong> ${announcement.target_audience}<br>
                                    ${announcement.department_name ? `<strong>Department:</strong> ${announcement.department_name}<br>` : ''}
                                    <strong>Publish Date:</strong> ${new Date(announcement.publish_date).toLocaleDateString()}
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong> ${announcement.is_active ? '<span class="text-success">Active</span>' : '<span class="text-secondary">Inactive</span>'}<br>
                                    ${announcement.expiry_date ? `<strong>Expires:</strong> ${new Date(announcement.expiry_date).toLocaleDateString()}<br>` : ''}
                                    <strong>Created:</strong> ${new Date(announcement.created_at).toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('viewAnnouncementContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('viewAnnouncementModal')).show();
            } else {
                alert('Error loading announcement details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading announcement details');
        });
}

// Edit announcement function
function editAnnouncement(id) {
    fetch(`get_announcement.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const announcement = data.announcement;
                document.getElementById('edit_announcement_id').value = announcement.id;
                document.getElementById('edit_title').value = announcement.title;
                document.getElementById('edit_type').value = announcement.type;
                document.getElementById('edit_priority').value = announcement.priority;
                document.getElementById('edit_target_audience').value = announcement.target_audience;
                document.getElementById('edit_department_id').value = announcement.department_id || '';
                document.getElementById('edit_publish_date').value = announcement.publish_date;
                document.getElementById('edit_expiry_date').value = announcement.expiry_date || '';
                document.getElementById('edit_is_active').checked = announcement.is_active == 1;
                document.getElementById('edit_content').value = announcement.content;
                
                toggleEditDepartment();
                new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
            } else {
                alert('Error loading announcement details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading announcement details');
        });
}

function getPriorityClass(priority) {
    switch(priority) {
        case 'urgent': return 'danger';
        case 'high': return 'warning';
        case 'medium': return 'primary';
        case 'low': return 'secondary';
        default: return 'secondary';
    }
}

// Initialize DataTable
$(document).ready(function() {
    $('#dataTable').DataTable({
        "order": [[ 5, "desc" ]],
        "pageLength": 25
    });
});
</script>

<?php
// Include footer with absolute path handling
$footer_path = __DIR__ . '/../layouts/footer.php';
if (!file_exists($footer_path)) {
    $footer_path = '../layouts/footer.php';
}
require_once $footer_path;
?>
