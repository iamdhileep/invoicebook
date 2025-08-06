<?php
/**
 * HRMS Mobile PWA Manager
 * Progressive Web App functionality with mobile optimization
 */

$page_title = "Mobile PWA Manager";
require_once 'includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    header('Location: ../hrms_portal.php?redirect=HRMS/mobile_pwa_manager.php');
    exit;
}

require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

$currentUserId = HRMSHelper::getCurrentUserId();
$currentUserRole = HRMSHelper::getCurrentUserRole();

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
                // Get current employee
                $stmt = $conn->prepare("SELECT id FROM hr_employees WHERE user_id = ?");
                $stmt->bind_param('i', $currentUserId);
                $stmt->execute();
                $employee = $stmt->get_result()->fetch_assoc();
                
                if (!$employee) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                    exit;
                }
                
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
    // Get mobile usage statistics
    $result = HRMSHelper::safeQuery("
        SELECT 
            COUNT(DISTINCT user_id) as registered_devices,
            COUNT(*) as total_registrations,
            SUM(CASE WHEN last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_last_week
        FROM hr_mobile_devices
    ");
    $mobileStats = $result->fetch_assoc();
    
    // Get mobile clock-ins today
    $result = HRMSHelper::safeQuery("
        SELECT COUNT(*) as mobile_clocks 
        FROM hr_attendance 
        WHERE date = CURDATE() AND (mobile_clock_in = 1 OR mobile_clock_out = 1)
    ");
    $mobileStats['mobile_clocks_today'] = $result->fetch_assoc()['mobile_clocks'];
    
} catch (Exception $e) {
    error_log("Mobile stats error: " . $e->getMessage());
}

// Get current employee info for quick actions
$currentEmployee = null;
if ($currentUserRole !== 'hr' && $currentUserRole !== 'admin') {
    try {
        $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE user_id = ?");
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $currentEmployee = $stmt->get_result()->fetch_assoc();
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-mobile-alt text-primary me-2"></i>
                            Mobile PWA Manager
                        </h1>
                        <p class="text-muted mb-0">Progressive Web App with mobile optimization and real-time features</p>
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
                                        $result = HRMSHelper::safeQuery("
                                            SELECT 
                                                md.*,
                                                u.username,
                                                e.first_name, e.last_name
                                            FROM hr_mobile_devices md
                                            LEFT JOIN users u ON md.user_id = u.id
                                            LEFT JOIN hr_employees e ON u.id = e.user_id
                                            ORDER BY md.last_active DESC
                                        ");
                                        
                                        while ($device = $result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-medium">
                                                        <?= htmlspecialchars(($device['first_name'] ?? '') . ' ' . ($device['last_name'] ?? '')) ?>
                                                    </div>
                                                    <small class="text-muted"><?= htmlspecialchars($device['username'] ?? 'N/A') ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars(substr($device['device_info'], 0, 30)) ?>
                                                    <?= strlen($device['device_info']) > 30 ? '...' : '' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('M j, Y g:i A', strtotime($device['registered_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $lastActive = strtotime($device['last_active']);
                                                $diff = time() - $lastActive;
                                                if ($diff < 3600): // Less than 1 hour
                                                ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($diff < 86400): // Less than 24 hours ?>
                                                    <span class="badge bg-warning">Recent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <?= date('M j', $lastActive) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted font-monospace">
                                                    <?= $device['push_token'] ? 'Enabled' : 'No Token' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="sendTestNotification(<?= $device['user_id'] ?>)">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    } catch (Exception $e) {
                                        echo "<tr><td colspan='6' class='text-center text-muted'>No devices registered</td></tr>";
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
.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

.pwa-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 15px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.feature-icon {
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
</script>

<?php require_once '../layouts/footer.php'; ?>
