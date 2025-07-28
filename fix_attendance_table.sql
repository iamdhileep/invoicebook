-- Fix attendance table structure for form compatibility
-- Run this SQL to ensure the attendance table has the correct columns

-- First, create the table with correct structure if it doesn't exist
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
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If the table exists with wrong column names, let's try to fix it
-- Check if old columns exist and migrate data

-- Add new columns if they don't exist
ALTER TABLE `attendance` 
ADD COLUMN IF NOT EXISTS `attendance_date` date NOT NULL AFTER `employee_id`,
ADD COLUMN IF NOT EXISTS `time_in` time DEFAULT NULL AFTER `attendance_date`,
ADD COLUMN IF NOT EXISTS `time_out` time DEFAULT NULL AFTER `time_in`,
ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL AFTER `status`;

-- Copy data from old columns to new columns if they exist
UPDATE `attendance` SET 
    `attendance_date` = COALESCE(`attendance_date`, `date`),
    `time_in` = COALESCE(`time_in`, `punch_in_time`),
    `time_out` = COALESCE(`time_out`, `punch_out_time`),
    `notes` = COALESCE(`notes`, `remarks`)
WHERE `attendance_date` IS NULL OR `time_in` IS NULL OR `time_out` IS NULL OR `notes` IS NULL;

-- Create unique index if it doesn't exist
CREATE UNIQUE INDEX IF NOT EXISTS `unique_employee_date` ON `attendance` (`employee_id`, `attendance_date`);

-- Create other indexes for performance
CREATE INDEX IF NOT EXISTS `idx_attendance_date` ON `attendance` (`attendance_date`);
CREATE INDEX IF NOT EXISTS `idx_attendance_status` ON `attendance` (`status`);

SELECT 'Attendance table structure fixed successfully!' as result;
