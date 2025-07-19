CREATE DATABASE IF NOT EXISTS invoice_db;
USE invoice_db;

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_contact VARCHAR(100),
    invoice_date DATE,
    items TEXT,
    total_amount DECIMAL(10,2)
);
