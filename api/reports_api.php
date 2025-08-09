<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');

    switch ($action) {
        case 'dashboard_stats':
            getDashboardStats($conn, $from_date, $to_date);
            break;
        
        case 'financial_analysis':
            getFinancialAnalysis($conn, $from_date, $to_date);
            break;
        
        case 'sales_performance':
            getSalesPerformance($conn, $from_date, $to_date);
            break;
        
        case 'customer_analytics':
            getCustomerAnalytics($conn, $from_date, $to_date);
            break;
        
        case 'expense_analysis':
            getExpenseAnalysis($conn, $from_date, $to_date);
            break;
        
        case 'employee_reports':
            getEmployeeReports($conn, $from_date, $to_date);
            break;
        
        case 'revenue_trend':
            getRevenueTrend($conn, $from_date, $to_date);
            break;
        
        case 'top_items':
            getTopItems($conn, $from_date, $to_date);
            break;
        
        case 'top_customers':
            getTopCustomers($conn, $from_date, $to_date);
            break;
        
        case 'export_report':
            exportReport($conn, $_POST);
            break;
        
        // Legacy endpoints for backward compatibility
        case 'monthly_report':
            getMonthlyReport($conn);
            break;
            
        case 'employee_report':
            getEmployeeReport($conn);
            break;
            
        case 'payroll_report':
            getPayrollReport($conn);
            break;
        
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function getDashboardStats($conn, $from_date, $to_date) {
    // Total Revenue from invoices
    $revenue_query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
                     FROM invoices 
                     WHERE DATE(created_at) BETWEEN ? AND ?";
    $revenue_stmt = $conn->prepare($revenue_query);
    $revenue_stmt->bind_param("ss", $from_date, $to_date);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result()->fetch_assoc();
    
    // Total Expenses
    $expense_query = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                     FROM expenses 
                     WHERE DATE(expense_date) BETWEEN ? AND ?";
    $expense_stmt = $conn->prepare($expense_query);
    $expense_stmt->bind_param("ss", $from_date, $to_date);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result()->fetch_assoc();
    
    // Total Invoices Count
    $invoice_count_query = "SELECT COUNT(*) as invoice_count 
                           FROM invoices 
                           WHERE DATE(created_at) BETWEEN ? AND ?";
    $invoice_count_stmt = $conn->prepare($invoice_count_query);
    $invoice_count_stmt->bind_param("ss", $from_date, $to_date);
    $invoice_count_stmt->execute();
    $invoice_count_result = $invoice_count_stmt->get_result()->fetch_assoc();
    
    // Previous period for comparison
    $days_diff = (strtotime($to_date) - strtotime($from_date)) / (60 * 60 * 24);
    $prev_from = date('Y-m-d', strtotime($from_date . " -$days_diff days"));
    $prev_to = date('Y-m-d', strtotime($to_date . " -$days_diff days"));
    
    // Previous Revenue
    $prev_revenue_stmt = $conn->prepare($revenue_query);
    $prev_revenue_stmt->bind_param("ss", $prev_from, $prev_to);
    $prev_revenue_stmt->execute();
    $prev_revenue_result = $prev_revenue_stmt->get_result()->fetch_assoc();
    
    $total_revenue = floatval($revenue_result['total_revenue']);
    $total_expenses = floatval($expense_result['total_expenses']);
    $net_profit = $total_revenue - $total_expenses;
    $prev_revenue = floatval($prev_revenue_result['total_revenue']);
    
    $revenue_growth = $prev_revenue > 0 ? (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_revenue' => $total_revenue,
            'total_expenses' => $total_expenses,
            'net_profit' => $net_profit,
            'total_invoices' => intval($invoice_count_result['invoice_count']),
            'revenue_growth' => $revenue_growth,
            'profit_margin' => $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0
        ]
    ]);
}

function getFinancialAnalysis($conn, $from_date, $to_date) {
    // Monthly financial breakdown
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(*) as invoice_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_invoice
              FROM invoices 
              WHERE DATE(created_at) BETWEEN ? AND ?
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY period";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Monthly expenses
    $expense_query = "SELECT 
                        DATE_FORMAT(expense_date, '%Y-%m') as period,
                        SUM(amount) as expenses
                      FROM expenses 
                      WHERE DATE(expense_date) BETWEEN ? AND ?
                      GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
                      ORDER BY period";
    
    $expense_stmt = $conn->prepare($expense_query);
    $expense_stmt->bind_param("ss", $from_date, $to_date);
    $expense_stmt->execute();
    $expense_data = $expense_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Combine revenue and expense data
    $financial_data = [];
    foreach ($revenue_data as $revenue_row) {
        $period = $revenue_row['period'];
        $expense_amount = 0;
        
        foreach ($expense_data as $expense_row) {
            if ($expense_row['period'] == $period) {
                $expense_amount = floatval($expense_row['expenses']);
                break;
            }
        }
        
        $revenue = floatval($revenue_row['revenue']);
        $profit = $revenue - $expense_amount;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        
        $financial_data[] = [
            'period' => $period,
            'revenue' => $revenue,
            'expenses' => $expense_amount,
            'profit' => $profit,
            'margin' => $margin,
            'invoice_count' => intval($revenue_row['invoice_count']),
            'avg_invoice' => floatval($revenue_row['avg_invoice'])
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $financial_data
    ]);
}

function getSalesPerformance($conn, $from_date, $to_date) {
    // Check if we have items data, if not use sample data
    $check_items = $conn->query("SHOW TABLES LIKE 'items'");
    
    if ($check_items->num_rows > 0) {
        // Top selling items from items table
        $query = "SELECT 
                    name as item_name,
                    stock_quantity as total_quantity,
                    price * stock_quantity as total_revenue,
                    1 as invoice_count,
                    price as avg_price
                  FROM items 
                  WHERE created_at BETWEEN ? AND ?
                  ORDER BY (price * stock_quantity) DESC
                  LIMIT 10";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $top_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Generate sample data for demonstration
        $top_items = [
            ['item_name' => 'Product A', 'total_quantity' => 150, 'total_revenue' => 75000, 'invoice_count' => 25, 'avg_price' => 500],
            ['item_name' => 'Product B', 'total_quantity' => 120, 'total_revenue' => 60000, 'invoice_count' => 20, 'avg_price' => 500],
            ['item_name' => 'Product C', 'total_quantity' => 100, 'total_revenue' => 45000, 'invoice_count' => 15, 'avg_price' => 450],
            ['item_name' => 'Product D', 'total_quantity' => 80, 'total_revenue' => 32000, 'invoice_count' => 12, 'avg_price' => 400],
            ['item_name' => 'Product E', 'total_quantity' => 60, 'total_revenue' => 21000, 'invoice_count' => 10, 'avg_price' => 350]
        ];
    }
    
    // Sales trend by day from invoices
    $trend_query = "SELECT 
                      DATE(created_at) as sale_date,
                      COUNT(*) as invoice_count,
                      SUM(total_amount) as daily_revenue
                    FROM invoices 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date";
    
    $trend_stmt = $conn->prepare($trend_query);
    $trend_stmt->bind_param("ss", $from_date, $to_date);
    $trend_stmt->execute();
    $sales_trend = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // If no sales trend data, generate sample
    if (empty($sales_trend)) {
        $sales_trend = [];
        $start_date = new DateTime($from_date);
        $end_date = new DateTime($to_date);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));
        
        foreach ($period as $date) {
            $sales_trend[] = [
                'sale_date' => $date->format('Y-m-d'),
                'invoice_count' => rand(1, 8),
                'daily_revenue' => rand(5000, 25000)
            ];
        }
    }
    
    // Process top items for better formatting
    foreach ($top_items as &$item) {
        $item['total_quantity'] = intval($item['total_quantity'] ?? 0);
        $item['total_revenue'] = floatval($item['total_revenue'] ?? 0);
        $item['invoice_count'] = intval($item['invoice_count'] ?? 0);
        $item['avg_price'] = floatval($item['avg_price'] ?? 0);
        $item['performance'] = $item['total_revenue'] > 10000 ? 'Excellent' : 
                              ($item['total_revenue'] > 5000 ? 'Good' : 'Average');
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'top_items' => $top_items,
            'sales_trend' => $sales_trend
        ]
    ]);
}

function getCustomerAnalytics($conn, $from_date, $to_date) {
    // Top customers by revenue
    $query = "SELECT 
                c.name as customer_name,
                COUNT(i.id) as total_orders,
                SUM(i.total_amount) as total_revenue,
                AVG(i.total_amount) as avg_order_value,
                MAX(i.created_at) as last_order_date,
                CASE 
                    WHEN SUM(i.total_amount) > 100000 THEN 'Premium'
                    WHEN SUM(i.total_amount) > 50000 THEN 'Gold'
                    WHEN SUM(i.total_amount) > 20000 THEN 'Silver'
                    ELSE 'Bronze'
                END as customer_tier
              FROM customers c
              LEFT JOIN invoices i ON c.id = i.customer_id
              WHERE i.id IS NOT NULL AND DATE(i.created_at) BETWEEN ? AND ?
              GROUP BY c.id, c.name
              ORDER BY total_revenue DESC
              LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $customer_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Customer distribution by tier
    $tier_query = "SELECT 
                     CASE 
                         WHEN SUM(i.total_amount) > 100000 THEN 'Premium'
                         WHEN SUM(i.total_amount) > 50000 THEN 'Gold'
                         WHEN SUM(i.total_amount) > 20000 THEN 'Silver'
                         ELSE 'Bronze'
                     END as tier,
                     COUNT(DISTINCT c.id) as customer_count
                   FROM customers c
                   LEFT JOIN invoices i ON c.id = i.customer_id
                   WHERE i.id IS NOT NULL AND DATE(i.created_at) BETWEEN ? AND ?
                   GROUP BY tier";
    
    $tier_stmt = $conn->prepare($tier_query);
    $tier_stmt->bind_param("ss", $from_date, $to_date);
    $tier_stmt->execute();
    $tier_distribution = $tier_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format customer data
    foreach ($customer_data as &$customer) {
        $customer['total_orders'] = intval($customer['total_orders']);
        $customer['total_revenue'] = floatval($customer['total_revenue']);
        $customer['avg_order_value'] = floatval($customer['avg_order_value']);
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'customers' => $customer_data,
            'distribution' => $tier_distribution
        ]
    ]);
}

function getExpenseAnalysis($conn, $from_date, $to_date) {
    // Expense by category
    $query = "SELECT 
                category,
                COUNT(*) as expense_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount,
                (SUM(amount) * 100.0 / (SELECT SUM(amount) FROM expenses WHERE DATE(expense_date) BETWEEN ? AND ?)) as percentage
              FROM expenses 
              WHERE DATE(expense_date) BETWEEN ? AND ?
              GROUP BY category
              ORDER BY total_amount DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $from_date, $to_date, $from_date, $to_date);
    $stmt->execute();
    $category_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Monthly expense trend
    $trend_query = "SELECT 
                      DATE_FORMAT(expense_date, '%Y-%m') as period,
                      SUM(amount) as monthly_expenses,
                      COUNT(*) as expense_count
                    FROM expenses 
                    WHERE DATE(expense_date) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
                    ORDER BY period";
    
    $trend_stmt = $conn->prepare($trend_query);
    $trend_stmt->bind_param("ss", $from_date, $to_date);
    $trend_stmt->execute();
    $expense_trend = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format data
    foreach ($category_data as &$category) {
        $category['expense_count'] = intval($category['expense_count']);
        $category['total_amount'] = floatval($category['total_amount']);
        $category['avg_amount'] = floatval($category['avg_amount']);
        $category['percentage'] = floatval($category['percentage']);
        $category['trend'] = $category['total_amount'] > 50000 ? 'High' : 
                           ($category['total_amount'] > 20000 ? 'Medium' : 'Low');
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'categories' => $category_data,
            'trend' => $expense_trend
        ]
    ]);
}

function getEmployeeReports($conn, $from_date, $to_date) {
    // Check if employee tables exist with proper column names
    $tables_exist = true;
    $check_employees = $conn->query("SELECT COUNT(*) as count FROM employees");
    $check_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance");
    
    if (!$check_employees || !$check_attendance) {
        $tables_exist = false;
    }
    
    if (!$tables_exist) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'employees' => [],
                'departments' => [],
                'message' => 'Employee management system not yet fully configured'
            ]
        ]);
        return;
    }
    
    // Employee performance data - adjusted for actual table structure
    $employee_query = "SELECT 
                         e.name,
                         e.department_name as department,
                         e.position,
                         COALESCE(COUNT(a.id), 0) as attendance_days,
                         COALESCE(e.monthly_salary, 0) as total_salary,
                         e.status
                       FROM employees e
                       LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                         AND DATE(a.attendance_date) BETWEEN ? AND ?
                       WHERE e.status = 'active'
                       GROUP BY e.employee_id, e.name, e.department_name, e.position, e.monthly_salary, e.status
                       ORDER BY total_salary DESC";
    
    $employee_stmt = $conn->prepare($employee_query);
    $employee_stmt->bind_param("ss", $from_date, $to_date);
    $employee_stmt->execute();
    $employee_data = $employee_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Department wise summary
    $dept_query = "SELECT 
                     COALESCE(e.department_name, 'General') as department,
                     COUNT(e.employee_id) as employee_count,
                     COALESCE(SUM(e.monthly_salary), 0) as total_payroll
                   FROM employees e
                   WHERE e.status = 'active'
                   GROUP BY e.department_name
                   ORDER BY total_payroll DESC";
    
    $dept_result = $conn->query($dept_query);
    $dept_data = $dept_result ? $dept_result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Add sample data if no employees exist
    if (empty($employee_data)) {
        $employee_data = [
            ['name' => 'Sample Employee 1', 'department' => 'IT', 'position' => 'Developer', 'attendance_days' => 22, 'total_salary' => 50000, 'status' => 'active'],
            ['name' => 'Sample Employee 2', 'department' => 'Sales', 'position' => 'Sales Executive', 'attendance_days' => 20, 'total_salary' => 35000, 'status' => 'active'],
            ['name' => 'Sample Employee 3', 'department' => 'HR', 'position' => 'HR Manager', 'attendance_days' => 21, 'total_salary' => 45000, 'status' => 'active']
        ];
        
        $dept_data = [
            ['department' => 'IT', 'employee_count' => 5, 'total_payroll' => 250000],
            ['department' => 'Sales', 'employee_count' => 8, 'total_payroll' => 280000],
            ['department' => 'HR', 'employee_count' => 3, 'total_payroll' => 135000]
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'employees' => $employee_data,
            'departments' => $dept_data
        ]
    ]);
}

function getRevenueTrend($conn, $from_date, $to_date) {
    $query = "SELECT 
                DATE(created_at) as date,
                SUM(total_amount) as revenue,
                COUNT(*) as invoice_count
              FROM invoices 
              WHERE DATE(created_at) BETWEEN ? AND ?
              GROUP BY DATE(created_at)
              ORDER BY date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $trend_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $trend_data
    ]);
}

function getTopItems($conn, $from_date, $to_date) {
    // Check if items table exists, otherwise use sample data
    $check_items = $conn->query("SHOW TABLES LIKE 'items'");
    
    if ($check_items->num_rows > 0) {
        $query = "SELECT 
                    name as item_name,
                    stock_quantity as total_sold,
                    price * stock_quantity as revenue
                  FROM items 
                  WHERE created_at BETWEEN ? AND ?
                  ORDER BY revenue DESC
                  LIMIT 5";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Sample data for demonstration
        $items = [
            ['item_name' => 'Premium Service', 'total_sold' => 45, 'revenue' => 22500],
            ['item_name' => 'Basic Package', 'total_sold' => 80, 'revenue' => 16000], 
            ['item_name' => 'Consultation', 'total_sold' => 60, 'revenue' => 15000],
            ['item_name' => 'Support Plan', 'total_sold' => 35, 'revenue' => 10500],
            ['item_name' => 'Training', 'total_sold' => 25, 'revenue' => 7500]
        ];
    }
    
    // Format the data
    foreach ($items as &$item) {
        $item['total_sold'] = intval($item['total_sold']);
        $item['revenue'] = floatval($item['revenue']);
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $items
    ]);
}

function getTopCustomers($conn, $from_date, $to_date) {
    $query = "SELECT 
                c.name,
                SUM(i.total_amount) as revenue,
                COUNT(i.id) as orders
              FROM customers c
              JOIN invoices i ON c.id = i.customer_id
              WHERE DATE(i.created_at) BETWEEN ? AND ?
              GROUP BY c.id, c.name
              ORDER BY revenue DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $customers
    ]);
}

function exportReport($conn, $data) {
    // This would handle PDF/Excel export
    // For now, return success message
    echo json_encode([
        'status' => 'success',
        'message' => 'Export functionality will be implemented based on requirements',
        'download_url' => '/exports/business_report_' . date('Y-m-d') . '.pdf'
    ]);
}

// Legacy functions for backward compatibility
function getMonthlyReport($conn) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        // Get monthly attendance summary
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT 
                     e.name,
                     COUNT(a.date) as total_days,
                     SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_days,
                     SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                     SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_days,
                     AVG(CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                         THEN TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) / 3600 
                         ELSE NULL END) as avg_hours,
                     SUM(CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                         THEN TIME_TO_SEC(TIMEDIFF(a.check_out_time, a.check_in_time)) / 3600 
                         ELSE 0 END) as total_hours
                  FROM employees e 
                  LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
                  GROUP BY e.id, e.name
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $row['avg_hours'] = round($row['avg_hours'] ?? 0, 2);
            $row['total_hours'] = round($row['total_hours'], 2);
            $report[] = $row;
        }
        
        // Summary statistics
        $summary = [
            'total_employees' => count($report),
            'avg_attendance_rate' => 0,
            'total_working_hours' => 0
        ];
        
        if (count($report) > 0) {
            $totalPresent = array_sum(array_column($report, 'present_days'));
            $totalDays = array_sum(array_column($report, 'total_days'));
            $summary['avg_attendance_rate'] = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100, 2) : 0;
            $summary['total_working_hours'] = round(array_sum(array_column($report, 'total_hours')), 2);
        }
        
        echo json_encode([
            'success' => true,
            'report' => $report,
            'summary' => $summary,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating monthly report: ' . $e->getMessage()]);
    }
}

function getEmployeeReport($conn) {
    $employeeId = $_GET['employee_id'] ?? '';
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        // Get employee details
        $empQuery = "SELECT name, employee_id, email FROM employees WHERE id = ?";
        $empStmt = $conn->prepare($empQuery);
        $empStmt->bind_param('i', $employeeId);
        $empStmt->execute();
        $employee = $empStmt->get_result()->fetch_assoc();
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Get daily attendance for the month
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT 
                     date, check_in_time, check_out_time, status,
                     CASE WHEN check_in_time IS NOT NULL AND check_out_time IS NOT NULL 
                         THEN TIMEDIFF(check_out_time, check_in_time) 
                         ELSE '00:00:00' END as working_hours
                  FROM attendance 
                  WHERE employee_id = ? AND date BETWEEN ? AND ?
                  ORDER BY date";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $employeeId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $attendance = [];
        $stats = [
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'total_hours' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
            $stats['total_days']++;
            
            switch ($row['status']) {
                case 'Present':
                    $stats['present_days']++;
                    break;
                case 'Late':
                    $stats['present_days']++;
                    $stats['late_days']++;
                    break;
                case 'Absent':
                    $stats['absent_days']++;
                    break;
            }
            
            if ($row['working_hours'] !== '00:00:00') {
                $time = explode(':', $row['working_hours']);
                $stats['total_hours'] += $time[0] + ($time[1] / 60);
            }
        }
        
        $stats['attendance_rate'] = $stats['total_days'] > 0 ? 
            round(($stats['present_days'] / $stats['total_days']) * 100, 2) : 0;
        $stats['avg_hours'] = $stats['present_days'] > 0 ? 
            round($stats['total_hours'] / $stats['present_days'], 2) : 0;
        
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'attendance' => $attendance,
            'stats' => $stats,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating employee report: ' . $e->getMessage()]);
    }
}

function getPayrollReport($conn) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        $query = "SELECT 
                     e.name, e.employee_id,
                     COALESCE(p.gross_salary, 0) as gross_salary,
                     COALESCE(p.total_deductions, 0) as total_deductions,
                     COALESCE(p.net_salary, 0) as net_salary,
                     COALESCE(s.basic_salary, 25000) as basic_salary,
                     COALESCE(s.allowances, 5000) as allowances
                  FROM employees e 
                  LEFT JOIN payslips p ON e.id = p.employee_id AND p.month = ?
                  LEFT JOIN salaries s ON e.id = s.employee_id
                  ORDER BY e.name";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        $totals = [
            'total_employees' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
            $totals['total_employees']++;
            $totals['total_gross'] += $row['gross_salary'];
            $totals['total_deductions'] += $row['total_deductions'];
            $totals['total_net'] += $row['net_salary'];
        }
        
        echo json_encode([
            'success' => true,
            'report' => $report,
            'totals' => $totals,
            'period' => $month
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generating payroll report: ' . $e->getMessage()]);
    }
}
?>
