<?php
// Simple test to verify delete endpoint accessibility
echo "<h3>Delete Endpoint Test</h3>";

// Test 1: Check if file exists
$deleteFile = 'delete_employee.php';
echo "<p><strong>Test 1:</strong> delete_employee.php exists: " . (file_exists($deleteFile) ? "✅ YES" : "❌ NO") . "</p>";

// Test 2: Check session
session_start();
echo "<p><strong>Test 2:</strong> Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Active" : "❌ Inactive") . "</p>";
echo "<p><strong>Admin session:</strong> " . (isset($_SESSION['admin']) ? "✅ Set (" . $_SESSION['admin'] . ")" : "❌ Not set") . "</p>";

// Test 3: Check database connection
include 'db.php';
echo "<p><strong>Test 3:</strong> Database connection: " . ($conn ? "✅ Connected" : "❌ Failed") . "</p>";

// Test 4: Check if employees table exists and has data
if ($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM employees");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Test 4:</strong> Employees in database: " . $row['count'] . "</p>";
    } else {
        echo "<p><strong>Test 4:</strong> ❌ Error querying employees: " . $conn->error . "</p>";
    }
}

// Test 5: Simulate a delete request
echo "<h4>Simulate Delete Request:</h4>";
if ($_POST['test_id'] ?? false) {
    $testId = intval($_POST['test_id']);
    echo "<p>Received test ID: $testId</p>";
    
    // Check if employee exists
    $stmt = $conn->prepare("SELECT name FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $testId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $emp = $result->fetch_assoc();
        echo "<p>✅ Employee found: " . htmlspecialchars($emp['name']) . "</p>";
        echo "<p>🔧 Would delete this employee (test mode - not actually deleting)</p>";
    } else {
        echo "<p>❌ Employee not found with ID: $testId</p>";
    }
}

// Test form
echo '<form method="POST">
    <label>Test Employee ID: <input type="number" name="test_id" value="1"></label>
    <button type="submit">Test Delete Endpoint</button>
</form>';

echo "<hr>";
echo "<p><strong>Path info:</strong></p>";
echo "<p>Current directory: " . getcwd() . "</p>";
echo "<p>Script name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
?>