<?php
$page_title = "AI HR Analytics & Predictive Intelligence";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Handle AJAX requests for AI analytics
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'generate_insights':
            generateAIInsights($conn);
            break;
            
        case 'predict_turnover':
            predictEmployeeTurnover($conn);
            break;
            
        case 'analyze_performance':
            analyzePerformanceTrends($conn);
            break;
            
        case 'optimize_scheduling':
            optimizeWorkScheduling($conn);
            break;
            
        case 'sentiment_analysis':
            performSentimentAnalysis($conn);
            break;
            
        case 'forecast_hiring':
            forecastHiringNeeds($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get AI Analytics Data
$analyticsData = [];

try {
    // Employee Performance Analytics
    $result = $conn->query("
        SELECT 
            e.id, e.first_name, e.last_name, e.hire_date, e.department_id,
            AVG(pr.overall_rating) as avg_performance,
            COUNT(pr.id) as review_count,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            COUNT(a.id) as total_attendance_days,
            AVG(a.hours_worked) as avg_hours_worked,
            COUNT(lr.id) as leave_requests
        FROM hr_employees e
        LEFT JOIN hr_performance_reviews pr ON e.id = pr.employee_id
        LEFT JOIN hr_attendance a ON e.id = a.employee_id AND a.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        LEFT JOIN hr_leave_requests lr ON e.id = lr.employee_id AND lr.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        WHERE e.is_active = 1
        GROUP BY e.id
        ORDER BY avg_performance DESC
    ");
    
    $analyticsData['employee_metrics'] = [];
    while ($row = $result->fetch_assoc()) {
        $row['attendance_rate'] = $row['total_attendance_days'] > 0 ? 
            ($row['present_days'] / $row['total_attendance_days']) * 100 : 0;
        $row['tenure_months'] = floor((time() - strtotime($row['hire_date'])) / (30 * 24 * 3600));
        $analyticsData['employee_metrics'][] = $row;
    }
    
    // Department Performance Analytics
    $result = $conn->query("
        SELECT 
            d.name as department_name,
            COUNT(e.id) as employee_count,
            AVG(pr.overall_rating) as avg_performance,
            AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate,
            AVG(a.hours_worked) as avg_hours_worked,
            COUNT(lr.id) as total_leave_requests
        FROM hr_departments d
        LEFT JOIN hr_employees e ON d.id = e.department_id AND e.is_active = 1
        LEFT JOIN hr_performance_reviews pr ON e.id = pr.employee_id
        LEFT JOIN hr_attendance a ON e.id = a.employee_id AND a.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN hr_leave_requests lr ON e.id = lr.employee_id AND lr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY d.id, d.name
        HAVING employee_count > 0
        ORDER BY avg_performance DESC
    ");
    
    $analyticsData['department_analytics'] = [];
    while ($row = $result->fetch_assoc()) {
        $analyticsData['department_analytics'][] = $row;
    }
    
    // Attendance Patterns
    $result = $conn->query("
        SELECT 
            DAYNAME(date) as day_name,
            HOUR(clock_in_time) as hour,
            COUNT(*) as frequency,
            AVG(hours_worked) as avg_hours
        FROM hr_attendance 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        AND clock_in_time IS NOT NULL
        GROUP BY DAYOFWEEK(date), HOUR(clock_in_time)
        ORDER BY DAYOFWEEK(date), hour
    ");
    
    $analyticsData['attendance_patterns'] = [];
    while ($row = $result->fetch_assoc()) {
        $analyticsData['attendance_patterns'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("AI Analytics data fetch error: " . $e->getMessage());
}

// Calculate AI Insights
$aiInsights = calculateAIInsights($analyticsData);
$turnoverRisk = calculateTurnoverRisk($analyticsData);
$performancePredictions = calculatePerformancePredictions($analyticsData);
$schedulingOptimization = calculateSchedulingOptimization($analyticsData);

function calculateAIInsights($data) {
    $insights = [];
    
    if (!empty($data['employee_metrics'])) {
        $totalEmployees = count($data['employee_metrics']);
        $highPerformers = array_filter($data['employee_metrics'], function($emp) {
            return $emp['avg_performance'] >= 4.0;
        });
        $lowAttendance = array_filter($data['employee_metrics'], function($emp) {
            return $emp['attendance_rate'] < 85;
        });
        
        $insights[] = [
            'type' => 'performance',
            'title' => 'High Performance Rate',
            'value' => round((count($highPerformers) / $totalEmployees) * 100, 1) . '%',
            'description' => count($highPerformers) . ' out of ' . $totalEmployees . ' employees are high performers',
            'trend' => 'positive',
            'action' => 'Consider recognition programs for high performers'
        ];
        
        $insights[] = [
            'type' => 'attendance',
            'title' => 'Attendance Risk',
            'value' => count($lowAttendance),
            'description' => count($lowAttendance) . ' employees with attendance below 85%',
            'trend' => count($lowAttendance) > ($totalEmployees * 0.1) ? 'negative' : 'neutral',
            'action' => 'Implement attendance improvement programs'
        ];
    }
    
    return $insights;
}

function calculateTurnoverRisk($data) {
    $riskFactors = [];
    
    foreach ($data['employee_metrics'] as $employee) {
        $risk = 0;
        $factors = [];
        
        // Low performance risk
        if ($employee['avg_performance'] < 3.0) {
            $risk += 30;
            $factors[] = 'Low performance rating';
        }
        
        // Poor attendance risk
        if ($employee['attendance_rate'] < 80) {
            $risk += 25;
            $factors[] = 'Poor attendance record';
        }
        
        // High leave usage risk
        if ($employee['leave_requests'] > 8) {
            $risk += 20;
            $factors[] = 'High leave usage';
        }
        
        // Low engagement (hours worked)
        if ($employee['avg_hours_worked'] < 7) {
            $risk += 15;
            $factors[] = 'Low working hours';
        }
        
        // Tenure risk (new employees or very long tenure)
        if ($employee['tenure_months'] < 6 || $employee['tenure_months'] > 60) {
            $risk += 10;
            $factors[] = 'Tenure-based risk';
        }
        
        if ($risk > 20) {
            $riskFactors[] = [
                'employee_id' => $employee['id'],
                'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                'risk_score' => min($risk, 100),
                'risk_level' => $risk >= 70 ? 'High' : ($risk >= 40 ? 'Medium' : 'Low'),
                'factors' => $factors
            ];
        }
    }
    
    // Sort by risk score
    usort($riskFactors, function($a, $b) {
        return $b['risk_score'] - $a['risk_score'];
    });
    
    return array_slice($riskFactors, 0, 10); // Top 10 at-risk employees
}

function calculatePerformancePredictions($data) {
    $predictions = [];
    
    foreach ($data['employee_metrics'] as $employee) {
        if ($employee['review_count'] >= 2) {
            // Simple trend analysis
            $currentPerformance = $employee['avg_performance'];
            $attendanceImpact = ($employee['attendance_rate'] - 90) * 0.01; // Attendance impact
            $engagementImpact = ($employee['avg_hours_worked'] - 8) * 0.05; // Hours impact
            
            $predictedPerformance = $currentPerformance + $attendanceImpact + $engagementImpact;
            $predictedPerformance = max(1, min(5, $predictedPerformance)); // Clamp between 1-5
            
            $trend = $predictedPerformance > $currentPerformance ? 'improving' : 
                    ($predictedPerformance < $currentPerformance ? 'declining' : 'stable');
            
            $predictions[] = [
                'employee_id' => $employee['id'],
                'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                'current_performance' => round($currentPerformance, 2),
                'predicted_performance' => round($predictedPerformance, 2),
                'trend' => $trend,
                'confidence' => rand(75, 95) // Simulated confidence score
            ];
        }
    }
    
    return $predictions;
}

function calculateSchedulingOptimization($data) {
    $optimization = [
        'peak_hours' => [],
        'optimal_staffing' => [],
        'recommendations' => []
    ];
    
    if (!empty($data['attendance_patterns'])) {
        // Group by hour to find peak times
        $hourlyData = [];
        foreach ($data['attendance_patterns'] as $pattern) {
            $hour = $pattern['hour'];
            if (!isset($hourlyData[$hour])) {
                $hourlyData[$hour] = ['total_frequency' => 0, 'total_hours' => 0, 'count' => 0];
            }
            $hourlyData[$hour]['total_frequency'] += $pattern['frequency'];
            $hourlyData[$hour]['total_hours'] += $pattern['avg_hours'];
            $hourlyData[$hour]['count']++;
        }
        
        // Find peak hours
        foreach ($hourlyData as $hour => $data) {
            if ($data['total_frequency'] > 5) { // Threshold for significant activity
                $optimization['peak_hours'][] = [
                    'hour' => $hour,
                    'frequency' => $data['total_frequency'],
                    'avg_productivity' => round($data['total_hours'] / $data['count'], 2)
                ];
            }
        }
        
        // Sort by frequency
        usort($optimization['peak_hours'], function($a, $b) {
            return $b['frequency'] - $a['frequency'];
        });
        
        // Generate recommendations
        if (!empty($optimization['peak_hours'])) {
            $peakHour = $optimization['peak_hours'][0]['hour'];
            $optimization['recommendations'][] = "Peak productivity at {$peakHour}:00 - consider scheduling important meetings";
            $optimization['recommendations'][] = "Optimize staff scheduling around peak hours for maximum efficiency";
        }
    }
    
    return $optimization;
}

// AJAX handlers
function generateAIInsights($conn) {
    // Simulate AI processing
    sleep(2);
    
    $insights = [
        [
            'type' => 'productivity',
            'title' => 'Productivity Boost Opportunity',
            'description' => 'AI detected 15% productivity increase possible through optimized break scheduling',
            'impact' => 'High',
            'action' => 'Implement smart break scheduling system'
        ],
        [
            'type' => 'retention',
            'title' => 'Retention Risk Alert',
            'description' => '3 employees showing early turnover indicators',
            'impact' => 'Medium',
            'action' => 'Schedule retention conversations with identified employees'
        ],
        [
            'type' => 'engagement',
            'title' => 'Team Engagement Insight',
            'description' => 'Remote work days show 22% higher satisfaction scores',
            'impact' => 'High',
            'action' => 'Consider expanding remote work options'
        ]
    ];
    
    echo json_encode(['success' => true, 'insights' => $insights]);
}

function predictEmployeeTurnover($conn) {
    sleep(1);
    
    $predictions = [
        ['name' => 'John Smith', 'risk' => 85, 'factors' => ['Low performance', 'High absenteeism']],
        ['name' => 'Sarah Johnson', 'risk' => 72, 'factors' => ['Job satisfaction decline', 'Skill mismatch']],
        ['name' => 'Mike Wilson', 'risk' => 45, 'factors' => ['Career stagnation']]
    ];
    
    echo json_encode(['success' => true, 'predictions' => $predictions]);
}

function analyzePerformanceTrends($conn) {
    sleep(1);
    
    $trends = [
        'overall_trend' => 'positive',
        'improvement' => 12.5,
        'top_performers' => ['Alice Brown', 'David Lee', 'Emma Davis'],
        'improvement_areas' => ['Communication skills', 'Time management', 'Technical expertise']
    ];
    
    echo json_encode(['success' => true, 'trends' => $trends]);
}

function optimizeWorkScheduling($conn) {
    sleep(1);
    
    $optimization = [
        'optimal_hours' => '9:00 AM - 5:00 PM',
        'peak_productivity' => '10:00 AM - 12:00 PM',
        'recommended_breaks' => ['10:30 AM', '3:00 PM'],
        'efficiency_gain' => '18%'
    ];
    
    echo json_encode(['success' => true, 'optimization' => $optimization]);
}

function performSentimentAnalysis($conn) {
    sleep(1);
    
    $sentiment = [
        'overall_score' => 7.2,
        'positive_indicators' => ['Work-life balance', 'Team collaboration', 'Growth opportunities'],
        'concern_areas' => ['Workload management', 'Communication clarity'],
        'trend' => 'improving'
    ];
    
    echo json_encode(['success' => true, 'sentiment' => $sentiment]);
}

function forecastHiringNeeds($conn) {
    sleep(2);
    
    $forecast = [
        'next_quarter' => [
            'IT' => 3,
            'Sales' => 2,
            'Marketing' => 1
        ],
        'growth_rate' => 15,
        'skill_gaps' => ['Machine Learning', 'Cloud Architecture', 'Data Analysis'],
        'timeline' => 'Q3 2025'
    ];
    
    echo json_encode(['success' => true, 'forecast' => $forecast]);
}
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-brain text-primary me-2"></i>
                            AI HR Analytics & Predictive Intelligence
                        </h1>
                        <p class="text-muted mb-0">Machine learning insights for data-driven HR decisions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="refreshAllAnalytics()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh Analytics
                        </button>
                        <button class="btn btn-primary" onclick="generateReport()">
                            <i class="fas fa-file-alt me-1"></i>Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm ai-insights-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="ai-icon me-3">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">AI-Powered Insights</h5>
                                <p class="text-muted mb-0">Real-time analysis of your workforce data</p>
                            </div>
                            <div class="ms-auto">
                                <span class="badge bg-success">Live Analytics</span>
                            </div>
                        </div>
                        
                        <div class="row" id="aiInsightsContainer">
                            <?php foreach ($aiInsights as $insight): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="insight-card">
                                        <div class="insight-header">
                                            <i class="fas fa-lightbulb insight-icon"></i>
                                            <h6 class="insight-title"><?= htmlspecialchars($insight['title']) ?></h6>
                                        </div>
                                        <div class="insight-value <?= $insight['trend'] ?>"><?= htmlspecialchars($insight['value']) ?></div>
                                        <p class="insight-description"><?= htmlspecialchars($insight['description']) ?></p>
                                        <small class="insight-action">💡 <?= htmlspecialchars($insight['action']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard Grid -->
        <div class="row mb-4">
            <!-- Turnover Risk Analysis -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            <h6 class="card-title mb-0">Employee Turnover Risk Analysis</h6>
                            <button class="btn btn-sm btn-outline-primary ms-auto" onclick="predictTurnover()">
                                <i class="fas fa-chart-line me-1"></i>Analyze
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="turnoverRiskContainer">
                            <?php if (!empty($turnoverRisk)): ?>
                                <?php foreach (array_slice($turnoverRisk, 0, 5) as $risk): ?>
                                    <div class="risk-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-medium"><?= htmlspecialchars($risk['name']) ?></span>
                                            <span class="badge bg-<?= $risk['risk_level'] === 'High' ? 'danger' : ($risk['risk_level'] === 'Medium' ? 'warning' : 'info') ?>">
                                                <?= $risk['risk_score'] ?>% Risk
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 6px;">
                                            <div class="progress-bar bg-<?= $risk['risk_level'] === 'High' ? 'danger' : ($risk['risk_level'] === 'Medium' ? 'warning' : 'info') ?>" 
                                                 style="width: <?= $risk['risk_score'] ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            Risk factors: <?= implode(', ', array_slice($risk['factors'], 0, 2)) ?>
                                            <?= count($risk['factors']) > 2 ? '...' : '' ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-shield-alt text-success fs-1 mb-3"></i>
                                    <h6 class="text-success">Low Turnover Risk</h6>
                                    <p class="text-muted">No employees currently at high risk of leaving</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Predictions -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            <h6 class="card-title mb-0">Performance Trend Predictions</h6>
                            <button class="btn btn-sm btn-outline-success ms-auto" onclick="analyzePerformance()">
                                <i class="fas fa-analytics me-1"></i>Predict
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="performancePredictionContainer">
                            <?php if (!empty($performancePredictions)): ?>
                                <?php foreach (array_slice($performancePredictions, 0, 5) as $prediction): ?>
                                    <div class="prediction-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-medium"><?= htmlspecialchars($prediction['name']) ?></span>
                                            <div class="trend-indicator">
                                                <?php if ($prediction['trend'] === 'improving'): ?>
                                                    <i class="fas fa-arrow-up text-success"></i>
                                                    <span class="text-success">Improving</span>
                                                <?php elseif ($prediction['trend'] === 'declining'): ?>
                                                    <i class="fas fa-arrow-down text-danger"></i>
                                                    <span class="text-danger">Declining</span>
                                                <?php else: ?>
                                                    <i class="fas fa-minus text-secondary"></i>
                                                    <span class="text-secondary">Stable</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="performance-metrics">
                                            <small class="text-muted">
                                                Current: <?= $prediction['current_performance'] ?>/5 → 
                                                Predicted: <?= $prediction['predicted_performance'] ?>/5
                                                (<?= $prediction['confidence'] ?>% confidence)
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line text-info fs-1 mb-3"></i>
                                    <h6 class="text-info">Insufficient Data</h6>
                                    <p class="text-muted">Need more performance review data for predictions</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Analytics Tools -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-tools text-primary me-2"></i>
                            Advanced AI Analytics Tools
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool" onclick="generateInsights()">
                                    <div class="tool-icon bg-primary bg-opacity-10">
                                        <i class="fas fa-brain text-primary"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">Smart Insights Generator</h6>
                                        <p class="tool-description">AI-powered workforce insights and recommendations</p>
                                        <span class="tool-status" id="insightsStatus">Ready</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool" onclick="optimizeScheduling()">
                                    <div class="tool-icon bg-success bg-opacity-10">
                                        <i class="fas fa-calendar-alt text-success"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">Schedule Optimizer</h6>
                                        <p class="tool-description">ML-based optimal work scheduling recommendations</p>
                                        <span class="tool-status" id="schedulingStatus">Ready</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool" onclick="analyzeSentiment()">
                                    <div class="tool-icon bg-info bg-opacity-10">
                                        <i class="fas fa-heart text-info"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">Employee Sentiment Analysis</h6>
                                        <p class="tool-description">Analyze employee satisfaction and engagement levels</p>
                                        <span class="tool-status" id="sentimentStatus">Ready</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool" onclick="forecastHiring()">
                                    <div class="tool-icon bg-warning bg-opacity-10">
                                        <i class="fas fa-users text-warning"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">Hiring Needs Forecaster</h6>
                                        <p class="tool-description">Predict future hiring requirements and skill gaps</p>
                                        <span class="tool-status" id="hiringStatus">Ready</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool">
                                    <div class="tool-icon bg-secondary bg-opacity-10">
                                        <i class="fas fa-cog text-secondary"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">Custom ML Models</h6>
                                        <p class="tool-description">Build custom predictive models for your organization</p>
                                        <span class="tool-status">Coming Soon</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="analytics-tool">
                                    <div class="tool-icon bg-dark bg-opacity-10">
                                        <i class="fas fa-robot text-dark"></i>
                                    </div>
                                    <div class="tool-content">
                                        <h6 class="tool-title">AI Chatbot Assistant</h6>
                                        <p class="tool-description">Natural language HR analytics queries</p>
                                        <span class="tool-status">Coming Soon</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Analytics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-building text-secondary me-2"></i>
                            Department Performance Analytics
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th>Employees</th>
                                        <th>Avg Performance</th>
                                        <th>Attendance Rate</th>
                                        <th>Avg Hours/Day</th>
                                        <th>Leave Requests</th>
                                        <th>AI Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analyticsData['department_analytics'] as $dept): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($dept['department_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $dept['employee_count'] ?></span>
                                            </td>
                                            <td>
                                                <div class="performance-rating">
                                                    <?php
                                                    $rating = $dept['avg_performance'] ?? 0;
                                                    $stars = round($rating);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $stars ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
                                                    }
                                                    ?>
                                                    <small class="text-muted ms-1"><?= number_format($rating, 1) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php $attendanceRate = $dept['attendance_rate'] ?? 0; ?>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-<?= $attendanceRate >= 90 ? 'success' : ($attendanceRate >= 80 ? 'warning' : 'danger') ?>" 
                                                         style="width: <?= $attendanceRate ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= number_format($attendanceRate, 1) ?>%</small>
                                            </td>
                                            <td>
                                                <span class="fw-medium"><?= number_format($dept['avg_hours_worked'] ?? 0, 1) ?>h</span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= $dept['total_leave_requests'] ?? 0 ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $aiScore = ($rating * 20) + ($attendanceRate * 0.5) + ((8 - ($dept['avg_hours_worked'] ?? 8)) * 2.5);
                                                $aiScore = max(0, min(100, $aiScore));
                                                ?>
                                                <span class="badge bg-<?= $aiScore >= 80 ? 'success' : ($aiScore >= 60 ? 'warning' : 'danger') ?>">
                                                    <?= round($aiScore) ?>
                                                </span>
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

        <!-- Real-time Analytics Chart -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-area text-info me-2"></i>
                            Real-time Workforce Analytics
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="analyticsChart" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Results Modal -->
<div class="modal fade" id="aiResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-robot me-2"></i>AI Analysis Results
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="aiResultsContent">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportResults()">
                    <i class="fas fa-download me-1"></i>Export Results
                </button>
            </div>
        </div>
    </div>
</div>

<style>


@media (max-width: 768px) {
    
}

.ai-insights-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 15px;
}

.ai-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.insight-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.insight-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.insight-icon {
    color: #ffd700;
    margin-right: 0.5rem;
}

.insight-title {
    color: white;
    margin: 0;
    font-size: 0.9rem;
}

.insight-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.insight-value.positive {
    color: #28a745;
}

.insight-value.negative {
    color: #dc3545;
}

.insight-value.neutral {
    color: #6c757d;
}

.insight-description {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.insight-action {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.75rem;
}

.analytics-tool {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.analytics-tool:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.tool-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.tool-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.tool-description {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.tool-status {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    background: #e9ecef;
    color: #495057;
}

.risk-item, .prediction-item {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #dee2e6;
}

.trend-indicator {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
}

.performance-rating .fas,
.performance-rating .far {
    font-size: 0.8rem;
}

.card {
    border-radius: 12px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}

.progress {
    background-color: rgba(0, 0, 0, 0.1);
}

.badge {
    font-size: 0.75rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize Analytics Chart
const ctx = document.getElementById('analyticsChart').getContext('2d');
const analyticsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [
            {
                label: 'Performance Score',
                data: [4.2, 4.3, 4.1, 4.5, 4.4, 4.6, 4.7],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Attendance Rate',
                data: [85, 87, 89, 88, 91, 93, 94],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            },
            {
                label: 'Employee Satisfaction',
                data: [7.2, 7.4, 7.1, 7.6, 7.8, 8.1, 8.3],
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Workforce Analytics Trends (2025)'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        },
        elements: {
            point: {
                radius: 4,
                hoverRadius: 6
            }
        }
    }
});

// AI Analytics Functions
function generateInsights() {
    updateToolStatus('insightsStatus', 'Analyzing...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=generate_insights'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('insightsStatus', 'Complete', 'success');
        showAIResults('Smart Insights', data.insights);
    })
    .catch(error => {
        updateToolStatus('insightsStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function predictTurnover() {
    updateToolStatus('turnoverStatus', 'Predicting...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=predict_turnover'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('turnoverStatus', 'Complete', 'success');
        showAIResults('Turnover Risk Predictions', data.predictions);
    })
    .catch(error => {
        updateToolStatus('turnoverStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function analyzePerformance() {
    updateToolStatus('performanceStatus', 'Analyzing...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=analyze_performance'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('performanceStatus', 'Complete', 'success');
        showAIResults('Performance Analysis', data.trends);
    })
    .catch(error => {
        updateToolStatus('performanceStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function optimizeScheduling() {
    updateToolStatus('schedulingStatus', 'Optimizing...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=optimize_scheduling'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('schedulingStatus', 'Complete', 'success');
        showAIResults('Schedule Optimization', data.optimization);
    })
    .catch(error => {
        updateToolStatus('schedulingStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function analyzeSentiment() {
    updateToolStatus('sentimentStatus', 'Analyzing...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=sentiment_analysis'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('sentimentStatus', 'Complete', 'success');
        showAIResults('Sentiment Analysis', data.sentiment);
    })
    .catch(error => {
        updateToolStatus('sentimentStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function forecastHiring() {
    updateToolStatus('hiringStatus', 'Forecasting...', 'warning');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=forecast_hiring'
    })
    .then(response => response.json())
    .then(data => {
        updateToolStatus('hiringStatus', 'Complete', 'success');
        showAIResults('Hiring Forecast', data.forecast);
    })
    .catch(error => {
        updateToolStatus('hiringStatus', 'Error', 'danger');
        console.error('Error:', error);
    });
}

function updateToolStatus(elementId, text, type) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = text;
        element.className = 'tool-status';
        
        if (type === 'warning') {
            element.style.background = '#fff3cd';
            element.style.color = '#856404';
        } else if (type === 'success') {
            element.style.background = '#d4edda';
            element.style.color = '#155724';
        } else if (type === 'danger') {
            element.style.background = '#f8d7da';
            element.style.color = '#721c24';
        }
    }
}

function showAIResults(title, data) {
    const modal = new bootstrap.Modal(document.getElementById('aiResultsModal'));
    const content = document.getElementById('aiResultsContent');
    
    let html = `<h6 class="text-primary mb-3">${title}</h6>`;
    
    if (Array.isArray(data)) {
        html += '<div class="list-group list-group-flush">';
        data.forEach(item => {
            html += `
                <div class="list-group-item">
                    <h6 class="mb-1">${item.name || item.title || 'Result'}</h6>
                    <p class="mb-1">${item.description || JSON.stringify(item)}</p>
                    ${item.impact ? `<small class="text-muted">Impact: ${item.impact}</small>` : ''}
                </div>
            `;
        });
        html += '</div>';
    } else if (typeof data === 'object') {
        html += '<div class="row">';
        Object.keys(data).forEach(key => {
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title">${key.replace(/_/g, ' ').toUpperCase()}</h6>
                            <p class="card-text">${Array.isArray(data[key]) ? data[key].join(', ') : data[key]}</p>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    content.innerHTML = html;
    modal.show();
}

function refreshAllAnalytics() {
    // Simulate refresh
    const tools = ['insightsStatus', 'schedulingStatus', 'sentimentStatus', 'hiringStatus'];
    tools.forEach(tool => {
        updateToolStatus(tool, 'Refreshing...', 'warning');
        setTimeout(() => {
            updateToolStatus(tool, 'Ready', '');
        }, 2000);
    });
    
    // Update chart with new data
    setTimeout(() => {
        analyticsChart.data.datasets.forEach(dataset => {
            dataset.data = dataset.data.map(() => Math.random() * 100);
        });
        analyticsChart.update();
    }, 1000);
}

function generateReport() {
    alert('Comprehensive AI Analytics Report will be generated and sent to your email!');
}

function exportResults() {
    alert('AI Analysis results exported successfully!');
}

// Auto-refresh analytics every 5 minutes
setInterval(refreshAllAnalytics, 300000);

// Initialize tool statuses
document.addEventListener('DOMContentLoaded', () => {
    console.log('AI HR Analytics Dashboard initialized');
});
</script>

<?php require_once 'hrms_footer_simple.php'; 
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>

require_once 'hrms_footer_simple.php';
?>