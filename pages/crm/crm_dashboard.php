<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'CRM Analytics Dashboard';
include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📈 CRM Analytics Dashboard</h1>
                <p class="text-muted">Sales performance insights and customer relationship analytics</p>
            </div>
            <div class="btn-group" role="group">
                <select id="dateRangeFilter" class="form-select">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 3 months</option>
                    <option value="180">Last 6 months</option>
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
            <p class="mt-3 text-muted">Loading CRM analytics...</p>
        </div>

        <!-- Dashboard Content -->
        <div id="dashboardContent" style="display: none;">
            <!-- Key Performance Indicators -->
            <div class="row g-3 mb-4" id="kpiCards">
                <!-- KPI cards will be populated here -->
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-4 mb-4">
                <!-- Lead Conversion Funnel -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Lead Conversion Funnel</h6>
                            <i class="bi bi-funnel text-primary"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="conversionFunnelChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sales Pipeline Value -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Sales Pipeline by Stage</h6>
                            <i class="bi bi-bar-chart text-success"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="pipelineChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-4 mb-4">
                <!-- Revenue Forecast -->
                <div class="col-xl-8 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Revenue Forecast (Next 12 Months)</h6>
                            <i class="bi bi-graph-up-arrow text-success"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueForecastChart" height="120"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Communication Breakdown -->
                <div class="col-xl-4 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Communication Types</h6>
                            <i class="bi bi-pie-chart text-info"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="communicationChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Tables Row -->
            <div class="row g-4 mb-4">
                <!-- Top Performers -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Sales Team Performance</h6>
                            <i class="bi bi-trophy text-warning"></i>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="performanceTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Sales Rep</th>
                                            <th>Leads</th>
                                            <th>Opportunities</th>
                                            <th>Won Value</th>
                                            <th>Avg. Probability</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Performance data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="col-xl-6 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Recent CRM Activities</h6>
                            <i class="bi bi-activity text-primary"></i>
                        </div>
                        <div class="card-body">
                            <div id="activityTimeline" class="activity-timeline">
                                <!-- Recent activities will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Analytics Row -->
            <div class="row g-4 mb-4">
                <!-- Lead Source Analysis -->
                <div class="col-xl-4 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Lead Sources</h6>
                            <i class="bi bi-diagram-2 text-secondary"></i>
                        </div>
                        <div class="card-body">
                            <canvas id="leadSourceChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Conversion Rates -->
                <div class="col-xl-4 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Conversion Metrics</h6>
                            <i class="bi bi-percent text-success"></i>
                        </div>
                        <div class="card-body">
                            <div id="conversionMetrics" class="conversion-metrics">
                                <!-- Conversion metrics will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline Health -->
                <div class="col-xl-4 col-lg-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Pipeline Health</h6>
                            <i class="bi bi-heart-pulse text-danger"></i>
                        </div>
                        <div class="card-body">
                            <div id="pipelineHealth" class="pipeline-health">
                                <!-- Pipeline health metrics will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Management Row -->
            <div class="row g-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">CRM Task Overview</h6>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="taskFilter" id="allTasks" value="all" checked>
                                <label class="btn btn-outline-primary" for="allTasks">All</label>
                                
                                <input type="radio" class="btn-check" name="taskFilter" id="overdueTasks" value="overdue">
                                <label class="btn btn-outline-danger" for="overdueTasks">Overdue</label>
                                
                                <input type="radio" class="btn-check" name="taskFilter" id="todayTasks" value="today">
                                <label class="btn btn-outline-warning" for="todayTasks">Today</label>
                                
                                <input type="radio" class="btn-check" name="taskFilter" id="weekTasks" value="this_week">
                                <label class="btn btn-outline-info" for="weekTasks">This Week</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="taskList" class="task-list">
                                <!-- Tasks will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    max-height: 350px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f1f3f4;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 14px;
    flex-shrink: 0;
}

.activity-content {
    flex-grow: 1;
}

.activity-time {
    font-size: 11px;
    color: #6c757d;
    white-space: nowrap;
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
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.kpi-label {
    font-size: 0.85rem;
    opacity: 0.8;
}

.conversion-metrics {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.metric-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.metric-label {
    font-weight: 500;
    color: #495057;
    flex-grow: 1;
}

.metric-value {
    font-weight: bold;
    font-size: 1.1rem;
}

.pipeline-health {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.health-indicator {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.health-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.health-dot.good { background-color: #28a745; }
.health-dot.warning { background-color: #ffc107; }
.health-dot.danger { background-color: #dc3545; }

.task-list {
    max-height: 400px;
    overflow-y: auto;
}

.task-item {
    display: flex;
    align-items: center;
    padding: 12px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #dee2e6;
}

.task-item.overdue {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.task-item.today {
    border-left-color: #ffc107;
    background: #fffbf0;
}

.task-item.upcoming {
    border-left-color: #17a2b8;
    background: #f0fbff;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let dashboardData = {};
let charts = {};

$(document).ready(function() {
    loadDashboard();
    
    // Refresh dashboard every 10 minutes
    setInterval(refreshDashboard, 600000);
    
    // Handle date range change
    $('#dateRangeFilter').change(function() {
        loadDashboard();
    });
    
    // Handle task filter change
    $('input[name="taskFilter"]').change(function() {
        loadTasks($(this).val());
    });
});

function loadDashboard() {
    $('#loadingSpinner').show();
    $('#dashboardContent').hide();
    
    const dateRange = $('#dateRangeFilter').val();
    
    // Load CRM analytics data
    Promise.all([
        fetch(`crm_api.php?action=crm_statistics&range=${dateRange}`).then(r => r.json()),
        fetch(`crm_api.php?action=lead_conversion_funnel`).then(r => r.json()),
        fetch(`crm_api.php?action=sales_pipeline`).then(r => r.json()),
        fetch(`crm_api.php?action=revenue_forecast`).then(r => r.json()),
        fetch(`crm_api.php?action=activity_timeline&limit=20`).then(r => r.json())
    ]).then(([statsData, funnelData, pipelineData, forecastData, activityData]) => {
        dashboardData = {
            stats: statsData.stats,
            funnel: funnelData.funnel,
            pipeline: pipelineData.pipeline,
            forecast: forecastData.forecast,
            activities: activityData.activities
        };
        
        renderDashboard();
        $('#loadingSpinner').hide();
        $('#dashboardContent').show();
    }).catch(error => {
        console.error('Error loading dashboard:', error);
        $('#loadingSpinner').hide();
        showAlert('Error loading dashboard data', 'error');
    });
}

function renderDashboard() {
    renderKPICards();
    renderCharts();
    renderPerformanceTable();
    renderActivityTimeline();
    renderConversionMetrics();
    renderPipelineHealth();
    loadTasks('all');
}

function renderKPICards() {
    const stats = dashboardData.stats;
    const kpiData = [
        {
            icon: 'bi-person-plus',
            value: stats.leads.total_leads || 0,
            label: 'Total Leads',
            gradient: ['#667eea', '#764ba2']
        },
        {
            icon: 'bi-bullseye',
            value: stats.opportunities.total_opportunities || 0,
            label: 'Opportunities',
            gradient: ['#f093fb', '#f5576c']
        },
        {
            icon: 'bi-currency-rupee',
            value: '₹' + formatCurrency((stats.opportunities.pipeline_value || 0) / 100000) + 'L',
            label: 'Pipeline Value',
            gradient: ['#4facfe', '#00f2fe']
        },
        {
            icon: 'bi-trophy',
            value: '₹' + formatCurrency((stats.opportunities.won_value || 0) / 100000) + 'L',
            label: 'Won Value',
            gradient: ['#43e97b', '#38f9d7']
        },
        {
            icon: 'bi-chat-dots',
            value: stats.communications.total_communications || 0,
            label: 'Communications',
            gradient: ['#fa709a', '#fee140']
        },
        {
            icon: 'bi-percent',
            value: calculateConversionRate() + '%',
            label: 'Conversion Rate',
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
                    <div class="kpi-value">${kpi.value}</div>
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
    
    renderConversionFunnelChart();
    renderPipelineChart();
    renderRevenueForecastChart();
    renderCommunicationChart();
    renderLeadSourceChart();
}

function renderConversionFunnelChart() {
    const ctx = document.getElementById('conversionFunnelChart').getContext('2d');
    const funnelData = dashboardData.funnel || [];
    
    charts.conversionFunnel = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: funnelData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
            datasets: [{
                label: 'Leads',
                data: funnelData.map(item => item.count),
                backgroundColor: [
                    '#6c757d', '#17a2b8', '#28a745', '#ffc107', 
                    '#fd7e14', '#007bff', '#dc3545'
                ],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const item = funnelData[context.dataIndex];
                            return `Conversion Rate: ${item.conversion_rate}%`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function renderPipelineChart() {
    const ctx = document.getElementById('pipelineChart').getContext('2d');
    const pipelineStages = Object.keys(dashboardData.pipeline || {});
    const pipelineValues = pipelineStages.map(stage => 
        (dashboardData.pipeline[stage]?.summary?.stage_value || 0) / 100000
    );
    
    charts.pipeline = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: pipelineStages.map(stage => stage.replace('_', ' ').toUpperCase()),
            datasets: [{
                data: pipelineValues,
                backgroundColor: [
                    '#007bff', '#28a745', '#ffc107', '#fd7e14', 
                    '#6f42c1', '#e83e8c', '#20c997'
                ],
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ₹${context.raw}L`;
                        }
                    }
                }
            }
        }
    });
}

function renderRevenueForecastChart() {
    const ctx = document.getElementById('revenueForecastChart').getContext('2d');
    const forecastData = dashboardData.forecast || [];
    
    charts.revenueForecast = new Chart(ctx, {
        type: 'line',
        data: {
            labels: forecastData.map(item => {
                const [year, month] = item.month.split('-');
                const date = new Date(year, month - 1);
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Forecasted Revenue',
                    data: forecastData.map(item => item.forecasted_revenue / 100000),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Potential Revenue',
                    data: forecastData.map(item => item.potential_revenue / 100000),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: false,
                    tension: 0.4,
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (₹ Lakhs)'
                    }
                }
            }
        }
    });
}

function renderCommunicationChart() {
    const ctx = document.getElementById('communicationChart').getContext('2d');
    const commStats = dashboardData.stats.communications || {};
    
    const communicationData = [
        commStats.emails || 0,
        commStats.phone_calls || 0,
        commStats.meetings || 0,
        (commStats.total_communications || 0) - (commStats.emails || 0) - (commStats.phone_calls || 0) - (commStats.meetings || 0)
    ];
    
    charts.communication = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Email', 'Phone', 'Meetings', 'Other'],
            datasets: [{
                data: communicationData,
                backgroundColor: ['#007bff', '#28a745', '#ffc107', '#6c757d'],
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
}

function renderLeadSourceChart() {
    const ctx = document.getElementById('leadSourceChart').getContext('2d');
    
    // Mock data for lead sources
    const sourceData = [
        { source: 'Website', count: 45 },
        { source: 'Referral', count: 32 },
        { source: 'Social Media', count: 28 },
        { source: 'Cold Call', count: 18 },
        { source: 'Email', count: 15 },
        { source: 'Event', count: 12 }
    ];
    
    charts.leadSource = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sourceData.map(item => item.source),
            datasets: [{
                label: 'Leads',
                data: sourceData.map(item => item.count),
                backgroundColor: '#17a2b8',
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
                    beginAtZero: true
                }
            }
        }
    });
}

function renderPerformanceTable() {
    const performance = dashboardData.stats.user_performance || [];
    let html = '';
    
    performance.forEach(user => {
        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            ${user.name ? user.name.charAt(0) : 'U'}
                        </div>
                        <span>${user.name || 'Unknown'}</span>
                    </div>
                </td>
                <td><span class="badge bg-info">${user.leads_assigned || 0}</span></td>
                <td><span class="badge bg-primary">${user.opportunities_assigned || 0}</span></td>
                <td>₹${formatCurrency((user.won_value || 0) / 100000)}L</td>
                <td>${Math.round(user.avg_opportunity_probability || 0)}%</td>
            </tr>
        `;
    });
    
    $('#performanceTable tbody').html(html);
}

function renderActivityTimeline() {
    const activities = dashboardData.activities || [];
    let html = '';
    
    activities.forEach(activity => {
        const iconMap = {
            'lead_created': { icon: 'bi-person-plus', color: 'bg-success' },
            'status_change': { icon: 'bi-arrow-up-circle', color: 'bg-warning' },
            'communication_logged': { icon: 'bi-chat-dots', color: 'bg-info' },
            'follow_up_scheduled': { icon: 'bi-calendar-plus', color: 'bg-primary' },
            'opportunity_created': { icon: 'bi-bullseye', color: 'bg-success' },
            'task_completed': { icon: 'bi-check-circle', color: 'bg-success' }
        };
        
        const activityIcon = iconMap[activity.activity_type] || { icon: 'bi-circle', color: 'bg-secondary' };
        const entityName = activity.customer_name || activity.lead_company || 'Unknown';
        
        html += `
            <div class="activity-item">
                <div class="activity-icon ${activityIcon.color} text-white">
                    <i class="bi ${activityIcon.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">${activity.description}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted">${entityName}</small>
                        <div class="activity-time">${formatTimeAgo(activity.activity_date)}</div>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#activityTimeline').html(html);
}

function renderConversionMetrics() {
    const stats = dashboardData.stats;
    const metrics = [
        {
            label: 'Lead to Opportunity',
            value: calculateLeadToOpportunityRate() + '%',
            color: '#28a745'
        },
        {
            label: 'Opportunity to Win',
            value: calculateOpportunityWinRate() + '%',
            color: '#007bff'
        },
        {
            label: 'Avg. Deal Size',
            value: '₹' + formatCurrency(calculateAvgDealSize() / 100000) + 'L',
            color: '#ffc107'
        },
        {
            label: 'Avg. Sales Cycle',
            value: '45 days',
            color: '#6f42c1'
        }
    ];
    
    let html = '';
    metrics.forEach(metric => {
        html += `
            <div class="metric-item">
                <span class="metric-label">${metric.label}</span>
                <span class="metric-value" style="color: ${metric.color}">${metric.value}</span>
            </div>
        `;
    });
    
    $('#conversionMetrics').html(html);
}

function renderPipelineHealth() {
    const pipeline = dashboardData.pipeline || {};
    const totalValue = Object.values(pipeline).reduce((sum, stage) => 
        sum + (stage.summary?.stage_value || 0), 0
    );
    
    const health = [
        {
            label: 'Pipeline Value',
            value: '₹' + formatCurrency(totalValue / 100000) + 'L',
            status: totalValue > 5000000 ? 'good' : totalValue > 2000000 ? 'warning' : 'danger'
        },
        {
            label: 'Avg. Deal Size',
            value: '₹' + formatCurrency(calculateAvgDealSize() / 100000) + 'L',
            status: 'good'
        },
        {
            label: 'Pipeline Velocity',
            value: '12 days avg.',
            status: 'warning'
        },
        {
            label: 'Win Rate',
            value: calculateOpportunityWinRate() + '%',
            status: calculateOpportunityWinRate() > 20 ? 'good' : 'warning'
        }
    ];
    
    let html = '';
    health.forEach(item => {
        html += `
            <div class="health-indicator">
                <div class="health-dot ${item.status}"></div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${item.value}</div>
                    <small class="text-muted">${item.label}</small>
                </div>
            </div>
        `;
    });
    
    $('#pipelineHealth').html(html);
}

function loadTasks(filter = 'all') {
    fetch(`crm_api.php?action=task_list&due_filter=${filter}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTaskList(data.tasks);
            }
        })
        .catch(error => console.error('Error loading tasks:', error));
}

function renderTaskList(tasks) {
    let html = '';
    
    if (tasks.length === 0) {
        html = '<div class="text-center py-4 text-muted">No tasks found</div>';
    } else {
        tasks.forEach(task => {
            const dueDate = new Date(task.due_date);
            const today = new Date();
            let taskClass = 'task-item';
            
            if (dueDate < today && task.status !== 'completed') {
                taskClass += ' overdue';
            } else if (dueDate.toDateString() === today.toDateString()) {
                taskClass += ' today';
            } else {
                taskClass += ' upcoming';
            }
            
            const entityName = task.customer_name || task.lead_company || 'Unknown';
            
            html += `
                <div class="${taskClass}">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${task.task_title}</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">${entityName} • ${task.assigned_name || 'Unassigned'}</small>
                            <small class="text-muted">Due: ${formatDate(task.due_date)}</small>
                        </div>
                    </div>
                    <div class="ms-3">
                        <span class="badge bg-${getPriorityColor(task.priority)}">${task.priority.toUpperCase()}</span>
                    </div>
                </div>
            `;
        });
    }
    
    $('#taskList').html(html);
}

// Utility functions
function calculateConversionRate() {
    const stats = dashboardData.stats;
    const totalLeads = stats.leads?.total_leads || 0;
    const convertedLeads = stats.leads?.converted_leads || 0;
    return totalLeads > 0 ? Math.round((convertedLeads / totalLeads) * 100) : 0;
}

function calculateLeadToOpportunityRate() {
    const stats = dashboardData.stats;
    const totalLeads = stats.leads?.total_leads || 0;
    const opportunities = stats.opportunities?.total_opportunities || 0;
    return totalLeads > 0 ? Math.round((opportunities / totalLeads) * 100) : 0;
}

function calculateOpportunityWinRate() {
    const stats = dashboardData.stats;
    const totalOpps = stats.opportunities?.total_opportunities || 0;
    const wonOpps = stats.opportunities?.won_opportunities || 0;
    return totalOpps > 0 ? Math.round((wonOpps / totalOpps) * 100) : 0;
}

function calculateAvgDealSize() {
    const stats = dashboardData.stats;
    const wonValue = stats.opportunities?.won_value || 0;
    const wonCount = stats.opportunities?.won_opportunities || 0;
    return wonCount > 0 ? wonValue / wonCount : 0;
}

function formatCurrency(amount) {
    return amount.toFixed(1);
}

function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
    return Math.floor(diffInSeconds / 86400) + 'd ago';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function getPriorityColor(priority) {
    const colors = {
        'urgent': 'danger',
        'high': 'warning',
        'medium': 'info',
        'low': 'secondary'
    };
    return colors[priority] || 'secondary';
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
    setTimeout(() => $('.alert').alert('close'), 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
