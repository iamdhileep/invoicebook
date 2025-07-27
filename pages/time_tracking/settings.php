<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
include '../../layouts/header.php';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'office_start_time' => $_POST['office_start_time'],
        'office_end_time' => $_POST['office_end_time'],
        'late_threshold' => $_POST['late_threshold'],
        'overtime_threshold' => $_POST['overtime_threshold'],
        'break_duration' => $_POST['break_duration'],
        'timezone' => $_POST['timezone']
    ];
    
    foreach ($settings as $name => $value) {
        $query = $conn->prepare("
            INSERT INTO time_tracking_settings (setting_name, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $query->bind_param("ss", $name, $value);
        $query->execute();
    }
    
    $success_message = "Settings updated successfully!";
}

// Get current settings
$settings = [];
$query = "SELECT setting_name, setting_value FROM time_tracking_settings";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
?>

<style>
    .settings-container {
        padding: 20px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .settings-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .setting-section {
        border-bottom: 1px solid #eee;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    
    .setting-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
</style>

<div class="main-content">
    <?php include '../../layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="settings-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Time Tracking Settings</h1>
                    <p class="text-muted">Configure time tracking system parameters</p>
                </div>
                <a href="time_tracking.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Time Tracking
                </a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="settings-card">
                <form method="POST">
                    <!-- Work Hours Settings -->
                    <div class="setting-section">
                        <h5 class="mb-3"><i class="bi bi-clock me-2"></i>Work Hours</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Office Start Time</label>
                                    <input type="time" class="form-control" name="office_start_time" 
                                           value="<?= $settings['office_start_time'] ?? '09:00' ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Office End Time</label>
                                    <input type="time" class="form-control" name="office_end_time" 
                                           value="<?= $settings['office_end_time'] ?? '18:00' ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Settings -->
                    <div class="setting-section">
                        <h5 class="mb-3"><i class="bi bi-person-check me-2"></i>Attendance Rules</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Late Threshold (minutes)</label>
                                    <input type="number" class="form-control" name="late_threshold" 
                                           value="<?= $settings['late_threshold'] ?? '15' ?>" min="0" max="60" required>
                                    <div class="form-text">Minutes after start time to consider as late</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Break Duration (minutes)</label>
                                    <input type="number" class="form-control" name="break_duration" 
                                           value="<?= $settings['break_duration'] ?? '60' ?>" min="0" max="120" required>
                                    <div class="form-text">Standard break/lunch duration</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overtime Settings -->
                    <div class="setting-section">
                        <h5 class="mb-3"><i class="bi bi-clock-fill me-2"></i>Overtime Rules</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Overtime Threshold (hours)</label>
                                    <input type="number" class="form-control" name="overtime_threshold" 
                                           value="<?= $settings['overtime_threshold'] ?? '8.5' ?>" 
                                           min="6" max="12" step="0.5" required>
                                    <div class="form-text">Hours after which overtime calculation starts</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select" name="timezone" required>
                                        <option value="Asia/Kolkata" <?= ($settings['timezone'] ?? 'Asia/Kolkata') === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                                        <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                                        <option value="Europe/London" <?= ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT)</option>
                                        <option value="Asia/Tokyo" <?= ($settings['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Asia/Tokyo (JST)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="settings-card text-center">
                        <i class="bi bi-people-fill text-primary fs-1 mb-3"></i>
                        <h5>Active Employees</h5>
                        <?php
                        $empCount = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch_assoc()['count'];
                        ?>
                        <h3 class="text-primary"><?= $empCount ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="settings-card text-center">
                        <i class="bi bi-calendar-check text-success fs-1 mb-3"></i>
                        <h5>Today's Attendance</h5>
                        <?php
                        $todayCount = $conn->query("SELECT COUNT(*) as count FROM time_clock WHERE clock_date = CURDATE() AND clock_in IS NOT NULL")->fetch_assoc()['count'];
                        ?>
                        <h3 class="text-success"><?= $todayCount ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="settings-card text-center">
                        <i class="bi bi-clock-history text-warning fs-1 mb-3"></i>
                        <h5>Pending Requests</h5>
                        <?php
                        $pendingCount = $conn->query("SELECT 
                            (SELECT COUNT(*) FROM time_off_requests WHERE status = 'Pending') + 
                            (SELECT COUNT(*) FROM overtime_requests WHERE status = 'Pending') as count")->fetch_assoc()['count'];
                        ?>
                        <h3 class="text-warning"><?= $pendingCount ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>
