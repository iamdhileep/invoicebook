<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_all_settings':
        echo json_encode(getAllSettings($conn));
        break;
    case 'update_setting':
        echo json_encode(updateSetting($conn, $_POST));
        break;
    case 'bulk_update_settings':
        echo json_encode(bulkUpdateSettings($conn, $_POST));
        break;
    case 'reset_settings':
        echo json_encode(resetSettings($conn, $_POST));
        break;
    case 'export_settings':
        echo json_encode(exportSettings($conn));
        break;
    case 'import_settings':
        echo json_encode(importSettings($conn, $_POST));
        break;
    case 'get_logs':
        echo json_encode(getSettingsLogs($conn));
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAllSettings($conn) {
    try {
        $settings = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        return ['success' => true, 'data' => $settings];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateSetting($conn, $data) {
    try {
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? '';
        
        if (empty($key)) {
            throw new Exception('Setting key is required');
        }
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param('sss', $key, $value, $value);
        
        if ($stmt->execute()) {
            // Log the change
            logSettingChange($conn, $key, $value);
            return ['success' => true, 'message' => 'Setting updated successfully'];
        } else {
            throw new Exception('Failed to update setting');
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkUpdateSettings($conn, $data) {
    try {
        $settings = $data['settings'] ?? [];
        $updated = 0;
        
        $conn->autocommit(false);
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            
            if ($stmt->execute()) {
                $updated++;
                logSettingChange($conn, $key, $value);
            }
        }
        
        $conn->commit();
        $conn->autocommit(true);
        
        return ['success' => true, 'message' => "$updated settings updated successfully"];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function resetSettings($conn, $data) {
    try {
        $keys = $data['keys'] ?? [];
        
        if (empty($keys)) {
            throw new Exception('No settings specified for reset');
        }
        
        // Default values for reset
        $defaults = [
            'site_name' => 'BillBook',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'language' => 'en',
            'items_per_page' => '25',
            'maintenance_mode' => '0',
            'session_timeout' => '30',
            'max_login_attempts' => '5',
            'lockout_duration' => '15',
            'password_min_length' => '8',
            'auto_backup_enabled' => '0',
            'backup_frequency' => 'daily',
            'backup_time' => '02:00',
            'backup_retention_days' => '30'
        ];
        
        $reset = 0;
        foreach ($keys as $key) {
            if (isset($defaults[$key])) {
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param('ss', $defaults[$key], $key);
                
                if ($stmt->execute()) {
                    $reset++;
                    logSettingChange($conn, $key, $defaults[$key], 'reset');
                }
            }
        }
        
        return ['success' => true, 'message' => "$reset settings reset to defaults"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function exportSettings($conn) {
    try {
        $settings = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        $export_data = [
            'export_date' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'settings' => $settings
        ];
        
        return [
            'success' => true,
            'data' => $export_data,
            'filename' => 'billbook_settings_' . date('Y-m-d_H-i-s') . '.json'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function importSettings($conn, $data) {
    try {
        $import_data = json_decode($data['json_data'] ?? '', true);
        
        if (!$import_data || !isset($import_data['settings'])) {
            throw new Exception('Invalid import data');
        }
        
        $settings = $import_data['settings'];
        $imported = 0;
        
        $conn->autocommit(false);
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $value, $value);
            
            if ($stmt->execute()) {
                $imported++;
                logSettingChange($conn, $key, $value, 'import');
            }
        }
        
        $conn->commit();
        $conn->autocommit(true);
        
        return ['success' => true, 'message' => "$imported settings imported successfully"];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getSettingsLogs($conn) {
    try {
        $logs = [];
        
        // Check if settings_log table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'settings_log'");
        if ($table_check->num_rows === 0) {
            // Create settings_log table
            $create_table = "
                CREATE TABLE settings_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL,
                    old_value TEXT,
                    new_value TEXT,
                    action VARCHAR(50) DEFAULT 'update',
                    changed_by INT,
                    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_setting_key (setting_key),
                    INDEX idx_changed_at (changed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $conn->query($create_table);
        }
        
        $result = $conn->query("SELECT * FROM settings_log ORDER BY changed_at DESC LIMIT 50");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        
        return ['success' => true, 'data' => $logs];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function logSettingChange($conn, $key, $new_value, $action = 'update') {
    try {
        // Get current value
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_value = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : null;
        
        // Check if settings_log table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'settings_log'");
        if ($table_check->num_rows === 0) {
            return; // Skip logging if table doesn't exist
        }
        
        $user_id = $_SESSION['user_id'] ?? $_SESSION['admin'] ?? null;
        
        $log_stmt = $conn->prepare("INSERT INTO settings_log (setting_key, old_value, new_value, action, changed_by) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->bind_param('ssssi', $key, $old_value, $new_value, $action, $user_id);
        $log_stmt->execute();
    } catch (Exception $e) {
        // Ignore logging errors
        error_log("Settings log error: " . $e->getMessage());
    }
}

// Utility functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>
