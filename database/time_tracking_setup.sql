-- Time Tracking System Database Setup
-- Run this SQL to create the necessary tables for the time tracking system

-- Time Off Requests Table
CREATE TABLE IF NOT EXISTS `time_off_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `request_type` enum('Medical','Personal','Sick','Vacation','Emergency') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `status` (`status`),
  KEY `request_type` (`request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Overtime Requests Table
CREATE TABLE IF NOT EXISTS `overtime_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours_requested` decimal(4,2) NOT NULL,
  `reason` text NOT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `status` (`status`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time Clock Table (for punch in/out tracking)
CREATE TABLE IF NOT EXISTS `time_clock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `clock_date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `break_start` datetime DEFAULT NULL,
  `break_end` datetime DEFAULT NULL,
  `total_hours` decimal(4,2) DEFAULT NULL,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `status` enum('Present','Late','Remote','WFH','Half Day','Absent') DEFAULT 'Present',
  `location` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_date` (`employee_id`, `clock_date`),
  KEY `clock_date` (`clock_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time Tracking Settings Table
CREATE TABLE IF NOT EXISTS `time_tracking_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `time_tracking_settings` (`setting_name`, `setting_value`, `description`) VALUES
('office_start_time', '09:00', 'Official office start time'),
('office_end_time', '18:00', 'Official office end time'),
('late_threshold', '15', 'Minutes after which arrival is considered late'),
('overtime_threshold', '8.5', 'Hours after which overtime calculation starts'),
('break_duration', '60', 'Standard break duration in minutes'),
('work_days', 'Monday,Tuesday,Wednesday,Thursday,Friday', 'Working days of the week'),
('timezone', 'Asia/Kolkata', 'Default timezone for time tracking');

-- Employee Schedules Table (for flexible schedules)
CREATE TABLE IF NOT EXISTS `employee_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_working_day` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_day` (`employee_id`, `day_of_week`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time Tracking Reports Table
CREATE TABLE IF NOT EXISTS `time_tracking_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `report_month` varchar(7) NOT NULL, -- Format: YYYY-MM
  `total_working_days` int(11) NOT NULL,
  `days_present` int(11) NOT NULL,
  `days_absent` int(11) NOT NULL,
  `days_late` int(11) NOT NULL,
  `total_hours` decimal(6,2) NOT NULL,
  `overtime_hours` decimal(6,2) DEFAULT 0.00,
  `time_off_days` int(11) DEFAULT 0,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_month` (`employee_id`, `report_month`),
  KEY `employee_id` (`employee_id`),
  KEY `report_month` (`report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
