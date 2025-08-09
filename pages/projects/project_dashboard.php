<?php
session_start();
if (!isset($_SESSION['admin'])) {
    // Check if admin login page exists
    if (file_exists('../../admin/login.php')) {
        header("Location: ../../admin/login.php");
    } else {
        echo "<div class='container mt-5'>
                <div class='alert alert-warning'>
                    <h4>Authentication Required</h4>
                    <p>Please login as admin to access the project dashboard.</p>
                    <p><a href='quick_login.php?admin_id=1' class='btn btn-primary'>Quick Admin Login</a></p>
                </div>
              </div>";
    }
    exit;
}

include '../../db.php';
$page_title = 'Project Analytics Dashboard';
include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Project Analytics Dashboard</h1>
                <p class="text-muted">Comprehensive project performance insights and analytics</p>
            </div>
            <div class="btn-group" role="group">
                <select id="dateRangeFilter" class="form-select">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 3 months</option>
                    <option value="365">Last year</option>
                </select>
                <button class="btn btn-primary" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading dashboard data...</p>
        </div>

        <!-- Dashboard Content -->
        <div id="dashboardContent" style="display: none;">
            <!-- Key Performance Indicators -->
            <div class="row g-3 mb-4" id="kpiCards">
                <!-- KPI cards will be populated here -->
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-4 mb-4">
                <!-- Project Status Distribution -->
                <div class="col-xl-4 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Project Status Distribution</h6>
                            <i class="bi bi-pie-chart text-primary"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="projectStatusChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Task Priority Breakdown -->
                <div class="col-xl-4 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Task Priority Breakdown</h6>
                            <i class="bi bi-bar-chart text-warning"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="taskPriorityChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Team Workload Overview -->
                <div class="col-xl-4 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Team Workload</h6>
                            <i class="bi bi-people text-info"></i>
                        </div>
                        <div class="card-body">
                            <div id="teamWorkloadList" class="team-workload-container">
                                <!-- Team workload will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-4 mb-4">
                <!-- Project Completion Trend -->
                <div class="col-xl-8 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Project Completion Trend</h6>
                            <i class="bi bi-graph-up text-success"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="completionTrendChart" height="120"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Time Tracking Summary -->
                <div class="col-xl-4 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Time Tracking Summary</h6>
                            <i class="bi bi-clock text-secondary"></i>
                        </div>
                        <div class="card-body">
                            <div id="timeTrackingSummary">
                                <!-- Time tracking data will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables Row -->
            <div class="row g-4 mb-4">
                <!-- Top Performers -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Top Performers</h6>
                            <i class="bi bi-trophy text-warning"></i>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="topPerformersTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Tasks</th>
                                            <th>Hours</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Top performers data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Project Activity -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Recent Activity</h6>
                            <i class="bi bi-activity text-primary"></i>
                        </div>
                        <div class="card-body">
                            <div id="recentActivityList" class="activity-timeline">
                                <!-- Recent activities will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Productivity Heatmap -->
            <div class="row g-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Team Productivity Over Time</h6>
                            <i class="bi bi-calendar-heat text-danger"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="productivityChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.team-workload-container {
    max-height: 300px;
    overflow-y: auto;
}

.workload-item {
    border-left: 4px solid #dee2e6;
    padding: 8px 12px;
    margin-bottom: 8px;
    border-radius: 0 4px 4px 0;
    background: #f8f9fa;
}

.workload-item.high-workload {
    border-left-color: #dc3545;
}

.workload-item.medium-workload {
    border-left-color: #ffc107;
}

.workload-item.low-workload {
    border-left-color: #28a745;
}

.activity-timeline {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items-start;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f4;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 14px;
}

.activity-content {
    flex-grow: 1;
}

.activity-time {
    font-size: 11px;
    color: #6c757d;
}

.kpi-card {
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    border-radius: 12px;
    color: white;
    padding: 1.5rem;
    text-align: center;
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
}

.kpi-card:hover {
    transform: translateY(-2px);
}

.kpi-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.kpi-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.kpi-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.chart-container {
    position: relative;
    height: 200px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let dashboardData = {};
let charts = {};

$(document).ready(function() {
    loadDashboard();
    
    // Refresh dashboard every 5 minutes
    setInterval(refreshDashboard, 300000);
    
    // Handle date range change
    $('#dateRangeFilter').change(function() {
        loadDashboard();
    });
});

function loadDashboard() {
    $('#loadingSpinner').show();
    $('#dashboardContent').hide();
    
    const dateRange = $('#dateRangeFilter').val();
    
    // Load dashboard data
    Promise.all([
        fetch(`project_api.php?action=project_stats&range=${dateRange}`).then(r => r.json()),
        fetch(`project_api.php?action=dashboard_charts&range=${dateRange}`).then(r => r.json()),
        fetch(`project_api.php?action=team_workload`).then(r => r.json())
    ]).then(([statsData, chartsData, workloadData]) => {
        // Check if setup is needed
        if (statsData.stats && statsData.stats.projects.total_projects === 0 && 
            chartsData.charts && chartsData.charts.project_status.data.every(d => d === 0)) {
            showSetupMessage();
            return;
        }
        
        dashboardData = {
            stats: statsData.stats,
            charts: chartsData.charts,
            workload: workloadData.workload || workloadData.team_workload
        };
        
        renderDashboard();
        $('#loadingSpinner').hide();
        $('#dashboardContent').show();
    }).catch(error => {
        console.error('Error loading dashboard:', error);
        $('#loadingSpinner').hide();
        showSetupMessage();
    });
}

function renderDashboard() {
    renderKPICards();
    renderCharts();
    renderTeamWorkload();
    renderTopPerformers();
    renderRecentActivity();
}

function renderKPICards() {
    const kpiData = [
        {
            icon: 'bi-folder',
            value: dashboardData.stats.projects.total_projects,
            label: 'Total Projects',
            gradient: ['#667eea', '#764ba2']
        },
        {
            icon: 'bi-play-circle',
            value: dashboardData.stats.projects.active_projects,
            label: 'Active Projects',
            gradient: ['#f093fb', '#f5576c']
        },
        {
            icon: 'bi-check-circle',
            value: dashboardData.stats.projects.completed_projects,
            label: 'Completed',
            gradient: ['#4facfe', '#00f2fe']
        },
        {
            icon: 'bi-list-task',
            value: dashboardData.stats.tasks.total_tasks,
            label: 'Total Tasks',
            gradient: ['#43e97b', '#38f9d7']
        },
        {
            icon: 'bi-exclamation-triangle',
            value: dashboardData.stats.tasks.overdue_tasks,
            label: 'Overdue',
            gradient: ['#ff6b6b', '#ffa500']
        },
        {
            icon: 'bi-clock',
            value: Math.round(dashboardData.stats.time_tracking.total_hours_logged || 0),
            label: 'Hours Logged',
            gradient: ['#a8edea', '#fed6e3']
        }
    ];
    
    let html = '';
    kpiData.forEach(kpi => {
        html += `
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="kpi-card" style="--gradient-start: ${kpi.gradient[0]}; --gradient-end: ${kpi.gradient[1]}">
                    <div class="kpi-icon">
                        <i class="bi ${kpi.icon}"></i>
                    </div>
                    <div class="kpi-value">${kpi.value || 0}</div>
                    <div class="kpi-label">${kpi.label}</div>
                </div>
            </div>
        `;
    });
    
    $('#kpiCards').html(html);
}

function renderCharts() {
    // Destroy existing charts
    Object.values(charts).forEach(chart => {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    });
    
    // Project Status Chart
    const statusCtx = document.getElementById('projectStatusChart').getContext('2d');
    const statusData = dashboardData.charts.project_status || [];
    
    charts.projectStatus = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status.replace('_', ' ').toUpperCase()),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: ['#28a745', '#007bff', '#ffc107', '#6c757d', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    
    // Task Priority Chart
    const priorityCtx = document.getElementById('taskPriorityChart').getContext('2d');
    const priorityData = dashboardData.charts.task_priority || [];
    
    charts.taskPriority = new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: priorityData.map(item => item.priority.toUpperCase()),
            datasets: [{
                label: 'Tasks',
                data: priorityData.map(item => item.count),
                backgroundColor: ['#6c757d', '#17a2b8', '#ffc107', '#dc3545'],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Completion Trend Chart
    const trendCtx = document.getElementById('completionTrendChart').getContext('2d');
    const trendData = dashboardData.charts.completion_trend || [];
    
    charts.completionTrend = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Projects Completed',
                data: trendData.map(item => item.completed_projects),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Productivity Chart
    const productivityCtx = document.getElementById('productivityChart').getContext('2d');
    const productivityData = dashboardData.charts.productivity || [];
    
    // Group data by employee
    const employeeData = {};
    productivityData.forEach(item => {
        if (!employeeData[item.name]) {
            employeeData[item.name] = {};
        }
        employeeData[item.name][item.month] = item.tasks_completed;
    });
    
    const months = [...new Set(productivityData.map(item => item.month))].sort();
    const employees = Object.keys(employeeData);
    
    const datasets = employees.map((employee, index) => {
        const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'];
        return {
            label: employee,
            data: months.map(month => employeeData[employee][month] || 0),
            borderColor: colors[index % colors.length],
            backgroundColor: colors[index % colors.length] + '20',
            fill: false,
            tension: 0.4
        };
    });
    
    charts.productivity = new Chart(productivityCtx, {
        type: 'line',
        data: {
            labels: months.map(month => {
                const date = new Date(month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short' });
            }),
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function renderTeamWorkload() {
    let html = '';
    
    dashboardData.workload.forEach(member => {
        const workloadLevel = member.active_tasks > 10 ? 'high-workload' : 
                            (member.active_tasks > 5 ? 'medium-workload' : 'low-workload');
        
        html += `
            <div class="workload-item ${workloadLevel}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${member.name}</div>
                        <small class="text-muted">${member.department || 'N/A'}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">${member.active_tasks} tasks</span>
                        ${member.urgent_tasks > 0 ? `<span class="badge bg-danger ms-1">${member.urgent_tasks} urgent</span>` : ''}
                    </div>
                </div>
                <div class="mt-2">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" style="width: ${Math.min(member.avg_progress || 0, 100)}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">${Math.round(member.avg_progress || 0)}% avg progress</small>
                        <small class="text-muted">${Math.round(member.hours_last_30_days || 0)}h logged</small>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#teamWorkloadList').html(html);
}

function renderTopPerformers() {
    let html = '';
    
    dashboardData.stats.top_performers.forEach(performer => {
        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                            ${performer.name.charAt(0)}
                        </div>
                        <span>${performer.name}</span>
                    </div>
                </td>
                <td><span class="badge bg-success">${performer.tasks_completed}</span></td>
                <td>${Math.round(performer.hours_logged || 0)}h</td>
                <td>
                    <div class="progress" style="height: 4px; width: 60px;">
                        <div class="progress-bar" style="width: ${Math.min(performer.avg_progress || 0, 100)}%"></div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#topPerformersTable tbody').html(html);
}

function renderRecentActivity() {
    // Mock recent activity data
    const activities = [
        { icon: 'bi-plus-circle', color: 'bg-success', text: 'New project "Mobile App Development" created', time: '2 minutes ago' },
        { icon: 'bi-check-circle', color: 'bg-primary', text: 'Task "UI Design" completed by John Doe', time: '15 minutes ago' },
        { icon: 'bi-exclamation-triangle', color: 'bg-warning', text: 'Task "API Integration" is overdue', time: '1 hour ago' },
        { icon: 'bi-people', color: 'bg-info', text: 'Sarah added to "E-commerce Project" team', time: '2 hours ago' },
        { icon: 'bi-clock', color: 'bg-secondary', text: '8.5 hours logged by development team', time: '3 hours ago' }
    ];
    
    let html = '';
    activities.forEach(activity => {
        html += `
            <div class="activity-item">
                <div class="activity-icon ${activity.color} text-white">
                    <i class="bi ${activity.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">${activity.text}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `;
    });
    
    $('#recentActivityList').html(html);
}

function renderTimeTrackingSummary() {
    const timeStats = dashboardData.stats.time_tracking;
    const html = `
        <div class="row g-3 text-center">
            <div class="col-6">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-bold text-primary">${Math.round(timeStats.total_hours_logged || 0)}</div>
                    <div class="small text-muted">Total Hours</div>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-bold text-success">${timeStats.active_team_members || 0}</div>
                    <div class="small text-muted">Active Members</div>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-bold text-info">${timeStats.total_time_entries || 0}</div>
                    <div class="small text-muted">Time Entries</div>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-bold text-warning">${Math.round(timeStats.avg_hours_per_entry || 0, 1)}</div>
                    <div class="small text-muted">Avg Hours/Entry</div>
                </div>
            </div>
        </div>
    `;
    
    $('#timeTrackingSummary').html(html);
}

function refreshDashboard() {
    loadDashboard();
}

function showAlert(message, type) {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
}

function showSetupMessage() {
    $('#loadingSpinner').hide();
    $('#dashboardContent').hide();
    
    const setupHtml = `
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Project Management Setup Required
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-4">
                                The project management system requires database tables to be created before you can start using it.
                                This is a one-time setup process that will create all necessary tables and sample data.
                            </p>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Required Tables:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-folder text-primary"></i> projects</li>
                                        <li><i class="bi bi-list-task text-success"></i> project_tasks</li>
                                        <li><i class="bi bi-people text-info"></i> project_team</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Additional Features:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-clock text-warning"></i> time_logs</li>
                                        <li><i class="bi bi-activity text-secondary"></i> project_activities</li>
                                        <li><i class="bi bi-diagram-3 text-primary"></i> task_dependencies</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <a href="setup_project_database.php" class="btn btn-warning btn-lg">
                                    <i class="bi bi-gear-fill"></i> Run Database Setup
                                </a>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <strong>Note:</strong> This setup will create database tables with sample data. 
                                You can safely run this multiple times if needed.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(setupHtml);
    $('body').append(setupHtml);
}
</script>

<?php include '../../layouts/footer.php'; ?>
