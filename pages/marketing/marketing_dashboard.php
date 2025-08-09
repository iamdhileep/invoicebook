<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../db.php';

// Get marketing overview data
$overview_data = [];

// Total campaigns
$campaigns_query = "SELECT COUNT(*) as total_campaigns FROM marketing_campaigns";
$campaigns_result = mysqli_query($conn, $campaigns_query);
$overview_data['total_campaigns'] = mysqli_fetch_assoc($campaigns_result)['total_campaigns'];

// Active campaigns
$active_campaigns_query = "SELECT COUNT(*) as active_campaigns FROM marketing_campaigns 
                          WHERE status = 'active' AND (end_date IS NULL OR end_date >= CURDATE())";
$active_campaigns_result = mysqli_query($conn, $active_campaigns_query);
$overview_data['active_campaigns'] = mysqli_fetch_assoc($active_campaigns_result)['active_campaigns'];

// Email performance
$email_performance_query = "SELECT 
                           COUNT(*) as total_sent,
                           SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as total_opened,
                           SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as total_clicked
                           FROM email_campaigns 
                           WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$email_performance_result = mysqli_query($conn, $email_performance_query);
$email_data = mysqli_fetch_assoc($email_performance_result);
$overview_data['emails_sent'] = $email_data['total_sent'];
$overview_data['open_rate'] = $email_data['total_sent'] > 0 ? 
    round(($email_data['total_opened'] / $email_data['total_sent']) * 100, 2) : 0;
$overview_data['click_rate'] = $email_data['total_sent'] > 0 ? 
    round(($email_data['total_clicked'] / $email_data['total_sent']) * 100, 2) : 0;

// Newsletter subscribers
$newsletter_query = "SELECT COUNT(*) as total_subscribers FROM newsletter_subscriptions WHERE is_active = 1";
$newsletter_result = mysqli_query($conn, $newsletter_query);
$overview_data['newsletter_subscribers'] = mysqli_fetch_assoc($newsletter_result)['total_subscribers'];

// Marketing segments
$segments_query = "SELECT COUNT(*) as total_segments FROM marketing_segments";
$segments_result = mysqli_query($conn, $segments_query);
$overview_data['marketing_segments'] = mysqli_fetch_assoc($segments_result)['total_segments'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Analytics Dashboard - Billbook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <style>
        .dashboard-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .metric-label {
            opacity: 0.9;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .small-chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .performance-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
        }
        .nav-item .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-item .nav-link:hover, .nav-item .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-chart-line me-2"></i>
                        Marketing Analytics
                    </h4>
                    <ul class="nav nav-pills flex-column" id="marketingSidebar">
                        <li class="nav-item">
                            <a class="nav-link active" href="#overview" onclick="showSection('overview')">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#campaigns" onclick="showSection('campaigns')">
                                <i class="fas fa-bullhorn me-2"></i>
                                Campaign Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#email" onclick="showSection('email')">
                                <i class="fas fa-envelope me-2"></i>
                                Email Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#segments" onclick="showSection('segments')">
                                <i class="fas fa-users me-2"></i>
                                Segmentation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#automation" onclick="showSection('automation')">
                                <i class="fas fa-robot me-2"></i>
                                Automation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#social" onclick="showSection('social')">
                                <i class="fab fa-facebook me-2"></i>
                                Social Media
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#newsletter" onclick="showSection('newsletter')">
                                <i class="fas fa-newspaper me-2"></i>
                                Newsletter
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#roi" onclick="showSection('roi')">
                                <i class="fas fa-dollar-sign me-2"></i>
                                ROI Analysis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#leads" onclick="showSection('leads')">
                                <i class="fas fa-star me-2"></i>
                                Lead Scoring
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Marketing Analytics Dashboard</h2>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="dateRange" onchange="updateDashboard()">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                        <button class="btn btn-outline-primary" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-primary" onclick="refreshDashboard()">
                            <i class="fas fa-sync me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading analytics data...</p>
                </div>

                <!-- Overview Section -->
                <div id="overview" class="dashboard-section">
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="metric-card position-relative">
                                <i class="fas fa-bullhorn stat-icon"></i>
                                <div class="metric-label">Total Campaigns</div>
                                <div class="metric-value"><?php echo $overview_data['total_campaigns']; ?></div>
                                <small><?php echo $overview_data['active_campaigns']; ?> Active</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="metric-card position-relative" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-envelope stat-icon"></i>
                                <div class="metric-label">Emails Sent (30d)</div>
                                <div class="metric-value"><?php echo number_format($overview_data['emails_sent']); ?></div>
                                <small><?php echo $overview_data['open_rate']; ?>% Open Rate</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="metric-card position-relative" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-mouse-pointer stat-icon"></i>
                                <div class="metric-label">Click Through Rate</div>
                                <div class="metric-value"><?php echo $overview_data['click_rate']; ?>%</div>
                                <small>Last 30 days</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="metric-card position-relative" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <i class="fas fa-users stat-icon"></i>
                                <div class="metric-label">Newsletter Subscribers</div>
                                <div class="metric-value"><?php echo number_format($overview_data['newsletter_subscribers']); ?></div>
                                <small><?php echo $overview_data['marketing_segments']; ?> Segments</small>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">Campaign Performance Trends</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="campaignTrendsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0 pb-0">
                                    <h5 class="card-title mb-0">Channel Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="small-chart-container">
                                        <canvas id="channelDistributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Performance Section -->
                <div id="campaigns" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Campaign Performance Analysis</h5>
                                        <button class="btn btn-sm btn-outline-primary" onclick="loadCampaignAnalytics()">
                                            <i class="fas fa-sync me-1"></i>Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="campaignsTable">
                                            <thead>
                                                <tr>
                                                    <th>Campaign</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Emails Sent</th>
                                                    <th>Open Rate</th>
                                                    <th>Click Rate</th>
                                                    <th>Budget</th>
                                                    <th>ROI</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Analytics Section -->
                <div id="email" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Email Performance Metrics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="emailMetricsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Best Send Times</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="sendTimesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Top Performing Templates</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="templatesTable">
                                            <thead>
                                                <tr>
                                                    <th>Template Name</th>
                                                    <th>Category</th>
                                                    <th>Emails Sent</th>
                                                    <th>Open Rate</th>
                                                    <th>Click Rate</th>
                                                    <th>Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Segmentation Section -->
                <div id="segments" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Segment Performance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="segmentsTable">
                                            <thead>
                                                <tr>
                                                    <th>Segment Name</th>
                                                    <th>Customers</th>
                                                    <th>Avg Order Value</th>
                                                    <th>Email Engagement</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Segment Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="small-chart-container">
                                        <canvas id="segmentDistributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation Section -->
                <div id="automation" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Marketing Automation Rules</h5>
                                        <button class="btn btn-primary btn-sm" onclick="createAutomationRule()">
                                            <i class="fas fa-plus me-1"></i>Create Rule
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body" id="automationRules">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media Section -->
                <div id="social" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Social Media Overview</h5>
                                </div>
                                <div class="card-body" id="socialOverview">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Scheduled Posts</h5>
                                </div>
                                <div class="card-body" id="scheduledPosts">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Newsletter Section -->
                <div id="newsletter" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Subscriber Growth</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="subscriberGrowthChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Subscriber Sources</h5>
                                </div>
                                <div class="card-body">
                                    <div class="small-chart-container">
                                        <canvas id="subscriberSourcesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROI Analysis Section -->
                <div id="roi" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Return on Investment Analysis</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="chart-container">
                                                <canvas id="roiChart"></canvas>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Channel</th>
                                                            <th>ROI</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="roiTableBody">
                                                        <!-- Dynamic content -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lead Scoring Section -->
                <div id="leads" class="dashboard-section" style="display: none;">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header bg-white border-0">
                                    <h5 class="card-title mb-0">Lead Scoring & Quality</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="leadsTable">
                                            <thead>
                                                <tr>
                                                    <th>Lead</th>
                                                    <th>Email</th>
                                                    <th>Score</th>
                                                    <th>Quality</th>
                                                    <th>Last Activity</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Dynamic content -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentSection = 'overview';
        let charts = {};

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
        });

        function initializeDashboard() {
            loadOverviewCharts();
        }

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionName).style.display = 'block';
            
            // Update sidebar
            document.querySelectorAll('#marketingSidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[href="#${sectionName}"]`).classList.add('active');
            
            currentSection = sectionName;
            
            // Load section specific data
            loadSectionData(sectionName);
        }

        function loadSectionData(section) {
            switch(section) {
                case 'campaigns':
                    loadCampaignAnalytics();
                    break;
                case 'email':
                    loadEmailAnalytics();
                    break;
                case 'segments':
                    loadSegmentationData();
                    break;
                case 'automation':
                    loadAutomationData();
                    break;
                case 'social':
                    loadSocialMediaData();
                    break;
                case 'newsletter':
                    loadNewsletterData();
                    break;
                case 'roi':
                    loadROIData();
                    break;
                case 'leads':
                    loadLeadScoringData();
                    break;
            }
        }

        function loadOverviewCharts() {
            // Campaign Trends Chart
            const ctx1 = document.getElementById('campaignTrendsChart').getContext('2d');
            charts.campaignTrends = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Emails Sent',
                        data: [1200, 1900, 1500, 2200, 1800, 2400],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Opens',
                        data: [300, 475, 375, 550, 450, 600],
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Channel Distribution Chart
            const ctx2 = document.getElementById('channelDistributionChart').getContext('2d');
            charts.channelDistribution = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Email', 'Social Media', 'PPC', 'Organic'],
                    datasets: [{
                        data: [45, 25, 20, 10],
                        backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }

        function loadCampaignAnalytics() {
            showLoading();
            
            // Mock data for demonstration
            const campaigns = [
                {
                    name: 'Summer Sale 2024',
                    type: 'Promotional',
                    status: 'Active',
                    emails_sent: 5420,
                    open_rate: '24.5%',
                    click_rate: '3.2%',
                    budget: '$2,500',
                    roi: '245%'
                },
                {
                    name: 'Welcome Series',
                    type: 'Nurturing',
                    status: 'Active',
                    emails_sent: 1230,
                    open_rate: '32.1%',
                    click_rate: '5.8%',
                    budget: '$500',
                    roi: '180%'
                }
            ];
            
            const tbody = document.querySelector('#campaignsTable tbody');
            tbody.innerHTML = '';
            
            campaigns.forEach(campaign => {
                const row = `
                    <tr>
                        <td><strong>${campaign.name}</strong></td>
                        <td><span class="badge bg-secondary">${campaign.type}</span></td>
                        <td><span class="badge bg-success">${campaign.status}</span></td>
                        <td>${campaign.emails_sent.toLocaleString()}</td>
                        <td>${campaign.open_rate}</td>
                        <td>${campaign.click_rate}</td>
                        <td>${campaign.budget}</td>
                        <td><span class="text-success fw-bold">${campaign.roi}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewCampaign()">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editCampaign()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
            
            hideLoading();
        }

        function loadEmailAnalytics() {
            // Email Metrics Chart
            const ctx = document.getElementById('emailMetricsChart').getContext('2d');
            if (charts.emailMetrics) {
                charts.emailMetrics.destroy();
            }
            
            charts.emailMetrics = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sent', 'Delivered', 'Opened', 'Clicked', 'Unsubscribed'],
                    datasets: [{
                        label: 'Email Metrics',
                        data: [10000, 9500, 2850, 456, 23],
                        backgroundColor: [
                            '#667eea',
                            '#43e97b',
                            '#f093fb',
                            '#4facfe',
                            '#f5576c'
                        ]
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

            // Best Send Times Chart
            const ctx2 = document.getElementById('sendTimesChart').getContext('2d');
            if (charts.sendTimes) {
                charts.sendTimes.destroy();
            }
            
            charts.sendTimes = new Chart(ctx2, {
                type: 'radar',
                data: {
                    labels: ['12 AM', '3 AM', '6 AM', '9 AM', '12 PM', '3 PM', '6 PM', '9 PM'],
                    datasets: [{
                        label: 'Open Rate by Hour',
                        data: [5, 3, 8, 25, 18, 15, 22, 12],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.2)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function loadSegmentationData() {
            // Mock segmentation data
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_segmentation_insights'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update segments table
                    const tbody = document.querySelector('#segmentsTable tbody');
                    tbody.innerHTML = '';
                    
                    data.insights.segments.forEach(segment => {
                        const row = `
                            <tr>
                                <td><strong>${segment.name}</strong></td>
                                <td>${segment.customer_count || 0}</td>
                                <td>$${(segment.avg_order_value || 0).toFixed(2)}</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: ${Math.random() * 100}%">
                                            ${(Math.random() * 50 + 10).toFixed(1)}%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSegment(${segment.id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            });
        }

        function loadAutomationData() {
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_automation_triggers'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('automationRules');
                    container.innerHTML = '';
                    
                    // Show available triggers
                    let html = '<h6>Available Triggers</h6><div class="row">';
                    Object.entries(data.triggers).forEach(([key, trigger]) => {
                        html += `
                            <div class="col-md-6 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="card-title">${trigger.name}</h6>
                                        <p class="card-text small text-muted">${trigger.description}</p>
                                        <button class="btn btn-sm btn-primary" onclick="createRuleFromTrigger('${key}')">
                                            Create Rule
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    
                    // Show existing rules
                    if (data.existing_rules.length > 0) {
                        html += '<h6 class="mt-4">Active Rules</h6><div class="list-group">';
                        data.existing_rules.forEach(rule => {
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${rule.name}</h6>
                                            <small class="text-muted">Trigger: ${rule.trigger_event}</small>
                                        </div>
                                        <div>
                                            <span class="badge bg-success">Active</span>
                                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="toggleRule(${rule.id})">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }
                    
                    container.innerHTML = html;
                }
            });
        }

        function loadSocialMediaData() {
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_social_media_insights'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Social Overview
                    const overviewContainer = document.getElementById('socialOverview');
                    let overviewHtml = '';
                    
                    Object.entries(data.insights.platforms).forEach(([platform, stats]) => {
                        overviewHtml += `
                            <div class="row mb-3">
                                <div class="col-3">
                                    <i class="fab fa-${platform} fa-2x text-primary"></i>
                                    <h6 class="mt-2">${platform.charAt(0).toUpperCase() + platform.slice(1)}</h6>
                                </div>
                                <div class="col-9">
                                    <div class="row">
                                        <div class="col-3">
                                            <small class="text-muted">Followers</small>
                                            <div class="fw-bold">${stats.followers.toLocaleString()}</div>
                                        </div>
                                        <div class="col-3">
                                            <small class="text-muted">Posts</small>
                                            <div class="fw-bold">${stats.posts_this_month}</div>
                                        </div>
                                        <div class="col-3">
                                            <small class="text-muted">Engagement</small>
                                            <div class="fw-bold">${stats.engagement_rate}%</div>
                                        </div>
                                        <div class="col-3">
                                            <small class="text-muted">Reach</small>
                                            <div class="fw-bold">${stats.reach.toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        `;
                    });
                    
                    overviewContainer.innerHTML = overviewHtml;
                    
                    // Scheduled Posts
                    const postsContainer = document.getElementById('scheduledPosts');
                    if (data.insights.upcoming_posts.length > 0) {
                        let postsHtml = '';
                        data.insights.upcoming_posts.forEach(post => {
                            postsHtml += `
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong>${post.platform}</strong>
                                        <small class="text-muted">${post.scheduled_date}</small>
                                    </div>
                                    <p class="mb-0 small">${post.content}</p>
                                </div>
                            `;
                        });
                        postsContainer.innerHTML = postsHtml;
                    } else {
                        postsContainer.innerHTML = '<p class="text-muted">No scheduled posts</p>';
                    }
                }
            });
        }

        function loadNewsletterData() {
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_newsletter_analytics'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Subscriber Growth Chart
                    const ctx = document.getElementById('subscriberGrowthChart').getContext('2d');
                    if (charts.subscriberGrowth) {
                        charts.subscriberGrowth.destroy();
                    }
                    
                    const labels = data.analytics.subscriber_growth.map(item => item.date);
                    const growthData = data.analytics.subscriber_growth.map(item => item.total_subscribers);
                    
                    charts.subscriberGrowth = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Total Subscribers',
                                data: growthData,
                                borderColor: '#43e97b',
                                backgroundColor: 'rgba(67, 233, 123, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    
                    // Subscriber Sources Chart
                    const ctx2 = document.getElementById('subscriberSourcesChart').getContext('2d');
                    if (charts.subscriberSources) {
                        charts.subscriberSources.destroy();
                    }
                    
                    const sourceLabels = data.analytics.sources.map(item => item.source || 'Direct');
                    const sourceData = data.analytics.sources.map(item => item.count);
                    
                    charts.subscriberSources = new Chart(ctx2, {
                        type: 'pie',
                        data: {
                            labels: sourceLabels,
                            datasets: [{
                                data: sourceData,
                                backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#f5576c']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }
            });
        }

        function loadROIData() {
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_roi_analysis'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // ROI Chart
                    const ctx = document.getElementById('roiChart').getContext('2d');
                    if (charts.roi) {
                        charts.roi.destroy();
                    }
                    
                    charts.roi = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data.roi_data.channels),
                            datasets: [{
                                label: 'ROI %',
                                data: Object.values(data.roi_data.channels).map(channel => channel.roi),
                                backgroundColor: ['#667eea', '#f093fb', '#4facfe']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    // ROI Table
                    const tbody = document.getElementById('roiTableBody');
                    tbody.innerHTML = '';
                    
                    Object.entries(data.roi_data.channels).forEach(([channel, data]) => {
                        const row = `
                            <tr>
                                <td>${channel.replace('_', ' ').toUpperCase()}</td>
                                <td class="text-success fw-bold">${data.roi}%</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            });
        }

        function loadLeadScoringData() {
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_lead_scoring'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#leadsTable tbody');
                    tbody.innerHTML = '';
                    
                    data.leads.forEach(lead => {
                        const row = `
                            <tr>
                                <td>
                                    <strong>${lead.customer_name}</strong><br>
                                    <small class="text-muted">Reg: ${new Date(lead.registration_date).toLocaleDateString()}</small>
                                </td>
                                <td>${lead.email}</td>
                                <td>
                                    <span class="badge bg-primary fs-6">${lead.total_score}</span>
                                </td>
                                <td>
                                    <span class="badge bg-${lead.quality_color}">${lead.quality}</span>
                                </td>
                                <td>
                                    <small class="text-muted">Recent activity</small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewCustomerJourney(${lead.id})">
                                        <i class="fas fa-route"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="contactLead(${lead.id})">
                                        <i class="fas fa-phone"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            });
        }

        // Utility functions
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        function updateDashboard() {
            const dateRange = document.getElementById('dateRange').value;
            // Refresh current section with new date range
            loadSectionData(currentSection);
        }

        function refreshDashboard() {
            loadSectionData(currentSection);
        }

        function exportData() {
            const exportType = currentSection === 'overview' ? 'campaigns' : currentSection;
            
            fetch('marketing_api.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'export_marketing_data',
                    export_type: exportType,
                    format: 'csv'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create and download CSV
                    const csvContent = data.data.map(row => row.join(',')).join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                }
            });
        }

        // Action functions
        function viewCampaign() {
            alert('Campaign details would open here');
        }

        function editCampaign() {
            alert('Campaign editor would open here');
        }

        function viewSegment(id) {
            alert('Segment details for ID: ' + id);
        }

        function createAutomationRule() {
            alert('Automation rule creator would open here');
        }

        function createRuleFromTrigger(trigger) {
            alert('Creating rule for trigger: ' + trigger);
        }

        function toggleRule(id) {
            alert('Toggle rule status for ID: ' + id);
        }

        function viewCustomerJourney(customerId) {
            alert('Customer journey for ID: ' + customerId);
        }

        function contactLead(leadId) {
            alert('Contact lead ID: ' + leadId);
        }
    </script>
</body>
</html>
