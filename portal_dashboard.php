<?php
session_start();

// Set default session for demo
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['employee_id'] = 1;
    $_SESSION['role'] = 'admin';
}

include 'db.php';
$pageTitle = "HRMS Dashboard - Portal Selection";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .portal-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .portal-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .portal-link {
            text-decoration: none;
            color: inherit;
        }
        
        .portal-link:hover {
            color: inherit;
        }
        
        .welcome-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .feature-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Welcome Header -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="welcome-header text-white p-4 text-center">
                    <h1 class="display-4 mb-3">
                        <i class="fas fa-building"></i> HRMS Portal System
                    </h1>
                    <p class="lead mb-0">Complete Human Resource Management Solution</p>
                    <small class="d-block mt-2 opacity-75">Choose your portal to access specialized features</small>
                </div>
            </div>
        </div>

        <!-- Portal Selection Cards -->
        <div class="row g-4 mb-5">
            <!-- Employee Portal -->
            <div class="col-lg-4 col-md-6">
                <a href="pages/employee/employee_portal.php" class="portal-link">
                    <div class="card portal-card h-100 text-center position-relative">
                        <div class="feature-badge">Self Service</div>
                        <div class="card-body p-4">
                            <div class="portal-icon text-primary">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h3 class="card-title mb-3">Employee Portal</h3>
                            <p class="card-text text-muted mb-4">
                                Self-service portal for employees to manage attendance, leave applications, and view payroll information.
                            </p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Punch In/Out System</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Leave Management</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Attendance History</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Payroll Information</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Profile Management</li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-arrow-right me-2"></i> Access Portal
                                </button>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Manager Dashboard -->
            <div class="col-lg-4 col-md-6">
                <a href="pages/manager/manager_dashboard.php" class="portal-link">
                    <div class="card portal-card h-100 text-center position-relative">
                        <div class="feature-badge">Team Management</div>
                        <div class="card-body p-4">
                            <div class="portal-icon text-warning">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="card-title mb-3">Manager Dashboard</h3>
                            <p class="card-text text-muted mb-4">
                                Comprehensive team management tools for supervisors and department heads.
                            </p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Team Oversight</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Leave Approvals</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Attendance Monitoring</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Performance Reviews</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Team Analytics</li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-arrow-right me-2"></i> Access Dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- HR Dashboard -->
            <div class="col-lg-4 col-md-6">
                <a href="pages/hr/hr_dashboard.php" class="portal-link">
                    <div class="card portal-card h-100 text-center position-relative">
                        <div class="feature-badge">Admin Control</div>
                        <div class="card-body p-4">
                            <div class="portal-icon text-danger">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 class="card-title mb-3">HR Dashboard</h3>
                            <p class="card-text text-muted mb-4">
                                Complete administrative control panel for HR professionals and system administrators.
                            </p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Employee Management</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> System Administration</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Payroll Generation</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Reports & Analytics</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Leave Management</li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-danger btn-lg w-100">
                                    <i class="fas fa-arrow-right me-2"></i> Access Dashboard
                                </button>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- System Status & Features -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> System Status & Features</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success"><i class="fas fa-check-circle me-2"></i> Active Features</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-1">✅ Global HRMS API System</li>
                                    <li class="mb-1">✅ Real-time Attendance Tracking</li>
                                    <li class="mb-1">✅ Leave Management Workflow</li>
                                    <li class="mb-1">✅ Payroll Integration</li>
                                    <li class="mb-1">✅ Performance Review System</li>
                                    <li class="mb-1">✅ Mobile Responsive Design</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-info"><i class="fas fa-info-circle me-2"></i> Technical Details</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-1"><strong>Framework:</strong> Bootstrap 5.3.2</li>
                                    <li class="mb-1"><strong>Backend:</strong> PHP with MySQL</li>
                                    <li class="mb-1"><strong>API:</strong> Centralized Global HRMS API</li>
                                    <li class="mb-1"><strong>Security:</strong> Session-based Authentication</li>
                                    <li class="mb-1"><strong>UI/UX:</strong> Modern Responsive Design</li>
                                    <li class="mb-1"><strong>Integration:</strong> Cross-portal Connectivity</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-5">
            <p class="text-white opacity-75">
                <i class="fas fa-shield-alt me-2"></i>
                Secure HRMS Portal System - All portals connected via Global API
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
