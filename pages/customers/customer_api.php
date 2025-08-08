<?php
/**
 * Customer API - Handle customer operations for integration
 */
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_all_customers':
        $query = "SELECT id, customer_name, email, phone, city, status FROM customers ORDER BY customer_name ASC";
        $result = mysqli_query($conn, $query);
        
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $customers]);
        break;

    case 'get_customer_by_name':
        $name = mysqli_real_escape_string($conn, $_GET['name'] ?? '');
        $query = "SELECT * FROM customers WHERE customer_name LIKE '%$name%' LIMIT 10";
        $result = mysqli_query($conn, $query);
        
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $customers]);
        break;

    case 'get_customer_info':
        $id = intval($_GET['id'] ?? 0);
        $query = "SELECT * FROM customers WHERE id = $id";
        $result = mysqli_query($conn, $query);
        
        if ($row = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        break;

    case 'search_customers':
        $search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
        $query = "SELECT id, customer_name, email, phone, city 
                  FROM customers 
                  WHERE customer_name LIKE '%$search%' 
                     OR email LIKE '%$search%' 
                     OR phone LIKE '%$search%'
                     OR company_name LIKE '%$search%'
                  ORDER BY customer_name ASC 
                  LIMIT 20";
        
        $result = mysqli_query($conn, $query);
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $customers]);
        break;

    case 'create_customer_from_invoice':
        // Used when creating invoice with new customer
        $name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['customer_contact'] ?? '');
        $address = mysqli_real_escape_string($conn, $_POST['bill_address'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Customer name is required']);
            break;
        }
        
        // Check if customer already exists
        $checkQuery = "SELECT id FROM customers WHERE customer_name = '$name' AND phone = '$phone'";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $existing = mysqli_fetch_assoc($checkResult);
            echo json_encode(['success' => true, 'customer_id' => $existing['id'], 'message' => 'Customer already exists']);
        } else {
            // Create new customer
            $insertQuery = "INSERT INTO customers (customer_name, phone, address) VALUES ('$name', '$phone', '$address')";
            if (mysqli_query($conn, $insertQuery)) {
                $customerId = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'customer_id' => $customerId, 'message' => 'Customer created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating customer: ' . mysqli_error($conn)]);
            }
        }
        break;

    case 'get_customer_statistics':
        $totalCustomers = 0;
        $activeCustomers = 0;
        $inactiveCustomers = 0;
        $totalRevenue = 0;
        $avgRevenue = 0;
        
        // Get customer counts
        $customerStats = mysqli_query($conn, "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
            FROM customers
        ");
        
        if ($row = mysqli_fetch_assoc($customerStats)) {
            $totalCustomers = $row['total'];
            $activeCustomers = $row['active'];
            $inactiveCustomers = $row['inactive'];
        }
        
        // Get revenue stats
        $revenueStats = mysqli_query($conn, "
            SELECT 
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_revenue
            FROM invoices
        ");
        
        if ($row = mysqli_fetch_assoc($revenueStats)) {
            $totalRevenue = $row['total_revenue'] ?? 0;
            $avgRevenue = $row['avg_revenue'] ?? 0;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'inactive_customers' => $inactiveCustomers,
                'total_revenue' => $totalRevenue,
                'avg_revenue' => $avgRevenue
            ]
        ]);
        break;

    case 'export_customers':
        $query = "SELECT 
                    customer_name,
                    email,
                    phone,
                    company_name,
                    address,
                    city,
                    state,
                    postal_code,
                    country,
                    tax_number,
                    status,
                    created_at
                  FROM customers 
                  ORDER BY customer_name ASC";
        
        $result = mysqli_query($conn, $query);
        $customers = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Customer Name', 'Email', 'Phone', 'Company Name', 'Address', 
            'City', 'State', 'Postal Code', 'Country', 'Tax Number', 'Status', 'Created Date'
        ]);
        
        // Add data rows
        foreach ($customers as $customer) {
            fputcsv($output, $customer);
        }
        
        fclose($output);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
