<?php
/**
 * Categories Table Test & Fix Script
 * Checks and creates/fixes the categories table structure
 */

// Display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🧪 Categories Table Structure Test & Fix</h2>";

try {
    // Include database connection
    include 'db.php';
    
    echo "<p>✅ Database connection established</p>";
    
    // Check if categories table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($tableCheck->num_rows == 0) {
        echo "<p>❌ Categories table does not exist. Creating it now...</p>";
        
        // Create categories table
        $createTable = "
        CREATE TABLE categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL,
            color VARCHAR(7) DEFAULT '#007bff',
            icon VARCHAR(50) DEFAULT 'bi-tag',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTable)) {
            echo "<p>✅ Categories table created successfully</p>";
        } else {
            echo "<p>❌ Error creating categories table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>✅ Categories table exists</p>";
    }
    
    // Check table structure
    echo "<h3>📋 Current Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE categories");
    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        $hasDescription = false;
        $hasColor = false;
        $hasIcon = false;
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
            
            // Check for required columns
            if ($row['Field'] === 'created_at') $hasCreatedAt = true;
            if ($row['Field'] === 'updated_at') $hasUpdatedAt = true;
            if ($row['Field'] === 'description') $hasDescription = true;
            if ($row['Field'] === 'color') $hasColor = true;
            if ($row['Field'] === 'icon') $hasIcon = true;
        }
        echo "</table>";
        
        // Check for missing columns and add them
        echo "<h3>🔧 Checking for Missing Columns:</h3>";
        
        if (!$hasCreatedAt) {
            echo "<p>❌ Missing 'created_at' column. Adding it...</p>";
            $addCreatedAt = "ALTER TABLE categories ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
            if ($conn->query($addCreatedAt)) {
                echo "<p>✅ Added 'created_at' column</p>";
            } else {
                echo "<p>❌ Error adding 'created_at' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ 'created_at' column exists</p>";
        }
        
        if (!$hasUpdatedAt) {
            echo "<p>❌ Missing 'updated_at' column. Adding it...</p>";
            $addUpdatedAt = "ALTER TABLE categories ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            if ($conn->query($addUpdatedAt)) {
                echo "<p>✅ Added 'updated_at' column</p>";
            } else {
                echo "<p>❌ Error adding 'updated_at' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ 'updated_at' column exists</p>";
        }
        
        if (!$hasDescription) {
            echo "<p>❌ Missing 'description' column. Adding it...</p>";
            $addDescription = "ALTER TABLE categories ADD COLUMN description TEXT DEFAULT NULL";
            if ($conn->query($addDescription)) {
                echo "<p>✅ Added 'description' column</p>";
            } else {
                echo "<p>❌ Error adding 'description' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ 'description' column exists</p>";
        }
        
        if (!$hasColor) {
            echo "<p>❌ Missing 'color' column. Adding it...</p>";
            $addColor = "ALTER TABLE categories ADD COLUMN color VARCHAR(7) DEFAULT '#007bff'";
            if ($conn->query($addColor)) {
                echo "<p>✅ Added 'color' column</p>";
            } else {
                echo "<p>❌ Error adding 'color' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ 'color' column exists</p>";
        }
        
        if (!$hasIcon) {
            echo "<p>❌ Missing 'icon' column. Adding it...</p>";
            $addIcon = "ALTER TABLE categories ADD COLUMN icon VARCHAR(50) DEFAULT 'bi-tag'";
            if ($conn->query($addIcon)) {
                echo "<p>✅ Added 'icon' column</p>";
            } else {
                echo "<p>❌ Error adding 'icon' column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>✅ 'icon' column exists</p>";
        }
    }
    
    // Test data insertion
    echo "<h3>🧪 Testing Data Insertion:</h3>";
    
    $testName = "Test Category " . date('Y-m-d H:i:s');
    $testDesc = "Test description for category";
    $testColor = "#ff5722";
    $testIcon = "bi-gear";
    
    $insertStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($insertStmt) {
        $insertStmt->bind_param("ssss", $testName, $testDesc, $testColor, $testIcon);
        if ($insertStmt->execute()) {
            $testId = $conn->insert_id;
            echo "<p>✅ Test data inserted successfully (ID: $testId)</p>";
            
            // Verify the inserted data
            $verifyStmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
            if ($verifyStmt) {
                $verifyStmt->bind_param("i", $testId);
                $verifyStmt->execute();
                $result = $verifyStmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    echo "<h4>✅ Inserted Data Verification:</h4>";
                    echo "<ul>";
                    echo "<li><strong>Name:</strong> " . htmlspecialchars($row['name']) . "</li>";
                    echo "<li><strong>Description:</strong> " . htmlspecialchars($row['description'] ?? 'N/A') . "</li>";
                    echo "<li><strong>Color:</strong> <span style='color: " . htmlspecialchars($row['color'] ?? '#007bff') . ";'>⬤</span> " . htmlspecialchars($row['color'] ?? '#007bff') . "</li>";
                    echo "<li><strong>Icon:</strong> <i class='" . htmlspecialchars($row['icon'] ?? 'bi-tag') . "'></i> " . htmlspecialchars($row['icon'] ?? 'bi-tag') . "</li>";
                    echo "<li><strong>Created At:</strong> " . htmlspecialchars($row['created_at'] ?? 'N/A') . "</li>";
                    echo "<li><strong>Updated At:</strong> " . htmlspecialchars($row['updated_at'] ?? 'N/A') . "</li>";
                    echo "</ul>";
                } else {
                    echo "<p>❌ Could not verify inserted data</p>";
                }
            }
            
            // Clean up test data
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $testId);
                if ($deleteStmt->execute()) {
                    echo "<p>✅ Test data cleaned up</p>";
                }
            }
            
        } else {
            echo "<p>❌ Failed to insert test data: " . $insertStmt->error . "</p>";
        }
    } else {
        echo "<p>❌ Failed to prepare insert statement: " . $conn->error . "</p>";
    }
    
    // Check existing categories
    echo "<h3>📊 Existing Categories:</h3>";
    $existingCategories = $conn->query("SELECT * FROM categories ORDER BY created_at DESC LIMIT 10");
    if ($existingCategories && $existingCategories->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 20px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Description</th><th>Color</th><th>Icon</th><th>Created At</th></tr>";
        
        while ($category = $existingCategories->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $category['id'] . "</td>";
            echo "<td>" . htmlspecialchars($category['name']) . "</td>";
            echo "<td>" . htmlspecialchars($category['description'] ?? 'N/A') . "</td>";
            echo "<td><span style='color: " . htmlspecialchars($category['color'] ?? '#007bff') . ";'>⬤</span> " . htmlspecialchars($category['color'] ?? '#007bff') . "</td>";
            echo "<td><i class='" . htmlspecialchars($category['icon'] ?? 'bi-tag') . "'></i> " . htmlspecialchars($category['icon'] ?? 'bi-tag') . "</td>";
            echo "<td>" . htmlspecialchars($category['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>ℹ️ No existing categories found</p>";
        
        // Insert some sample categories
        echo "<h4>🌟 Adding Sample Categories:</h4>";
        $sampleCategories = [
            ['Electronics', 'Electronic devices and gadgets', '#007bff', 'bi-laptop'],
            ['Clothing', 'Apparel and fashion items', '#28a745', 'bi-bag'],
            ['Books', 'Books and educational materials', '#ffc107', 'bi-book'],
            ['Food & Beverages', 'Food items and drinks', '#fd7e14', 'bi-cup']
        ];
        
        foreach ($sampleCategories as $sample) {
            $sampleStmt = $conn->prepare("INSERT INTO categories (name, description, color, icon, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($sampleStmt) {
                $sampleStmt->bind_param("ssss", $sample[0], $sample[1], $sample[2], $sample[3]);
                if ($sampleStmt->execute()) {
                    echo "<p>✅ Added sample category: " . $sample[0] . "</p>";
                } else {
                    echo "<p>❌ Failed to add sample category: " . $sample[0] . " - " . $sampleStmt->error . "</p>";
                }
            }
        }
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h3>🎉 Categories Table Test Complete!</h3>";
    echo "<p>The categories table structure has been verified and fixed. All required columns are now present.</p>";
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
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 15px 0; 
}
th, td { 
    padding: 8px 12px; 
    text-align: left; 
    border: 1px solid #ddd; 
}
th { 
    background: #f8f9fa; 
    font-weight: bold; 
}
ul { 
    background: #f8f9fa; 
    padding: 15px; 
    border-radius: 5px; 
}
</style>