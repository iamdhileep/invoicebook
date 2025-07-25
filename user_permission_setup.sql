-- User Permission System Database Setup
-- Run this SQL to create the necessary tables for user management and permissions

-- Create users table if it doesn't exist
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `permissions` text,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_logs table for tracking user activities
CREATE TABLE IF NOT EXISTS `user_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_sessions table for managing user sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `permissions`) VALUES
('admin', 'admin@billbook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'dashboard,employees,attendance,invoices,items,reports,settings,export,bulk_actions');

-- Insert sample users with different roles and permissions
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `permissions`) VALUES
('manager', 'manager@billbook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'dashboard,employees,attendance,reports,export'),
('user', 'user@billbook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'dashboard,attendance'),
('hr_user', 'hr@billbook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'dashboard,employees,attendance,reports'),
('accountant', 'accountant@billbook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'dashboard,invoices,items,reports,export');

-- Update existing admin table structure if it exists (backup compatibility)
-- This will help if you have an existing admin table
-- ALTER TABLE `admin` ADD COLUMN `role` enum('admin','manager','user') DEFAULT 'admin' AFTER `password`;
-- ALTER TABLE `admin` ADD COLUMN `permissions` text AFTER `role`;
-- ALTER TABLE `admin` ADD COLUMN `remember_token` varchar(100) DEFAULT NULL AFTER `permissions`;
-- ALTER TABLE `admin` ADD COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `remember_token`;
-- ALTER TABLE `admin` ADD COLUMN `last_login` timestamp NULL DEFAULT NULL AFTER `created_at`;

-- Create permissions reference table (optional - for documentation)
CREATE TABLE IF NOT EXISTS `permissions_reference` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(50) NOT NULL UNIQUE,
  `permission_name` varchar(100) NOT NULL,
  `description` text,
  `category` varchar(50),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_permission_key` (`permission_key`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert available permissions into reference table
INSERT IGNORE INTO `permissions_reference` (`permission_key`, `permission_name`, `description`, `category`) VALUES
('dashboard', 'Dashboard Access', 'Access to main dashboard and overview', 'core'),
('employees', 'Employee Management', 'Add, edit, delete employees', 'hr'),
('attendance', 'Attendance Management', 'Manage employee attendance', 'hr'),
('invoices', 'Invoice Management', 'Create and manage invoices', 'finance'),
('items', 'Item Management', 'Manage products and inventory', 'inventory'),
('reports', 'Reports Access', 'View and generate reports', 'reporting'),
('settings', 'Settings Access', 'Access system settings and user management', 'admin'),
('export', 'Export Data', 'Export data to various formats', 'utility'),
('bulk_actions', 'Bulk Actions', 'Perform bulk operations', 'utility');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_users_role_status` ON `users`(`role`, `status`);
CREATE INDEX IF NOT EXISTS `idx_user_logs_user_action` ON `user_logs`(`user_id`, `action`);

-- Sample data cleanup (remove users with default passwords in production)
-- DELETE FROM users WHERE username IN ('admin', 'manager', 'user', 'hr_user', 'accountant');

-- Show created tables
SHOW TABLES LIKE '%user%';
SHOW TABLES LIKE '%permission%';
