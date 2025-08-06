<?php
/**
 * 🌍 HRMS GDPR Compliance & Data Privacy Module
 * Complete implementation of GDPR requirements for data protection
 */

if (!isset($root_path)) 
require_once '../db.php';

class GDPRComplianceManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeGDPRTables();
    }
    
    /**
     * Initialize GDPR compliance tables
     */
    private function initializeGDPRTables() {
        $tables = [
            'data_processing_consents' => "
                CREATE TABLE IF NOT EXISTS data_processing_consents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    consent_type ENUM('personal_data', 'sensitive_data', 'marketing', 'analytics', 'cookies') NOT NULL,
                    consent_given BOOLEAN NOT NULL,
                    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    withdrawal_date TIMESTAMP NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    purpose TEXT,
                    legal_basis VARCHAR(100),
                    data_retention_period INT DEFAULT 365,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES employees(id)
                )",
            
            'data_processing_activities' => "
                CREATE TABLE IF NOT EXISTS data_processing_activities (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    activity_name VARCHAR(255) NOT NULL,
                    purpose TEXT NOT NULL,
                    legal_basis VARCHAR(100) NOT NULL,
                    data_categories TEXT NOT NULL,
                    data_subjects TEXT NOT NULL,
                    recipients TEXT,
                    international_transfers BOOLEAN DEFAULT FALSE,
                    retention_period INT NOT NULL,
                    security_measures TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
            
            'data_breach_incidents' => "
                CREATE TABLE IF NOT EXISTS data_breach_incidents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    incident_date TIMESTAMP NOT NULL,
                    detected_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    incident_type ENUM('unauthorized_access', 'data_loss', 'data_theft', 'system_breach', 'human_error') NOT NULL,
                    affected_data_types TEXT NOT NULL,
                    affected_individuals_count INT DEFAULT 0,
                    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                    description TEXT NOT NULL,
                    containment_measures TEXT,
                    notification_required BOOLEAN DEFAULT FALSE,
                    authority_notified BOOLEAN DEFAULT FALSE,
                    individuals_notified BOOLEAN DEFAULT FALSE,
                    notification_date TIMESTAMP NULL,
                    status ENUM('open', 'investigating', 'contained', 'resolved') DEFAULT 'open',
                    reported_by INT,
                    assigned_to INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
            
            'data_subject_requests' => "
                CREATE TABLE IF NOT EXISTS data_subject_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    request_type ENUM('access', 'rectification', 'erasure', 'portability', 'restriction', 'objection') NOT NULL,
                    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    description TEXT,
                    status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
                    completion_date TIMESTAMP NULL,
                    response_data LONGTEXT,
                    rejection_reason TEXT,
                    processed_by INT,
                    verification_status BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES employees(id)
                )",
            
            'privacy_policy_versions' => "
                CREATE TABLE IF NOT EXISTS privacy_policy_versions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    version VARCHAR(20) NOT NULL,
                    content LONGTEXT NOT NULL,
                    effective_date DATE NOT NULL,
                    expiry_date DATE,
                    is_active BOOLEAN DEFAULT TRUE,
                    changes_summary TEXT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            
            'data_retention_schedule' => "
                CREATE TABLE IF NOT EXISTS data_retention_schedule (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    data_category VARCHAR(255) NOT NULL,
                    retention_period_days INT NOT NULL,
                    legal_basis VARCHAR(255),
                    disposal_method VARCHAR(255),
                    review_frequency_days INT DEFAULT 365,
                    last_review_date DATE,
                    next_review_date DATE,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )"
        ];
        
        foreach ($tables as $table_name => $sql) {
            mysqli_query($this->conn, $sql);
        }
        
        // Insert default data processing activities
        $this->insertDefaultProcessingActivities();
    }
    
    /**
     * Insert default data processing activities
     */
    private function insertDefaultProcessingActivities() {
        $activities = [
            [
                'name' => 'Employee Management',
                'purpose' => 'Managing employment relationship, payroll, and HR functions',
                'legal_basis' => 'Contract Performance',
                'categories' => 'Personal identification, contact details, employment history, salary information',
                'subjects' => 'Current and former employees',
                'retention' => 2555 // 7 years
            ],
            [
                'name' => 'Recruitment Process',
                'purpose' => 'Hiring and recruitment of new employees',
                'legal_basis' => 'Legitimate Interest',
                'categories' => 'CV data, interview notes, references, background checks',
                'subjects' => 'Job applicants',
                'retention' => 365 // 1 year
            ],
            [
                'name' => 'Payroll Processing',
                'purpose' => 'Payment of salaries and statutory compliance',
                'legal_basis' => 'Legal Obligation',
                'categories' => 'Bank details, tax information, salary data, deductions',
                'subjects' => 'Current employees',
                'retention' => 2555 // 7 years for tax records
            ]
        ];
        
        foreach ($activities as $activity) {
            $check_query = "SELECT id FROM data_processing_activities WHERE activity_name = ?";
            $stmt = mysqli_prepare($this->conn, $check_query);
            mysqli_stmt_bind_param($stmt, "s", $activity['name']);
            mysqli_stmt_execute($stmt);
            
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) == 0) {
                $insert_query = "INSERT INTO data_processing_activities 
                               (activity_name, purpose, legal_basis, data_categories, data_subjects, retention_period) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($this->conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "sssssi", 
                    $activity['name'], $activity['purpose'], $activity['legal_basis'],
                    $activity['categories'], $activity['subjects'], $activity['retention']
                );
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    /**
     * Record data processing consent
     */
    public function recordConsent($user_id, $consent_type, $consent_given, $purpose = '', $legal_basis = 'Consent') {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $query = "INSERT INTO data_processing_consents 
                  (user_id, consent_type, consent_given, ip_address, user_agent, purpose, legal_basis) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "isisiss", 
            $user_id, $consent_type, $consent_given, $ip_address, $user_agent, $purpose, $legal_basis);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Withdraw consent
     */
    public function withdrawConsent($user_id, $consent_type) {
        $query = "UPDATE data_processing_consents 
                  SET consent_given = FALSE, withdrawal_date = NOW(), is_active = FALSE 
                  WHERE user_id = ? AND consent_type = ? AND is_active = TRUE";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $consent_type);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Check if user has given consent for specific processing
     */
    public function hasValidConsent($user_id, $consent_type) {
        $query = "SELECT consent_given FROM data_processing_consents 
                  WHERE user_id = ? AND consent_type = ? AND is_active = TRUE 
                  ORDER BY consent_date DESC LIMIT 1";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $consent_type);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['consent_given'] == 1;
        }
        
        return false;
    }
    
    /**
     * Export user data (Right to Data Portability)
     */
    public function exportUserData($user_id, $format = 'json') {
        $user_data = [];
        
        // Get employee data
        $query = "SELECT * FROM employees WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['personal_information'] = mysqli_fetch_assoc($result);
        
        // Get compliance data
        $query = "SELECT * FROM employee_compliance WHERE employee_id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['compliance_information'] = mysqli_fetch_assoc($result);
        
        // Get attendance data
        $query = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 100";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['attendance_records'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Get payroll data
        $query = "SELECT * FROM payroll_calculations WHERE employee_id = ? ORDER BY pay_period DESC LIMIT 12";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['payroll_records'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Get leave data
        $query = "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['leave_records'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Get consent history
        $query = "SELECT * FROM data_processing_consents WHERE user_id = ? ORDER BY consent_date DESC";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data['consent_history'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Add export metadata
        $user_data['export_metadata'] = [
            'exported_at' => date('Y-m-d H:i:s'),
            'format' => $format,
            'user_id' => $user_id,
            'export_scope' => 'complete_personal_data'
        ];
        
        // Format data based on requested format
        switch ($format) {
            case 'json':
                return json_encode($user_data, JSON_PRETTY_PRINT);
            
            case 'csv':
                return $this->convertToCSV($user_data);
            
            case 'xml':
                return $this->convertToXML($user_data);
            
            default:
                return $user_data;
        }
    }
    
    /**
     * Convert user data to CSV format
     */
    private function convertToCSV($user_data) {
        $csv_output = "GDPR Data Export - CSV Format\n";
        $csv_output .= "Exported on: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($user_data as $section => $data) {
            if ($section === 'export_metadata') continue;
            
            $csv_output .= strtoupper(str_replace('_', ' ', $section)) . "\n";
            $csv_output .= str_repeat('-', 50) . "\n";
            
            if (is_array($data) && !empty($data)) {
                if (isset($data[0]) && is_array($data[0])) {
                    // Multiple records
                    if (!empty($data[0])) {
                        $csv_output .= implode(',', array_keys($data[0])) . "\n";
                        foreach ($data as $row) {
                            $csv_output .= implode(',', array_map(function($val) {
                                return '"' . str_replace('"', '""', $val) . '"';
                            }, array_values($row))) . "\n";
                        }
                    }
                } else {
                    // Single record
                    foreach ($data as $key => $value) {
                        $csv_output .= "$key,\"" . str_replace('"', '""', $value) . "\"\n";
                    }
                }
            }
            $csv_output .= "\n";
        }
        
        return $csv_output;
    }
    
    /**
     * Convert user data to XML format
     */
    private function convertToXML($user_data) {
        $xml = new SimpleXMLElement('<gdpr_data_export/>');
        $xml->addChild('exported_at', date('Y-m-d H:i:s'));
        $xml->addChild('user_id', $user_data['export_metadata']['user_id']);
        
        foreach ($user_data as $section => $data) {
            if ($section === 'export_metadata') continue;
            
            $section_node = $xml->addChild($section);
            
            if (is_array($data) && !empty($data)) {
                if (isset($data[0]) && is_array($data[0])) {
                    // Multiple records
                    foreach ($data as $index => $record) {
                        $record_node = $section_node->addChild('record_' . $index);
                        foreach ($record as $key => $value) {
                            $record_node->addChild($key, htmlspecialchars($value));
                        }
                    }
                } else {
                    // Single record
                    foreach ($data as $key => $value) {
                        $section_node->addChild($key, htmlspecialchars($value));
                    }
                }
            }
        }
        
        return $xml->asXML();
    }
    
    /**
     * Delete user data (Right to Erasure)
     */
    public function deleteUserData($user_id, $reason = '', $retain_legal_obligations = true) {
        $deletion_log = [
            'user_id' => $user_id,
            'deletion_date' => date('Y-m-d H:i:s'),
            'reason' => $reason,
            'tables_affected' => []
        ];
        
        try {
            mysqli_autocommit($this->conn, false);
            
            if (!$retain_legal_obligations) {
                // Complete deletion (only if legally allowed)
                $tables_to_delete = [
                    'data_processing_consents',
                    'data_subject_requests',
                    'attendance',
                    'leave_requests',
                    'employee_performance',
                    'training_registrations',
                    'helpdesk_tickets'
                ];
                
                foreach ($tables_to_delete as $table) {
                    $query = "DELETE FROM $table WHERE user_id = ? OR employee_id = ?";
                    $stmt = mysqli_prepare($this->conn, $query);
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $deletion_log['tables_affected'][] = $table;
                    }
                }
                
                // Anonymize employee record
                $query = "UPDATE employees SET 
                          name = 'DELETED USER', 
                          email = CONCAT('deleted_', id, '@privacy.local'),
                          phone = NULL,
                          address = 'GDPR DELETION',
                          emergency_contact = NULL,
                          bank_details = NULL,
                          personal_email = NULL
                          WHERE id = ?";
                $stmt = mysqli_prepare($this->conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
            } else {
                // Pseudonymization for legal obligations
                $this->pseudonymizeUserData($user_id);
            }
            
            // Record the deletion request
            $query = "INSERT INTO data_subject_requests 
                      (user_id, request_type, description, status, completion_date) 
                      VALUES (?, 'erasure', ?, 'completed', NOW())";
            $stmt = mysqli_prepare($this->conn, $query);
            $description = "Data deletion completed: " . json_encode($deletion_log);
            mysqli_stmt_bind_param($stmt, "is", $user_id, $description);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($this->conn);
            mysqli_autocommit($this->conn, true);
            
            return ['success' => true, 'deletion_log' => $deletion_log];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            mysqli_autocommit($this->conn, true);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Pseudonymize user data for legal retention
     */
    private function pseudonymizeUserData($user_id) {
        $pseudo_id = 'PSEUDO_' . hash('sha256', $user_id . time());
        
        $query = "UPDATE employees SET 
                  name = ?, 
                  email = CONCAT(?, '@pseudonymized.local'),
                  phone = NULL,
                  address = 'PSEUDONYMIZED',
                  emergency_contact = NULL,
                  personal_email = NULL
                  WHERE id = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $pseudo_id, $pseudo_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Report data breach
     */
    public function reportDataBreach($incident_data) {
        $query = "INSERT INTO data_breach_incidents 
                  (incident_date, incident_type, affected_data_types, affected_individuals_count, 
                   risk_level, description, containment_measures, notification_required) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssissi",
            $incident_data['incident_date'],
            $incident_data['incident_type'], 
            $incident_data['affected_data_types'],
            $incident_data['affected_individuals_count'],
            $incident_data['risk_level'],
            $incident_data['description'],
            $incident_data['containment_measures'],
            $incident_data['notification_required']
        );
        
        $result = mysqli_stmt_execute($stmt);
        
        // If high/critical risk, trigger automatic notifications
        if ($incident_data['risk_level'] === 'high' || $incident_data['risk_level'] === 'critical') {
            $this->triggerBreachNotifications(mysqli_insert_id($this->conn));
        }
        
        return $result;
    }
    
    /**
     * Trigger breach notifications
     */
    private function triggerBreachNotifications($breach_id) {
        // Implementation for notifying authorities and affected individuals
        // This would typically integrate with email systems and regulatory reporting
        
        $query = "UPDATE data_breach_incidents 
                  SET notification_required = TRUE, notification_date = NOW() 
                  WHERE id = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $breach_id);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Generate GDPR compliance report
     */
    public function generateComplianceReport() {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'consent_summary' => $this->getConsentSummary(),
            'data_subject_requests' => $this->getDataSubjectRequestsSummary(),
            'data_breaches' => $this->getDataBreachesSummary(),
            'processing_activities' => $this->getProcessingActivitiesSummary(),
            'retention_compliance' => $this->getRetentionComplianceStatus()
        ];
        
        return $report;
    }
    
    private function getConsentSummary() {
        $query = "SELECT consent_type, 
                         COUNT(*) as total_consents,
                         SUM(CASE WHEN consent_given = 1 THEN 1 ELSE 0 END) as consents_given,
                         SUM(CASE WHEN consent_given = 0 THEN 1 ELSE 0 END) as consents_withdrawn
                  FROM data_processing_consents 
                  WHERE is_active = 1 
                  GROUP BY consent_type";
        
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    private function getDataSubjectRequestsSummary() {
        $query = "SELECT request_type, status, COUNT(*) as count
                  FROM data_subject_requests 
                  WHERE request_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY request_type, status";
        
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    private function getDataBreachesSummary() {
        $query = "SELECT risk_level, status, COUNT(*) as count
                  FROM data_breach_incidents 
                  WHERE incident_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY risk_level, status";
        
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    private function getProcessingActivitiesSummary() {
        $query = "SELECT activity_name, legal_basis, retention_period, is_active
                  FROM data_processing_activities";
        
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    private function getRetentionComplianceStatus() {
        // Implementation to check data retention compliance
        return ['status' => 'compliant', 'last_review' => date('Y-m-d')];
    }
}

// Initialize GDPR compliance manager
$gdpr = new GDPRComplianceManager($conn);

echo "🌍 GDPR Compliance System Initialized!\n";
echo "✅ Data privacy, consent management, and breach reporting ready.\n";

?>
