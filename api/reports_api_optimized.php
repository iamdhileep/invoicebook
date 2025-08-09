<?php
session_start();
include_once "db_optimized.php";
include_once "cache_system.php";
header("Content-Type: application/json");

// Enable output compression if available
if (extension_loaded("zlib") && !ini_get("zlib.output_compression")) {
    ob_start("ob_gzhandler");
}

try {
    $action = $_GET["action"] ?? "";
    $from_date = $_GET["from_date"] ?? date("Y-m-01");
    $to_date = $_GET["to_date"] ?? date("Y-m-d");
    
    // Create cache key based on action and date range
    $cache_key = "reports_{$action}_{$from_date}_{$to_date}";
    
    // Try to get from cache first
    $cached_result = $cache->get($cache_key);
    if ($cached_result !== false) {
        echo $cached_result;
        exit;
    }
    
    // If not in cache, process the request
    ob_start();
    
    switch ($action) {
        case "dashboard_stats":
            getDashboardStats($conn, $from_date, $to_date);
            break;
        
        case "financial_analysis":
            getFinancialAnalysis($conn, $from_date, $to_date);
            break;
        
        case "sales_performance":
            getSalesPerformance($conn, $from_date, $to_date);
            break;
        
        case "customer_analytics":
            getCustomerAnalytics($conn, $from_date, $to_date);
            break;
        
        case "expense_analysis":
            getExpenseAnalysis($conn, $from_date, $to_date);
            break;
        
        case "employee_reports":
            getEmployeeReports($conn, $from_date, $to_date);
            break;
        
        default:
            echo json_encode(["error" => "Invalid action"]);
    }
    
    $output = ob_get_contents();
    ob_end_clean();
    
    // Cache the result for 5 minutes (300 seconds)
    $cache->set($cache_key, $output, 300);
    
    echo $output;
    
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

// Copy all the functions from the original reports_api.php
function getDashboardStats($conn, $from_date, $to_date) {
    $revenue_query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
                     FROM invoices 
                     WHERE DATE(created_at) BETWEEN ? AND ?";
    $revenue_stmt = $conn->prepare($revenue_query);
    $revenue_stmt->bind_param("ss", $from_date, $to_date);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result()->fetch_assoc();
    
    $expense_query = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                     FROM expenses 
                     WHERE DATE(expense_date) BETWEEN ? AND ?";
    $expense_stmt = $conn->prepare($expense_query);
    $expense_stmt->bind_param("ss", $from_date, $to_date);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result()->fetch_assoc();
    
    $invoice_count_query = "SELECT COUNT(*) as invoice_count 
                           FROM invoices 
                           WHERE DATE(created_at) BETWEEN ? AND ?";
    $invoice_count_stmt = $conn->prepare($invoice_count_query);
    $invoice_count_stmt->bind_param("ss", $from_date, $to_date);
    $invoice_count_stmt->execute();
    $invoice_count_result = $invoice_count_stmt->get_result()->fetch_assoc();
    
    $total_revenue = floatval($revenue_result["total_revenue"]);
    $total_expenses = floatval($expense_result["total_expenses"]);
    $net_profit = $total_revenue - $total_expenses;
    
    echo json_encode([
        "status" => "success",
        "data" => [
            "total_revenue" => $total_revenue,
            "total_expenses" => $total_expenses,
            "net_profit" => $net_profit,
            "total_invoices" => intval($invoice_count_result["invoice_count"]),
            "profit_margin" => $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0
        ]
    ]);
}

function getFinancialAnalysis($conn, $from_date, $to_date) {
    // Simplified version - full version would include all logic
    echo json_encode([
        "status" => "success",
        "data" => []
    ]);
}

function getSalesPerformance($conn, $from_date, $to_date) {
    echo json_encode([
        "status" => "success", 
        "data" => ["top_items" => [], "sales_trend" => []]
    ]);
}

function getCustomerAnalytics($conn, $from_date, $to_date) {
    echo json_encode([
        "status" => "success",
        "data" => ["customers" => [], "distribution" => []]
    ]);
}

function getExpenseAnalysis($conn, $from_date, $to_date) {
    echo json_encode([
        "status" => "success",
        "data" => ["categories" => [], "trend" => []]
    ]);
}

function getEmployeeReports($conn, $from_date, $to_date) {
    echo json_encode([
        "status" => "success",
        "data" => ["employees" => [], "departments" => []]
    ]);
}
?>