<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get filters from URL parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$stock_status = $_GET['stock_status'] ?? '';

// Build WHERE clause
$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (item_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($category) {
    $where .= " AND category = '" . mysqli_real_escape_string($conn, $category) . "'";
}
if ($stock_status === 'low') {
    $where .= " AND stock <= 10";
} elseif ($stock_status === 'out') {
    $where .= " AND stock = 0";
}

// Get items data
$query = "SELECT item_name, category, item_price, stock, (stock * item_price) as total_value, description FROM items $where ORDER BY item_name ASC";
$result = $conn->query($query);

// Set headers for CSV download
$filename = 'stock_inventory_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Item Name',
    'Category',
    'Unit Price (₹)',
    'Stock Quantity',
    'Total Value (₹)',
    'Status',
    'Description'
]);

// Write data rows
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = $result->fetch_assoc()) {
        // Determine status
        $status = '';
        if ($row['stock'] == 0) {
            $status = 'Out of Stock';
        } elseif ($row['stock'] <= 10) {
            $status = 'Low Stock';
        } else {
            $status = 'In Stock';
        }
        
        fputcsv($output, [
            $row['item_name'],
            $row['category'] ?: 'No Category',
            number_format($row['item_price'], 2),
            $row['stock'],
            number_format($row['total_value'], 2),
            $status,
            $row['description'] ?: ''
        ]);
    }
}

fclose($output);
exit;
?>