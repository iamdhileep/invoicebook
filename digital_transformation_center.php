<?php
/**
 * Digital Transformation Center
 * Comprehensive digital transformation management and automation hub
 */

session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
include 'db.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_transformation_metrics':
            getTransformationMetrics($conn);
            break;
        case 'analyze_digital_readiness':
            analyzeDigitalReadiness($conn);
            break;
        case 'generate_automation_recommendations':
            generateAutomationRecommendations($conn);
            break;
        case 'create_transformation_initiative':
            createTransformationInitiative($conn, $_POST);
            break;
        case 'update_initiative_progress':
            updateInitiativeProgress($conn, $_POST);
            break;
    }
    exit;
}

// Digital Transformation Functions
function getTransformationMetrics($conn) {
    $metrics = [
        'digital_maturity_score' => 78.5,
        'automation_coverage' => 65.2,
        'cloud_adoption' => 82.7,
        'data_analytics_maturity' => 71.3,
        'employee_digital_skills' => 74.8,
        'customer_digital_experience' => 88.1,
        'process_digitization' => 69.4,
        'innovation_index' => 76.9
    ];
    
    echo json_encode(['success' => true, 'metrics' => $metrics]);
}

function analyzeDigitalReadiness($conn) {
    $analysis = [
        'overall_readiness' => 'Advanced',
        'readiness_score' => 82.3,
        'strengths' => [
            'Strong cloud infrastructure adoption (82.7%)',
            'Excellent customer digital experience (88.1%)',
            'High innovation index (76.9%)',
            'Good employee digital skills (74.8%)'
        ],
        'improvement_areas' => [
            'Process digitization needs acceleration (69.4%)',
            'Automation coverage requires expansion (65.2%)',
            'Data analytics capabilities need enhancement (71.3%)',
            'Digital maturity can be improved (78.5%)'
        ],
        'next_steps' => [
            'Implement advanced process automation',
            'Expand AI and ML capabilities',
            'Enhance data analytics infrastructure',
            'Develop comprehensive digital training programs'
        ],
        'roi_projection' => '235% over 18 months'
    ];
    
    echo json_encode(['success' => true, 'analysis' => $analysis]);
}

function generateAutomationRecommendations($conn) {
    $recommendations = [
        [
            'category' => 'Process Automation',
            'title' => 'Workflow Automation Suite',
            'description' => 'Automate repetitive business processes and approval workflows',
            'impact' => 'High',
            'effort' => 'Medium',
            'timeline' => '3-4 months',
            'cost_saving' => '₹8-12 Lakhs annually',
            'efficiency_gain' => '45%',
            'priority' => 'High'
        ],
        [
            'category' => 'Data Analytics',
            'title' => 'AI-Powered Business Intelligence',
            'description' => 'Implement machine learning for predictive analytics and insights',
            'impact' => 'Very High',
            'effort' => 'High',
            'timeline' => '6-8 months',
            'cost_saving' => '₹15-20 Lakhs annually',
            'efficiency_gain' => '60%',
            'priority' => 'High'
        ],
        [
            'category' => 'Customer Experience',
            'title' => 'Omnichannel Digital Platform',
            'description' => 'Unified customer experience across all digital touchpoints',
            'impact' => 'High',
            'effort' => 'Medium',
            'timeline' => '4-5 months',
            'cost_saving' => '₹10-15 Lakhs annually',
            'efficiency_gain' => '35%',
            'priority' => 'Medium'
        ],
        [
            'category' => 'Infrastructure',
            'title' => 'Cloud-Native Architecture',
            'description' => 'Migrate to microservices and containerized infrastructure',
            'impact' => 'Medium',
            'effort' => 'High',
            'timeline' => '8-10 months',
            'cost_saving' => '₹12-18 Lakhs annually',
            'efficiency_gain' => '40%',
            'priority' => 'Medium'
        ]
    ];
    
    echo json_encode(['success' => true, 'recommendations' => $recommendations]);
}

function createTransformationInitiative($conn, $data) {
    $initiative = [
        'id' => rand(1000, 9999),
        'title' => $data['title'] ?? 'New Digital Initiative',
        'category' => $data['category'] ?? 'General',
        'description' => $data['description'] ?? '',
        'priority' => $data['priority'] ?? 'medium',
        'timeline' => $data['timeline'] ?? '3 months',
        'budget' => $data['budget'] ?? '₹5-10 Lakhs',
        'status' => 'planning',
        'progress' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode(['success' => true, 'initiative' => $initiative, 'message' => 'Digital transformation initiative created successfully']);
}

function updateInitiativeProgress($conn, $data) {
    $initiative_id = $data['initiative_id'] ?? 0;
    $progress = $data['progress'] ?? 0;
    
    echo json_encode(['success' => true, 'message' => "Initiative #{$initiative_id} progress updated to {$progress}%"]);
}

$page_title = 'Digital Transformation Center';
include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Transformation Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-arrow-up-right-circle text-primary me-3"></i>Digital Transformation Center
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Comprehensive digital transformation management and automation hub</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-warning" onclick="analyzeDigitalReadiness()">
                    <i class="bi bi-search me-2"></i>Readiness Analysis
                </button>
                <button class="btn btn-outline-success" onclick="showCreateInitiativeModal()">
                    <i class="bi bi-plus-circle me-2"></i>New Initiative
                </button>
                <button class="btn btn-primary" onclick="refreshTransformationData()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Digital Maturity Dashboard -->
        <div class="row g-4 mb-4">
            <!-- Digital Maturity Score -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white p-4">
                        <div class="maturity-gauge mb-3">
                            <div class="gauge-circle" id="maturityGauge">
                                <canvas width="120" height="120"></canvas>
                                <div class="gauge-center">
                                    <span class="gauge-value">78.5</span>
                                    <small class="gauge-label">Digital Maturity</small>
                                </div>
                            </div>
                        </div>
                        <h6 class="mb-2">Advanced Level</h6>
                        <small class="opacity-75">Ready for next-gen transformation</small>
                    </div>
                </div>
            </div>

            <!-- Transformation Metrics -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-speedometer2 text-primary me-2"></i>
                            Transformation Metrics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <div class="metric-card text-center p-3 border rounded">
                                    <div class="metric-icon bg-success bg-opacity-10 rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                                        <i class="bi bi-cloud text-success" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h4 class="text-success mb-1">82.7%</h4>
                                    <small class="text-muted">Cloud Adoption</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="metric-card text-center p-3 border rounded">
                                    <div class="metric-icon bg-primary bg-opacity-10 rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                                        <i class="bi bi-robot text-primary" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h4 class="text-primary mb-1">65.2%</h4>
                                    <small class="text-muted">Automation Coverage</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="metric-card text-center p-3 border rounded">
                                    <div class="metric-icon bg-info bg-opacity-10 rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                                        <i class="bi bi-graph-up text-info" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h4 class="text-info mb-1">71.3%</h4>
                                    <small class="text-muted">Data Analytics</small>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="metric-card text-center p-3 border rounded">
                                    <div class="metric-icon bg-warning bg-opacity-10 rounded-circle p-3 mx-auto mb-2" style="width: 60px; height: 60px;">
                                        <i class="bi bi-people text-warning" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h4 class="text-warning mb-1">74.8%</h4>
                                    <small class="text-muted">Digital Skills</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button class="btn btn-outline-primary btn-sm me-2" onclick="loadDetailedMetrics()">
                                <i class="bi bi-bar-chart me-1"></i>Detailed View
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="generateMetricsReport()">
                                <i class="bi bi-file-earmark-text me-1"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transformation Initiatives -->
        <div class="row g-4 mb-4">
            <!-- Active Initiatives -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-rocket text-success me-2"></i>
                                Active Transformation Initiatives
                            </h5>
                            <button class="btn btn-outline-success btn-sm" onclick="showCreateInitiativeModal()">
                                <i class="bi bi-plus-circle me-1"></i>Add Initiative
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="initiatives-container" id="initiativesContainer">
                            <!-- Sample Initiatives -->
                            <div class="initiative-card border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1">AI-Powered Analytics Platform</h6>
                                        <span class="badge bg-primary">Data Analytics</span>
                                    </div>
                                    <span class="badge bg-warning">In Progress</span>
                                </div>
                                <p class="small text-muted mb-3">Implementing machine learning algorithms for predictive business analytics and automated reporting.</p>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: 65%;">
                                        <span class="visually-hidden">65% Complete</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">65% Complete • Due: Q1 2026</small>
                                    <div>
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewInitiativeDetails(1001)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" onclick="updateInitiativeProgress(1001)">
                                            <i class="bi bi-arrow-up-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="initiative-card border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1">Process Automation Suite</h6>
                                        <span class="badge bg-success">Automation</span>
                                    </div>
                                    <span class="badge bg-info">Planning</span>
                                </div>
                                <p class="small text-muted mb-3">Comprehensive workflow automation to eliminate manual processes and improve efficiency.</p>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 25%;">
                                        <span class="visually-hidden">25% Complete</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">25% Complete • Due: Q2 2026</small>
                                    <div>
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewInitiativeDetails(1002)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" onclick="updateInitiativeProgress(1002)">
                                            <i class="bi bi-arrow-up-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="initiative-card border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1">Cloud Infrastructure Migration</h6>
                                        <span class="badge bg-info">Infrastructure</span>
                                    </div>
                                    <span class="badge bg-success">Completed</span>
                                </div>
                                <p class="small text-muted mb-3">Migration to cloud-native architecture with containerization and microservices.</p>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 100%;">
                                        <span class="visually-hidden">100% Complete</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-success">100% Complete • Completed Q4 2025</small>
                                    <div>
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewInitiativeDetails(1003)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="viewInitiativeReport(1003)">
                                            <i class="bi bi-file-text"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transformation Insights -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb text-warning me-2"></i>
                            Transformation Insights
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="insights-container" id="insightsContainer">
                            <div class="insight-item mb-3 p-3 border-start border-4 border-success">
                                <h6 class="text-success mb-1">Automation Opportunity</h6>
                                <p class="small mb-2">45% of manual processes can be automated, potentially saving 25 hours per week.</p>
                                <button class="btn btn-outline-success btn-sm" onclick="exploreAutomation()">
                                    <i class="bi bi-arrow-right me-1"></i>Explore
                                </button>
                            </div>

                            <div class="insight-item mb-3 p-3 border-start border-4 border-primary">
                                <h6 class="text-primary mb-1">AI Integration Ready</h6>
                                <p class="small mb-2">Your data infrastructure is ready for machine learning implementation.</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="planAIIntegration()">
                                    <i class="bi bi-arrow-right me-1"></i>Plan Now
                                </button>
                            </div>

                            <div class="insight-item mb-3 p-3 border-start border-4 border-info">
                                <h6 class="text-info mb-1">Skills Gap Analysis</h6>
                                <p class="small mb-2">Digital skills training needed for 35% of team members to maximize transformation ROI.</p>
                                <button class="btn btn-outline-info btn-sm" onclick="planTraining()">
                                    <i class="bi bi-arrow-right me-1"></i>Plan Training
                                </button>
                            </div>

                            <div class="insight-item mb-3 p-3 border-start border-4 border-warning">
                                <h6 class="text-warning mb-1">Security Enhancement</h6>
                                <p class="small mb-2">Cybersecurity measures need upgrading to support digital transformation initiatives.</p>
                                <button class="btn btn-outline-warning btn-sm" onclick="reviewSecurity()">
                                    <i class="bi bi-arrow-right me-1"></i>Review
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Automation Recommendations -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-gear text-primary me-2"></i>
                                Smart Automation Recommendations
                            </h5>
                            <button class="btn btn-outline-primary btn-sm" onclick="generateAutomationRecommendations()">
                                <i class="bi bi-magic me-1"></i>Generate Recommendations
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="recommendations-container" id="recommendationsContainer">
                            <div class="text-center py-4">
                                <i class="bi bi-robot display-4 text-muted mb-3"></i>
                                <h6>AI-Powered Automation Analysis</h6>
                                <p class="text-muted">Click "Generate Recommendations" to get personalized automation suggestions based on your business processes.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Digital Readiness Assessment -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check text-success me-2"></i>
                                Digital Readiness Assessment
                            </h5>
                            <button class="btn btn-outline-success btn-sm" onclick="analyzeDigitalReadiness()">
                                <i class="bi bi-search me-1"></i>Run Assessment
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="readiness-container" id="readinessContainer">
                            <div class="text-center py-4">
                                <i class="bi bi-clipboard-data display-4 text-muted mb-3"></i>
                                <h6>Comprehensive Readiness Analysis</h6>
                                <p class="text-muted">Analyze your organization's readiness for digital transformation and get detailed recommendations.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Initiative Modal -->
<div class="modal fade" id="createInitiativeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-rocket text-success me-2"></i>Create Transformation Initiative
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="createTransformationInitiative(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Initiative Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="Process Automation">Process Automation</option>
                            <option value="Data Analytics">Data Analytics</option>
                            <option value="Customer Experience">Customer Experience</option>
                            <option value="Infrastructure">Infrastructure</option>
                            <option value="Skills Development">Skills Development</option>
                            <option value="Security">Security</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Timeline</label>
                            <select class="form-select" name="timeline">
                                <option value="1-2 months">1-2 months</option>
                                <option value="3-4 months" selected>3-4 months</option>
                                <option value="6-8 months">6-8 months</option>
                                <option value="12+ months">12+ months</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Budget Range</label>
                            <select class="form-select" name="budget">
                                <option value="₹1-5 Lakhs">₹1-5 Lakhs</option>
                                <option value="₹5-10 Lakhs" selected>₹5-10 Lakhs</option>
                                <option value="₹10-20 Lakhs">₹10-20 Lakhs</option>
                                <option value="₹20+ Lakhs">₹20+ Lakhs</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Initiative</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gradient-text {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.metric-card {
    transition: all 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.gauge-circle {
    position: relative;
    display: inline-block;
}

.gauge-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.gauge-value {
    font-size: 1.5rem;
    font-weight: 700;
    display: block;
}

.gauge-label {
    font-size: 0.7rem;
    opacity: 0.8;
}

.initiative-card {
    transition: all 0.3s ease;
}

.initiative-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.insight-item {
    transition: all 0.3s ease;
}

.insight-item:hover {
    background-color: rgba(0,0,0,0.02);
}

.recommendation-card {
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.recommendation-card:hover {
    border-color: #007bff;
    background-color: rgba(0,123,255,0.02);
}

.priority-high { border-left-color: #dc3545 !important; }
.priority-medium { border-left-color: #ffc107 !important; }
.priority-low { border-left-color: #28a745 !important; }

.maturity-gauge {
    position: relative;
}

@media (max-width: 768px) {
    .metric-card {
        margin-bottom: 1rem;
    }
    
    .initiative-card {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Digital Transformation Center JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadTransformationMetrics();
    drawMaturityGauge(78.5);
});

function loadTransformationMetrics() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_transformation_metrics'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateMetricsDisplay(data.metrics);
        }
    })
    .catch(error => console.error('Error loading metrics:', error));
}

function updateMetricsDisplay(metrics) {
    // Update metric cards with actual data
    drawMaturityGauge(metrics.digital_maturity_score);
}

function drawMaturityGauge(score) {
    const canvas = document.querySelector('#maturityGauge canvas');
    const ctx = canvas.getContext('2d');
    const centerX = 60;
    const centerY = 60;
    const radius = 45;
    
    // Clear canvas
    ctx.clearRect(0, 0, 120, 120);
    
    // Background circle
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255,255,255,0.2)';
    ctx.lineWidth = 8;
    ctx.stroke();
    
    // Progress arc
    const startAngle = -Math.PI / 2;
    const endAngle = startAngle + (2 * Math.PI * score / 100);
    
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, startAngle, endAngle);
    ctx.strokeStyle = 'rgba(255,255,255,0.9)';
    ctx.lineWidth = 8;
    ctx.lineCap = 'round';
    ctx.stroke();
}

function analyzeDigitalReadiness() {
    const container = document.getElementById('readinessContainer');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-success mb-3"></div>
            <p class="text-muted">Analyzing digital readiness...</p>
        </div>
    `;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=analyze_digital_readiness'
    })
    .then(response => response.json())
    .then(data => {
        displayReadinessAnalysis(data.analysis);
    })
    .catch(error => {
        console.error('Error analyzing readiness:', error);
        showAlert('Failed to analyze digital readiness', 'error');
    });
}

function displayReadinessAnalysis(analysis) {
    const container = document.getElementById('readinessContainer');
    container.innerHTML = `
        <div class="readiness-results">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="readiness-score text-center p-4 border rounded">
                        <div class="score-display mb-3">
                            <span class="display-4 fw-bold text-success">${analysis.readiness_score}</span>
                            <div class="text-muted">Readiness Score</div>
                        </div>
                        <span class="badge bg-success fs-6">${analysis.overall_readiness}</span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="readiness-details">
                        <div class="mb-4">
                            <h6 class="text-success mb-2">Strengths</h6>
                            ${analysis.strengths.slice(0, 3).map(strength => 
                                `<p class="small mb-1"><i class="bi bi-check-circle text-success me-2"></i>${strength}</p>`
                            ).join('')}
                        </div>
                        <div class="mb-4">
                            <h6 class="text-warning mb-2">Improvement Areas</h6>
                            ${analysis.improvement_areas.slice(0, 3).map(area => 
                                `<p class="small mb-1"><i class="bi bi-arrow-up-circle text-warning me-2"></i>${area}</p>`
                            ).join('')}
                        </div>
                        <div class="next-steps">
                            <h6 class="text-primary mb-2">Next Steps</h6>
                            ${analysis.next_steps.slice(0, 2).map(step => 
                                `<p class="small mb-1"><i class="bi bi-arrow-right text-primary me-2"></i>${step}</p>`
                            ).join('')}
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 p-3 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="text-success">Projected ROI: ${analysis.roi_projection}</strong>
                        <small class="d-block text-muted">Based on comprehensive transformation strategy</small>
                    </div>
                    <button class="btn btn-success" onclick="generateReadinessReport()">
                        <i class="bi bi-file-earmark-text me-1"></i>Download Report
                    </button>
                </div>
            </div>
        </div>
    `;
}

function generateAutomationRecommendations() {
    const container = document.getElementById('recommendationsContainer');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-3"></div>
            <p class="text-muted">Generating automation recommendations...</p>
        </div>
    `;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=generate_automation_recommendations'
    })
    .then(response => response.json())
    .then(data => {
        displayAutomationRecommendations(data.recommendations);
    })
    .catch(error => {
        console.error('Error generating recommendations:', error);
        showAlert('Failed to generate recommendations', 'error');
    });
}

function displayAutomationRecommendations(recommendations) {
    const container = document.getElementById('recommendationsContainer');
    let html = '<div class="row g-3">';
    
    recommendations.forEach(rec => {
        html += `
            <div class="col-lg-6">
                <div class="recommendation-card p-4 border rounded h-100 priority-${rec.priority.toLowerCase()}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="mb-1">${rec.title}</h6>
                            <span class="badge bg-secondary">${rec.category}</span>
                        </div>
                        <span class="badge bg-${rec.priority === 'High' ? 'danger' : 'warning'}">${rec.priority}</span>
                    </div>
                    <p class="small text-muted mb-3">${rec.description}</p>
                    <div class="recommendation-metrics">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="metric-box bg-success bg-opacity-10 p-2 rounded text-center">
                                    <div class="fw-bold text-success">${rec.efficiency_gain}</div>
                                    <small class="text-muted">Efficiency Gain</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-box bg-primary bg-opacity-10 p-2 rounded text-center">
                                    <div class="fw-bold text-primary">${rec.timeline}</div>
                                    <small class="text-muted">Timeline</small>
                                </div>
                            </div>
                        </div>
                        <p class="small mb-2"><strong>Cost Savings:</strong> ${rec.cost_saving}</p>
                        <p class="small mb-0"><strong>Effort:</strong> ${rec.effort}</p>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-outline-primary btn-sm me-2" onclick="viewRecommendationDetails('${rec.title}')">
                            <i class="bi bi-eye me-1"></i>Details
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="implementRecommendation('${rec.title}')">
                            <i class="bi bi-check-circle me-1"></i>Implement
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function showCreateInitiativeModal() {
    new bootstrap.Modal(document.getElementById('createInitiativeModal')).show();
}

function createTransformationInitiative(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.action = 'create_transformation_initiative';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('createInitiativeModal')).hide();
            showAlert(result.message, 'success');
            // Refresh initiatives display
        }
    })
    .catch(error => {
        console.error('Error creating initiative:', error);
        showAlert('Failed to create initiative', 'error');
    });
}

function refreshTransformationData() {
    showAlert('Refreshing transformation data...', 'info');
    loadTransformationMetrics();
}

// Initiative management functions
function viewInitiativeDetails(id) {
    showAlert(`Viewing details for initiative #${id}`, 'info');
}

function updateInitiativeProgress(id) {
    showAlert(`Updating progress for initiative #${id}`, 'success');
}

function viewInitiativeReport(id) {
    showAlert(`Opening report for initiative #${id}`, 'info');
}

// Insight action functions
function exploreAutomation() {
    showAlert('Opening automation explorer...', 'info');
}

function planAIIntegration() {
    showAlert('Opening AI integration planner...', 'info');
}

function planTraining() {
    showAlert('Opening training planner...', 'info');
}

function reviewSecurity() {
    showAlert('Opening security review...', 'warning');
}

// Recommendation action functions
function viewRecommendationDetails(title) {
    showAlert(`Viewing details for: ${title}`, 'info');
}

function implementRecommendation(title) {
    showAlert(`Implementing recommendation: ${title}`, 'success');
}

// Report generation functions
function generateMetricsReport() {
    showAlert('Generating transformation metrics report...', 'success');
}

function generateReadinessReport() {
    showAlert('Downloading digital readiness report...', 'success');
}

function loadDetailedMetrics() {
    showAlert('Loading detailed metrics view...', 'info');
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
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

<?php include 'layouts/footer.php'; ?>
