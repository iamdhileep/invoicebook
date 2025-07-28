-- Performance Optimization Database Indexes
-- These indexes will significantly improve query performance

-- 1. Attendance table indexes for faster employee-date lookups
CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON attendance (employee_id, attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance (attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance (employee_id);

-- 2. Employee table indexes for status and search operations
CREATE INDEX IF NOT EXISTS idx_employees_status ON employees (status);
CREATE INDEX IF NOT EXISTS idx_employees_name ON employees (name);
CREATE INDEX IF NOT EXISTS idx_employees_code ON employees (employee_code);

-- 3. Leave requests indexes for faster approval workflows
CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_requests (status);
CREATE INDEX IF NOT EXISTS idx_leave_employee ON leave_requests (employee_id);
CREATE INDEX IF NOT EXISTS idx_leave_dates ON leave_requests (start_date, end_date);

-- 4. Composite indexes for complex queries
CREATE INDEX IF NOT EXISTS idx_attendance_composite ON attendance (employee_id, attendance_date, punch_in_time);
CREATE INDEX IF NOT EXISTS idx_leave_composite ON leave_requests (employee_id, status, start_date);

-- 5. Performance monitoring - show index creation results
SELECT 
    'Performance indexes created successfully' as status,
    COUNT(*) as total_indexes
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name IN ('attendance', 'employees', 'leave_requests');
