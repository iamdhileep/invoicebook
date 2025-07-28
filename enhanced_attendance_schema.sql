-- Enhanced Attendance Management System Database Schema
-- This script creates tables for modern attendance features

-- Create leave_requests table for automated leave management
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` varchar(50) NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `duration` decimal(4,2) NOT NULL,
    `reason` text NOT NULL,
    `reason_category` varchar(50) DEFAULT 'personal',
    `emergency_contact` varchar(20) DEFAULT NULL,
    `attachment_path` varchar(255) DEFAULT NULL,
    `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `approver_id` int(11) DEFAULT NULL,
    `approved_by` varchar(100) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `comments` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `status` (`status`),
    KEY `leave_type` (`leave_type`),
    KEY `start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create short_leave_requests table for short leave management
CREATE TABLE IF NOT EXISTS `short_leave_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` varchar(50) NOT NULL,
    `leave_date` date NOT NULL,
    `from_time` time NOT NULL,
    `to_time` time NOT NULL,
    `duration` decimal(3,1) NOT NULL COMMENT 'Duration in hours',
    `reason` text NOT NULL,
    `compensation_method` enum('deduct-salary','extra-hours','weekend-work','leave-balance') DEFAULT 'deduct-salary',
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `leave_date` (`leave_date`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create leave_balances table for tracking employee leave balances
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` varchar(50) NOT NULL,
    `annual_limit` decimal(5,2) DEFAULT 0.00,
    `available_balance` decimal(5,2) DEFAULT 0.00,
    `used_balance` decimal(5,2) DEFAULT 0.00,
    `pending_balance` decimal(5,2) DEFAULT 0.00,
    `carry_forward` decimal(5,2) DEFAULT 0.00,
    `year` int(4) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_leave_year` (`employee_id`,`leave_type`,`year`),
    KEY `employee_id` (`employee_id`),
    KEY `leave_type` (`leave_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create leave_types table for configurable leave policies
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type_name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `annual_limit` decimal(5,2) NOT NULL,
    `min_days_advance` int(3) DEFAULT 2,
    `max_consecutive_days` int(3) DEFAULT NULL,
    `requires_approval` tinyint(1) DEFAULT 1,
    `auto_approve_conditions` text,
    `carry_forward_allowed` tinyint(1) DEFAULT 0,
    `carry_forward_limit` decimal(5,2) DEFAULT 0.00,
    `gender_specific` enum('all','male','female') DEFAULT 'all',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default leave types
INSERT INTO `leave_types` (`type_name`, `display_name`, `annual_limit`, `min_days_advance`, `max_consecutive_days`, `requires_approval`, `carry_forward_allowed`, `carry_forward_limit`, `gender_specific`) VALUES
('sick', 'Sick Leave', 12.00, 0, NULL, 0, 0, 0.00, 'all'),
('casual', 'Casual Leave', 10.00, 2, 3, 1, 1, 3.00, 'all'),
('earned', 'Earned Leave', 21.00, 7, NULL, 1, 1, 15.00, 'all'),
('maternity', 'Maternity Leave', 180.00, 30, NULL, 1, 0, 0.00, 'female'),
('paternity', 'Paternity Leave', 15.00, 7, NULL, 1, 0, 0.00, 'male'),
('comp-off', 'Compensatory Off', 5.00, 1, NULL, 0, 0, 0.00, 'all'),
('wfh', 'Work From Home', 24.00, 1, 5, 1, 0, 0.00, 'all'),
('half-day', 'Half Day', 12.00, 0, NULL, 0, 0, 0.00, 'all'),
('short-leave', 'Short Leave', 48.00, 0, NULL, 0, 0, 0.00, 'all');

-- Create punch_methods table for tracking different punch methods
CREATE TABLE IF NOT EXISTS `punch_methods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `method_name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `priority` int(3) DEFAULT 1,
    `requires_verification` tinyint(1) DEFAULT 0,
    `settings` json DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `method_name` (`method_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert punch methods
INSERT INTO `punch_methods` (`method_name`, `display_name`, `is_active`, `priority`, `requires_verification`, `settings`) VALUES
('biometric', 'Biometric Scanner', 1, 1, 1, '{"accuracy_required": 95}'),
('mobile', 'Mobile App', 1, 2, 1, '{"location_required": true, "photo_required": false}'),
('geo', 'Geo Location', 1, 3, 1, '{"fence_radius": 100, "accuracy_required": 50}'),
('manual', 'Manual Entry', 1, 4, 0, '{"requires_reason": true}'),
('qr', 'QR Code Scanner', 1, 2, 0, '{"qr_timeout": 300}'),
('face', 'Face Recognition', 1, 1, 1, '{"confidence_threshold": 0.8}');

-- Enhance existing attendance table with new columns
ALTER TABLE `attendance` 
ADD COLUMN IF NOT EXISTS `punch_method` varchar(50) DEFAULT 'manual',
ADD COLUMN IF NOT EXISTS `punch_location` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `ip_address` varchar(45) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `user_agent` varchar(500) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `gps_coordinates` varchar(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `photo_path` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `verification_score` decimal(5,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `is_verified` tinyint(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `modified_by` int(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `modification_reason` text DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `short_leave_duration` decimal(3,1) DEFAULT 0.0,
ADD COLUMN IF NOT EXISTS `overtime_hours` decimal(4,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS `break_duration` decimal(4,2) DEFAULT 0.00;

-- Add indexes for performance
ALTER TABLE `attendance` 
ADD INDEX IF NOT EXISTS `idx_punch_method` (`punch_method`),
ADD INDEX IF NOT EXISTS `idx_is_verified` (`is_verified`),
ADD INDEX IF NOT EXISTS `idx_attendance_date_employee` (`attendance_date`, `employee_id`);

-- Create mobile_device_sessions table for mobile app integration
CREATE TABLE IF NOT EXISTS `mobile_device_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `device_id` varchar(255) NOT NULL,
    `device_name` varchar(255) DEFAULT NULL,
    `os_version` varchar(50) DEFAULT NULL,
    `app_version` varchar(20) DEFAULT NULL,
    `fcm_token` varchar(500) DEFAULT NULL,
    `last_active` datetime DEFAULT NULL,
    `location_lat` decimal(10,8) DEFAULT NULL,
    `location_lng` decimal(11,8) DEFAULT NULL,
    `location_accuracy` int(5) DEFAULT NULL,
    `battery_level` int(3) DEFAULT NULL,
    `is_online` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_device` (`employee_id`,`device_id`),
    KEY `employee_id` (`employee_id`),
    KEY `is_online` (`is_online`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create geo_fences table for location-based attendance
CREATE TABLE IF NOT EXISTS `geo_fences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `center_lat` decimal(10,8) NOT NULL,
    `center_lng` decimal(11,8) NOT NULL,
    `radius_meters` int(6) NOT NULL DEFAULT 100,
    `is_office_location` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `is_active` (`is_active`),
    KEY `is_office_location` (`is_office_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default office geo-fence (Bangalore coordinates as example)
INSERT INTO `geo_fences` (`name`, `description`, `center_lat`, `center_lng`, `radius_meters`, `is_office_location`, `is_active`) VALUES
('Main Office', 'Primary office location in Bangalore', 12.97160000, 77.59460000, 100, 1, 1);

-- Create activity_logs table for audit trail
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `user_type` enum('admin','employee','system') DEFAULT 'admin',
    `action` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `table_affected` varchar(50) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` varchar(500) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`),
    KEY `table_affected` (`table_affected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create policy_settings table for configurable policies
CREATE TABLE IF NOT EXISTS `policy_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text NOT NULL,
    `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
    `category` varchar(50) DEFAULT 'general',
    `description` text,
    `is_system` tinyint(1) DEFAULT 0,
    `updated_by` int(11) DEFAULT NULL,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`),
    KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default policy settings
INSERT INTO `policy_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
('office_start_time', '09:00', 'string', 'attendance', 'Default office start time'),
('office_end_time', '18:00', 'string', 'attendance', 'Default office end time'),
('grace_period_minutes', '15', 'number', 'attendance', 'Grace period for late arrival in minutes'),
('min_work_hours', '8', 'number', 'attendance', 'Minimum work hours per day'),
('overtime_after_hours', '9', 'number', 'attendance', 'Overtime calculation starts after these hours'),
('auto_approve_short_leave', 'true', 'boolean', 'leave', 'Auto-approve short leaves <= 2 hours'),
('email_notifications_enabled', 'true', 'boolean', 'notifications', 'Enable email notifications'),
('biometric_required', 'false', 'boolean', 'security', 'Require biometric verification'),
('geo_fencing_enabled', 'true', 'boolean', 'security', 'Enable geo-fencing validation'),
('mobile_app_required', 'false', 'boolean', 'security', 'Require mobile app for punch'),
('ip_whitelisting_enabled', 'true', 'boolean', 'security', 'Enable IP address whitelisting'),
('max_hours_per_day', '12', 'number', 'compliance', 'Maximum allowed work hours per day'),
('max_hours_per_week', '48', 'number', 'compliance', 'Maximum allowed work hours per week'),
('compliance_region', 'IN', 'string', 'compliance', 'Compliance region (IN=India, US=USA, etc.)');

-- Create compliance_violations table for tracking violations
CREATE TABLE IF NOT EXISTS `compliance_violations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `violation_type` varchar(100) NOT NULL,
    `violation_date` date NOT NULL,
    `description` text NOT NULL,
    `severity` enum('low','medium','high','critical') DEFAULT 'medium',
    `status` enum('open','acknowledged','resolved','dismissed') DEFAULT 'open',
    `resolved_by` int(11) DEFAULT NULL,
    `resolved_at` datetime DEFAULT NULL,
    `resolution_notes` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `violation_type` (`violation_type`),
    KEY `violation_date` (`violation_date`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create notification_queue table for automated notifications
CREATE TABLE IF NOT EXISTS `notification_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `recipient_id` int(11) NOT NULL,
    `recipient_type` enum('employee','admin','manager') DEFAULT 'employee',
    `notification_type` varchar(50) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `channel` enum('email','sms','push','in-app') DEFAULT 'email',
    `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
    `scheduled_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `sent_at` datetime DEFAULT NULL,
    `error_message` text,
    `metadata` json DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `recipient_id` (`recipient_id`),
    KEY `status` (`status`),
    KEY `scheduled_at` (`scheduled_at`),
    KEY `notification_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create attendance_analytics table for pre-calculated analytics
CREATE TABLE IF NOT EXISTS `attendance_analytics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `date` date NOT NULL,
    `employee_id` int(11) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `metric_type` varchar(50) NOT NULL,
    `metric_value` decimal(10,2) NOT NULL,
    `additional_data` json DEFAULT NULL,
    `calculated_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `date_employee_metric` (`date`,`employee_id`,`metric_type`),
    KEY `date` (`date`),
    KEY `employee_id` (`employee_id`),
    KEY `metric_type` (`metric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraints (optional, based on your existing schema)
-- ALTER TABLE `leave_requests` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE;
-- ALTER TABLE `short_leave_requests` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE;
-- ALTER TABLE `leave_balances` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE;
-- ALTER TABLE `mobile_device_sessions` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE;
-- ALTER TABLE `compliance_violations` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_attendance_date_status` ON `attendance`(`attendance_date`, `status`);
CREATE INDEX IF NOT EXISTS `idx_leave_requests_dates` ON `leave_requests`(`start_date`, `end_date`);
CREATE INDEX IF NOT EXISTS `idx_activity_logs_datetime` ON `activity_logs`(`created_at`, `user_id`);

-- Create views for commonly used queries
CREATE OR REPLACE VIEW `attendance_summary` AS
SELECT 
    a.attendance_date,
    a.employee_id,
    e.name as employee_name,
    e.employee_code,
    a.status,
    a.time_in,
    a.time_out,
    a.punch_method,
    a.is_verified,
    a.notes,
    CASE 
        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
        THEN TIMEDIFF(a.time_out, a.time_in)
        ELSE NULL 
    END as work_duration,
    a.overtime_hours,
    a.short_leave_duration
FROM attendance a
JOIN employees e ON a.employee_id = e.employee_id
WHERE e.status = 'active';

CREATE OR REPLACE VIEW `leave_balance_summary` AS
SELECT 
    lb.employee_id,
    e.name as employee_name,
    e.employee_code,
    lb.leave_type,
    lt.display_name as leave_type_name,
    lb.annual_limit,
    lb.available_balance,
    lb.used_balance,
    lb.pending_balance,
    lb.carry_forward,
    (lb.available_balance + lb.carry_forward) as total_available
FROM leave_balances lb
JOIN employees e ON lb.employee_id = e.employee_id
JOIN leave_types lt ON lb.leave_type = lt.type_name
WHERE e.status = 'active' AND lb.year = YEAR(CURDATE());

-- Stored procedures for common operations
DELIMITER //

-- Procedure to initialize leave balances for new employee
CREATE OR REPLACE PROCEDURE InitializeEmployeeLeaveBalances(IN emp_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE leave_type VARCHAR(50);
    DECLARE annual_limit DECIMAL(5,2);
    DECLARE cur CURSOR FOR 
        SELECT type_name, annual_limit FROM leave_types WHERE is_active = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    leave_loop: LOOP
        FETCH cur INTO leave_type, annual_limit;
        IF done THEN
            LEAVE leave_loop;
        END IF;
        
        INSERT IGNORE INTO leave_balances 
        (employee_id, leave_type, annual_limit, available_balance, year)
        VALUES 
        (emp_id, leave_type, annual_limit, annual_limit, YEAR(CURDATE()));
    END LOOP;
    CLOSE cur;
END //

-- Procedure to calculate daily attendance metrics
CREATE OR REPLACE PROCEDURE CalculateDailyMetrics(IN calc_date DATE)
BEGIN
    -- Clear existing metrics for the date
    DELETE FROM attendance_analytics WHERE date = calc_date;
    
    -- Overall attendance rate
    INSERT INTO attendance_analytics (date, metric_type, metric_value)
    SELECT 
        calc_date,
        'attendance_rate',
        (COUNT(CASE WHEN status IN ('Present', 'Late', 'WFH') THEN 1 END) * 100.0 / COUNT(*))
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.attendance_date = calc_date AND e.status = 'active';
    
    -- Average work hours
    INSERT INTO attendance_analytics (date, metric_type, metric_value)
    SELECT 
        calc_date,
        'avg_work_hours',
        AVG(TIME_TO_SEC(TIMEDIFF(time_out, time_in)) / 3600)
    FROM attendance
    WHERE attendance_date = calc_date 
      AND time_in IS NOT NULL 
      AND time_out IS NOT NULL
      AND status IN ('Present', 'Late');
      
    -- Overtime hours
    INSERT INTO attendance_analytics (date, metric_type, metric_value)
    SELECT 
        calc_date,
        'total_overtime_hours',
        SUM(overtime_hours)
    FROM attendance
    WHERE attendance_date = calc_date;
END //

DELIMITER ;

-- Create triggers for audit logging
DELIMITER //

CREATE OR REPLACE TRIGGER attendance_audit_update 
AFTER UPDATE ON attendance 
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (
        user_id, action, description, table_affected, record_id, 
        old_values, new_values
    ) VALUES (
        COALESCE(NEW.modified_by, 1),
        'attendance_update',
        CONCAT('Attendance updated for employee ', NEW.employee_id),
        'attendance',
        NEW.id,
        JSON_OBJECT(
            'status', OLD.status,
            'time_in', OLD.time_in,
            'time_out', OLD.time_out,
            'notes', OLD.notes
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'time_in', NEW.time_in,
            'time_out', NEW.time_out,
            'notes', NEW.notes
        )
    );
END //

CREATE OR REPLACE TRIGGER leave_request_audit 
AFTER INSERT ON leave_requests 
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (
        user_id, action, description, table_affected, record_id
    ) VALUES (
        NEW.employee_id,
        'leave_request_created',
        CONCAT('Leave request created: ', NEW.leave_type, ' for ', NEW.duration, ' days'),
        'leave_requests',
        NEW.id
    );
END //

DELIMITER ;

-- Final setup message
SELECT 'Enhanced Attendance Management System database schema created successfully!' as status;
