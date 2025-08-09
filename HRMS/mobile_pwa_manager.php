<?php
$page_title = "Mobile PWA Manager";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts - using global layouts for consistency
require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle AJAX requests for PWA functionality
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'register_device':
            $userId = $_POST['user_id'] ?? $currentUserId;
            $deviceInfo = $_POST['device_info'] ?? '';
            $pushToken = $_POST['push_token'] ?? '';
            
            try {
                // Store device registration
                $stmt = $conn->prepare("
                    INSERT INTO hr_mobile_devices (user_id, device_info, push_token, registered_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    device_info = VALUES(device_info),
                    push_token = VALUES(push_token),
                    last_active = NOW()
                ");
                $stmt->bind_param('iss', $userId, $deviceInfo, $pushToken);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Device registered successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to register device']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'quick_clock':
            $action = $_POST['clock_action'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $accuracy = $_POST['accuracy'] ?? '';
            
            try {
                // Get current employee by email
                $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $userStmt->bind_param('i', $currentUserId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                
                if (!$userResult || $userResult->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    exit;
                }
                
                $userData = $userResult->fetch_assoc();
                $stmt = $conn->prepare("SELECT id FROM hr_employees WHERE email = ?");
                $stmt->bind_param('s', $userData['email']);
                $stmt->execute();
                $empResult = $stmt->get_result();
                
                if (!$empResult || $empResult->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
                $employee = $empResult->fetch_assoc();
                
                $today = date('Y-m-d');
                $now = date('Y-m-d H:i:s');
                $location = "GPS: {$latitude},{$longitude} (±{$accuracy}m)";
                
                if ($action === 'in') {
                    // Check if already clocked in
                    $stmt = $conn->prepare("
                        SELECT id FROM hr_attendance 
                        WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
                    ");
                    $stmt->bind_param('is', $employee['id'], $today);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
                        exit;
                    }
                    
                    // Clock in
                    $stmt = $conn->prepare("
                        INSERT INTO hr_attendance 
                        (employee_id, date, clock_in_time, clock_in_location, status, mobile_clock_in) 
                        VALUES (?, ?, ?, ?, 'present', 1)
                    ");
                    $stmt->bind_param('isss', $employee['id'], $today, $now, $location);
                    
                    if ($stmt->execute()) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Clocked in successfully',
                            'time' => date('g:i A', strtotime($now))
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to clock in']);
                    }
                    
                } else if ($action === 'out') {
                    // Find today's attendance record
                    $stmt = $conn->prepare("
                        SELECT id, clock_in_time FROM hr_attendance 
                        WHERE employee_id = ? AND date = ? AND clock_out_time IS NULL
                    ");
                    $stmt->bind_param('is', $employee['id'], $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        echo json_encode(['success' => false, 'message' => 'No clock-in record found']);
                        exit;
                    }
                    
                    $attendance = $result->fetch_assoc();
                    
                    // Calculate hours worked
                    $clockIn = new DateTime($attendance['clock_in_time']);
                    $clockOut = new DateTime($now);
                    $hoursWorked = $clockIn->diff($clockOut)->h + ($clockIn->diff($clockOut)->i / 60);
                    
                    // Clock out
                    $stmt = $conn->prepare("
                        UPDATE hr_attendance 
                        SET clock_out_time = ?, clock_out_location = ?, hours_worked = ?, mobile_clock_out = 1
                        WHERE id = ?
                    ");
                    $stmt->bind_param('ssdi', $now, $location, $hoursWorked, $attendance['id']);
                    
                    if ($stmt->execute()) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Clocked out successfully',
                            'time' => date('g:i A', strtotime($now)),
                            'hours_worked' => round($hoursWorked, 2)
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to clock out']);
                    }
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_notifications':
            try {
                $stmt = $conn->prepare("
                    SELECT * FROM hr_notifications 
                    WHERE user_id = ? OR user_id IS NULL 
                    ORDER BY created_at DESC 
                    LIMIT 20
                ");
                $stmt->bind_param('i', $currentUserId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $notifications = [];
                while ($row = $result->fetch_assoc()) {
                    $notifications[] = $row;
                }
                
                echo json_encode(['success' => true, 'notifications' => $notifications]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'mark_notification_read':
            $notificationId = $_POST['notification_id'] ?? 0;
            
            try {
                $stmt = $conn->prepare("
                    UPDATE hr_notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->bind_param('ii', $notificationId, $currentUserId);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get mobile statistics
$mobileStats = [];
try {
    // Get mobile usage statistics with optimized single query
    $result = $conn->query("
        SELECT 
            (SELECT COUNT(DISTINCT user_id) FROM hr_mobile_devices) as registered_devices,
            (SELECT COUNT(*) FROM hr_mobile_devices) as total_registrations,
            (SELECT COUNT(*) FROM hr_mobile_devices WHERE last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_last_week,
            (SELECT COUNT(*) FROM hr_attendance WHERE date = CURDATE() AND (mobile_clock_in = 1 OR mobile_clock_out = 1)) as mobile_clocks_today
    ");
    
    if ($result && $result->num_rows > 0) {
        $mobileStats = $result->fetch_assoc();
    } else {
        $mobileStats = ['registered_devices' => 0, 'total_registrations' => 0, 'active_last_week' => 0, 'mobile_clocks_today' => 0];
    }
    
} catch (Exception $e) {
    error_log("Mobile stats error: " . $e->getMessage());
}

// Get current employee info for quick actions
$currentEmployee = null;
if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
    try {
        // Get user email first
        $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->bind_param('i', $currentUserId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult && $userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $userEmail = $userData['email'];
            
            // Now get employee data using email
            $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE email = ?");
            $stmt->bind_param('s', $userEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $currentEmployee = $result->fetch_assoc();
            }
        }
    } catch (Exception $e) {
        error_log("Current employee fetch error: " . $e->getMessage());
    }
}

// Check if user is clocked in
$isClockedIn = false;
if ($currentEmployee) {
    try {
        $stmt = $conn->prepare("
            SELECT id FROM hr_attendance 
            WHERE employee_id = ? AND date = CURDATE() AND clock_out_time IS NULL
        ");
        $stmt->bind_param('i', $currentEmployee['id']);
        $stmt->execute();
        $isClockedIn = $stmt->get_result()->num_rows > 0;
    } catch (Exception $e) {
        error_log("Clock status check error: " . $e->getMessage());
    }
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📱 Mobile PWA Manager</h1>
                <p class="text-muted">Progressive Web App with mobile optimization and real-time features</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="installPWA()">
                    <i class="fas fa-download me-1"></i>Install App
                        </button>
                        <button class="btn btn-outline-secondary" onclick="testNotification()">
                            <i class="fas fa-bell me-1"></i>Test Notification
                        </button>
                    </div>
                </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="fas fa-mobile-alt fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $mobileStats['registered_devices'] ?? 0 ?></h3>
                        <p class="mb-0 small">Registered Devices</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="fas fa-clock fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $mobileStats['mobile_clocks_today'] ?? 0 ?></h3>
                        <p class="mb-0 small">Mobile Clock-ins Today</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="fas fa-bell fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $mobileStats['active_last_week'] ?? 0 ?></h3>
                        <p class="mb-0 small">Active This Week</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="fas fa-sync fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $mobileStats['total_registrations'] ?? 0 ?></h3>
                        <p class="mb-0 small">Total Registrations</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- PWA Features -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm pwa-card">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-smartphone text-success me-2"></i>
                            Progressive Web App Features
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="feature-item">
                                    <div class="feature-icon bg-success bg-opacity-10">
                                        <i class="fas fa-wifi text-success"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6 class="mb-1">Offline Support</h6>
                                        <p class="text-muted mb-0 small">Work without internet connection</p>
                                    </div>
                                    <div class="feature-status">
                                        <span class="badge bg-success" id="offlineStatus">Active</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="feature-item">
                                    <div class="feature-icon bg-primary bg-opacity-10">
                                        <i class="fas fa-bell text-primary"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6 class="mb-1">Push Notifications</h6>
                                        <p class="text-muted mb-0 small">Real-time alerts and updates</p>
                                    </div>
                                    <div class="feature-status">
                                        <span class="badge bg-warning" id="notificationStatus">Permission Needed</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="feature-item">
                                    <div class="feature-icon bg-info bg-opacity-10">
                                        <i class="fas fa-map-marker-alt text-info"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6 class="mb-1">Location Services</h6>
                                        <p class="text-muted mb-0 small">GPS-based attendance tracking</p>
                                    </div>
                                    <div class="feature-status">
                                        <span class="badge bg-secondary" id="locationStatus">Checking...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="feature-item">
                                    <div class="feature-icon bg-warning bg-opacity-10">
                                        <i class="fas fa-sync text-warning"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6 class="mb-1">Background Sync</h6>
                                        <p class="text-muted mb-0 small">Automatic data synchronization</p>
                                    </div>
                                    <div class="feature-status">
                                        <span class="badge bg-success" id="syncStatus">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-chart-bar text-info me-2"></i>
                            Mobile Usage Stats
                        </h6>
                        
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Registered Devices</span>
                                <span class="fw-bold"><?= $mobileStats['registered_devices'] ?? 0 ?></span>
                            </div>
                        </div>
                        
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Active This Week</span>
                                <span class="fw-bold text-success"><?= $mobileStats['active_last_week'] ?? 0 ?></span>
                            </div>
                        </div>
                        
                        <div class="stat-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Mobile Clocks Today</span>
                                <span class="fw-bold text-primary"><?= $mobileStats['mobile_clocks_today'] ?? 0 ?></span>
                            </div>
                        </div>
                        
                        <div class="progress mb-2" style="height: 8px;">
                            <?php
                            $usagePercent = $mobileStats['registered_devices'] > 0 ? 
                                            ($mobileStats['active_last_week'] / $mobileStats['registered_devices']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-gradient" style="width: <?= $usagePercent ?>%"></div>
                        </div>
                        <small class="text-muted">Mobile adoption rate: <?= round($usagePercent) ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Mobile Actions -->
        <?php if ($currentEmployee): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm quick-actions-card">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-zap text-warning me-2"></i>
                            Quick Mobile Actions
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="quick-action-item">
                                    <div class="quick-action-content">
                                        <h6 class="mb-1">Quick Clock In/Out</h6>
                                        <p class="text-muted mb-3">One-tap attendance with GPS location</p>
                                        
                                        <?php if (!$isClockedIn): ?>
                                            <button class="btn btn-success" onclick="quickClock('in')">
                                                <i class="fas fa-sign-in-alt me-1"></i>Clock In Now
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-danger" onclick="quickClock('out')">
                                                <i class="fas fa-sign-out-alt me-1"></i>Clock Out Now
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="quick-action-item">
                                    <div class="quick-action-content">
                                        <h6 class="mb-1">Real-time Notifications</h6>
                                        <p class="text-muted mb-3">Get instant updates and alerts</p>
                                        
                                        <button class="btn btn-primary" onclick="loadNotifications()">
                                            <i class="fas fa-bell me-1"></i>View Notifications
                                            <span class="badge bg-light text-dark ms-1" id="notificationCount">0</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mobile Device Management -->
        <?php if ($currentUserRole === 'hr' || $currentUserRole === 'admin'): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-mobile-alt text-secondary me-2"></i>
                            Mobile Device Management
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="deviceTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Device Info</th>
                                        <th>Registered</th>
                                        <th>Last Active</th>
                                        <th>Push Token</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $result = $conn->query("
                                            SELECT 
                                                md.*,
                                                u.username,
                                                e.first_name, e.last_name
                                            FROM hr_mobile_devices md
                                            LEFT JOIN users u ON md.user_id = u.id
                                            LEFT JOIN hr_employees e ON u.email = e.email
                                            ORDER BY md.last_active DESC
                                            LIMIT 100
                                        ");
                                        
                                        if ($result && $result->num_rows > 0) {
                                            while ($device = $result->fetch_assoc()) {
                                    ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-medium">
                                                        <?= htmlspecialchars(($device['first_name'] ?? '') . ' ' . ($device['last_name'] ?? '')) ?: htmlspecialchars($device['username'] ?? 'Unknown User') ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars(substr($device['device_info'] ?? 'Unknown Device', 0, 30)) ?>
                                                    <?= strlen($device['device_info'] ?? '') > 30 ? '...' : '' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $device['registered_at'] ? date('M j, Y g:i A', strtotime($device['registered_at'])) : 'Unknown' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                if ($device['last_active']) {
                                                    $lastActive = strtotime($device['last_active']);
                                                    $diff = time() - $lastActive;
                                                    if ($diff < 3600): // Less than 1 hour
                                                ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($diff < 86400): // Less than 24 hours ?>
                                                        <span class="badge bg-warning">Recent</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                <?php } else { ?>
                                                    <span class="badge bg-secondary">Never</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <small class="text-muted font-monospace">
                                                    <?= $device['push_token'] ? substr($device['push_token'], 0, 20) . '...' : 'Not set' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="sendTestNotification(<?= $device['id'] ?>)">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php 
                                            }
                                        } else {
                                            echo "<tr><td colspan='6' class='text-center text-muted'>No mobile devices registered</td></tr>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<tr><td colspan='6' class='text-center text-muted'>Error loading devices. Please check database connection.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PWA Installation Guide -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            PWA Installation Guide
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">On Android/Chrome:</h6>
                                <ol class="text-muted">
                                    <li>Open Chrome browser</li>
                                    <li>Navigate to the HRMS site</li>
                                    <li>Tap the "Install App" button</li>
                                    <li>Or use Chrome menu → "Add to Home screen"</li>
                                </ol>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">On iOS/Safari:</h6>
                                <ol class="text-muted">
                                    <li>Open Safari browser</li>
                                    <li>Navigate to the HRMS site</li>
                                    <li>Tap the Share button</li>
                                    <li>Select "Add to Home Screen"</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Benefits:</strong> Offline access, push notifications, faster loading, native app-like experience, and quick access from home screen.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notifications Modal -->
<div class="modal fade" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bell me-2"></i>Notifications
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="notificationsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading notifications...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Professional PWA Manager Styles - Consistent with Global UI */
.stats-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-radius: 12px;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15)!important;
}

.feature-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    background: white;
    transition: all 0.3s ease;
}

.feature-item:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.feature-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.feature-content {
    flex: 1;
}

.feature-status {
    margin-left: auto;
}

.pwa-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 15px;
}

.pwa-card .card-title {
    color: white;
}

.device-table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.device-table td {
    vertical-align: middle;
    padding: 1rem 0.75rem;
}

.device-table tbody tr {
    transition: background-color 0.2s ease;
}

.device-table tbody tr:hover {
    background-color: #f8f9fa;
}

.quick-action-card {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    border-radius: 12px;
    color: white;
    transition: all 0.3s ease;
}

.quick-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
}

.clock-buttons .btn {
    border-radius: 25px;
    font-weight: 600;
    padding: 0.75rem 2rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.clock-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.installation-guide .card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.installation-guide ol {
    counter-reset: step-counter;
    padding-left: 0;
}

.installation-guide ol li {
    counter-increment: step-counter;
    position: relative;
    padding: 0.5rem 0 0.5rem 3rem;
    margin-bottom: 0.5rem;
    border-left: 2px solid #e9ecef;
}

.installation-guide ol li:before {
    content: counter(step-counter);
    position: absolute;
    left: -15px;
    top: 0.5rem;
    background: #007bff;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.8rem;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .feature-item {
        flex-direction: column;
        text-align: center;
    }
    
    .feature-icon {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
    
    .feature-status {
        margin-left: 0;
        margin-top: 0.5rem;
    }
    
    .stats-card h3 {
        font-size: 1.8rem;
    }
    
    .clock-buttons .btn {
        padding: 0.5rem 1.5rem;
        font-size: 0.9rem;
    }
}
</style>

<!-- Notifications Modal -->
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.feature-content {
    flex: 1;
}

.feature-content h6 {
    color: white;
    margin-bottom: 0.25rem;
}

.feature-content p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
}

.quick-actions-card {
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
}

.quick-action-item {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
    height: 100%;
}

.stat-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.stat-item:last-child {
    border-bottom: none;
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.badge {
    font-size: 0.75rem;
}

.progress-bar {
    background: linear-gradient(45deg, #007bff, #6610f2);
}

@media (max-width: 768px) {
    .feature-item {
        flex-direction: column;
        text-align: center;
    }
    
    .quick-action-item {
        text-align: center;
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Performance optimization: defer heavy operations
document.addEventListener('DOMContentLoaded', function() {
    // Show loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.id = 'loading-indicator';
    loadingIndicator.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2">Loading Mobile PWA Manager...</div>
            </div>
        </div>
    `;
    document.body.appendChild(loadingIndicator);

    // Remove loading indicator after page is fully loaded
    setTimeout(() => {
        const loader = document.getElementById('loading-indicator');
        if (loader) {
            loader.remove();
        }
    }, 1000);

    // Initialize features after DOM is ready
    initializeMobilePWA();
    
    // Performance monitoring
    const loadEndTime = performance.now();
    console.log(`%c🚀 Page Load Performance: ${Math.round(loadEndTime)}ms`, 'color: green; font-weight: bold;');
    
    if (loadEndTime < 1000) {
        console.log('%c✅ Performance: EXCELLENT', 'color: green;');
    } else if (loadEndTime < 3000) {
        console.log('%c✅ Performance: GOOD', 'color: orange;');  
    } else {
        console.log('%c⚠️ Performance: NEEDS IMPROVEMENT', 'color: red;');
    }
});

function initializeMobilePWA() {
    let deferredPrompt;
    let notificationPermission = 'default';

    // Check service worker support and register
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/HRMS/sw.js')
            .then(registration => {
                console.log('Service Worker registered:', registration);
                updateServiceWorkerStatus('Active');
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
                updateServiceWorkerStatus('Failed');
            });
    }

// Handle PWA installation
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install button
    const installBtn = document.querySelector('[onclick="installPWA()"]');
    if (installBtn) {
        installBtn.style.display = 'block';
    }
});

function installPWA() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
                alert('App installed successfully!');
            } else {
                console.log('User dismissed the install prompt');
            }
            deferredPrompt = null;
        });
    } else {
        alert('Install prompt not available. Please use browser menu to add to home screen.');
    }
}

// Check notification permission
function checkNotificationPermission() {
    if ('Notification' in window) {
        notificationPermission = Notification.permission;
        updateNotificationStatus(notificationPermission);
        
        if (notificationPermission === 'default') {
            requestNotificationPermission();
        }
    } else {
        updateNotificationStatus('not-supported');
    }
}

function requestNotificationPermission() {
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            notificationPermission = permission;
            updateNotificationStatus(permission);
            
            if (permission === 'granted') {
                registerForPushNotifications();
            }
        });
    }
}

function updateNotificationStatus(status) {
    const statusElement = document.getElementById('notificationStatus');
    if (statusElement) {
        switch (status) {
            case 'granted':
                statusElement.className = 'badge bg-success';
                statusElement.textContent = 'Enabled';
                break;
            case 'denied':
                statusElement.className = 'badge bg-danger';
                statusElement.textContent = 'Blocked';
                break;
            case 'default':
                statusElement.className = 'badge bg-warning';
                statusElement.textContent = 'Permission Needed';
                break;
            default:
                statusElement.className = 'badge bg-secondary';
                statusElement.textContent = 'Not Supported';
        }
    }
}

// Check location permission
function checkLocationPermission() {
    if ('geolocation' in navigator) {
        navigator.permissions.query({name: 'geolocation'}).then(result => {
            updateLocationStatus(result.state);
            
            result.addEventListener('change', () => {
                updateLocationStatus(result.state);
            });
        }).catch(() => {
            updateLocationStatus('unknown');
        });
    } else {
        updateLocationStatus('not-supported');
    }
}

function updateLocationStatus(status) {
    const statusElement = document.getElementById('locationStatus');
    if (statusElement) {
        switch (status) {
            case 'granted':
                statusElement.className = 'badge bg-success';
                statusElement.textContent = 'Enabled';
                break;
            case 'denied':
                statusElement.className = 'badge bg-danger';
                statusElement.textContent = 'Blocked';
                break;
            case 'prompt':
                statusElement.className = 'badge bg-warning';
                statusElement.textContent = 'Permission Needed';
                break;
            default:
                statusElement.className = 'badge bg-secondary';
                statusElement.textContent = 'Unknown';
        }
    }
}

// Quick clock in/out with GPS
function quickClock(action) {
    if ('geolocation' in navigator) {
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        };
        
        navigator.geolocation.getCurrentPosition(
            position => {
                const formData = new FormData();
                formData.append('action', 'quick_clock');
                formData.append('clock_action', action);
                formData.append('latitude', position.coords.latitude);
                formData.append('longitude', position.coords.longitude);
                formData.append('accuracy', position.coords.accuracy);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Success', data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error', 'Network error: ' + error.message, 'error');
                });
            },
            error => {
                console.error('Location error:', error);
                showNotification('Warning', 'Location unavailable. Continuing without GPS...', 'warning');
                
                // Proceed without location
                const formData = new FormData();
                formData.append('action', 'quick_clock');
                formData.append('clock_action', action);
                formData.append('latitude', '');
                formData.append('longitude', '');
                formData.append('accuracy', '');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Success', data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error', 'Network error: ' + error.message, 'error');
                });
            },
            options
        );
    } else {
        showNotification('Warning', 'Geolocation not supported', 'warning');
    }
}

// Load notifications
function loadNotifications() {
    const modal = new bootstrap.Modal(document.getElementById('notificationsModal'));
    modal.show();
    
    const formData = new FormData();
    formData.append('action', 'get_notifications');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayNotifications(data.notifications);
            updateNotificationCount(data.notifications.filter(n => !n.is_read).length);
        } else {
            document.getElementById('notificationsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load notifications: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('notificationsContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Network error: ${error.message}
            </div>
        `;
    });
}

function displayNotifications(notifications) {
    const content = document.getElementById('notificationsContent');
    
    if (notifications.length === 0) {
        content.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-bell-slash text-muted fs-1 mb-3"></i>
                <h6 class="text-muted">No notifications</h6>
                <p class="text-muted">You're all caught up!</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    
    notifications.forEach(notification => {
        const isRead = notification.is_read;
        const timeAgo = getTimeAgo(notification.created_at);
        const typeIcon = getNotificationIcon(notification.type);
        
        html += `
            <div class="list-group-item ${isRead ? '' : 'list-group-item-primary'}" 
                 ${!isRead ? `onclick="markNotificationRead(${notification.id})"` : ''}>
                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <i class="${typeIcon} text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${notification.title}</h6>
                        <p class="mb-1">${notification.message}</p>
                        <small class="text-muted">${timeAgo}</small>
                    </div>
                    ${!isRead ? '<div class="badge bg-primary">New</div>' : ''}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
}

function getNotificationIcon(type) {
    const icons = {
        'attendance': 'fas fa-clock',
        'leave': 'fas fa-calendar-alt',
        'announcement': 'fas fa-bullhorn',
        'system': 'fas fa-cog',
        'reminder': 'fas fa-bell'
    };
    return icons[type] || 'fas fa-info-circle';
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return `${days}d ago`;
}

function markNotificationRead(notificationId) {
    const formData = new FormData();
    formData.append('action', 'mark_notification_read');
    formData.append('notification_id', notificationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notifications
            loadNotifications();
        }
    });
}

function updateNotificationCount(count) {
    const countElement = document.getElementById('notificationCount');
    if (countElement) {
        countElement.textContent = count;
        countElement.style.display = count > 0 ? 'inline' : 'none';
    }
}

// Test notification
function testNotification() {
    if (notificationPermission === 'granted') {
        new Notification('HRMS Test Notification', {
            body: 'This is a test notification from HRMS PWA',
            icon: '/HRMS/assets/icon-192x192.png',
            badge: '/HRMS/assets/badge-72x72.png',
            tag: 'test'
        });
    } else {
        showNotification('Warning', 'Notification permission not granted', 'warning');
    }
}

// Show toast notification
function showNotification(title, message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'primary'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Initialize PWA features on page load
document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    checkLocationPermission();
    
    // Register device if service worker is supported
    if ('serviceWorker' in navigator) {
        registerDevice();
    }
    
    // Load notification count
    loadNotificationCount();
});

function registerDevice() {
    const deviceInfo = `${navigator.userAgent} | ${navigator.platform} | ${screen.width}x${screen.height}`;
    
    const formData = new FormData();
    formData.append('action', 'register_device');
    formData.append('device_info', deviceInfo);
    formData.append('push_token', ''); // Would be populated by push subscription
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Device registration:', data);
    })
    .catch(error => {
        console.error('Device registration error:', error);
    });
}

function loadNotificationCount() {
    const formData = new FormData();
    formData.append('action', 'get_notifications');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const unreadCount = data.notifications.filter(n => !n.is_read).length;
            updateNotificationCount(unreadCount);
        }
    })
    .catch(error => {
        console.error('Notification count error:', error);
    });
}

function sendTestNotification(userId) {
    // This would typically send a push notification to the specified user
    alert(`Test notification sent to user ID: ${userId}`);
}

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

    </div>
</div>

<?php include '../layouts/footer.php'; ?>