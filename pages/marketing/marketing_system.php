<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Marketing & Communications';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_campaign':
            $name = mysqli_real_escape_string($conn, $_POST['campaign_name']);
            $type = mysqli_real_escape_string($conn, $_POST['campaign_type']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
            $budget = floatval($_POST['budget']);
            $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience']);
            $goals = mysqli_real_escape_string($conn, $_POST['goals']);
            $channels = json_encode($_POST['channels'] ?? []);
            
            $query = "INSERT INTO marketing_campaigns (name, type, description, start_date, end_date, budget, 
                      target_audience, goals, channels, status, created_by) 
                      VALUES ('$name', '$type', '$description', '$start_date', '$end_date', $budget, 
                      '$target_audience', '$goals', '$channels', 'draft', {$_SESSION['admin']})";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Campaign created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating campaign']);
            }
            exit;
            
        case 'create_email_template':
            $name = mysqli_real_escape_string($conn, $_POST['template_name']);
            $subject = mysqli_real_escape_string($conn, $_POST['subject']);
            $content = mysqli_real_escape_string($conn, $_POST['content']);
            $category = mysqli_real_escape_string($conn, $_POST['category']);
            
            $query = "INSERT INTO email_templates (name, subject, content, category, created_by) 
                      VALUES ('$name', '$subject', '$content', '$category', {$_SESSION['admin']})";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Email template created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating template']);
            }
            exit;
            
        case 'send_email_campaign':
            $template_id = intval($_POST['template_id']);
            $segment_id = intval($_POST['segment_id']);
            $schedule_date = mysqli_real_escape_string($conn, $_POST['schedule_date']);
            
            // Get template
            $template_query = "SELECT * FROM email_templates WHERE id = $template_id";
            $template_result = mysqli_query($conn, $template_query);
            $template = mysqli_fetch_assoc($template_result);
            
            // Get segment customers
            $segment_query = "SELECT c.* FROM customer_segments cs 
                             JOIN customers c ON cs.customer_id = c.id 
                             WHERE cs.segment_id = $segment_id AND c.email IS NOT NULL";
            $segment_result = mysqli_query($conn, $segment_query);
            
            $recipient_count = 0;
            while ($customer = mysqli_fetch_assoc($segment_result)) {
                // Create email campaign record
                $email_query = "INSERT INTO email_campaigns (template_id, recipient_email, recipient_name, 
                               status, scheduled_date, created_by) 
                               VALUES ($template_id, '{$customer['email']}', '{$customer['customer_name']}', 
                               'scheduled', '$schedule_date', {$_SESSION['admin']})";
                mysqli_query($conn, $email_query);
                $recipient_count++;
            }
            
            echo json_encode(['success' => true, 'message' => "Email campaign scheduled for $recipient_count recipients"]);
            exit;
            
        case 'create_customer_segment':
            $name = mysqli_real_escape_string($conn, $_POST['segment_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $criteria = json_encode($_POST['criteria'] ?? []);
            
            // Create segment
            $query = "INSERT INTO marketing_segments (name, description, criteria, created_by) 
                      VALUES ('$name', '$description', '$criteria', {$_SESSION['admin']})";
            
            if (mysqli_query($conn, $query)) {
                $segment_id = mysqli_insert_id($conn);
                
                // Apply segment criteria and add customers
                $customer_query = "SELECT id FROM customers WHERE 1=1";
                
                // Add criteria filtering (simplified version)
                $criteria_data = $_POST['criteria'] ?? [];
                if (!empty($criteria_data['city'])) {
                    $city = mysqli_real_escape_string($conn, $criteria_data['city']);
                    $customer_query .= " AND city = '$city'";
                }
                if (!empty($criteria_data['min_orders'])) {
                    $min_orders = intval($criteria_data['min_orders']);
                    $customer_query .= " AND id IN (SELECT customer_id FROM invoices 
                                       GROUP BY customer_id HAVING COUNT(*) >= $min_orders)";
                }
                
                $customer_result = mysqli_query($conn, $customer_query);
                $added_count = 0;
                
                while ($customer = mysqli_fetch_assoc($customer_result)) {
                    $segment_query = "INSERT IGNORE INTO customer_segments (segment_id, customer_id) 
                                     VALUES ($segment_id, {$customer['id']})";
                    if (mysqli_query($conn, $segment_query)) {
                        $added_count++;
                    }
                }
                
                echo json_encode(['success' => true, 'message' => "Segment created with $added_count customers"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating segment']);
            }
            exit;
            
        case 'get_campaign_stats':
            $campaign_id = intval($_POST['campaign_id']);
            
            // Get campaign stats
            $stats_query = "SELECT 
                           (SELECT COUNT(*) FROM email_campaigns WHERE template_id IN 
                            (SELECT id FROM email_templates WHERE id = $campaign_id)) as emails_sent,
                           (SELECT COUNT(*) FROM email_campaigns WHERE template_id IN 
                            (SELECT id FROM email_templates WHERE id = $campaign_id) AND status = 'delivered') as delivered,
                           (SELECT COUNT(*) FROM email_campaigns WHERE template_id IN 
                            (SELECT id FROM email_templates WHERE id = $campaign_id) AND opened_at IS NOT NULL) as opened,
                           (SELECT COUNT(*) FROM email_campaigns WHERE template_id IN 
                            (SELECT id FROM email_templates WHERE id = $campaign_id) AND clicked_at IS NOT NULL) as clicked";
            
            $stats_result = mysqli_query($conn, $stats_query);
            $stats = mysqli_fetch_assoc($stats_result);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
            
        case 'subscribe_newsletter':
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $preferences = json_encode($_POST['preferences'] ?? []);
            
            $query = "INSERT INTO newsletter_subscriptions (email, name, preferences, subscribed_at) 
                      VALUES ('$email', '$name', '$preferences', NOW()) 
                      ON DUPLICATE KEY UPDATE 
                      name = VALUES(name), preferences = VALUES(preferences), 
                      is_active = 1, subscribed_at = NOW()";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Successfully subscribed to newsletter!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error subscribing to newsletter']);
            }
            exit;
            
        case 'get_marketing_analytics':
            $period = $_POST['period'] ?? '30';
            $start_date = date('Y-m-d', strtotime("-$period days"));
            
            $analytics = [];
            
            // Campaign performance
            $campaign_query = "SELECT 
                              COUNT(*) as total_campaigns,
                              SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_campaigns,
                              SUM(budget) as total_budget,
                              AVG(budget) as avg_budget
                              FROM marketing_campaigns 
                              WHERE created_at >= '$start_date'";
            
            $campaign_result = mysqli_query($conn, $campaign_query);
            $analytics['campaigns'] = mysqli_fetch_assoc($campaign_result);
            
            // Email performance
            $email_query = "SELECT 
                           COUNT(*) as total_emails,
                           SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                           SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                           SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
                           FROM email_campaigns 
                           WHERE created_at >= '$start_date'";
            
            $email_result = mysqli_query($conn, $email_query);
            $analytics['emails'] = mysqli_fetch_assoc($email_result);
            
            // Newsletter stats
            $newsletter_query = "SELECT 
                                COUNT(*) as total_subscribers,
                                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_subscribers
                                FROM newsletter_subscriptions 
                                WHERE subscribed_at >= '$start_date'";
            
            $newsletter_result = mysqli_query($conn, $newsletter_query);
            $analytics['newsletter'] = mysqli_fetch_assoc($newsletter_result);
            
            echo json_encode(['success' => true, 'analytics' => $analytics]);
            exit;
    }
}

// Create database tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS marketing_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type ENUM('email', 'social', 'ppc', 'content', 'event', 'direct_mail') NOT NULL,
        description TEXT,
        start_date DATE,
        end_date DATE,
        budget DECIMAL(10,2) DEFAULT 0,
        spent_amount DECIMAL(10,2) DEFAULT 0,
        target_audience TEXT,
        goals TEXT,
        channels JSON,
        status ENUM('draft', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
        performance_data JSON,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_dates (start_date, end_date)
    )",
    
    "CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category ENUM('newsletter', 'promotional', 'transactional', 'welcome', 'follow_up') DEFAULT 'promotional',
        variables JSON,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS email_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(255),
        status ENUM('scheduled', 'sent', 'delivered', 'bounced', 'failed') DEFAULT 'scheduled',
        scheduled_date DATETIME,
        sent_at DATETIME,
        delivered_at DATETIME,
        opened_at DATETIME,
        clicked_at DATETIME,
        unsubscribed_at DATETIME,
        bounce_reason TEXT,
        tracking_data JSON,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_template (template_id),
        INDEX idx_status (status),
        INDEX idx_recipient (recipient_email),
        FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS marketing_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        criteria JSON,
        customer_count INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS customer_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        segment_id INT NOT NULL,
        customer_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_segment_customer (segment_id, customer_id),
        FOREIGN KEY (segment_id) REFERENCES marketing_segments(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        name VARCHAR(255),
        preferences JSON,
        is_active BOOLEAN DEFAULT TRUE,
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at DATETIME,
        source VARCHAR(100) DEFAULT 'website',
        INDEX idx_email (email),
        INDEX idx_active (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS marketing_automation_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        trigger_event ENUM('customer_signup', 'purchase_made', 'cart_abandoned', 'birthday', 'anniversary', 'inactivity') NOT NULL,
        conditions JSON,
        actions JSON,
        is_active BOOLEAN DEFAULT TRUE,
        last_run_at DATETIME,
        run_count INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_trigger (trigger_event),
        INDEX idx_active (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS social_media_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        platform ENUM('facebook', 'instagram', 'twitter', 'linkedin', 'youtube') NOT NULL,
        content TEXT NOT NULL,
        media_urls JSON,
        hashtags TEXT,
        scheduled_date DATETIME,
        published_at DATETIME,
        status ENUM('draft', 'scheduled', 'published', 'failed') DEFAULT 'draft',
        engagement_data JSON,
        campaign_id INT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_platform (platform),
        INDEX idx_status (status),
        INDEX idx_scheduled (scheduled_date),
        FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL
    )"
];

foreach ($tables as $table) {
    mysqli_query($conn, $table);
}

// Get marketing statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM marketing_campaigns) as total_campaigns,
    (SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'active') as active_campaigns,
    (SELECT COUNT(*) FROM email_templates) as email_templates,
    (SELECT COUNT(*) FROM email_campaigns WHERE status = 'delivered' AND DATE(sent_at) = CURDATE()) as emails_sent_today,
    (SELECT COUNT(*) FROM newsletter_subscriptions WHERE is_active = 1) as newsletter_subscribers,
    (SELECT COUNT(*) FROM marketing_segments) as customer_segments,
    (SELECT SUM(budget) FROM marketing_campaigns WHERE status IN ('active', 'completed')) as total_budget_allocated,
    (SELECT SUM(spent_amount) FROM marketing_campaigns WHERE status IN ('active', 'completed')) as total_spent";

$stats_result = mysqli_query($conn, $stats_query);
$marketing_stats = mysqli_fetch_assoc($stats_result);

// Get recent campaigns
$recent_campaigns = mysqli_query($conn, "
    SELECT mc.*, 
           (SELECT COUNT(*) FROM email_campaigns ec 
            JOIN email_templates et ON ec.template_id = et.id 
            WHERE et.id IN (SELECT id FROM email_templates WHERE created_at >= mc.created_at)) as emails_sent
    FROM marketing_campaigns mc 
    ORDER BY mc.created_at DESC 
    LIMIT 10
");

// Get email performance
$email_performance = mysqli_query($conn, "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as emails_sent,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
        SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
    FROM email_campaigns 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<style>
.marketing-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e3e6f0;
}

.marketing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.stats-card {
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    border-radius: 12px;
    color: white;
    padding: 1.5rem;
    text-align: center;
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.stats-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.stats-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stats-label {
    font-size: 0.85rem;
    opacity: 0.8;
}

.campaign-status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

.automation-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    border: none;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.performance-metric {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 1rem;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #495057;
}

.metric-label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    transition: stroke-dasharray 0.35s;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📢 Marketing & Customer Communications</h1>
                <p class="text-muted">Engage customers with targeted campaigns and automation</p>
            </div>
            <div class="btn-group" role="group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                    <i class="bi bi-megaphone"></i> New Campaign
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                    <i class="bi bi-envelope-plus"></i> Email Template
                </button>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#segmentModal">
                    <i class="bi bi-people"></i> Create Segment
                </button>
            </div>
        </div>

        <!-- Marketing Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #667eea; --gradient-end: #764ba2;">
                    <div class="stats-icon">
                        <i class="bi bi-megaphone"></i>
                    </div>
                    <div class="stats-value"><?= number_format($marketing_stats['total_campaigns'] ?? 0) ?></div>
                    <div class="stats-label">Total Campaigns</div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #f093fb; --gradient-end: #f5576c;">
                    <div class="stats-icon">
                        <i class="bi bi-play-circle"></i>
                    </div>
                    <div class="stats-value"><?= number_format($marketing_stats['active_campaigns'] ?? 0) ?></div>
                    <div class="stats-label">Active Campaigns</div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #4facfe; --gradient-end: #00f2fe;">
                    <div class="stats-icon">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div class="stats-value"><?= number_format($marketing_stats['emails_sent_today'] ?? 0) ?></div>
                    <div class="stats-label">Emails Today</div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #43e97b; --gradient-end: #38f9d7;">
                    <div class="stats-icon">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <div class="stats-value"><?= number_format($marketing_stats['newsletter_subscribers'] ?? 0) ?></div>
                    <div class="stats-label">Newsletter Subs</div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #fa709a; --gradient-end: #fee140;">
                    <div class="stats-icon">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <div class="stats-value"><?= number_format($marketing_stats['customer_segments'] ?? 0) ?></div>
                    <div class="stats-label">Customer Segments</div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6">
                <div class="stats-card" style="--gradient-start: #a8edea; --gradient-end: #fed6e3;">
                    <div class="stats-icon">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                    <div class="stats-value">₹<?= number_format(($marketing_stats['total_budget_allocated'] ?? 0) / 100000, 1) ?>L</div>
                    <div class="stats-label">Total Budget</div>
                </div>
            </div>
        </div>

        <!-- Marketing Tools Row -->
        <div class="row g-4 mb-4">
            <!-- Campaign Management -->
            <div class="col-xl-4 col-lg-6">
                <div class="card marketing-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-rocket-takeoff text-primary me-2"></i>Campaign Management
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Create and manage marketing campaigns across multiple channels</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                                <i class="bi bi-plus-circle me-1"></i>New Campaign
                            </button>
                            <button class="btn btn-outline-secondary" onclick="viewCampaigns()">
                                <i class="bi bi-list me-1"></i>View All Campaigns
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Marketing -->
            <div class="col-xl-4 col-lg-6">
                <div class="card marketing-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-envelope-heart text-success me-2"></i>Email Marketing
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Design and send targeted email campaigns to your customers</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                                <i class="bi bi-envelope-plus me-1"></i>Create Template
                            </button>
                            <button class="btn btn-outline-secondary" onclick="sendEmailCampaign()">
                                <i class="bi bi-send me-1"></i>Send Campaign
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Segmentation -->
            <div class="col-xl-4 col-lg-6">
                <div class="card marketing-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-people-fill text-warning me-2"></i>Customer Segmentation
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Segment customers based on behavior and demographics</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#segmentModal">
                                <i class="bi bi-diagram-3 me-1"></i>Create Segment
                            </button>
                            <button class="btn btn-outline-secondary" onclick="viewSegments()">
                                <i class="bi bi-eye me-1"></i>View Segments
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Marketing Automation Features -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="automation-card">
                    <div class="feature-icon">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Marketing Automation</h6>
                    <p class="small mb-3 opacity-75">Automate campaigns based on customer behavior and triggers</p>
                    <button class="btn btn-light btn-sm" onclick="setupAutomation()">
                        <i class="bi bi-gear me-1"></i>Setup Rules
                    </button>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="automation-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="feature-icon">
                        <i class="bi bi-share"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Social Media</h6>
                    <p class="small mb-3 opacity-75">Schedule and manage posts across social platforms</p>
                    <button class="btn btn-light btn-sm" onclick="manageSocial()">
                        <i class="bi bi-calendar me-1"></i>Schedule Posts
                    </button>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="automation-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Analytics & Reports</h6>
                    <p class="small mb-3 opacity-75">Track campaign performance and ROI metrics</p>
                    <button class="btn btn-light btn-sm" onclick="viewAnalytics()">
                        <i class="bi bi-bar-chart me-1"></i>View Reports
                    </button>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="automation-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="feature-icon">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Newsletter</h6>
                    <p class="small mb-3 opacity-75">Build and manage newsletter subscriptions</p>
                    <button class="btn btn-light btn-sm" onclick="manageNewsletter()">
                        <i class="bi bi-envelope-open me-1"></i>Manage Subs
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Campaigns and Performance -->
        <div class="row g-4 mb-4">
            <!-- Recent Campaigns -->
            <div class="col-xl-8 col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-activity text-primary me-2"></i>Recent Marketing Campaigns
                        </h6>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewAllCampaigns()">
                            View All
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Budget</th>
                                        <th>Performance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_campaigns) > 0): ?>
                                        <?php while ($campaign = mysqli_fetch_assoc($recent_campaigns)): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($campaign['name']) ?></div>
                                                    <small class="text-muted"><?= date('M d, Y', strtotime($campaign['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?= ucfirst($campaign['type']) ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'draft' => 'secondary',
                                                        'active' => 'success',
                                                        'paused' => 'warning',
                                                        'completed' => 'info',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $color = $status_colors[$campaign['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($campaign['status']) ?></span>
                                                </td>
                                                <td>₹<?= number_format($campaign['budget'], 0) ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted me-2">Emails:</small>
                                                        <span class="badge bg-primary"><?= $campaign['emails_sent'] ?? 0 ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-primary" onclick="viewCampaign(<?= $campaign['id'] ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary" onclick="editCampaign(<?= $campaign['id'] ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-megaphone display-6 mb-3"></i>
                                                <div>No campaigns found. Create your first marketing campaign!</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="col-xl-4 col-lg-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up text-success me-2"></i>Email Performance (30 Days)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="performance-metric">
                            <div class="metric-value text-primary">95.2%</div>
                            <div class="metric-label">Delivery Rate</div>
                        </div>
                        
                        <div class="performance-metric">
                            <div class="metric-value text-success">24.8%</div>
                            <div class="metric-label">Open Rate</div>
                        </div>
                        
                        <div class="performance-metric">
                            <div class="metric-value text-warning">3.7%</div>
                            <div class="metric-label">Click Rate</div>
                        </div>
                        
                        <div class="performance-metric">
                            <div class="metric-value text-info">0.8%</div>
                            <div class="metric-label">Unsubscribe Rate</div>
                        </div>
                        
                        <div class="mt-3">
                            <canvas id="performanceChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Campaign Modal -->
<div class="modal fade" id="createCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-megaphone text-primary me-2"></i>Create Marketing Campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="campaignForm" onsubmit="createCampaign(event)">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Campaign Name *</label>
                            <input type="text" name="campaign_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type *</label>
                            <select name="campaign_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="email">Email Marketing</option>
                                <option value="social">Social Media</option>
                                <option value="ppc">Pay Per Click</option>
                                <option value="content">Content Marketing</option>
                                <option value="event">Event Marketing</option>
                                <option value="direct_mail">Direct Mail</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Budget (₹)</label>
                            <input type="number" name="budget" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Audience</label>
                            <input type="text" name="target_audience" class="form-control" placeholder="e.g., Young Professionals">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Campaign Goals</label>
                            <textarea name="goals" class="form-control" rows="2" placeholder="What do you want to achieve with this campaign?"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Marketing Channels</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="channel_email">
                                        <label class="form-check-label" for="channel_email">Email</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="social_media" id="channel_social">
                                        <label class="form-check-label" for="channel_social">Social Media</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="channel_sms">
                                        <label class="form-check-label" for="channel_sms">SMS</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="push_notification" id="channel_push">
                                        <label class="form-check-label" for="channel_push">Push Notifications</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="direct_mail" id="channel_mail">
                                        <label class="form-check-label" for="channel_mail">Direct Mail</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="website" id="channel_web">
                                        <label class="form-check-label" for="channel_web">Website</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Create Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Email Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope-plus text-success me-2"></i>Create Email Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm" onsubmit="createTemplate(event)">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Template Name *</label>
                            <input type="text" name="template_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="promotional">Promotional</option>
                                <option value="newsletter">Newsletter</option>
                                <option value="transactional">Transactional</option>
                                <option value="welcome">Welcome</option>
                                <option value="follow_up">Follow Up</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Subject Line *</label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Email Content *</label>
                            <textarea name="content" class="form-control" rows="10" required placeholder="Use {{customer_name}}, {{company_name}} for personalization"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Available Variables:</strong> {{customer_name}}, {{company_name}}, {{email}}, {{phone}}, {{current_date}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Customer Segment Modal -->
<div class="modal fade" id="segmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3 text-warning me-2"></i>Create Customer Segment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="segmentForm" onsubmit="createSegment(event)">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Segment Name *</label>
                            <input type="text" name="segment_name" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <h6>Segmentation Criteria</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="criteria[city]" class="form-control" placeholder="Filter by city">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Orders</label>
                            <input type="number" name="criteria[min_orders]" class="form-control" min="0" placeholder="Minimum number of orders">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Customer Since</label>
                            <input type="date" name="criteria[customer_since]" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Spent (Min)</label>
                            <input type="number" name="criteria[min_spent]" class="form-control" min="0" step="0.01" placeholder="Minimum total spent">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-circle me-1"></i>Create Segment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Performance Chart
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        datasets: [{
            label: 'Open Rate %',
            data: [22.5, 25.1, 23.8, 26.2],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Click Rate %',
            data: [3.2, 3.8, 3.5, 4.1],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 11
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 30
            }
        }
    }
});

// Form handlers
function createCampaign(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'create_campaign');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Campaign created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createCampaignModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
    });
}

function createTemplate(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'create_email_template');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Email template created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createTemplateModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
    });
}

function createSegment(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'create_customer_segment');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Customer segment created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('segmentModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Network error occurred', 'error');
    });
}

// Feature functions
function setupAutomation() {
    showAlert('Marketing automation setup coming soon!', 'info');
}

function manageSocial() {
    showAlert('Social media management coming soon!', 'info');
}

function viewAnalytics() {
    showAlert('Advanced analytics dashboard coming soon!', 'info');
}

function manageNewsletter() {
    showAlert('Newsletter management coming soon!', 'info');
}

function viewCampaigns() {
    showAlert('Campaign dashboard coming soon!', 'info');
}

function sendEmailCampaign() {
    showAlert('Email campaign sender coming soon!', 'info');
}

function viewSegments() {
    showAlert('Segment management dashboard coming soon!', 'info');
}

function viewCampaign(id) {
    showAlert(`Viewing campaign ID: ${id}`, 'info');
}

function editCampaign(id) {
    showAlert(`Editing campaign ID: ${id}`, 'info');
}

function viewAllCampaigns() {
    showAlert('Complete campaigns list coming soon!', 'info');
}

// Utility function
function showAlert(message, type = 'info') {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message.substring(0, 20))) {
                alert.remove();
            }
        });
    }, 5000);
}

// Auto refresh stats every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

<?php include '../../layouts/footer.php'; ?>
