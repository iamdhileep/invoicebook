<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

try {
    // Fetch categories with item counts
    $categoriesQuery = "
        SELECT 
            c.id,
            c.name,
            c.description,
            c.color,
            c.icon,
            c.created_at,
            c.updated_at,
            COALESCE(COUNT(i.id), 0) as item_count
        FROM categories c
        LEFT JOIN items i ON c.name = i.category
        GROUP BY c.id, c.name, c.description, c.color, c.icon, c.created_at, c.updated_at
        ORDER BY c.name ASC
    ";
    
    $result = $conn->query($categoriesQuery);
    
    if ($result) {
        // Set headers for CSV download
        $filename = 'categories_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        $headers = [
            'ID',
            'Category Name',
            'Description',
            'Color',
            'Icon',
            'Items Count',
            'Status',
            'Created Date',
            'Updated Date'
        ];
        
        fputcsv($output, $headers);
        
        // Export data rows
        while ($category = $result->fetch_assoc()) {
            $status = $category['item_count'] > 0 ? 'Active' : 'Unused';
            $createdDate = !empty($category['created_at']) ? date('Y-m-d H:i:s', strtotime($category['created_at'])) : 'N/A';
            $updatedDate = !empty($category['updated_at']) && $category['updated_at'] != $category['created_at'] 
                         ? date('Y-m-d H:i:s', strtotime($category['updated_at'])) : 'N/A';
            
            $row = [
                $category['id'],
                $category['name'],
                $category['description'] ?? '',
                $category['color'] ?? '#007bff',
                $category['icon'] ?? 'bi-tag',
                $category['item_count'],
                $status,
                $createdDate,
                $updatedDate
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        
    } else {
        // Error occurred
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">';
        echo '<h2 style="color: #dc3545;">Export Error</h2>';
        echo '<p>Failed to export categories: ' . htmlspecialchars($conn->error) . '</p>';
        echo '<p><a href="manage_categories.php" style="color: #007bff; text-decoration: none;">&larr; Back to Categories</a></p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">';
    echo '<h2 style="color: #dc3545;">Export Error</h2>';
    echo '<p>An error occurred during export: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="manage_categories.php" style="color: #007bff; text-decoration: none;">&larr; Back to Categories</a></p>';
    echo '</div>';
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>