<?php
/**
 * Advanced Notification System
 * Handles real-time notifications, email alerts, and SMS notifications
 * Supports multiple notification channels and templates
 */

require_once 'config.php';

class NotificationSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Send notification with multiple channels
     */
    public function sendNotification($employee_id, $title, $message, $type = 'info', $channels = ['web']) {
        try {
            $notification_id = $this->createWebNotification($employee_id, $title, $message, $type);
            
            if (!$notification_id) {
                return false;
            }
            
            // Get employee details
            $employee = $this->getEmployeeDetails($employee_id);
            if (!$employee) {
                error_log("Failed to get employee details for ID: $employee_id");
                return false; // Still return false but at least web notification was created
            }
            
            $success = true;
            
            // Send through requested channels
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $success &= $this->sendEmailNotification($employee, $title, $message, $type);
                        break;
                    case 'sms':
                        $success &= $this->sendSMSNotification($employee, $title, $message);
                        break;
                    case 'push':
                        $success &= $this->sendPushNotification($employee, $title, $message);
                        break;
                    case 'web':
                        // Already created above
                        break;
                }
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Notification system error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create web notification in database
     */
    private function createWebNotification($employee_id, $title, $message, $type) {
        $query = "INSERT INTO notifications (employee_id, title, message, type, is_read, created_at) 
                 VALUES (?, ?, ?, ?, 0, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("isss", $employee_id, $title, $message, $type);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        
        return false;
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($employee, $title, $message, $type) {
        if (empty($employee['email'])) {
            return false;
        }
        
        // For testing purposes, simulate email sending
        // In production, replace with actual mail() function or SMTP
        error_log("Simulated email to {$employee['email']}: $title - $message");
        return true; // Simulate successful email sending
        
        /* Actual email code - uncomment when email is configured
        $email_template = $this->getEmailTemplate($type);
        $email_body = str_replace(
            ['{{employee_name}}', '{{title}}', '{{message}}', '{{company_name}}'],
            [$employee['name'], $title, $message, 'BillBook Company'],
            $email_template
        );
        
        $headers = [
            'From: noreply@billbook.com',
            'Reply-To: hr@billbook.com',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($employee['email'], $title, $email_body, implode("\r\n", $headers));
        */
    }
    
    /**
     * Send SMS notification (integration with SMS gateway)
     */
    private function sendSMSNotification($employee, $title, $message) {
        if (empty($employee['phone'])) {
            return false;
        }
        
        // SMS template
        $sms_message = "[BillBook] {$title}: {$message}";
        
        // Example integration with SMS gateway (replace with your provider)
        $sms_data = [
            'phone' => $employee['phone'],
            'message' => $sms_message,
            'api_key' => 'your_sms_api_key'
        ];
        
        // Simulate SMS sending (replace with actual API call)
        $this->logNotificationAttempt('SMS', $employee['employee_id'], $sms_message);
        
        return true; // Return actual response from SMS gateway
    }
    
    /**
     * Send push notification (for mobile app)
     */
    private function sendPushNotification($employee, $title, $message) {
        // Get device tokens for employee
        $tokens = $this->getDeviceTokens($employee['employee_id']);
        
        if (empty($tokens)) {
            return false;
        }
        
        $success = true;
        
        foreach ($tokens as $token) {
            $push_data = [
                'to' => $token['device_token'],
                'title' => $title,
                'body' => $message,
                'data' => [
                    'type' => 'attendance_notification',
                    'employee_id' => $employee['employee_id'],
                    'timestamp' => time()
                ]
            ];
            
            // Send to Firebase Cloud Messaging or other push service
            $success &= $this->sendFCMNotification($push_data);
        }
        
        return $success;
    }
    
    /**
     * Get email template based on notification type
     */
    private function getEmailTemplate($type) {
        $templates = [
            'leave_approved' => '
                <html>
                <head><title>{{title}}</title></head>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background-color: #28a745; color: white; padding: 20px; text-align: center;">
                        <h2>{{company_name}}</h2>
                        <h3>Leave Request Approved</h3>
                    </div>
                    <div style="padding: 20px;">
                        <p>Dear {{employee_name}},</p>
                        <p>{{message}}</p>
                        <p>You can view the details in your attendance portal.</p>
                        <p>Best regards,<br>HR Team</p>
                    </div>
                    <div style="background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px;">
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </body>
                </html>',
            
            'leave_rejected' => '
                <html>
                <head><title>{{title}}</title></head>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background-color: #dc3545; color: white; padding: 20px; text-align: center;">
                        <h2>{{company_name}}</h2>
                        <h3>Leave Request Update</h3>
                    </div>
                    <div style="padding: 20px;">
                        <p>Dear {{employee_name}},</p>
                        <p>{{message}}</p>
                        <p>Please contact HR for more information or to discuss alternatives.</p>
                        <p>Best regards,<br>HR Team</p>
                    </div>
                    <div style="background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px;">
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </body>
                </html>',
            
            'attendance_reminder' => '
                <html>
                <head><title>{{title}}</title></head>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background-color: #ffc107; color: black; padding: 20px; text-align: center;">
                        <h2>{{company_name}}</h2>
                        <h3>Attendance Reminder</h3>
                    </div>
                    <div style="padding: 20px;">
                        <p>Dear {{employee_name}},</p>
                        <p>{{message}}</p>
                        <p>Please ensure to mark your attendance on time.</p>
                        <p>Best regards,<br>HR Team</p>
                    </div>
                    <div style="background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px;">
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </body>
                </html>',
            
            'default' => '
                <html>
                <head><title>{{title}}</title></head>
                <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background-color: #007bff; color: white; padding: 20px; text-align: center;">
                        <h2>{{company_name}}</h2>
                        <h3>{{title}}</h3>
                    </div>
                    <div style="padding: 20px;">
                        <p>Dear {{employee_name}},</p>
                        <p>{{message}}</p>
                        <p>Best regards,<br>HR Team</p>
                    </div>
                    <div style="background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px;">
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </body>
                </html>'
        ];
        
        return $templates[$type] ?? $templates['default'];
    }
    
    /**
     * Send FCM notification
     */
    private function sendFCMNotification($data) {
        $fcm_url = 'https://fcm.googleapis.com/fcm/send';
        $server_key = 'your_fcm_server_key'; // Replace with your FCM server key
        
        $headers = [
            'Authorization: key=' . $server_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fcm_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    /**
     * Get employee details
     */
    private function getEmployeeDetails($employee_id) {
        $query = "SELECT employee_id, name, phone FROM employees WHERE employee_id = ?";
        $stmt = $this->db->prepare($query);
        
        if (!$stmt) {
            error_log("Failed to prepare employee details query: " . $this->db->error);
            return null;
        }
        
        $stmt->bind_param("i", $employee_id);
        
        if (!$stmt->execute()) {
            error_log("Failed to execute employee details query: " . $stmt->error);
            return null;
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            error_log("Failed to get result from employee details query");
            return null;
        }
        
        $employee = $result->fetch_assoc();
        if ($employee) {
            // Add a default email if not present
            $employee['email'] = $employee['name'] . '@company.com'; // Placeholder email
        }
        
        return $employee;
    }
    
    /**
     * Get device tokens for push notifications
     */
    private function getDeviceTokens($employee_id) {
        $query = "SELECT device_token, platform FROM employee_devices 
                 WHERE employee_id = ? AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        
        $tokens = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row;
        }
        
        return $tokens;
    }
    
    /**
     * Log notification attempt
     */
    private function logNotificationAttempt($channel, $employee_id, $message) {
        $query = "INSERT INTO notification_log (employee_id, channel, message, sent_at) 
                 VALUES (?, ?, ?, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iss", $employee_id, $channel, $message);
        $stmt->execute();
    }
    
    /**
     * Send bulk notifications to multiple employees
     */
    public function sendBulkNotification($employee_ids, $title, $message, $type = 'info', $channels = ['web']) {
        $success_count = 0;
        
        foreach ($employee_ids as $employee_id) {
            if ($this->sendNotification($employee_id, $title, $message, $type, $channels)) {
                $success_count++;
            }
        }
        
        return [
            'total' => count($employee_ids),
            'success' => $success_count,
            'failed' => count($employee_ids) - $success_count
        ];
    }
    
    /**
     * Send notification to all employees in a department
     */
    public function sendDepartmentNotification($department, $title, $message, $type = 'info', $channels = ['web']) {
        $query = "SELECT employee_id FROM employees WHERE department = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $department);
        $stmt->execute();
        
        $employee_ids = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $employee_ids[] = $row['employee_id'];
        }
        
        return $this->sendBulkNotification($employee_ids, $title, $message, $type, $channels);
    }
    
    /**
     * Automated attendance reminder system
     */
    public function sendAttendanceReminders() {
        $current_time = date('H:i');
        $today = date('Y-m-d');
        
        // Get employees who haven't marked attendance yet
        $query = "SELECT e.employee_id, e.name, e.email, e.phone 
                 FROM employees e 
                 LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.attendance_date = ?
                 WHERE a.employee_id IS NULL AND e.status = 'Active'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reminded_count = 0;
        
        while ($employee = $result->fetch_assoc()) {
            $message = "You haven't marked your attendance for today. Please check in as soon as possible.";
            
            if ($this->sendNotification(
                $employee['employee_id'],
                'Attendance Reminder',
                $message,
                'attendance_reminder',
                ['web', 'email']
            )) {
                $reminded_count++;
            }
        }
        
        return $reminded_count;
    }
    
    /**
     * Get unread notifications for employee
     */
    public function getUnreadNotifications($employee_id) {
        $query = "SELECT * FROM notifications 
                 WHERE employee_id = ? AND is_read = 0 
                 ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        
        $notifications = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $employee_id) {
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() 
                 WHERE id = ? AND employee_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $notification_id, $employee_id);
        
        return $stmt->execute();
    }
    
    /**
     * Schedule notification for future delivery
     */
    public function scheduleNotification($employee_id, $title, $message, $schedule_time, $type = 'info', $channels = ['web']) {
        $query = "INSERT INTO scheduled_notifications 
                 (employee_id, title, message, type, channels, schedule_time, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($query);
        $channels_json = json_encode($channels);
        $stmt->bind_param("isssss", $employee_id, $title, $message, $type, $channels_json, $schedule_time);
        
        return $stmt->execute();
    }
    
    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications() {
        $current_time = date('Y-m-d H:i:s');
        
        $query = "SELECT * FROM scheduled_notifications 
                 WHERE schedule_time <= ? AND is_sent = 0";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $processed_count = 0;
        
        while ($notification = $result->fetch_assoc()) {
            $channels = json_decode($notification['channels'], true);
            
            if ($this->sendNotification(
                $notification['employee_id'],
                $notification['title'],
                $notification['message'],
                $notification['type'],
                $channels
            )) {
                // Mark as sent
                $update_query = "UPDATE scheduled_notifications 
                               SET is_sent = 1, sent_at = NOW() 
                               WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bind_param("i", $notification['id']);
                $update_stmt->execute();
                
                $processed_count++;
            }
        }
        
        return $processed_count;
    }
}

// Usage Examples:

/*
// Initialize notification system
$notificationSystem = new NotificationSystem($conn);

// Send single notification
$notificationSystem->sendNotification(
    1, // employee_id
    'Leave Approved',
    'Your leave request for Dec 25-27 has been approved.',
    'leave_approved',
    ['web', 'email', 'sms']
);

// Send bulk notification
$employee_ids = [1, 2, 3, 4, 5];
$result = $notificationSystem->sendBulkNotification(
    $employee_ids,
    'System Maintenance',
    'The attendance system will be under maintenance from 2-4 AM tomorrow.',
    'system_maintenance',
    ['web', 'email']
);

// Send department-wide notification
$notificationSystem->sendDepartmentNotification(
    'IT',
    'Team Meeting',
    'Monthly team meeting scheduled for tomorrow at 10 AM.',
    'meeting',
    ['web', 'email']
);

// Send attendance reminders
$reminded_count = $notificationSystem->sendAttendanceReminders();

// Schedule notification
$notificationSystem->scheduleNotification(
    1,
    'Deadline Reminder',
    'Your monthly report is due tomorrow.',
    '2024-12-25 09:00:00',
    'reminder',
    ['web', 'email']
);

// Process scheduled notifications (run via cron job)
$processed = $notificationSystem->processScheduledNotifications();
*/

?>
