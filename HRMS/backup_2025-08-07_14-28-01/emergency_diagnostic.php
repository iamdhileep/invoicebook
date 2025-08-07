<?php
$page_title = "HRMS Emergency Diagnostic";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
    /* EMERGENCY SIDEBAR FIX - INLINE STYLES */
    .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        width: 280px !important;
        background: white !important;
        border-right: 1px solid #dee2e6 !important;
        box-shadow: 0 0 10px rgba(0,0,0,0.1) !important;
        z-index: 1000 !important;
        overflow-y: auto !important;
        padding: 1rem 0 !important;
    }
    
    .sidebar .nav-link {
        display: flex !important;
        align-items: center !important;
        padding: 0.75rem 1rem !important;
        color: #495057 !important;
        text-decoration: none !important;
        transition: all 0.2s !important;
        border-left: 3px solid transparent !important;
        margin: 2px 0 !important;
    }
    
    .sidebar .nav-link:hover {
        background-color: #f8f9fa !important;
        color: #007bff !important;
        border-left-color: #007bff !important;
    }
    
    .sidebar .nav-link i {
        width: 20px !important;
        margin-right: 0.5rem !important;
        text-align: center !important;
    }
    
    .main-content {
        margin-left: 280px !important;
        padding: 1rem !important;
        min-height: 100vh !important;
    }
    
    .nav-section-title {
        font-size: 0.75rem !important;
        font-weight: bold !important;
        text-transform: uppercase !important;
        color: #6c757d !important;
        padding: 0.5rem 1rem !important;
        margin-top: 1rem !important;
    }
    </style>
</head>
<body>

<!-- SIMPLIFIED SIDEBAR -->
<nav class="sidebar">
    <div class="p-3">
        <h5 class="text-primary">BillBook Pro</h5>
        <small class="text-muted">HRMS System</small>
    </div>
    
    <div class="nav-section-title">Dashboard</div>
    <a href="../pages/dashboard/dashboard.php" class="nav-link">
        <i class="bi bi-house"></i>
        <span>Main Dashboard</span>
    </a>
    <a href="index.php" class="nav-link">
        <i class="bi bi-people"></i>
        <span>HRMS Dashboard</span>
    </a>
    
    <div class="nav-section-title">HRMS Modules</div>
    <a href="employee_directory.php" class="nav-link">
        <i class="bi bi-person-lines-fill"></i>
        <span>Employee Directory</span>
    </a>
    <a href="attendance_management.php" class="nav-link">
        <i class="bi bi-clock"></i>
        <span>Attendance</span>
    </a>
    <a href="leave_management.php" class="nav-link">
        <i class="bi bi-calendar-x"></i>
        <span>Leave Management</span>
    </a>
    <a href="payroll_processing.php" class="nav-link">
        <i class="bi bi-cash-coin"></i>
        <span>Payroll</span>
    </a>
    <a href="performance_management.php" class="nav-link">
        <i class="bi bi-graph-up"></i>
        <span>Performance</span>
    </a>
    <a href="employee_onboarding.php" class="nav-link">
        <i class="bi bi-person-plus"></i>
        <span>Onboarding</span>
    </a>
    
    <div class="nav-section-title">Reports</div>
    <a href="employee_reports.php" class="nav-link">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Employee Reports</span>
    </a>
    <a href="payroll_reports.php" class="nav-link">
        <i class="bi bi-file-earmark-spreadsheet"></i>
        <span>Payroll Reports</span>
    </a>
</nav>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger">
                    <h4><i class="bi bi-exclamation-triangle me-2"></i>EMERGENCY DIAGNOSTIC MODE</h4>
                    <p><strong>Current Status:</strong> This is a simplified emergency diagnostic page.</p>
                    <p><strong>Purpose:</strong> To test if the basic sidebar structure works with inline CSS.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>What You Should See</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>✅ Sidebar on the left side (fixed position)</li>
                            <li>✅ White background with border</li>
                            <li>✅ Bootstrap icons visible</li>
                            <li>✅ Hover effects on menu items</li>
                            <li>✅ This content area offset to the right</li>
                        </ul>
                        
                        <p class="mt-3"><strong>If you can see the sidebar working here but not on other HRMS pages, then the issue is with the include files or CSS conflicts.</strong></p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Browser Console Check</h5>
                    </div>
                    <div class="card-body">
                        <p>Press F12 and check the Console tab for any errors:</p>
                        <ul class="small">
                            <li>CSS loading errors</li>
                            <li>JavaScript errors</li>
                            <li>Bootstrap resource failures</li>
                            <li>Network request failures</li>
                        </ul>
                        
                        <button onclick="runDiagnostic()" class="btn btn-primary btn-sm">Run Browser Diagnostic</button>
                        <div id="diagnosticResult" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Tests</h5>
                    </div>
                    <div class="card-body">
                        <p>Test these HRMS pages to see which ones work and which don't:</p>
                        
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="index.php" class="btn btn-outline-primary w-100">HRMS Dashboard</a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="employee_directory.php" class="btn btn-outline-secondary w-100">Employee Directory</a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="attendance_management.php" class="btn btn-outline-info w-100">Attendance</a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="leave_management.php" class="btn btn-outline-warning w-100">Leave Mgmt</a>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Report back:</strong>
                            <ol>
                                <li>Can you see the sidebar on THIS page?</li>
                                <li>Which HRMS pages show the sidebar correctly?</li>
                                <li>Which pages have broken/missing sidebar?</li>
                                <li>Any console errors when opening the broken pages?</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function runDiagnostic() {
    const result = document.getElementById('diagnosticResult');
    let html = '<div class="alert alert-info small mt-2">';
    
    // Check sidebar
    const sidebar = document.querySelector('.sidebar');
    html += '<strong>Sidebar Check:</strong><br>';
    html += `- Element found: ${sidebar ? '✅ Yes' : '❌ No'}<br>`;
    if (sidebar) {
        html += `- Width: ${sidebar.offsetWidth}px<br>`;
        html += `- Height: ${sidebar.offsetHeight}px<br>`;
        html += `- Position: ${window.getComputedStyle(sidebar).position}<br>`;
    }
    
    // Check Bootstrap
    html += '<br><strong>Bootstrap Check:</strong><br>';
    const bootstrapLoaded = document.querySelector('link[href*="bootstrap"]');
    html += `- Bootstrap CSS: ${bootstrapLoaded ? '✅ Found' : '❌ Missing'}<br>`;
    
    const iconsLoaded = document.querySelector('link[href*="bootstrap-icons"]');
    html += `- Bootstrap Icons: ${iconsLoaded ? '✅ Found' : '❌ Missing'}<br>`;
    
    // Check icons
    const icons = document.querySelectorAll('[class*="bi-"]');
    html += `- Icon elements: ${icons.length} found<br>`;
    
    html += '</div>';
    result.innerHTML = html;
}
</script>

</body>
</html>
