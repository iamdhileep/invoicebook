<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'HR Analytics Dashboard';

// Include global layouts
include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<!-- Custom styles for Analytics Dashboard -->
<style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            color: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .metric-card .card-body {
            padding: 1.5rem;
        }
        .metric-card i {
            opacity: 0.8;
            margin-bottom: 0.75rem !important;
        }
        .metric-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .metric-card p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0.25rem !important;
        }
        .metric-card small {
            opacity: 0.8;
            font-size: 0.85rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            transition: box-shadow 0.3s ease;
        }
        .chart-container:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .chart-container h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .activity-item:hover {
            background: #e9ecef;
            border-left-color: #0056b3;
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
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
            transition: all 0.2s ease;
        }
        .upcoming-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }
        .card {
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <span class="real-time-indicator"></span>
                    📊 HR Analytics Dashboard
                </h1>
                <p class="text-muted">Real-time insights and analytics for your HR operations</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>
        
        <small class="text-muted mb-4 d-block">Last updated: <span id="lastUpdated">Loading...</span></small>

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
        let dashboardCache = null;
        let lastFetch = 0;
        const CACHE_DURATION = 2 * 60 * 1000; // 2 minutes cache
        
        // Show loading state
        function showLoading() {
            const metricsRow = document.getElementById('metricsRow');
            metricsRow.innerHTML = `
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading dashboard data...</p>
                </div>
            `;
        }
        
        // Show error state
        function showError(message) {
            const metricsRow = document.getElementById('metricsRow');
            metricsRow.innerHTML = `
                <div class="col-12 text-center">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${message}
                        <button class="btn btn-sm btn-outline-primary ms-2" onclick="loadDashboardData(true)">
                            Retry
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Load dashboard data with caching and timeout
        async function loadDashboardData(forceRefresh = false) {
            const now = Date.now();
            
            // Use cache if available and not expired
            if (!forceRefresh && dashboardCache && (now - lastFetch) < CACHE_DURATION) {
                updateInterface(dashboardCache);
                return;
            }
            
            showLoading();
            
            try {
                // Set a timeout for the fetch request
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
                
                const response = await fetch('api/dashboard_data.php', {
                    signal: controller.signal,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    dashboardCache = data;
                    lastFetch = now;
                    updateInterface(data);
                } else {
                    throw new Error(data.message || 'Unknown API error');
                }
                
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                
                if (error.name === 'AbortError') {
                    showError('Request timeout. The server may be busy. Please try again.');
                } else if (error.message.includes('fetch')) {
                    showError('Network error. Please check your connection.');
                } else {
                    showError(`Error: ${error.message}`);
                }
                
                // If we have cached data, show it as fallback
                if (dashboardCache) {
                    setTimeout(() => {
                        updateInterface(dashboardCache);
                        document.getElementById('lastUpdated').textContent = 'Showing cached data';
                    }, 2000);
                }
            }
        }
        
        function updateInterface(data) {
            updateMetrics(data.statistics);
            updateCharts(data.weekly_trend, data.department_attendance);
            updateDepartmentStatus(data.department_attendance);
            updateRecentActivities(data.recent_activities);
            updateUpcomingEvents(data.upcoming_events);
            document.getElementById('lastUpdated').textContent = new Date(data.timestamp).toLocaleString();
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
            loadDashboardData(true); // Force refresh
        }

        function exportReport() {
            // Create basic CSV export if the API doesn't exist
            try {
                window.open('api/export_dashboard_report.php', '_blank');
            } catch (error) {
                // Fallback: client-side export
                exportDashboardData();
            }
        }
        
        function exportDashboardData() {
            if (!dashboardCache) {
                alert('No data to export. Please wait for the dashboard to load.');
                return;
            }
            
            const csvData = [
                ['Metric', 'Value'],
                ['Total Employees', dashboardCache.statistics.employees.total_employees],
                ['Active Employees', dashboardCache.statistics.employees.active_employees],
                ['Present Today', dashboardCache.statistics.attendance_today.present_today],
                ['Absent Today', dashboardCache.statistics.attendance_today.absent_today],
                ['Attendance Rate', dashboardCache.statistics.attendance_today.attendance_rate + '%'],
                ['Pending Leaves', dashboardCache.statistics.leaves.pending_leaves]
            ];
            
            const csvContent = csvData.map(row => row.join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'dashboard_report_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Optimize auto-refresh: only refresh if page is visible
        let refreshInterval;
        
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(() => {
                if (!document.hidden) {
                    loadDashboardData();
                }
            }, 5 * 60 * 1000); // 5 minutes
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
        }

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
                // Refresh data when page becomes visible again
                if (dashboardCache && (Date.now() - lastFetch) > CACHE_DURATION) {
                    loadDashboardData();
                }
            }
        });

        // Initial load and start auto-refresh
        document.addEventListener('DOMContentLoaded', () => {
            loadDashboardData();
            startAutoRefresh();
        });
    </script>

    </div>
</div>

<?php include 'layouts/footer.php'; ?>
