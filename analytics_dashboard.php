<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            color: white;
            transition: transform 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .activity-time {
            font-size: 0.8em;
            color: #6c757d;
        }
        .dept-progress {
            margin-bottom: 15px;
        }
        .real-time-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 5px;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .upcoming-event {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body class="bg-light">
    <?php
    session_start();
    require_once 'auth_check.php';
    require_once 'db.php';
    ?>

    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">
                        <span class="real-time-indicator"></span>
                        HR Analytics Dashboard
                    </h1>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn btn-primary" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </div>
                <small class="text-muted">Last updated: <span id="lastUpdated">Loading...</span></small>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="row mb-4" id="metricsRow">
            <!-- Metrics will be loaded here -->
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5>Weekly Attendance Trend</h5>
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5>Department Attendance</h5>
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Details Row -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Department Status</h5>
                    </div>
                    <div class="card-body" id="departmentStatus">
                        <!-- Department status will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Recent Activities</h5>
                    </div>
                    <div class="card-body" id="recentActivities">
                        <!-- Recent activities will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt"></i> Upcoming Events</h5>
                    </div>
                    <div class="card-body" id="upcomingEvents">
                        <!-- Upcoming events will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let attendanceChart, departmentChart;
        
        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch('api/dashboard_data.php');
                const data = await response.json();
                
                if (data.success) {
                    updateMetrics(data.statistics);
                    updateCharts(data.weekly_trend, data.department_attendance);
                    updateDepartmentStatus(data.department_attendance);
                    updateRecentActivities(data.recent_activities);
                    updateUpcomingEvents(data.upcoming_events);
                    document.getElementById('lastUpdated').textContent = new Date(data.timestamp).toLocaleString();
                } else {
                    console.error('Error loading dashboard data:', data.message);
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }

        function updateMetrics(stats) {
            const metricsRow = document.getElementById('metricsRow');
            metricsRow.innerHTML = `
                <div class="col-md-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3>${stats.employees.total_employees}</h3>
                            <p class="mb-0">Total Employees</p>
                            <small>${stats.employees.active_employees} Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-check fa-2x mb-2"></i>
                            <h3>${stats.attendance_today.present_today}</h3>
                            <p class="mb-0">Present Today</p>
                            <small>${stats.attendance_today.attendance_rate}% Attendance</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-times fa-2x mb-2"></i>
                            <h3>${stats.attendance_today.absent_today}</h3>
                            <p class="mb-0">Absent Today</p>
                            <small>${stats.attendance_today.late_today} Late Arrivals</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                            <h3>${stats.leaves.pending_leaves}</h3>
                            <p class="mb-0">Pending Leaves</p>
                            <small>${stats.leaves.on_leave_today} On Leave Today</small>
                        </div>
                    </div>
                </div>
            `;
        }

        function updateCharts(weeklyData, departmentData) {
            // Weekly Attendance Chart
            const ctx1 = document.getElementById('attendanceChart').getContext('2d');
            if (attendanceChart) attendanceChart.destroy();
            
            attendanceChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: weeklyData.map(d => d.day),
                    datasets: [{
                        label: 'Daily Attendance',
                        data: weeklyData.map(d => d.count),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.4
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

            // Department Chart
            const ctx2 = document.getElementById('departmentChart').getContext('2d');
            if (departmentChart) departmentChart.destroy();
            
            departmentChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: departmentData.map(d => d.department),
                    datasets: [{
                        data: departmentData.map(d => d.percentage),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function updateDepartmentStatus(departmentData) {
            const container = document.getElementById('departmentStatus');
            container.innerHTML = departmentData.map(dept => `
                <div class="dept-progress">
                    <div class="d-flex justify-content-between">
                        <small><strong>${dept.department}</strong></small>
                        <small>${dept.present}/${dept.total} (${dept.percentage}%)</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: ${dept.percentage}%"></div>
                    </div>
                </div>
            `).join('');
        }

        function updateRecentActivities(activities) {
            const container = document.getElementById('recentActivities');
            if (activities.length === 0) {
                container.innerHTML = '<p class="text-muted">No recent activities</p>';
                return;
            }
            
            container.innerHTML = activities.map(activity => `
                <div class="activity-item">
                    <div><strong>${activity.employee}</strong></div>
                    <div>${activity.action}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            `).join('');
        }

        function updateUpcomingEvents(events) {
            const container = document.getElementById('upcomingEvents');
            if (events.length === 0) {
                container.innerHTML = '<p class="text-muted">No upcoming events</p>';
                return;
            }
            
            container.innerHTML = events.map(event => `
                <div class="upcoming-event">
                    <div><strong>${event.title}</strong></div>
                    <div class="text-muted">${new Date(event.date).toLocaleDateString()}</div>
                    <small class="text-warning">In ${Math.ceil(event.days_away)} days</small>
                </div>
            `).join('');
        }

        function refreshDashboard() {
            loadDashboardData();
        }

        function exportReport() {
            // Simple export functionality
            window.open('api/export_dashboard_report.php', '_blank');
        }

        // Auto-refresh every 5 minutes
        setInterval(loadDashboardData, 5 * 60 * 1000);

        // Initial load
        document.addEventListener('DOMContentLoaded', loadDashboardData);
    </script>
</body>
</html>
