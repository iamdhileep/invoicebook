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
    // Mobile App Integration
    case 'sync_mobile_data':
        syncMobileData();
        break;
    case 'get_mobile_settings':
        getMobileSettings();
        break;
    case 'register_device':
        registerDevice();
        break;
    
    // Advanced Security
    case 'configure_ip_restrictions':
        configureIPRestrictions();
        break;
    case 'get_security_logs':
        getSecurityLogs();
        break;
    case 'enable_2fa':
        enable2FA();
        break;
    
    // Custom Fields & Integration
    case 'manage_custom_fields':
        manageCustomFields();
        break;
    case 'sync_external_data':
        syncExternalData();
        break;
    case 'configure_webhooks':
        configureWebhooks();
        break;
    
    // Compliance & Audit
    case 'generate_compliance_report':
        generateComplianceReport();
        break;
    case 'export_audit_trail':
        exportAuditTrail();
        break;
    case 'configure_retention_policy':
        configureRetentionPolicy();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function syncMobileData() {
    global $conn;
    
    $device_id = $_POST['device_id'] ?? '';
    $sync_data = json_decode($_POST['sync_data'] ?? '{}', true);
    $last_sync = $_POST['last_sync'] ?? '1970-01-01 00:00:00';
    
    if (empty($device_id) || empty($sync_data)) {
        echo json_encode(['success' => false, 'message' => 'Device ID and sync data required']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $sync_results = [];
        
        // Sync attendance records
        if (isset($sync_data['attendance'])) {
            foreach ($sync_data['attendance'] as $attendance_record) {
                $stmt = $conn->prepare("
                    INSERT INTO attendance 
                    (employee_id, date, punch_in, punch_out, status, location_name, device_info, sync_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'synced')
                    ON DUPLICATE KEY UPDATE
                    punch_out = VALUES(punch_out),
                    status = VALUES(status),
                    location_name = VALUES(location_name),
                    sync_status = 'synced'
                ");
                
                $stmt->bind_param("issssss",
                    $attendance_record['employee_id'],
                    $attendance_record['date'],
                    $attendance_record['punch_in'],
                    $attendance_record['punch_out'],
                    $attendance_record['status'],
                    $attendance_record['location_name'],
                    $device_id
                );
                $stmt->execute();
            }
            $sync_results['attendance_synced'] = count($sync_data['attendance']);
        }
        
        // Get updates from server
        $stmt = $conn->prepare("
            SELECT * FROM attendance 
            WHERE updated_at > ? AND device_info != ?
            ORDER BY updated_at DESC
            LIMIT 100
        ");
        $stmt->bind_param("ss", $last_sync, $device_id);
        $stmt->execute();
        $server_updates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Update sync timestamp
        $stmt = $conn->prepare("
            UPDATE employee_devices 
            SET last_sync = NOW(), sync_count = sync_count + 1
            WHERE device_id = ?
        ");
        $stmt->bind_param("s", $device_id);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'sync_results' => $sync_results,
            'server_updates' => $server_updates,
            'sync_timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()]);
    }
}

function getMobileSettings() {
    global $conn;
    
    $employee_id = intval($_GET['employee_id'] ?? 0);
    
    if (!$employee_id) {
        echo json_encode(['success' => false, 'message' => 'Employee ID required']);
        return;
    }
    
    // Get employee mobile settings
    $stmt = $conn->prepare("
        SELECT * FROM mobile_app_settings 
        WHERE employee_id = ?
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    
    // Get default settings if none exist
    if (!$settings) {
        $settings = [
            'notifications_enabled' => true,
            'location_tracking' => true,
            'offline_mode' => true,
            'biometric_login' => false,
            'auto_sync' => true,
            'sync_frequency' => 30 // minutes
        ];
    } else {
        $settings = json_decode($settings['settings_json'] ?? '{}', true);
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function registerDevice() {
    global $conn;
    
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $device_id = $_POST['device_id'] ?? '';
    $device_name = $_POST['device_name'] ?? '';
    $device_type = $_POST['device_type'] ?? 'mobile';
    $push_token = $_POST['push_token'] ?? '';
    $device_info = json_encode($_POST['device_info'] ?? []);
    
    if (!$employee_id || !$device_id) {
        echo json_encode(['success' => false, 'message' => 'Employee ID and Device ID required']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO employee_devices 
        (employee_id, device_id, device_name, device_type, push_token, device_info, registered_at, is_active)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
        ON DUPLICATE KEY UPDATE
        device_name = VALUES(device_name),
        push_token = VALUES(push_token),
        device_info = VALUES(device_info),
        last_active = NOW(),
        is_active = 1
    ");
    
    $stmt->bind_param("isssss", $employee_id, $device_id, $device_name, $device_type, $push_token, $device_info);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'device_id' => $device_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to register device']);
    }
}

function configureIPRestrictions() {
    global $conn;
    
    $restrictions = json_decode($_POST['restrictions'] ?? '[]', true);
    $company_id = intval($_POST['company_id'] ?? 1);
    
    if (empty($restrictions)) {
        echo json_encode(['success' => false, 'message' => 'No restrictions provided']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Clear existing restrictions
        $stmt = $conn->prepare("DELETE FROM ip_restrictions WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        
        // Insert new restrictions
        $stmt = $conn->prepare("
            INSERT INTO ip_restrictions 
            (company_id, ip_address, ip_range, rule_type, description, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($restrictions as $restriction) {
            $stmt->bind_param("issss",
                $company_id,
                $restriction['ip_address'] ?? null,
                $restriction['ip_range'] ?? null,
                $restriction['rule_type'], // 'allow' or 'deny'
                $restriction['description'] ?? ''
            );
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'IP restrictions configured successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to configure restrictions: ' . $e->getMessage()]);
    }
}

function getSecurityLogs() {
    global $conn;
    
    $log_type = $_GET['log_type'] ?? 'all';
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $limit = intval($_GET['limit'] ?? 100);
    
    $where_conditions = ["log_timestamp BETWEEN ? AND ?"];
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    $param_types = "ss";
    
    if ($log_type !== 'all') {
        $where_conditions[] = "log_type = ?";
        $params[] = $log_type;
        $param_types .= "s";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT sl.*, e.name as employee_name
        FROM security_logs sl
        LEFT JOIN employees e ON sl.employee_id = e.employee_id
        WHERE $where_clause
        ORDER BY sl.log_timestamp DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $param_types .= "i";
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total_count' => count($logs)
    ]);
}

function enable2FA() {
    global $conn;
    
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $method = $_POST['method'] ?? 'app'; // 'app', 'sms', 'email'
    $contact_info = $_POST['contact_info'] ?? '';
    
    if (!$employee_id) {
        echo json_encode(['success' => false, 'message' => 'Employee ID required']);
        return;
    }
    
    // Generate secret key for TOTP
    $secret_key = generateTOTPSecret();
    
    $stmt = $conn->prepare("
        INSERT INTO two_factor_auth 
        (employee_id, auth_method, secret_key, contact_info, is_enabled, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
        auth_method = VALUES(auth_method),
        secret_key = VALUES(secret_key),
        contact_info = VALUES(contact_info),
        is_enabled = 1
    ");
    
    $stmt->bind_param("isss", $employee_id, $method, $secret_key, $contact_info);
    
    if ($stmt->execute()) {
        // Generate QR code for app-based 2FA
        $qr_code_url = '';
        if ($method === 'app') {
            $qr_code_url = generateTOTPQRCode($employee_id, $secret_key);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '2FA enabled successfully',
            'secret_key' => $secret_key,
            'qr_code_url' => $qr_code_url
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to enable 2FA']);
    }
}

function manageCustomFields() {
    global $conn;
    
    $field_action = $_POST['field_action'] ?? 'get';
    $table_name = $_POST['table_name'] ?? 'employees';
    
    switch ($field_action) {
        case 'add':
            $field_name = $_POST['field_name'] ?? '';
            $field_type = $_POST['field_type'] ?? 'text';
            $field_options = json_encode($_POST['field_options'] ?? []);
            
            $stmt = $conn->prepare("
                INSERT INTO custom_fields 
                (table_name, field_name, field_type, field_options, is_required, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->bind_param("ssss", $table_name, $field_name, $field_type, $field_options);
            
            if ($stmt->execute()) {
                // Add column to actual table
                addCustomColumn($table_name, $field_name, $field_type);
                echo json_encode(['success' => true, 'message' => 'Custom field added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add custom field']);
            }
            break;
            
        case 'get':
            $stmt = $conn->prepare("SELECT * FROM custom_fields WHERE table_name = ? ORDER BY created_at");
            $stmt->bind_param("s", $table_name);
            $stmt->execute();
            $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'custom_fields' => $fields]);
            break;
    }
}

function configureWebhooks() {
    global $conn;
    
    $webhook_config = json_decode($_POST['webhook_config'] ?? '{}', true);
    
    if (empty($webhook_config)) {
        echo json_encode(['success' => false, 'message' => 'Webhook configuration required']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO webhook_configurations 
        (webhook_url, event_types, headers, authentication, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->bind_param("ssss",
        $webhook_config['url'],
        json_encode($webhook_config['events'] ?? []),
        json_encode($webhook_config['headers'] ?? []),
        json_encode($webhook_config['auth'] ?? [])
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Webhook configured successfully',
            'webhook_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to configure webhook']);
    }
}

function generateComplianceReport() {
    global $conn;
    
    $report_type = $_POST['report_type'] ?? 'gdpr_compliance';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    
    $compliance_data = [];
    
    switch ($report_type) {
        case 'gdpr_compliance':
            // Data access logs
            $stmt = $conn->prepare("
                SELECT 
                    'data_access' as activity_type,
                    employee_id,
                    accessed_data,
                    access_timestamp,
                    access_purpose
                FROM data_access_logs
                WHERE access_timestamp BETWEEN ? AND ?
                ORDER BY access_timestamp DESC
            ");
            $stmt->bind_param("ss", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
            $stmt->execute();
            $compliance_data['data_access'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Data retention compliance
            $compliance_data['retention_status'] = checkDataRetentionCompliance();
            break;
            
        case 'attendance_compliance':
            // Working hours compliance
            $stmt = $conn->prepare("
                SELECT 
                    e.employee_id,
                    e.name,
                    COUNT(*) as total_days,
                    SUM(a.hours_worked) as total_hours,
                    AVG(a.hours_worked) as avg_daily_hours,
                    SUM(a.overtime_hours) as total_overtime
                FROM attendance a
                JOIN employees e ON a.employee_id = e.employee_id
                WHERE a.date BETWEEN ? AND ?
                GROUP BY e.employee_id
                HAVING total_overtime > 20 OR avg_daily_hours > 10
                ORDER BY total_overtime DESC
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $compliance_data['overtime_violations'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;
    }
    
    echo json_encode([
        'success' => true,
        'compliance_data' => $compliance_data,
        'report_type' => $report_type,
        'period' => "$start_date to $end_date",
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

// Helper functions
function generateTOTPSecret() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $secret;
}

function generateTOTPQRCode($employee_id, $secret) {
    // Generate QR code URL for Google Authenticator
    $issuer = 'BillBook Attendance';
    $account = "employee_$employee_id";
    
    $qr_url = "otpauth://totp/$issuer:$account?secret=$secret&issuer=$issuer";
    
    // Return URL to generate QR code (you'd use a QR code library in production)
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_url);
}

function addCustomColumn($table_name, $field_name, $field_type) {
    global $conn;
    
    $sql_type = match($field_type) {
        'text' => 'VARCHAR(255)',
        'number' => 'DECIMAL(10,2)',
        'date' => 'DATE',
        'boolean' => 'BOOLEAN',
        'textarea' => 'TEXT',
        default => 'VARCHAR(255)'
    };
    
    $safe_field_name = preg_replace('/[^a-zA-Z0-9_]/', '', $field_name);
    $safe_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
    
    $alter_sql = "ALTER TABLE `$safe_table_name` ADD COLUMN `custom_$safe_field_name` $sql_type NULL";
    
    try {
        $conn->query($alter_sql);
    } catch (Exception $e) {
        error_log("Failed to add custom column: " . $e->getMessage());
    }
}

function checkDataRetentionCompliance() {
    global $conn;
    
    $retention_status = [];
    
    // Check for data older than retention period
    $stmt = $conn->prepare("
        SELECT 
            'attendance' as data_type,
            COUNT(*) as records_count,
            MIN(date) as oldest_record,
            MAX(date) as newest_record
        FROM attendance
        WHERE date < DATE_SUB(CURDATE(), INTERVAL 7 YEAR)
    ");
    $stmt->execute();
    $retention_status['old_attendance'] = $stmt->get_result()->fetch_assoc();
    
    return $retention_status;
}

function syncExternalData() {
    global $conn;
    
    $system_type = $_POST['system_type'] ?? '';
    $sync_config = json_decode($_POST['sync_config'] ?? '{}', true);
    
    if (empty($system_type) || empty($sync_config)) {
        echo json_encode(['success' => false, 'message' => 'System type and configuration required']);
        return;
    }
    
    $sync_results = [];
    
    switch ($system_type) {
        case 'hr_system':
            $sync_results = syncWithHRSystem($sync_config);
            break;
        case 'payroll_system':
            $sync_results = syncWithPayrollSystem($sync_config);
            break;
        case 'erp_system':
            $sync_results = syncWithERPSystem($sync_config);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported system type']);
            return;
    }
    
    echo json_encode([
        'success' => true,
        'sync_results' => $sync_results,
        'system_type' => $system_type
    ]);
}

function syncWithHRSystem($config) {
    // Placeholder for HR system integration
    return ['employees_synced' => 0, 'departments_synced' => 0];
}

function syncWithPayrollSystem($config) {
    // Placeholder for payroll system integration  
    return ['attendance_records_sent' => 0, 'payroll_data_received' => 0];
}

function syncWithERPSystem($config) {
    // Placeholder for ERP system integration
    return ['data_exchanged' => 0];
}

function exportAuditTrail() {
    global $conn;
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $format = $_GET['format'] ?? 'csv';
    
    $stmt = $conn->prepare("
        SELECT 
            al.*,
            e.name as changed_by_name
        FROM audit_logs al
        LEFT JOIN employees e ON al.changed_by = e.employee_id
        WHERE al.changed_at BETWEEN ? AND ?
        ORDER BY al.changed_at DESC
    ");
    
    $stmt->bind_param("ss", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
    $stmt->execute();
    $audit_trail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_trail_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($audit_trail)) {
            fputcsv($output, array_keys($audit_trail[0]));
            foreach ($audit_trail as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    } else {
        echo json_encode([
            'success' => true,
            'audit_trail' => $audit_trail,
            'total_records' => count($audit_trail)
        ]);
    }
}

function configureRetentionPolicy() {
    global $conn;
    
    $policies = json_decode($_POST['policies'] ?? '[]', true);
    
    if (empty($policies)) {
        echo json_encode(['success' => false, 'message' => 'No policies provided']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Clear existing policies
        $stmt = $conn->prepare("DELETE FROM data_retention_policies");
        $stmt->execute();
        
        // Insert new policies
        $stmt = $conn->prepare("
            INSERT INTO data_retention_policies 
            (data_type, retention_period_months, auto_delete, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($policies as $policy) {
            $stmt->bind_param("sii",
                $policy['data_type'],
                $policy['retention_period'],
                $policy['auto_delete'] ? 1 : 0
            );
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Retention policies configured successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to configure policies: ' . $e->getMessage()]);
    }
}

?>
