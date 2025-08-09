<?php
// Customer Management API
// Comprehensive CRUD operations for customer management

session_start();

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

include '../../db.php';

header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$action = $_POST['action'];

switch ($action) {
    case 'get_customers':
        $page = $_POST['page'] ?? 1;
        $limit = $_POST['limit'] ?? 10;
        $search = $_POST['search'] ?? '';
        $status = $_POST['status'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        $types = '';
        
        if ($search) {
            $where_conditions[] = "(customer_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ?)";
            $search_term = "%$search%";
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
            $types .= 'ssss';
        }
        
        if ($status) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM customers $where_clause";
        if (!empty($params)) {
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($types, ...$params);
            $count_stmt->execute();
            $total_result = $count_stmt->get_result();
        } else {
            $total_result = $conn->query($count_query);
        }
        $total = $total_result->fetch_assoc()['total'];
        
        // Get customers
        $query = "SELECT * FROM customers $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'customers' => $customers,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
        break;
        
    case 'get_customer':
        $id = $_POST['id'] ?? '';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($customer = $result->fetch_assoc()) {
            // Get customer invoice history
            $invoice_stmt = $conn->prepare("SELECT COUNT(*) as total_invoices, SUM(total_amount) as total_amount FROM invoices WHERE customer_name = ? OR customer_contact = ?");
            $invoice_stmt->bind_param('ss', $customer['customer_name'], $customer['phone']);
            $invoice_stmt->execute();
            $invoice_result = $invoice_stmt->get_result();
            $invoice_data = $invoice_result->fetch_assoc();
            
            $customer['total_invoices'] = $invoice_data['total_invoices'] ?? 0;
            $customer['total_amount'] = $invoice_data['total_amount'] ?? 0;
            
            echo json_encode(['success' => true, 'customer' => $customer]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        break;
        
    case 'create_customer':
        $customer_name = $_POST['customer_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? '';
        $tax_number = $_POST['tax_number'] ?? '';
        $company_name = $_POST['company_name'] ?? '';
        $website = $_POST['website'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (!$customer_name || !$phone) {
            echo json_encode(['success' => false, 'message' => 'Customer name and phone are required']);
            exit;
        }
        
        // Check if customer already exists
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE customer_name = ? AND phone = ?");
        $check_stmt->bind_param('ss', $customer_name, $phone);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Customer with this name and phone already exists']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO customers (customer_name, email, phone, address, city, state, postal_code, country, tax_number, company_name, website, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssssssss', $customer_name, $email, $phone, $address, $city, $state, $postal_code, $country, $tax_number, $company_name, $website, $notes, $status);
        
        if ($stmt->execute()) {
            $customer_id = $conn->insert_id;
            echo json_encode(['success' => true, 'message' => 'Customer created successfully', 'customer_id' => $customer_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create customer: ' . $stmt->error]);
        }
        break;
        
    case 'update_customer':
        $id = $_POST['id'] ?? '';
        $customer_name = $_POST['customer_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? '';
        $tax_number = $_POST['tax_number'] ?? '';
        $company_name = $_POST['company_name'] ?? '';
        $website = $_POST['website'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (!$id || !$customer_name || !$phone) {
            echo json_encode(['success' => false, 'message' => 'Customer ID, name, and phone are required']);
            exit;
        }
        
        // Check if another customer has the same name and phone
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE customer_name = ? AND phone = ? AND id != ?");
        $check_stmt->bind_param('ssi', $customer_name, $phone, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Another customer with this name and phone already exists']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE customers SET customer_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ?, tax_number = ?, company_name = ?, website = ?, notes = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('sssssssssssssi', $customer_name, $email, $phone, $address, $city, $state, $postal_code, $country, $tax_number, $company_name, $website, $notes, $status, $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made or customer not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update customer: ' . $stmt->error]);
        }
        break;
        
    case 'delete_customer':
        $id = $_POST['id'] ?? '';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            exit;
        }
        
        // Check if customer has invoices
        $check_stmt = $conn->prepare("SELECT customer_name, phone FROM customers WHERE id = ?");
        $check_stmt->bind_param('i', $id);
        $check_stmt->execute();
        $customer_result = $check_stmt->get_result();
        
        if ($customer = $customer_result->fetch_assoc()) {
            $invoice_check = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE customer_name = ? OR customer_contact = ?");
            $invoice_check->bind_param('ss', $customer['customer_name'], $customer['phone']);
            $invoice_check->execute();
            $invoice_count = $invoice_check->get_result()->fetch_assoc()['count'];
            
            if ($invoice_count > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete customer. They have $invoice_count invoice(s) associated."]);
                exit;
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete customer: ' . $stmt->error]);
        }
        break;
        
    case 'get_customer_invoices':
        $id = $_POST['id'] ?? '';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            exit;
        }
        
        // Get customer details
        $customer_stmt = $conn->prepare("SELECT customer_name, phone FROM customers WHERE id = ?");
        $customer_stmt->bind_param('i', $id);
        $customer_stmt->execute();
        $customer_result = $customer_stmt->get_result();
        
        if ($customer = $customer_result->fetch_assoc()) {
            // Get invoices
            $invoice_stmt = $conn->prepare("SELECT * FROM invoices WHERE customer_name = ? OR customer_contact = ? ORDER BY created_at DESC");
            $invoice_stmt->bind_param('ss', $customer['customer_name'], $customer['phone']);
            $invoice_stmt->execute();
            $invoice_result = $invoice_stmt->get_result();
            
            $invoices = [];
            while ($row = $invoice_result->fetch_assoc()) {
                $invoices[] = $row;
            }
            
            echo json_encode(['success' => true, 'invoices' => $invoices]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        break;
        
    case 'import_from_invoices':
        // Import unique customers from invoices table
        $query = "SELECT DISTINCT customer_name, customer_contact, bill_address FROM invoices WHERE customer_name NOT IN (SELECT customer_name FROM customers)";
        $result = $conn->query($query);
        
        $imported = 0;
        $errors = [];
        
        while ($row = $result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, address, status) VALUES (?, ?, ?, 'active')");
            $stmt->bind_param('sss', $row['customer_name'], $row['customer_contact'], $row['bill_address']);
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Failed to import: " . $row['customer_name'];
            }
        }
        
        $message = "Imported $imported customers from invoices.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        echo json_encode(['success' => true, 'message' => $message, 'imported' => $imported]);
        break;
        
    case 'get_dashboard_stats':
        // Customer dashboard statistics
        $stats = [];
        
        // Total customers
        $result = $conn->query("SELECT COUNT(*) as total FROM customers");
        $stats['total_customers'] = $result->fetch_assoc()['total'];
        
        // Active customers
        $result = $conn->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
        $stats['active_customers'] = $result->fetch_assoc()['total'];
        
        // New customers this month
        $result = $conn->query("SELECT COUNT(*) as total FROM customers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stats['new_this_month'] = $result->fetch_assoc()['total'];
        
        // Top customers by invoice value
        $result = $conn->query("SELECT customer_name, customer_contact, COUNT(*) as invoice_count, SUM(total_amount) as total_amount FROM invoices GROUP BY customer_name, customer_contact ORDER BY total_amount DESC LIMIT 5");
        $top_customers = [];
        while ($row = $result->fetch_assoc()) {
            $top_customers[] = $row;
        }
        $stats['top_customers'] = $top_customers;
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
