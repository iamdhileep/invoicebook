-- ===============================================
-- BILLBOOK APPLICATION DATABASE SETUP
-- Complete database structure for all modules
-- ===============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS billing;
USE billing;

-- ===============================================
-- 1. USERS TABLE - Authentication & Admin Management
-- ===============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$nOUIs5kJ7naTuTFkBy1/eeFJWr29Zhc4qshNq3Le.Q1orY0c88q/e', 'admin')
ON DUPLICATE KEY UPDATE username=username;
-- Default password: admin123

-- ===============================================
-- 2. CATEGORIES TABLE - Product Categories Management
-- ===============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50) DEFAULT 'bi-tag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (name, description, color, icon) VALUES
('Electronics', 'Electronic devices and gadgets', '#007bff', 'bi-laptop'),
('Clothing', 'Apparel and fashion items', '#28a745', 'bi-bag'),
('Books', 'Books and educational materials', '#ffc107', 'bi-book'),
('Food & Beverages', 'Food items and drinks', '#fd7e14', 'bi-cup'),
('Home & Garden', 'Home improvement and garden items', '#20c997', 'bi-house'),
('Sports', 'Sports and fitness equipment', '#6f42c1', 'bi-trophy'),
('Toys & Games', 'Toys and gaming products', '#e83e8c', 'bi-controller'),
('Health & Beauty', 'Health and beauty products', '#17a2b8', 'bi-heart-pulse')
ON DUPLICATE KEY UPDATE name=name;

-- ===============================================
-- 3. ITEMS TABLE - Product/Inventory Management
-- ===============================================
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    item_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    stock INT DEFAULT 0,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    sku VARCHAR(50) DEFAULT NULL,
    barcode VARCHAR(100) DEFAULT NULL,
    min_stock INT DEFAULT 5,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for better performance
    INDEX idx_item_name (item_name),
    INDEX idx_category (category),
    INDEX idx_stock (stock),
    INDEX idx_status (status)
);

-- Insert sample items
INSERT INTO items (item_name, item_price, category, stock, description) VALUES
('Laptop Computer', 899.99, 'Electronics', 15, 'High-performance laptop for business and personal use'),
('Wireless Mouse', 29.99, 'Electronics', 50, 'Ergonomic wireless mouse with long battery life'),
('Office Chair', 199.99, 'Home & Garden', 8, 'Comfortable ergonomic office chair'),
('Coffee Mug', 12.99, 'Food & Beverages', 25, 'Ceramic coffee mug with company logo'),
('Notebook', 5.99, 'Books', 100, 'Spiral-bound notebook for notes and planning')
ON DUPLICATE KEY UPDATE item_name=item_name;

-- ===============================================
-- 4. EMPLOYEES TABLE - Employee Management
-- ===============================================
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE DEFAULT NULL,
    employee_name VARCHAR(255) NOT NULL,
    employee_code VARCHAR(50) UNIQUE DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    monthly_salary DECIMAL(10,2) DEFAULT 0.00,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    photo VARCHAR(500) DEFAULT NULL,
    joining_date DATE DEFAULT NULL,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_employee_code (employee_code),
    INDEX idx_employee_name (employee_name),
    INDEX idx_status (status)
);

-- Insert sample employees
INSERT INTO employees (employee_name, employee_code, position, monthly_salary, phone, email, joining_date) VALUES
('John Smith', 'EMP001', 'Manager', 5000.00, '+1234567890', 'john@company.com', '2024-01-15'),
('Sarah Johnson', 'EMP002', 'Sales Associate', 3000.00, '+1234567891', 'sarah@company.com', '2024-02-01'),
('Mike Davis', 'EMP003', 'Accountant', 4000.00, '+1234567892', 'mike@company.com', '2024-01-20')
ON DUPLICATE KEY UPDATE employee_name=employee_name;

-- ===============================================
-- 5. ATTENDANCE TABLE - Employee Attendance Tracking
-- ===============================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employee_code VARCHAR(50) DEFAULT NULL,
    date DATE NOT NULL,
    punch_in TIME DEFAULT NULL,
    punch_out TIME DEFAULT NULL,
    work_hours DECIMAL(4,2) DEFAULT 0.00,
    overtime_hours DECIMAL(4,2) DEFAULT 0.00,
    status ENUM('present', 'absent', 'late', 'half_day') DEFAULT 'absent',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Composite unique key to prevent duplicate entries
    UNIQUE KEY unique_employee_date (employee_id, date),
    
    -- Indexes
    INDEX idx_employee_id (employee_id),
    INDEX idx_date (date),
    INDEX idx_status (status),
    
    -- Foreign key constraint
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- ===============================================
-- 6. INVOICES TABLE - Invoice Management
-- ===============================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE DEFAULT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(100) DEFAULT NULL,
    customer_phone VARCHAR(20) DEFAULT NULL,
    customer_address TEXT DEFAULT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_customer_name (customer_name),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_status (status)
);

-- ===============================================
-- 7. INVOICE_ITEMS TABLE - Invoice Line Items
-- ===============================================
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_id INT DEFAULT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_item_id (item_id)
);

-- ===============================================
-- 8. EXPENSES TABLE - Expense Management
-- ===============================================
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(50) UNIQUE DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    note TEXT DEFAULT NULL, -- Alternative field name
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    expense_date DATE NOT NULL,
    receipt_path VARCHAR(500) DEFAULT NULL,
    bill_path VARCHAR(500) DEFAULT NULL, -- Alternative field name
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_expense_date (expense_date),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_amount (amount)
);

-- Insert sample expenses
INSERT INTO expenses (title, description, amount, category, payment_method, expense_date) VALUES
('Office Supplies', 'Pens, papers, and other office materials', 150.50, 'Office', 'card', CURDATE()),
('Internet Bill', 'Monthly internet service payment', 89.99, 'Utilities', 'bank_transfer', CURDATE()),
('Team Lunch', 'Monthly team building lunch', 250.00, 'Food', 'cash', CURDATE())
ON DUPLICATE KEY UPDATE title=title;

-- ===============================================
-- 9. STOCK_LOGS TABLE - Stock Movement Tracking
-- ===============================================
CREATE TABLE IF NOT EXISTS stock_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    action ENUM('add', 'remove', 'adjust', 'sale', 'return') NOT NULL,
    quantity_before INT DEFAULT 0,
    quantity_change INT NOT NULL DEFAULT 0,
    quantity_after INT DEFAULT 0,
    reason VARCHAR(255) DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL, -- 'invoice', 'manual', 'return', etc.
    reference_id INT DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_item_id (item_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ===============================================
-- 10. PAYROLL TABLE - Payroll Management
-- ===============================================
CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    basic_salary DECIMAL(10,2) DEFAULT 0.00,
    hra DECIMAL(10,2) DEFAULT 0.00,
    da DECIMAL(10,2) DEFAULT 0.00,
    allowances DECIMAL(10,2) DEFAULT 0.00,
    overtime_pay DECIMAL(10,2) DEFAULT 0.00,
    gross_salary DECIMAL(10,2) DEFAULT 0.00,
    pf_deduction DECIMAL(10,2) DEFAULT 0.00,
    esi_deduction DECIMAL(10,2) DEFAULT 0.00,
    tax_deduction DECIMAL(10,2) DEFAULT 0.00,
    other_deductions DECIMAL(10,2) DEFAULT 0.00,
    total_deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) DEFAULT 0.00,
    days_worked INT DEFAULT 0,
    days_absent INT DEFAULT 0,
    overtime_hours DECIMAL(4,2) DEFAULT 0.00,
    status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_employee_id (employee_id),
    INDEX idx_pay_period (pay_period_start, pay_period_end),
    INDEX idx_status (status)
);

-- ===============================================
-- 11. SETTINGS TABLE - Application Settings
-- ===============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'Business Manager', 'string', 'Company name displayed in the application'),
('company_address', '123 Business Street, City, State 12345', 'string', 'Company address for invoices and reports'),
('company_phone', '+1 (555) 123-4567', 'string', 'Company phone number'),
('company_email', 'contact@company.com', 'string', 'Company email address'),
('tax_rate', '10', 'number', 'Default tax rate percentage'),
('currency_symbol', '$', 'string', 'Currency symbol for monetary values'),
('low_stock_threshold', '10', 'number', 'Minimum stock level before low stock alert'),
('invoice_prefix', 'INV-', 'string', 'Prefix for invoice numbers'),
('expense_prefix', 'EXP-', 'string', 'Prefix for expense numbers')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- ===============================================
-- CREATE VIEWS FOR BETTER DATA ACCESS
-- ===============================================

-- View for items with category information
CREATE OR REPLACE VIEW items_with_category AS
SELECT 
    i.*,
    c.color as category_color,
    c.icon as category_icon,
    c.description as category_description,
    CASE 
        WHEN i.stock <= i.min_stock THEN 'low'
        WHEN i.stock = 0 THEN 'out'
        ELSE 'normal'
    END as stock_status
FROM items i
LEFT JOIN categories c ON i.category = c.name
WHERE i.status = 'active';

-- View for employee attendance summary
CREATE OR REPLACE VIEW employee_attendance_summary AS
SELECT 
    e.id,
    e.employee_name,
    e.employee_code,
    COUNT(a.id) as total_days,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
    SUM(a.work_hours) as total_work_hours,
    SUM(a.overtime_hours) as total_overtime_hours
FROM employees e
LEFT JOIN attendance a ON e.id = a.employee_id
WHERE e.status = 'active'
GROUP BY e.id, e.employee_name, e.employee_code;

-- View for invoice summary
CREATE OR REPLACE VIEW invoice_summary AS
SELECT 
    i.*,
    COUNT(ii.id) as item_count,
    (i.total_amount - i.paid_amount) as balance_due,
    CASE 
        WHEN i.status = 'paid' THEN 'Paid'
        WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN 'Overdue'
        WHEN i.due_date = CURDATE() AND i.status != 'paid' THEN 'Due Today'
        ELSE 'Pending'
    END as payment_status
FROM invoices i
LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
GROUP BY i.id;

-- ===============================================
-- CREATE TRIGGERS FOR AUTOMATION
-- ===============================================

-- Trigger to update invoice totals when items are added/updated
DELIMITER $$
CREATE TRIGGER update_invoice_total 
AFTER INSERT ON invoice_items 
FOR EACH ROW
BEGIN
    UPDATE invoices 
    SET subtotal = (
        SELECT COALESCE(SUM(line_total), 0) 
        FROM invoice_items 
        WHERE invoice_id = NEW.invoice_id
    ),
    total_amount = subtotal + tax_amount - discount_amount
    WHERE id = NEW.invoice_id;
END$$

CREATE TRIGGER update_invoice_total_on_update
AFTER UPDATE ON invoice_items 
FOR EACH ROW
BEGIN
    UPDATE invoices 
    SET subtotal = (
        SELECT COALESCE(SUM(line_total), 0) 
        FROM invoice_items 
        WHERE invoice_id = NEW.invoice_id
    ),
    total_amount = subtotal + tax_amount - discount_amount
    WHERE id = NEW.invoice_id;
END$$

CREATE TRIGGER update_invoice_total_on_delete
AFTER DELETE ON invoice_items 
FOR EACH ROW
BEGIN
    UPDATE invoices 
    SET subtotal = (
        SELECT COALESCE(SUM(line_total), 0) 
        FROM invoice_items 
        WHERE invoice_id = OLD.invoice_id
    ),
    total_amount = subtotal + tax_amount - discount_amount
    WHERE id = OLD.invoice_id;
END$$
DELIMITER ;

-- ===============================================
-- FINAL SETUP COMPLETION MESSAGE
-- ===============================================
SELECT 'Database setup completed successfully!' as Status;
SELECT 'All tables, views, and triggers have been created.' as Info;
SELECT 'Default admin user: admin / admin123' as Login;