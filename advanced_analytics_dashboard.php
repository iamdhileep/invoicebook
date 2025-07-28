<?php
/**
 * Advanced Analytics and Compliance Management System
 * Provides comprehensive reporting, analytics, and compliance monitoring
 */

session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
date_default_timezone_set('Asia/Kolkata');

$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'dashboard':
        showAnalyticsDashboard();
        break;
    case 'compliance_report':
        generateComplianceReport();
        break;
    case 'export_analytics':
        exportAnalyticsData();
        break;
    case 'ajax_data':
        handleAjaxData();
        break;
    default:
        showAnalyticsDashboard();
}

function showAnalyticsDashboard() {
    global $conn;
    
    // Get date range
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    include 'layouts/header.php';
    ?>
    
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h4 mb-0">📊 Advanced Analytics & Compliance Dashboard</h1>
                <p class="text-muted">Comprehensive insights into attendance patterns and compliance metrics</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-success" onclick="exportDashboard()">
                    <i class="bi bi-download"></i> Export
                </button>
                <button class="btn btn-warning" onclick="scheduleReport()">
                    <i class="bi bi-calendar"></i> Schedule
                </button>
            </div>
        </div>
        
        <!-- Date Range Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3" id="dateRangeForm">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                            <option value="HR">Human Resources</option>
                            <option value="IT">Information Technology</option>
                            <option value="Finance">Finance</option>
                            <option value="Sales">Sales</option>
                            <option value="Marketing">Marketing</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Key Metrics Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="mb-0" id="totalEmployees">-</h2>
                                <p class="mb-0">Total Employees</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small>Active: <span id="activeEmployees">-</span></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="mb-0" id="avgAttendanceRate">-</h2>
                                <p class="mb-0">Avg Attendance Rate</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up-arrow" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small>Target: <span class="text-light">95%</span></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="mb-0" id="complianceScore">-</h2>
                                <p class="mb-0">Compliance Score</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-check" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small>Issues: <span id="complianceIssues">-</span></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h2 class="mb-0" id="totalOvertimeHours">-</h2>
                                <p class="mb-0">Total OT Hours</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small>This Month: <span id="monthlyOT">-</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Attendance Trends Chart -->
            <div class="col-xl-8 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📈 Attendance Trends</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" onclick="changeChartPeriod('daily')">Daily</button>
                            <button class="btn btn-outline-primary" onclick="changeChartPeriod('weekly')">Weekly</button>
                            <button class="btn btn-outline-primary" onclick="changeChartPeriod('monthly')">Monthly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceTrendsChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Punch Method Distribution -->
            <div class="col-xl-4 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 Punch Method Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="punchMethodChart" style="height: 250px;"></canvas>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between text-sm">
                                <span>Biometric:</span>
                                <strong id="biometricPercentage">-</strong>
                            </div>
                            <div class="d-flex justify-content-between text-sm">
                                <span>Mobile:</span>
                                <strong id="mobilePercentage">-</strong>
                            </div>
                            <div class="d-flex justify-content-between text-sm">
                                <span>Geo:</span>
                                <strong id="geoPercentage">-</strong>
                            </div>
                            <div class="d-flex justify-content-between text-sm">
                                <span>Manual:</span>
                                <strong id="manualPercentage">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Analytics and Compliance Status -->
        <div class="row mb-4">
            <!-- Department Performance -->
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">🏢 Department Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Attendance</th>
                                        <th>Avg Hours</th>
                                        <th>Overtime</th>
                                        <th>Compliance</th>
                                    </tr>
                                </thead>
                                <tbody id="departmentPerformanceTable">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Compliance Status -->
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">⚖️ Compliance Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Overall Compliance:</span>
                                <strong class="text-success" id="overallCompliance">94%</strong>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar bg-success" id="complianceProgressBar" style="width: 94%"></div>
                            </div>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <small class="text-muted">Working Hours Compliance</small>
                                    <div>Max hours per day/week</div>
                                </div>
                                <span class="badge bg-success rounded-pill">97%</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <small class="text-muted">Leave Policy Compliance</small>
                                    <div>Leave approvals & balances</div>
                                </div>
                                <span class="badge bg-warning rounded-pill">89%</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <small class="text-muted">Attendance Verification</small>
                                    <div>Biometric/Location verification</div>
                                </div>
                                <span class="badge bg-info rounded-pill">95%</span>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-warning btn-sm w-100" onclick="showComplianceDetails()">
                                <i class="bi bi-exclamation-triangle"></i> View Compliance Issues
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leave Analytics and Real-time Activity -->
        <div class="row mb-4">
            <!-- Leave Analytics -->
            <div class="col-xl-8 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">🏖️ Leave Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <canvas id="leaveTypesChart" style="height: 200px;"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Taken</th>
                                                <th>Remaining</th>
                                                <th>Utilization</th>
                                            </tr>
                                        </thead>
                                        <tbody id="leaveAnalyticsTable">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Activity -->
            <div class="col-xl-4 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🔴 Live Activity</h5>
                        <span class="badge bg-success">Real-time</span>
                    </div>
                    <div class="card-body">
                        <div id="realTimeActivity" style="max-height: 300px; overflow-y: auto;">
                            <div class="text-center text-muted">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <p class="mt-2">Loading live data...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Analytics -->
        <div class="row mb-4">
            <!-- Predictive Analytics -->
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">🔮 Predictive Analytics</h5>
                    </div>
                    <div class="card-body">
                        <h6>Attendance Forecast (Next 30 Days)</h6>
                        <canvas id="predictiveChart" style="height: 200px;"></canvas>
                        
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <small>
                                    <strong>AI Insights:</strong>
                                    <ul class="mb-0">
                                        <li>Attendance likely to drop by 5% next week due to festival season</li>
                                        <li>IT department showing consistent late arrival trend</li>
                                        <li>Recommend policy review for overtime management</li>
                                    </ul>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Employee Insights -->
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">👥 Employee Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 text-center">
                                <h3 class="text-success" id="topPerformers">12</h3>
                                <small class="text-muted">Top Performers<br>(>95% attendance)</small>
                            </div>
                            <div class="col-6 text-center">
                                <h3 class="text-warning" id="attentionNeeded">3</h3>
                                <small class="text-muted">Need Attention<br>(<80% attendance)</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6>Recent Recognition</h6>
                        <div class="list-group list-group-flush" id="recognitionList">
                            <div class="list-group-item px-0 d-flex align-items-center">
                                <div class="bg-success rounded-circle me-3" style="width: 8px; height: 8px;"></div>
                                <div class="flex-grow-1">
                                    <div class="small"><strong>Sarah Johnson</strong></div>
                                    <div class="text-muted small">Perfect attendance - 30 days</div>
                                </div>
                                <small class="text-muted">2h ago</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="showEmployeeDetails()">
                                <i class="bi bi-people"></i> View All Employees
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export and Action Buttons -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 Export & Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <button class="btn btn-success w-100" onclick="exportToExcel()">
                                    <i class="bi bi-file-earmark-excel"></i> Export to Excel
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-danger w-100" onclick="exportToPDF()">
                                    <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-info w-100" onclick="scheduleReport()">
                                    <i class="bi bi-clock-history"></i> Schedule Report
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-warning w-100" onclick="sendAlert()">
                                    <i class="bi bi-bell"></i> Send Alert
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .card {
        transition: transform 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
    }
    
    .progress {
        height: 8px;
    }
    
    .list-group-item {
        border: none;
        padding: 0.5rem 0;
    }
    
    .text-sm {
        font-size: 0.875rem;
    }
    
    #realTimeActivity .activity-item {
        border-left: 3px solid #007bff;
        padding-left: 1rem;
        margin-bottom: 1rem;
    }
    
    #realTimeActivity .activity-item.success {
        border-left-color: #28a745;
    }
    
    #realTimeActivity .activity-item.warning {
        border-left-color: #ffc107;
    }
    
    #realTimeActivity .activity-item.danger {
        border-left-color: #dc3545;
    }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Global variables for charts
    let attendanceTrendsChart, punchMethodChart, leaveTypesChart, predictiveChart;
    
    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();
        initializeCharts();
        startRealTimeUpdates();
        
        // Form submission
        document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            loadDashboardData();
        });
    });
    
    function loadDashboardData() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const department = document.getElementById('departmentFilter').value;
        
        // Show loading state
        showLoadingState();
        
        // Fetch analytics data
        fetch(`?action=ajax_data&type=dashboard_metrics&start_date=${startDate}&end_date=${endDate}&department=${department}`)
            .then(response => response.json())
            .then(data => {
                updateDashboardMetrics(data);
                updateCharts(data);
                hideLoadingState();
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
                hideLoadingState();
            });
    }
    
    function updateDashboardMetrics(data) {
        document.getElementById('totalEmployees').textContent = data.total_employees || '-';
        document.getElementById('activeEmployees').textContent = data.active_employees || '-';
        document.getElementById('avgAttendanceRate').textContent = (data.avg_attendance_rate || 0) + '%';
        document.getElementById('complianceScore').textContent = (data.compliance_score || 0) + '%';
        document.getElementById('complianceIssues').textContent = data.compliance_issues || '-';
        document.getElementById('totalOvertimeHours').textContent = data.total_overtime_hours || '-';
        document.getElementById('monthlyOT').textContent = data.monthly_overtime || '-';
    }
    
    function initializeCharts() {
        // Attendance Trends Chart
        const ctx1 = document.getElementById('attendanceTrendsChart').getContext('2d');
        attendanceTrendsChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Attendance Rate',
                    data: [],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Target',
                    data: [],
                    borderColor: '#28a745',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        
        // Punch Method Chart
        const ctx2 = document.getElementById('punchMethodChart').getContext('2d');
        punchMethodChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Biometric', 'Mobile', 'Geo', 'Manual'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#28a745', '#007bff', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Leave Types Chart
        const ctx3 = document.getElementById('leaveTypesChart').getContext('2d');
        leaveTypesChart = new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: ['Sick', 'Casual', 'Earned', 'WFH'],
                datasets: [{
                    label: 'Days Taken',
                    data: [0, 0, 0, 0],
                    backgroundColor: '#007bff',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Predictive Chart
        const ctx4 = document.getElementById('predictiveChart').getContext('2d');
        predictiveChart = new Chart(ctx4, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Historical',
                    data: [],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)'
                }, {
                    label: 'Predicted',
                    data: [],
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    borderDash: [3, 3]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    function updateCharts(data) {
        // Update attendance trends chart
        if (data.attendance_trends) {
            attendanceTrendsChart.data.labels = data.attendance_trends.labels;
            attendanceTrendsChart.data.datasets[0].data = data.attendance_trends.data;
            attendanceTrendsChart.data.datasets[1].data = new Array(data.attendance_trends.labels.length).fill(95);
            attendanceTrendsChart.update();
        }
        
        // Update punch method chart
        if (data.punch_methods) {
            punchMethodChart.data.datasets[0].data = data.punch_methods.data;
            punchMethodChart.update();
            
            // Update percentages
            const total = data.punch_methods.data.reduce((sum, val) => sum + val, 0);
            if (total > 0) {
                document.getElementById('biometricPercentage').textContent = Math.round((data.punch_methods.data[0] / total) * 100) + '%';
                document.getElementById('mobilePercentage').textContent = Math.round((data.punch_methods.data[1] / total) * 100) + '%';
                document.getElementById('geoPercentage').textContent = Math.round((data.punch_methods.data[2] / total) * 100) + '%';
                document.getElementById('manualPercentage').textContent = Math.round((data.punch_methods.data[3] / total) * 100) + '%';
            }
        }
        
        // Update leave types chart
        if (data.leave_analytics) {
            leaveTypesChart.data.datasets[0].data = data.leave_analytics.data;
            leaveTypesChart.update();
            
            // Update leave analytics table
            updateLeaveAnalyticsTable(data.leave_analytics);
        }
        
        // Update department performance table
        if (data.department_performance) {
            updateDepartmentPerformanceTable(data.department_performance);
        }
    }
    
    function updateLeaveAnalyticsTable(data) {
        const tbody = document.getElementById('leaveAnalyticsTable');
        tbody.innerHTML = '';
        
        data.details.forEach(item => {
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>${item.leave_type}</td>
                <td>${item.taken}</td>
                <td>${item.remaining}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: ${item.utilization}%">${item.utilization}%</div>
                    </div>
                </td>
            `;
        });
    }
    
    function updateDepartmentPerformanceTable(data) {
        const tbody = document.getElementById('departmentPerformanceTable');
        tbody.innerHTML = '';
        
        data.forEach(item => {
            const row = tbody.insertRow();
            const complianceClass = item.compliance >= 95 ? 'success' : item.compliance >= 85 ? 'warning' : 'danger';
            
            row.innerHTML = `
                <td>${item.department}</td>
                <td>
                    <span class="badge bg-${item.attendance >= 95 ? 'success' : item.attendance >= 85 ? 'warning' : 'danger'}">
                        ${item.attendance}%
                    </span>
                </td>
                <td>${item.avg_hours}h</td>
                <td>${item.overtime}h</td>
                <td>
                    <span class="badge bg-${complianceClass}">${item.compliance}%</span>
                </td>
            `;
        });
    }
    
    function startRealTimeUpdates() {
        updateRealTimeActivity();
        setInterval(updateRealTimeActivity, 30000); // Update every 30 seconds
    }
    
    function updateRealTimeActivity() {
        fetch('?action=ajax_data&type=real_time_activity')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('realTimeActivity');
                container.innerHTML = '';
                
                data.activities.forEach(activity => {
                    const div = document.createElement('div');
                    div.className = `activity-item ${activity.type}`;
                    div.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${activity.employee_name}</strong>
                                <div class="text-muted small">${activity.description}</div>
                            </div>
                            <small class="text-muted">${activity.time_ago}</small>
                        </div>
                    `;
                    container.appendChild(div);
                });
            })
            .catch(error => console.error('Error updating real-time activity:', error));
    }
    
    function showLoadingState() {
        // Add loading spinners to key metrics
        document.querySelectorAll('.card h2').forEach(el => {
            el.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
        });
    }
    
    function hideLoadingState() {
        // Loading state will be replaced by actual data
    }
    
    function changeChartPeriod(period) {
        // Update chart period and reload data
        document.querySelectorAll('[onclick^="changeChartPeriod"]').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        loadDashboardData();
    }
    
    function refreshDashboard() {
        loadDashboardData();
        showAlert('Dashboard refreshed successfully!', 'success');
    }
    
    function exportDashboard() {
        window.open('?action=export_analytics&format=pdf', '_blank');
    }
    
    function exportToExcel() {
        window.open('?action=export_analytics&format=excel', '_blank');
    }
    
    function exportToPDF() {
        window.open('?action=export_analytics&format=pdf', '_blank');
    }
    
    function scheduleReport() {
        // Show modal for scheduling reports
        alert('Schedule Report functionality would open a modal here');
    }
    
    function sendAlert() {
        // Show modal for sending alerts
        alert('Send Alert functionality would open a modal here');
    }
    
    function showComplianceDetails() {
        window.open('?action=compliance_report', '_blank');
    }
    
    function showEmployeeDetails() {
        window.location.href = 'employees.php';
    }
    
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    </script>
    
    <?php
    include 'layouts/footer.php';
}

function handleAjaxData() {
    global $conn;
    
    $type = $_GET['type'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $department = $_GET['department'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($type) {
        case 'dashboard_metrics':
            echo json_encode(getDashboardMetrics($startDate, $endDate, $department));
            break;
        case 'real_time_activity':
            echo json_encode(getRealTimeActivity());
            break;
        default:
            echo json_encode(['error' => 'Invalid type']);
    }
}

function getDashboardMetrics($startDate, $endDate, $department) {
    global $conn;
    
    $data = [];
    
    // Total and active employees
    $sql = "SELECT 
                COUNT(*) as total_employees,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees
            FROM employees";
    
    if ($department) {
        $sql .= " WHERE department = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $department);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $employeeStats = $result->fetch_assoc();
    
    $data['total_employees'] = $employeeStats['total_employees'];
    $data['active_employees'] = $employeeStats['active_employees'];
    
    // Average attendance rate
    $sql = "SELECT AVG(
                CASE WHEN status IN ('Present', 'Late', 'WFH') THEN 100 ELSE 0 END
            ) as avg_attendance_rate
            FROM attendance a
            JOIN employees e ON a.employee_id = e.employee_id
            WHERE a.attendance_date BETWEEN ? AND ?";
    
    if ($department) {
        $sql .= " AND e.department = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $startDate, $endDate, $department);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $attendanceStats = $result->fetch_assoc();
    
    $data['avg_attendance_rate'] = round($attendanceStats['avg_attendance_rate'] ?? 0, 1);
    
    // Generate sample data for charts
    $data['attendance_trends'] = generateAttendanceTrends($startDate, $endDate);
    $data['punch_methods'] = getPunchMethodDistribution($startDate, $endDate);
    $data['leave_analytics'] = getLeaveAnalytics($startDate, $endDate);
    $data['department_performance'] = getDepartmentPerformance($startDate, $endDate);
    
    // Compliance and overtime data
    $data['compliance_score'] = 94; // Sample data
    $data['compliance_issues'] = 6;
    $data['total_overtime_hours'] = 245;
    $data['monthly_overtime'] = 89;
    
    return $data;
}

function generateAttendanceTrends($startDate, $endDate) {
    $labels = [];
    $data = [];
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($start <= $end) {
        $labels[] = $start->format('M d');
        $data[] = rand(85, 98); // Sample data
        $start->add(new DateInterval('P1D'));
    }
    
    return ['labels' => $labels, 'data' => $data];
}

function getPunchMethodDistribution($startDate, $endDate) {
    // Sample data - in real implementation, this would query the database
    return [
        'data' => [45, 30, 15, 10], // Biometric, Mobile, Geo, Manual
        'labels' => ['Biometric', 'Mobile', 'Geo', 'Manual']
    ];
}

function getLeaveAnalytics($startDate, $endDate) {
    return [
        'data' => [25, 18, 42, 15], // Sick, Casual, Earned, WFH
        'details' => [
            ['leave_type' => 'Sick Leave', 'taken' => 25, 'remaining' => 87, 'utilization' => 22],
            ['leave_type' => 'Casual Leave', 'taken' => 18, 'remaining' => 92, 'utilization' => 16],
            ['leave_type' => 'Earned Leave', 'taken' => 42, 'remaining' => 158, 'utilization' => 21],
            ['leave_type' => 'WFH', 'taken' => 15, 'remaining' => 185, 'utilization' => 7]
        ]
    ];
}

function getDepartmentPerformance($startDate, $endDate) {
    return [
        ['department' => 'IT', 'attendance' => 96, 'avg_hours' => 8.5, 'overtime' => 12, 'compliance' => 97],
        ['department' => 'HR', 'attendance' => 94, 'avg_hours' => 8.2, 'overtime' => 5, 'compliance' => 95],
        ['department' => 'Finance', 'attendance' => 98, 'avg_hours' => 8.8, 'overtime' => 18, 'compliance' => 92],
        ['department' => 'Sales', 'attendance' => 91, 'avg_hours' => 9.2, 'overtime' => 25, 'compliance' => 89],
        ['department' => 'Marketing', 'attendance' => 93, 'avg_hours' => 8.4, 'overtime' => 8, 'compliance' => 94]
    ];
}

function getRealTimeActivity() {
    return [
        'activities' => [
            [
                'employee_name' => 'John Doe',
                'description' => 'Punched in via mobile app',
                'time_ago' => '2 min ago',
                'type' => 'success'
            ],
            [
                'employee_name' => 'Jane Smith',
                'description' => 'Late arrival - 15 minutes',
                'time_ago' => '5 min ago',
                'type' => 'warning'
            ],
            [
                'employee_name' => 'Mike Johnson',
                'description' => 'Geo-fence violation detected',
                'time_ago' => '8 min ago',
                'type' => 'danger'
            ],
            [
                'employee_name' => 'Sarah Wilson',
                'description' => 'Leave request submitted',
                'time_ago' => '12 min ago',
                'type' => 'info'
            ]
        ]
    ];
}

function generateComplianceReport() {
    // Generate detailed compliance report
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="compliance_report_' . date('Y-m-d') . '.pdf"');
    
    // In real implementation, this would generate a PDF using libraries like TCPDF or FPDF
    echo "Compliance Report - Generated on " . date('Y-m-d H:i:s');
}

function exportAnalyticsData() {
    $format = $_GET['format'] ?? 'excel';
    
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.xls"');
        
        // Generate Excel content
        echo "Analytics Data Export\n";
        echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
    } elseif ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.pdf"');
        
        // Generate PDF content
        echo "Analytics PDF Export - " . date('Y-m-d H:i:s');
    }
}

?>
