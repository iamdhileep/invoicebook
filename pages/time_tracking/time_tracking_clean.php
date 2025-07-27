<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Database connection
include '../../db.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Include header
include '../../layouts/header.php';
?>

<div class="main-content">
    <?php include '../../layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-0">Time Tracking Dashboard</h1>
                    <p class="text-muted">Manage employee time and attendance</p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2>0</h2>
                            <p class="mb-0">Present Today</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2>0</h2>
                            <p class="mb-0">On Time</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h2>0</h2>
                            <p class="mb-0">Late Arrivals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2>0</h2>
                            <p class="mb-0">Remote</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mb-4">
                <div class="col-12">
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#clockModal">
                        <i class="bi bi-clock"></i> Clock In/Out
                    </button>
                    <button class="btn btn-success me-2">
                        <i class="bi bi-calendar-plus"></i> Request Time Off
                    </button>
                    <button class="btn btn-warning me-2">
                        <i class="bi bi-clock-fill"></i> Request Overtime
                    </button>
                    <a href="settings.php" class="btn btn-secondary">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Time Off Requests</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No pending requests</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Overtime Requests</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-4">
                                <i class="bi bi-clock-fill text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No pending requests</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= date('F Y') ?> Attendance Calendar</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-5" style="background: #f8f9fa;">
                                <i class="bi bi-calendar3 text-muted" style="font-size: 4rem;"></i>
                                <h6 class="mt-3 text-muted">Calendar View</h6>
                                <p class="text-muted">Attendance calendar coming soon</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clock In/Out Modal -->
<div class="modal fade" id="clockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clock In/Out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select class="form-select" required>
                            <option value="">Select Employee</option>
                            <?php
                            $empQuery = "SELECT id, name FROM employees ORDER BY name";
                            $empResult = $conn->query($empQuery);
                            if ($empResult) {
                                while ($emp = $empResult->fetch_assoc()) {
                                    echo '<option value="' . $emp['id'] . '">' . htmlspecialchars($emp['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select class="form-select" required>
                            <option value="">Select Action</option>
                            <option value="clock_in">Clock In</option>
                            <option value="clock_out">Clock Out</option>
                            <option value="break_start">Start Break</option>
                            <option value="break_end">End Break</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select">
                            <option value="Office">Office</option>
                            <option value="Remote">Remote/WFH</option>
                            <option value="Field">Field Work</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Submit</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>
