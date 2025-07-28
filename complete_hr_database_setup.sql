-- Complete HR Management Database Setup
-- This script creates all necessary tables and sample data for the HR dashboard system

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS billing_demo;
USE billing_demo;

-- Create departments table
CREATE TABLE IF NOT EXISTS `departments` (
    `department_id` int(11) NOT NULL AUTO_INCREMENT,
    `department_name` varchar(100) NOT NULL,
    `department_head` varchar(100) DEFAULT NULL,
    `budget` decimal(15,2) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create employees table
CREATE TABLE IF NOT EXISTS `employees` (
    `employee_id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `employee_code` varchar(50) UNIQUE NOT NULL,
    `position` varchar(100) DEFAULT NULL,
    `department_id` int(11) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `joining_date` date DEFAULT NULL,
    `date_of_birth` date DEFAULT NULL,
    `salary` decimal(10,2) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `manager_id` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employee_id`),
    KEY `idx_employee_code` (`employee_code`),
    KEY `idx_status` (`status`),
    KEY `idx_department` (`department_id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create attendance table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `attendance_date` date NOT NULL,
    `check_in_time` time DEFAULT NULL,
    `check_out_time` time DEFAULT NULL,
    `status` enum('Present','Absent','Late','Half Day','On Leave','Work From Home') DEFAULT 'Absent',
    `working_hours` decimal(4,2) DEFAULT 0.00,
    `overtime_hours` decimal(4,2) DEFAULT 0.00,
    `break_hours` decimal(4,2) DEFAULT 0.00,
    `notes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_date` (`employee_id`, `attendance_date`),
    KEY `idx_date` (`attendance_date`),
    KEY `idx_status` (`status`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create leave_requests table
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` enum('casual','sick','earned','maternity','emergency','comp_off') NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `days_requested` int(11) NOT NULL,
    `reason` text DEFAULT NULL,
    `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` timestamp NULL DEFAULT NULL,
    `comments` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create leave_balance table
CREATE TABLE IF NOT EXISTS `leave_balance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) NOT NULL,
    `leave_type` enum('casual','sick','earned','maternity','emergency','comp_off') NOT NULL,
    `total_days` int(11) NOT NULL DEFAULT 0,
    `used_days` int(11) NOT NULL DEFAULT 0,
    `available_days` int(11) GENERATED ALWAYS AS (`total_days` - `used_days`) STORED,
    `year` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_leave_year` (`employee_id`, `leave_type`, `year`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample departments
INSERT IGNORE INTO `departments` (`department_name`, `department_head`, `budget`, `status`) VALUES
('Engineering', 'John Manager', 500000.00, 'active'),
('Human Resources', 'Sarah HR', 200000.00, 'active'),
('Marketing', 'Mike Marketing', 300000.00, 'active'),
('Finance', 'Lisa Finance', 250000.00, 'active'),
('Operations', 'Tom Operations', 180000.00, 'active');

-- Insert sample employees
INSERT IGNORE INTO `employees` (`name`, `employee_code`, `position`, `department_id`, `email`, `phone`, `joining_date`, `date_of_birth`, `salary`, `status`) VALUES
('John Doe', 'EMP001', 'Senior Developer', 1, 'john.doe@company.com', '123-456-7890', '2023-01-15', '1990-05-12', 75000.00, 'active'),
('Jane Smith', 'EMP002', 'UI Designer', 1, 'jane.smith@company.com', '123-456-7891', '2023-02-20', '1992-08-23', 65000.00, 'active'),
('Mike Johnson', 'EMP003', 'Business Analyst', 1, 'mike.johnson@company.com', '123-456-7892', '2023-03-10', '1988-12-05', 60000.00, 'active'),
('Sarah Wilson', 'EMP004', 'HR Manager', 2, 'sarah.wilson@company.com', '123-456-7893', '2022-11-01', '1985-03-18', 80000.00, 'active'),
('Tom Brown', 'EMP005', 'Marketing Specialist', 3, 'tom.brown@company.com', '123-456-7894', '2023-04-05', '1991-07-30', 55000.00, 'active'),
('Lisa Davis', 'EMP006', 'Financial Analyst', 4, 'lisa.davis@company.com', '123-456-7895', '2023-05-12', '1989-11-14', 62000.00, 'active'),
('Alex Garcia', 'EMP007', 'Operations Coordinator', 5, 'alex.garcia@company.com', '123-456-7896', '2023-06-18', '1993-04-07', 50000.00, 'active'),
('Emma White', 'EMP008', 'Junior Developer', 1, 'emma.white@company.com', '123-456-7897', '2023-07-25', '1995-09-21', 45000.00, 'active'),
('David Lee', 'EMP009', 'Senior Designer', 1, 'david.lee@company.com', '123-456-7898', '2023-08-30', '1987-01-16', 70000.00, 'active'),
('Rachel Green', 'EMP010', 'HR Specialist', 2, 'rachel.green@company.com', '123-456-7899', '2023-09-15', '1990-10-03', 48000.00, 'active');

-- Insert sample attendance data for current month
INSERT IGNORE INTO `attendance` (`employee_id`, `attendance_date`, `check_in_time`, `check_out_time`, `status`, `working_hours`, `overtime_hours`) VALUES
-- Today's attendance
(1, CURDATE(), '09:15:00', NULL, 'Present', 0.00, 0.00),
(2, CURDATE(), '09:30:00', NULL, 'Late', 0.00, 0.00),
(3, CURDATE(), '09:00:00', NULL, 'Present', 0.00, 0.00),
(4, CURDATE(), NULL, NULL, 'On Leave', 0.00, 0.00),
(5, CURDATE(), '09:45:00', NULL, 'Late', 0.00, 0.00),
(6, CURDATE(), '09:10:00', NULL, 'Present', 0.00, 0.00),
(7, CURDATE(), '08:55:00', NULL, 'Present', 0.00, 0.00),
(8, CURDATE(), '09:20:00', NULL, 'Present', 0.00, 0.00),
(9, CURDATE(), NULL, NULL, 'Absent', 0.00, 0.00),
(10, CURDATE(), '09:05:00', NULL, 'Present', 0.00, 0.00),

-- Yesterday's attendance (completed)
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '18:00:00', 'Present', 8.00, 0.00),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00', '18:15:00', 'Present', 8.00, 0.00),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', '18:30:00', 'Late', 8.00, 0.00),
(4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '19:00:00', 'Present', 8.00, 1.00),
(5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '18:00:00', 'Present', 8.00, 0.00),
(6, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:05:00', '18:05:00', 'Present', 8.00, 0.00),
(7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:50:00', '17:50:00', 'Present', 8.00, 0.00),
(8, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:10:00', '18:10:00', 'Present', 8.00, 0.00),
(9, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '18:30:00', 'Present', 8.00, 0.50),
(10, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '18:00:00', 'Present', 8.00, 0.00);

-- Insert sample leave requests
INSERT IGNORE INTO `leave_requests` (`employee_id`, `leave_type`, `start_date`, `end_date`, `days_requested`, `reason`, `status`, `approved_by`, `approved_at`) VALUES
(1, 'casual', '2024-12-23', '2024-12-24', 2, 'Christmas vacation', 'approved', 4, '2024-12-18 10:30:00'),
(2, 'sick', '2024-12-20', '2024-12-20', 1, 'Flu symptoms', 'pending', NULL, NULL),
(3, 'earned', '2025-01-15', '2025-01-19', 5, 'Family trip', 'pending', NULL, NULL),
(5, 'casual', '2024-12-25', '2024-12-25', 1, 'Personal work', 'rejected', 4, '2024-12-19 14:20:00'),
(6, 'comp_off', '2024-12-21', '2024-12-21', 1, 'Worked on weekend', 'approved', 4, '2024-12-19 09:15:00'),
(8, 'casual', '2025-01-02', '2025-01-03', 2, 'New Year celebration', 'pending', NULL, NULL);

-- Insert sample leave balances for current year
INSERT IGNORE INTO `leave_balance` (`employee_id`, `leave_type`, `total_days`, `used_days`, `year`) VALUES
-- Employee 1 (John Doe)
(1, 'casual', 12, 2, 2024),
(1, 'sick', 10, 0, 2024),
(1, 'earned', 21, 5, 2024),
(1, 'comp_off', 5, 1, 2024),

-- Employee 2 (Jane Smith) 
(2, 'casual', 12, 1, 2024),
(2, 'sick', 10, 2, 2024),
(2, 'earned', 21, 3, 2024),
(2, 'comp_off', 5, 0, 2024),

-- Employee 3 (Mike Johnson)
(3, 'casual', 12, 0, 2024),
(3, 'sick', 10, 1, 2024),
(3, 'earned', 21, 2, 2024),
(3, 'comp_off', 5, 0, 2024),

-- Continue for other employees...
(4, 'casual', 15, 3, 2024),
(4, 'sick', 12, 0, 2024),
(4, 'earned', 25, 8, 2024),
(4, 'comp_off', 8, 2, 2024),

(5, 'casual', 12, 2, 2024),
(5, 'sick', 10, 1, 2024),
(5, 'earned', 21, 4, 2024),
(5, 'comp_off', 5, 1, 2024);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_attendance_date_status ON attendance(attendance_date, status);
CREATE INDEX IF NOT EXISTS idx_leave_requests_status_date ON leave_requests(status, created_at);
CREATE INDEX IF NOT EXISTS idx_employees_status_dept ON employees(status, department_id);
