-- Database Schema for Advanced Attendance & Leave Management System
-- Created: <?= date('Y-m-d H:i:s') ?>

-- 1. Smart Attendance Features
CREATE TABLE IF NOT EXISTS `smart_attendance_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `attendance_type` enum('face_recognition','qr_code','gps','ip_based','manual') DEFAULT 'manual',
    `punch_type` enum('in','out') NOT NULL,
    `punch_time` datetime NOT NULL,
    `location_lat` decimal(10,8) DEFAULT NULL,
    `location_lng` decimal(11,8) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `device_info` text DEFAULT NULL,
    `confidence_score` decimal(5,2) DEFAULT NULL,
    `image_path` varchar(255) DEFAULT NULL,
    `status` enum('success','failed','pending') DEFAULT 'success',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `punch_time` (`punch_time`)
);

-- 2. Face Recognition Data
CREATE TABLE IF NOT EXISTS `face_recognition_data` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `face_encoding` longtext NOT NULL,
    `training_images` json DEFAULT NULL,
    `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_id` (`employee_id`)
);

-- 3. QR Code Management
CREATE TABLE IF NOT EXISTS `employee_qr_codes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `qr_code` varchar(255) NOT NULL,
    `qr_image_path` varchar(255) DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_id` (`employee_id`),
    UNIQUE KEY `qr_code` (`qr_code`)
);

-- 4. GPS Attendance Zones
CREATE TABLE IF NOT EXISTS `attendance_zones` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `zone_name` varchar(100) NOT NULL,
    `center_lat` decimal(10,8) NOT NULL,
    `center_lng` decimal(11,8) NOT NULL,
    `radius_meters` int(11) DEFAULT 100,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

-- 5. IP-based Attendance Rules
CREATE TABLE IF NOT EXISTS `ip_attendance_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `rule_name` varchar(100) NOT NULL,
    `ip_range_start` varchar(45) NOT NULL,
    `ip_range_end` varchar(45) NOT NULL,
    `location_name` varchar(100) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

-- 6. Leave Management Enhanced
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type_name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `color_code` varchar(7) DEFAULT '#007bff',
    `max_days_per_year` int(11) DEFAULT NULL,
    `carry_forward_allowed` tinyint(1) DEFAULT 0,
    `encashment_allowed` tinyint(1) DEFAULT 0,
    `requires_approval` tinyint(1) DEFAULT 1,
    `advance_notice_days` int(11) DEFAULT 1,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `type_name` (`type_name`)
);

-- 7. Leave Balances
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type_id` int(11) NOT NULL,
    `year` int(4) NOT NULL,
    `allocated_days` decimal(5,2) DEFAULT 0,
    `used_days` decimal(5,2) DEFAULT 0,
    `carried_forward` decimal(5,2) DEFAULT 0,
    `encashed_days` decimal(5,2) DEFAULT 0,
    `remaining_days` decimal(5,2) GENERATED ALWAYS AS (allocated_days + carried_forward - used_days - encashed_days) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
    KEY `leave_type_id` (`leave_type_id`)
);

-- 8. Enhanced Leave Requests
ALTER TABLE `leave_requests` ADD COLUMN IF NOT EXISTS `workflow_stage` varchar(50) DEFAULT 'pending';
ALTER TABLE `leave_requests` ADD COLUMN IF NOT EXISTS `approver_level` int(11) DEFAULT 1;
ALTER TABLE `leave_requests` ADD COLUMN IF NOT EXISTS `escalation_date` datetime DEFAULT NULL;
ALTER TABLE `leave_requests` ADD COLUMN IF NOT EXISTS `ai_recommendation` text DEFAULT NULL;
ALTER TABLE `leave_requests` ADD COLUMN IF NOT EXISTS `team_impact_score` decimal(3,2) DEFAULT NULL;

-- 9. Approval Workflow
CREATE TABLE IF NOT EXISTS `approval_workflows` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `workflow_name` varchar(100) NOT NULL,
    `leave_type_id` int(11) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `min_days_trigger` int(11) DEFAULT 1,
    `levels` json NOT NULL,
    `escalation_hours` int(11) DEFAULT 24,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
);

-- 10. Approval History
CREATE TABLE IF NOT EXISTS `approval_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `leave_request_id` int(11) NOT NULL,
    `approver_id` int(11) NOT NULL,
    `level` int(11) NOT NULL,
    `action` enum('approved','rejected','escalated','commented') NOT NULL,
    `comments` text DEFAULT NULL,
    `action_date` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `leave_request_id` (`leave_request_id`)
);

-- 11. Holiday Calendar
CREATE TABLE IF NOT EXISTS `holiday_calendar` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `holiday_name` varchar(100) NOT NULL,
    `holiday_date` date NOT NULL,
    `is_optional` tinyint(1) DEFAULT 0,
    `applicable_locations` json DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `holiday_date` (`holiday_date`)
);

-- 12. AI Leave Analytics
CREATE TABLE IF NOT EXISTS `leave_patterns` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `pattern_type` varchar(50) NOT NULL,
    `pattern_data` json NOT NULL,
    `confidence_score` decimal(3,2) DEFAULT NULL,
    `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
);

-- 13. Notification Settings
CREATE TABLE IF NOT EXISTS `notification_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `notification_type` varchar(50) NOT NULL,
    `email_enabled` tinyint(1) DEFAULT 1,
    `sms_enabled` tinyint(1) DEFAULT 0,
    `whatsapp_enabled` tinyint(1) DEFAULT 0,
    `push_enabled` tinyint(1) DEFAULT 1,
    `frequency` enum('immediate','daily','weekly') DEFAULT 'immediate',
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_notification` (`user_id`, `notification_type`)
);

-- 14. Notification Logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `recipient_id` int(11) NOT NULL,
    `notification_type` varchar(50) NOT NULL,
    `channel` enum('email','sms','whatsapp','push') NOT NULL,
    `subject` varchar(255) DEFAULT NULL,
    `message` text NOT NULL,
    `status` enum('sent','failed','pending') DEFAULT 'pending',
    `sent_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `error_message` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `recipient_id` (`recipient_id`),
    KEY `sent_at` (`sent_at`)
);

-- 15. Manager Assignments
CREATE TABLE IF NOT EXISTS `manager_assignments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `manager_id` int(11) NOT NULL,
    `level` int(11) NOT NULL DEFAULT 1,
    `can_approve_leave` tinyint(1) DEFAULT 1,
    `can_mark_attendance` tinyint(1) DEFAULT 0,
    `effective_from` date NOT NULL,
    `effective_until` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`),
    KEY `manager_id` (`manager_id`)
);

-- 16. Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `employee_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `table_name` varchar(50) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `timestamp` (`timestamp`)
);

-- 17. Mobile App Tokens
CREATE TABLE IF NOT EXISTS `mobile_app_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `device_token` varchar(255) NOT NULL,
    `device_type` enum('android','ios') NOT NULL,
    `app_version` varchar(20) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_used` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `employee_id` (`employee_id`)
);

-- 18. Policy Configurations
CREATE TABLE IF NOT EXISTS `policy_configurations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `policy_name` varchar(100) NOT NULL,
    `policy_type` varchar(50) NOT NULL,
    `department` varchar(100) DEFAULT NULL,
    `configuration` json NOT NULL,
    `effective_from` date NOT NULL,
    `effective_until` date DEFAULT NULL,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `policy_type` (`policy_type`)
);

-- 19. Integration Settings
CREATE TABLE IF NOT EXISTS `integration_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `integration_name` varchar(50) NOT NULL,
    `integration_type` varchar(50) NOT NULL,
    `configuration` json NOT NULL,
    `is_active` tinyint(1) DEFAULT 0,
    `last_sync` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `integration_name` (`integration_name`)
);

-- Insert default leave types
INSERT IGNORE INTO `leave_types` (`type_name`, `display_name`, `color_code`, `max_days_per_year`, `carry_forward_allowed`, `encashment_allowed`) VALUES
('casual', 'Casual Leave', '#28a745', 12, 1, 0),
('sick', 'Sick Leave', '#dc3545', 10, 0, 0),
('earned', 'Earned Leave', '#007bff', 21, 1, 1),
('wfh', 'Work From Home', '#6f42c1', 24, 0, 0),
('maternity', 'Maternity Leave', '#e83e8c', 180, 0, 0),
('paternity', 'Paternity Leave', '#fd7e14', 15, 0, 0),
('comp_off', 'Compensatory Off', '#20c997', 12, 1, 0);

-- Insert default holidays
INSERT IGNORE INTO `holiday_calendar` (`holiday_name`, `holiday_date`) VALUES
('New Year Day', '2025-01-01'),
('Republic Day', '2025-01-26'),
('Independence Day', '2025-08-15'),
('Gandhi Jayanti', '2025-10-02'),
('Christmas', '2025-12-25');

-- Insert default attendance zones (example office location)
INSERT IGNORE INTO `attendance_zones` (`zone_name`, `center_lat`, `center_lng`, `radius_meters`) VALUES
('Main Office', 28.6139, 77.2090, 100),
('Branch Office', 19.0760, 72.8777, 150);

-- Insert default IP rules (example office network)
INSERT IGNORE INTO `ip_attendance_rules` (`rule_name`, `ip_range_start`, `ip_range_end`, `location_name`) VALUES
('Office Network', '192.168.1.1', '192.168.1.255', 'Main Office'),
('Guest Network', '192.168.2.1', '192.168.2.255', 'Main Office');

-- Insert default notification settings
INSERT IGNORE INTO `notification_settings` (`user_id`, `notification_type`, `email_enabled`, `sms_enabled`) VALUES
(1, 'attendance_missing', 1, 0),
(1, 'leave_approval', 1, 1),
(1, 'monthly_summary', 1, 0);
