<?php
/**
 * Supplier API - Handle supplier operations for integration
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
    case 'get_all_suppliers':
        $query = "SELECT id, supplier_name, company_name, contact_person, phone, email, city, status, supplier_type, rating FROM suppliers ORDER BY supplier_name ASC";
        $result = mysqli_query($conn, $query);
        
        $suppliers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $suppliers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $suppliers]);
        break;

    case 'search_suppliers':
        $search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
        $query = "SELECT id, supplier_name, company_name, contact_person, phone, email, city 
                  FROM suppliers 
                  WHERE supplier_name LIKE '%$search%' 
                     OR company_name LIKE '%$search%' 
                     OR contact_person LIKE '%$search%'
                     OR phone LIKE '%$search%'
                  AND status = 'active'
                  ORDER BY supplier_name ASC 
                  LIMIT 20";
        
        $result = mysqli_query($conn, $query);
        $suppliers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $suppliers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $suppliers]);
        break;

    case 'get_supplier_info':
        $id = intval($_GET['id'] ?? 0);
        $query = "SELECT * FROM suppliers WHERE id = $id";
        $result = mysqli_query($conn, $query);
        
        if ($row = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        }
        break;

    case 'get_suppliers_by_category':
        $category = mysqli_real_escape_string($conn, $_GET['category'] ?? '');
        $query = "SELECT * FROM suppliers WHERE category = '$category' AND status = 'active' ORDER BY supplier_name ASC";
        $result = mysqli_query($conn, $query);
        
        $suppliers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $suppliers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $suppliers]);
        break;

    case 'get_supplier_statistics':
        $totalSuppliers = 0;
        $activeSuppliers = 0;
        $inactiveSuppliers = 0;
        $totalSpent = 0;
        $avgRating = 0;
        
        // Get supplier counts
        $supplierStats = mysqli_query($conn, "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                AVG(rating) as avg_rating
            FROM suppliers
        ");
        
        if ($row = mysqli_fetch_assoc($supplierStats)) {
            $totalSuppliers = $row['total'];
            $activeSuppliers = $row['active'];
            $inactiveSuppliers = $row['inactive'];
            $avgRating = round($row['avg_rating'] ?? 0, 1);
        }
        
        // Get spending stats
        $spentStats = mysqli_query($conn, "
            SELECT SUM(total_amount) as total_spent
            FROM purchase_orders
        ");
        
        if ($row = mysqli_fetch_assoc($spentStats)) {
            $totalSpent = $row['total_spent'] ?? 0;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_suppliers' => $totalSuppliers,
                'active_suppliers' => $activeSuppliers,
                'inactive_suppliers' => $inactiveSuppliers,
                'total_spent' => $totalSpent,
                'avg_rating' => $avgRating
            ]
        ]);
        break;

    case 'export_suppliers':
        $query = "SELECT 
                    supplier_name,
                    company_name,
                    contact_person,
                    phone,
                    alternate_phone,
                    email,
                    website,
                    address,
                    city,
                    state,
                    postal_code,
                    country,
                    tax_number,
                    supplier_type,
                    category,
                    payment_terms,
                    credit_limit,
                    rating,
                    status,
                    created_at
                  FROM suppliers 
                  ORDER BY supplier_name ASC";
        
        $result = mysqli_query($conn, $query);
        $suppliers = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $suppliers[] = $row;
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="suppliers_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Supplier Name', 'Company Name', 'Contact Person', 'Phone', 'Alternate Phone',
            'Email', 'Website', 'Address', 'City', 'State', 'Postal Code', 'Country',
            'Tax Number', 'Supplier Type', 'Category', 'Payment Terms', 'Credit Limit',
            'Rating', 'Status', 'Created Date'
        ]);
        
        // Add data rows
        foreach ($suppliers as $supplier) {
            fputcsv($output, $supplier);
        }
        
        fclose($output);
        exit;

    case 'get_supplier_performance':
        $supplierId = intval($_GET['supplier_id'] ?? 0);
        
        // Get performance metrics
        $performanceQuery = mysqli_query($conn, "
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_value,
                AVG(total_amount) as avg_order_value,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                AVG(DATEDIFF(expected_delivery_date, order_date)) as avg_delivery_time
            FROM purchase_orders
            WHERE supplier_id = $supplierId
        ");
        
        $performance = mysqli_fetch_assoc($performanceQuery);
        
        // Get recent order trends (last 12 months)
        $trendsQuery = mysqli_query($conn, "
            SELECT 
                DATE_FORMAT(order_date, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount
            FROM purchase_orders
            WHERE supplier_id = $supplierId 
                AND order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(order_date, '%Y-%m')
            ORDER BY month ASC
        ");
        
        $trends = [];
        while ($row = mysqli_fetch_assoc($trendsQuery)) {
            $trends[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'performance' => $performance,
            'trends' => $trends
        ]);
        break;

    case 'update_supplier_rating':
        $supplierId = intval($_POST['supplier_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 3);
        
        if ($supplierId > 0 && $rating >= 1 && $rating <= 5) {
            $query = "UPDATE suppliers SET rating = $rating, updated_at = CURRENT_TIMESTAMP WHERE id = $supplierId";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating rating']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid supplier or rating']);
        }
        break;

    case 'get_top_suppliers':
        $limit = intval($_GET['limit'] ?? 10);
        
        $query = "
            SELECT 
                s.*,
                COALESCE(po_stats.total_orders, 0) as total_orders,
                COALESCE(po_stats.total_spent, 0) as total_spent,
                COALESCE(item_stats.items_supplied, 0) as items_supplied
            FROM suppliers s
            LEFT JOIN (
                SELECT 
                    supplier_id,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent
                FROM purchase_orders
                GROUP BY supplier_id
            ) po_stats ON s.id = po_stats.supplier_id
            LEFT JOIN (
                SELECT 
                    supplier_id,
                    COUNT(*) as items_supplied
                FROM items
                WHERE supplier_id IS NOT NULL
                GROUP BY supplier_id
            ) item_stats ON s.id = item_stats.supplier_id
            WHERE s.status = 'active'
            ORDER BY po_stats.total_spent DESC, s.rating DESC
            LIMIT $limit
        ";
        
        $result = mysqli_query($conn, $query);
        $topSuppliers = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $topSuppliers[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $topSuppliers]);
        break;

    case 'get_category_breakdown':
        $query = "
            SELECT 
                category,
                COUNT(*) as supplier_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                AVG(rating) as avg_rating
            FROM suppliers 
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category
            ORDER BY supplier_count DESC
        ";
        
        $result = mysqli_query($conn, $query);
        $categories = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = [
                'category' => $row['category'],
                'supplier_count' => $row['supplier_count'],
                'active_count' => $row['active_count'],
                'avg_rating' => round($row['avg_rating'], 1)
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
        break;

    case 'supplier_quick_add':
        // Quick add for minimal supplier data
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
        
        if (empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
            break;
        }
        
        $query = "INSERT INTO suppliers (supplier_name, phone, email, category) VALUES ('$name', '$phone', '$email', '$category')";
        
        if (mysqli_query($conn, $query)) {
            $supplierId = mysqli_insert_id($conn);
            echo json_encode([
                'success' => true, 
                'message' => 'Supplier added successfully',
                'supplier_id' => $supplierId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding supplier: ' . mysqli_error($conn)]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
