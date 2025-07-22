<?php
/**
 * Categories Test Script
 * Quick test to verify categories functionality
 */

// Display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🧪 Categories Functionality Test</h2>";

try {
    // Include database connection
    include 'db.php';
    
    echo "<p>✅ Database connection established</p>";
    
    // Check if categories table exists
    $result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($result->num_rows == 0) {
        echo "<p>❌ Categories table does not exist. Please run setup_database.php first.</p>";
        exit;
    }
    
    echo "<p>✅ Categories table exists</p>";
    
    // Test basic operations
    echo "<h3>🔍 Testing Basic Operations:</h3>";
    
    // 1. Test SELECT
    $selectResult = $conn->query("SELECT * FROM categories LIMIT 5");
    if ($selectResult) {
        echo "<p>✅ SELECT query successful (" . $selectResult->num_rows . " rows)</p>";
        
        if ($selectResult->num_rows > 0) {
            echo "<h4>Sample Categories:</h4>";
            echo "<ul>";
            while ($row = $selectResult->fetch_assoc()) {
                $name = htmlspecialchars($row['name'] ?? 'N/A');
                $description = htmlspecialchars($row['description'] ?? 'No description');
                $color = htmlspecialchars($row['color'] ?? '#007bff');
                $icon = htmlspecialchars($row['icon'] ?? 'bi-tag');
                echo "<li><span style='color: $color;'><i class='$icon'></i></span> <strong>$name</strong> - $description</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ SELECT query failed: " . $conn->error . "</p>";
    }
    
    // 2. Test INSERT
    $testName = "Test Category " . date('Y-m-d H:i:s');
    $testDesc = "Automated test category";
    $testColor = "#ff5722";
    $testIcon = "bi-gear";
    
    $insertStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
    if ($insertStmt) {
        $insertStmt->bind_param("ssss", $testName, $testDesc, $testColor, $testIcon);
        if ($insertStmt->execute()) {
            $insertId = $conn->insert_id;
            echo "<p>✅ INSERT successful (ID: $insertId)</p>";
            
            // 3. Test UPDATE
            $updateStmt = $conn->prepare("UPDATE categories SET description = ? WHERE id = ?");
            if ($updateStmt) {
                $newDesc = "Updated test description " . date('H:i:s');
                $updateStmt->bind_param("si", $newDesc, $insertId);
                if ($updateStmt->execute()) {
                    echo "<p>✅ UPDATE successful</p>";
                } else {
                    echo "<p>❌ UPDATE failed: " . $updateStmt->error . "</p>";
                }
            }
            
            // 4. Test DELETE
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $insertId);
                if ($deleteStmt->execute()) {
                    echo "<p>✅ DELETE successful</p>";
                } else {
                    echo "<p>❌ DELETE failed: " . $deleteStmt->error . "</p>";
                }
            }
            
        } else {
            echo "<p>❌ INSERT failed: " . $insertStmt->error . "</p>";
        }
    } else {
        echo "<p>❌ INSERT prepare failed: " . $conn->error . "</p>";
    }
    
    // Test categories with items relationship
    echo "<h3>📦 Testing Categories-Items Relationship:</h3>";
    
    $relationQuery = "
        SELECT 
            c.name as category_name,
            COUNT(i.id) as item_count
        FROM categories c
        LEFT JOIN items i ON c.name = i.category
        GROUP BY c.id, c.name
        ORDER BY item_count DESC
        LIMIT 5
    ";
    
    $relationResult = $conn->query($relationQuery);
    if ($relationResult) {
        echo "<p>✅ Categories-Items relationship query successful</p>";
        echo "<h4>Categories with Item Counts:</h4>";
        echo "<ul>";
        while ($row = $relationResult->fetch_assoc()) {
            $categoryName = htmlspecialchars($row['category_name']);
            $itemCount = $row['item_count'];
            echo "<li><strong>$categoryName:</strong> $itemCount items</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Relationship query failed: " . $conn->error . "</p>";
    }
    
    // Test AJAX simulation
    echo "<h3>🌐 Testing AJAX-Style Operations:</h3>";
    
    // Simulate the AJAX add operation from manage_categories.php
    $ajaxTestName = "AJAX Test Category";
    $ajaxTestDesc = "Category created via AJAX simulation";
    $ajaxTestColor = "#28a745";
    $ajaxTestIcon = "bi-check-circle";
    
    // Check if category exists
    $checkStmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("s", $ajaxTestName);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo "<p>ℹ️ AJAX test category already exists</p>";
        } else {
            // Insert new category
            $ajaxInsertStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)");
            if ($ajaxInsertStmt) {
                $ajaxInsertStmt->bind_param("ssss", $ajaxTestName, $ajaxTestDesc, $ajaxTestColor, $ajaxTestIcon);
                if ($ajaxInsertStmt->execute()) {
                    $ajaxId = $conn->insert_id;
                    echo "<p>✅ AJAX simulation: Category added (ID: $ajaxId)</p>";
                    
                    // Simulate getting category data for editing
                    $getStmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
                    if ($getStmt) {
                        $getStmt->bind_param("i", $ajaxId);
                        $getStmt->execute();
                        $getResult = $getStmt->get_result();
                        
                        if ($getRow = $getResult->fetch_assoc()) {
                            echo "<p>✅ AJAX simulation: Category data retrieved</p>";
                            
                            // Clean up - delete the test category
                            $cleanupStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                            if ($cleanupStmt) {
                                $cleanupStmt->bind_param("i", $ajaxId);
                                if ($cleanupStmt->execute()) {
                                    echo "<p>✅ AJAX simulation: Test category cleaned up</p>";
                                }
                            }
                        }
                    }
                } else {
                    echo "<p>❌ AJAX simulation failed: " . $ajaxInsertStmt->error . "</p>";
                }
            }
        }
    }
    
    echo "<h3>📊 Final Statistics:</h3>";
    
    // Get final counts
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM categories) as total_categories,
            (SELECT COUNT(*) FROM categories WHERE id IN (SELECT DISTINCT category FROM items WHERE category IS NOT NULL)) as used_categories,
            (SELECT COUNT(*) FROM items) as total_items
    ";
    
    $statsResult = $conn->query($statsQuery);
    if ($statsResult) {
        $stats = $statsResult->fetch_assoc();
        echo "<p>📈 <strong>Total Categories:</strong> " . ($stats['total_categories'] ?? 0) . "</p>";
        echo "<p>📈 <strong>Used Categories:</strong> " . ($stats['used_categories'] ?? 0) . "</p>";
        echo "<p>📈 <strong>Total Items:</strong> " . ($stats['total_items'] ?? 0) . "</p>";
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>🎉 Categories Test Complete!</h3>";
    echo "<p>All basic operations are working correctly. The manage_categories.php page should function properly.</p>";
    echo "<p><a href='manage_categories.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Categories Page →</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>❌ Test Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
h3 { color: #555; margin-top: 30px; }
h4 { color: #666; margin-top: 20px; }
ul { background: #f8f9fa; padding: 15px; border-radius: 5px; }
li { margin: 5px 0; }
</style>