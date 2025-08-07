<?php
$page_title = "HRMS Testing Dashboard";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Test database tables
$tables_to_check = [
    'hr_departments',
    'hr_employees', 
    'hr_leave_types',
    'hr_leave_applications',
    'hr_leave_balances',
    'hr_attendance',
    'hr_payroll',
    'hr_performance_reviews'
];

$table_status = [];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $table_status[$table] = $result && $result->num_rows > 0;
}

// Test HRMS files
$hrms_files = [
    'employee_directory.php' => 'Employee Directory',
    'leave_management.php' => 'Leave Management',  
    'attendance_management.php' => 'Attendance Management',
    'payroll_processing.php' => 'Payroll Processing',
    'performance_management.php' => 'Performance Management',
    'training_management.php' => 'Training Management',
    'employee_onboarding.php' => 'Employee Onboarding',
    'hr_dashboard.php' => 'HR Dashboard'
];

?>

<div class="content-wrapper">
    <div class="container-fluid">
        <h1>🔍 HRMS System Testing Dashboard</h1>
        
        <!-- Database Tables Status -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📊 Database Tables Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($table_status as $table => $exists): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card <?php echo $exists ? 'border-success' : 'border-danger'; ?>">
                                    <div class="card-body text-center">
                                        <i class="fas <?php echo $exists ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?> fa-2x mb-2"></i>
                                        <h6><?php echo str_replace('hr_', '', $table); ?></h6>
                                        <small class="<?php echo $exists ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $exists ? 'EXISTS' : 'MISSING'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- HRMS Module Status -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🏢 HRMS Module Testing</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($hrms_files as $file => $title): ?>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <?php 
                                $file_exists = file_exists(__DIR__ . '/' . $file);
                                $file_size = $file_exists ? filesize(__DIR__ . '/' . $file) : 0;
                                $is_functional = $file_exists && $file_size > 1000; // Basic check
                                ?>
                                <div class="card <?php echo $is_functional ? 'border-primary' : 'border-warning'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0"><?php echo $title; ?></h6>
                                            <i class="fas <?php echo $is_functional ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning'; ?>"></i>
                                        </div>
                                        <small class="text-muted">Size: <?php echo number_format($file_size); ?> bytes</small>
                                        <div class="mt-2">
                                            <?php if ($file_exists): ?>
                                                <a href="<?php echo $file; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> Test
                                                </a>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-secondary disabled">Missing</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sample Data Check -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📈 Sample Data Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th>Record Count</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_status as $table => $exists): 
                                        if ($exists) {
                                            $result = $conn->query("SELECT COUNT(*) as count FROM $table");
                                            $count = $result ? $result->fetch_assoc()['count'] : 0;
                                        } else {
                                            $count = 0;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $table; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $count > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo $count; ?> records
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($exists && $count > 0): ?>
                                                <i class="fas fa-check-circle text-success"></i> Ready
                                            <?php elseif ($exists): ?>
                                                <i class="fas fa-exclamation-triangle text-warning"></i> Empty
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i> Missing
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$exists || $count == 0): ?>
                                                <button class="btn btn-sm btn-outline-primary" onclick="addSampleData('<?php echo $table; ?>')">
                                                    Add Sample Data
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">⚡ Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="btn-group mb-2" role="group">
                            <a href="complete_database_setup.php" class="btn btn-success">
                                <i class="fas fa-database"></i> Setup All Tables
                            </a>
                            <a href="hrms_mass_fix.php" class="btn btn-warning">
                                <i class="fas fa-tools"></i> Apply Mass Fixes
                            </a>
                            <a href="ajax_modal_fixer.php" class="btn btn-info">
                                <i class="fas fa-code"></i> Fix AJAX Handlers
                            </a>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-primary" onclick="runAllTests()">
                                <i class="fas fa-play"></i> Run All Tests
                            </button>
                            <button class="btn btn-secondary" onclick="location.reload()">
                                <i class="fas fa-sync"></i> Refresh Status
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
function addSampleData(table) {
    if (confirm('Add sample data to ' + table + '?')) {
        fetch('complete_database_setup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=add_sample_data&table=' + table
        })
        .then(response => response.text())
        .then(data => {
            alert('Sample data added to ' + table);
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding sample data');
        });
    }
}

function runAllTests() {
    const links = document.querySelectorAll('a[href$=".php"]:not([href*="complete_database_setup"]):not([href*="mass_fix"]):not([href*="ajax_modal_fixer"])');
    let passCount = 0;
    let totalCount = links.length;
    
    links.forEach((link, index) => {
        setTimeout(() => {
            fetch(link.href)
            .then(response => {
                if (response.ok) {
                    passCount++;
                    link.parentElement.innerHTML += '<i class="fas fa-check text-success ml-1"></i>';
                } else {
                    link.parentElement.innerHTML += '<i class="fas fa-times text-danger ml-1"></i>';
                }
                
                if (index === totalCount - 1) {
                    setTimeout(() => {
                        alert(`Testing Complete: ${passCount}/${totalCount} modules passed`);
                    }, 500);
                }
            })
            .catch(error => {
                link.parentElement.innerHTML += '<i class="fas fa-times text-danger ml-1"></i>';
            });
        }, index * 200); // Stagger requests
    });
}
</script>

<style>
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.border-success { border-color: #28a745 !important; }
.border-danger { border-color: #dc3545 !important; }
.border-warning { border-color: #ffc107 !important; }
.border-primary { border-color: #007bff !important; }

.btn-group .btn {
    margin-right: 5px;
}

.table th {
    border-top: none;
    background-color: #f8f9fa;
}
</style>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>