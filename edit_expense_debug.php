<?php
// Debug version of edit_expense.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug: edit_expense.php</h2>";

// Step 1: Check session
session_start();
echo "<p><strong>Step 1 - Session Check:</strong><br>";
if (!isset($_SESSION['admin'])) {
    echo "❌ Session not set. <a href='login.php'>Please login first</a></p>";
    // Don't exit for debugging
} else {
    echo "✅ Admin session active</p>";
}

// Step 2: Check database connection
echo "<p><strong>Step 2 - Database Connection:</strong><br>";
try {
    include 'db.php';
    if (isset($conn) && $conn) {
        echo "✅ Database connected successfully</p>";
    } else {
        echo "❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "</p>";
}

// Step 3: Check expense ID
$expense_id = $_GET['id'] ?? null;
echo "<p><strong>Step 3 - Expense ID:</strong><br>";
if (!$expense_id) {
    echo "❌ No expense ID provided in URL</p>";
    echo "<p>Try: <a href='edit_expense_debug.php?id=1'>edit_expense_debug.php?id=1</a></p>";
} else {
    echo "✅ Expense ID: " . htmlspecialchars($expense_id) . "</p>";
}

// Step 4: Check if expense exists
if ($expense_id && isset($conn)) {
    echo "<p><strong>Step 4 - Fetch Expense Data:</strong><br>";
    
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    if (!$stmt) {
        echo "❌ Database prepare failed: " . $conn->error . "</p>";
    } else {
        $stmt->bind_param("i", $expense_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $expense = $result->fetch_assoc();
        
        if (!$expense) {
            echo "❌ Expense not found with ID: $expense_id</p>";
            
            // Show available expenses
            $allExpenses = $conn->query("SELECT id, category, amount, expense_date FROM expenses LIMIT 5");
            if ($allExpenses && $allExpenses->num_rows > 0) {
                echo "<p><strong>Available expenses:</strong><br>";
                while ($row = $allExpenses->fetch_assoc()) {
                    echo "ID: {$row['id']} - {$row['category']} - ₹{$row['amount']} - {$row['expense_date']} ";
                    echo "<a href='edit_expense_debug.php?id={$row['id']}'>[Test]</a><br>";
                }
                echo "</p>";
            }
        } else {
            echo "✅ Expense found:</p>";
            echo "<ul>";
            echo "<li>ID: " . htmlspecialchars($expense['id']) . "</li>";
            echo "<li>Category: " . htmlspecialchars($expense['category'] ?? 'N/A') . "</li>";
            echo "<li>Amount: ₹" . number_format($expense['amount'] ?? 0, 2) . "</li>";
            echo "<li>Date: " . htmlspecialchars($expense['expense_date'] ?? 'N/A') . "</li>";
            echo "<li>Description: " . htmlspecialchars($expense['description'] ?? $expense['note'] ?? 'N/A') . "</li>";
            echo "</ul>";
        }
    }
}

// Step 5: Check layout files
echo "<p><strong>Step 5 - Layout Files:</strong><br>";
$layoutFiles = ['layouts/header.php', 'layouts/sidebar.php', 'layouts/footer.php'];
foreach ($layoutFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}
echo "</p>";

// Step 6: Test form rendering
if ($expense_id && isset($expense) && $expense) {
    echo "<p><strong>Step 6 - Test Form Rendering:</strong></p>";
    echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0;'>";
    echo "<h4>Simple Edit Form Test</h4>";
    echo "<form method='POST' action='edit_expense_debug.php?id=$expense_id'>";
    echo "<p>Date: <input type='date' name='expense_date' value='" . htmlspecialchars($expense['expense_date'] ?? '') . "'></p>";
    echo "<p>Category: <input type='text' name='category' value='" . htmlspecialchars($expense['category'] ?? '') . "'></p>";
    echo "<p>Amount: <input type='number' step='0.01' name='amount' value='" . htmlspecialchars($expense['amount'] ?? '') . "'></p>";
    echo "<p>Description: <textarea name='description'>" . htmlspecialchars($expense['description'] ?? $expense['note'] ?? '') . "</textarea></p>";
    echo "<p><button type='submit'>Test Update</button></p>";
    echo "</form>";
    echo "</div>";
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<p><strong>Form Submitted:</strong><br>";
        echo "Date: " . htmlspecialchars($_POST['expense_date'] ?? 'N/A') . "<br>";
        echo "Category: " . htmlspecialchars($_POST['category'] ?? 'N/A') . "<br>";
        echo "Amount: " . htmlspecialchars($_POST['amount'] ?? 'N/A') . "<br>";
        echo "Description: " . htmlspecialchars($_POST['description'] ?? 'N/A') . "</p>";
        
        // Try to update
        if (isset($conn)) {
            $date = $_POST['expense_date'];
            $category = $_POST['category'];
            $amount = $_POST['amount'];
            $description = $_POST['description'];
            
            $updateStmt = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, description = ? WHERE id = ?");
            if (!$updateStmt) {
                // Try with note instead of description
                $updateStmt = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, note = ? WHERE id = ?");
            }
            
            if ($updateStmt) {
                $updateStmt->bind_param("ssdsi", $date, $category, $amount, $description, $expense_id);
                if ($updateStmt->execute()) {
                    echo "<p>✅ <strong>Update successful!</strong></p>";
                } else {
                    echo "<p>❌ Update failed: " . $updateStmt->error . "</p>";
                }
            } else {
                echo "<p>❌ Could not prepare update statement: " . $conn->error . "</p>";
            }
        }
    }
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li><a href='expense_history.php'>Go back to expense history</a></li>";
echo "<li><a href='edit_expense.php?id=$expense_id'>Try original edit_expense.php</a></li>";
echo "<li><a href='test_edit_expense.php'>Run general tests</a></li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; }
.error { color: red; }
.success { color: green; }
</style>