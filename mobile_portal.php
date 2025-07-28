<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Employee Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px;
        }
        
        .action-btn {
            background: white;
            border: none;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            color: inherit;
        }
        
        .action-btn i {
            font-size: 2em;
            margin-bottom: 10px;
            display: block;
        }
        
        .status-card {
            background: white;
            margin: 20px;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .clock-status {
            text-align: center;
            padding: 20px;
        }
        
        .clock-time {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .clock-date {
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .punch-btn {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: none;
            font-size: 1.2em;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
            margin: 10px;
        }
        
        .punch-in-btn {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
        }
        
        .punch-out-btn {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
        }
        
        .punch-btn:hover {
            transform: scale(1.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9em;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
        }
        
        .nav-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .nav-item.active,
        .nav-item:hover {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .nav-item i {
            display: block;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .content-section {
            padding-bottom: 80px; /* Space for bottom nav */
        }
        
        @media (max-width: 576px) {
            .mobile-container {
                max-width: 100%;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php
    session_start();
    require_once 'auth_check.php';
    require_once 'db.php';
    
    // Get employee info
    $employee_id = $_SESSION['user_id'] ?? $_SESSION['admin'];
    $employee = $conn->query("SELECT * FROM employees WHERE id = $employee_id")->fetch_assoc();
    
    // Check today's attendance
    $today = date('Y-m-d');
    $today_attendance = $conn->query("
        SELECT * FROM attendance 
        WHERE employee_id = $employee_id AND DATE(check_in) = '$today'
        ORDER BY check_in DESC LIMIT 1
    ")->fetch_assoc();
    
    $is_clocked_in = $today_attendance && !$today_attendance['check_out'];
    ?>

    <div class="mobile-container">
        <!-- Header -->
        <div class="mobile-header">
            <h4>Welcome, <?= htmlspecialchars($employee['first_name']) ?>!</h4>
            <small><?= date('l, F j, Y') ?></small>
        </div>

        <div class="content-section">
            <!-- Clock Status -->
            <div class="status-card">
                <div class="clock-status">
                    <div class="clock-time" id="currentTime"></div>
                    <div class="clock-date"><?= date('l, F j, Y') ?></div>
                    
                    <div class="d-flex justify-content-center">
                        <?php if (!$is_clocked_in): ?>
                            <button class="punch-btn punch-in-btn" onclick="clockIn()">
                                <i class="fas fa-play"></i><br>
                                Clock In
                            </button>
                        <?php else: ?>
                            <button class="punch-btn punch-out-btn" onclick="clockOut()">
                                <i class="fas fa-stop"></i><br>
                                Clock Out
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($today_attendance): ?>
                        <div class="mt-3">
                            <small class="text-muted">
                                Clocked in at: <?= date('h:i A', strtotime($today_attendance['check_in'])) ?>
                                <?php if ($today_attendance['check_out']): ?>
                                    | Clocked out at: <?= date('h:i A', strtotime($today_attendance['check_out'])) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="mobile_leave_request.php" class="action-btn">
                    <i class="fas fa-calendar-times text-warning"></i>
                    <div>Request Leave</div>
                </a>
                <a href="mobile_timesheet.php" class="action-btn">
                    <i class="fas fa-clock text-info"></i>
                    <div>View Timesheet</div>
                </a>
                <a href="mobile_profile.php" class="action-btn">
                    <i class="fas fa-user text-success"></i>
                    <div>My Profile</div>
                </a>
                <a href="mobile_notifications.php" class="action-btn position-relative">
                    <i class="fas fa-bell text-danger"></i>
                    <div>Notifications</div>
                    <span class="notification-badge" id="notificationCount">0</span>
                </a>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="monthlyHours">0</div>
                    <div class="stat-label">Hours This Month</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="leaveBalance">0</div>
                    <div class="stat-label">Leave Balance</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="attendanceRate">0%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="pendingRequests">0</div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <div class="bottom-nav">
            <a href="mobile_portal.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="mobile_attendance.php" class="nav-item">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
            <a href="mobile_leaves.php" class="nav-item">
                <i class="fas fa-calendar"></i>
                <span>Leaves</span>
            </a>
            <a href="mobile_profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </div>

    <!-- Clock In/Out Modal -->
    <div class="modal fade" id="clockModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clockModalTitle">Clock In</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 3em;"></i>
                    <h4 class="mt-3" id="clockModalMessage">Successfully clocked in!</h4>
                    <p id="clockModalTime"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
            document.getElementById('currentTime').textContent = timeString;
        }

        // Clock In
        async function clockIn() {
            try {
                const response = await fetch('api/clock_in.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('clockModalTitle').textContent = 'Clock In';
                    document.getElementById('clockModalMessage').textContent = 'Successfully clocked in!';
                    document.getElementById('clockModalTime').textContent = 'Time: ' + new Date().toLocaleTimeString();
                    
                    const modal = new bootstrap.Modal(document.getElementById('clockModal'));
                    modal.show();
                    
                    // Refresh page after modal closes
                    modal._element.addEventListener('hidden.bs.modal', () => {
                        location.reload();
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error clocking in: ' + error.message);
            }
        }

        // Clock Out
        async function clockOut() {
            try {
                const response = await fetch('api/clock_out.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('clockModalTitle').textContent = 'Clock Out';
                    document.getElementById('clockModalMessage').textContent = 'Successfully clocked out!';
                    document.getElementById('clockModalTime').textContent = 'Total hours today: ' + (data.hours_worked || '0') + ' hours';
                    
                    const modal = new bootstrap.Modal(document.getElementById('clockModal'));
                    modal.show();
                    
                    // Refresh page after modal closes
                    modal._element.addEventListener('hidden.bs.modal', () => {
                        location.reload();
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error clocking out: ' + error.message);
            }
        }

        // Load employee stats
        async function loadStats() {
            try {
                const response = await fetch('api/employee_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('monthlyHours').textContent = data.monthly_hours || '0';
                    document.getElementById('leaveBalance').textContent = data.leave_balance || '0';
                    document.getElementById('attendanceRate').textContent = (data.attendance_rate || '0') + '%';
                    document.getElementById('pendingRequests').textContent = data.pending_requests || '0';
                    document.getElementById('notificationCount').textContent = data.notifications || '0';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Initialize
        setInterval(updateClock, 1000);
        updateClock();
        loadStats();
    </script>
</body>
</html>
