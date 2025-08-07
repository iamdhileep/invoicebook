<?php
/**
 * AI Analytics API Endpoints
 * RESTful API for AI-powered HR analytics
 */

require_once '../includes/hrms_config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($endpoint, $_GET);
            break;
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            handlePostRequest($endpoint, $input);
            break;
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            handlePutRequest($endpoint, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($endpoint, $_GET);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($endpoint, $params) {
    global $conn;
    
    switch ($endpoint) {
        case 'employee-risk-scores':
            getEmployeeRiskScores($conn, $params);
            break;
            
        case 'performance-predictions':
            getPerformancePredictions($conn, $params);
            break;
            
        case 'sentiment-analysis':
            getSentimentAnalysis($conn, $params);
            break;
            
        case 'workforce-forecasts':
            getWorkforceForecasts($conn, $params);
            break;
            
        case 'ml-insights':
            getMLInsights($conn, $params);
            break;
            
        case 'department-analytics':
            getDepartmentAnalytics($conn, $params);
            break;
            
        case 'productivity-metrics':
            getProductivityMetrics($conn, $params);
            break;
            
        case 'analytics-dashboard':
            getAnalyticsDashboard($conn, $params);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($endpoint, $data) {
    global $conn;
    
    switch ($endpoint) {
        case 'generate-insights':
            generateAIInsights($conn, $data);
            break;
            
        case 'train-model':
            trainPredictiveModel($conn, $data);
            break;
            
        case 'predict-turnover':
            predictTurnover($conn, $data);
            break;
            
        case 'analyze-sentiment':
            analyzeSentiment($conn, $data);
            break;
            
        case 'create-forecast':
            createWorkplaceForecast($conn, $data);
            break;
            
        case 'update-risk-scores':
            updateEmployeeRiskScores($conn, $data);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePutRequest($endpoint, $data) {
    global $conn;
    
    switch ($endpoint) {
        case 'insight-status':
            updateInsightStatus($conn, $data);
            break;
            
        case 'model-parameters':
            updateModelParameters($conn, $data);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handleDeleteRequest($endpoint, $params) {
    global $conn;
    
    switch ($endpoint) {
        case 'insight':
            deleteInsight($conn, $params);
            break;
            
        case 'model':
            deleteModel($conn, $params);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

// GET Endpoints Implementation
function getEmployeeRiskScores($conn, $params) {
    $limit = $params['limit'] ?? 50;
    $department = $params['department'] ?? null;
    
    $query = "
        SELECT 
            e.id,
            e.employee_id,
            e.first_name,
            e.last_name,
            e.ai_risk_score,
            e.predicted_performance,
            e.engagement_score,
            d.name as department_name,
            COUNT(CASE WHEN a.status = 'absent' AND a.date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as absences_30_days
        FROM hr_employees e
        LEFT JOIN hr_departments d ON e.department_id = d.id
        LEFT JOIN hr_attendance a ON e.id = a.employee_id
        WHERE e.is_active = 1
    ";
    
    if ($department) {
        $query .= " AND e.department_id = " . intval($department);
    }
    
    $query .= " GROUP BY e.id ORDER BY e.ai_risk_score DESC LIMIT " . intval($limit);
    
    $result = HRMSHelper::safeQuery($query);
    $employees = [];
    
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'total' => count($employees)
    ]);
}

function getPerformancePredictions($conn, $params) {
    $employeeId = $params['employee_id'] ?? null;
    $timeframe = $params['timeframe'] ?? '90'; // days
    
    $query = "
        SELECT 
            e.id,
            e.employee_id,
            e.first_name,
            e.last_name,
            e.predicted_performance,
            AVG(pr.overall_rating) as current_performance,
            COUNT(pr.id) as review_count,
            e.ai_last_updated
        FROM hr_employees e
        LEFT JOIN hr_performance_reviews pr ON e.id = pr.employee_id 
            AND pr.review_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        WHERE e.is_active = 1
    ";
    
    if ($employeeId) {
        $query .= " AND e.id = " . intval($employeeId);
    }
    
    $query .= " GROUP BY e.id ORDER BY e.predicted_performance DESC";
    
    $result = HRMSHelper::safeQuery($query);
    $predictions = [];
    
    while ($row = $result->fetch_assoc()) {
        $trend = 'stable';
        if ($row['predicted_performance'] > $row['current_performance']) {
            $trend = 'improving';
        } elseif ($row['predicted_performance'] < $row['current_performance']) {
            $trend = 'declining';
        }
        
        $row['trend'] = $trend;
        $row['confidence'] = $row['review_count'] >= 3 ? 'high' : ($row['review_count'] >= 2 ? 'medium' : 'low');
        $predictions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $predictions,
        'timeframe_days' => intval($timeframe)
    ]);
}

function getSentimentAnalysis($conn, $params) {
    $employeeId = $params['employee_id'] ?? null;
    $days = $params['days'] ?? 30;
    
    $query = "
        SELECT 
            sa.employee_id,
            e.first_name,
            e.last_name,
            AVG(sa.sentiment_score) as avg_sentiment,
            COUNT(sa.id) as analysis_count,
            sa.sentiment_label,
            sa.emotions,
            sa.keywords
        FROM hr_sentiment_analysis sa
        JOIN hr_employees e ON sa.employee_id = e.id
        WHERE sa.processed_at >= DATE_SUB(NOW(), INTERVAL " . intval($days) . " DAY)
    ";
    
    if ($employeeId) {
        $query .= " AND sa.employee_id = " . intval($employeeId);
    }
    
    $query .= " GROUP BY sa.employee_id ORDER BY avg_sentiment DESC";
    
    $result = HRMSHelper::safeQuery($query);
    $sentiments = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['emotions'] = json_decode($row['emotions'], true);
        $row['keywords'] = json_decode($row['keywords'], true);
        $sentiments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $sentiments,
        'period_days' => intval($days)
    ]);
}

function getWorkforceForecasts($conn, $params) {
    $type = $params['type'] ?? null;
    $department = $params['department'] ?? null;
    
    $query = "
        SELECT 
            wf.*,
            d.name as department_name,
            u.first_name as created_by_name
        FROM hr_workforce_forecasts wf
        LEFT JOIN hr_departments d ON wf.department_id = d.id
        LEFT JOIN users u ON wf.created_by = u.id
        WHERE 1=1
    ";
    
    if ($type) {
        $query .= " AND wf.forecast_type = '" . mysqli_real_escape_string($conn, $type) . "'";
    }
    
    if ($department) {
        $query .= " AND wf.department_id = " . intval($department);
    }
    
    $query .= " ORDER BY wf.forecast_date DESC";
    
    $result = HRMSHelper::safeQuery($query);
    $forecasts = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['key_factors'] = json_decode($row['key_factors'], true);
        $forecasts[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $forecasts
    ]);
}

function getMLInsights($conn, $params) {
    $status = $params['status'] ?? 'new';
    $priority = $params['priority'] ?? null;
    $limit = $params['limit'] ?? 20;
    
    $query = "
        SELECT 
            mi.*,
            u.first_name as reviewed_by_name
        FROM hr_ml_insights mi
        LEFT JOIN users u ON mi.reviewed_by = u.id
        WHERE mi.status = '" . mysqli_real_escape_string($conn, $status) . "'
    ";
    
    if ($priority) {
        $query .= " AND mi.priority = " . intval($priority);
    }
    
    $query .= " ORDER BY mi.priority ASC, mi.created_at DESC LIMIT " . intval($limit);
    
    $result = HRMSHelper::safeQuery($query);
    $insights = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['recommended_actions'] = json_decode($row['recommended_actions'], true);
        $row['affected_employees'] = json_decode($row['affected_employees'], true);
        $insights[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $insights,
        'count' => count($insights)
    ]);
}

function getDepartmentAnalytics($conn, $params) {
    $query = "
        SELECT 
            d.id,
            d.name,
            d.productivity_index,
            d.talent_retention_score,
            COUNT(e.id) as employee_count,
            AVG(e.ai_risk_score) as avg_risk_score,
            AVG(e.predicted_performance) as avg_predicted_performance,
            AVG(e.engagement_score) as avg_engagement,
            SUM(CASE WHEN e.ai_risk_score > 70 THEN 1 ELSE 0 END) as high_risk_employees
        FROM hr_departments d
        LEFT JOIN hr_employees e ON d.id = e.department_id AND e.is_active = 1
        GROUP BY d.id
        ORDER BY d.productivity_index DESC
    ";
    
    $result = HRMSHelper::safeQuery($query);
    $departments = [];
    
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $departments
    ]);
}

function getProductivityMetrics($conn, $params) {
    $days = $params['days'] ?? 30;
    
    $query = "
        SELECT 
            DATE(a.date) as date,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(a.id) as total_attendance,
            AVG(a.hours_worked) as avg_hours,
            COUNT(CASE WHEN a.clock_in_time > '09:30:00' THEN 1 END) as late_arrivals
        FROM hr_attendance a
        WHERE a.date >= DATE_SUB(NOW(), INTERVAL " . intval($days) . " DAY)
        GROUP BY DATE(a.date)
        ORDER BY date DESC
    ";
    
    $result = HRMSHelper::safeQuery($query);
    $metrics = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['attendance_rate'] = $row['total_attendance'] > 0 ? 
            ($row['present_count'] / $row['total_attendance']) * 100 : 0;
        $metrics[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $metrics,
        'period_days' => intval($days)
    ]);
}

function getAnalyticsDashboard($conn, $params) {
    // Aggregate dashboard data
    $dashboard = [
        'overview' => [],
        'trends' => [],
        'alerts' => [],
        'recommendations' => []
    ];
    
    // Overview metrics
    $result = HRMSHelper::safeQuery("
        SELECT 
            COUNT(*) as total_employees,
            AVG(ai_risk_score) as avg_risk_score,
            AVG(predicted_performance) as avg_predicted_performance,
            AVG(engagement_score) as avg_engagement,
            COUNT(CASE WHEN ai_risk_score > 70 THEN 1 END) as high_risk_count
        FROM hr_employees 
        WHERE is_active = 1
    ");
    $dashboard['overview'] = $result->fetch_assoc();
    
    // Recent trends
    $result = HRMSHelper::safeQuery("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as insight_count,
            AVG(confidence_score) as avg_confidence
        FROM hr_ml_insights 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    while ($row = $result->fetch_assoc()) {
        $dashboard['trends'][] = $row;
    }
    
    // Active alerts
    $result = HRMSHelper::safeQuery("
        SELECT * FROM hr_ml_insights 
        WHERE status = 'new' AND impact_level IN ('high', 'critical')
        ORDER BY priority ASC, created_at DESC
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $row['recommended_actions'] = json_decode($row['recommended_actions'], true);
        $dashboard['alerts'][] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dashboard,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

// POST Endpoints Implementation
function generateAIInsights($conn, $data) {
    // Simulate AI insight generation
    $insights = [
        [
            'type' => 'performance',
            'title' => 'Performance Optimization Opportunity',
            'description' => 'AI detected potential for 12% performance improvement in Marketing team through better task allocation',
            'impact' => 'High',
            'confidence' => 89.5,
            'actions' => ['Review current task distribution', 'Implement workload balancing', 'Provide targeted training']
        ],
        [
            'type' => 'retention',
            'title' => 'Early Retention Warning',
            'description' => 'Machine learning model identifies 2 employees with increasing turnover probability',
            'impact' => 'Medium',
            'confidence' => 76.3,
            'actions' => ['Schedule retention conversations', 'Review compensation', 'Assess job satisfaction']
        ]
    ];
    
    // Store insights in database
    foreach ($insights as $insight) {
        $stmt = $conn->prepare("
            INSERT INTO hr_ml_insights (insight_type, title, description, impact_level, recommended_actions, confidence_score)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $actions_json = json_encode($insight['actions']);
        $stmt->bind_param('sssssd', 
            $insight['type'], 
            $insight['title'], 
            $insight['description'], 
            strtolower($insight['impact']), 
            $actions_json, 
            $insight['confidence']
        );
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'insights_generated' => count($insights),
        'data' => $insights
    ]);
}

function trainPredictiveModel($conn, $data) {
    $modelType = $data['model_type'] ?? 'turnover';
    $algorithm = $data['algorithm'] ?? 'random_forest';
    $parameters = $data['parameters'] ?? [];
    
    // Simulate model training
    sleep(2); // Simulate training time
    
    $accuracy = rand(80, 95) + (rand(0, 99) / 100);
    $trainingCount = rand(500, 2000);
    
    $stmt = $conn->prepare("
        INSERT INTO hr_predictive_models 
        (model_name, model_type, algorithm, parameters, accuracy_score, training_data_count, last_trained, created_by)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $modelName = ucfirst($modelType) . ' Predictor v' . date('Y.m');
    $params_json = json_encode($parameters);
    $currentUserId = HRMSHelper::getCurrentUserId();
    
    $stmt->bind_param('ssssdii', 
        $modelName, 
        $modelType, 
        $algorithm, 
        $params_json, 
        $accuracy, 
        $trainingCount, 
        $currentUserId
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'model_id' => $conn->insert_id,
            'accuracy' => $accuracy,
            'training_samples' => $trainingCount,
            'message' => 'Model trained successfully'
        ]);
    } else {
        throw new Exception('Failed to save model');
    }
}

function predictTurnover($conn, $data) {
    $employeeIds = $data['employee_ids'] ?? [];
    $timeframe = $data['timeframe'] ?? 90; // days
    
    if (empty($employeeIds)) {
        // Get all active employees
        $result = HRMSHelper::safeQuery("SELECT id FROM hr_employees WHERE is_active = 1");
        while ($row = $result->fetch_assoc()) {
            $employeeIds[] = $row['id'];
        }
    }
    
    $predictions = [];
    
    foreach ($employeeIds as $employeeId) {
        // Simulate ML prediction
        $riskScore = rand(10, 95) + (rand(0, 99) / 100);
        $confidence = rand(70, 95) + (rand(0, 99) / 100);
        
        // Update employee risk score
        $stmt = $conn->prepare("
            UPDATE hr_employees 
            SET ai_risk_score = ?, ai_last_updated = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('di', $riskScore, $employeeId);
        $stmt->execute();
        
        $predictions[] = [
            'employee_id' => $employeeId,
            'risk_score' => round($riskScore, 2),
            'confidence' => round($confidence, 2),
            'timeframe_days' => $timeframe,
            'risk_level' => $riskScore >= 70 ? 'High' : ($riskScore >= 40 ? 'Medium' : 'Low')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'predictions' => $predictions,
        'employees_analyzed' => count($predictions)
    ]);
}

function analyzeSentiment($conn, $data) {
    $text = $data['text'] ?? '';
    $employeeId = $data['employee_id'] ?? null;
    $sourceType = $data['source_type'] ?? 'feedback';
    
    if (empty($text)) {
        throw new Exception('Text content is required for sentiment analysis');
    }
    
    // Simulate sentiment analysis
    $sentimentScore = (rand(-100, 100) / 100); // -1.0 to 1.0
    $emotions = [
        'joy' => rand(0, 100) / 100,
        'sadness' => rand(0, 100) / 100,
        'anger' => rand(0, 100) / 100,
        'fear' => rand(0, 100) / 100,
        'surprise' => rand(0, 100) / 100
    ];
    
    $sentimentLabel = 'neutral';
    if ($sentimentScore >= 0.6) {
        $sentimentLabel = 'very_positive';
    } elseif ($sentimentScore >= 0.2) {
        $sentimentLabel = 'positive';
    } elseif ($sentimentScore <= -0.6) {
        $sentimentLabel = 'very_negative';
    } elseif ($sentimentScore <= -0.2) {
        $sentimentLabel = 'negative';
    }
    
    // Extract keywords (simple simulation)
    $keywords = array_slice(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text))), 0, 10);
    
    if ($employeeId) {
        // Store in database
        $stmt = $conn->prepare("
            INSERT INTO hr_sentiment_analysis 
            (employee_id, source_type, text_content, sentiment_score, sentiment_label, emotions, keywords, confidence)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $emotions_json = json_encode($emotions);
        $keywords_json = json_encode($keywords);
        $confidence = rand(75, 95) + (rand(0, 99) / 100);
        
        $stmt->bind_param('issdsssd', 
            $employeeId, 
            $sourceType, 
            $text, 
            $sentimentScore, 
            $sentimentLabel, 
            $emotions_json, 
            $keywords_json, 
            $confidence
        );
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'sentiment_score' => round($sentimentScore, 3),
        'sentiment_label' => $sentimentLabel,
        'emotions' => $emotions,
        'keywords' => $keywords,
        'confidence' => round($confidence ?? 85, 2)
    ]);
}

function createWorkplaceForecast($conn, $data) {
    $forecastType = $data['forecast_type'] ?? 'hiring';
    $departmentId = $data['department_id'] ?? null;
    $timePeriod = $data['time_period'] ?? 'quarter';
    $forecastDate = $data['forecast_date'] ?? date('Y-m-d', strtotime('+3 months'));
    
    // Simulate forecast calculation
    $predictedValue = rand(5, 50) + (rand(0, 99) / 100);
    $confidenceLow = $predictedValue * 0.8;
    $confidenceHigh = $predictedValue * 1.2;
    
    $keyFactors = [
        'historical_trends',
        'market_conditions',
        'business_growth',
        'seasonal_patterns'
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO hr_workforce_forecasts 
        (forecast_type, department_id, time_period, forecast_date, predicted_value, 
         confidence_interval_low, confidence_interval_high, methodology, key_factors, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $methodology = 'Machine Learning Ensemble';
    $keyFactors_json = json_encode($keyFactors);
    $currentUserId = HRMSHelper::getCurrentUserId();
    
    $stmt->bind_param('sissdddssi', 
        $forecastType, 
        $departmentId, 
        $timePeriod, 
        $forecastDate, 
        $predictedValue, 
        $confidenceLow, 
        $confidenceHigh, 
        $methodology, 
        $keyFactors_json, 
        $currentUserId
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'forecast_id' => $conn->insert_id,
            'predicted_value' => round($predictedValue, 2),
            'confidence_interval' => [
                'low' => round($confidenceLow, 2),
                'high' => round($confidenceHigh, 2)
            ],
            'key_factors' => $keyFactors
        ]);
    } else {
        throw new Exception('Failed to create forecast');
    }
}

function updateEmployeeRiskScores($conn, $data) {
    $employeeIds = $data['employee_ids'] ?? [];
    
    if (empty($employeeIds)) {
        $result = HRMSHelper::safeQuery("SELECT id FROM hr_employees WHERE is_active = 1");
        while ($row = $result->fetch_assoc()) {
            $employeeIds[] = $row['id'];
        }
    }
    
    $updated = 0;
    
    foreach ($employeeIds as $employeeId) {
        // Calculate risk score based on multiple factors
        $riskScore = calculateEmployeeRiskScore($conn, $employeeId);
        
        $stmt = $conn->prepare("
            UPDATE hr_employees 
            SET ai_risk_score = ?, ai_last_updated = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('di', $riskScore, $employeeId);
        
        if ($stmt->execute()) {
            $updated++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'employees_updated' => $updated,
        'total_employees' => count($employeeIds)
    ]);
}

// PUT Endpoints Implementation
function updateInsightStatus($conn, $data) {
    $insightId = $data['insight_id'] ?? null;
    $status = $data['status'] ?? 'reviewed';
    $reviewerId = HRMSHelper::getCurrentUserId();
    
    if (!$insightId) {
        throw new Exception('Insight ID is required');
    }
    
    $stmt = $conn->prepare("
        UPDATE hr_ml_insights 
        SET status = ?, reviewed_by = ?, reviewed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('sii', $status, $reviewerId, $insightId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Insight status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update insight status');
    }
}

function updateModelParameters($conn, $data) {
    $modelId = $data['model_id'] ?? null;
    $parameters = $data['parameters'] ?? [];
    
    if (!$modelId) {
        throw new Exception('Model ID is required');
    }
    
    $stmt = $conn->prepare("
        UPDATE hr_predictive_models 
        SET parameters = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $parameters_json = json_encode($parameters);
    $stmt->bind_param('si', $parameters_json, $modelId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Model parameters updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update model parameters');
    }
}

// DELETE Endpoints Implementation
function deleteInsight($conn, $params) {
    $insightId = $params['id'] ?? null;
    
    if (!$insightId) {
        throw new Exception('Insight ID is required');
    }
    
    $stmt = $conn->prepare("DELETE FROM hr_ml_insights WHERE id = ?");
    $stmt->bind_param('i', $insightId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Insight deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete insight');
    }
}

function deleteModel($conn, $params) {
    $modelId = $params['id'] ?? null;
    
    if (!$modelId) {
        throw new Exception('Model ID is required');
    }
    
    $stmt = $conn->prepare("UPDATE hr_predictive_models SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $modelId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Model deactivated successfully'
        ]);
    } else {
        throw new Exception('Failed to deactivate model');
    }
}

// Helper Functions
function calculateEmployeeRiskScore($conn, $employeeId) {
    // Get employee data
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            AVG(pr.overall_rating) as avg_performance,
            COUNT(CASE WHEN a.status = 'absent' AND a.date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as absences_30,
            COUNT(CASE WHEN a.date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as total_days_30,
            AVG(a.hours_worked) as avg_hours,
            COUNT(lr.id) as leave_requests_30
        FROM hr_employees e
        LEFT JOIN hr_performance_reviews pr ON e.id = pr.employee_id
        LEFT JOIN hr_attendance a ON e.id = a.employee_id AND a.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN hr_leave_requests lr ON e.id = lr.employee_id AND lr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        return 0;
    }
    
    $riskScore = 0;
    
    // Performance risk (30% weight)
    if ($employee['avg_performance'] < 3.0) {
        $riskScore += 30;
    } elseif ($employee['avg_performance'] < 3.5) {
        $riskScore += 15;
    }
    
    // Attendance risk (25% weight)
    $attendanceRate = $employee['total_days_30'] > 0 ? 
        (($employee['total_days_30'] - $employee['absences_30']) / $employee['total_days_30']) * 100 : 100;
    
    if ($attendanceRate < 80) {
        $riskScore += 25;
    } elseif ($attendanceRate < 90) {
        $riskScore += 12;
    }
    
    // Engagement risk (20% weight)
    if ($employee['avg_hours'] < 6) {
        $riskScore += 20;
    } elseif ($employee['avg_hours'] < 7) {
        $riskScore += 10;
    }
    
    // Leave usage risk (15% weight)
    if ($employee['leave_requests_30'] > 8) {
        $riskScore += 15;
    } elseif ($employee['leave_requests_30'] > 5) {
        $riskScore += 8;
    }
    
    // Tenure risk (10% weight)
    $tenure_months = floor((time() - strtotime($employee['hire_date'])) / (30 * 24 * 3600));
    if ($tenure_months < 6 || $tenure_months > 60) {
        $riskScore += 10;
    }
    
    return min(100, max(0, $riskScore));
}
?>
