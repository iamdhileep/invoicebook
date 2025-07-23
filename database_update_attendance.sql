-- Database update for Advanced Attendance Management
-- Add break tracking columns to attendance table

-- Check if columns exist and add them if they don't
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS break_start DATETIME NULL,
ADD COLUMN IF NOT EXISTS break_end DATETIME NULL;

-- Create index for better performance on attendance queries
CREATE INDEX IF NOT EXISTS idx_attendance_date_employee ON attendance(attendance_date, employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_punch_status ON attendance(time_in, time_out);

-- Update existing records to have proper status based on punch times
UPDATE attendance 
SET status = CASE 
    WHEN time_in IS NOT NULL AND TIME(time_in) > '09:00:00' THEN 'Late'
    WHEN time_in IS NOT NULL THEN 'Present'
    ELSE status
END
WHERE status IS NULL OR status = '';

-- Optional: Create a view for attendance summary
CREATE OR REPLACE VIEW attendance_summary AS
SELECT 
    e.employee_id,
    e.name,
    e.employee_code,
    e.position,
    a.attendance_date,
    a.status,
    a.time_in,
    a.time_out,
    a.break_start,
    a.break_end,
    CASE 
        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
        THEN TIMEDIFF(a.time_out, a.time_in)
        ELSE NULL
    END as total_time,
    CASE 
        WHEN a.break_start IS NOT NULL AND a.break_end IS NOT NULL 
        THEN TIMEDIFF(a.break_end, a.break_start)
        ELSE NULL
    END as break_duration,
    CASE 
        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL AND a.break_start IS NOT NULL AND a.break_end IS NOT NULL
        THEN TIMEDIFF(TIMEDIFF(a.time_out, a.time_in), TIMEDIFF(a.break_end, a.break_start))
        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL
        THEN TIMEDIFF(a.time_out, a.time_in)
        ELSE NULL
    END as work_duration,
    CASE 
        WHEN a.time_in IS NOT NULL AND a.time_out IS NULL THEN 'Active'
        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN 'Completed'
        WHEN a.break_start IS NOT NULL AND a.break_end IS NULL THEN 'On Break'
        ELSE 'Not Started'
    END as punch_status
FROM employees e
LEFT JOIN attendance a ON e.employee_id = a.employee_id
ORDER BY e.name ASC; 