<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

$page_title = 'Basic Attendance';
include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clock me-2"></i>
                    Basic Attendance Management
                </h1>
                <p class="text-muted">Simple attendance tracking and management</p>
            </div>
            <div>
                <a href="../../advanced_attendance.php" class="btn btn-primary">
                    <i class="bi bi-lightning-fill"></i> Advanced Attendance
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-check me-2"></i>
                            Basic Attendance System
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Basic Attendance Features</h6>
                            <p class="mb-0">Simple clock in/out functionality with basic reporting and time tracking.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-play-circle display-4 text-success"></i>
                                        <h6>Clock In</h6>
                                        <button class="btn btn-success btn-sm">Start Work</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-stop-circle display-4 text-danger"></i>
                                        <h6>Clock Out</h6>
                                        <button class="btn btn-danger btn-sm">End Work</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Quick Actions:</h6>
                            <a href="../../pages/attendance/attendance.php" class="btn btn-outline-primary btn-sm me-2">
                                <i class="bi bi-calendar"></i> View Full Attendance
                            </a>
                            <a href="../../advanced_attendance.php" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-lightning"></i> Advanced Features
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>
