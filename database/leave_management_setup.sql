-- Leave Management System Database Setup
-- This file contains the SQL commands to create tables for leave and permission management

-- Create leaves table
CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('casual_leave', 'sick_leave', 'paid_leave', 'lop') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT NOT NULL,
    contact_during_leave VARCHAR(20),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT,
    approved_by INT,
    approved_date TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_leave_type (leave_type),
    INDEX idx_applied_date (applied_date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    permission_date DATE NOT NULL,
    permission_type ENUM('early_departure', 'late_arrival', 'extended_lunch', 'personal_work') NOT NULL,
    from_time TIME NOT NULL,
    to_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT,
    approved_by INT,
    approved_date TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_permission_type (permission_type),
    INDEX idx_permission_date (permission_date),
    INDEX idx_applied_date (applied_date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- Create leave_balance table for tracking employee leave balances
CREATE TABLE IF NOT EXISTS leave_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('casual_leave', 'sick_leave', 'paid_leave') NOT NULL,
    total_allocated INT DEFAULT 0,
    used_days INT DEFAULT 0,
    remaining_days INT DEFAULT 0,
    year YEAR NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_leave_year (employee_id, leave_type, year),
    INDEX idx_employee_id (employee_id),
    INDEX idx_year (year),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- Insert default leave balances for existing employees (you can modify these values as needed)
INSERT IGNORE INTO leave_balance (employee_id, leave_type, total_allocated, remaining_days, year)
SELECT 
    employee_id,
    'casual_leave' as leave_type,
    12 as total_allocated,
    12 as remaining_days,
    YEAR(CURDATE()) as year
FROM employees
UNION ALL
SELECT 
    employee_id,
    'sick_leave' as leave_type,
    10 as total_allocated,
    10 as remaining_days,
    YEAR(CURDATE()) as year
FROM employees
UNION ALL
SELECT 
    employee_id,
    'paid_leave' as leave_type,
    21 as total_allocated,
    21 as remaining_days,
    YEAR(CURDATE()) as year
FROM employees;

-- Create view for leave history with employee details
CREATE OR REPLACE VIEW leave_history_view AS
SELECT 
    l.id,
    l.employee_id,
    e.name as employee_name,
    e.employee_code,
    e.position,
    'leave' as request_type,
    l.leave_type as type_detail,
    l.start_date,
    l.end_date,
    NULL as permission_date,
    NULL as from_time,
    NULL as to_time,
    l.total_days,
    l.reason,
    l.contact_during_leave,
    l.status,
    l.applied_date,
    l.approved_date,
    l.rejection_reason,
    ab.name as applied_by_name,
    apb.name as approved_by_name
FROM leaves l
INNER JOIN employees e ON l.employee_id = e.employee_id
LEFT JOIN employees ab ON l.applied_by = ab.employee_id
LEFT JOIN employees apb ON l.approved_by = apb.employee_id

UNION ALL

SELECT 
    p.id,
    p.employee_id,
    e.name as employee_name,
    e.employee_code,
    e.position,
    'permission' as request_type,
    p.permission_type as type_detail,
    NULL as start_date,
    NULL as end_date,
    p.permission_date,
    p.from_time,
    p.to_time,
    NULL as total_days,
    p.reason,
    NULL as contact_during_leave,
    p.status,
    p.applied_date,
    p.approved_date,
    p.rejection_reason,
    ab.name as applied_by_name,
    apb.name as approved_by_name
FROM permissions p
INNER JOIN employees e ON p.employee_id = e.employee_id
LEFT JOIN employees ab ON p.applied_by = ab.employee_id
LEFT JOIN employees apb ON p.approved_by = apb.employee_id;

-- Add indexes for better performance
ALTER TABLE attendance ADD INDEX IF NOT EXISTS idx_attendance_date (attendance_date);
ALTER TABLE attendance ADD INDEX IF NOT EXISTS idx_employee_date (employee_id, attendance_date);
ALTER TABLE employees ADD INDEX IF NOT EXISTS idx_employee_code (employee_code);

-- Insert sample data (optional - remove if not needed)
-- INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status) 
-- VALUES (1, 'casual_leave', '2025-08-01', '2025-08-03', 3, 'Family function', 'pending');

-- INSERT INTO permissions (employee_id, permission_date, permission_type, from_time, to_time, reason, status)
-- VALUES (1, '2025-07-28', 'early_departure', '14:00:00', '18:00:00', 'Medical appointment', 'pending');
