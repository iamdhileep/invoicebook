<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';

$page_title = 'User Profile';
$current_user_id = $_SESSION['admin'] ?? $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_profile':
            $result = updateUserProfile($conn, $current_user_id, $_POST);
            echo json_encode($result);
            exit;
            
        case 'change_password':
            $result = changeUserPassword($conn, $current_user_id, $_POST);
            echo json_encode($result);
            exit;
            
        case 'upload_avatar':
            $result = uploadUserAvatar($conn, $current_user_id, $_FILES);
            echo json_encode($result);
            exit;
            
        case 'get_activity_log':
            $result = getUserActivityLog($conn, $current_user_id);
            echo json_encode($result);
            exit;
    }
}

// Get user information
function getCurrentUser($conn, $userId) {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id) as total_sessions,
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND status = 'active') as active_sessions
              FROM users u WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function updateUserProfile($conn, $userId, $data) {
    $updates = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['full_name', 'email', 'phone', 'address', 'department', 'position'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= "s";
        }
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'No fields to update'];
    }
    
    $params[] = $userId;
    $types .= "i";
    
    $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $userId, 'profile_updated', 'Updated profile information');
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error updating profile: ' . $stmt->error];
    }
}

function changeUserPassword($conn, $userId, $data) {
    if (empty($data['current_password']) || empty($data['new_password'])) {
        return ['success' => false, 'message' => 'Current password and new password are required'];
    }
    
    if ($data['new_password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'New passwords do not match'];
    }
    
    // Get current password hash
    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    if (!password_verify($data['current_password'], $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
    
    $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $newPasswordHash, $userId);
    
    if ($updateStmt->execute()) {
        logUserActivity($conn, $userId, 'password_changed', 'Changed password');
        return ['success' => true, 'message' => 'Password changed successfully'];
    } else {
        return ['success' => false, 'message' => 'Error changing password: ' . $updateStmt->error];
    }
}

function uploadUserAvatar($conn, $userId, $files) {
    if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    $file = $files['avatar'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Only JPEG, PNG, and GIF images are allowed'];
    }
    
    if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
        return ['success' => false, 'message' => 'File size must be less than 2MB'];
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../../uploads/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $avatar_url = 'uploads/avatars/' . $filename;
        
        $updateQuery = "UPDATE users SET avatar = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $avatar_url, $userId);
        
        if ($updateStmt->execute()) {
            logUserActivity($conn, $userId, 'avatar_updated', 'Updated profile avatar');
            return ['success' => true, 'message' => 'Avatar updated successfully', 'avatar_url' => $avatar_url];
        } else {
            unlink($filepath); // Delete uploaded file on database error
            return ['success' => false, 'message' => 'Error saving avatar to database'];
        }
    } else {
        return ['success' => false, 'message' => 'Error uploading file'];
    }
}

function getUserActivityLog($conn, $userId) {
    $query = "SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    return ['success' => true, 'activities' => $activities];
}

function logUserActivity($conn, $userId, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $query = "INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $userId, $action, $description, $ip);
    $stmt->execute();
}

$current_user = getCurrentUser($conn, $current_user_id);
if (!$current_user) {
    die('User not found');
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">👤 User Profile</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-person-gear"></i> 
                        Manage your account settings and preferences
                        <span class="badge bg-light text-dark ms-2">Personal Settings</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="downloadProfile()">
                        <i class="bi bi-download"></i> Export Profile
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <!-- Profile Information -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-person-lines-fill"></i> Profile Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="profileForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($current_user['full_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($current_user['username']) ?>" readonly>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($current_user['email']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Department</label>
                                        <input type="text" class="form-control" name="department" value="<?= htmlspecialchars($current_user['department'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" name="position" value="<?= htmlspecialchars($current_user['position'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($current_user['address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                        <i class="bi bi-check-lg"></i> Update Profile
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Change -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-key"></i> Change Password
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="passwordForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <div></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="6">
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-warning" onclick="changePassword()">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile Sidebar -->
                <div class="col-lg-4">
                    <!-- Avatar -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-image"></i> Profile Picture
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if (!empty($current_user['avatar'])): ?>
                                    <img src="../../<?= htmlspecialchars($current_user['avatar']) ?>" class="rounded-circle" width="120" height="120" id="avatarPreview">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold" style="width: 120px; height: 120px; font-size: 3rem;" id="avatarPreview">
                                        <?= strtoupper(substr($current_user['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form id="avatarForm" enctype="multipart/form-data">
                                <input type="file" class="form-control" id="avatarFile" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar()">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('avatarFile').click()">
                                    <i class="bi bi-camera"></i> Change Photo
                                </button>
                                <button type="button" class="btn btn-success btn-sm" onclick="uploadAvatar()" style="display: none;" id="uploadBtn">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-info-circle"></i> Account Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Role:</strong></td>
                                    <td>
                                        <?php
                                        $role_badges = [
                                            'admin' => '<span class="badge bg-danger">Administrator</span>',
                                            'manager' => '<span class="badge bg-warning">Manager</span>',
                                            'employee' => '<span class="badge bg-info">Employee</span>',
                                            'user' => '<span class="badge bg-primary">User</span>'
                                        ];
                                        echo $role_badges[$current_user['role']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'active' => '<span class="badge bg-success">Active</span>',
                                            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
                                            'suspended' => '<span class="badge bg-danger">Suspended</span>'
                                        ];
                                        echo $status_badges[$current_user['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Member Since:</strong></td>
                                    <td><?= date('M d, Y', strtotime($current_user['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Sessions:</strong></td>
                                    <td><?= $current_user['total_sessions'] ?? 0 ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Active Sessions:</strong></td>
                                    <td><?= $current_user['active_sessions'] ?? 0 ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Last Login:</strong></td>
                                    <td><?= $current_user['last_login'] ? date('M d, Y H:i', strtotime($current_user['last_login'])) : 'Never' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-clock-history"></i> Recent Activity
                            </h6>
                            <button class="btn btn-outline-primary btn-sm" onclick="loadActivityLog()">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="activityLog">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
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

<script>
// Load activity log on page load
document.addEventListener('DOMContentLoaded', function() {
    loadActivityLog();
});

function updateProfile() {
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);
    formData.append('action', 'update_profile');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error updating profile');
    });
}

function changePassword() {
    const form = document.getElementById('passwordForm');
    const formData = new FormData(form);
    formData.append('action', 'change_password');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            form.reset();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error changing password');
    });
}

function previewAvatar() {
    const fileInput = document.getElementById('avatarFile');
    const preview = document.getElementById('avatarPreview');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = '';
            preview.style.backgroundImage = `url(${e.target.result})`;
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center';
            uploadBtn.style.display = 'inline-block';
        }
        
        reader.readAsDataURL(fileInput.files[0]);
    }
}

function uploadAvatar() {
    const form = document.getElementById('avatarForm');
    const formData = new FormData(form);
    formData.append('action', 'upload_avatar');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            document.getElementById('uploadBtn').style.display = 'none';
            // Update avatar preview
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = `<img src="../../${data.avatar_url}" class="rounded-circle" width="120" height="120">`;
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error uploading avatar');
    });
}

function loadActivityLog() {
    const formData = new FormData();
    formData.append('action', 'get_activity_log');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayActivityLog(data.activities);
        } else {
            document.getElementById('activityLog').innerHTML = '<div class="text-danger small">Error loading activities</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('activityLog').innerHTML = '<div class="text-danger small">Network error</div>';
    });
}

function displayActivityLog(activities) {
    const container = document.getElementById('activityLog');
    let html = '';
    
    if (activities.length === 0) {
        html = '<div class="text-muted small">No recent activities</div>';
    } else {
        activities.slice(0, 10).forEach(activity => {
            const date = new Date(activity.created_at).toLocaleDateString();
            const time = new Date(activity.created_at).toLocaleTimeString();
            
            html += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="small fw-semibold">${activity.action.replace('_', ' ').toUpperCase()}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${activity.description}</div>
                    </div>
                    <div class="text-muted" style="font-size: 0.7rem;">
                        ${date}<br>${time}
                    </div>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

function resetForm() {
    document.getElementById('profileForm').reset();
    // Reset to original values
    location.reload();
}

function downloadProfile() {
    // Create profile data object
    const profileData = {
        username: '<?= htmlspecialchars($current_user['username']) ?>',
        full_name: '<?= htmlspecialchars($current_user['full_name']) ?>',
        email: '<?= htmlspecialchars($current_user['email']) ?>',
        role: '<?= htmlspecialchars($current_user['role']) ?>',
        department: '<?= htmlspecialchars($current_user['department'] ?? '') ?>',
        position: '<?= htmlspecialchars($current_user['position'] ?? '') ?>',
        phone: '<?= htmlspecialchars($current_user['phone'] ?? '') ?>',
        address: '<?= htmlspecialchars($current_user['address'] ?? '') ?>',
        created_at: '<?= htmlspecialchars($current_user['created_at']) ?>',
        status: '<?= htmlspecialchars($current_user['status']) ?>'
    };
    
    // Convert to JSON and download
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(profileData, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", "profile_data.json");
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
    
    showAlert('success', 'Profile data exported successfully');
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
