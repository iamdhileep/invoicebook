-- Complete database schema for attendance system
-- Run this to fix database structure issues

-- Create employees table if not exists
CREATE TABLE IF NOT EXISTS `employees` (
    `employee_id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `employee_code` varchar(50) UNIQUE NOT NULL,
    `position` varchar(100) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `hire_date` date DEFAULT NULL,
    `salary` decimal(10,2) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employee_id`),
    KEY `idx_employee_code` (`employee_code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance table if not exists
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `attendance_date` date NOT NULL,
    `time_in` time DEFAULT NULL,
    `time_out` time DEFAULT NULL,
    `status` enum('Present','Absent','Late','Half Day','Work From Home') DEFAULT 'Absent',
    `notes` text DEFAULT NULL,
    `overtime_hours` decimal(4,2) DEFAULT 0.00,
    `break_hours` decimal(4,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `marked_by` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_date` (`employee_id`, `attendance_date`),
    KEY `idx_date` (`attendance_date`),
    KEY `idx_status` (`status`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create leave_applications table
CREATE TABLE IF NOT EXISTS `leave_applications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` enum('Casual Leave','Sick Leave','Earned Leave','Maternity Leave','Emergency Leave') NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `days_requested` int(11) NOT NULL,
    `reason` text NOT NULL,
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` timestamp NULL DEFAULT NULL,
    `rejection_reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee` (`employee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create permission_requests table
CREATE TABLE IF NOT EXISTS `permission_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `permission_date` date NOT NULL,
    `from_time` time NOT NULL,
    `to_time` time NOT NULL,
    `reason` text NOT NULL,
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` timestamp NULL DEFAULT NULL,
    `rejection_reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee` (`employee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_date` (`permission_date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create biometric_devices table
CREATE TABLE IF NOT EXISTS `biometric_devices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `device_name` varchar(100) NOT NULL,
    `device_type` enum('fingerprint','biometric','facial_recognition') NOT NULL,
    `location` varchar(200) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `port` int(11) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_sync` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_type` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create device_sync_logs table
CREATE TABLE IF NOT EXISTS `device_sync_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `device_id` int(11) NOT NULL,
    `sync_date` date NOT NULL,
    `sync_time` timestamp DEFAULT CURRENT_TIMESTAMP,
    `records_synced` int(11) DEFAULT 0,
    `status` enum('success','failed','partial') DEFAULT 'success',
    `error_message` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_device` (`device_id`),
    KEY `idx_date` (`sync_date`),
    KEY `idx_status` (`status`),
    FOREIGN KEY (`device_id`) REFERENCES `biometric_devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create audit_logs table
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `table_name` varchar(50) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_action` (`action`),
    KEY `idx_table` (`table_name`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create system_settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL UNIQUE,
    `setting_value` text NOT NULL,
    `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
    `description` text DEFAULT NULL,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('default_work_start_time', '09:00', 'text', 'Default work start time'),
('default_work_end_time', '18:00', 'text', 'Default work end time'),
('late_threshold_minutes', '30', 'number', 'Minutes after which employee is marked late'),
('auto_punch_out_enabled', '0', 'boolean', 'Enable automatic punch out'),
('auto_punch_out_time', '20:00', 'text', 'Time for automatic punch out'),
('overtime_calculation_enabled', '1', 'boolean', 'Enable overtime calculation'),
('minimum_work_hours', '8', 'number', 'Minimum work hours per day'),
('weekend_days', '["Saturday","Sunday"]', 'json', 'Weekend days configuration');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON attendance(employee_id, attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendance_date_status ON attendance(attendance_date, status);
CREATE INDEX IF NOT EXISTS idx_leave_employee_dates ON leave_applications(employee_id, start_date, end_date);
