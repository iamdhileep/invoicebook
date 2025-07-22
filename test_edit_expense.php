<?php
// Simple test to check edit_expense.php functionality
session_start();

echo "<h3>Testing edit_expense.php</h3>";

// Check if we can include the database
try {
    include 'db.php';
    echo "✅ Database connection: OK<br>";
} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "<br>";
}

// Check if we can access with a test ID
echo "<br><strong>Testing Links:</strong><br>";
echo "<a href='edit_expense.php?id=1' target='_blank'>Test edit_expense.php?id=1</a><br>";
echo "<a href='expense_history.php' target='_blank'>Go to expense_history.php</a><br>";

// Check if session is working
echo "<br><strong>Session Status:</strong><br>";
if (isset($_SESSION['admin'])) {
    echo "✅ Admin session: Active<br>";
} else {
    echo "❌ Admin session: Not set<br>";
    echo "<a href='login.php'>Login first</a><br>";
}

// Check if expense ID is provided
$expense_id = $_GET['id'] ?? null;
if ($expense_id) {
    echo "<br><strong>Expense ID:</strong> " . htmlspecialchars($expense_id) . "<br>";
    
    // Try to fetch expense data
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $expense_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $expense = $result->fetch_assoc();
            
            if ($expense) {
                echo "✅ Expense found: " . htmlspecialchars($expense['category'] ?? 'N/A') . "<br>";
                echo "Amount: ₹" . number_format($expense['amount'] ?? 0, 2) . "<br>";
            } else {
                echo "❌ Expense not found with ID: $expense_id<br>";
            }
        } else {
            echo "❌ Database query failed: " . $conn->error . "<br>";
        }
    }
} else {
    echo "<br>❌ No expense ID provided<br>";
}

// Check if layout files exist
echo "<br><strong>Layout Files:</strong><br>";
echo file_exists('layouts/header.php') ? "✅ header.php: Found<br>" : "❌ header.php: Missing<br>";
echo file_exists('layouts/sidebar.php') ? "✅ sidebar.php: Found<br>" : "❌ sidebar.php: Missing<br>";
echo file_exists('layouts/footer.php') ? "✅ footer.php: Found<br>" : "❌ footer.php: Missing<br>";

// Check uploads directory
echo "<br><strong>Upload Directory:</strong><br>";
echo is_dir('uploads/expenses') ? "✅ uploads/expenses: Exists<br>" : "❌ uploads/expenses: Missing<br>";
echo file_exists('uploads/expenses/.htaccess') ? "✅ .htaccess: Found<br>" : "❌ .htaccess: Missing<br>";

?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
</style>