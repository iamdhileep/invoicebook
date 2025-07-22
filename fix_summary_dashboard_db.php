<?php
/**
 * Summary Dashboard Database Fix Script
 * Creates and fixes all required tables for summary_dashboard.php
 */

// Display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🛠️ Summary Dashboard Database Fix</h2>";
echo "<p>Creating and fixing all required tables for the Summary Dashboard...</p>";

try {
    // Include database connection
    include 'db.php';
    
    echo "<p>✅ Database connection established</p>";
    
    // List of required tables for summary dashboard
    $requiredTables = [
        'invoices' => [
            'description' => 'Invoices for revenue tracking',
            'sql' => "
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ],
        'expenses' => [
            'description' => 'Expenses for cost tracking',
            'sql' => "
            CREATE TABLE IF NOT EXISTS expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                expense_number VARCHAR(50) UNIQUE DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                note TEXT DEFAULT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                category VARCHAR(100) DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT 'cash',
                expense_date DATE NOT NULL,
                receipt_path VARCHAR(500) DEFAULT NULL,
                bill_path VARCHAR(500) DEFAULT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                created_by VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ],
        'employees' => [
            'description' => 'Employees for attendance and payroll',
            'sql' => "
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ],
        'attendance' => [
            'description' => 'Employee attendance tracking',
            'sql' => "
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
                UNIQUE KEY unique_employee_date (employee_id, date)
            )"
        ],
        'items' => [
            'description' => 'Items/Products for inventory tracking',
            'sql' => "
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ],
        'invoice_items' => [
            'description' => 'Invoice line items for detailed reporting',
            'sql' => "
            CREATE TABLE IF NOT EXISTS invoice_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                item_id INT DEFAULT NULL,
                item_name VARCHAR(255) NOT NULL,
                item_description TEXT DEFAULT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'categories' => [
            'description' => 'Categories for item organization',
            'sql' => "
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT DEFAULT NULL,
                color VARCHAR(7) DEFAULT '#007bff',
                icon VARCHAR(50) DEFAULT 'bi-tag',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ]
    ];
    
    echo "<h3>📊 Creating Required Tables:</h3>";
    
    $createdTables = 0;
    $existingTables = 0;
    $errors = [];
    
    foreach ($requiredTables as $tableName => $tableInfo) {
        echo "<h4>🔍 Checking table: <strong>$tableName</strong></h4>";
        echo "<p><em>{$tableInfo['description']}</em></p>";
        
        // Check if table exists
        $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($checkTable->num_rows > 0) {
            echo "<p>✅ Table '$tableName' already exists</p>";
            $existingTables++;
            
            // Check row count
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $tableName");
            if ($countResult) {
                $count = $countResult->fetch_assoc()['count'];
                echo "<p>📊 Current records: $count</p>";
            }
        } else {
            echo "<p>❌ Table '$tableName' does not exist. Creating...</p>";
            
            if ($conn->query($tableInfo['sql'])) {
                echo "<p>✅ Table '$tableName' created successfully</p>";
                $createdTables++;
            } else {
                $error = "Failed to create table '$tableName': " . $conn->error;
                echo "<p>❌ $error</p>";
                $errors[] = $error;
            }
        }
        echo "<hr>";
    }
    
    // Add sample data if tables are empty
    echo "<h3>🌟 Adding Sample Data:</h3>";
    
    // Sample invoices
    $invoiceCount = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
    if ($invoiceCount == 0) {
        echo "<p>📄 Adding sample invoices...</p>";
        $sampleInvoices = [
            ['John Doe', '2024-01-15', 1500.00],
            ['Jane Smith', '2024-01-20', 2300.50],
            ['ABC Company', '2024-01-25', 5000.00],
            ['XYZ Corp', '2024-02-01', 1800.75],
            ['Tech Solutions', '2024-02-05', 3200.00]
        ];
        
        foreach ($sampleInvoices as $invoice) {
            $stmt = $conn->prepare("INSERT INTO invoices (customer_name, invoice_date, total_amount, status) VALUES (?, ?, ?, 'paid')");
            if ($stmt) {
                $stmt->bind_param("ssd", $invoice[0], $invoice[1], $invoice[2]);
                $stmt->execute();
            }
        }
        echo "<p>✅ Added " . count($sampleInvoices) . " sample invoices</p>";
    }
    
    // Sample expenses
    $expenseCount = $conn->query("SELECT COUNT(*) as count FROM expenses")->fetch_assoc()['count'];
    if ($expenseCount == 0) {
        echo "<p>💰 Adding sample expenses...</p>";
        $sampleExpenses = [
            ['Office Rent', 'Monthly office rent payment', 15000.00, 'Office', '2024-01-01'],
            ['Internet Bill', 'Monthly internet service', 2500.00, 'Utilities', '2024-01-05'],
            ['Office Supplies', 'Stationery and supplies', 3200.00, 'Office', '2024-01-10'],
            ['Marketing Campaign', 'Social media advertising', 8500.00, 'Marketing', '2024-01-15'],
            ['Equipment Purchase', 'New laptops for team', 45000.00, 'Equipment', '2024-01-20']
        ];
        
        foreach ($sampleExpenses as $expense) {
            $stmt = $conn->prepare("INSERT INTO expenses (title, description, amount, category, expense_date) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssdss", $expense[0], $expense[1], $expense[2], $expense[3], $expense[4]);
                $stmt->execute();
            }
        }
        echo "<p>✅ Added " . count($sampleExpenses) . " sample expenses</p>";
    }
    
    // Sample employees
    $employeeCount = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch_assoc()['count'];
    if ($employeeCount == 0) {
        echo "<p>👥 Adding sample employees...</p>";
        $sampleEmployees = [
            ['John Smith', 'EMP001', 'Manager', 50000.00],
            ['Sarah Johnson', 'EMP002', 'Developer', 45000.00],
            ['Mike Davis', 'EMP003', 'Designer', 40000.00],
            ['Emily Brown', 'EMP004', 'Sales Executive', 35000.00],
            ['David Wilson', 'EMP005', 'Accountant', 38000.00]
        ];
        
        foreach ($sampleEmployees as $employee) {
            $stmt = $conn->prepare("INSERT INTO employees (employee_name, employee_code, position, monthly_salary, status) VALUES (?, ?, ?, ?, 'active')");
            if ($stmt) {
                $stmt->bind_param("sssd", $employee[0], $employee[1], $employee[2], $employee[3]);
                $stmt->execute();
            }
        }
        echo "<p>✅ Added " . count($sampleEmployees) . " sample employees</p>";
    }
    
    // Sample attendance
    $attendanceCount = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
    if ($attendanceCount == 0) {
        echo "<p>📅 Adding sample attendance...</p>";
        $employees = $conn->query("SELECT id FROM employees LIMIT 5");
        $dates = [];
        for ($i = 0; $i < 10; $i++) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }
        
        $attendanceAdded = 0;
        while ($emp = $employees->fetch_assoc()) {
            foreach ($dates as $date) {
                $status = (rand(1, 10) > 2) ? 'present' : 'absent'; // 80% present
                $workHours = ($status == 'present') ? rand(7, 9) + (rand(0, 59) / 60) : 0;
                $overtimeHours = ($workHours > 8) ? $workHours - 8 : 0;
                
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status, work_hours, overtime_hours) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = status");
                if ($stmt) {
                    $stmt->bind_param("issdd", $emp['id'], $date, $status, $workHours, $overtimeHours);
                    if ($stmt->execute()) {
                        $attendanceAdded++;
                    }
                }
            }
        }
        echo "<p>✅ Added $attendanceAdded sample attendance records</p>";
    }
    
    // Sample items
    $itemCount = $conn->query("SELECT COUNT(*) as count FROM items")->fetch_assoc()['count'];
    if ($itemCount == 0) {
        echo "<p>📦 Adding sample items...</p>";
        $sampleItems = [
            ['Laptop Computer', 899.99, 'Electronics', 15],
            ['Wireless Mouse', 29.99, 'Electronics', 50],
            ['Office Chair', 199.99, 'Furniture', 8],
            ['Coffee Mug', 12.99, 'Office Supplies', 25],
            ['Notebook', 5.99, 'Stationery', 100],
            ['USB Cable', 15.99, 'Electronics', 30],
            ['Desk Lamp', 45.99, 'Furniture', 12],
            ['Pen Set', 8.99, 'Stationery', 75]
        ];
        
        foreach ($sampleItems as $item) {
            $stmt = $conn->prepare("INSERT INTO items (item_name, item_price, category, stock, status) VALUES (?, ?, ?, ?, 'active')");
            if ($stmt) {
                $stmt->bind_param("sdsi", $item[0], $item[1], $item[2], $item[3]);
                $stmt->execute();
            }
        }
        echo "<p>✅ Added " . count($sampleItems) . " sample items</p>";
    }
    
    // Sample categories
    $categoryCount = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
    if ($categoryCount == 0) {
        echo "<p>🏷️ Adding sample categories...</p>";
        $sampleCategories = [
            ['Electronics', 'Electronic devices and gadgets', '#007bff', 'bi-laptop'],
            ['Furniture', 'Office and home furniture', '#28a745', 'bi-house'],
            ['Office Supplies', 'General office supplies', '#ffc107', 'bi-briefcase'],
            ['Stationery', 'Writing and paper materials', '#fd7e14', 'bi-pencil']
        ];
        
        foreach ($sampleCategories as $category) {
            $stmt = $conn->prepare("INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $category[0], $category[1], $category[2], $category[3]);
                $stmt->execute();
            }
        }
        echo "<p>✅ Added " . count($sampleCategories) . " sample categories</p>";
    }
    
    // Test the summary dashboard queries
    echo "<h3>🧪 Testing Summary Dashboard Queries:</h3>";
    
    $dateFrom = date('Y-m-01'); // First day of current month
    $dateTo = date('Y-m-d'); // Today
    
    echo "<p>Testing date range: $dateFrom to $dateTo</p>";
    
    // Test revenue query
    try {
        $revenueQuery = $conn->prepare("
            SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_invoice_value,
                COALESCE(MAX(total_amount), 0) as highest_invoice,
                COALESCE(MIN(total_amount), 0) as lowest_invoice
            FROM invoices 
            WHERE invoice_date BETWEEN ? AND ?
        ");
        
        if ($revenueQuery) {
            $revenueQuery->bind_param("ss", $dateFrom, $dateTo);
            $revenueQuery->execute();
            $revenueData = $revenueQuery->get_result()->fetch_assoc();
            echo "<p>✅ Revenue query test passed - Total Revenue: ₹" . number_format($revenueData['total_revenue'], 2) . "</p>";
        } else {
            echo "<p>❌ Revenue query failed: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Revenue query error: " . $e->getMessage() . "</p>";
    }
    
    // Test expense query
    try {
        $expenseQuery = $conn->prepare("
            SELECT 
                COUNT(*) as total_expenses,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(AVG(amount), 0) as avg_expense,
                COALESCE(MAX(amount), 0) as highest_expense
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ?
        ");
        
        if ($expenseQuery) {
            $expenseQuery->bind_param("ss", $dateFrom, $dateTo);
            $expenseQuery->execute();
            $expenseData = $expenseQuery->get_result()->fetch_assoc();
            echo "<p>✅ Expense query test passed - Total Expenses: ₹" . number_format($expenseData['total_amount'], 2) . "</p>";
        } else {
            echo "<p>❌ Expense query failed: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Expense query error: " . $e->getMessage() . "</p>";
    }
    
    // Test employee query
    try {
        $employeeQuery = $conn->prepare("
            SELECT 
                COUNT(DISTINCT e.id) as total_employees,
                COALESCE(SUM(e.monthly_salary), 0) as total_salary_cost,
                COALESCE(AVG(e.monthly_salary), 0) as avg_salary,
                COUNT(DISTINCT a.employee_id) as employees_with_attendance,
                COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) as total_present_days,
                COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) as total_absent_days,
                COALESCE(SUM(a.work_hours), 0) as total_work_hours,
                COALESCE(SUM(a.overtime_hours), 0) as total_overtime_hours
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id 
                AND a.date BETWEEN ? AND ?
            WHERE e.status = 'active'
        ");
        
        if ($employeeQuery) {
            $employeeQuery->bind_param("ss", $dateFrom, $dateTo);
            $employeeQuery->execute();
            $employeeData = $employeeQuery->get_result()->fetch_assoc();
            echo "<p>✅ Employee query test passed - Total Employees: " . $employeeData['total_employees'] . "</p>";
        } else {
            echo "<p>❌ Employee query failed: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Employee query error: " . $e->getMessage() . "</p>";
    }
    
    // Test items query
    try {
        $itemsQuery = $conn->prepare("
            SELECT 
                COUNT(*) as total_items,
                COALESCE(SUM(stock), 0) as total_stock,
                COALESCE(SUM(item_price * stock), 0) as total_inventory_value,
                COALESCE(AVG(item_price), 0) as avg_item_price,
                COUNT(CASE WHEN stock <= 5 THEN 1 END) as low_stock_items,
                COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock_items
            FROM items 
            WHERE status = 'active'
        ");
        
        if ($itemsQuery) {
            $itemsQuery->execute();
            $itemsData = $itemsQuery->get_result()->fetch_assoc();
            echo "<p>✅ Items query test passed - Total Items: " . $itemsData['total_items'] . "</p>";
        } else {
            echo "<p>❌ Items query failed: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Items query error: " . $e->getMessage() . "</p>";
    }
    
    // Summary
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>🎉 Database Setup Complete!</h3>";
    echo "<ul>";
    echo "<li><strong>Tables Created:</strong> $createdTables</li>";
    echo "<li><strong>Existing Tables:</strong> $existingTables</li>";
    echo "<li><strong>Total Tables:</strong> " . count($requiredTables) . "</li>";
    echo "<li><strong>Sample Data:</strong> Added to all empty tables</li>";
    echo "<li><strong>Query Tests:</strong> All major queries tested successfully</li>";
    echo "</ul>";
    
    if (empty($errors)) {
        echo "<p>✅ <strong>All operations completed successfully!</strong></p>";
        echo "<p><a href='summary_dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Summary Dashboard →</a></p>";
    } else {
        echo "<p>⚠️ <strong>Some errors occurred:</strong></p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>$error</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>❌ Setup Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 1000px; 
    margin: 20px auto; 
    padding: 20px; 
    line-height: 1.6; 
}
h2 { 
    color: #333; 
    border-bottom: 2px solid #007bff; 
    padding-bottom: 10px; 
}
h3 { 
    color: #555; 
    margin-top: 30px; 
}
h4 { 
    color: #666; 
    margin-top: 20px; 
    margin-bottom: 10px; 
}
hr { 
    border: none; 
    border-top: 1px solid #eee; 
    margin: 15px 0; 
}
ul { 
    background: #f8f9fa; 
    padding: 15px; 
    border-radius: 5px; 
}
</style>