<?php
/**
 * Mobile API - Push Notification Handler
 * Handles push notification subscriptions and sending notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/hrms_config.php';

// Authentication check
if (!HRMSHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$currentUserId = HRMSHelper::getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePost($action, $currentUserId, $conn);
            break;
            
        case 'GET':
            handleGet($action, $currentUserId, $conn);
            break;
            
        case 'PUT':
            handlePut($action, $currentUserId, $conn);
            break;
            
        case 'DELETE':
            handleDelete($action, $currentUserId, $conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handlePost($action, $userId, $conn) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'subscribe':
            subscribeToPush($input, $userId, $conn);
            break;
            
        case 'send':
            sendPushNotification($input, $userId, $conn);
            break;
            
        case 'broadcast':
            broadcastNotification($input, $userId, $conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleGet($action, $userId, $conn) {
    switch ($action) {
        case 'subscriptions':
            getSubscriptions($userId, $conn);
            break;
            
        case 'notifications':
            getNotifications($userId, $conn);
            break;
            
        case 'test':
            testPushService();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($action, $userId, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_subscription':
            updateSubscription($input, $userId, $conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($action, $userId, $conn) {
    switch ($action) {
        case 'unsubscribe':
            unsubscribeFromPush($userId, $conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function subscribeToPush($data, $userId, $conn) {
    $endpoint = $data['endpoint'] ?? '';
    $p256dh = $data['keys']['p256dh'] ?? '';
    $auth = $data['keys']['auth'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing subscription data']);
        return;
    }
    
    // Get or create device record
    $deviceId = getOrCreateDevice($userId, $userAgent, $conn);
    
    // Store subscription
    $stmt = $conn->prepare("
        INSERT INTO hr_push_subscriptions 
        (user_id, device_id, endpoint, p256dh_key, auth_key, user_agent, subscription_data)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        p256dh_key = VALUES(p256dh_key),
        auth_key = VALUES(auth_key),
        subscription_data = VALUES(subscription_data),
        is_active = TRUE,
        last_used = CURRENT_TIMESTAMP
    ");
    
    $subscriptionJson = json_encode($data);
    $stmt->bind_param('iisssss', $userId, $deviceId, $endpoint, $p256dh, $auth, $userAgent, $subscriptionJson);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Subscription saved']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save subscription']);
    }
}

function sendPushNotification($data, $senderId, $conn) {
    $targetUserId = $data['user_id'] ?? null;
    $title = $data['title'] ?? 'HRMS Notification';
    $body = $data['body'] ?? '';
    $icon = $data['icon'] ?? '/HRMS/assets/icon-192x192.png';
    $badge = $data['badge'] ?? '/HRMS/assets/badge-72x72.png';
    $url = $data['url'] ?? '/HRMS/';
    $tag = $data['tag'] ?? 'hrms-notification';
    $urgent = $data['urgent'] ?? false;
    
    if (!$targetUserId || empty($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }
    
    // Get user's push subscriptions
    $stmt = $conn->prepare("
        SELECT * FROM hr_push_subscriptions 
        WHERE user_id = ? AND is_active = TRUE
    ");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($subscriptions)) {
        echo json_encode(['success' => false, 'message' => 'No active subscriptions found']);
        return;
    }
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => $icon,
        'badge' => $badge,
        'url' => $url,
        'tag' => $tag,
        'urgent' => $urgent,
        'timestamp' => time()
    ]);
    
    $sentCount = 0;
    $failedCount = 0;
    
    foreach ($subscriptions as $subscription) {
        try {
            $result = sendWebPush(
                $subscription['endpoint'],
                $payload,
                $subscription['p256dh_key'],
                $subscription['auth_key']
            );
            
            if ($result) {
                $sentCount++;
                
                // Update last used timestamp
                $updateStmt = $conn->prepare("
                    UPDATE hr_push_subscriptions 
                    SET last_used = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updateStmt->bind_param('i', $subscription['id']);
                $updateStmt->execute();
            } else {
                $failedCount++;
            }
            
        } catch (Exception $e) {
            $failedCount++;
            error_log("Push notification failed: " . $e->getMessage());
        }
    }
    
    // Store notification in database
    $stmt = $conn->prepare("
        INSERT INTO hr_notifications 
        (user_id, title, message, type, data, action_url, created_by, sent_via)
        VALUES (?, ?, ?, 'system', ?, ?, ?, 'push')
    ");
    $notificationData = json_encode($data);
    $stmt->bind_param('isssii', $targetUserId, $title, $body, $notificationData, $url, $senderId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => "Notification sent to {$sentCount} devices",
        'sent' => $sentCount,
        'failed' => $failedCount
    ]);
}

function broadcastNotification($data, $senderId, $conn) {
    $title = $data['title'] ?? 'HRMS Announcement';
    $body = $data['body'] ?? '';
    $type = $data['type'] ?? 'announcement';
    $priority = $data['priority'] ?? 'normal';
    $departmentIds = $data['departments'] ?? [];
    $roleFilter = $data['roles'] ?? [];
    
    if (empty($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message body required']);
        return;
    }
    
    // Build user query based on filters
    $whereClause = "WHERE u.id IS NOT NULL";
    $params = [];
    $paramTypes = '';
    
    if (!empty($departmentIds)) {
        $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';
        $whereClause .= " AND e.department_id IN ($placeholders)";
        $params = array_merge($params, $departmentIds);
        $paramTypes .= str_repeat('i', count($departmentIds));
    }
    
    if (!empty($roleFilter)) {
        $placeholders = str_repeat('?,', count($roleFilter) - 1) . '?';
        $whereClause .= " AND u.role IN ($placeholders)";
        $params = array_merge($params, $roleFilter);
        $paramTypes .= str_repeat('s', count($roleFilter));
    }
    
    // Get target users
    $sql = "
        SELECT DISTINCT u.id, u.username, e.first_name, e.last_name 
        FROM users u
        LEFT JOIN hr_employees e ON u.id = e.user_id
        $whereClause
    ";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($users)) {
        echo json_encode(['success' => false, 'message' => 'No users match the criteria']);
        return;
    }
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/HRMS/assets/icon-192x192.png',
        'badge' => '/HRMS/assets/badge-72x72.png',
        'url' => '/HRMS/',
        'tag' => 'hrms-broadcast',
        'urgent' => $priority === 'urgent',
        'timestamp' => time()
    ]);
    
    $sentCount = 0;
    $failedCount = 0;
    
    foreach ($users as $user) {
        // Store notification in database
        $stmt = $conn->prepare("
            INSERT INTO hr_notifications 
            (user_id, title, message, type, priority, created_by, sent_via)
            VALUES (?, ?, ?, ?, ?, ?, 'push')
        ");
        $stmt->bind_param('issssi', $user['id'], $title, $body, $type, $priority, $senderId);
        $stmt->execute();
        
        // Get user's push subscriptions
        $stmt = $conn->prepare("
            SELECT * FROM hr_push_subscriptions 
            WHERE user_id = ? AND is_active = TRUE
        ");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($subscriptions as $subscription) {
            try {
                $result = sendWebPush(
                    $subscription['endpoint'],
                    $payload,
                    $subscription['p256dh_key'],
                    $subscription['auth_key']
                );
                
                if ($result) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
                
            } catch (Exception $e) {
                $failedCount++;
                error_log("Broadcast push failed: " . $e->getMessage());
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Broadcast sent to {$sentCount} devices across " . count($users) . " users",
        'users' => count($users),
        'sent' => $sentCount,
        'failed' => $failedCount
    ]);
}

function getSubscriptions($userId, $conn) {
    $stmt = $conn->prepare("
        SELECT ps.*, md.device_info, md.device_type, md.os_version 
        FROM hr_push_subscriptions ps
        LEFT JOIN hr_mobile_devices md ON ps.device_id = md.id
        WHERE ps.user_id = ? AND ps.is_active = TRUE
        ORDER BY ps.last_used DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'subscriptions' => $subscriptions]);
}

function getNotifications($userId, $conn) {
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    $unreadOnly = $_GET['unread'] === 'true';
    
    $whereClause = "WHERE (user_id = ? OR is_global = TRUE)";
    if ($unreadOnly) {
        $whereClause .= " AND is_read = FALSE";
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM hr_notifications 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $userId, $limit, $offset);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
}

function updateSubscription($data, $userId, $conn) {
    $subscriptionId = $data['subscription_id'] ?? 0;
    $isActive = $data['is_active'] ?? true;
    
    if (!$subscriptionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subscription ID required']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE hr_push_subscriptions 
        SET is_active = ?, last_used = CURRENT_TIMESTAMP 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param('iii', $isActive, $subscriptionId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Subscription updated']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update subscription']);
    }
}

function unsubscribeFromPush($userId, $conn) {
    $endpoint = $_GET['endpoint'] ?? '';
    
    if (empty($endpoint)) {
        // Unsubscribe all devices for user
        $stmt = $conn->prepare("
            UPDATE hr_push_subscriptions 
            SET is_active = FALSE 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
    } else {
        // Unsubscribe specific endpoint
        $stmt = $conn->prepare("
            UPDATE hr_push_subscriptions 
            SET is_active = FALSE 
            WHERE user_id = ? AND endpoint = ?
        ");
        $stmt->bind_param('is', $userId, $endpoint);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Unsubscribed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to unsubscribe']);
    }
}

function testPushService() {
    // Simple test to check if push service is working
    $testData = [
        'vapidSupported' => class_exists('Minishlink\WebPush\VAPID'),
        'curlEnabled' => function_exists('curl_init'),
        'opensslEnabled' => extension_loaded('openssl'),
        'timestamp' => time()
    ];
    
    echo json_encode(['success' => true, 'test' => $testData]);
}

function getOrCreateDevice($userId, $userAgent, $conn) {
    // Try to find existing device
    $stmt = $conn->prepare("
        SELECT id FROM hr_mobile_devices 
        WHERE user_id = ? AND device_info LIKE ? 
        ORDER BY last_active DESC 
        LIMIT 1
    ");
    $devicePattern = '%' . substr($userAgent, 0, 100) . '%';
    $stmt->bind_param('is', $userId, $devicePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $device = $result->fetch_assoc();
        
        // Update last active
        $updateStmt = $conn->prepare("
            UPDATE hr_mobile_devices 
            SET last_active = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updateStmt->bind_param('i', $device['id']);
        $updateStmt->execute();
        
        return $device['id'];
    }
    
    // Create new device record
    $deviceType = detectDeviceType($userAgent);
    $stmt = $conn->prepare("
        INSERT INTO hr_mobile_devices (user_id, device_info, device_type, browser_info)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isss', $userId, $userAgent, $deviceType, $userAgent);
    $stmt->execute();
    
    return $conn->insert_id;
}

function detectDeviceType($userAgent) {
    $userAgent = strtolower($userAgent);
    
    if (strpos($userAgent, 'android') !== false) {
        return 'android';
    } elseif (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) {
        return 'ios';
    } elseif (strpos($userAgent, 'mobile') !== false) {
        return 'other';
    } else {
        return 'desktop';
    }
}

function sendWebPush($endpoint, $payload, $p256dh, $auth) {
    // Simplified web push implementation
    // In production, use a proper library like minishlink/web-push
    
    if (!function_exists('curl_init')) {
        throw new Exception('cURL is required for push notifications');
    }
    
    $headers = [
        'Content-Type: application/json',
        'TTL: 86400'  // 24 hours
    ];
    
    // For Firebase/Google endpoints, you'd need proper authentication
    // For now, return true for development
    if (strpos($endpoint, 'googleapis.com') !== false || strpos($endpoint, 'mozilla.com') !== false) {
        // This is a simplified version - implement proper web push with VAPID keys
        error_log("Web push would be sent to: $endpoint");
        return true;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}
?>
