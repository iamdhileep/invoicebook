<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Access - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .access-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            text-align: center;
        }
        .feature-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="access-card">
                    <h1 class="text-primary mb-4">
                        <i class="fas fa-calendar-alt"></i> 
                        HR Timesheet System
                    </h1>
                    
                    <?php
                    session_start();
                    require_once 'db.php';
                    
                    // Check database and data
                    $status_ok = true;
                    $messages = [];
                    
                    if ($conn) {
                        $messages[] = "✅ Database connection successful";
                        
                        // Check attendance data
                        $result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = 1");
                        if ($result) {
                            $count = $result->fetch_assoc()['count'];
                            $messages[] = "✅ Found $count attendance records for Employee ID 1";
                            
                            if ($count > 0) {
                                // Show sample of recent data
                                $sample = $conn->query("
                                    SELECT attendance_date, punch_in_time, punch_out_time 
                                    FROM attendance 
                                    WHERE employee_id = 1 
                                    ORDER BY attendance_date DESC 
                                    LIMIT 3
                                ");
                                
                                if ($sample && $sample->num_rows > 0) {
                                    $messages[] = "✅ Recent attendance data available";
                                }
                            } else {
                                $status_ok = false;
                                $messages[] = "❌ No attendance data found";
                            }
                        } else {
                            $status_ok = false;
                            $messages[] = "❌ Error checking attendance table: " . $conn->error;
                        }
                        
                        // Check employees table
                        $emp_result = $conn->query("SELECT COUNT(*) as count FROM employees");
                        if ($emp_result) {
                            $emp_count = $emp_result->fetch_assoc()['count'];
                            $messages[] = "✅ Found $emp_count employees in database";
                        }
                        
                    } else {
                        $status_ok = false;
                        $messages[] = "❌ Database connection failed";
                    }
                    ?>
                    
                    <div class="mb-4">
                        <?php foreach ($messages as $message): ?>
                            <p class="<?= strpos($message, '✅') !== false ? 'text-success' : 'text-danger' ?>">
                                <?= $message ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($status_ok): ?>
                        <div class="d-grid gap-3">
                            <a href="timesheet/timesheet.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-check"></i> Access Timesheet
                            </a>
                            <a href="timesheet/test.php" class="btn btn-outline-info">
                                <i class="fas fa-vial"></i> Run Quick Test
                            </a>
                            <a href="employee_portal.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user"></i> Employee Portal
                            </a>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <h5><i class="fas fa-clock text-primary"></i> Features</h5>
                                    <ul class="list-unstyled text-start">
                                        <li>• Monthly timesheet view</li>
                                        <li>• Attendance tracking</li>
                                        <li>• Hours calculation</li>
                                        <li>• Export to CSV</li>
                                        <li>• Print functionality</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <h5><i class="fas fa-chart-bar text-success"></i> Data Summary</h5>
                                    <?php
                                    if ($conn) {
                                        $today = date('Y-m-d');
                                        $this_month = date('Y-m');
                                        
                                        $monthly_data = $conn->query("
                                            SELECT COUNT(*) as days_worked,
                                                   SUM(CASE WHEN punch_out_time IS NOT NULL THEN 
                                                       TIMESTAMPDIFF(MINUTE, 
                                                           CONCAT(attendance_date, ' ', punch_in_time), 
                                                           CONCAT(attendance_date, ' ', punch_out_time)
                                                       ) / 60 ELSE 0 END) as total_hours
                                            FROM attendance 
                                            WHERE employee_id = 1 
                                            AND DATE_FORMAT(attendance_date, '%Y-%m') = '$this_month'
                                        ");
                                        
                                        if ($monthly_data) {
                                            $data = $monthly_data->fetch_assoc();
                                            echo "<p><strong>This Month:</strong></p>";
                                            echo "<p>Days: " . ($data['days_worked'] ?? 0) . "</p>";
                                            echo "<p>Hours: " . number_format($data['total_hours'] ?? 0, 1) . "</p>";
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> Setup Required</h5>
                            <p>Some issues were detected. Please run the setup script first:</p>
                            <a href="#" onclick="runSetup()" class="btn btn-warning">
                                <i class="fas fa-tools"></i> Run Setup
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    <p class="text-muted">
                        <small>
                            HR Timesheet System v2.0 | 
                            Current Time: <?= date('Y-m-d H:i:s') ?> |
                            <a href="analytics_dashboard.php">Analytics Dashboard</a>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function runSetup() {
            if (confirm('This will set up sample attendance data. Continue?')) {
                window.location.href = 'setup_correct_attendance.php';
            }
        }
    </script>
</body>
</html>
