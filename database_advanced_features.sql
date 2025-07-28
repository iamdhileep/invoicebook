-- Advanced Attendance System Database Schema
-- Date: 2025-01-27
-- Features: Smart Attendance, Leave Management, AI Analytics, Mobile Integration

USE billbook;

-- 1. Smart Attendance Tables
CREATE TABLE IF NOT EXISTS smart_attendance_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    method_name VARCHAR(50) NOT NULL,
    method_type ENUM('face_recognition', 'qr_code', 'gps', 'ip_based', 'biometric') NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    configuration JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS face_recognition_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    face_encoding TEXT NOT NULL,
    face_image_path VARCHAR(255),
    confidence_threshold DECIMAL(3,2) DEFAULT 0.85,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS qr_attendance_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_hash VARCHAR(64) UNIQUE NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    valid_from DATETIME NOT NULL,
    valid_until DATETIME NOT NULL,
    max_uses INT DEFAULT NULL,
    current_uses INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(employee_id)
);

CREATE TABLE IF NOT EXISTS gps_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ip_restrictions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    ip_range_start VARCHAR(45),
    ip_range_end VARCHAR(45),
    subnet_mask VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS smart_attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    attendance_method ENUM('face_recognition', 'qr_code', 'gps', 'ip_based', 'biometric', 'manual') NOT NULL,
    punch_type ENUM('in', 'out') NOT NULL,
    punch_time DATETIME NOT NULL,
    location_data JSON,
    device_info JSON,
    confidence_score DECIMAL(3,2),
    verification_status ENUM('verified', 'pending', 'failed') DEFAULT 'verified',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_employee_date (employee_id, DATE(punch_time)),
    INDEX idx_punch_time (punch_time)
);

-- 2. Leave Management Tables
CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_name VARCHAR(50) NOT NULL,
    leave_code VARCHAR(10) UNIQUE NOT NULL,
    color_code VARCHAR(7) DEFAULT '#007bff',
    max_days_per_year INT DEFAULT 365,
    can_carry_forward BOOLEAN DEFAULT FALSE,
    carry_forward_limit INT DEFAULT 0,
    can_encash BOOLEAN DEFAULT FALSE,
    requires_approval BOOLEAN DEFAULT TRUE,
    advance_days_required INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leave_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_name VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    leave_type_id INT,
    entitlement_days INT NOT NULL,
    min_service_months INT DEFAULT 0,
    probation_applicable BOOLEAN DEFAULT FALSE,
    weekend_included BOOLEAN DEFAULT FALSE,
    holiday_included BOOLEAN DEFAULT FALSE,
    medical_certificate_required BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    opening_balance DECIMAL(4,1) DEFAULT 0,
    earned_balance DECIMAL(4,1) DEFAULT 0,
    used_balance DECIMAL(4,1) DEFAULT 0,
    carry_forward DECIMAL(4,1) DEFAULT 0,
    encashed DECIMAL(4,1) DEFAULT 0,
    remaining_balance DECIMAL(4,1) GENERATED ALWAYS AS (opening_balance + earned_balance + carry_forward - used_balance - encashed) STORED,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_leave_year (employee_id, leave_type_id, year)
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested DECIMAL(3,1) NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'withdrawn') DEFAULT 'pending',
    applied_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attachments JSON,
    emergency_contact VARCHAR(15),
    handover_details TEXT,
    ai_recommendation JSON,
    approval_comments TEXT,
    rejected_reason TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE RESTRICT,
    INDEX idx_employee_dates (employee_id, start_date, end_date),
    INDEX idx_status_date (status, applied_on)
);

CREATE TABLE IF NOT EXISTS leave_approval_workflow (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_request_id INT NOT NULL,
    approver_id INT NOT NULL,
    approval_level INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'delegated') DEFAULT 'pending',
    action_date TIMESTAMP NULL,
    comments TEXT,
    escalation_date TIMESTAMP NULL,
    is_escalated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES employees(employee_id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS public_holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    is_optional BOOLEAN DEFAULT FALSE,
    applies_to_department VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_holiday_date (holiday_date)
);

-- 3. AI and Analytics Tables
CREATE TABLE IF NOT EXISTS attendance_patterns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    pattern_type ENUM('punctuality', 'leave_frequency', 'overtime', 'weekend_work') NOT NULL,
    pattern_data JSON NOT NULL,
    confidence_score DECIMAL(3,2),
    analysis_period_start DATE NOT NULL,
    analysis_period_end DATE NOT NULL,
    recommendations JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS leave_ai_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    suggested_dates JSON NOT NULL,
    reason_code ENUM('workload_low', 'team_available', 'consecutive_leave', 'use_or_lose', 'health_break') NOT NULL,
    confidence_score DECIMAL(3,2),
    valid_until DATE,
    is_accepted BOOLEAN DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_name VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    employee_id INT,
    department VARCHAR(50),
    calculation_date DATE NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_metric_date (metric_name, calculation_date),
    INDEX idx_employee_period (employee_id, period_type, calculation_date)
);

-- 4. Mobile Integration Tables
CREATE TABLE IF NOT EXISTS mobile_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    device_token VARCHAR(255) UNIQUE NOT NULL,
    device_type ENUM('android', 'ios') NOT NULL,
    device_model VARCHAR(100),
    app_version VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    last_sync TIMESTAMP NULL,
    push_enabled BOOLEAN DEFAULT TRUE,
    location_enabled BOOLEAN DEFAULT TRUE,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS mobile_attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    device_id INT NOT NULL,
    punch_type ENUM('in', 'out') NOT NULL,
    punch_time DATETIME NOT NULL,
    gps_latitude DECIMAL(10, 8),
    gps_longitude DECIMAL(11, 8),
    gps_accuracy DECIMAL(6,2),
    selfie_path VARCHAR(255),
    is_offline_sync BOOLEAN DEFAULT FALSE,
    sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE CASCADE
);

-- 5. Notification System Tables
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    template_type ENUM('email', 'sms', 'push', 'whatsapp') NOT NULL,
    subject VARCHAR(200),
    message_body TEXT NOT NULL,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_id INT NOT NULL,
    notification_type ENUM('attendance_missing', 'leave_approval', 'monthly_summary', 'reminder', 'alert') NOT NULL,
    delivery_method ENUM('email', 'sms', 'push', 'whatsapp') NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS smart_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alert_name VARCHAR(100) NOT NULL,
    alert_type ENUM('attendance_missing', 'late_arrival', 'early_departure', 'overtime_exceed', 'leave_pending') NOT NULL,
    conditions JSON NOT NULL,
    recipients JSON NOT NULL,
    delivery_methods JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Manager Tools Tables
CREATE TABLE IF NOT EXISTS manager_delegations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    manager_id INT NOT NULL,
    delegate_id INT NOT NULL,
    delegation_type ENUM('leave_approval', 'attendance_management', 'team_reports') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (delegate_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS team_dashboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    manager_id INT NOT NULL,
    dashboard_config JSON NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- 7. Audit and History Tables
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    changed_by INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    geo_location JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (changed_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_timestamp (timestamp)
);

CREATE TABLE IF NOT EXISTS session_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    session_token VARCHAR(255),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_fingerprint VARCHAR(255),
    location_data JSON,
    session_duration INT GENERATED ALWAYS AS (TIMESTAMPDIFF(SECOND, login_time, logout_time)) STORED,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_employee_login (employee_id, login_time)
);

-- 8. API Integration Tables
CREATE TABLE IF NOT EXISTS api_integrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    integration_name VARCHAR(100) NOT NULL,
    integration_type ENUM('hrms', 'payroll', 'slack', 'teams', 'webhook') NOT NULL,
    api_endpoint VARCHAR(255),
    api_key_hash VARCHAR(255),
    configuration JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync TIMESTAMP NULL,
    sync_frequency INT DEFAULT 3600, -- seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    integration_id INT NOT NULL,
    sync_type ENUM('import', 'export', 'bidirectional') NOT NULL,
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    error_details JSON,
    sync_duration INT, -- seconds
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (integration_id) REFERENCES api_integrations(id) ON DELETE CASCADE
);

-- 9. Biometric Integration Tables
CREATE TABLE IF NOT EXISTS biometric_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    device_type ENUM('fingerprint', 'face', 'iris', 'palm', 'card') NOT NULL,
    device_model VARCHAR(100),
    serial_number VARCHAR(100),
    ip_address VARCHAR(45),
    location_name VARCHAR(100),
    api_endpoint VARCHAR(255),
    sync_interval INT DEFAULT 300, -- seconds
    last_sync TIMESTAMP NULL,
    is_online BOOLEAN DEFAULT FALSE,
    configuration JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS biometric_sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id INT NOT NULL,
    employee_id INT NOT NULL,
    biometric_id VARCHAR(50), -- device-specific employee ID
    punch_time DATETIME NOT NULL,
    punch_type ENUM('in', 'out', 'break_out', 'break_in') NOT NULL,
    sync_status ENUM('pending', 'processed', 'duplicate', 'error') DEFAULT 'pending',
    raw_data JSON,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES biometric_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_sync_status (sync_status, created_at)
);

-- Insert Default Data
INSERT INTO leave_types (leave_name, leave_code, color_code, max_days_per_year, can_carry_forward, carry_forward_limit) VALUES
('Casual Leave', 'CL', '#28a745', 12, TRUE, 5),
('Sick Leave', 'SL', '#dc3545', 12, FALSE, 0),
('Earned Leave', 'EL', '#007bff', 21, TRUE, 15),
('Work From Home', 'WFH', '#17a2b8', 365, FALSE, 0),
('Maternity Leave', 'ML', '#e83e8c', 180, FALSE, 0),
('Paternity Leave', 'PL', '#fd7e14', 15, FALSE, 0),
('Comp Off', 'CO', '#6f42c1', 365, FALSE, 0),
('Loss of Pay', 'LOP', '#6c757d', 365, FALSE, 0);

INSERT INTO smart_attendance_methods (method_name, method_type, configuration) VALUES
('Face Recognition', 'face_recognition', '{"confidence_threshold": 0.85, "max_attempts": 3}'),
('QR Code Scanning', 'qr_code', '{"code_expiry_minutes": 15, "location_required": true}'),
('GPS Location', 'gps', '{"accuracy_threshold": 50, "allowed_locations": []}'),
('IP Based Check-in', 'ip_based', '{"allowed_networks": [], "strict_mode": false}'),
('Biometric Device', 'biometric', '{"sync_interval": 300, "auto_process": true}');

INSERT INTO notification_templates (template_name, template_type, subject, message_body) VALUES
('Attendance Missing Alert', 'email', 'Attendance Missing - {date}', 'Dear {employee_name}, your attendance is missing for {date}. Please mark your attendance or apply for leave.'),
('Leave Approval Request', 'email', 'Leave Approval Request - {employee_name}', 'Dear {manager_name}, {employee_name} has applied for {leave_type} from {start_date} to {end_date}.'),
('Monthly Attendance Summary', 'email', 'Monthly Attendance Summary - {month} {year}', 'Dear {employee_name}, here is your attendance summary for {month} {year}: Present: {present_days}, Absent: {absent_days}, Leave: {leave_days}'),
('Punch Reminder', 'push', 'Punch Reminder', 'Don\'t forget to mark your attendance!');

INSERT INTO public_holidays (holiday_name, holiday_date, description) VALUES
('New Year', '2025-01-01', 'New Year Day'),
('Republic Day', '2025-01-26', 'Republic Day of India'),
('Independence Day', '2025-08-15', 'Independence Day of India'),
('Gandhi Jayanti', '2025-10-02', 'Mahatma Gandhi Birthday'),
('Diwali', '2025-10-20', 'Festival of Lights'),
('Christmas', '2025-12-25', 'Christmas Day');

-- Create indexes for better performance
CREATE INDEX idx_smart_attendance_employee_date ON smart_attendance_logs(employee_id, DATE(punch_time));
CREATE INDEX idx_leave_requests_dates ON leave_requests(start_date, end_date, status);
CREATE INDEX idx_notification_logs_status ON notification_logs(status, created_at);
CREATE INDEX idx_audit_trail_timestamp ON audit_trail(timestamp);
CREATE INDEX idx_mobile_sync_status ON mobile_attendance_logs(sync_status, created_at);

-- Create views for common queries
CREATE OR REPLACE VIEW vw_employee_leave_summary AS
SELECT 
    e.employee_id,
    e.name,
    e.department,
    lt.leave_name,
    elb.year,
    elb.opening_balance,
    elb.earned_balance,
    elb.used_balance,
    elb.remaining_balance
FROM employees e
LEFT JOIN employee_leave_balances elb ON e.employee_id = elb.employee_id
LEFT JOIN leave_types lt ON elb.leave_type_id = lt.id
WHERE e.status = 'active';

CREATE OR REPLACE VIEW vw_daily_attendance_summary AS
SELECT 
    DATE(punch_time) as attendance_date,
    COUNT(DISTINCT employee_id) as total_punches,
    COUNT(DISTINCT CASE WHEN punch_type = 'in' THEN employee_id END) as punch_ins,
    COUNT(DISTINCT CASE WHEN punch_type = 'out' THEN employee_id END) as punch_outs,
    AVG(confidence_score) as avg_confidence
FROM smart_attendance_logs
WHERE verification_status = 'verified'
GROUP BY DATE(punch_time);

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE sp_calculate_leave_balance(IN emp_id INT, IN leave_type INT, IN year INT)
BEGIN
    DECLARE opening DECIMAL(4,1) DEFAULT 0;
    DECLARE earned DECIMAL(4,1) DEFAULT 0;
    DECLARE used DECIMAL(4,1) DEFAULT 0;
    DECLARE carry_fwd DECIMAL(4,1) DEFAULT 0;
    
    -- Calculate used leaves for the year
    SELECT COALESCE(SUM(days_requested), 0) INTO used
    FROM leave_requests 
    WHERE employee_id = emp_id 
    AND leave_type_id = leave_type 
    AND YEAR(start_date) = year 
    AND status = 'approved';
    
    -- Get policy entitlement
    SELECT COALESCE(entitlement_days, 0) INTO earned
    FROM leave_policies lp
    JOIN employees e ON (lp.department = e.department OR lp.department IS NULL)
    WHERE e.employee_id = emp_id 
    AND lp.leave_type_id = leave_type
    AND lp.is_active = TRUE
    LIMIT 1;
    
    -- Insert or update balance
    INSERT INTO employee_leave_balances 
    (employee_id, leave_type_id, year, opening_balance, earned_balance, used_balance, carry_forward)
    VALUES (emp_id, leave_type, year, opening, earned, used, carry_fwd)
    ON DUPLICATE KEY UPDATE
    earned_balance = earned,
    used_balance = used;
END //

CREATE PROCEDURE sp_process_biometric_sync(IN device_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE emp_id INT;
    DECLARE punch_dt DATETIME;
    DECLARE punch_tp ENUM('in', 'out', 'break_out', 'break_in');
    
    DECLARE sync_cursor CURSOR FOR
        SELECT employee_id, punch_time, punch_type
        FROM biometric_sync_logs
        WHERE device_id = device_id AND sync_status = 'pending'
        ORDER BY punch_time;
        
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN sync_cursor;
    
    sync_loop: LOOP
        FETCH sync_cursor INTO emp_id, punch_dt, punch_tp;
        IF done THEN
            LEAVE sync_loop;
        END IF;
        
        -- Insert into smart attendance logs
        INSERT INTO smart_attendance_logs 
        (employee_id, attendance_method, punch_type, punch_time, verification_status, notes)
        VALUES (emp_id, 'biometric', punch_tp, punch_dt, 'verified', 'Auto-synced from biometric device');
        
        -- Update sync status
        UPDATE biometric_sync_logs 
        SET sync_status = 'processed', processed_at = NOW()
        WHERE employee_id = emp_id AND punch_time = punch_dt AND device_id = device_id;
        
    END LOOP;
    
    CLOSE sync_cursor;
END //

DELIMITER ;

-- Create triggers for audit trail
DELIMITER //

CREATE TRIGGER tr_attendance_audit_insert AFTER INSERT ON smart_attendance_logs
FOR EACH ROW
BEGIN
    INSERT INTO audit_trail (table_name, record_id, action, new_values, changed_by, ip_address)
    VALUES ('smart_attendance_logs', NEW.id, 'INSERT', 
            JSON_OBJECT('employee_id', NEW.employee_id, 'punch_type', NEW.punch_type, 'punch_time', NEW.punch_time),
            NEW.employee_id, @user_ip);
END //

CREATE TRIGGER tr_leave_request_audit_update AFTER UPDATE ON leave_requests
FOR EACH ROW
BEGIN
    INSERT INTO audit_trail (table_name, record_id, action, old_values, new_values, changed_by, ip_address)
    VALUES ('leave_requests', NEW.id, 'UPDATE',
            JSON_OBJECT('status', OLD.status, 'approval_comments', OLD.approval_comments),
            JSON_OBJECT('status', NEW.status, 'approval_comments', NEW.approval_comments),
            @user_id, @user_ip);
END //

DELIMITER ;

-- Grant necessary permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON billbook.* TO 'attendance_user'@'localhost';

COMMIT;
