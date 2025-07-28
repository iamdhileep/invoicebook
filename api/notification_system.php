<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../../../db.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_notifications':
        getNotifications();
        break;
    case 'mark_read':
        markNotificationAsRead();
        break;
    case 'get_notification_preferences':
        getNotificationPreferences();
        break;
    case 'update_preferences':
        updateNotificationPreferences();
        break;
    case 'send_custom_notification':
        sendCustomNotification();
        break;
    case 'get_notification_analytics':
        getNotificationAnalytics();
        break;
    case 'configure_smart_alerts':
        configureSmartAlerts();
        break;
    case 'get_alert_templates':
        getAlertTemplates();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getNotifications() {
    global $conn;
    
    $user_id = intval($_GET['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    $type = $_GET['type'] ?? 'all';
    $limit = intval($_GET['limit'] ?? 50);
    $unread_only = $_GET['unread_only'] ?? false;
    
    $where_conditions = ["recipient_id = ?"];
    $params = [$user_id];
    $param_types = "i";
    
    if ($type !== 'all') {
        $where_conditions[] = "type = ?";
        $params[] = $type;
        $param_types .= "s";
    }
    
    if ($unread_only) {
        $where_conditions[] = "is_read = 0";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT n.*, e.name as sender_name
        FROM notifications n
        LEFT JOIN employees e ON n.sender_id = e.employee_id
        WHERE $where_clause
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $param_types .= "i";
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get unread count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
}

function markNotificationAsRead() {
    global $conn;
    
    $notification_id = intval($_POST['notification_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND recipient_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
    }
}

function getNotificationPreferences() {
    global $conn;
    
    $user_id = intval($_GET['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT * FROM notification_preferences 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $preferences = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Convert to associative array for easier handling
    $formatted_preferences = [];
    foreach ($preferences as $pref) {
        $formatted_preferences[$pref['notification_type']] = [
            'email' => (bool)$pref['email_enabled'],
            'push' => (bool)$pref['push_enabled'],
            'sms' => (bool)$pref['sms_enabled'],
            'in_app' => (bool)$pref['in_app_enabled']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'preferences' => $formatted_preferences
    ]);
}

function updateNotificationPreferences() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    $preferences = json_decode($_POST['preferences'] ?? '{}', true);
    
    if (empty($preferences)) {
        echo json_encode(['success' => false, 'message' => 'No preferences provided']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Delete existing preferences
        $stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Insert new preferences
        $stmt = $conn->prepare("
            INSERT INTO notification_preferences 
            (user_id, notification_type, email_enabled, push_enabled, sms_enabled, in_app_enabled)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($preferences as $type => $settings) {
            $stmt->bind_param("isiiiii", 
                $user_id,
                $type,
                $settings['email'] ? 1 : 0,
                $settings['push'] ? 1 : 0,
                $settings['sms'] ? 1 : 0,
                $settings['in_app'] ? 1 : 0
            );
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update preferences: ' . $e->getMessage()]);
    }
}

function sendCustomNotification() {
    global $conn;
    
    $sender_id = intval($_POST['sender_id'] ?? $_SESSION['admin_id'] ?? 0);
    $recipients = json_decode($_POST['recipients'] ?? '[]', true);
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $type = $_POST['type'] ?? 'general';
    $priority = $_POST['priority'] ?? 'normal';
    $channels = json_decode($_POST['channels'] ?? '["in_app"]', true);
    
    if (empty($recipients) || empty($title) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    $success_count = 0;
    $errors = [];
    
    foreach ($recipients as $recipient_id) {
        try {
            // Insert notification
            $stmt = $conn->prepare("
                INSERT INTO notifications 
                (sender_id, recipient_id, title, message, type, priority, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iissss", $sender_id, $recipient_id, $title, $message, $type, $priority);
            $stmt->execute();
            
            $notification_id = $conn->insert_id;
            
            // Send via requested channels
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        sendEmailNotification($recipient_id, $title, $message);
                        break;
                    case 'sms':
                        sendSMSNotification($recipient_id, $title, $message);
                        break;
                    case 'push':
                        sendPushNotification($recipient_id, $title, $message);
                        break;
                }
            }
            
            $success_count++;
            
        } catch (Exception $e) {
            $errors[] = "Failed to send to recipient $recipient_id: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => $success_count > 0,
        'message' => "Notification sent to $success_count recipients",
        'errors' => $errors
    ]);
}

function getNotificationAnalytics() {
    global $conn;
    
    $period = $_GET['period'] ?? 'monthly';
    $start_date = getStartDateForPeriod($period);
    
    $analytics = [];
    
    // Notification counts by type
    $stmt = $conn->prepare("
        SELECT type, COUNT(*) as count
        FROM notifications
        WHERE created_at >= ?
        GROUP BY type
        ORDER BY count DESC
    ");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $analytics['by_type'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Daily notification trends
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM notifications
        WHERE created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $analytics['daily_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Read vs Unread ratio
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM notifications
        WHERE created_at >= ?
    ");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $analytics['read_ratio'] = $stmt->get_result()->fetch_assoc();
    
    // Response time analysis
    $stmt = $conn->prepare("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, created_at, read_at)) as avg_response_time_minutes,
            MIN(TIMESTAMPDIFF(MINUTE, created_at, read_at)) as min_response_time_minutes,
            MAX(TIMESTAMPDIFF(MINUTE, created_at, read_at)) as max_response_time_minutes
        FROM notifications
        WHERE created_at >= ? AND read_at IS NOT NULL
    ");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $analytics['response_time'] = $stmt->get_result()->fetch_assoc();
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function configureSmartAlerts() {
    global $conn;
    
    $alert_config = json_decode($_POST['config'] ?? '{}', true);
    
    if (empty($alert_config)) {
        echo json_encode(['success' => false, 'message' => 'No configuration provided']);
        return;
    }
    
    $user_id = intval($_POST['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    
    $conn->begin_transaction();
    
    try {
        // Delete existing smart alert configs
        $stmt = $conn->prepare("DELETE FROM smart_alert_config WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Insert new configurations
        $stmt = $conn->prepare("
            INSERT INTO smart_alert_config 
            (user_id, alert_type, condition_json, action_json, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($alert_config as $alert) {
            $stmt->bind_param("isssi",
                $user_id,
                $alert['type'],
                json_encode($alert['conditions']),
                json_encode($alert['actions']),
                $alert['is_active'] ? 1 : 0
            );
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Smart alerts configured successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to configure smart alerts: ' . $e->getMessage()]);
    }
}

function getAlertTemplates() {
    global $conn;
    
    $category = $_GET['category'] ?? 'all';
    
    $where_clause = $category !== 'all' ? "WHERE category = ?" : "";
    
    $stmt = $conn->prepare("
        SELECT * FROM alert_templates $where_clause
        ORDER BY category, template_name
    ");
    
    if ($category !== 'all') {
        $stmt->bind_param("s", $category);
    }
    
    $stmt->execute();
    $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'templates' => $templates]);
}

// Helper functions for notification delivery
function sendEmailNotification($recipient_id, $title, $message) {
    global $conn;
    
    // Get recipient email
    $stmt = $conn->prepare("SELECT email FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Recipient email not found");
    }
    
    $recipient = $result->fetch_assoc();
    $email = $recipient['email'];
    
    // Simple email sending (in production, use proper email service)
    $headers = "From: noreply@billbook.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $email_body = "
        <html>
        <body>
            <h2>$title</h2>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            <p><small>This is an automated notification from BillBook Attendance System.</small></p>
        </body>
        </html>
    ";
    
    mail($email, $title, $email_body, $headers);
}

function sendSMSNotification($recipient_id, $title, $message) {
    global $conn;
    
    // Get recipient phone
    $stmt = $conn->prepare("SELECT phone FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Recipient phone not found");
    }
    
    $recipient = $result->fetch_assoc();
    $phone = $recipient['phone'];
    
    // SMS sending logic would go here
    // This would integrate with SMS service provider like Twilio, etc.
    
    // Log SMS attempt
    error_log("SMS notification sent to $phone: $title");
}

function sendPushNotification($recipient_id, $title, $message) {
    global $conn;
    
    // Get recipient's push token
    $stmt = $conn->prepare("SELECT push_token FROM employee_devices WHERE employee_id = ? AND is_active = 1");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $tokens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($tokens as $token_row) {
        // Push notification logic would go here
        // This would integrate with FCM, APNS, etc.
        error_log("Push notification sent to token: " . $token_row['push_token']);
    }
}

function getStartDateForPeriod($period) {
    switch ($period) {
        case 'weekly':
            return date('Y-m-d', strtotime('-1 week'));
        case 'monthly':
            return date('Y-m-d', strtotime('-1 month'));
        case 'quarterly':
            return date('Y-m-d', strtotime('-3 months'));
        case 'yearly':
            return date('Y-m-d', strtotime('-1 year'));
        default:
            return date('Y-m-d', strtotime('-1 month'));
    }
}

// Background job for processing smart alerts
function processSmartAlerts() {
    global $conn;
    
    // Get all active smart alert configurations
    $stmt = $conn->prepare("SELECT * FROM smart_alert_config WHERE is_active = 1");
    $stmt->execute();
    $configs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($configs as $config) {
        $conditions = json_decode($config['condition_json'], true);
        $actions = json_decode($config['action_json'], true);
        
        // Evaluate conditions
        if (evaluateAlertConditions($conditions)) {
            // Execute actions
            executeAlertActions($config['user_id'], $actions);
        }
    }
}

function evaluateAlertConditions($conditions) {
    // Implement condition evaluation logic
    // This would check various metrics like attendance rates, leave patterns, etc.
    return false; // Placeholder
}

function executeAlertActions($user_id, $actions) {
    // Implement action execution logic
    // This would send notifications, create reports, etc.
}
?>
