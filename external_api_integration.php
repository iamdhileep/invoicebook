<?php
/**
 * External API Integration System
 * Handles integration with third-party systems like Slack, Microsoft Teams, 
 * payroll systems, biometric devices, and HR management systems
 */

require_once 'config.php';
require_once 'notification_system.php';

class ExternalAPIIntegration {
    private $db;
    private $notificationSystem;
    
    public function __construct($database) {
        $this->db = $database;
        $this->notificationSystem = new NotificationSystem($database);
    }
    
    /**
     * Slack Integration
     */
    public function sendSlackNotification($channel, $message, $attachment = null) {
        $slack_webhook = $this->getAPIConfig('slack_webhook_url');
        
        if (!$slack_webhook) {
            return false;
        }
        
        $payload = [
            'channel' => $channel,
            'text' => $message,
            'username' => 'BillBook Attendance Bot',
            'icon_emoji' => ':calendar:'
        ];
        
        if ($attachment) {
            $payload['attachments'] = [$attachment];
        }
        
        return $this->sendWebhook($slack_webhook, $payload);
    }
    
    /**
     * Microsoft Teams Integration
     */
    public function sendTeamsNotification($webhook_url, $title, $message, $color = '0078D4') {
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $title,
            'themeColor' => $color,
            'sections' => [
                [
                    'activityTitle' => $title,
                    'activitySubtitle' => 'BillBook Attendance System',
                    'activityImage' => 'https://your-domain.com/logo.png',
                    'text' => $message,
                    'markdown' => true
                ]
            ]
        ];
        
        return $this->sendWebhook($webhook_url, $payload);
    }
    
    /**
     * Payroll System Integration
     */
    public function syncAttendanceToPayroll($start_date, $end_date) {
        $payroll_api = $this->getAPIConfig('payroll_api_url');
        $api_key = $this->getAPIConfig('payroll_api_key');
        
        if (!$payroll_api || !$api_key) {
            return ['success' => false, 'error' => 'Payroll API not configured'];
        }
        
        // Get attendance data for the period
        $attendance_data = $this->getAttendanceForPeriod($start_date, $end_date);
        
        // Format data for payroll system
        $payroll_data = [];
        foreach ($attendance_data as $record) {
            $payroll_data[] = [
                'employee_id' => $record['employee_id'],
                'employee_code' => $record['employee_code'],
                'date' => $record['attendance_date'],
                'hours_worked' => $record['working_hours'],
                'overtime_hours' => max(0, $record['working_hours'] - 8),
                'status' => $record['status'],
                'late_minutes' => $record['late_minutes'] ?? 0
            ];
        }
        
        // Send to payroll system
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];
        
        $response = $this->sendAPIRequest(
            $payroll_api . '/attendance-sync',
            'POST',
            $payroll_data,
            $headers
        );
        
        // Log the sync attempt
        $this->logAPIActivity('payroll_sync', json_encode($response));
        
        return $response;
    }
    
    /**
     * Biometric Device Integration
     */
    public function syncBiometricData($device_ip, $device_port = 4370) {
        try {
            // Connect to biometric device (example for ZKTeco devices)
            $connection = $this->connectToBiometricDevice($device_ip, $device_port);
            
            if (!$connection) {
                throw new Exception('Failed to connect to biometric device');
            }
            
            // Get attendance records from device
            $device_records = $this->getBiometricRecords($connection);
            
            $synced_count = 0;
            foreach ($device_records as $record) {
                if ($this->processBiometricRecord($record)) {
                    $synced_count++;
                }
            }
            
            $this->closeBiometricConnection($connection);
            
            return [
                'success' => true,
                'synced_records' => $synced_count,
                'total_records' => count($device_records)
            ];
            
        } catch (Exception $e) {
            $this->logAPIActivity('biometric_sync_error', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * HR Management System Integration
     */
    public function syncEmployeeData($hr_system_type = 'bamboohr') {
        switch ($hr_system_type) {
            case 'bamboohr':
                return $this->syncBambooHRData();
            case 'workday':
                return $this->syncWorkdayData();
            case 'adp':
                return $this->syncADPData();
            default:
                return ['success' => false, 'error' => 'Unsupported HR system'];
        }
    }
    
    /**
     * Calendar Integration (Google Calendar, Outlook)
     */
    public function syncCalendarEvents($calendar_type = 'google') {
        switch ($calendar_type) {
            case 'google':
                return $this->syncGoogleCalendar();
            case 'outlook':
                return $this->syncOutlookCalendar();
            default:
                return ['success' => false, 'error' => 'Unsupported calendar type'];
        }
    }
    
    /**
     * Custom Webhook Integration
     */
    public function sendCustomWebhook($webhook_url, $event_type, $data) {
        $payload = [
            'event' => $event_type,
            'timestamp' => date('c'),
            'data' => $data,
            'source' => 'billbook_attendance'
        ];
        
        return $this->sendWebhook($webhook_url, $payload);
    }
    
    // Private Helper Methods
    
    private function sendWebhook($url, $payload) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: BillBook-Attendance/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'response' => $response
        ];
    }
    
    private function sendAPIRequest($url, $method, $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'data' => json_decode($response, true)
        ];
    }
    
    private function getAPIConfig($key) {
        $query = "SELECT config_value FROM api_configurations WHERE config_key = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $key);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['config_value'] ?? null;
    }
    
    private function getAttendanceForPeriod($start_date, $end_date) {
        $query = "SELECT a.*, e.employee_code, e.name 
                 FROM attendance a 
                 JOIN employees e ON a.employee_id = e.employee_id
                 WHERE a.attendance_date BETWEEN ? AND ?
                 ORDER BY a.attendance_date, e.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    private function connectToBiometricDevice($ip, $port) {
        // Placeholder for biometric device connection
        // This would use actual SDK or TCP connection
        return true; // Simulate successful connection
    }
    
    private function getBiometricRecords($connection) {
        // Placeholder for fetching records from biometric device
        // This would use actual device SDK
        return []; // Return array of attendance records
    }
    
    private function processBiometricRecord($record) {
        // Process individual biometric record
        // Convert device data to database format
        return true;
    }
    
    private function closeBiometricConnection($connection) {
        // Close connection to biometric device
        return true;
    }
    
    private function syncBambooHRData() {
        $api_key = $this->getAPIConfig('bamboohr_api_key');
        $subdomain = $this->getAPIConfig('bamboohr_subdomain');
        
        if (!$api_key || !$subdomain) {
            return ['success' => false, 'error' => 'BambooHR credentials not configured'];
        }
        
        $url = "https://api.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/directory";
        $headers = [
            'Authorization: Basic ' . base64_encode($api_key . ':x'),
            'Accept: application/json'
        ];
        
        $response = $this->sendAPIRequest($url, 'GET', null, $headers);
        
        if ($response['success']) {
            // Process employee data and update local database
            $this->updateEmployeesFromHR($response['data']['employees']);
        }
        
        return $response;
    }
    
    private function syncGoogleCalendar() {
        $access_token = $this->getAPIConfig('google_calendar_token');
        $calendar_id = $this->getAPIConfig('google_calendar_id');
        
        if (!$access_token || !$calendar_id) {
            return ['success' => false, 'error' => 'Google Calendar not configured'];
        }
        
        $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events";
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ];
        
        $response = $this->sendAPIRequest($url, 'GET', null, $headers);
        
        if ($response['success']) {
            // Process calendar events (holidays, meetings, etc.)
            $this->processCalendarEvents($response['data']['items']);
        }
        
        return $response;
    }
    
    private function updateEmployeesFromHR($employees) {
        foreach ($employees as $emp_data) {
            // Update or insert employee data
            $query = "UPDATE employees SET 
                        name = ?, email = ?, phone = ?, department = ?, position = ?
                      WHERE employee_code = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ssssss", 
                $emp_data['displayName'],
                $emp_data['workEmail'],
                $emp_data['mobilePhone'],
                $emp_data['division'],
                $emp_data['jobTitle'],
                $emp_data['employeeNumber']
            );
            
            $stmt->execute();
        }
    }
    
    private function processCalendarEvents($events) {
        foreach ($events as $event) {
            // Process calendar events for holidays or important dates
            if (strpos(strtolower($event['summary']), 'holiday') !== false) {
                $this->addHoliday($event['start']['date'], $event['summary']);
            }
        }
    }
    
    private function addHoliday($date, $description) {
        $query = "INSERT IGNORE INTO holidays (date, description, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $date, $description);
        $stmt->execute();
    }
    
    private function logAPIActivity($action, $details) {
        $query = "INSERT INTO api_activity_log (action, details, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $action, $details);
        $stmt->execute();
    }
    
    /**
     * Automated sync methods (to be called via cron jobs)
     */
    
    public function dailyAutomatedSync() {
        $results = [];
        
        // Sync biometric devices
        $biometric_devices = $this->getConfiguredBiometricDevices();
        foreach ($biometric_devices as $device) {
            $results['biometric'][] = $this->syncBiometricData($device['ip'], $device['port']);
        }
        
        // Send daily reports to configured channels
        $this->sendDailyReport();
        
        // Sync with HR system if configured
        $results['hr_sync'] = $this->syncEmployeeData();
        
        return $results;
    }
    
    public function weeklyAutomatedSync() {
        $results = [];
        
        // Sync attendance to payroll system
        $start_date = date('Y-m-d', strtotime('last Monday'));
        $end_date = date('Y-m-d', strtotime('last Sunday'));
        $results['payroll_sync'] = $this->syncAttendanceToPayroll($start_date, $end_date);
        
        // Generate and send weekly reports
        $this->sendWeeklyReport();
        
        return $results;
    }
    
    private function getConfiguredBiometricDevices() {
        $query = "SELECT * FROM biometric_devices WHERE is_active = 1";
        $result = $this->db->query($query);
        
        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
        
        return $devices;
    }
    
    private function sendDailyReport() {
        $today = date('Y-m-d');
        $report_data = $this->generateDailyReportData($today);
        
        // Send to Slack if configured
        $slack_channel = $this->getAPIConfig('daily_report_slack_channel');
        if ($slack_channel) {
            $message = $this->formatDailyReportForSlack($report_data);
            $this->sendSlackNotification($slack_channel, $message);
        }
        
        // Send to Teams if configured
        $teams_webhook = $this->getAPIConfig('daily_report_teams_webhook');
        if ($teams_webhook) {
            $message = $this->formatDailyReportForTeams($report_data);
            $this->sendTeamsNotification($teams_webhook, 'Daily Attendance Report', $message);
        }
    }
    
    private function generateDailyReportData($date) {
        $query = "SELECT 
                    COUNT(*) as total_employees,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as on_leave,
                    AVG(working_hours) as avg_working_hours
                  FROM attendance 
                  WHERE attendance_date = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function formatDailyReportForSlack($data) {
        return "📊 *Daily Attendance Report - " . date('F d, Y') . "*\n" .
               "👥 Total Employees: {$data['total_employees']}\n" .
               "✅ Present: {$data['present']}\n" .
               "❌ Absent: {$data['absent']}\n" .
               "🏖️ On Leave: {$data['on_leave']}\n" .
               "⏰ Average Working Hours: " . round($data['avg_working_hours'], 2) . " hrs";
    }
    
    private function formatDailyReportForTeams($data) {
        return "**Total Employees:** {$data['total_employees']}\n\n" .
               "**Present:** {$data['present']}\n\n" .
               "**Absent:** {$data['absent']}\n\n" .
               "**On Leave:** {$data['on_leave']}\n\n" .
               "**Average Working Hours:** " . round($data['avg_working_hours'], 2) . " hours";
    }
}

// Usage Examples and Automation Scripts:

/*
// Initialize API integration
$apiIntegration = new ExternalAPIIntegration($conn);

// Send Slack notification
$apiIntegration->sendSlackNotification(
    '#hr-notifications',
    'New employee John Doe has been added to the system.'
);

// Sync with payroll system
$result = $apiIntegration->syncAttendanceToPayroll('2024-12-01', '2024-12-07');

// Sync biometric data
$biometric_result = $apiIntegration->syncBiometricData('192.168.1.100');

// Send custom webhook
$apiIntegration->sendCustomWebhook(
    'https://your-app.com/webhook/attendance',
    'employee_checked_in',
    ['employee_id' => 123, 'time' => '09:00:00']
);

// Daily automated sync (call via cron job)
$daily_results = $apiIntegration->dailyAutomatedSync();

// Weekly automated sync (call via cron job)
$weekly_results = $apiIntegration->weeklyAutomatedSync();
*/

?>
