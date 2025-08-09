<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_campaign_analytics':
        getCampaignAnalytics($conn);
        break;
        
    case 'get_email_performance':
        getEmailPerformance($conn);
        break;
        
    case 'get_customer_journey':
        getCustomerJourney($conn);
        break;
        
    case 'get_segmentation_insights':
        getSegmentationInsights($conn);
        break;
        
    case 'schedule_email_campaign':
        scheduleEmailCampaign($conn);
        break;
        
    case 'get_template_analytics':
        getTemplateAnalytics($conn);
        break;
        
    case 'get_automation_triggers':
        getAutomationTriggers($conn);
        break;
        
    case 'create_automation_rule':
        createAutomationRule($conn);
        break;
        
    case 'get_social_media_insights':
        getSocialMediaInsights($conn);
        break;
        
    case 'schedule_social_post':
        scheduleSocialPost($conn);
        break;
        
    case 'get_newsletter_analytics':
        getNewsletterAnalytics($conn);
        break;
        
    case 'export_marketing_data':
        exportMarketingData($conn);
        break;
        
    case 'get_roi_analysis':
        getROIAnalysis($conn);
        break;
        
    case 'get_campaign_comparison':
        getCampaignComparison($conn);
        break;
        
    case 'get_lead_scoring':
        getLeadScoring($conn);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getCampaignAnalytics($conn) {
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $date_range = $_POST['date_range'] ?? '30';
    $start_date = date('Y-m-d', strtotime("-$date_range days"));
    
    $analytics = [];
    
    // Campaign overview
    if ($campaign_id > 0) {
        $campaign_query = "SELECT mc.*, 
                          (SELECT COUNT(*) FROM email_campaigns ec 
                           JOIN email_templates et ON ec.template_id = et.id 
                           WHERE et.created_at >= mc.created_at) as total_emails,
                          (SELECT COUNT(*) FROM email_campaigns ec 
                           JOIN email_templates et ON ec.template_id = et.id 
                           WHERE et.created_at >= mc.created_at AND ec.status = 'delivered') as delivered_emails,
                          (SELECT COUNT(*) FROM email_campaigns ec 
                           JOIN email_templates et ON ec.template_id = et.id 
                           WHERE et.created_at >= mc.created_at AND ec.opened_at IS NOT NULL) as opened_emails,
                          (SELECT COUNT(*) FROM email_campaigns ec 
                           JOIN email_templates et ON ec.template_id = et.id 
                           WHERE et.created_at >= mc.created_at AND ec.clicked_at IS NOT NULL) as clicked_emails
                          FROM marketing_campaigns mc WHERE mc.id = $campaign_id";
        
        $campaign_result = mysqli_query($conn, $campaign_query);
        $analytics['campaign'] = mysqli_fetch_assoc($campaign_result);
        
        // Calculate rates
        $total = $analytics['campaign']['total_emails'] ?? 1;
        $analytics['campaign']['delivery_rate'] = round(($analytics['campaign']['delivered_emails'] / $total) * 100, 2);
        $analytics['campaign']['open_rate'] = round(($analytics['campaign']['opened_emails'] / $total) * 100, 2);
        $analytics['campaign']['click_rate'] = round(($analytics['campaign']['clicked_emails'] / $total) * 100, 2);
    }
    
    // Daily performance for chart
    $daily_query = "SELECT 
                    DATE(ec.created_at) as date,
                    COUNT(*) as emails_sent,
                    SUM(CASE WHEN ec.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
                    FROM email_campaigns ec
                    WHERE ec.created_at >= '$start_date'
                    GROUP BY DATE(ec.created_at)
                    ORDER BY date ASC";
    
    $daily_result = mysqli_query($conn, $daily_query);
    $analytics['daily_performance'] = [];
    while ($row = mysqli_fetch_assoc($daily_result)) {
        $analytics['daily_performance'][] = $row;
    }
    
    // Top performing templates
    $template_query = "SELECT 
                      et.name,
                      et.subject,
                      COUNT(ec.id) as emails_sent,
                      SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                      SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                      ROUND((SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(ec.id)) * 100, 2) as open_rate
                      FROM email_templates et
                      LEFT JOIN email_campaigns ec ON et.id = ec.template_id
                      WHERE et.created_at >= '$start_date'
                      GROUP BY et.id
                      ORDER BY open_rate DESC
                      LIMIT 10";
    
    $template_result = mysqli_query($conn, $template_query);
    $analytics['top_templates'] = [];
    while ($row = mysqli_fetch_assoc($template_result)) {
        $analytics['top_templates'][] = $row;
    }
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function getEmailPerformance($conn) {
    $period = $_POST['period'] ?? 'last_30_days';
    
    switch ($period) {
        case 'today':
            $start_date = date('Y-m-d');
            break;
        case 'yesterday':
            $start_date = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'last_7_days':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'last_30_days':
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
    }
    
    $performance = [];
    
    // Overall metrics
    $overall_query = "SELECT 
                     COUNT(*) as total_sent,
                     SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                     SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                     SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                     SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                     SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed
                     FROM email_campaigns 
                     WHERE created_at >= '$start_date'";
    
    $overall_result = mysqli_query($conn, $overall_query);
    $overall = mysqli_fetch_assoc($overall_result);
    
    // Calculate rates
    $total = $overall['total_sent'] ?: 1;
    $performance['metrics'] = [
        'total_sent' => $overall['total_sent'],
        'delivery_rate' => round(($overall['delivered'] / $total) * 100, 2),
        'bounce_rate' => round(($overall['bounced'] / $total) * 100, 2),
        'open_rate' => round(($overall['opened'] / $total) * 100, 2),
        'click_rate' => round(($overall['clicked'] / $total) * 100, 2),
        'unsubscribe_rate' => round(($overall['unsubscribed'] / $total) * 100, 2)
    ];
    
    // Performance by email category
    $category_query = "SELECT 
                      et.category,
                      COUNT(ec.id) as emails_sent,
                      SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                      ROUND((SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(ec.id)) * 100, 2) as open_rate
                      FROM email_campaigns ec
                      JOIN email_templates et ON ec.template_id = et.id
                      WHERE ec.created_at >= '$start_date'
                      GROUP BY et.category
                      ORDER BY open_rate DESC";
    
    $category_result = mysqli_query($conn, $category_query);
    $performance['by_category'] = [];
    while ($row = mysqli_fetch_assoc($category_result)) {
        $performance['by_category'][] = $row;
    }
    
    // Hourly send patterns
    $hourly_query = "SELECT 
                    HOUR(sent_at) as hour,
                    COUNT(*) as emails_sent,
                    SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens
                    FROM email_campaigns 
                    WHERE sent_at >= '$start_date' AND sent_at IS NOT NULL
                    GROUP BY HOUR(sent_at)
                    ORDER BY hour";
    
    $hourly_result = mysqli_query($conn, $hourly_query);
    $performance['hourly_pattern'] = [];
    while ($row = mysqli_fetch_assoc($hourly_result)) {
        $performance['hourly_pattern'][] = $row;
    }
    
    echo json_encode(['success' => true, 'performance' => $performance]);
}

function getCustomerJourney($conn) {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    
    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'Customer ID required']);
        return;
    }
    
    $journey = [];
    
    // Customer basic info
    $customer_query = "SELECT * FROM customers WHERE id = $customer_id";
    $customer_result = mysqli_query($conn, $customer_query);
    $journey['customer'] = mysqli_fetch_assoc($customer_result);
    
    // Email interactions
    $email_query = "SELECT 
                   ec.*,
                   et.name as template_name,
                   et.subject,
                   et.category
                   FROM email_campaigns ec
                   JOIN email_templates et ON ec.template_id = et.id
                   WHERE ec.recipient_email = '{$journey['customer']['email']}'
                   ORDER BY ec.created_at DESC";
    
    $email_result = mysqli_query($conn, $email_query);
    $journey['email_interactions'] = [];
    while ($row = mysqli_fetch_assoc($email_result)) {
        $journey['email_interactions'][] = $row;
    }
    
    // Purchase history (from invoices)
    $purchase_query = "SELECT 
                      invoice_number,
                      invoice_date,
                      total_amount,
                      status
                      FROM invoices 
                      WHERE customer_id = $customer_id
                      ORDER BY invoice_date DESC
                      LIMIT 20";
    
    $purchase_result = mysqli_query($conn, $purchase_query);
    $journey['purchases'] = [];
    while ($row = mysqli_fetch_assoc($purchase_result)) {
        $journey['purchases'][] = $row;
    }
    
    // Customer segments
    $segment_query = "SELECT 
                     ms.name,
                     ms.description,
                     cs.added_at
                     FROM customer_segments cs
                     JOIN marketing_segments ms ON cs.segment_id = ms.id
                     WHERE cs.customer_id = $customer_id
                     ORDER BY cs.added_at DESC";
    
    $segment_result = mysqli_query($conn, $segment_query);
    $journey['segments'] = [];
    while ($row = mysqli_fetch_assoc($segment_result)) {
        $journey['segments'][] = $row;
    }
    
    echo json_encode(['success' => true, 'journey' => $journey]);
}

function getSegmentationInsights($conn) {
    $insights = [];
    
    // Segment performance
    $segment_query = "SELECT 
                     ms.*,
                     COUNT(cs.customer_id) as customer_count,
                     AVG(inv.total_amount) as avg_order_value,
                     COUNT(DISTINCT inv.id) as total_orders
                     FROM marketing_segments ms
                     LEFT JOIN customer_segments cs ON ms.id = cs.segment_id
                     LEFT JOIN customers c ON cs.customer_id = c.id
                     LEFT JOIN invoices inv ON c.id = inv.customer_id
                     GROUP BY ms.id
                     ORDER BY customer_count DESC";
    
    $segment_result = mysqli_query($conn, $segment_query);
    $insights['segments'] = [];
    while ($row = mysqli_fetch_assoc($segment_result)) {
        $insights['segments'][] = $row;
    }
    
    // Segment email engagement
    $engagement_query = "SELECT 
                        ms.name as segment_name,
                        COUNT(ec.id) as emails_sent,
                        SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as emails_opened,
                        ROUND((SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(ec.id)) * 100, 2) as open_rate
                        FROM marketing_segments ms
                        JOIN customer_segments cs ON ms.id = cs.segment_id
                        JOIN customers c ON cs.customer_id = c.id
                        JOIN email_campaigns ec ON c.email = ec.recipient_email
                        GROUP BY ms.id
                        ORDER BY open_rate DESC";
    
    $engagement_result = mysqli_query($conn, $engagement_query);
    $insights['engagement'] = [];
    while ($row = mysqli_fetch_assoc($engagement_result)) {
        $insights['engagement'][] = $row;
    }
    
    echo json_encode(['success' => true, 'insights' => $insights]);
}

function scheduleEmailCampaign($conn) {
    $template_id = intval($_POST['template_id']);
    $segment_id = intval($_POST['segment_id']);
    $schedule_date = mysqli_real_escape_string($conn, $_POST['schedule_date']);
    $send_now = $_POST['send_now'] ?? false;
    
    if (!$template_id || !$segment_id) {
        echo json_encode(['success' => false, 'message' => 'Template and segment required']);
        return;
    }
    
    // Get template
    $template_query = "SELECT * FROM email_templates WHERE id = $template_id";
    $template_result = mysqli_query($conn, $template_query);
    $template = mysqli_fetch_assoc($template_result);
    
    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        return;
    }
    
    // Get segment customers
    $customers_query = "SELECT c.* FROM customer_segments cs 
                       JOIN customers c ON cs.customer_id = c.id 
                       WHERE cs.segment_id = $segment_id AND c.email IS NOT NULL AND c.email != ''";
    $customers_result = mysqli_query($conn, $customers_query);
    
    $scheduled_count = 0;
    $errors = [];
    
    while ($customer = mysqli_fetch_assoc($customers_result)) {
        $status = $send_now ? 'sent' : 'scheduled';
        $sent_at = $send_now ? 'NOW()' : 'NULL';
        $delivered_at = $send_now ? 'NOW()' : 'NULL';
        
        $campaign_query = "INSERT INTO email_campaigns 
                          (template_id, recipient_email, recipient_name, status, scheduled_date, sent_at, delivered_at, created_by) 
                          VALUES ($template_id, '{$customer['email']}', '{$customer['customer_name']}', 
                          '$status', '$schedule_date', $sent_at, $delivered_at, {$_SESSION['admin']})";
        
        if (mysqli_query($conn, $campaign_query)) {
            $scheduled_count++;
            
            // If sending now, simulate email sending process
            if ($send_now) {
                // In real implementation, integrate with email service (SendGrid, Mailchimp, etc.)
                // For now, just mark as delivered with some random success rate
                $campaign_id = mysqli_insert_id($conn);
                
                // Simulate 95% delivery rate
                if (rand(1, 100) <= 95) {
                    // Simulate 25% open rate
                    if (rand(1, 100) <= 25) {
                        $opened_at = date('Y-m-d H:i:s', strtotime('+' . rand(1, 120) . ' minutes'));
                        mysqli_query($conn, "UPDATE email_campaigns SET opened_at = '$opened_at' WHERE id = $campaign_id");
                        
                        // Simulate 15% click rate of opened emails
                        if (rand(1, 100) <= 15) {
                            $clicked_at = date('Y-m-d H:i:s', strtotime($opened_at . ' +' . rand(1, 30) . ' minutes'));
                            mysqli_query($conn, "UPDATE email_campaigns SET clicked_at = '$clicked_at' WHERE id = $campaign_id");
                        }
                    }
                } else {
                    // Mark as bounced
                    mysqli_query($conn, "UPDATE email_campaigns SET status = 'bounced', bounce_reason = 'Email address invalid' WHERE id = $campaign_id");
                }
            }
        } else {
            $errors[] = "Failed to schedule email for {$customer['email']}";
        }
    }
    
    $message = $send_now ? 
        "Email campaign sent to $scheduled_count recipients immediately" : 
        "Email campaign scheduled for $scheduled_count recipients on $schedule_date";
    
    if (!empty($errors)) {
        $message .= ". Errors: " . implode(', ', array_slice($errors, 0, 3));
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'scheduled_count' => $scheduled_count,
        'errors' => $errors
    ]);
}

function getTemplateAnalytics($conn) {
    $template_id = intval($_POST['template_id'] ?? 0);
    $analytics = [];
    
    if ($template_id > 0) {
        // Single template analytics
        $template_query = "SELECT 
                          et.*,
                          COUNT(ec.id) as total_sent,
                          SUM(CASE WHEN ec.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                          SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                          SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                          SUM(CASE WHEN ec.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed
                          FROM email_templates et
                          LEFT JOIN email_campaigns ec ON et.id = ec.template_id
                          WHERE et.id = $template_id
                          GROUP BY et.id";
        
        $template_result = mysqli_query($conn, $template_query);
        $template_data = mysqli_fetch_assoc($template_result);
        
        // Calculate performance metrics
        $total = $template_data['total_sent'] ?: 1;
        $analytics['template'] = $template_data;
        $analytics['template']['delivery_rate'] = round(($template_data['delivered'] / $total) * 100, 2);
        $analytics['template']['open_rate'] = round(($template_data['opened'] / $total) * 100, 2);
        $analytics['template']['click_rate'] = round(($template_data['clicked'] / $total) * 100, 2);
        $analytics['template']['unsubscribe_rate'] = round(($template_data['unsubscribed'] / $total) * 100, 2);
        
    } else {
        // All templates comparison
        $templates_query = "SELECT 
                           et.id,
                           et.name,
                           et.category,
                           et.created_at,
                           COUNT(ec.id) as emails_sent,
                           SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                           SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                           ROUND((SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(ec.id)) * 100, 2) as open_rate,
                           ROUND((SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(ec.id)) * 100, 2) as click_rate
                           FROM email_templates et
                           LEFT JOIN email_campaigns ec ON et.id = ec.template_id
                           GROUP BY et.id
                           ORDER BY open_rate DESC";
        
        $templates_result = mysqli_query($conn, $templates_query);
        $analytics['templates'] = [];
        while ($row = mysqli_fetch_assoc($templates_result)) {
            $analytics['templates'][] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function getAutomationTriggers($conn) {
    $triggers = [
        'customer_signup' => [
            'name' => 'New Customer Registration',
            'description' => 'Triggered when a new customer registers',
            'available_actions' => ['send_welcome_email', 'add_to_segment', 'assign_rep']
        ],
        'purchase_made' => [
            'name' => 'Purchase Completed',
            'description' => 'Triggered when customer makes a purchase',
            'available_actions' => ['send_thank_you', 'request_review', 'recommend_products']
        ],
        'cart_abandoned' => [
            'name' => 'Cart Abandoned',
            'description' => 'Triggered when customer leaves items in cart',
            'available_actions' => ['send_reminder', 'offer_discount', 'send_alternatives']
        ],
        'birthday' => [
            'name' => 'Customer Birthday',
            'description' => 'Triggered on customer birthday',
            'available_actions' => ['send_birthday_wish', 'offer_birthday_discount']
        ],
        'anniversary' => [
            'name' => 'Customer Anniversary',
            'description' => 'Triggered on customer anniversary date',
            'available_actions' => ['send_anniversary_email', 'special_offer']
        ],
        'inactivity' => [
            'name' => 'Customer Inactivity',
            'description' => 'Triggered when customer is inactive for specified period',
            'available_actions' => ['send_win_back', 'offer_incentive', 'survey_feedback']
        ]
    ];
    
    // Get existing automation rules
    $rules_query = "SELECT * FROM marketing_automation_rules ORDER BY created_at DESC";
    $rules_result = mysqli_query($conn, $rules_query);
    $existing_rules = [];
    while ($row = mysqli_fetch_assoc($rules_result)) {
        $existing_rules[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'triggers' => $triggers,
        'existing_rules' => $existing_rules
    ]);
}

function createAutomationRule($conn) {
    $name = mysqli_real_escape_string($conn, $_POST['rule_name']);
    $trigger = mysqli_real_escape_string($conn, $_POST['trigger_event']);
    $conditions = json_encode($_POST['conditions'] ?? []);
    $actions = json_encode($_POST['actions'] ?? []);
    
    $query = "INSERT INTO marketing_automation_rules 
              (name, trigger_event, conditions, actions, created_by) 
              VALUES ('$name', '$trigger', '$conditions', '$actions', {$_SESSION['admin']})";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Automation rule created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating automation rule']);
    }
}

function getSocialMediaInsights($conn) {
    // Mock social media data since we don't have real integration
    $insights = [
        'platforms' => [
            'facebook' => [
                'followers' => 2450,
                'posts_this_month' => 15,
                'engagement_rate' => 4.2,
                'reach' => 18500
            ],
            'instagram' => [
                'followers' => 1890,
                'posts_this_month' => 22,
                'engagement_rate' => 6.8,
                'reach' => 14200
            ],
            'twitter' => [
                'followers' => 980,
                'posts_this_month' => 45,
                'engagement_rate' => 2.1,
                'reach' => 8900
            ],
            'linkedin' => [
                'followers' => 520,
                'posts_this_month' => 8,
                'engagement_rate' => 3.5,
                'reach' => 4200
            ]
        ],
        'top_posts' => [
            [
                'platform' => 'Instagram',
                'content' => 'New product launch announcement!',
                'likes' => 145,
                'shares' => 23,
                'comments' => 18
            ],
            [
                'platform' => 'Facebook',
                'content' => 'Customer success story spotlight',
                'likes' => 89,
                'shares' => 34,
                'comments' => 12
            ]
        ],
        'upcoming_posts' => []
    ];
    
    // Get scheduled posts from database
    $posts_query = "SELECT * FROM social_media_posts WHERE status = 'scheduled' ORDER BY scheduled_date ASC LIMIT 10";
    $posts_result = mysqli_query($conn, $posts_query);
    while ($row = mysqli_fetch_assoc($posts_result)) {
        $insights['upcoming_posts'][] = $row;
    }
    
    echo json_encode(['success' => true, 'insights' => $insights]);
}

function scheduleSocialPost($conn) {
    $platform = mysqli_real_escape_string($conn, $_POST['platform']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $scheduled_date = mysqli_real_escape_string($conn, $_POST['scheduled_date']);
    $hashtags = mysqli_real_escape_string($conn, $_POST['hashtags'] ?? '');
    $media_urls = json_encode($_POST['media_urls'] ?? []);
    
    $query = "INSERT INTO social_media_posts 
              (platform, content, hashtags, media_urls, scheduled_date, created_by) 
              VALUES ('$platform', '$content', '$hashtags', '$media_urls', '$scheduled_date', {$_SESSION['admin']})";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Social media post scheduled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error scheduling social media post']);
    }
}

function getNewsletterAnalytics($conn) {
    $analytics = [];
    
    // Subscriber growth
    $growth_query = "SELECT 
                    DATE(subscribed_at) as date,
                    COUNT(*) as new_subscribers,
                    SUM(COUNT(*)) OVER (ORDER BY DATE(subscribed_at)) as total_subscribers
                    FROM newsletter_subscriptions 
                    WHERE subscribed_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    GROUP BY DATE(subscribed_at)
                    ORDER BY date";
    
    $growth_result = mysqli_query($conn, $growth_query);
    $analytics['subscriber_growth'] = [];
    while ($row = mysqli_fetch_assoc($growth_result)) {
        $analytics['subscriber_growth'][] = $row;
    }
    
    // Subscriber stats
    $stats_query = "SELECT 
                   COUNT(*) as total_subscribers,
                   SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_subscribers,
                   SUM(CASE WHEN subscribed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week,
                   SUM(CASE WHEN unsubscribed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as unsubscribed_this_week
                   FROM newsletter_subscriptions";
    
    $stats_result = mysqli_query($conn, $stats_query);
    $analytics['stats'] = mysqli_fetch_assoc($stats_result);
    
    // Subscriber sources
    $sources_query = "SELECT 
                     source,
                     COUNT(*) as count
                     FROM newsletter_subscriptions 
                     GROUP BY source
                     ORDER BY count DESC";
    
    $sources_result = mysqli_query($conn, $sources_query);
    $analytics['sources'] = [];
    while ($row = mysqli_fetch_assoc($sources_result)) {
        $analytics['sources'][] = $row;
    }
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function exportMarketingData($conn) {
    $export_type = $_POST['export_type'] ?? 'campaigns';
    $format = $_POST['format'] ?? 'csv';
    
    switch ($export_type) {
        case 'campaigns':
            $query = "SELECT * FROM marketing_campaigns ORDER BY created_at DESC";
            $filename = 'marketing_campaigns_' . date('Y-m-d');
            break;
        case 'email_performance':
            $query = "SELECT ec.*, et.name as template_name FROM email_campaigns ec 
                     JOIN email_templates et ON ec.template_id = et.id 
                     ORDER BY ec.created_at DESC";
            $filename = 'email_performance_' . date('Y-m-d');
            break;
        case 'segments':
            $query = "SELECT * FROM marketing_segments ORDER BY created_at DESC";
            $filename = 'customer_segments_' . date('Y-m-d');
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid export type']);
            return;
    }
    
    $result = mysqli_query($conn, $query);
    
    if ($format === 'csv') {
        $csv_data = [];
        $headers = [];
        
        if ($row = mysqli_fetch_assoc($result)) {
            $headers = array_keys($row);
            $csv_data[] = $headers;
            $csv_data[] = array_values($row);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $csv_data[] = array_values($row);
            }
        }
        
        echo json_encode([
            'success' => true,
            'filename' => $filename . '.csv',
            'data' => $csv_data
        ]);
    } else {
        // JSON export
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'filename' => $filename . '.json',
            'data' => $data
        ]);
    }
}

function getROIAnalysis($conn) {
    $period = $_POST['period'] ?? '30';
    $start_date = date('Y-m-d', strtotime("-$period days"));
    
    $roi_data = [];
    
    // Campaign ROI
    $campaign_roi_query = "SELECT 
                          mc.name,
                          mc.budget,
                          mc.spent_amount,
                          COALESCE(SUM(inv.total_amount), 0) as revenue_generated
                          FROM marketing_campaigns mc
                          LEFT JOIN email_campaigns ec ON ec.created_at >= mc.start_date AND ec.created_at <= mc.end_date
                          LEFT JOIN customers c ON c.email = ec.recipient_email
                          LEFT JOIN invoices inv ON inv.customer_id = c.id AND inv.invoice_date >= mc.start_date
                          WHERE mc.created_at >= '$start_date'
                          GROUP BY mc.id
                          ORDER BY revenue_generated DESC";
    
    $campaign_result = mysqli_query($conn, $campaign_roi_query);
    $roi_data['campaigns'] = [];
    while ($row = mysqli_fetch_assoc($campaign_result)) {
        $spent = $row['spent_amount'] ?: $row['budget'] ?: 1;
        $row['roi_percentage'] = round((($row['revenue_generated'] - $spent) / $spent) * 100, 2);
        $roi_data['campaigns'][] = $row;
    }
    
    // Channel performance
    $channel_data = [
        'email' => [
            'cost' => 5000,
            'conversions' => 45,
            'revenue' => 125000,
            'roi' => 2400
        ],
        'social_media' => [
            'cost' => 8000,
            'conversions' => 32,
            'revenue' => 98000,
            'roi' => 1125
        ],
        'ppc' => [
            'cost' => 12000,
            'conversions' => 78,
            'revenue' => 185000,
            'roi' => 1441
        ]
    ];
    
    $roi_data['channels'] = $channel_data;
    
    echo json_encode(['success' => true, 'roi_data' => $roi_data]);
}

function getCampaignComparison($conn) {
    $campaign_ids = $_POST['campaign_ids'] ?? [];
    
    if (empty($campaign_ids)) {
        echo json_encode(['success' => false, 'message' => 'Campaign IDs required']);
        return;
    }
    
    $ids_string = implode(',', array_map('intval', $campaign_ids));
    
    $comparison = [];
    
    // Basic campaign data
    $campaigns_query = "SELECT * FROM marketing_campaigns WHERE id IN ($ids_string)";
    $campaigns_result = mysqli_query($conn, $campaigns_query);
    
    while ($campaign = mysqli_fetch_assoc($campaigns_result)) {
        $campaign_id = $campaign['id'];
        
        // Get email performance for this campaign
        $email_query = "SELECT 
                       COUNT(ec.id) as emails_sent,
                       SUM(CASE WHEN ec.opened_at IS NOT NULL THEN 1 ELSE 0 END) as emails_opened,
                       SUM(CASE WHEN ec.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as emails_clicked
                       FROM email_campaigns ec
                       JOIN email_templates et ON ec.template_id = et.id
                       WHERE et.created_at >= '{$campaign['start_date']}' 
                       AND et.created_at <= '{$campaign['end_date']}'";
        
        $email_result = mysqli_query($conn, $email_query);
        $email_data = mysqli_fetch_assoc($email_result);
        
        $campaign['email_performance'] = $email_data;
        $sent = $email_data['emails_sent'] ?: 1;
        $campaign['open_rate'] = round(($email_data['emails_opened'] / $sent) * 100, 2);
        $campaign['click_rate'] = round(($email_data['emails_clicked'] / $sent) * 100, 2);
        
        $comparison[] = $campaign;
    }
    
    echo json_encode(['success' => true, 'comparison' => $comparison]);
}

function getLeadScoring($conn) {
    $scoring_rules = [
        'email_opened' => 5,
        'email_clicked' => 10,
        'website_visit' => 3,
        'form_submission' => 15,
        'download' => 8,
        'purchase' => 50,
        'social_media_interaction' => 2
    ];
    
    // Calculate lead scores for customers
    $leads_query = "SELECT 
                   c.id,
                   c.customer_name,
                   c.email,
                   c.phone,
                   c.created_at as registration_date,
                   COUNT(DISTINCT inv.id) as total_purchases,
                   SUM(inv.total_amount) as total_spent,
                   COUNT(DISTINCT ec_opened.id) * {$scoring_rules['email_opened']} as email_open_score,
                   COUNT(DISTINCT ec_clicked.id) * {$scoring_rules['email_clicked']} as email_click_score,
                   COUNT(DISTINCT inv.id) * {$scoring_rules['purchase']} as purchase_score
                   FROM customers c
                   LEFT JOIN invoices inv ON c.id = inv.customer_id
                   LEFT JOIN email_campaigns ec_opened ON c.email = ec_opened.recipient_email AND ec_opened.opened_at IS NOT NULL
                   LEFT JOIN email_campaigns ec_clicked ON c.email = ec_clicked.recipient_email AND ec_clicked.clicked_at IS NOT NULL
                   WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                   GROUP BY c.id
                   ORDER BY (email_open_score + email_click_score + purchase_score) DESC
                   LIMIT 100";
    
    $leads_result = mysqli_query($conn, $leads_query);
    $leads = [];
    
    while ($lead = mysqli_fetch_assoc($leads_result)) {
        $total_score = $lead['email_open_score'] + $lead['email_click_score'] + $lead['purchase_score'];
        $lead['total_score'] = $total_score;
        
        // Categorize lead quality
        if ($total_score >= 100) {
            $lead['quality'] = 'Hot';
            $lead['quality_color'] = 'danger';
        } elseif ($total_score >= 50) {
            $lead['quality'] = 'Warm';
            $lead['quality_color'] = 'warning';
        } elseif ($total_score >= 20) {
            $lead['quality'] = 'Cold';
            $lead['quality_color'] = 'info';
        } else {
            $lead['quality'] = 'New';
            $lead['quality_color'] = 'secondary';
        }
        
        $leads[] = $lead;
    }
    
    echo json_encode([
        'success' => true, 
        'leads' => $leads,
        'scoring_rules' => $scoring_rules
    ]);
}
?>
